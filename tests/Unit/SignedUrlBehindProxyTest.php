<?php

namespace Tests\Unit;

use App\Http\Middleware\ValidateSignature;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Signed links must keep working behind Vercel, where PHP may see http, a
 * different host or an empty QUERY_STRING for the public https URL.
 */
class SignedUrlBehindProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://tica.example', 'app.key' => 'base64:' . base64_encode(str_repeat('k', 32))]);
        URL::forceRootUrl('https://tica.example');
        URL::forceScheme('https');
        Route::get('/signed-probe/{id}', fn () => 'ok')->name('signed.probe');
    }

    public function test_a_link_signed_for_https_passes_when_php_sees_http(): void
    {
        $url = URL::temporarySignedRoute('signed.probe', now()->addHour(), ['id' => 7]);
        $request = Request::create(str_replace('https://', 'http://', $url));

        $this->assertTrue($this->passes($request));
    }

    public function test_a_link_passes_when_the_query_string_only_arrives_in_the_request_uri(): void
    {
        $url = URL::temporarySignedRoute('signed.probe', now()->addHour(), ['id' => 7]);
        $request = Request::create(str_replace('https://tica.example', 'http://internal.vercel', $url));
        $request->server->set('QUERY_STRING', '');

        $this->assertTrue($this->passes($request));
    }

    public function test_platform_parameters_are_ignored_and_relative_links_still_work(): void
    {
        $url = URL::temporarySignedRoute('signed.probe', now()->addHour(), ['id' => 7]) . '&_vercel_share=abc';
        $this->assertTrue($this->passes(Request::create($url)));

        $relative = URL::temporarySignedRoute('signed.probe', now()->addHour(), ['id' => 7], false);
        $this->assertTrue($this->passes(Request::create('http://internal.vercel' . $relative), 'relative'));
    }

    public function test_tampered_expired_and_unsigned_links_are_still_refused(): void
    {
        $url = URL::temporarySignedRoute('signed.probe', now()->addHour(), ['id' => 7]);
        $this->assertFalse($this->passes(Request::create(str_replace('signed-probe/7', 'signed-probe/8', $url))));

        $expired = URL::temporarySignedRoute('signed.probe', now()->subMinute(), ['id' => 7]);
        $this->assertFalse($this->passes(Request::create($expired)));

        $this->assertFalse($this->passes(Request::create('https://tica.example/signed-probe/7')));
        $this->assertFalse($this->passes(Request::create(preg_replace('/signature=[a-f0-9]+/', 'signature=' . str_repeat('0', 64), $url))));
    }

    public function test_the_app_uses_this_middleware_and_trusts_vercels_scheme_headers(): void
    {
        $this->assertSame(ValidateSignature::class, app('router')->getMiddleware()['signed'] ?? null);
        $proxy = file_get_contents(app_path('Http/Middleware/TrustProxies.php'));
        $this->assertStringContainsString("protected \$proxies = '*'", $proxy);
        $this->assertStringContainsString('HEADER_X_FORWARDED_PROTO', $proxy);
        $this->assertStringNotContainsString('HEADER_X_FORWARDED_FOR', $proxy);
    }

    private function passes(Request $request, string ...$args): bool
    {
        try {
            app(ValidateSignature::class)->handle($request, fn () => response('ok'), ...$args);

            return true;
        } catch (InvalidSignatureException) {
            return false;
        }
    }
}
