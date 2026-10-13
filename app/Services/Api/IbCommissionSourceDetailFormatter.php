<?php

namespace App\Services\Api;

use App\Models\Api\Source;

class IbCommissionSourceDetailFormatter
{
    private const DEFAULT_SETTLEMENT_UNIT = '1000';

    /**
     * 格式化交易品种详情（包含佣金规则明细）
     *
     * @param  array<string, mixed>|null  $detail
     * @return array{
     *     source:string,
     *     source_name:string,
     *     source_status:int,
     *     is_source_deleted:bool,
     *     settlement_unit:?string,
     *     commission_amount:string,
     *     commission_fee_rate:string,
     *     is_support_open_close_fee:bool,
     *     direction_details: array{
     *         buy: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string},
     *         sell: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string}
     *     }
     * }
     */
    public function format(Source $source, string $lang, ?array $detail = null): array
    {
        $settlementUnit = $this->formatSettlementUnit($detail);
        $commissionAmount = $this->formatCommissionAmount($detail);
        $commissionFeeRate = $this->formatCommissionFeeRate($detail);

        /** @var array<string, mixed> $sourceConfig */
        $sourceConfig = is_array($source->config) ? $source->config : [];

        return $this->formatWithValues(
            (string) $source->source,
            $this->resolveLocalizedName($source->name ?? [], $lang),
            $source->status,
            false,
            $sourceConfig,
            $settlementUnit,
            $commissionAmount,
            $commissionFeeRate
        );
    }

    /**
     * 格式化交易品种默认信息（无佣金规则明细时使用）
     *
     * @return array{
     *     source:string,
     *     source_name:string,
     *     source_status:int,
     *     is_source_deleted:bool,
     *     settlement_unit:string,
     *     commission_amount:string,
     *     commission_fee_rate:string,
     *     is_support_open_close_fee:bool,
     *     direction_details: array{
     *         buy: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string},
     *         sell: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string}
     *     }
     * }
     */
    public function formatDefault(Source $source, string $lang, string $settlementUnit = self::DEFAULT_SETTLEMENT_UNIT): array
    {
        /** @var array<string, mixed> $sourceConfig */
        $sourceConfig = is_array($source->config) ? $source->config : [];

        return $this->formatWithValues(
            (string) $source->source,
            $this->resolveLocalizedName($source->name ?? [], $lang),
            $source->status,
            false,
            $sourceConfig,
            $settlementUnit,
            '0',
            '0'
        );
    }

    /**
     * 格式化已删除交易品种的信息（仅保留 source_code，其余字段使用默认值）
     *
     * 已删除的交易品种 source_status 为下架状态（0）。
     *
     * @param  array<string, mixed>|null  $detail
     * @return array{
     *     source:string,
     *     source_name:string,
     *     source_status:int,
     *     is_source_deleted:bool,
     *     settlement_unit:?string,
     *     commission_amount:string,
     *     commission_fee_rate:string,
     *     is_support_open_close_fee:bool,
     *     direction_details: array{
     *         buy: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string},
     *         sell: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string}
     *     }
     * }
     */
    public function formatDeleted(string $sourceCode, ?array $detail = null): array
    {
        return $this->formatWithValues(
            $sourceCode,
            $sourceCode,
            Source::STATUS_DELISTED,
            true,
            [],
            $this->formatSettlementUnit($detail),
            $this->formatCommissionAmount($detail),
            $this->formatCommissionFeeRate($detail)
        );
    }

    /**
     * @param  array<string, mixed>|null  $detail
     */
    private function formatSettlementUnit(?array $detail): ?string
    {
        $settlementUnit = $detail ? $this->trimTrailingZeros((string) $detail['position_size_threshold']) : '1000';

        if ($settlementUnit === '') {
            return self::DEFAULT_SETTLEMENT_UNIT;
        }

        return $settlementUnit;
    }

    /**
     * @param  array<string, mixed>|null  $detail
     */
    private function formatCommissionAmount(?array $detail): string
    {
        return $detail
            ? $this->trimTrailingZeros((string) $detail['commission_amount'])
            : '';
    }

    /**
     * @param  array<string, mixed>|null  $detail
     */
    private function formatCommissionFeeRate(?array $detail): string
    {
        return $detail
            ? $this->formatRatePercentage($detail['commission_fee_rate'] ?? '0')
            : '';
    }

