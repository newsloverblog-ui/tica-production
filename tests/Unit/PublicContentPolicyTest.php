<?php

namespace Tests\Unit;

use App\Services\PublicContentPolicy;
use Tests\TestCase;

class PublicContentPolicyTest extends TestCase
{
    public function test_legacy_demo_copy_and_vendor_links_are_not_publishable(): void
    {
        $policy = new PublicContentPolicy;

        $this->assertTrue($policy->containsPlaceholder('Lorem Ipsum dolor sit amet'));
        $this->assertTrue($policy->containsPlaceholder('Contact demo@demo.com'));
        $this->assertTrue($policy->isVendorDemoUrl('https://codecanyon8.kreativdev.com/evento/contact'));
        $this->assertFalse($policy->isPublishableQuickLink((object) [
            'title' => 'Contact us',
            'url' => 'https://codecanyon8.kreativdev.com/evento/contact',
        ]));
    }

    public function test_real_content_and_owned_social_profiles_remain_publishable(): void
    {
        $policy = new PublicContentPolicy;

        $this->assertFalse($policy->containsPlaceholder('Find memorable events across Lagos.'));
        $this->assertTrue($policy->isPublishableQuickLink((object) [
            'title' => 'Privacy policy',
            'url' => 'https://tica.ng/privacy',
        ]));
        $this->assertTrue($policy->isPublishableSocialLink((object) ['url' => 'https://www.instagram.com/tica.ng/']));
        $this->assertFalse($policy->isPublishableSocialLink((object) ['url' => 'https://www.facebook.com/']));
    }

    public function test_demo_contact_values_are_suppressed_without_hiding_real_values(): void
    {
        $policy = new PublicContentPolicy;

        $this->assertFalse($policy->isPublishableContact('demo@demo.com'));
        $this->assertFalse($policy->isPublishableContact('Winooski, Virginia, United States'));
        $this->assertFalse($policy->isPublishableContact('(225)571-6212'));
        $this->assertTrue($policy->isPublishableContact('support@tica.ng'));
        $this->assertSame('Tica', $policy->publicBrandName('Evento'));
        $this->assertSame('Tica Africa', $policy->publicBrandName('Tica Africa'));
    }
}
