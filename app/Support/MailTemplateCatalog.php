<?php

namespace App\Support;

/**
 * The canonical inventory for admin-editable transactional email templates.
 *
 * Template values intentionally use the same {placeholder} syntax as the
 * original Evento/TICA mail_templates records. The database remains the source
 * of truth at runtime; these definitions are safe defaults for new installs.
 */
final class MailTemplateCatalog
{
    public static function definitions(): array
    {
        $signature = '<p>Regards,<br>{website_title}</p>';
        $button = static fn (string $label, string $url): string => '<p><a href="'.$url.'" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#2866d3;color:#ffffff;text-decoration:none;font-weight:600">'.$label.'</a></p>';

        return [
            'payment_failed' => self::item('Payment unsuccessful for {event_title}', '<p>Hi {customer_name},</p><p>We could not complete your payment for <strong>{event_title}</strong>.</p><p>Amount: <strong>{amount}</strong><br>Reference: {reference}<br>Reason: {reason}</p>'.$button('Try payment again', '{checkout_link}').$signature, ['customer_name','event_title','amount','reference','reason','checkout_link','website_title']),
            'payment_pending_reminder' => self::item('Complete your booking for {event_title}', '<p>Hi {customer_name},</p><p>Your checkout for <strong>{event_title}</strong> is still waiting for payment.</p><p>Amount: <strong>{amount}</strong><br>Reference: {reference}</p>'.$button('Complete checkout', '{checkout_link}').'<p>If you already paid, you can safely ignore this message.</p>'.$signature, ['customer_name','event_title','amount','reference','checkout_link','website_title']),
            'refund_initiated' => self::item('Your refund for {event_title} has started', '<p>Hi {customer_name},</p><p>We have started your refund for <strong>{event_title}</strong>.</p><p>Amount: <strong>{amount}</strong><br>Refund reference: {reference}<br>Reason: {reason}</p><p>We will email you again when the refund is complete.</p>'.$button('View refund status', '{dashboard_link}').$signature, ['customer_name','event_title','amount','reference','reason','dashboard_link','website_title']),
            'refund_completed' => self::item('Your refund for {event_title} is complete', '<p>Hi {customer_name},</p><p>Your refund for <strong>{event_title}</strong> has been completed.</p><p>Amount: <strong>{amount}</strong><br>Refund reference: {reference}</p><p>Your bank may need a few business days to display the credit.</p>'.$button('View order', '{dashboard_link}').$signature, ['customer_name','event_title','amount','reference','dashboard_link','website_title']),

            'withdraw_request_received' => self::item('Withdrawal request {reference} received', '<p>Hi {organizer_name},</p><p>We received your withdrawal request and it is being reviewed.</p><p>Amount: <strong>{amount}</strong><br>Reference: {reference}<br>Bank: {bank_name}<br>Account: {masked_account_number}</p>'.$button('View payout', '{dashboard_link}').$signature, ['organizer_name','amount','reference','bank_name','masked_account_number','dashboard_link','website_title']),
            'payout_successful' => self::item('Your payout of {amount} was sent', '<p>Hi {organizer_name},</p><p>Your payout has been sent to your verified bank account.</p><p>Amount: <strong>{amount}</strong><br>Reference: {reference}<br>Bank: {bank_name}<br>Account: {masked_account_number}</p>'.$button('View finance statement', '{dashboard_link}').$signature, ['organizer_name','amount','reference','bank_name','masked_account_number','dashboard_link','website_title']),
            'payout_failed' => self::item('Action needed: payout {reference} was not completed', '<p>Hi {organizer_name},</p><p>We could not complete your payout. The funds have been returned to your available balance where applicable.</p><p>Amount: <strong>{amount}</strong><br>Reference: {reference}<br>Reason: {reason}</p>'.$button('Review payout details', '{dashboard_link}').$signature, ['organizer_name','amount','reference','reason','dashboard_link','website_title']),
            'bank_account_changed' => self::item('Security alert: your payout account changed', '<p>Hi {organizer_name},</p><p>Your payout account was changed.</p><p>Bank: {bank_name}<br>Account: {masked_account_number}<br>Changed at: {changed_at}</p><p>If you did not make this change, secure your account immediately.</p>'.$button('Review payout account', '{dashboard_link}').$signature, ['organizer_name','bank_name','masked_account_number','changed_at','dashboard_link','website_title']),

            'new_ticket_sale' => self::item('New ticket sale for {event_title}', '<p>Hi {organizer_name},</p><p>You received a new order for <strong>{event_title}</strong>.</p><p>Tickets: {ticket_quantity}<br>Gross amount: <strong>{amount}</strong><br>Order reference: {reference}</p>'.$button('View order', '{dashboard_link}').$signature, ['organizer_name','event_title','ticket_quantity','amount','reference','dashboard_link','website_title']),
            'event_updated' => self::item('Important update for {event_title}', '<p>Hi {customer_name},</p><p>The details for <strong>{event_title}</strong> have changed.</p><p>Date: {event_date}<br>Venue: {event_venue}<br>Update: {change_summary}</p><p>Your ticket remains valid.</p>'.$button('View updated event', '{event_link}').$signature, ['customer_name','event_title','event_date','event_venue','change_summary','event_link','website_title']),
            'event_cancelled' => self::item('{event_title} has been cancelled', '<p>Hi {customer_name},</p><p><strong>{event_title}</strong> has been cancelled.</p><p>Reason: {reason}<br>Refund information: {refund_info}</p>'.$button('View refund status', '{dashboard_link}').$signature, ['customer_name','event_title','reason','refund_info','dashboard_link','website_title']),
            'event_reminder' => self::item('Tomorrow: {event_title}', '<p>Hi {customer_name},</p><p><strong>{event_title}</strong> starts in about 24 hours.</p><p>Date: {event_date}<br>Venue: {event_venue}</p><p>Have your ticket QR ready at the entrance.</p>'.$button('Open ticket and QR', '{ticket_link}').$signature, ['customer_name','event_title','event_date','event_venue','ticket_link','website_title']),
            'event_sold_out' => self::item('{event_title} is sold out', '<p>Hi {organizer_name},</p><p>Every available ticket for <strong>{event_title}</strong> has been sold.</p><p>Tickets sold: {ticket_quantity}<br>Gross sales: {amount}</p>'.$button('View event analytics', '{dashboard_link}').$signature, ['organizer_name','event_title','ticket_quantity','amount','dashboard_link','website_title']),

            'welcome_customer' => self::item('Welcome to {website_title}', '<p>Hi {customer_name},</p><p>Welcome to {website_title}. Your account is ready, so you can keep tickets, orders and saved events in one place.</p>'.$button('Open your dashboard', '{dashboard_link}').$signature, ['customer_name','dashboard_link','website_title']),
            'welcome_organizer' => self::item('Welcome to {website_title} — confirm your email', '<p>Hi {organizer_name},</p><p>Welcome to {website_title}. Confirm your email to start creating events and preparing your payout account.</p>'.$button('Confirm email', '{verification_link}').$signature, ['organizer_name','verification_link','website_title']),
            'organizer_account_approved' => self::item('Your organizer account is approved', '<p>Hi {organizer_name},</p><p>Your organizer account has been approved.</p>'.$button('Open your dashboard', '{dashboard_link}').$signature, ['organizer_name','dashboard_link','website_title']),
            'organizer_account_rejected' => self::item('Update about your organizer account', '<p>Hi {organizer_name},</p><p>We could not approve your organizer account.</p><p>Reason: {reason}</p>'.$button('Review account details', '{dashboard_link}').$signature, ['organizer_name','reason','dashboard_link','website_title']),
            'organizer_upgrade_received' => self::item('We received your request to become an organizer', '<p>Hi {customer_name},</p><p>Thanks for asking to sell tickets on {website_title}. We are reviewing your request for <strong>{organisation}</strong> and will email you once it is decided.</p><p>You can keep using your account as normal while you wait.</p>'.$button('Open your account', '{dashboard_link}').$signature, ['customer_name','organisation','dashboard_link','website_title']),
            'organizer_upgrade_approved' => self::item('You are now an organizer on {website_title}', '<p>Hi {customer_name},</p><p>Your account is now an organizer account for <strong>{organisation}</strong>. Log in as an organizer with the same email to create your first event.</p><p>Your past tickets and orders are still in your account.</p>'.$button('Log in as an organizer', '{login_link}').$signature, ['customer_name','organisation','login_link','website_title']),
            'organizer_upgrade_rejected' => self::item('Update about your organizer request', '<p>Hi {customer_name},</p><p>We could not approve your request to become an organizer for <strong>{organisation}</strong>.</p><p>Reason: {reason}</p><p>Your customer account is unchanged.</p>'.$button('Open your account', '{dashboard_link}').$signature, ['customer_name','organisation','reason','dashboard_link','website_title']),
            'password_changed' => self::item('Security alert: your password changed', '<p>Hi {account_name},</p><p>The password for your {website_title} account was changed at {changed_at}.</p><p>If this was not you, reset your password and contact support immediately.</p>'.$button('Secure my account', '{dashboard_link}').$signature, ['account_name','changed_at','dashboard_link','website_title']),
            'account_suspended' => self::item('Your {website_title} account has been suspended', '<p>Hi {account_name},</p><p>Your account has been suspended.</p><p>Reason: {reason}</p>'.$button('Contact support', '{support_link}').$signature, ['account_name','reason','support_link','website_title']),

            'admin_new_organizer' => self::item('New organizer: {organizer_name}', '<p>A new organizer account has been created.</p><p>Name: {organizer_name}<br>Email: {organizer_email}</p>'.$button('Review organizer', '{admin_link}').$signature, ['organizer_name','organizer_email','admin_link','website_title']),
            'admin_organizer_upgrade_request' => self::item('New organizer upgrade request: {organisation}', '<p>A customer asked to become an organizer.</p><p>Name: {customer_name}<br>Email: {customer_email}<br>Organisation: {organisation}<br>Phone: {phone}</p>'.$button('Review request', '{admin_link}').$signature, ['customer_name','customer_email','organisation','phone','admin_link','website_title']),
            'admin_new_withdrawal_request' => self::item('New withdrawal request {reference}', '<p>{organizer_name} requested a withdrawal.</p><p>Amount: <strong>{amount}</strong><br>Reference: {reference}<br>Bank: {bank_name}<br>Account: {masked_account_number}</p>'.$button('Review withdrawal', '{admin_link}').$signature, ['organizer_name','amount','reference','bank_name','masked_account_number','admin_link','website_title']),
            'admin_payment_webhook_failure' => self::item('Payment webhook needs attention', '<p>A payment webhook could not be processed safely.</p><p>Provider: {provider}<br>Event: {webhook_event}<br>Reference: {reference}<br>Reason: {reason}</p>'.$button('Open payment operations', '{admin_link}').$signature, ['provider','webhook_event','reference','reason','admin_link','website_title']),

            'support_ticket_created' => self::item('Support request #{ticket_id} received', '<p>Hi {account_name},</p><p>We received your support request: <strong>{ticket_subject}</strong>.</p><p>Ticket: #{ticket_id}</p>'.$button('View request', '{ticket_link}').$signature, ['account_name','ticket_id','ticket_subject','ticket_link','website_title']),
            'support_ticket_reply' => self::item('New reply to support request #{ticket_id}', '<p>Hi {account_name},</p><p>There is a new reply to <strong>{ticket_subject}</strong>.</p><p>{reply_excerpt}</p>'.$button('Read and reply', '{ticket_link}').$signature, ['account_name','ticket_id','ticket_subject','reply_excerpt','ticket_link','website_title']),
            'support_ticket_closed' => self::item('Support request #{ticket_id} closed', '<p>Hi {account_name},</p><p>Your support request <strong>{ticket_subject}</strong> has been closed.</p>'.$button('View request', '{ticket_link}').$signature, ['account_name','ticket_id','ticket_subject','ticket_link','website_title']),
            'admin_contact_form' => self::item('New contact message from {customer_name}', '<p>A new contact form message was received.</p><p>Name: {customer_name}<br>Email: {customer_email}<br>Subject: {message_subject}</p><p>{message_body}</p>', ['customer_name','customer_email','message_subject','message_body','website_title']),
        ];
    }

