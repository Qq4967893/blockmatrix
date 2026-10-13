<?php

namespace Tests\Unit\Services\Blockmatrix;

use App\Repositories\Blockmatrix\MenuEndpointRepository;
use App\Services\Blockmatrix\MenuEndpointService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class MenuEndpointServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_get_api_path_combines_menu_endpoints(): void
    {
        $repository = Mockery::mock(MenuEndpointRepository::class);
        $repository->shouldReceive('getByMenuId')
            ->once()
            ->with(10)
            ->andReturn([
                ['api_path' => 'api/menus'],
                ['api_path' => 'api/menus/POST'],
            ]);

        $service = new MenuEndpointService($repository);

        self::assertSame(
            ['api_path' => 'api/menus,api/menus/POST'],
            $service->getApiPath(10)
        );
    }

    public function test_get_api_path_returns_empty_string_when_menu_has_no_endpoints(): void
    {
        $repository = Mockery::mock(MenuEndpointRepository::class);
        $repository->shouldReceive('getByMenuId')
            ->once()
            ->with(10)
            ->andReturn([]);

        $service = new MenuEndpointService($repository);

        self::assertSame(['api_path' => ''], $service->getApiPath(10));
    }

    public function test_parse_api_paths_trims_filters_and_deduplicates_paths(): void
    {
        $service = new MenuEndpointService(Mockery::mock(MenuEndpointRepository::class));

        self::assertSame(
            ['api/menus', 'api/menus/POST'],
            $service->parseApiPaths(' api/menus, ,api/menus/POST,api/menus ')
        );
    }

    public function test_parse_api_paths_treats_null_as_an_empty_list(): void
    {
        $service = new MenuEndpointService(Mockery::mock(MenuEndpointRepository::class));

        self::assertSame([], $service->parseApiPaths(null));
    }
}
