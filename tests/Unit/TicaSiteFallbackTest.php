<?php

namespace Tests\Unit;

use App\Support\TicaSite;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class TicaSiteFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearSiteCache();
    }

    protected function tearDown(): void
    {
        $this->clearSiteCache();
        parent::tearDown();
    }

    public function test_brand_defaults_remain_available_when_the_database_is_down(): void
    {
        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('basic_settings', 'tica_site')
            ->andThrow(new RuntimeException('database unavailable'));

        $site = TicaSite::all();

        $this->assertSame('TICA', $site['branding']['site_name']);
        $this->assertSame('#2F5BD8', $site['branding']['primary_color']);
        $this->assertSame("Events shape kulture.\nLet's build it together.", $site['footer']['tagline']);
    }

    private function clearSiteCache(): void
    {
        $cache = new ReflectionProperty(TicaSite::class, 'cache');
        $cache->setValue(null, null);
    }
}
