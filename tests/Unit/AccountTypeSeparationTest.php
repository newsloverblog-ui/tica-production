<?php

namespace Tests\Unit;

use App\Http\Controllers\FrontEnd\CustomerController;
use App\Http\Controllers\FrontEnd\OrganizerUpgradeController;
use App\Http\Controllers\FrontEnd\SocialSignInController;
use App\Http\Controllers\FrontEnd\TicaApiController;
use App\Models\Customer;
use App\Models\Organizer;
use App\Models\OrganizerUpgradeRequest;
use App\Rules\AccountTypeEmail;
use App\Services\AccountMfaService;
use App\Services\AccountTypeGuard;
use App\Services\OrganizerUpgradeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * One email = one account type, and the customer-to-organizer upgrade.
 */
class AccountTypeSeparationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        config()->set('session.driver', 'array');
        config()->set('services.google.client_id', 'id');
        config()->set('services.google.client_secret', 'secret');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Queue::fake();
        $this->schema();
        (require database_path('migrations/2026_11_19_180000_add_account_type_controls.php'))->up();
        $this->withSession([]);
    }

    // ---- Google ---------------------------------------------------------

    public function test_organizer_google_sign_in_with_a_customer_email_is_blocked_and_creates_nothing(): void
    {
        $this->customer('ada@example.com');
        $this->fakeGoogle('Ada@Example.com');
        $request = $this->request('/auth/google/callback', [], ['social_as' => 'org']);

        $response = app(SocialSignInController::class)->callback($request);

        $this->assertSame(0, Organizer::query()->count());
        $this->assertStringContainsString('/login?role=att', $response->getTargetUrl());
        $this->assertStringContainsString('registered as a customer account', (string) session('alert'));
        $this->assertFalse(Auth::guard('organizer')->check());
    }

    public function test_customer_google_sign_in_with_an_organizer_email_is_blocked_and_creates_nothing(): void
    {
        $this->organizer('tobi@example.com');
        $this->fakeGoogle('TOBI@example.com');
        $this->request('/login/google/callback');

        $response = (new CustomerController())->authenticationViaProvider('google');

        $this->assertSame(0, Customer::query()->count());
        $this->assertStringContainsString('/login?role=org', $response->getTargetUrl());
        $this->assertStringContainsString('belongs to an organizer account', (string) session('alert'));
        $this->assertFalse(Auth::guard('customer')->check());
    }

    // ---- Email sign-up and change-email -----------------------------------

    public function test_email_sign_up_is_blocked_both_ways_whatever_the_case(): void
    {
        $this->customer('ada@example.com');
        $this->organizer('tobi@example.com');

        $asOrganizer = $this->signupErrors(['role' => 'org', 'organisation' => 'Ada Events', 'email' => 'ADA@example.com ']);
        $this->assertStringContainsString('registered as a customer account', $asOrganizer['email'][0] ?? '');

        $asCustomer = $this->signupErrors(['role' => 'att', 'email' => 'Tobi@Example.COM']);
        $this->assertStringContainsString('belongs to an organizer account', $asCustomer['email'][0] ?? '');

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(1, Organizer::query()->count());
    }

    public function test_upgrade_hint_is_added_only_when_upgrades_are_on(): void
    {
        $this->customer('ada@example.com');
        $this->assertStringNotContainsString('upgrade', app(AccountTypeGuard::class)->message('organizer', 'ada@example.com'));

        $this->settings(upgrade: 1);
        $this->assertStringContainsString('upgrade to an organizer account', app(AccountTypeGuard::class)->message('organizer', 'ada@example.com'));
    }

    public function test_change_email_rules_refuse_the_other_account_type(): void
    {
        $this->customer('ada@example.com');
        $this->organizer('tobi@example.com');

        $this->assertTrue(Validator::make(['email' => 'Tobi@example.com'], ['email' => [AccountTypeEmail::customer()]])->fails());
        $this->assertTrue(Validator::make(['email' => ' ADA@EXAMPLE.COM'], ['email' => [AccountTypeEmail::organizer()]])->fails());
        $this->assertFalse(Validator::make(['email' => 'new@example.com'], ['email' => [AccountTypeEmail::customer()]])->fails());

        // Every form that sets an email uses the rule.
        foreach ([
            'app/Http/Controllers/FrontEnd/CustomerController.php' => 2,
            'app/Http/Controllers/BackEnd/Organizer/OrganizerController.php' => 2,
            'app/Http/Controllers/FrontEnd/TicaWorkspaceController.php' => 1,
            'app/Http/Controllers/BackEnd/CustomerManagementController.php' => 2,
            'app/Http/Controllers/BackEnd/Organizer/OrganizerManagementController.php' => 2,
            'app/Http/Controllers/FrontEnd/TicaApiController.php' => 1,
        ] as $file => $uses) {
            $this->assertGreaterThanOrEqual($uses, substr_count(file_get_contents(base_path($file)), 'AccountTypeEmail'), $file);
        }
    }

    // ---- Upgrade setting ---------------------------------------------------

    public function test_upgrade_button_and_routes_follow_the_setting(): void
    {
        $customer = $this->customer('ada@example.com');
        Auth::guard('customer')->login($customer);
        $upgrades = app(OrganizerUpgradeService::class);

        $this->assertNull($upgrades->headerState($customer));
        try {
            app(OrganizerUpgradeController::class)->show($this->request('/customer/become-organizer'));
            $this->fail('The upgrade page must not exist while upgrades are off.');
        } catch (NotFoundHttpException) {
            $this->addToAssertionCount(1);
        }

        $this->settings(upgrade: 1);
        $this->assertSame('available', $upgrades->headerState($customer)['state']);
        $view = app(OrganizerUpgradeController::class)->show($this->request('/customer/become-organizer'));
        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
        $this->assertSame('auth.become-organizer', $view->name());

        $account = file_get_contents(resource_path('views/tica-ui/pages/account.blade.php'));
        $this->assertStringContainsString('id="acd-upgrade"', $account);
        $this->assertStringContainsString('ACC.upgrade', file_get_contents(public_path('assets/tica-ui/js/tica.js')));
    }

    // ---- Upgrade flow ---------------------------------------------------------

    public function test_upgrade_with_approval_waits_then_moves_the_account_and_keeps_history(): void
    {
        $this->settings(upgrade: 1, approval: 1);
        $customer = $this->customer('ada@example.com', 'Secret-pass-123');
        DB::table('bookings')->insert(['customer_id' => $customer->id, 'event_id' => 7, 'organizer_id' => 99, 'quantity' => 2, 'paymentStatus' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
        $upgrades = app(OrganizerUpgradeService::class);

        $result = $upgrades->submit($customer, ['organisation' => 'Ada Events', 'contact_name' => 'Ada Obi', 'phone' => '+2348012345678']);
        $this->assertSame('pending', $result['status']);
        $this->assertSame('pending', $upgrades->headerState($customer->fresh())['state']);
        $this->assertSame(0, Organizer::query()->count());
        $this->assertTrue(DB::table('application_mail_deliveries')->where('kind', 'template.organizer_upgrade_received')->exists());
        $this->assertTrue(DB::table('application_mail_deliveries')->where('kind', 'template.admin_organizer_upgrade_request')->exists());

        $organizer = $upgrades->approve($result['request'], 1);

        $customer->refresh();
        $this->assertSame('ada@example.com', $organizer->email);
        $this->assertTrue(Hash::check('Secret-pass-123', $organizer->password), 'The person keeps their password.');
        $this->assertSame($organizer->id, (int) $customer->migrated_to_organizer_id);
        $this->assertSame(0, (int) $customer->status);
        $this->assertSame((int) $customer->id, (int) $organizer->migrated_from_customer_id);
        $this->assertSame('Ada Events', DB::table('organizer_infos')->where('organizer_id', $organizer->id)->value('name'));
        $this->assertSame(1, DB::table('bookings')->where('customer_id', $customer->id)->count(), 'Orders stay linked, nothing duplicated.');
        $this->assertSame('approved', OrganizerUpgradeRequest::query()->value('status'));
        $this->assertTrue(DB::table('application_mail_deliveries')->where('kind', 'template.organizer_upgrade_approved')->exists());
        $this->assertFalse(DB::table('application_mail_deliveries')->where('kind', 'template.account_suspended')->exists(), 'An upgrade is not a suspension.');
        $this->assertTrue(DB::table('audit_logs')->where('action', 'organizer_upgrade.migrated')->exists());

        // From now on the email is an organiser email.
        $this->assertNull(app(AccountTypeGuard::class)->customer('ada@example.com'));
        $this->assertNull(app(AccountTypeGuard::class)->message('organizer', 'ada@example.com'));
        $this->assertNotNull(app(AccountTypeGuard::class)->message('customer', 'ada@example.com'));
    }

    public function test_upgrade_without_approval_is_instant(): void
    {
        $this->settings(upgrade: 1, approval: 0);
        $customer = $this->customer('ada@example.com');

        $result = app(OrganizerUpgradeService::class)->submit($customer, ['organisation' => 'Ada Events', 'contact_name' => 'Ada Obi', 'phone' => '+2348012345678']);

        $this->assertSame('upgraded', $result['status']);
        $this->assertSame('ada@example.com', $result['organizer']->email);
        $this->assertSame($result['organizer']->id, (int) $customer->fresh()->migrated_to_organizer_id);
    }

    public function test_rejected_upgrade_leaves_the_customer_alone_and_emails_the_reason(): void
    {
        $this->settings(upgrade: 1, approval: 1);
        $customer = $this->customer('ada@example.com');
        $upgrades = app(OrganizerUpgradeService::class);
        $request = $upgrades->submit($customer, ['organisation' => 'Ada Events', 'contact_name' => 'Ada Obi', 'phone' => '+2348012345678'])['request'];

        $upgrades->reject($request, 'Please add your company registration first.', 1);

        $this->assertSame(0, Organizer::query()->count());
        $this->assertSame(1, (int) $customer->fresh()->status);
        $this->assertNull($customer->fresh()->migrated_to_organizer_id);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('Please add your company registration first.', $request->fresh()->reason);
        $this->assertTrue(DB::table('application_mail_deliveries')->where('kind', 'template.organizer_upgrade_rejected')->exists());
        $this->assertSame('available', $upgrades->headerState($customer->fresh())['state'], 'They may ask again.');
    }

    public function test_old_customer_log_in_after_the_upgrade_says_where_to_go(): void
    {
        $this->settings(upgrade: 1, approval: 0);
        $customer = $this->customer('ada@example.com', 'Secret-pass-123');
        app(OrganizerUpgradeService::class)->submit($customer, ['organisation' => 'Ada Events', 'contact_name' => 'Ada Obi', 'phone' => '+2348012345678']);

        $request = $this->request('/tica-api/login', ['role' => 'att', 'email' => 'Ada@example.com', 'password' => 'Secret-pass-123']);
        $response = app(TicaApiController::class)->login($request, app(AccountMfaService::class));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('upgraded to an organizer account', $response->getData(true)['message']);
        $this->assertSame('org', $response->getData(true)['switch_role']);
        $this->assertFalse(Auth::guard('customer')->check());
    }

    // ---- Duplicates ------------------------------------------------------------

    public function test_duplicate_accounts_command_lists_only_shared_emails(): void
    {
        $this->customer('both@example.com');
        DB::table('organizers')->insert(['email' => 'BOTH@example.com', 'username' => 'both_org', 'password' => 'x', 'status' => 1, 'amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->customer('only-customer@example.com');
        $this->organizer('only-organizer@example.com');

        Artisan::call('accounts:duplicates', ['--json' => true]);
        $rows = json_decode(Artisan::output(), true);

        $this->assertSame(['both@example.com'], array_column($rows, 'email'));
        $this->assertTrue($rows[0]['organizer']['can_retire']);
    }

    // ---- helpers ---------------------------------------------------------------

    private function signupErrors(array $input): array
    {
        $request = $this->request('/tica-api/signup', $input + ['name' => 'Test Person', 'phone' => '08012345678', 'password' => 'Strong-pass-123']);
        try {
            app(TicaApiController::class)->signup($request);
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Sign-up should have been refused.');
    }

    private function request(string $uri, array $input = [], array $session = []): Request
    {
        $request = Request::create($uri, 'POST', $input);
        $store = app('session')->driver();
        foreach ($session as $key => $value) {
            $store->put($key, $value);
        }
        $request->setLaravelSession($store);
        $this->app->instance('request', $request);

        return $request;
    }

    private function fakeGoogle(string $email): void
    {
        $user = (new SocialiteUser())->map(['id' => 'g-' . md5($email), 'name' => 'Google Person', 'email' => $email]);
        $user->setRaw(['email_verified' => true]);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function customer(string $email, string $password = 'Pass-word-123'): Customer
    {
        $customer = new Customer();
        $customer->forceFill([
            'fname' => 'Ada', 'lname' => 'Obi', 'username' => 'u' . substr(md5($email), 0, 10), 'email' => $email,
            'password' => Hash::make($password), 'status' => 1, 'email_verified_at' => now(), 'phone' => '+2348012345678',
        ])->saveQuietly();

        return $customer;
    }

    private function organizer(string $email): Organizer
    {
        $organizer = new Organizer();
        $organizer->forceFill(['email' => $email, 'username' => 'o' . substr(md5($email), 0, 10), 'password' => Hash::make('x'), 'status' => 1, 'amount' => 0])->saveQuietly();

        return $organizer;
    }

    private function settings(int $upgrade = 0, int $approval = 1): void
    {
        DB::table('basic_settings')->update(['customer_organizer_upgrade' => $upgrade, 'customer_organizer_upgrade_approval' => $approval]);
    }

    private function schema(): void
    {
        Schema::create('customers', function (Blueprint $t): void {
            $t->id(); $t->string('provider')->nullable(); $t->string('provider_id')->nullable();
            $t->string('fname')->nullable(); $t->string('lname')->nullable(); $t->string('username')->nullable(); $t->string('email')->nullable();
            $t->string('photo')->nullable(); $t->string('phone')->nullable(); $t->string('address')->nullable(); $t->string('country')->nullable();
            $t->string('state')->nullable(); $t->string('city')->nullable(); $t->string('zip_code')->nullable(); $t->string('password');
            $t->string('gender')->nullable(); $t->integer('status')->default(1); $t->string('email_verified_at')->nullable(); $t->string('verification_token')->nullable();
            $t->timestamp('two_factor_enabled_at')->nullable(); $t->text('two_factor_secret')->nullable(); $t->text('two_factor_recovery_codes')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        Schema::create('organizers', function (Blueprint $t): void {
            $t->id(); $t->string('photo')->nullable(); $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->string('username')->nullable();
            $t->string('password'); $t->string('status')->default('1'); $t->decimal('amount', 12, 2)->default(0); $t->timestamp('email_verified_at')->nullable();
            $t->string('facebook')->nullable(); $t->string('twitter')->nullable(); $t->string('linkedin')->nullable();
            $t->timestamp('two_factor_enabled_at')->nullable(); $t->text('two_factor_secret')->nullable(); $t->text('two_factor_recovery_codes')->nullable();
            $t->text('totp_secret')->nullable(); $t->timestamp('totp_confirmed_at')->nullable();
            $t->rememberToken(); $t->timestamps();
        });
        Schema::create('organizer_infos', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('language_id')->nullable(); $t->unsignedBigInteger('organizer_id'); $t->string('name')->nullable();
            $t->string('country')->nullable(); $t->string('city')->nullable(); $t->string('state')->nullable(); $t->string('zip_code')->nullable();
            $t->text('address')->nullable(); $t->text('details')->nullable(); $t->string('designation')->nullable(); $t->timestamps();
        });
        Schema::create('basic_settings', function (Blueprint $t): void {
            $t->id(); $t->integer('uniqid')->default(12345); $t->boolean('google_recaptcha_status')->default(false); $t->integer('google_login_status')->default(1);
            $t->integer('organizer_email_verification')->default(0); $t->integer('organizer_admin_approval')->default(0);
            $t->string('website_title')->nullable(); $t->string('to_mail')->nullable(); $t->string('email_address')->nullable();
        });
        DB::table('basic_settings')->insert(['uniqid' => 12345, 'website_title' => 'TICA', 'to_mail' => 'admin@tica.test']);
        Schema::create('languages', function (Blueprint $t): void { $t->id(); $t->string('code')->default('en'); $t->integer('is_default')->default(1); });
        DB::table('languages')->insert(['code' => 'en', 'is_default' => 1]);
        Schema::create('admins', function (Blueprint $t): void { $t->id(); $t->string('username')->nullable(); });
        DB::table('admins')->insert(['username' => 'admin']);
        Schema::create('bookings', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('customer_id')->nullable(); $t->unsignedBigInteger('organizer_id')->nullable(); $t->unsignedBigInteger('event_id')->nullable();
            $t->integer('quantity')->default(1); $t->string('paymentStatus')->nullable(); $t->timestamps();
        });
        Schema::create('events', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('organizer_id')->nullable(); $t->timestamps(); });
        Schema::create('withdraws', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('organizer_id'); $t->timestamps(); });
        Schema::create('mail_templates', function (Blueprint $t): void { $t->id(); $t->string('mail_type')->unique(); $t->string('mail_subject'); $t->text('mail_body'); });
        Schema::create('audit_logs', function (Blueprint $t): void {
            $t->id(); $t->string('actor_type')->nullable(); $t->unsignedBigInteger('actor_id')->nullable(); $t->string('action');
            $t->string('subject_type')->nullable(); $t->unsignedBigInteger('subject_id')->nullable(); $t->text('before')->nullable(); $t->text('after')->nullable();
            $t->text('metadata')->nullable(); $t->string('request_id')->nullable(); $t->string('ip_address')->nullable(); $t->text('user_agent')->nullable(); $t->timestamp('created_at')->nullable();
        });
        (require database_path('migrations/2026_11_03_150000_create_application_mail_deliveries_table.php'))->up();
        (require database_path('migrations/2026_11_05_160000_add_application_mail_dispatch_metadata.php'))->up();
        (require database_path('migrations/2026_11_10_121000_add_dedupe_key_to_application_mail_deliveries.php'))->up();
    }
}
