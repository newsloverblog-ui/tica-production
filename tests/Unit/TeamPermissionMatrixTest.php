<?php

namespace Tests\Unit;

use App\Http\Middleware\TicaTeamRole;
use App\Models\Organizer;
use App\Models\OrganizerTeamMember;
use App\Support\TeamPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every organiser page and API action behind the team gate, checked for each
 * role against the rules in config/tica_team.php.
 */
class TeamPermissionMatrixTest extends TestCase
{
  /**
   * Plain-language rules, written out by hand so a config change that widens
   * a role shows up here. [route or Controller@method, section, manager, finance]
   */
  private const MATRIX = [
    // Dashboard sections
    ['tica.dashboard', 'overview', true, true],
    ['tica.dashboard', 'events', true, true],
    ['tica.dashboard', 'orders', true, true],
    ['tica.dashboard', 'promote', true, false],
    ['tica.dashboard', 'payouts', false, true],
    ['tica.dashboard', 'team', false, false],
    ['tica.dashboard', 'settings', false, false],
    // Pages
    ['tica.create', null, true, false],
    ['tica.edit', null, true, false],
    ['tica.checkin', null, true, false],
    // Events
    ['TicaOrganizerController@storeEvent', null, true, false],
    ['TicaOrganizerController@updateOrganizerEvent', null, true, false],
    ['TicaWorkspaceController@updateEvent', null, true, false],
    ['TicaWorkspaceController@changeDate', null, true, false],
    ['TicaWorkspaceController@cancel', null, true, false],
    ['TicaWorkspaceController@guest', null, true, false],
    // Orders and attendees
    ['TicaWorkspaceController@message', null, true, false],
    ['TicaWorkspaceController@resend', null, true, false],
    ['TicaWorkspaceController@attendeesCsv', null, false, true],
    ['TicaWorkspaceController@refund', null, false, true],
    // Money
    ['TicaWorkspaceController@withdraw', null, false, true],
    ['TicaWorkspaceController@saveBank', null, false, false],
    ['TicaWorkspaceController@payoutSchedule', null, false, false],
    // Team and settings: owner only
    ['TicaWorkspaceController@invite', null, false, false],
    ['TicaWorkspaceController@removeMember', null, false, false],
    ['TicaWorkspaceController@changeMemberRole', null, false, false],
    ['TicaWorkspaceController@profile', null, false, false],
    ['TicaWorkspaceController@password', null, false, false],
    ['TicaWorkspaceController@prefs', null, false, false],
    // Legacy organiser pages
    ['organizer.dashboard', null, true, true],
    ['organizer.logout', null, true, true],
    ['organizer.edit.profile', null, false, false],
    ['organizer.change.password', null, false, false],
    ['organizer.kyc.show', null, false, false],
  ];

  public function test_permission_matrix_for_every_role(): void
  {
    foreach (self::MATRIX as [$target, $section, $manager, $finance]) {
      $route = $this->route($target);
      $this->assertNotNull($route, "Route {$target} is not registered.");
      $this->assertTrue(TeamPermissions::allows($route, $section, 'owner'), "Owner should reach {$target}.");
      $this->assertSame($manager, TeamPermissions::allows($route, $section, 'manager'), "Manager on {$target} {$section}.");
      $this->assertSame($finance, TeamPermissions::allows($route, $section, 'finance'), "Finance on {$target} {$section}.");
    }
  }

  public function test_every_gated_route_is_decided_and_unmapped_routes_stay_owner_only(): void
  {
    $gated = collect(app('router')->getRoutes()->getRoutes())
      ->filter(fn (Route $route) => in_array('tica.team', $route->gatherMiddleware(), true));
    $this->assertGreaterThan(20, $gated->count(), 'Expected the organiser routes to sit behind tica.team.');

    foreach ($gated as $route) {
      $permission = TeamPermissions::forRoute($route);
      if ($permission === null) {
        foreach (['manager', 'finance', 'gate', 'unknown'] as $role) {
          $this->assertFalse(TeamPermissions::allows($route, null, $role), $route->uri() . " must be owner-only for {$role}.");
        }
      }
      if ($permission !== 'common') {
        $this->assertFalse(TeamPermissions::allows($route, null, 'gate'), $route->uri() . ' must refuse gate staff.');
      }
    }
  }

