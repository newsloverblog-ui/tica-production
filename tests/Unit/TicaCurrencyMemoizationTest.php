<?php

namespace Tests\Unit;

use App\Support\TicaCurrency;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TicaCurrencyMemoizationTest extends TestCase
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

        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('base_currency_text')->nullable();
            $table->string('base_currency_symbol')->nullable();
        });
        DB::table('basic_settings')->insert([
            'base_currency_text' => 'GHS',
            'base_currency_symbol' => 'GH₵',
        ]);
        TicaCurrency::reset();
    }

    public function test_base_currency_settings_are_read_once_per_request(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        for ($i = 0; $i < 100; $i++) {
            $this->assertSame('GHS', TicaCurrency::base());
            $this->assertSame('GH₵', TicaCurrency::symbol('GHS'));
            $this->assertTrue(TicaCurrency::isBase('GHS'));
        }

        $reads = collect(DB::getQueryLog())->filter(
            fn (array $entry): bool => str_contains($entry['query'], 'from "basic_settings"')
        );
        $this->assertCount(1, $reads);
    }

    public function test_reset_exposes_an_admin_currency_change_immediately(): void
    {
        $this->assertSame('GHS', TicaCurrency::base());
        DB::table('basic_settings')->update([
            'base_currency_text' => 'NGN',
            'base_currency_symbol' => '₦',
        ]);

        $this->assertSame('GHS', TicaCurrency::base());
        TicaCurrency::reset();
        $this->assertSame('NGN', TicaCurrency::base());
        $this->assertSame('₦', TicaCurrency::symbol('NGN'));
    }

    public function test_event_cards_do_not_rebuild_checkout_configuration_for_currency_formatting(): void
    {
        $catalog = (string) file_get_contents(app_path('Services/TicaUi/EventCatalog.php'));

        $this->assertStringNotContainsString("? \$this->currency()['position']", $catalog);
        $this->assertStringNotContainsString('$currency = $this->currency();', $catalog);
        $this->assertStringContainsString("\$this->basic()->base_currency_symbol_position", $catalog);
    }
}
