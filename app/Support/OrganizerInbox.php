<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The organiser's bell: short, plain messages about what TICA decided.
 *
 * Writing happens after the response is sent, so an admin's click never
 * waits on it, and a failure (or the table not existing yet) is logged and
 * never breaks the admin action that triggered it.
 */
class OrganizerInbox
{
  public const KEEP = 50;

  public static function send(int $organizerId, string $kind, string $title, ?string $body = null, ?string $url = null): void
  {
    if ($organizerId <= 0) {
      return;
    }
    $write = static function () use ($organizerId, $kind, $title, $body, $url): void {
      self::write($organizerId, $kind, $title, $body, $url);
    };
    app()->runningInConsole() || app()->runningUnitTests() ? $write() : dispatch($write)->afterResponse();
  }

  public static function write(int $organizerId, string $kind, string $title, ?string $body = null, ?string $url = null): void
  {
    try {
      if (!self::ready()) {
        return;
      }
      DB::table('organizer_notifications')->insert([
        'organizer_id' => $organizerId,
        'kind' => mb_substr($kind, 0, 40),
        'title' => mb_substr($title, 0, 191),
        'body' => $body === null ? null : mb_substr($body, 0, 500),
        'url' => $url === null ? null : mb_substr($url, 0, 500),
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    } catch (Throwable $e) {
      Log::warning('Organiser notification not saved', ['organizer_id' => $organizerId, 'kind' => $kind, 'error' => $e->getMessage()]);
    }
  }

  /** @return array{unread:int, items:array<int, array<string, mixed>>} */
  public static function latest(int $organizerId, int $limit = 10): array
  {
    try {
      if ($organizerId <= 0 || !self::ready()) {
        return ['unread' => 0, 'items' => []];
      }
      $rows = DB::table('organizer_notifications')->where('organizer_id', $organizerId)->orderByDesc('id')->limit($limit)->get();

      return [
        'unread' => DB::table('organizer_notifications')->where('organizer_id', $organizerId)->whereNull('read_at')->count(),
        'items' => $rows->map(fn ($n) => [
          'id' => (int) $n->id,
          'kind' => $n->kind,
          'title' => $n->title,
          'body' => $n->body,
          'url' => $n->url,
          'read' => $n->read_at !== null,
          'when' => \Carbon\Carbon::parse($n->created_at)->diffForHumans(),
        ])->all(),
      ];
    } catch (Throwable $e) {
      Log::warning('Organiser notifications not read', ['error' => $e->getMessage()]);
      return ['unread' => 0, 'items' => []];
    }
  }

  public static function markAllRead(int $organizerId): void
  {
    try {
      if ($organizerId > 0 && self::ready()) {
        DB::table('organizer_notifications')->where('organizer_id', $organizerId)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
      }
    } catch (Throwable $e) {
      Log::warning('Organiser notifications not marked read', ['error' => $e->getMessage()]);
    }
  }

  private static function ready(): bool
  {
    return Schema::hasTable('organizer_notifications');
  }
}
