<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Event\Booking;
use App\Models\TicaTrackingLink;
use App\Models\TicaTrackingPixel;
use App\Models\TicaPromotionCampaign;
use App\Services\TicaUi\EventCatalog;
use App\Services\TicaUi\TicaUiPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Serves the TICA design shell. Every public page of the design lives in one
 * document; the server picks the first page and supplies live data, and the
 * design's own router takes over navigation from there.
 */
class TicaUiController extends Controller
{
  private const TITLES = [
    'home' => 'Home', 'discover' => 'Discover events', 'how' => 'How TICA works', 'pricing' => 'Pricing',
    'about' => 'About us', 'contact' => 'Contact', 'create' => 'Create an event', 'policies' => 'Terms and policies',
    'login' => 'Log in', 'account' => 'Your tickets', 'checkin' => 'Gate check-in', 'tickets' => 'My tickets',
    'dashboard' => 'Dashboard', 'signup' => 'Create your account', 'forgot' => 'Reset your password', 'join' => 'Join',
    'missing' => 'Page not found', 'content' => 'TICA',
  ];

  public function page(Request $request, TicaUiPayload $payload, string $page = 'home', ?string $section = null)
  {
    // Laravel hands route values over by position, so /dashboard/{section} with a
    // 'page' default arrives swapped. Read both by name instead.
    $page = (string) ($request->route('page') ?? 'home');
    $section = $request->route('section');
    $path = $page === 'home' ? '' : $page . ($section ? '/' . $section : '');

    return $this->render($request, $payload, $page, $path);
  }

  public function event(Request $request, TicaUiPayload $payload, string $slug, int $id)
  {
    $this->recordTrackingClick($request, $id);

    return $this->eventPage($request, $payload, 'event', $slug, $id);
  }

  public function checkout(Request $request, TicaUiPayload $payload, string $slug, int $id)
  {
    return $this->eventPage($request, $payload, 'checkout', $slug, $id);
  }

  /** The order confirmation, reached from the signed booking-complete link. */
  public function order(Request $request, TicaUiPayload $payload, Booking $booking)
  {
    $catalog = new EventCatalog($this->getLanguage());
    $event = $catalog->find((int) $booking->event_id) ?? $catalog->find((int) $booking->event_id, true);
    abort_unless($event, 404);

    return $this->render($request, $payload, 'ticket', 'ticket/' . $event['slug'], 200, $event, ['order' => $payload->order($booking)])
      ->header('Cache-Control', 'no-store, private')
      ->header('Pragma', 'no-cache');
  }

  /** A team invite opens the design's sign-up page, set up to accept it. */
  public function teamAccept(Request $request, TicaUiPayload $payload, string $token)
  {
    $member = app(\App\Services\TicaUi\OrganizerTeam::class)->invitation($token);
    $orgName = $member ? (\App\Models\OrganizerInfo::query()->where('organizer_id', $member->organizer_id)->value('name') ?: 'an organiser') : null;
    $roles = ['gate' => 'Gate staff', 'manager' => 'Manager', 'finance' => 'Finance'];

    return $this->render($request, $payload, 'signup', 'signup', $member ? 200 : 410, null, [
      'teamInvite' => $member
        ? ['token' => $token, 'email' => $member->email, 'org' => $orgName, 'role' => $roles[$member->role] ?? $member->role]
        : ['expired' => true],
    ]);
  }

  public function missing(Request $request, TicaUiPayload $payload)
  {
    return $this->render($request, $payload, 'missing', ltrim($request->path(), '/'), 404);
  }

  /** The blog, in the TICA design. ?category=slug narrows it to one category. */
  public function blog(Request $request, TicaUiPayload $payload)
  {
    $language = $this->getLanguage();
    $categories = DB::table('blog_categories')->where('language_id', $language->id)->where('status', 1)->orderBy('serial_number')->get(['name', 'slug']);
    $category = $categories->firstWhere('slug', (string) $request->query('category'));
    $posts = DB::table('blogs')
      ->join('blog_informations', 'blogs.id', '=', 'blog_informations.blog_id')
      ->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_informations.blog_category_id')
      ->where('blog_informations.language_id', $language->id)
      ->when($category, fn ($query) => $query->where('blog_categories.slug', $category->slug))
      ->orderBy('blogs.serial_number')
      ->get(['blogs.image', 'blogs.created_at', 'blog_informations.title', 'blog_informations.slug', 'blog_informations.content', 'blog_categories.name as category']);

    return $this->renderContent($request, $payload, 'blog', [
      'kind' => 'blog',
      'title' => 'Blog',
      'lead' => 'Stories, tips and news from the TICA community.',
      'posts' => $posts,
      'categories' => $categories,
      'category' => $category,
    ], 'Stories, tips and news from TICA.');
  }

