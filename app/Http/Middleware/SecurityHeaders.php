<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $frameAncestors = $request->attributes->get('secure_embed_frame_ancestors');
        if (!is_array($frameAncestors) || $frameAncestors === []) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        } else {
            $response->headers->remove('X-Frame-Options');
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        }
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(self), payment=(self)'
        );

        $policy = trim((string) config('security.content_security_policy'));
        if (is_array($frameAncestors) && $frameAncestors !== []) {
            $directive = "frame-ancestors 'self' ".implode(' ', $frameAncestors);
            if (preg_match('/(?:^|;)\s*frame-ancestors\s+[^;]*/i', $policy)) {
                $policy = preg_replace('/(?:^|;)\s*frame-ancestors\s+[^;]*/i', '; '.$directive, $policy, 1);
                $policy = ltrim(trim((string) $policy), '; ');
            } else {
                $policy = rtrim($policy, '; ').'; '.$directive;
            }
        }
        if ($policy !== '') {
            $header = (is_array($frameAncestors) && $frameAncestors !== []) || config('security.enforce_content_security_policy')
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';
            $response->headers->set($header, $policy);
            if ($header === 'Content-Security-Policy') {
                $response->headers->remove('Content-Security-Policy-Report-Only');
            }
        }

        if ($request->isSecure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

