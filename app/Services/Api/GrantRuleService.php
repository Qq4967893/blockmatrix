<?php

namespace App\Services\Api;

use App\Exceptions\BusinessException;
use App\Imports\ArrayImport;
use App\Models\Api\Coupon;
use App\Models\Api\GrantRule;
use App\Models\Api\User;
use App\Models\Api\UserCoupon;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\CouponRepository;
use App\Repositories\Api\GrantRuleRepository;
use App\Repositories\Api\UserCouponRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Scaffold\Framework\Enums\TypeCoupon;
use Scaffold\Framework\Enums\TypeGrantEvent;

class GrantRuleService
{
    public function __construct(
        private CouponRepository $couponRepo,
        private GrantRuleRepository $grantRuleRepo,
        private UserRepository $userRepo,
        private UserCouponRepository $userCouponRepo,
        private UserOperationLogRepository $userOperationLogRepo,
        private AdminUserRepository $adminUserRepo,
    ) {}

    /**
     * @return array{data: array<int, array{id:int,name:string,target_type:int,target_type_text:string,target:int,target_name:string,grant_method:int,grant_method_text:string,event_key:?string,event_key_text:string,event_params:array,event_params_text:array<int, string>,post_count:int,granted_count:int,total_count:int,start_at:?string,end_at:?string,scheduled_at:?string,status:int,status_text:string,operator_name:string}>, total_count:int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        $filters = $this->applyGrantRuleOperatorNameFilter($filters);
        if (($filters['operator_name'] ?? '') !== '' && empty($filters['rule_ids'] ?? [])) {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        [$rows, $count] = $this->grantRuleRepo->getList($filters, $limit, $offset);
        [$operateInfos, $adminMaps] = $this->getGrantRuleOperateInfos($rows);

        return [
            'data' => array_map(fn (mixed $row): array => $this->formatGrantRule($row, $operateInfos, $adminMaps), $rows),
            'total_count' => $count,
        ];
    }

    /**
     * @return bool|array{
     *     valid_count:int,
     *     valid_data:array<int, array{user_code:int,name:string,status:int,status_text:string}>,
     *     invalid_count:int,
     *     invalid_data:array<int, array{user_code:string,reason:string}>
     * }
     */
    public function create(array $payload): bool|array
    {
        $normalized = $this->normalizeGrantRulePayload($payload);
        if ($result = $this->validateManualUsersForSave($normalized)) {
            return $result;
        }
        $normalized = $this->validateAndNormalizeInviteeRegisteredUsers($normalized);

        $this->ensureUniqueName($normalized['name']);
        $this->ensureGrantTargetExists((int) $normalized['target_type'], (int) $normalized['target']);
        $this->ensureManualUsersHaveNoExistingCreditBonus($normalized);
        $normalized = $this->applyAutoGrantCreditBonusSkipFlag($normalized);

        $grantRule = $this->grantRuleRepo->createGrantRule($normalized + [
            'status' => GrantRule::STATUS_DISABLED,
        ]);
        $this->logGrantRuleOperation($grantRule, UserOperationLog::ACTION_TYPE_ADD);

        return true;
    }

    /**
     * @return bool|array{
     *     valid_count:int,
     *     valid_data:array<int, array{user_code:int,name:string,status:int,status_text:string}>,
     *     invalid_count:int,
     *     invalid_data:array<int, array{user_code:string,reason:string}>
     * }|null
     */
    public function update(int $id, array $payload): bool|array|null
    {
        $grantRule = $this->grantRuleRepo->findById($id);
        if (! $grantRule) {
            return null;
        }

        $this->ensureGrantRuleEditable($grantRule);

        $normalized = $this->normalizeGrantRulePayload($payload);
        if ($result = $this->validateManualUsersForSave($normalized)) {
            return $result;
        }
        $normalized = $this->validateAndNormalizeInviteeRegisteredUsers($normalized);

        $this->ensureUniqueName($normalized['name'], $id);
        $this->ensureGrantTargetExists((int) $normalized['target_type'], (int) $normalized['target']);
        $this->ensureManualUsersHaveNoExistingCreditBonus($normalized);
        $normalized = $this->applyAutoGrantCreditBonusSkipFlag($normalized);
        $this->ensureTotalCountNotLessThanGrantedCount((int) $normalized['total_count'], (int) $grantRule->granted_count);

        $updated = $this->grantRuleRepo->updateById($id, $normalized, $grantRule);
        if ($updated) {
            $this->logGrantRuleOperation($updated, UserOperationLog::ACTION_TYPE_UPDATE);
        }

        return $updated !== null;
    }

    public function updateStatus(int $id, int $status): ?array
    {
        $grantRule = $this->grantRuleRepo->findById($id);
        if (! $grantRule) {
            return null;
        }

        $this->ensureGrantRuleStatusChangeAllowed($grantRule, $status);

        $updated = $this->grantRuleRepo->updateById($id, ['status' => $status], $grantRule);
        if (! $updated) {
            return null;
        }

        $this->logGrantRuleOperation($updated, UserOperationLog::ACTION_TYPE_UPDATE);

        return [
            'id' => (int) $updated->id,
            'status' => (int) $updated->status,
            'status_text' => $this->grantRuleStatusLabel((int) $updated->status),
        ];
    }

