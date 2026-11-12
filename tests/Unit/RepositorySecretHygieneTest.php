<?php

namespace Tests\Unit;

use Tests\TestCase;

class RepositorySecretHygieneTest extends TestCase
{
    public function test_local_environment_and_composer_credentials_are_ignored(): void
    {
        $ignoreRules = file_get_contents(base_path('.gitignore'));

        $this->assertIsString($ignoreRules);
        $this->assertMatchesRegularExpression('/^\.env$/m', $ignoreRules);
        $this->assertMatchesRegularExpression('/^\.env\.\*$/m', $ignoreRules);
        $this->assertMatchesRegularExpression('/^!\.env\.example$/m', $ignoreRules);
        $this->assertMatchesRegularExpression('/^!\.env\.\*\.example$/m', $ignoreRules);
        $this->assertMatchesRegularExpression('/^auth\.json$/m', $ignoreRules);
    }

    public function test_the_distributable_environment_template_contains_no_assigned_secrets(): void
    {
        $template = file_get_contents(base_path('.env.example'));

        $this->assertIsString($template);

        foreach ([
            'APP_KEY',
            'TICKET_SIGNING_KEY',
            'TICKET_BUYER_IDENTITY_HASH_KEY',
            'TERMII_API_KEY',
            'OPS_INCIDENT_WEBHOOK_SECRET',
            'VAPID_PUBLIC_KEY',
            'VAPID_PRIVATE_KEY',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'PUSHER_APP_SECRET',
        ] as $secretName) {
            $this->assertMatchesRegularExpression(
                '/^'.preg_quote($secretName, '/').'=$/m',
                $template,
                "{$secretName} must stay blank in .env.example."
            );
        }
    }
}
