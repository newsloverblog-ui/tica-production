<?php

namespace Tests\Unit;

use App\Services\ConfiguredMailer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ConfiguredMailerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id(); $table->string('from_mail')->nullable(); $table->string('from_name')->nullable();
        });
        DB::table('basic_settings')->insert(['from_mail' => 'support@tica.test', 'from_name' => 'Tica']);
    }

    public function test_mail_uses_laravel_transport_from_identity_reply_to_and_attachment(): void
    {
        config()->set('mail.default', 'array');
        $sentMessage = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sentMessage): void {
            $sentMessage = $event->message;
        });
        $attachment = tempnam(sys_get_temp_dir(), 'tica-mail-');
        file_put_contents($attachment, 'private document');

        try {
            (new ConfiguredMailer())->sendHtml(
                'buyer@example.com', 'Your ticket', '<p>Ready</p>', [$attachment], 'reply@example.com'
            );

            $this->assertNotNull($sentMessage);
            $this->assertSame('buyer@example.com', $sentMessage->getTo()[0]->getAddress());
            $this->assertSame('support@tica.test', $sentMessage->getFrom()[0]->getAddress());
            $this->assertSame('reply@example.com', $sentMessage->getReplyTo()[0]->getAddress());
            $this->assertSame('Your ticket', $sentMessage->getSubject());
            $this->assertCount(1, $sentMessage->getAttachments());
        } finally {
            @unlink($attachment);
        }
    }

    public function test_invalid_recipient_and_missing_attachment_fail_closed(): void
    {
        $mailer = new ConfiguredMailer();

        try {
            $mailer->sendHtml('not-an-email', 'Subject', 'Body');
            $this->fail('Invalid recipients must fail closed.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $mailer->sendHtml('buyer@example.com', 'Subject', 'Body', ['/missing/private-document.pdf']);
    }
}
