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
use App\Rules\ImageMimeTypeRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
    $searchKey = null;

    if ($request->filled('info')) {
      $searchKey = $request['info'];
    }

    $customers = Customer::when($searchKey, function ($query, $searchKey) {
      return $query->where('fname', 'like', '%' . $searchKey . '%')
        ->orWhere('lname', 'like', '%' . $searchKey . '%')
        ->orWhere('email', 'like', '%' . $searchKey . '%');
    })
      ->orderBy('id', 'desc')
      ->paginate(10);

    return view('backend.end-user.customer.index', compact('customers'));
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

  //secrtet login
  public function secret_login(Request $request, $id)
  {
    $user = Customer::findOrFail($id);
    $adminId = (int) Auth::guard('admin')->id();
    AuditLogger::record('customer.impersonation_started', $user, [], [], [
      'admin_id' => $adminId,
      'target_email' => $user->email,
    ]);
    Session::put([
      'secret_login' => 1,
      'impersonator_admin_id' => $adminId,
      'impersonation_started_at' => now()->toIso8601String(),
    ]);
    Auth::guard('customer')->login($user);
    return redirect()->route('customer.dashboard');
  }
}
