<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Services\PublicAssetStorage;
use Illuminate\Http\RedirectResponse;

class PublicAssetController extends Controller
{
    public function show(string $path, PublicAssetStorage $storage): RedirectResponse
    {
        abort_unless($storage->enabled(), 404);
        abort_if(
            $path === '' || str_contains($path, '..') || str_contains($path, "\0")
                || !preg_match('/^[A-Za-z0-9._\/-]+$/', $path),
            404
        );

        return redirect()->away($storage->publicUrl('assets/' . $path), 302, [
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
