<?php

namespace Tests\Unit;

use App\Http\Helpers\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UploadFileSecurityTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.supabase.url');
        config()->set('services.supabase.secret_key');
        config()->set('services.supabase.service_role_key');
        $this->directory = sys_get_temp_dir() . '/tica-upload-' . bin2hex(random_bytes(6)) . '/';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '*') ?: [] as $file) {
            if (is_file($file)) unlink($file);
        }
        if (is_dir($this->directory)) rmdir($this->directory);
        parent::tearDown();
    }

    public function test_image_name_uses_server_detected_type_and_random_identifier(): void
    {
        $name = UploadFile::store($this->directory, UploadedFile::fake()->image('avatar.png', 320, 320));

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $name);
        $this->assertFileExists($this->directory . $name);
    }

    public function test_disguised_executable_upload_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        UploadFile::store($this->directory, UploadedFile::fake()->createWithContent('avatar.jpg', '<?php echo 1;'));
    }

    public function test_failed_replacement_does_not_delete_existing_image(): void
    {
        mkdir($this->directory, 0775, true);
        file_put_contents($this->directory . 'existing.png', 'existing');

        try {
            UploadFile::update($this->directory, UploadedFile::fake()->createWithContent('bad.png', '<script>'), 'existing.png');
            $this->fail('The disguised upload should fail.');
        } catch (ValidationException) {
            $this->assertFileExists($this->directory . 'existing.png');
        }
    }

    public function test_public_image_uses_durable_storage_when_supabase_is_configured(): void
    {
        config()->set('services.supabase.url', 'https://storage.example.test');
        config()->set('services.supabase.secret_key', 'server-secret');
        config()->set('services.supabase.public_asset_bucket', 'tica-public');

        Http::fake([
            'https://storage.example.test/storage/v1/bucket/tica-public' => Http::response(['message' => 'Bucket not found'], 400),
            'https://storage.example.test/storage/v1/bucket' => Http::response(['name' => 'tica-public'], 200),
            'https://storage.example.test/storage/v1/object/tica-public/assets/admin/img/admins/*' => Http::response(['Key' => 'stored'], 200),
        ]);

        $name = UploadFile::store(
            public_path('assets/admin/img/admins/'),
            UploadedFile::fake()->image('avatar.png', 320, 320)
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $name);
        $this->assertFileDoesNotExist(public_path('assets/admin/img/admins/' . $name));
        Http::assertSent(fn ($request) =>
            str_contains($request->url(), '/storage/v1/object/tica-public/assets/admin/img/admins/')
            && $request->hasHeader('apikey', 'server-secret')
            && $request->hasHeader('x-upsert', 'false')
        );
    }

    public function test_remote_failure_becomes_a_validation_error_instead_of_a_server_error(): void
    {
        config()->set('services.supabase.url', 'https://storage.example.test');
        config()->set('services.supabase.secret_key', 'server-secret');

        Http::fake([
            'https://storage.example.test/storage/v1/bucket/*' => Http::response(['name' => 'tica-public'], 200),
            'https://storage.example.test/storage/v1/object/*' => Http::response(['message' => 'unavailable'], 503),
        ]);

        try {
            UploadFile::store(
                public_path('assets/admin/img/admins/'),
                UploadedFile::fake()->image('avatar.png', 320, 320)
            );
            $this->fail('A failed object-store write must not be reported as a successful upload.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
            $this->assertStringContainsString('could not save', $exception->errors()['file'][0]);
        }
    }

    public function test_downloaded_provider_avatar_uses_durable_storage_without_a_public_write(): void
    {
        config()->set('services.supabase.url', 'https://storage.example.test');
        config()->set('services.supabase.secret_key', 'server-secret');
        config()->set('services.supabase.public_asset_bucket', 'tica-public');

        Http::fake([
            'https://storage.example.test/storage/v1/bucket/tica-public' => Http::response([], 200),
            'https://storage.example.test/storage/v1/object/tica-public/assets/admin/img/customer-profile/*' => Http::response(['Key' => 'stored'], 200),
        ]);

        UploadFile::storeContents(
            public_path('assets/admin/img/customer-profile/'),
            'safe-avatar-bytes',
            'provider-avatar.jpg',
            'image/jpeg'
        );

        $this->assertFileDoesNotExist(public_path('assets/admin/img/customer-profile/provider-avatar.jpg'));
        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/assets/admin/img/customer-profile/provider-avatar.jpg')
            && $request->hasHeader('Content-Type', 'image/jpeg')
        );
    }
}
