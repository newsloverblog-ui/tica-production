<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Integrations\IntegrationRegistry;
use App\Support\Integrations\Integrations;
use App\Support\Integrations\IntegrationTester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Integrations: every outside service TICA depends on, in one place.
 * Only a super admin can open it; changing a secret needs the admin's password.
 */
class IntegrationController extends Controller
{
  public function index()
  {
    $this->superAdminOnly();
    $all = collect(array_keys(IntegrationRegistry::all()))->map(fn ($key) => Integrations::describe($key));

    return view('backend.integrations.index', [
      'groups' => IntegrationRegistry::GROUPS,
      'integrations' => $all->groupBy('group'),
      'storeReady' => Integrations::storeReady(),
      'counts' => $all->countBy(fn ($i) => in_array($i['status']['code'], ['connected', 'env'], true) ? 'ok' : (in_array($i['status']['code'], ['failing', 'incomplete', 'untested'], true) ? 'attention' : 'other')),
    ]);
  }

  public function show(string $key)
  {
    $this->superAdminOnly();
    abort_unless(IntegrationRegistry::get($key), 404);

    return view('backend.integrations.show', [
      'i' => Integrations::describe($key),
      'storeReady' => Integrations::storeReady(),
    ]);
  }

  public function update(Request $request, string $key)
  {
    $this->superAdminOnly();
    $def = IntegrationRegistry::get($key);
    abort_unless($def, 404);

    $rules = [];
    $input = [];
    $changesSecret = false;
    foreach ($def['fields'] as $field) {
      $name = 'f_' . $field['name'];
      $r = ['nullable'];
      $r[] = match ($field['type']) {
        'email' => 'email:rfc',
        'url' => 'url:https',
        'toggle' => 'boolean',
        'select' => 'in:' . implode(',', array_keys($field['options'])),
        default => 'string',
      };
      if (!empty($field['max'])) {
        $r[] = 'max:' . $field['max'];
      } elseif ($field['type'] !== 'toggle') {
        $r[] = 'max:2000';
      }
      if (!empty($field['min'])) {
        $r[] = 'min:' . $field['min'];
      }
      if (!empty($field['pattern'])) {
        $r[] = 'regex:' . $field['pattern'];
      }
      $rules[$name] = $r;
      if ($field['type'] === 'toggle') {
        $input[$field['name']] = $request->boolean($name);
      } elseif ($request->has($name)) {
        $input[$field['name']] = $request->input($name);
        if ($field['type'] === 'secret' && trim((string) $request->input($name)) !== '') {
          $changesSecret = true;
        }
      }
    }
    $rules['enabled'] = ['nullable', 'boolean'];
    $labels = collect($def['fields'])->mapWithKeys(fn ($f) => ['f_' . $f['name'] => $f['label']])->all();
    $request->validate($rules, [
      'regex' => 'The :attribute doesn\'t look right. Check the help text under it.',
      'url' => 'The :attribute must be a full https:// address.',
    ], $labels);

    if ($changesSecret || $request->filled('current_password')) {
      $this->requirePassword($request);
    }

    $enabled = $request->has('enabled') ? $request->boolean('enabled') : null;
    if ($enabled && !$this->canTurnOn($key, $input)) {
      throw ValidationException::withMessages(['enabled' => 'Fill in every required field before turning this on.']);
    }

    Integrations::save($key, $input, $def['enabled'] === 'always' ? null : $enabled, (int) Auth::guard('admin')->id());
    // Record which settings changed, never their values.
    AuditLogger::record('integration.updated', null, [], [
      'integration' => $key,
      'fields' => array_keys(array_filter($input, fn ($v) => $v !== null && $v !== '')),
      'enabled' => $enabled,
    ]);

    $after = !empty($def['apply_after_test'])
      ? ' It takes effect after a passing test.'
      : '';

    return redirect()->route('admin.integrations.show', $key)->with('success', $def['name'] . ' saved.' . $after);
  }

  public function test(string $key)
  {
    $this->superAdminOnly();
    abort_unless(IntegrationRegistry::get($key), 404);
    [$ok, $message] = IntegrationTester::run($key, (string) Auth::guard('admin')->user()?->email);
    Integrations::recordTest($key, $ok, $message);
    AuditLogger::record('integration.tested', null, [], ['integration' => $key, 'passed' => $ok]);

    return redirect()->route('admin.integrations.show', $key)->with($ok ? 'success' : 'warning', $message);
  }

  /** Fresh web-push (VAPID) keys, shown once in the form for the admin to save. */
  public function generate(string $key)
  {
    $this->superAdminOnly();
    abort_unless($key === 'push' && class_exists(\Minishlink\WebPush\VAPID::class), 404);
    $keys = \Minishlink\WebPush\VAPID::createVapidKeys();

    return redirect()->route('admin.integrations.show', $key)
      ->withInput(['f_public_key' => $keys['publicKey'], 'f_private_key' => $keys['privateKey']])
      ->with('success', 'New keys are filled in below. Press Save to keep them. Browsers subscribed with the old keys will need to allow notifications again.');
  }

  private function canTurnOn(string $key, array $input): bool
  {
    $describe = Integrations::describe($key);
    foreach ($describe['fields'] as $field) {
      if (empty($field['required'])) {
        continue;
      }
      $given = $input[$field['name']] ?? null;
      if (!$field['set'] && !$field['env_set'] && ($given === null || trim((string) $given) === '') && empty($field['default'])) {
        return false;
      }
    }

    return true;
  }

  private function superAdminOnly(): void
  {
    $admin = Auth::guard('admin')->user();
    abort_unless($admin && $admin->role_id === null, 403, 'Only a super admin can manage integrations.');
  }

  private function requirePassword(Request $request): void
  {
    $request->validate(['current_password' => ['required', 'string', 'max:255']], ['current_password.required' => 'Enter your admin password to change a key or secret.']);
    $admin = Auth::guard('admin')->user();
    if (!$admin || !Hash::check((string) $request->input('current_password'), (string) $admin->password)) {
      throw ValidationException::withMessages(['current_password' => 'Your admin password is incorrect.']);
    }
  }
}
