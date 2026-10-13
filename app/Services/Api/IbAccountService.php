<?php

namespace App\Services\Api;

use App\Models\Blockmatrix\UserOperationLog;
use App\Models\Api\IbAccount;
use App\Repositories\Api\IbAccountRepository;
use App\Repositories\Api\IbCommissionRuleRepository;
use Scaffold\Framework\Enums\TypeIbApplicationStatus;
use Carbon\Carbon;
use App\Repositories\Api\IbApplicationRepository;
use App\Repositories\Api\IbLevelRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use BackedEnum;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Framework\Services\IbLevelOperateService;

class IbAccountService
{
    public function __construct(
        private UserRepository $userRepo,
        private IbApplicationRepository $applicationRepo,
        private IbAccountRepository $ibAccountRepo,
        private IbLevelRepository $levelRepo,
        private IbLevelOperateService $ibLevelOperateService,
        private UserOperationLogRepository $userOperationLogRepo,
    ) {
    }
    /**
     * 基于 ib_account 表的代理商列表
     *
     * @param array{
     *     user_code?: int|null,
     *     ib_id?: string|null,
     *     parent_ib_id?: string|null,
     *     level_name?: string|null,
     *     commission_status?: int|null,
     *     user_type?: string|null,
     *     protection_status?: int|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        $query = DB::connection(DB_API)
            ->table('ib_account as ia')
            ->leftJoin('ib_account as parent_ia', 'parent_ia.id', '=', 'ia.parent_id')
            ->leftJoin('ib_level as il', 'il.id', '=', 'ia.level_id')
            ->leftJoin('ib_commission_rule as icr', 'icr.id', '=', 'il.commission_rule_id')
            ->leftJoin('ib_commission_rule_version as icrv', 'icrv.id', '=', 'icr.current_version_id')
            ->leftJoin('ib_stat_snapshot as iss', 'iss.user_code', '=', 'ia.user_code')
            ->select([
                'ia.id',
                'ia.user_code',
                'ia.parent_id',
                'parent_ia.user_code as parent_user_code',
                'ia.is_primary',
                'ia.is_self_commission_enabled',
                'ia.level_id',
                'ia.activated_at',
                'ia.updated_at',
                'ia.level_protection_started_at',
                'ia.level_protection_ended_at',
                'il.name as level_name',
                'il.commission_rule_id',
                'icr.name as commission_rule_name',
                'icrv.status as commission_rule_status',
                DB::raw($this->teamSizeSelectExpression('iss')),
                DB::raw('IFNULL(iss.team_position_size, 0) as team_position_size'),
            ]);

        if (($filters['user_code'] ?? null) !== null) {
            $query->where('ia.user_code', (int) $filters['user_code']);
        }

        if (($filters['ib_id'] ?? '') !== '') {
            $accountId = $this->parseIbId((string) $filters['ib_id']);
            if ($accountId !== null) {
                $query->where('ia.id', $accountId);
            } else {
                return ['data' => [], 'total_count' => 0];
            }
        }

        if (($filters['parent_ib_id'] ?? '') !== '') {
            $parent = trim((string) $filters['parent_ib_id']);
            if ($parent === '-' || $parent === '0') {
                $query->whereNull('ia.parent_id');
            } elseif (ctype_digit($parent)) {
                $query->where('parent_ia.user_code', (int) $parent);
            } else {
                return ['data' => [], 'total_count' => 0];
            }
        }

        if (($filters['level_name'] ?? '') !== '') {
            $this->appendJsonNameKeywordFilter($query, (string) $filters['level_name'], 'il.name');
        }

        if (($filters['commission_status'] ?? null) !== null) {
            $expectedStatus = (int) $filters['commission_status'];
            if ($expectedStatus === IbAccount::COMMISSION_STATUS_ASSIGNED) {
                $query->whereNotNull('il.commission_rule_id')
                    ->where('icrv.status', IbCommissionRuleRepository::STATUS_APPROVED);
            } elseif ($expectedStatus === IbAccount::COMMISSION_STATUS_UNASSIGNED) {
                $query->where(function (Builder $builder): void {
                    $builder->whereNull('il.commission_rule_id')
                        ->orWhere('icrv.status', '!=', IbCommissionRuleRepository::STATUS_APPROVED);
                });
            }
        }

        if (($filters['user_type'] ?? '') !== '') {
            $expectedPrimary = $this->parseUserTypeToPrimary((string) $filters['user_type']);
            if ($expectedPrimary !== null) {
                $query->where('ia.is_primary', $expectedPrimary);
            }
        }

        if (($filters['protection_status'] ?? null) !== null && $filters['protection_status'] !== '') {
            $now = now()->toDateTimeString();
            $protectionStatus = (int) $filters['protection_status'];

            if ($protectionStatus === IbAccount::PROTECTION_STATUS_ACTIVE) {
                $query->whereNotNull('ia.level_protection_started_at')
                    ->where('ia.level_protection_started_at', '<=', $now)
                    ->where(function (Builder $builder) use ($now): void {
                        $builder->whereNull('ia.level_protection_ended_at')
                            ->orWhere('ia.level_protection_ended_at', '>=', $now);
                    });
            } elseif ($protectionStatus === IbAccount::PROTECTION_STATUS_INACTIVE) {
                $query->where(function (Builder $builder) use ($now): void {
                    $builder->whereNull('ia.level_protection_started_at')
                        ->orWhere('ia.level_protection_started_at', '>', $now)
                        ->orWhere('ia.level_protection_ended_at', '<', $now);
                });
            }
        }

        $total = (clone $query)->count('ia.id');
        $rows = $query->orderByDesc('ia.id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $nowTs = now()->timestamp;
        $lang = $this->currentLang();

        $data = $rows->map(function ($row) use ($lang, $nowTs): array {
            $commissionStatus = ! empty($row->commission_rule_id)
                && (int) ($row->commission_rule_status ?? 0) === IbCommissionRuleRepository::STATUS_APPROVED
                ? IbAccount::COMMISSION_STATUS_ASSIGNED : IbAccount::COMMISSION_STATUS_UNASSIGNED;
            $protectionStatus = $this->resolveProtectionStatus(
                $row->level_protection_started_at,
                $row->level_protection_ended_at,
                $nowTs
            );
            $selfCommissionEnabled = (int) ($row->is_self_commission_enabled ?? 0) === IbAccount::SELF_COMMISSION_ENABLED
                ? IbAccount::SELF_COMMISSION_ENABLED
                : IbAccount::SELF_COMMISSION_DISABLED;

            return [
                'user_code' => (int) ($row->user_code ?? 0),
                'ib_id' => (string) ((int) ($row->id ?? 0)),
                'is_primary' => (int) ($row->is_primary ?? 0),
                'is_self_commission_enabled' => $selfCommissionEnabled,
                'is_self_commission_enabled_text' => $this->booleanText($selfCommissionEnabled),
                'level_id' => (int) ($row->level_id ?? 0),
                'level_name' => $this->resolveLocalizedName($row->level_name, $lang),
                'parent_ib_id' => empty($row->parent_user_code) ? '-' : (string) ((int) $row->parent_user_code),
                'user_type' => (int) ($row->is_primary ?? 0) === 1 ? IbAccount::USER_TYPE_PARTNER : IbAccount::USER_TYPE_SUB_PARTNER,
                'team_size' => (int) ($row->team_size ?? 0),
                'team_position_size' => $this->formatDecimal($row->team_position_size ?? '0'),
                'activated_at' => $this->formatDateTime($row->activated_at),
                'updated_at' => $this->formatDateTime($row->updated_at),
                'commission_rule_id' => (int) ($row->commission_rule_id ?? 0),
                'commission_rule_name' => $this->resolveLocalizedName($row->commission_rule_name, $lang),
                'commission_status' => $commissionStatus,
                'commission_status_text' => $commissionStatus === IbAccount::COMMISSION_STATUS_ASSIGNED
                    ? trans('ib_level.commission_status.assigned')
                    : trans('ib_level.commission_status.unassigned'),
                'protection_status' => $protectionStatus,
                'protection_status_text' => $this->protectionStatusText($protectionStatus),
                'level_protection_started_at' => $this->formatDateTime($row->level_protection_started_at),
                'level_protection_ended_at' => $this->formatDateTime($row->level_protection_ended_at),
            ];
        })->all();

        return [
            'data' => $data,
            'total_count' => $total,
        ];
    }

    /**
     * 根据用户ID获取 IB 账户信息
     *
     * @param int|string $userCode 用户ID
     *
     * @return object|null
     */
    public function getInfo($userCode)
    {
        return $this->ibAccountRepo->getInfo($userCode);
    }

