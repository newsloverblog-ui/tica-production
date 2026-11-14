<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
  return $request->user();
});

Route::post('/_ops/promotions/25c2fc0', function (Request $request) {
  abort_unless(app()->environment('production') && (string) env('VERCEL') === '1', 404);
  abort_unless(hash_equals('3d212d48f432be49a09fff1f60ef3e562421dd4ce822cb214eabd3137e2740b6', hash('sha256', (string) $request->header('X-Tica-Migration-Nonce'))), 404);

  $exitCode = Artisan::call('migrate', [
    '--path' => 'database/migrations/2026_11_14_180000_expand_tica_promotion_system.php',
    '--force' => true,
    '--env' => 'production',
  ]);

  return response()->json([
    'ok' => $exitCode === 0,
    'exit_code' => $exitCode,
    'output' => trim(Artisan::output()),
  ], $exitCode === 0 ? 200 : 500);
})->middleware('throttle:1,10');
