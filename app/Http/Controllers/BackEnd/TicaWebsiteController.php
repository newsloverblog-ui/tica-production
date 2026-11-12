<?php

namespace App\Http\Controllers\BackEnd;

use App\Http\Controllers\Controller;
use App\Http\Helpers\UploadFile;
use App\Models\CustomPage\Page;
use App\Models\FAQ;
use App\Models\Journal\Blog;
use App\Rules\ImageMimeTypeRule;
use App\Support\TicaSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * One place for everything that changes the public TICA pages. Each tab
 * keeps the permission of the old page it replaces.
 */
class TicaWebsiteController extends Controller
{
  private const TABS = [
    'home' => ['Home page', 'Home Page'],
    'menu' => ['Menu', 'Menu Builder'],
    'footer' => ['Footer', 'Footer'],
    'contact' => ['Contact details', 'Contact Page'],
  ];

  public function show(Request $request): View
  {
    $tabs = $this->tabs();
    abort_if($tabs === [], 403);
    $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : array_key_first($tabs);
    $admin = Auth::guard('admin')->user();
    $lang = \App\Models\Language::query()->where('is_default', 1)->first();

    return view('backend.tica.website', [
      'tab' => $tab,
      'tabs' => $tabs,
      'site' => TicaSite::all(),
      'sections' => TicaSite::SECTIONS,
      'networks' => TicaSite::NETWORKS,
      'counts' => [
        'pages' => $admin->hasPermission('Custom Pages') ? Page::query()->count() : null,
        'blog' => $admin->hasPermission('Blog Management') ? Blog::query()->count() : null,
        'faq' => $admin->hasPermission('FAQ Management') ? FAQ::query()->where('language_id', $lang->id)->count() : null,
      ],
    ]);
  }

  public function update(Request $request, string $tab): RedirectResponse
  {
    abort_unless(array_key_exists($tab, $this->tabs()), 403);

    if ($request->boolean('reset')) {
      TicaSite::reset($tab);

      return redirect()->route('admin.tica.website', ['tab' => $tab])->with('success', __('Back to the original TICA design content.'));
    }

    $value = match ($tab) {
      'home' => $this->home($request),
      'menu' => $this->menu($request),
      'footer' => $this->footer($request),
      'contact' => $this->contact($request),
    };
    TicaSite::save($tab, $value);

    return redirect()->route('admin.tica.website', ['tab' => $tab])->with('success', __('Saved. The website shows the change now.'));
  }

  private function tabs(): array
  {
    $admin = Auth::guard('admin')->user();

    return array_filter(self::TABS, fn ($tab) => $admin->hasPermission($tab[1]));
  }

  private function home(Request $request): array
  {
    $request->validate([
      'hero_title' => ['required', 'string', 'max:120'],
      'hero_text' => ['nullable', 'string', 'max:240'],
      'hero_images' => ['nullable', 'array', 'max:6'],
      'hero_images.*' => ['nullable', 'string', 'max:500'],
      'hero_uploads.*' => ['nullable', 'file', 'max:4096', new ImageMimeTypeRule()],
      'sections' => ['nullable', 'array'],
      'organisers_title' => ['required', 'string', 'max:120'],
      'organisers_text' => ['nullable', 'string', 'max:400'],
      'organisers_points.*.title' => ['nullable', 'string', 'max:60'],
      'organisers_points.*.text' => ['nullable', 'string', 'max:140'],
      'eticket_title' => ['required', 'string', 'max:120'],
      'eticket_steps.*.title' => ['nullable', 'string', 'max:60'],
      'eticket_steps.*.text' => ['nullable', 'string', 'max:140'],
      'partners.*.short' => ['nullable', 'string', 'max:12'],
      'partners.*.name' => ['nullable', 'string', 'max:60'],
      'partner_logos.*' => ['nullable', 'file', 'max:2048', new ImageMimeTypeRule()],
    ], ['hero_uploads.*.max' => __('Each photo can be up to 4 MB.')]);

    $images = collect((array) $request->input('hero_images', []))->filter(fn ($url) => filled($url))->values()->all();
    foreach ((array) $request->file('hero_uploads', []) as $file) {
      $images[] = $this->store($file);
    }
    if ($images === []) {
      $images = TicaSite::defaults()['home']['hero_images'];
    }

    $pairs = fn (string $key, int $max) => collect((array) $request->input($key, []))
      ->map(fn ($row) => ['title' => trim((string) ($row['title'] ?? '')), 'text' => trim((string) ($row['text'] ?? ''))])
      ->filter(fn ($row) => $row['title'] !== '')->take($max)->values()->all();

    $partners = [];
    foreach ((array) $request->input('partners', []) as $i => $row) {
      $short = trim((string) ($row['short'] ?? ''));
      $name = trim((string) ($row['name'] ?? ''));
      $logo = (string) ($row['logo'] ?? '');
      if ($request->hasFile("partner_logos.$i")) {
        $logo = $this->store($request->file("partner_logos.$i"));
      }
      if ($short !== '' || $name !== '' || $logo !== '') {
        $partners[] = ['short' => $short, 'name' => $name, 'logo' => $logo];
      }
    }

    $sections = [];
    foreach (array_keys(TicaSite::SECTIONS) as $key) {
      $sections[$key] = (bool) ($request->input("sections.$key") ?? false);
    }

    return [
      'hero_title' => trim((string) $request->input('hero_title')),
      'hero_text' => trim((string) $request->input('hero_text')),
      'hero_images' => array_slice($images, 0, 6),
      'sections' => $sections,
      'organisers_title' => trim((string) $request->input('organisers_title')),
      'organisers_text' => trim((string) $request->input('organisers_text')),
      'organisers_points' => $pairs('organisers_points', 4),
      'eticket_title' => trim((string) $request->input('eticket_title')),
      'eticket_steps' => $pairs('eticket_steps', 4),
      'partners' => array_slice($partners, 0, 8),
    ];
  }