    /**
     * 判断指定用户是否为代理商
     *
     * @param int|string $userCode 用户ID
     *
     * @return bool
     */
    public function isIb($userCode){
        return $this->ibAccountRepo->exists($userCode, 'user_code');
    }

    /**
     * 基于 user_code 添加代理商
     *
     * @return array{user_code:int,ib_id:string,status_text:string}
     */
    public function store(int $userCode): array
    {
        $this->assertUserExists($userCode);

        $application = $this->applicationRepo->findLatestByUserCode($userCode);
        if ($application) {
            throw new \Exception($this->resolveApplicationMessage($application->status->value));
        }

        $accountId = $this->createPrimaryAccountForUser($userCode);

        return [
            'user_code' => $userCode,
            'ib_id' => $this->formatIbId($accountId),
            'status_text' => trans('public.message.ib_account_add_success'),
        ];
    }

    public function createPrimaryAccountForUser(int $userCode, mixed $initialStatStartAt = null): int
    {
        $this->assertUserExists($userCode);
        $this->assertIbAccountNotExists($userCode);
        $this->ibLevelOperateService->init($userCode, $this->operatorId());

        $account = $this->ibAccountRepo->findByUserCode($userCode);
        if (! $account) {
            throw new \Exception(trans('public.message.state_error'));
        }

        return (int) $account->id;
    }

