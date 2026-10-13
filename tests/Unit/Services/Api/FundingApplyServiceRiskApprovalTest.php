<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\FundingApply;
use App\Repositories\Api\FundingApplyRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Services\Api\FundingApplyService;
use App\Services\Api\FundingNetworkConfigService;
use App\Services\Api\WithdrawalRetryService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Scaffold\FluxWallet\Facades\FluxWallet;
use Scaffold\Framework\Models\Api\FundingApply as ScaffoldFundingApply;
use Scaffold\Funding\Enums\FundingApplyStatus;
use Scaffold\Funding\Enums\FundingFinanceStatus;
use Scaffold\Funding\Enums\FundingRiskStatus;
use Scaffold\Funding\Facades\Funding as FundingFacade;
use Tests\TestCase;

class FundingApplyServiceRiskApprovalTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_condition_failure_keeps_order_pending_without_calling_wallet(): void
    {
        [$service, $repository] = $this->service();
        $ledger = $this->pendingLedger();

        $repository->shouldReceive('findWithdrawalByOrderNo')->once()->andReturn($ledger);
        $repository->shouldReceive('lockWithdrawalUser')->once()->with(7);
        $repository->shouldReceive('lockWithdrawalByOrderNo')->once()->andReturn($ledger);
        FundingFacade::shouldReceive('approveRisk')->never();
        FluxWallet::shouldReceive('createWithdrawal')->never();
        $this->mockTransaction();

        $result = $service->approveRiskWhen(1001, 0, static fn (): bool => false);

        $this->assertNull($result);
    }

    public function test_existing_approval_calls_wallet_once_after_the_risk_transaction_commits(): void
    {
        [$service, $repository] = $this->service();
        $ledger = $this->pendingLedger();
        $processingLedger = $this->processingLedger();
        $events = [];

        $repository->shouldReceive('findWithdrawalByOrderNo')->once()->andReturn($ledger);
        $repository->shouldReceive('lockWithdrawalUser')->once()->with(7);
        $repository->shouldReceive('lockWithdrawalByOrderNo')->times(3)->andReturn($ledger, $processingLedger, $processingLedger);
        $repository->shouldReceive('mergeWithdrawalWalletDispatch')->twice();

        FundingFacade::shouldReceive('approveRisk')
            ->once()
            ->with(1001, 99)
            ->andReturnUsing(static function () use (&$events): ScaffoldFundingApply {
                $events[] = 'risk_approved';

                return new ScaffoldFundingApply([
                    'order_no' => 1001,
                    'order_id' => null,
                    'effective_at' => null,
                    'status' => FundingApplyStatus::Processing->value,
                    'risk_status' => FundingRiskStatus::Approved->value,
                    'finance_status' => FundingFinanceStatus::Pending->value,
                ]);
            });
        FluxWallet::shouldReceive('createWithdrawal')
            ->once()
            ->with('1001', 'ethereum', 'USDT', '10', '0xrecipient', '1001@retry')
            ->andReturnUsing(static function () use (&$events): array {
                $events[] = 'wallet_called';

                return ['request_id' => 'withdrawal-1', 'status' => 'pending'];
            });
        $this->mockTransaction($events, 3);

        $result = $service->approveRisk(1001, 99);

        $this->assertSame([
            'risk_approved',
            'transaction_committed',
            'transaction_committed',
            'wallet_called',
            'transaction_committed',
        ], $events);
        $this->assertSame('withdrawal-1', $result['withdrawal']['request_id']);
    }

    public function test_failed_withdrawal_can_be_reset_and_dispatched_again(): void
    {
        [$service, $repository] = $this->service();
        $failedLedger = array_merge($this->pendingLedger(), [
            'status' => FundingApplyStatus::Failed->value,
            'risk_status' => FundingRiskStatus::Approved->value,
            'finance_status' => FundingFinanceStatus::Failed->value,
            'reference_content' => $this->exhaustedNotSubmittedReference(),
        ]);
        $processingLedger = $this->processingLedger();
        $events = [];

        $repository->shouldReceive('findWithdrawalByOrderNo')->once()->andReturn($failedLedger);
        $repository->shouldReceive('lockWithdrawalUser')->once()->with(7);
        $repository->shouldReceive('lockWithdrawalByOrderNo')->times(3)->andReturn($failedLedger, $processingLedger, $processingLedger);
        $repository->shouldReceive('mergeWithdrawalWalletDispatch')->twice();
        $repository->shouldReceive('resetFailedWithdrawalForRiskRetry')->once()->with(1001)->andReturn(1);
        FundingFacade::shouldReceive('approveRisk')
            ->once()
            ->with(1001, 99)
            ->andReturnUsing(static function () use (&$events): ScaffoldFundingApply {
                $events[] = 'risk_approved';

                return new ScaffoldFundingApply([
                    'order_no' => 1001,
                    'order_id' => null,
                    'effective_at' => null,
                    'status' => FundingApplyStatus::Processing->value,
                    'risk_status' => FundingRiskStatus::Approved->value,
                    'finance_status' => FundingFinanceStatus::Pending->value,
                ]);
            });
        FluxWallet::shouldReceive('createWithdrawal')
            ->once()
            ->with('1001', 'ethereum', 'USDT', '10', '0xrecipient', '1001@retry')
            ->andReturnUsing(static function () use (&$events): array {
                $events[] = 'wallet_called';

                return ['request_id' => 'withdrawal-retry-1', 'status' => 'pending'];
            });
        $this->mockTransaction($events, 3);

        $result = $service->retryFailedWithdrawal(1001, 99);

        $this->assertSame([
            'risk_approved',
            'transaction_committed',
            'transaction_committed',
            'wallet_called',
            'transaction_committed',
        ], $events);
        $this->assertSame('withdrawal-retry-1', $result['withdrawal']['request_id']);
    }

    public function test_failed_withdrawal_with_another_error_cannot_be_reset_and_dispatched_again(): void
    {
        [$service, $repository] = $this->service();
        $ledger = array_merge($this->pendingLedger(), [
            'status' => FundingApplyStatus::Failed->value,
            'risk_status' => FundingRiskStatus::Approved->value,
            'finance_status' => FundingFinanceStatus::Failed->value,
            'reference_content' => $this->exhaustedReference('WALLET_REQUEST_FAILED'),
        ]);

        $repository->shouldReceive('findWithdrawalByOrderNo')->once()->andReturn($ledger);
        $repository->shouldReceive('lockWithdrawalUser')->once()->with(7);
        $repository->shouldReceive('lockWithdrawalByOrderNo')->once()->andReturn($ledger);
        $repository->shouldReceive('resetFailedWithdrawalForRiskRetry')->never();
        FundingFacade::shouldReceive('approveRisk')->never();
        FluxWallet::shouldReceive('createWithdrawal')->never();
        $this->mockTransaction();

        $this->expectException(\Exception::class);

        $service->retryFailedWithdrawal(1001, 99);
    }

    public function test_retry_rejects_an_order_that_is_not_finance_failed(): void
    {
        [$service, $repository] = $this->service();
        $ledger = $this->pendingLedger();

        $repository->shouldReceive('findWithdrawalByOrderNo')->once()->andReturn($ledger);
        $repository->shouldReceive('lockWithdrawalUser')->once()->with(7);
        $repository->shouldReceive('lockWithdrawalByOrderNo')->once()->andReturn($ledger);
        $repository->shouldReceive('resetFailedWithdrawalForRiskRetry')->never();
        FundingFacade::shouldReceive('approveRisk')->never();
        FluxWallet::shouldReceive('createWithdrawal')->never();
        $this->mockTransaction();

        $this->expectException(\Exception::class);

        $service->retryFailedWithdrawal(1001, 99);
    }

    /**
     * @return array{FundingApplyService, FundingApplyRepository&MockInterface}
     */
    private function service(): array
    {
        $repository = Mockery::mock(FundingApplyRepository::class);
        $retryService = new WithdrawalRetryService($repository);

        return [
            new FundingApplyService(
                $repository,
                Mockery::mock(FundingNetworkConfigService::class),
                Mockery::mock(AdminUserRepository::class),
                $retryService,
            ),
            $repository,
        ];
    }

    private function mockTransaction(?array &$events = null, int $transactions = 1): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        DB::shouldReceive('connection')->times($transactions)->with(DB_API)->andReturn($connection);
        $connection->shouldReceive('transaction')
            ->times($transactions)
            ->andReturnUsing(static function (callable $callback) use (&$events): mixed {
                $result = $callback();
                if ($events !== null) {
                    $events[] = 'transaction_committed';
                }

                return $result;
            });
    }

    private function pendingLedger(): array
    {
        return [
            'id' => 1,
            'order_no' => 1001,
            'user_code' => 7,
            'status' => FundingApplyStatus::Pending->value,
            'risk_status' => FundingRiskStatus::Pending->value,
            'finance_status' => null,
            'currency' => 'USDT',
            'pay_currency' => 'USDT',
            'apply_amount' => '10.000000',
            'net_amount' => '10.000000',
            'rate' => '1.000000',
            'withdraw_to' => [
                'chain_id' => 'ethereum',
                'wallet_address' => '0xrecipient',
            ],
            'reference_content' => [],
            'created_at' => '2026-08-21 12:00:00',
        ];
    }

    private function processingLedger(): array
    {
        return array_merge($this->pendingLedger(), [
            'status' => FundingApplyStatus::Processing->value,
            'risk_status' => FundingRiskStatus::Approved->value,
            'finance_status' => FundingFinanceStatus::Pending->value,
        ]);
    }

    private function exhaustedNotSubmittedReference(): array
    {
        return $this->exhaustedReference(FundingApply::WALLET_STATUS_NOT_SUBMITTED);
    }

    private function exhaustedReference(string $errorCode): array
    {
        return [
            'wallet_dispatch' => [
                'request_id' => '1001@retry',
                'retry_phase' => FundingApply::WALLET_RETRY_PHASE_EXHAUSTED,
                'auto_retry_count' => FundingApply::MAX_AUTO_RETRY_COUNT,
                'auto_max_attempts' => FundingApply::MAX_AUTO_RETRY_COUNT,
                'next_retry_at' => null,
                'last_error_code' => $errorCode,
                'last_error_message' => 'wallet request failed',
            ],
        ];
    }
}
