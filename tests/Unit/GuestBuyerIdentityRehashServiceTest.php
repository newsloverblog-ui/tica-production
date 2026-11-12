<?php

namespace Tests\Unit;

use App\Services\GuestBuyerIdentityRehashService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class GuestBuyerIdentityRehashServiceTest extends TestCase
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
        config()->set('tickets.buyer_identity_hash_key', 'replacement-buyer-identity-key-32-bytes');
        config()->set('tickets.buyer_identity_hash_version', 2);

        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('email');
            $table->char('buyer_email_hash', 64)->nullable();
            $table->unsignedSmallInteger('buyer_email_hash_version')->nullable();
        });
        Schema::create('ticket_reservations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('status', 24);
            $table->char('buyer_email_hash', 64)->nullable();
            $table->unsignedSmallInteger('buyer_email_hash_version')->nullable();
        });
    }

    public function test_rehash_fails_closed_then_updates_every_guest_atomically(): void
    {
        DB::table('bookings')->insert([
            ['customer_id' => null, 'email' => 'guest@example.com', 'buyer_email_hash' => str_repeat('a', 64), 'buyer_email_hash_version' => 1],
            ['customer_id' => null, 'email' => 'invalid', 'buyer_email_hash' => null, 'buyer_email_hash_version' => null],
            ['customer_id' => 7, 'email' => 'member@example.com', 'buyer_email_hash' => null, 'buyer_email_hash_version' => null],
        ]);
        DB::table('ticket_reservations')->insert([
            'customer_id' => null, 'status' => 'active', 'buyer_email_hash' => str_repeat('b', 64), 'buyer_email_hash_version' => 1,
        ]);

        $service = app(GuestBuyerIdentityRehashService::class);
        $report = $service->inspect();
        $this->assertSame(2, $report['guest_bookings']);
        $this->assertSame(1, $report['stale_bookings']);
        $this->assertSame(1, $report['invalid_guest_emails']);
        $this->assertSame(1, $report['stale_active_reservations']);

        try {
            $service->rehash();
            $this->fail('Expected unresolved guest identity evidence to block rehash.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cannot be rehashed', $exception->getMessage());
        }
        $this->assertSame(str_repeat('a', 64), DB::table('bookings')->where('email', 'guest@example.com')->value('buyer_email_hash'));

        DB::table('bookings')->where('email', 'invalid')->delete();
        DB::table('ticket_reservations')->update(['status' => 'expired']);
        $result = $service->rehash();

        $this->assertSame(1, $result['changed_bookings']);
        $this->assertSame(0, $result['stale_bookings']);
        $this->assertSame(2, (int) DB::table('bookings')->where('email', 'guest@example.com')->value('buyer_email_hash_version'));
        $this->assertNotSame(str_repeat('a', 64), DB::table('bookings')->where('email', 'guest@example.com')->value('buyer_email_hash'));
        $this->assertNull(DB::table('bookings')->where('customer_id', 7)->value('buyer_email_hash'));
    }

    public function test_command_is_dry_run_by_default_and_apply_requires_maintenance_mode(): void
    {
        DB::table('bookings')->insert([
            'customer_id' => null,
            'email' => 'guest@example.com',
            'buyer_email_hash' => str_repeat('a', 64),
            'buyer_email_hash_version' => 1,
        ]);

        $this->artisan('tickets:rehash-guest-buyer-identities', ['--json' => true])
            ->assertSuccessful();
        $this->assertSame(1, (int) DB::table('bookings')->value('buyer_email_hash_version'));

        $this->artisan('tickets:rehash-guest-buyer-identities', ['--apply' => true, '--json' => true])
            ->expectsOutput('Enter maintenance mode before rehashing guest buyer identities.')
            ->assertFailed();
        $this->assertSame(1, (int) DB::table('bookings')->value('buyer_email_hash_version'));

        $this->artisan('down')->assertSuccessful();
        try {
            $this->artisan('tickets:rehash-guest-buyer-identities', ['--apply' => true, '--json' => true])
                ->assertSuccessful();
        } finally {
            $this->artisan('up')->assertSuccessful();
        }

        $this->assertSame(2, (int) DB::table('bookings')->value('buyer_email_hash_version'));
        $this->assertNotSame(str_repeat('a', 64), DB::table('bookings')->value('buyer_email_hash'));
    }
}
