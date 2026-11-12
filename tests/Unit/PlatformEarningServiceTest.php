<?php

namespace Tests\Unit;

use App\Models\Earning;
use App\Services\PlatformEarningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class PlatformEarningServiceTest extends TestCase
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

        Schema::create('earnings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_earning', 18, 2)->default(0);
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->timestamps();
        });
    }

    public function test_returns_the_one_authoritative_row(): void
    {
        $earning = Earning::create([
            'total_revenue' => 100,
            'total_earning' => 25,
            'singleton_key' => 1,
        ]);

        $locked = DB::transaction(fn () => app(PlatformEarningService::class)->lock());

        $this->assertTrue($locked->is($earning));
    }

    public function test_missing_authoritative_row_fails_closed(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('exactly one');

        DB::transaction(fn () => app(PlatformEarningService::class)->lock());
    }

    public function test_legacy_duplicate_rows_fail_closed(): void
    {
        Schema::table('earnings', function (Blueprint $table): void {
            $table->dropUnique('earnings_singleton_key_unique');
            $table->dropColumn('singleton_key');
        });
        Earning::create(['total_revenue' => 100, 'total_earning' => 25]);
        Earning::create(['total_revenue' => 200, 'total_earning' => 50]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('exactly one');

        DB::transaction(fn () => app(PlatformEarningService::class)->lock());
    }
}
