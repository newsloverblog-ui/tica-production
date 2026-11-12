<?php

namespace Tests\Unit;

use App\Http\Middleware\RequestId;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RequestIdMiddlewareTest extends TestCase
{
    public function test_it_generates_and_returns_a_request_id(): void
    {
        $request = Request::create('/health/live');
        $response = (new RequestId())->handle($request, fn () => new Response('ok'));

        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertSame(
            $request->attributes->get('request_id'),
            $response->headers->get('X-Request-ID')
        );
    }

    public function test_it_preserves_a_valid_upstream_request_id(): void
    {
        $request = Request::create('/health/live', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => 'edge-request-12345',
        ]);
        $response = (new RequestId())->handle($request, fn () => new Response('ok'));

        $this->assertSame('edge-request-12345', $response->headers->get('X-Request-ID'));
    }
}