    /**
     * @param array<int, mixed> $ibIds
     */
    public function updateLevel(
        ?string $ibId,
        array $ibIds,
        int $levelId,
        mixed $levelProtectionStartedAt,
        mixed $levelProtectionEndedAt
    ): void
    {
        $accountIds = $this->normalizeIbAccountIds($ibId, $ibIds);
        if ($levelId <= 0) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $targetLevel = $this->levelRepo->findById($levelId);
        $targetLevelStatus = $targetLevel && ($targetLevel->status ?? null) instanceof BackedEnum
            ? $targetLevel->status->value
            : (int) ($targetLevel->status ?? 0);
        if (! $targetLevel || $targetLevelStatus !== 1) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        $normalizedProtectionStartedAt = $this->normalizeDateTimeInput($levelProtectionStartedAt);
        $normalizedProtectionEndedAt = $this->normalizeDateTimeInput($levelProtectionEndedAt);

        DB::connection(DB_API)->transaction(function () use (
            $accountIds,
            $targetLevel,
            $levelId,
            $normalizedProtectionStartedAt,
            $normalizedProtectionEndedAt
        ): void {
            $payload = [
                'level_id' => $levelId,
                'level_protection_started_at' => $normalizedProtectionStartedAt,
                'level_protection_ended_at' => $normalizedProtectionEndedAt,
            ];

            foreach ($accountIds as $accountId) {
                $account = $this->ibAccountRepo->findByIdForUpdate($accountId);
                if (! $account) {
                    throw new \Exception(trans('public.message.non_existent'));
                }

                $beforeLevelId = (int) ($account->level_id ?? 0);
                $beforeLevel = $this->levelRepo->findById($beforeLevelId);
                $before = [
                    'ib_id' => $this->formatIbId((int) $account->id),
                    'level_id' => $beforeLevelId,
                    'level_name' => $this->resolveLocalizedName($beforeLevel?->name, $this->currentLang()),
                    'level_protection_started_at' => $this->formatDateTime($account->level_protection_started_at),
                    'level_protection_ended_at' => $this->formatDateTime($account->level_protection_ended_at),
                ];

                $updated = $this->ibAccountRepo->updateAccount((int) $account->id, $payload, $account);
                if (! $updated) {
                    throw new \Exception(trans('public.message.state_error'));
                }

                if ($beforeLevelId !== $levelId) {
                    $this->ibLevelOperateService->modify(
                        userCode: (int) $account->user_code,
                        beforeLevelId: $beforeLevelId,
                        afterLevelId: $levelId,
                        operatorId: $this->operatorId(),
                        statStartAt: $payload['level_protection_started_at'],
                        statEndAt: $payload['level_protection_ended_at'],
                        activeTraderCount: 0,
                        teamPositionSize: 0.0,
                    );
                    $after = [
                        'ib_id' => $this->formatIbId((int) $updated->id),
                        'level_id' => (int) ($updated->level_id ?? 0),
                        'level_name' => $this->resolveLocalizedName($targetLevel->name ?? null, $this->currentLang()),
                        'level_protection_started_at' => $this->formatDateTime($updated->level_protection_started_at),
                        'level_protection_ended_at' => $this->formatDateTime($updated->level_protection_ended_at),
                    ];

                    $recorded = $this->userOperationLogRepo->addRecord(
                        (int) $account->id,
                        UserOperationLog::TARGET_TYPE_IB_LEVEL,
                        json_encode(
                            ['before' => $before, 'after' => $after],
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ),
                        UserOperationLog::ACTION_TYPE_UPDATE
                    );
                    if (! $recorded) {
                        throw new \Exception(trans('public.message.state_error'));
                    }
                }
            }
        });
    }

