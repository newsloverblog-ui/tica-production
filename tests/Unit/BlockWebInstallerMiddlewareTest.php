<?php

namespace Tests\Unit;

use App\Http\Middleware\BlockWebInstaller;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class BlockWebInstallerMiddlewareTest extends TestCase
{
    public function test_installer_and_updater_are_blocked_by_default(): void
    {
        config()->set('app.env', 'local');
        config()->set('installer.webInstallerEnabled', false);
        config()->set('installer.updaterEnabled', false);

        foreach (['/install', '/install/environment', '/update', '/update/final'] as $uri) {
            try {
                (new BlockWebInstaller())->handle(Request::create($uri), fn () => response('unsafe'));
                $this->fail("Web installer route was reachable: {$uri}");
            } catch (NotFoundHttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
    }

    public function test_explicit_local_enablement_can_reach_vendor_middleware(): void
    {
        config()->set('app.env', 'local');
        config()->set('installer.webInstallerEnabled', true);
        config()->set('installer.updaterEnabled', true);
        $middleware = new BlockWebInstaller();

        $this->assertSame('next', $middleware->handle(Request::create('/install'), fn () => 'next'));
        $this->assertSame('next', $middleware->handle(Request::create('/update/final'), fn () => 'next'));
    }

    public function test_production_blocks_installer_even_if_flags_are_misconfigured(): void
    {
        config()->set('app.env', 'production');
        config()->set('installer.webInstallerEnabled', true);
        config()->set('installer.updaterEnabled', true);

        $this->expectException(NotFoundHttpException::class);
        (new BlockWebInstaller())->handle(Request::create('/update/final'), fn () => response('unsafe'));
    }

    public function test_unrelated_application_routes_are_untouched(): void
    {
        config()->set('app.env', 'production');

        $result = (new BlockWebInstaller())->handle(Request::create('/events'), fn () => 'next');

        $this->assertSame('next', $result);
    }
}
