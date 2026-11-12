<?php

namespace App\Http\Controllers;

use App\Services\QueueHealthService;
use App\Services\SharedStateHealthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationalHealthController extends Controller
{
    public function ready(QueueHealthService $queues, SharedStateHealthService $sharedState)
    {
        $databaseHealthy = true;
        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            Log::error('Readiness database check failed.', [
                'exception_type' => $exception::class,
                'exception_code' => (string) $exception->getCode(),
            ]);
            $databaseHealthy = false;
        }

        $queue = $queues->evaluate();
        $state = $sharedState->evaluate();
        $healthy = $databaseHealthy && $queue['healthy'] && $state['healthy'];

        return response()->json([
            'status' => $healthy ? 'ready' : 'unavailable',
        ], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
