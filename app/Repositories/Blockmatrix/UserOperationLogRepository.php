<?php

namespace App\Repositories\Blockmatrix;

use Scaffold\Framework\Repositories\Repository;
use App\Models\Blockmatrix\UserOperationLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserOperationLogRepository extends Repository
{
    public function __construct(UserOperationLog $model)
    {
        parent::__construct($model);
    }

    /**
     * 批量获取客户的最后一次操作记录
     *
     * @param int|array $targetType  操作类型
     * @param array     $targetIds   用户ID
     * @param int|array $actionTypes 状态
     *
     * @return array
     */
    public function getBatchOperateInfo($targetType, $targetIds, $actionTypes = UserOperationLog::ACTION_TYPE_ADD)
    {
        $types = is_array($targetType) ? $targetType : [$targetType];
        $query = $this->getQuery()->whereIn('target_type', $types);
        if ($actionTypes != 0) {
            $query->whereIn('action_type', is_array($actionTypes) ? $actionTypes : [$actionTypes]);
        }

        $ids          = $query->whereIn('target_id', $targetIds)
            ->orderBy('id')
            ->pluck('id', 'target_id')
            ->toArray();
        $operateInfos = $this->getQuery()->whereIn('id', $ids)->get()->toArray();

        return array_column($operateInfos, null, 'target_id');
    }

    /**
     * 分页获取操作记录列表
     *
     * 查询结果包含:
     * - u.content: 操作日志内容
     * - u.created_at: 操作时间
     * - a.login_name: 操作人名称
     *
     * @param int $targetType 内容类型
     * @param int $userCode   用户ID
     * @param int $actionType 操作类型
     * @param int $limit      每页条数
     * @param int $offset     分页偏移量
     *
     * @return array{0: array<int, array{content:string,created_at:string,login_name:?string}>, 1: int}
     */
    public function getOperateList($targetType, $userCode, int $actionType, $limit, $offset): array
    {
         $query = $this->getQuery()->from('user_operation_log as u')
             ->leftJoin('admin_user as a', 'a.id', '=', 'u.operator_id')
            ->where('u.target_type', $targetType)
            ->where('u.action_type', $actionType)
            ->where('u.user_code', $userCode);
        $count = $query->count();

        $data = $query->select('u.content', 'u.created_at', 'a.login_name', 'u.user_code')
            ->orderBy('u.id', 'desc')
            ->offset($offset)->limit($limit)
            ->get()
            ->toArray();
        return [$data, $count];
    }

    public function getAll($targetType, $actionType, $targetIds): array
    {
        return $this->getQuery()
            ->leftJoin('admin_user', 'admin_user.id', '=', 'user_operation_log.operator_id')
            ->where('user_operation_log.target_type', $targetType)
            ->where('user_operation_log.action_type', $actionType)
            ->whereIn('user_operation_log.target_id', $targetIds)
            ->selectRaw('user_operation_log.id, user_operation_log.content, user_operation_log.target_id, admin_user.login_name')
            ->get()
            ->toArray();
    }

    /**
     * 按优惠券ID和券类别批量获取最后一次操作记录
     *
     * @param array<int, int> $targetIds
     * @param array<int, int> $couponTypes
     * @param int|array       $actionTypes
     * @return array<string, array{target_id:int,coupon_type:int,operator_id:int,operator_name:string,created_at:?string}>
     */
    public function getLatestCouponOperateInfo(array $targetIds, array $couponTypes, $actionTypes = [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]): array
    {
        $targetIds = array_values(array_unique(array_filter(array_map('intval', $targetIds), static fn (int $id): bool => $id > 0)));
        $couponTypes = array_values(array_unique(array_filter(array_map('intval', $couponTypes), static fn (int $type): bool => $type > 0)));
        if (empty($targetIds) || empty($couponTypes)) {
            return [];
        }

        $rows = $this->getQuery()
            ->from('user_operation_log as u')
            ->leftJoin('admin_user as a', 'a.id', '=', 'u.operator_id')
            ->where('u.target_type', UserOperationLog::TARGET_TYPE_COUPON)
            ->whereIn('u.target_id', $targetIds)
            ->whereIn('u.action_type', is_array($actionTypes) ? $actionTypes : [$actionTypes])
            ->where(function ($query) use ($couponTypes): void {
                foreach ($couponTypes as $couponType) {
                    $query->orWhereRaw("CAST(u.content->>'$.coupon_type' AS SIGNED) = ?", [$couponType]);
                }
            })
            ->orderByDesc('u.id')
            ->get([
                'u.target_id',
                'u.operator_id',
                'u.created_at',
                'a.login_name as operator_name',
                DB::raw("CAST(u.content->>'$.coupon_type' AS SIGNED) as coupon_type"),
            ])
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $targetId = (int) ($row['target_id'] ?? 0);
            $couponType = (int) ($row['coupon_type'] ?? 0);
            if ($targetId <= 0 || $couponType <= 0) {
                continue;
            }

            $key = $this->couponOperateKey($targetId, $couponType);
            if (isset($result[$key])) {
                continue;
            }

            $result[$key] = [
                'target_id' => $targetId,
                'coupon_type' => $couponType,
                'operator_id' => (int) ($row['operator_id'] ?? 0),
                'operator_name' => (string) ($row['operator_name'] ?? ''),
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * 获取最近一次操作人为指定名称的优惠券ID列表
     *
     * @param int|array $actionTypes
     * @return array<int, int>
     */
    public function getLatestCouponTargetIdsByOperatorName(string $operatorName, $actionTypes = [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]): array
    {
        return $this->getLatestTargetIdsByOperatorName(
            UserOperationLog::TARGET_TYPE_COUPON,
            $operatorName,
            $actionTypes
        );
    }

    /**
     * 获取最近一次操作人为指定名称的投递任务ID列表
     *
     * @param int|array $actionTypes
     * @return array<int, int>
     */
    public function getLatestGrantRuleTargetIdsByOperatorName(string $operatorName, $actionTypes = [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]): array
    {
        return $this->getLatestTargetIdsByOperatorName(
            UserOperationLog::TARGET_TYPE_GRANT_RULE,
            $operatorName,
            $actionTypes
        );
    }

    /**
     * @param array{
     *     target_type:int,
     *     action_type:int,
     *     user_code?:int|null,
     *     email?:string|null
     * } $filters
     * @return array{0: array<int, array{target_id:int,content:string,created_at:string,login_name:?string}>, 1: int}
     */
    public function getPaginatedLogs(array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery()->from('user_operation_log as u')
            ->leftJoin('admin_user as a', 'a.id', '=', 'u.operator_id')
            ->where('u.target_type', $filters['target_type'])
            ->where('u.action_type', $filters['action_type']);

        if (($filters['user_code'] ?? null) !== null) {
            $query->where('u.target_id', (int) $filters['user_code']);
        }

        if (($filters['email'] ?? null) !== null) {
            $query->whereRaw("u.content->>'$.email' = ?", [(string) $filters['email']]);
        }

        $count = $query->count();

        $data = $query->select('u.target_id', 'u.content', 'u.created_at', 'a.login_name')
            ->orderBy('u.id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return [$data, $count];
    }

    /**
     * 获取操作人
     *
     * @param int $targetType      内容类型
     * @param int $actionType      状态
     * @param int $operateTargetId 操作内容ID
     *
     * @return object|null
     */
    public function getOperator($targetType, $actionType, $operateTargetId){
        return $this->getQuery()->from('user_operation_log as u')
            ->leftJoin('admin_user as ad', 'ad.id', '=', 'u.operator_id')
            ->where('u.target_type', $targetType)
            ->where('u.action_type', $actionType)
            ->where('u.target_id', $operateTargetId)
            ->select('ad.login_name')->first();
    }

    /**
     * 添加操作记录
     *
     * @param int    $targetId   用户编码
     * @param int    $targetType 类型
     * @param string $content    内容
     * @param int    $actionType 状态
     * @param int    $userCode 用户ID
     *
     * @return bool
     */
    public function addRecord($targetId, $targetType, $content, $actionType = UserOperationLog::ACTION_TYPE_ADD, $userCode = null)
    {
        $loginUser           = Auth::user();
        $request             = request();
        $data                = [];
        $data['target_id']   = $targetId;
        $data['target_type'] = $targetType;
        $data['content']     = $content;
        $data['action_type'] = $actionType;
        $data['operator_id'] = is_array($loginUser)
            ? (int) ($loginUser['id'] ?? 0)
            : (is_object($loginUser) ? (int) ($loginUser->id ?? 0) : 0);
        $data['ip']          = $request ? (string) $request->ip() : '';
        $data['user_agent']  = $request ? (string) $request->userAgent() : '';

        if (! is_null($userCode)) {
            $data['user_code'] = $userCode;
        }
        return $this->getQuery()->insert($data);
    }

    /**
     * 按目标ID批量获取最后一次操作记录的操作人
     *
     * @param int       $targetType 操作类型
     * @param array<int, int> $targetIds 目标ID
     * @param int|array $actionTypes 操作类型
     *
     * @return array<int, array{operator_id:int, operator_name:string}>
     */
    public function getLatestOperateInfo(int $targetType, array $targetIds, $actionTypes = [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]): array
    {
        $targetIds = array_values(array_unique(array_filter(array_map('intval', $targetIds), static fn (int $id): bool => $id > 0)));
        if ($targetIds === []) {
            return [];
        }

        $rows = $this->getQuery()
            ->from('user_operation_log as u')
            ->leftJoin('admin_user as a', 'a.id', '=', 'u.operator_id')
            ->where('u.target_type', $targetType)
            ->whereIn('u.target_id', $targetIds)
            ->whereIn('u.action_type', is_array($actionTypes) ? $actionTypes : [$actionTypes])
            ->orderByDesc('u.id')
            ->get(['u.target_id', 'u.operator_id', 'a.login_name as operator_name'])
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $targetId = (int) ($row['target_id'] ?? 0);
            if ($targetId <= 0 || isset($result[$targetId])) {
                continue; // 按 id 倒序，仅保留最新一条
            }

            $result[$targetId] = [
                'operator_id' => (int) ($row['operator_id'] ?? 0),
                'operator_name' => (string) ($row['operator_name'] ?? ''),
            ];
        }

        return $result;
    }

    private function like(string $value): string
    {
        return sprintf('%%%s%%', addcslashes($value, '%_'));
    }

    /**
     * @param int|array $actionTypes
     * @return array<int, int>
     */
    private function getLatestTargetIdsByOperatorName(int $targetType, string $operatorName, $actionTypes): array
    {
        $operatorName = trim($operatorName);
        if ($operatorName === '') {
            return [];
        }

        $actionTypes = is_array($actionTypes) ? $actionTypes : [$actionTypes];

        $latestRows = $this->getQuery()
            ->from('user_operation_log as u')
            ->where('u.target_type', $targetType)
            ->whereIn('u.action_type', $actionTypes)
            ->selectRaw('MAX(u.id) as latest_id, u.target_id')
            ->groupBy('u.target_id');

        return DB::query()
            ->fromSub($latestRows->toBase(), 'latest')
            ->join('user_operation_log as u', 'u.id', '=', 'latest.latest_id')
            ->join('admin_user as a', 'a.id', '=', 'u.operator_id')
            ->where('a.login_name', 'like', $this->like($operatorName))
            ->orderByDesc('u.id')
            ->pluck('u.target_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 根据时间范围获取操作对应的用户ID
     *
     * @param int    $targetType 操作类型
     * @param string $start      开始时间
     * @param string $end        结束时间
     * @param int    $actionType 状态
     *
     * @return array
     */
    public function getOperateTargetIdsByTimeRange($targetType, $start, $end, $actionType = UserOperationLog::ACTION_TYPE_ADD)
    {
        $targetTypes = is_array($targetType) ? $targetType : [$targetType];
        $query = $this->getQuery()->select('target_id')->whereIn('target_type', $targetTypes);
        if ($actionType != 0) {
            $query->whereIn('action_type', is_array($actionType) ? $actionType : [$actionType]);
        }

        if (!$start && !$end) {
            return [];
        }

        if ($start) {
            $query->where('created_at', '>=', $start);
        }

        if ($end) {
            $query->where('created_at', '<=', $end);
        }

        return $query->pluck('target_id')
            ->unique()
            ->toArray();
    }

    private function couponOperateKey(int $targetId, int $couponType): string
    {
        return $targetId . ':' . $couponType;
    }
}