    /**
     * @return array{
     *     status_options: array<int, array{code:int,label:string}>,
     *     grant_method_options: array<int, array{code:int,label:string}>,
     *     target_options: array<int, array{key:int|string,value:string,search_key?:string,children:array<int, array{key:int,search_key?:string,value:string,children?:array<int, array{key:int,search_key?:string,value:string,expired_status:int,expired_status_text:string}>}>}>,
     *     auto_trigger_conditions: array<int, array{
     *         key:string,
     *         name:string,
     *         event_params:array<int, array{
     *             type:string,
     *             param_key:string,
     *             param_name:string,
     *             is_required:bool,
     *             is_select_required:bool,
     *             param_value:string
     *         }>,
     *         relation:array<int, string>,
     *         is_select_required?:bool
     *     }>
     * }
     */
    public function options(): array
    {
        return [
            'status_options' => [
                ['code' => GrantRule::STATUS_DISABLED, 'label' => $this->grantRuleStatusLabel(GrantRule::STATUS_DISABLED)],
                ['code' => GrantRule::STATUS_ENABLED, 'label' => $this->grantRuleStatusLabel(GrantRule::STATUS_ENABLED)],
                ['code' => GrantRule::STATUS_VOIDED, 'label' => $this->grantRuleStatusLabel(GrantRule::STATUS_VOIDED)],
            ],
            'grant_method_options' => [
                ['code' => GrantRule::GRANT_METHOD_MANUAL, 'label' => $this->grantMethodLabel(GrantRule::GRANT_METHOD_MANUAL)],
                ['code' => GrantRule::GRANT_METHOD_AUTO, 'label' => $this->grantMethodLabel(GrantRule::GRANT_METHOD_AUTO)],
            ],
            'target_options' => $this->buildTargetOptions(),
            'auto_trigger_conditions' => GrantRule::eventOptions(),
        ];
    }

    /**
     * @return array{data: array<int, array{id:int,rule_id:int,rule_name:string,target_type:int,target:int,target_name:string,user_code:int,received_at:?string,target_start_at:?string,target_end_at:?string,status:int,status_text:string,operator_name:string}>, total_count:int}
     */
    public function records(array $filters, int $limit, int $offset): array
    {
        [$rows, $count] = $this->userCouponRepo->getGrantRecordList($filters, $limit, $offset);

        return [
            'data' => $this->attachOperateInfo($rows),
            'total_count' => $count,
        ];
    }

    /**
     * @return array{
     *     valid_count:int,
     *     valid_data:array<int, array{user_code:int,name:string,status:int,status_text:string}>,
     *     invalid_count:int,
     *     invalid_data:array<int, array{user_code:string,reason:string}>
     * }
     */
    public function validateUsers(string $userCodes): array
    {
        [$normalizedCodes, $invalidData] = $this->normalizeUserCodes($userCodes);
        $rows = $this->userRepo->getByUserCodes($normalizedCodes, ['user_code', 'username']);
        $userMap = [];

        foreach ($rows as $row) {
            $userCode = (int) ($row['user_code'] ?? 0);
            if ($userCode <= 0) {
                continue;
            }

            $userMap[$userCode] = $row;
        }

        $validData = [];
        foreach ($normalizedCodes as $userCode) {
            $user = $userMap[$userCode] ?? null;
            if ($user === null) {
                $invalidData[] = [
                    'user_code' => (string) $userCode,
                    'reason' => trans('user.message.not_exist'),
                ];

                continue;
            }

            $validData[] = [
                'user_code' => $userCode,
                'name' => (string) ($user['username'] ?? ''),
                'status' => User::VALIDATION_STATUS_NORMAL,
                'status_text' => User::validationStatusLabel(User::VALIDATION_STATUS_NORMAL),
            ];
        }

        return [
            'valid_count' => count($validData),
            'valid_data' => $validData,
            'invalid_count' => count($invalidData),
            'invalid_data' => $invalidData,
        ];
    }