  public function blogPost(Request $request, TicaUiPayload $payload, string $slug)
  {
    $language = $this->getLanguage();
    $post = DB::table('blogs')
      ->join('blog_informations', 'blogs.id', '=', 'blog_informations.blog_id')
      ->leftJoin('blog_categories', 'blog_categories.id', '=', 'blog_informations.blog_category_id')
      ->where('blog_informations.language_id', $language->id)
      ->where('blog_informations.slug', $slug)
      ->first(['blogs.id', 'blogs.image', 'blogs.created_at', 'blog_informations.title', 'blog_informations.author', 'blog_informations.content', 'blog_informations.meta_description', 'blog_categories.name as category', 'blog_categories.slug as category_slug']);
    if (!$post) {
      return $this->missing($request, $payload);
    }
    $related = DB::table('blogs')
      ->join('blog_informations', 'blogs.id', '=', 'blog_informations.blog_id')
      ->where('blog_informations.language_id', $language->id)
      ->where('blogs.id', '!=', $post->id)
      ->orderBy('blogs.serial_number')->limit(3)
      ->get(['blogs.image', 'blogs.created_at', 'blog_informations.title', 'blog_informations.slug', 'blog_informations.content']);

    return $this->renderContent($request, $payload, 'blog/' . $slug, [
      'kind' => 'post',
      'title' => $post->title,
      'post' => $post,
      'related' => $related,
    ], $post->meta_description ?: Str::limit(trim(strip_tags($post->content)), 158), asset('assets/admin/img/blogs/' . $post->image));
  }

  /** A page made under Website > Pages, at /its-slug. */
  public function customPage(Request $request, TicaUiPayload $payload, string $slug)
  {
    $page = DB::table('pages')
      ->join('page_contents', 'pages.id', '=', 'page_contents.page_id')
      ->where('pages.status', 1)
      ->where('page_contents.language_id', $this->getLanguage()->id)
      ->where('page_contents.slug', $slug)
      ->first(['page_contents.title', 'page_contents.content', 'page_contents.meta_description', 'page_contents.updated_at']);
    if (!$page) {
      return $this->missing($request, $payload);
    }

    return $this->renderContent($request, $payload, $slug, [
      'kind' => 'page',
      'title' => $page->title,
      'page' => $page,
    ], $page->meta_description ?: Str::limit(trim(strip_tags((string) $page->content)), 158));
  }

  /** Blog, posts and pages share one server-rendered page in the design shell. */
  private function renderContent(Request $request, TicaUiPayload $payload, string $path, array $content, string $description, ?string $image = null)
  {
    return $this->render($request, $payload, 'content', $path, 200, null, ['content' => ['title' => $content['title']]], [
      'content' => $content,
      'meta' => ['title' => $content['title'] . ' | TICA', 'description' => $description, 'ogTitle' => $content['title'] . ' | TICA', 'ogDescription' => $description, 'image' => $image],
    ]);
  }

  private function eventPage(Request $request, TicaUiPayload $payload, string $page, string $slug, int $id)
  {
    $event = (new EventCatalog($this->getLanguage()))->find($id);
    if (!$event) {
      return $this->render($request, $payload, 'missing', $page . '/' . $slug . '/' . $id, 404);
    }

    [$eventSlug] = explode('/', $event['slug']);
    if (urldecode($slug) !== $eventSlug) {
      return redirect('/' . $page . '/' . $event['slug'], 301);
    }

    return $this->render($request, $payload, $page, $page . '/' . $event['slug'], 200, $event);
  }

