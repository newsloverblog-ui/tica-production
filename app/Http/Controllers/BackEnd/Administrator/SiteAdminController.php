<?php

namespace App\Http\Controllers\BackEnd\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Http\Requests\Admin\StoreRequest;
use App\Http\Requests\Admin\UpdateRequest;
use App\Models\Admin;
use App\Models\RolePermission;
use App\Services\AdminAccountService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SiteAdminController extends Controller
{
  public function index()
  {
    $information['roles'] = RolePermission::all();

    $information['admins'] = Admin::where('role_id', '!=', NULL)->get();

    return view('backend.administrator.site-admin.index', $information);
  }

  public function store(StoreRequest $request)
  {
    $imageName = UploadFile::store(public_path('assets/admin/img/admins/'), $request->file('image'));

    $admin = Admin::create($request->except('image', 'password') + [
      'image' => $imageName,
      'password' => Hash::make($request->password)
    ]);
    AuditLogger::record('admin.created', $admin, [], [
      'role_id' => $admin->role_id,
      'username' => $admin->username,
      'email' => $admin->email,
      'status' => $admin->status,
    ]);

    $request->session()->flash('success', 'New admin added successfully!');

    return response()->json(['status' => 'success'], 200);
  }

  public function updateStatus(Request $request, $id)
  {
    $status = (int) $request->validate([
      'status' => ['required', Rule::in([0, 1])],
    ])['status'];
    (new AdminAccountService())->setStatus((int) $id, $status, (int) Auth::guard('admin')->id());

    $request->session()->flash('success', 'Status updated successfully!');

    return redirect()->back();
  }

  public function update(UpdateRequest $request)
  {
    $admin = Admin::findOrFail($request->id);
    $before = $admin->only(['role_id', 'first_name', 'last_name', 'username', 'email']);

    if ($request->hasFile('image')) {
      $imageName = UploadFile::update(public_path('assets/admin/img/admins/'), $request->file('image'), $admin->image);
    }

    $admin->update($request->except('image') + [
      'image' => $request->hasFile('image') ? $imageName : $admin->image
    ]);
    AuditLogger::record(
      'admin.profile_changed',
      $admin,
      $before,
      $admin->fresh()->only(['role_id', 'first_name', 'last_name', 'username', 'email'])
    );

    $request->session()->flash('success', 'Admin updated successfully!');

    return response()->json(['status' => 'success'], 200);
  }

  public function destroy($id)
  {
    (new AdminAccountService())->archive((int) $id, (int) Auth::guard('admin')->id());

    return redirect()->back()->with('success', 'Administrator archived successfully.');
  }
}
