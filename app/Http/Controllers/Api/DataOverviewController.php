<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Services\Api\DataOverviewService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;

#[Group('DataOverview', '数据概览')]
class DataOverviewController extends BaseController
{
    public function __construct(private DataOverviewService $dataOverviewService)
    {
    }

    /**
     * 获取数据概览
     *
     * 返回指定日期范围内的数据指标：
     * - 注册人数
     * - 首次入金用户数
     * - 有交易的用户数
     * - 交易手数（trading_lots）、交易金额（trading_amount）、订单数（order_count）
     * - 入金金额（deposit_amount）、出金金额（withdrawal_amount）、净入金金额（net_deposit_amount，trial 时固定为 0）
     * - IB返佣金额（ib_commission_amount，统计已结算分配记录，并按结算时间统计；trial 时固定为 0）
     * - IB返佣客户数（ib_commission_customer_count，按已结算返佣的 IB 客户去重；trial 时固定为 0）
     * - 高手返佣金额（master_commission_amount，统计已分润记录并按分润时间统计；trial 时固定为 0）
     * - 高手返佣客户数（master_commission_customer_count，按已分润记录的跟单客户 user_code 去重；trial 时固定为 0）
     * - source_trading_rank：按 source_trading_type 指定维度的品种交易排行，
     *   元素为 {source, value}，按统计值降序，排除值为 0 的品种
     * - source_type_order_count_distribution：贵金属（metal）、指数（index）、虚拟货币（crypto）、
     *   外汇（forex）、能源（energy）五类品种的订单数百分比分布，元素为 {source_type, source_type_name, order_count, percentage}
     * - chart：图表分组，按 chart_date_type 粒度（日/周（周一起始）/月）统计 daily_registered_users、
     *   daily_first_deposit_users、daily_trading_users、daily_trading_lots、daily_trading_amount、
     *   daily_order_count、daily_deposit_amount、daily_withdrawal_amount、daily_net_deposit_amount
     *   各自独立列表（元素为 {date, value}，date 为期间起始日期，无数据的期间补 0），仅 chart 分组受该参数影响
     *
     * 所有统计均只包含 user.type 为真实用户的数据，
     * 并根据 trade_account_type 参数区分为非赠金账户或赠金账户。
     * customer_type=ib 时仅统计代理客户（user_code 存在于 ib_account 表），
     *
     * @param DataOverviewRequest $request 包含 date_start、date_end、trade_account_type、customer_type、source_trading_type、chart_date_type
     *
     * @return JsonResponse
     */
    #[QueryParameter(name: 'date_start', description: '开始日期，格式：Y-m-d', type: 'string', required: true)]
    #[QueryParameter(name: 'date_end', description: '结束日期，格式：Y-m-d', type: 'string', required: true)]
    #[QueryParameter(name: 'trade_account_type', description: '用户类型：real=非赠金账户，trial=赠金账户', type: 'string', required: true, default: 'real')]
    #[QueryParameter(name: 'customer_type', description: '客户类型：all=全部，ib=代理客户', type: 'string', required: true, default: 'all')]
    #[QueryParameter(name: 'source_trading_type', description: '品种排行维度：lot=手数，amount=金额，order_count=订单数', type: 'string', required: true, default: 'lot')]
    #[QueryParameter(name: 'chart_date_type', description: '图表日期粒度：day=日，week=周，month=月，仅作用于 chart 分组', type: 'string', required: true, default: 'week')]
    public function index(DataOverviewRequest $request): JsonResponse
    {
        $dateStart        = $request->input('date_start');
        $dateEnd          = $request->input('date_end');
        $tradeAccountType = $request->input('trade_account_type', DataOverviewRequest::TRADE_ACCOUNT_TYPE_REAL);
        $customerType     = $request->input('customer_type', DataOverviewRequest::CUSTOMER_TYPE_ALL);
        $sourceTradingType = $request->input('source_trading_type', DataOverviewRequest::TRADING_TYPE_LOT);
        $chartDateType    = $request->input('chart_date_type', DataOverviewRequest::CHART_DATE_TYPE_WEEK);

        $result = $this->dataOverviewService->getOverview($dateStart, $dateEnd, $tradeAccountType, $customerType, $sourceTradingType, $chartDateType);
        $result['source_type_order_count_distribution'] = array_map(static function (array $item): array {
            $translationKey = 'source.type.' . ($item['source_type'] ?? '');
            $sourceTypeName = trans($translationKey);

            $item['source_type_name'] = $sourceTypeName === $translationKey
                ? (string) ($item['source_type'] ?? '')
                : $sourceTypeName;

            return $item;
        }, $result['source_type_order_count_distribution'] ?? []);

        return $this->succeed($result);
    }
}
