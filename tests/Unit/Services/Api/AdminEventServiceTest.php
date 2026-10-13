<?php

namespace Tests\Unit\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\AdminEvent;
use App\Repositories\Api\AdminEventRepository;
use App\Services\Api\AdminEventService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Scaffold\Framework\Enums\TypeAdminEvent;
use Tests\TestCase;

class AdminEventServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_create_open_bonus_fund_writes_pending_event_with_amount(): void
    {
        $repo = Mockery::mock(AdminEventRepository::class);
        $service = new AdminEventService($repo);
        $model = new AdminEvent;
        $model->id = 10;

        $repo->shouldReceive('existsByEventKeyAndUserCode')
            ->once()
            ->with(TypeAdminEvent::OpenBonusFund->value, 587239582)
            ->andReturnFalse();
        $repo->shouldReceive('create')
            ->once()
            ->with([
                'event_key' => TypeAdminEvent::OpenBonusFund->value,
                'event_params' => [
                    'amount' => 10000.0,
                    'user_code' => 587239582,
                ],
                'operator_id' => 1,
                'status' => AdminEvent::STATUS_PENDING,
                'executed_at' => null,
            ])
            ->andReturn($model);

        $id = $service->createOpenBonusFund(587239582, 10000.0, 1);

        $this->assertSame(10, $id);
    }

    public function test_create_allows_success_status_and_executed_at(): void
    {
        $repo = Mockery::mock(AdminEventRepository::class);
        $service = new AdminEventService($repo);
        $model = new AdminEvent;
        $model->id = 11;

        $repo->shouldReceive('existsByEventKeyAndUserCode')
            ->once()
            ->with(TypeAdminEvent::OpenBonusFund->value, 587239582)
            ->andReturnFalse();
        $repo->shouldReceive('create')
            ->once()
            ->with([
                'event_key' => TypeAdminEvent::OpenBonusFund->value,
                'event_params' => [
                    'amount' => 10000,
                    'user_code' => 587239582,
                ],
                'operator_id' => 1,
                'status' => AdminEvent::STATUS_SUCCESS,
                'executed_at' => '2026-08-17 08:21:24',
            ])
            ->andReturn($model);

        $id = $service->create([
            'event_key' => TypeAdminEvent::OpenBonusFund,
            'event_params' => [
                'amount' => 10000,
                'user_code' => 587239582,
            ],
            'status' => AdminEvent::STATUS_SUCCESS,
            'executed_at' => '2026-08-17 08:21:24',
        ], 1);

        $this->assertSame(11, $id);
    }

    public function test_create_rejects_duplicate_user_event(): void
    {
        $repo = Mockery::mock(AdminEventRepository::class);
        $service = new AdminEventService($repo);

        $repo->shouldReceive('existsByEventKeyAndUserCode')
            ->once()
            ->with(TypeAdminEvent::OpenBonusFund->value, 587239582)
            ->andReturnTrue();
        $repo->shouldNotReceive('create');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('public.message.not_repeat'));

        $service->createOpenBonusFund(587239582, 10000.0, 1);
    }

    public function test_exists_open_bonus_fund_returns_true_when_event_exists(): void
    {
        $repo = Mockery::mock(AdminEventRepository::class);
        $service = new AdminEventService($repo);

        $repo->shouldReceive('existsByEventKeyAndUserCode')
            ->once()
            ->with(TypeAdminEvent::OpenBonusFund->value, 587239582)
            ->andReturnTrue();

        $this->assertTrue($service->existsOpenBonusFundByUserCode(587239582));
    }

    public function test_exists_open_bonus_fund_returns_false_when_no_event(): void
    {
        $repo = Mockery::mock(AdminEventRepository::class);
        $service = new AdminEventService($repo);

        $repo->shouldReceive('existsByEventKeyAndUserCode')
            ->once()
            ->with(TypeAdminEvent::OpenBonusFund->value, 587239582)
            ->andReturnFalse();

        $this->assertFalse($service->existsOpenBonusFundByUserCode(587239582));
    }
}
