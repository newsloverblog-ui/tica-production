<?php

namespace App\Services;

use Illuminate\Cache\RedisStore;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SessionHandlerInterface;
use Throwable;

class SharedStateHealthService
{
    /**
     * Verify the shared stores used for coordination and authenticated state.
     * The probes use short-lived, random records and always attempt cleanup.
     *
     * @return array{
     *     healthy: bool,
     *     cache: array{healthy: bool, message: string},
     *     session: array{healthy: bool, message: string}
     * }
     */
    public function evaluate(): array
    {
        $cache = $this->cache();
        $session = $this->session();

        return [
            'healthy' => $cache['healthy'] && $session['healthy'],
            'cache' => $cache,
            'session' => $session,
        ];
    }

    /** @return array{healthy: bool, message: string} */
    protected function cache(): array
    {
        $storeName = strtolower(trim((string) config('cache.default')));
        if (!in_array($storeName, ['redis', 'memcached', 'database'], true)) {
            return $this->failure('The configured cache is not a supported shared store.');
        }

        $key = 'ops:shared-state-probe:'.Str::uuid();
        $value = Str::random(40);
        $lock = null;
        $lockAcquired = false;

        try {
            $store = Cache::store($storeName);
            $written = $store->put($key, $value, now()->addMinute());
            $read = $store->get($key);
            $lock = $store->lock($key.':lock', 10);
            $lockAcquired = $lock->get();

            return $written !== false
                && hash_equals($value, is_string($read) ? $read : '')
                && $lockAcquired
                ? $this->success()
                : $this->failure('The shared cache write/read/lock probe did not round trip.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('The shared cache could not be reached.');
        } finally {
            if ($lock !== null && $lockAcquired) {
                try {
                    $lock->release();
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
            try {
                Cache::store($storeName)->forget($key);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /** @return array{healthy: bool, message: string} */
    protected function session(): array
    {
        $driver = strtolower(trim((string) config('session.driver')));
        if (!in_array($driver, ['redis', 'database'], true)) {
            return $this->failure('The configured session backend is not a supported shared store.');
        }

        $id = 'ops-shared-state-'.Str::uuid();
        $value = Str::random(40);
        $handler = null;

        try {
            // Never reuse the request-scoped session handler. Database session
            // handlers retain an internal "current record exists" flag, so
            // probing through that singleton can both false-fail and disturb
            // the authenticated request that invoked /health/ready.
            $handler = $this->makeSessionHandler($driver);
            $written = $handler->write($id, $value);
            $read = $handler->read($id);

            return $written && hash_equals($value, is_string($read) ? $read : '')
                ? $this->success()
                : $this->failure('The shared session write/read probe did not round trip.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('The shared session backend could not be reached.');
        } finally {
            if ($handler !== null) {
                try {
                    $handler->destroy($id);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    protected function makeSessionHandler(string $driver): SessionHandlerInterface
    {
        $minutes = max(1, (int) config('session.lifetime', 120));

        if ($driver === 'database') {
            return new DatabaseSessionHandler(
                DB::connection(config('session.connection')),
                (string) config('session.table', 'sessions'),
                $minutes
            );
        }

        $storeName = (string) (config('session.store') ?: 'redis');
        $cache = clone app('cache')->store($storeName);
        $store = $cache->getStore();
        if (!$store instanceof RedisStore) {
            throw new RuntimeException('The Redis session store is not backed by a Redis cache store.');
        }

        $store->setConnection(config('session.connection'));
        if ($prefix = config('session.prefix')) {
            $store->setPrefix((string) $prefix);
        }

        return new CacheBasedSessionHandler($cache, $minutes);
    }

    /** @return array{healthy: true, message: string} */
    private function success(): array
    {
        return ['healthy' => true, 'message' => 'Ready.'];
    }

    /** @return array{healthy: false, message: string} */
    private function failure(string $message): array
    {
        return ['healthy' => false, 'message' => $message];
    }
}
