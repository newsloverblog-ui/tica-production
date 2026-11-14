<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Database updates (Laravel migrations) that this code expects but the
 * connected database hasn't had yet, and a safe way to apply them from the
 * admin. Works the same on cPanel MySQL/MariaDB and on Supabase Postgres:
 * Laravel writes the right SQL for whichever database is connected, and also
 * runs the data steps (such as encrypting saved keys) a hand-written SQL file
 * can't.
 */
class DatabaseUpdates
{
  /** @return array{ok:bool, pending:array<int,string>, applied:int, total:int, driver:string, error:?string} */
  public static function status(): array
  {
    $driver = (string) config('database.connections.' . config('database.default') . '.driver');
    try {
      $migrator = app('migrator');
      $files = array_keys($migrator->getMigrationFiles([database_path('migrations')]));
      $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
      $pending = array_values(array_diff($files, $ran));
      sort($pending);

      return ['ok' => true, 'pending' => $pending, 'applied' => count($files) - count($pending), 'total' => count($files), 'driver' => $driver, 'error' => null];
    } catch (Throwable $e) {
      return ['ok' => false, 'pending' => [], 'applied' => 0, 'total' => 0, 'driver' => $driver, 'error' => $e->getMessage()];
    }
  }

  public static function pendingCount(): int
  {
    try {
      return Cache::remember('tica:db-updates-pending', 300, fn () => count(self::status()['pending']));
    } catch (Throwable) {
      return count(self::status()['pending']); // the cache table itself may be one of the missing updates
    }
  }

  /**
   * Applies every pending update, one at a time, stopping at the first that
   * fails. Updates already applied are never run again, so it can be repeated.
   *
   * @return array{ok:bool, output:string}
   */
  public static function apply(): array
  {
    try {
      $lock = Cache::lock('tica:db-updates-running', 600);
      if (!$lock->get()) {
        return ['ok' => false, 'output' => 'Another admin is applying updates right now. Wait a minute and refresh.'];
      }
    } catch (Throwable) {
      $lock = null; // no cache table yet (first upgrade): run without the lock
    }
    @set_time_limit(600);
    @ignore_user_abort(true);
    $buffer = new BufferedOutput();
    try {
      $code = Artisan::call('migrate', ['--force' => true, '--step' => true], $buffer);
      $ok = $code === 0;
    } catch (Throwable $e) {
      $ok = false;
      $buffer->writeln('Stopped: ' . $e->getMessage());
      Log::error('Database update failed', ['exception' => $e::class, 'message' => $e->getMessage()]);
    } finally {
      rescue(fn () => $lock?->release(), null, false);
      rescue(fn () => Cache::forget('tica:db-updates-pending'), null, false);
    }

    return ['ok' => $ok, 'output' => trim(preg_replace('/\e\[[\d;]*m/', '', $buffer->fetch()))];
  }
}
