<?php

namespace Tests\Unit;

use App\Services\TicaPromotionPaystackService;
use ReflectionMethod;
use Tests\TestCase;

class TicaPromotionPaystackEmailTest extends TestCase
{
    public function test_checkout_normalizes_the_organizer_email_before_sending_it_to_paystack(): void
    {
        $method = new ReflectionMethod(TicaPromotionPaystackService::class, 'paymentEmail');
        $method->setAccessible(true);

        $this->assertSame(
            'organizer@example.com',
            $method->invoke(app(TicaPromotionPaystackService::class), '  Organizer@Example.com  ')
        );
    }

    public function test_checkout_uses_a_valid_platform_email_for_legacy_accounts_with_bad_email_data(): void
    {
        config()->set('mail.from.address', 'payments@tica.test');
        $method = new ReflectionMethod(TicaPromotionPaystackService::class, 'paymentEmail');
        $method->setAccessible(true);

        $this->assertSame(
            'payments@tica.test',
            $method->invoke(app(TicaPromotionPaystackService::class), 'not-an-email')
        );
    }
}
