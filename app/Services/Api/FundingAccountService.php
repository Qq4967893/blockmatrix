<?php

namespace App\Services\Api;

use App\Repositories\Api\FundingAccountRepository;

class FundingAccountService
{
    public function __construct(private FundingAccountRepository $repo)
    {
    }

    /**
     * @return array{data: array<int, array{funding_account_id:int,total_balance:float}>}
     */
    public function getByUserCode(int $userCode): array
    {
        $account = $this->repo->findByUserCode($userCode);
        if (empty($account)) {
            return ['data' => []];
        }

        return [
            // 产品约定：funding_account_id 对应 user_code
            'funding_account_id' => (int) ($account['user_code'] ?? 0),
            'total_balance'      => (float) ($account['total_balance'] ?? 0),
        ];
    }
}
