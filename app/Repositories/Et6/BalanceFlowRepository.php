<?php

namespace App\Repositories\Et6;

use App\Models\Et6\BalanceFlow;
use Scaffold\Framework\Repositories\Repository;

class BalanceFlowRepository extends Repository
{
    public function __construct(BalanceFlow $model)
    {
        parent::__construct($model);
    }

    // 获取交易账号净入金
    public function getNetDeposit(array $logins, int $cmd, int $action, int $feeCategory){
        return $this->getQuery()->whereIn('login', $logins)
            ->where('cmd', $cmd)
            ->where('action', $action)
            ->where('fee_category', $feeCategory)
            ->groupBy('login')
            ->selectRaw('login, SUM(amount) as net_amount')
            ->pluck('net_amount', 'login')
            ->toArray();
    }

    /**
     * @param array<int> $logins
     * @return array<int, array{deposit:float, withdraw:float, net_deposit:float}>
     */
    public function getDepositWithdrawByLogins(array $logins): array
    {
        if (empty($logins)) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('login', $logins)
            ->where('cmd', BalanceFlow::TRADE_PAY_CMD)
            ->where('action', BalanceFlow::TRADE_PAY_ACTION)
            ->where('fee_category', BalanceFlow::DEPOSIT_WITHDRAW_FEE_CATEGORY)
            ->groupBy('login')
            ->selectRaw(
                'login, '
                . 'SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS deposit, '
                . 'ABS(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END)) AS withdraw, '
                . 'SUM(amount) AS net_deposit'
            )
            ->get()
            ->mapWithKeys(static function ($summary): array {
                return [
                    (int) $summary->login => [
                        'deposit'     => round((float) $summary->deposit, 2),
                        'withdraw'    => round((float) $summary->withdraw, 2),
                        'net_deposit' => round((float) $summary->net_deposit, 2),
                    ],
                ];
            })
            ->all();
    }
}
