<?php

namespace App\Support;

use App\Models\Withdraw;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Everything organisers are waiting on TICA for, in one place.
 *
 * Used by the admin bell, the admin sidebar badges and the admin dashboard.
 * Each queue is counted on its own so one missing table (for example before
 * a SQL file has been run) never hides the others.
 */
class AdminQueues
{
  /** @return array<int, array{key:string,label:string,count:int,url:?string}> */
  public static function all(): array
  {
    return Cache::remember('tica:admin-queues', 60, fn () => self::count());
  }

  public static function total(): int
  {
    return array_sum(array_column(self::all(), 'count'));
  }

  public static function forget(): void
  {
    Cache::forget('tica:admin-queues');
  }

  /** @return array<int, array{key:string,label:string,count:int,url:?string}> */
  public static function count(): array
  {
    $queues = [
      ['kyc', 'Identity checks', 'admin.organizer_kyc.index', fn () => self::table('organizer_kyc_submissions', fn ($q) => $q->where('status', 'pending'))],
      ['payout_accounts', 'Bank accounts to review', 'admin.payout_accounts.index', fn () => self::table('organizer_payout_accounts', fn ($q) => $q->where('status', 'needs_review'))],
      ['withdrawals', 'Withdrawals to send', 'admin.withdraw.withdraw_request', fn () => self::table('withdraws', fn ($q) => $q->whereIn('status', [Withdraw::STATUS_PENDING, Withdraw::STATUS_HELD]))],
      ['refunds', 'Refunds to approve', 'admin.event.booking', fn () => self::table('payment_refunds', fn ($q) => $q->whereIn('status', ['requested', 'approval_pending']))],
      ['promotions', 'Promotions to review', 'admin.advertise.tica_promotions', fn () => self::table('tica_promotion_campaigns', fn ($q) => $q->where('status', 'pending_review'))],
      ['pixels', 'Tracking pixels to review', 'admin.advertise.tica_promotions', fn () => self::table('tica_tracking_pixels', fn ($q) => $q->where('status', 'pending_review'))],
      ['support', 'Support requests', 'admin.support_tickets', fn () => self::table('support_tickets', fn ($q) => $q->where('status', 1))],
      ['approvals', 'Organisers to approve', 'admin.organizer_management.registered_organizer', fn () => (int) DB::table('basic_settings')->value('organizer_admin_approval') === 1 ? self::table('organizers', fn ($q) => $q->where('status', 0)) : 0],
    ];

    return array_map(fn (array $q) => [
      'key' => $q[0],
      'label' => __($q[1]),
      'count' => self::safely($q[3]),
      'url' => Route::has($q[2]) ? route($q[2]) : null,
    ], $queues);
  }

  private static function table(string $table, callable $where): int
  {
    if (!Schema::hasTable($table)) {
      return 0;
    }

    return (int) $where(DB::table($table))->count();
  }

  private static function safely(callable $count): int
  {
    try {
      return (int) $count();
    } catch (Throwable $e) {
      Log::warning('Admin queue count failed', ['error' => $e->getMessage()]);
      return 0;
    }
  }
}
