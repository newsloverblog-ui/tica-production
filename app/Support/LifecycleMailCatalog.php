<?php

namespace App\Support;

use InvalidArgumentException;

final class LifecycleMailCatalog
{
    public const BUYER_ORDER_CONFIRMATION = 'buyer.order_confirmation';
    public const BUYER_EVENT_REMINDER = 'buyer.event_reminder';
    public const BUYER_ONLINE_JOIN = 'buyer.online_join';
    public const BUYER_EVENT_DATE_CHANGED = 'buyer.event_date_changed';
    public const BUYER_EVENT_CANCELLED = 'buyer.event_cancelled';
    public const BUYER_REFUND_ISSUED = 'buyer.refund_issued';
    public const BUYER_TICKET_TRANSFERRED = 'buyer.ticket_transferred';
    public const BUYER_WAITLIST_AVAILABLE = 'buyer.waitlist_available';
    public const BUYER_LOGIN_CODE = 'buyer.login_code';
    public const BUYER_SAVE_TICKETS = 'buyer.save_tickets';
    public const ORGANIZER_WELCOME = 'organizer.welcome';
    public const ORGANIZER_EVENT_PUBLISHED = 'organizer.event_published';
    public const ORGANIZER_NEW_SALE = 'organizer.new_sale';
    public const ORGANIZER_SALES_SUMMARY = 'organizer.sales_summary';
    public const ORGANIZER_PAYOUT_SENT = 'organizer.payout_sent';
    public const ORGANIZER_PAYOUT_ACCOUNT_CHANGED = 'organizer.payout_account_changed';
    public const ORGANIZER_STAFF_INVITE = 'organizer.staff_invite';
    public const ORGANIZER_VERIFICATION_APPROVED = 'organizer.verification_approved';
    public const ORGANIZER_INVENTORY_ALERT = 'organizer.inventory_alert';

    public static function kinds(): array
    {
        return [
            self::BUYER_ORDER_CONFIRMATION, self::BUYER_EVENT_REMINDER, self::BUYER_ONLINE_JOIN,
            self::BUYER_EVENT_DATE_CHANGED, self::BUYER_EVENT_CANCELLED, self::BUYER_REFUND_ISSUED,
            self::BUYER_TICKET_TRANSFERRED, self::BUYER_WAITLIST_AVAILABLE, self::BUYER_LOGIN_CODE,
            self::BUYER_SAVE_TICKETS, self::ORGANIZER_WELCOME, self::ORGANIZER_EVENT_PUBLISHED,
            self::ORGANIZER_NEW_SALE, self::ORGANIZER_SALES_SUMMARY, self::ORGANIZER_PAYOUT_SENT,
            self::ORGANIZER_PAYOUT_ACCOUNT_CHANGED, self::ORGANIZER_STAFF_INVITE,
            self::ORGANIZER_VERIFICATION_APPROVED, self::ORGANIZER_INVENTORY_ALERT,
        ];
    }

