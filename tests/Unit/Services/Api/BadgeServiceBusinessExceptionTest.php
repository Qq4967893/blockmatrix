<?php

namespace Tests\Unit\Services\Api;

use App\Exceptions\BusinessException;
use App\Repositories\Api\BadgeRepository;
use App\Repositories\Api\CopyTradingRepository;
use App\Services\Api\BadgeService;
use Mockery;
use Tests\TestCase;

class BadgeServiceBusinessExceptionTest extends TestCase
{
    public function test_create_throws_business_exception_for_duplicate_language(): void
    {
        $service = $this->makeService();
        $payload = $this->validPayload();
        $payload['locale_configs'][] = $payload['locale_configs'][0];

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('badge.message.duplicate_lang'));

        $service->create($payload);
    }

    public function test_create_throws_business_exception_when_name_is_empty(): void
    {
        $service = $this->makeService();
        $payload = $this->validPayload();
        $payload['locale_configs'][0]['name'] = ' ';

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('badge.message.name_required'));

        $service->create($payload);
    }

    public function test_create_throws_business_exception_for_duplicate_name(): void
    {
        $repo = Mockery::mock(BadgeRepository::class);
        $repo->shouldReceive('existsByName')->once()->with(['Badge'], null)->andReturnTrue();
        $service = $this->makeService($repo);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('badge.message.duplicate_name'));

        $service->create($this->validPayload());
    }

    public function test_create_throws_business_exception_for_duplicate_short_name(): void
    {
        $repo = Mockery::mock(BadgeRepository::class);
        $repo->shouldReceive('existsByName')->once()->with(['Badge'], null)->andReturnFalse();
        $repo->shouldReceive('existsByShortName')->once()->with('badge', null)->andReturnTrue();
        $service = $this->makeService($repo);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(trans('badge.message.duplicate_short_name'));

        $service->create($this->validPayload());
    }

    private function makeService(?BadgeRepository $repo = null): BadgeService
    {
        return new BadgeService(
            $repo ?? Mockery::mock(BadgeRepository::class),
            Mockery::mock(CopyTradingRepository::class)
        );
    }

    private function validPayload(): array
    {
        return [
            'icon' => 'badges/default.png',
            'short_name' => 'badge',
            'event_key' => 'registered',
            'event_params' => [],
            'locale_configs' => [
                [
                    'lang' => 'en_US',
                    'name' => 'Badge',
                    'description' => '',
                ],
            ],
        ];
    }
}
