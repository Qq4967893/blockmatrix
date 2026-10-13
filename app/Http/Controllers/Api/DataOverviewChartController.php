<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\DataOverview\DataOverviewChartRequest;
use App\Services\Api\DataOverviewChartService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;

#[Group('DataOverview', '数据概览')]
class DataOverviewChartController extends BaseController
{
    public function __construct(private DataOverviewChartService $dataOverviewChartService)
    {
    }

    /**
     * 获取数据概览单个图表序列。
     *
     * @param DataOverviewChartRequest $request 包含日期范围、账户类型、客户类型、图表日期粒度及图表类型
     *
     * @return JsonResponse
     */
    #[QueryParameter(name: 'date_start', description: '开始日期，格式：Y-m-d', type: 'string', required: true)]
    #[QueryParameter(name: 'date_end', description: '结束日期，格式：Y-m-d', type: 'string', required: true)]
    #[QueryParameter(name: 'trade_account_type', description: '用户类型：real=非赠金账户，trial=赠金账户', type: 'string', required: true, default: 'real')]
    #[QueryParameter(name: 'customer_type', description: '客户类型：all=全部，ib=代理客户', type: 'string', required: true, default: 'all')]
    #[QueryParameter(name: 'chart_date_type', description: '图表日期粒度：day=日，week=周，month=月', type: 'string', required: true, default: 'week')]
    #[QueryParameter(name: 'chart_type', description: '图表类型：daily_registered_users、daily_first_deposit_users、daily_trading_users、daily_trading_lots、daily_trading_amount、daily_order_count、daily_deposit_amount、daily_withdrawal_amount、daily_net_deposit_amount', type: 'string', required: true)]
    public function index(DataOverviewChartRequest $request): JsonResponse
    {
        return $this->succeed($this->dataOverviewChartService->getChartSeries(
            $request->input('date_start'),
            $request->input('date_end'),
            $request->input('trade_account_type'),
            $request->input('customer_type'),
            $request->input('chart_date_type'),
            $request->input('chart_type'),
        ));
    }
}
