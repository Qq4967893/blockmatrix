<?php

namespace Tests\Unit\Services\Api;

use App\Repositories\Api\SourceLeverageRepository;
use App\Repositories\Api\SourceRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\SourceService;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SourceServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new class extends Container {
            public function getLocale(): string
            {
                return 'zh_CN';
            }
        };
        $container->instance('config', new ConfigRepository([
            'app.locale' => 'zh_CN',
            'app.fallback_locale' => 'en_US',
        ]));
        $container->instance('translator', new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'zh_CN'));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_get_all_sources_returns_source_codes(): void
    {
        $sourceRepo = Mockery::mock(SourceRepository::class);
        $sourceRepo->shouldReceive('getAllSources')->once()->andReturn(['BTCUSD', 'EURUSD']);

        $service = new SourceService(
            $sourceRepo,
            Mockery::mock(SourceLeverageRepository::class),
            Mockery::mock(UserOperationLogRepository::class),
        );

        self::assertSame(['BTCUSD', 'EURUSD'], $service->getAllSources());
    }
}