    /**
     * 设置指定代理商的自己交易反佣开关
     *
     * @return array{ib_id:string,is_self_commission_enabled:int,is_self_commission_enabled_text:string}
     */
    public function updateSelfCommission(string $ibId, int $isEnabled): array
    {
        $accountId = $this->parseIbId($ibId);
        if ($accountId === null || $accountId <= 0) {
            throw new \Exception(trans('public.message.data_error'));
        }

        if (! in_array($isEnabled, [IbAccount::SELF_COMMISSION_DISABLED, IbAccount::SELF_COMMISSION_ENABLED], true)) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $updated = DB::connection(DB_API)->transaction(function () use ($accountId, $isEnabled) {
            $account = $this->ibAccountRepo->findByIdForUpdate($accountId);
            if (! $account) {
                throw new \Exception(trans('public.message.non_existent'));
            }

            return $this->ibAccountRepo->updateAccount(
                $accountId,
                ['is_self_commission_enabled' => $isEnabled],
                $account
            );
        });

        if (! $updated) {
            throw new \Exception(trans('public.message.state_error'));
        }

        $normalizedEnabled = (int) ($updated->is_self_commission_enabled ?? 0) === IbAccount::SELF_COMMISSION_ENABLED
            ? IbAccount::SELF_COMMISSION_ENABLED
            : IbAccount::SELF_COMMISSION_DISABLED;

        return [
            'ib_id' => $this->formatIbId($accountId),
            'is_self_commission_enabled' => $normalizedEnabled,
            'is_self_commission_enabled_text' => $this->booleanText($normalizedEnabled),
        ];
    }

