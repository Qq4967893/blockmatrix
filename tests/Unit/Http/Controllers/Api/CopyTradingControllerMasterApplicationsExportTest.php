<?php

namespace Tests\Unit\Http\Controllers\Api;

use App\Exports\ArrayExport;
use App\Http\Controllers\Api\CopyTradingController;
use App\Services\Api\CopyTradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class CopyTradingControllerMasterApplicationsExportTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_export_master_applications_forwards_filters_and_maps_columns(): void
    {
        Carbon::setTestNow('2026-08-20 12:34:56');
        $this->app->setLocale('zh_CN');
        Excel::fake();

        $filters = [
            'user_code' => '100001',
            'status' => '2',
            'operator_name' => 'admin',
            'applied_at_start' => '2026-08-01',
            'applied_at_end' => '2026-08-20 23:59:59',
        ];
        $item = [
            'trade_platform' => 'MT5',
            'project' => '合约',
            'display_name' => 'Account A',
            'project_type' => '公开高手',
            'profit_share_rate' => '0.2',
            'initial_funding' => '10000',
            'operator_name' => 'admin',
            'applied_at' => '2026-08-20 10:00:00',
            'status_text' => '通过',
        ];

        $service = Mockery::mock(CopyTradingService::class);
        $service->shouldReceive('getMasterApplications')
            ->once()
            ->with($filters, 1, 0)
            ->ordered()
            ->andReturn(['data' => [$item], 'total_count' => 1]);
        $service->shouldReceive('getMasterApplications')
            ->once()
            ->with($filters, 1, 0)
            ->ordered()
            ->andReturn(['data' => [$item], 'total_count' => 1]);

        $controller = new CopyTradingController($service);
        $request = Request::create('/api/copytrading/master_applications/export', 'GET', $filters);

        $controller->exportMasterApplications($request);

        Excel::assertDownloaded(
            'copytrading-master-applications-20260820123456.xlsx',
            function (ArrayExport $export): bool {
                $this->assertSame(
                    ['交易平台', '交易类型', '账户信息', '高手类型', '分润比例', '带单规模', '操作人', '申请时间', '审核状态'],
                    $export->headings()
                );
                $this->assertSame([[
                    'MT5',
                    '合约',
                    'Account A',
                    '公开高手',
                    '0.2',
                    '10000',
                    'admin',
                    '2026-08-20 10:00:00',
                    '通过',
                ]], $export->array());

                return true;
            }
        );
    }

    public function test_export_master_applications_respects_export_ext_csv(): void
    {
        $this->app->setLocale('zh_CN');

        $filters = [
            'user_code' => '100001',
            'status' => '2',
            'operator_name' => 'admin',
            'applied_at_start' => '2026-08-01',
            'applied_at_end' => '2026-08-20 23:59:59',
        ];
        $item = [
            'trade_platform' => 'MT5',
            'project' => '合约',
            'display_name' => 'Account A',
            'project_type' => '公开高手',
            'profit_share_rate' => '0.2',
            'initial_funding' => '10000',
            'operator_name' => 'admin',
            'applied_at' => '2026-08-20 10:00:00',
            'status_text' => '通过',
        ];

        $service = Mockery::mock(CopyTradingService::class);
        $service->shouldReceive('getMasterApplications')
            ->twice()
            ->with($filters, 1, 0)
            ->andReturn(['data' => [$item], 'total_count' => 1]);

        $controller = new CopyTradingController($service);
        $request = Request::create(
            '/api/copytrading/master_applications/export',
            'GET',
            array_merge($filters, ['export_ext' => 'csv'])
        );

        $response = $controller->exportMasterApplications($request);

        // CSV 走 streamDownload（Excel::fake 不拦截），只校验响应头
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'copytrading-master-applications-',
            (string) $response->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString('.csv', (string) $response->headers->get('Content-Disposition'));
    }
}
