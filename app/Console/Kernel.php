<?php

namespace App\Console;

use App\Jobs\QueueHeartbeat;
use App\Services\QueueHealthService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
  /**
   * The Artisan commands provided by your application.
   *
   * @var array
   */
  protected $commands = [
    //
  ];

  /**
   * Define the application's command schedule.
   *
   * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
   * @return void
   */
  protected function schedule(Schedule $schedule)
  {
    $schedule->call(function () {
      $health = app(QueueHealthService::class);
      foreach ($health->queues() as $queue) {
        QueueHeartbeat::dispatch($queue);
      }
    })->everyMinute()->name('queue-heartbeats')->withoutOverlapping();

    $schedule->command('ops:monitor-queues')
      ->everyFiveMinutes()
      ->withoutOverlapping();
    $schedule->command('ops:monitor-webhooks')
      ->everyFiveMinutes()
      ->withoutOverlapping();
    $schedule->command('ops:prune-webhook-receipts')
      ->dailyAt('03:20')
      ->withoutOverlapping();

    $schedule->command('payments:monitor-intents')
      ->everyFiveMinutes()
      ->withoutOverlapping();
    $schedule->command('payments:dispatch-iyzico-pending')
      ->everyTenMinutes()
      ->withoutOverlapping();
    $schedule->command('payments:dispatch-fulfillments')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('payments:process-refunds')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('payments:reconcile-refunds')
      ->everyTenMinutes()
      ->withoutOverlapping();
    $schedule->command('payments:reconcile-paystack')
      ->dailyAt('02:15')
      ->withoutOverlapping();
    $schedule->command('payments:reconcile-flutterwave')
      ->dailyAt('02:30')
      ->withoutOverlapping();
    $schedule->command('payments:reconcile-paystack-settlements')
      ->dailyAt('02:45')
      ->withoutOverlapping();
    $schedule->command('payouts:reconcile-paystack')
      ->everyTenMinutes()
      ->withoutOverlapping();
    $schedule->command('payouts:dispatch-pending')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('payouts:release-eligible --apply')
      ->dailyAt(sprintf('%02d:00', max(0, min(23, (int) config('payouts.eligibility_policy.release_hour', 10)))))
      ->timezone((string) config('payouts.eligibility_policy.timezone', 'Africa/Lagos'))
      ->when(fn () => (bool) config('payouts.automatic_release_enabled', false))
      ->withoutOverlapping();
    $schedule->command('bookings:monitor-deliveries')
      ->everyFiveMinutes()
      ->withoutOverlapping();
    $schedule->command('campaigns:dispatch-pending')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('promotions:sync')
      ->everyFiveMinutes()
      ->withoutOverlapping();
    $schedule->command('events:dispatch-waitlist')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('events:dispatch-cancellation-notices')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('tickets:dispatch-transfers')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('tickets:dispatch-resends')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('guests:dispatch-invitations')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('accounts:dispatch-verifications')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('mail:dispatch-pending')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('notifications:dispatch-lifecycle --mode=hourly')
      ->hourly()
      ->withoutOverlapping();
    $schedule->command('notifications:dispatch-lifecycle --mode=daily')
      ->dailyAt('08:00')
      ->timezone('Africa/Lagos')
      ->withoutOverlapping();
    $schedule->command('notifications:dispatch-lifecycle --mode=weekly')
      ->weeklyOn(1, '08:15')
      ->timezone('Africa/Lagos')
      ->withoutOverlapping();
    $schedule->command('scanner:prune-offline-data')
      ->dailyAt('03:15')
      ->withoutOverlapping();
    $schedule->command('exports:prune')
      ->dailyAt('03:30')
      ->withoutOverlapping();
    $schedule->command('exports:run-schedules')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('exports:dispatch-pending')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('exports:notify-ready')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('imports:dispatch-pending')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('registrations:dispatch-allocations')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('registrations:dispatch-notifications')
      ->everyMinute()
      ->withoutOverlapping();
    $schedule->command('audit:apply-retention --apply')
      ->dailyAt('04:05')
      ->when(fn () => (bool) config('audit.retention_enabled', false))
      ->withoutOverlapping();
    $schedule->command('registrations:apply-retention --apply')
      ->dailyAt('03:40')
      ->when(fn () => (bool) config('registration.retention_enabled', false))
      ->withoutOverlapping();
    $schedule->command('registrations:prune-imports')
      ->dailyAt('03:45')
      ->withoutOverlapping();
    $schedule->command('registrations:expire-invoices')
      ->everyFifteenMinutes()
      ->withoutOverlapping();
    $schedule->command('guests:apply-retention --apply')
      ->dailyAt('03:50')
      ->when(fn () => (bool) config('tickets.guest_retention_enabled', false))
      ->withoutOverlapping();
    $schedule->command('guests:prune-imports')
      ->dailyAt('03:55')
      ->withoutOverlapping();
  }

  /**
   * Register the commands for the application.
   *
   * @return void
   */
  protected function commands()
  {
    $this->load(__DIR__ . '/Commands');

    require base_path('routes/console.php');
  }
}
