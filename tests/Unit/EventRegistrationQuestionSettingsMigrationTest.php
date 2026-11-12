<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventRegistrationQuestionSettingsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('event_registration_questions', function (Blueprint $table): void {
            $table->id();
            $table->json('condition')->nullable();
        });
    }

    public function test_migration_adds_and_removes_question_settings(): void
    {
        $migration = require database_path(
            'migrations/2026_11_03_130000_add_settings_to_event_registration_questions.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumn('event_registration_questions', 'settings'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('event_registration_questions', 'settings'));
    }
}
