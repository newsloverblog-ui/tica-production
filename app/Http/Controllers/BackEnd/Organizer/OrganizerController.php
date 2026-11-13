<?php

namespace App\Http\Controllers\BackEnd\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Models\Admin;
use App\Models\BasicSettings\Basic;
use App\Models\Organizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Rules\MatchEmailRule;
use App\Rules\ImageMimeTypeRule;
use App\Models\BasicSettings\MailTemplate;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\BookingTicket;
use App\Models\Language;
use App\Models\OrganizerInfo;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Models\ScannerDevice;
use App\Rules\MatchOldPasswordRule;
use App\Services\AuditLogger;
use App\Services\PasswordResetService;
use App\Services\PasswordPolicy;
use App\Services\OrganizerEmailVerificationService;
use App\Services\ScannerConflictService;
use App\Services\ApplicationMailService;
use App\Services\AccountMfaService;
use App\Services\TransactionExportQueryService;
use App\Services\TicketIssuanceService;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class OrganizerController extends Controller
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
    // The TICA design's organiser workspace replaces the legacy dashboard.
    if (!request()->boolean('legacy')) {
      session()->reflash();
      return redirect('/dashboard');
    }
    $information['total_events'] = Event::where('organizer_id', Auth::guard('organizer')->user()->id)->get()->count();
    $information['total_event_bookings'] = Booking::where('organizer_id', Auth::guard('organizer')->user()->id)->get()->count();
    $information['transcation_count'] = Transaction::where('organizer_id', Auth::guard('organizer')->user()->id)->get()->count();
    $information['today_sales'] = (float) Booking::query()
      ->where('organizer_id', Auth::guard('organizer')->user()->id)
      ->where('paymentStatus', 'completed')
      ->whereDate('created_at', today())
      ->selectRaw('COALESCE(SUM(price + COALESCE(tax, 0)), 0) as aggregate')
      ->value('aggregate');
    $information['today_bookings'] = Booking::query()
      ->where('organizer_id', Auth::guard('organizer')->user()->id)
      ->where('paymentStatus', 'completed')
      ->whereDate('created_at', today())
      ->count();
    $information['pending_payouts'] = Withdraw::query()
      ->where('organizer_id', Auth::guard('organizer')->user()->id)
      ->whereIn('status', [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD])
      ->count();

    //income of event bookings
    $eventBookingTotalIncomes = DB::table('bookings')
      ->select(DB::raw('month(created_at) as month'), DB::raw('sum(price) as total'))
      ->where('paymentStatus', '=', 'completed')
      ->groupBy('month')
      ->whereYear('created_at', '=', date('Y'))
      ->where('organizer_id', Auth::guard('organizer')->user()->id)
      ->get();

    $TotalEventBookings = DB::table('bookings')
      ->select(DB::raw('month(created_at) as month'), DB::raw('count(id) as total'))
      ->where('paymentStatus', '=', 'completed')
      ->groupBy('month')
      ->whereYear('created_at', '=', date('Y'))
      ->where('organizer_id', Auth::guard('organizer')->user()->id)
      ->get();

    $eventMonths   = [];
    $eventIncomes  = [];
    $totalBookings = [];

    for ($i = 1; $i <= 12; $i++) {
      $monthNum  = $i;
      $dateObj   = DateTime::createFromFormat('!m', $monthNum);
      $monthName = $dateObj->format('M');
      array_push($eventMonths, $monthName);

      $incomeFound = false;
      foreach ($eventBookingTotalIncomes as $eventIncomeInfo) {
        if ($eventIncomeInfo->month == $i) {
          $incomeFound = true;
          array_push($eventIncomes, $eventIncomeInfo->total);
          break;
        }
      }
      if ($incomeFound == false) {
        array_push($eventIncomes, 0);
      }

      $bookingFound = false;
      foreach ($TotalEventBookings as $eventInfo) {
        if ($eventInfo->month == $i) {
          $bookingFound = true;
          array_push($totalBookings, $eventInfo->total);
          break;
        }
      }
      if ($bookingFound == false) {
        array_push($totalBookings, 0);
      }
    }

    $information['eventIncomes']  = $eventIncomes;
    $information['eventMonths']   = $eventMonths;
    $information['totalBookings'] = $totalBookings;
    $information['admin_setting'] = DB::table('basic_settings')->where('uniqid', 12345)->select('organizer_admin_approval', 'admin_approval_notice')->first();

    return view('organizer.index', $information);
  }

  //login
  // The TICA design's account pages replace the legacy organiser views.
  public function login()
  {
    session()->reflash();
    return redirect('/login?role=org');
  }

  //signup
  public function signup()
  {
    session()->reflash();
    return redirect('/signup?role=org');
  }

  //create — organizer registration
  public function create(Request $request)
  {
    $request->merge([
      'name' => trim((string) $request->input('name')),
      'username' => trim((string) $request->input('username')),
      'email' => mb_strtolower(trim((string) $request->input('email'))),
    ]);
    $rules = [
      'name'     => 'required|string|max:100',
      'username' => [
        'required',
        'string',
        'alpha_dash',
        'max:100',
        'unique:organizers',
        "not_in:$this->admin_user_name"
      ],
      'email'    => 'required|string|email:rfc|max:254|unique:organizers',
      'password' => ['required', 'confirmed', PasswordPolicy::rule()],
    ];

    $info = Basic::select('google_recaptcha_status')->first();
    if ($info->google_recaptcha_status == 1) {
      $rules['g-recaptcha-response'] = 'required|captcha';
    }

    $messages = [];
    if ($info->google_recaptcha_status == 1) {
      $messages['g-recaptcha-response.required'] = 'Please verify that you are not a robot.';
      $messages['g-recaptcha-response.captcha']  = 'Captcha error! try again later or contact site admin.';
    }

    $validator = Validator::make($request->all(), $rules, $messages);
    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    $validated = $validator->validated();
    $setting = DB::table('basic_settings')->where('uniqid', 12345)->select('organizer_email_verification', 'organizer_admin_approval')->first();
    $requiresEmailVerification = (int) $setting->organizer_email_verification === 1;
    $requiresAdminApproval = (int) $setting->organizer_admin_approval === 1;
    $status = ($requiresEmailVerification || $requiresAdminApproval) ? 0 : 1;

    if (!$requiresEmailVerification) {
      Session::flash('success', 'Sign up successfully completed. Please Login Now');
    }

    $organizer = Organizer::create([
      'username' => $validated['username'],
      'email' => $validated['email'],
      'password' => Hash::make((string) $validated['password']),
      'status' => $status,
    ]);
    $language       = $this->getLanguage();
    OrganizerInfo::create([
      'organizer_id' => $organizer->id,
      'language_id' => $language->id,
      'name' => $validated['name'],
    ]);

    if ($requiresEmailVerification) {
      try {
        app(OrganizerEmailVerificationService::class)->request($organizer);
        Session::flash('success', 'Your account was created. Check your email for the expiring verification link.');
      } catch (\Throwable $exception) {
        report($exception);
        Session::flash('warning', 'Your account was created, but verification delivery is delayed. Use the verification resend page to try again.');
      }
    }

    return redirect()->route('organizer.login');
  }

  //authenticate
  public function authentication(Request $request, AccountMfaService $mfa)
  {
    $rules = [
      'username' => 'required',
      'password' => 'required',
    ];

    $info = Basic::select('google_recaptcha_status')->first();
    if ($info->google_recaptcha_status == 1) {
      $rules['g-recaptcha-response'] = 'required|captcha';
    }

    $messages = [];
    if ($info->google_recaptcha_status == 1) {
      $messages['g-recaptcha-response.required'] = 'Please verify that you are not a robot.';
      $messages['g-recaptcha-response.captcha']  = 'Captcha error! try again later or contact site admin.';
    }

    $validator = Validator::make($request->all(), $rules, $messages);
    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    if (Auth::guard('organizer')->attempt([
      'username' => $request->username,
      'password' => $request->password,
      'status' => 1,
    ])) {
      $authAdmin = Auth::guard('organizer')->user();
      $setting   = DB::table('basic_settings')->where('uniqid', 12345)->select('organizer_email_verification', 'organizer_admin_approval')->first();

      if ($setting->organizer_email_verification == 1 && $authAdmin->email_verified_at == NULL) {
        Session::flash('alert', 'Please Verify Your Email Address!');
        Auth::guard('organizer')->logout();
        return redirect()->back();
      } else {
        if (app(\App\Services\TwoFactorAuthenticator::class)->enabled($authAdmin)) {
          app(\App\Services\TwoFactorAuthenticator::class)->beginLogin($request, $authAdmin, '/dashboard');
          return redirect()->route('totp.login.challenge');
        }
        if ($mfa->enabled($authAdmin)) {
          $mfa->beginLogin($request, 'organizer', $authAdmin, '/dashboard');
          return redirect()->route('mfa.login.challenge');
        }
        Auth::guard('scanner')->logout();
        $request->session()->regenerate();
        Session::put('secret_login', 0);
        return redirect()->route('organizer.dashboard');
      }
    } else {
      return redirect()->back()->with('alert', 'Oops, Username or password does not match!');
    }
  }

  //forget_passord
  public function forget_passord()
  {
    session()->reflash();
    return redirect('/forgot?role=org');
  }

  //forget_mail — sends password reset link
  public function forget_mail(Request $request)
  {
    $rules = [
      'email' => [
        'required',
        'email:rfc,dns',
        new MatchEmailRule('organizer'),
      ],
    ];

    $validator = Validator::make($request->all(), $rules);
    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator)->withInput();
    }

    $user         = Organizer::where('email', $request->email)->first();
    $mailTemplate = MailTemplate::where('mail_type', 'reset_password')->first();
    $mailSubject  = $mailTemplate->mail_subject;
    $mailBody     = $mailTemplate->mail_body;

    $basicInfo = DB::table('basic_settings')
      ->select('website_title')
      ->first();

    $token = (new PasswordResetService())->issue($user);

    $link = '<a href="' . e(route('organizer.reset.password', ['token' => $token, 'email' => $user->email])) . '">Click Here</a>';

    $mailBody = str_replace('{customer_name}',      e((string) $user->name), $mailBody);
    $mailBody = str_replace('{password_reset_link}', $link,                $mailBody);
    $mailBody = str_replace('{website_title}',       e((string) $basicInfo->website_title), $mailBody);

    try {
      app(ApplicationMailService::class)->queueHtml(
        'organizer_password_reset', $request->email, $mailSubject, $mailBody, [], null, $user
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
    return view('frontend.organizer.reset-password');
  }

  //update_password
  public function update_password(Request $request)
  {
    $request->validate([
      'password' => ['required', 'confirmed', PasswordPolicy::rule()],
      'token'    => 'required',
      'email'    => 'required|email',
    ]);

    (new PasswordResetService())->consume(
      Organizer::class,
      $request->email,
      $request->token,
      $request->password
    );

    Session::flash('success', 'Your password has been reset.');
    return redirect()->route('organizer.login');
  }

  public function logout(Request $request)
  {
    Auth::guard('organizer')->logout();
    Session::forget(['secret_login', 'impersonator_admin_id', 'impersonation_started_at']);
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('organizer.login');
  }

  //change_password
  public function change_password()
  {
    return view('organizer.change-password');
  }

  //updated_password
  public function updated_password(Request $request)
  {
    $rules = [
      'current_password'          => ['required', new MatchOldPasswordRule('organizer')],
      'new_password'              => ['required', 'confirmed', PasswordPolicy::rule()],
      'new_password_confirmation' => 'required',
    ];

    $messages = [
      'new_password.confirmed'              => 'Password confirmation does not match.',
      'new_password_confirmation.required'  => 'The confirm new password field is required.',
    ];

    $validator = Validator::make($request->all(), $rules, $messages);
    if ($validator->fails()) {
      return Response::json(['errors' => $validator->getMessageBag()->toArray()], 400);
    }

    $organizer = Auth::guard('organizer')->user();
    $organizer->update(['password' => Hash::make($request->new_password)]);
    AuditLogger::record('organizer.password_changed', $organizer);

    Session::flash('success', 'Password updated successfully!');
    return response()->json(['status' => 'success'], 200);
  }

  //edit_profile
  public function edit_profile()
  {
    $languages = Language::get();
    return view('organizer.edit-profile', compact('languages'));
  }

  //update_profile
  public function update_profile(Request $request, OrganizerEmailVerificationService $verifications)
  {
    $organizer = Auth::guard('organizer')->user();
    $requestedEmail = mb_strtolower(trim((string) $request->input('email')));
    $request->merge([
      'email' => $requestedEmail,
      'username' => trim((string) $request->input('username')),
    ]);
    $emailChanged = !hash_equals(
      mb_strtolower(trim((string) $organizer->email)),
      $requestedEmail
    );
    $rules = [
      'email'    => [
        'required', 'string', 'email:rfc', 'max:254',
        Rule::unique('organizers', 'email')->ignore($organizer->id),
      ],
      'username' => [
        'required',
        'string',
        'alpha_dash',
        'max:100',
        "not_in:$this->admin_user_name",
        Rule::unique('organizers', 'username')->ignore($organizer->id),
      ],
      'phone' => 'nullable|string|max:40',
      'facebook' => 'nullable|string|max:500',
      'twitter' => 'nullable|string|max:500',
      'linkedin' => 'nullable|string|max:500',
      'current_password' => $emailChanged
        ? ['required', new MatchOldPasswordRule('organizer')]
        : ['nullable'],
    ];

    $languages = Language::get();
    $messages  = [];

    foreach ($languages as $language) {
      $rules[$language->code . '_name']    = 'required|string|max:100';
      $rules[$language->code . '_country'] = 'nullable|string|max:100';
      $rules[$language->code . '_city'] = 'nullable|string|max:100';
      $rules[$language->code . '_state'] = 'nullable|string|max:100';
      $rules[$language->code . '_zip_code'] = 'nullable|string|max:30';
      $rules[$language->code . '_address'] = 'nullable|string|max:500';
      $rules[$language->code . '_details'] = 'nullable|string|max:10000';
      $messages[$language->code . '_name'] = 'The name field is required for ' . $language->name . ' language.';
    }

    if ($request->hasFile('photo')) {
      $rules['photo'] = ['file', 'dimensions:width=300,height=300', new ImageMimeTypeRule()];
    }

    $validator = Validator::make($request->all(), $rules, $messages);
    if ($validator->fails()) {
      return Response::json(['errors' => $validator->getMessageBag()], 400);
    }

    $validated = $validator->validated();
    $profile = [
      'email' => $requestedEmail,
      'username' => $validated['username'],
      'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
      'facebook' => trim((string) ($validated['facebook'] ?? '')) ?: null,
      'twitter' => trim((string) ($validated['twitter'] ?? '')) ?: null,
      'linkedin' => trim((string) ($validated['linkedin'] ?? '')) ?: null,
    ];
    $file      = $request->file('photo');

    if ($file) {
      $extension = ImageMimeTypeRule::extension($file);
      $directory = public_path('assets/admin/img/organizer-photo/');
      $fileName  = (string) Str::uuid() . '.' . strtolower($extension);
      UploadFile::storeAs($directory, $file, $fileName);
      UploadFile::delete($directory, (string) $organizer->photo);
      $profile['photo'] = $fileName;
    }

    if ($emailChanged) {
      $profile['email_verified_at'] = null;
    }

    $changedFields = array_keys(array_filter(
      $profile,
      fn ($value, $key) => $organizer->getAttribute($key) !== $value,
      ARRAY_FILTER_USE_BOTH
    ));
    $organizer->forceFill($profile)->save();

    foreach ($languages as $language) {
      $organizer_info = OrganizerInfo::where('organizer_id', $organizer->id)->where('language_id', $language->id)->first();
      if (!$organizer_info) {
        $organizer_info              = new OrganizerInfo();
        $organizer_info->language_id  = $language->id;
        $organizer_info->organizer_id = $organizer->id;
      }
      $organizer_info->name        = $request[$language->code . '_name'];
      $organizer_info->country     = $request[$language->code . '_country'];
      $organizer_info->city        = $request[$language->code . '_city'];
      $organizer_info->state       = $request[$language->code . '_state'];
      $organizer_info->zip_code    = $request[$language->code . '_zip_code'];
      $organizer_info->address     = $request[$language->code . '_address'];
      $organizer_info->details     = $request[$language->code . '_details'];
      $organizer_info->save();
    }

    AuditLogger::record('organizer.profile_updated', $organizer, [], [], [
      'changed_fields' => $changedFields,
      'email_reverification_required' => $emailChanged,
    ]);

    if ($emailChanged) {
      $verificationQueued = true;
      try {
        $verifications->request($organizer->fresh());
      } catch (\Throwable $exception) {
        report($exception);
        $verificationQueued = false;
      }
      Auth::guard('organizer')->logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();

      return Response::json([
        'status' => 'success',
        'redirect' => route('organizer.login'),
        'message' => $verificationQueued
          ? 'Your email was updated. Verify the new address before signing in again.'
          : 'Your email was updated, but verification delivery is delayed. Request a new link before signing in.',
      ], 200);
    }

    Session::flash('success', 'Updated Successfully');
    return Response::json(['status' => 'success'], 200);
  }

  //verify_email
  public function verify_email()
  {
    return view('frontend.organizer.verify-email');
  }

  //send_link — resend email verification link
  public function send_link(Request $request, OrganizerEmailVerificationService $verifications)
  {
    $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
    $user = Organizer::query()->where('email', mb_strtolower(trim($validated['email'])))->first();
    if ($user && !$user->email_verified_at) {
      try {
        $verifications->request($user);
      } catch (\Throwable $exception) {
        report($exception);
      }
    }

    return back()->with('success', 'If that organizer account requires verification, an email has been queued.');
  }

  //confirm_email
  public function confirm_email(Request $request, $organizer)
  {
    $user = app(OrganizerEmailVerificationService::class)->verify(
      (int) $organizer,
      (string) $request->query('delivery'),
      (string) $request->query('email')
    );

    if ((int) $user->status === 1) {
      Auth::guard('organizer')->login($user);
      $request->session()->regenerate();
      Session::put('secret_login', 0);
      return redirect()->route('organizer.dashboard')->with('success', 'Your email address has been verified.');
    }

    return redirect()->route('organizer.login')
      ->with('success', 'Your email address has been verified. Your account is awaiting administrator approval.');
  }

  //pwa
  public function pwa()
  {
    // Organisers manage scanners inside their dashboard; gate staff keep the standalone workspace.
    return view('organizer.pwa.dashboard');
  }

  //check_qrcode
  public function check_qrcode(Request $request)
  {
    $request->validate([
      'booking_id' => ['required', 'string', 'max:512'],
      'source' => ['sometimes', 'required', Rule::in(['scanner', 'manual', 'offline_sync'])],
    ]);
    $checkInSource = (string) $request->input('source', 'scanner');
    $scannerDevice = $request->attributes->get('scanner_device');
    $scannerStaff = $request->attributes->get('scanner_staff');
    $ticket = verifySignedTicketPayload($request->booking_id);
    if (!$ticket) {
      return response()->json(['alert_type' => 'error', 'message' => 'Invalid or unsigned ticket'], 422);
    }

    return DB::transaction(function () use ($ticket, $request, $scannerDevice, $scannerStaff, $checkInSource) {
      $organizerId = (int) ($request->attributes->get('scanner_organizer_id') ?: Auth::guard('organizer')->id());
      $unit = null;

      if (isset($ticket['ticket_unit_id'])) {
        $unit = BookingTicket::where('public_id', $ticket['ticket_unit_id'])
          ->where('event_id', $ticket['event_id'])
          ->where('organizer_id', $organizerId)
          ->lockForUpdate()
          ->first();

        $booking = $unit
          ? Booking::whereKey($unit->booking_id)->where('organizer_id', $organizerId)->lockForUpdate()->first()
          : null;
      } else {
        $booking = Booking::where('booking_id', $ticket['booking_id'])
          ->where('event_id', $ticket['event_id'])
          ->where('organizer_id', $organizerId)
          ->lockForUpdate()
          ->first();
      }

      if (!$booking) {
        return response()->json(['alert_type' => 'error', 'message' => 'Ticket is not valid for your event'], 404);
      }

      $units = BookingTicket::where('booking_id', $booking->id)
        ->orderBy('sequence')
        ->lockForUpdate()
        ->get();
      $unitIntegrityIssues = app(TicketIssuanceService::class)
        ->integrityIssues($booking, $units);
      if ($unitIntegrityIssues !== []) {
        return response()->json([
          'alert_type' => 'error',
          'code' => 'ticket_units_inconsistent',
          'message' => 'Ticket setup is incomplete. Ask a supervisor to resolve this booking before entry.',
          'booking_id' => $booking->booking_id,
        ], 409);
      }

      if (!isset($ticket['ticket_unit_id'])) {
        if ($units->count() > 1) {
          return response()->json([
            'alert_type' => 'error',
            'message' => 'This booking contains individual tickets. Scan the QR for the attendee entering.',
            'booking_id' => $booking->booking_id,
            'ticket_count' => $units->count(),
          ], 422);
        }
        $unit = $units->first();
      }

      if (!in_array((string) $booking->paymentStatus, ['completed', 'free', '1'], true)) {
        return response()->json(['alert_type' => 'error', 'message' => 'Payment is not complete', 'booking_id' => $booking->booking_id], 422);
      }
      if ($booking->refund_status === 'refunded') {
        return response()->json(['alert_type' => 'error', 'message' => 'Ticket has been fully refunded', 'booking_id' => $booking->booking_id], 422);
      }

      if ($unit) {
        if ($unit->status === 'checked_in') {
          $originalDevice = $unit->checked_in_scanner_device_id
            ? ScannerDevice::find($unit->checked_in_scanner_device_id)
            : null;
          $conflict = null;
          if ($originalDevice && $scannerDevice && $originalDevice->id !== $scannerDevice->id) {
            $conflict = app(ScannerConflictService::class)->record(
              $unit,
              $originalDevice,
              $scannerDevice,
              $checkInSource,
              $scannerStaff
            );
          }
          return response()->json([
            'alert_type' => 'error',
            'message' => $originalDevice
              ? 'Already scanned at ' . $originalDevice->name . ' at ' . optional($unit->checked_in_at)->format('H:i:s')
              : 'Already scanned',
            'booking_id' => $booking->booking_id,
            'ticket_unit_id' => $unit->public_id,
            'checked_in_at' => optional($unit->checked_in_at)->toIso8601String(),
            'checked_in_gate' => optional($originalDevice)->name,
            'multi_gate_conflict' => (bool) $conflict,
            'conflict_id' => optional($conflict)->id,
          ], 409);
        }
        if ($unit->status !== 'issued') {
          return response()->json([
            'alert_type' => 'error',
            'message' => 'This ticket is ' . str_replace('_', ' ', $unit->status) . '.',
            'booking_id' => $booking->booking_id,
          ], 422);
        }

        $unit->update([
          'status' => 'checked_in',
          'checked_in_at' => now(),
          'checked_in_by_organizer_id' => $organizerId,
          'checked_in_scanner_device_id' => optional($scannerDevice)->id,
          'checked_in_scanner_staff_id' => optional($scannerStaff)->id,
          'checked_in_source' => $checkInSource,
        ]);
        $remaining = BookingTicket::where('booking_id', $booking->id)->where('status', 'issued')->count();
        if ($remaining === 0) {
          $booking->update(['scan_status' => 1]);
        }

        AuditLogger::record(
          'ticket_unit.checked_in',
          $unit,
          ['status' => 'issued'],
          ['status' => 'checked_in', 'checked_in_at' => $unit->checked_in_at],
          [
            'event_id' => $booking->event_id,
            'booking_id' => $booking->booking_id,
            'sequence' => $unit->sequence,
            'ticket_count' => (int) $booking->quantity,
            'remaining' => $remaining,
            'source' => $checkInSource,
            'scanner_device_id' => optional($scannerDevice)->id,
            'scanner_device_public_id' => optional($scannerDevice)->public_id,
            'scanner_device_name' => optional($scannerDevice)->name,
            'scanner_staff_id' => optional($scannerStaff)->id,
            'scanner_staff_name' => optional($scannerStaff)->name,
          ]
        );

        app(\App\Services\FunnelAnalyticsService::class)->recordServerSafely('ticket_scanned', [
          'event_id' => $booking->event_id,
          'booking_id' => $booking->id,
          'organizer_id' => $organizerId,
          'customer_id' => $booking->customer_id,
          'ticket_unit_id' => $unit->id,
          'source' => $checkInSource,
          'scanner_device_id' => optional($scannerDevice)->id,
          'scanner_staff_id' => optional($scannerStaff)->id,
        ], 'ticket_scanned:unit:' . $unit->id);

        return response()->json([
          'alert_type' => 'success',
          'message' => 'Ticket ' . $unit->sequence . ' of ' . $booking->quantity . ' verified',
          'booking_id' => $booking->booking_id,
          'ticket_unit_id' => $unit->public_id,
          'remaining' => $remaining,
        ]);
      }

      return response()->json([
        'alert_type' => 'error',
        'code' => 'ticket_units_inconsistent',
        'message' => 'Ticket setup is incomplete. Ask a supervisor to resolve this booking before entry.',
        'booking_id' => $booking->booking_id,
      ], 409);
    });
  }

  public function ticketLookup(Request $request)
  {
    $validated = $request->validate([
      'query' => ['required', 'string', 'min:3', 'max:100'],
      'event_id' => ['nullable', 'integer', 'min:1'],
    ]);
    $term = trim(str_replace(['%', '_'], '', $validated['query']));
    if (mb_strlen($term) < 3) {
      return response()->json(['message' => 'Enter at least three searchable characters.'], 422);
    }
    $like = '%' . $term . '%';
    $organizerId = (int) ($request->attributes->get('scanner_organizer_id') ?: Auth::guard('organizer')->id());

    $tickets = BookingTicket::query()
      ->with('booking')
      ->where('organizer_id', $organizerId)
      ->when($validated['event_id'] ?? null, fn ($query, $eventId) => $query->where('event_id', $eventId))
      ->where(function ($query) use ($term, $like) {
        $query->where('public_id', $term)
          ->orWhere('holder_email', $term)
          ->orWhere('holder_name', 'like', $like)
          ->orWhereHas('booking', function ($booking) use ($term, $like) {
            $booking->where('booking_id', $term)
              ->orWhere('email', $term)
              ->orWhere('fname', 'like', $like)
              ->orWhere('lname', 'like', $like);
          });
      })
      ->orderByDesc('id')
      ->limit(20)
      ->get();

    return response()->json([
      'tickets' => $tickets->map(function (BookingTicket $ticket) {
        $booking = $ticket->booking;
        return [
          'id' => $ticket->id,
          'holder_name' => $ticket->holder_name ?: trim(($booking->fname ?? '') . ' ' . ($booking->lname ?? '')),
          'holder_email' => $ticket->holder_email ?: ($booking->email ?? ''),
          'booking_id' => $booking->booking_id ?? '',
          'event_id' => $ticket->event_id,
          'sequence' => $ticket->sequence,
          'quantity' => (int) ($booking->quantity ?? 1),
          'status' => $ticket->status,
          'checked_in_at' => optional($ticket->checked_in_at)->toIso8601String(),
          'variation_name' => $ticket->variation_name,
        ];
      })->values(),
    ]);
  }

  public function manualCheckIn(Request $request)
  {
    $validated = $request->validate([
      'ticket_unit_id' => ['required', 'integer', 'min:1'],
    ]);
    $unit = BookingTicket::query()
      ->whereKey($validated['ticket_unit_id'])
      ->where('organizer_id', $request->attributes->get('scanner_organizer_id') ?: Auth::guard('organizer')->id())
      ->first();

    if (!$unit) {
      return response()->json(['alert_type' => 'error', 'message' => 'Ticket is not valid for your event'], 404);
    }

    $request->merge([
      'booking_id' => signedTicketUnitPayload($unit->public_id, $unit->event_id),
      'source' => 'manual',
    ]);
    return $this->check_qrcode($request);
  }

  public function changeTheme(Request $request)
  {
    Session::put('organizer_theme_version', $request->organizer_theme_version);
    return redirect()->back();
  }

  //transaction
  public function transaction(Request $request)
  {
    $filters = $request->validate([
      'transcation_id' => ['nullable', 'string', 'max:100'],
      'transaction_type' => ['nullable', 'integer', 'between:1,7'],
      'payment_status' => ['nullable', 'integer', 'between:0,2'],
      'from_date' => ['nullable', 'date_format:Y-m-d'],
      'to_date' => ['nullable', 'date_format:Y-m-d'],
    ]);
    $filters['transaction_reference'] = trim((string) $request->input('transcation_id', ''));
    $transcations = app(TransactionExportQueryService::class)->query(
      $filters, (int) Auth::guard('organizer')->id()
    )->orderByDesc('id')->paginate(10)->withQueryString();

    return view('organizer.transaction', compact('transcations'));
  }

  //monthly income
  public function monthly_income(Request $request)
  {
    $date = $request->filled('year') ? $request->input('year') : date('Y');

    $monthWiseTotalIncomes = DB::table('transactions')->where('organizer_id', Auth::guard('organizer')->user()->id)
      ->select(DB::raw('month(created_at) as month'), DB::raw('sum(grand_total) as total'))
      ->where(function ($query) {
        return $query->where('transcation_type', 1)->orWhere('transcation_type', 4);
      })
      ->where('payment_status', 1)
      ->groupBy('month')
      ->whereYear('created_at', '=', $date)
      ->get();

    $monthWiseTotalReject = DB::table('transactions')->where('organizer_id', Auth::guard('organizer')->user()->id)
      ->select(DB::raw('month(created_at) as month'), DB::raw('sum(grand_total) as total'))
      ->where('transcation_type', 3)
      ->where('payment_status', 2)
      ->groupBy('month')
      ->whereYear('created_at', '=', $date)
      ->get();

    $monthWiseTotalCommission = DB::table('transactions')->where('organizer_id', Auth::guard('organizer')->user()->id)
      ->select(DB::raw('month(created_at) as month'), DB::raw('sum(commission) as total'))
      ->where(function ($query) {
        return $query->where('transcation_type', 1)->orWhere('transcation_type', 3);
      })
      ->where('payment_status', 1)
      ->groupBy('month')
      ->whereYear('created_at', '=', $date)
      ->get();

    $monthWiseTotalExpenses = DB::table('transactions')->where('organizer_id', Auth::guard('organizer')->user()->id)
      ->select(DB::raw('month(created_at) as month'), DB::raw('sum(grand_total) as total'))
      ->where(function ($query) {
        return $query->whereIn('transcation_type', [3, 5, 6]);
      })
      ->groupBy('month')
      ->whereYear('created_at', '=', $date)
      ->get();

    $months      = [];
    $incomes     = [];
    $rejects     = [];
    $commissions = [];
    $expenses    = [];

    for ($i = 1; $i <= 12; $i++) {
      $dateObj   = DateTime::createFromFormat('!m', $i);
      $monthName = $dateObj->format('M');
      array_push($months, $monthName);

      $incomeFound = false;
      foreach ($monthWiseTotalIncomes as $incomeInfo) {
        if ($incomeInfo->month == $i) { $incomeFound = true; array_push($incomes, $incomeInfo->total); break; }
      }
      if (!$incomeFound) array_push($incomes, 0);

      $rejectFound = false;
      foreach ($monthWiseTotalReject as $Reject) {
        if ($Reject->month == $i) { $rejectFound = true; array_push($rejects, $Reject->total); break; }
      }
      if (!$rejectFound) array_push($rejects, 0);

      $commissionFound = false;
      foreach ($monthWiseTotalCommission as $commissionInfo) {
        if ($commissionInfo->month == $i) { $commissionFound = true; array_push($commissions, $commissionInfo->total); break; }
      }
      if (!$commissionFound) array_push($commissions, 0);

      $expensesFound = false;
      foreach ($monthWiseTotalExpenses as $expensesInfo) {
        if ($expensesInfo->month == $i) { $expensesFound = true; array_push($expenses, $expensesInfo->total); break; }
      }
      if (!$expensesFound) array_push($expenses, 0);
    }

    $information['months']      = $months;
    $information['incomes']     = $incomes;
    $information['rejects']     = $rejects;
    $information['commissions'] = $commissions;
    $information['expenses']    = $expenses;

    return view('organizer.income', $information);
  }
}
