<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** The rebuilt "More tools" pages compile, use the design system and keep their working hooks. */
class OrganizerToolPagesTest extends TestCase
{
  private const PAGES = [
    'organizer/transaction.blade.php',
    'organizer/finance/index.blade.php',
    'organizer/support_ticket/index.blade.php',
    'organizer/support_ticket/create.blade.php',
    'organizer/support_ticket/messages.blade.php',
  ];

  public function test_pages_compile_to_valid_php(): void
  {
    foreach (self::PAGES as $page) {
      $php = Blade::compileString(file_get_contents(resource_path('views/' . $page)));
      $file = tempnam(sys_get_temp_dir(), 'blade') . '.php';
      file_put_contents($file, $php);
      exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
      @unlink($file);
      $this->assertSame(0, $code, $page . ': ' . implode("\n", $out));
    }
  }

  public function test_pages_use_one_page_head_and_no_breadcrumb_header(): void
  {
    foreach (self::PAGES as $page) {
      $src = file_get_contents(resource_path('views/' . $page));
      $this->assertSame(1, substr_count($src, 't-page-head'), $page);
      $this->assertStringNotContainsString('class="page-header"', $src, $page);
    }
  }

  public function test_working_hooks_are_kept(): void
  {
    $tx = file_get_contents(resource_path('views/organizer/transaction.blade.php'));
    $this->assertStringContainsString("route('organizer.transaction_exports.store')", $tx);
    $this->assertStringContainsString("route('organizer.event_booking.invoice'", $tx);

    $fin = file_get_contents(resource_path('views/organizer/finance/index.blade.php'));
    foreach (['csv', 'xlsx', 'pdf'] as $format) {
      $this->assertStringContainsString("'$format' =>", $fin);
    }
    $this->assertStringContainsString("route('organizer.finance.export'", $fin);

    $support = file_get_contents(resource_path('views/organizer/support_ticket/index.blade.php'));
    foreach (['bulk-check', 'bulk-delete', 'deleteForm', 'deleteBtn', "route('organizer.support_ticket.create')"] as $hook) {
      $this->assertStringContainsString($hook, $support, $hook);
    }

    $messages = file_get_contents(resource_path('views/organizer/support_ticket/messages.blade.php'));
    $this->assertStringContainsString('$ticket->description', $messages);
    $this->assertStringContainsString('$ticket->attachment', $messages);
    $this->assertStringNotContainsString('{{ $admin->username }}', $messages, 'Deleted admins must not crash the page.');
  }
}
