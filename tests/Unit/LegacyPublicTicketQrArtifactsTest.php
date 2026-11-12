<?php

namespace Tests\Unit;

use App\Services\LegacyPublicTicketQrArtifacts;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LegacyPublicTicketQrArtifactsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/legacy-public-qrs-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->directory);
        config(['tickets.legacy_public_qr_directory' => $this->directory]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_dry_run_reports_without_deleting_and_apply_purges_only_svg_artifacts(): void
    {
        File::put($this->directory.'/ticket.svg', '<svg/>');
        File::put($this->directory.'/keep.txt', 'not a qr');

        $this->artisan('tickets:purge-public-qr-artifacts')->assertSuccessful();
        $this->assertFileExists($this->directory.'/ticket.svg');

        $this->artisan('tickets:purge-public-qr-artifacts', ['--apply' => true])->assertSuccessful();
        $this->assertFileDoesNotExist($this->directory.'/ticket.svg');
        $this->assertFileExists($this->directory.'/keep.txt');
        $this->assertSame(0, app(LegacyPublicTicketQrArtifacts::class)->count());
    }
}
