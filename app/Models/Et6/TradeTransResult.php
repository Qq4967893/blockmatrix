<?php

namespace App\Models\Et6;

use Illuminate\Support\Str;
use Scaffold\Framework\Models\TradingSystem\Order;

class TradeTransResult extends Order
{
    public const TRADE_DECIMAL_PLACES = 6;
    public const DEFAULT_CLOSE_TIME = null;

    /** 订单状态 */
    public const ORDER_STATUS_OPEN = 0;
    public const ORDER_STATUS_CLOSED = 1;

    /** 是否平仓筛选值 */
    public const IS_CLOSED_FILTER_ALL = 0;
    public const IS_CLOSED_FILTER_OPEN = 1;
    public const IS_CLOSED_FILTER_CLOSED = 2;

    /** 平仓类型：对应 trade.close_type 翻译与接口返回值 */
    public const CLOSE_TYPE_EMPTY = -1;
    public const CLOSE_TYPE_MANUAL_LEGACY = 0;
    public const CLOSE_TYPE_MANUAL = 1;
    public const CLOSE_TYPE_BACKEND = 2;
    public const CLOSE_TYPE_STOP_LOSS = 3;
    public const CLOSE_TYPE_TAKE_PROFIT = 4;
    public const CLOSE_TYPE_FORCED = 5;
    public const CLOSE_TYPE_FOLLOW = 9;

    /** 挂单状态 */
    public const PENDING_STATUS_PENDING = 0;
    public const PENDING_STATUS_ACTIVATED = 1;
    public const PENDING_STATUS_EXPIRED = 2;
    public const PENDING_STATUS_CANCELLED = 3;

    /** 未知命令 */
    public const CMD_UNKNOWN = -1;

    /** 全部筛选值 */
    public const FILTER_ALL = -1;

    /** 市价单 cmd 上限（cmd < 2 为市价单） */
    public const CMD_MARKET_THRESHOLD = 2;

    /** 挂单 cmd 集合 */
    public const PENDING_CMDS = [2, 3, 4, 5];

    /**
     * 获取平仓类型
     *
     * @param string $comment 备注
     *
     * @return int
     * @throws Exception
     */
    public static function getPendingStatus(string $comment): int
    {
        // 挂单的状态：0挂单中，1activate激活（挂单已成交），2expiration挂单已过期，3cancelled挂单已取消
        switch (true) {
            case Str::contains($comment, 'activate'):
                $status = self::PENDING_STATUS_ACTIVATED;
                break;
            case Str::contains($comment, 'expiration'):
                $status = self::PENDING_STATUS_EXPIRED;
                break;
            case Str::contains($comment, 'cancelled'):
                $status = self::PENDING_STATUS_CANCELLED;
                break;
            default:
                $status = self::PENDING_STATUS_PENDING;
                break;
        }
        return $status;
    }

    /**
     * 获取已成交挂单初始cmd
     *
     * @param string $comment 备注
     *
     * @return int
     * @throws Exception
     */
    public static function getPendingOriginalCmd(string $comment): int
    {
        if (preg_match('/\[cmd:(\d+)\]/', $comment, $matches)) {
            $cmd = (int) $matches[1];
        } else {
            // 未找到
            $cmd = self::CMD_UNKNOWN;
        }
        return $cmd;
    }
}
