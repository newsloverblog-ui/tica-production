<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Http\Controllers\BackEnd\Organizer\OrganizerController;
use App\Http\Controllers\FrontEnd\Event\BookingController;
use App\Models\Customer;
use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Services\CustomerEmailVerificationService;
use App\Services\GuestTicketClaimService;
use App\Services\AccountMfaService;
use App\Services\OrganizerEmailVerificationService;
use App\Services\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Event\Coupon;
use App\Models\Event\Wishlist;
use App\Services\BookingCompletionUrlService;
use App\Models\PaymentGateway\OnlineGateway;
use App\Services\CustomerLoginCodeService;
use App\Services\EventCouponService;
use App\Services\EventPublicVisibility;
use App\Services\EventWaitlistService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

/** JSON endpoints behind the TICA design's interactive pages. */
class TicaApiController extends Controller
{
  /** Validate a discount code for one event before checkout starts. */
  public function coupon(Request $request, EventCouponService $coupons): JsonResponse
  {
    $validated = $request->validate([
      'event_id' => ['required', 'integer'],
      'code' => ['required', 'string', 'max:100'],
    ]);
    $event = Event::query()->find((int) $validated['event_id']);
    $coupon = Coupon::query()->where('code', trim($validated['code']))->first();

    // A nominal subtotal checks dates, event scope and value without knowing
    // the final basket; checkout re-applies the code to the real subtotal.
    $valid = $event && $event->isBookable() && $coupon
      && $coupons->discount($coupon, (int) $event->id, 1000000, 0) !== null;
    if (!$valid) {
      return response()->json(['message' => "That code isn't valid. Check it and try again."], 422);
    }

    return response()->json([
      'code' => strtoupper((string) $coupon->code),
      'type' => (string) $coupon->type,
      'value' => (float) $coupon->value,
    ]);
  }

