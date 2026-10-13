<?php

namespace Tests\Unit;

use App\Repositories\Api\CopyTradingRepository;
use App\Repositories\Api\MasterAccountRepository;
use App\Repositories\Api\TradeAccountRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Repositories\Et6\AccountRepository;
use App\Repositories\Et6\BalanceFlowRepository;
use App\Repositories\Et6\CopyHistoryRepository;
use App\Services\Api\CopyTradingService;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Scaffold\Framework\Enums\TypeTradeAccount;
use Scaffold\Framework\Services\MasterStatusOperateService;

class CopyTradingOptionsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new class extends Container {
            public function getLocale(): string
            {
                return (string) config('app.locale', 'zh_CN');
            }
        };
        $container->instance('config', new ConfigRepository(['app.locale' => 'zh_CN']));

        $loader = new ArrayLoader();
        $loader->addMessages('zh_CN', 'copytrading', [
            'master_status' => ['0' => '申请中', '1' => '正常', '2' => '已拒绝', '3' => '已失效', '4' => '已隐藏'],
            'master_type' => ['0' => '公开高手', '1' => '私密高手'],
            'follow_status' => ['following' => '跟单中', 'ended' => '已结束'],
        ]);
        $loader->addMessages('zh_CN', 'user', [
            'trade_account' => [
                'type' => [
                    0 => '统一交易账号',
                    1 => '跟单账号',
                    2 => '比赛账号',
                    3 => '体验金账号',
                ],
            ],
        ]);

        $translator = new Translator($loader, 'zh_CN');
        $container->instance('translator', $translator);
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_master_level_options_include_follow_statuses_and_all_trade_account_types(): void
    {
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $copyTradingRepo->shouldReceive('getAllMasterLevels')
            ->once()
            ->andReturn(new EloquentCollection());

        $service = new CopyTradingService(
            $copyTradingRepo,
            Mockery::mock(MasterStatusOperateService::class),
            Mockery::mock(CopyHistoryRepository::class),
            Mockery::mock(AccountRepository::class),
            Mockery::mock(BalanceFlowRepository::class),
            Mockery::mock(UserRepository::class),
            Mockery::mock(TradeAccountRepository::class),
            Mockery::mock(MasterAccountRepository::class),
            Mockery::mock(AdminUserRepository::class),
            Mockery::mock(UserOperationLogRepository::class)
        );

        $options = $service->getMasterLevelOptions();

        self::assertSame(['0', '1'], array_column($options['follow_statuses'], 'key'));
        self::assertSame(
            [
                (string) TypeTradeAccount::UTA->value,
                (string) TypeTradeAccount::BonusFund->value,
            ],
            array_column($options['trade_account_types'], 'key')
        );
    }
}
