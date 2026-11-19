<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\ValidateSignature as Middleware;
use Illuminate\Support\Carbon;

/**
 * Signed links (payment recovery, booking cancel, email verification,
 * downloads) behind Vercel.
 *
 * Laravel checks a signature against the URL the request arrived on. Behind
 * Vercel's edge the PHP runtime can see a different scheme (http), host or an
 * empty QUERY_STRING than the public https URL the link was signed for, so a
 * perfectly valid link failed with "403 Invalid signature". When Laravel's own
 * check fails, this re-checks the same HMAC against the public forms of the
 * URL: https on the request host, the forwarded host and APP_URL. The secret,
 * the expiry and the query parameters are unchanged, so nothing that was not
 * signed by TICA can pass.
 */
class ValidateSignature extends Middleware
{
    /** Parameters platforms add to a URL that TICA never signs. */
    protected $ignore = ['_vercel_share', '_vercel_jwt'];

    public function handle($request, Closure $next, ...$args)
    {
        [$relative, $ignore] = $this->parseArguments($args);

        if ($request->hasValidSignatureWhileIgnoring($ignore, !$relative) || $this->publicUrlMatches($request, $ignore, $relative)) {
            return $next($request);
        }

        throw new InvalidSignatureException;
    }

    private function publicUrlMatches(Request $request, array $ignore, bool $relative): bool
    {
        $signature = $request->query('signature');
        if (!is_string($signature) || $signature === '') {
            return false;
        }
        $expires = $request->query('expires');
        if ($expires !== null && (!is_string($expires) || ($expires !== '' && Carbon::now()->getTimestamp() > (int) $expires))) {
            return false;
        }

        $query = $this->queryString($request, $ignore);
        $path = $request->getBaseUrl() . $request->getPathInfo();
        // Laravel signs relative links over '/' . path(), absolute ones over the full URL.
        $bases = ['/' . $request->path()];
        $appUrl = (string) config('app.url');
        $appHost = parse_url($appUrl, PHP_URL_HOST);
        $appPort = parse_url($appUrl, PHP_URL_PORT);
        $hosts = array_filter([
            $request->getHttpHost(),
            $this->forwardedHost($request),
            $appHost ? $appHost . ($appPort ? ':' . $appPort : '') : null,
        ]);
        foreach (array_unique($hosts) as $host) {
            foreach (array_unique(['https', $request->getScheme()]) as $scheme) {
                $bases[] = rtrim($scheme . '://' . $host . $path, '/');
            }
        }

        $keys = (array) config('app.key');
        foreach ((array) (config('app.previous_keys') ?? []) as $previous) {
            $keys[] = $previous;
        }
        foreach (array_unique($bases) as $base) {
            $original = rtrim($base . '?' . $query, '?');
            foreach (array_filter($keys) as $key) {
                if (hash_equals(hash_hmac('sha256', $original, (string) $key), $signature)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** The raw query, in the order it was signed, without the signature and ignored parameters. */
    private function queryString(Request $request, array $ignore): string
    {
        $raw = (string) ($request->server->get('QUERY_STRING') ?? '');
        if ($raw === '') {
            $uri = (string) $request->server->get('REQUEST_URI', '');
            $raw = str_contains($uri, '?') ? substr($uri, strpos($uri, '?') + 1) : '';
        }
        $parts = array_filter(explode('&', $raw), function (string $parameter) use ($ignore) {
            $name = urldecode(explode('=', $parameter, 2)[0]);

            return $parameter !== '' && $name !== 'signature' && !in_array($name, $ignore, true);
        });

        return implode('&', $parts);
    }

    private function forwardedHost(Request $request): ?string
    {
        $host = trim(explode(',', (string) $request->headers->get('X-Forwarded-Host', ''))[0]);

        return $host !== '' && preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host) ? $host : null;
    }
}
