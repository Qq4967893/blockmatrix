<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\IbCommissionRuleDetail;
use Scaffold\Framework\Repositories\Repository;

class IbCommissionRuleDetailRepository extends Repository
{
    public function __construct(IbCommissionRuleDetail $model)
    {
        parent::__construct($model);
    }

    /**
     * @return array<int, array>
     */
    public function listByRuleVersionId(int $ruleVersionId): array
    {
        return $this->getQuery()
            ->where('rule_version_id', $ruleVersionId)
            ->orderBy('source')
            ->get()
            ->toArray();
    }

    /**
     * @param array<int, array{
     *     source:string,
     *     position_size_threshold:numeric-string|int|float,
     *     commission_amount:numeric-string|int|float,
     *     commission_fee_rate:numeric-string|int|float
     * }> $details
     */
    public function syncByRuleVersionId(int $ruleVersionId, array $details): void
    {
        $this->getQuery()->where('rule_version_id', $ruleVersionId)->delete();

        if (empty($details)) {
            return;
        }

        $payload = array_map(static function (array $item) use ($ruleVersionId): array {
            return [
                'rule_version_id' => $ruleVersionId,
                'source' => $item['source'],
                'position_size_threshold' => $item['position_size_threshold'],
                'commission_amount' => $item['commission_amount'],
                'commission_fee_rate' => $item['commission_fee_rate'],
            ];
        }, $details);

        $this->getQuery()->insert($payload);
    }
}
