<?php

namespace Tests\Unit;

use App\Models\Organizer;
use App\Support\OrganizerInbox;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The organiser's bell: what TICA decided, shown once, owner only. */
class OrganizerInboxTest extends TestCase
{
  public function test_nothing_breaks_before_the_table_exists(): void
  {
    Schema::drop('organizer_notifications');
    OrganizerInbox::write(4, 'kyc.verified', 'You are verified');
    $this->assertSame(['unread' => 0, 'items' => []], OrganizerInbox::latest(4));
    OrganizerInbox::markAllRead(4);
    $this->assertStringContainsString('os-bell', $this->render());
  }

  public function test_messages_are_listed_newest_first_and_marked_read(): void
  {
    OrganizerInbox::write(4, 'kyc.verified', 'You are verified', 'You can now be paid.', '/organizer/kyc');
    OrganizerInbox::write(4, 'payout.success', 'Payout sent', 'NGN 5,000.00 has been paid to your bank account.', '/dashboard/payouts');
    OrganizerInbox::write(9, 'payout.success', 'Someone else', null, null);

    $inbox = OrganizerInbox::latest(4);
    $this->assertSame(2, $inbox['unread']);
    $this->assertSame(['Payout sent', 'You are verified'], array_column($inbox['items'], 'title'));

    OrganizerInbox::markAllRead(4);
    $this->assertSame(0, OrganizerInbox::latest(4)['unread']);
    $this->assertSame(1, OrganizerInbox::latest(9)['unread'], 'Other organisers are untouched.');
  }

  public function test_long_text_is_trimmed_to_fit(): void
  {
    OrganizerInbox::write(4, 'support.reply', str_repeat('T', 400), str_repeat('B', 900));
    $row = DB::table('organizer_notifications')->first();
    $this->assertSame(191, mb_strlen($row->title));
    $this->assertSame(500, mb_strlen($row->body));
  }

  public function test_owner_sees_the_bell_with_unread_count_and_team_members_do_not(): void
  {
    OrganizerInbox::write(4, 'refund.approved', 'Refund approved', 'Refund RF-1 was approved.', '/dashboard/orders');
    $html = $this->render();
    $this->assertStringContainsString('id="os-bell"', $html);
    $this->assertStringContainsString('Refund approved', $html);
    $this->assertStringContainsString('os-bell__n', $html);

    session()->put('tica_team_member', ['id' => 1, 'role' => 'finance', 'name' => 'F']);
    $this->assertStringNotContainsString('os-bell', $this->render());
  }

  public function test_admin_decisions_feed_the_bell(): void
  {
    foreach ([
      'app/Http/Controllers/BackEnd/Organizer/OrganizerKycReviewController.php' => "'kyc.'",
      'app/Http/Controllers/BackEnd/PayoutAccountController.php' => "'payout_account.'",
      'app/Http/Controllers/BackEnd/WithdrawController.php' => "'withdrawal.'",
      'app/Http/Controllers/BackEnd/TicaPromotionController.php' => "'promotion.'",
      'app/Http/Controllers/BackEnd/PaymentRefundController.php' => "'refund.approved'",
      'app/Http/Controllers/BackEnd/SupportTicketController.php' => "'support.reply'",
      'app/Jobs/SendPayoutStatusNotification.php' => "'payout.'",
    ] as $file => $kind) {
      $src = file_get_contents(base_path($file));
      $this->assertStringContainsString('OrganizerInbox::send(', $src, $file);
      $this->assertStringContainsString($kind, $src, $file);
    }
  }

  protected function setUp(): void
  {
    parent::setUp();
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
    Schema::create('organizer_payout_accounts', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('organizer_id'); $t->boolean('is_default'); $t->string('status'); });
    Schema::create('organizer_infos', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('organizer_id'); $t->string('name')->nullable(); });
    (require base_path('database/migrations/2026_11_13_200000_create_organizer_notifications_table.php'))->up();
    $organizer = new Organizer();
    $organizer->forceFill(['id' => 4, 'email' => 'o@example.com', 'username' => 'vibes', 'kyc_status' => 'verified']);
    $organizer->exists = true;
    Auth::guard('organizer')->setUser($organizer);
  }

  private function render(): string
  {
    return Blade::render('<x-shell.organizer active="overview" :spa="true" />');
  }
}
