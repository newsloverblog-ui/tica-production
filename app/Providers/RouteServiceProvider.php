<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
  /**
   * The path to the "home" route for your application.
   *
   * This is used by Laravel authentication to redirect users after login.
   *
   * @var string
   */
  public const HOME = '/home';

  /**
   * The controller namespace for the application.
   *
   * When present, controller route declarations will automatically be prefixed with this namespace.
   *
   * @var string|null
   */
  protected $namespace = 'App\\Http\\Controllers';

  /**
   * Define your route model bindings, pattern filters, etc.
   *
   * @return void
   */
  public function boot()
  {
    $this->configureRateLimiting();

    $this->routes(function () {
      Route::prefix('api')
        ->middleware('api')
        ->namespace($this->namespace)
        ->group(base_path('routes/api.php'));

      // Frontend routes 
      Route::middleware('web')
        ->namespace($this->namespace)
        ->group(base_path('routes/web.php'));

      // admin routes are goes 
      Route::middleware('web')
        ->namespace($this->namespace)
        ->group(base_path('routes/admin.php'));

      // organizer routes are goes 
      Route::middleware('web')
        ->namespace($this->namespace)
        ->group(base_path('routes/organizer.php'));
    });
  }

  /**
   * 
   * Configure the rate limiters for the application.
   *
   * @return void
   */
  protected function configureRateLimiting()
  {
    RateLimiter::for('api', function (Request $request) {
      return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
    });

    RateLimiter::for('authentication', function (Request $request) {
      $identity = strtolower((string) ($request->input('email') ?: $request->input('username')));
      return Limit::perMinute(10)->by($request->ip() . '|' . $identity);
    });

    RateLimiter::for('checkout', function (Request $request) {
      return Limit::perMinute(20)->by($request->ip() . '|' . $request->session()->getId());
    });

    RateLimiter::for('public-form', function (Request $request) {
      $route = $request->route()?->getName() ?: $request->path();
      return Limit::perMinute(10)->by($request->ip() . '|' . $route);
    });

    RateLimiter::for('scanner', function (Request $request) {
      $organizerId = $request->attributes->get('scanner_organizer_id') ?: auth('organizer')->id();
      $actor = auth('scanner')->id() ? ':staff:' . auth('scanner')->id() : '';
      return Limit::perMinute(180)->by('organizer:' . ($organizerId ?: $request->ip()) . $actor);
    });

    RateLimiter::for('subscriber-campaign-mail', function () {
      return Limit::perMinute(max(1, (int) config('mail.campaigns_per_minute', 60)))
        ->by('subscriber-campaign-mail');
    });

    RateLimiter::for('push-notification-campaign', function () {
      return Limit::perMinute(max(1, (int) config('services.webpush.campaigns_per_minute', 120)))
        ->by('push-notification-campaign');
    });
  }
}
