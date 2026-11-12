<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\EventPublicVisibility;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventPublicVisibilityTest extends TestCase
{
    #[DataProvider('visibilityCases')]
    public function test_it_exposes_only_published_or_cancellation_notice_events(
        int $status,
        ?string $lifecycle,
        bool $expected
    ): void {
        $event = new Event(['status' => $status, 'lifecycle_status' => $lifecycle]);

        $this->assertSame($expected, (new EventPublicVisibility())->allows($event));
    }

    public static function visibilityCases(): array
    {
        return [
            'published event' => [1, 'scheduled', true],
            'draft event' => [0, 'scheduled', false],
            'archived legacy event' => [0, null, false],
            'cancellation pending notice' => [0, 'cancellation_pending', true],
            'cancelled notice' => [0, 'cancelled', true],
        ];
    }

    public function test_it_applies_the_same_rule_to_public_queries(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('events_visibility_test', function (Blueprint $table): void {
            $table->id();
            $table->boolean('status');
            $table->string('lifecycle_status')->nullable();
        });

        DB::table('events_visibility_test')->insert([
            ['id' => 1, 'status' => 1, 'lifecycle_status' => 'scheduled'],
            ['id' => 2, 'status' => 0, 'lifecycle_status' => 'scheduled'],
            ['id' => 3, 'status' => 0, 'lifecycle_status' => null],
            ['id' => 4, 'status' => 0, 'lifecycle_status' => 'cancellation_pending'],
            ['id' => 5, 'status' => 0, 'lifecycle_status' => 'cancelled'],
        ]);

        $query = DB::table('events_visibility_test');
        (new EventPublicVisibility())->apply($query, 'events_visibility_test');

        $this->assertSame([1, 4, 5], $query->orderBy('id')->pluck('id')->all());
    }

    public function test_it_falls_back_to_the_legacy_published_rule_during_schema_rollout(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('legacy_events_visibility_test', function (Blueprint $table): void {
            $table->id();
            $table->boolean('status');
        });

        DB::table('legacy_events_visibility_test')->insert([
            ['id' => 1, 'status' => 1],
            ['id' => 2, 'status' => 0],
        ]);

        $query = DB::table('legacy_events_visibility_test');
        (new EventPublicVisibility())->apply($query, 'legacy_events_visibility_test');

        $this->assertSame([1], $query->orderBy('id')->pluck('id')->all());
    }
}
