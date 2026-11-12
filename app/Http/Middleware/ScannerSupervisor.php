<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ScannerSupervisor
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->attributes->get('scanner_access_role'), ['organizer', 'supervisor'], true)) {
            return $next($request);
        }

        return response()->json(['message' => 'Gate supervisor access is required.'], 403);
    }
}
