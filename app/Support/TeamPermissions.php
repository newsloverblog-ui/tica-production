<?php

namespace App\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Reads config/tica_team.php: which role the signed-in organiser session has,
 * what that role may do, and which permission a route needs.
 */
class TeamPermissions
{
  /** The signed-in role: "owner" unless a team member is acting for the account. */
  public static function role(): string
  {
    $role = Session::get('tica_team_member.role');

    return is_string($role) && $role !== '' ? $role : 'owner';
  }

  public static function isOwner(): bool
  {
    return self::role() === 'owner';
  }

  public static function can(string $permission, ?string $role = null): bool
  {
    $granted = (array) config('tica_team.roles.' . ($role ?? self::role()), []);

    return in_array('*', $granted, true) || in_array($permission, $granted, true);
  }

  /** @return string[] */
  public static function permissionsFor(string $role): array
  {
    $granted = (array) config('tica_team.roles.' . $role, []);
    if (!in_array('*', $granted, true)) {
      return $granted;
    }
    $all = array_merge(
      array_keys((array) config('tica_team.routes', [])),
      array_values((array) config('tica_team.sections', [])),
      array_values((array) config('tica_team.pages', [])),
      array_values((array) config('tica_team.actions', []))
    );

    return array_values(array_unique(array_merge($all, ['bank.manage', 'team.manage', 'settings.manage', 'promote.pay'])));
  }

  /**
   * The permission a route needs. "common" means any signed-in member;
   * null means nothing maps it, which only the owner may reach.
   */
  public static function forRoute(?Route $route, ?string $section = null): ?string
  {
    if (!$route) {
      return null;
    }
    $name = (string) $route->getName();
    if ($name !== '' && in_array($name, (array) config('tica_team.common_routes', []), true)) {
      return 'common';
    }
    if ($name === 'tica.dashboard') {
      $sections = (array) config('tica_team.sections', []);

      return (string) ($sections[$section ?: 'overview'] ?? 'overview.view');
    }
    // Route names contain dots, so read the map directly rather than through dot notation.
    $pages = (array) config('tica_team.pages', []);
    if ($name !== '' && isset($pages[$name])) {
      return (string) $pages[$name];
    }

    $action = class_basename((string) $route->getActionName());
    if (in_array($action, (array) config('tica_team.common_actions', []), true)) {
      return 'common';
    }
    $actions = (array) config('tica_team.actions', []);
    if (isset($actions[$action])) {
      return (string) $actions[$action];
    }

    if ($name !== '') {
      foreach ((array) config('tica_team.routes', []) as $permission => $patterns) {
        foreach ((array) $patterns as $pattern) {
          if (Str::is($pattern, $name)) {
            return $permission === 'owner.only' ? null : (string) $permission;
          }
        }
      }
    }

    return null;
  }

  public static function allows(?Route $route, ?string $section = null, ?string $role = null): bool
  {
    $role ??= self::role();
    if ($role === 'owner') {
      return true;
    }
    $permission = self::forRoute($route, $section);

    return $permission === 'common' || ($permission !== null && self::can($permission, $role));
  }
}
