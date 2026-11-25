<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\CustomPage\PageContent;
use App\Models\Language;
use App\Services\TicaUi\EventCatalog;
use App\Support\TicaSite;
use Carbon\Carbon;

class TicaSeoController extends Controller
{
    public function sitemap()
    {
        $language = Language::query()->where('is_default', 1)->firstOrFail();
        $urls = collect(['/', '/discover', '/how', '/pricing', '/about', '/contact', '/blog'])
            ->map(fn (string $path) => ['loc' => url($path), 'lastmod' => null]);

        PageContent::query()->join('pages', 'pages.id', '=', 'page_contents.page_id')
            ->where('pages.status', 1)->where('page_contents.language_id', $language->id)
            ->get(['page_contents.slug', 'page_contents.updated_at'])
            ->each(fn ($page) => $urls->push(['loc' => url('/' . $page->slug), 'lastmod' => $page->updated_at ? Carbon::parse($page->updated_at)->toAtomString() : null]));

        (new EventCatalog($language))->upcoming(500)->each(fn (array $event) => $urls->push([
            'loc' => url('/event/' . $event['slug']),
            'lastmod' => !empty($event['updatedAt']) ? $event['updatedAt'] : null,
        ]));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls->unique('loc') as $entry) {
            $xml .= '<url><loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . '</loc>';
            if ($entry['lastmod']) $xml .= '<lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1) . '</lastmod>';
            $xml .= '</url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=900']);
    }

    public function robots()
    {
        $index = (bool) (TicaSite::get('seo')['index_site'] ?? true);
        $lines = $index
            ? ['User-agent: *', 'Allow: /', 'Disallow: /admin', 'Disallow: /checkout', 'Disallow: /dashboard', 'Disallow: /account', 'Sitemap: ' . url('/sitemap.xml')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=900']);
    }
}
