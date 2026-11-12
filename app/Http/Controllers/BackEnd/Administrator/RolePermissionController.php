<?php

namespace App\Http\Controllers\BackEnd\Administrator;

use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use App\Services\RolePermissionService;
use App\Services\BookingExportFieldPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class RolePermissionController extends Controller
{
  public function index()
  {
    $roles = RolePermission::orderBy('id', 'desc')->get();

    return view('backend.administrator.role-permission.index', compact('roles'));
  }

  public function store(Request $request)
  {
    $rule = ['name' => 'required'];

    $validator = Validator::make($request->all(), $rule);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }

    (new RolePermissionService())->create($request->name);

    Session::flash('success', 'New role added successfully!');

    return Response::json(['status' => 'success'], 200);
  }

  public function permissions($id)
  {
    $role = RolePermission::findOrFail($id);

    $exportFieldOptions = BookingExportFieldPolicyService::FIELDS;

    return view('backend.administrator.role-permission.permissions', compact('role', 'exportFieldOptions'));
  }

  public function updatePermissions(Request $request, $id)
  {
    (new RolePermissionService())->updateAccess(
      $id,
      $request->input('permissions', []),
      $request->input('export_fields', [])
    );

    Session::flash('success', 'Permissions updated successfully!');

    return redirect()->back();
  }

  public function update(Request $request)
  {
    $rule = ['name' => 'required'];

    $validator = Validator::make($request->all(), $rule);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }

    (new RolePermissionService())->rename((int) $request->id, $request->name);

    Session::flash('success', 'Role updated successfully!');

    return Response::json(['status' => 'success'], 200);
  }

  public function destroy($id)
  {
    try {
      (new RolePermissionService())->delete($id);
    } catch (\Illuminate\Validation\ValidationException $exception) {
      return redirect()->back()->with('warning', collect($exception->errors())->flatten()->first());
    }

    return redirect()->back()->with('success', 'Role deleted successfully!');
  }
}
