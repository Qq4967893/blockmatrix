<?php

namespace App\Services\Api;

use App\Constants\FieldType;
use App\Exceptions\BusinessException;
use App\Models\Api\Coupon;
use App\Models\Api\UserCoupon;
use App\Models\Api\UserCreditRecord;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\CouponRepository;
use App\Repositories\Api\GrantRuleRepository;
use App\Repositories\Api\SourceRepository;
use App\Repositories\Api\UserCouponRepository;
use App\Repositories\Api\UserCreditLedgerRepository;
use App\Repositories\Api\UserCreditRecordRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Repositories\Et6\TradeRepository;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\TypeCoupon;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Scaffold\Framework\Enums\TypeTradeAccount;
use Scaffold\Framework\Models\Api\UserCreditLedger;
use Scaffold\TradeSystem\Facades\TsCoupon;

class CouponService
{
    private ?array $sourceContractSizeMap = null;

    public function __construct(
        private CouponRepository $couponRepo,
        private GrantRuleRepository $grantRuleRepo,
        private UserCouponRepository $userCouponRepo,
        private TradeRepository $tradeRepo,
        private SourceRepository $sourceRepo,
        private UserOperationLogRepository $userOperationLogRepo,
        private UserCreditRecordRepository $userCreditRecordRepo,
        private UserCreditLedgerRepository $userCreditLedgerRepo,
    ) {}

