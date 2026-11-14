<?php

namespace Tests\Unit;

use App\Support\AdminQueues;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** What organisers are waiting on TICA for: one list for the bell and the dashboard. */
class AdminQueuesTest extends TestCase
{
  public function test_every_queue_has_a_count_and_a_link(): void
  {
    $queues = AdminQueues::count();
    $keys = array_column($queues, 'key');

    foreach (['kyc', 'payout_accounts', 'withdrawals', 'refunds', 'promotions', 'pixels', 'support', 'approvals'] as $key) {
      $this->assertContains($key, $keys, $key);
    }
    foreach ($queues as $queue) {
      $this->assertIsInt($queue['count'], $queue['key']);
      $this->assertGreaterThanOrEqual(0, $queue['count']);
      $this->assertNotNull($queue['url'], $queue['key'] . ' needs a screen to open');
    }
  }

  public function test_top_bar_and_dashboard_compile(): void
  {
    foreach (['backend/partials/top-navbar.blade.php', 'backend/admin/dashboard.blade.php'] as $page) {
      $php = Blade::compileString(file_get_contents(resource_path('views/' . $page)));
      $file = tempnam(sys_get_temp_dir(), 'blade') . '.php';
      file_put_contents($file, $php);
      exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
      @unlink($file);
      $this->assertSame(0, $code, $page . ': ' . implode("\n", $out));
    }
  }

  public function test_top_bar_has_title_bell_and_account_menu(): void
  {
    $bar = file_get_contents(resource_path('views/backend/partials/top-navbar.blade.php'));
    foreach (['id="os-title"', 'ad-bell', 'AdminQueues::all()', "route('admin.edit_profile')", "route('admin.change_password')", "route('index')", "route('admin.logout')"] as $needle) {
      $this->assertStringContainsString($needle, $bar, $needle);
    }
  }
}
