<?php

namespace Tests\Unit;

use Tests\TestCase;

class VercelFrontendBuildConfigTest extends TestCase
{
    public function test_vercel_build_creates_the_vite_manifest_before_copying_public_assets(): void
    {
        $config = json_decode(file_get_contents(base_path('vercel.json')), true, flags: JSON_THROW_ON_ERROR);
        $ignoreRules = file_get_contents(base_path('.vercelignore'));

        $this->assertSame('npm ci', $config['installCommand']);
        $this->assertStringContainsString('npm run build', $config['buildCommand']);
        $this->assertStringContainsString('node build-vercel-static.mjs', $config['buildCommand']);
        $this->assertStringContainsString('!scripts/build-frontend.mjs', $ignoreRules);
        $this->assertDoesNotMatchRegularExpression('/^scripts$/m', $ignoreRules);
        $this->assertLessThan(
            strpos($config['buildCommand'], 'node build-vercel-static.mjs'),
            strpos($config['buildCommand'], 'npm run build'),
            'The Vite-compatible frontend manifest must be built before public assets are copied.'
        );
    }
}
