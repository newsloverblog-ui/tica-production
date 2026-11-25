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
        $check = 'database';
        try {
            DB::select('select 1');
            $check = 'dependency';
            $queue = $queues->evaluate();
            $state = $sharedState->evaluate();
            $healthy = $queue['healthy'] && $state['healthy'];
        } catch (Throwable $exception) {
            Log::error("Readiness {$check} check failed.", [
                'exception_type' => $exception::class,
                'exception_code' => (string) $exception->getCode(),
            ]);
            $healthy = false;
        }

        return response()->json([
            'status' => $healthy ? 'ready' : 'unavailable',
        ], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
