<?php

namespace App\Models\Api;

use Scaffold\Framework\Enums\TypeLedger;
use Scaffold\Funding\Enums\FundingApplyStatus;

class TradeAccountLedger extends FundingLedger
{
    // 兼容旧资金流水模型命名，底层使用当前 funding_ledger 模型。

    // 入金筛选：充值类型
    public const DEPOSIT_TYPE_CHAIN  = 'chain';
    public const DEPOSIT_TYPE_WALLET = 'wallet';

    public const PLATFORM_COBO  = 1;
    public const PLATFORM_VAULT = 2;
    public const PLATFORM_MEPAY = 3;

    public const DEPOSIT_TYPE_WALLET_GROUP = 1; // 包含 Cobo + Vault
    public const DEPOSIT_TYPE_BANK_GROUP   = 2; // Mepay
    public const DEPOSIT_TYPE_OPTIONS = [
        self::DEPOSIT_TYPE_WALLET_GROUP,
        self::DEPOSIT_TYPE_BANK_GROUP,
    ];

    public const DEPOSIT_PLATFORM_OPTIONS = [
        self::PLATFORM_COBO,
        self::PLATFORM_VAULT,
        self::PLATFORM_MEPAY,
    ];

    // 入金筛选：状态
    public const DEPOSIT_STATUS_APPROVED = FundingApplyStatus::Success->value;
    public const DEPOSIT_STATUS_FAILED   = FundingApplyStatus::Failed->value;
    public const DEPOSIT_STATUS_OPTIONS = [
        self::DEPOSIT_STATUS_APPROVED,
        self::DEPOSIT_STATUS_FAILED,
    ];

    // 出金筛选：风控/财务状态
    public const WITHDRAWAL_RISK_STATUS_PENDING  = 'pending';
    public const WITHDRAWAL_RISK_STATUS_APPROVED = 'approved';
    public const WITHDRAWAL_RISK_STATUS_REJECTED = 'rejected';
    public const WITHDRAWAL_RISK_STATUS_CANCELED = 'canceled';
    public const WITHDRAWAL_RISK_STATUS_OPTIONS = [
        self::WITHDRAWAL_RISK_STATUS_PENDING,
        self::WITHDRAWAL_RISK_STATUS_APPROVED,
        self::WITHDRAWAL_RISK_STATUS_REJECTED,
        self::WITHDRAWAL_RISK_STATUS_CANCELED,
    ];

    public const WITHDRAWAL_FINANCE_STATUS_PENDING    = 'pending';
    public const WITHDRAWAL_FINANCE_STATUS_SUCCESS    = 'success';
    public const WITHDRAWAL_FINANCE_STATUS_FAILED     = 'failed';
    public const WITHDRAWAL_FINANCE_STATUS_REJECTED   = 'rejected';
    public const WITHDRAWAL_FINANCE_STATUS_PROCESSING = 'processing';
    public const WITHDRAWAL_FINANCE_STATUS_OPTIONS = [
        self::WITHDRAWAL_FINANCE_STATUS_PENDING,
        self::WITHDRAWAL_FINANCE_STATUS_SUCCESS,
        self::WITHDRAWAL_FINANCE_STATUS_FAILED,
        self::WITHDRAWAL_FINANCE_STATUS_REJECTED,
        self::WITHDRAWAL_FINANCE_STATUS_PROCESSING,
    ];

    // 资金流水类型与状态（前端 code 映射）
    public const FUND_FLOW_TYPE_DEPOSIT           = 'deposit';
    public const FUND_FLOW_TYPE_WITHDRAWAL        = 'withdrawal';
    public const FUND_FLOW_TYPE_WITHDRAWAL_RETURN = 'withdrawal_return';
    public const FUND_FLOW_TYPE_REFERRAL          = 'referral_commission';
    public const FUND_FLOW_TYPE_LOSS_COMPENSATION = 'loss_compensation';
    public const FUND_FLOW_TYPE_FTMO_CASHBACK     = 'ftmo_cashback';
    public const FUND_FLOW_TYPE_FTMO_PAYOUT       = 'ftmo_payout';

    /** @var array<string, array{label:string, fund_types:?array<int>, direction:?int}> */
    public const FUND_FLOW_TYPE_MAP = [
        self::FUND_FLOW_TYPE_DEPOSIT           => ['label' => 'type.deposit', 'fund_types' => [TypeLedger::Deposit->value, TypeLedger::FundsRefund->value], 'direction' => self::DIRECTION_IN],
        self::FUND_FLOW_TYPE_WITHDRAWAL        => ['label' => 'type.withdrawal', 'fund_types' => [TypeLedger::Deposit->value], 'direction' => self::DIRECTION_OUT],
        self::FUND_FLOW_TYPE_WITHDRAWAL_RETURN => ['label' => 'type.withdrawal_return', 'fund_types' => [TypeLedger::Deposit->value], 'direction' => self::DIRECTION_IN],
        self::FUND_FLOW_TYPE_REFERRAL          => ['label' => 'type.referral', 'fund_types' => [TypeLedger::IbCommission->value], 'direction' => self::DIRECTION_IN],
        self::FUND_FLOW_TYPE_LOSS_COMPENSATION => ['label' => 'type.loss_compensation', 'fund_types' => [TypeLedger::Deposit->value], 'direction' => self::DIRECTION_IN],
        self::FUND_FLOW_TYPE_FTMO_CASHBACK     => ['label' => 'type.ftmo_cashback', 'fund_types' => [TypeLedger::Deposit->value], 'direction' => self::DIRECTION_IN],
        self::FUND_FLOW_TYPE_FTMO_PAYOUT       => ['label' => 'type.ftmo_payout', 'fund_types' => [TypeLedger::Deposit->value], 'direction' => self::DIRECTION_OUT],
    ];

    public const FUND_FLOW_STATUS_PROCESSING = 0;
    public const FUND_FLOW_STATUS_SUCCESS    = 1;
    public const FUND_FLOW_STATUS_FAILED     = 2;

    /**
     * @return int[]
     */
    public static function depositPlatformOptions(): array
    {
        return self::DEPOSIT_PLATFORM_OPTIONS;
    }

    /**
     * @return int[]
     */
    public static function depositTypeOptions(): array
    {
        return self::DEPOSIT_TYPE_OPTIONS;
    }
}
