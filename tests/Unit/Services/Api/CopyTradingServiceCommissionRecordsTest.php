<?php

namespace Tests\Unit\Services\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Api\CopyTradingController;
use App\Http\Requests\CopyTrading\MasterProtectionTimeUpdateRequest;
use App\Models\Api\MasterAccount;
use App\Models\Api\MasterCommissionRecord;
use App\Models\TradingSystem\Copy;
use App\Repositories\Api\CopyTradingRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Et6\BalanceFlowRepository;
use App\Repositories\Et6\CopyHistoryRepository;
use App\Services\Api\CopyTradingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Mockery;
use ReflectionClass;
use Scaffold\TradeSystem\Facades\TS;
use Tests\TestCase;

class CopyTradingServiceCommissionRecordsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_commission_records_controller_forwards_master_account_filters(): void
    {
        $service = Mockery::mock(CopyTradingService::class);
        $service->shouldReceive('getCommissionRecords')
            ->once()
            ->with(Mockery::on(static function (array $filters): bool {
                return ($filters['master_trade_account_id'] ?? null) === '900001'
                    && ($filters['master_trade_account_type'] ?? null) === '3';
            }), 20, 0)
            ->andReturn(['data' => [], 'total_count' => 0]);

        $controller = new CopyTradingController($service);
        $request = Request::create('/copytrading/commission_records', 'GET', [
            'master_trade_account_id' => '900001',
            'master_trade_account_type' => '3',
        ]);

        $response = $controller->commissionRecords($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_commission_account_filters_use_record_trade_account_ids(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->once()
            ->with('mcr.master_trade_account_id', 900001)
            ->andReturnSelf();
        $query->shouldReceive('whereRaw')
            ->once()
            ->with(
                'EXISTS (SELECT 1 FROM '.DB_API.'.trade_account ta WHERE ta.trade_account_id = mcr.master_trade_account_id AND ta.trade_account_type = ?)',
                [3]
            )
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->once()
            ->with('mcr.follower_trade_account_id', 800001)
            ->andReturnSelf();

        $method = (new ReflectionClass(CopyTradingRepository::class))
            ->getMethod('applyCommissionAccountFilters');
        $method->setAccessible(true);
        $method->invoke(new CopyTradingRepository, $query, [
            'master_trade_account_id' => '900001',
            'master_trade_account_type' => '3',
            'follower_trade_account_id' => '800001',
        ]);

        $this->assertTrue(true);
    }

    public function test_commission_records_include_master_trade_account_id(): void
    {
        $filters = ['master_trade_account_id' => '900001'];
        $repo = Mockery::mock(CopyTradingRepository::class);
        $repo->shouldReceive('getCommissionRecords')
            ->once()
            ->with($filters, 20, 0, null)
            ->andReturn([
                collect([
                    (object) [
                        'follower_ticket' => 'T10001',
                        'follower_user_code' => 200001,
                        'master_user_code' => 100001,
                        'master_trade_account_id' => 900001,
                        'profit_amount' => '12.340000',
                        'commission_amount' => '1.230000',
                        'commission_rate' => '0.150000',
                        'level_pos' => 2,
                        'master_trade_account_type' => null,
                        'follower_trade_account_id' => 800001,
                        'follower_trade_account_type' => null,
                        'close_time' => '2026-08-04 10:00:00',
                        'settled_at' => '2026-08-04 11:00:00',
                        'status' => MasterCommissionRecord::STATUS_SETTLED,
                    ],
                ]),
                1,
            ]);

        $service = $this->makeService($repo);

        $result = $service->getCommissionRecords($filters, 20, 0);

        $this->assertSame(1, $result['total_count']);
        $this->assertSame(900001, $result['data'][0]['master_trade_account_id']);
    }

    public function test_commission_records_resolve_fee_amounts_from_close_snapshot(): void
    {
        $repo = Mockery::mock(CopyTradingRepository::class);
        $repo->shouldReceive('getCommissionRecords')
            ->once()
            ->with([], 20, 0, null)
            ->andReturn([
                collect([
                    (object) [
                        'follower_ticket' => 'T10001',
                        'follower_user_code' => 200001,
                        'master_user_code' => 100001,
                        'master_trade_account_id' => 900001,
                        'profit_amount' => '12.340000',
                        'commission_amount' => '1.230000',
                        'commission_rate' => '0.150000',
                        // 平仓快照：应收=减免前原始开平仓费 51.499331+51.488357；实收=减免后 10.702588+1.488357
                        'copy_order_info' => [
                            'order' => [
                                'open_fee' => -10.702588,
                                'close_fee' => -1.488357,
                                'open_fee_original' => -51.499331,
                                'close_fee_original' => -51.488357,
                                'coupon_deduct' => 40.796743,
                            ],
                        ],
                        'fee_commission_rate' => '0.100000',
                        'fee_commission_amount' => '1.219095',
                        'level_pos' => 2,
                        'master_trade_account_type' => null,
                        'follower_trade_account_id' => 800001,
                        'follower_trade_account_type' => null,
                        'close_time' => '2026-08-04 10:00:00',
                        'settled_at' => '2026-08-04 11:00:00',
                        'status' => MasterCommissionRecord::STATUS_SETTLED,
                    ],
                ]),
                1,
            ]);

        $service = $this->makeService($repo);
        $result = $service->getCommissionRecords([], 20, 0);

        $this->assertSame('102.99', $result['data'][0]['receivable_fee_amount']);
        $this->assertSame('12.19', $result['data'][0]['actual_fee_amount']);
        $this->assertSame('1.219095', $result['data'][0]['fee_commission_amount']);
        $this->assertSame('10', $result['data'][0]['fee_commission_rate']);
        $this->assertSame('2026-08-04 11:00:00', $result['data'][0]['fee_commission_time']);
    }

    public function test_potential_masters_preserve_integer_balance_trailing_zeroes(): void
    {
        $filters = ['trade_account_type' => '3'];
        $repo = Mockery::mock(CopyTradingRepository::class);
        $repo->shouldReceive('getPotentialMasters')
            ->once()
            ->with($filters, 20, 0)
            ->andReturn([
                collect([
                    (object) [
                        'user_code' => 100001,
                        'trade_account_id' => 900001,
                        'trade_account_type' => 3,
                        'type' => 0,
                        'balance' => '10000',
                        'order_count_30d' => 0,
                        'profit_amount_30d' => 0,
                        'total_margin_30d' => 0,
                    ],
                ]),
                1,
            ]);

        $service = $this->makeService($repo);

        $result = $service->getPotentialMasters($filters, 20, 0);

        $this->assertSame('10000', $result['data'][0]['balance']);
    }

    public function test_masters_include_level_protection_fields_and_status(): void
    {
        Carbon::setTestNow('2026-08-20 12:00:00');
        app()->setLocale('zh_CN');

        $repo = Mockery::mock(CopyTradingRepository::class);
        $repo->shouldReceive('getMasters')
            ->once()
            ->with([], 20, 0, null)
            ->andReturn([
                collect([
                    (object) [
                        'user_code' => 100001,
                        'trade_account_id' => 900001,
                        'trade_account_type' => 0,
                        'total_margin' => '100.000000',
                        'follower_fund' => '50.000000',
                        'follower_count' => 10,
                        'commission_amount' => '5.000000',
                        'commission_rate' => '0.100000',
                        'fee_commission_rate' => '0.050000',
                        'status' => 1,
                        'is_private' => 0,
                        'status_updated_at' => null,
                        'level_protection_started_at' => '2026-08-01 00:00:00',
                        'level_protection_ended_at' => '2026-08-20 23:59:59',
                        'level_pos' => 2,
                        'profit_amount_30d' => '30.000000',
                        'total_margin_30d' => '60.000000',
                        'order_count_30d' => 3,
                        'balance' => '1000.000000',
                        'operator_id' => null,
                        'reason' => '',
                    ],
                ]),
                1,
            ]);

        $copyHistoryRepo = Mockery::mock(CopyHistoryRepository::class);
        $copyHistoryRepo->shouldReceive('getAll')
            ->once()
            ->with([900001], [], Copy::STATUS_FOLLOWING)
            ->andReturn([
                ['login_master' => 900001, 'login_follower' => 800001],
                ['login_master' => 900001, 'login_follower' => 800002],
                ['login_master' => 900001, 'login_follower' => 800003],
            ]);
        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getDirectCustomerUserCodesByTradeAccountIds')
            ->once()
            ->with([800001, 800002, 800003])
            ->andReturn([800001 => 11, 800002 => 12]);
        $repo->shouldReceive('getSettledFeeCommissionByMasterTradeAccountIds')
            ->once()
            ->with([900001])
            ->andReturn([900001 => '3.500000']);

        $service = $this->makeService($repo, [
            'copyHistoryRepo' => $copyHistoryRepo,
            'userRepo' => $userRepo,
        ]);
        $result = $service->getMasters([], 20, 0);

        $this->assertSame('2026-08-01 00:00:00', $result['data'][0]['level_protection_started_at']);
        $this->assertSame('2026-08-20 23:59:59', $result['data'][0]['level_protection_ended_at']);
        $this->assertSame(1, $result['data'][0]['protection_status']);
        $this->assertSame('保护中', $result['data'][0]['protection_status_text']);

        // 3 个跟随者中 800003 被判定为代理归属被剔除，其余 2 人按用户去重计入
        $this->assertSame(2, $result['data'][0]['commission_follower_count']);

        // 手续费分佣比例取当前等级配置，0.05 → 5
        $this->assertSame('5', $result['data'][0]['fee_commission_rate']);

        // 已获得手续费分佣金额为已结算记录汇总，3.500000 → 3.5
        $this->assertSame('3.5', $result['data'][0]['obtained_fee_commission_amount']);
    }

    public function test_update_master_protection_time_rejects_reversed_range(): void
    {
        $repo = Mockery::mock(CopyTradingRepository::class);
        $repo->shouldNotReceive('updateMasterProtectionTimeByTradeAccountIds');
        $service = $this->makeService($repo);

        $this->expectException(BusinessException::class);

        $service->updateMasterProtectionTime(900001, [], '2026-08-20 10:00:00', '2026-08-10 10:00:00');
    }

    public function test_update_master_protection_time_rejects_disallowed_master_status(): void
    {
        $repo = Mockery::mock(CopyTradingRepository::class);
        $repo->shouldReceive('getMasterAccountsByTradeAccountIds')
            ->once()
            ->with([900001])
            ->andReturn(new EloquentCollection([
                (object) [
                    'trade_account_id' => 900001,
                    'status' => MasterAccount::STATUS_REJECT,
                ],
            ]));
        $repo->shouldNotReceive('updateMasterProtectionTimeByTradeAccountIds');
        $service = $this->makeService($repo);

        $this->expectException(BusinessException::class);

        $service->updateMasterProtectionTime(900001, [], '2026-08-10 10:00:00', '2026-08-20 10:00:00');
    }

    public function test_has_commission_filter_applies_following_and_exists_conditions(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->once()
            ->with('h.status', 0)
            ->andReturnSelf();
        $query->shouldReceive('whereExists')
            ->once()
            ->with(Mockery::type('Closure'))
            ->andReturnSelf();

        $method = (new ReflectionClass(CopyTradingRepository::class))
            ->getMethod('applyHasCommissionFilter');
        $method->setAccessible(true);
        $method->invoke(new CopyTradingRepository, $query, 'status', 'login_follower', 1);

        $this->assertTrue(true);
    }

    public function test_has_commission_filter_rejects_invalid_value(): void
    {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('whereRaw')
            ->once()
            ->with('1 = 0')
            ->andReturnSelf();

        $method = (new ReflectionClass(CopyTradingRepository::class))
            ->getMethod('applyHasCommissionFilter');
        $method->setAccessible(true);
        $method->invoke(new CopyTradingRepository, $query, 'status', 'login_follower', 2);

        $this->assertTrue(true);
    }

    public function test_master_protection_time_request_requires_one_target(): void
    {
        $request = new MasterProtectionTimeUpdateRequest;
        $this->assertArrayHasKey('trade_account_id', $request->rules());
        $this->assertArrayHasKey('trade_account_ids', $request->rules());
    }

    public function test_follow_relations_mark_commission_eligibility(): void
    {
        app()->setLocale('zh_CN');

        $repo = Mockery::mock(CopyTradingRepository::class);
        $repo->shouldReceive('getFollowRelations')
            ->once()
            ->with([], 20, 0)
            ->andReturn([
                [
                    (object) [
                        'master_user_code' => 100001,
                        'follower_user_code' => 200001,
                        'master_trade_account_id' => 900001,
                        'follower_trade_account_id' => 800001,
                        'master_trade_account_type' => 0,
                        'follower_trade_account_type' => 0,
                        'invest_amount' => '100.000000',
                        'level_pos' => 2,
                        'copy_start_time' => '2026-08-01 00:00:00',
                        'copy_end_time' => null,
                        'follow_status' => 0,
                    ],
                    (object) [
                        'master_user_code' => 100002,
                        'follower_user_code' => 200002,
                        'master_trade_account_id' => 900002,
                        'follower_trade_account_id' => 800002,
                        'master_trade_account_type' => 0,
                        'follower_trade_account_type' => 0,
                        'invest_amount' => '200.000000',
                        'level_pos' => 1,
                        'copy_start_time' => '2026-08-02 00:00:00',
                        'copy_end_time' => null,
                        'follow_status' => 0,
                    ],
                ],
                2,
            ]);
        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getDirectCustomerUserCodesByTradeAccountIds')
            ->once()
            ->with([800001, 800002])
            ->andReturn([800001 => 11]);
        TS::shouldReceive('getUserEquity')
            ->once()
            ->with([800001, 800002])
            ->andReturn((object) ['data' => []]);

        $service = $this->makeService($repo, [
            'userRepo' => $userRepo,
            'balanceFlowRepo' => Mockery::mock(BalanceFlowRepository::class),
        ]);
        $result = $service->getFollowRelations([], 20, 0);

        // 800001 是直客且跟随中 → 可产生分佣；800002 有代理归属 → 不可产生
        $this->assertSame(1, $result['data'][0]['has_commission']);
        $this->assertSame('是', $result['data'][0]['has_commission_text']);
        $this->assertSame(0, $result['data'][1]['has_commission']);
        $this->assertSame('否', $result['data'][1]['has_commission_text']);
    }

    public function test_follow_relation_commission_eligibility_rules(): void
    {
        $service = (new ReflectionClass(CopyTradingService::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(CopyTradingService::class))->getMethod('resolveHasCommission');
        $method->setAccessible(true);

        $invoke = fn (int $status, int $login, array $direct): int => $method->invoke($service, $status, $login, $direct);

        $this->assertSame(1, $invoke(Copy::STATUS_FOLLOWING, 800001, [800001 => 11]));
        $this->assertSame(0, $invoke(Copy::STATUS_FOLLOWING, 800001, []));
        $this->assertSame(0, $invoke(Copy::STATUS_ENDED, 800001, [800001 => 11]));
        $this->assertSame(0, $invoke(Copy::STATUS_FOLLOWING, 0, [800001 => 11]));
    }

    private function makeService(CopyTradingRepository $repo, array $extraProperties = []): CopyTradingService
    {
        $reflection = new ReflectionClass(CopyTradingService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $properties = array_merge(['copyTradingRepo' => $repo], $extraProperties);
        foreach ($properties as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($service, $value);
        }

        return $service;
    }
}