    /**
     * 基于 ib_account 表的代理结构子层级节点
     *
     * @return array{
     *     current: array{
     *         user_code:int,
     *         level:string,
     *         ib_id:string,
     *         is_primary:int,
     *         user_type:string,
     *         team_size:int,
     *         team_position_size:string,
     *         activated_at:string,
     *         trader_count:int
     *     },
     *     data: array<int, array{
     *         user_code:int,
     *         level:string,
     *         ib_id:string,
     *         is_primary:int,
     *         user_type:string,
     *         team_size:int,
     *         team_position_size:string,
     *         activated_at:string,
     *         has_children:int,
     *         trader_count:int
     *     }>
     * }
     */
    public function getChildren(string $ibId): array
    {
        $accountId = $this->parseIbId($ibId);
        if ($accountId === null || $accountId <= 0) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $current = DB::connection(DB_API)
            ->table('ib_account as ia')
            ->leftJoin('ib_stat_snapshot as iss', 'iss.user_code', '=', 'ia.user_code')
            ->where('ia.id', $accountId)
            ->select([
                'ia.id',
                'ia.user_code',
                'ia.is_primary',
                'ia.depth',
                'ia.activated_at',
                DB::raw($this->teamSizeSelectExpression('iss')),
                DB::raw('IFNULL(iss.team_position_size, 0) as team_position_size'),
            ])
            ->first();

        if (! $current) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        $children = DB::connection(DB_API)
            ->table('ib_account as ia')
            ->leftJoin('ib_stat_snapshot as iss', 'iss.user_code', '=', 'ia.user_code')
            ->where('ia.parent_id', $accountId)
            ->select([
                'ia.id',
                'ia.user_code',
                'ia.is_primary',
                'ia.depth',
                'ia.activated_at',
                DB::raw($this->teamSizeSelectExpression('iss')),
                DB::raw('IFNULL(iss.team_position_size, 0) as team_position_size'),
                DB::raw('EXISTS(SELECT 1 FROM ib_account as c WHERE c.parent_id = ia.id) as has_children'),
            ])
            ->orderByDesc('ia.id')
            ->get();

        $ibUserCodes = array_merge(
            [(int) ($current->user_code ?? 0)],
            $children->map(static fn ($row): int => (int) ($row->user_code ?? 0))->all()
        );
        $directTraderCountMap = DB::connection(DB_API)
            ->table('user as u')
            ->leftJoin('ib_account as ia', 'ia.user_code', '=', 'u.user_code')
            ->whereIn('u.ib_affiliation', $ibUserCodes)
            ->whereNull('ia.id')
            ->groupBy('u.ib_affiliation')
            ->pluck(DB::raw('COUNT(*)'), 'u.ib_affiliation')
            ->toArray();

        return [
            'current' => [
                'user_code' => (int) ($current->user_code ?? 0),
                'level' => 'L' . (int) ($current->depth ?? 0),
                'ib_id' => (string) ((int) ($current->user_code ?? 0)),
                'is_primary' => (int) ($current->is_primary ?? 0),
                'user_type' => (int) ($current->is_primary ?? 0) === 1 ? IbAccount::USER_TYPE_PARTNER : IbAccount::USER_TYPE_SUB_PARTNER,
                'team_size' => (int) ($current->team_size ?? 0),
                'team_position_size' => $this->formatDecimal($current->team_position_size ?? '0'),
                'activated_at' => $this->formatDateTime($current->activated_at) ?? '',
                'trader_count' => (int) ($directTraderCountMap[(int) ($current->user_code ?? 0)] ?? 0),
            ],
            'data' => $children->map(function ($row) use ($directTraderCountMap): array {
                $userCode = (int) ($row->user_code ?? 0);

                return [
                    'user_code' => $userCode,
                    'level' => 'L' . (int) ($row->depth ?? 0),
                    'ib_id' => (string) $userCode,
                    'is_primary' => (int) ($row->is_primary ?? 0),
                    'user_type' => (int) ($row->is_primary ?? 0) === 1 ? IbAccount::USER_TYPE_PARTNER : IbAccount::USER_TYPE_SUB_PARTNER,
                    'team_size' => (int) ($row->team_size ?? 0),
                    'team_position_size' => $this->formatDecimal($row->team_position_size ?? '0'),
                    'activated_at' => $this->formatDateTime($row->activated_at) ?? '',
                    'has_children' => (int) ($row->has_children ?? 0),
                    'trader_count' => (int) ($directTraderCountMap[$userCode] ?? 0),
                ];
            })->all(),
        ];
    }

