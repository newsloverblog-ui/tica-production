<?php

namespace Tests\Unit;

use App\Casts\PrefixedEncryptedString;
use App\Models\PaymentIntent;
use App\Models\ProductPaymentIntent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentIntentSensitiveDataSecurityTest extends TestCase
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

        foreach (['payment_intents', 'product_payment_intents'] as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('email');
                $blueprint->text('last_error')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    public function test_migration_encrypts_existing_payment_identity_and_diagnostics(): void
    {
        DB::table('payment_intents')->insert([
            'email' => 'event-buyer@example.test',
            'last_error' => 'provider secret diagnostic',
        ]);
        DB::table('product_payment_intents')->insert([
            'email' => 'shop-buyer@example.test',
            'last_error' => 'shop provider secret diagnostic',
        ]);

        $migration = require database_path(
            'migrations/2026_11_06_170000_encrypt_payment_intent_sensitive_fields.php'
        );
        $migration->up();

        $eventRaw = DB::table('payment_intents')->first();
        $productRaw = DB::table('product_payment_intents')->first();
        foreach ([$eventRaw, $productRaw] as $row) {
            $this->assertStringStartsWith(PrefixedEncryptedString::PREFIX, $row->email);
            $this->assertStringStartsWith(PrefixedEncryptedString::PREFIX, $row->last_error);
            $this->assertStringNotContainsString('example.test', $row->email);
            $this->assertStringNotContainsString('secret diagnostic', $row->last_error);
        }

        $this->assertSame('event-buyer@example.test', PaymentIntent::firstOrFail()->email);
        $this->assertSame('provider secret diagnostic', PaymentIntent::firstOrFail()->last_error);
        $this->assertSame('shop-buyer@example.test', ProductPaymentIntent::firstOrFail()->email);
        $this->assertSame('shop provider secret diagnostic', ProductPaymentIntent::firstOrFail()->last_error);

        $eventIntent = PaymentIntent::firstOrFail();
        $eventIntent->last_error = 'replacement private diagnostic';
        $eventIntent->save();
        $this->assertStringStartsWith(
            PrefixedEncryptedString::PREFIX,
            DB::table('payment_intents')->where('id', $eventIntent->id)->value('last_error')
        );

        $migration->down();
        $this->assertSame('event-buyer@example.test', DB::table('payment_intents')->value('email'));
        $this->assertSame('replacement private diagnostic', DB::table('payment_intents')->value('last_error'));
    }
}
