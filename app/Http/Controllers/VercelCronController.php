<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VercelCronController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.vercel.cron_secret');
        $provided = (string) $request->bearerToken();

        if ($secret === '' || $provided === '' || !hash_equals($secret, $provided)) {
            throw new HttpException(401, 'Invalid scheduler credentials.');
        }

        $lock = Cache::lock('tica:vercel-cron', 55);
        if (!$lock->get()) {
            return response()->json(['status' => 'already-running'], 202);
        }

        try {
            $scheduleCode = Artisan::call('schedule:run', [
                '--no-interaction' => true,
            ]);
            $scheduleOutput = trim(Artisan::output());

            $queueCode = Artisan::call('queue:work', [
                'connection' => (string) config('queue.default'),
                '--queue' => 'payments,default',
                '--stop-when-empty' => true,
                '--max-time' => 120,
                '--sleep' => 1,
                '--tries' => 3,
                '--no-interaction' => true,
            ]);
            $queueOutput = trim(Artisan::output());

            $ok = $scheduleCode === 0 && $queueCode === 0;

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

