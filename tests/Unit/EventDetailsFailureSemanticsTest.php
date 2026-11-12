<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\EventController;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventDetailsFailureSemanticsTest extends TestCase
{
    public function test_unexpected_database_failures_are_not_disguised_as_not_found(): void
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

        $this->expectException(QueryException::class);

        (new EventController())->details('unavailable-event', 999);
    }
}