  /**
   * Place an order from the design's checkout. Each step runs through Tica's
   * existing checkout and booking controllers, so stock, limits, coupons,
   * fees and payment initialisation behave exactly as before.
   */
  public function checkout(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'event_id' => ['required', 'integer'],
      'event_date' => ['nullable', 'string', 'max:100'],
      'quantities' => ['required', 'array', 'min:1', 'max:500'],
      'quantities.*' => ['required', 'integer', 'min:0', 'max:10000'],
      'coupon' => ['nullable', 'string', 'max:100'],
      'buyer.name' => ['required', 'string', 'min:2', 'max:200'],
      'buyer.email' => ['required', 'email:rfc', 'max:191'],
      'buyer.phone' => ['required', 'string', 'max:40'],
      'attendees' => ['nullable', 'array', 'max:500'],
      'attendees.*.name' => ['nullable', 'string', 'max:200'],
      'attendees.*.email' => ['nullable', 'email:rfc', 'max:191'],
      'attendees.*.answer' => ['nullable', 'string', 'max:500'],
      'gateway' => ['nullable', 'string', 'in:' . implode(',', array_keys(\App\Support\TicaPayments::GATEWAYS))],
    ]);
    $event = Event::query()->findOrFail((int) $validated['event_id']);
    if (($event->visibility ?? 'public') === 'private') {
      return response()->json(['message' => 'This event is by invitation only.'], 403);
    }
    $quantities = array_map('intval', $validated['quantities']);

    $selection = [
      'event_id' => $event->id,
      'quantity' => $event->event_type === 'online' ? array_sum($quantities) : $quantities,
      'event_date' => $validated['event_date'] ?? null,
    ];
    $response = $this->dispatchTo('check-out2', $selection, fn (Request $sub) => app(CheckOutController::class)->checkout2($sub));
    if ($this->redirectsTo($response, route('customer.login'))) {
      return response()->json(['message' => 'Log in to buy tickets for this event.', 'login' => true], 401);
    }
    if (!$this->redirectsTo($response, route('check-out'))) {
      return $this->failed('We could not hold those tickets. Review your selection and try again.');
    }

    if (!empty($validated['coupon'])) {
      $coupon = $this->dispatchTo('apply-coupon', ['coupon_code' => $validated['coupon']], fn (Request $sub) => app(EventController::class)->applyCoupon($sub));
      if (!$coupon instanceof JsonResponse || ($coupon->getData(true)['status'] ?? null) !== 'success') {
        return response()->json(['message' => "That discount code isn't valid for this order."], 422);
      }
    }

    $quote = app(CheckOutController::class)->checkout(
      app(\App\Services\EventFeeCalculatorService::class),
      app(\App\Services\EventCheckoutQuoteService::class)
    );
    if ($quote instanceof RedirectResponse) {
      return $this->failed('Your ticket selection changed. Review it and try again.');
    }

    $paid = (float) Session::get('grand_total', 0) > 0;
    $gateway = null;
    if ($paid) {
      // The buyer's choice when it can take this event's currency, otherwise the first that can.
      $usable = \App\Support\TicaPayments::available($event->currency);
      if ($usable === []) {
        return response()->json(['message' => 'Online payment is unavailable right now. Please try again later.'], 503);
      }
      $gateway = in_array($validated['gateway'] ?? null, $usable, true) ? $validated['gateway'] : $usable[0];
    }

    [$first, $last] = $this->splitName($validated['buyer']['name']);
    Session::put('tica_attendees', array_values(array_map(fn ($attendee) => [
      'name' => trim((string) ($attendee['name'] ?? '')),
      'email' => trim((string) ($attendee['email'] ?? '')),
      'answer' => trim((string) ($attendee['answer'] ?? '')),
    ], $validated['attendees'] ?? [])));
    $booking = $this->dispatchTo('ticket.booking', [
      'fname' => $first,
      'lname' => $last,
      'email' => $validated['buyer']['email'],
      'phone' => $validated['buyer']['phone'],
      'gateway' => $gateway,
    ], fn (Request $sub) => app(BookingController::class)->index($sub, $event->id), ['id' => $event->id]);

    $target = $booking instanceof RedirectResponse ? $booking->getTargetUrl() : null;
    $host = parse_url((string) $target, PHP_URL_HOST);
    $local = $host === null || $host === $request->getHost();
    if ($target && (!$local || str_contains((string) parse_url($target, PHP_URL_PATH), 'event-booking-complete') || preg_match('#/(paystack|monnify|flutterwave|stripe)/recovery/#', (string) parse_url($target, PHP_URL_PATH)))) {
      return response()->json(['redirect' => $target]);
    }

    return $this->failed('We could not complete this booking. No payment was taken; please try again.');
  }

  /** Email and password log in for attendees (customers) and organisers. */
  public function login(Request $request, AccountMfaService $mfa): JsonResponse
  {
    $validated = $request->validate([
      'role' => ['required', 'in:att,org'],
      'email' => ['required', 'email:rfc', 'max:254'],
      'password' => ['required', 'string', 'max:255'],
    ] + $this->captchaRules());
    $email = mb_strtolower(trim($validated['email']));
    $denied = response()->json(['message' => "That email and password don't match. Check them and try again."], 422);

    if ($validated['role'] === 'org') {
      if (!Auth::guard('organizer')->attempt(['email' => $email, 'password' => $validated['password'], 'status' => 1])) {
        return $this->teamLogin($request, $email, $validated['password']) ?? $denied;
      }
      Session::forget('tica_team_member');
      $organizer = Auth::guard('organizer')->user();
      $mustVerify = (int) DB::table('basic_settings')->where('uniqid', 12345)->value('organizer_email_verification') === 1;
      if ($mustVerify && $organizer->email_verified_at === null) {
        Auth::guard('organizer')->logout();
        return response()->json(['message' => 'Verify your email address first. Use the link we emailed you.'], 403);
      }
      if ($mfa->enabled($organizer)) {
        $mfa->beginLogin($request, 'organizer', $organizer, '/dashboard');
        return response()->json(['mfa_required' => true, 'redirect' => route('mfa.login.challenge')]);
      }
      Auth::guard('scanner')->logout();
      $request->session()->regenerate();
      Session::put('secret_login', 0);

      return response()->json(['redirect' => '/create']);
    }

    if (!Auth::guard('customer')->attempt(['email' => $email, 'password' => $validated['password'], 'status' => 1])) {
      return $denied;
    }
    $customer = Auth::guard('customer')->user();
    if ($customer->email_verified_at === null) {
      Auth::guard('customer')->logout();
      return response()->json(['message' => 'Verify your email address first. Use the link we emailed you.'], 403);
    }
    $next = $this->safeRedirect($request->session()->pull('redirectTo') ?: $request->session()->pull('url.intended'));
    // A page another account tried to open in this browser (admin, organiser, scanner) is no place to send a buyer.
    if ($next && preg_match('#^/(admin|organizer|scanner|dashboard|create|checkin)(/|\?|$)#', $next)) {
      $next = null;
    }

    if ($mfa->enabled($customer)) {
      $mfa->beginLogin($request, 'customer', $customer, $next ?: '/account');
      return response()->json(['mfa_required' => true, 'redirect' => route('mfa.login.challenge')]);
    }
    $request->session()->regenerate();
    $claim = (int) $request->session()->pull('claim_booking_id', 0);
    if ($claim > 0) {
      app(GuestTicketClaimService::class)->claim($claim, $customer);
    }

    return response()->json(['redirect' => $next ?: '/account']);
  }

  /**
   * Team members sign in on the organiser side: managers and finance act for
   * the organiser within their role; gate staff go straight to check-in.
   */
  private function teamLogin(Request $request, string $email, string $password): ?JsonResponse
  {
    $member = app(\App\Services\TicaUi\OrganizerTeam::class)->authenticate($email, $password);
    if ($member) {
      Auth::guard('scanner')->logout();
      Auth::guard('organizer')->login(Organizer::query()->findOrFail($member->organizer_id));
      $request->session()->regenerate();
      Session::put('tica_team_member', ['id' => (int) $member->id, 'role' => $member->role, 'name' => (string) $member->name]);
      Session::put('secret_login', 0);
      \App\Services\AuditLogger::record('organizer.team_member_logged_in', $member, [], ['role' => $member->role]);

      return response()->json(['redirect' => '/dashboard']);
    }

    if (Auth::guard('scanner')->attempt(['email' => $email, 'password' => $password, 'status' => 'active'])) {
      $staff = Auth::guard('scanner')->user();
      if (!$staff->organizer || (int) $staff->organizer->status !== 1) {
        Auth::guard('scanner')->logout();
        return null;
      }
      Auth::guard('organizer')->logout();
      $request->session()->regenerate();
      $request->session()->put('scanner_auth_version', (int) $staff->auth_version);
      $staff->forceFill(['last_login_at' => now(), 'last_seen_at' => now()])->save();
      \App\Services\AuditLogger::record('scanner_staff.logged_in', $staff, [], ['role' => $staff->role]);

      return response()->json(['redirect' => '/checkin']);
    }

    return null;
  }

  /**
   * Turn a typed ticket code (TICA-000123-2) into the signed payload the gate
   * scanner checks, for the organiser or their gate staff only.
   */
  public function checkinCode(Request $request): JsonResponse
  {
    $validated = $request->validate(['event_id' => ['required', 'integer'], 'code' => ['required', 'string', 'max:40']]);
    $organizerId = Auth::guard('organizer')->id();
    if (!$organizerId) {
      $staff = Auth::guard('scanner')->user();
      if ($staff && $staff->status === 'active' && (int) Session::get('scanner_auth_version', -1) === (int) $staff->auth_version) {
        $organizerId = $staff->organizer_id;
      }
    }
    abort_unless($organizerId, 401, 'Sign in with an organiser or gate staff account.');
    if (!preg_match('/^TICA-0*(\d+)-(\d+)$/i', trim($validated['code']), $match)) {
      return response()->json(['message' => 'Codes look like TICA-123456-1.'], 422);
    }
    $ticket = \App\Models\Event\BookingTicket::query()
      ->where('booking_id', (int) $match[1])
      ->where('sequence', (int) $match[2])
      ->where('event_id', (int) $validated['event_id'])
      ->where('organizer_id', (int) $organizerId)
      ->first();
    if (!$ticket) {
      return response()->json(['message' => 'No ticket with code ' . strtoupper($validated['code']) . ' for this event.'], 404);
    }
    $tier = $ticket->variation_name ?: (\App\Models\Event\TicketContent::query()->where('ticket_id', $ticket->ticket_id)->orderBy('language_id')->value('title') ?: 'Ticket');

    return response()->json([
      'payload' => signedTicketUnitPayload($ticket->public_id, $ticket->event_id),
      'name' => (string) ($ticket->holder_name ?: 'Guest'),
      'tier' => (string) $tier,
    ]);
  }

  /** Accept a team invite: set a name and password. */
  public function acceptTeamInvite(Request $request): JsonResponse
  {
    $validated = $request->validate(['token' => ['required', 'string', 'max:100'], 'name' => ['required', 'string', 'max:120'], 'password' => ['required', 'string', 'max:255']]);
    $member = app(\App\Services\TicaUi\OrganizerTeam::class)->accept($validated['token'], $validated['name'], $validated['password']);

    return response()->json(['message' => "You're in. Log in with " . $member->email . ' to get started.', 'redirect' => '/login?role=org']);
  }

  /** Create an attendee or organiser account from the design's sign-up form. */
  public function signup(Request $request): JsonResponse
  {
    $role = $request->input('role') === 'org' ? 'org' : 'att';
    $table = $role === 'org' ? 'organizers' : 'customers';
    $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
    $validated = $request->validate([
      'role' => ['required', 'in:att,org'],
      'organisation' => [$role === 'org' ? 'required' : 'nullable', 'string', 'min:2', 'max:100'],
      'name' => ['required', 'string', 'min:2', 'max:200'],
      'email' => ['required', 'email:rfc', 'max:254', Rule::unique($table, 'email')],
      'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\s()-]{10,20}$/'],
      'password' => ['required', 'string', PasswordPolicy::rule()],
    ] + $this->captchaRules(), [
      'email.unique' => 'An account with this email already exists. Log in instead.',
      'phone.regex' => 'Enter a phone number with at least 10 digits.',
    ]);
    $phone = $this->nigerianPhone($validated['phone']);

    if ($role === 'org') {
      $settings = DB::table('basic_settings')->where('uniqid', 12345)->select('organizer_email_verification', 'organizer_admin_approval')->first();
      $mustVerify = (int) $settings->organizer_email_verification === 1;
      $needsApproval = (int) $settings->organizer_admin_approval === 1;
      $organizer = DB::transaction(function () use ($validated, $phone, $mustVerify, $needsApproval) {
        $organizer = Organizer::create([
          'username' => $this->uniqueUsername('organizers', $validated['organisation']),
          'email' => $validated['email'],
          'phone' => $phone,
          'password' => Hash::make($validated['password']),
          'status' => ($mustVerify || $needsApproval) ? 0 : 1,
        ]);
        OrganizerInfo::create([
          'organizer_id' => $organizer->id,
          'language_id' => $this->getLanguage()->id,
          'name' => $validated['organisation'],
          'designation' => $validated['name'],
        ]);

        return $organizer;
      });

      if ($mustVerify) {
        rescue(fn () => app(OrganizerEmailVerificationService::class)->request($organizer));
        return response()->json(['message' => 'Your organiser account is ready. Check your email to verify it, then log in.']);
      }
      if ($needsApproval) {
        return response()->json(['message' => "Your organiser account is ready. We'll email you once it's approved."]);
      }
      Auth::guard('organizer')->login($organizer);
      $request->session()->regenerate();
      Session::put('secret_login', 0);

      return response()->json(['redirect' => '/create']);
    }

    [$first, $last] = $this->splitName($validated['name']);
    $customer = Customer::create([
      'fname' => $first,
      'lname' => $last,
      'username' => $this->uniqueUsername('customers', Str::before($validated['email'], '@')),
      'email' => $validated['email'],
      'password' => Hash::make($validated['password']),
      'verification_token' => null,
    ]);
    $customer->forceFill(['phone' => $phone])->save();
    rescue(fn () => app(CustomerEmailVerificationService::class)->request($customer));

    return response()->json(['message' => 'Your TICA account is ready. Check your email to verify it, then log in.']);
  }

  /** Send a password reset link. The reply is the same whether or not the email has an account. */
  public function forgot(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'role' => ['required', 'in:att,org'],
      'email' => ['required', 'email:rfc', 'max:254'],
    ]);
    $exists = $validated['role'] === 'org'
      ? Organizer::query()->where('email', $validated['email'])->exists()
      : Customer::query()->where('email', $validated['email'])->exists();
    if ($exists) {
      $route = $validated['role'] === 'org' ? 'organizer.forget.mail' : 'customer.forget.mail';
      $controller = $validated['role'] === 'org' ? app(OrganizerController::class) : app(CustomerController::class);
      $this->dispatchTo($route, ['email' => $validated['email']], fn (Request $sub) => $controller->forget_mail($sub));
      Session::forget(['success', 'error']);
    }

    return response()->json(['message' => 'If an account uses that email, we sent a link to reset your password.']);
  }

  private function captchaRules(): array
  {
    return (int) DB::table('basic_settings')->value('google_recaptcha_status') === 1
      ? ['g-recaptcha-response' => ['required', 'captcha']]
      : [];
  }

  private function uniqueUsername(string $table, string $seed): string
  {
    $base = Str::limit(Str::slug($seed, '_') ?: 'tica_user', 40, '');
    $reserved = (string) DB::table('admins')->value('username');
    $username = $base;
    for ($i = 2; DB::table($table)->where('username', $username)->exists() || $username === $reserved; $i++) {
      $username = $base . '_' . $i;
    }

    return $username;
  }

  /** The design's phone field is Nigerian (+234); store numbers in that format. */
  private function nigerianPhone(string $phone): string
  {
    $digits = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($digits, '234')) {
      return '+' . $digits;
    }

    return '+234' . ltrim($digits, '0');
  }

  private function safeRedirect(?string $url): ?string
  {
    if (!$url) {
      return null;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if ($host !== null && $host !== request()->getHost()) {
      return null;
    }
    $path = (string) parse_url($url, PHP_URL_PATH);

    return str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path . (parse_url($url, PHP_URL_QUERY) ? '?' . parse_url($url, PHP_URL_QUERY) : '') : null;
  }

  /** Send a one-time login code during checkout. Never reveals whether an account exists. */
  public function loginCode(Request $request, CustomerLoginCodeService $codes): JsonResponse
  {
    $validated = $request->validate(['login' => ['required', 'string', 'max:191']]);
    $email = $this->customerEmail($validated['login']);
    if ($email) {
      $codes->request($email);
    }
    Session::put('tica_login_code_email', $email ?: mb_strtolower(trim($validated['login'])));

    return response()->json(['message' => 'If an account matches, we sent a 6-digit code.']);
  }

  public function loginCodeVerify(Request $request, CustomerLoginCodeService $codes): JsonResponse
  {
    $validated = $request->validate(['code' => ['required', 'digits:6']]);
    $email = (string) Session::get('tica_login_code_email', '');
    $customer = $codes->consume($email, $validated['code']);
    if ($customer->two_factor_enabled_at !== null) {
      Session::forget('tica_login_code_email');
      return response()->json(['message' => 'This account uses two-step verification. Sign in with your password first.'], 403);
    }
    Auth::guard('customer')->login($customer);
    $request->session()->regenerate();
    Session::forget('tica_login_code_email');

    return response()->json(['user' => [
      'id' => (int) $customer->id,
      'name' => trim($customer->fname . ' ' . $customer->lname) ?: $customer->username,
      'email' => (string) $customer->email,
      'phone' => (string) ($customer->phone ?? ''),
    ], 'csrf' => csrf_token()]);
  }

  public function logout(Request $request): JsonResponse
  {
    Auth::guard('customer')->logout();
    $request->session()->regenerateToken();

    return response()->json(['csrf' => csrf_token()]);
  }

  private function customerEmail(string $login): ?string
  {
    $login = trim($login);
    if (str_contains($login, '@')) {
      return mb_strtolower($login);
    }
    $digits = preg_replace('/\D+/', '', $login);
    if (strlen($digits) < 10) {
      return null;
    }
    $tail = substr($digits, -10);

    return Customer::query()
      ->where('status', 1)
      ->whereNotNull('phone')
      ->get(['email', 'phone'])
      ->first(fn ($customer) => substr(preg_replace('/\D+/', '', (string) $customer->phone), -10) === $tail)
      ?->email;
  }

  /** Run an existing controller action with its own request, sharing this session. */
  private function dispatchTo(string $route, array $input, callable $action, array $parameters = [])
  {
    $current = request();
    // A form-encoded sub-request, so the target reads $input rather than this JSON body.
    $server = array_diff_key($current->server->all(), array_flip(['CONTENT_TYPE', 'HTTP_CONTENT_TYPE', 'CONTENT_LENGTH', 'HTTP_CONTENT_LENGTH']));
    $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    $sub = Request::create(route($route, $parameters), 'POST', $input, $current->cookies->all(), [], $server);
    $sub->headers->set('referer', (string) $current->headers->get('referer'));
    $sub->setLaravelSession($current->session());
    $sub->setUserResolver($current->getUserResolver());
    $sub->setRouteResolver($current->getRouteResolver());
    app()->instance('request', $sub);
    try {
      return $action($sub);
    } catch (HttpResponseException $exception) {
      return $exception->getResponse();
    } finally {
      app()->instance('request', $current);
    }
  }

  private function redirectsTo($response, string $url): bool
  {
    return $response instanceof RedirectResponse
      && rtrim(strtok($response->getTargetUrl(), '?'), '/') === rtrim($url, '/');
  }

  private function failed(string $fallback): JsonResponse
  {
    $message = Session::pull('error') ?: Session::pull('currency_error') ?: (Session::get('alert-type') === 'error' ? Session::pull('message') : null) ?: $fallback;
    Session::forget(['alert-type', 'currency_error']);

    return response()->json(['message' => $message], 422);
  }

  private function splitName(string $name): array
  {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $first = array_shift($parts) ?: $name;

    return [$first, $parts !== [] ? implode(' ', $parts) : $first];
  }

  /** Save or unsave an event for the signed-in attendee. */
  public function wishlist(Request $request): JsonResponse
  {
    $validated = $request->validate(['event_id' => ['required', 'integer'], 'saved' => ['required', 'boolean']]);
    $customer = Auth::guard('customer')->user();
    if (!$customer) {
      return response()->json(['message' => 'Log in to save events.'], 401);
    }
    $event = Event::query()->findOrFail((int) $validated['event_id']);
    abort_unless(app(EventPublicVisibility::class)->allows($event), 404);

    if ($validated['saved']) {
      if (!Wishlist::query()->where('event_id', $event->id)->where('customer_id', $customer->id)->exists()) {
        $wish = new Wishlist();
        $wish->event_id = $event->id;
        $wish->customer_id = $customer->id;
        $wish->save();
      }
    } else {
      Wishlist::query()->where('event_id', $event->id)->where('customer_id', $customer->id)->delete();
    }

    return response()->json(['saved' => (bool) $validated['saved']]);
  }

  /** Find a guest order by email and order number, then open its tickets. */
  public function orderLookup(Request $request): JsonResponse
  {
    $validated = $request->validate([
      'email' => ['required', 'email:rfc', 'max:254'],
      'ref' => ['required', 'string', 'regex:/^#?TICA-\d{6,}$/i'],
    ]);
    $id = (int) preg_replace('/\D+/', '', $validated['ref']);
    $booking = Booking::query()
      ->whereKey($id)
      ->whereIn('paymentStatus', ['completed', 'free'])
      ->first();
    if (!$booking || !hash_equals(mb_strtolower(trim((string) $booking->email)), mb_strtolower(trim($validated['email'])))) {
      return response()->json(['message' => "We couldn't find an order with those details. Check them against your confirmation email."], 404);
    }

    return response()->json(['redirect' => app(BookingCompletionUrlService::class)->make((int) $booking->event_id, (int) $booking->id)]);
  }

  /** Join the waitlist for every sold-out ticket of an event. */
  public function waitlist(Request $request, EventWaitlistService $waitlist): JsonResponse
  {
    $validated = $request->validate([
      'event_id' => ['required', 'integer'],
      'email' => ['required', 'email:rfc', 'max:190'],
      'name' => ['nullable', 'string', 'max:120'],
    ]);
    $event = Event::query()->findOrFail((int) $validated['event_id']);
    abort_unless(app(EventPublicVisibility::class)->allows($event), 404);

    $customer = auth('customer')->user();
    $name = trim((string) ($validated['name'] ?? '')) ?: ($customer ? trim($customer->fname . ' ' . $customer->lname) : '');
    $name = $name ?: Str::headline(Str::before($validated['email'], '@'));
    $joined = 0;
    $lastError = null;
    foreach ($waitlist->soldOutSelections($event) as $selection) {
      try {
        $waitlist->register($event, (int) $selection['ticket_id'], $selection['variation_name'] ?? null, $name, $validated['email'], auth('customer')->id());
        $joined++;
      } catch (RuntimeException $exception) {
        $lastError = $exception->getMessage();
      }
    }

    if ($joined === 0) {
      return response()->json(['message' => $lastError ?: 'Tickets are still available for this event.'], 422);
    }

    return response()->json(['message' => "You're on the list. We'll email you if tickets come back."]);
  }
}
