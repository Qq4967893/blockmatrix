<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\FundingApply as ScaffoldFundingApply;
use Scaffold\Funding\Enums\FundingApplyStatus;
use Scaffold\Funding\Enums\FundingFinanceStatus;
use Scaffold\Funding\Enums\FundingRiskStatus;

class FundingApply extends ScaffoldFundingApply
{
    /** 自动任务写入的系统审核人 ID */
    public const SYSTEM_AUDITOR_ID = 0;

    /** 钱包服务明确表示未提交 PSP 的状态，仅此状态允许自动重试 */
    public const WALLET_STATUS_NOT_SUBMITTED = 'WITHDRAWAL_NOT_SUBMITTED';

    /** 出金钱包重试阶段：无重试记录（存量单默认值） */
    public const WALLET_RETRY_PHASE_NONE = 'none';

    /** 出金钱包重试阶段：自动重试等待中 */
    public const WALLET_RETRY_PHASE_RETRYING = 'retrying';

    /** 出金钱包重试阶段：钱包已受理提交 */
    public const WALLET_RETRY_PHASE_SUBMITTED = 'submitted';

    /** 出金钱包重试阶段：自动重试次数用完，已转失败人工处理 */
    public const WALLET_RETRY_PHASE_EXHAUSTED = 'exhausted';

    /** 出金钱包重试阶段：人工驳回/确认等操作已接管，取消自动重试 */
    public const WALLET_RETRY_PHASE_CANCELLED = 'cancelled';

    /** 出金钱包重试触发来源：首次发起 */
    public const WALLET_RETRY_TRIGGER_INITIAL = 'initial';

    /** 出金钱包重试触发来源：定时任务 */
    public const WALLET_RETRY_TRIGGER_AUTO = 'auto';

    /** 出金钱包重试触发来源：页面按钮 */
    public const WALLET_RETRY_TRIGGER_MANUAL = 'manual';

    /** 出金钱包重试尝试的占位租约（秒）：防止调度重叠期间同一单被重复发起 */
    public const WALLET_RETRY_CLAIM_LEASE_SECONDS = 900;

    /** 钱包出金自动重试次数上限（一单终身） */
    public const MAX_AUTO_RETRY_COUNT = 3;

    /** 钱包出金自动重试间隔（秒）：1 分钟、5 分钟、10 分钟 */
    public const AUTO_RETRY_INTERVALS = [60, 300, 600];

    /** 钱包出金自动重试每批扫描数量 */
    public const AUTO_RETRY_BATCH_SIZE = 100;

    public const DEPOSIT_TYPE_WALLET_GROUP = 1;

    public const DEPOSIT_TYPE_BANK_GROUP = 2;

    public const STATUS_PENDING = FundingApplyStatus::Pending->value;

    public const STATUS_APPROVED = FundingApplyStatus::Success->value;

    public const STATUS_FAILED = FundingApplyStatus::Failed->value;

    public const STATUS_CANCEL = FundingApplyStatus::Cancel->value;

    public const DEPOSIT_DIRECTION_OPTIONS = [
        self::DIRECTION_DEPOSIT,
        self::DIRECTION_WITHDRAW,
    ];

    public const DEPOSIT_STATUS_OPTIONS = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_FAILED,
    ];

    public const DEPOSIT_STATUS_FIRST_TIME = 'first_time';

    public const DEPOSIT_STATUS_MULTIPLE = 'multiple';

    public const DEPOSIT_STATUS_FILTER_OPTIONS = [
        self::DEPOSIT_STATUS_FIRST_TIME,
        self::DEPOSIT_STATUS_MULTIPLE,
    ];

    public const WITHDRAWAL_RISK_STATUS_OPTIONS = [
        'pending',
        'approved',
        'rejected',
    ];

    public const WITHDRAWAL_FINANCE_STATUS_OPTIONS = [
        'pending',
        'approved',
        'failed',
        'rejected',
    ];

    /** 风控审核结果（前端传值） */
    public const RISK_RESULT_APPROVED = 'approved';

    public const RISK_RESULT_REJECTED = 'rejected';

    /** 财务审核结果（前端传值，注意：success ≠ approved，前者是重试动作，后者是状态） */
    public const FINANCE_RESULT_SUCCESS = 'success';

    public const FINANCE_RESULT_REJECTED = 'rejected';

    /**
     * @return int[]
     */
    public static function depositTypeOptions(): array
    {
        return [
            self::DEPOSIT_TYPE_WALLET_GROUP,
            self::DEPOSIT_TYPE_BANK_GROUP,
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function withdrawalRiskStatusMap(): array
    {
        return [
            'pending' => FundingRiskStatus::Pending->value,
            'approved' => FundingRiskStatus::Approved->value,
            'rejected' => FundingRiskStatus::Rejected->value,
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function withdrawalFinanceStatusMap(): array
    {
        return [
            'pending' => FundingFinanceStatus::Pending->value,
            'approved' => FundingFinanceStatus::Approved->value,
            'failed' => FundingFinanceStatus::Failed->value,
            'rejected' => FundingFinanceStatus::Rejected->value,
        ];
    }
}
