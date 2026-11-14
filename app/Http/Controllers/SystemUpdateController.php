<?php

namespace App\Http\Controllers;

use App\Support\DatabaseUpdates;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /system/update — for hosts without a terminal (cPanel): applies database
 * updates when the admin can't log in yet because the database is too old.
 * Off unless DB_UPDATE_TOKEN (32+ characters) is set in .env; remove it after.
 * Uses no session, cookies or cache, so it works before those tables exist.
 */
class SystemUpdateController extends Controller
{
  public function show(): Response
  {
    abort_unless(self::token() !== null, 404);
    $s = DatabaseUpdates::status();

    return $this->page($s['ok']
      ? (count($s['pending']) ? count($s['pending']) . ' database update(s) waiting (' . $s['applied'] . ' of ' . $s['total'] . ' applied).' : 'The database is up to date. Remove DB_UPDATE_TOKEN from .env.')
      : 'The database could not be read: ' . e($s['error']), $s['ok'] && count($s['pending']) > 0);
  }

  public function apply(Request $request): Response
  {
    $token = self::token();
    abort_unless($token !== null, 404);
    if (!hash_equals($token, (string) $request->input('token'))) {
      usleep(800000);
      return $this->page('That update key is wrong.', true, 403);
    }
    $result = DatabaseUpdates::apply();
    $s = DatabaseUpdates::status();

    return $this->page(($result['ok'] ? 'Done. ' : 'Stopped before finishing. ') . (count($s['pending']) ? count($s['pending']) . ' update(s) still waiting.' : 'The database is up to date. Now remove DB_UPDATE_TOKEN from .env.'), count($s['pending']) > 0, 200, $result['output']);
  }

  private static function token(): ?string
  {
    $token = trim((string) config('app.db_update_token', ''));

    return strlen($token) >= 32 ? $token : null;
  }

  private function page(string $message, bool $form, int $status = 200, string $output = ''): Response
  {
    $html = '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
      . '<title>TICA database update</title><style>body{font:16px/1.5 system-ui,sans-serif;max-width:640px;margin:48px auto;padding:0 16px;color:#111827}input,button{font:inherit;padding:10px 12px;border-radius:8px;border:1px solid #CDD4DE}button{background:#2E4FD8;color:#fff;border-color:#2E4FD8;cursor:pointer}pre{white-space:pre-wrap;background:#F1F3F6;padding:12px;border-radius:8px;font-size:13px}</style>'
      . '<h1>TICA database update</h1><p>' . $message . '</p>';
    if ($form) {
      $html .= '<form method="post"><label for="t">Update key (DB_UPDATE_TOKEN from .env)</label><br><input id="t" name="token" type="password" required autocomplete="off" style="width:100%;margin:8px 0"><br><button type="submit">Apply updates</button></form><p><small>Back up the database first (cPanel → phpMyAdmin → Export).</small></p>';
    }
    if ($output !== '') {
      $html .= '<h2>What happened</h2><pre>' . e($output) . '</pre>';
    }

    return response($html, $status)->header('Cache-Control', 'no-store');
  }
}
