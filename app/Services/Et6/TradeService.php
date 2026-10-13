<?php

namespace App\Services\Et6;

use App\Repositories\Et6\TradeRepository;
use Exception;

class TradeService
{
    public function __construct(
        private TradeRepository $tradeRepo
    ) {
    }

    /**
     * 获取订单列表
     *
     * @param int    $userCode 用户ID
     * @param int    $accountType 账号类型：-1全部，0统一交易账号，1跟单账号，2比赛账号，3赠金账户
     * @param int    $login
     * @param int    $ticket 订单号
     * @param int    $isClosed 是否已平仓：0全部，1未平仓，2已平仓
     * @param string $openStartTime 开仓开始时间
     * @param string $openEndTime 开仓结束时间
     * @param string $closeStartTime 平仓开始时间
     * @param string $closeEndTime 平仓结束时间
     * @param int    $profitMode 盈利模式：-1全部，0RWA，1Crypto
     * @param string $sort
     * @param string $order
     * @param int    $limit
     * @param int    $offset
     *
     * @return array
     * @throws Exception
     */
    public function getList($userCode, $accountType, $login, $ticket, $isClosed, $openStartTime, $openEndTime, $closeStartTime, $closeEndTime, $profitMode, $sort, $order, $limit, $offset)
    {
        return $this->tradeRepo->getList($userCode, $accountType, $login, $ticket, $isClosed, $openStartTime, $openEndTime, $closeStartTime, $closeEndTime, $profitMode, $sort, $order, $limit, $offset);
    }

    /**
     * 获取订单列表
     *
     * @param int    $userCode 用户ID
     * @param int    $login
     * @param int    $ticket 订单号
     * @param string $pendingStartTime 平仓开始时间
     * @param string $pendingEndTime 平仓结束时间
     * @param int    $profitMode 盈利模式：-1全部，0RWA，1Crypto
     * @param string $sort
     * @param string $order
     * @param int    $limit
     * @param int    $offset
     *
     * @return array
     * @throws Exception
     */
    public function getPendingList($userCode, $login, $ticket, $pendingStartTime, $pendingEndTime, $profitMode, $sort, $order, $limit, $offset)
    {
        return $this->tradeRepo->getPendingList($userCode, $login, $ticket, $pendingStartTime, $pendingEndTime, $profitMode, $sort, $order, $limit, $offset);
    }
}