  private function menu(Request $request): array
  {
    $request->validate([
      'links' => ['required', 'array', 'min:1', 'max:7'],
      'links.*.label' => ['nullable', 'string', 'max:30'],
      'links.*.url' => ['nullable', 'string', 'max:300'],
    ], ['links.required' => __('Keep at least one menu link.')]);

    return $this->links($request->input('links', []), 7);
  }

  private function footer(Request $request): array
  {
    $request->validate([
      'tagline' => ['nullable', 'string', 'max:120'],
      'columns' => ['nullable', 'array', 'max:2'],
      'columns.*.title' => ['nullable', 'string', 'max:30'],
      'columns.*.links.*.label' => ['nullable', 'string', 'max:40'],
      'columns.*.links.*.url' => ['nullable', 'string', 'max:300'],
      'copyright' => ['nullable', 'string', 'max:160'],
    ]);

    $columns = [];
    foreach ((array) $request->input('columns', []) as $column) {
      $title = trim((string) ($column['title'] ?? ''));
      $links = $this->links($column['links'] ?? [], 8);
      if ($title !== '' && $links !== []) {
        $columns[] = ['title' => $title, 'links' => $links];
      }
    }

    return [
      'tagline' => trim((string) $request->input('tagline')),
      'columns' => $columns,
      'copyright' => trim((string) $request->input('copyright')),
    ];
  }

  private function contact(Request $request): array
  {
    $request->validate([
      'phone' => ['nullable', 'string', 'max:30'],
      'phone_hours' => ['nullable', 'string', 'max:80'],
      'city' => ['nullable', 'string', 'max:80'],
      'emails.*.address' => ['nullable', 'email', 'max:120'],
      'emails.*.label' => ['nullable', 'string', 'max:30'],
      'socials.*.network' => ['nullable', Rule::in(array_keys(TicaSite::NETWORKS))],
      'socials.*.handle' => ['nullable', 'string', 'max:40'],
      'socials.*.url' => ['nullable', 'url', 'max:300'],
    ], ['emails.*.address.email' => __('Check the email addresses.'), 'socials.*.url.url' => __('Social links must start with https://')]);

    return [
      'phone' => trim((string) $request->input('phone')),
      'phone_hours' => trim((string) $request->input('phone_hours')),
      'city' => trim((string) $request->input('city')),
      'emails' => collect((array) $request->input('emails', []))->map(fn ($row) => ['address' => trim((string) ($row['address'] ?? '')), 'label' => trim((string) ($row['label'] ?? ''))])->filter(fn ($row) => $row['address'] !== '')->take(5)->values()->all(),
      'socials' => collect((array) $request->input('socials', []))->map(fn ($row) => ['network' => (string) ($row['network'] ?? ''), 'handle' => trim((string) ($row['handle'] ?? '')), 'url' => trim((string) ($row['url'] ?? ''))])->filter(fn ($row) => $row['url'] !== '' && isset(TicaSite::NETWORKS[$row['network']]))->take(7)->values()->all(),
    ];
  }

  /** Label and address pairs, skipping blank rows. Addresses are site paths or http(s) links. */
  private function links(array $rows, int $max): array
  {
    return collect($rows)->map(function ($row) {
      $label = trim((string) ($row['label'] ?? ''));
      $url = trim((string) ($row['url'] ?? ''));
      if ($url !== '' && !preg_match('#^(https?://|/|mailto:|tel:)#i', $url)) {
        $url = '/' . ltrim($url, '/');
      }
      return ['label' => $label, 'url' => $url];
    })->filter(fn ($row) => $row['label'] !== '' && $row['url'] !== '')->take($max)->values()->all();
  }

  private function store($file): string
  {
    $name = (string) Str::uuid() . '.' . ImageMimeTypeRule::extension($file);
    UploadFile::storeAs(public_path('assets/tica-ui/site'), $file, $name);

    return $name;
  }
}
