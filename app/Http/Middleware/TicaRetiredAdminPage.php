<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Admin pages from the old template that no longer change anything on the
 * TICA site. Opening one goes to the Website tab that now does the job.
 */
class TicaRetiredAdminPage
{
  /** [route name patterns, Website tab] */
  private const RETIRED = [
    [['admin.home_page.*'], 'home'],
    [['admin.menu_builder', 'admin.menu_builder.*'], 'menu'],
    [['admin.footer.*', 'admin.basic_settings.footer_logo'], 'footer'],
    [['admin.basic_settings.contact_page', 'admin.contact.page', 'admin.basic_settings.social_medias'], 'contact'],
    [['admin.advertise.*', 'admin.announcement_popups', 'admin.announcement_popups.*', 'admin.basic_settings.breadcrumb', 'admin.basic_settings.page_headings', 'admin.basic_settings.cookie_alert'], 'home'],
  ];

  public function handle(Request $request, Closure $next)
  {
    if (!$request->isMethod('GET') || !$request->route()) {
      return $next($request);
    }
    foreach (self::RETIRED as [$patterns, $tab]) {
      if ($request->routeIs(...$patterns)) {
        return redirect()->route('admin.tica.website', ['tab' => $tab])
          ->with('warning', __('That page belonged to the old site design and has no effect on TICA. Edit the website here instead.'));
      }
    }

    return $next($request);
  }
}
