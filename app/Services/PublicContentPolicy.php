<?php

namespace App\Services;

use Illuminate\Support\Str;

class PublicContentPolicy
{
    /**
     * Content fingerprints shipped by the legacy vendor demo. These are never
     * appropriate for a public Tica experience.
     */
    private const PLACEHOLDER_FRAGMENTS = [
        'lorem ipsum',
        'morbi volutpat',
        'demo@demo.',
        'example@example.',
        'example@examle.',
        'our service is free to users because vendors pay us',
        'winooski, virginia',
        'mobile , alaska',
        'colorado springs, colorado',
        '(225)571-6212',
        '(480)529-9734',
    ];

    private const VENDOR_HOST_FRAGMENTS = [
        'codecanyon',
        'kreativdev',
    ];

    private const GENERIC_SOCIAL_HOSTS = [
        'facebook.com',
        'www.facebook.com',
        'linkedin.com',
        'www.linkedin.com',
        'twitter.com',
        'www.twitter.com',
        'x.com',
        'www.x.com',
    ];

    public function containsPlaceholder(?string $value): bool
    {
        $normalized = Str::lower(trim(html_entity_decode(strip_tags((string) $value))));

        if ($normalized === '') {
            return false;
        }

        foreach (self::PLACEHOLDER_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    public function isVendorDemoUrl(?string $url): bool
    {
        $host = Str::lower((string) parse_url(trim((string) $url), PHP_URL_HOST));

        foreach (self::VENDOR_HOST_FRAGMENTS as $fragment) {
            if ($host !== '' && str_contains($host, $fragment)) {
                return true;
            }
        }

        return false;
    }

    public function isPublishableQuickLink(object $link): bool
    {
        return trim((string) ($link->title ?? '')) !== ''
            && trim((string) ($link->url ?? '')) !== ''
            && ! $this->containsPlaceholder((string) ($link->title ?? ''))
            && ! $this->isVendorDemoUrl((string) ($link->url ?? ''));
    }

    public function isPublishablePopup(object $popup): bool
    {
        return ! $this->containsPlaceholder(implode(' ', [
            (string) ($popup->name ?? ''),
            (string) ($popup->title ?? ''),
            (string) ($popup->text ?? ''),
        ])) && ! $this->isVendorDemoUrl((string) ($popup->button_url ?? ''));
    }

    public function isPublishableSocialLink(object $social): bool
    {
        $url = trim((string) ($social->url ?? ''));
        $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $this->isVendorDemoUrl($url)) {
            return false;
        }

        return ! ($path === '' && in_array($host, self::GENERIC_SOCIAL_HOSTS, true));
    }

    public function isPublishableContact(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && ! $this->containsPlaceholder($value);
    }

    public function publicBrandName(?string $value): string
    {
        $value = trim((string) $value);

        return Str::lower($value) === 'evento' || $value === '' ? 'Tica' : $value;
    }
}
