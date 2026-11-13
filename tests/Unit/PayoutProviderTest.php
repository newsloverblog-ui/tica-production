<?php

namespace Tests\Unit;

use App\Contracts\PayoutProvider;
use App\Services\Payouts\BankDirectory;
use App\Services\Payouts\MonnifyPayoutProvider;
use App\Services\Payouts\PayoutProviders;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class PayoutProviderTest extends TestCase
{
  public function test_bank_list_is_cached_and_falls_back_to_the_last_good_copy_when_the_provider_is_down(): void
  {
    Cache::flush();
    $provider = $this->provider([['code' => '058', 'name' => 'GTBank']]);
    $directory = new BankDirectory(app(PayoutProviders::class));

    $first = $directory->list(null, $provider);
    $this->assertFalse($first['stale']);
    $this->assertSame('058', $first['banks'][0]['code']);

    // Fresh copy expires; provider now fails.
    Cache::forget('payout-banks:fake');
    $provider->fail = true;
    $fallback = $directory->list(null, $provider);

    $this->assertTrue($fallback['stale']);
    $this->assertSame('GTBank', $fallback['banks'][0]['name']);
  }

  public function test_bank_list_failure_with_no_saved_copy_gives_a_plain_message(): void
  {
    Cache::flush();
    $provider = $this->provider([]);
    $provider->fail = true;

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('couldn\'t load the bank list');
    (new BankDirectory(app(PayoutProviders::class)))->list(null, $provider);
  }

  public function test_a_bank_not_on_the_list_is_refused(): void
  {
    Cache::flush();
    $this->expectExceptionMessage('Choose your bank from the list.');
    (new BankDirectory(app(PayoutProviders::class)))->find('999', null, $this->provider([['code' => '058', 'name' => 'GTBank']]));
  }

  public function test_monnify_statuses_map_onto_the_transfer_states_tica_uses(): void
  {
    $map = [
      'SUCCESS' => 'success', 'COMPLETED' => 'success', 'FAILED' => 'failed', 'EXPIRED' => 'failed',
      'REVERSED' => 'reversed', 'PENDING' => 'pending', 'PENDING_AUTHORIZATION' => 'pending', 'SOMETHING_NEW' => 'processing',
    ];
    foreach ($map as $monnify => $tica) {
      $this->assertSame($tica, MonnifyPayoutProvider::normalise(['status' => $monnify], 'ref')['status'], $monnify);
    }
    $data = MonnifyPayoutProvider::normalise(['status' => 'SUCCESS', 'amount' => 9800.5, 'currency' => 'NGN', 'transactionReference' => 'MFDS1'], 'ref-1');
    $this->assertSame(980050, $data['amount'], 'Amounts are converted to kobo like Paystack.');
    $this->assertSame('MFDS1', $data['transfer_code']);
    $this->assertSame('ref-1', $data['reference']);
  }

  public function test_monnify_transfer_refuses_an_account_saved_for_another_provider(): void
  {
    config()->set('services.monnify.disbursement_wallet', '1234567890');
    $provider = new MonnifyPayoutProvider(\Mockery::mock(\App\Services\MonnifyGatewayClient::class));

    $this->expectExceptionMessage('saved for another provider');
    $provider->initiateTransfer('RCP_paystack_code', 100, 'ref', 'reason', 'NGN');
  }

  public function test_provider_switch_is_stored_and_unknown_providers_are_refused(): void
  {
    $this->assertInstanceOf(MonnifyPayoutProvider::class, app(PayoutProviders::class)->for('monnify'));
    $this->expectException(\InvalidArgumentException::class);
    app(PayoutProviders::class)->for('flutterwave');
  }

  public function test_payout_keys_are_never_sent_to_the_browser(): void
  {
    $js = file_get_contents(public_path('assets/tica-ui/js/tica.js'));
    foreach (['sk_live', 'sk_test', 'secret_key', 'PAYSTACK_SECRET', 'api.paystack.co', 'monnify.com'] as $needle) {
      $this->assertStringNotContainsString($needle, $js, 'Payout calls must go through the backend.');
    }
  }

  private function provider(array $banks): PayoutProvider
  {
    return new class($banks) implements PayoutProvider {
      public bool $fail = false;
      public function __construct(private array $list) {}
      public function name(): string { return 'fake'; }
      public function banks(): array { if ($this->fail) throw new RuntimeException('timeout'); return $this->list; }
      public function resolveAccount(string $a, string $b): array { return []; }
      public function createRecipient(string $n, string $a, string $b, string $c): string { return ''; }
      public function initiateTransfer(string $r, float $amount, string $ref, string $reason, string $cur): array { return []; }
      public function verifyTransfer(string $ref): array { return []; }
      public function testConnection(): void {}
    };
  }
}
