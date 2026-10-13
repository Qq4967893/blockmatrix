<?php

namespace App\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\MasterAccount;
use App\Models\Api\MasterCommissionRecord;
use App\Models\Api\MasterLevel;
use App\Models\Blockmatrix\UserOperationLog;
use App\Models\TradingSystem\Copy;
use App\Repositories\Api\CopyTradingRepository;
use App\Repositories\Api\MasterAccountRepository;
use App\Repositories\Api\TradeAccountRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Repositories\Et6\AccountRepository;
use App\Repositories\Et6\BalanceFlowRepository;
use App\Repositories\Et6\CopyHistoryRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\TypeTradeAccount;
use Scaffold\Framework\Models\Api\MasterApplication;
use Scaffold\Framework\Services\MasterStatusOperateService;
use Scaffold\TradeSystem\Facades\TS;

class CopyTradingService
{
    public function __construct(
        private CopyTradingRepository $copyTradingRepo,
        private MasterStatusOperateService $masterStatusOperateService,
        private CopyHistoryRepository $copyHistoryRepo,
        private AccountRepository $accountRepo,
        private BalanceFlowRepository $balanceFlowRepo,
        private UserRepository $userRepo,
        private TradeAccountRepository $tradeAccountRepo,
        private MasterAccountRepository $masterAccountRepo,
        private AdminUserRepository $adminUserRepo,
        private UserOperationLogRepository $userOperationLogRepo,
    ) {}

    /**
     * 获取客户当前正在跟随的高手列表
     *
     * @param  int  $userCode  客户 ID
     */
    public function customerCurrentFollowedMasters(int $userCode): array
    {
        $logins = $this->tradeAccountRepo->getFollowerAccountsByUserCode($userCode);
        if (count($logins) == 0) {
            return ['is_succ' => true, 'message' => '', 'data' => []];
        }

        $copyInfos = $this->copyHistoryRepo->getAll([], $logins, Copy::STATUS_FOLLOWING);
        $loginMasters = array_column($copyInfos, 'login_master');
        $masterUserNames = $this->userRepo->getUsernamesByTradeAccountId($loginMasters);
        $equities = $this->loadCurrentFollowMetrics($logins);

        $data = $this->buildCurrentFollowRows(
            $copyInfos,
            $masterUserNames,
            'login_master',
            'master_nickname',
            $equities
        );

        return ['is_succ' => true, 'message' => '', 'data' => $data];
    }

    /**
     * 获取客户历史跟随过的高手列表
     *
     * @param  int  $userCode  客户 ID
     * @param  int  $offset  偏移量
     * @param  int  $limit  每页条数
     */
    public function customerHistoryFollowedMasters(int $userCode, $offset, $limit): array
    {
        $logins = $this->tradeAccountRepo->getFollowerAccountsByUserCode($userCode);
        if (count($logins) == 0) {
            return ['is_succ' => true, 'message' => '', 'data' => [], 'count' => 0];
        }

        [$copyInfos, $count] = $this->copyHistoryRepo->getList([], $logins, Copy::STATUS_ENDED, $offset, $limit);
        $loginMasters = array_column($copyInfos, 'login_master');
        $masterUserNames = $this->userRepo->getUsernamesByTradeAccountId($loginMasters);
        $netDeposits = $this->balanceFlowRepo->getNetDeposit($logins, 6, 2, 4);

        $data = $this->buildHistoryFollowRows(
            $copyInfos,
            $masterUserNames,
            'login_master',
            'master_nickname',
            $netDeposits
        );

        return ['is_succ' => true, 'message' => '', 'data' => $data, 'count' => $count];
    }

    /**
     * 获取高手当前跟随中的投资人列表
     *
     * @param  int  $masterUserCode  高手用户 ID
     * @param  int  $tradeAccountType  账户类型：0 统一交易账户，3 赠金账户
     */
    public function masterCurrentFollowers(int $masterUserCode, int $tradeAccountType): array
    {
        // 判断是否是已上榜的高手账号
        $masterAccountInfo = $this->masterAccountRepo->getInfo(
            $masterUserCode,
            [MasterAccount::STATUS_SUCCESS, MasterAccount::STATUS_HIDE],
            $tradeAccountType
        );
        if (! $masterAccountInfo) {
            return ['is_succ' => true, 'message' => '', 'data' => []];
        }

        $copyInfos = $this->copyHistoryRepo->getAll([$masterAccountInfo->trade_account_id], [], Copy::STATUS_FOLLOWING);
        $loginFollowers = array_column($copyInfos, 'login_follower');
        $followerUserNames = $this->userRepo->getUsernamesByTradeAccountId($loginFollowers);
        $equities = $this->loadCurrentFollowMetrics($loginFollowers);

        $data = $this->buildCurrentFollowRows(
            $copyInfos,
            $followerUserNames,
            'login_follower',
            'follower_nickname',
            $equities
        );

        return ['is_succ' => true, 'message' => '', 'data' => $data];
    }

    /**
     * 获取高手历史跟单投资人列表
     *
     * @param  int  $masterUserCode  高手用户 ID
     * @param  int  $tradeAccountType  账户类型：0 统一交易账户，3 赠金账户
     * @param  int  $offset  偏移量
     * @param  int  $limit  每页条数
     */
    public function masterHistoryFollowers(int $masterUserCode, int $tradeAccountType, int $offset, int $limit): array
    {
        // 判断是否是已上榜的高手账号
        $masterAccountInfo = $this->masterAccountRepo->getInfo(
            $masterUserCode,
            [MasterAccount::STATUS_SUCCESS, MasterAccount::STATUS_OFFLINE, MasterAccount::STATUS_HIDE],
            $tradeAccountType
        );
        if (! $masterAccountInfo) {
            return ['is_succ' => true, 'message' => '', 'data' => [], 'count' => 0];
        }

        [$copyInfos, $count] = $this->copyHistoryRepo->getList([$masterAccountInfo->trade_account_id], [], Copy::STATUS_ENDED, $offset, $limit);
        $loginFollowers = array_column($copyInfos, 'login_follower');
        $followerUserNames = $this->userRepo->getUsernamesByTradeAccountId($loginFollowers);
        $netDeposits = $this->balanceFlowRepo->getNetDeposit($loginFollowers, 6, 2, 4);

        $data = $this->buildHistoryFollowRows(
            $copyInfos,
            $followerUserNames,
            'login_follower',
            'follower_nickname',
            $netDeposits
        );

        return ['is_succ' => true, 'message' => '', 'data' => $data, 'count' => $count];
    }

    /**
     * 加载当前跟单列表展示所需的账号指标
     *
     * @param  array  $logins  交易账号列表
     */
    private function loadCurrentFollowMetrics(array $logins): array
    {
        $equities = TS::getUserEquity($logins)->data ?? [];
        $equities = array_column($equities, 'equity', 'login');

        return $equities;
    }

    /**
     * 组装当前跟单列表返回数据
     *
     * @param  array  $copyInfos  跟单关系列表
     * @param  array  $nameMap  账号名称映射
     * @param  string  $nameSourceField  名称来源字段
     * @param  string  $nameTargetField  返回名称字段
     * @param  array  $equities  当前净值映射
     * @return array<int, array<string, mixed>>
     */
    private function buildCurrentFollowRows(
        array $copyInfos,
        array $nameMap,
        string $nameSourceField,
        string $nameTargetField,
        array $equities
    ): array {
        $data = [];
        foreach ($copyInfos as $copyInfo) {
            $loginFollower = $copyInfo['login_follower'];
            $data[] = [
                $nameTargetField => $nameMap[$copyInfo[$nameSourceField]] ?? '',
                'invest_current_amount' => $equities[$loginFollower] ?? 0,
                'invest_initial_amount' => $copyInfo['amount'] ?? 0,
                'invest_start_time' => $copyInfo['op_time'] ?? '',
            ];
        }

        return $data;
    }

