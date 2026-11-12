<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class CiSupplyChainPolicyTest extends TestCase
{
    #[Test]
    public function third_party_actions_are_immutable_and_dependency_audits_fail_closed(): void
    {
        $workflowPath = dirname(__DIR__, 2).'/.github/workflows/ci.yml';
        $workflow = file_get_contents($workflowPath);

        $this->assertIsString($workflow);
        $this->assertNotEmpty(Yaml::parse($workflow));
        $this->assertStringContainsString(
            'composer audit --locked --abandoned=fail --no-interaction',
            $workflow
        );
        $this->assertStringContainsString('npm audit --audit-level=high', $workflow);

        preg_match_all('/^\s*uses:\s*([^\s#]+)/m', $workflow, $matches);

        $this->assertNotEmpty($matches[1], 'The workflow must retain explicit action dependencies.');

        foreach ($matches[1] as $action) {
            if (str_starts_with($action, './')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^[^@\s]+@[a-f0-9]{40}$/',
                $action,
                "Third-party action [{$action}] must be pinned to a full commit SHA."
            );
        }

        preg_match_all('/^\s*image:\s*([^\s#]+)/m', $workflow, $imageMatches);

        $this->assertNotEmpty($imageMatches[1], 'CI service images must remain explicit.');

        foreach ($imageMatches[1] as $image) {
            $this->assertMatchesRegularExpression(
                '/^[^:@\s]+:[^@\s]+@sha256:[a-f0-9]{64}$/',
                $image,
                "Service image [{$image}] must use an explicit version and immutable digest."
            );
        }
    }

    #[Test]
    public function dependabot_covers_every_dependency_ecosystem_used_by_ci(): void
    {
        $configurationPath = dirname(__DIR__, 2).'/.github/dependabot.yml';
        $configuration = Yaml::parseFile($configurationPath);

        $this->assertSame(2, $configuration['version'] ?? null);

        $ecosystems = array_column($configuration['updates'] ?? [], 'package-ecosystem');

        $this->assertEqualsCanonicalizing(
            ['github-actions', 'composer', 'npm'],
            $ecosystems
        );
    }
}