    /**
     * @return array{
     *     file_name:string,
     *     valid_count:int,
     *     valid_data:array<int, array{user_code:int,name:string,status:int,status_text:string}>,
     *     invalid_count:int,
     *     invalid_data:array<int, array{user_code:string,reason:string}>
     * }
     */
    public function importUsers(mixed $file): array
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw new BusinessException(trans('public.message.data_error'));
        }

        try {
            $sheets = Excel::toArray(new ArrayImport, $file);
        } catch (\Throwable) {
            throw new BusinessException(trans('grant_rule.message.import_file_invalid'));
        }

        $userCodes = $this->extractImportedUserCodes($sheets);

        return array_merge(
            ['file_name' => $file->getClientOriginalName() ?: 'grant-rule-users.xlsx'],
            $this->validateUsers(implode(',', $userCodes))
        );
    }

    /**
     * 把校验失败的明细按原因分组，生成可直接展示的提示，例如「客户不存在：10001,10003」。
     *
     * @param  array<string, mixed>  $validation  validateUsers/importUsers 返回的校验结果
     */
    public function userValidationFailureMessage(array $validation): string
    {
        $groupedReasons = [];
        foreach ($validation['invalid_data'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $reason = trim((string) ($item['reason'] ?? ''));
            if ($reason === '') {
                continue;
            }

            $groupedReasons[$reason][] = trim((string) ($item['user_code'] ?? ''));
        }

        $messages = [];
        foreach ($groupedReasons as $reason => $userCodes) {
            $userCodes = array_values(array_filter($userCodes, static fn (string $userCode): bool => $userCode !== ''));
            $messages[] = $userCodes === []
                ? $reason
                : sprintf('%s：%s', $reason, implode(',', $userCodes));
        }

        return $messages === []
            ? trans('grant_rule.message.user_validation_failed')
            : implode('；', $messages);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{id:int,rule_id:int,rule_name:string,target_type:int,target:int,target_name:string,user_code:int,received_at:?string,target_start_at:?string,target_end_at:?string,status:int,status_text:string,operator_name:string}>
     */
    private function attachOperateInfo(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $ruleIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $row): int => (int) data_get($row, 'rule_id', 0),
            $rows
        ))));

        $operateInfos = $this->userOperationLogRepo->getBatchOperateInfo(
            UserOperationLog::TARGET_TYPE_GRANT_RULE,
            $ruleIds,
            [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]
        );

        $adminIds = array_values(array_unique(array_filter(array_column($operateInfos, 'operator_id'))));
        $adminMaps = $this->adminUserRepo->getColumnByIds($adminIds, 'login_name');

        return array_map(function (mixed $row) use ($operateInfos, $adminMaps): array {
            $ruleId = (int) data_get($row, 'rule_id', 0);
            $status = (int) data_get($row, 'status', UserCoupon::STATUS_USABLE);
            $operateInfo = $operateInfos[$ruleId] ?? null;

            return [
                'id' => (int) data_get($row, 'id', 0),
                'rule_id' => $ruleId,
                'rule_name' => (string) data_get($row, 'rule_name', ''),
                'target_type' => (int) data_get($row, 'target_type', 0),
                'target' => (int) data_get($row, 'target', 0),
                'target_name' => (string) data_get($row, 'target_name', ''),
                'user_code' => (int) data_get($row, 'user_code', 0),
                'received_at' => $this->formatDateTime(data_get($row, 'received_at')),
                'target_start_at' => $this->formatDateTime(data_get($row, 'target_start_at')),
                'target_end_at' => $this->formatDateTime(data_get($row, 'target_end_at')),
                'status' => $status,
                'status_text' => $this->userCouponStatusLabel($status),
                'operator_name' => $operateInfo ? (string) ($adminMaps[$operateInfo['operator_id']] ?? '') : '',
            ];
        }, $rows);
    }

    private function ensureGrantRuleStatusChangeAllowed(GrantRule $grantRule, int $targetStatus): void
    {
        $currentStatus = (int) $grantRule->status;

        if ($targetStatus === GrantRule::STATUS_ENABLED) {
            if ($currentStatus !== GrantRule::STATUS_DISABLED) {
                throw new BusinessException(trans('grant_rule.message.enable_not_allowed'));
            }

            $this->ensureGrantRuleCanBeEnabled($grantRule);

            return;
        }

        if ($targetStatus === GrantRule::STATUS_VOIDED) {
            if (! in_array($currentStatus, GrantRule::VOIDABLE_STATUSES, true)) {
                throw new BusinessException(trans('grant_rule.message.void_not_allowed'));
            }

            if ($this->isGrantRuleCompleted($grantRule)) {
                throw new BusinessException(trans('grant_rule.message.completed_cannot_void'));
            }
        }
    }

    private function ensureUniqueName(string $name, ?int $excludeId = null): void
    {
        if ($this->grantRuleRepo->existsByName($name, $excludeId)) {
            throw new BusinessException(trans('grant_rule.message.name_exists'));
        }
    }

    private function ensureGrantRuleEditable(GrantRule $grantRule): void
    {
        if ((int) $grantRule->status !== GrantRule::STATUS_DISABLED) {
            throw new BusinessException(trans('grant_rule.message.edit_not_allowed'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     valid_count:int,
     *     valid_data:array<int, array{user_code:int,name:string,status:int,status_text:string}>,
     *     invalid_count:int,
     *     invalid_data:array<int, array{user_code:string,reason:string}>
     * }|null
     */
    private function validateManualUsersForSave(array $payload): ?array
    {
        if (($payload['event_key'] ?? null) !== TypeGrantEvent::Manual->value) {
            return null;
        }

        $userCodes = data_get($payload, 'event_params.user_codes', []);
        if (! is_array($userCodes) || $userCodes === []) {
            return null;
        }

        $validation = $this->validateUsers(implode(',', $userCodes));

        return (int) ($validation['invalid_count'] ?? 0) > 0 ? $validation : null;
    }

    /**
     * 手工投递赠金券（通用/限制型，共用同一赠金池）时，校验用户是否已有有效赠金
     * （未作废、未过期且未用尽的券，或仍有剩余且未过期的赠金记录）。
     * 若用户已持有有效赠金，则不可重复发放；赠金已用完的用户可再次发放。
     *
     * @param  array<string, mixed>  $payload  已归一化的投递规则数据
     */
    private function ensureManualUsersHaveNoExistingCreditBonus(array $payload): void
    {
        if (($payload['event_key'] ?? null) !== TypeGrantEvent::Manual->value) {
            return;
        }

        if ((int) ($payload['target_type'] ?? 0) !== GrantRule::TARGET_COUPON) {
            return;
        }

        $coupon = $this->couponRepo->findById((int) ($payload['target'] ?? 0));
        if (! $coupon || ! ($coupon->coupon_type instanceof TypeCoupon) || ! Coupon::isCreditBonusGroupType($coupon->coupon_type)) {
            return;
        }

        $userCodes = data_get($payload, 'event_params.user_codes', []);
        if (! is_array($userCodes) || $userCodes === []) {
            return;
        }

        $existingBonusUserCodes = $this->userCouponRepo->findUserCodesWithExistingCreditBonus($userCodes);
        if ($existingBonusUserCodes === []) {
            return;
        }

        $displayCodes = implode(',', array_map(static fn (int $code): string => (string) $code, $existingBonusUserCodes));
        throw new BusinessException(trans('grant_rule.message.user_already_has_credit_bonus', [
            'user_codes' => $displayCodes,
        ]));
    }

    /**
     * 自动投递赠金券（通用/限制型）时，自动在 event_params 中标记跳过已有赠金的用户。
     * 下游投递执行方应根据此标记判断是否跳过。
     *
     * @param  array<string, mixed>  $payload  已归一化的投递规则数据
     * @return array<string, mixed> 可能更新了 event_params 的数据
     */
    private function applyAutoGrantCreditBonusSkipFlag(array $payload): array
    {
        if (($payload['event_key'] ?? null) === TypeGrantEvent::Manual->value) {
            return $payload;
        }

        if ((int) ($payload['target_type'] ?? 0) !== GrantRule::TARGET_COUPON) {
            return $payload;
        }

        $coupon = $this->couponRepo->findById((int) ($payload['target'] ?? 0));
        if (! $coupon || ! ($coupon->coupon_type instanceof TypeCoupon) || ! Coupon::isCreditBonusGroupType($coupon->coupon_type)) {
            return $payload;
        }

        $eventParams = $payload['event_params'] ?? [];
        $eventParams['skip_if_has_bonus'] = true;
        $payload['event_params'] = $eventParams;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateAndNormalizeInviteeRegisteredUsers(array $payload): array
    {
        if (($payload['event_key'] ?? null) !== TypeGrantEvent::InviteeRegistered->value) {
            return $payload;
        }

        $userCodes = data_get($payload, 'event_params.inviter_user_code');
        if ($this->isBlankValue($userCodes)) {
            return $payload;
        }

        if (is_array($userCodes)) {
            foreach ($userCodes as $userCode) {
                if (! is_string($userCode) && ! is_int($userCode)) {
                    throw new BusinessException(trans('grant_rule.message.invalid_user_code_format'));
                }
            }

            $userCodes = implode(',', $userCodes);
        } elseif (! is_string($userCodes) && ! is_int($userCodes)) {
            throw new BusinessException(trans('grant_rule.message.invalid_user_code_format'));
        }

        $validation = $this->validateUsers((string) $userCodes);
        $invalidData = is_array($validation['invalid_data'] ?? null)
            ? $validation['invalid_data']
            : [];

        if ($invalidData === []) {
            if ((int) ($validation['valid_count'] ?? 0) > 0) {
                $payload['event_params']['inviter_user_code'] = array_values(array_map(
                    static fn (array $user): int => (int) ($user['user_code'] ?? 0),
                    $validation['valid_data'] ?? []
                ));

                return $payload;
            }

            throw new BusinessException(trans('grant_rule.message.invalid_user_code_format'));
        }

        $messages = [];
        foreach ($invalidData as $item) {
            if (! is_array($item)) {
                continue;
            }

            $userCode = trim((string) ($item['user_code'] ?? ''));
            $reason = trim((string) ($item['reason'] ?? ''));
            if ($reason === '') {
                continue;
            }

            if ($reason === trans('user.message.not_exist')) {
                $messages[] = trans('grant_rule.message.inviter_user_not_exist', [
                    'user_code' => $userCode,
                ]);

                continue;
            }

            $messages[] = $userCode.$reason;
        }

        throw new BusinessException(
            $messages === []
                ? trans('grant_rule.message.invalid_user_code_format')
                : implode('；', $messages)
        );
    }

    private function ensureGrantTargetExists(int $targetType, int $target): void
    {
        if (! in_array($targetType, GrantRule::SUPPORTED_TARGET_TYPES, true) || $target <= 0) {
            throw new BusinessException(trans('grant_rule.message.target_not_exist'));
        }

        if ($targetType === GrantRule::TARGET_COUPON
            && ! $this->couponRepo->findById($target)
        ) {
            throw new BusinessException(trans('grant_rule.message.target_not_exist'));
        }
    }

    private function ensureGrantRuleCanBeEnabled(GrantRule $grantRule): void
    {
        if ($this->isExpired($grantRule->end_at)) {
            throw new BusinessException(trans('grant_rule.message.expired'));
        }

        if ((int) $grantRule->target_type === GrantRule::TARGET_COUPON) {
            $coupon = $this->resolveCouponTarget($grantRule);
            if (! $coupon) {
                throw new BusinessException(trans('public.message.non_existent'));
            }

            if ((int) $coupon->status === Coupon::STATUS_VOIDED) {
                throw new BusinessException(trans('grant_rule.message.coupon_voided'));
            }

            if ((int) $coupon->status !== Coupon::STATUS_ENABLED) {
                throw new BusinessException(trans('grant_rule.message.coupon_not_enabled'));
            }

            if ($this->isExpired($coupon->end_at)) {
                throw new BusinessException(trans('grant_rule.message.expired'));
            }
        }

        if (! $this->hasAvailableGrantInventory($grantRule)) {
            throw new BusinessException(trans('grant_rule.message.insufficient_inventory'));
        }
    }

    private function resolveCouponTarget(GrantRule $grantRule): ?Coupon
    {
        if ((int) $grantRule->target_type !== GrantRule::TARGET_COUPON) {
            return null;
        }

        return $this->couponRepo->findById((int) $grantRule->target);
    }

    private function hasAvailableGrantInventory(GrantRule $grantRule): bool
    {
        $totalCount = (int) $grantRule->total_count;
        if ($totalCount === 0) {
            return true;
        }

        return $totalCount > (int) $grantRule->granted_count;
    }

    private function isGrantRuleCompleted(GrantRule $grantRule): bool
    {
        if ($this->isExpired($grantRule->end_at)) {
            return true;
        }

        $totalCount = (int) $grantRule->total_count;

        return $totalCount > 0 && (int) $grantRule->granted_count >= $totalCount;
    }

    private function ensureTotalCountNotLessThanGrantedCount(int $totalCount, int $grantedCount): void
    {
        if ($totalCount !== 0 && $totalCount < $grantedCount) {
            throw new BusinessException(trans('public.message.data_error'));
        }
    }

    private function isExpired(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return Carbon::parse($value)->lt(Carbon::now());
    }

    private function logGrantRuleOperation(GrantRule $grantRule, int $actionType): void
    {
        $this->userOperationLogRepo->addRecord(
            (int) $grantRule->id,
            UserOperationLog::TARGET_TYPE_GRANT_RULE,
            $this->buildGrantRuleLogContent($grantRule),
            $actionType
        );
    }

    private function buildGrantRuleLogContent(GrantRule $grantRule): string
    {
        return (string) json_encode([
            'name' => (string) $grantRule->name,
            'target_type' => (int) $grantRule->target_type,
            'target' => (int) $grantRule->target,
            'event_key' => $this->enumValue($grantRule->event_key),
            'event_params' => is_array($grantRule->event_params) ? $grantRule->event_params : [],
            'total_count' => (int) $grantRule->total_count,
            'status' => (int) $grantRule->status,
            'start_at' => $this->formatDateTime($grantRule->start_at),
            'end_at' => $this->formatDateTime($grantRule->end_at),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeGrantRulePayload(array $payload): array
    {
        $grantMethod = (int) ($payload['grant_method'] ?? 0);
        $eventKey = $grantMethod === GrantRule::GRANT_METHOD_MANUAL
            ? TypeGrantEvent::Manual->value
            : trim((string) ($payload['event_key'] ?? ''));
        $eventParams = $this->normalizeLotteryProbabilityForStorage(
            $this->normalizeGrantEventParams($payload['event_params'] ?? []),
            $eventKey
        );
        $userCodes = $this->normalizeManualUserCodes($payload['user_codes'] ?? []);
        $totalCount = (int) ($payload['total_count'] ?? 0);
        if ($grantMethod === GrantRule::GRANT_METHOD_MANUAL && $totalCount <= 0) {
            // 人工投递发放总量跟随投递用户数（去重后）。
            $totalCount = count($userCodes);
        }

        return [
            'name' => trim((string) ($payload['name'] ?? '')),
            'target_type' => (int) ($payload['target_type'] ?? 0),
            'target' => (int) ($payload['target'] ?? 0),
            'event_key' => $eventKey,
            'event_params' => $grantMethod === GrantRule::GRANT_METHOD_MANUAL
                ? ['user_codes' => $userCodes]
                : $this->fillMissingEventParams($eventParams, $eventKey),
            'total_count' => $totalCount,
            'start_at' => trim((string) ($payload['start_at'] ?? '')),
            'end_at' => trim((string) ($payload['end_at'] ?? '')),
        ];
    }

    /**
     * 抽奖概率前端使用百分比展示，入库使用万分比。
     *
     * @param  array<string, mixed>  $eventParams
     * @return array<string, mixed>
     */
    private function normalizeLotteryProbabilityForStorage(array $eventParams, string $eventKey): array
    {
        if ($eventKey !== TypeGrantEvent::Lottery->value || ! array_key_exists('weight', $eventParams)) {
            return $eventParams;
        }

        $probability = $eventParams['weight'];
        if (! is_numeric($probability)) {
            return $eventParams;
        }

        $eventParams['weight'] = (int) round(((float) $probability / 100) * 10000);

        return $eventParams;
    }

    /**
     * 入库时补齐事件定义的全部数值、下拉参数键，前端未传或缺省的默认存 0；
     * relation 前端传了才存，未传不落库。
     *
     * @param  array<string, mixed>  $eventParams
     * @return array<string, mixed>
     */
    private function fillMissingEventParams(array $eventParams, string $eventKey): array
    {
        foreach (GrantRule::eventParamDefaults($eventKey) as $paramKey => $default) {
            if (! array_key_exists($paramKey, $eventParams) || $this->isBlankValue($eventParams[$paramKey])) {
                $eventParams[$paramKey] = $default;
            }
        }

        return $eventParams;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeGrantEventParams(mixed $eventParams): array
    {
        if (! is_array($eventParams)) {
            return [];
        }

        if (! array_is_list($eventParams)) {
            return array_filter(
                $eventParams,
                fn (mixed $value): bool => ! $this->isBlankValue($value)
            );
        }

        $normalized = [];
        foreach ($eventParams as $item) {
            if (! is_array($item)) {
                continue;
            }

            $paramKey = trim((string) ($item['param_key'] ?? ''));
            if ($paramKey === '') {
                continue;
            }

            $paramValue = $item['param_value'] ?? null;
            if ($this->isBlankValue($paramValue)) {
                continue;
            }

            $normalized[$paramKey] = $paramValue;
        }

        return $normalized;
    }

    private function isBlankValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || trim((string) $value) === '';
    }

    /**
     * @return array<int, int>
     */
    private function normalizeManualUserCodes(mixed $userCodes): array
    {
        if (! is_array($userCodes)) {
            return [];
        }

        $normalized = [];
        foreach ($userCodes as $userCode) {
            $userCode = (int) $userCode;
            if ($userCode <= 0) {
                continue;
            }

            $normalized[$userCode] = $userCode;
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array{0: array<int, array>, 1: array<int, string>}
     */
    private function getGrantRuleOperateInfos(array $rows): array
    {
        if ($rows === []) {
            return [[], []];
        }

        $ruleIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $row): int => (int) data_get($row, 'id', 0),
            $rows
        ))));

        $operateInfos = $this->userOperationLogRepo->getBatchOperateInfo(
            UserOperationLog::TARGET_TYPE_GRANT_RULE,
            $ruleIds,
            [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]
        );

        $adminIds = array_values(array_unique(array_filter(array_map(
            static fn (array $operateInfo): int => (int) ($operateInfo['operator_id'] ?? 0),
            $operateInfos
        ))));

        return [
            $operateInfos,
            $this->adminUserRepo->getColumnByIds($adminIds, 'login_name'),
        ];
    }

    private function formatGrantRule(mixed $row, array $operateInfos, array $adminMaps): array
    {
        $id = (int) data_get($row, 'id', 0);
        $eventKey = $this->nullableString(data_get($row, 'event_key'));
        $eventParams = $this->decodeJsonField(data_get($row, 'event_params'));
        $grantMethod = $this->resolveGrantMethod($eventKey);
        $displayEventParams = $this->formatLotteryProbabilityForDisplay($eventParams, (string) $eventKey);
        $operateInfo = $operateInfos[$id] ?? null;
        $targetType = (int) data_get($row, 'target_type', 0);

        return [
            'id' => $id,
            'name' => (string) data_get($row, 'name', ''),
            'target_type' => $targetType,
            'target_type_text' => $this->targetTypeLabel($targetType),
            'target' => (int) data_get($row, 'target', 0),
            'target_name' => $this->buildGrantTargetName($row),
            'grant_method' => $grantMethod,
            'grant_method_text' => $this->grantMethodLabel($grantMethod),
            'event_key' => $eventKey,
            'event_key_text' => $this->eventLabel($eventKey),
            'event_params' => $this->filterInternalEventParams($displayEventParams),
            'event_params_text' => $this->buildEventParamsText($eventKey, $displayEventParams),
            // 非优惠券类型无已发放张数概念，直接返回单次发放数量（target 值）
            'post_count' => $targetType === GrantRule::TARGET_COUPON
                ? (int) data_get($row, 'post_count', 0)
                : (int) data_get($row, 'target', 0),
            'granted_count' => (int) data_get($row, 'granted_count', 0),
            'total_count' => (int) data_get($row, 'total_count', 0),
            'start_at' => $this->formatDateTime(data_get($row, 'start_at')),
            'end_at' => $this->formatDateTime(data_get($row, 'end_at')),
            'scheduled_at' => null,
            'status' => (int) data_get($row, 'status', 0),
            'status_text' => $this->grantRuleStatusLabel((int) data_get($row, 'status', 0)),
            'operator_name' => $this->resolveOperatorName($operateInfo, $adminMaps),
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function userCouponStatusLabel(int $status): string
    {
        return $this->translateOption('coupon.user_coupon_status', $status);
    }

    private function grantRuleStatusLabel(int $status): string
    {
        return $this->translateOption('grant_rule.status', $status);
    }

    private function grantMethodLabel(int $method): string
    {
        return $this->translateOption('grant_rule.grant_method', $method);
    }

    private function targetTypeLabel(int $type): string
    {
        return $this->translateOption('grant_rule.target_type', $type);
    }

    private function translateOption(string $group, int|string $value): string
    {
        $key = sprintf('%s.%s', $group, $value);
        $label = trans($key);

        return $label === $key ? trans(sprintf('%s.unknown', $group)) : $label;
    }

    private function eventLabel(?string $eventKey): string
    {
        if ($eventKey === null || $eventKey === '') {
            return '';
        }

        $key = sprintf('scaffold::type_grant_event.%s.description', $eventKey);
        $label = trans($key);

        return $label === $key ? $eventKey : $label;
    }

    private function resolveGrantMethod(?string $eventKey): int
    {
        return $eventKey === TypeGrantEvent::Manual->value
            ? GrantRule::GRANT_METHOD_MANUAL
            : GrantRule::GRANT_METHOD_AUTO;
    }

    /**
     * @param  array<int, array{target_id:int,operator_id:int,created_at:?string}>|array<string, mixed>|null  $operateInfo
     * @param  array<int, string>  $adminMaps
     */
    private function resolveOperatorName(?array $operateInfo, array $adminMaps): string
    {
        if ($operateInfo === null) {
            return '';
        }

        $operatorId = (int) ($operateInfo['operator_id'] ?? 0);
        if ($operatorId <= 0) {
            return '';
        }

        return (string) ($adminMaps[$operatorId] ?? '');
    }

    private function buildGrantTargetName(mixed $row): string
    {
        $targetType = (int) data_get($row, 'target_type', 0);
        if ($targetType !== GrantRule::TARGET_COUPON) {
            // 非优惠券类型 target 存的是金额，无名称可匹配，直接回显类型名
            return $this->targetTypeLabel($targetType);
        }

        $couponName = trim((string) data_get($row, 'coupon_name', ''));
        if ($couponName === '') {
            return '';
        }

        $couponType = TypeCoupon::tryFrom((int) data_get($row, 'coupon_type', 0));
        if ($couponType === null) {
            return $couponName;
        }

        return sprintf('%s-%s', $this->couponTypeLabel($couponType), $couponName);
    }

    /**
     * @param  array<string, mixed>  $eventParams
     * @return array<int, string>
     */
    /**
     * 过滤内部使用的 event_params 键，不返回给前端。
     *
     * @param  array<string, mixed>  $eventParams
     * @return array<string, mixed>
     */
    private function filterInternalEventParams(array $eventParams): array
    {
        unset($eventParams['skip_if_has_bonus']);

        return $eventParams;
    }

    /**
     * 列表回显时，将万分比概率还原成前端使用的百分比。
     *
     * @param  array<string, mixed>  $eventParams
     * @return array<string, mixed>
     */
    private function formatLotteryProbabilityForDisplay(array $eventParams, string $eventKey): array
    {
        if ($eventKey !== TypeGrantEvent::Lottery->value || ! array_key_exists('weight', $eventParams)) {
            return $eventParams;
        }

        $basisPoints = $eventParams['weight'];
        if (! is_numeric($basisPoints)) {
            return $eventParams;
        }

        $probability = round(((float) $basisPoints / 10000) * 100, 2);
        $eventParams['weight'] = $probability == (int) $probability
            ? (int) $probability
            : $probability;

        return $eventParams;
    }

    private function buildEventParamsText(?string $eventKey, array $eventParams): array
    {
        if ($eventKey === null || $eventKey === '' || $eventParams === []) {
            return [];
        }

        $pairs = $this->normalizeEventParamsForDisplay($eventParams);
        $texts = [];

        foreach ($pairs as $paramKey => $paramValue) {
            if ($paramKey === 'relation' || $paramKey === 'skip_if_has_bonus') {
                continue;
            }

            $label = $this->eventParamLabel($eventKey, $paramKey);
            $value = $this->stringifyEventParamValue($eventKey, $paramKey, $paramValue);
            if ($value === '') {
                continue;
            }

            $texts[] = sprintf('%s：%s', $label, $value);
        }

        return $texts;
    }

    private function eventParamLabel(string $eventKey, string $paramKey): string
    {
        $key = sprintf('scaffold::type_grant_event.%s.variables.%s', $eventKey, $paramKey);
        $label = trans($key);

        return $label === $key ? $paramKey : $label;
    }

    /**
     * 事件参数里的下拉值保存的是原始值，展示时需要还原成 options 里的文案。
     */
    private function stringifyEventParamValue(string $eventKey, string $paramKey, mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_map(
                fn (mixed $item): string => $this->eventParamOptionLabel($eventKey, $paramKey, $item),
                $value
            ));
        }

        return $this->eventParamOptionLabel($eventKey, $paramKey, $value);
    }

    private function eventParamOptionLabel(string $eventKey, string $paramKey, mixed $value): string
    {
        $fallback = is_bool($value)
            ? ($value ? '1' : '0')
            : (string) $value;

        $event = TypeGrantEvent::tryFrom($eventKey);
        if ($event === null) {
            return $fallback;
        }

        foreach (TypeGrantEvent::parameterOptions($event, $paramKey) as $option) {
            if (! is_array($option) || ! $this->isSameEventParamValue($option['value'] ?? null, $value)) {
                continue;
            }

            $label = trim((string) ($option['label'] ?? ''));

            return $label === '' ? $fallback : $label;
        }

        return $fallback;
    }

    private function isSameEventParamValue(mixed $optionValue, mixed $value): bool
    {
        if (is_numeric($optionValue) && is_numeric($value)) {
            return (float) $optionValue === (float) $value;
        }

        return $optionValue === $value;
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $eventParams
     * @return array<string, mixed>
     */
    private function normalizeEventParamsForDisplay(array $eventParams): array
    {
        if (! array_is_list($eventParams)) {
            return $eventParams;
        }

        $normalized = [];
        foreach ($eventParams as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_key_exists('param_key', $item)) {
                $paramKey = trim((string) ($item['param_key'] ?? ''));
                if ($paramKey === '') {
                    continue;
                }

                $normalized[$paramKey] = $item['param_value'] ?? null;

                continue;
            }

            if (count($item) === 1) {
                $key = (string) array_key_first($item);
                $normalized[$key] = $item[$key];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function applyGrantRuleOperatorNameFilter(array $filters): array
    {
        $operatorName = trim((string) ($filters['operator_name'] ?? ''));
        if ($operatorName === '') {
            return $filters;
        }

        $filters['operator_name'] = $operatorName;
        $filters['rule_ids'] = $this->userOperationLogRepo->getLatestGrantRuleTargetIdsByOperatorName($operatorName);

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return (string) $value;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * @return array{0: array<int, int>, 1: array<int, array{user_code:string,reason:string}>}
     */
    private function normalizeUserCodes(string $userCodes): array
    {
        $segments = preg_split('/[\s,，;；]+/u', trim($userCodes), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $normalized = [];
        $invalidData = [];

        foreach ($segments as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '') {
                continue;
            }

            if (! preg_match('/^\d+$/', $segment)) {
                $invalidData[] = [
                    'user_code' => $segment,
                    'reason' => trans('grant_rule.message.invalid_user_code_format'),
                ];

                continue;
            }

            $normalized[(int) $segment] = (int) $segment;
        }

        if (count($normalized) > GrantRule::MAX_MANUAL_USER_COUNT) {
            return [[], [[
                'user_code' => '',
                'reason' => trans('grant_rule.message.user_code_limit_exceeded', [
                    'count' => GrantRule::MAX_MANUAL_USER_COUNT,
                ]),
            ]]];
        }

        return [array_values($normalized), $invalidData];
    }

    /**
     * @param  array<int, mixed>  $sheets
     * @return array<int, string>
     */
    private function extractImportedUserCodes(array $sheets): array
    {
        $rows = $sheets[0] ?? null;
        if (! is_array($rows) || $rows === []) {
            throw new BusinessException(trans('grant_rule.message.import_template_invalid'));
        }

        $header = trim((string) ($rows[0][0] ?? ''));
        if (! in_array($header, $this->acceptedTemplateHeaders(), true)) {
            throw new BusinessException(trans('grant_rule.message.import_template_invalid'));
        }

        $userCodes = [];
        foreach (array_slice($rows, 1) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cell = $row[0] ?? null;
            if ($cell === null || $cell === '') {
                continue;
            }

            $userCode = $this->normalizeImportedUserCodeCell($cell);
            if ($userCode === '') {
                continue;
            }

            $userCodes[] = $userCode;
        }

        if ($userCodes === []) {
            throw new BusinessException(trans('public.message.data_error'));
        }

        return $userCodes;
    }

    /**
     * @return array<int, string>
     */
    private function acceptedTemplateHeaders(): array
    {
        $headers = [];
        $locales = array_keys(config('lang.languages', []));
        $locales[] = config('app.locale', 'en_US');
        $locales[] = config('app.fallback_locale', 'en_US');

        foreach (array_unique(array_filter($locales)) as $locale) {
            $headers[] = (string) trans('grant_rule.template.user_code_header', locale: $locale);
        }

        return array_values(array_unique(array_filter(array_map('trim', $headers))));
    }

    private function normalizeImportedUserCodeCell(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return fmod($value, 1.0) === 0.0 ? number_format($value, 0, '.', '') : (string) $value;
        }

        return trim((string) $value);
    }

    /**
     * @return array<int, array{key:int|string,value:string,search_key?:string,children:array<int, array{key:int,search_key?:string,value:string,children?:array<int, array{key:int,search_key?:string,value:string,expired_status:int,expired_status_text:string}>}>}>
     */
    private function buildTargetOptions(): array
    {
        return [
            [
                'key' => GrantRule::TARGET_COUPON,
                'search_key' => 'target_type',
                'value' => $this->targetTypeLabel(GrantRule::TARGET_COUPON),
                'children' => $this->buildCouponTargetOptions(),
            ],
            [
                'key' => 'reward',
                'value' => trans('grant_rule.target_group.reward'),
                'children' => $this->buildTargetTypeOptions([
                    GrantRule::TARGET_BALANCE_FUNDING,
                    GrantRule::TARGET_BALANCE_COMPETITION,
                    GrantRule::TARGET_BALANCE_BONUSFUND,
                ]),
            ],
            [
                'key' => 'points',
                'value' => trans('grant_rule.target_group.points'),
                'children' => $this->buildTargetTypeOptions([
                    GrantRule::TARGET_POINTS_XP,
                    GrantRule::TARGET_POINTS_DROPLET,
                ]),
            ],
        ];
    }

    /**
     * @return array<int, array{key:int,value:string,expired_status:int,expired_status_text:string}>
     */
    private function buildCouponTargetOptions(): array
    {
        $coupons = $this->couponRepo->getGrantTargetCoupons();

        $grouped = [];
        foreach ($coupons as $coupon) {
            $type = $coupon->coupon_type instanceof BackedEnum
                ? $coupon->coupon_type->value
                : (int) $coupon->coupon_type;
            $expiredStatus = $this->resolveExpiredStatus($coupon->end_at);

            $grouped[$type][] = [
                'key' => (int) $coupon->id,
                'search_key' => 'target',
                'value' => trans('grant_rule.target_option_format', [
                    'name' => (string) $coupon->name_internal,
                    'start_at' => $this->formatDateTime($coupon->start_at) ?? '',
                    'end_at' => $this->formatDateTime($coupon->end_at) ?? '',
                ]),
                'expired_status' => $expiredStatus,
                'expired_status_text' => $this->expiredStatusLabel($expiredStatus),
            ];
        }

        $options = [];
        foreach (TypeCoupon::cases() as $type) {
            $typeValue = $type->value;
            if (empty($grouped[$typeValue])) {
                continue;
            }

            $options[] = [
                'key' => $typeValue,
                'search_key' => 'coupon_type',
                'value' => $this->couponTypeLabel($type),
                'children' => $grouped[$typeValue],
            ];
        }

        return $options;
    }

    /**
     * @param  array<int, int>  $targetTypes
     * @return array<int, array{key:int,search_key:string,value:string}>
     */
    private function buildTargetTypeOptions(array $targetTypes): array
    {
        return array_map(
            fn (int $targetType): array => [
                'key' => $targetType,
                'search_key' => 'target_type',
                'value' => $this->targetTypeLabel($targetType),
            ],
            $targetTypes
        );
    }

    private function resolveExpiredStatus(mixed $endAt): int
    {
        if ($endAt === null || $endAt === '') {
            return Coupon::EXPIRED_STATUS_NOT_EXPIRED;
        }

        return Carbon::parse($endAt)->lt(Carbon::now())
            ? Coupon::EXPIRED_STATUS_EXPIRED
            : Coupon::EXPIRED_STATUS_NOT_EXPIRED;
    }

    private function expiredStatusLabel(int $status): string
    {
        return $this->translateOption('coupon.expired_status', $status);
    }

    private function couponTypeLabel(int|TypeCoupon $type): string
    {
        return Coupon::typeLabel($type);
    }
}
