<?php

namespace Tests\Unit;

use App\Http\Controllers\VercelCronController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class VercelCronControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.vercel.cron_secret' => 'local-scheduler-fixture', 'cache.default' => 'array', 'queue.default' => 'database']);
        Artisan::shouldReceive('call')->with('schedule:run', ['--no-interaction' => true])->once()->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('');
    }

    public function test_queue_drain_uses_only_the_remaining_http_budget(): void
    {
        Artisan::shouldReceive('call')->once()->withArgs(fn ($command, $options) =>
            $command === 'queue:work' && $options['--max-time'] > 0 && $options['--max-time'] <= 9
        )->andReturn(0);

        $response = (new VercelCronController())($this->cronRequest(41));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $response->getData(true)['queue_exit_code']);
    }

    public function test_scheduler_does_not_start_queue_work_after_budget_expires(): void
    {
        $response = (new VercelCronController())($this->cronRequest(51));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->getData(true)['queue_exit_code']);
    }

    private function cronRequest(int $elapsed): Request
    {
        return Request::create('/api/vercel/cron', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer local-scheduler-fixture',
            'REQUEST_TIME_FLOAT' => microtime(true) - $elapsed,
        ]);
    }
}
