<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\BasicSettings\MailTemplateController;
use App\Jobs\SendApplicationMailDelivery;
use App\Models\BasicSettings\MailTemplate;
use App\Services\MailTemplateService;
use App\Support\LifecycleMailCatalog;
use App\Support\MailTemplateCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionalMailTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('session.driver', 'array');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('mail_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('mail_type')->unique();
            $table->string('mail_subject');
            $table->text('mail_body');
        });
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('website_title')->nullable();
            $table->string('to_mail')->nullable();
            $table->string('email_address')->nullable();
        });
        DB::table('basic_settings')->insert([
            'website_title' => 'TICA',
            'to_mail' => 'admin@tica.test',
            'email_address' => 'hello@tica.test',
        ]);

        (require database_path('migrations/2026_11_03_150000_create_application_mail_deliveries_table.php'))->up();
        (require database_path('migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php'))->up();
        (require database_path('migrations/2026_11_10_121000_add_dedupe_key_to_application_mail_deliveries.php'))->up();
        Queue::fake();
    }

    public function test_migration_adds_every_supported_template_without_overwriting_existing_copy(): void
    {
        DB::table('mail_templates')->insert([
            'mail_type' => 'payment_failed',
            'mail_subject' => 'My custom subject',
            'mail_body' => '<p>My custom body</p>',
        ]);

        (require database_path('migrations/2026_11_18_120000_add_transactional_mail_templates.php'))->up();

        $this->assertSame(count(MailTemplateCatalog::definitions()), DB::table('mail_templates')->count());
        $this->assertSame(
            array_keys(MailTemplateCatalog::definitions()),
            DB::table('mail_templates')->orderBy('id')->pluck('mail_type')->all()
        );
        $this->assertSame('My custom subject', DB::table('mail_templates')
            ->where('mail_type', 'payment_failed')->value('mail_subject'));
    }

    public function test_every_template_replaces_its_placeholders_and_can_be_queued_once(): void
    {
        (require database_path('migrations/2026_11_18_120000_add_transactional_mail_templates.php'))->up();
        $service = app(MailTemplateService::class);

        foreach (MailTemplateCatalog::definitions() as $type => $definition) {
            $data = array_fill_keys($definition['placeholders'], 'Example');
            $rendered = $service->render($type, $data);
            $this->assertDoesNotMatchRegularExpression('/\{[a-z0-9_]+\}/i', $rendered['subject'].$rendered['html'], $type);

            $first = $service->queueSafely($type, 'recipient@tica.test', $data, null, 'test:'.$type);
            $second = $service->queueSafely($type, 'recipient@tica.test', $data, null, 'test:'.$type);
            $this->assertNotNull($first, $type);
            $this->assertSame($first?->id, $second?->id, $type.' must be idempotent');
        }

        $this->assertDatabaseCount('application_mail_deliveries', count(MailTemplateCatalog::definitions()));
        Queue::assertPushed(SendApplicationMailDelivery::class, count(MailTemplateCatalog::definitions()));
    }

    public function test_admin_page_lists_templates_shows_relevant_placeholders_and_saves_edits(): void
    {
        (require database_path('migrations/2026_11_18_120000_add_transactional_mail_templates.php'))->up();
        $controller = app(MailTemplateController::class);
        $template = MailTemplate::query()->where('mail_type', 'payout_successful')->firstOrFail();

        $index = $controller->index();
        $this->assertCount(count(MailTemplateCatalog::definitions()), $index->getData()['templates']);

        $edit = $controller->edit($template->id);
        $this->assertSame(
            MailTemplateCatalog::placeholders('payout_successful'),
            $edit->getData()['availablePlaceholders']
        );

        $request = Request::create('/admin/mail-template/'.$template->id, 'POST', [
            'mail_subject' => 'Custom payout {reference}',
            'mail_body' => '<p>Hello {organizer_name}</p>',
        ]);
        $request->setLaravelSession(app('session')->driver());
        $controller->update($request, $template->id);

        $rendered = app(MailTemplateService::class)->render('payout_successful', [
            'reference' => 'PAY-100', 'organizer_name' => 'Ada',
        ]);
        $this->assertSame('Custom payout PAY-100', $rendered['subject']);
        $this->assertStringContainsString('Hello Ada', $rendered['html']);
    }

    public function test_bank_accounts_are_masked_before_being_put_into_a_template(): void
    {
        $this->assertSame('******4521', MailTemplateService::maskAccount('0123454521'));
        $this->assertSame('Not provided', MailTemplateService::maskAccount(null));
    }

    public function test_every_supported_template_is_wired_to_a_real_trigger_and_the_reminders_are_scheduled(): void
    {
        $directTriggers = [
            'payment_failed' => 'app/Models/PaymentIntent.php',
            'payment_pending_reminder' => 'app/Console/Commands/DispatchLifecycleNotifications.php',
            'refund_initiated' => 'app/Services/PaymentRefundService.php',
            'withdraw_request_received' => 'app/Services/WithdrawalService.php',
            'payout_failed' => 'app/Mail/PayoutStatusMail.php',
            'welcome_customer' => 'app/Services/CustomerEmailVerificationService.php',
            'organizer_account_rejected' => 'app/Services/OrganizerKycService.php',
            'password_changed' => 'app/Models/Admin.php',
            'account_suspended' => 'app/Models/Organizer.php',
            'admin_new_organizer' => 'app/Models/Organizer.php',
            'admin_new_withdrawal_request' => 'app/Services/WithdrawalService.php',
            'admin_payment_webhook_failure' => 'app/Http/Controllers/FrontEnd/PaymentGateway/PaystackController.php',
            'support_ticket_created' => 'app/Services/SupportTicketMailService.php',
            'support_ticket_reply' => 'app/Services/SupportTicketMailService.php',
            'support_ticket_closed' => 'app/Services/SupportTicketMailService.php',
            'admin_contact_form' => 'app/Http/Controllers/FrontEnd/ContactController.php',
            'organizer_upgrade_received' => 'app/Services/OrganizerUpgradeService.php',
            'admin_organizer_upgrade_request' => 'app/Services/OrganizerUpgradeService.php',
            'organizer_upgrade_approved' => 'app/Services/OrganizerUpgradeService.php',
            'organizer_upgrade_rejected' => 'app/Services/OrganizerUpgradeService.php',
        ];

        foreach ($directTriggers as $type => $file) {
            $this->assertStringContainsString("'{$type}'", file_get_contents(base_path($file)), "{$type} is not wired in {$file}");
        }

        $lifecycleTriggers = [
            LifecycleMailCatalog::BUYER_EVENT_REMINDER => 'event_reminder',
            LifecycleMailCatalog::BUYER_EVENT_DATE_CHANGED => 'event_updated',
            LifecycleMailCatalog::BUYER_EVENT_CANCELLED => 'event_cancelled',
            LifecycleMailCatalog::BUYER_REFUND_ISSUED => 'refund_completed',
            LifecycleMailCatalog::ORGANIZER_WELCOME => 'welcome_organizer',
            LifecycleMailCatalog::ORGANIZER_NEW_SALE => 'new_ticket_sale',
            LifecycleMailCatalog::ORGANIZER_PAYOUT_SENT => 'payout_successful',
            LifecycleMailCatalog::ORGANIZER_PAYOUT_ACCOUNT_CHANGED => 'bank_account_changed',
            LifecycleMailCatalog::ORGANIZER_VERIFICATION_APPROVED => 'organizer_account_approved',
        ];
        foreach ($lifecycleTriggers as $kind => $type) {
            $this->assertSame($type, MailTemplateCatalog::lifecycleTemplate($kind));
        }
        $this->assertSame(
            'event_sold_out',
            MailTemplateCatalog::lifecycleTemplate(LifecycleMailCatalog::ORGANIZER_INVENTORY_ALERT, ['sold_out' => true])
        );

        $schedule = file_get_contents(app_path('Console/Kernel.php'));
        $this->assertStringContainsString("notifications:dispatch-lifecycle --mode=hourly", $schedule);
        $this->assertStringContainsString('->hourly()', $schedule);

        $triggeredTypes = array_merge(array_keys($directTriggers), array_values($lifecycleTriggers), ['event_sold_out']);
        $this->assertEqualsCanonicalizing(array_keys(MailTemplateCatalog::definitions()), $triggeredTypes);
    }
}
