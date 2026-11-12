<?php

namespace App\Support;

use App\Models\BasicSettings\Basic;
use Illuminate\Support\Facades\Schema;

/**
 * The editable parts of the public TICA pages: home page text, images and
 * sections, the main menu, the footer and the contact details. The
 * defaults are the design's own content, so the site looks the same until
 * an admin changes something.
 */
final class TicaSite
{
  public const SECTIONS = [
    'spotlight' => 'Featured events carousel',
    'categories' => 'Browse by category',
    'popular' => 'Popular events',
    'cities' => 'Going out near you',
    'organisers' => 'For organisers',
    'eticket' => 'How tickets work',
    'partners' => 'Our partners',
  ];

  public const NETWORKS = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'x' => 'X (Twitter)', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp'];

  private static ?array $cache = null;

  public static function defaults(): array
  {
    $img = fn (string $id, int $w = 1800) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w={$w}&q=72";

    return [
      'home' => [
        'hero_title' => "Events shape kulture.\nLet's build it together.",
        'hero_text' => 'Find concerts, campus nights, conferences and more across Nigeria.',
        'hero_images' => [$img('1647796310365-8741019c7ca9'), $img('1690013429722-87852aae164b'), $img('1615842046963-9eb6748cbb10'), $img('1778847195159-874f36cf4e3b')],
        'sections' => array_map(fn () => true, self::SECTIONS),
        'organisers_title' => 'You bring the people. TICA handles the tickets.',
        'organisers_text' => 'For first-time hosts and seasoned KultureCreators: set your ticket types, promote your event, and follow every sale as it comes in.',
        'organisers_points' => [
          ['title' => 'Set up quickly', 'text' => 'Add your flyer, dates and ticket types.'],
          ['title' => 'Sell anywhere', 'text' => 'Share your event link and get listed on Discover.'],
          ['title' => 'Get paid fast', 'text' => 'Follow your sales and receive fast payouts.'],
        ],
        'organisers_images' => [$img('1689864727821-e47577e88226', 1100), $img('1751749909426-ac23333e8b33', 700)],
        'eticket_title' => 'Your ticket lands on your phone in seconds.',
        'eticket_steps' => [
          ['title' => 'Choose your event', 'text' => 'Pick your ticket type and how many you need.'],
          ['title' => 'Pay securely', 'text' => 'Complete your payment in a few steps.'],
          ['title' => 'Show it at the gate', 'text' => 'Your e-ticket is ready with a QR code.'],
        ],
        'eticket_images' => [$img('1739271933163-8dcc7c8e8a3e', 1200), $img('1758611974001-2e7ad7b83432', 800)],
        'partners' => [
          ['short' => 'COH', 'name' => "Creative\nOpen House", 'logo' => ''],
          ['short' => 'NDFA', 'name' => "Nollywood DIC\nFilm Academy", 'logo' => ''],
        ],
      ],
      'menu' => [
        ['label' => 'Discover events', 'url' => '/discover'],
        ['label' => 'How TICA works', 'url' => '/how'],
        ['label' => 'Pricing', 'url' => '/pricing'],
        ['label' => 'About us', 'url' => '/about'],
        ['label' => 'Contact', 'url' => '/contact'],
      ],
      'footer' => [
        'tagline' => "Events shape kulture.\nLet's build it together.",
        'columns' => [
          ['title' => 'Explore', 'links' => [['label' => 'Discover events', 'url' => '/discover'], ['label' => 'How TICA works', 'url' => '/how'], ['label' => 'About us', 'url' => '/about']]],
          ['title' => 'Organisers', 'links' => [['label' => 'Create an event', 'url' => '/create'], ['label' => 'Pricing', 'url' => '/pricing'], ['label' => 'FAQ', 'url' => '/how#faq']]],
        ],
        'copyright' => '© {year} TICA Technologies Limited. All rights reserved.',
      ],
      'contact' => [
        'phone' => '0707 379 7999',
        'phone_hours' => 'Monday to Saturday, 9am to 6pm',
        'city' => 'Owerri, Nigeria',
        'emails' => [
          ['address' => 'hello@tica.ng', 'label' => 'General'],
          ['address' => 'tickets@tica.ng', 'label' => 'Ticket help'],
          ['address' => 'reports@tica.ng', 'label' => 'Reports'],
        ],
        'socials' => [
          ['network' => 'facebook', 'handle' => 'TICA', 'url' => 'https://www.facebook.com/people/TICA/61578202947876/'],
          ['network' => 'tiktok', 'handle' => '@tica.ng1', 'url' => 'https://www.tiktok.com/@tica.ng1'],
        ],
      ],
    ];
  }

