<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

class AuditConfigurationChanges
{
    private const ROUTE_PREFIXES = [
        'admin.basic_settings.',
        'admin.payment_gateways.',
        'admin.withdraw_payment_method.',
        'admin.organizer_management.setting.',
        'admin.support_ticket.update_setting',
        'admin.product.setting.',
        'admin.advertise.',
        'admin.user_management.push_notification.',
        'admin.pwa.',
        'admin.event_booking.settings.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $route = $request->route();
        if (!$route instanceof Route || !$this->shouldAudit($request, $route, $response)) {
            return $response;
        }

        $fieldNames = array_values(array_filter(
            array_keys($request->except(['_token', '_method'])),
            fn (string $key) => !$request->hasFile($key)
        ));
        sort($fieldNames);
        $fileFields = array_keys($request->allFiles());
        sort($fileFields);

        AuditLogger::record('configuration.changed', null, [], [], [
            'route' => $route->getName(),
            'method' => $request->getMethod(),
            'changed_fields' => $fieldNames,
            'file_fields' => $fileFields,
            'route_parameters' => $this->safeRouteParameters($route),
            'response_status' => $response->getStatusCode(),
        ]);

        return $response;
    }

    private function shouldAudit(Request $request, Route $route, Response $response): bool
    {
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true) || $response->getStatusCode() >= 400) {
            return false;
        }

        $name = (string) $route->getName();
        foreach (self::ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function safeRouteParameters(Route $route): array
    {
        $parameters = [];
        foreach ($route->parameters() as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $parameters[$key] = $value;
            } elseif (is_object($value) && method_exists($value, 'getKey')) {
                $parameters[$key] = $value->getKey();
            }
        }

        return $parameters;
    }
}