    /**
     * 组装交易品种格式化后的返回数据
     *
     * @param  int     $sourceStatus     交易品种上下架状态，使用 Source::STATUS_LISTED / Source::STATUS_DELISTED 常量
     * @param  bool    $isSourceDeleted  交易品种是否已被删除
     * @param  array   $sourceConfig     交易品种配置（手续费等）
     * @param  ?string $settlementUnit   结算单位（仓位阈值）
     * @param  string  $commissionAmount 佣金金额
     * @param  string  $commissionFeeRate 佣金费率
     * @return array{
     *     source:string,
     *     source_name:string,
     *     source_status:int,
     *     is_source_deleted:bool,
     *     settlement_unit:?string,
     *     commission_amount:string,
     *     commission_fee_rate:string,
     *     is_support_open_close_fee:bool,
     *     direction_details: array{
     *         buy: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string},
     *         sell: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string}
     *     }
     * }
     */
    private function formatWithValues(
        string $sourceCode,
        string $sourceName,
        int $sourceStatus,
        bool $isSourceDeleted,
        array $sourceConfig,
        ?string $settlementUnit,
        string $commissionAmount,
        string $commissionFeeRate
    ): array {
        return [
            'source' => $sourceCode,
            'source_name' => $sourceName,
            'source_status' => $sourceStatus,
            'is_source_deleted' => $isSourceDeleted,
            'settlement_unit' => $settlementUnit,
            'commission_amount' => $commissionAmount,
            'commission_fee_rate' => $commissionFeeRate,
            ...$this->formatConfigFields($sourceConfig, $settlementUnit),
        ];
    }

    /**
     * @return array{
     *     is_support_open_close_fee:bool,
     *     direction_details: array{
     *         buy: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string},
     *         sell: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string}
     *     }
     * }
     */
    public function formatConfigFields(array $sourceConfig, ?string $settlementUnit): array
    {
        $openFeeBuyRate = $this->normalizeDirectionFeeRate($sourceConfig['open_fee_buy_rate'] ?? null);
        $openFeeSellRate = $this->normalizeDirectionFeeRate($sourceConfig['open_fee_sell_rate'] ?? null);
        $closeFeeRate = $this->normalizeDirectionFeeRate($sourceConfig['close_fee_rate'] ?? null);

        $buyOpenFee = $this->calculateFee($settlementUnit, $openFeeBuyRate);
        $sellOpenFee = $this->calculateFee($settlementUnit, $openFeeSellRate);
        $closeFee = $this->calculateFee($settlementUnit, $closeFeeRate);

        return [
            'is_support_open_close_fee' => (bool) ($sourceConfig['is_support_open_close_fee'] ?? false),
            'direction_details' => [
                'buy' => [
                    'open_fee_rate' => $openFeeBuyRate,
                    'open_fee' => $buyOpenFee,
                    'close_fee_rate' => $closeFeeRate,
                    'close_fee' => $closeFee,
                ],
                'sell' => [
                    'open_fee_rate' => $openFeeSellRate,
                    'open_fee' => $sellOpenFee,
                    'close_fee_rate' => $closeFeeRate,
                    'close_fee' => $closeFee,
                ],
            ],
        ];
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

    private function formatRatePercentage(mixed $value): string
    {
        return $this->trimTrailingZeros(number_format((float) $value, 4, '.', ''));
    }

    private function formatFeeRate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return $this->trimTrailingZeros(number_format((float) $value, 10, '.', ''));
    }

    private function normalizeDirectionFeeRate(mixed $value): ?string
    {
        $feeRate = $this->formatFeeRate($value);
        if ($feeRate === null) {
            return null;
        }

        $normalized = -((float) $feeRate);

        return $normalized < 0 ? '0' : $this->trimTrailingZeros(number_format($normalized, 10, '.', ''));
    }

    private function calculateFee(?string $settlementUnit, ?string $feeRate): ?string
    {
        if ($settlementUnit === null || $settlementUnit === '' || $feeRate === null || $feeRate === '') {
            return null;
        }

        return $this->trimTrailingZeros(number_format((float) $settlementUnit * (float) $feeRate, 10, '.', ''));
    }

    private function trimTrailingZeros(string $value): string
    {
        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
