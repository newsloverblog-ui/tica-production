<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
  /**
   * The application's global HTTP middleware stack.
   *
   * These middleware are run during every request to your application.
   *
   * @var array
   */
  protected $middleware = [
    \App\Http\Middleware\TrustProxies::class,
    \App\Http\Middleware\RequestId::class,
    \App\Http\Middleware\BlockWebInstaller::class,
    \Illuminate\Http\Middleware\HandleCors::class,
    \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
    \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
    \App\Http\Middleware\TrimStrings::class,
    \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    \App\Http\Middleware\SecurityHeaders::class,
  ];

  /**
   * The application's route middleware groups.
   *
   * @var array
   */
  protected $middlewareGroups = [
    'web' => [
      \App\Http\Middleware\EncryptCookies::class,
      \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
      \Illuminate\Session\Middleware\StartSession::class,
      \Illuminate\View\Middleware\ShareErrorsFromSession::class,
      \App\Http\Middleware\VerifyCsrfToken::class,
      \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],

    'api' => [
      'throttle:api',
      \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
  ];

  /**
   * The application's route middleware.
   *
   * These middleware may be assigned to groups or used individually.
   *
   * @var array
   */
  protected $routeMiddleware = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
    'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
    'can' => \Illuminate\Auth\Middleware\Authorize::class,
    'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
    'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
    'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
    'change.lang' => \App\Http\Middleware\ChangeLanguage::class,
    'exists.down' => \App\Http\Middleware\RedirectIfDownFileExists::class,
    'account.status' => \App\Http\Middleware\PreventRequestsForDeactivatedAccount::class,
    'scanner.device' => \App\Http\Middleware\VerifyScannerDevice::class,
    'scanner.access' => \App\Http\Middleware\ScannerAccess::class,
    'scanner.supervisor' => \App\Http\Middleware\ScannerSupervisor::class,
    'tica.team' => \App\Http\Middleware\TicaTeamRole::class,
    'tica.no-shop' => \App\Http\Middleware\TicaShopRemoved::class,
    'tica.retired' => \App\Http\Middleware\TicaRetiredAdminPage::class,
    'permission' => \App\Http\Middleware\HasPermission::class,
    'webhook.receipt' => \App\Http\Middleware\TrackWebhookReceipt::class,
    'payment.gateway' => \App\Http\Middleware\EnsurePaymentGatewayAllowed::class,
    'audit.configuration' => \App\Http\Middleware\AuditConfigurationChanges::class,
    'Demo' => \App\Http\Middleware\Demo::class,
    'certificate.status' => \App\Http\Middleware\EnsureCertificateIsEnable::class,
    'Deactive' => \App\Http\Middleware\Deactive::class,
    'EmailStatus' => \App\Http\Middleware\EmailStatus::class,
    "adminLang" => \App\Http\Middleware\SetLangMiddleware::class
  ];
}
