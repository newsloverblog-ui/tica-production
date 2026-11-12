<?php

namespace Tests\Unit;

use Tests\TestCase;

class PublicAssetStorageRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.supabase.url', 'https://storage.example.test');
        config()->set('services.supabase.secret_key', 'server-secret');
        config()->set('services.supabase.public_asset_bucket', 'tica-public');
    }

    public function test_missing_legacy_asset_redirects_to_the_durable_public_object(): void
    {
        $this->get('/assets/admin/img/admins/avatar.png')
            ->assertRedirect('https://storage.example.test/storage/v1/object/public/tica-public/assets/admin/img/admins/avatar.png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_asset_redirect_rejects_traversal_and_invalid_characters(): void
    {
        $this->get('/assets/admin/img/../secret.txt')->assertNotFound();
        $this->get('/assets/admin/img/%3Cscript%3E.png')->assertNotFound();
    }

    public function test_missing_legacy_asset_is_not_redirected_without_remote_storage(): void
    {
        config()->set('services.supabase.secret_key');

        $this->get('/assets/admin/img/admins/avatar.png')->assertNotFound();
    }
}
