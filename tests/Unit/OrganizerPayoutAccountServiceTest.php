<?php

namespace Tests\Unit;

use App\Models\Organizer;
use App\Models\OrganizerPayoutAccount;
use App\Services\OrganizerPayoutAccountService;
use App\Services\PaystackGatewayClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OrganizerPayoutAccountServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_verified_account_is_name_matched_encrypted_and_audited(): void
    {
        $organizer = $this->organizer('verified', 'Ada Augusta Lovelace');
        $client = $this->client('0123456789', 'LOVELACE ADA AUGUSTA');

        $account = (new OrganizerPayoutAccountService())->verify(
            $organizer->id, '058', '0123456789', $client
        );

        $this->assertSame('verified', $account->status);
        $this->assertTrue($account->is_default);
        $this->assertSame('6789', $account->account_last4);
        $this->assertSame('0123456789', $account->account_number);
        $this->assertTrue($account->hold_until->isFuture());
        $this->assertNotSame('0123456789', DB::table('organizer_payout_accounts')->value('account_number'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organizer.payout_account_verified',
            'subject_id' => $account->id,
        ]);
    }

    public function test_a_replacement_retires_the_previous_destination(): void
    {
        $organizer = $this->organizer('verified', 'Ada Lovelace');
        $service = new OrganizerPayoutAccountService();
        $first = $service->verify($organizer->id, '058', '0123456789', $this->client(
            '0123456789', 'ADA LOVELACE'
        ));
        $second = $service->verify($organizer->id, '058', '9876543210', $this->client(
            '9876543210', 'LOVELACE ADA'
        ));

        $this->assertSame('replaced', $first->fresh()->status);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame('verified', $second->status);
        $this->assertTrue($second->is_default);
        $this->assertSame(1, OrganizerPayoutAccount::where('organizer_id', $organizer->id)
            ->where('status', 'verified')->where('is_default', true)->count());
    }

    public function test_security_link_can_lock_the_payout_destination_idempotently(): void
    {
        $organizer = $this->organizer('verified', 'Ada Lovelace');
        $service = new OrganizerPayoutAccountService();
        $account = $service->verify($organizer->id, '058', '0123456789', $this->client(
            '0123456789', 'ADA LOVELACE'
        ));

        $locked = $service->lock($account->public_id);
        $lockedAgain = $service->lock($account->public_id);

        $this->assertSame('locked', $locked->status);
        $this->assertFalse($locked->is_default);
        $this->assertNotNull($locked->locked_at);
        $this->assertSame($locked->id, $lockedAgain->id);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'organizer.payout_account_locked')->count());
    }

    public function test_bank_name_mismatch_is_kept_for_review_and_never_used_for_payouts(): void
    {
        $organizer = $this->organizer('verified', 'Ada Lovelace');
        $service = new OrganizerPayoutAccountService();
        $current = $service->verify($organizer->id, '058', '1111111111', $this->client('1111111111', 'ADA LOVELACE'));

        $account = $service->verify($organizer->id, '058', '0123456789', $this->client('0123456789', 'GRACE HOPPER'));

        $this->assertSame('needs_review', $account->status);
        $this->assertSame('mismatch', $account->name_match_status);
        $this->assertFalse($account->is_default);
        $this->assertNull($account->provider_recipient_code, 'No transfer recipient for an unapproved account.');
        $this->assertSame('verified', $current->fresh()->status, 'The current account keeps receiving payouts.');
        $this->assertTrue($current->fresh()->is_default);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organizer.payout_account_name_mismatch', 'subject_id' => $organizer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organizer.payout_account_needs_review', 'subject_id' => $account->id]);
    }

    public function test_admin_approval_makes_a_reviewed_account_the_payout_account_with_a_fresh_hold(): void
    {
        $organizer = $this->organizer('verified', 'Ada Lovelace');
        $service = new OrganizerPayoutAccountService();
        $current = $service->verify($organizer->id, '058', '1111111111', $this->client('1111111111', 'ADA LOVELACE'));
        $pending = $service->verify($organizer->id, '058', '0123456789', $this->client('0123456789', 'LOVELACE EVENTS LTD'));
        $this->travel(2)->days();

        $approved = $service->review($pending->public_id, true, 7, null);

        $this->assertSame('verified', $approved->status);
        $this->assertTrue($approved->is_default);
        $this->assertSame(7, (int) $approved->reviewed_by_admin_id);
        $this->assertTrue($approved->hold_until->isFuture(), 'Approval starts a new 24-hour hold.');
        $this->assertSame('replaced', $current->fresh()->status);
    }

    public function test_admin_rejection_needs_a_reason_and_keeps_the_current_account(): void
    {
        $organizer = $this->organizer('verified', 'Ada Lovelace');
        $service = new OrganizerPayoutAccountService();
        $current = $service->verify($organizer->id, '058', '1111111111', $this->client('1111111111', 'ADA LOVELACE'));
        $pending = $service->verify($organizer->id, '058', '0123456789', $this->client('0123456789', 'GRACE HOPPER'));

        try {
            $service->review($pending->public_id, false, 7, '  ');
            $this->fail('A rejection without a reason should be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reason', $exception->getMessage());
        }
        $rejected = $service->review($pending->public_id, false, 7, 'Account belongs to someone else.');

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Account belongs to someone else.', $rejected->review_reason);
        $this->assertTrue($current->fresh()->is_default);
    }

    public function test_a_slow_bank_is_reported_as_slow_not_as_a_wrong_account(): void
    {
        $organizer = $this->organizer('verified', 'Ada Lovelace');
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldReceive('listBanks')->andReturn(['status' => true, 'data' => [['name' => 'GTBank', 'code' => '058']]]);
        $client->shouldReceive('resolveAccount')->andThrow(new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out'));

        $this->expectException(\App\Exceptions\PayoutProviderUnavailable::class);
        $this->expectExceptionMessage('taking too long');
        (new OrganizerPayoutAccountService())->verify($organizer->id, '058', '0123456789', $client);
    }

    public function test_verified_account_gets_its_transfer_recipient_straight_away(): void
    {
        $organizer = $this->organizer('verified', 'Ada Lovelace');

        $account = (new OrganizerPayoutAccountService())->verify($organizer->id, '058', '0123456789', $this->client('0123456789', 'ADA LOVELACE'));

        $this->assertSame('RCP_0123456789', $account->fresh()->provider_recipient_code);
    }

    public function test_unverified_organizer_cannot_add_a_payout_account(): void
    {
        $organizer = $this->organizer('pending', null);
        $client = Mockery::mock(PaystackGatewayClient::class);
        $client->shouldNotReceive('listBanks');
        $client->shouldNotReceive('resolveAccount');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Complete organizer verification');
        (new OrganizerPayoutAccountService())->verify(
            $organizer->id, '058', '0123456789', $client
        );
    }

    private function client(string $number, string $name): PaystackGatewayClient
    {
        $client = Mockery::mock(PaystackGatewayClient::class);
        // The bank list is cached, so a second verify in the same test may not ask again.
        $client->shouldReceive('listBanks')->atMost()->once()->with('nigeria', 'NGN')->andReturn([
            'status' => true,
            'data' => [['name' => 'Guarantee Trust Bank', 'code' => '058']],
        ]);
        $client->shouldReceive('resolveAccount')->once()->with($number, '058')->andReturn([
            'status' => true,
            'data' => ['account_number' => $number, 'account_name' => $name],
        ]);
        $client->shouldReceive('createTransferRecipient')->andReturn(['status' => true, 'data' => ['recipient_code' => 'RCP_' . $number]])->byDefault();
        return $client;
    }

    private function organizer(string $status, ?string $legalName): Organizer
    {
        return Organizer::create([
            'email' => uniqid('organizer-', true) . '@example.com',
            'username' => uniqid('organizer-', true),
            'password' => 'not-used',
            'status' => 1,
            'amount' => 50000,
            'kyc_status' => $status,
            'verified_legal_name' => $legalName,
            'kyc_verified_at' => $status === 'verified' ? now() : null,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->integer('status')->default(1);
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('kyc_status', 24)->default('unverified');
            $table->string('verified_legal_name')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('organizer_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('organizer_id');
            $table->string('provider', 30);
            $table->string('country_code', 2);
            $table->string('currency', 8);
            $table->string('bank_code', 30);
            $table->string('bank_name');
            $table->text('account_number');
            $table->char('account_number_hash', 64);
            $table->string('account_last4', 4);
            $table->string('resolved_account_name');
            $table->string('name_match_status', 24);
            $table->string('status', 24);
            $table->boolean('is_default')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->text('provider_recipient_code')->nullable();
            $table->timestamp('recipient_created_at')->nullable();
            $table->string('review_reason', 500)->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'bank_code', 'account_number_hash'], 'payout_account_destination_unique');
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
