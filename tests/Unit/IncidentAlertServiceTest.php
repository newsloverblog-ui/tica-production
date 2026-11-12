<?php

namespace Tests\Unit;

use App\Services\IncidentAlertService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class IncidentAlertServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('i', 32)));
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('operational_incidents', function (Blueprint $table) {
            $table->id(); $table->uuid('public_id')->unique(); $table->char('key_hash', 64)->unique();
            $table->string('incident_key', 191); $table->string('severity', 16); $table->string('status', 16);
            $table->string('title', 500); $table->unsignedBigInteger('occurrence_count')->default(0);
            $table->longText('context')->nullable(); $table->json('delivery_status')->nullable();
            $table->timestamp('first_seen_at'); $table->timestamp('last_seen_at');
            $table->timestamp('last_alerted_at')->nullable(); $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable(); $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->timestamps();
        });
        config()->set('cache.default', 'array');
        config()->set('app.env', 'production');
        config()->set('app.url', 'https://tica.example');
        config()->set('operations.incident_email', 'oncall@tica.example');
        config()->set('operations.incident_webhook_url', 'https://alerts.example.test/tica');
        config()->set('operations.incident_webhook_secret', str_repeat('s', 32));
        Cache::flush();
    }

    public function test_alert_is_logged_emailed_and_sent_to_a_signed_webhook(): void
    {
        Log::spy();
        Mail::shouldReceive('raw')->once();
        Http::fake(['https://alerts.example.test/*' => Http::response(['ok' => true], 202)]);

        $result = app(IncidentAlertService::class)->alert('queue:payments', 'Payments queue is stale.', [
            'queue' => 'payments', 'token' => 'must-not-leak',
        ]);

        $this->assertTrue($result['sent']);
        $this->assertNotNull($result['incident_id']);
        $this->assertSame(['email' => 'sent', 'webhook' => 'sent'], $result['destinations']);
        $this->assertDatabaseHas('operational_incidents', [
            'incident_key' => 'queue:payments', 'status' => 'open', 'occurrence_count' => 1,
        ]);
        $rawContext = (string) DB::table('operational_incidents')->value('context');
        $this->assertStringNotContainsString('payments', $rawContext);
        Log::shouldHaveReceived('critical')->once();
        Http::assertSent(function ($request): bool {
            $payload = json_decode($request->body(), true);
            $expected = hash_hmac('sha256', $request->body(), str_repeat('s', 32));
            return $request->url() === 'https://alerts.example.test/tica'
                && $request->hasHeader('X-Tica-Alert-Id')
                && $request->header('X-Tica-Signature')[0] === $expected
                && $payload['context']['token'] === '[REDACTED]';
        });
    }

    public function test_duplicate_alert_is_suppressed_during_cooldown(): void
    {
        config()->set('operations.incident_email', null);
        Http::fake(['https://alerts.example.test/*' => Http::response([], 204)]);

        $first = app(IncidentAlertService::class)->alert('same-incident', 'First alert');
        $second = app(IncidentAlertService::class)->alert('same-incident', 'Repeated alert');

        $this->assertFalse($first['suppressed']);
        $this->assertTrue($second['suppressed']);
        $this->assertDatabaseHas('operational_incidents', [
            'incident_key' => 'same-incident', 'occurrence_count' => 2,
        ]);
        Http::assertSentCount(1);
    }

    public function test_new_occurrence_reopens_a_resolved_incident(): void
    {
        config()->set('operations.incident_email', null);
        config()->set('operations.incident_webhook_url', null);
        $service = app(IncidentAlertService::class);
        $service->alert('reopen-me', 'Initial incident');
        DB::table('operational_incidents')->where('incident_key', 'reopen-me')->update([
            'status' => 'resolved', 'resolved_at' => now(), 'resolution_note' => 'Recovered',
            'resolved_by_admin_id' => 9,
        ]);
        Cache::flush();

        $service->alert('reopen-me', 'Incident returned');

        $this->assertDatabaseHas('operational_incidents', [
            'incident_key' => 'reopen-me', 'status' => 'open', 'occurrence_count' => 2,
            'resolution_note' => null, 'resolved_by_admin_id' => null,
        ]);
    }

    public function test_delivery_failure_logs_only_safe_exception_metadata(): void
    {
        Log::spy();
        config()->set('operations.incident_webhook_url', null);
        Mail::shouldReceive('raw')->once()
            ->andThrow(new RuntimeException('SMTP response contains recipient detail', 451));

        $result = app(IncidentAlertService::class)->alert(
            'mail-transport-failed',
            'Incident email could not be delivered.'
        );

        $this->assertSame('failed', $result['destinations']['email']);
        Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context): bool {
            return $message === 'Incident email delivery failed.'
                && !array_key_exists('exception', $context)
                && $context['exception_type'] === RuntimeException::class
                && $context['exception_code'] === 451;
        });
    }
}
