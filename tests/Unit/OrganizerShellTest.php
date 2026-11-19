<?php

namespace Tests\Unit;

use App\Models\Organizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The one organiser shell: same sidebar everywhere, only what the role can open. */
class OrganizerShellTest extends TestCase
{
  public function test_owner_sees_every_section_and_one_create_button(): void
  {
    $html = $this->render('overview');

    foreach (['Overview', 'Events', 'Registration forms', 'Orders and attendees', 'Gate check-in', 'Promote', 'Payouts', 'Team', 'Settings', 'More tools', 'Log out'] as $label) {
      $this->assertStringContainsString($label, $html, $label);
    }
    $this->assertStringNotContainsString('Customer messages', $html);
    $this->assertSame(1, substr_count($html, 'Create an event'));
    $this->assertSame(1, substr_count($html, 'aria-current="page"'), 'Exactly one active item.');
    // Door sales, Sponsorships and Plans and usage were removed from TICA.
    $this->assertStringNotContainsString('/organizer/operations/', $html);
  }

  public function test_finance_member_only_sees_what_finance_can_open(): void
  {
    session()->put('tica_team_member', ['id' => 1, 'role' => 'finance', 'name' => 'F']);
    $html = $this->render('payouts');

    $this->assertStringContainsString('Payouts', $html);
    $this->assertStringContainsString('Transactions', $html);
    $text = $this->text($html);
    foreach (['Team', 'Settings', 'Create an event', 'Promote', 'Gate check-in', 'Identity and bank'] as $hidden) {
      $this->assertStringNotContainsString($hidden, $text, $hidden);
    }
  }

  public function test_manager_cannot_see_money_or_account_items(): void
  {
    session()->put('tica_team_member', ['id' => 1, 'role' => 'manager', 'name' => 'M']);
    $text = $this->text($this->render('events'));

    $this->assertStringContainsString('Events', $text);
    $this->assertStringContainsString('Create an event', $text);
    foreach (['Payouts', 'Team', 'Settings', 'Identity and bank'] as $hidden) {
      $this->assertStringNotContainsString($hidden, $text, $hidden);
    }
  }

  public function test_sub_pages_highlight_their_parent(): void
  {
    $html = $this->render('events', 'Edit event');
    $this->assertMatchesRegularExpression('/tsh-link os-link is-active[^>]*href="\/dashboard\/events"/', $html);
    $this->assertStringContainsString('Edit event', $html);
  }

  public function test_primary_operations_are_direct_and_secondary_tools_are_grouped(): void
  {
    $html = $this->render('orders');

    $this->assertMatchesRegularExpression('/tsh-link os-link is-active[^>]*href="\/dashboard\/orders"/', $html);
    $this->assertStringContainsString('Workspace', $html);
    $this->assertStringContainsString('Growth', $html);
    $this->assertStringContainsString('Finance', $html);
    foreach (['Event setup', 'Account help'] as $group) {
      $this->assertStringContainsString('class="tsh-subgroup">'.$group, $html);
    }
    foreach (['Reports', 'Exports and access'] as $retiredGroup) {
      $this->assertStringNotContainsString('class="tsh-subgroup">'.$retiredGroup, $html);
    }
  }

  public function test_drawer_controls_are_labelled(): void
  {
    $html = $this->render('overview');
    $this->assertStringContainsString('aria-label="Open menu"', $html);
    $this->assertStringContainsString('aria-controls="os-side"', $html);
    $this->assertStringContainsString('aria-label="Account menu"', $html);
    $this->assertStringNotContainsString('View website', $html);
    $this->assertStringNotContainsString('Organiser workspace', $html);
  }

  public function test_every_organiser_page_uses_the_one_shell(): void
  {
    $legacy = file_get_contents(resource_path('views/organizer/layout.blade.php'));
    $app = file_get_contents(resource_path('views/tica-ui/app.blade.php'));
    $this->assertStringContainsString('<x-shell.organizer', $legacy);
    $this->assertStringContainsString('<x-shell.organizer', $app);
    $this->assertStringNotContainsString("organizer.partials.side-navbar", $legacy);
    $this->assertStringNotContainsString('od-side', file_get_contents(resource_path('views/tica-ui/pages/dashboard.blade.php')));
  }