    /**
     * 基于 ib_account 的 Trader 列表
     *
     * @return array<string, mixed>
     */
    public function getTraders(string $ibId, int $limit, int $offset): array
    {
        $accountId = $this->parseIbId($ibId);
        if ($accountId === null || $accountId <= 0) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $ibAccount = $this->ibAccountRepo->findById($accountId);
        if (! $ibAccount) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        $query = DB::connection(DB_API)
            ->table('user as u')
            ->leftJoin('ib_account as ia', 'ia.user_code', '=', 'u.user_code')
            ->leftJoin('ib_stat_snapshot as iss', 'iss.user_code', '=', 'u.user_code')
            ->where('u.ib_affiliation', (int) $ibAccount->user_code)
            ->whereNull('ia.id');

        $total = (clone $query)->count('u.user_code');
        $rows = $query->select([
            'u.user_code',
            'u.created_at',
            DB::raw('IFNULL(iss.team_position_size, 0) as total_position_size'),
        ])->orderByDesc('u.user_code')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'data' => $rows->map(function ($row): array {
                return [
                    'user_code' => (int) ($row->user_code ?? 0),
                    'total_position_size' => $this->formatDecimal($row->total_position_size ?? '0'),
                    'registered_at' => $this->formatDateTime($row->created_at) ?? '',
                ];
            })->all(),
            'total_count' => $total,
        ];
    }

    /**
     * 获取 IB 代理商当前信息
     *
     * 说明:
     * - 基于 user_code 查询 ib_account、ib_level 及上级代理信息
     * - 当用户不是 IB 代理时，返回默认 Trader 信息
     * - 保护期根据 level_protection_started_at 与 level_protection_ended_at 判断
     *
     * @param array{
     *     user_code: int
     * } $filters
     *
     * @return array{
     *     is_ib:int,
     *     ib_id:int|string,
     *     is_primary:int,
     *     user_type:string,
     *     user_level:string,
     *     parent_ib_id:int|string,
     *     ib_level_name:string,
     *     activated_at:string,
     *     is_protection_period:int
     * }
     */
    public function getCurrentInfo(array $filters): array
    {
        $ibAccountInfo = $this->ibAccountRepo->getInfo($filters['user_code']);
        if (! $ibAccountInfo) {
            return [
                'is_ib'                => 0,
                'ib_id'                => '-',
                'is_primary'           => 0,
                'user_type'            => IbAccount::USER_TYPE_TRADER,
                'user_level'           => '-',
                'parent_ib_id'         => 0,
                'ib_level_name'        => '-',
                'activated_at'         => '-',
                'is_protection_period' => 0,
            ];
        }
        $user_type = $ibAccountInfo['is_primary'] ? IbAccount::USER_TYPE_PARTNER : IbAccount::USER_TYPE_SUB_PARTNER;
        if (time() >= $ibAccountInfo->level_protection_started_at && time() <= $ibAccountInfo->level_protection_ended_at) {
            $isProtectionPeriod = 1;
        } else {
            $isProtectionPeriod = 0;
        }
        $lang = $this->currentLang();
        $levelName = '';
        $levelId = (int) ($ibAccountInfo->level_id ?? 0);
        if ($levelId > 0) {
            $level = $this->levelRepo->findById($levelId);
            $levelName = $this->resolveLocalizedName($level?->name, $lang);
        }
        if ($levelName === '') {
            $levelName = $this->resolveLocalizedName($ibAccountInfo->level_name ?? null, $lang);
        }
        return [
            'is_ib'                => 1,
            'ib_id'                => $ibAccountInfo->id,
            'is_primary'           => $ibAccountInfo->is_primary,
            'user_type'            => $user_type,
            'user_level'           => 'L'.$ibAccountInfo->depth,
            'parent_ib_id'         => $ibAccountInfo->parent_user_code ?? 0,
            'ib_level_name'        => $levelName,
            'activated_at'         => Carbon::createFromTimestamp((int) $ibAccountInfo->activated_at)->format('Y-m-d H:i:s'),
            'is_protection_period' => $isProtectionPeriod,
        ];
    }

    /**
     * 校验代理用户是否允许划转
     *
     * 说明:
     * - 一级代理不允许划转
     * - 若传入目标代理，则不允许划转到当前代理链的下级节点
     *
     * @param int|string      $userCode       当前代理用户ID
     * @param int|string|null $targetUserCode 目标代理用户ID
     *
     * @return array{is_succ:bool,message:string}
     */
    public function checkTransferUser($userCode, $targetUserCode){
        // 判断是否是一级代理商，一级代理商无法进行划转
        $ibAccount = $this->ibAccountRepo->getInfo((int) $userCode);
        if ($ibAccount->is_primary) {
            return [
                'is_succ' => false,
                'message' => trans('user.message.transfer_is_not_allowed.0'),
            ];
        }

        // 如果target_user_code是null，是验证客户用户ID；如果传递了，是验证目标客户用户ID
        if (! is_null($targetUserCode)) {
            $targetIbAccount = $this->ibAccountRepo->getInfo((int) $targetUserCode);
            // 判断是否是同一个链上的划转，且从高层级向低层级划转
            if (str_contains($targetIbAccount->ib_path, $ibAccount->ib_path)){
                return [
                    'is_succ' => false,
                    'message' => trans('user.message.transfer_is_not_allowed.2'),
                ];
            }
        }
        return [
            'is_succ' => true,
            'message' => '',
        ];
    }

    /**
     * 将代理用户划转到新的上级代理
     *
     * 说明:
     * - 一级代理不可划转
     * - 不允许向当前代理链下级节点划转
     * - 会同步更新当前代理节点及其所有下级代理的 depth 和 ib_path
     *
     * @param int $userCode       当前代理用户ID
     * @param int $targetUserCode 目标上级代理用户ID
     *
     * @return array{is_succ:bool,message:string}
     */
    public function transfer($userCode, $targetUserCode)
    {
        // 判断是否是一级代理商，一级代理商无法进行划转
        $ibAccount = $this->ibAccountRepo->getInfo($userCode);
        if ($ibAccount->is_primary) {
            return [
                'is_succ' => false,
                'message' => trans('user.message.transfer_is_not_allowed.0'),
            ];
        }

        $targetIbAccount = $this->ibAccountRepo->getInfo($targetUserCode);
        // 判断是否是同一个链上的划转，且从高层级向低层级划转
        if (str_contains($targetIbAccount->ib_path, $ibAccount->ib_path)){
            return [
                'is_succ' => false,
                'message' => trans('user.message.transfer_is_not_allowed.2'),
            ];
        }

        $middleIbPath = $this->getMiddleIbPath($ibAccount->id, $ibAccount->ib_path);
        // 修改userCode自身的代理层级路径
        $newIbPath = $this->replacePathPrefix($ibAccount->ib_path, $middleIbPath, $targetIbAccount->ib_path);
        $this->ibAccountRepo->updateAccount($ibAccount->id, ['parent_id' => $targetIbAccount->id, 'depth' => $this->getDepth($newIbPath), 'ib_path' => $newIbPath]);

        // 根据递归ib_path来查找所有userCode的下属代理层级路径的链条，并整体修改下属代理层级路径链条
        $ibPaths = $this->ibAccountRepo->getIbPaths($ibAccount->ib_path);
        if ($ibPaths) {
            foreach ($ibPaths as $ibPath) {
                $newIbPath = $this->replacePathPrefix($ibPath['ib_path'], $middleIbPath, $targetIbAccount->ib_path);
                $this->ibAccountRepo->updateAccount($ibPath['id'], ['depth' => $this->getDepth($newIbPath), 'ib_path' => $newIbPath]);
            }
        }
        return [
            'is_succ' => true,
            'message' => '',
        ];
    }

    private function getDepth($ibPath): int
    {
        $ibPath = trim($ibPath, '/');
        // 防止空路径
        if ($ibPath === '') return 0;
        return count(explode('/', $ibPath));
    }

    private function getMiddleIbPath(int $ibAccountId, string $ibPath): string
    {
        $parts = explode('/', trim($ibPath, '/'));
        // 判断是否存在
        if (!in_array((string)$ibAccountId, $parts, true) && !in_array($ibAccountId, $parts, true)) {
            throw new \Exception(trans('user.message.transfer_is_not_allowed.3'));
        }

        $filtered = array_filter($parts, function ($v) use ($ibAccountId) {
            return (string)$v !== (string)$ibAccountId;
        });
        return '/' . implode('/', $filtered) . '/';
    }

    /**
     * 划转替换代理层级路径
     *
     * @param string $ibPath 代理层级
     * @param string $middleIbPath 目标代理账户id
     * @param string $targetIbPath 要替换的层级路径
     *
     * @return string
     *
     * 使用示例：
     * $ibPath = '/1/2/3/';
     * $middleIbPath = '/1/2/';
     * $targetIbPath = '/5/6/7/';
     * echo $result; // /5/6/7/3/;
     */
    private function replacePathPrefix(string $ibPath, string $middleIbPath, string $targetIbPath): string
    {
        $normalize = function (string $path): string {
            return '/' . trim($path, '/') . '/';
        };

        $ibPath = $normalize($ibPath);
        $middleIbPath = $normalize($middleIbPath);
        $targetIbPath = $normalize($targetIbPath);

        // 校验前缀
        if (strpos($ibPath, $middleIbPath) !== 0) {
            throw new \Exception(trans('user.message.transfer_is_not_allowed.4'));
        }
        // 截取剩余部分
        $remaining = substr($ibPath, strlen($middleIbPath));
        return $targetIbPath . ltrim($remaining, '/');
    }

    private function resolveApplicationMessage(int $status): string
    {
        return match ($status) {
            TypeIbApplicationStatus::Pending->value => trans('public.message.ib_application_pending'),
            TypeIbApplicationStatus::Approved->value => trans('public.message.ib_application_approved'),
            TypeIbApplicationStatus::Rejected->value => trans('public.message.ib_application_rejected'),
            default => trans('public.message.state_error'),
        };
    }

    private function assertUserExists(int $userCode): void
    {
        $user = $this->userRepo->firstBy('user_code', $userCode, ['user_code']);
        if (! $user) {
            throw new \Exception(trans('public.message.non_existent'));
        }
    }

    private function assertIbAccountNotExists(int $userCode): void
    {
        if ($this->ibAccountRepo->findByUserCode($userCode)) {
            throw new \Exception(trans('public.message.ib_account_exists'));
        }
    }

    private function formatIbId(int $accountId): string
    {
        return str_pad((string) $accountId, IbAccount::IB_ID_PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    private function currentLang(): string
    {
        return (string) (app()->getLocale() ?: config('app.locale', 'en_US'));
    }

    private function parseIbId(string $ibId): ?int
    {
        $normalized = ltrim(trim($ibId), '0');
        if ($normalized === '') {
            $normalized = '0';
        }
        if (! ctype_digit($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    /**
     * @param array<int, mixed> $ibIds
     * @return array<int, int>
     */
    private function normalizeIbAccountIds(?string $ibId, array $ibIds): array
    {
        $rawIds = $ibIds;
        if ($ibId !== null && trim($ibId) !== '') {
            $rawIds[] = $ibId;
        }

        $accountIds = [];
        foreach ($rawIds as $rawId) {
            if (! is_scalar($rawId)) {
                throw new \Exception(trans('public.message.data_error'));
            }

            $accountId = $this->parseIbId((string) $rawId);
            if ($accountId === null || $accountId <= 0) {
                throw new \Exception(trans('public.message.data_error'));
            }

            $accountIds[] = $accountId;
        }

        $accountIds = array_values(array_unique($accountIds));
        if ($accountIds === []) {
            throw new \Exception(trans('public.message.in_complete'));
        }

        return $accountIds;
    }

    private function normalizeDateTimeInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            throw new \Exception(trans('public.message.data_error'));
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function parseUserTypeToPrimary(string $userType): ?int
    {
        return match (strtolower(trim($userType))) {
            'partner' => 1,
            'sub partner', 'sub_partner', 'subpartner' => 0,
            default => null,
        };
    }

    private function resolveProtectionStatus(mixed $startedAt, mixed $endedAt, int $nowTs): int
    {
        $startTs = $this->toTimestamp($startedAt);
        $endTs = $this->toTimestamp($endedAt);

        if ($endTs !== null && $endTs < $nowTs) {
            return IbAccount::PROTECTION_STATUS_ENDED;
        }

        if ($startTs === null || $startTs > $nowTs) {
            return IbAccount::PROTECTION_STATUS_INACTIVE;
        }

        return IbAccount::PROTECTION_STATUS_ACTIVE;
    }

    private function protectionStatusText(int $status): string
    {
        return match ($status) {
            IbAccount::PROTECTION_STATUS_ACTIVE => '保护中',
            IbAccount::PROTECTION_STATUS_ENDED => '已结束',
            default => '未生效',
        };
    }

    private function toTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : $timestamp;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function formatDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $string = (string) $value;
        if (str_contains($string, '.')) {
            $string = rtrim(rtrim($string, '0'), '.');
        }

        return $string === '' ? '0' : $string;
    }

    private function booleanText(int $value): string
    {
        return (string) trans('user.boolean.' . ($value === 1 ? 'yes' : 'no'));
    }

    private function teamSizeSelectExpression(string $snapshotAlias): string
    {
        if ($this->hasIbCountColumn()) {
            return sprintf(
                '(IFNULL(%1$s.ib_count, 0) + IFNULL(%1$s.trader_count, 0)) as team_size',
                $snapshotAlias
            );
        }

        return sprintf('IFNULL(%s.trader_count, 0) as team_size', $snapshotAlias);
    }

    private function hasIbCountColumn(): bool
    {
        static $hasIbCount = null;
        if ($hasIbCount !== null) {
            return $hasIbCount;
        }

        $hasIbCount = Schema::connection(DB_API)->hasColumn('ib_stat_snapshot', 'ib_count');

        return $hasIbCount;
    }

    private function appendJsonNameKeywordFilter(Builder $query, string $keyword, string $column): void
    {
        $like = $this->like($keyword);
        $langs = array_keys(config('lang.languages', []));

        $query->where(function (Builder $builder) use ($column, $langs, $like): void {
            foreach ($langs as $lang) {
                $builder->orWhere("$column->$lang", 'like', $like);
            }
        });
    }

    private function like(string $value): string
    {
        $escaped = addcslashes($value, '%_');
        return sprintf('%%%s%%', $escaped);
    }

    /**
     * @param array<string, string>|string|null $value
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

    private function operatorId(): int
    {
        return (int) (Auth::id() ?? 0);
    }
}