    public static function placeholders(string $type): array
    {
        return self::definitions()[$type]['placeholders'] ?? self::legacyPlaceholders()[$type] ?? ['website_title'];
    }

    public static function labels(): array
    {
        return [
            'customer_name' => 'Customer name', 'organizer_name' => 'Organizer name', 'account_name' => 'Account holder name',
            'organizer_email' => 'Organizer email', 'customer_email' => 'Customer email', 'event_title' => 'Event title',
            'event_date' => 'Event date and time', 'event_venue' => 'Event venue', 'ticket_quantity' => 'Number of tickets',
            'amount' => 'Formatted amount', 'reference' => 'Payment, order or payout reference', 'reason' => 'Reason or provider response',
            'refund_info' => 'Refund status and timing', 'bank_name' => 'Bank name', 'masked_account_number' => 'Masked bank account number',
            'changed_at' => 'Date and time of change', 'dashboard_link' => 'Relevant dashboard link', 'checkout_link' => 'Checkout recovery link',
            'event_link' => 'Public event link', 'ticket_link' => 'Ticket and QR link', 'support_link' => 'Support page link',
            'admin_link' => 'Admin review link', 'provider' => 'Payment provider', 'webhook_event' => 'Webhook event name',
            'ticket_id' => 'Support ticket number', 'ticket_subject' => 'Support ticket subject', 'ticket_link' => 'Support ticket link',
            'reply_excerpt' => 'Short reply preview', 'feedback_link' => 'Post-event feedback link', 'message_subject' => 'Contact message subject',
            'message_body' => 'Contact message body', 'change_summary' => 'Summary of event changes', 'website_title' => 'Website title',
            'organisation' => 'Organisation or brand name', 'phone' => 'Phone number', 'login_link' => 'Organizer log-in link',
        ];
    }

