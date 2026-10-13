<?php

namespace App\Services\Api;

use Scaffold\Framework\Enums\TypeIbLevelChangeReason;
use Scaffold\Framework\Enums\TypeIbLevelChangeType;
use Scaffold\Framework\Models\Api\IbLevelChangeLog;
use Scaffold\Framework\Services\IbLevelOperateService;

class IbLevelChangeRecorder
{
    public function __construct(private IbLevelOperateService $operateService)
    {
    }

    public function recordLevelChange(
        int $ibUserCode,
        ?int $beforeLevelId,
        int $afterLevelId,
        TypeIbLevelChangeType|int $changeType,
        TypeIbLevelChangeReason|int $changeReason,
        mixed $assessedAt = null,
        mixed $statStartAt = null,
        mixed $statEndAt = null,
        int $activeTraderCount = 0,
        int|float|string $teamPositionSize = '0.000000',
        ?int $operatorId = null,
        ?string $remark = null
    ): IbLevelChangeLog {
        if ($beforeLevelId === null) {
            $this->operateService->init($ibUserCode, $operatorId);
        } else {
            $this->operateService->modify(
                userCode: $ibUserCode,
                beforeLevelId: $beforeLevelId,
                afterLevelId: $afterLevelId,
                operatorId: $operatorId,
                statStartAt: $statStartAt ? (string) $statStartAt : null,
                statEndAt: $statEndAt ? (string) $statEndAt : null,
                activeTraderCount: $activeTraderCount,
                teamPositionSize: is_numeric($teamPositionSize) ? (float) $teamPositionSize : 0.0,
            );
        }

        return IbLevelChangeLog::query()
            ->where('ib_user_code', $ibUserCode)
            ->orderByDesc('id')
            ->firstOrFail();
    }

}
