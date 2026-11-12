<?php

namespace Tests\Unit;

use App\Http\Controllers\BackEnd\BasicSettings\BasicController;
use App\Http\Requests\MailFromAdminRequest;
use App\Models\BasicSettings\Basic;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class BasicSettingSecretSecurityTest extends TestCase
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
            $table->id(); $table->unsignedInteger('uniqid')->unique();
            $table->unsignedTinyInteger('smtp_status')->default(0);
            $table->string('smtp_host')->nullable(); $table->unsignedInteger('smtp_port')->nullable();
            $table->string('encryption')->nullable(); $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable(); $table->string('from_mail')->nullable();
            $table->string('from_name')->nullable();
            $table->unsignedTinyInteger('google_recaptcha_status')->default(0);
            $table->string('google_recaptcha_site_key')->nullable();
            $table->text('google_recaptcha_secret_key')->nullable();
            $table->unsignedTinyInteger('facebook_login_status')->default(0);
            $table->string('facebook_app_id')->nullable(); $table->text('facebook_app_secret')->nullable();
            $table->unsignedTinyInteger('google_login_status')->default(0);
            $table->string('google_client_id')->nullable(); $table->text('google_client_secret')->nullable();
            $table->string('maintenance_img')->nullable();
            $table->unsignedTinyInteger('maintenance_status')->default(0);
            $table->text('maintenance_msg')->nullable();
            $table->string('bypass_token')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function test_model_encrypts_sensitive_setting_values_and_reads_legacy_plaintext(): void
    {
        $basic = Basic::create([
            'uniqid' => 12345,
            'smtp_password' => 'mail-secret',
            'google_recaptcha_secret_key' => 'captcha-secret',
            'facebook_app_secret' => 'facebook-secret',
            'google_client_secret' => 'google-secret',
        ]);

        $raw = DB::table('basic_settings')->where('id', $basic->id)->first();
        foreach (Basic::SECRET_ATTRIBUTES as $attribute) {
            $this->assertTrue(Basic::secretIsEncrypted($raw->{$attribute}));
            $this->assertStringNotContainsString('secret', $raw->{$attribute});
        }
        $this->assertSame('mail-secret', $basic->fresh()->smtp_password);

        DB::table('basic_settings')->where('id', $basic->id)->update(['smtp_password' => 'legacy-password']);
        $this->assertSame('legacy-password', $basic->fresh()->smtp_password);
    }

    public function test_secret_encryption_command_is_dry_run_first_and_idempotent(): void
    {
        DB::table('basic_settings')->insert([
            'uniqid' => 12345, 'smtp_password' => 'smtp-plain',
            'google_recaptcha_secret_key' => 'captcha-plain',
            'facebook_app_secret' => null, 'google_client_secret' => '',
        ]);

        $this->artisan('settings:encrypt-secrets', ['--json' => true])
            ->expectsOutputToContain('"pending_values": 2')->assertSuccessful();
        $this->assertSame('smtp-plain', DB::table('basic_settings')->value('smtp_password'));

        $this->artisan('settings:encrypt-secrets', ['--apply' => true, '--json' => true])
            ->expectsOutputToContain('"encrypted_values": 2')->assertSuccessful();
        $this->assertTrue(Basic::secretIsEncrypted(DB::table('basic_settings')->value('smtp_password')));
        $this->assertSame('captcha-plain', Basic::firstOrFail()->google_recaptcha_secret_key);

        $this->artisan('settings:encrypt-secrets', ['--apply' => true, '--json' => true])
            ->expectsOutputToContain('"encrypted_values": 0')->assertSuccessful();
    }

    public function test_secret_forms_never_render_saved_values(): void
    {
        $views = implode("\n", [
            (string) file_get_contents(resource_path('views/backend/basic-settings/plugins.blade.php')),
            (string) file_get_contents(resource_path('views/backend/basic-settings/email/mail-from-admin.blade.php')),
        ]);

        foreach (Basic::SECRET_ATTRIBUTES as $name) {
            $this->assertMatchesRegularExpression(
                '/<input[^>]+type="password"[^>]+name="'.preg_quote($name, '/').'"[^>]+value=""/s',
                $views,
                "{$name} must be a blank write-only field."
            );
        }
        $this->assertStringNotContainsString('value="{{ $data->smtp_password }}"', $views);
        $this->assertStringNotContainsString('value="{{ $data->google_recaptcha_secret_key }}"', $views);
    }

    public function test_blank_plugin_and_mail_secrets_preserve_existing_values(): void
    {
        Basic::create([
            'uniqid' => 12345,
            'smtp_password' => 'mail-existing',
            'google_recaptcha_secret_key' => 'captcha-existing',
            'facebook_app_secret' => 'facebook-existing',
            'google_client_secret' => 'google-existing',
        ]);
        $session = app('session')->driver();
        $session->start();
        $controller = new BasicController();

        $recaptcha = Request::create('/admin/basic-settings/recaptcha', 'POST', [
            'google_recaptcha_status' => 1, 'google_recaptcha_site_key' => 'site-rotated',
            'google_recaptcha_secret_key' => '',
        ]);
        $recaptcha->setLaravelSession($session);
        $controller->updateRecaptcha($recaptcha);
        $facebook = Request::create('/admin/basic-settings/facebook', 'POST', [
            'facebook_login_status' => 1, 'facebook_app_id' => 'facebook-id-rotated',
            'facebook_app_secret' => '',
        ]);
        $facebook->setLaravelSession($session);
        $controller->updateFacebook($facebook);
        $google = Request::create('/admin/basic-settings/google', 'POST', [
            'google_login_status' => 1, 'google_client_id' => 'google-id-rotated',
            'google_client_secret' => '',
        ]);
        $google->setLaravelSession($session);
        $controller->updateGoogle($google);

        $mail = MailFromAdminRequest::create('/admin/basic-settings/mail', 'POST', [
            'smtp_status' => 1, 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587,
            'encryption' => 'tls', 'smtp_username' => 'mailer', 'smtp_password' => '',
            'from_mail' => 'hello@example.test', 'from_name' => 'Tica',
        ]);
        $prepare = new ReflectionMethod($mail, 'prepareForValidation');
        $prepare->invoke($mail);
        $mail->setLaravelSession($session);
        $this->assertSame('mail-existing', $mail->input('smtp_password'));
        $controller->updateMailFromAdmin($mail);

        $basic = Basic::firstOrFail();
        $this->assertSame('captcha-existing', $basic->google_recaptcha_secret_key);
        $this->assertSame('facebook-existing', $basic->facebook_app_secret);
        $this->assertSame('google-existing', $basic->google_client_secret);
        $this->assertSame('mail-existing', $basic->smtp_password);
        $this->assertSame('site-rotated', $basic->google_recaptcha_site_key);
        $this->assertSame('facebook-id-rotated', $basic->facebook_app_id);
        $this->assertSame('google-id-rotated', $basic->google_client_id);
    }

    public function test_maintenance_mode_has_no_public_bypass_and_uses_structured_command_arguments(): void
    {
        DB::table('basic_settings')->insert([
            'uniqid' => 12345,
            'maintenance_img' => 'maintenance.png',
            'maintenance_status' => 0,
            'maintenance_msg' => 'Previous notice',
            'bypass_token' => 'legacy-public-secret',
        ]);
        $session = app('session')->driver();
        $session->start();
        $request = Request::create('/admin/basic-settings/update-maintenance-mode', 'POST', [
            'maintenance_status' => 1,
            'maintenance_msg' => 'Planned maintenance',
        ]);
        $request->setLaravelSession($session);

        Artisan::shouldReceive('call')
            ->once()
            ->with('down', ['--redirect' => '/service-unavailable'])
            ->andReturn(0);

        (new BasicController())->updateMaintenance($request);

        $stored = DB::table('basic_settings')->first();
        $this->assertSame(1, (int) $stored->maintenance_status);
        $this->assertSame('Planned maintenance', $stored->maintenance_msg);
        $this->assertNull($stored->bypass_token);

        $view = (string) file_get_contents(resource_path('views/backend/basic-settings/maintenance.blade.php'));
        $this->assertStringNotContainsString('name="bypass_token"', $view);
        $this->assertStringNotContainsString('token-value', $view);
    }
}
