<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\DatabaseUpdates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Admin > Database updates: see and apply the updates this code needs. Super admin only. */
class DatabaseUpdateController extends Controller
{
  public function index()
  {
    $this->superAdminOnly();

    return view('backend.database-updates.index', ['status' => DatabaseUpdates::status()]);
  }

  public function apply(Request $request)
  {
    $this->superAdminOnly();
    $request->validate(['current_password' => ['required', 'string', 'max:255']], ['current_password.required' => 'Enter your admin password to apply database updates.']);
    if (!Hash::check((string) $request->input('current_password'), (string) Auth::guard('admin')->user()->password)) {
      throw ValidationException::withMessages(['current_password' => 'Your admin password is incorrect.']);
    }
    $before = DatabaseUpdates::status()['pending'];
    $result = DatabaseUpdates::apply();
    $after = DatabaseUpdates::status()['pending'];
    AuditLogger::record('database.updates_applied', null, [], ['applied' => array_values(array_diff($before, $after)), 'still_pending' => count($after), 'ok' => $result['ok']]);

    return redirect()->route('admin.database_updates.index')
      ->with($result['ok'] ? 'success' : 'warning', $result['ok']
        ? (count($before) - count($after)) . ' update(s) applied. The database is up to date.'
        : 'Updates stopped before finishing. Nothing already applied is lost; fix the problem below and press Apply again.')
      ->with('db_output', $result['output']);
  }

  private function superAdminOnly(): void
  {
    $admin = Auth::guard('admin')->user();
    abort_unless($admin && $admin->role_id === null, 403, 'Only a super admin can apply database updates.');
  }
}