    /**
     * 组装历史跟单列表返回数据
     *
     * @param  array  $copyInfos  历史跟单关系列表
     * @param  array  $nameMap  账号名称映射
     * @param  string  $nameSourceField  名称来源字段
     * @param  string  $nameTargetField  返回名称字段
     * @param  array  $netDeposits  净入金
     * @return array<int, array<string, mixed>>
     */
    private function buildHistoryFollowRows(
        array $copyInfos,
        array $nameMap,
        string $nameSourceField,
        string $nameTargetField,
        array $netDeposits
    ): array {
        $data = [];
        foreach ($copyInfos as $copyInfo) {
            $netDeposits = (float) ($netDeposits[$copyInfo['login_follower']] ?? 0);
            $copyAmount = (float) ($copyInfo['amount'] ?? 0);
            $loginBalance = round(($copyAmount - $netDeposits), 2);
            $roiRate = round((($loginBalance - $copyAmount) / $copyAmount) * 100, 2);

            $data[] = [
                $nameTargetField => $nameMap[$copyInfo[$nameSourceField]] ?? '',
                'invest_end_amount' => $loginBalance,
                'invest_initial_amount' => $copyAmount,
                'invest_start_time' => $copyInfo['created_at'] ?? '',
                'invest_end_time' => $copyInfo['op_time'],
                'roi_rate' => $roiRate.'%',
            ];
        }

        return $data;
    }

