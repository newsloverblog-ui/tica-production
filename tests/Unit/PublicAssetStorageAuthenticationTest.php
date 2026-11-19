<?php

namespace Tests\Unit;

use App\Services\PublicAssetStorage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicAssetStorageAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.supabase.url', 'https://storage.example.test');
        config()->set('services.supabase.public_asset_bucket', 'tica-public');
    }

    public function test_opaque_secret_key_is_sent_only_as_an_api_key(): void
    {
        config()->set('services.supabase.secret_key', 'sb_secret_server_test');
        config()->set('services.supabase.service_role_key');
        Http::fake([
            'https://storage.example.test/storage/v1/bucket/tica-public' => Http::response([], 200),
            'https://storage.example.test/storage/v1/object/*' => Http::response([], 200),
        ]);

        app(PublicAssetStorage::class)->putContents(
            'image-bytes',
            'assets/admin/img/tica-promotions/campaign.png',
            'image/png'
        );

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('apikey', 'sb_secret_server_test')
                && !$request->hasHeader('Authorization');
        });
        Http::assertSentCount(2);
    }

    public function test_legacy_service_role_jwt_keeps_the_bearer_header(): void
    {
        config()->set('services.supabase.secret_key');
        config()->set('services.supabase.service_role_key', 'eyJlegacy-service-role');
        Http::fake([
            'https://storage.example.test/storage/v1/bucket/tica-public' => Http::response([], 200),
            'https://storage.example.test/storage/v1/object/*' => Http::response([], 200),
        ]);

        app(PublicAssetStorage::class)->putContents(
            'image-bytes',
            'assets/admin/img/tica-promotions/campaign.png',
            'image/png'
        );

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('apikey', 'eyJlegacy-service-role')
                && $request->hasHeader('Authorization', 'Bearer eyJlegacy-service-role');
        });
        Http::assertSentCount(2);
    }
}
