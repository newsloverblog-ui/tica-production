<?php

namespace Tests\Unit;

use Tests\TestCase;

class TicaImageDeferralTest extends TestCase
{
    public function test_hidden_tica_pages_defer_their_content_images_until_the_route_is_visible(): void
    {
        $catalog = (string) file_get_contents(app_path('Services/TicaUi/EventCatalog.php'));
        $client = (string) file_get_contents(public_path('assets/tica-ui/js/tica.js'));

        $this->assertStringContainsString('<img data-src="', $catalog);
        $this->assertStringContainsString("function hydrateImages(root)", $client);
        $this->assertStringContainsString("root.querySelectorAll('img[data-src]')", $client);
        $this->assertStringContainsString('hydrateImages(pg);', $client);

        foreach ([
            'about',
            'content',
            'forgot',
            'home',
            'how',
            'join',
            'login',
            'pricing',
            'signup',
        ] as $page) {
            $view = (string) file_get_contents(resource_path("views/tica-ui/pages/{$page}.blade.php"));

            $this->assertDoesNotMatchRegularExpression(
                '/<img\b[^>]*\ssrc=["\'][^"\']*images\.unsplash\.com/i',
                $view,
                "The {$page} page must not eagerly download hidden Unsplash content.",
            );
        }
    }
}
