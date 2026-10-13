<?php

namespace App\Services\Api;

use App\Repositories\Api\IbCommissionRuleRepository;
use App\Repositories\Api\IbLevelRepository;

class IbLevelOrderService
{
    public function __construct(private IbLevelRepository $levelRepo) {}

    /**
     * Reorder all active levels by their effective base commission rates.
     */
    public function sync(): void
    {
        $levels = $this->levelRepo->getActiveLevelsForOrderUpdate();
        $orderedLevels = $this->planOrder($levels);

        $this->levelRepo->applyOrder($orderedLevels);
    }

    /**
     * Validate and sort levels by base commission rate. The rate is the only
     * business ordering key; old position and ID are deterministic fallbacks.
     *
     * @param  array<int, array<string, mixed>>  $levels
     * @return array<int, array<string, mixed>>
     */
    public function planOrder(array $levels): array
    {
        $normalizedLevels = [];
        foreach ($levels as $level) {
            if (! is_array($level)) {
                throw new \Exception(trans('public.message.data_error'));
            }

            $id = (int) ($level['id'] ?? 0);
            $rate = $level['base_commission_rate'] ?? null;
            $versionStatus = (int) ($level['commission_version_status'] ?? 0);
            if ($id <= 0 || ! is_numeric($rate) || $versionStatus !== IbCommissionRuleRepository::STATUS_APPROVED) {
                throw new \Exception(trans('public.message.data_error'));
            }

            $normalizedLevels[] = $level;
        }

        usort($normalizedLevels, static function (array $left, array $right): int {
            return self::compareBaseCommissionRates(
                $left['base_commission_rate'],
                $right['base_commission_rate']
            ) ?: ((int) $left['level_pos'] <=> (int) $right['level_pos'])
                ?: ((int) $left['id'] <=> (int) $right['id']);
        });

        $seenRates = [];
        foreach ($normalizedLevels as $level) {
            $rateKey = $this->baseCommissionRateKey((string) $level['base_commission_rate']);
            if (isset($seenRates[$rateKey])) {
                throw new \Exception(trans('ib_level.message.base_commission_rate_conflict', [
                    'rate' => $this->trimTrailingZeros((string) $level['base_commission_rate']),
                ]));
            }

            $seenRates[$rateKey] = true;
        }

        return array_map(static function (array $level, int $index): array {
            $levelPos = $index + 1;
            $level['new_level_pos'] = $levelPos;
            $level['new_is_root'] = $levelPos === 1 ? 1 : 0;

            return $level;
        }, $normalizedLevels, array_keys($normalizedLevels));
    }

    /**
     * Resolve the one-based position at which a new rate would be inserted.
     *
     * @param  array<int, array<string, mixed>>  $levels
     */
    public function resolveInsertPosition(array $levels, string $baseCommissionRate): int
    {
        $orderedLevels = $this->planOrder($levels);

        foreach ($orderedLevels as $index => $level) {
            $comparison = self::compareBaseCommissionRates($baseCommissionRate, (string) $level['base_commission_rate']);
            if ($comparison < 0) {
                return $index + 1;
            }

            if ($comparison === 0) {
                throw new \Exception(trans('ib_level.message.base_commission_rate_conflict', [
                    'rate' => $this->trimTrailingZeros($baseCommissionRate),
                ]));
            }
        }

        return count($orderedLevels) + 1;
    }

    private static function compareBaseCommissionRates(mixed $left, mixed $right): int
    {
        return self::baseCommissionRateKey($left) <=> self::baseCommissionRateKey($right);
    }

    private static function baseCommissionRateKey(mixed $rate): int
    {
        if (! is_numeric($rate)) {
            throw new \Exception(trans('public.message.data_error'));
        }

        return (int) round(((float) $rate) * 10000);
    }

    private function trimTrailingZeros(string $rate): string
    {
        $trimmed = rtrim(rtrim($rate, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
