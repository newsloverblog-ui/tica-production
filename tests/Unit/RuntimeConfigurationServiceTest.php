<?php

namespace Tests\Unit;

use App\Models\BasicSettings\Basic;
use App\Services\RuntimeConfigurationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RuntimeConfigurationServiceTest extends TestCase
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
        config()->set('app.url', 'https://tica.test');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set('UTC');
        parent::tearDown();
    }

    public function test_database_settings_hydrate_request_scoped_sdk_configuration(): void
    {
        DB::table('basic_settings')->insert([
            'website_title' => 'Tica Events', 'timezone' => 'Africa/Lagos',
            'google_recaptcha_site_key' => 'captcha-public',
            'google_recaptcha_secret_key' => Basic::encryptSecret('captcha-secret'),
            'facebook_app_id' => 'facebook-id',
            'facebook_app_secret' => Basic::encryptSecret('facebook-secret'),
            'google_client_id' => 'google-id',
            'google_client_secret' => Basic::encryptSecret('google-secret'),
        ]);
        foreach ([
            'flutterwave' => ['public_key' => 'flw-public', 'secret_key' => 'flw-secret'],
            'mollie' => ['key' => 'mollie-key'],
            'stripe' => ['key' => 'stripe-public', 'secret' => 'stripe-secret'],
            'paytm' => [
                'environment' => 'local', 'merchant_mid' => 'merchant-id',
                'merchant_key' => 'merchant-key', 'merchant_website' => 'WEBSTAGING',
                'industry_type' => 'Retail',
            ],
            'xendit' => ['secret_key' => 'xendit-secret'],
            'myfatoorah' => ['token' => 'myfatoorah-token', 'sandbox_status' => 1],
        ] as $keyword => $information) {
            DB::table('online_gateways')->insert([
                'keyword' => $keyword, 'information' => json_encode($information), 'status' => 1,
            ]);
        }

        (new RuntimeConfigurationService())->hydrate();

        $this->assertSame('Tica Events', config('app.name'));
        $this->assertSame('Africa/Lagos', config('app.timezone'));
        $this->assertSame('Africa/Lagos', date_default_timezone_get());
        $this->assertSame('captcha-secret', config('captcha.secret'));
        $this->assertSame('facebook-id', config('services.facebook.client_id'));
        $this->assertSame('https://tica.test/login/google/callback', config('services.google.redirect'));
        $this->assertSame('flw-secret', config('flutterwave.secretKey'));
        $this->assertSame('mollie-key', config('mollie.key'));
        $this->assertSame('stripe-secret', config('services.stripe.secret'));
        $this->assertSame('merchant-id', config('services.paytm-wallet.merchant_id'));
        $this->assertSame(base64_encode('xendit-secret:'), config('xendit.key_auth'));
        $this->assertSame('myfatoorah-token', config('myfatoorah.token'));
        $this->assertSame('https://tica.test/myfatoorah/callback', config('myfatoorah.CallBackUrl'));
    }

    public function test_invalid_database_timezone_does_not_change_runtime_timezone(): void
    {
        date_default_timezone_set('UTC');
        config()->set('app.timezone', 'UTC');
        DB::table('basic_settings')->insert([
            'website_title' => 'Tica', 'timezone' => 'Not/A-Timezone',
        ]);

        (new RuntimeConfigurationService())->hydrate();

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
    }

    public function test_active_application_controllers_cannot_rewrite_environment_files(): void
    {
        $controllerFiles = glob(app_path('Http/Controllers/**/*.php')) ?: [];
        $controllerFiles = array_merge(
            $controllerFiles,
            glob(app_path('Http/Controllers/**/**/*.php')) ?: [],
            glob(app_path('Http/Controllers/**/**/**/*.php')) ?: []
        );
        $source = implode("\n", array_map(
            static fn (string $file): string => (string) file_get_contents($file),
            array_unique($controllerFiles)
        ));

        $this->assertStringNotContainsString('setEnvironmentValue(', $source);
        $this->assertStringNotContainsString("Artisan::call('config:clear'", $source);
    }

    private function createSchema(): void
    {
        Schema::create('basic_settings', function (Blueprint $table): void {
            $table->id(); $table->string('website_title')->nullable(); $table->string('timezone')->nullable();
            $table->text('google_recaptcha_site_key')->nullable(); $table->text('google_recaptcha_secret_key')->nullable();
            $table->text('facebook_app_id')->nullable(); $table->text('facebook_app_secret')->nullable();
            $table->text('google_client_id')->nullable(); $table->text('google_client_secret')->nullable();
        });
        Schema::create('online_gateways', function (Blueprint $table): void {
            $table->id(); $table->string('keyword')->unique(); $table->longText('information');
            $table->unsignedTinyInteger('status')->default(0);
        });
    }
}
