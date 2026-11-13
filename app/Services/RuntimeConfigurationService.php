<?php

namespace App\Services;

use App\Models\PaymentGateway\OnlineGateway;
use App\Models\BasicSettings\Basic;
use Illuminate\Support\Facades\Config;

class RuntimeConfigurationService
{
    /**
     * Hydrate request-scoped SDK configuration from the application's
     * authoritative settings rows. This deliberately never writes `.env` or
     * clears shared configuration caches from a web request.
     */
    public function hydrate(): void
    {
        $basic = Basic::query()->select([
            'website_title', 'timezone',
            'google_recaptcha_site_key', 'google_recaptcha_secret_key',
            'facebook_app_id', 'facebook_app_secret',
            'google_client_id', 'google_client_secret',
        ])->first();

        if ($basic) {
            $timezone = trim((string) $basic->timezone);
            if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
                Config::set('app.timezone', $timezone);
                date_default_timezone_set($timezone);
            }
            if (trim((string) $basic->website_title) !== '') {
                Config::set('app.name', trim((string) $basic->website_title));
            }
            Config::set('captcha.sitekey', (string) $basic->google_recaptcha_site_key);
            Config::set('captcha.secret', (string) $basic->google_recaptcha_secret_key);
            Config::set('services.facebook.client_id', (string) $basic->facebook_app_id);
            Config::set('services.facebook.client_secret', (string) $basic->facebook_app_secret);
            Config::set('services.facebook.redirect', $this->appUrl('login/facebook/callback'));
            Config::set('services.google.client_id', (string) $basic->google_client_id);
            Config::set('services.google.client_secret', (string) $basic->google_client_secret);
            Config::set('services.google.redirect', $this->appUrl('login/google/callback'));
        }

        $gateways = OnlineGateway::query()
            ->whereIn('keyword', ['flutterwave', 'mollie', 'stripe', 'paytm', 'xendit', 'myfatoorah'])
            ->get(['keyword', 'information']);

        foreach ($gateways as $gateway) {
            $keyword = (string) $gateway->keyword;
            $information = json_decode((string) $gateway->information, true);
            if (!is_array($information)) {
                continue;
            }

            match ((string) $keyword) {
                'flutterwave' => $this->hydrateFlutterwave($information),
                'mollie' => Config::set('mollie.key', (string) ($information['key'] ?? '')),
                'stripe' => $this->hydrateStripe($information),
                'paytm' => $this->hydratePaytm($information),
                'xendit' => Config::set(
                    'xendit.key_auth',
                    base64_encode((string) ($information['secret_key'] ?? '').':')
                ),
                'myfatoorah' => $this->hydrateMyFatoorah($information),
                default => null,
            };
        }

        // Admin > Integrations: SMS, push, storage, payout extras and SMTP.
        \App\Support\Integrations\Integrations::hydrate();
    }

    private function hydrateFlutterwave(array $information): void
    {
        Config::set('flutterwave.publicKey', (string) ($information['public_key'] ?? ''));
        Config::set('flutterwave.secretKey', (string) ($information['secret_key'] ?? ''));
    }

    private function hydrateStripe(array $information): void
    {
        Config::set('services.stripe.key', (string) ($information['key'] ?? ''));
        Config::set('services.stripe.secret', (string) ($information['secret'] ?? ''));
    }

    private function hydratePaytm(array $information): void
    {
        Config::set('services.paytm-wallet.env', (string) ($information['environment'] ?? ''));
        Config::set('services.paytm-wallet.merchant_id', (string) ($information['merchant_mid'] ?? ''));
        Config::set('services.paytm-wallet.merchant_key', (string) ($information['merchant_key'] ?? ''));
        Config::set('services.paytm-wallet.merchant_website', (string) ($information['merchant_website'] ?? ''));
        Config::set('services.paytm-wallet.industry_type', (string) ($information['industry_type'] ?? ''));
    }

    private function hydrateMyFatoorah(array $information): void
    {
        Config::set('myfatoorah.token', (string) ($information['token'] ?? ''));
        Config::set('myfatoorah.CallBackUrl', $this->appUrl('myfatoorah/callback'));
        Config::set('myfatoorah.ErrorUrl', $this->appUrl('myfatoorah/cancel'));
    }

    private function appUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