    public static function compose(string $kind, array $data = []): array
    {
        if (!in_array($kind, self::kinds(), true)) {
            throw new InvalidArgumentException("Unsupported lifecycle mail kind: {$kind}");
        }

        $event = trim((string) ($data['event_title'] ?? 'Your event'));
        $firstName = trim((string) ($data['first_name'] ?? 'there'));
        $eventCard = array_filter([
            'title' => $event,
            'image' => $data['event_image'] ?? null,
            'date' => $data['event_date'] ?? null,
            'time' => $data['event_time'] ?? null,
            'venue' => $data['event_venue'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $base = [
            'kind' => $kind,
            'preheader' => '',
            'subject' => 'An update from Tica',
            'title' => 'An update from Tica',
            'intro' => "Hi {$firstName},",
            'body' => [],
            'event' => $eventCard,
            'details' => [],
            'line_items' => [],
            'tickets' => $data['tickets'] ?? [],
            'steps' => [],
            'primary_action' => null,
            'secondary_action' => null,
            'notice' => null,
            'code' => null,
            'old_date' => null,
            'new_date' => null,
            'tone' => 'default',
        ];

        $content = match ($kind) {
            self::BUYER_ORDER_CONFIRMATION => [
                'subject' => "Your tickets for {$event}",
                'preheader' => 'Your order is confirmed and your tickets are ready.',
                'title' => 'You’re going.',
                'body' => ['Your order is confirmed. Each admission has its own QR code—keep the attached tickets together or open them from your Tica account.'],
                'details' => array_filter([
                    'Order' => $data['order_reference'] ?? null,
                    'Tickets' => $data['ticket_count'] ?? null,
                    'Subtotal' => $data['subtotal'] ?? null,
                    'Booking fee (6%)' => $data['booking_fee'] ?? null,
                    'Total paid' => $data['total'] ?? null,
                ]),
                'primary_action' => self::action('Open tickets', $data['tickets_url'] ?? null),
                'secondary_action' => self::action('Add to calendar', $data['calendar_url'] ?? null),
                'notice' => 'Screenshots can be hard to find at the door. Open your tickets before leaving home.',
            ],
            self::BUYER_EVENT_REMINDER => [
                'subject' => "Tomorrow: {$event}",
                'preheader' => 'Doors, directions and entry details for tomorrow.',
                'title' => 'See you tomorrow.',
                'body' => ['Your event is 24 hours away. Here is everything you need for a smooth arrival.'],
                'details' => array_filter([
                    'Doors open' => $data['doors_time'] ?? $data['event_time'] ?? null,
                    'Entry rules' => $data['entry_rules'] ?? 'Bring your ticket QR and a valid ID.',
                    'Directions' => $data['directions'] ?? null,
                ]),
                'primary_action' => self::action('Open tickets', $data['tickets_url'] ?? null),
                'secondary_action' => self::action('Get directions', $data['directions_url'] ?? null),
                'notice' => 'Open your tickets before leaving home so they are ready even if reception is poor.',
            ],
            self::BUYER_ONLINE_JOIN => [
                'subject' => "Your personal link for {$event}",
                'preheader' => 'Your online event link is ready.',
                'title' => 'Your event is ready to join.',
                'body' => ['Use the personal link below when the event begins. It works on one device at a time and should not be shared.'],
                'primary_action' => self::action($data['platform_label'] ?? 'Join on Zoom', $data['join_url'] ?? null),
                'notice' => 'This is your personal access link. Opening it on a second device will end the first session.',
            ],
            self::BUYER_EVENT_DATE_CHANGED => [
                'subject' => "New date for {$event}",
                'preheader' => 'The event date changed; your tickets remain valid.',
                'title' => 'The date has changed.',
                'body' => ['Your tickets are still valid for the new date. If it no longer works for you, request a refund before the deadline below.'],
                'old_date' => $data['old_date'] ?? null,
                'new_date' => $data['new_date'] ?? null,
                'details' => array_filter(['Refund deadline' => $data['refund_deadline'] ?? null]),
                'primary_action' => self::action('View updated event', $data['event_url'] ?? null),
                'secondary_action' => self::action('Request a refund', $data['refund_url'] ?? null),
            ],
            self::BUYER_EVENT_CANCELLED => [
                'subject' => "{$event} has been cancelled",
                'preheader' => 'Your full refund includes the booking fee.',
                'title' => 'Event cancelled.',
                'body' => [$data['reason'] ?? 'The organiser has cancelled this event.', 'A full refund, including the booking fee, will return to your original payment method.'],
                'details' => array_filter([
                    'Order' => $data['order_reference'] ?? null,
                    'Refund amount' => $data['refund_amount'] ?? null,
                    'Expected timing' => $data['refund_timing'] ?? 'Usually 5–10 business days after processing.',
                ]),
                'primary_action' => self::action('View refund status', $data['refund_url'] ?? null),
                'tone' => 'critical',
            ],
            self::BUYER_REFUND_ISSUED => [
                'subject' => "Your refund for {$event} was issued",
                'preheader' => 'Your refund is on its way to the original payment method.',
                'title' => 'Refund issued.',
                'body' => ['We have sent your refund to the original payment method. Your bank may need a few business days to show it.'],
                'details' => array_filter([
                    'Refund amount' => $data['refund_amount'] ?? null,
                    'Refund reference' => $data['refund_reference'] ?? null,
                    'Expected by' => $data['expected_by'] ?? null,
                ]),
                'primary_action' => self::action('View order', $data['order_url'] ?? null),
                'tone' => 'success',
            ],
            self::BUYER_TICKET_TRANSFERRED => [
                'subject' => "A ticket for {$event} was transferred to you",
                'preheader' => 'The ticket is now in your name.',
                'title' => 'A ticket is yours.',
                'body' => ['The transfer is complete. The previous QR code no longer works and your new ticket is ready.'],
                'details' => array_filter(['Ticket' => $data['ticket_label'] ?? null, 'From' => $data['sender_name'] ?? null]),
                'primary_action' => self::action('Open your ticket', $data['ticket_url'] ?? null),
                'notice' => 'Keep your QR private. Tica staff will never ask you to send it by message.',
            ],
            self::BUYER_WAITLIST_AVAILABLE => [
                'subject' => "Tickets are back for {$event}",
                'preheader' => 'Your two-hour purchase window has started.',
                'title' => 'Tickets are back.',
                'body' => ['We saved your place in line. Complete checkout before the hold expires; after that, the tickets return to the waitlist.'],
                'details' => array_filter(['Held until' => $data['hold_expires_at'] ?? null, 'Ticket' => $data['ticket_label'] ?? null]),
                'primary_action' => self::action('Buy held tickets', $data['checkout_url'] ?? null),
                'notice' => 'This private link is tied to your waitlist entry and expires after two hours.',
            ],
            self::BUYER_LOGIN_CODE => [
                'subject' => 'Your one-time Tica login code',
                'preheader' => 'Use this code to finish signing in.',
                'title' => 'Your login code.',
                'body' => ['Enter this code on the Tica sign-in screen.'],
                'code' => $data['code'] ?? null,
                'details' => array_filter(['Expires in' => $data['expires_in'] ?? '10 minutes']),
                'notice' => 'Never share this code. Tica support will not ask for it.',
            ],
            self::BUYER_SAVE_TICKETS => [
                'subject' => 'Save your tickets to a Tica account',
                'preheader' => 'Keep every ticket and order in one place.',
                'title' => 'Keep your tickets close.',
                'body' => ['Create or sign in to a Tica account with this email address to save the tickets from your recent order.'],
                'details' => array_filter(['Order' => $data['order_reference'] ?? null, 'Event' => $event]),
                'primary_action' => self::action('Save tickets to my account', $data['claim_url'] ?? null),
            ],
            self::ORGANIZER_WELCOME => [
                'subject' => 'Welcome to Tica — confirm your email',
                'preheader' => 'Three steps stand between you and your first payout.',
                'title' => 'Welcome to Tica.',
                'body' => ['Confirm your email to start publishing events and preparing for your first payout.'],
                'steps' => ['Confirm your email', 'Verify your organiser identity', 'Add a verified payout account'],
                'primary_action' => self::action('Confirm email', $data['verification_url'] ?? null),
            ],
            self::ORGANIZER_EVENT_PUBLISHED => [
                'subject' => "{$event} is live",
                'preheader' => 'Your event page is published and ready to share.',
                'title' => 'Your event is live.',
                'body' => ['People can now discover your event and buy tickets. Use the share kit to start spreading the word.'],
                'primary_action' => self::action('Open share kit', $data['share_url'] ?? null),
                'secondary_action' => self::action('View event page', $data['event_url'] ?? null),
            ],
            self::ORGANIZER_NEW_SALE => [
                'subject' => "New sale for {$event}",
                'preheader' => 'A new ticket order just came in.',
                'title' => 'You made a sale.',
                'body' => ['A new paid order has been confirmed.'],
                'details' => array_filter([
                    'Order' => $data['order_reference'] ?? null,
                    'Tickets' => $data['ticket_count'] ?? null,
                    'Gross sale' => $data['gross_amount'] ?? null,
                    'Your net' => $data['net_amount'] ?? null,
                ]),
                'primary_action' => self::action('View booking', $data['booking_url'] ?? null),
                'tone' => 'success',
            ],
            self::ORGANIZER_SALES_SUMMARY => [
                'subject' => ($data['period_label'] ?? 'Your') . ' Tica sales summary',
                'preheader' => 'Sales, ticket pace and the events that need attention.',
                'title' => ($data['period_label'] ?? 'Your') . ' sales at a glance.',
                'body' => [$data['summary'] ?? 'Here is how your events performed.'],
                'details' => array_filter([
                    'Gross sales' => $data['gross_amount'] ?? null,
                    'Tickets sold' => $data['ticket_count'] ?? null,
                    'Orders' => $data['order_count'] ?? null,
                    'Refunds' => $data['refund_amount'] ?? null,
                ]),
                'notice' => $data['selling_fast_message'] ?? null,
                'primary_action' => self::action('View sales analytics', $data['analytics_url'] ?? null),
            ],
            self::ORGANIZER_PAYOUT_SENT => [
                'subject' => 'Your Tica payout was sent',
                'preheader' => 'A complete breakdown of your payout.',
                'title' => 'Payout sent.',
                'body' => ['Your payout has been sent to your verified bank account.'],
                'details' => array_filter([
                    'Gross sales' => $data['gross_amount'] ?? null,
                    'Refunds' => $data['refund_amount'] ?? null,
                    'Tica fees' => $data['fees'] ?? null,
                    'Payout amount' => $data['payout_amount'] ?? null,
                    'Bank account' => $data['bank_account'] ?? null,
                    'Reference' => $data['payout_reference'] ?? null,
                ]),
                'primary_action' => self::action('View finance statement', $data['finance_url'] ?? null),
                'tone' => 'success',
            ],
            self::ORGANIZER_PAYOUT_ACCOUNT_CHANGED => [
                'subject' => 'Security alert: payout account changed',
                'preheader' => 'Payouts are on hold for 24 hours.',
                'title' => 'Your payout account changed.',
                'body' => ['A new payout account was added to your organiser profile. For your protection, payouts are on hold for 24 hours.'],
                'details' => array_filter(['Bank' => $data['bank_name'] ?? null, 'Account' => $data['account_last4'] ?? null, 'Changed at' => $data['changed_at'] ?? null]),
                'primary_action' => self::action('This wasn’t me — lock payouts', $data['lock_url'] ?? null),
                'secondary_action' => self::action('Review payout account', $data['account_url'] ?? null),
                'tone' => 'critical',
            ],
            self::ORGANIZER_STAFF_INVITE => [
                'subject' => 'You were invited to help run an event on Tica',
                'preheader' => 'Your event staff role and sign-in details.',
                'title' => 'You’re on the event team.',
                'body' => [($data['organizer_name'] ?? 'An organiser') . ' invited you to help manage event entry.'],
                'details' => array_filter(['Role' => $data['role'] ?? null, 'Event' => $event, 'Temporary password' => $data['temporary_password'] ?? null]),
                'primary_action' => self::action('Sign in to scanner', $data['login_url'] ?? null),
                'notice' => 'Change the temporary password after your first sign-in. Do not share staff credentials.',
            ],
            self::ORGANIZER_VERIFICATION_APPROVED => [
                'subject' => 'Your Tica verification was approved',
                'preheader' => 'You can now add a payout account.',
                'title' => 'You’re verified.',
                'body' => ['Your organiser verification is complete. Add a payout account to receive event earnings.'],
                'primary_action' => self::action('Add payout account', $data['payout_account_url'] ?? null),
                'tone' => 'success',
            ],
            self::ORGANIZER_INVENTORY_ALERT => [
                'subject' => ($data['sold_out'] ?? false) ? "{$event} is sold out" : "{$event} is selling fast",
                'preheader' => ($data['sold_out'] ?? false) ? 'Every available ticket has been sold.' : 'Ticket inventory is running low.',
                'title' => ($data['sold_out'] ?? false) ? 'Sold out.' : 'Selling fast.',
                'body' => [($data['sold_out'] ?? false) ? 'Your available ticket inventory has sold out.' : 'Ticket sales have crossed your low-inventory threshold.'],
                'details' => array_filter(['Tickets sold' => $data['tickets_sold'] ?? null, 'Tickets remaining' => $data['tickets_remaining'] ?? null]),
                'primary_action' => self::action('Manage tickets', $data['tickets_manage_url'] ?? null),
                'tone' => 'success',
            ],
        };

        return array_replace($base, $content);
    }

    private static function action(string $label, ?string $url): ?array
    {
        return $url ? ['label' => $label, 'url' => $url] : null;
    }
}
