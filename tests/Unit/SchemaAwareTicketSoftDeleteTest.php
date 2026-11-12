<?php

namespace Tests\Unit;

use App\Models\Event\Ticket;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaAwareTicketSoftDeleteTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('legacy_tickets_without_soft_deletes');
        Schema::dropIfExists('migrated_tickets_with_soft_deletes');

        parent::tearDown();
    }

    public function test_ticket_reads_remain_available_before_soft_delete_migration(): void
    {
        Schema::create('legacy_tickets_without_soft_deletes', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        $ticket = new LegacyTicketWithoutSoftDeletes();
        $ticket->forceFill(['title' => 'Legacy ticket'])->save();

        $this->assertSame(1, LegacyTicketWithoutSoftDeletes::query()->count());
        $this->assertSame('Legacy ticket', LegacyTicketWithoutSoftDeletes::query()->firstOrFail()->title);
    }

    public function test_standard_soft_delete_scope_applies_after_migration(): void
    {
        Schema::create('migrated_tickets_with_soft_deletes', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });

        $ticket = new MigratedTicketWithSoftDeletes();
        $ticket->forceFill(['title' => 'Retired ticket'])->save();
        $ticket->delete();

        $this->assertSame(0, MigratedTicketWithSoftDeletes::query()->count());
        $this->assertSame(1, MigratedTicketWithSoftDeletes::withTrashed()->count());
    }
}

class LegacyTicketWithoutSoftDeletes extends Ticket
{
    protected $table = 'legacy_tickets_without_soft_deletes';
}

class MigratedTicketWithSoftDeletes extends Ticket
{
    protected $table = 'migrated_tickets_with_soft_deletes';
}