  private function render(Request $request, TicaUiPayload $payload, string $page, string $path, int $status = 200, ?array $event = null, array $extra = [], array $view = [])
  {
    $language = $this->getLanguage();
    if ($event && !empty($event['organizerId']) && Schema::hasTable('tica_tracking_pixels')) {
      $extra['trackingProviders'] = TicaTrackingPixel::query()
        ->where('organizer_id', (int) $event['organizerId'])
        ->where('status', 'approved')
        ->get(['provider', 'pixel_identifier'])
        ->map(fn (TicaTrackingPixel $pixel) => ['provider' => $pixel->provider, 'id' => $pixel->pixel_identifier])
        ->values()->all();
    }
    if (in_array($page, ['home', 'discover', 'how', 'pricing', 'about', 'contact'], true) && Schema::hasTable('tica_promotion_campaigns')) {
      $popup = TicaPromotionCampaign::query()->with(['event.information', 'plan'])
        ->where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>', now())
        ->whereHas('plan', fn ($query) => $query->where('placement', 'event_popup'))
        ->whereHas('event', fn ($query) => $query->where('status', 1))->inRandomOrder()->first();
      if ($popup) {
        $content = optional($popup->event)->information;
        $extra['promotionPopup'] = [
          'id' => (int) $popup->id,
          'headline' => (string) ($popup->headline ?: optional($content)->title ?: __('Featured event')),
          'body' => (string) ($popup->body ?: __('Discover this event on TICA.')),
          'image' => $popup->creative_path ? asset('assets/admin/img/tica-promotions/' . basename($popup->creative_path)) : null,
          'url' => (string) ($popup->destination_url ?: route('event.details', [optional($content)->slug, $popup->event_id])),
        ];
      }
    }
    $data = $payload->build($language, $path, $event, $extra);
    $title = $event ? $event['name'] : (self::TITLES[$page] ?? 'TICA');
    $description = $event
      ? Str::limit(trim(strip_tags($event['desc'])) ?: $event['name'] . ', ' . $event['label'], 158)
      : ($this->seo($page, $language->id) ?: 'Find events, buy tickets and sell tickets across Nigeria with TICA.');

    $meta = null;
    if (isset($view['meta'])) {
      $meta = $view['meta'] + ['robots' => 'index,follow'];
    }

    return response()->view('tica-ui.app', [
      'initialPage' => $page,
      'payload' => $data,
      'assetVersion' => $this->assetVersion(),
      'meta' => $meta ?? [
        'title' => $page === 'home' ? 'TICA — Events shape kulture' : $title . ' | TICA',
        'description' => $description,
        'ogTitle' => $event ? $event['name'] . ' | TICA' : 'TICA: find and sell event tickets in Nigeria',
        'ogDescription' => $event ? $description : 'Concerts, campus nights, conferences and more. Buy tickets in minutes or sell tickets to your own event.',
        'image' => $event['image'] ?? null,
        'robots' => in_array($page, ['checkout', 'login', 'signup', 'forgot', 'account', 'tickets', 'dashboard', 'create', 'checkin', 'missing'], true) ? 'noindex,follow' : 'index,follow',
      ],
      'structuredData' => $event ? $this->eventSchema($event) : null,
      'content' => $view['content'] ?? null,
    ], $status);
  }

  /** Admin > SEO descriptions, for the design pages that have one. */
  private function seo(string $page, int $languageId): ?string
  {
    $field = [
      'home' => 'meta_description_home', 'discover' => 'meta_description_event', 'about' => 'meta_description_about',
      'contact' => 'meta_description_contact', 'how' => 'meta_description_faq', 'login' => 'meta_description_customer_login',
      'signup' => 'meta_description_customer_signup', 'forgot' => 'meta_description_customer_forget_password', 'join' => 'meta_description_organizer',
    ][$page] ?? null;
    if (!$field) {
      return null;
    }
    $value = trim((string) DB::table('seos')->where('language_id', $languageId)->value($field));

    // The template shipped placeholders such as "Home Description"; those are not real descriptions.
    if ($value === '' || preg_match('/^[\w\s]{0,40}\bdescription$/i', $value)) {
      return null;
    }

    return Str::limit($value, 300);
  }

  /** Count one click per visitor session for an organiser's tracking link (?ref=CODE). */
  private function recordTrackingClick(Request $request, int $eventId): void
  {
    $code = strtoupper(trim((string) $request->query('ref', '')));
    if ($code === '' || !preg_match('/^[A-Z0-9]{4,32}$/', $code)) {
      return;
    }
    $link = TicaTrackingLink::query()
      ->where('code', $code)
      ->where(fn ($query) => $query->whereNull('event_id')->orWhere('event_id', $eventId))
      ->first();
    if (!$link || (int) Event::query()->whereKey($eventId)->value('organizer_id') !== (int) $link->organizer_id) {
      return;
    }
    $seen = (array) $request->session()->get('tica_tracking_clicks', []);
    if (!in_array($link->id, $seen, true)) {
      $link->increment('clicks');
      $request->session()->put('tica_tracking_clicks', array_slice(array_merge($seen, [$link->id]), -50));
    }
    $request->session()->put('tica_tracking_link_id', $link->id);
  }

  private function eventSchema(array $event): array
  {
    return array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Event',
      'name' => $event['name'],
      'startDate' => date(DATE_ATOM, intdiv($event['ts'], 1000)),
      'eventAttendanceMode' => $event['mode'] === 'online' ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
      'location' => $event['mode'] === 'online'
        ? ['@type' => 'VirtualLocation', 'url' => $event['url']]
        : ['@type' => 'Place', 'name' => $event['venue'], 'address' => trim($event['venue'] . ', ' . $event['city'] . ', ' . $event['country'], ', ')],
      'image' => $event['image'],
      'description' => Str::limit(trim(strip_tags($event['desc'])), 300),
      'organizer' => ['@type' => 'Organization', 'name' => $event['by']],
      'url' => $event['url'],
    ]);
  }

  private function assetVersion(): string
  {
    $files = ['css/tica.css', 'js/tica.js', 'js/qrcode.js'];

    return substr(md5(implode('|', array_map(fn ($file) => (string) @filemtime(public_path('assets/tica-ui/' . $file)), $files))), 0, 10);
  }
}
