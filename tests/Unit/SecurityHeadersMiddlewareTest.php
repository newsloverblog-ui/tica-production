<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersMiddlewareTest extends TestCase
{
    public function test_it_adds_safe_default_headers_in_report_only_mode(): void
    {
        config([
            'security.enforce_content_security_policy' => false,
            'security.content_security_policy' => "default-src 'self'",
        ]);

        $response = (new SecurityHeaders())->handle(
            Request::create('/'),
            fn () => new Response('ok')
        );

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame("default-src 'self'", $response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_embed_uses_enforced_exact_frame_ancestors_without_legacy_x_frame_header(): void
    {
        config([
            'security.enforce_content_security_policy' => false,
            'security.content_security_policy' => "default-src 'self'; frame-ancestors 'self'; form-action 'self'",
        ]);
        $request = Request::create('/event/1/registration/embed');
        $request->attributes->set('secure_embed_frame_ancestors', [
            'https://events.example.com', 'http://localhost:5173',
        ]);

        $response = (new SecurityHeaders())->handle($request, fn () => new Response('ok'));

        $this->assertNull($response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertStringContainsString(
            "frame-ancestors 'self' https://events.example.com http://localhost:5173",
            (string) $response->headers->get('Content-Security-Policy')
        );
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}