    public function getList(array $filters, int $limit, int $offset): array
    {
        $filters = $this->applyOperatorNameFilter($filters);
        if ($this->isEmptyCouponFilter($filters)) {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        [$records, $count] = $this->couponRepo->getList($filters, $limit, $offset);
        $operateInfos = $this->getCouponOperateInfos($records);

        return [
            'data' => array_map(fn (Coupon $coupon): array => $this->formatCoupon($coupon, $operateInfos), $records),
            'total_count' => $count,
        ];
    }

    public function create(array $payload): bool
    {
        $normalized = $this->normalizeCouponPayload($payload);
        $this->ensureUniqueInternalName($normalized['name_internal'], (int) $normalized['coupon_type']);

        $coupon = $this->couponRepo->createCoupon($normalized + ['status' => Coupon::STATUS_DISABLED]);
        $this->logCouponOperation($coupon, UserOperationLog::ACTION_TYPE_ADD);

        return true;
    }

    public function update(int $id, array $payload): ?bool
    {
        $coupon = $this->couponRepo->findById($id);
        if (! $coupon) {
            return null;
        }

        $this->ensureEditable($coupon);

        $normalized = $this->normalizeCouponPayload($payload);
        $this->ensureUniqueInternalName($normalized['name_internal'], (int) $normalized['coupon_type'], $id);

        $updated = $this->couponRepo->updateById($id, $normalized, $coupon);
        if ($updated) {
            $this->logCouponOperation($updated, UserOperationLog::ACTION_TYPE_UPDATE);
        }

        return $updated !== null;
    }

    public function options(): array
    {
        return [
            // 优惠券状态
            'status_options' => [
                ['code' => Coupon::STATUS_DISABLED, 'label' => $this->couponStatusLabel(Coupon::STATUS_DISABLED)],
                ['code' => Coupon::STATUS_ENABLED, 'label' => $this->couponStatusLabel(Coupon::STATUS_ENABLED)],
                ['code' => Coupon::STATUS_VOIDED, 'label' => $this->couponStatusLabel(Coupon::STATUS_VOIDED)],
                ['code' => Coupon::STATUS_STOPPED, 'label' => $this->couponStatusLabel(Coupon::STATUS_STOPPED)],
            ],
            // 优惠券过期状态
            'expired_status_options' => [
                ['code' => Coupon::EXPIRED_STATUS_NOT_EXPIRED, 'label' => $this->expiredStatusLabel(Coupon::EXPIRED_STATUS_NOT_EXPIRED)],
                ['code' => Coupon::EXPIRED_STATUS_EXPIRED, 'label' => $this->expiredStatusLabel(Coupon::EXPIRED_STATUS_EXPIRED)],
            ],
            // 优惠券类型；赠金券类聚合为虚拟分组（code=0），前端选定子类型后才提交真实 coupon_type
            'coupon_type_options' => $this->couponTypeOptions(),
            // 优惠券核销状态
            'redeem_status_options' => $this->redeemStatusOptions(),
            // 账号类型
            'trade_account_type_options' => array_map(
                static fn (TypeTradeAccount $type): array => [
                    'code' => $type->value,
                    'label' => $type->label(),
                ],
                TypeTradeAccount::cases()
            ),
            // 激活配置；active_config=null 表示关闭，传对象表示开启。
            'active_config_options' => $this->activeConfigOptions(),
            // 各券种对应的变量定义（前端新增/修改时据此渲染字段）
            'type_detail_options' => array_map(
                fn (array $detail): array => [
                    'code' => $detail['value'],
                    'label' => $detail['description'],
                    'relation' => is_array($detail['relation'] ?? null)
                        ? array_values($detail['relation'])
                        : [],
                    'variable' => $this->couponTypeVariableOptions($detail),
                ],
                TypeCoupon::details()
            ),
        ];
    }

    public function couponStatusLabel(int $status): string
    {
        return $this->translateOption('coupon.status', $status);
    }

    /**
     * 券类型下拉选项；CreditBonus 系列聚合成一个带 children 的虚拟分组项，
     * 分组 code 不在 coupon_type 校验白名单内，确保永不入库。
     */
    private function couponTypeOptions(): array
    {
        $groupTypes = Coupon::CREDIT_BONUS_GROUP_TYPES;
        $groupEmitted = false;
        $options = [];

        foreach (TypeCoupon::cases() as $type) {
            if (in_array($type, $groupTypes, true)) {
                if ($groupEmitted) {
                    continue;
                }

                $options[] = [
                    'code' => Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP,
                    'label' => $this->translateOption('coupon.type', Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP),
                    'children' => array_map(
                        fn (TypeCoupon $type): array => ['code' => $type->value, 'label' => $this->couponTypeLabel($type)],
                        $groupTypes
                    ),
                ];
                $groupEmitted = true;

                continue;
            }

            $options[] = ['code' => $type->value, 'label' => $this->couponTypeLabel($type)];
        }

        return $options;
    }

    public function updateStatus(int $id, int $status): ?array
    {
        $coupon = $this->couponRepo->findById($id);
        if (! $coupon) {
            return null;
        }

        $this->ensureStatusChangeAllowed($coupon, $status);

        $updated = DB::connection(DB_API)->transaction(function () use ($id, $status, $coupon): ?Coupon {
            $updatedCoupon = $this->couponRepo->updateById($id, ['status' => $status], $coupon);
            if (! $updatedCoupon) {
                return null;
            }

            if ($status === Coupon::STATUS_VOIDED) {
                // Empty userCouponIds means all user coupons under this coupon template.
                TsCoupon::voidCoupon($id, []);
                $this->userCouponRepo->forceVoidByCouponId((int) $updatedCoupon->id);
            }

            return $updatedCoupon;
        });

        if (! $updated) {
            return null;
        }

        $this->logCouponOperation($updated, UserOperationLog::ACTION_TYPE_UPDATE);

        return [
            'id' => (int) $updated->id,
            'status' => (int) $updated->status,
            'status_text' => $this->couponStatusLabel((int) $updated->status),
        ];
    }

    public function getUserCouponList(array $filters, int $limit, int $offset): array
    {
        $filters = $this->applyOperatorNameFilter($filters);
        if ($this->isEmptyCouponFilter($filters)) {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        [$records, $count] = $this->userCouponRepo->getList($filters, $limit, $offset);
        $bizPayloads = $this->resolveBizPayloads($records);

        return [
            'data' => array_map(fn (mixed $record): array => $this->formatUserCoupon($record, $bizPayloads), $records),
            'total_count' => $count,
        ];
    }

    public function getTradeCashbackDetailList(int $userCouponId, array $filters, int $limit, int $offset): array
    {
        [$records, $count] = $this->userCouponRepo->getTradeCashbackDetailList($userCouponId, $filters, $limit, $offset);
        $tradePayloads = $this->resolveTradeOrderPayloads($this->collectTradeCashbackDetailTickets($records));

        return [
            'data' => array_map(
                fn (mixed $record): array => $this->formatTradeCashbackDetail($record, $tradePayloads),
                $records
            ),
            'total_count' => $count,
        ];
    }

    public function getCreditBonusDetailList(int $userCouponId, int $limit, int $offset): array
    {
        [$count, $records] = $this->userCreditRecordRepo->getCreditBonusDetailList($userCouponId, $limit, $offset);
        $ledgers = $this->groupCreditLedgersByRecordId(
            $this->userCreditLedgerRepo->listsByCreditRecordIds(
                array_map(fn (UserCreditRecord $record): int => (int) $record->id, $records)
            )
        );

        return [
            'data' => array_map(
                fn (UserCreditRecord $record): array => $this->formatCreditBonusDetail($record, $ledgers[(int) $record->id] ?? []),
                $records
            ),
            'total_count' => $count,
        ];
    }

    private function userCouponStatusLabel(int $status): string
    {
        return $this->translateOption('coupon.user_coupon_status', $status);
    }

    private function normalizeCouponPayload(array $payload): array
    {
        return [
            'name_internal' => trim((string) ($payload['name_internal'] ?? '')),
            'name_external' => $this->normalizeLocaleMap($payload['name_external'] ?? []),
            'description' => $this->normalizeLocaleMap($payload['rule_name'] ?? []),
            'coupon_type' => (int) ($payload['coupon_type'] ?? 0),
            'start_at' => trim((string) ($payload['start_at'] ?? '')),
            'end_at' => trim((string) ($payload['end_at'] ?? '')),
            'config' => $this->normalizeConfig((int) ($payload['coupon_type'] ?? 0), $payload['config'] ?? []),
            'active_config' => $this->normalizeActiveConfig($payload['active_config'] ?? null),
        ];
    }

    private function normalizeActiveConfig(mixed $config): ?array
    {
        if (! is_array($config)) {
            return null;
        }

        return [
            'unit_type' => $config['unit_type'] ?? null,
            'unit_size' => (float) $this->formatDecimal($config['unit_size'] ?? 0, 6),
            'expire_days' => (int) ($config['expire_days'] ?? 0),
            'source' => is_array($config['source'] ?? null) ? array_values($config['source']) : [],
        ];
    }

    /**
     * 按券种规范化 config JSON，确保入库格式一致。
     */
    private function normalizeConfig(int $couponType, array $config): array
    {
        $type = TypeCoupon::tryFrom($couponType);
        if ($type === null) {
            return [];
        }

        $normalized = match ($type) {
            TypeCoupon::LossRecover => [
                'discount_method' => (int) ($config['discount_method'] ?? 0),
                'discount_rate' => (float) $this->formatDiscountRateForStorage($config['discount_rate'] ?? 0),
                'discount_cap' => (float) $this->formatDecimal($config['discount_cap'] ?? 0, 6),
            ],
            TypeCoupon::TradeCashback => [
                'total_amount' => (float) $this->formatDecimal($config['total_amount'] ?? 0, 6),
                'unit_type' => (int) ($config['unit_type'] ?? 0),
                'unit_size' => (float) $this->formatDecimal($config['unit_size'] ?? 0, 6),
                'unit_amount' => (float) $this->formatDecimal($config['unit_amount'] ?? 0, 6),
                'source' => is_array($config['source'] ?? null) ? array_values($config['source']) : [],
            ],
            default => [],
        };
        foreach ($this->couponConfigVariables($type) as $variable) {
            $field = (string) ($variable['variable'] ?? '');
            if ($field === '' || ! array_key_exists($field, $config)) {
                continue;
            }

            if ($field === 'deposited_rate'
                && ($type !== TypeCoupon::LossRecover
                    || (int) ($config['discount_method'] ?? 0) !== TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT)
            ) {
                continue;
            }

            $normalized[$field] = $this->normalizeConfigValue(
                $field,
                $config[$field],
                $variable,
                (int) ($config['unlock_unit_type'] ?? 0)
            );
        }

        // 过滤空值
        return array_filter($normalized, static fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    }

    private function normalizeConfigValue(string $field, mixed $value, array $variable, int $unlockUnitType = 0): mixed
    {
        if ($field === 'source' || str_ends_with($field, '_source')) {
            return is_array($value) ? array_values($value) : [];
        }

        if ($field === 'unlock_status') {
            return (bool) $value;
        }

        if (in_array($field, ['discount_rate', 'deposited_rate'], true)) {
            return (float) $this->formatDiscountRateForStorage($value);
        }

        // 解锁单位=交易手续费时，解锁金额为手续费比例：前端传百分点（50=50%），入库统一 ÷100。
        if ($field === 'unlock_unit_amount' && $unlockUnitType === TypeCoupon::UNIT_TYPE_TRADE_FEE) {
            return (float) $this->formatDiscountRateForStorage($value);
        }

        if (str_ends_with($field, '_days')) {
            return (int) $value;
        }

        $optionValues = array_column((array) ($variable['options'] ?? []), 'value');
        if ($optionValues !== [] && collect($optionValues)->every(static fn (mixed $option): bool => is_int($option))) {
            return (int) $value;
        }

        if ($this->isNumericConfigField($field)) {
            return (float) $this->formatDecimal($value, 6);
        }

        return $value;
    }

    private function normalizeLocaleMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $lang => $text) {
            $lang = trim((string) $lang);
            $text = trim((string) $text);
            if ($lang === '' || $text === '') {
                continue;
            }

            $result[$lang] = $text;
        }

        return $result;
    }

    private function logCouponOperation(Coupon $coupon, int $actionType): void
    {
        $this->userOperationLogRepo->addRecord(
            (int) $coupon->id,
            UserOperationLog::TARGET_TYPE_COUPON,
            $this->buildCouponLogContent($coupon),
            $actionType
        );
    }

    private function buildCouponLogContent(Coupon $coupon): string
    {
        return (string) json_encode([
            'coupon_type' => $this->enumValue($coupon->coupon_type),
            'name_internal' => (string) $coupon->name_internal,
            'name_external' => $this->decodeLocaleField($coupon->name_external),
            'rule_name' => $this->decodeLocaleField($coupon->description),
            'start_at' => $this->formatDateTime($coupon->start_at),
            'end_at' => $this->formatDateTime($coupon->end_at),
            'config' => $coupon->config,
            'active_config' => $coupon->active_config,
            'status' => (int) $coupon->status,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function ensureUniqueInternalName(string $nameInternal, int $couponType, ?int $excludeId = null): void
    {
        if ($this->couponRepo->existsByInternalNameAndType($nameInternal, $couponType, $excludeId)) {
            throw new BusinessException(trans('public.message.already_exist'));
        }
    }

    private function ensureEditable(Coupon $coupon): void
    {
        if (! in_array((int) $coupon->status, Coupon::EDITABLE_STATUSES, true)) {
            throw new BusinessException(trans('public.message.state_error'));
        }
    }

    private function ensureStatusChangeAllowed(Coupon $coupon, int $targetStatus): void
    {
        $currentStatus = (int) $coupon->status;

        if ($targetStatus === Coupon::STATUS_ENABLED && $currentStatus !== Coupon::STATUS_DISABLED) {
            throw new BusinessException(trans('public.message.state_error'));
        }

        if ($targetStatus === Coupon::STATUS_STOPPED && $currentStatus !== Coupon::STATUS_ENABLED) {
            throw new BusinessException(trans('public.message.state_error'));
        }

        if ($targetStatus === Coupon::STATUS_VOIDED && ! in_array($currentStatus, Coupon::VOIDABLE_STATUSES, true)) {
            throw new BusinessException(trans('public.message.state_error'));
        }

        if ($targetStatus === Coupon::STATUS_STOPPED) {
            $this->ensureNoEnabledGrantRules($coupon);
        }

        if ($targetStatus === Coupon::STATUS_VOIDED) {
            $this->ensureNoEnabledGrantRulesForVoiding($coupon);
        }
    }

    private function ensureNoEnabledGrantRules(Coupon $coupon): void
    {
        $ruleNames = $this->grantRuleRepo->getEnabledUnfinishedNamesByCouponId((int) $coupon->id);
        if ($ruleNames === []) {
            return;
        }

        throw new BusinessException(trans('coupon.message.active_grant_rule_conflict', [
            'names' => $this->formatGrantRuleNames($ruleNames),
        ]));
    }

    private function ensureNoEnabledGrantRulesForVoiding(Coupon $coupon): void
    {
        $ruleNames = $this->grantRuleRepo->getEnabledUnfinishedNamesByCouponId((int) $coupon->id);
        $ruleCount = count($ruleNames);

        if ($ruleCount === 0) {
            return;
        }

        if ($ruleCount === 1) {
            throw new BusinessException(trans('coupon.message.active_grant_rule_conflict', [
                'names' => $ruleNames[0],
            ]));
        }

        throw new BusinessException(trans('coupon.message.active_grant_rule_conflict_multiple', [
            'names' => $this->formatGrantRuleNames($ruleNames),
        ]));
    }

    private function formatGrantRuleNames(array $ruleNames): string
    {
        $separator = str_starts_with(app()->getLocale(), 'zh') ? '、' : ', ';

        return implode($separator, $ruleNames);
    }

    /**
     * 根据券种解析 config JSON，返回前端展示用的配置字段。
     * 每种券种返回不同的字段集合，前端按 coupon_type 区分。
     */
    private function parseCouponConfig(Coupon $coupon): array
    {
        $config = $coupon->config ?? [];

        return match ($this->enumValue($coupon->coupon_type)) {
            // 抹亏券：按亏损金额比例返利，需配置计算基数、抹亏比例、上限
            TypeCoupon::LossRecover->value => [
                'trade_account_type' => (int) ($config['trade_account_type'] ?? 0),
                'discount_method' => (int) ($config['discount_method'] ?? 0),           // 计算基数：1=按亏损金额，2=按入金金额
                'discount_method_text' => $this->discountMethodLabel((int) ($config['discount_method'] ?? 0)),
                'discount_rate' => $this->formatPercentage($config['discount_rate'] ?? '0', 2),  // 抹亏比例（%），如 15 表示 15%
                'discount_cap' => $this->formatDecimal($config['discount_cap'] ?? '0', 6),       // 抹亏上限
                'deposited_rate' => array_key_exists('deposited_rate', $config)
                    ? $this->formatPercentage($config['deposited_rate'], 2)
                    : null,
            ],
            // 交易返现券：按交易量返现，需配置返现总额、计算单位、单笔返现金额等
            TypeCoupon::TradeCashback->value => [
                'trade_account_type' => (int) ($config['trade_account_type'] ?? 0),
                'total_amount' => $this->formatDecimal($config['total_amount'] ?? '0', 6),    // 返现总额
                'unit_type' => (int) ($config['unit_type'] ?? 0),                          // 计算单位：1=按手数，2=按交易量
                'unit_type_text' => $this->unitTypeLabel((int) ($config['unit_type'] ?? 0)),
                'unit_size' => $this->formatDecimal($config['unit_size'] ?? '0', 6),       // 单位大小
                'unit_amount' => $this->formatDecimal($config['unit_amount'] ?? '0', 6),     // 单位金额
                'source' => $config['source'] ?? [],                                   // 适用品种（数组）
            ],
            // 赠金券系列：config 原样返回，仅手续费比例出库时还原为百分点
            TypeCoupon::CreditBonus->value,
            TypeCoupon::RestrictedCreditBonus->value => $this->parseCreditBonusConfig($config),
            // New coupon types follow the scaffold structure and return their stored config directly.
            default => is_array($config) ? $config : [],
        };
    }

    /**
     * 解锁单位=交易手续费时解锁金额为手续费比例（入库 ÷100），出库 ×100 还原为百分点；
     * 其余字段原样返回，保证与前端提交格式往返一致。
     */
    private function parseCreditBonusConfig(array $config): array
    {
        if ((int) ($config['unlock_unit_type'] ?? 0) === TypeCoupon::UNIT_TYPE_TRADE_FEE
            && array_key_exists('unlock_unit_amount', $config)
        ) {
            $config['unlock_unit_amount'] = $this->formatPercentage($config['unlock_unit_amount'], 2);
        }

        return $config;
    }

    private function parseActiveConfig(mixed $config): ?array
    {
        if (! is_array($config) || $config === []) {
            return null;
        }

        return [
            'unit_type' => $config['unit_type'] ?? null,
            'unit_size' => $this->formatDecimal($config['unit_size'] ?? 0, 6),
            'expire_days' => (int) ($config['expire_days'] ?? 0),
            'source' => is_array($config['source'] ?? null) ? array_values($config['source']) : [],
        ];
    }

    /**
     * 根据字段名检测前端 UI 组件类型，与 PointRuleConstant 的检测逻辑保持一致。
     *
     * @return self::FIELD_TYPE_*
     */
    private function detectFieldType(string $field, array $options = []): int
    {
        if ($options !== []) {
            return FieldType::SELECT;
        }

        return match (true) {
            $field === 'unlock_status' => FieldType::SWITCH,
            $field === 'source', str_ends_with($field, '_source') => FieldType::CASCADER,
            $this->isNumericConfigField($field) => FieldType::NUMBER,
            default => FieldType::TEXT,
        };
    }

    private function couponTypeVariableOptions(array $detail): array
    {
        $requiredVariables = is_array($detail['required_variables'] ?? null)
            ? $detail['required_variables']
            : [];
        $variables = is_array($detail['variable'] ?? null) ? $detail['variable'] : [];
        $items = array_map(function (array $variable) use ($requiredVariables, $detail): array {
            $field = (string) ($variable['variable'] ?? '');
            $description = (string) ($variable['description'] ?? '');
            $options = is_array($variable['options'] ?? null) ? $variable['options'] : [];
            $isRequired = in_array($field, $requiredVariables, true)
                || Coupon::isConfigFieldRequired((int) ($detail['value'] ?? 0), $field);
            $type = $this->detectFieldType($field, $options);
            $labels = $this->resolveConfigFieldLabels($field);
            $item = [
                'variable' => $field,
                'variable_label' => $labels['label'] ?: $description,
                'description' => $description,
                'type' => $type,
                'type_text' => $this->fieldTypeLabel($type),
                'is_required' => $isRequired,
                'is_select_required' => $isRequired && $options !== [],
            ];

            if ($labels['note'] !== null) {
                $item['variable_label_note'] = $labels['note'];
            }

            if ($options !== []) {
                $item['options'] = $options;
            }

            return $item;
        }, $this->couponTypeVariables((int) ($detail['value'] ?? 0), $variables));

        return $this->nestUnlockVariableOptions($items);
    }

    private function nestUnlockVariableOptions(array $variables): array
    {
        if (! collect($variables)->contains('variable', 'unlock_status')) {
            return $variables;
        }

        $children = array_values(array_filter(
            $variables,
            fn (array $variable): bool => $this->isUnlockChildField((string) ($variable['variable'] ?? ''))
        ));
        $result = [];

        foreach ($variables as $variable) {
            $field = (string) ($variable['variable'] ?? '');
            if ($this->isUnlockChildField($field)) {
                continue;
            }

            if ($field === 'unlock_status') {
                $variable['children'] = $children;
            }

            $result[] = $variable;
        }

        return $result;
    }

    private function isUnlockChildField(string $field): bool
    {
        return $field !== 'unlock_status' && str_starts_with($field, 'unlock_');
    }

    private function isNumericConfigField(string $field): bool
    {
        foreach (['_rate', '_amount', '_size', '_days', '_cap', '_leverage'] as $suffix) {
            if (str_ends_with($field, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function activeConfigOptions(): array
    {
        $unitTypeOptions = array_map(
            fn (int|string $unitType): array => [
                'value' => $unitType,
                'label' => $this->activeUnitTypeLabel($unitType),
            ],
            Coupon::activeUnitTypes()
        );

        return [
            'relation' => ['and'],
            'variable' => [
                $this->activeConfigFieldOption('unit_type', FieldType::SELECT, true, $unitTypeOptions),
                $this->activeConfigFieldOption('unit_size', FieldType::NUMBER, true),
                $this->activeConfigFieldOption('expire_days', FieldType::NUMBER, true),
                $this->activeConfigFieldOption('source', FieldType::CASCADER, false),
            ],
        ];
    }

    private function activeConfigFieldOption(
        string $field,
        int $type,
        bool $isRequired,
        array $options = []
    ): array {
        $labelKey = "coupon.active_config_field.{$field}.label";
        $descriptionKey = "coupon.active_config_field.{$field}.description";
        $label = trans($labelKey);
        $description = trans($descriptionKey);

        $item = [
            'variable' => $field,
            'variable_label' => $label === $labelKey ? $field : $label,
            'description' => $description === $descriptionKey ? '' : $description,
            'type' => $type,
            'type_text' => $this->fieldTypeLabel($type),
            'is_required' => $isRequired,
            'is_select_required' => $isRequired && $options !== [],
        ];

        if ($options !== []) {
            $item['options'] = $options;
        }

        return $item;
    }

    private function activeUnitTypeLabel(int|string $unitType): string
    {
        $key = "coupon.active_unit_type.{$unitType}";
        $label = trans($key);

        return $label === $key ? (string) $unitType : $label;
    }

    private function couponTypeVariables(int $couponType, array $variables): array
    {
        if ($couponType !== TypeCoupon::LossRecover->value) {
            return $variables;
        }

        $depositedRate = [
            'variable' => 'deposited_rate',
            'description' => 'Deposit rate',
        ];

        if (collect($variables)->contains('variable', 'deposited_rate')) {
            return $variables;
        }

        $result = [];
        foreach ($variables as $variable) {
            if (($variable['variable'] ?? null) === 'discount_rate') {
                $result[] = $depositedRate;
            }

            $result[] = $variable;
        }

        return $result === $variables ? [...$variables, $depositedRate] : $result;
    }

    private function couponConfigVariables(TypeCoupon $couponType): array
    {
        $detail = collect(TypeCoupon::details())->firstWhere('value', $couponType->value);
        $variables = is_array($detail['variable'] ?? null) ? $detail['variable'] : [];

        return $this->couponTypeVariables($couponType->value, $variables);
    }

    /**
     * 字段 UI 类型 → 中文名。
     */
    private function fieldTypeLabel(int $type): string
    {
        return match ($type) {
            FieldType::TEXT => '文本框',
            FieldType::SELECT => '下拉框',
            FieldType::CASCADER => '级联选择器',
            FieldType::SWITCH => '开关',
            FieldType::NUMBER => '数字输入框',
            default => '未知',
        };
    }

    /**
     * 字段名 → 前端显示名 + 可选注释。
     */
    private function resolveConfigFieldLabels(string $field): array
    {
        $label = trans("coupon.config_field.{$field}.label");
        $label = $label === "coupon.config_field.{$field}.label" ? '' : $label;

        $note = trans("coupon.config_field.{$field}.note");
        $note = $note === "coupon.config_field.{$field}.note" ? null : $note;

        return ['label' => $label, 'note' => $note];
    }

    private function formatCoupon(Coupon $coupon, array $operateInfos): array
    {
        $nameExternal = $this->decodeLocaleField($coupon->name_external);
        $ruleName = $this->decodeLocaleField($coupon->description);
        $couponType = $this->enumValue($coupon->coupon_type);
        $expiredStatus = $this->resolveExpiredStatus($coupon->end_at);
        $operateInfo = $operateInfos[$this->couponOperateKey((int) $coupon->id, $couponType)] ?? null;

        return [
            'id' => (int) $coupon->id,
            'name_internal' => (string) $coupon->name_internal,
            'name_external' => $nameExternal,
            'name_external_text' => $this->resolveLocalizedText($nameExternal),
            'locale_configs' => $this->buildLocaleConfigs($nameExternal, $ruleName),
            'coupon_type' => $couponType,
            'coupon_type_text' => $this->couponTypeLabel($couponType),
            'rule_name' => $ruleName,
            'rule_name_text' => $this->resolveLocalizedText($ruleName),
            'start_at' => $this->formatDateTime($coupon->start_at),
            'end_at' => $this->formatDateTime($coupon->end_at),
            'config' => $this->parseCouponConfig($coupon),
            'active_config' => $this->parseActiveConfig($coupon->active_config),
            'status' => (int) $coupon->status,
            'status_text' => $this->couponStatusLabel((int) $coupon->status),
            'expired_status' => $expiredStatus,
            'expired_status_text' => $this->expiredStatusLabel($expiredStatus),
            'operator_id' => (int) ($operateInfo['operator_id'] ?? 0),
            'operator_name' => (string) ($operateInfo['operator_name'] ?? ''),
        ];
    }

    private function formatUserCoupon(mixed $record, array $bizPayloads): array
    {
        $nameExternal = $this->decodeLocaleField(data_get($record, 'name_external'));
        $couponType = $this->enumValue(data_get($record, 'coupon_type'));
        $tradeAccountId = data_get($record, 'trade_account_id');
        $tradeAccountType = data_get($record, 'trade_account_type');
        $status = (int) data_get($record, 'status', UserCoupon::STATUS_USABLE);
        $expiredStatus = $status === UserCoupon::STATUS_EXPIRED
            ? Coupon::EXPIRED_STATUS_EXPIRED
            : Coupon::EXPIRED_STATUS_NOT_EXPIRED;
        $bizId = $this->nullableString(data_get($record, 'biz_id'));
        // 手续费减免券核销口径与交易返现券一致，但列表不展示订单、手数相关信息。
        if ($couponType === TypeCoupon::FeeReduction->value) {
            $bizId = null;
        }
        $lookupBizId = $bizId;
        if ($lookupBizId === null && $couponType === TypeCoupon::TradeCashback->value) {
            $lookupBizId = $this->extractTicketFromEventData(data_get($record, 'event_data'));
            $lookupBizId = $lookupBizId !== '' ? $lookupBizId : null;
        }
        $bizPayload = $this->resolveBizPayload($lookupBizId, $bizPayloads);

        $totalAmount = (float) data_get($record, 'total_amount', 0);
        $usedAmount = (float) data_get($record, 'used_amount', 0);
        $redeemStatus = $this->resolveRedeemStatus($couponType, $totalAmount, $usedAmount);

        return [
            'id' => (int) data_get($record, 'id', 0),
            'biz_id' => $bizId,
            'trade_account_id' => $tradeAccountId === null ? null : (int) $tradeAccountId,
            'trade_account_type' => $tradeAccountType === null ? null : (int) $tradeAccountType,
            'trade_account_type_text' => $this->tradeAccountTypeLabel($tradeAccountType),
            'user_code' => (int) data_get($record, 'user_code', 0),
            'name_internal' => (string) data_get($record, 'name_internal', ''),
            'name_external' => $nameExternal,
            'name_external_text' => $this->resolveLocalizedText($nameExternal),
            'coupon_type' => $couponType,
            'coupon_type_text' => $this->couponTypeLabel($couponType),
            'triggered_at' => $this->formatDateTime(data_get($record, 'triggered_at')),
            'used_at' => $this->formatDateTime(data_get($record, 'used_at')),
            'start_at' => $this->formatDateTime(data_get($record, 'start_at')),
            'end_at' => $this->formatDateTime(data_get($record, 'end_at')),
            'position_size' => $this->nullableDecimal(data_get($bizPayload, 'position_size'), 6),
            'trade_volume' => $this->nullableDecimal(data_get($bizPayload, 'trade_volume'), 2),
            'total_amount' => $this->formatDecimal($totalAmount, 6),
            'used_amount' => $this->formatDecimal($usedAmount, 6),
            'status' => $status,
            'status_text' => $this->userCouponStatusLabel($status),
            'expired_status' => $expiredStatus,
            'expired_status_text' => $this->expiredStatusLabel($expiredStatus),
            'redeem_status' => $redeemStatus,
            'redeem_status_text' => $this->resolveRedeemStatusLabel($couponType, $redeemStatus),
            'operator_name' => (string) data_get($record, 'operator_name', ''),
        ];
    }

    private function formatTradeCashbackDetail(mixed $record, array $tradePayloads): array
    {
        $eventData = $this->decodeEventData(data_get($record, 'event_data'));
        $ticket = trim((string) ($eventData['ticket'] ?? data_get($record, 'biz_id', '')));
        $tradePayload = $this->resolveBizPayload($ticket, $tradePayloads);
        $positionSize = data_get($tradePayload, 'position_size');
        $tradeVolume = data_get($tradePayload, 'trade_volume');
        $closeTime = $eventData['close_time'] ?? data_get($tradePayload, 'close_time');

        return [
            'id' => (int) data_get($record, 'id', 0),
            'ticket' => $ticket,
            'position_size' => $this->nullableDecimal($positionSize, 6),
            'trade_volume' => $this->nullableDecimal($tradeVolume, 2),
            'open_time' => $this->formatDateTime(data_get($tradePayload, 'open_time')),
            'close_time' => $this->isUnsetTimestamp($closeTime) ? '-' : $this->formatDateTime($closeTime),
        ];
    }

    private function isUnsetTimestamp(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_numeric($value)) {
            return (float) $value <= 0;
        }

        return Carbon::parse($value)->getTimestamp() === 0;
    }

    /**
     * 按 user_credit_record.id 分组流水。
     *
     * @param  array<int, UserCreditLedger>  $ledgers
     * @return array<int, array<int, UserCreditLedger>>
     */
    private function groupCreditLedgersByRecordId(array $ledgers): array
    {
        $grouped = [];
        foreach ($ledgers as $ledger) {
            $grouped[(int) $ledger->user_credit_record_id][] = $ledger;
        }

        return $grouped;
    }

    private function formatCreditBonusDetail(UserCreditRecord $record, array $ledgers): array
    {
        $status = (int) $record->status;
        $initialAmount = (float) $record->grant_amount;
        $unlockedAmount = $this->resolveCreditBonusUnlockedAmount($ledgers);

        return [
            'id' => (int) $record->id,
            'ticket' => $this->resolveCreditBonusGrantTicket($ledgers),
            'initial_bonus_amount' => $this->formatDecimal($initialAmount, 6),
            'remaining_bonus_amount' => $this->formatDecimal(max(0, $initialAmount - (float) $record->deduct_amount), 6),
            'unlocked_bonus_amount' => $this->formatDecimal($unlockedAmount, 6),
            'currency' => UserCreditRecord::CURRENCY,
            'bonus_status' => $status,
            'bonus_status_text' => $this->translateOption('coupon.credit_bonus_status', $status),
            'credited_at' => $this->formatDateTime($record->created_at),
            'expired_at' => $this->formatDateTime($record->end_at),
        ];
    }

    /**
     * 已解锁金额取解锁流水累计。
     */
    private function resolveCreditBonusUnlockedAmount(array $ledgers): float
    {
        $unlockedAmount = 0.0;
        foreach ($ledgers as $ledger) {
            if ($ledger->type === TypeCreditLedger::Unlock) {
                $unlockedAmount += (float) $ledger->amount;
            }
        }

        return $unlockedAmount;
    }

    /**
     * 订单号取发放流水单号。
     */
    private function resolveCreditBonusGrantTicket(array $ledgers): string
    {
        foreach ($ledgers as $ledger) {
            if ($ledger->type === TypeCreditLedger::Grant && (string) $ledger->ticket !== '') {
                return (string) $ledger->ticket;
            }
        }

        return '';
    }

    private function buildLocaleConfigs(array $nameExternal, array $ruleName): array
    {
        $languages = array_unique(array_merge(array_keys($nameExternal), array_keys($ruleName)));
        $result = [];

        foreach ($this->orderLanguages($languages) as $lang) {
            $result[] = [
                'lang' => $lang,
                'name_external' => $nameExternal[$lang] ?? '',
                'rule_name' => $ruleName[$lang] ?? '',
            ];
        }

        return $result;
    }

    private function decodeLocaleField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return [config('app.fallback_locale', 'en_US') => (string) $value];
    }

    private function resolveLocalizedText(array $values): string
    {
        if (empty($values)) {
            return '';
        }

        $locale = app()->getLocale();
        if (isset($values[$locale]) && $values[$locale] !== '') {
            return (string) $values[$locale];
        }

        $fallback = config('app.fallback_locale', 'en_US');
        if (isset($values[$fallback]) && $values[$fallback] !== '') {
            return (string) $values[$fallback];
        }

        return (string) (reset($values) ?: '');
    }

    private function tradeAccountTypeLabel(mixed $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }

        $tradeAccountType = (int) $type;

        return TypeTradeAccount::tryFrom($tradeAccountType)?->label() ?? (string) $tradeAccountType;
    }

    private function couponTypeLabel(int|TypeCoupon $type): string
    {
        return Coupon::typeLabel($type);
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

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    private function formatDecimal(mixed $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
    }

    private function formatDiscountRateForStorage(mixed $value): string
    {
        return $this->formatDecimal(((float) $value) / 100, 4);
    }

    private function formatPercentage(mixed $value, int $scale): string
    {
        return $this->formatDecimal(((float) $value) * 100, $scale);
    }

    private function enumValue(mixed $value): int
    {
        return $value instanceof BackedEnum ? (int) $value->value : (int) $value;
    }

    private function orderLanguages(array $languages): array
    {
        $configured = array_keys(config('lang.languages', []));
        $ordered = [];

        foreach ($configured as $lang) {
            if (in_array($lang, $languages, true)) {
                $ordered[] = $lang;
            }
        }

        foreach ($languages as $lang) {
            if (! in_array($lang, $ordered, true)) {
                $ordered[] = $lang;
            }
        }

        return $ordered;
    }

    private function discountMethodLabel(int $method): string
    {
        return $this->translateOption('coupon.discount_method', $method);
    }

    private function expiredStatusLabel(int $status): string
    {
        return $this->translateOption('coupon.expired_status', $status);
    }

    private function redeemStatusLabel(int $status): string
    {
        return $this->translateOption('coupon.redeem_result', $status);
    }

    private function redeemStatusOptions(): array
    {
        $options = [];
        foreach ($this->redeemStatusLabels() as $code => $label) {
            if ((int) $code === $this->redeemStatusCodeByIndex(0)) {
                continue;
            }

            $options[] = [
                'code' => (int) $code,
                'label' => (string) $label,
            ];
        }

        return $options;
    }

    private function redeemStatusCodeByIndex(int $index): int
    {
        $codes = array_keys($this->redeemStatusLabels());

        return (int) ($codes[$index] ?? $index);
    }

    private function redeemStatusLabels(): array
    {
        $labels = trans('coupon.redeem_result');
        if (! is_array($labels)) {
            return [];
        }

        unset($labels['unknown']);

        return $labels;
    }

    private function unitTypeLabel(int $unitType): string
    {
        return $this->translateOption('coupon.unit_type', $unitType);
    }

    private function translateOption(string $group, int|string $value): string
    {
        $key = sprintf('%s.%s', $group, $value);
        $label = trans($key);

        return $label === $key ? trans(sprintf('%s.unknown', $group)) : $label;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function nullableDecimal(mixed $value, int $scale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->formatDecimal($value, $scale);
    }

    private function resolveBizPayloads(array $records): array
    {
        $bizIdsByType = $this->collectBizIdsByType($records);
        $payloads = [];

        foreach ($bizIdsByType as $bizType => $bizIds) {
            $payloads += match ($bizType) {
                UserCoupon::BIZ_TYPE_TRADE_ORDER => $this->resolveTradeOrderPayloads($bizIds),
                default => [],
            };
        }

        return $payloads;
    }

    private function collectBizIdsByType(array $records): array
    {
        $bizIdsByType = [];

        foreach ($records as $record) {
            if ((int) data_get($record, 'coupon_type', 0) === TypeCoupon::FeeReduction->value) {
                continue;
            }

            $bizId = trim((string) data_get($record, 'biz_id', ''));
            if ($bizId === '') {
                $couponType = (int) data_get($record, 'coupon_type', 0);
                if ($couponType === TypeCoupon::TradeCashback->value) {
                    $bizId = $this->extractTicketFromEventData(data_get($record, 'event_data'));
                }
            }
            $bizType = $this->inferBizType($bizId);
            if ($bizType === null) {
                continue;
            }

            $bizIdsByType[$bizType][] = $bizId;
        }

        foreach ($bizIdsByType as $bizType => $bizIds) {
            $bizIdsByType[$bizType] = array_values(array_unique($bizIds));
        }

        return $bizIdsByType;
    }

    private function collectTradeCashbackDetailTickets(array $records): array
    {
        $tickets = [];
        foreach ($records as $record) {
            $eventData = $this->decodeEventData(data_get($record, 'event_data'));
            $ticket = trim((string) ($eventData['ticket'] ?? data_get($record, 'biz_id', '')));
            if ($ticket !== '') {
                $tickets[] = $ticket;
            }
        }

        return array_values(array_unique($tickets));
    }

    private function inferBizType(mixed $bizId): ?string
    {
        $bizId = trim((string) $bizId);
        if ($bizId === '') {
            return null;
        }

        return UserCoupon::BIZ_TYPE_TRADE_ORDER;
    }

    private function resolveTradeOrderPayloads(array $bizIds): array
    {
        $metrics = $this->tradeRepo->getOrderMetricsByTickets($bizIds);
        if (empty($metrics)) {
            return [];
        }

        $contractSizes = $this->getSourceContractSizeMap();
        foreach ($metrics as $ticket => $metric) {
            $source = (string) ($metric['source'] ?? '');
            $orderQty = (float) ($metric['order_qty'] ?? 0);
            $contractSize = (float) ($contractSizes[$source] ?? 0);
            $metrics[$ticket]['trade_volume'] = $contractSize > 0 ? round($orderQty / $contractSize, 2) : null;
        }

        $payloads = [];
        foreach ($metrics as $ticket => $metric) {
            $payloads[$this->bizPayloadKey(UserCoupon::BIZ_TYPE_TRADE_ORDER, (string) $ticket)] = $metric;
        }

        return $payloads;
    }

    private function resolveBizPayload(?string $bizId, array $bizPayloads): ?array
    {
        $bizType = $this->inferBizType($bizId);
        if ($bizType === null || $bizId === null || trim($bizId) === '') {
            return null;
        }

        return $bizPayloads[$this->bizPayloadKey($bizType, trim($bizId))] ?? null;
    }

    private function bizPayloadKey(string $bizType, string $bizId): string
    {
        return $bizType.':'.$bizId;
    }

    private function extractTicketFromEventData(mixed $eventData): string
    {
        return (string) ($this->decodeEventData($eventData)['ticket'] ?? '');
    }

    private function decodeEventData(mixed $eventData): array
    {
        if (is_array($eventData)) {
            return $eventData;
        }

        if (is_string($eventData) && $eventData !== '') {
            $decoded = json_decode($eventData, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function getSourceContractSizeMap(): array
    {
        if ($this->sourceContractSizeMap !== null) {
            return $this->sourceContractSizeMap;
        }

        $this->sourceContractSizeMap = [];
        foreach ($this->sourceRepo->getAll() as $source) {
            $code = (string) ($source['source'] ?? '');
            $contractSize = (float) data_get($source, 'config.contract_size', 0);
            if ($code === '' || $contractSize <= 0) {
                continue;
            }

            $this->sourceContractSizeMap[$code] = $contractSize;
        }

        return $this->sourceContractSizeMap;
    }

    private function applyOperatorNameFilter(array $filters): array
    {
        $operatorName = trim((string) ($filters['operator_name'] ?? ''));
        if ($operatorName === '') {
            return $filters;
        }

        $filters['coupon_ids'] = $this->userOperationLogRepo->getLatestCouponTargetIdsByOperatorName(
            $operatorName,
            [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]
        );

        return $filters;
    }

    private function isEmptyCouponFilter(array $filters): bool
    {
        return array_key_exists('coupon_ids', $filters) && $filters['coupon_ids'] === [];
    }

    /**
     * @param  array<int, Coupon>  $records
     * @return array<string, array{target_id:int,coupon_type:int,operator_id:int,operator_name:string,created_at:?string}>
     */
    private function getCouponOperateInfos(array $records): array
    {
        if (empty($records)) {
            return [];
        }

        $targetIds = [];
        $couponTypes = [];

        foreach ($records as $record) {
            $targetIds[] = (int) $record->id;
            $couponTypes[] = $this->enumValue($record->coupon_type);
        }

        return $this->userOperationLogRepo->getLatestCouponOperateInfo(
            $targetIds,
            $couponTypes,
            [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]
        );
    }

    private function couponOperateKey(int $targetId, int $couponType): string
    {
        return $targetId.':'.$couponType;
    }

    /**
     * 核销状态。
     * 抹亏券(1)：0=未核销，2=已核销
     * 返现券(2)：0=未核销，1=部分核销，2=已核销
     */
    private function resolveRedeemStatus(int $couponType, float $totalAmount, float $usedAmount): int
    {
        if ($usedAmount <= 0) {
            return $this->redeemStatusCodeByIndex(0); // 未核销
        }

        // 抹亏券：只要用过就算核销
        if ($couponType === TypeCoupon::LossRecover->value) {
            return $this->redeemStatusCodeByIndex(2); // 核销
        }

        // 返现券：按 total_amount 和 used_amount 差值判断
        if ($usedAmount >= $totalAmount) {
            return $this->redeemStatusCodeByIndex(2); // 已核销
        }

        return $this->redeemStatusCodeByIndex(1); // 部分核销
    }

    private function resolveRedeemStatusLabel(int $couponType, int $status): string
    {
        return $this->translateOption('coupon.redeem_result', $status);
    }
}