  public function test_gate_staff_session_is_rejected_by_the_middleware(): void
  {
    $this->signInMember('gate');
    $request = $this->requestFor('organizer.dashboard');

    $this->assertSame(302, (new TicaTeamRole())->handle($request, fn () => response('ok'))->getStatusCode());
    $this->assertFalse(Auth::guard('organizer')->check());
  }

  public function test_config_only_uses_known_permission_names(): void
  {
    $known = [
      'overview.view', 'events.view', 'events.manage', 'orders.view', 'orders.manage', 'orders.export',
      'attendees.message', 'checkin.run', 'promote.manage', 'promote.pay', 'reports.view', 'refunds.manage',
      'payouts.view', 'withdrawals.request', 'bank.manage', 'team.manage', 'settings.manage', 'support.use',
    ];
    $used = array_merge(
      array_merge(...array_values(array_map(fn ($p) => array_diff((array) $p, ['*']), config('tica_team.roles')))),
      array_diff(array_keys(config('tica_team.routes')), ['owner.only']),
      array_values(config('tica_team.sections')),
      array_values(config('tica_team.pages')),
      array_values(config('tica_team.actions'))
    );
    foreach (array_unique($used) as $permission) {
      $this->assertContains($permission, $known, "Unknown permission name {$permission} in config/tica_team.php.");
    }
    foreach (['manager', 'finance'] as $role) {
      foreach (['bank.manage', 'team.manage', 'settings.manage'] as $ownerOnly) {
        $this->assertFalse(TeamPermissions::can($ownerOnly, $role), "{$role} must not have {$ownerOnly}.");
      }
    }
  }

  public function test_blocked_member_gets_403_page_with_back_to_overview_link(): void
  {
    $this->signInMember('manager');
    $request = $this->requestFor('tica.dashboard', ['section' => 'payouts']);

    $response = (new TicaTeamRole())->handle($request, fn () => response('ok'));

    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('Back to Overview', $response->getContent());
  }

  public function test_blocked_member_gets_json_403_from_the_api(): void
  {
    $this->signInMember('manager');
    $request = $this->requestFor('TicaWorkspaceController@withdraw', [], true);

    $response = (new TicaTeamRole())->handle($request, fn () => response('ok'));

    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('role', json_decode($response->getContent(), true)['message']);
  }

  public function test_allowed_member_passes_through(): void
  {
    $this->signInMember('finance');
    $request = $this->requestFor('TicaWorkspaceController@withdraw', [], true);

    $this->assertSame('ok', (new TicaTeamRole())->handle($request, fn () => response('ok'))->getContent());
  }

  public function test_removed_member_loses_access_on_the_next_request(): void
  {
    $member = $this->signInMember('finance');
    $member->forceFill(['status' => 'removed'])->save();
    $request = $this->requestFor('TicaWorkspaceController@withdraw', [], true);

    $response = (new TicaTeamRole())->handle($request, fn () => response('ok'));

    $this->assertSame(401, $response->getStatusCode());
    $this->assertFalse(Auth::guard('organizer')->check());
  }

  public function test_role_change_applies_on_the_next_request(): void
  {
    $member = $this->signInMember('finance');
    $member->forceFill(['role' => 'manager'])->save();
    $request = $this->requestFor('TicaWorkspaceController@withdraw', [], true);

    $this->assertSame(403, (new TicaTeamRole())->handle($request, fn () => response('ok'))->getStatusCode());
  }

  public function test_owner_is_never_limited(): void
  {
    Auth::guard('organizer')->setUser($this->organizer());
    $request = $this->requestFor('TicaWorkspaceController@invite', [], true);

    $this->assertSame('ok', (new TicaTeamRole())->handle($request, fn () => response('ok'))->getContent());
  }

