<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockWebInstaller
{
    public function handle(Request $request, Closure $next)
    {
        $installerRequest = $request->is('install', 'install/*');
        $updaterRequest = $request->is('update', 'update/*');

        if (!$installerRequest && !$updaterRequest) {
            return $next($request);
        }

        if ((string) config('app.env') === 'production') {
            abort(404);
        }

        if ($installerRequest && !(bool) config('installer.webInstallerEnabled', false)) {
            abort(404);
        }

        if ($updaterRequest && !(bool) config('installer.updaterEnabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
