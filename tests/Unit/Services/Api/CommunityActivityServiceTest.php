<?php

namespace Tests\Unit\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\CommunityActivity;
use App\Repositories\Api\CommunityActivityApplicationRepository;
use App\Repositories\Api\CommunityActivityRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\CommunityActivityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Scaffold\Framework\Utils\S3;
use Tests\TestCase;

class CommunityActivityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en_US');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();
        Auth::clearResolvedInstances();

        parent::tearDown();
    }

    private function makeService(
        CommunityActivityRepository $activityRepo,
        CommunityActivityApplicationRepository $applicationRepo,
        ?UserOperationLogRepository $operationLogRepo = null
    ): CommunityActivityService {
        $operationLogRepo ??= Mockery::mock(UserOperationLogRepository::class);
        $operationLogRepo->shouldReceive('getLatestOperateInfo')->andReturn([])->byDefault();
        $operationLogRepo->shouldReceive('addRecord')->andReturn(true)->byDefault();

        return new CommunityActivityService($activityRepo, $applicationRepo, $operationLogRepo);
    }

    private function loginAs(int $adminId): void
    {
        Auth::shouldReceive('id')->andReturn($adminId);
    }

    public function test_create_stores_locale_configs_as_localized_columns_and_returns_edit_config(): void
    {
        Carbon::setTestNow('2026-09-01 00:00:00');

        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();

        $activityRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                $this->assertSame([
                    'en_US' => 'London Web3 Trading Meetup',
                    'zh_CN' => '伦敦 Web3 交易者见面会',
                ], $data['name']);
                $this->assertSame([
                    'en_US' => 'community_activity/20260820/cover-en.jpg',
                    'zh_CN' => 'community_activity/20260820/cover-zh.jpg',
                ], $data['cover_image']);
                $this->assertSame([
                    'en_US' => 'community_activity/20260820/background-en.jpg',
                    'zh_CN' => 'community_activity/20260820/background-zh.jpg',
                ], $data['bg_image']);
                $this->assertSame(CommunityActivity::STATUS_PENDING, $data['status']);
                $this->assertSame('2026-09-12 10:00:00', $data['apply_start_at']);
                $this->assertSame('2026-09-12 13:00:00', $data['apply_end_at']);
                $this->assertArrayNotHasKey('locale_configs', $data);

                return true;
            }))
            ->andReturn($this->activity($localeConfigs));

        $result = $service->create([
            'type' => 1,
            'city' => 'london',
            'start_at' => '2026-09-12 14:00:00',
            'end_at' => '2026-09-12 18:00:00',
            'apply_start_at' => '2026-09-12 10:00:00',
            'apply_end_at' => '2026-09-12 13:00:00',
            'audience' => ['web3_users', 'local_trading_community'],
            'locale_configs' => $localeConfigs,
        ]);

        $expectedLocaleConfigs = $this->expectedReturnedLocaleConfigs();

        $this->assertSame($expectedLocaleConfigs, $result['locale_configs']);
        $this->assertSame($this->expectedFileUrl('community_activity/20260820/cover-en.jpg'), $result['cover_image']['en_US']);
        $this->assertSame('https://static.example.com/community_activity/20260820/cover-zh.jpg', $result['cover_image']['zh_CN']);
        $this->assertSame($this->expectedFileUrl('community_activity/20260820/background-en.jpg'), $result['bg_image']['en_US']);
        $this->assertSame('https://static.example.com/community_activity/20260820/background-zh.jpg', $result['bg_image']['zh_CN']);
        $this->assertSame('London Web3 Trading Meetup', $result['name_text']);
        $this->assertSame('2026-09-12 10:00:00', $result['apply_start_at']);
        $this->assertSame('2026-09-12 13:00:00', $result['apply_end_at']);
    }

    public function test_index_formats_relative_activity_images_as_file_urls_and_keeps_http_urls(): void
    {
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();

        $activityRepo->shouldReceive('getList')
            ->once()
            ->with('', null, null, null, 20, 0, Mockery::any())
            ->andReturn([collect([$this->activity($localeConfigs)]), 1]);

        $result = $service->index([], 20, 0);
        $activity = $result['data'][0];

        $this->assertSame(1, $result['total_count']);
        $this->assertSame($this->expectedFileUrl('community_activity/20260820/cover-en.jpg'), $activity['cover_image']['en_US']);
        $this->assertSame('https://static.example.com/community_activity/20260820/cover-zh.jpg', $activity['cover_image']['zh_CN']);
        $this->assertSame($this->expectedFileUrl('community_activity/20260820/background-en.jpg'), $activity['bg_image']['en_US']);
        $this->assertSame('https://static.example.com/community_activity/20260820/background-zh.jpg', $activity['bg_image']['zh_CN']);
        $this->assertSame($this->expectedReturnedLocaleConfigs(), $activity['locale_configs']);
    }

    public function test_index_returns_effective_status_without_waiting_for_status_refresh(): void
    {
        Carbon::setTestNow('2026-09-12 15:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $online = $this->activity($this->localeConfigs(), CommunityActivity::STATUS_PENDING);
        $voided = $this->activity($this->localeConfigs(), CommunityActivity::STATUS_VOIDED);

        $activityRepo->shouldReceive('getList')
            ->once()
            ->with('', null, null, null, 20, 0, Mockery::any())
            ->andReturn([collect([$online, $voided]), 2]);

        $result = $service->index([], 20, 0);

        $this->assertSame(CommunityActivity::STATUS_ONLINE, $result['data'][0]['status']);
        $this->assertSame(CommunityActivity::STATUS_VOIDED, $result['data'][1]['status']);
    }

    public function test_void_activity_returns_true_for_non_voided_activity(): void
    {
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();
        $activity = $this->activity($localeConfigs, CommunityActivity::STATUS_PENDING);
        $voidedActivity = $this->activity($localeConfigs, CommunityActivity::STATUS_VOIDED);

        $activityRepo->shouldReceive('findById')
            ->once()
            ->with(101)
            ->andReturn($activity);
        $activityRepo->shouldReceive('updateById')
            ->once()
            ->with(101, Mockery::on(function (array $data): bool {
                $this->assertSame(CommunityActivity::STATUS_VOIDED, $data['status']);
                $this->assertArrayNotHasKey('operator_id', $data);

                return true;
            }), $activity)
            ->andReturn($voidedActivity);

        $this->loginAs(7);
        $result = $service->voidActivity(101);

        $this->assertTrue($result);
    }

    public function test_void_activity_rejects_already_voided_activity(): void
    {
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $activity = $this->activity($this->localeConfigs(), CommunityActivity::STATUS_VOIDED);

        $activityRepo->shouldReceive('findById')
            ->once()
            ->with(101)
            ->andReturn($activity);
        $activityRepo->shouldNotReceive('updateById');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('community_activity.message.voided_no_action'));

        $service->voidActivity(101);
    }

    public function test_create_and_update_derive_activity_status_from_current_time(): void
    {
        Carbon::setTestNow('2026-09-12 15:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();

        $activityRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => $data['status'] === CommunityActivity::STATUS_ONLINE))
            ->andReturn($this->activity($localeConfigs, CommunityActivity::STATUS_ONLINE));
        $activityRepo->shouldReceive('findById')
            ->once()
            ->with(101)
            ->andReturn($this->activity($localeConfigs, CommunityActivity::STATUS_PENDING));
        $activityRepo->shouldReceive('updateById')
            ->once()
            ->with(101, Mockery::on(fn (array $data): bool => $data['status'] === CommunityActivity::STATUS_ONLINE), Mockery::any())
            ->andReturn($this->activity($localeConfigs, CommunityActivity::STATUS_ONLINE));

        $payload = $this->payload();
        $this->assertSame(CommunityActivity::STATUS_ONLINE, $service->create($payload)['status']);
        $this->assertSame(CommunityActivity::STATUS_ONLINE, $service->update(101, $payload)['status']);
    }

    public function test_update_keeps_voided_activity_as_final_status(): void
    {
        Carbon::setTestNow('2026-09-12 10:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();
        $activity = $this->activity($localeConfigs, CommunityActivity::STATUS_VOIDED);

        $activityRepo->shouldReceive('findById')
            ->once()
            ->with(101)
            ->andReturn($activity);
        $activityRepo->shouldReceive('updateById')
            ->once()
            ->with(101, Mockery::on(fn (array $data): bool => $data['status'] === CommunityActivity::STATUS_VOIDED), $activity)
            ->andReturn($activity);

        $result = $service->update(101, $this->payload());

        $this->assertSame(CommunityActivity::STATUS_VOIDED, $result['status']);
    }

    public function test_expired_activity_is_derived_as_voided(): void
    {
        Carbon::setTestNow('2026-09-12 19:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);

        $activityRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => $data['status'] === CommunityActivity::STATUS_VOIDED))
            ->andReturn($this->activity($this->localeConfigs(), CommunityActivity::STATUS_VOIDED));

        $this->assertSame(CommunityActivity::STATUS_VOIDED, $service->create($this->payload())['status']);
    }

    public function test_publish_derives_manual_publish_type_and_operator_from_operation_log(): void
    {
        Carbon::setTestNow('2026-09-12 10:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $operationLogRepo = Mockery::mock(UserOperationLogRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo, $operationLogRepo);
        $localeConfigs = $this->localeConfigs();
        $pending = $this->activity($localeConfigs, CommunityActivity::STATUS_PENDING);
        $published = $this->activity($localeConfigs, CommunityActivity::STATUS_ONLINE);

        $activityRepo->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(101)
            ->andReturn($pending);
        $activityRepo->shouldReceive('updateById')
            ->once()
            ->with(101, Mockery::on(function (array $data): bool {
                $this->assertSame(CommunityActivity::STATUS_ONLINE, $data['status']);
                $this->assertArrayNotHasKey('publish_type', $data);
                $this->assertArrayNotHasKey('operator_id', $data);

                return true;
            }), $pending)
            ->andReturn($published);
        $operationLogRepo->shouldReceive('addRecord')
            ->once()
            ->with(101, Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(true);
        $operationLogRepo->shouldReceive('getLatestOperateInfo')
            ->once()
            ->andReturn([101 => ['operator_id' => 9, 'operator_name' => 'admin9']]);

        $this->loginAs(9);
        $result = $service->publish(101);

        $this->assertSame(CommunityActivity::STATUS_ONLINE, $result['status']);
        $this->assertSame(CommunityActivity::PUBLISH_TYPE_MANUAL, $result['publish_type']);
        $this->assertSame('Manual', $result['publish_type_text']);
        $this->assertSame(9, $result['operator_id']);
        $this->assertSame('admin9', $result['operator_name']);
    }

    public function test_publish_rejects_online_activity(): void
    {
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $activity = $this->activity($this->localeConfigs(), CommunityActivity::STATUS_ONLINE);

        $activityRepo->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(101)
            ->andReturn($activity);
        $activityRepo->shouldNotReceive('updateById');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('community_activity.message.only_pending_can_publish'));

        $service->publish(101);
    }

    public function test_publish_rejects_voided_activity(): void
    {
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $activity = $this->activity($this->localeConfigs(), CommunityActivity::STATUS_VOIDED);

        $activityRepo->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(101)
            ->andReturn($activity);
        $activityRepo->shouldNotReceive('updateById');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('community_activity.message.voided_no_action'));

        $service->publish(101);
    }

    public function test_publish_rejects_expired_activity(): void
    {
        Carbon::setTestNow('2026-09-12 19:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $activity = $this->activity($this->localeConfigs(), CommunityActivity::STATUS_PENDING);

        $activityRepo->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(101)
            ->andReturn($activity);
        $activityRepo->shouldNotReceive('updateById');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('community_activity.message.activity_ended'));

        $service->publish(101);
    }

    public function test_publish_returns_null_for_missing_activity(): void
    {
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);

        $activityRepo->shouldReceive('findByIdForUpdate')
            ->once()
            ->with(101)
            ->andReturnNull();

        $this->assertNull($service->publish(101));
    }

    public function test_update_keeps_manually_online_activity_online(): void
    {
        Carbon::setTestNow('2026-09-12 10:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();
        // 上线状态且未到开始时间，纯逻辑即为人工提前发布
        $manualOnline = $this->activity($localeConfigs, CommunityActivity::STATUS_ONLINE);

        $activityRepo->shouldReceive('findById')
            ->once()
            ->with(101)
            ->andReturn($manualOnline);
        $activityRepo->shouldReceive('updateById')
            ->once()
            ->with(101, Mockery::on(function (array $data): bool {
                $this->assertSame(CommunityActivity::STATUS_ONLINE, $data['status']);
                $this->assertArrayNotHasKey('operator_id', $data);

                return true;
            }), $manualOnline)
            ->andReturn($manualOnline);

        $this->loginAs(3);
        $result = $service->update(101, $this->payload());

        $this->assertSame(CommunityActivity::STATUS_ONLINE, $result['status']);
        $this->assertSame(CommunityActivity::PUBLISH_TYPE_MANUAL, $result['publish_type']);
    }

    public function test_create_immediately_online_activity_has_no_stored_publish_type(): void
    {
        Carbon::setTestNow('2026-09-12 15:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();

        $activityRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                $this->assertSame(CommunityActivity::STATUS_ONLINE, $data['status']);
                $this->assertArrayNotHasKey('publish_type', $data);
                $this->assertArrayNotHasKey('operator_id', $data);

                return true;
            }))
            ->andReturn($this->activity($localeConfigs, CommunityActivity::STATUS_ONLINE));

        $this->loginAs(5);
        $result = $service->create($this->payload());

        // 活动时间窗内上线，按逻辑推导为定时发布
        $this->assertSame(CommunityActivity::PUBLISH_TYPE_SCHEDULED, $result['publish_type']);
    }

    public function test_create_pending_activity_derives_scheduled_publish_type(): void
    {
        Carbon::setTestNow('2026-09-01 00:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();

        $activityRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                $this->assertSame(CommunityActivity::STATUS_PENDING, $data['status']);
                $this->assertArrayNotHasKey('publish_type', $data);

                return true;
            }))
            ->andReturn($this->activity($localeConfigs, CommunityActivity::STATUS_PENDING));

        $this->loginAs(5);
        $result = $service->create($this->payload());

        $this->assertSame(CommunityActivity::PUBLISH_TYPE_SCHEDULED, $result['publish_type']);
    }

    public function test_update_putting_pending_activity_into_window_derives_status_by_time(): void
    {
        Carbon::setTestNow('2026-09-12 15:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();
        $pending = $this->activity($localeConfigs, CommunityActivity::STATUS_PENDING);

        $activityRepo->shouldReceive('findById')
            ->once()
            ->with(101)
            ->andReturn($pending);
        $activityRepo->shouldReceive('updateById')
            ->once()
            ->with(101, Mockery::on(function (array $data): bool {
                $this->assertSame(CommunityActivity::STATUS_ONLINE, $data['status']);
                $this->assertArrayNotHasKey('publish_type', $data);

                return true;
            }), $pending)
            ->andReturn($pending);

        $this->loginAs(5);
        $result = $service->update(101, $this->payload());

        $this->assertSame(CommunityActivity::STATUS_ONLINE, $result['status']);
        $this->assertSame(CommunityActivity::PUBLISH_TYPE_SCHEDULED, $result['publish_type']);
    }

    public function test_update_scheduled_online_activity_still_recomputes_from_time(): void
    {
        Carbon::setTestNow('2026-09-12 10:00:00');
        $activityRepo = Mockery::mock(CommunityActivityRepository::class);
        $applicationRepo = Mockery::mock(CommunityActivityApplicationRepository::class);
        $service = $this->makeService($activityRepo, $applicationRepo);
        $localeConfigs = $this->localeConfigs();
        $scheduledOnline = $this->activity($localeConfigs, CommunityActivity::STATUS_ONLINE);
        // 原活动时间窗进行中（09:00 起），编辑后把开始时间改到未来
        $scheduledOnline->start_at = '2026-09-12 09:00:00';

        $activityRepo->shouldReceive('findById')
            ->once()
            ->with(101)
            ->andReturn($scheduledOnline);
        $activityRepo->shouldReceive('updateById')
            ->once()
            ->with(101, Mockery::on(fn (array $data): bool => $data['status'] === CommunityActivity::STATUS_PENDING), $scheduledOnline)
            ->andReturnUsing(function () use ($scheduledOnline): CommunityActivity {
                // 模拟落库后的新状态与新活动时间
                $updated = clone $scheduledOnline;
                $updated->status = CommunityActivity::STATUS_PENDING;
                $updated->start_at = '2026-09-12 14:00:00';

                return $updated;
            });

        $this->loginAs(5);
        $result = $service->update(101, $this->payload());

        $this->assertSame(CommunityActivity::STATUS_PENDING, $result['status']);
    }

    private function activity(array $localeConfigs, int $status = CommunityActivity::STATUS_PENDING): CommunityActivity
    {
        $localized = [];
        foreach (['name', 'summary', 'cover_image', 'bg_image', 'introduction', 'content'] as $field) {
            $localized[$field] = [];
            foreach ($localeConfigs as $config) {
                $localized[$field][$config['lang']] = $config[$field];
            }
        }

        $activity = new CommunityActivity;
        $activity->forceFill([
            'id' => 101,
            'type' => 1,
            'city' => 'london',
            'start_at' => '2026-09-12 14:00:00',
            'end_at' => '2026-09-12 18:00:00',
            'apply_start_at' => '2026-09-12 10:00:00',
            'apply_end_at' => '2026-09-12 13:00:00',
            'status' => $status,
            'audience' => ['web3_users', 'local_trading_community'],
            ...$localized,
            'created_at' => '2026-08-20 10:30:00',
            'updated_at' => '2026-08-20 10:30:00',
        ]);

        return $activity;
    }

    private function payload(): array
    {
        return [
            'type' => 1,
            'city' => 'london',
            'start_at' => '2026-09-12 14:00:00',
            'end_at' => '2026-09-12 18:00:00',
            'apply_start_at' => '2026-09-12 10:00:00',
            'apply_end_at' => '2026-09-12 13:00:00',
            'audience' => ['web3_users', 'local_trading_community'],
            'locale_configs' => $this->localeConfigs(),
        ];
    }

    private function localeConfigs(): array
    {
        return [
            [
                'lang' => 'en_US',
                'name' => 'London Web3 Trading Meetup',
                'summary' => 'Meet local traders and learn practical Web3 trading strategies.',
                'cover_image' => 'community_activity/20260820/cover-en.jpg',
                'bg_image' => 'community_activity/20260820/background-en.jpg',
                'introduction' => 'An in-person meetup for traders who want to exchange market insights.',
                'content' => '<p>Join our analysts and local community leaders for market sharing.</p>',
            ],
            [
                'lang' => 'zh_CN',
                'name' => '伦敦 Web3 交易者见面会',
                'summary' => '与本地交易者交流并学习实用的 Web3 交易策略。',
                'cover_image' => 'https://static.example.com/community_activity/20260820/cover-zh.jpg',
                'bg_image' => 'https://static.example.com/community_activity/20260820/background-zh.jpg',
                'introduction' => '面向希望交流市场观点的交易者举办的线下见面会。',
                'content' => '<p>与分析师及本地社区领袖共同参与市场分享。</p>',
            ],
        ];
    }

    private function expectedReturnedLocaleConfigs(): array
    {
        $localeConfigs = $this->localeConfigs();
        $localeConfigs[0]['cover_image'] = $this->expectedFileUrl('community_activity/20260820/cover-en.jpg');
        $localeConfigs[0]['bg_image'] = $this->expectedFileUrl('community_activity/20260820/background-en.jpg');

        return $localeConfigs;
    }

    private function expectedFileUrl(string $path): string
    {
        return (string) app(S3::class)->fileUrl($path);
    }
}
