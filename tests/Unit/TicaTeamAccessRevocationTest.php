<?php

namespace Tests\Unit;

use App\Http\Middleware\TicaTeamRole;
use App\Models\Organizer;
use App\Models\OrganizerTeamMember;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicaTeamAccessRevocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('organizers', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('password')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
        Schema::create('organizer_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_id');
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('role');
            $table->string('status');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Auth::guard('organizer')->logout();
        parent::tearDown();
    }

    public function test_active_team_member_keeps_only_the_role_stored_in_the_database(): void
    {
        [$request, $member] = $this->requestFor('manager');
        $request->session()->put('tica_team_member.role', 'finance');

        $response = (new TicaTeamRole())->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('manager', $request->session()->get('tica_team_member.role'));
        $this->assertTrue(Auth::guard('organizer')->check());
        $this->assertSame($member->id, $request->session()->get('tica_team_member.id'));
    }

    public function test_removed_team_member_is_logged_out_before_an_api_action_runs(): void
    {
        [$request, $member] = $this->requestFor('manager');
        $member->forceFill(['status' => 'removed'])->save();
        $called = false;

        $response = (new TicaTeamRole())->handle($request, function () use (&$called) {
            $called = true;
            return response('unsafe');
        });

        $this->assertFalse($called);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Your team access has ended. Sign in again.', $response->getData(true)['message']);
        $this->assertFalse(Auth::guard('organizer')->check());
        $this->assertFalse($request->session()->has('tica_team_member'));
    }

    public function test_workspace_pages_and_json_actions_all_validate_team_membership(): void
    {
        foreach (['tica.create', 'tica.edit', 'tica.dashboard', 'tica.checkin', 'tica.api.events.store'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertContains('tica.team', $route->gatherMiddleware(), $name);
        }

        $attendees = app('router')->getRoutes()->match(Request::create('/tica-api/organizer/attendees', 'GET'));
        $this->assertContains('tica.team', $attendees->gatherMiddleware());
    }

    /** @return array{0: Request, 1: OrganizerTeamMember} */
    private function requestFor(string $role): array
    {
        $organizer = Organizer::query()->create([
            'email' => 'owner@example.test',
            'password' => 'not-used',
            'status' => 1,
        ]);
        $member = OrganizerTeamMember::query()->create([
            'organizer_id' => $organizer->id,
            'email' => 'member@example.test',
            'role' => $role,
            'status' => 'active',
        ]);
        Auth::guard('organizer')->login($organizer);

        $request = Request::create('/tica-api/organizer/attendees', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->session()->put('tica_team_member', [
            'id' => $member->id,
            'role' => $role,
            'name' => 'Member',
        ]);

        return [$request, $member];
    }
}
