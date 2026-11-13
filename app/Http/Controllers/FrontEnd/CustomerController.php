<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Models\Admin;
use App\Models\BasicSettings\Basic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Rules\MatchEmailRule;
use App\Rules\ImageMimeTypeRule;
use App\Models\BasicSettings\MailTemplate;
use App\Models\Customer;
use App\Models\Event\Booking;
use App\Models\Event\Wishlist;
use App\Models\Language;
use App\Rules\MatchOldPasswordRule;
use App\Services\PasswordResetService;
use App\Services\PasswordPolicy;
use App\Services\AuditLogger;
use App\Services\CustomerEmailVerificationService;
use App\Services\CustomerLoginCodeService;
use App\Services\GuestTicketClaimService;
use App\Services\ApplicationMailService;
use App\Services\AccountMfaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class CustomerController extends Controller
{
  private $admin_user_name;
  public function __construct()
  {
    $this->middleware(function ($request, $next) {
      $this->admin_user_name = optional(Admin::select('username')->first())->username;
      return $next($request);
    });
  }

  //index
  public function index()
  {
    // The TICA design's account page replaces the legacy customer dashboard.
    session()->reflash();
    return redirect('/account');
  }
  //signup
  public function signup()
  {
    session()->reflash();
    return redirect('/signup');
  }
  //create
  public function create(Request $request)
  {
    $request->merge([
      'fname' => trim((string) $request->input('fname')),
      'lname' => trim((string) $request->input('lname')),
      'username' => trim((string) $request->input('username')),
      'email' => mb_strtolower(trim((string) $request->input('email'))),
    ]);

    $rules = [
      'fname' => 'required',
      'lname' => 'required',
      'email' => 'required|email|unique:customers',
      'username' => [
        'required',
        'alpha_dash',
        "not_in:$this->admin_user_name",
        Rule::unique('customers', 'username')
      ],
      'password' => ['required', 'confirmed', PasswordPolicy::rule()],
    ];

    $info = Basic::select('google_recaptcha_status')->first();
    if ($info->google_recaptcha_status == 1) {
      $rules['g-recaptcha-response'] = 'required|captcha';
    }

    $messages = [];

    if ($info->google_recaptcha_status == 1) {
      $messages['g-recaptcha-response.required'] = 'Please verify that you are not a robot.';
      $messages['g-recaptcha-response.captcha'] = 'Captcha error! try again later or contact site admin.';
    }

    $validated = $request->validate($rules, $messages);

    $customer = Customer::create([
      'fname' => trim((string) $validated['fname']),
      'lname' => trim((string) $validated['lname']),
      'username' => trim((string) $validated['username']),
      'email' => mb_strtolower(trim((string) $validated['email'])),
      'password' => Hash::make((string) $validated['password']),
      'verification_token' => null,
    ]);

    try {
      app(CustomerEmailVerificationService::class)->request($customer);
      Session::flash('success', 'A verification email has been queued for delivery.');
    } catch (\Throwable $exception) {
      report($exception);
      Session::flash('alert', 'Your account was created, but the verification email could not be queued. Please request a new link.');
    }

    return redirect()->route('customer.login');
  }

  public function verify_email()
  {
    return view('frontend.customer.verify-email');
  }

  public function send_verification_link(Request $request, CustomerEmailVerificationService $verifications)
  {
    $request->validate(['email' => 'required|email:rfc']);

    try {
      $customer = Customer::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($request->email))])->first();
      if ($customer && !$customer->email_verified_at) {
        $verifications->request($customer);
      }
    } catch (\Throwable $exception) {
      report($exception);
    }

    return back()->with('success', 'If an unverified account matches that address, a verification link will be sent.');
  }

  public function signupVerify(
    Request $request,
    int $customer,
    CustomerEmailVerificationService $verifications
  ) {
    $verified = $verifications->verify(
      $customer,
      (string) $request->query('delivery'),
      (string) $request->query('email')
    );

    Session::flash('success', 'Your email has been verified.');
    Auth::guard('customer')->login($verified);
    $request->session()->regenerate();
    $this->claimPendingBooking($request, $verified);

    $redirectURL = $this->safeCustomerRedirect($request->session()->pull('redirectTo'));

    return $redirectURL ? redirect($redirectURL) : redirect()->route('customer.dashboard');
  }

  //login
  public function login(Request $request)
  {
    if ($request->input('redirectPath') == 'event_checkout') {
      $basic = Basic::select('event_guest_checkout_status')->first();
      $event_guest_checkout_status = $basic->event_guest_checkout_status;
      if ($event_guest_checkout_status != 1) {
        $url = url()->previous();
      } else {
        $url = route('check-out');
      }
    }
    if ($request->input('redirectPath') == 'checkout') {
      $url = url()->previous();
    }

    // when user have to redirect to course details page after login.
    if (isset($url)) {
      $request->session()->put('redirectTo', $url);
    }
    // The TICA design's log in page replaces the legacy view.
    $request->session()->reflash();
    return redirect('/login');
  }


  //authenticate
  public function authentication(Request $request, AccountMfaService $mfa)
  {
    // at first, get the url from session which will be redirect after login
    if ($request->session()->has('redirectTo')) {
      $redirectURL = $request->session()->get('redirectTo');
    } else {
      $redirectURL = null;
    }
    $rules = [
      'username' => 'required',
      'password' => 'required'
    ];

    $info = Basic::select('google_recaptcha_status')->first();
    if ($info->google_recaptcha_status == 1) {
      $rules['g-recaptcha-response'] = 'required|captcha';
    }

    $messages = [];

    if ($info->google_recaptcha_status == 1) {
      $messages['g-recaptcha-response.required'] = 'Please verify that you are not a robot.';
      $messages['g-recaptcha-response.captcha'] = 'Captcha error! try again later or contact site admin.';
    }

    $validator = Validator::make($request->all(), $rules, $messages);


    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    if (
      Auth::guard('customer')->attempt([
        'username' => $request->username,
        'password' => $request->password,
        'status' => 1,
      ])
    ) {
      $authAdmin = Auth::guard('customer')->user();

      // check whether the admin's account is active or not
      if ($authAdmin->status == 0) {
        Session::flash('alert', 'Sorry, your account has been deactivated!');
        // logout auth admin as condition not satisfied
        Auth::guard('customer')->logout();

        return redirect()->back();
      } elseif ($authAdmin->email_verified_at == null) {
        Session::flash('alert', 'Sorry, Please verify your email address !');
        // logout auth admin as condition not satisfied
        Auth::guard('customer')->logout();

        return redirect()->back();
      } else {
        if ($mfa->enabled($authAdmin)) {
          $target = $this->safeCustomerRedirect($redirectURL) ?: '/account';
          $mfa->beginLogin($request, 'customer', $authAdmin, $target);
          return redirect()->route('mfa.login.challenge');
        }
        $request->session()->regenerate();
        $this->claimPendingBooking($request, $authAdmin);
        // otherwise, redirect auth user to next url
        if ($redirectURL == null) {
          return redirect()->route('customer.dashboard');
        } else {
          // before, redirect to next url forget the session value
          $request->session()->forget('redirectTo');

          return redirect($this->safeCustomerRedirect($redirectURL) ?: route('customer.dashboard'));
        }
      }
    } else {
      return redirect()->back()->with('alert', 'Oops, Username or password does not match!');
    }
  }

  public function requestLoginCode(Request $request, CustomerLoginCodeService $codes)
  {
    $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
    $codes->request($validated['email']);
    return back()->with('success', 'If an active account matches that email, a six-digit code has been sent.');
  }

  public function verifyLoginCode(Request $request, CustomerLoginCodeService $codes)
  {
    $validated = $request->validate([
      'email' => ['required', 'email:rfc', 'max:255'],
      'code' => ['required', 'digits:6'],
    ]);
    $customer = $codes->consume($validated['email'], $validated['code']);
    if ($customer->two_factor_enabled_at !== null) {
      return back()->with('alert', 'This account uses two-step verification. Sign in with your password first.');
    }
    Auth::guard('customer')->login($customer);
    $request->session()->regenerate();
    $this->claimPendingBooking($request, $customer);
    return redirect()->intended(route('customer.dashboard'));
  }

  public function claimTickets(Request $request, int $booking)
  {
    if (Auth::guard('customer')->check()) {
      app(GuestTicketClaimService::class)->claim($booking, Auth::guard('customer')->user());
      return redirect()->route('customer.booking_details', $booking)->with('success', 'Tickets saved to your account.');
    }
    $request->session()->put('claim_booking_id', $booking);
    $request->session()->put('url.intended', route('customer.booking_details', $booking));
    return redirect()->route('customer.login')->with('success', 'Sign in or create an account with the booking email to save these tickets.');
  }

  private function claimPendingBooking(Request $request, Customer $customer): void
  {
    $bookingId = (int) $request->session()->pull('claim_booking_id', 0);
    if ($bookingId > 0) app(GuestTicketClaimService::class)->claim($bookingId, $customer);
  }
  //forget_passord
  public function forget_passord()
  {
    session()->reflash();
    return redirect('/forgot');
  }
  //forget_mail
  public function forget_mail(Request $request)
  {
    $rules = [
      'email' => [
        'required',
        'email:rfc,dns',
        new MatchEmailRule('customer')
      ]
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator)->withInput();
    }

    $user = Customer::where('email', $request->email)->firstOrFail();

    // first, get the mail template information from db
    $mailTemplate = MailTemplate::where('mail_type', 'reset_password')->first();
    $mailSubject = $mailTemplate->mail_subject;
    $mailBody = $mailTemplate->mail_body;

    // second, send a password reset link to user via the configured transport
    $info = DB::table('basic_settings')
      ->select('website_title')
      ->first();

    $name = $user->username ?: trim($user->fname . ' ' . $user->lname);
    $token = (new PasswordResetService())->issue($user);

    $link = '<a href="' . e(route('customer.reset.password', ['token' => $token, 'email' => $user->email])) . '">Click Here</a>';

    $mailBody = str_replace('{customer_name}', e($name), $mailBody);
    $mailBody = str_replace('{password_reset_link}', $link, $mailBody);
    $mailBody = str_replace('{website_title}', e((string) $info->website_title), $mailBody);

    try {
      app(ApplicationMailService::class)->queueHtml(
        'customer_password_reset', $request->email, $mailSubject, $mailBody, [], null, $user
      );

      Session::flash('success', 'A password reset email has been queued.');
    } catch (\Exception $e) {
      Session::flash('error', 'The reset email could not be queued.');
    }

    return redirect()->back();
  }
  //reset_password
  public function reset_password(Request $request)
  {
    return view('frontend.customer.reset-password');
  }
  //update_password
  public function update_password(Request $request)
  {
    $request->validate([
      'password' => ['required', 'confirmed', PasswordPolicy::rule()],
      'token' => 'required',
      'email' => 'required|email',
    ]);
    (new PasswordResetService())->consume(
      Customer::class,
      $request->email,
      $request->token,
      $request->password
    );
    Session::flash('success', 'Your password has been reset.');

    return redirect()->route('customer.login');
  }
  public function logout(Request $request)
  {
    Auth::guard('customer')->logout();
    Session::forget(['secret_login', 'impersonator_admin_id', 'impersonation_started_at']);
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('customer.login');
  }
  //change_password
  public function change_password()
  {
    return view('frontend.customer.dashboard.change-password');
  }
  //update_password
  public function updated_password(Request $request)
  {
    $rules = [
      'current_password' => [
        'required',
        new MatchOldPasswordRule('customer')

      ],
      'new_password' => ['required', 'confirmed', PasswordPolicy::rule()],
      'new_password_confirmation' => 'required'
    ];

    $messages = [
      'new_password.confirmed' => 'Password confirmation does not match.',
      'new_password_confirmation.required' => 'The confirm new password field is required.'
    ];

    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      // The account page sends this in the background and shows the errors itself.
      if ($request->expectsJson()) {
        return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
      }
      return redirect()->back()->withErrors($validator)->withInput();
    }

    $customer = Auth::guard('customer')->user();

    $customer->update([
      'password' => Hash::make($request->new_password)
    ]);
    AuditLogger::record('customer.password_changed', $customer);

    if ($request->expectsJson()) {
      return response()->json(['message' => 'Password updated successfully!']);
    }

    Session::flash('success', 'Password updated successfully!');

    return back();
  }
  //edit_profile
  public function edit_profile()
  {
    return view('frontend.customer.dashboard.edit-profile');
  }
  //update_profile
  public function update_profile(Request $request, CustomerEmailVerificationService $verifications)
  {
    $customer = Auth::guard('customer')->user();
    $requestedEmail = mb_strtolower(trim((string) $request->input('email')));
    $request->merge([
      'fname' => trim((string) $request->input('fname')),
      'lname' => trim((string) $request->input('lname')),
      'username' => trim((string) $request->input('username')),
      'email' => $requestedEmail,
    ]);
    $emailChanged = !hash_equals(
      mb_strtolower(trim((string) $customer->email)),
      $requestedEmail
    );

    $validated = $request->validate([
      'fname' => 'required|string|max:100',
      'lname' => 'required|string|max:100',
      'email' => [
        'required',
        'string',
        'email:rfc',
        'max:254',
        Rule::unique('customers', 'email')->ignore($customer->id)
      ],
      'username' => [
        'required',
        'string',
        'alpha_dash',
        'max:100',
        "not_in:$this->admin_user_name",
        Rule::unique('customers', 'username')->ignore($customer->id)
      ],
      'phone' => 'nullable|string|max:40',
      'country' => 'nullable|string|max:100',
      'state' => 'nullable|string|max:100',
      'city' => 'nullable|string|max:100',
      'zip_code' => 'nullable|string|max:30',
      'address' => 'nullable|string|max:500',
      'photo' => ['nullable', 'file', new ImageMimeTypeRule()],
      'current_password' => $emailChanged
        ? ['required', new MatchOldPasswordRule('customer')]
        : ['nullable'],
    ]);

    $profile = [
      'fname' => trim((string) $validated['fname']),
      'lname' => trim((string) $validated['lname']),
      'email' => $requestedEmail,
      'username' => trim((string) $validated['username']),
      'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
      'country' => trim((string) ($validated['country'] ?? '')) ?: null,
      'state' => trim((string) ($validated['state'] ?? '')) ?: null,
      'city' => trim((string) ($validated['city'] ?? '')) ?: null,
      'zip_code' => trim((string) ($validated['zip_code'] ?? '')) ?: null,
      'address' => trim((string) ($validated['address'] ?? '')) ?: null,
    ];

    $file = $request->file('photo');
    if ($file) {
      $extension = ImageMimeTypeRule::extension($file);
      $directory = public_path('assets/admin/img/customer-profile/');
      $fileName = (string) Str::uuid() . '.' . strtolower($extension);
      UploadFile::storeAs($directory, $file, $fileName);
      UploadFile::delete($directory, (string) $customer->photo);
      $profile['photo'] = $fileName;
    }

    if ($emailChanged) {
      $profile['email_verified_at'] = null;
      $profile['verification_token'] = null;
    }

    $changedFields = array_keys(array_filter(
      $profile,
      fn ($value, $key) => $customer->getAttribute($key) !== $value,
      ARRAY_FILTER_USE_BOTH
    ));
    $customer->forceFill($profile)->save();
    AuditLogger::record('customer.profile_updated', $customer, [], [], [
      'changed_fields' => $changedFields,
      'email_reverification_required' => $emailChanged,
    ]);

    if ($emailChanged) {
      $verificationQueued = true;
      try {
        $verifications->request($customer->fresh());
      } catch (\Throwable $exception) {
        report($exception);
        $verificationQueued = false;
      }
      Auth::guard('customer')->logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();

      return redirect()->route('customer.login')->with(
        $verificationQueued ? 'success' : 'alert',
        $verificationQueued
          ? 'Your email was updated. Verify the new address before signing in again.'
          : 'Your email was updated, but the verification message could not be queued. Request a new link before signing in.'
      );
    }

    Session::flash('success', 'Your profile has been successfully updated!');

    return back();
  }
  //wishlist
  public function wishlist()
  {
    $language = $this->getLanguage();
    $customer_id = Auth::guard('customer')->user()->id;
    $wishlist = Wishlist::where('customer_id', $customer_id)
      ->get();
    return view('frontend.customer.dashboard.wishlist', compact('wishlist'));
  }
  //remove_wishlist
  //add_to_wishlist
  public function remove_wishlist($id)
  {
    if (Auth::guard('customer')->check()) {
      $remove = Wishlist::where([['event_id', $id], ['customer_id', Auth::guard('customer')->user()->id]])->first();
      if ($remove) {
        $remove->delete();
        $notification = array('message' => 'Removed from wishlist successfully..!', 'alert-type' => 'info');
      } else {
        $notification = array('message' => 'Something went wrong', 'alert-type' => 'danger');
      }
      return back()->with($notification);
    } else {
      return redirect()->route('customer.login');
    }
  }

  public function facebookRedirect()
  {
    return Socialite::driver('facebook')->redirect();
  }

  public function handleFacebookCallback()
  {
    return $this->authenticationViaProvider('facebook');
  }

  public function googleRedirect()
  {
    return Socialite::driver('google')->redirect();
  }

  public function handleGoogleCallback()
  {
    return $this->authenticationViaProvider('google');
  }

  public function authenticationViaProvider($driver)
  {
    try {

      $user = Socialite::driver($driver)->user();
      $isUser = Customer::where('provider_id', $user->id)->first();
      // Someone who signed up with email and later uses "Continue with Google" with the
      // same, Google-verified address gets their existing account, not a duplicate.
      $verifiedEmail = $driver === 'google' && (bool) data_get($user->user, 'email_verified', data_get($user->user, 'verified_email', false));
      if (!$isUser && $verifiedEmail && filled($user->getEmail())) {
        $isUser = Customer::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $user->getEmail()))])->first();
        if ($isUser) {
          $isUser->forceFill(['provider' => $driver, 'provider_id' => $user->id, 'email_verified_at' => $isUser->email_verified_at ?: now()])->save();
        }
      }

      if ($isUser) {
        if ((int) $isUser->status !== 1) {
          return redirect()->route('customer.login')
            ->with('alert', 'Sorry, your account has been deactivated!');
        }

        $redirectURL = $this->safeCustomerRedirect(request()->session()->pull('redirectTo'));
        if (app(AccountMfaService::class)->enabled($isUser)) {
          Auth::guard('customer')->login($isUser);
          app(AccountMfaService::class)->beginLogin(request(), 'customer', $isUser, $redirectURL ?: '/account');
          return redirect()->route('mfa.login.challenge');
        }
        Auth::guard('customer')->login($isUser);
        request()->session()->regenerate();
        return $redirectURL ? redirect($redirectURL) : redirect()->route('customer.dashboard');
      } else {
        // Store the provider avatar in the same durable object store used by
        // regular profile uploads. Vercel's bundled public directory is read-only.
        // The profile photo is a nice-to-have: a failed download never blocks signing in.
        $avatarName = rescue(function () use ($user, $driver) {
          $avatarResponse = Http::accept('image/*')->timeout(12)->get((string) $user->getAvatar());
          if (!$avatarResponse->successful()) {
            return null;
          }
          $contentType = strtolower(trim(strtok((string) $avatarResponse->header('Content-Type'), ';') ?: ''));
          $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$contentType] ?? null;
          $fileContents = $avatarResponse->body();
          if (!$extension || $fileContents === '' || strlen($fileContents) > 5 * 1024 * 1024) {
            return null;
          }
          $name = hash('sha256', $driver . '|' . $user->getId()) . '.' . $extension;
          UploadFile::storeContents(public_path('assets/admin/img/customer-profile/'), $fileContents, $name, $contentType);

          return $name;
        }, null, false);

        $createUser = Customer::create([
          'photo' => $avatarName,
          'fname' => $user->name,
          'email' => $user->email,
          'username' => $user->id,
          'provider' => $driver,
          'provider_id' => $user->id,
          'password' => Hash::make(Str::random(40)),
          'email_verified_at' => now()
        ]);

        Auth::guard('customer')->login($createUser);
        request()->session()->regenerate();
        $redirectURL = $this->safeCustomerRedirect(request()->session()->pull('redirectTo'));
        return $redirectURL ? redirect($redirectURL) : redirect()->route('customer.dashboard');
      }
    } catch (Throwable $e) {
      Log::warning('customer.social_authentication_failed', [
        'provider' => $driver,
        'exception' => $e::class,
      ]);

      return redirect()->route('customer.login')
        ->with('alert', 'Social sign-in could not be completed. Please try again.');
    }
  }

  private function safeCustomerRedirect(?string $url): ?string
  {
    if (!$url) {
      return null;
    }

    if (Str::startsWith($url, '/') && !Str::startsWith($url, '//')) {
      return $url;
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host']) || strcasecmp($parts['host'], request()->getHost()) !== 0) {
      return null;
    }

    if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
      return null;
    }

    return $url;
  }
}
