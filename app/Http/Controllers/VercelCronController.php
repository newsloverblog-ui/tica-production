<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VercelCronController extends Controller
{
    // Leave ten seconds for the HTTP response and Vercel's runtime overhead.
    private const WORK_DEADLINE_SECONDS = 50;
    private const QUEUE_DRAIN_SECONDS = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.vercel.cron_secret');
        $provided = (string) $request->bearerToken();

        if ($secret === '' || $provided === '' || !hash_equals($secret, $provided)) {
            throw new HttpException(401, 'Invalid scheduler credentials.');
        }

        $startedAt = (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));
        $lock = Cache::lock('tica:vercel-cron', 65);
        if (!$lock->get()) {
            return response()->json(['status' => 'already-running'], 202);
        }

        try {
            $scheduleCode = Artisan::call('schedule:run', [
                '--no-interaction' => true,
            ]);
            $scheduleOutput = trim(Artisan::output());

            $remaining = self::WORK_DEADLINE_SECONDS - (int) ceil(microtime(true) - $startedAt);
            $connection = (string) config('queue.default');
            $queueCode = null;
            $queueOutput = '';
            if ($remaining > 0 && config("queue.connections.{$connection}.driver") !== 'sync') {
                $queueCode = Artisan::call('queue:work', [
                    'connection' => $connection,
                    '--queue' => 'payments,default',
                    '--stop-when-empty' => true,
                    '--max-time' => min($remaining, self::QUEUE_DRAIN_SECONDS),
                    '--sleep' => 1,
                    '--tries' => 3,
                    '--no-interaction' => true,
                ]);
                $queueOutput = trim(Artisan::output());
            }

            $ok = $scheduleCode === 0 && ($queueCode === null || $queueCode === 0);

            return response()->json([
                'status' => $ok ? 'ok' : 'failed',
                'schedule_exit_code' => $scheduleCode,
                'queue_exit_code' => $queueCode,
                'schedule_output' => mb_substr($scheduleOutput, 0, 2000),
                'queue_output' => mb_substr($queueOutput, 0, 2000),
            ], $ok ? 200 : 500)->header('Cache-Control', 'no-store');
        } finally {
            $lock->release();
        }
    }
}

