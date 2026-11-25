<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Models\Admin;
use App\Models\Language;
use App\Models\OperationalIncident;
use App\Models\PaymentIntent;
use App\Models\PaymentRefund;
use App\Models\PaymentRefundDispute;
use App\Models\ShopManagement\Product;
use App\Models\ShopManagement\ProductOrder;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Rules\ImageMimeTypeRule;
use App\Rules\MatchEmailRule;
use App\Services\PasswordResetService;
use App\Services\PasswordPolicy;
use App\Services\AuditLogger;
use App\Services\ApplicationMailService;
use App\Services\AccountMfaService;
use App\Services\TransactionExportQueryService;
use App\Rules\MatchOldPasswordRule;
use App\Support\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
  public function login()
  {
    return view('backend.login');
  }

  public function authentication(Request $request, AccountMfaService $mfa)
  {
    $rules = [
      'username' => 'required',
      'password' => 'required'
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    if (
      Auth::guard('admin')->attempt([
        'username' => $request->username,
        'password' => $request->password,
        'status' => 1,
      ])
    ) {
      $authAdmin = Auth::guard('admin')->user();

      // check whether the admin's account is active or not
      if ($authAdmin->status == 0) {
        Session::flash('alert', 'Sorry, your account has been deactivated!');

        // logout auth admin as condition not satisfied
        Auth::guard('admin')->logout();

        return redirect()->back();
      } else {
        if ($mfa->enabled($authAdmin)) {
          $mfa->beginLogin($request, 'admin', $authAdmin, '/admin/dashboard');
          return redirect()->route('mfa.login.challenge');
        }
        $request->session()->regenerate();
        return redirect()->route('admin.dashboard');
      }
    } else {
      return redirect()->back()->with('alert', 'Wrong username or password. Try again or reset your password.');
    }
  }

  public function forgetPassword()
  {
    return view('backend.forget-password');
  }

  public function sendMail(Request $request)
  {
    $rules = [
      'email' => [
        'required',
        'email:rfc,dns',
        new MatchEmailRule('admin')
      ]
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors())->withInput();
    }

    $admin = Admin::where('email', $request->email)->firstOrFail();
    $token = (new PasswordResetService())->issue($admin);
    $resetUrl = route('admin.reset_password', ['token' => $token, 'email' => $admin->email]);

    try {
      app(ApplicationMailService::class)->queueHtml(
        'admin_password_reset',
        $request->email,
        'Reset Password',
        'Hello ' . e($admin->first_name) . ',<br/><br/>We received a request to reset your password. This link expires in 60 minutes and can only be used once:<br/><br/><a href="' . e($resetUrl) . '">Reset password</a><br/><br/>If you did not request this, you can ignore this email.<br/><br/>Thank you.',
        [],
        null,
        $admin
      );

      Session::flash('success', 'A password reset email has been queued.');
    } catch (\Throwable $e) {
      report($e);
      Session::flash('warning', 'The reset email could not be queued.');
    }

    return redirect()->back();
  }

  public function resetPassword()
  {
    return view('backend.reset-password');
  }

  public function updateForgottenPassword(Request $request)
  {
    $validated = $request->validate([
      'email' => ['required', 'email'],
      'token' => ['required', 'string'],
      'password' => ['required', 'confirmed', PasswordPolicy::rule()],
    ]);

    (new PasswordResetService())->consume(
      Admin::class,
      $validated['email'],
      $validated['token'],
      $validated['password']
    );

    return redirect()->route('admin.login')->with('success', 'Your password has been reset.');
  }

  public function redirectToDashboard()
  {
    $language = Language::query()->where('is_default', 1)->first();

    return view('backend.admin.dashboard', [
      'overview' => (new \App\Services\TicaUi\AdminOverview((int) $language->id))->build(),
    ]);
  }

  public function changeTheme(Request $request)
  {
    DB::table('basic_settings')->updateOrInsert(
      ['uniqid' => 12345],
      ['admin_theme_version' => $request->admin_theme_version]
    );

    return redirect()->back();
  }

  public function editProfile()
  {
    $adminInfo = Auth::guard('admin')->user();

    return view('backend.admin.edit-profile', compact('adminInfo'));
  }

  public function updateProfile(Request $request)
  {
    $admin = Auth::guard('admin')->user();

    $rules = [];

    if ($request->hasFile('image')) {
      $rules['image'] = new ImageMimeTypeRule();
    }

    $rules['username'] = [
      'required',
      Rule::unique('admins')->ignore($admin->id)
    ];

    $rules['email'] = [
      'required',
      'email:rfc,dns',
      Rule::unique('admins')->ignore($admin->id)
    ];

    $rules['first_name'] = 'required';

    $rules['last_name'] = 'required';

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return redirect()->back()->withErrors($validator->errors());
    }

    if ($request->hasFile('image')) {
      $imageName = UploadFile::update(public_path('assets/admin/img/admins/'), $request->file('image'), $admin->image);
    }

    $admin->update([
      'first_name' => $request->first_name,
      'last_name' => $request->last_name,
      'image' => $request->hasFile('image') ? $imageName : $admin->image,
      'username' => $request->username,
      'email' => $request->email,
      'phone' => $request->phone,
      'address' => $request->address,
      'details' => $request->details,
    ]);
    Session::flash('success', 'Profile updated successfully!');

    return redirect()->back();
  }

  public function changePassword()
  {
    return view('backend.admin.change-password');
  }

  public function updatePassword(Request $request)
  {
    $rules = [
      'current_password' => [
        'required',
        new MatchOldPasswordRule('admin')
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
      return Response::json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }

    $admin = Auth::guard('admin')->user();

    $admin->update([
      'password' => Hash::make($request->new_password)
    ]);
    AuditLogger::record('admin.password_changed', $admin);

    Session::flash('success', 'Updated Successfully');

    return response()->json(['status' => 'success'], 200);
  }

  public function logout(Request $request)
  {
    Auth::guard('admin')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('admin.login');
  }

  //transcation 
  public function transcation(Request $request)
  {
    $filters = $request->validate([
      'transcation_id' => ['nullable', 'string', 'max:100'],
      'transaction_type' => ['nullable', 'integer', 'between:1,7'],
      'payment_status' => ['nullable', 'integer', 'between:0,2'],
      'from_date' => ['nullable', 'date_format:Y-m-d'],
      'to_date' => ['nullable', 'date_format:Y-m-d'],
    ]);
    $filters['transaction_reference'] = trim((string) $request->input('transcation_id', ''));
    $transcations = app(TransactionExportQueryService::class)->query($filters)
      ->orderByDesc('id')->paginate(10)->withQueryString();

    $references = $transcations->getCollection()
      ->where('transcation_type', 1)
      ->pluck('transcation_id')
      ->filter()
      ->values();
    $bookingIds = $transcations->getCollection()
      ->where('transcation_type', 1)
      ->pluck('booking_id')
      ->filter()
      ->values();
    $paymentIntentRecords = PaymentIntent::with(['booking.ticketUnits'])
      ->whereIn('gateway', ['paystack', 'flutterwave'])
      ->where(function ($query) use ($references, $bookingIds) {
        $query->whereIn('reference', $references)->orWhereIn('booking_id', $bookingIds);
      })
      ->get()
      ->sortByDesc('id');
    $paymentIntents = $paymentIntentRecords->keyBy('reference');
    $paymentIntentsByBooking = $paymentIntentRecords->keyBy('booking_id');
    $refundsByIntent = PaymentRefund::whereIn('payment_intent_id', $paymentIntentRecords->pluck('id'))
      ->orderByDesc('id')
      ->get()
      ->groupBy('payment_intent_id');
    $organizerRefundRequests = PaymentRefund::with(['booking', 'organizer'])
      ->where('status', 'approval_pending')
      ->whereNotNull('requested_by_organizer_id')
      ->orderBy('requested_at')
      ->limit(100)
      ->get();
    $refundDisputes = PaymentRefundDispute::with(['booking', 'organizer', 'paymentIntent', 'refund'])
      ->whereIn('status', [
        PaymentRefundDispute::STATUS_OPEN,
        PaymentRefundDispute::STATUS_UNDER_REVIEW,
      ])
      ->orderBy('opened_at')
      ->limit(100)
      ->get();

    return view('backend.admin.transaction', compact(
      'transcations', 'paymentIntents', 'paymentIntentsByBooking', 'refundsByIntent',
      'organizerRefundRequests', 'refundDisputes'
    ));
  }
  //destroy
  public function destroy(Request $request)
  {
    Session::flash('warning', 'Financial transactions are immutable and cannot be deleted.');
    return back();
  }

  //destroy
  public function bulk_destroy(Request $request)
  {
    return response()->json([
      'status' => 'error',
      'message' => 'Financial transactions are immutable and cannot be deleted.',
    ], 422);
  }

  public function monthly_earning(Request $request)
  {
    $year = MonthlyReport::year($request);
    $rows = MonthlyReport::query(DB::table('transactions')->where('payment_status', '1'), $year)
      ->selectRaw('SUM(CASE WHEN transcation_type NOT IN (3, 4, 5) THEN grand_total ELSE 0 END) AS incomes')
      ->selectRaw('SUM(CASE WHEN transcation_type NOT IN (2, 3, 4, 5) THEN tax ELSE 0 END) AS taxs')
      ->get();

    return view('backend.admin.earning', MonthlyReport::series($rows, ['incomes', 'taxs'], 'F'));
  }

  public function monthly_profit(Request $request)
  {
    $year = MonthlyReport::year($request);
    $rows = MonthlyReport::query(DB::table('transactions')->where('payment_status', '1'), $year)
      ->selectRaw('SUM(CASE WHEN organizer_id IS NOT NULL THEN commission ELSE 0 END) AS incomes')
      ->selectRaw('SUM(tax) AS taxs')
      ->selectRaw('SUM(CASE WHEN organizer_id IS NULL THEN grand_total ELSE 0 END) AS admin_profit')
      ->get();

    return view('backend.admin.profit', MonthlyReport::series($rows, ['incomes', 'taxs', 'admin_profit']));
  }
}
