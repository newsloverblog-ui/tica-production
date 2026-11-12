<?php

namespace App\Services\TicaUi;

use App\Models\Organizer;
use App\Models\OrganizerInfo;
use App\Models\OrganizerTeamMember;
use App\Models\ScannerStaff;
use App\Services\ApplicationMailService;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;
use App\Services\ScannerStaffService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Organiser team members. Managers and finance staff sign in with their own
 * email and password and act for the organiser within their role; gate staff
 * become Tica scanner staff, limited to checking tickets.
 */
class OrganizerTeam
{
  public function invite(Organizer $owner, string $email, string $role): OrganizerTeamMember
  {
    $email = mb_strtolower(trim($email));
    if ($email === mb_strtolower((string) $owner->email)) {
      throw ValidationException::withMessages(['email' => 'That\'s your own email. Invite someone else.']);
    }
    $existing = OrganizerTeamMember::query()->where('organizer_id', $owner->id)->where('email', $email)->first();
    if ($existing && $existing->status !== 'removed') {
      throw ValidationException::withMessages(['email' => 'This person is already on your team.']);
    }
    if ($role === 'gate' && ScannerStaff::query()->where('email', $email)->exists()) {
      throw ValidationException::withMessages(['email' => 'This email already has a gate staff account.']);
    }

    $token = Str::random(48);
    $member = $existing ?: new OrganizerTeamMember(['organizer_id' => $owner->id, 'email' => $email]);
    $member->forceFill([
      'role' => $role,
      'name' => null,
      'password' => null,
      'invite_token_hash' => hash('sha256', $token),
      'invite_expires_at' => now()->addDays(7),
      'accepted_at' => null,
      'status' => 'invited',
      'scanner_staff_id' => null,
    ])->save();

    $orgName = OrganizerInfo::query()->where('organizer_id', $owner->id)->value('name') ?: $owner->username;
    $roleName = ['gate' => 'gate staff', 'manager' => 'a manager', 'finance' => 'finance'][$role];
    $link = route('tica.team.accept', ['token' => $token]);
    app(ApplicationMailService::class)->queueHtml(
      'organizer_team_invite',
      $email,
      'Join ' . $orgName . ' on TICA',
      '<p>' . e($orgName) . ' has invited you to help run their events on TICA as ' . e($roleName) . '.</p>'
        . '<p><a href="' . e($link) . '">Accept the invite</a></p><p>The link works for 7 days.</p>',
      [],
      null,
      $member
    );
    AuditLogger::record('organizer.team_member_invited', $member, [], ['role' => $role]);

    return $member;
  }

  public function remove(Organizer $owner, int $memberId): void
  {
    DB::transaction(function () use ($owner, $memberId) {
      $member = OrganizerTeamMember::query()->where('organizer_id', $owner->id)->whereKey($memberId)->lockForUpdate()->firstOrFail();
      if ($member->scanner_staff_id) {
        $staff = ScannerStaff::query()->whereKey($member->scanner_staff_id)->where('organizer_id', $owner->id)->first();
        if ($staff) {
          app(ScannerStaffService::class)->update((int) $owner->id, (int) $staff->id, ['name' => $staff->name, 'role' => $staff->role, 'status' => 'inactive', 'password' => null]);
        }
      }
      $member->forceFill(['status' => 'removed', 'invite_token_hash' => null, 'password' => null])->save();
      AuditLogger::record('organizer.team_member_removed', $member, [], ['role' => $member->role]);
    });
  }

  public function invitation(string $token): ?OrganizerTeamMember
  {
    $member = OrganizerTeamMember::query()->where('invite_token_hash', hash('sha256', $token))->where('status', 'invited')->first();

    return $member && $member->invite_expires_at && $member->invite_expires_at->isFuture() ? $member : null;
  }

  public function accept(string $token, string $name, string $password): OrganizerTeamMember
  {
    Validator::make(['name' => $name, 'password' => $password], [
      'name' => ['required', 'string', 'min:2', 'max:120'],
      'password' => ['required', 'string', PasswordPolicy::rule()],
    ])->validate();

    return DB::transaction(function () use ($token, $name, $password) {
      $member = OrganizerTeamMember::query()->where('invite_token_hash', hash('sha256', $token))->where('status', 'invited')->lockForUpdate()->first();
      if (!$member || !$member->invite_expires_at || $member->invite_expires_at->isPast()) {
        throw ValidationException::withMessages(['token' => 'This invite has expired. Ask for a new one.']);
      }
      $staffId = null;
      if ($member->role === 'gate') {
        $staff = app(ScannerStaffService::class)->create((int) $member->organizer_id, [
          'name' => $name,
          'email' => $member->email,
          'role' => 'check_in',
          'password' => $password,
        ]);
        $staffId = $staff->id;
      }
      $member->forceFill([
        'name' => $name,
        'password' => $member->role === 'gate' ? null : Hash::make($password),
        'invite_token_hash' => null,
        'accepted_at' => now(),
        'status' => 'active',
        'scanner_staff_id' => $staffId,
      ])->save();
      AuditLogger::record('organizer.team_member_joined', $member, [], ['role' => $member->role]);

      return $member;
    });
  }

  /** A manager or finance member signing in, or null. */
  public function authenticate(string $email, string $password): ?OrganizerTeamMember
  {
    $member = OrganizerTeamMember::query()
      ->where('email', mb_strtolower(trim($email)))
      ->whereIn('role', ['manager', 'finance'])
      ->where('status', 'active')
      ->first();
    if (!$member || !$member->password || !Hash::check($password, $member->password)) {
      return null;
    }
    $owner = Organizer::query()->find($member->organizer_id);

    return $owner && (int) $owner->status === 1 ? $member : null;
  }
}
