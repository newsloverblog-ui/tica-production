<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Event\Booking;
use App\Services\OperationalArchiveService;
use App\Services\OperationalStatusService;
use App\Services\PasswordPolicy;
use App\Services\AuditLogger;
use App\Services\ApplicationMailService;
use App\Rules\ImageMimeTypeRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class CustomerManagementController extends Controller
{
  private $admin_user_name;
  public function __construct()
  {
    $this->middleware(function ($request, $next) {
      $this->admin_user_name = optional(Admin::select('username')->first())->username;
      return $next($request);
    });
  }

  public function index(Request $request)
  {
    $filters = $request->validate([
      'info' => ['nullable', 'string', 'max:120'],
      'status' => ['nullable', Rule::in(['0', '1'])],
      'verified' => ['nullable', Rule::in(['0', '1'])],
    ]);
    $searchKey = trim((string) ($filters['info'] ?? ''));
    $status = $filters['status'] ?? null;
    $verified = $filters['verified'] ?? null;

    $customers = Customer::query()
      ->when($searchKey !== '', function ($query) use ($searchKey) {
        $query->where(function ($nested) use ($searchKey) {
          $nested->where('fname', 'like', '%' . $searchKey . '%')
            ->orWhere('lname', 'like', '%' . $searchKey . '%')
            ->orWhere('username', 'like', '%' . $searchKey . '%')
            ->orWhere('email', 'like', '%' . $searchKey . '%');
        });
      })
      ->when($status !== null, fn ($query) => $query->where('status', (int) $status))
      ->when($verified !== null, fn ($query) => (int) $verified === 1
        ? $query->whereNotNull('email_verified_at')
        : $query->whereNull('email_verified_at'))
      ->orderBy('id', 'desc')
      ->paginate(15)
      ->withQueryString();

    $summary = [
      'total' => Customer::query()->count(),
      'active' => Customer::query()->where('status', 1)->count(),
      'verified' => Customer::query()->whereNotNull('email_verified_at')->count(),
      'with_orders' => Customer::query()->whereHas('bookings')->count(),
    ];

    return view('backend.end-user.customer.index', compact('customers', 'summary'));
  }



  public function show(Request $request, $id)
  {

    $bookingId = $paymentStatus = null;
    if ($request->filled('booking_id')) {
      $bookingId = $request['booking_id'];
    }

    if ($request->filled('status')) {
      $paymentStatus = $request['status'];
    }

    $bookings = Booking::when($bookingId, function ($query, $bookingId) {
      return $query->where('booking_id', 'like', '%' . $bookingId . '%');
    })
      ->when($paymentStatus, function ($query, $paymentStatus) {
        return $query->where('paymentStatus', '=', $paymentStatus);
      })
      ->where('customer_id', $id)
      ->orderByDesc('id')
      ->paginate(10);

    $customer = Customer::findOrFail($id);
    $information['bookings'] = $bookings;
    $information['customer'] = $customer;

    return view('backend.end-user.customer.details', $information);
  }

  public function create()
  {
    return view('backend.end-user.customer.create');
  }

  public function store(Request $request)
  {
    $rules = [
      'fname' => 'required',
      'lname' => 'required',
      'email' => [
        'required',
        'email',
        Rule::unique('customers', 'email')
      ],
      'username' => [
        'required',
        'alpha_dash',
        "not_in:$this->admin_user_name",
        Rule::unique('customers', 'username')
      ],
      'password' => ['required', 'confirmed', PasswordPolicy::rule()]
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }


    $in = $request->all();

    $file = $request->file('photo');
    if ($file) {
      $extension = ImageMimeTypeRule::extension($file);
      $directory = public_path('assets/admin/img/customer-profile/');
      $fileName = uniqid() . '.' . $extension;
      UploadFile::storeAs($directory, $file, $fileName);
      $in['photo'] = $fileName;
    }
    $in['status'] = 1;
    $in['email_verified_at'] = now();
    $in['password'] = Hash::make($request->password);

    Customer::create($in);
    Session::flash('success', 'Added Successfully');

    return Response::json(['status' => 'success'], 200);
  }

  public function updateAccountStatus(Request $request, $id)
  {
    $status = (int) $request->validate([
      'account_status' => ['required', Rule::in([0, 1])],
    ])['account_status'];
    (new OperationalStatusService())->setCustomerStatus((int) $id, $status);
    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }
  public function updateEmailStatus(Request $request, $id)
  {
    $status = (int) $request->validate([
      'email_status' => ['required', Rule::in([0, 1])],
    ])['email_status'];
    (new OperationalStatusService())->setCustomerEmailVerification((int) $id, $status);
    Session::flash('success', 'Updated Successfully');

    return redirect()->back();
  }

  public function message(Request $request, $id, ApplicationMailService $mailService)
  {
    $validated = $request->validate([
      'subject' => ['required', 'string', 'max:160'],
      'message' => ['required', 'string', 'max:5000'],
    ]);
    $customer = Customer::findOrFail($id);
    $subject = preg_replace('/[\r\n]+/', ' ', trim($validated['subject']));
    $name = trim((string) $customer->fname . ' ' . (string) $customer->lname)
      ?: ($customer->username ?: 'there');
    $websiteTitle = trim((string) config('app.name')) ?: 'TICA';
    $adminEmail = optional(Auth::guard('admin')->user())->email;
    $replyTo = filter_var($adminEmail, FILTER_VALIDATE_EMAIL) ? $adminEmail : null;
    $body = '<div style="font-family:Arial,sans-serif;max-width:620px;color:#152238;line-height:1.65">'
      . '<p>Hello ' . e($name) . ',</p>'
      . '<div>' . nl2br(e(trim($validated['message']))) . '</div>'
      . '<p style="margin-top:28px">Regards,<br>' . e($websiteTitle) . '</p>'
      . '</div>';

    try {
      $delivery = $mailService->queueHtml(
        'admin_customer_message',
        $customer->email,
        $subject,
        $body,
        [],
        $replyTo,
        $customer
      );
    } catch (Throwable $exception) {
      report($exception);

      return redirect()->back()->with('warning', 'The message could not be queued. Please try again.');
    }

    try {
      AuditLogger::record('customer.message_queued', $customer, [], [], [
        'delivery_id' => (int) $delivery->id,
        'subject_fingerprint' => hash('sha256', $subject),
      ]);
    } catch (Throwable $exception) {
      report($exception);
    }

    return redirect()->back()->with('success', 'Message queued for ' . $name . '.');
  }
  public function changePassword($id)
  {
    $userInfo = Customer::findOrFail($id);

    return view('backend.end-user.customer.change-password', compact('userInfo'));
  }
  public function updatePassword(Request $request, $id)
  {
    $rules = [
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

    $user = Customer::findOrFail($id);

    $user->update([
      'password' => Hash::make($request->new_password)
    ]);
    AuditLogger::record('customer.password_changed_by_admin', $user);

    Session::flash('success', 'Updated Successfully');

    return Response::json(['status' => 'success'], 200);
  }

  public function edit($id)
  {
    $customer = Customer::findOrFail($id);
    return view('backend.end-user.customer.edit', compact('customer'));
  }

  //update
  public function update(Request $request, $id, Customer $customer)
  {
    $rules = [
      'fname' => 'required',
      'lname' => 'required',
      'email' => [
        'required',
        'email',
        Rule::unique('customers', 'email')->ignore($id)
      ],
      'username' => [
        'required',
        'alpha_dash',
        "not_in:$this->admin_user_name",
        Rule::unique('customers', 'username')->ignore($id)
      ]

    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()
      ], 400);
    }


    $in = $request->all();
    $customer  = Customer::where('id', $id)->first();

    $file = $request->file('photo');
    if ($file) {
      $extension = ImageMimeTypeRule::extension($file);
      $directory = public_path('assets/admin/img/customer-profile/');
      $fileName = uniqid() . '.' . $extension;
      UploadFile::storeAs($directory, $file, $fileName);
      $in['photo'] = $fileName;
      UploadFile::delete($directory, (string) $customer->photo);
    }

    $customer->update($in);
    Session::flash('success', 'Updated Successfully');

    return Response::json(['status' => 'success'], 200);
  }

  public function destroy($id)
  {
    (new OperationalArchiveService())->archiveCustomers([(int) $id]);

    return redirect()->back()->with('success', 'Customer archived. Booking, order and support history was preserved.');
  }

  public function bulkDestroy(Request $request)
  {
    $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
    (new OperationalArchiveService())->archiveCustomers($ids);

    Session::flash('success', 'Customers archived. Booking, order and support history was preserved.');

    return Response::json(['status' => 'success'], 200);
  }

  // Start a bounded administrator impersonation session.
  public function secret_login(Request $request, $id)
  {
    $user = Customer::findOrFail($id);
    $adminId = (int) Auth::guard('admin')->id();
    abort_if($request->session()->get('secret_login'), 409, 'Finish the current impersonation session first.');
    abort_unless($adminId > 0, 403);
    AuditLogger::record('customer.impersonation_started', $user, [], [], [
      'admin_id' => $adminId,
      'target_email' => $user->email,
    ]);
    Auth::guard('customer')->logout();
    $request->session()->regenerate();
    $request->session()->put([
      'secret_login' => 1,
      'impersonator_admin_id' => $adminId,
      'impersonation_started_at' => now()->toIso8601String(),
    ]);
    Auth::guard('customer')->login($user);
    return redirect()->route('tica.account');
  }
}
