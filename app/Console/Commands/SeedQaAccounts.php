<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Throwaway accounts for the browser QA run (tests/e2e). Refuses to run in
 * production. One password for every QA account, taken from QA_PASSWORD.
 */
class SeedQaAccounts extends Command
{
  protected $signature = 'tica:qa-seed';

  protected $description = 'Create QA accounts (owner, manager, finance, gate, admin, a long-name rich organiser) for browser tests. Never in production.';

  public function handle(): int
  {
    if (app()->environment('production')) {
      $this->error('tica:qa-seed never runs in production.');
      return self::FAILURE;
    }
    $password = (string) env('QA_PASSWORD', '');
    if (strlen($password) < 12) {
      $this->error('Set QA_PASSWORD (12+ characters) for the QA accounts.');
      return self::FAILURE;
    }
    $hash = Hash::make($password);
    $now = now();

    // Owner with an empty organisation (no events, no sales): the "empty org" edge case.
    $owner = $this->organizer('qa-owner@tica.test', 'qa_owner', 'QA Events', $hash, 0);
    // A second organiser with a very long name and ₦100,000,000 balance.
    $this->organizer('qa-rich@tica.test', 'qa_rich', 'The Extraordinarily Long Named Lagos Island Weekend Festival Of Music Food And Culture Organising Committee Limited', $hash, 100000000);

    foreach (['manager', 'finance'] as $role) {
      DB::table('organizer_team_members')->updateOrInsert(
        ['organizer_id' => $owner, 'email' => "qa-{$role}@tica.test"],
        ['name' => 'QA ' . ucfirst($role), 'role' => $role, 'password' => $hash, 'status' => 'active', 'accepted_at' => $now, 'created_at' => $now, 'updated_at' => $now]
      );
    }
    DB::table('scanner_staff')->updateOrInsert(
      ['email' => 'qa-gate@tica.test'],
      ['organizer_id' => $owner, 'name' => 'QA Gate', 'password' => $hash, 'role' => 'check_in', 'status' => 'active', 'auth_version' => 1, 'created_at' => $now, 'updated_at' => $now]
    );

    $admin = ['first_name' => 'QA', 'last_name' => 'Admin', 'email' => 'qa-admin@tica.test', 'password' => $hash, 'status' => 1, 'role_id' => null, 'updated_at' => $now];
    DB::table('admins')->updateOrInsert(['username' => 'qa_admin'], $admin + ['created_at' => $now]);

    $this->info('QA accounts ready.');
    return self::SUCCESS;
  }

  private function organizer(string $email, string $username, string $name, string $hash, float $amount): int
  {
    $now = now();
    $values = ['username' => $username, 'password' => $hash, 'status' => '1', 'amount' => $amount, 'email_verified_at' => $now, 'updated_at' => $now];
    if (Schema::hasColumn('organizers', 'kyc_status')) {
      $values['kyc_status'] = 'verified';
    }
    if (Schema::hasColumn('organizers', 'verified_legal_name')) {
      $values['verified_legal_name'] = $name;
    }
    DB::table('organizers')->updateOrInsert(['email' => $email], $values + ['created_at' => $now]);
    $id = (int) DB::table('organizers')->where('email', $email)->value('id');
    $language = (int) DB::table('languages')->where('is_default', 1)->value('id');
    DB::table('organizer_infos')->updateOrInsert(['organizer_id' => $id], ['name' => $name, 'language_id' => $language ?: null, 'updated_at' => $now]);

    return $id;
  }
}