  public function test_owner_can_switch_manager_and_finance_and_it_is_logged_with_the_member(): void
  {
    $owner = $this->organizer();
    $member = OrganizerTeamMember::create(['organizer_id' => 41, 'email' => 'f@example.com', 'role' => 'finance', 'status' => 'active']);

    app(\App\Services\TicaUi\OrganizerTeam::class)->changeRole($owner, $member->id, 'manager');

    $this->assertSame('manager', $member->fresh()->role);
    $log = DB::table('audit_logs')->where('action', 'organizer.team_member_role_changed')->first();
    $this->assertNotNull($log);
    $this->assertSame(['role' => 'finance'], json_decode($log->before, true));
  }

  public function test_gate_staff_cannot_be_switched_to_an_office_role(): void
  {
    $member = OrganizerTeamMember::create(['organizer_id' => 41, 'email' => 'g@example.com', 'role' => 'gate', 'status' => 'active']);

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    app(\App\Services\TicaUi\OrganizerTeam::class)->changeRole($this->organizer(), $member->id, 'manager');
  }

  public function test_audit_entries_name_the_team_member_who_acted(): void
  {
    $this->signInMember('finance');
    $this->app['request']->setLaravelSession(session()->driver());

    \App\Services\AuditLogger::record('test.action');

    $meta = json_decode(DB::table('audit_logs')->where('action', 'test.action')->value('metadata'), true);
    $this->assertSame('finance', $meta['team_member']['role']);
  }

  /* ------------------------------------------------------------- helpers */

  protected function setUp(): void
  {
    parent::setUp();
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
    Schema::create('organizer_team_members', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('organizer_id');
      $table->string('name')->nullable();
      $table->string('email');
      $table->string('role');
      $table->text('event_ids')->nullable();
      $table->string('password')->nullable();
      $table->string('invite_token_hash')->nullable();
      $table->timestamp('invite_expires_at')->nullable();
      $table->timestamp('accepted_at')->nullable();
      $table->string('status')->default('active');
      $table->unsignedBigInteger('scanner_staff_id')->nullable();
      $table->timestamps();
    });
    Schema::create('audit_logs', function (Blueprint $table) {
      $table->id();
      $table->string('actor_type')->nullable();
      $table->unsignedBigInteger('actor_id')->nullable();
      $table->string('action');
      $table->string('subject_type')->nullable();
      $table->unsignedBigInteger('subject_id')->nullable();
      $table->text('before')->nullable();
      $table->text('after')->nullable();
      $table->text('metadata')->nullable();
      $table->string('request_id')->nullable();
      $table->string('ip_address')->nullable();
      $table->text('user_agent')->nullable();
      $table->timestamp('created_at')->nullable();
    });
  }

  protected function tearDown(): void
  {
    Auth::guard('organizer')->logout();
    parent::tearDown();
  }

  private function organizer(): Organizer
  {
    $organizer = new Organizer();
    $organizer->forceFill(['id' => 41, 'email' => 'owner@example.com', 'username' => 'owner']);
    $organizer->exists = true;

    return $organizer;
  }

  private function signInMember(string $role): OrganizerTeamMember
  {
    Auth::guard('organizer')->setUser($this->organizer());
    $member = OrganizerTeamMember::create(['organizer_id' => 41, 'name' => 'Ada', 'email' => 'ada@example.com', 'role' => $role, 'status' => 'active']);
    session()->put('tica_team_member', ['id' => $member->id, 'role' => $role, 'name' => 'Ada']);

    return $member;
  }

  private function requestFor(string $target, array $parameters = [], bool $json = false): Request
  {
    $route = $this->route($target);
    $request = Request::create('/' . ltrim($route->uri(), '/'), $route->methods()[0]);
    if ($json) {
      $request->headers->set('Accept', 'application/json');
    }
    $route->bind($request);
    foreach ($parameters as $key => $value) {
      $route->setParameter($key, $value);
    }
    $request->setRouteResolver(fn () => $route);
    $request->setLaravelSession(session()->driver());

    return $request;
  }

  private function route(string $target): ?Route
  {
    $routes = app('router')->getRoutes();
    if (!str_contains($target, '@')) {
      return $routes->getByName($target);
    }
    foreach ($routes->getRoutes() as $route) {
      if (class_basename((string) $route->getActionName()) === $target) {
        return $route;
      }
    }

    return null;
  }
}
