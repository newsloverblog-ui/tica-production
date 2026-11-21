<?php

namespace Tests\Unit;

use App\Database\TicaPostgresConnection;
use Illuminate\Database\Connection;
use PDO;
use Tests\TestCase;

/**
 * Booleans must reach Supabase as '1'/'0': TICA's yes/no columns are mostly
 * smallint, and with emulated prepares Laravel would otherwise send 'false'.
 */
class PostgresBooleanBindingTest extends TestCase
{
    public function test_booleans_become_one_and_zero_with_and_without_emulated_prepares(): void
    {
        foreach ([true, false] as $emulated) {
            $connection = new TicaPostgresConnection(fn () => null, 'postgres', '', [
                'driver' => 'pgsql',
                'options' => [PDO::ATTR_EMULATE_PREPARES => $emulated],
            ]);

            $this->assertSame(['1', '0', 5, 'text', null], $connection->prepareBindings([true, false, 5, 'text', null]));
        }
    }

    public function test_the_pgsql_driver_uses_the_tica_connection(): void
    {
        $resolver = Connection::getResolver('pgsql');
        $this->assertNotNull($resolver);
        $this->assertInstanceOf(TicaPostgresConnection::class, $resolver(fn () => null, 'postgres', '', ['driver' => 'pgsql']));
    }
}
