<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * database/supabase/tica_database_update.sql is the one script for the live
 * database. It must cover every migration and every table the app uses, so
 * its health check can say "All good" only when the database really is.
 */
class DatabaseUpdateScriptTest extends TestCase
{
  private function script(): string
  {
    return (string) file_get_contents(dirname(__DIR__, 2) . '/database/supabase/tica_database_update.sql');
  }

  public function test_it_is_the_only_sql_file_to_run(): void
  {
    $files = array_map('basename', glob(dirname(__DIR__, 2) . '/database/supabase/*.sql'));
    $this->assertSame(['tica_database_update.sql'], $files);
  }

  public function test_health_check_knows_every_migration(): void
  {
    $sql = $this->script();
    foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.php') as $file) {
      $name = basename($file, '.php');
      $this->assertStringContainsString("('{$name}')", $sql, "Add {$name} to the health check (and its schema change above it if the live database needs it).");
    }
  }

  public function test_health_check_knows_every_table_a_migration_creates(): void
  {
    $sql = $this->script();
    foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.php') as $file) {
      $up = explode('function down', (string) file_get_contents($file))[0];
      preg_match_all("/Schema::create\\('([a-z_0-9]+)'/", $up, $m);
      foreach ($m[1] as $table) {
        $this->assertStringContainsString("('{$table}')", $sql, "{$table} from " . basename($file));
      }
    }
  }

  public function test_it_never_drops_or_deletes(): void
  {
    // "DROP NOT NULL" only relaxes a rule (a column may be empty); it removes nothing.
    $sql = preg_replace('/\bDROP\s+NOT\s+NULL\b/i', '', preg_replace('/--.*$/m', '', $this->script()));
    $this->assertDoesNotMatchRegularExpression('/\b(DROP|DELETE|TRUNCATE)\b/i', $sql);
  }

  public function test_migration_rows_are_added_once(): void
  {
    // An INSERT ... SELECT MAX(...) ... WHERE NOT EXISTS still inserts when the
    // row exists (aggregates always return a row); the helper checks first.
    $sql = $this->script();
    $this->assertStringContainsString('IF EXISTS (SELECT 1 FROM public.migrations WHERE migration = name) THEN', $sql);
    $this->assertDoesNotMatchRegularExpression('/INSERT INTO migrations[^;]*FROM migrations\s+WHERE NOT EXISTS/i', $sql);
  }

  public function test_organizer_order_index_is_created_and_recorded(): void
  {
    $sql = $this->script();

    $this->assertStringContainsString(
      'CREATE INDEX IF NOT EXISTS bookings_organizer_orders_idx',
      $sql
    );
    $this->assertStringContainsString(
      "pg_temp.tica_mark_migration('2026_11_13_100000_add_organizer_order_index_to_bookings')",
      $sql
    );
  }
}
