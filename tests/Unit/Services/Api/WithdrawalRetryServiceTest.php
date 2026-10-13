<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\FundingApply;
use App\Models\Api\UserProhibition;
use App\Repositories\Api\FundingApplyRepository;
use App\Services\Api\WithdrawalRetryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Funding\Enums\FundingFinanceStatus;
use Scaffold\Funding\Enums\FundingRiskStatus;
use Tests\TestCase;

class WithdrawalRetryServiceTest extends TestCase
{
    private array $originalApiConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalApiConnection = config('database.connections.'.DB_API);
        config([
            'database.connections.'.DB_API => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge(DB_API);

        Schema::connection(DB_API)->create('user', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_code')->primary();
            $table->string('email')->default('');
            $table->unsignedTinyInteger('type')->default(0);
        });

        Schema::connection(DB_API)->create('user_prohibition', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_code');
            $table->unsignedTinyInteger('type');
            $table->timestamps();
        });

        Schema::connection(DB_API)->create('funding_apply', function (Blueprint $table): void {
            $table->id();
            $table->string('order_no');
            $table->unsignedBigInteger('user_code');
            $table->unsignedBigInteger('trade_account_id')->nullable();
            $table->unsignedTinyInteger('direction');
            $table->unsignedTinyInteger('fund_type');
            $table->decimal('apply_amount', 20, 6);
            $table->decimal('fee_amount', 20, 6)->default(0);
            $table->decimal('net_amount', 20, 6)->default(0);
            $table->string('currency')->default('USDT');
            $table->decimal('rate', 20, 8)->default(1);
            $table->string('pay_currency')->nullable();
            $table->string('platform')->nullable();
            $table->unsignedTinyInteger('status');
            $table->string('order_id')->nullable();
            $table->text('withdraw_to')->nullable();
            $table->dateTime('effective_at')->nullable();
            $table->text('reference_content')->nullable();
            $table->unsignedTinyInteger('risk_status')->nullable();
            $table->unsignedTinyInteger('finance_status')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_initial_dispatch_persists_stable_request_id(): void
    {
        $this->seedProcessingWithdrawal(1001, 7, ['chain_id' => 'ethereum', 'wallet_address' => '0xabc']);
        $service = $this->service();

        $prepared = $service->prepareInitialDispatch(1001);

        $this->assertSame('1001@retry', $prepared['request_id']);
        $state = $this->walletDispatch(1001);
        $this->assertSame('1001@retry', $state['request_id']);

        // 二次准备复用同一幂等键，不生成新值。
        $this->assertSame('1001@retry', $service->prepareInitialDispatch(1001)['request_id']);
    }

    public function test_initial_failure_schedules_first_auto_retry_without_consuming_quota(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $service = $this->service();
        $service->prepareInitialDispatch(1001);

        $retry = $service->recordInitialRetryFailure(
            1001,
            FundingApply::WALLET_STATUS_NOT_SUBMITTED,
            'withdrawal was not submitted to PSP'
        );

        $this->assertSame(FundingApply::WALLET_RETRY_PHASE_RETRYING, $retry['retry_phase']);
        $this->assertSame(0, $retry['auto_retry_count']);
        $this->assertSame(FundingApply::WALLET_STATUS_NOT_SUBMITTED, $retry['last_error_code']);

        $state = $this->walletDispatch(1001);
        $this->assertSecondsNear(FundingApply::AUTO_RETRY_INTERVALS[0], $this->secondsUntil($state['next_retry_at']));
    }

    public function test_auto_attempts_consume_quota_with_backoff_and_exhaust(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $service = $this->service();
        $service->prepareInitialDispatch(1001);
        $service->recordInitialRetryFailure(1001, FundingApply::WALLET_STATUS_NOT_SUBMITTED, 'not submitted');

        $retry = $service->recordRetryAttemptFailure(1001, FundingApply::WALLET_RETRY_TRIGGER_AUTO, FundingApply::WALLET_STATUS_NOT_SUBMITTED, 'not submitted');
        $this->assertSame(1, $retry['auto_retry_count']);
        $this->assertSame(FundingApply::WALLET_RETRY_PHASE_RETRYING, $retry['retry_phase']);

        $retry = $service->recordRetryAttemptFailure(1001, FundingApply::WALLET_RETRY_TRIGGER_AUTO, FundingApply::WALLET_STATUS_NOT_SUBMITTED, 'not submitted');
        $this->assertSame(2, $retry['auto_retry_count']);
        $this->assertSame(FundingApply::WALLET_RETRY_PHASE_RETRYING, $retry['retry_phase']);

        $retry = $service->recordRetryAttemptFailure(1001, FundingApply::WALLET_RETRY_TRIGGER_AUTO, FundingApply::WALLET_STATUS_NOT_SUBMITTED, 'not submitted');
        $this->assertSame(3, $retry['auto_retry_count']);
        $this->assertSame(FundingApply::WALLET_RETRY_PHASE_EXHAUSTED, $retry['retry_phase']);
    }

    public function test_failure_state_is_stored_as_nested_array_in_reference_content(): void
    {
        $this->seedProcessingWithdrawal(1001, 7, [
            'chain_id' => 'ethereum',
            'wallet_address' => '0xabc',
        ]);
        $service = $this->service();
        $service->prepareInitialDispatch(1001);
        $service->recordInitialRetryFailure(1001, FundingApply::WALLET_STATUS_NOT_SUBMITTED, 'not submitted');
        $service->recordRetryAttemptFailure(
            1001,
            FundingApply::WALLET_RETRY_TRIGGER_AUTO,
            FundingApply::WALLET_STATUS_NOT_SUBMITTED,
            'not submitted again'
        );

        $record = DB::connection(DB_API)
            ->table('funding_apply')
            ->where('order_no', 1001)
            ->first(['reference_content']);
        $decoded = json_decode((string) $record->reference_content, true);

        // 原有兄弟键完整保留，取参逻辑（chain_id/wallet_address）不受影响。
        $this->assertSame('ethereum', $decoded['chain_id']);
        $this->assertSame('0xabc', $decoded['wallet_address']);

        // wallet_dispatch 为新增 key，内部是数组，包含幂等键与重试状态全集。
        $this->assertIsArray($decoded['wallet_dispatch']);
        $this->assertSame('1001@retry', $decoded['wallet_dispatch']['request_id']);
        $this->assertSame(FundingApply::WALLET_RETRY_PHASE_RETRYING, $decoded['wallet_dispatch']['retry_phase']);
        $this->assertSame(1, $decoded['wallet_dispatch']['auto_retry_count']);
        $this->assertSame(FundingApply::MAX_AUTO_RETRY_COUNT, $decoded['wallet_dispatch']['auto_max_attempts']);
        $this->assertSame(FundingApply::WALLET_STATUS_NOT_SUBMITTED, $decoded['wallet_dispatch']['last_error_code']);
        $this->assertSame('not submitted again', $decoded['wallet_dispatch']['last_error_message']);
        $this->assertNotSame('', (string) $decoded['wallet_dispatch']['next_retry_at']);
        $this->assertNotSame('', (string) $decoded['wallet_dispatch']['last_attempted_at']);
    }

    public function test_manual_attempts_do_not_consume_auto_quota(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $service = $this->service();
        $service->prepareInitialDispatch(1001);
        $service->recordInitialRetryFailure(1001, FundingApply::WALLET_STATUS_NOT_SUBMITTED, 'not submitted');

        $this->assertNotNull($service->prepareRetryAttempt(1001, FundingApply::WALLET_RETRY_TRIGGER_MANUAL));
        $retry = $service->recordRetryAttemptFailure(1001, FundingApply::WALLET_RETRY_TRIGGER_MANUAL, FundingApply::WALLET_STATUS_NOT_SUBMITTED, 'not submitted');

        $this->assertSame(0, $retry['auto_retry_count']);
        $this->assertSame(FundingApply::WALLET_RETRY_PHASE_RETRYING, $retry['retry_phase']);
        $this->assertArrayNotHasKey('retry_lease_until', $this->walletDispatch(1001));
    }

    public function test_due_candidates_filter_by_phase_and_next_retry_time(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $this->seedProcessingWithdrawal(1002, 8);
        $service = $this->service();

        $now = CarbonImmutable::now('UTC');

        // 1001：已到期。
        $this->setWalletDispatch(1001, [
            'request_id' => '1001@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => FundingApply::WALLET_STATUS_NOT_SUBMITTED,
            'next_retry_at' => $now->subSeconds(10)->format('Y-m-d H:i:s'),
        ]);

        // 1002：未到期。
        $this->setWalletDispatch(1002, [
            'request_id' => '1002@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => FundingApply::WALLET_STATUS_NOT_SUBMITTED,
            'next_retry_at' => $now->addSeconds(60)->format('Y-m-d H:i:s'),
        ]);

        $candidates = $service->getDueRetryCandidates($now, 100);

        $this->assertSame([['id' => 1, 'order_no' => '1001', 'user_code' => 7]], $candidates);
    }

    public function test_exhausted_orders_are_not_scanned_again(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $now = CarbonImmutable::now('UTC');
        $this->setWalletDispatch(1001, [
            'request_id' => '1001@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_EXHAUSTED,
            'auto_retry_count' => 3,
            'next_retry_at' => $now->subSeconds(10)->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame([], $this->service()->getDueRetryCandidates($now, 100));
    }

    public function test_due_candidates_only_include_not_submitted_errors(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $this->seedProcessingWithdrawal(1002, 8);
        $this->seedProcessingWithdrawal(1003, 9);
        $now = CarbonImmutable::now('UTC');

        $this->setWalletDispatch(1001, [
            'request_id' => '1001@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => 'WALLET_REQUEST_FAILED',
            'next_retry_at' => $now->subSeconds(10)->format('Y-m-d H:i:s'),
        ]);
        $this->setWalletDispatch(1002, [
            'request_id' => '1002@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => FundingApply::WALLET_STATUS_NOT_SUBMITTED,
            'next_retry_at' => $now->subSeconds(10)->format('Y-m-d H:i:s'),
        ]);
        $this->setWalletDispatch(1003, [
            'request_id' => '1003@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => FundingApply::WALLET_STATUS_NOT_SUBMITTED.'_SUFFIX',
            'next_retry_at' => $now->subSeconds(10)->format('Y-m-d H:i:s'),
        ]);

        $candidates = $this->service()->getDueRetryCandidates($now, 100);

        $this->assertSame([['id' => 2, 'order_no' => '1002', 'user_code' => 8]], $candidates);
    }

    public function test_due_candidates_exclude_withdrawal_restricted_users(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $this->seedProcessingWithdrawal(1002, 8);
        $now = CarbonImmutable::now('UTC');

        $this->setWalletDispatch(1001, [
            'request_id' => '1001@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => FundingApply::WALLET_STATUS_NOT_SUBMITTED,
            'next_retry_at' => $now->subSeconds(10)->format('Y-m-d H:i:s'),
        ]);
        $this->setWalletDispatch(1002, [
            'request_id' => '1002@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => FundingApply::WALLET_STATUS_NOT_SUBMITTED,
            'next_retry_at' => $now->subSeconds(10)->format('Y-m-d H:i:s'),
        ]);

        DB::connection(DB_API)->table('user_prohibition')->insert([
            'user_code' => 7,
            'type' => UserProhibition::TYPE_WITHDRAW,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $candidates = $this->service()->getDueRetryCandidates($now, 100);

        $this->assertSame([['id' => 2, 'order_no' => '1002', 'user_code' => 8]], $candidates);
    }

    public function test_retry_attempt_claim_advances_lease_and_reuses_request_id(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $now = CarbonImmutable::now('UTC');
        $this->setWalletDispatch(1001, [
            'request_id' => '1001@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => FundingApply::WALLET_STATUS_NOT_SUBMITTED,
            'next_retry_at' => $now->subSeconds(10)->format('Y-m-d H:i:s'),
        ]);

        $prepared = $this->service()->prepareRetryAttempt(1001, FundingApply::WALLET_RETRY_TRIGGER_AUTO);

        $this->assertNotNull($prepared);
        $this->assertSame('1001@retry', $prepared['request_id']);

        $state = $this->walletDispatch(1001);
        $this->assertSecondsNear(FundingApply::WALLET_RETRY_CLAIM_LEASE_SECONDS, $this->secondsUntil($state['retry_lease_until']));
        $this->assertSecondsNear(10, $this->secondsUntil($state['next_retry_at']));

        // 租约期内再次准备：不可重复执行。
        $this->assertNull($this->service()->prepareRetryAttempt(1001, FundingApply::WALLET_RETRY_TRIGGER_AUTO));
    }

    public function test_manual_retry_can_run_before_scheduled_time_but_not_concurrently(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $now = CarbonImmutable::now('UTC');
        $scheduledAt = $now->addSeconds(60)->format('Y-m-d H:i:s');
        $this->setWalletDispatch(1001, [
            'request_id' => '1001@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => FundingApply::WALLET_STATUS_NOT_SUBMITTED,
            'next_retry_at' => $scheduledAt,
        ]);

        $prepared = $this->service()->prepareRetryAttempt(1001, FundingApply::WALLET_RETRY_TRIGGER_MANUAL);

        $this->assertNotNull($prepared);
        $this->assertSame($scheduledAt, $this->walletDispatch(1001)['next_retry_at']);
        $this->assertArrayHasKey('retry_lease_until', $this->walletDispatch(1001));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(trans('withdrawal.message.retry_in_progress'));

        $this->service()->prepareRetryAttempt(1001, FundingApply::WALLET_RETRY_TRIGGER_MANUAL);
    }

    public function test_retry_attempt_rejects_another_wallet_error(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $now = CarbonImmutable::now('UTC');
        $this->setWalletDispatch(1001, [
            'request_id' => '1001@retry',
            'retry_phase' => FundingApply::WALLET_RETRY_PHASE_RETRYING,
            'auto_retry_count' => 0,
            'last_error_code' => 'WALLET_REQUEST_FAILED',
            'next_retry_at' => $now->subSeconds(10)->format('Y-m-d H:i:s'),
        ]);

        $this->assertNull($this->service()->prepareRetryAttempt(1001, FundingApply::WALLET_RETRY_TRIGGER_MANUAL));
    }

    public function test_legacy_orders_without_wallet_dispatch_are_not_retryable(): void
    {
        $this->seedProcessingWithdrawal(1001, 7);
        $now = CarbonImmutable::now('UTC');

        $this->assertSame([], $this->service()->getDueRetryCandidates($now, 100));
        $this->assertNull($this->service()->prepareRetryAttempt(1001, FundingApply::WALLET_RETRY_TRIGGER_AUTO));
    }

    private function service(): WithdrawalRetryService
    {
        return new WithdrawalRetryService(new FundingApplyRepository(new FundingApply));
    }

    private function seedProcessingWithdrawal(int $orderNo, int $userCode, array $referenceContent = []): void
    {
        DB::connection(DB_API)->table('user')->insertOrIgnore([
            'user_code' => $userCode,
            'email' => 'user'.$userCode.'@example.com',
            'type' => 0,
        ]);

        DB::connection(DB_API)->table('funding_apply')->insert([
            'order_no' => $orderNo,
            'user_code' => $userCode,
            'direction' => FundingApply::DIRECTION_WITHDRAW,
            'fund_type' => 2,
            'apply_amount' => 10,
            'net_amount' => 10,
            'status' => 3,
            'risk_status' => FundingRiskStatus::Approved->value,
            'finance_status' => FundingFinanceStatus::Pending->value,
            'reference_content' => $referenceContent === [] ? null : json_encode($referenceContent, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setWalletDispatch(int $orderNo, array $walletDispatch): void
    {
        $record = DB::connection(DB_API)->table('funding_apply')->where('order_no', $orderNo)->first(['id', 'reference_content']);
        $decoded = json_decode((string) ($record->reference_content ?? ''), true);
        $referenceContent = is_array($decoded) ? $decoded : [];
        $referenceContent['wallet_dispatch'] = $walletDispatch;

        DB::connection(DB_API)->table('funding_apply')
            ->where('order_no', $orderNo)
            ->update(['reference_content' => json_encode($referenceContent, JSON_UNESCAPED_UNICODE)]);
    }

    private function walletDispatch(int $orderNo): array
    {
        $record = DB::connection(DB_API)->table('funding_apply')->where('order_no', $orderNo)->first(['reference_content']);
        $decoded = json_decode((string) ($record->reference_content ?? ''), true);

        return is_array($decoded) ? ($decoded['wallet_dispatch'] ?? []) : [];
    }

    private function secondsUntil(string $datetime): int
    {
        return abs(CarbonImmutable::parse($datetime, 'UTC')->diffInSeconds(CarbonImmutable::now('UTC'), false));
    }

    private function assertSecondsNear(int $expected, int $actual): void
    {
        // 执行耗时允许 1 秒误差。
        $this->assertGreaterThanOrEqual($expected - 1, $actual);
        $this->assertLessThanOrEqual($expected, $actual);
    }
}
