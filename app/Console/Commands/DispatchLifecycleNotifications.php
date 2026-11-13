<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\Organizer;
use App\Services\EventLifecycleNotificationService;
use App\Services\LifecycleMailService;
use App\Support\LifecycleMailCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DispatchLifecycleNotifications extends Command
{
    protected $signature = 'notifications:dispatch-lifecycle {--mode=hourly : hourly, daily, or weekly}';
    protected $description = 'Queue due buyer and organiser lifecycle notifications';

    public function handle(): int
    {
        if (!Schema::hasTable('application_mail_deliveries')) {
            $this->warn('The mail delivery ledger has not been migrated.');
            return self::FAILURE;
        }

        $mode = strtolower((string) $this->option('mode'));
        if (!in_array($mode, ['hourly', 'daily', 'weekly'], true)) {
            $this->error('Mode must be hourly, daily, or weekly.');
            return self::INVALID;
        }

        $count = $mode === 'hourly' ? $this->dispatchHourly() : $this->dispatchSummaries($mode);
        $this->info("Queued {$count} lifecycle notification(s).");
        return self::SUCCESS;
    }

    private function dispatchHourly(): int
    {
        $count = 0;
        Event::query()->with(['information', 'organizer', 'tickets'])
            ->where('status', 1)->where(function ($query) {
                $query->whereNull('lifecycle_status')->orWhere('lifecycle_status', 'scheduled');
            })->chunkById(100, function ($events) use (&$count): void {
                foreach ($events as $event) {
                    $start = app(EventLifecycleNotificationService::class)->start($event);
                    if (!$start) continue;
                    $minutes = now()->diffInMinutes($start, false);
                    if ($minutes >= 1380 && $minutes <= 1500) {
                        $count += $this->queueBuyerNotice($event, LifecycleMailCatalog::BUYER_EVENT_REMINDER, [
                            'event_name' => $event->information->title ?? 'Your event',
                            'event_date' => $start->format('D, j M Y · g:i A'),
                            'doors_open' => $start->copy()->subHour()->format('g:i A'),
                            'venue' => $this->venue($event),
                            'directions_url' => $event->latitude && $event->longitude
                                ? 'https://www.google.com/maps/search/?api=1&query=' . $event->latitude . ',' . $event->longitude
                                : null,
                            'order_url' => null,
                            'entry_rules' => 'Bring the QR ticket for each guest and keep it ready at the entrance.',
                        ], '24h:' . $start->toIso8601String());
                    }
                    if (($event->event_type === 'online' || !empty($event->is_hybrid)) && $event->online_join_url && $minutes >= 15 && $minutes <= 75) {
                        $count += $this->queueBuyerNotice($event, LifecycleMailCatalog::BUYER_ONLINE_JOIN_LINK, [
                            'event_name' => $event->information->title ?? 'Your online event',
                            'event_date' => $start->format('D, j M Y · g:i A'),
                            'join_url' => $event->online_join_url,
                            'platform_label' => 'Join event',
                        ], 'join:' . $start->toIso8601String());
                    }
                    $count += $this->inventoryAlert($event);
                }
            });

        return $count;
    }

    private function queueBuyerNotice(Event $event, string $kind, array $data, string $dedupe): int
    {
        $queued = 0;
        Booking::query()->where('event_id', $event->id)
            ->whereIn('paymentStatus', ['completed', 'free', '1'])->whereNotNull('email')
            ->chunkById(100, function ($bookings) use ($kind, $data, $dedupe, &$queued): void {
                foreach ($bookings as $booking) {
                    $payload = $data;
                    $payload['order_url'] = url('/customer/booking/details/' . $booking->id);
                    if (app(LifecycleMailService::class)->queueSafely($kind, $booking->email, $payload, $booking, [], $dedupe)) $queued++;
                }
            });
        return $queued;
    }

    private function inventoryAlert(Event $event): int
    {
        $capacity = (int) $event->tickets->where('ticket_available_type', 'limited')->sum('ticket_available');
        if ($capacity < 1 || !$event->organizer) return 0;
        $sold = (int) Booking::where('event_id', $event->id)
            ->whereIn('paymentStatus', ['completed', 'free', '1'])->sum('quantity');
        $remaining = max(0, $capacity - $sold);
        if ($remaining > max(5, (int) ceil($capacity * .1))) return 0;
        $soldOut = $remaining === 0;
        return app(LifecycleMailService::class)->queueSafely(
            LifecycleMailCatalog::ORGANIZER_INVENTORY_ALERT,
            $event->organizer->email,
            [
                'event_name' => $event->information->title ?? 'Your event',
                'sold_out' => $soldOut,
                'tickets_sold' => $sold,
                'tickets_remaining' => $remaining,
                'tickets_manage_url' => route('organizer.event.ticket'),
            ],
            $event,
            [],
            $soldOut ? 'sold-out' : 'selling-fast'
        ) ? 1 : 0;
    }

    private function dispatchSummaries(string $mode): int
    {
        $from = $mode === 'weekly' ? now()->subWeek() : now()->subDay();
        $periodKey = $mode . ':' . ($mode === 'weekly' ? now()->format('o-W') : now()->toDateString());
        $count = 0;
        Organizer::query()->where('status', 1)->whereNotNull('email')->chunkById(100, function ($organizers) use ($from, $periodKey, $mode, &$count): void {
            foreach ($organizers as $organizer) {
                // One preference controls both scheduled digests. Keeping the
                // daily and weekly sends behind the same opt-in prevents a
                // disabled setting from continuing to generate weekly mail.
                if (!$organizer->preferenceEnabled('daily_summary', true)) {
                    continue;
                }
                $sales = Booking::query()->where('organizer_id', $organizer->id)
                    ->whereIn('paymentStatus', ['completed', 'free', '1'])->where('created_at', '>=', $from);
                $orders = (clone $sales)->count();
                $tickets = (int) (clone $sales)->sum('quantity');
                $gross = (float) (clone $sales)->sum(DB::raw('price + tax'));
                $refunds = (float) Booking::where('organizer_id', $organizer->id)->where('updated_at', '>=', $from)->sum('refunded_amount');
                if (app(LifecycleMailService::class)->queueSafely(
                    LifecycleMailCatalog::ORGANIZER_SALES_SUMMARY,
                    $organizer->email,
                    [
                        'period_label' => ucfirst($mode),
                        'summary' => $orders ? 'Your events kept moving. Here is the latest performance.' : 'No new orders arrived in this period. Your dashboard is ready when you want to promote an event.',
                        'gross_amount' => 'NGN ' . number_format($gross, 2),
                        'ticket_count' => $tickets,
                        'order_count' => $orders,
                        'refund_amount' => 'NGN ' . number_format($refunds, 2),
                        'analytics_url' => route('organizer.dashboard'),
                    ],
                    $organizer,
                    [],
                    $periodKey
                )) $count++;
            }
        });
        return $count;
    }

    private function venue(Event $event): string
    {
        $parts = array_filter([
            $event->information->address ?? null,
            $event->information->city ?? null,
            $event->information->state ?? null,
        ]);
        return $parts ? implode(', ', $parts) : ($event->event_type === 'online' ? 'Online' : 'See event page');
    }
}
