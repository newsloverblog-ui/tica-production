<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * TICA runs behind Vercel's edge, which terminates HTTPS and sets these
     * headers itself. Trusting the scheme, host and port lets Laravel see the
     * public https URL, so signed links and generated URLs match what people
     * open. X-Forwarded-For stays untrusted: client IPs are not taken from a
     * header a caller could add.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}
