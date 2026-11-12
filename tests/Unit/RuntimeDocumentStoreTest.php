<?php

namespace Tests\Unit;

use App\Services\RuntimeDocumentStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RuntimeDocumentStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uniqid')->unique();
            $table->json('tica_site')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        DB::table('basic_settings')->insert([
            'uniqid' => 12345,
            'tica_site' => json_encode(['home' => ['hero' => 'preserved']]),
        ]);
    }

    public function test_runtime_documents_are_persisted_and_retrieved_as_arrays(): void
    {
        $store = app(RuntimeDocumentStore::class);
        $store->put(RuntimeDocumentStore::WEB_MANIFEST, ['name' => 'TICA', 'display' => 'standalone']);

        $this->assertSame(
            ['name' => 'TICA', 'display' => 'standalone'],
            $store->get(RuntimeDocumentStore::WEB_MANIFEST)
        );
        $this->assertSame(
            'preserved',
            data_get(json_decode(DB::table('basic_settings')->value('tica_site'), true), 'home.hero')
        );
    }

    public function test_manifest_defaults_do_not_depend_on_a_writable_public_file(): void
    {
        $store = app(RuntimeDocumentStore::class);

        $this->assertSame('TICA Events', $store->webManifestDefaults()['name']);
        $this->assertSame('/scanner/workspace', $store->scannerManifestDefaults()['start_url']);
    }
}
