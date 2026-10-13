<?php

namespace App\Http\Controllers\Et6;

use App\Http\Controllers\BaseController;
use App\Models\Et6\TradeTransResult;
use App\Services\Api\SourceService;
use App\Services\Et6\TradeService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Scaffold\Framework\Enums\TypeTradeAccount;

class TradeController extends BaseController
{
    public function __construct(
        private TradeService $tradeService,
        private SourceService $sourceService
    ) {
    }

    /**
     * 获取订单筛选类型列表
     *
     * @return JsonResponse
     */
    public function types(): JsonResponse
    {
        return $this->succeed([
            'account_types' => array_merge([
                ['code' => TradeTransResult::FILTER_ALL, 'name' => trans('public.filter_all')],
            ], array_map(
                static fn (TypeTradeAccount $type) => [
                    'code' => $type->value,
                    'name' => $type->label(),
                ],
                TypeTradeAccount::cases()
            )),
            'is_closed_types' => [
                ['code' => TradeTransResult::IS_CLOSED_FILTER_ALL, 'name' => trans('public.filter_all')],
                ['code' => TradeTransResult::IS_CLOSED_FILTER_OPEN, 'name' => trans('trade.order_status.' . TradeTransResult::ORDER_STATUS_OPEN)],
                ['code' => TradeTransResult::IS_CLOSED_FILTER_CLOSED, 'name' => trans('trade.order_status.' . TradeTransResult::ORDER_STATUS_CLOSED)],
            ],
        ]);
    }

    /**
     * 获取订单列表
     *
     * Query:
     *  - user_code:int 用户ID
     *  - account_type:int 账号类型：-1全部（默认选项），0统一交易账号，1跟单账号，2比赛账号，3赠金账户
     *  - login:int 子账号
     *  - ticket:int 订单号
     *  - is_closed:int 是否平仓：0全部，1未平仓，2已平仓 默认：全部
     *  - open_start_time:string 开仓开始时间
     *  - open_end_time:string 开仓结束时间
     *  - close_start_time:string 平仓开始时间
     *  - close_end_time:string 平仓结束时间
     *  - profit_mode:int 盈利模式筛选：-1全部（默认），0RWA，1Crypto
     *
     * Response.data[].fields:
     * - user_code:int 用户ID
     * - login:int 子账号
     * - trade_account_type 账号类型：0统一交易账号，1跟单账号，2比赛账号，3赠金账户
     * - ticket:int 订单号
     * - cmd:int 买卖方向：0买，1卖
     * - source:string 品种
     * - category:string 交易品类
     * - leverage:int 杠杆
     * - so_type:int 交易模式：1全仓，2逐仓
     * - open_time:string 开仓时间
     * - close_time:string 平仓时间
     * - open_price:float 开仓价
     * - close_price:float 平仓价
     * - margin_order:float 占用保证金
     * - position_size:float 持仓量
     * - trade_volume:float 交易量（profit_mode=0取trade_trans_result.volume，profit_mode=1取trade_trans_result.position_size）
     * - profit:float 盈亏
     * - profit_pl:float 净盈亏
     * - borrow:float Borrow
     * - swaps:float Swap
     * - fee:float 开平仓手续费
     * - profit_mode:int 盈利模式：0RWA，1Crypto
     * - order_status:int 订单状态 0:持仓单，1:平仓单
     * - close_type:int 平仓类型：-1:空，1:手动平仓，2:后台平仓，3:止损平仓，4:止盈平仓，5:强制平仓，9:跟随平仓
     *
     * @param Request $request request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function list(Request $request)
    {
        /* 每页条数 */
        $limit = $request->input('limit', 20);
        /* 偏移量 */
        $offset = $request->input('offset', 0);