  public static function all(): array
  {
    if (self::$cache !== null) {
      return self::$cache;
    }
    $stored = [];
    if (Schema::hasColumn('basic_settings', 'tica_site')) {
      $stored = json_decode((string) Basic::query()->value('tica_site'), true) ?: [];
    }
    $site = self::defaults();
    foreach ($site as $area => $value) {
      if (!isset($stored[$area]) || !is_array($stored[$area])) {
        continue;
      }
      // Lists replace the default list; keyed groups merge key by key.
      $site[$area] = array_is_list($value) ? $stored[$area] : array_replace($value, $stored[$area]);
    }
    $site['home']['sections'] = array_replace(array_map(fn () => true, self::SECTIONS), array_map('boolval', (array) $site['home']['sections']));

    return self::$cache = $site;
  }

  public static function get(string $area): array
  {
    return self::all()[$area];
  }

  public static function save(string $area, array $value): void
  {
    $stored = json_decode((string) Basic::query()->value('tica_site'), true) ?: [];
    $stored[$area] = $value;
    Basic::query()->update(['tica_site' => json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    self::$cache = null;
  }

  public static function reset(string $area): void
  {
    $stored = json_decode((string) Basic::query()->value('tica_site'), true) ?: [];
    unset($stored[$area]);
    Basic::query()->update(['tica_site' => json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    self::$cache = null;
  }

  /** Images an admin uploads are kept here and served from /assets/tica-ui/site. */
  public static function imageUrl(string $path): string
  {
    return preg_match('#^https?://#', $path) || str_starts_with($path, '/') ? $path : asset('assets/tica-ui/site/' . $path);
  }

  /** The phone number in a form tel: links accept. */
  public static function tel(string $phone): string
  {
    $digits = preg_replace('/[^\d+]/', '', $phone);
    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
      return '+234' . substr($digits, 1);
    }

    return $digits;
  }

  /**
   * Text an admin typed, made safe for the page: plain text with line
   * breaks, plus links, bold and italics. {fee} becomes the booking fee.
   */
  public static function richText(?string $text): string
  {
    $html = strip_tags((string) $text, '<a><b><strong><em><i><br>');
    $html = preg_replace('#<(b|strong|em|i|br)\b[^>]*>#i', '<$1>', $html);
    $html = preg_replace_callback('#<a\b([^>]*)>#i', function ($m) {
      preg_match('#href\s*=\s*(["\'])(.*?)\1#i', $m[1], $href);
      $url = trim(html_entity_decode($href[2] ?? '', ENT_QUOTES));
      if (!preg_match('#^(https?://|/|mailto:|tel:)#i', $url)) {
        return '<a>';
      }
      $external = preg_match('#^https?://#i', $url) && !str_starts_with($url, url('/'));
      return '<a href="' . e($url) . '"' . ($external ? ' target="_blank" rel="noopener"' : '') . '>';
    }, $html);
    $fee = rtrim(rtrim(number_format((float) (Basic::query()->value('booking_fee_percentage') ?? 6), 2), '0'), '.');
    $html = str_replace('{fee}', '<span data-fee-pct>' . $fee . '%</span>', $html);

    return nl2br($html, false);
  }
}
