<?php

namespace Tests\Unit\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\MasterLevel;
use App\Repositories\Api\CopyTradingRepository;
use App\Services\Api\CopyTradingService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use Scaffold\Framework\Enums\TypeTradeAccount;
use Tests\TestCase;

class CopyTradingServiceOptionsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_master_level_options_include_initial_filter_options(): void
    {
        app()->setLocale('zh_CN');

        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $copyTradingRepo->shouldReceive('getAllMasterLevels')
            ->once()
            ->andReturn(new Collection);

        $options = $this->invokeCopyTradingServiceMethod($copyTradingRepo, 'getMasterLevelOptions');

        $this->assertSame([
            ['key' => '1', 'value' => '是'],
            ['key' => '0', 'value' => '否'],
        ], $options['is_initial_options']);
    }

    public function test_master_level_options_include_has_commission_filter_options(): void
    {
        app()->setLocale('zh_CN');

        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $copyTradingRepo->shouldReceive('getAllMasterLevels')
            ->once()
            ->andReturn(new Collection);

        $options = $this->invokeCopyTradingServiceMethod($copyTradingRepo, 'getMasterLevelOptions');

        $this->assertSame([
            ['key' => '1', 'value' => '是'],
            ['key' => '0', 'value' => '否'],
        ], $options['has_commission_options']);
    }

    public function test_master_level_options_only_include_supported_trade_account_types(): void
    {
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $copyTradingRepo->shouldReceive('getAllMasterLevels')
            ->once()
            ->andReturn(new Collection);

        $options = $this->invokeCopyTradingServiceMethod($copyTradingRepo, 'getMasterLevelOptions');

        $this->assertSame([
            [
                'key' => (string) TypeTradeAccount::UTA->value,
                'value' => TypeTradeAccount::UTA->label(),
            ],
            [
                'key' => (string) TypeTradeAccount::BonusFund->value,
                'value' => TypeTradeAccount::BonusFund->label(),
            ],
        ], $options['trade_account_types']);
    }

    public function test_master_levels_derive_default_level_from_level_pos(): void
    {
        app()->setLocale('zh_CN');

        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $copyTradingRepo->shouldReceive('getMasterLevels')
            ->once()
            ->with(
                Mockery::on(static fn (array $filters): bool => $filters['is_initial'] === '1'),
                20,
                0,
                null
            )
            ->andReturn([
                new Collection([
                    (object) [
                        'id' => 1,
                        'level_pos' => 1,
                        'is_initial' => MasterLevel::IS_INITIAL_NO,
                        'name' => ['zh_CN' => '白银高手', 'en_US' => 'Silver Master'],
                        'balance_threshold' => '500.000000',
                        'order_count_30d_threshold' => 5,
                        'profit_rate_30d_threshold' => '0.100000',
                        'follower_fund_threshold' => '200.000000',
                        'follower_count_threshold' => 1,
                        'commission_rate' => '0.100000',
                        'fee_commission_rate' => '0.050000',
                        'max_investable_amount' => '3000.000000',
                        'status' => MasterLevel::STATUS_ENABLED,
                        'created_at' => '2026-08-11 10:00:00',
                        'updated_at' => '2026-08-11 11:00:00',
                    ],
                    (object) [
                        'id' => 3,
                        'level_pos' => 3,
                        'is_initial' => MasterLevel::IS_INITIAL_YES,
                        'name' => ['zh_CN' => '黄金高手', 'en_US' => 'Gold Master'],
                        'balance_threshold' => '1000.000000',
                        'order_count_30d_threshold' => 10,
                        'profit_rate_30d_threshold' => '0.150000',
                        'follower_fund_threshold' => '500.000000',
                        'follower_count_threshold' => 2,
                        'commission_rate' => '0.200000',
                        'fee_commission_rate' => '0.100000',
                        'max_investable_amount' => '5000.000000',
                        'status' => MasterLevel::STATUS_ENABLED,
                        'created_at' => '2026-08-11 10:00:00',
                        'updated_at' => '2026-08-11 11:00:00',
                    ],
                ]),
                2,
                4,
            ]);

        $result = $this->invokeCopyTradingServiceMethod(
            $copyTradingRepo,
            'getMasterLevels',
            ['is_initial' => '1'],
            20,
            0
        );

        // level_pos=1：默认等级固定为是；初始默认等级由 is_initial 决定（此处为否）
        $this->assertSame(0, $result['data'][0]['is_initial']);
        $this->assertSame('否', $result['data'][0]['is_initial_text']);
        $this->assertSame(1, $result['data'][0]['is_default_level']);
        $this->assertSame('是', $result['data'][0]['is_default_level_text']);

        // level_pos=3 + is_initial=1：初始默认等级为是；默认等级（仅 level_pos==1）为否
        $this->assertSame(1, $result['data'][1]['is_initial']);
        $this->assertSame('是', $result['data'][1]['is_initial_text']);
        $this->assertSame(0, $result['data'][1]['is_default_level']);
        $this->assertSame('否', $result['data'][1]['is_default_level_text']);

        // 手续费分佣比例以小数存储，返回时换算为百分比数值文案
        $this->assertSame('5', $result['data'][0]['fee_commission_rate']);
        $this->assertSame('10', $result['data'][1]['fee_commission_rate']);
    }

    public function test_normalize_master_level_payload_converts_fee_commission_rate_to_decimal(): void
    {
        $normalized = $this->invokeCopyTradingServiceMethod(
            Mockery::mock(CopyTradingRepository::class),
            'normalizeMasterLevelPayload',
            [
                'locale_configs' => [
                    ['lang' => 'zh_CN', 'name' => '白银高手'],
                    ['lang' => 'en_US', 'name' => 'Silver Master'],
                ],
                'status' => 1,
                'balance_threshold' => '500',
                'order_count_30d_threshold' => 5,
                'profit_rate_30d_threshold' => '10',
                'follower_fund_threshold' => '200',
                'follower_count_threshold' => 1,
                'profit_share_rate' => '10',
                'fee_commission_rate' => '50',
                'max_invest_amount' => '3000',
                'is_initial' => 0,
            ]
        );

        $this->assertSame('0.100000', $normalized['commission_rate']);
        $this->assertSame('0.500000', $normalized['fee_commission_rate']);
    }

    public function test_duplicate_initial_level_error_message_contains_existing_level(): void
    {
        app()->setLocale('zh_CN');

        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $copyTradingRepo->shouldReceive('findInitialMasterLevel')
            ->once()
            ->with(7)
            ->andReturn(new MasterLevel(['level_pos' => 3]));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('已存在初始默认等级：等级Level3。');

        $this->invokeCopyTradingServiceMethod($copyTradingRepo, 'assertSingleInitialMasterLevel', 7);
    }

    public function test_duplicate_initial_level_error_message_contains_existing_level_for_traditional_chinese(): void
    {
        app()->setLocale('zh_TW');

        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $copyTradingRepo->shouldReceive('findInitialMasterLevel')
            ->once()
            ->with(null)
            ->andReturn(new MasterLevel(['level_pos' => 5]));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('已存在初始預設等級：等級Level5。');

        $this->invokeCopyTradingServiceMethod($copyTradingRepo, 'assertSingleInitialMasterLevel', null);
    }

    private function invokeCopyTradingServiceMethod(
        CopyTradingRepository $copyTradingRepo,
        string $methodName,
        mixed ...$args
    ): mixed {
        $reflection = new ReflectionClass(CopyTradingService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $copyTradingRepoProperty = $reflection->getProperty('copyTradingRepo');
        $copyTradingRepoProperty->setAccessible(true);
        $copyTradingRepoProperty->setValue($service, $copyTradingRepo);

        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($service, ...$args);
    }
}
