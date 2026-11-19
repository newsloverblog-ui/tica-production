<?php

namespace Tests\Unit;

use App\Services\SupportTicketAttachmentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SupportTicketAttachmentStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.supabase.url' => null,
            'services.supabase.secret_key' => null,
            'services.supabase.service_role_key' => null,
            'filesystems.support_ticket_attachments_disk' => 'local',
        ]);
        Storage::fake('local');
    }

    public function test_support_attachments_are_stored_on_a_private_disk_and_downloaded_without_caching(): void
    {
        $storage = app(SupportTicketAttachmentStorage::class);
        $path = $storage->store(
            UploadedFile::fake()->createWithContent('support.zip', 'private-support-data'),
            'attachments'
        );

        $this->assertStringStartsWith('support-ticket/attachments/', $path);
        $this->assertStringEndsWith('.zip', $path);
        Storage::disk('local')->assertExists($path);

        $response = $storage->download($path, 'ticket.zip');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_public_and_traversal_paths_are_rejected(): void
    {
        $this->expectException(NotFoundHttpException::class);

        app(SupportTicketAttachmentStorage::class)->download(
            'assets/admin/img/support-ticket/exposed.zip'
        );
    }

    public function test_delete_only_accepts_managed_private_paths(): void
    {
        $storage = app(SupportTicketAttachmentStorage::class);
        $path = $storage->store(
            UploadedFile::fake()->createWithContent('support.zip', 'private-support-data'),
            'replies'
        );

        $storage->delete('../' . $path);
        Storage::disk('local')->assertExists($path);

        $storage->delete($path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_legacy_files_are_deleted_only_after_the_private_copy_is_persisted(): void
    {
        $storage = app(SupportTicketAttachmentStorage::class);
        $legacyDirectory = public_path('assets/admin/img/support-ticket');
        if (!is_dir($legacyDirectory)) {
            mkdir($legacyDirectory, 0777, true);
        }
        $legacyPath = $legacyDirectory . '/legacy-test.zip';
        file_put_contents($legacyPath, 'legacy-private-data');

        try {
            $privatePath = $storage->migrateLegacy(
                'legacy-test.zip',
                'assets/admin/img/support-ticket/legacy-test.zip',
                'attachments'
            );

            $this->assertNotNull($privatePath);
            $this->assertFileExists($legacyPath);
            Storage::disk('local')->assertExists($privatePath);

            $storage->deleteLegacy('assets/admin/img/support-ticket/legacy-test.zip');
            $this->assertFileDoesNotExist($legacyPath);
        } finally {
            if (is_file($legacyPath)) {
                unlink($legacyPath);
            }
        }
    }

    public function test_delete_stored_or_legacy_accepts_only_an_exact_legacy_zip_reference(): void
    {
        $storage = app(SupportTicketAttachmentStorage::class);
        $legacyDirectory = public_path('assets/admin/img/support-ticket');
        if (!is_dir($legacyDirectory)) {
            mkdir($legacyDirectory, 0777, true);
        }

        $legacyPath = $legacyDirectory . '/legacy-delete-test.zip';
        file_put_contents($legacyPath, 'legacy-private-data');

        try {
            $storage->deleteStoredOrLegacy(
                '../legacy-delete-test.zip',
                'assets/admin/img/support-ticket/legacy-delete-test.zip'
            );
            $this->assertFileExists($legacyPath);

            $storage->deleteStoredOrLegacy(
                'legacy-delete-test.zip',
                'assets/admin/img/support-ticket/legacy-delete-test.zip'
            );
            $this->assertFileDoesNotExist($legacyPath);
        } finally {
            if (is_file($legacyPath)) {
                unlink($legacyPath);
            }
        }
    }
}
