<?php

namespace App\Repositories\Et6;

use Scaffold\Framework\Models\TradingSystem\Account;
use Scaffold\Framework\Repositories\Repository;

class AccountRepository extends Repository
{
    public function __construct(Account $model)
    {
        parent::__construct($model);
    }

    public function getBalanceByLogins($logins)
    {
        return $this->getQuery()->whereIn('login', $logins)->pluck('balance', 'login')->toArray();
    }

    /**
     * @param array<int> $logins
     * @return array<int, array{currency:string, so_type:int, position_mode:int}>
     */
    public function getTradeAccountInfoByLogins(array $logins): array
    {
        if (empty($logins)) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('login', $logins)
            ->get(['login', 'currency', 'so_type', 'position_mode'])
            ->mapWithKeys(static function ($account): array {
                $row = is_array($account) ? $account : $account->toArray();

                return [
                    (int) $row['login'] => [
                        'currency'      => strtoupper((string) ($row['currency'] ?? 'USD')),
                        'so_type'       => (int) ($row['so_type'] ?? 0),
                        'position_mode' => (int) ($row['position_mode'] ?? 0),
                    ],
                ];
            })
            ->all();
    }
}