    public static function lifecycleTemplate(string $kind, array $data = []): ?string
    {
        return match ($kind) {
            LifecycleMailCatalog::BUYER_EVENT_REMINDER => 'event_reminder',
            LifecycleMailCatalog::BUYER_EVENT_DATE_CHANGED => 'event_updated',
            LifecycleMailCatalog::BUYER_EVENT_CANCELLED => 'event_cancelled',
            LifecycleMailCatalog::BUYER_REFUND_ISSUED => 'refund_completed',
            LifecycleMailCatalog::ORGANIZER_WELCOME => 'welcome_organizer',
            LifecycleMailCatalog::ORGANIZER_NEW_SALE => 'new_ticket_sale',
            LifecycleMailCatalog::ORGANIZER_PAYOUT_SENT => 'payout_successful',
            LifecycleMailCatalog::ORGANIZER_PAYOUT_ACCOUNT_CHANGED => 'bank_account_changed',
            LifecycleMailCatalog::ORGANIZER_VERIFICATION_APPROVED => 'organizer_account_approved',
            LifecycleMailCatalog::ORGANIZER_INVENTORY_ALERT => !empty($data['sold_out']) ? 'event_sold_out' : null,
            default => null,
        };
    }

    private static function item(string $subject, string $body, array $placeholders): array
    {
        return compact('subject', 'body', 'placeholders');
    }

    private static function legacyPlaceholders(): array
    {
        return [
            'verify_email' => ['username','verification_link','website_title'],
            'reset_password' => ['customer_name','password_reset_link','website_title'],
            'event_booking' => ['customer_name','order_id','title','transaction_id','website_title'],
            'event_booking_approved' => ['customer_name','order_id','title','website_title'],
            'event_booking_rejected' => ['customer_name','order_id','title','website_title'],
            'product_order' => ['customer_name','order_id','website_title'],
            'product_shipping' => ['customer_name','order_id','status','website_title'],
            'withdraw_approve' => ['organizer_username','withdraw_id','withdraw_amount','charge','payable_amount','withdraw_method','transaction_id','current_balance','website_title'],
            'withdraw_rejected' => ['organizer_username','withdraw_id','current_balance','website_title'],
            'balance_add' => ['username','amount','current_balance','transaction_id','website_title'],
            'balance_subtract' => ['username','amount','current_balance','transaction_id','website_title'],
        ];
    }
}