        return $this->succeed($this->getTradeList($request, (int) $limit, (int) $offset));
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getTradeList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $result = $this->getTradeList($request, (int) ($preview['total_count'] ?? 0), 0);
        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'login' => $item['login'] ?? '',
                'trade_account_type_name' => $item['trade_account_type_name'] ?? '',
                'ticket' => $item['ticket'] ?? '',
                'source' => $item['source'] ?? '',
                'trade_volume' => $item['trade_volume'] ?? '',
                'so_type_name' => $item['so_type_name'] ?? '',
                'cmd_text' => $item['cmd_text'] ?? '',
                'leverage' => $item['leverage'] ?? '',
                'open_time' => $item['open_time'] ?? '',
                'close_time' => $item['close_time'] ?? '',
                'open_price' => $item['open_price'] ?? '',
                'close_price' => $item['close_price'] ?? '',
                'margin_order' => $item['margin_order'] ?? '',
                'profit' => $item['profit'] ?? '',
                'fee' => $item['fee'] ?? '',
                'funding_fee' => $item['funding_fee'] ?? '',
                'order_status_text' => $item['order_status_text'] ?? '',
                'close_type_text' => $item['close_type_text'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('trades-%s', now()->format('YmdHis')),
            trans('trade.export.trades.headers'),
            $rows,
            $exportExtension
        );
    }

    /**
     * 获取挂单列表
     *
     * Query:
     *  - user_code:int 用户ID
     *  - login:int 子账号
     *  - ticket:int 订单号
     *  - pending_start_time:string 挂单开始时间
     *  - pending_end_time:string 挂单结束时间
     *  - profit_mode:int 盈利模式筛选：-1全部（默认），0RWA，1Crypto
     *
     * Response.data[].fields:
     * - user_code:int 用户ID
     * - login:int 子账号
     * - ticket:int 订单号
     * - cmd:int 买卖方向：2挂单买入，3挂单卖出，4挂单追涨，5挂单追空；2，4买，3，5卖；
     * - source:string 品种
     * - category:string 交易品类
     * - leverage:int 杠杆
     * - so_type:int 交易模式：1全仓，2逐仓
     * - open_time:string 挂单时间
     * - expiration:string 挂单有效时间
     * - open_price:float 挂单价格
     * - position_size:float 持仓量
     * - trade_volume:float 交易量（profit_mode=0取trade_trans_result.volume，profit_mode=1取trade_trans_result.position_size）
     * - swaps:float Swap
     * - profit_mode:int 盈利模式：0RWA，1Crypto
     * - tp:float 止盈设置
     * - sl:float 止损设置
     * - pending_status:int 挂单状态：0挂单中，1挂单已成交，2挂单已过期，3挂单已取消
     *
     * @param Request $request request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function pendingList(Request $request)
    {
        /* 每页条数 */
        $limit = $request->input('limit', 20);
        /* 偏移量 */
        $offset = $request->input('offset', 0);

        return $this->succeed($this->getPendingTradeList($request, (int) $limit, (int) $offset));
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function exportPending(Request $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getPendingTradeList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $result = $this->getPendingTradeList($request, (int) ($preview['total_count'] ?? 0), 0);
        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'login' => $item['login'] ?? '',
                'ticket' => $item['ticket'] ?? '',
                'source' => $item['source'] ?? '',
                'trade_volume' => $item['trade_volume'] ?? '',
                'so_type_name' => $item['so_type_name'] ?? '',
                'cmd_text' => $item['cmd_text'] ?? '',
                'leverage' => $item['leverage'] ?? '',
                'pending_time' => $item['pending_time'] ?? '',
                'expiration' => $item['expiration'] ?? '',
                'open_price' => $item['open_price'] ?? '',
                'position_size' => $item['position_size'] ?? '',
                'tp' => $item['tp'] ?? '',
                'sl' => $item['sl'] ?? '',
                'pending_status_text' => $item['pending_status_text'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('pending-trades-%s', now()->format('YmdHis')),
            trans('trade.export.pending_trades.headers'),
            $rows,
            $exportExtension
        );
    }

    private function getTradeList(Request $request, int $limit, int $offset): array
    {
        /* 用户ID */
        $userCode = $request->input('user_code', 0);
        /* 账号类型 */
        $accountType = $request->input('account_type', TradeTransResult::FILTER_ALL);
        /* 子账号 */
        $login = $request->input('login', 0);
        /* 订单号 */
        $ticket = $request->input('ticket', 0);
        /* 是否平仓 */
        $isClosed = $request->input('is_closed', TradeTransResult::IS_CLOSED_FILTER_ALL);
        /* 开仓开始时间 */
        $openStartTime = $request->input('open_start_time', '');
        /* 开仓结束时间 */
        $openEndTime = $request->input('open_end_time', '');
        /* 平仓开始时间 */
        $closeStartTime = $request->input('close_start_time', '');
        /* 平仓结束时间 */
        $closeEndTime = $request->input('close_end_time', '');
        /* 盈利模式 */
        $profitMode = $request->input('profit_mode', TradeTransResult::FILTER_ALL);
        /* 排序字段 */
        $sort = $request->input('sort', 'open_time');
        /* 排序方式 */
        $order = $request->input('order', 'desc');

        [$trades, $count] = $this->tradeService->getList($userCode, $accountType, $login, $ticket, $isClosed, $openStartTime, $openEndTime, $closeStartTime, $closeEndTime, $profitMode, $sort, $order, $limit, $offset);

        return ['data' => $this->formatTradeRows($trades), 'total_count' => $count];
    }

    private function getPendingTradeList(Request $request, int $limit, int $offset): array
    {
        /* 用户ID */
        $userCode = $request->input('user_code', 0);
        /* 子账号 */
        $login = $request->input('login', 0);
        /* 订单号 */
        $ticket = $request->input('ticket', 0);
        /* 挂单开始时间 */
        $pendingStartTime = $request->input('pending_start_time', '');
        /* 挂单结束时间 */
        $pendingEndTime = $request->input('pending_end_time', '');
        /* 盈利模式 */
        $profitMode = $request->input('profit_mode', TradeTransResult::FILTER_ALL);
        /* 排序字段 */
        $sort = $request->input('sort', 'ticket');
        /* 排序方式 */
        $order = $request->input('order', 'desc');

        [$trades, $count] = $this->tradeService->getPendingList($userCode, $login, $ticket, $pendingStartTime, $pendingEndTime, $profitMode, $sort, $order, $limit, $offset);

        return ['data' => $this->formatPendingTradeRows($trades), 'total_count' => $count];
    }

    private function formatTradeRows(array $trades): array
    {
        [$sourceDigits, $sourceContractSize] = $this->sourceService->getSourceInfos();
        $sourceCategories = $this->sourceService->getSourceCategories();
        $data = [];
        foreach ($trades as $trade) {
            $borrow = $trade['swaps'] + $trade['commission'];
            $fee = $trade['open_fee'] + $trade['close_fee'];
            $orderStatus = $trade['close_time'] == TradeTransResult::DEFAULT_CLOSE_TIME
                ? TradeTransResult::ORDER_STATUS_OPEN
                : TradeTransResult::ORDER_STATUS_CLOSED;
            $closeType = $trade['close_time'] == TradeTransResult::DEFAULT_CLOSE_TIME
                ? TradeTransResult::CLOSE_TYPE_EMPTY
                : (int) $trade['close_type'];

            if (is_null($trade['user_code'])) {
                $trade['user_code'] = 0;
            }

            $digit = isset($sourceDigits[$trade['source']]) ? $sourceDigits[$trade['source']] : 4;
            $closeTime = $trade['close_time'] == TradeTransResult::DEFAULT_CLOSE_TIME ? '--' : $trade['close_time'];
            $volume = round($trade['order_qty'] / $sourceContractSize[$trade['source']], 2);
            $data[] = [
                'user_code' => $trade['user_code'],
                'login' => $trade['login'],
                'trade_account_type' => $trade['trade_account_type'],
                'trade_account_type_name' => $this->resolveTradeAccountTypeName((int) $trade['trade_account_type']),
                'ticket' => $trade['ticket'],
                'cmd' => $trade['cmd'],
                'cmd_text' => trans('trade.direction.trade.' . (int) $trade['cmd']),
                'source' => $trade['source'],
                'category' => (string) ($sourceCategories[$trade['source']] ?? ''),
                'leverage' => $trade['leverage'],
                'so_type' => $trade['so_type'],
                'so_type_name' => $this->resolveSoTypeName((int) $trade['so_type']),
                'open_time' => $trade['open_time'],
                'close_time' => $closeTime,
                'open_price' => number_format($trade['open_price'], $digit, '.', ''),
                'close_price' => number_format($trade['close_price'], $digit, '.', ''),
                'margin_order' => number_format($trade['margin_order'], TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'position_size' => number_format($trade['position_size'], TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'trade_volume' => $volume,
                'profit' => number_format($trade['profit'], TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'borrow' => number_format($borrow, TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'swaps' => number_format($trade['swaps'], TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'funding_fee' => number_format($trade['swaps'], TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'fee' => number_format($fee, TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'profit_pl' => number_format(($trade['profit'] + $borrow + $fee), TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'profit_mode' => (int) $trade['profit_mode'],
                'order_status' => $orderStatus,
                'order_status_text' => trans('trade.order_status.' . $orderStatus),
                'close_type' => $closeType,
                'close_type_text' => trans('trade.close_type.' . $closeType),
            ];
        }

        return $data;
    }

    private function formatPendingTradeRows(array $trades): array
    {
        [$sourceDigits, $sourceContractSize] = $this->sourceService->getSourceInfos();
        $sourceCategories = $this->sourceService->getSourceCategories();
        $data = [];
        foreach ($trades as $trade) {
            $digit = isset($sourceDigits[$trade['source']]) ? $sourceDigits[$trade['source']] : 4;
            $pendingStatus = TradeTransResult::getPendingStatus($trade['comment']);

            // 判断如果挂单已成交，那么它的cmd变为0或1，需要追溯它成交前的挂单状态cmd是什么
            $cmd = $trade['cmd'];
            if (in_array($cmd, [0, 1], true)) {
                $cmd = TradeTransResult::getPendingOriginalCmd($trade['comment']);
            }
            $tradeVolume = round($trade['order_qty'] / $sourceContractSize[$trade['source']], 2);
            $data[] = [
                'user_code' => $trade['user_code'],
                'login' => $trade['login'],
                'ticket' => $trade['ticket'],
                'cmd' => $cmd,
                'cmd_text' => trans('trade.direction.pending.' . (int) $cmd),
                'source' => $trade['source'],
                'category' => (string) ($sourceCategories[$trade['source']] ?? ''),
                'leverage' => $trade['leverage'],
                'so_type' => $trade['so_type'],
                'so_type_name' => $this->resolveSoTypeName((int) $trade['so_type']),
                'pending_time' => $trade['open_time'],
                'expiration' => $trade['expiration'],
                'open_price' => number_format($trade['open_price'], $digit, '.', ''),
                'position_size' => number_format($trade['position_size'], TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'trade_volume' => $tradeVolume,
                'swaps' => number_format($trade['swaps'], TradeTransResult::TRADE_DECIMAL_PLACES, '.', ''),
                'profit_mode' => (int) $trade['profit_mode'],
                'sl' => number_format($trade['sl'], $digit, '.', ''),
                'tp' => number_format($trade['tp'], $digit, '.', ''),
                'pending_status' => $pendingStatus,
                'pending_status_text' => trans('trade.pending_status.' . $pendingStatus),
            ];
        }

        return $data;
    }

    private function resolveSoTypeName(int $soType): string
    {
        $key = 'user.trade_account.so_type.' . $soType;
        $value = trans($key);

        if ($value === $key) {
            return trans('user.trade_account.so_type.default');
        }

        return $value;
    }

    private function resolveTradeAccountTypeName(int $tradeAccountType): string
    {
        return TypeTradeAccount::tryFrom($tradeAccountType)?->label() ?? (string) $tradeAccountType;
    }
}
