<?php

namespace Tests\Unit\Repositories\Api;

use App\Models\Api\FundingApply;
use App\Models\Api\User;
use App\Repositories\Api\FundingApplyRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Services\Api\FundingApplyService;
use App\Services\Api\FundingNetworkConfigService;
use App\Services\Api\WithdrawalRetryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Scaffold\Framework\Enums\TypeLedger;
use Scaffold\Funding\Enums\FundingApplyStatus;
use Scaffold\Funding\Enums\FundingFinanceStatus;
use Scaffold\Funding\Enums\FundingRiskStatus;
use Tests\TestCase;

class FundingApplyRepositoryTest extends TestCase
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

        $this->createTables();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_risk_list_returns_dynamic_daily_successful_withdrawals_plus_current_order(): void
    {
        $this->seedFundingApplies();

        $adminUserRepository = Mockery::mock(AdminUserRepository::class);
        $adminUserRepository->shouldReceive('getColumnByIds')
            ->once()
            ->with([], 'login_name')
            ->andReturn([]);

        $repository = new FundingApplyRepository(new FundingApply);

        $service = new FundingApplyService(
            $repository,
            Mockery::mock(FundingNetworkConfigService::class),
            $adminUserRepository,
            new WithdrawalRetryService($repository),
        );

        $result = $service->getWithdrawalRiskList(['status' => null], 20, 0);

        $currentOrder = $this->findById($result['data'], 3);
        $laterOrderWithSameTimestamp = $this->findById($result['data'], 4);
        $nextDayOrder = $this->findById($result['data'], 5);
        $successfulOrder = $this->findById($result['data'], 1);

        $this->assertSame(3, $currentOrder['daily_withdraw_count']);
        $this->assertSame(100.0, $currentOrder['daily_total_withdraw_amount']);
        $this->assertSame(3, $laterOrderWithSameTimestamp['daily_withdraw_count']);
        $this->assertSame(110.0, $laterOrderWithSameTimestamp['daily_total_withdraw_amount']);
        $this->assertSame(1, $nextDayOrder['daily_withdraw_count']);
        $this->assertSame(40.0, $nextDayOrder['daily_total_withdraw_amount']);
        $this->assertSame(2, $successfulOrder['daily_withdraw_count']);
        $this->assertSame(80.0, $successfulOrder['daily_total_withdraw_amount']);
    }

    public function test_finance_list_returns_display_network_text(): void
    {
        $this->seedFundingApplies();
        DB::connection(DB_API)->table('funding_apply')
            ->where('id', 3)
            ->update([
                'reference_content' => null,
                'withdraw_to' => json_encode([
                    'token' => 'USDT',
                    'chain_id' => 'eip155:11155111',
                    'wallet_address' => '0x58458b7c6fC8cDe3425Ad645a32e04FE66E018CB',
                ], JSON_THROW_ON_ERROR),
                'finance_status' => FundingApply::withdrawalFinanceStatusMap()['pending'],
            ]);

        $adminUserRepository = Mockery::mock(AdminUserRepository::class);
        $adminUserRepository->shouldReceive('getColumnByIds')
            ->once()
            ->with([], 'login_name')
            ->andReturn([]);

        $fundingNetworkConfigService = Mockery::mock(FundingNetworkConfigService::class);
        $fundingNetworkConfigService->shouldReceive('networkDisplayText')
            ->once()
            ->with('eip155:11155111', 'USDT')
            ->andReturn('以太坊 Sepolia 测试网(ERC20)');

        $repository = new FundingApplyRepository(new FundingApply);
        $service = new FundingApplyService(
            $repository,
            $fundingNetworkConfigService,
            $adminUserRepository,
            new WithdrawalRetryService($repository),
        );

        $result = $service->getWithdrawalFinanceList(['status' => null], 20, 0);
        $order  = $this->findById($result['data'], 3);

        $this->assertSame('以太坊 Sepolia 测试网(ERC20)', $order['network_text']);
    }

    public function test_risk_list_returns_display_network_text(): void
    {
        $this->seedFundingApplies();
        DB::connection(DB_API)->table('funding_apply')
            ->where('id', 3)
            ->update([
                'reference_content' => null,
                'withdraw_to' => json_encode([
                    'token' => 'USDT',
                    'chain_id' => 'eip155:11155111',
                    'wallet_address' => '0x58458b7c6fC8cDe3425Ad645a32e04FE66E018CB',
                ], JSON_THROW_ON_ERROR),
            ]);

        $adminUserRepository = Mockery::mock(AdminUserRepository::class);
        $adminUserRepository->shouldReceive('getColumnByIds')
            ->once()
            ->with([], 'login_name')
            ->andReturn([]);

        $fundingNetworkConfigService = Mockery::mock(FundingNetworkConfigService::class);
        $fundingNetworkConfigService->shouldReceive('networkDisplayText')
            ->once()
            ->with('eip155:11155111', 'USDT')
            ->andReturn('以太坊 Sepolia 测试网(ERC20)');

        $repository = new FundingApplyRepository(new FundingApply);
        $service = new FundingApplyService(
            $repository,
            $fundingNetworkConfigService,
            $adminUserRepository,
            new WithdrawalRetryService($repository),
        );

        $result = $service->getWithdrawalRiskList(['status' => null], 20, 0);
        $order  = $this->findById($result['data'], 3);

        $this->assertSame('以太坊 Sepolia 测试网(ERC20)', $order['network_text']);
    }

    public function test_deposit_list_returns_display_network_text(): void
    {
        $this->seedFundingApplies();
        DB::connection(DB_API)->table('funding_apply')->insert(array_merge(
            $this->fundingApply(
                10,
                7,
                FundingApply::DIRECTION_DEPOSIT,
                60,
                '2026-08-20 10:00:00',
                FundingApply::STATUS_APPROVED,
                FundingApply::withdrawalRiskStatusMap()['pending'],
                '2026-08-20 10:30:00',
            ),
            [
                'reference_content' => json_encode([
                    'token' => 'USDT',
                    'chain_id' => 'eip155:11155111',
                    'transaction_hash' => '0xabc',
                ], JSON_THROW_ON_ERROR),
            ],
        ));

        $fundingNetworkConfigService = Mockery::mock(FundingNetworkConfigService::class);
        $fundingNetworkConfigService->shouldReceive('networkDisplayText')
            ->once()
            ->with('eip155:11155111', 'USDT')
            ->andReturn('以太坊 Sepolia 测试网(ERC20)');

        $repository = new FundingApplyRepository(new FundingApply);
        $service = new FundingApplyService(
            $repository,
            $fundingNetworkConfigService,
            Mockery::mock(AdminUserRepository::class),
            new WithdrawalRetryService($repository),
        );

        $result = $service->getDepositList([], 20, 0);
        $order  = $this->findById($result['data'], 10);

        $this->assertSame('以太坊 Sepolia 测试网(ERC20)', $order['network_text']);
    }

    public function test_withdrawal_audit_lookup_includes_current_user_type(): void
    {
        $this->seedFundingApplies();
        DB::connection(DB_API)->table('user')
            ->where('user_code', 8)
            ->update(['type' => User::TYPE_TEST]);

        $repository = new FundingApplyRepository(new FundingApply);

        $normalUser = $repository->findWithdrawalByOrderNo(1003);
        $whiteListUser = $repository->findWithdrawalByOrderNo(1006);

        $this->assertSame(User::TYPE_REAL, (int) $normalUser['user_type']);
        $this->assertSame(User::TYPE_TEST, (int) $whiteListUser['user_type']);
    }

    public function test_finance_list_manual_retry_flag_requires_handleable_state_and_retry_phase(): void
    {
        $this->seedFundingApplies();
        $referenceWithRetrying = $this->walletDispatchReference(FundingApply::WALLET_RETRY_PHASE_RETRYING);
        $referenceWithExhausted = $this->walletDispatchReference(FundingApply::WALLET_RETRY_PHASE_EXHAUSTED);
        $referenceWithAnotherExhaustedError = $this->walletDispatchReference(
            FundingApply::WALLET_RETRY_PHASE_EXHAUSTED,
            'WALLET_REQUEST_FAILED'
        );
        $referenceWithRetryInProgress = json_decode($this->walletDispatchReference(FundingApply::WALLET_RETRY_PHASE_RETRYING), true);
        $referenceWithRetryInProgress['wallet_dispatch']['retry_lease_until'] = now()->addMinutes(15)->format('Y-m-d H:i:s');
        $referenceWithRetryInProgress = json_encode($referenceWithRetryInProgress, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        DB::connection(DB_API)->table('funding_apply')->insert([
            array_merge($this->fundingApply(8, 7, FundingApply::DIRECTION_WITHDRAW, 10, '2026-08-20 12:00:00', FundingApplyStatus::Processing->value, FundingRiskStatus::Approved->value), [
                'finance_status' => FundingFinanceStatus::Pending->value,
                'reference_content' => $referenceWithRetrying,
            ]),
            array_merge($this->fundingApply(9, 7, FundingApply::DIRECTION_WITHDRAW, 11, '2026-08-20 12:01:00', FundingApplyStatus::Success->value, FundingRiskStatus::Approved->value), [
                'finance_status' => FundingFinanceStatus::Approved->value,
                'reference_content' => $referenceWithRetrying,
            ]),
            array_merge($this->fundingApply(10, 7, FundingApply::DIRECTION_WITHDRAW, 12, '2026-08-20 12:02:00', FundingApplyStatus::FinanceRejected->value, FundingRiskStatus::Approved->value), [
                'finance_status' => FundingFinanceStatus::Rejected->value,
                'reference_content' => $referenceWithRetrying,
            ]),
            array_merge($this->fundingApply(11, 7, FundingApply::DIRECTION_WITHDRAW, 13, '2026-08-20 12:03:00', FundingApplyStatus::Failed->value, FundingRiskStatus::Approved->value), [
                'finance_status' => FundingFinanceStatus::Failed->value,
                'reference_content' => $referenceWithExhausted,
            ]),
            array_merge($this->fundingApply(12, 7, FundingApply::DIRECTION_WITHDRAW, 14, '2026-08-20 12:04:00', FundingApplyStatus::Failed->value, FundingRiskStatus::Approved->value), [
                'finance_status' => FundingFinanceStatus::Failed->value,
                'reference_content' => $referenceWithAnotherExhaustedError,
            ]),
            array_merge($this->fundingApply(13, 7, FundingApply::DIRECTION_WITHDRAW, 15, '2026-08-20 12:05:00', FundingApplyStatus::Processing->value, FundingRiskStatus::Approved->value), [
                'finance_status' => FundingFinanceStatus::Pending->value,
                'reference_content' => $referenceWithRetryInProgress,
            ]),
        ]);

        $adminUserRepository = Mockery::mock(AdminUserRepository::class);
        $adminUserRepository->shouldReceive('getColumnByIds')
            ->once()
            ->with([], 'login_name')
            ->andReturn([]);
        $repository = new FundingApplyRepository(new FundingApply);
        $service = new FundingApplyService(
            $repository,
            Mockery::mock(FundingNetworkConfigService::class),
            $adminUserRepository,
            new WithdrawalRetryService($repository),
        );

        $result = $service->getWithdrawalFinanceList(['status' => null], 20, 0);

        $this->assertSame(1, $this->findByOrderNo($result['data'], '1008')['can_manual_retry']);
        $this->assertSame(0, $this->findByOrderNo($result['data'], '1009')['can_manual_retry']);
        $this->assertSame(0, $this->findByOrderNo($result['data'], '1010')['can_manual_retry']);
        $this->assertSame(1, $this->findByOrderNo($result['data'], '1011')['can_manual_retry']);
        $this->assertSame(0, $this->findByOrderNo($result['data'], '1012')['can_manual_retry']);
        $this->assertSame(0, $this->findByOrderNo($result['data'], '1013')['can_manual_retry']);
        $this->assertTrue($this->findByOrderNo($result['data'], '1013')['retry']['retry_in_progress']);
    }

    public function test_failed_withdrawal_is_reset_for_risk_retry(): void
    {
        DB::connection(DB_API)->table('funding_apply')->insert(array_merge(
            $this->fundingApply(
                9,
                7,
                FundingApply::DIRECTION_WITHDRAW,
                20,
                '2026-08-20 11:00:00',
                FundingApplyStatus::Failed->value,
                FundingRiskStatus::Approved->value,
                '2026-08-20 11:05:00',
            ),
            [
                'finance_status' => FundingFinanceStatus::Failed->value,
                'risk_audited_by' => 91,
                'risk_audited_at' => '2026-08-20 11:01:00',
                'risk_reason' => 'previous risk audit',
                'finance_audited_by' => 92,
                'finance_audited_at' => '2026-08-20 11:05:00',
                'finance_reason' => 'wallet request failed',
            ],
        ));

        $repository = new FundingApplyRepository(new FundingApply);

        $affected = $repository->resetFailedWithdrawalForRiskRetry(1009);
        $apply = DB::connection(DB_API)->table('funding_apply')->where('order_no', '1009')->first();

        $this->assertSame(1, $affected);
        $this->assertSame(FundingApplyStatus::Pending->value, (int) $apply->status);
        $this->assertSame(FundingRiskStatus::Pending->value, (int) $apply->risk_status);
        $this->assertNull($apply->finance_status);
        $this->assertNull($apply->risk_audited_by);
        $this->assertNull($apply->risk_audited_at);
        $this->assertNull($apply->risk_reason);
        $this->assertNull($apply->finance_audited_by);
        $this->assertNull($apply->finance_audited_at);
        $this->assertNull($apply->finance_reason);
        $this->assertNull($apply->effective_at);
    }

    private function createTables(): void
    {
        Schema::connection(DB_API)->create('user', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_code')->primary();
            $table->string('email')->default('');
            $table->unsignedTinyInteger('type')->default(0);
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
            $table->decimal('pay_amount', 20, 6)->nullable();
            $table->string('pay_currency')->nullable();
            $table->decimal('pay_rate', 20, 8)->nullable();
            $table->string('platform')->nullable();
            $table->unsignedTinyInteger('status');
            $table->string('order_id')->nullable();
            $table->text('reference_content')->nullable();
            $table->dateTime('effective_at')->nullable();
            $table->text('withdraw_to')->nullable();
            $table->unsignedTinyInteger('risk_status')->nullable();
            $table->unsignedTinyInteger('finance_status')->nullable();
            $table->unsignedBigInteger('risk_audited_by')->nullable();
            $table->dateTime('risk_audited_at')->nullable();
            $table->unsignedBigInteger('finance_audited_by')->nullable();
            $table->dateTime('finance_audited_at')->nullable();
            $table->string('risk_reason')->nullable();
            $table->string('finance_reason')->nullable();
            $table->timestamps();
        });
    }

    private function seedFundingApplies(): void
    {
        DB::connection(DB_API)->table('user')->insert([
            ['user_code' => 7, 'email' => 'user7@example.com', 'type' => 0],
            ['user_code' => 8, 'email' => 'user8@example.com', 'type' => 0],
        ]);

        $pendingRisk = FundingApply::withdrawalRiskStatusMap()['pending'];
        DB::connection(DB_API)->table('funding_apply')->insert([
            $this->fundingApply(1, 7, FundingApply::DIRECTION_WITHDRAW, 10, '2026-08-20 09:00:00', FundingApply::STATUS_APPROVED, $pendingRisk, '2026-08-20 09:30:00'),
            $this->fundingApply(2, 7, FundingApply::DIRECTION_WITHDRAW, 999, '2026-08-20 09:15:00', FundingApply::STATUS_CANCEL, $pendingRisk, '2026-08-20 09:20:00'),
            $this->fundingApply(3, 7, FundingApply::DIRECTION_WITHDRAW, 20, '2026-08-20 11:00:00', FundingApply::STATUS_PENDING, $pendingRisk),
            $this->fundingApply(4, 7, FundingApply::DIRECTION_WITHDRAW, 30, '2026-08-20 11:00:00', FundingApply::STATUS_PENDING, $pendingRisk),
            $this->fundingApply(5, 7, FundingApply::DIRECTION_WITHDRAW, 40, '2026-08-21 09:00:00', FundingApply::STATUS_PENDING, $pendingRisk),
            $this->fundingApply(6, 8, FundingApply::DIRECTION_WITHDRAW, 50, '2026-08-20 10:30:00', FundingApply::STATUS_APPROVED, $pendingRisk, '2026-08-20 10:45:00'),
            $this->fundingApply(7, 7, FundingApply::DIRECTION_WITHDRAW, 70, '2026-08-20 12:00:00', FundingApply::STATUS_APPROVED, $pendingRisk, '2026-08-20 12:30:00'),
        ]);
    }

    private function fundingApply(
        int $id,
        int $userCode,
        int $direction,
        int $amount,
        string $createdAt,
        int $status,
        int $riskStatus,
        ?string $effectiveAt = null,
    ): array {
        return [
            'id' => $id,
            'order_no' => (string) (1000 + $id),
            'user_code' => $userCode,
            'direction' => $direction,
            'fund_type' => $direction === FundingApply::DIRECTION_WITHDRAW
                ? TypeLedger::Withdraw->value
                : TypeLedger::Deposit->value,
            'apply_amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => $amount,
            'status' => $status,
            'risk_status' => $riskStatus,
            'effective_at' => $effectiveAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    private function findById(array $rows, int $id): array
    {
        foreach ($rows as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        $this->fail(sprintf('Funding apply [%d] was not found.', $id));
    }

    private function findByOrderNo(array $rows, string $orderNo): array
    {
        foreach ($rows as $row) {
            if (($row['order_no'] ?? null) === $orderNo) {
                return $row;
            }
        }

        $this->fail(sprintf('Funding apply [%s] was not found.', $orderNo));
    }

    private function walletDispatchReference(string $retryPhase, ?string $errorCode = null): string
    {
        return json_encode([
            'wallet_dispatch' => [
                'request_id' => '1000@retry',
                'retry_phase' => $retryPhase,
                'auto_retry_count' => 3,
                'auto_max_attempts' => 3,
                'next_retry_at' => null,
                'last_error_code' => $errorCode ?? FundingApply::WALLET_STATUS_NOT_SUBMITTED,
                'last_error_message' => 'withdrawal was not submitted to PSP',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
