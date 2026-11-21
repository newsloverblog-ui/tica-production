<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection;

/**
 * Postgres connection for TICA's Supabase database.
 *
 * TICA's tables came from MySQL, so most yes/no columns are smallint
 * (is_required, status flags…), not boolean. On Vercel the connection uses
 * emulated prepares (Supabase's pooler), where Laravel writes PHP booleans as
 * 'true' / 'false'. Postgres rejects those for smallint columns:
 * "invalid input syntax for type smallint: "false"", which broke saving
 * registration-form questions and any other true/false write.
 *
 * '1' and '0' are valid input for smallint, integer and boolean columns
 * alike, so booleans are sent that way instead.
 */
class TicaPostgresConnection extends PostgresConnection
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? '1' : '0';
            }
        }

        return parent::prepareBindings($bindings);
    }
}
