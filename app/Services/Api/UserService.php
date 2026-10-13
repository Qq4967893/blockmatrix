<?php

namespace App\Services\Api;

use App\Models\Api\User;
use App\Models\Api\UserCreditRecord;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\CountryRepository;
use App\Repositories\Api\FundingApplyRepository;
use App\Repositories\Api\TradeAccountRepository;
use App\Repositories\Api\UserCreditLedgerRepository;
use App\Repositories\Api\UserCreditRecordRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Api\UserSocialRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Repositories\Et6\AccountRepository;
use App\Repositories\Et6\BalanceFlowRepository;
use App\Repositories\Et6\TradeRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Scaffold\Framework\Enums\TypeTradeAccount;

class UserService
{
    public function __construct(
        private UserRepository $repo,
        private UserSocialRepository $userSocialRepo,
        private TradeAccountRepository $tradeAccountRepo,
        private UserOperationLogRepository $userOperationLogRepo,
        private CountryRepository $countryRepo,
        private FundingApplyRepository $fundingApplyRepo,
        private TradeRepository $tradeRepo,
        private AccountRepository $accountRepo,
        private BalanceFlowRepository $balanceFlowRepo,
        private UserCreditRecordRepository $userCreditRecordRepo,
        private UserCreditLedgerRepository $userCreditLedgerRepo,
    ) {}

    /**
     * 客户列表
     *
     * @param  string  $searchValue  关键字
     * @param  int  $payStatus  是否充值 0-全部 1-是 2-否
     * @param  int|null  $ibAffiliation  代理归属
     * @param  string  $registerTimeStart  注册开始时间
     * @param  string  $registerTimeEnd  注册结束时间
     * @param  string  $sort  排序字段
     * @param  string  $order  排序方式
     * @param  int  $limit  每页条数
     * @param  int  $offset  偏移量
     * @return array
     */
    public function getList(
        $searchValue,
        $payStatus,
        $ibAffiliation,
        $registerTimeStart,
        $registerTimeEnd,
        $sort,
        $order,
        $limit,
        $offset
    ) {
        [$data, $count] = $this->repo->list(
            $searchValue,
            $payStatus,
            $ibAffiliation,
            $registerTimeStart,
            $registerTimeEnd,
            $sort,
            $order,
            $limit,
            $offset
        );

        $this->buildData($data);

        return ['data' => $data, 'total_count' => $count];
    }

    /**
     * 根据用户ID查询用户信息
     *
     * @param  int  $userCode  用户编码
     * @param  array  $columns  字段
     * @return Builder|Model|object|null
     */
    public function getByUserCode($userCode, $columns = ['*'])
    {
        return $this->repo->firstBy('user_code', $userCode, $columns);
    }

    /**
     * 获取客户概览信息
     *
     * @param  int  $userCode  用户编码
     * @return array|null
     */
    public function getOverviewInfo($userCode)
    {
        $userInfo = $this->getByUserCode($userCode);
        if (empty($userInfo)) {
            return null;
        }

        $userData = is_array($userInfo) ? $userInfo : $userInfo->toArray();
        $userSocials = $this->userSocialRepo->getByUserCode($userCode);
        $registerMode = $this->resolveRegistrationWay($userData);

        $userData['register_mode'] = $registerMode;
        $userData['register_mode_name'] = $registerMode;
        $userData['type_text'] = trans('user.user_type.'.(int) ($userData['type'] ?? 0));
        $userData['bounds'] = $userSocials;
        $userData['country_text'] = $this->resolveCountryName($userData['country'] ?? null);
        // pre_wallet_address 已废弃，network 暂返回空串，待后续接入新数据源。
        $userData['network'] = '';
        $userData['has_open_bonus_fund'] = $this->tradeAccountRepo->existsBonusFundByUserCode((int) $userCode);
        $userData = array_merge($userData, $this->buildCreditBonusOverview(
            $this->userCreditRecordRepo->latestByUserCode((int) $userCode)
        ));

        return array_merge($userData, $this->buildTradeSummary($userCode));
    }

    /**
     * @return array{bonus_amount:float,unlocked_bonus_amount:float,bonus_status:int,bonus_status_text:string}
     */
    private function buildCreditBonusOverview(?UserCreditRecord $record): array
    {
        if ($record === null) {
            return [
                'bonus_amount' => 0.0,
                'unlocked_bonus_amount' => 0.0,
                'bonus_status' => UserCreditRecord::STATUS_UNAVAILABLE,
                'bonus_status_text' => trans('user.bonus_status.'.UserCreditRecord::STATUS_UNAVAILABLE),
            ];
        }

        $status = (int) $record->status;

        return [
            'bonus_amount' => (float) $record->grant_amount,
            'unlocked_bonus_amount' => $this->userCreditLedgerRepo->sumUnlockByCreditRecordId((int) $record->id),
            'bonus_status' => $status,
            'bonus_status_text' => trans('user.bonus_status.'.$status),
        ];
    }

