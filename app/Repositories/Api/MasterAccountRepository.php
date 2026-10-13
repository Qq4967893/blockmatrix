<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\MasterAccount;
use Scaffold\Framework\Repositories\Repository;

class MasterAccountRepository extends Repository
{
    public function __construct(MasterAccount $model)
    {
        parent::__construct($model);
    }

    public function getInfo(int $userCode, array $statuses, ?int $tradeAccountType = null)
    {
        $query = $this->getQuery()
            ->where('master_account.user_code', $userCode)
            ->whereIn('master_account.status', $statuses);

        if ($tradeAccountType !== null) {
            $query->join('trade_account as ta', 'ta.trade_account_id', '=', 'master_account.trade_account_id')
                ->where('ta.trade_account_type', $tradeAccountType)
                ->select('master_account.*');
        }

        return $query->first();
    }
}
