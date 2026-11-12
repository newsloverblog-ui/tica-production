<?php

namespace App\Console\Commands;

use App\Models\BookingDelivery;
use App\Models\BookingTicketDeliveryRequest;
use App\Models\ProductOrderDelivery;
use App\Models\EventCancellationNotification;
use App\Models\EventGuestInvitation;
use App\Models\EventRegistrationNotification;
use App\Models\EventRegistrationAllocation;
use App\Models\EventRegistrationAllocationClaim;
use App\Models\EventWaitlistEntry;
use App\Models\Event\BookingTicketTransfer;
use App\Models\OrganizerEmailVerificationDelivery;
use App\Models\CustomerEmailVerificationDelivery;
use App\Models\ApplicationMailDelivery;
use App\Models\SubscriberCampaignDelivery;
use App\Models\PushNotificationCampaignDelivery;
use App\Services\IncidentAlertService;
use App\Services\SubscriberCampaignService;
use App\Services\PushNotificationCampaignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MonitorBookingDeliveries extends Command
{
    protected $signature = 'bookings:monitor-deliveries
        {--stale-minutes=30}
        {--batch-size=100 : Maximum records processed from each delivery family per run}';

    protected $description = 'Detect stuck or exhausted booking confirmation deliveries';

    private int $batchSize = 100;

    public function handle(
        IncidentAlertService $alerts,
        SubscriberCampaignService $campaigns,
        PushNotificationCampaignService $pushCampaigns
    ): int
    {
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));
        $this->batchSize = max(1, min(500, (int) $this->option('batch-size')));
        $pendingHandoffs = $this->failPendingHandoffs($alerts, $staleMinutes);

        $stale = BookingDelivery::where('status', 'processing')
            ->whereNull('alerted_at')
            ->where('processing_at', '<', now()->subMinutes($staleMinutes))
            ->limit($this->batchSize)->get();

        foreach ($stale as $delivery) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => 'Delivery processing exceeded ' . $staleMinutes . ' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('booking-delivery-stuck:' . $delivery->id, 'Booking confirmation delivery became stuck.', [
                'delivery_id' => $delivery->id,
                'booking_id' => $delivery->booking_id,
                'attempts' => $delivery->attempts,
            ], 'critical');
        }

        $exhausted = BookingDelivery::where('status', 'failed')
            ->where('attempts', '>=', 5)
            ->whereNull('alerted_at')
            ->limit($this->batchSize)->get();
        foreach ($exhausted as $delivery) {
            $delivery->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('booking-delivery-exhausted:' . $delivery->id, 'Booking confirmation delivery exhausted retries.', [
                'delivery_id' => $delivery->id,
                'booking_id' => $delivery->booking_id,
                'attempts' => $delivery->attempts,
                'failure_recorded' => filled($delivery->last_error),
            ], 'critical');
        }

        $staleTicketResends = Schema::hasTable('booking_ticket_delivery_requests')
            ? BookingTicketDeliveryRequest::where('status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleTicketResends as $delivery) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => 'Ticket resend processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('booking-ticket-resend-stuck:'.$delivery->id, 'Ticket resend delivery became stuck.', [
                'delivery_request_id' => $delivery->public_id,
                'booking_id' => $delivery->booking_id,
                'attempts' => $delivery->attempts,
            ], 'critical');
        }

        $exhaustedTicketResends = Schema::hasTable('booking_ticket_delivery_requests')
            ? BookingTicketDeliveryRequest::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedTicketResends as $delivery) {
            $delivery->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('booking-ticket-resend-exhausted:'.$delivery->id, 'Ticket resend delivery exhausted retries.', [
                'delivery_request_id' => $delivery->public_id,
                'booking_id' => $delivery->booking_id,
                'attempts' => $delivery->attempts,
                'failure_recorded' => filled($delivery->last_error),
            ], 'critical');
        }

        $staleTicketTransfers = Schema::hasTable('booking_ticket_transfers')
            && Schema::hasColumn('booking_ticket_transfers', 'delivery_status')
            ? BookingTicketTransfer::where('delivery_status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleTicketTransfers as $transfer) {
            $transfer->forceFill([
                'delivery_status' => 'failed',
                'processing_at' => null,
                'last_error' => 'Ticket transfer invitation processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('ticket-transfer-invitation-stuck:'.$transfer->id, 'Ticket transfer invitation became stuck.', [
                'transfer_id' => $transfer->id,
                'booking_ticket_id' => $transfer->booking_ticket_id,
                'attempts' => $transfer->delivery_attempts,
            ], 'critical');
        }

        $exhaustedTicketTransfers = Schema::hasTable('booking_ticket_transfers')
            && Schema::hasColumn('booking_ticket_transfers', 'delivery_status')
            ? BookingTicketTransfer::where('delivery_status', 'failed')
                ->where('delivery_attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedTicketTransfers as $transfer) {
            $transfer->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('ticket-transfer-invitation-exhausted:'.$transfer->id, 'Ticket transfer invitation exhausted retries.', [
                'transfer_id' => $transfer->id,
                'booking_ticket_id' => $transfer->booking_ticket_id,
                'attempts' => $transfer->delivery_attempts,
                'failure_recorded' => filled($transfer->last_error),
            ], 'critical');
        }

        $staleOrganizerVerifications = Schema::hasTable('organizer_email_verification_deliveries')
            ? OrganizerEmailVerificationDelivery::where('status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleOrganizerVerifications as $delivery) {
            $delivery->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'last_error' => 'Organizer email verification processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('organizer-email-verification-stuck:'.$delivery->id, 'Organizer email verification delivery became stuck.', [
                'delivery_id' => $delivery->public_id,
                'organizer_id' => $delivery->organizer_id,
                'attempts' => $delivery->attempts,
            ], 'critical');
        }

        $exhaustedOrganizerVerifications = Schema::hasTable('organizer_email_verification_deliveries')
            ? OrganizerEmailVerificationDelivery::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedOrganizerVerifications as $delivery) {
            $delivery->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('organizer-email-verification-exhausted:'.$delivery->id, 'Organizer email verification delivery exhausted retries.', [
                'delivery_id' => $delivery->public_id,
                'organizer_id' => $delivery->organizer_id,
                'attempts' => $delivery->attempts,
                'failure_recorded' => filled($delivery->last_error),
            ], 'critical');
        }

        $staleCustomerVerifications = Schema::hasTable('customer_email_verification_deliveries')
            ? CustomerEmailVerificationDelivery::where('status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleCustomerVerifications as $delivery) {
            $delivery->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'last_error' => 'Customer email verification processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('customer-email-verification-stuck:'.$delivery->id, 'Customer email verification delivery became stuck.', [
                'delivery_id' => $delivery->public_id,
                'customer_id' => $delivery->customer_id,
                'attempts' => $delivery->attempts,
            ], 'critical');
        }

        $exhaustedCustomerVerifications = Schema::hasTable('customer_email_verification_deliveries')
            ? CustomerEmailVerificationDelivery::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedCustomerVerifications as $delivery) {
            $delivery->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('customer-email-verification-exhausted:'.$delivery->id, 'Customer email verification delivery exhausted retries.', [
                'delivery_id' => $delivery->public_id,
                'customer_id' => $delivery->customer_id,
                'attempts' => $delivery->attempts,
                'failure_recorded' => filled($delivery->last_error),
            ], 'critical');
        }

        $staleApplicationMail = Schema::hasTable('application_mail_deliveries')
            ? ApplicationMailDelivery::where('status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleApplicationMail as $delivery) {
            $delivery->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'failed_at' => now(),
                'last_error' => 'Application mail delivery processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('application-mail-delivery-stuck:'.$delivery->id, 'Application mail delivery became stuck.', [
                'delivery_id' => $delivery->public_id,
                'kind' => $delivery->kind,
                'source_type' => $delivery->source_type,
                'source_id' => $delivery->source_id,
                'attempts' => $delivery->attempts,
            ], 'critical');
        }

        $exhaustedApplicationMail = Schema::hasTable('application_mail_deliveries')
            ? ApplicationMailDelivery::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedApplicationMail as $delivery) {
            $delivery->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('application-mail-delivery-exhausted:'.$delivery->id, 'Application mail delivery exhausted retries.', [
                'delivery_id' => $delivery->public_id,
                'kind' => $delivery->kind,
                'source_type' => $delivery->source_type,
                'source_id' => $delivery->source_id,
                'attempts' => $delivery->attempts,
                'failure_recorded' => filled($delivery->last_error),
            ], 'critical');
        }

        $staleSubscriberDeliveries = Schema::hasTable('subscriber_campaign_deliveries')
            ? SubscriberCampaignDelivery::where('status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleSubscriberDeliveries as $delivery) {
            $delivery->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'failed_at' => now(),
                'last_error' => 'Subscriber campaign delivery processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $campaigns->refreshStatus($delivery->campaign_id);
            $alerts->alert('subscriber-campaign-delivery-stuck:'.$delivery->id, 'Subscriber campaign delivery became stuck.', [
                'campaign_id' => $delivery->campaign_id,
                'delivery_id' => $delivery->id,
                'attempts' => $delivery->attempts,
            ], 'critical');
        }

        $exhaustedSubscriberDeliveries = Schema::hasTable('subscriber_campaign_deliveries')
            ? SubscriberCampaignDelivery::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedSubscriberDeliveries as $delivery) {
            $delivery->forceFill(['alerted_at' => now()])->save();
            $campaigns->refreshStatus($delivery->campaign_id);
            $alerts->alert('subscriber-campaign-delivery-exhausted:'.$delivery->id, 'Subscriber campaign delivery exhausted retries.', [
                'campaign_id' => $delivery->campaign_id,
                'delivery_id' => $delivery->id,
                'attempts' => $delivery->attempts,
                'failure_recorded' => filled($delivery->last_error),
            ], 'critical');
        }

        $stalePushDeliveries = Schema::hasTable('push_notification_campaign_deliveries')
            ? PushNotificationCampaignDelivery::where('status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($stalePushDeliveries as $delivery) {
            $delivery->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'failed_at' => now(),
                'last_error' => 'Push campaign delivery processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $pushCampaigns->refreshStatus($delivery->campaign_id);
            $alerts->alert('push-campaign-delivery-stuck:'.$delivery->id, 'Push campaign delivery became stuck.', [
                'campaign_id' => $delivery->campaign_id,
                'delivery_id' => $delivery->id,
                'guest_id' => $delivery->guest_id,
                'attempts' => $delivery->attempts,
            ], 'critical');
        }

        $exhaustedPushDeliveries = Schema::hasTable('push_notification_campaign_deliveries')
            ? PushNotificationCampaignDelivery::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedPushDeliveries as $delivery) {
            $delivery->forceFill(['alerted_at' => now()])->save();
            $pushCampaigns->refreshStatus($delivery->campaign_id);
            $alerts->alert('push-campaign-delivery-exhausted:'.$delivery->id, 'Push campaign delivery exhausted retries.', [
                'campaign_id' => $delivery->campaign_id,
                'delivery_id' => $delivery->id,
                'guest_id' => $delivery->guest_id,
                'attempts' => $delivery->attempts,
                'failure_recorded' => filled($delivery->last_error),
            ], 'critical');
        }

        $staleProductOrders = ProductOrderDelivery::where('status', 'processing')
            ->whereNull('alerted_at')
            ->where('processing_at', '<', now()->subMinutes($staleMinutes))
            ->limit($this->batchSize)->get();
        foreach ($staleProductOrders as $delivery) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => 'Delivery processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('product-order-delivery-stuck:'.$delivery->id, 'Product-order confirmation delivery became stuck.', [
                'delivery_id' => $delivery->id,
                'product_order_id' => $delivery->product_order_id,
                'attempts' => $delivery->attempts,
            ], 'critical');
        }

        $exhaustedProductOrders = ProductOrderDelivery::where('status', 'failed')
            ->where('attempts', '>=', 5)
            ->whereNull('alerted_at')
            ->limit($this->batchSize)->get();
        foreach ($exhaustedProductOrders as $delivery) {
            $delivery->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('product-order-delivery-exhausted:'.$delivery->id, 'Product-order confirmation delivery exhausted retries.', [
                'delivery_id' => $delivery->id,
                'product_order_id' => $delivery->product_order_id,
                'attempts' => $delivery->attempts,
                'failure_recorded' => filled($delivery->last_error),
            ], 'critical');
        }

        $staleCancellationNotices = Schema::hasTable('event_cancellation_notifications')
            ? EventCancellationNotification::where('status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleCancellationNotices as $delivery) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => 'Cancellation notice processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('event-cancellation-notice-stuck:'.$delivery->id, 'Event cancellation notice became stuck.', [
                'notification_id' => $delivery->id,
                'cancellation_request_id' => $delivery->event_cancellation_request_id,
                'booking_id' => $delivery->booking_id,
                'attempts' => $delivery->attempts,
            ], 'critical');
        }

        $exhaustedCancellationNotices = Schema::hasTable('event_cancellation_notifications')
            ? EventCancellationNotification::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedCancellationNotices as $delivery) {
            $delivery->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('event-cancellation-notice-exhausted:'.$delivery->id, 'Event cancellation notice exhausted retries.', [
                'notification_id' => $delivery->id,
                'cancellation_request_id' => $delivery->event_cancellation_request_id,
                'booking_id' => $delivery->booking_id,
                'attempts' => $delivery->attempts,
                'failure_recorded' => filled($delivery->last_error),
            ], 'critical');
        }

        $staleWaitlistNotices = Schema::hasTable('event_waitlist_entries')
            ? EventWaitlistEntry::where('status', 'notification_pending')
                ->whereNotNull('processing_at')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleWaitlistNotices as $entry) {
            $entry->forceFill([
                'status' => 'failed',
                'last_error' => 'Waitlist notice processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('event-waitlist-notice-stuck:'.$entry->id, 'Event waitlist notice became stuck.', [
                'waitlist_entry_id' => $entry->id,
                'event_id' => $entry->event_id,
                'ticket_id' => $entry->ticket_id,
                'attempts' => $entry->attempts,
            ], 'critical');
        }

        $exhaustedWaitlistNotices = Schema::hasTable('event_waitlist_entries')
            ? EventWaitlistEntry::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedWaitlistNotices as $entry) {
            $entry->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('event-waitlist-notice-exhausted:'.$entry->id, 'Event waitlist notice exhausted retries.', [
                'waitlist_entry_id' => $entry->id,
                'event_id' => $entry->event_id,
                'ticket_id' => $entry->ticket_id,
                'attempts' => $entry->attempts,
                'failure_recorded' => filled($entry->last_error),
            ], 'critical');
        }

        $staleGuestInvitations = Schema::hasTable('event_guest_invitations')
            ? EventGuestInvitation::where('delivery_status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleGuestInvitations as $invitation) {
            $invitation->forceFill([
                'delivery_status' => 'failed',
                'processing_at' => null,
                'last_error' => 'Guest invitation processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('event-guest-invitation-stuck:'.$invitation->id, 'Event guest invitation became stuck.', [
                'invitation_id' => $invitation->id,
                'event_id' => $invitation->event_id,
                'attempts' => $invitation->delivery_attempts,
            ], 'critical');
        }

        $exhaustedGuestInvitations = Schema::hasTable('event_guest_invitations')
            ? EventGuestInvitation::where('delivery_status', 'failed')
                ->where('delivery_attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedGuestInvitations as $invitation) {
            $invitation->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('event-guest-invitation-exhausted:'.$invitation->id, 'Event guest invitation exhausted retries.', [
                'invitation_id' => $invitation->id,
                'event_id' => $invitation->event_id,
                'attempts' => $invitation->delivery_attempts,
                'failure_recorded' => filled($invitation->last_error),
            ], 'critical');
        }

        $staleRegistrationNotices = Schema::hasTable('event_registration_notifications')
            ? EventRegistrationNotification::where('status', 'processing')
                ->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($staleRegistrationNotices as $notification) {
            $notification->forceFill([
                'status' => 'failed',
                'processing_at' => null,
                'last_error' => 'Registration notice processing exceeded '.$staleMinutes.' minutes.',
                'alerted_at' => now(),
            ])->save();
            $alerts->alert('event-registration-notice-stuck:'.$notification->id, 'Event registration notice became stuck.', [
                'notification_id' => $notification->id,
                'submission_id' => $notification->submission_id,
                'event_id' => $notification->event_id,
                'kind' => $notification->kind,
                'attempts' => $notification->attempts,
            ], 'critical');
        }

        $exhaustedRegistrationNotices = Schema::hasTable('event_registration_notifications')
            ? EventRegistrationNotification::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->whereNull('alerted_at')
                ->limit($this->batchSize)->get()
            : collect();
        foreach ($exhaustedRegistrationNotices as $notification) {
            $notification->forceFill(['alerted_at' => now()])->save();
            $alerts->alert('event-registration-notice-exhausted:'.$notification->id, 'Event registration notice exhausted retries.', [
                'notification_id' => $notification->id,
                'submission_id' => $notification->submission_id,
                'event_id' => $notification->event_id,
                'kind' => $notification->kind,
                'attempts' => $notification->attempts,
                'failure_recorded' => filled($notification->last_error),
            ], 'critical');
        }

        $allocationDeliveries = collect();
        foreach ([EventRegistrationAllocation::class, EventRegistrationAllocationClaim::class] as $model) {
            $table = (new $model)->getTable();
            if (!Schema::hasTable($table)) continue;
            $stuck = $model::where('delivery_status', 'processing')->whereNull('alerted_at')
                ->where('processing_at', '<', now()->subMinutes($staleMinutes))
                ->limit($this->batchSize)->get();
            foreach ($stuck as $delivery) {
                $delivery->forceFill([
                    'delivery_status' => 'failed', 'processing_at' => null,
                    'last_error' => 'Allocation delivery processing exceeded '.$staleMinutes.' minutes.', 'alerted_at' => now(),
                ])->save();
                $alerts->alert('registration-allocation-delivery-stuck:'.$table.':'.$delivery->id,
                    'Registration allocation delivery became stuck.', ['record_type' => $table, 'record_id' => $delivery->id,
                        'event_id' => $delivery->event_id, 'attempts' => $delivery->delivery_attempts], 'critical');
            }
            $exhaustedAllocation = $model::where('delivery_status', 'failed')->where('delivery_attempts', '>=', 5)
                ->whereNull('alerted_at')->limit($this->batchSize)->get();
            foreach ($exhaustedAllocation as $delivery) {
                $delivery->forceFill(['alerted_at' => now()])->save();
                $alerts->alert('registration-allocation-delivery-exhausted:'.$table.':'.$delivery->id,
                    'Registration allocation delivery exhausted retries.', ['record_type' => $table, 'record_id' => $delivery->id,
                        'event_id' => $delivery->event_id, 'attempts' => $delivery->delivery_attempts,
                        'failure_recorded' => filled($delivery->last_error)], 'critical');
            }
            $allocationDeliveries = $allocationDeliveries->concat($exhaustedAllocation);
        }

        $this->line(sprintf(
            'Pending handoff: %d | Booking stuck: %d | Booking exhausted: %d | Ticket resend stuck: %d | Ticket resend exhausted: %d | Ticket transfer stuck: %d | Ticket transfer exhausted: %d | Organizer verification stuck: %d | Organizer verification exhausted: %d | Customer verification stuck: %d | Customer verification exhausted: %d | Application mail stuck: %d | Application mail exhausted: %d | Subscriber campaign stuck: %d | Subscriber campaign exhausted: %d | Push campaign stuck: %d | Push campaign exhausted: %d | Product stuck: %d | Product exhausted: %d | Cancellation notice stuck: %d | Cancellation notice exhausted: %d | Waitlist notice stuck: %d | Waitlist notice exhausted: %d | Guest invitation stuck: %d | Guest invitation exhausted: %d | Registration notice stuck: %d | Registration notice exhausted: %d | Allocation delivery exhausted: %d',
            $pendingHandoffs,
            $stale->count(),
            $exhausted->count(),
            $staleTicketResends->count(),
            $exhaustedTicketResends->count(),
            $staleTicketTransfers->count(),
            $exhaustedTicketTransfers->count(),
            $staleOrganizerVerifications->count(),
            $exhaustedOrganizerVerifications->count(),
            $staleCustomerVerifications->count(),
            $exhaustedCustomerVerifications->count(),
            $staleApplicationMail->count(),
            $exhaustedApplicationMail->count(),
            $staleSubscriberDeliveries->count(),
            $exhaustedSubscriberDeliveries->count(),
            $stalePushDeliveries->count(),
            $exhaustedPushDeliveries->count(),
            $staleProductOrders->count(),
            $exhaustedProductOrders->count(),
            $staleCancellationNotices->count(),
            $exhaustedCancellationNotices->count(),
            $staleWaitlistNotices->count(),
            $exhaustedWaitlistNotices->count(),
            $staleGuestInvitations->count(),
            $exhaustedGuestInvitations->count(),
            $staleRegistrationNotices->count(),
            $exhaustedRegistrationNotices->count(),
            $allocationDeliveries->count()
        ));

        return $exhausted->isEmpty()
            && $exhaustedTicketResends->isEmpty()
            && $exhaustedTicketTransfers->isEmpty()
            && $exhaustedOrganizerVerifications->isEmpty()
            && $exhaustedCustomerVerifications->isEmpty()
            && $exhaustedApplicationMail->isEmpty()
            && $exhaustedSubscriberDeliveries->isEmpty()
            && $exhaustedPushDeliveries->isEmpty()
            && $exhaustedProductOrders->isEmpty()
            && $exhaustedCancellationNotices->isEmpty()
            && $exhaustedWaitlistNotices->isEmpty()
            && $exhaustedGuestInvitations->isEmpty()
            && $exhaustedRegistrationNotices->isEmpty()
            && $allocationDeliveries->isEmpty()
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function failPendingHandoffs(IncidentAlertService $alerts, int $staleMinutes): int
    {
        $definitions = [
            [BookingDelivery::class, 'status', 'pending', 'booking-delivery'],
            [BookingTicketDeliveryRequest::class, 'status', 'pending', 'booking-ticket-resend'],
            [ProductOrderDelivery::class, 'status', 'pending', 'product-order-delivery'],
            [EventCancellationNotification::class, 'status', 'pending', 'event-cancellation-notice'],
            [EventRegistrationNotification::class, 'status', 'pending', 'event-registration-notice'],
            [OrganizerEmailVerificationDelivery::class, 'status', 'pending', 'organizer-email-verification'],
            [CustomerEmailVerificationDelivery::class, 'status', 'pending', 'customer-email-verification'],
            [ApplicationMailDelivery::class, 'status', 'pending', 'application-mail-delivery'],
            [BookingTicketTransfer::class, 'delivery_status', 'pending', 'ticket-transfer-invitation'],
            [EventGuestInvitation::class, 'delivery_status', 'pending', 'event-guest-invitation'],
            [EventRegistrationAllocation::class, 'delivery_status', 'pending', 'registration-allocation-delivery'],
            [EventRegistrationAllocationClaim::class, 'delivery_status', 'pending', 'registration-allocation-claim-delivery'],
            [EventWaitlistEntry::class, 'status', 'notification_pending', 'event-waitlist-notice'],
        ];
        $count = 0;

        foreach ($definitions as [$model, $stateColumn, $pendingState, $kind]) {
            $table = (new $model)->getTable();
            if (!Schema::hasTable($table)
                || !Schema::hasColumn($table, $stateColumn)
                || !Schema::hasColumn($table, 'alerted_at')
                || !Schema::hasColumn($table, 'updated_at')) {
                continue;
            }

            // Modern ledgers own initial-handoff retries and stale incidents in
            // their minute-level dispatcher. Converting them to failed here
            // would stop the recovery command from restoring a queue outage.
            if (Schema::hasColumn($table, 'dispatch_attempts')
                && Schema::hasColumn($table, 'dispatch_last_attempt_at')) {
                continue;
            }

            $query = $model::query()
                ->where($stateColumn, $pendingState)
                ->whereNull('alerted_at')
                ->where('updated_at', '<', now()->subMinutes($staleMinutes));
            if (Schema::hasColumn($table, 'processing_at')) {
                $query->whereNull('processing_at');
            }

            foreach ($query->limit($this->batchSize)->get() as $delivery) {
                $updates = [
                    $stateColumn => 'failed',
                    'alerted_at' => now(),
                ];
                if (Schema::hasColumn($table, 'last_error')) {
                    $updates['last_error'] = 'Delivery remained pending for more than '.$staleMinutes
                        .' minutes; queue handoff or worker health must be verified.';
                }
                if (Schema::hasColumn($table, 'processing_at')) {
                    $updates['processing_at'] = null;
                }
                if (Schema::hasColumn($table, 'failed_at')) {
                    $updates['failed_at'] = now();
                }

                $delivery->forceFill($updates)->save();
                $alerts->alert('delivery-handoff-pending:'.$table.':'.$delivery->getKey(),
                    'Durable delivery did not start within the expected window.', [
                        'record_type' => $kind,
                        'record_id' => $delivery->getKey(),
                    ], 'critical');
                $count++;
            }
        }

        return $count;
    }
}