    /**
     * 更新用户基础信息（first_name/last_name/country）
     *
     * @param  array{first_name?:string|null,last_name?:string|null,country?:string|null}  $payload
     */
    public function updateUserProfile(int $userCode, array $payload, int $operatorId = 0): array
    {
        $user = $this->getByUserCode($userCode);
        if (! $user) {
            throw new \Exception(trans('user.message.not_exist'));
        }

        $data = array_filter([
            'first_name' => $payload['first_name'] ?? null,
            'last_name' => $payload['last_name'] ?? null,
            'country' => $payload['country'] ?? null,
        ], static fn ($v) => $v !== null);

        if (empty($data)) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $before = array_intersect_key(
            is_array($user) ? $user : $user->toArray(),
            $data
        );

        $this->repo->updateByUserCode($userCode, $data);

        $content = json_encode(
            ['before' => $before, 'after' => $data],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $this->userOperationLogRepo->addRecord(
            UserOperationLog::DEFAULT_TARGET_ID,
            UserOperationLog::TARGET_TYPE_USER_PROFILE,
            $content,
            UserOperationLog::ACTION_TYPE_UPDATE,
            $userCode
        );

        return array_merge(['user_code' => $userCode], $data);
    }

    /**
     * 根据用户ID下线客户
     *
     * @param  int  $userCode  用户编码
     * @return bool
     */
    public function downLine($userCode)
    {
        return $this->repo->downLine($userCode);
    }

    /**
     * 更新客户的代理归属
     *
     * 说明:
     * - 校验当前客户与目标代理用户都存在
     * - 将客户的 ib_affiliation 更新为目标代理 user_code
     *
     * @param  int  $userCode  客户用户ID
     * @param  int  $targetUserCode  目标代理用户ID
     * @return array{is_succ:bool,message:string}
     */
    public function transfer(int $userCode, int $targetUserCode): array
    {
        $user = $this->getByUserCode($userCode, ['user_code']);
        if (! $user) {
            return [
                'is_succ' => false,
                'message' => trans('user.message.not_exist'),
            ];
        }

        $targetUser = $this->getByUserCode($targetUserCode, ['user_code']);
        if (! $targetUser) {
            return [
                'is_succ' => false,
                'message' => trans('user.message.not_exist'),
            ];
        }

        $this->repo->updateByUserCode($userCode, ['ib_affiliation' => $targetUserCode]);

        return [
            'is_succ' => true,
            'message' => '',
        ];
    }

    /**
     * 校验客户是否可划转
     *
     * 说明:
     * - 仅校验客户是否存在
     * - 校验通过后返回客户基础信息和当前代理归属
     *
     * @param  int  $userCode  客户用户ID
     * @return array{is_succ:bool,message:string,data?:array{user_code:int,username:string,ib_affiliation:int}}
     */
    public function checkTransferUser(int $userCode): array
    {
        $user = $this->getByUserCode($userCode, ['user_code', 'username', 'ib_affiliation']);

        if (! $user) {
            return [
                'is_succ' => false,
                'message' => trans('user.message.not_exist'),
            ];
        }

        return [
            'is_succ' => true,
            'message' => '',
            'data' => [
                'user_code' => (int) $user->user_code,
                'username' => (string) $user->username,
                'ib_affiliation' => (int) $user->ib_affiliation,
            ],
        ];
    }

    /**
     * 构建客户列表信息
     *
     * @param  array  $users  客户列表
     * @return void
     */
    private function buildData(&$users)
    {
        if (empty($users)) {
            return;
        }

        $userCodes = array_column($users, 'user_code');
        $hasBeenCashInflow = $this->repo->hasBeenCashInflow($userCodes);
        $hasPayLookup = array_flip($hasBeenCashInflow);

        foreach ($users as &$user) {
            $user['reg_way'] = $this->resolveRegistrationWay($user);
            $hasPay = isset($hasPayLookup[$user['user_code']]);
            $user['is_pay'] = $hasPay;
            $user['is_pay_name'] = trans('user.is_pay.'.($hasPay ? 1 : 0));
            $user['type_text'] = trans('user.user_type.'.(int) ($user['type'] ?? 0));
            $user['ib_affiliation'] = $user['ib_affiliation'] ?? null;
            // pre_wallet_address 已废弃，network 暂返回空串，待后续接入新数据源。
            $user['network'] = '';
        }
    }

    /**
     * Determine registration method label for a single user.
     */
    private function resolveRegistrationWay(array $user): string
    {
        return User::resolveRegistrationWayLabel($user);
    }

    /**
     * 获取交易数据汇总
     *
     * @param  int  $userCode  用户编码
     */
    private function buildTradeSummary(int $userCode): array
    {
        $fundingTotals = $this->fundingApplyRepo->getSuccessfulAmountTotalsByUserCode($userCode);
        $tradeTotals = $this->tradeRepo->getUserTradeSummary($userCode);
        $totalDeposit = round((float) $fundingTotals['deposit'], 2);
        $totalWithdraw = round((float) $fundingTotals['withdraw'], 2);
        $netDeposit = round($totalDeposit - $totalWithdraw, 2);
        $totalProfit = round((float) $tradeTotals['profit'], 2);
        $denominator = $totalDeposit > 0 ? $totalDeposit : ($netDeposit !== 0.0 ? abs($netDeposit) : 0.0);
        $profitRate = $denominator > 0 ? round($totalProfit / $denominator, 6) : 0.0;

        return [
            'total_deposit_amount' => $totalDeposit,
            'total_withdraw_amount' => $totalWithdraw,
            'net_deposit_amount' => $netDeposit,
            'total_profit_amount' => $totalProfit,
            'total_profit_rate' => $this->formatSignedPercentage($profitRate, false),
            'total_order_count' => (int) $tradeTotals['order_count'],
        ];
    }

    /**
     * 获取客户交易账号及统计
     *
     * @param  int  $userCode  用户编码
     * @param  array<int>  $accountTypes  账号类型筛选，空数组表示全部
     * @return array<int, array>
     */
    public function getUserTradeAccounts(int $userCode, array $accountTypes = []): array
    {
        $accounts = $this->tradeAccountRepo->getAccountsByUserCode($userCode);
        if (empty($accounts)) {
            return [];
        }

        $tradeAccountIds = array_values(array_filter(array_map(
            static fn (array $account): int => (int) ($account['trade_account_id'] ?? 0),
            $accounts
        )));
        $accountInfos = $this->accountRepo->getTradeAccountInfoByLogins($tradeAccountIds);
        $fundingSummaries = $this->balanceFlowRepo->getDepositWithdrawByLogins($tradeAccountIds);
        $tradeSummaries = $this->tradeRepo->getTradeAccountSummaries($tradeAccountIds);

        $result = [];
        foreach ($accounts as $account) {
            $tradeAccountId = (int) ($account['trade_account_id'] ?? 0);
            if ($tradeAccountId <= 0) {
                continue;
            }

            $accountInfo = $accountInfos[$tradeAccountId] ?? [
                'currency' => 'USD',
                'so_type' => 0,
                'position_mode' => 0,
            ];
            $totals = $this->buildLocalTradeAccountTotals(
                $fundingSummaries[$tradeAccountId] ?? [],
                $tradeSummaries[$tradeAccountId] ?? []
            );
            $currency = $accountInfo['currency'];

            $record = $this->formatTradeAccountRecord($account, $totals, $currency, $accountInfo);
            if (! $this->matchesTradeAccountTypeFilter(
                $record['trade_account_type'],
                $accountTypes
            )) {
                continue;
            }

            $result[] = $record;
        }

        usort(
            $result,
            static function (array $a, array $b): int {
                $typeA = (int) ($a['trade_account_type'] ?? 0);
                $typeB = (int) ($b['trade_account_type'] ?? 0);

                if ($typeA === $typeB) {
                    return (int) ($a['trade_account_id'] ?? 0) <=> (int) ($b['trade_account_id'] ?? 0);
                }

                return $typeA <=> $typeB;
            }
        );

        return $result;
    }

    private function buildLocalTradeAccountTotals(array $fundingSummary, array $tradeSummary): array
    {
        $deposit = round((float) ($fundingSummary['deposit'] ?? 0), 2);
        $withdraw = round((float) ($fundingSummary['withdraw'] ?? 0), 2);
        $netDeposit = round($deposit - $withdraw, 2);
        $profit = round((float) ($tradeSummary['profit'] ?? 0), 2);
        $denominator = $deposit > 0 ? $deposit : ($netDeposit !== 0.0 ? abs($netDeposit) : 0.0);

        return [
            'deposit' => $deposit,
            'withdraw' => $withdraw,
            'net_deposit' => $netDeposit,
            'profit' => $profit,
            'profit_rate' => $denominator > 0 ? round($profit / $denominator, 6) : 0.0,
            'order_count' => (int) ($tradeSummary['order_count'] ?? 0),
        ];
    }

    private function formatTradeAccountRecord(array $account, array $totals, string $currency, array $accountInfo): array
    {
        $tradeAccountType = (int) ($account['trade_account_type'] ?? 0);
        $tradeAccountId = (int) ($account['trade_account_id'] ?? 0);
        $soType = $accountInfo['so_type'];
        $positionMode = $accountInfo['position_mode'];

        return [
            'trade_account_id' => $tradeAccountId,
            'opened_at' => $account['created_at'] ?? null,
            'trade_account_type' => $tradeAccountType,
            'trade_account_type_name' => $this->resolveTradeAccountTypeName($tradeAccountType),
            'so_type' => $soType,
            'so_type_name' => $this->resolveSoTypeName($soType),
            'position_mode' => $positionMode,
            'position_mode_text' => $this->resolvePositionModeName($positionMode),
            'total_profit_amount' => $totals['profit'],
            'total_profit_amount_text' => $this->formatSignedMoney($totals['profit'], $currency),
            'total_profit_rate' => $this->formatSignedPercentage($totals['profit_rate'], false),
            'total_profit_rate_text' => $this->formatSignedPercentage($totals['profit_rate'], false),
            'total_order_count' => $totals['order_count'],
            'total_deposit_amount' => $totals['deposit'],
            'total_deposit_amount_text' => $this->formatMoney($totals['deposit'], $currency),
            'total_withdraw_amount' => $totals['withdraw'],
            'total_withdraw_amount_text' => $this->formatMoney($totals['withdraw'], $currency),
            'net_deposit_amount' => $totals['net_deposit'],
            'net_deposit_amount_text' => $this->formatSignedMoney($totals['net_deposit'], $currency),
        ];
    }

    /**
     * @param  array<int>  $filters
     */
    private function matchesTradeAccountTypeFilter(int $accountType, array $filters): bool
    {
        return $filters === [] || in_array($accountType, $filters, true);
    }

    private function resolveTradeAccountTypeName(int $tradeAccountType): string
    {
        return TypeTradeAccount::tryFrom($tradeAccountType)?->label() ?? (string) $tradeAccountType;
    }

    private function resolveSoTypeName(int $soType): string
    {
        $key = sprintf('user.trade_account.so_type.%s', $soType);
        $label = trans($key);
        if ($label !== $key) {
            return $label;
        }

        $fallbackKey = 'user.trade_account.so_type.default';
        $fallback = trans($fallbackKey);

        return $fallback === $fallbackKey ? 'Cross' : $fallback;
    }

    private function resolvePositionModeName(int $positionMode): string
    {
        $key = sprintf('user.trade_account.position_mode.%s', $positionMode);
        $label = trans($key);

        return $label !== $key ? $label : (string) $positionMode;
    }

    private function resolveCountryName(?string $countryCode): ?string
    {
        if (empty($countryCode)) {
            return null;
        }

        $countries = $this->countryRepo->getByAlphas([$countryCode], ['alpha', 'name']);
        if (empty($countries[0]['name'])) {
            return null;
        }

        $name = $countries[0]['name'];
        $currentLang = config('app.locale', 'en_US');

        if (is_array($name)) {
            return $name[$currentLang] ?? reset($name) ?? null;
        }

        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded[$currentLang] ?? reset($decoded) ?? null;
            }

            return $name;
        }

        return null;
    }

    private function formatMoney(float $amount, string $currency): string
    {
        return sprintf('%s %s', $this->formatNumber(abs($amount)), strtoupper($currency));
    }

    private function formatSignedMoney(float $amount, string $currency): string
    {
        $sign = $amount >= 0 ? '+' : '-';

        return sprintf('%s %s %s', $sign, $this->formatNumber(abs($amount)), strtoupper($currency));
    }

    private function formatSignedPercentage(float $rate, bool $withSign = true): string
    {
        $percent = round($rate * 100, 2);
        $sign = $percent >= 0 ? '+' : '-';
        $number = $this->formatNumber(abs($percent));

        if ($withSign) {
            return sprintf('%s %s%%', $sign, $number);
        }

        return $percent < 0 ? sprintf('-%s%%', $number) : sprintf('%s%%', $number);
    }

    private function formatNumber(float $number): string
    {
        return number_format($number, 2, '.', '');
    }
}