  public function test_shell_is_isolated_and_the_profile_is_always_there(): void
  {
    foreach (['overview', 'events', 'payouts'] as $active) {
      $html = $this->render($active);
      $this->assertSame(1, substr_count($html, 'id="tica-shell"'), $active);
      $this->assertStringContainsString('class="tsh-org"', $html, 'Organiser profile on '.$active);
      $this->assertStringContainsString('tsh-org__nm', $html);
      $this->assertSame(1, substr_count($html, 'aria-current="page"'), $active);
    }
    $forms = $this->render('forms');
    $this->assertMatchesRegularExpression('/<details class="tsh-more"[^>]*open.*?<a class="tsh-sublink os-link is-active" href="\/organizer\/forms"/s', $forms);
    $this->assertStringNotContainsString('tsh-link os-link is-active" href="/organizer/forms"', $forms);
    // A verified organiser gets a badge next to the name, not text.
    $html = $this->render('overview');
    $this->assertStringContainsString('class="tsh-verified"', $html);
    $this->assertStringNotContainsString('>Verified organiser</span>', $html);
  }

  public function test_shell_styles_load_last_in_both_layouts(): void
  {
    $legacy = file_get_contents(resource_path('views/organizer/partials/styles.blade.php'));
    $app = file_get_contents(resource_path('views/tica-ui/app.blade.php'));
    foreach (['legacy' => $legacy, 'app' => $app] as $name => $html) {
      $shell = strrpos($html, 'tica-ui/css/shell.css');
      $this->assertNotFalse($shell, $name);
      preg_match_all('/\.css/', $html, $all, PREG_OFFSET_CAPTURE);
      $lastCss = end($all[0])[1];
      $this->assertGreaterThanOrEqual($lastCss, $shell + strlen('tica-ui/css/shell'), $name.': shell.css must be the last stylesheet.');
    }
    foreach (['tica.css', 'ds.css', 'shell.css'] as $stylesheet) {
      $this->assertStringContainsString(
        "tica_asset_version('assets/tica-ui/css/{$stylesheet}')",
        $app,
        "{$stylesheet} must use its own content fingerprint."
      );
    }
    $this->assertStringNotContainsString('.os-side{', str_replace(' ', '', file_get_contents(public_path('assets/tica-ui/css/ds.css'))));
  }

  public function test_shared_organizer_action_groups_keep_consistent_spacing(): void
  {
    $css = file_get_contents(public_path('assets/tica-ui/css/shell.css'));

    foreach (['.or-actions', '.ev-actions', '.nav-actions', '.workspace-actions', '.org-overview-actions', '.tica-quick-actions', '.t-toolbar', '.tica-report-toolbar', '.lg-actions', '.lg-range', '.fin-balance__actions', '.gl-head__actions', '.rf-actions', '.pr-form__actions', '.tsc-actions', '.ce-success-actions', '.t-modal__foot'] as $selector) {
      $this->assertStringContainsString($selector, $css, $selector);
    }
    $this->assertStringContainsString('column-gap:16px!important;row-gap:16px!important', $css);
    $this->assertStringContainsString('.or-actions{display:flex!important;flex-wrap:wrap!important}', $css);
    $this->assertStringContainsString('.or-actions .t-btn{flex:1 1 100%!important;justify-content:center}', $css);
  }

  /* ------------------------------------------------------------- helpers */

  protected function setUp(): void
  {
    parent::setUp();
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
    Schema::create('organizer_payout_accounts', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('organizer_id'); $t->boolean('is_default'); $t->string('status'); });
    Schema::create('organizer_infos', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('organizer_id'); $t->string('name')->nullable(); });
    $organizer = new Organizer();
    $organizer->forceFill(['id' => 4, 'email' => 'o@example.com', 'username' => 'vibes', 'kyc_status' => 'verified', 'verified_legal_name' => 'Vibes Events']);
    $organizer->exists = true;
    Auth::guard('organizer')->setUser($organizer);
  }

  private function render(string $active, ?string $title = null): string
  {
    return Blade::render('<x-shell.organizer :active="$active" :title="$title" :spa="true" />', compact('active', 'title'));
  }

  private function text(string $html): string
  {
    return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('/<script.*?<\/script>/s', '', $html))));
  }
}
