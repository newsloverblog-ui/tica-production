<?php

namespace App\Support\Integrations;

/**
 * Every outside service TICA needs, described once. Admin > Integrations is
 * built from this list; RuntimeConfiguration applies the saved values.
 *
 * Field "source":
 *   store            platform_integrations.settings[<field>] (encrypted)
 *   gateway:<kw>.<k> online_gateways (<kw>).information[<k>] (encrypted)
 *   basic.<column>   basic_settings.<column> (secrets encrypted by the model)
 *   platform.<key>   basic_settings.tica_platform[<key>]
 * "enabled": the same kinds, or "always".
 * "config": the Laravel config key a store value overrides when it is set.
 * "env": the server variable used when nothing is saved here.
 * "apply_after_test": saved values only take effect after a passing test,
 *   for services where a wrong value would break the site (email, storage).
 */
class IntegrationRegistry
{
  public const GROUPS = [
    'payments' => 'Payments',
    'payouts' => 'Payouts',
    'messaging' => 'Email, SMS and push',
    'signin' => 'Sign-in and security',
    'storage' => 'Files and images',
  ];

  /** @return array<string, array<string, mixed>> */
  public static function all(): array
  {
    return [
      'paystack' => [
        'group' => 'payments', 'name' => 'Paystack',
        'summary' => 'Card, bank transfer and USSD payments at checkout. The same key sends organiser payouts when Paystack is the payout provider.',
        'enabled' => 'gateway:paystack',
        'fields' => [
          ['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'secret', 'source' => 'gateway:paystack.key', 'required' => true, 'help' => 'Paystack dashboard → Settings → API Keys & Webhooks. Starts with sk_live_ (or sk_test_ while testing).', 'pattern' => '/^sk_(live|test)_[A-Za-z0-9]{20,}$/'],
        ],
        'urls' => [['label' => 'Webhook URL (paste into Paystack → Settings → API Keys & Webhooks)', 'route' => 'paystack.webhook']],
        'notes' => ['env_override' => ['PAYSTACK_SECRET_KEY', 'The server variable PAYSTACK_SECRET_KEY is set, so payouts use that key instead of the one saved here. Remove it on Vercel to manage the key only here.']],
      ],
      'flutterwave' => [
        'group' => 'payments', 'name' => 'Flutterwave',
        'summary' => 'An alternative card and bank payment gateway at checkout.',
        'enabled' => 'gateway:flutterwave',
        'fields' => [
          ['name' => 'public_key', 'label' => 'Public key', 'type' => 'text', 'source' => 'gateway:flutterwave.public_key', 'required' => true, 'help' => 'Flutterwave dashboard → Settings → API keys.'],
          ['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'secret', 'source' => 'gateway:flutterwave.secret_key', 'required' => true],
          ['name' => 'webhook_hash', 'label' => 'Webhook secret hash', 'type' => 'secret', 'source' => 'store', 'config' => 'services.flutterwave.secret_hash', 'env' => 'FLUTTERWAVE_SECRET_HASH', 'required' => true, 'help' => 'Any long random text, at least 32 characters. Paste the same value into Flutterwave → Settings → Webhooks → Secret hash.', 'min' => 32],
        ],
        'urls' => [['label' => 'Webhook URL (Flutterwave → Settings → Webhooks)', 'route' => 'flutterwave.webhook']],
      ],
      'monnify' => [
        'group' => 'payments', 'name' => 'Monnify',
        'summary' => 'Bank transfer payments at checkout, and organiser payouts when Monnify is the payout provider.',
        'enabled' => 'gateway:monnify',
        'fields' => [
          ['name' => 'mode', 'label' => 'Mode', 'type' => 'select', 'source' => 'gateway:monnify.mode', 'options' => ['live' => 'Live', 'sandbox' => 'Sandbox (testing)'], 'required' => true],
          ['name' => 'api_key', 'label' => 'API key', 'type' => 'text', 'source' => 'gateway:monnify.api_key', 'required' => true, 'help' => 'Monnify dashboard → Developer → API keys & contracts.'],
          ['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'secret', 'source' => 'gateway:monnify.secret_key', 'required' => true],
          ['name' => 'contract_code', 'label' => 'Contract code', 'type' => 'text', 'source' => 'gateway:monnify.contract_code', 'required' => true],
          ['name' => 'disbursement_wallet', 'label' => 'Disbursement wallet account number', 'type' => 'text', 'source' => 'store', 'config' => 'services.monnify.disbursement_wallet', 'env' => 'MONNIFY_DISBURSEMENT_WALLET', 'help' => 'Only needed to send payouts with Monnify.'],
        ],
        'urls' => [['label' => 'Webhook URL (Monnify → Developer → Webhook URLs)', 'route' => 'monnify.webhook']],
      ],
      'payouts' => [
        'group' => 'payouts', 'name' => 'Organiser payouts',
        'summary' => 'Which provider sends money to organisers\' bank accounts, and whether payouts are sent automatically.',
        'enabled' => 'always',
        'fields' => [
          ['name' => 'provider', 'label' => 'Payout provider', 'type' => 'select', 'source' => 'platform.payout_provider', 'options' => ['paystack' => 'Paystack', 'monnify' => 'Monnify'], 'required' => true, 'help' => 'Set up the provider\'s keys above first.'],
          ['name' => 'transfers_enabled', 'label' => 'Send payouts through the provider', 'type' => 'toggle', 'source' => 'store', 'config' => 'payouts.paystack_transfers_enabled', 'env' => 'PAYSTACK_TRANSFERS_ENABLED', 'help' => 'Off: admins mark payouts as paid by hand after sending them from the bank.'],
          ['name' => 'finance_email', 'label' => 'Finance alert email', 'type' => 'email', 'source' => 'store', 'config' => 'payouts.finance_email', 'env' => 'PAYOUT_FINANCE_EMAIL', 'help' => 'Gets an email when a payout fails or needs checking.'],
        ],
      ],
      'email' => [
        'group' => 'messaging', 'name' => 'Email (SMTP)',
        'summary' => 'Tickets, receipts, sign-in codes, payout and account emails. Any SMTP service works: Zoho, Google Workspace, Brevo, Mailgun, Resend, SES.',
        'enabled' => 'basic.smtp_status', 'apply_after_test' => true,
        'fields' => [
          ['name' => 'host', 'label' => 'SMTP host', 'type' => 'text', 'source' => 'basic.smtp_host', 'required' => true, 'help' => 'For example smtp.zoho.com or smtp-relay.brevo.com.'],
          ['name' => 'port', 'label' => 'Port', 'type' => 'text', 'source' => 'basic.smtp_port', 'required' => true, 'help' => 'Usually 587 (TLS) or 465 (SSL).', 'pattern' => '/^\d{2,5}$/'],
          ['name' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'source' => 'basic.encryption', 'options' => ['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'], 'required' => true],
          ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'source' => 'basic.smtp_username', 'required' => true],
          ['name' => 'password', 'label' => 'Password or API key', 'type' => 'secret', 'source' => 'basic.smtp_password', 'required' => true],
          ['name' => 'from_mail', 'label' => 'Send from (email)', 'type' => 'email', 'source' => 'basic.from_mail', 'required' => true, 'help' => 'Use an address on a domain you verified with the email service.'],
          ['name' => 'from_name', 'label' => 'Send from (name)', 'type' => 'text', 'source' => 'basic.from_name', 'required' => true],
        ],
      ],
      'sms' => [
        'group' => 'messaging', 'name' => 'SMS (Termii)',
        'summary' => 'Ticket links by text, payout alerts for organisers who turn them on, and SMS to attendees.',
        'enabled' => 'store',
        'fields' => [
          ['name' => 'api_key', 'label' => 'API key', 'type' => 'secret', 'source' => 'store', 'config' => 'ticket_delivery.sms.api_key', 'env' => 'TERMII_API_KEY', 'required' => true, 'help' => 'Termii dashboard → API settings.'],
          ['name' => 'sender_id', 'label' => 'Sender ID', 'type' => 'text', 'source' => 'store', 'config' => 'ticket_delivery.sms.sender_id', 'env' => 'TERMII_SENDER_ID', 'required' => true, 'help' => 'The approved sender name, up to 11 letters, for example TICA.', 'max' => 11],
          ['name' => 'channel', 'label' => 'Route', 'type' => 'select', 'source' => 'store', 'config' => 'ticket_delivery.sms.channel', 'env' => 'TERMII_CHANNEL', 'options' => ['dnd' => 'DND (reaches numbers on Do-Not-Disturb)', 'generic' => 'Generic', 'whatsapp' => 'WhatsApp'], 'required' => true],
          ['name' => 'base_url', 'label' => 'API address', 'type' => 'url', 'source' => 'store', 'config' => 'ticket_delivery.sms.base_url', 'env' => 'TERMII_BASE_URL', 'help' => 'Leave as https://api.ng.termii.com unless Termii gave you another.', 'default' => 'https://api.ng.termii.com'],
        ],
        'enabled_config' => 'ticket_delivery.sms.enabled',
      ],
      'push' => [
        'group' => 'messaging', 'name' => 'Web push notifications',
        'summary' => 'Browser notifications to visitors who allow them (Admin → Push notifications).',
        'enabled' => 'store', 'generate' => 'vapid',
        'fields' => [
          ['name' => 'public_key', 'label' => 'Public key (VAPID)', 'type' => 'text', 'source' => 'store', 'config' => 'webpush.vapid.public_key', 'env' => 'VAPID_PUBLIC_KEY', 'required' => true, 'min' => 40],
          ['name' => 'private_key', 'label' => 'Private key (VAPID)', 'type' => 'secret', 'source' => 'store', 'config' => 'webpush.vapid.private_key', 'env' => 'VAPID_PRIVATE_KEY', 'required' => true, 'min' => 40],
          ['name' => 'subject', 'label' => 'Contact', 'type' => 'text', 'source' => 'store', 'config' => 'webpush.vapid.subject', 'env' => 'VAPID_SUBJECT', 'required' => true, 'help' => 'mailto:you@tica.ng or https://tica.ng', 'pattern' => '/^(mailto:[^@\s]+@[^@\s]+|https:\/\/\S+)$/'],
        ],
      ],
      'google_login' => [
        'group' => 'signin', 'name' => 'Sign in with Google',
        'summary' => 'A "Continue with Google" button for attendees and organisers on the log-in and sign-up pages.',
        'enabled' => 'basic.google_login_status',
        'fields' => [
          ['name' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'source' => 'basic.google_client_id', 'required' => true, 'help' => 'Google Cloud console → APIs & Services → Credentials → OAuth client ID (Web application).', 'pattern' => '/\.apps\.googleusercontent\.com$/'],
          ['name' => 'client_secret', 'label' => 'Client secret', 'type' => 'secret', 'source' => 'basic.google_client_secret', 'required' => true],
        ],
        'urls' => [['label' => 'Authorised redirect URI (add it to the OAuth client)', 'url' => 'login/google/callback'], ['label' => 'Authorised JavaScript origin', 'url' => '']],
      ],
      'facebook_login' => [
        'group' => 'signin', 'name' => 'Sign in with Facebook',
        'summary' => 'A "Continue with Facebook" button for attendees.',
        'enabled' => 'basic.facebook_login_status',
        'fields' => [
          ['name' => 'app_id', 'label' => 'App ID', 'type' => 'text', 'source' => 'basic.facebook_app_id', 'required' => true],
          ['name' => 'app_secret', 'label' => 'App secret', 'type' => 'secret', 'source' => 'basic.facebook_app_secret', 'required' => true],
        ],
        'urls' => [['label' => 'Valid OAuth redirect URI', 'url' => 'login/facebook/callback']],
      ],
      'recaptcha' => [
        'group' => 'signin', 'name' => 'Google reCAPTCHA',
        'summary' => 'Stops bots on sign-up, log-in and contact forms.',
        'enabled' => 'basic.google_recaptcha_status',
        'fields' => [
          ['name' => 'site_key', 'label' => 'Site key', 'type' => 'text', 'source' => 'basic.google_recaptcha_site_key', 'required' => true, 'help' => 'google.com/recaptcha/admin → v2 "I\'m not a robot". Add your domain.'],
          ['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'secret', 'source' => 'basic.google_recaptcha_secret_key', 'required' => true],
        ],
      ],
      'storage' => [
        'group' => 'storage', 'name' => 'File storage (Supabase)',
        'summary' => 'Event flyers, profile photos and other uploads. Vercel can\'t keep uploaded files, so they live here.',
        'enabled' => 'store', 'apply_after_test' => true,
        'fields' => [
          ['name' => 'url', 'label' => 'Project URL', 'type' => 'url', 'source' => 'store', 'config' => 'services.supabase.url', 'env' => 'SUPABASE_URL', 'required' => true, 'help' => 'Supabase → Project settings → API → Project URL.', 'pattern' => '/^https:\/\/[a-z0-9-]+\.supabase\.co$/'],
          ['name' => 'service_key', 'label' => 'Secret (service role) key', 'type' => 'secret', 'source' => 'store', 'config' => 'services.supabase.secret_key', 'env' => 'SUPABASE_SECRET_KEY', 'required' => true],
          ['name' => 'bucket', 'label' => 'Public bucket', 'type' => 'text', 'source' => 'store', 'config' => 'services.supabase.public_asset_bucket', 'env' => 'SUPABASE_PUBLIC_ASSET_BUCKET', 'required' => true, 'default' => 'tica-public'],
        ],
      ],
    ];
  }

  /** @return array<string, mixed>|null */
  public static function get(string $key): ?array
  {
    return self::all()[$key] ?? null;
  }
}