    /**
     * @param array{
     *   master_user_code?: int|string|null,
     *   trade_account_id?: int|string|null,
     *   trade_account_type?: int|string|null,
     *   master_level?: string|null,
     *   status?: int|string|null,
     *   is_manual?: int|string|null,
     *   is_private?: int|string|null
     * } $filters
     * @return array{data: array<int, array<string, mixed>>, total_count: int}
     */
    public function getMasters(array $filters, int $limit, int $offset): array
    {
        $levelPos = ! empty($filters['master_level']) ? $this->parseLevelPos((string) $filters['master_level']) : null;
        [$rows, $total] = $this->copyTradingRepo->getMasters($filters, $limit, $offset, $levelPos);
        $nowTs = now()->timestamp;
        $masterTradeAccountIds = $rows->pluck('trade_account_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $realFollowerCounts = $this->countRealFollowers($masterTradeAccountIds);
        $feeCommissionAmounts = $this->copyTradingRepo->getSettledFeeCommissionByMasterTradeAccountIds($masterTradeAccountIds);

        $data = $rows->map(function ($row) use ($nowTs, $realFollowerCounts, $feeCommissionAmounts): array {
            $status = (int) ($row->status ?? 0);
            $isPrivate = (int) ($row->is_private ?? MasterAccount::IS_PRIVATE_PUBLIC);
            $hiddenDays = $this->calculateCurrentHiddenDays($status, $row->status_updated_at ?? null);
            $protectionStatus = $this->resolveProtectionStatus(
                $row->level_protection_started_at ?? null,
                $row->level_protection_ended_at ?? null,
                $nowTs
            );
            $margin30d = (float) ($row->total_margin_30d ?? 0);
            $profitRate30d = $margin30d > 0 ? ((float) ($row->profit_amount_30d ?? 0) / $margin30d) * 100 : 0.0;

            $tradeAccountType = (int) ($row->trade_account_type ?? 0);

            return [
                'master_user_code' => (int) ($row->user_code ?? 0),
                'trade_account_id' => (int) ($row->trade_account_id ?? 0),
                'trade_account_type' => $tradeAccountType,
                'trade_account_type_name' => $this->resolveTradeAccountTypeName($tradeAccountType),
                'trade_account_type_text' => $this->resolveTradeAccountTypeName($tradeAccountType),
                'master_level' => sprintf('Level%d', (int) ($row->level_pos ?? 0)),
                'balance' => $this->formatAmount((string) ($row->balance ?? '0')),
                'order_count_30d' => (int) ($row->order_count_30d ?? 0),
                'profit_rate_30d' => $this->formatPercent($profitRate30d),
                'aum' => $this->formatAmount((string) ($row->follower_fund ?? '0')),
                'investor_count' => (int) ($row->follower_count ?? 0),
                'commission_follower_count' => $realFollowerCounts[(int) ($row->trade_account_id ?? 0)] ?? 0,
                'obtained_profit_share_amount' => $this->formatAmount((string) ($row->commission_amount ?? '0')),
                'profit_share_rate' => $this->formatPercent(((float) ($row->commission_rate ?? 0)) * 100),
                'fee_commission_rate' => $this->formatPercent(((float) ($row->fee_commission_rate ?? 0)) * 100),
                'obtained_fee_commission_amount' => $this->formatAmount((string) ($feeCommissionAmounts[(int) ($row->trade_account_id ?? 0)] ?? 0)),
                'current_hidden_days' => $hiddenDays,
                'level_protection_started_at' => $this->formatDateTime($row->level_protection_started_at ?? null),
                'level_protection_ended_at' => $this->formatDateTime($row->level_protection_ended_at ?? null),
                'protection_status' => $protectionStatus,
                'protection_status_text' => $this->protectionStatusText($protectionStatus),
                'status' => $status,
                'status_text' => $this->statusText($status),
                'is_private' => $isPrivate,
                'is_private_text' => $this->privateText($isPrivate),
                'can_manual_delist' => in_array($status, [MasterAccount::STATUS_SUCCESS, MasterAccount::STATUS_HIDE], true) ? 1 : 0,
                'is_manual' => ! is_null($row->operator_id ?? null) ? 1 : 0,
                'reason' => (string) ($row->reason ?? ''),
            ];
        })->all();

        return [
            'data' => $data,
            'total_count' => $total,
        ];
    }

    /**
     * 统计高手名下真实跟随者人数（分佣人数）。
     *
     * 仅统计当前跟随中的跟随者并按用户去重；
     * 跟随者本人是代理或有上级代理（ib_affiliation 归属）的，不计入。
     *
     * @param  array<int, int>  $masterTradeAccountIds
     * @return array<int, int> trade_account_id => 真实跟随者人数
     */
    private function countRealFollowers(array $masterTradeAccountIds): array
    {
        if ($masterTradeAccountIds === []) {
            return [];
        }

        $copyInfos = $this->copyHistoryRepo->getAll($masterTradeAccountIds, [], Copy::STATUS_FOLLOWING);
        if ($copyInfos === []) {
            return [];
        }

        $followerLogins = array_values(array_unique(array_map('intval', array_column($copyInfos, 'login_follower'))));
        $directUserCodes = $this->userRepo->getDirectCustomerUserCodesByTradeAccountIds($followerLogins);

        $counts = [];
        foreach ($copyInfos as $copyInfo) {
            $masterLogin = (int) ($copyInfo['login_master'] ?? 0);
            $userCode = $directUserCodes[(int) ($copyInfo['login_follower'] ?? 0)] ?? null;
            if ($masterLogin <= 0 || $userCode === null) {
                continue;
            }

            $counts[$masterLogin][$userCode] = true;
        }

        return array_map(static fn (array $userCodes): int => count($userCodes), $counts);
    }

    private function calculateCurrentHiddenDays(int $status, mixed $statusUpdatedAt): ?int
    {
        if ($status !== MasterAccount::STATUS_HIDE || empty($statusUpdatedAt)) {
            return null;
        }

        try {
            $hiddenAt = Carbon::parse($statusUpdatedAt)->startOfDay();
            $today = Carbon::now()->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return max(0, $hiddenAt->diffInDays($today, false));
    }

    private function resolveProtectionStatus(mixed $startedAt, mixed $endedAt, int $nowTs): int
    {
        $startTs = $this->toTimestamp($startedAt);
        $endTs = $this->toTimestamp($endedAt);

        if ($endTs !== null && $endTs < $nowTs) {
            return MasterAccount::PROTECTION_STATUS_ENDED;
        }

        if ($startTs === null || $startTs > $nowTs) {
            return MasterAccount::PROTECTION_STATUS_INACTIVE;
        }

        return MasterAccount::PROTECTION_STATUS_ACTIVE;
    }

    /**
     * @param  array<int, int>  $tradeAccountIds
     */
    public function updateMasterProtectionTime(?int $tradeAccountId, array $tradeAccountIds, mixed $startedAt, mixed $endedAt): void
    {
        $tradeAccountIds = $this->normalizeTradeAccountIds($tradeAccountId, $tradeAccountIds);
        [$startedAt, $endedAt] = $this->normalizeProtectionTimeRange($startedAt, $endedAt);
        $excludedStatuses = $this->masterProtectionTimeExcludedStatuses();

        $accounts = $this->copyTradingRepo->getMasterAccountsByTradeAccountIds($tradeAccountIds);
        $this->assertMasterProtectionTimeCanBeUpdated($accounts, $tradeAccountIds, $excludedStatuses);

        DB::connection(DB_API)->transaction(function () use ($tradeAccountIds, $startedAt, $endedAt, $excludedStatuses): void {
            $accounts = $this->copyTradingRepo->getMasterAccountsByTradeAccountIds($tradeAccountIds, true);
            $this->assertMasterProtectionTimeCanBeUpdated($accounts, $tradeAccountIds, $excludedStatuses);

            $payload = [
                'level_protection_started_at' => $startedAt,
                'level_protection_ended_at' => $endedAt,
            ];

            $updated = $this->copyTradingRepo->updateMasterProtectionTimeByTradeAccountIds($tradeAccountIds, $payload);
            if ($updated <= 0) {
                throw new BusinessException(trans('public.message.non_existent'));
            }
        });
    }

    /**
     * @return array<int, int>
     */
    private function masterProtectionTimeExcludedStatuses(): array
    {
        return [
            MasterAccount::STATUS_CONFIRMING,
            MasterAccount::STATUS_REJECT,
            MasterAccount::STATUS_OFFLINE,
        ];
    }

    /**
     * @param  Collection<int, object>  $accounts
     * @param  array<int, int>  $tradeAccountIds
     * @param  array<int, int>  $excludedStatuses
     */
    private function assertMasterProtectionTimeCanBeUpdated(Collection $accounts, array $tradeAccountIds, array $excludedStatuses): void
    {
        $existingTradeAccountIds = $accounts
            ->pluck('trade_account_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (array_diff($tradeAccountIds, $existingTradeAccountIds) !== []) {
            throw new BusinessException(trans('public.message.non_existent'));
        }

        foreach ($accounts as $account) {
            if (in_array((int) ($account->status ?? -1), $excludedStatuses, true)) {
                throw new BusinessException(trans('copytrading.message.not_allow_level_protection'));
            }
        }
    }

    /**
     * 潜在高手列表（用户表中不在 master_account 的用户，附带 30 日交易统计）。
     * 以交易账号维度展示，每个交易账号独立一行。
     *
     * @param array{
     *   user_code?: int|string|null,
     *   trade_account_id?: int|string|null,
     *   min_balance?: string|null,
     *   min_order_count_30d?: int|string|null,
     *   min_profit_rate_30d?: string|null,
     *   trade_account_type?: int|string|null
     * } $filters
     * @return array{data: array<int, array<string, mixed>>, total_count: int}
     */
    public function getPotentialMasters(array $filters, int $limit, int $offset): array
    {
        [$rows, $total] = $this->copyTradingRepo->getPotentialMasters($filters, $limit, $offset);

        $data = array_map(function ($row): array {
            $balance = (float) ($row->balance ?? 0);
            $totalMargin30d = (float) ($row->total_margin_30d ?? 0);
            $profitAmount30d = (float) ($row->profit_amount_30d ?? 0);
            $profitRate30d = $totalMargin30d > 0 ? ($profitAmount30d / $totalMargin30d) * 100 : 0.0;

            $tradeAccountType = (int) ($row->trade_account_type ?? 0);

            return [
                'user_code' => (int) ($row->user_code ?? 0),
                'trade_account_id' => (int) ($row->trade_account_id ?? 0),
                'trade_account_type' => $tradeAccountType,
                'trade_account_type_name' => $this->resolveTradeAccountTypeName($tradeAccountType),
                'trade_account_type_text' => $this->resolveTradeAccountTypeName($tradeAccountType),
                'is_whitelist' => (int) ($row->type ?? 0),
                'is_whitelist_text' => (string) trans('user.user_type.'.(int) ($row->type ?? 0)),
                'balance' => $this->formatAmount((string) $balance),
                'order_count_30d' => (int) ($row->order_count_30d ?? 0),
                'profit_rate_30d' => $this->formatPercent($profitRate30d),
            ];
        }, $rows->all());

        return [
            'data' => $data,
            'total_count' => $total,
        ];
    }

    /**
     * @return array<int>
     */
    private function parseTradeAccountTypes(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : explode(',', (string) $value);

        return collect($values)
            ->map(static fn ($type): int => (int) $type)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function resolveTradeAccountTypeText(array $tradeAccountTypes): string
    {
        return implode(', ', array_map(fn (int $type): string => $this->resolveTradeAccountTypeName($type), $tradeAccountTypes));
    }

    private function resolveTradeAccountTypeName(int $tradeAccountType): string
    {
        return TypeTradeAccount::tryFrom($tradeAccountType)?->label() ?? (string) $tradeAccountType;
    }

    /**
     * @return array<int>
     */
    private function masterTradeAccountTypes(): array
    {
        return [
            TypeTradeAccount::UTA->value,
            TypeTradeAccount::BonusFund->value,
        ];
    }

    /**
     * 变更高手状态（设为公开 / 设为私密 / 移除高手）。
     *
     * 操作规则：
     * - 设为公开 (1)：状态改正常，类型改公开。当前已是 成功+公开 时不可操作。
     * - 设为私密 (5)：状态改正常，类型改私密。当前已是 成功+私密 时不可操作。
     * - 移除高手 (3)：状态改已失效。当前为 申请中/拒绝/已失效 时不可操作。
     *
     * @param  int  $tradeAccountId  交易账号 ID
     * @param  int  $targetStatus  目标状态/操作码
     * @param  int  $operatorId  操作人 ID
     * @param  string  $remark  备注
     *
     * @throws \Exception
     */
    public function changeMasterStatus(int $tradeAccountId, int $targetStatus, int $operatorId, string $remark): void
    {
        $accounts = $this->copyTradingRepo->getMasterAccountsByTradeAccountId($tradeAccountId);
        if ($accounts->isEmpty()) {
            throw new BusinessException(trans('public.message.non_existent'));
        }

        foreach ($accounts as $account) {
            $currentStatus = (int) ($account->status ?? -1);
            $currentIsPrivate = (int) ($account->is_private ?? MasterAccount::IS_PRIVATE_PUBLIC);

            match ($targetStatus) {
                MasterAccount::STATUS_SUCCESS => $currentStatus === MasterAccount::STATUS_SUCCESS && $currentIsPrivate === MasterAccount::IS_PRIVATE_PUBLIC
                    && throw new BusinessException(trans('copytrading.message.already_public')),
                MasterAccount::STATUS_OFFLINE => in_array($currentStatus, [MasterAccount::STATUS_CONFIRMING, MasterAccount::STATUS_REJECT, MasterAccount::STATUS_OFFLINE], true)
                    && throw new BusinessException(trans('copytrading.message.not_allow_master_operation')),
                MasterAccount::OPERATION_PRIVATE => $currentStatus === MasterAccount::STATUS_SUCCESS && $currentIsPrivate === MasterAccount::IS_PRIVATE_YES
                    && throw new BusinessException(trans('copytrading.message.already_private')),
                default => throw new BusinessException(trans('copytrading.message.unsupported_target_status')),
            };
        }

        DB::connection(DB_API)->transaction(function () use ($accounts, $targetStatus, $operatorId, $remark, $tradeAccountId): void {
            if ($targetStatus === MasterAccount::OPERATION_PRIVATE) {
                foreach ($accounts as $account) {
                    $this->masterStatusOperateService->changeStatus(
                        (int) ($account->user_code ?? 0),
                        (int) ($account->trade_account_id ?? 0),
                        MasterAccount::STATUS_SUCCESS,
                        $operatorId > 0 ? $operatorId : null,
                        $remark
                    );
                }
                $this->copyTradingRepo->updateMasterPrivateByTradeAccountId($tradeAccountId, MasterAccount::IS_PRIVATE_YES);

                return;
            }

            foreach ($accounts as $account) {
                $this->masterStatusOperateService->changeStatus(
                    (int) ($account->user_code ?? 0),
                    (int) ($account->trade_account_id ?? 0),
                    $targetStatus,
                    $operatorId > 0 ? $operatorId : null,
                    $remark
                );
            }

            if ($targetStatus === MasterAccount::STATUS_SUCCESS) {
                $this->copyTradingRepo->updateMasterPrivateByTradeAccountId($tradeAccountId, MasterAccount::IS_PRIVATE_PUBLIC);
            }
        });
    }

    public function changePotentialMasterStatus(int $tradeAccountId, int $targetStatus, int $operatorId, string $remark): void
    {
        $isPrivate = match ($targetStatus) {
            MasterAccount::STATUS_SUCCESS => MasterAccount::IS_PRIVATE_PUBLIC,
            MasterAccount::OPERATION_PRIVATE => MasterAccount::IS_PRIVATE_YES,
            default => throw new BusinessException(trans('copytrading.message.unsupported_target_status')),
        };

        $account = $this->tradeAccountRepo->getAccountByTradeAccountId($tradeAccountId);
        if ($account === null) {
            throw new BusinessException(trans('public.message.non_existent'));
        }

        $userCode = (int) ($account['user_code'] ?? 0);
        $accountType = (int) ($account['trade_account_type'] ?? -1);

        if (! in_array($accountType, $this->masterTradeAccountTypes(), true)) {
            throw new BusinessException(trans('public.message.non_existent'));
        }

        DB::connection(DB_API)->transaction(function () use ($userCode, $tradeAccountId, $isPrivate, $operatorId, $remark): void {
            $this->masterStatusOperateService->changeStatus(
                $userCode,
                $tradeAccountId,
                MasterAccount::STATUS_SUCCESS,
                $operatorId > 0 ? $operatorId : null,
                $remark
            );

            $initialLevelId = $this->copyTradingRepo->findEnabledInitialMasterLevelId();
            if ($initialLevelId !== null) {
                $this->copyTradingRepo->updateMasterLevelIdByTradeAccountId($tradeAccountId, $initialLevelId);
            }

            $this->copyTradingRepo->updateMasterPrivateByTradeAccountId($tradeAccountId, $isPrivate);
            if (! $this->copyTradingRepo->existsMasterAccountByTradeAccountId($tradeAccountId)) {
                throw new \Exception(trans('public.message.upd_error'));
            }
        });
    }

    private function resolveMasterAccountsForStatusChange(int $masterUserCode, int $targetStatus): array|Collection
    {
        $accounts = $this->copyTradingRepo->getMasterAccountsForDelist($masterUserCode);
        if ($accounts->isNotEmpty()) {
            return $accounts;
        }

        $tradeAccounts = $this->tradeAccountRepo->getMasterDisplayAccountsByUserCode($masterUserCode);
        if ($tradeAccounts === []) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        return array_map(static function (array $account) use ($masterUserCode) {
            return (object) [
                'user_code' => $masterUserCode,
                'trade_account_id' => (int) ($account['trade_account_id'] ?? 0),
                'status' => null,
            ];
        }, $tradeAccounts);
    }

    /**
     * @param array{
     *   master_level?: string|null,
     *   level_name?: string|null,
     *   status?: int|string|null,
     *   is_initial?: int|string|null,
     *   is_default_level?: int|string|null
     * } $filters
     * @return array{data: array<int, array<string, mixed>>, total_count: int, next_level_id: int}
     */
    public function getMasterLevels(array $filters, int $limit, int $offset): array
    {
        $levelPos = ! empty($filters['master_level']) ? $this->parseLevelPos((string) $filters['master_level']) : null;
        [$rows, $total, $nextLevelId] = $this->copyTradingRepo->getMasterLevels($filters, $limit, $offset, $levelPos);

        $lang = $this->currentLang();
        $data = $rows->map(function ($row) use ($lang): array {
            $status = (int) ($row->status ?? 0);
            $isInitial = (int) ($row->is_initial ?? MasterLevel::IS_INITIAL_NO) === MasterLevel::IS_INITIAL_YES
                ? MasterLevel::IS_INITIAL_YES
                : MasterLevel::IS_INITIAL_NO;
            $isInitialText = (string) trans('copytrading.boolean.'.($isInitial === MasterLevel::IS_INITIAL_YES ? 'yes' : 'no'));
            $isDefaultLevel = (int) ($row->level_pos ?? 0) === MasterLevel::DEFAULT_LEVEL_POS
                ? MasterLevel::IS_INITIAL_YES
                : MasterLevel::IS_INITIAL_NO;
            $isDefaultLevelText = (string) trans('copytrading.boolean.'.($isDefaultLevel === MasterLevel::IS_INITIAL_YES ? 'yes' : 'no'));

            return [
                'level_id' => (int) ($row->id ?? 0),
                'master_level' => sprintf('Level%d', (int) ($row->level_pos ?? 0)),
                'is_initial' => $isInitial,
                'is_initial_text' => $isInitialText,
                'is_default_level' => $isDefaultLevel,
                'is_default_level_text' => $isDefaultLevelText,
                'level_name' => $this->resolveLocalizedName($row->name ?? [], $lang),
                'balance_threshold' => $row->balance_threshold === null ? null : $this->formatAmount((string) $row->balance_threshold),
                'order_count_30d_threshold' => $row->order_count_30d_threshold === null ? null : (int) $row->order_count_30d_threshold,
                'profit_rate_30d_threshold' => $row->profit_rate_30d_threshold === null ? null : $this->formatPercent(((float) $row->profit_rate_30d_threshold) * 100),
                'follower_fund_threshold' => $row->follower_fund_threshold === null ? null : $this->formatAmount((string) $row->follower_fund_threshold),
                'follower_count_threshold' => $row->follower_count_threshold === null ? null : (int) $row->follower_count_threshold,
                'profit_share_rate' => $this->formatPercent(((float) ($row->commission_rate ?? 0)) * 100),
                'fee_commission_rate' => $this->formatPercent(((float) ($row->fee_commission_rate ?? 0)) * 100),
                'max_invest_amount' => $this->formatAmount((string) ($row->max_investable_amount ?? '0')),
                'status' => $status,
                'status_text' => $status === MasterAccount::STATUS_SUCCESS ? '启用' : '禁用',
                'created_at' => $this->formatDateTime($row->created_at ?? null),
                'updated_at' => $this->formatDateTime($row->updated_at ?? null),
            ];
        })->all();

        return [
            'data' => $data,
            'total_count' => $total,
            'next_level_id' => $nextLevelId,
        ];
    }

    /**
     * @return array{master_levels: array<int, array{key:string,value:string}>, master_level_names: array<int, array{key:string,value:string}>, is_initial_options: array<int, array{key:string,value:string}>, master_statuses: array<int, array{key:string,value:string}>, master_types: array<int, array{key:string,value:string}>, follow_statuses: array<int, array{key:string,value:string}>, trade_account_types: array<int, array{key:string,value:string}>}
     */
    public function getMasterLevelOptions(): array
    {
        $lang = $this->currentLang();
        $rows = $this->copyTradingRepo->getAllMasterLevels();

        $masterLevels = [];
        $masterLevelNames = [];
        foreach ($rows as $row) {
            $key = (string) ((int) ($row->level_pos ?? 0));
            $masterLevels[] = ['key' => $key, 'value' => sprintf('Level%s', $key)];
            $masterLevelNames[] = ['key' => $key, 'value' => $this->resolveLocalizedName($row->name ?? [], $lang)];
        }

        return [
            'master_levels' => $masterLevels,
            'master_level_names' => $masterLevelNames,
            'is_initial_options' => [
                ['key' => (string) MasterLevel::IS_INITIAL_YES, 'value' => trans('copytrading.boolean.yes')],
                ['key' => (string) MasterLevel::IS_INITIAL_NO, 'value' => trans('copytrading.boolean.no')],
            ],
            'has_commission_options' => [
                ['key' => '1', 'value' => trans('copytrading.boolean.yes')],
                ['key' => '0', 'value' => trans('copytrading.boolean.no')],
            ],
            'master_statuses' => [
                ['key' => (string) MasterAccount::STATUS_CONFIRMING, 'value' => $this->statusText(MasterAccount::STATUS_CONFIRMING)],
                ['key' => (string) MasterAccount::STATUS_SUCCESS,    'value' => $this->statusText(MasterAccount::STATUS_SUCCESS)],
                ['key' => (string) MasterAccount::STATUS_REJECT,     'value' => $this->statusText(MasterAccount::STATUS_REJECT)],
                ['key' => (string) MasterAccount::STATUS_OFFLINE,    'value' => $this->statusText(MasterAccount::STATUS_OFFLINE)],
                ['key' => (string) MasterAccount::STATUS_HIDE,       'value' => $this->statusText(MasterAccount::STATUS_HIDE)],
            ],
            'master_types' => [
                ['key' => (string) MasterAccount::IS_PRIVATE_PUBLIC, 'value' => trans('copytrading.master_type.0')],
                ['key' => (string) MasterAccount::IS_PRIVATE_YES,    'value' => trans('copytrading.master_type.1')],
            ],
            'follow_statuses' => [
                ['key' => (string) Copy::STATUS_FOLLOWING, 'value' => $this->followStatusText(Copy::STATUS_FOLLOWING)],
                ['key' => (string) Copy::STATUS_ENDED, 'value' => $this->followStatusText(Copy::STATUS_ENDED)],
            ],
            'trade_account_types' => array_map(
                fn (int $type): array => [
                    'key' => (string) $type,
                    'value' => $this->resolveTradeAccountTypeName($type),
                ],
                $this->masterTradeAccountTypes()
            ),
        ];
    }

    /**
     * @return array{status_options: array<int, array{key:string,value:string}>}
     */
    public function getMasterApplicationOptions(): array
    {
        return [
            'status_options' => [
                [
                    'key' => (string) MasterApplication::STATUS_PROCESSING,
                    'value' => (string) trans('copytrading.master_application_status.'.MasterApplication::STATUS_PROCESSING),
                ],
                [
                    'key' => (string) MasterApplication::STATUS_APPROVED,
                    'value' => (string) trans('copytrading.master_application_status.'.MasterApplication::STATUS_APPROVED),
                ],
                [
                    'key' => (string) MasterApplication::STATUS_REJECTED,
                    'value' => (string) trans('copytrading.master_application_status.'.MasterApplication::STATUS_REJECTED),
                ],
            ],
        ];
    }

    /**
     * @param array{
     *   user_code?: int|string|null,
     *   status?: int|string|null,
     *   operator_name?: string|null,
     *   applied_at_start?: string|null,
     *   applied_at_end?: string|null
     * } $filters
     * @return array{data: array<int, array<string, mixed>>, total_count: int}
     */
    public function getMasterApplications(array $filters, int $limit, int $offset): array
    {
        $reviewedByIds = null;
        $operatorName = trim((string) ($filters['operator_name'] ?? ''));
        if ($operatorName !== '') {
            $reviewedByIds = $this->adminUserRepo->searchIdsByLoginName($operatorName);
        }

        [$rows, $total] = $this->copyTradingRepo->getMasterApplications([
            'user_code' => $filters['user_code'] ?? null,
            'status' => $filters['status'] ?? null,
            'reviewed_by_ids' => $reviewedByIds,
            'applied_at_start' => $filters['applied_at_start'] ?? null,
            'applied_at_end' => $filters['applied_at_end'] ?? null,
        ], $limit, $offset);

        if ($rows->isEmpty()) {
            return [
                'data' => [],
                'total_count' => $total,
            ];
        }

        $reviewedByIds = $rows
            ->map(static fn (object $row): int => (int) ($row->reviewed_by ?? 0))
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
        $adminNames = $this->adminUserRepo->getColumnByIds($reviewedByIds, 'login_name');

        $data = $rows->map(function (object $row) use ($adminNames): array {
            $status = (int) ($row->status ?? MasterApplication::STATUS_PROCESSING);
            $reviewedBy = (int) ($row->reviewed_by ?? 0);

            return [
                'id' => (int) ($row->id ?? 0),
                'user_code' => (int) ($row->user_code ?? 0),
                'trade_platform' => (string) ($row->trade_platform ?? ''),
                'project' => (string) ($row->project ?? ''),
                'display_name' => (string) ($row->display_name ?? ''),
                'project_type' => (string) ($row->project_type ?? ''),
                'profit_share_rate' => $this->formatAmount((string) ($row->profit_share_rate ?? '0')),
                'initial_funding' => $this->formatAmount((string) ($row->initial_funding ?? '0')),
                'bio' => (string) ($row->bio ?? ''),
                'operator_name' => (string) ($adminNames[$reviewedBy] ?? ''),
                'rejection_reason' => (string) ($row->rejection_reason ?? ''),
                'applied_at' => $this->formatDateTime($row->applied_at ?? null),
                'status' => $status,
                'status_text' => $this->masterApplicationStatusText($status),
            ];
        })->all();

        return [
            'data' => $data,
            'total_count' => $total,
        ];
    }

    public function reviewMasterApplication(int $id, int $status, ?string $rejectionReason): void
    {
        if (! in_array($status, [MasterApplication::STATUS_APPROVED, MasterApplication::STATUS_REJECTED], true)) {
            throw new BusinessException(trans('public.message.data_error'));
        }

        DB::connection(DB_API)->transaction(function () use ($id, $status, $rejectionReason): void {
            $application = $this->copyTradingRepo->findMasterApplicationForUpdate($id);
            if (! $application) {
                throw new BusinessException(trans('public.message.non_existent'));
            }

            $before = [
                'status' => (int) ($application->status ?? 0),
                'status_text' => $this->masterApplicationStatusText((int) ($application->status ?? 0)),
                'rejection_reason' => (string) ($application->rejection_reason ?? ''),
                'reviewed_at' => $this->formatDateTime($application->reviewed_at ?? null),
                'reviewed_by' => (int) ($application->reviewed_by ?? 0),
            ];

            if ((int) ($application->status ?? 0) !== MasterApplication::STATUS_PROCESSING) {
                throw new BusinessException(trans('public.message.state_error'));
            }

            $application->status = $status;
            $application->reviewed_at = now();
            $application->reviewed_by = $this->operatorId();
            $application->rejection_reason = $status === MasterApplication::STATUS_REJECTED
                ? trim((string) $rejectionReason)
                : null;
            $this->copyTradingRepo->saveMasterApplication($application);

            $after = [
                'status' => $status,
                'status_text' => $this->masterApplicationStatusText($status),
                'rejection_reason' => (string) ($application->rejection_reason ?? ''),
                'reviewed_at' => $this->formatDateTime($application->reviewed_at ?? null),
                'reviewed_by' => (int) ($application->reviewed_by ?? 0),
            ];

            $recorded = $this->userOperationLogRepo->addRecord(
                $id,
                UserOperationLog::TARGET_TYPE_MASTER_APPLICATION,
                json_encode(
                    [
                        'user_code' => (int) ($application->user_code ?? 0),
                        'before' => $before,
                        'after' => $after,
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                UserOperationLog::ACTION_TYPE_UPDATE,
                (int) ($application->user_code ?? 0)
            );

            if (! $recorded) {
                throw new BusinessException(trans('public.message.state_error'));
            }
        });
    }

    public function deleteMasterLevel(int $levelId): void
    {
        DB::connection(DB_API)->transaction(function () use ($levelId): void {
            $level = $this->copyTradingRepo->findMasterLevelById($levelId);
            if (! $level) {
                throw new BusinessException(trans('public.message.non_existent'));
            }

            if ($this->copyTradingRepo->existsMasterAccountByLevelId($levelId)) {
                throw new BusinessException(trans('copytrading.message.level_has_master_users_cannot_delete'));
            }

            $deletedLevelPos = (int) ($level->level_pos ?? 0);
            $deleted = $this->copyTradingRepo->deleteMasterLevel($levelId);
            if ($deleted <= 0) {
                throw new BusinessException(trans('public.message.non_existent'));
            }

            $this->copyTradingRepo->decrementMasterLevelPosGreaterThan($deletedLevelPos);
        });
    }

    public function createMasterLevel(array $payload): void
    {
        $normalized = $this->normalizeMasterLevelPayload($payload);

        DB::connection(DB_API)->transaction(function () use ($normalized): void {
            $nextLevelPos = $this->copyTradingRepo->getMaxMasterLevelPos() + 1;
            $this->assertUniqueMasterLevelName($normalized['name']);
            $this->assertStaircaseForTarget($normalized, $nextLevelPos, null);
            if (($normalized['is_initial'] ?? MasterLevel::IS_INITIAL_NO) === MasterLevel::IS_INITIAL_YES) {
                $this->assertSingleInitialMasterLevel(null);
            }

            $this->copyTradingRepo->createMasterLevel([
                'name' => $normalized['name'],
                'level_pos' => $nextLevelPos,
                'is_initial' => $normalized['is_initial'] ?? MasterLevel::IS_INITIAL_NO,
                'status' => $normalized['status'],
                'balance_threshold' => $normalized['balance_threshold'],
                'order_count_30d_threshold' => $normalized['order_count_30d_threshold'],
                'profit_rate_30d_threshold' => $normalized['profit_rate_30d_threshold'],
                'follower_fund_threshold' => $normalized['follower_fund_threshold'],
                'follower_count_threshold' => $normalized['follower_count_threshold'],
                'commission_rate' => $normalized['commission_rate'],
                'fee_commission_rate' => $normalized['fee_commission_rate'],
                'max_investable_amount' => $normalized['max_investable_amount'],
            ]);
        });
    }

    public function updateMasterLevel(int $levelId, array $payload): void
    {
        $normalized = $this->normalizeMasterLevelPayload($payload);

        DB::connection(DB_API)->transaction(function () use ($levelId, $normalized): void {
            $level = $this->copyTradingRepo->findMasterLevelById($levelId);
            if (! $level) {
                throw new BusinessException(trans('public.message.non_existent'));
            }

            $levelPos = (int) ($level->level_pos ?? 0);
            $this->assertUniqueMasterLevelName($normalized['name'], $levelId);
            $this->assertStaircaseForTarget($normalized, $levelPos, $levelId);
            if ($normalized['is_initial'] === MasterLevel::IS_INITIAL_YES) {
                $this->assertSingleInitialMasterLevel($levelId);
            }

            $updatePayload = [
                'name' => $normalized['name'],
                'status' => $normalized['status'],
                'balance_threshold' => $normalized['balance_threshold'],
                'order_count_30d_threshold' => $normalized['order_count_30d_threshold'],
                'profit_rate_30d_threshold' => $normalized['profit_rate_30d_threshold'],
                'follower_fund_threshold' => $normalized['follower_fund_threshold'],
                'follower_count_threshold' => $normalized['follower_count_threshold'],
                'commission_rate' => $normalized['commission_rate'],
                'fee_commission_rate' => $normalized['fee_commission_rate'],
                'max_investable_amount' => $normalized['max_investable_amount'],
            ];

            if ($normalized['is_initial'] !== null) {
                $updatePayload['is_initial'] = $normalized['is_initial'];
            }

            $updated = $this->copyTradingRepo->updateMasterLevelById($levelId, $updatePayload);

            if ($updated <= 0) {
                throw new BusinessException(trans('public.message.non_existent'));
            }
        });
    }

    private function parseLevelPos(string $masterLevel): ?int
    {
        if (preg_match('/(\d+)/', $masterLevel, $matches) !== 1) {
            return null;
        }

        $levelPos = (int) ($matches[1] ?? 0);

        return $levelPos > 0 ? $levelPos : null;
    }

    private function formatAmount(string $value): string
    {
        return $this->trimTrailingZeros($value);
    }

    /**
     * @param  array<int, int>  $tradeAccountIds
     * @return array<int, int>
     */
    private function normalizeTradeAccountIds(?int $tradeAccountId, array $tradeAccountIds): array
    {
        $ids = $tradeAccountIds;
        if ($tradeAccountId !== null && $tradeAccountId > 0) {
            $ids[] = $tradeAccountId;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            throw new BusinessException(trans('public.message.in_complete'));
        }

        return $ids;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function normalizeProtectionTimeRange(mixed $startedAt, mixed $endedAt): array
    {
        $startedAt = $this->normalizeDateTimeInput($startedAt);
        $endedAt = $this->normalizeDateTimeInput($endedAt);

        if ($startedAt !== null && $endedAt !== null && strtotime($startedAt) > strtotime($endedAt)) {
            throw new BusinessException(trans('public.message.wrong_time_select'));
        }

        return [$startedAt, $endedAt];
    }

    private function normalizeDateTimeInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            throw new BusinessException(trans('public.message.data_error'));
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function formatPercent(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        if ($formatted === '') {
            $formatted = '0';
        }

        return $formatted;
    }

    private function trimTrailingZeros(string $value): string
    {
        $value = trim($value);
        if (! str_contains($value, '.')) {
            return $value === '' ? '0' : $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }

    public function statusText(int $status): string
    {
        $translationKey = "copytrading.master_status.$status";
        $translated = trans($translationKey);

        if ($translated !== $translationKey) {
            return (string) $translated;
        }

        return '-';
    }

    private function masterApplicationStatusText(int $status): string
    {
        $translationKey = "copytrading.master_application_status.$status";
        $translated = trans($translationKey);

        if ($translated !== $translationKey) {
            return (string) $translated;
        }

        return '-';
    }

    private function operatorId(): int
    {
        return (int) (Auth::id() ?? 0);
    }

    public function privateText(int $isPrivate): string
    {
        $translationKey = "copytrading.master_private.$isPrivate";
        $translated = trans($translationKey);

        if ($translated !== $translationKey) {
            return (string) $translated;
        }

        return '-';
    }

    public function protectionStatusText(int $status): string
    {
        return match ($status) {
            MasterAccount::PROTECTION_STATUS_ACTIVE => trans('copytrading.protection_status.active'),
            MasterAccount::PROTECTION_STATUS_ENDED => trans('copytrading.protection_status.ended'),
            default => trans('copytrading.protection_status.inactive'),
        };
    }

    /**
     * @param array{
     *   order_id?: string|null,
     *   master_user_code?: int|string|null,
     *   follower_user_code?: int|string|null,
     *   master_level?: string|null,
     *   master_trade_account_id?: int|string|null,
     *   master_trade_account_type?: int|string|null,
     *   follower_trade_account_id?: int|string|null,
     *   close_time_start?: string|null,
     *   close_time_end?: string|null,
     *   profit_time_start?: string|null,
     *   profit_time_end?: string|null,
     *   status?: int|string|null
     * } $filters
     * @return array{data: array<int, array<string,mixed>>, total_count:int}
     */
    public function getCommissionRecords(array $filters, int $limit, int $offset): array
    {
        $levelPos = ! empty($filters['master_level']) ? $this->parseLevelPos((string) $filters['master_level']) : null;
        [$rows, $total] = $this->copyTradingRepo->getCommissionRecords($filters, $limit, $offset, $levelPos);

        $data = $rows->map(function ($row): array {
            $closeTime = $this->formatDateTime($row->close_time ?? null);
            if ($closeTime === '') {
                $closeTime = $this->resolveCloseTime((array) ($row->copy_order_info ?? []));
            }
            $status = (int) ($row->status ?? 0);
            $masterTradeAccountType = $row->master_trade_account_type ?? null;
            $followerTradeAccountType = $row->follower_trade_account_type ?? null;
            $profitTime = $this->formatDateTime($row->settled_at ?? null);

            // 手续费取自平仓快照：应收=减免前原始开平仓费，实收=实际扣收的开平仓费（手续费减免券减免后）
            $order = (array) (($row->copy_order_info ?? [])['order'] ?? []);
            $receivableFeeAmount = round(abs((float) ($order['open_fee_original'] ?? 0) + (float) ($order['close_fee_original'] ?? 0)), 2);
            $actualFeeAmount = round(abs((float) ($order['open_fee'] ?? 0) + (float) ($order['close_fee'] ?? 0)), 2);

            return [
                'order_id' => (string) ($row->follower_ticket ?? ''),
                'follower_user_code' => (int) ($row->follower_user_code ?? 0),
                'master_user_code' => (int) ($row->master_user_code ?? 0),
                'master_trade_account_id' => (int) ($row->master_trade_account_id ?? 0),
                'current_profit_amount' => $this->formatAmount((string) ($row->profit_amount ?? '0')),
                'profit_amount' => $this->formatAmount((string) ($row->profit_amount ?? '0')),
                'profit_share_amount' => $this->formatAmount((string) ($row->commission_amount ?? '0')),
                'profit_share_rate' => $this->formatPercent(((float) ($row->commission_rate ?? 0)) * 100),
                'receivable_fee_amount' => $this->formatAmount((string) $receivableFeeAmount),
                'actual_fee_amount' => $this->formatAmount((string) $actualFeeAmount),
                'fee_commission_amount' => $this->formatAmount((string) ($row->fee_commission_amount ?? '0')),
                'fee_commission_rate' => $this->formatPercent(((float) ($row->fee_commission_rate ?? 0)) * 100),
                'fee_commission_time' => $profitTime,
                'master_level' => sprintf('Level%d', (int) ($row->level_pos ?? 0)),
                'master_trade_account_type' => (int) ($masterTradeAccountType ?? 0),
                'master_trade_account_type_name' => $masterTradeAccountType === null ? '' : $this->resolveTradeAccountTypeName((int) $masterTradeAccountType),
                'follower_trade_account_id' => (int) ($row->follower_trade_account_id ?? 0),
                'follower_trade_account_type' => (int) ($followerTradeAccountType ?? 0),
                'follower_trade_account_type_name' => $followerTradeAccountType === null ? '' : $this->resolveTradeAccountTypeName((int) $followerTradeAccountType),
                'close_time' => $closeTime,
                'profit_time' => $profitTime,
                'status' => $status,
                'status_text' => $this->commissionStatusText($status),
            ];
        })->all();

        return [
            'data' => $data,
            'total_count' => $total,
        ];
    }

    public function commissionStatusText(int $status): string
    {
        $key = match ($status) {
            MasterCommissionRecord::STATUS_PENDING => 'pending',
            MasterCommissionRecord::STATUS_SETTLED => 'settled',
            default => 'unknown',
        };

        return (string) trans("copytrading.commission_status.$key");
    }

    /**
     * @param array{
     *   master_user_code?: int|string|null,
     *   master_trade_account_id?: int|string|null,
     *   master_trade_account_type?: int|string|null,
     *   follower_user_code?: int|string|null,
     *   follow_status?: int|string|null,
     *   has_commission?: int|string|null,
     *   copy_start_time_start?: string|null,
     *   copy_start_time_end?: string|null,
     *   copy_end_time_start?: string|null,
     *   copy_end_time_end?: string|null
     * } $filters
     * @return array{data: array<int, array<string,mixed>>, total_count:int}
     */
    public function getFollowRelations(array $filters, int $limit, int $offset): array
    {
        [$rows, $total] = $this->copyTradingRepo->getFollowRelations($filters, $limit, $offset);
        $currentInvestAmounts = $this->loadFollowRelationCurrentInvestAmounts($rows);

        $followerLogins = [];
        foreach ($rows as $row) {
            $followerLogin = (int) ($row->follower_trade_account_id ?? 0);
            if ($followerLogin > 0) {
                $followerLogins[$followerLogin] = true;
            }
        }
        $directUserCodes = $this->userRepo->getDirectCustomerUserCodesByTradeAccountIds(array_keys($followerLogins));

        $data = [];
        foreach ($rows as $index => $row) {
            $status = $this->normalizeFollowStatus((int) ($row->follow_status ?? 0));
            $levelPos = (int) ($row->level_pos ?? 0);
            $masterTradeAccountType = $row->master_trade_account_type ?? null;
            $followerTradeAccountType = $row->follower_trade_account_type ?? null;
            $hasCommission = $this->resolveHasCommission($status, (int) ($row->follower_trade_account_id ?? 0), $directUserCodes);

            $data[] = [
                'master_user_code' => (int) ($row->master_user_code ?? 0),
                'follower_user_code' => (int) ($row->follower_user_code ?? 0),
                'master_trade_account_id' => (int) ($row->master_trade_account_id ?? 0),
                'follower_trade_account_id' => (int) ($row->follower_trade_account_id ?? 0),
                'master_trade_account_type_name' => $masterTradeAccountType === null ? '' : $this->resolveTradeAccountTypeName((int) $masterTradeAccountType),
                'follower_trade_account_type_name' => $followerTradeAccountType === null ? '' : $this->resolveTradeAccountTypeName((int) $followerTradeAccountType),
                'invest_amount' => $this->formatAmount((string) ($row->invest_amount ?? '0')),
                'current_invest_amount' => $this->formatAmount((string) ($currentInvestAmounts[$index] ?? 0)),
                'master_level' => $levelPos > 0 ? sprintf('Level%d', $levelPos) : '-',
                'copy_start_time' => $this->formatDateTime($row->copy_start_time ?? null),
                'copy_end_time' => $this->formatDateTime($row->copy_end_time ?? null),
                'follow_status' => $status,
                'follow_status_text' => $this->followStatusText($status),
                'has_commission' => $hasCommission,
                'has_commission_text' => (string) trans('copytrading.boolean.'.($hasCommission === 1 ? 'yes' : 'no')),
            ];
        }

        return [
            'data' => $data,
            'total_count' => $total,
        ];
    }

    /**
     * 判断跟随关系当前是否可对高手产生分佣。
     *
     * 跟随者对高手可产生分佣需同时满足：跟随关系仍在跟随中，
     * 且跟随者是直客（本人不是代理、也没有上级代理归属）。
     *
     * @param  array<int, int>  $directUserCodes  直客交易账号 => 用户ID 映射
     */
    private function resolveHasCommission(int $followStatus, int $followerTradeAccountId, array $directUserCodes): int
    {
        if ($followStatus !== Copy::STATUS_FOLLOWING || $followerTradeAccountId <= 0) {
            return 0;
        }

        return isset($directUserCodes[$followerTradeAccountId]) ? 1 : 0;
    }

    /**
     * @param  array<int, object>  $rows
     * @return array<int, float>
     */
    private function loadFollowRelationCurrentInvestAmounts(array $rows): array
    {
        $followingLogins = [];
        $endedLogins = [];
        foreach ($rows as $row) {
            $followerLogin = (int) ($row->follower_trade_account_id ?? 0);
            if ($followerLogin <= 0) {
                continue;
            }

            $status = $this->normalizeFollowStatus((int) ($row->follow_status ?? 0));
            if ($status === Copy::STATUS_FOLLOWING) {
                $followingLogins[] = $followerLogin;
            } else {
                $endedLogins[] = $followerLogin;
            }
        }

        $equities = $followingLogins === []
            ? []
            : $this->loadCurrentFollowMetrics(array_values(array_unique($followingLogins)));
        $netDeposits = $endedLogins === []
            ? []
            : $this->balanceFlowRepo->getNetDeposit(array_values(array_unique($endedLogins)), 6, 2, 4);

        $amounts = [];
        foreach ($rows as $index => $row) {
            $followerLogin = (int) ($row->follower_trade_account_id ?? 0);
            if ($followerLogin <= 0) {
                $amounts[$index] = 0;

                continue;
            }

            $status = $this->normalizeFollowStatus((int) ($row->follow_status ?? 0));
            if ($status === Copy::STATUS_FOLLOWING) {
                $amounts[$index] = (float) ($equities[$followerLogin] ?? 0);

                continue;
            }

            $copyAmount = (float) ($row->invest_amount ?? 0);
            $netDeposit = (float) ($netDeposits[$followerLogin] ?? 0);
            $amounts[$index] = round($copyAmount - $netDeposit, 2);
        }

        return $amounts;
    }

    private function normalizeFollowStatus(int $status): int
    {
        return in_array($status, [Copy::STATUS_FOLLOWING, Copy::STATUS_ENDED], true) ? $status : Copy::STATUS_ENDED;
    }

    public function followStatusText(int $status): string
    {
        $key = match ($status) {
            Copy::STATUS_FOLLOWING => 'following',
            Copy::STATUS_ENDED => 'ended',
            default => 'ended',
        };

        return (string) trans("copytrading.follow_status.$key");
    }

    private function resolveCloseTime(array $copyOrderInfo): string
    {
        foreach (['close_time', 'order_close_time', 'closed_at'] as $key) {
            if (! empty($copyOrderInfo[$key])) {
                return $this->formatDateTime($copyOrderInfo[$key]);
            }
        }

        return '';
    }

    private function toTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }

    private function formatDateTime(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $timestamp = (float) $value;
            if ($timestamp <= 0) {
                return '';
            }

            if ($timestamp > 9999999999) {
                $timestamp /= 1000;
            }

            return Carbon::createFromTimestamp(
                (int) floor($timestamp),
                date_default_timezone_get()
            )->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return '';
        }
    }

    private function currentLang(): string
    {
        return (string) (app()->getLocale() ?: config('app.locale', 'en_US'));
    }

    /**
     * @param  array<string, string>|string|null  $value
     */
    private function resolveLocalizedName(array|string|null $value, string $lang): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return '';
        }

        return (string) ($value[$lang] ?? $value['en_US'] ?? reset($value) ?: '');
    }

    /**
     * @return array{
     *   name: array<string, string>,
     *   status: int,
     *   balance_threshold: string|null,
     *   order_count_30d_threshold: int|null,
     *   profit_rate_30d_threshold: string|null,
     *   follower_fund_threshold: string|null,
     *   follower_count_threshold: int|null,
     *   commission_rate: string,
     *   fee_commission_rate: string,
     *   max_investable_amount: string,
     *   is_initial: int|null
     * }
     */
    private function normalizeMasterLevelPayload(array $payload): array
    {
        $name = $this->normalizeLocaleConfigs($payload['locale_configs'] ?? []);
        $orderCount = $this->nullableInt($payload['order_count_30d_threshold'] ?? null);
        $followerFundThreshold = $this->normalizeNullableDecimalString($payload['follower_fund_threshold'] ?? null);
        $followerCount = $this->nullableInt($payload['follower_count_threshold'] ?? null);
        $maxInvestableAmount = $this->normalizeDecimalString($payload['max_invest_amount'] ?? 0);

        if ($followerFundThreshold !== null && (float) $maxInvestableAmount < (float) $followerFundThreshold) {
            throw new BusinessException(trans('copytrading.message.max_invest_amount_gte_follower_fund'));
        }

        return [
            'name' => $name,
            'status' => (int) ($payload['status'] ?? 1),
            'balance_threshold' => $this->normalizeNullableDecimalString($payload['balance_threshold'] ?? null),
            'order_count_30d_threshold' => $orderCount,
            'profit_rate_30d_threshold' => $this->normalizeNullableRateToDecimal($payload['profit_rate_30d_threshold'] ?? null),
            'follower_fund_threshold' => $followerFundThreshold,
            'follower_count_threshold' => $followerCount,
            'commission_rate' => $this->normalizeRateToDecimal($payload['profit_share_rate'] ?? 0),
            'fee_commission_rate' => $this->normalizeRateToDecimal($payload['fee_commission_rate'] ?? 0),
            'max_investable_amount' => $maxInvestableAmount,
            'is_initial' => $this->normalizeOptionalInitial($payload),
        ];
    }

    /**
     * @param  array<string, string>  $names
     */
    private function assertUniqueMasterLevelName(array $names, ?int $ignoreId = null): void
    {
        if ($this->copyTradingRepo->existsByLocalizedNames($names, $ignoreId)) {
            throw new BusinessException(trans('public.message.already_exist'));
        }
    }

    private function assertSingleInitialMasterLevel(?int $ignoreId): void
    {
        $initialLevel = $this->copyTradingRepo->findInitialMasterLevel($ignoreId);
        if (! $initialLevel) {
            return;
        }

        throw new BusinessException(trans('copytrading.message.initial_level_exists', [
            'level_code' => sprintf('Level%d', (int) ($initialLevel->level_pos ?? 0)),
        ]));
    }

    /**
     * @param  array<string, string|int|null>  $target
     */
    private function assertStaircaseForTarget(array $target, int $targetLevelPos, ?int $targetLevelId): void
    {
        $prev = $this->copyTradingRepo->findPrevMasterLevel($targetLevelPos);
        if ($prev && (int) ($prev->id ?? 0) !== $targetLevelId) {
            $this->assertGreaterThan($target, $prev, true);
        }

        $next = $this->copyTradingRepo->findNextMasterLevel($targetLevelPos);
        if ($next && (int) ($next->id ?? 0) !== $targetLevelId) {
            $this->assertGreaterThan($target, $next, false);
        }
    }

    /**
     * @param  array<string, string|int|null>  $target
     */
    private function assertGreaterThan(array $target, object $compared, bool $mustBeGreater): void
    {
        $rules = [
            'balance_threshold' => ['label' => 'balance_threshold', 'value' => $target['balance_threshold'] ?? null, 'compared' => $compared->balance_threshold ?? null],
            'order_count_30d_threshold' => ['label' => 'order_count_30d_threshold', 'value' => $target['order_count_30d_threshold'] ?? null, 'compared' => $compared->order_count_30d_threshold ?? null],
            'profit_rate_30d_threshold' => ['label' => 'profit_rate_30d_threshold', 'value' => $target['profit_rate_30d_threshold'] ?? null, 'compared' => $compared->profit_rate_30d_threshold ?? null],
        ];

        foreach ($rules as $field => $rule) {
            if ($rule['value'] === null || $rule['compared'] === null) {
                continue;
            }

            $value = (float) $rule['value'];
            $comparedValue = (float) $rule['compared'];
            $ok = $mustBeGreater
                ? ($value > $comparedValue)
                : ($value < $comparedValue);
            if (! $ok) {
                $fieldName = (string) trans('copytrading.field.'.$field);
                $message = $mustBeGreater
                    ? (string) trans('copytrading.message.level_threshold_must_be_greater_than_previous', ['field' => $fieldName])
                    : (string) trans('copytrading.message.level_threshold_must_be_less_than_next', ['field' => $fieldName]);
                throw new BusinessException($message);
            }
        }
    }

    /**
     * @param  array<int, array{lang:string,name:string}>  $localeConfigs
     * @return array<string, string>
     */
    private function normalizeLocaleConfigs(array $localeConfigs): array
    {
        if (empty($localeConfigs)) {
            throw new BusinessException(trans('public.message.in_complete'));
        }

        $names = [];
        foreach ($localeConfigs as $config) {
            $lang = (string) ($config['lang'] ?? '');
            $name = trim((string) ($config['name'] ?? ''));
            if ($lang === '' || $name === '') {
                throw new BusinessException(trans('public.message.in_complete'));
            }
            if (isset($names[$lang])) {
                throw new BusinessException(trans('public.message.repeat_lang'));
            }
            $names[$lang] = $name;
        }

        if (! isset($names['en_US'])) {
            throw new BusinessException(trans('public.message.default_lang'));
        }

        return $names;
    }

    private function normalizeDecimalString(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new BusinessException(trans('public.message.data_error'));
        }

        return number_format((float) $value, MasterAccount::AMOUNT_DECIMAL_PLACES, '.', '');
    }

    private function normalizeNullableDecimalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeDecimalString($value);
    }

    private function normalizeRateToDecimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new BusinessException(trans('public.message.data_error'));
        }

        return number_format(((float) $value) / 100, MasterAccount::RATE_DECIMAL_PLACES, '.', '');
    }

    private function normalizeNullableRateToDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeRateToDecimal($value);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizeOptionalInitial(array $payload): ?int
    {
        if (! array_key_exists('is_initial', $payload)) {
            return null;
        }

        $value = $payload['is_initial'];
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value === MasterLevel::IS_INITIAL_YES
            ? MasterLevel::IS_INITIAL_YES
            : MasterLevel::IS_INITIAL_NO;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }
}
