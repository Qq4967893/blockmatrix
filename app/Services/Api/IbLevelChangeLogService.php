<?php

namespace App\Services\Api;

use App\Repositories\Api\IbLevelChangeLogRepository;
use App\Repositories\Api\IbLevelRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use Carbon\CarbonInterface;

class IbLevelChangeLogService
{
    public function __construct(
        private IbLevelChangeLogRepository $ibLevelChangeLogRepo,
        private IbLevelRepository $ibLevelRepo,
        private AdminUserRepository $adminUserRepo,
    ){}

    /**
     * 获取指定用户的 IB 等级变更记录
     *
     * 说明:
     * - 从 ib_level_change_log 表分页读取当前用户的等级变更日志
     * - 结合 ib_level.level_pos 解析变更前后等级名称与佣金规则名称
     * - 结合 admin_user 解析操作人名称
     *
     * @param array{
     *     user_code: int,
     *     limit?: int,
     *     offset?: int
     * } $filters
     *
     * @return array{
     *     data: array<int, array{
     *         before_level_name:string,
     *         after_level_name:string,
     *         before_commission_rule_name:string|array,
     *         after_commission_rule_name:string|array,
     *         trigger_type_text:string,
     *         changed_at:string,
     *         operator_name:string
     *     }>,
     *     total_count: int
     * }
     */
    public function getList(array $filters): array
    {
        [$rows, $total] = $this->ibLevelChangeLogRepo->getList($filters);
        if (empty($rows)) {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        $ibLevels = $this->ibLevelRepo->getAll(1);
        $ibLevels = array_column($ibLevels, null, 'level_pos');
        $adminIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['operator_id'] ?? 0),
            $rows
        ))));
        $adminNameMap = $this->adminUserRepo->getColumnByIds($adminIds, 'login_name');

        $data = [];
        $currentLocale = (string) (app()->getLocale() ?: config('app.locale', 'en_US'));
        foreach ($rows as $row) {
            $beforeLevelPos = (int) ($row['before_level_pos'] ?? 0);
            $afterLevelPos = (int) ($row['after_level_pos'] ?? 0);
            $operatorId = (int) ($row['operator_id'] ?? 0);

            $beforeLevelName = $this->resolveLocalizedName(
                $beforeLevelPos && isset($ibLevels[$beforeLevelPos]) ? $ibLevels[$beforeLevelPos]['name'] : null,
                $currentLocale
            );
            $afterLevelName = $this->resolveLocalizedName(
                isset($ibLevels[$afterLevelPos]) ? $ibLevels[$afterLevelPos]['name'] : null,
                $currentLocale
            );
            $beforeCommissionRuleName = $this->resolveLocalizedName(
                $beforeLevelPos && isset($ibLevels[$beforeLevelPos]) ? $ibLevels[$beforeLevelPos]['commission_rule_name'] : null,
                $currentLocale
            );
            $afterCommissionRuleName = $this->resolveLocalizedName(
                isset($ibLevels[$afterLevelPos]) ? $ibLevels[$afterLevelPos]['commission_rule_name'] : null,
                $currentLocale
            );

            $changeType = (int) ($row['change_type'] ?? 0);
            $changeTypeTextMap = trans('ib_level_change_log.change_type');
            $changedAtRaw = $row['assessed_at'] ?? null;
            if (($changedAtRaw === null || $changedAtRaw === '') && array_key_exists('created_at', $row)) {
                $changedAtRaw = $row['created_at'];
            }

            $data[] = [
                'before_level_name' => $beforeLevelName,
                'after_level_name' => $afterLevelName,
                'before_commission_rule_name' => $beforeCommissionRuleName,
                'after_commission_rule_name' => $afterCommissionRuleName,
                'trigger_type_text' => (string) ($changeTypeTextMap[$changeType] ?? ''),
                'changed_at' => $this->formatDateTime($changedAtRaw) ?? '',
                'operator_name' => $this->resolveOperatorName($operatorId, $adminNameMap),
            ];
        }

        return ['data' => $data, 'total_count' => $total];
    }

    private function resolveLocalizedName(array|string|null $value, string $lang): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                return $value !== '' ? $value : '-';
            }
        }

        if (! is_array($value) || $value === []) {
            return '-';
        }

        $fallback = (string) config('app.fallback_locale', 'en_US');

        return (string) ($value[$lang] ?? $value[$fallback] ?? reset($value) ?: '-');
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $timestamp = (int) $value;

            return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param array<int, string> $adminNameMap
     */
    private function resolveOperatorName(int $operatorId, array $adminNameMap): string
    {
        if ($operatorId > 0) {
            return (string) ($adminNameMap[$operatorId] ?? '');
        }

        return (string) trans('fund_flow.actor.system');
    }
}
