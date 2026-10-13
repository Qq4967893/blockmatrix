<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\UserCreditRecord;
use App\Repositories\Api\CountryRepository;
use App\Repositories\Api\FundingApplyRepository;
use App\Repositories\Api\TradeAccountRepository;
use App\Repositories\Api\UserCreditLedgerRepository;
use App\Repositories\Api\UserCreditRecordRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Api\UserSocialRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Repositories\Et6\AccountRepository;
use App\Repositories\Et6\BalanceFlowRepository;
use App\Repositories\Et6\TradeRepository;
use App\Services\Api\UserService;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class UserServiceOverviewTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container;
        $container->instance('config', new ConfigRepository(['app.locale' => 'zh_CN']));

        $loader = new ArrayLoader;
        $loader->addMessages('zh_CN', 'user', [
            'reg_way' => ['email' => '邮箱'],
            'user_type' => [0 => '真实账户'],
            'bonus_status' => [1 => '可用', 0 => '不可用'],
        ]);
        $container->instance('translator', new Translator($loader, 'zh_CN'));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_overview_aggregates_local_data_by_user_code(): void
    {
        $userCode = 637580377;

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('firstBy')
            ->once()
            ->with('user_code', $userCode, ['*'])
            ->andReturn([
                'id' => 1,
                'user_code' => $userCode,
                'email' => 'user@example.com',
                'country' => null,
                'type' => 0,
            ]);
        $userSocialRepo = Mockery::mock(UserSocialRepository::class);
        $userSocialRepo->shouldReceive('getByUserCode')
            ->once()
            ->with($userCode)
            ->andReturn([]);

        $fundingApplyRepo = Mockery::mock(FundingApplyRepository::class);
        $fundingApplyRepo->shouldReceive('getSuccessfulAmountTotalsByUserCode')
            ->once()
            ->with($userCode)
            ->andReturn(['deposit' => 1250.50, 'withdraw' => 200.25]);

        $tradeRepo = Mockery::mock(TradeRepository::class);
        $tradeRepo->shouldReceive('getUserTradeSummary')
            ->once()
            ->with($userCode)
            ->andReturn(['profit' => 105.03, 'order_count' => 18]);

        $tradeAccountRepo = Mockery::mock(TradeAccountRepository::class);
        $tradeAccountRepo->shouldNotReceive('getTradeAccountIdsByUserCode');
        $tradeAccountRepo->shouldReceive('existsBonusFundByUserCode')
            ->once()
            ->with($userCode)
            ->andReturn(true);

        $creditRecordRepo = Mockery::mock(UserCreditRecordRepository::class);
        $creditRecordRepo->shouldReceive('latestByUserCode')
            ->once()
            ->with($userCode)
            ->andReturn($this->availableCreditRecord());

        $creditLedgerRepo = Mockery::mock(UserCreditLedgerRepository::class);
        $creditLedgerRepo->shouldReceive('sumUnlockByCreditRecordId')
            ->once()
            ->with(11)
            ->andReturn(100.0);

        $service = new UserService(
            $userRepo,
            $userSocialRepo,
            $tradeAccountRepo,
            Mockery::mock(UserOperationLogRepository::class),
            Mockery::mock(CountryRepository::class),
            $fundingApplyRepo,
            $tradeRepo,
            Mockery::mock(AccountRepository::class),
            Mockery::mock(BalanceFlowRepository::class),
            $creditRecordRepo,
            $creditLedgerRepo
        );

        $overview = $service->getOverviewInfo($userCode);

        self::assertSame(1250.50, $overview['total_deposit_amount']);
        self::assertSame(200.25, $overview['total_withdraw_amount']);
        self::assertSame(1050.25, $overview['net_deposit_amount']);
        self::assertSame(105.03, $overview['total_profit_amount']);
        self::assertSame('8.40%', $overview['total_profit_rate']);
        self::assertSame(18, $overview['total_order_count']);
        self::assertSame('', $overview['network']);
        self::assertTrue($overview['has_open_bonus_fund']);
        self::assertSame(100.0, $overview['bonus_amount']);
        self::assertSame(100.0, $overview['unlocked_bonus_amount']);
        self::assertSame(1, $overview['bonus_status']);
        self::assertSame('可用', $overview['bonus_status_text']);
    }

    public function test_trade_accounts_use_local_account_funding_and_trade_data(): void
    {
        $userCode = 990834751;
        $tradeAccountIds = [4573141273, 4933736486];

        $tradeAccountRepo = Mockery::mock(TradeAccountRepository::class);
        $tradeAccountRepo->shouldReceive('getAccountsByUserCode')
            ->twice()
            ->with($userCode)
            ->andReturn([
                [
                    'trade_account_id' => 4573141273,
                    'trade_account_type' => 0,
                    'created_at' => '2026-08-05 03:44:00',
                ],
                [
                    'trade_account_id' => 4933736486,
                    'trade_account_type' => 2,
                    'created_at' => '2026-08-05 03:44:00',
                ],
            ]);

        $accountRepo = Mockery::mock(AccountRepository::class);
        $accountRepo->shouldReceive('getTradeAccountInfoByLogins')
            ->twice()
            ->with($tradeAccountIds)
            ->andReturn([
                4573141273 => ['currency' => 'USDT', 'so_type' => 2, 'position_mode' => 2],
                4933736486 => ['currency' => 'USDT', 'so_type' => 2, 'position_mode' => 2],
            ]);

        $balanceFlowRepo = Mockery::mock(BalanceFlowRepository::class);
        $balanceFlowRepo->shouldReceive('getDepositWithdrawByLogins')
            ->twice()
            ->with($tradeAccountIds)
            ->andReturn([
                4573141273 => ['deposit' => 200.0, 'withdraw' => 25.5, 'net_deposit' => 174.5],
                4933736486 => ['deposit' => 10000.0, 'withdraw' => 0.0, 'net_deposit' => 10000.0],
            ]);

        $tradeRepo = Mockery::mock(TradeRepository::class);
        $tradeRepo->shouldReceive('getTradeAccountSummaries')
            ->twice()
            ->with($tradeAccountIds)
            ->andReturn([
                4573141273 => ['profit' => -15.56, 'order_count' => 4],
                4933736486 => ['profit' => 0.0, 'order_count' => 0],
            ]);

        $service = new UserService(
            Mockery::mock(UserRepository::class),
            Mockery::mock(UserSocialRepository::class),
            $tradeAccountRepo,
            Mockery::mock(UserOperationLogRepository::class),
            Mockery::mock(CountryRepository::class),
            Mockery::mock(FundingApplyRepository::class),
            $tradeRepo,
            $accountRepo,
            $balanceFlowRepo,
            Mockery::mock(UserCreditRecordRepository::class),
            Mockery::mock(UserCreditLedgerRepository::class)
        );

        $accounts = $service->getUserTradeAccounts($userCode);

        self::assertCount(2, $accounts);
        self::assertSame(200.0, $accounts[0]['total_deposit_amount']);
        self::assertSame(25.5, $accounts[0]['total_withdraw_amount']);
        self::assertSame(174.5, $accounts[0]['net_deposit_amount']);
        self::assertSame(-15.56, $accounts[0]['total_profit_amount']);
        self::assertSame(4, $accounts[0]['total_order_count']);
        self::assertSame(2, $accounts[0]['so_type']);
        self::assertSame(2, $accounts[0]['position_mode']);
        self::assertSame('200.00 USDT', $accounts[0]['total_deposit_amount_text']);
        self::assertSame(10000.0, $accounts[1]['total_deposit_amount']);
        self::assertSame(10000.0, $accounts[1]['net_deposit_amount']);

        $filteredAccounts = $service->getUserTradeAccounts($userCode, [2, 3]);

        self::assertCount(1, $filteredAccounts);
        self::assertSame(2, $filteredAccounts[0]['trade_account_type']);
    }

    private function availableCreditRecord(): UserCreditRecord
    {
        $record = Mockery::mock(UserCreditRecord::class);
        $record->shouldReceive('getAttribute')->with('id')->andReturn(11);
        $record->shouldReceive('getAttribute')->with('grant_amount')->andReturn(100.0);
        $record->shouldReceive('getAttribute')->with('status')->andReturn(1);

        return $record;
    }
}
