<?php

namespace App\Services\Api;

use App\Models\Api\PositionRecord;
use App\Repositories\Api\PositionRecordRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PositionRecordStatusService
{
    public function __construct(
        private PositionRecordRepository $recordRepo,
    ) {}

    /**
     * 根据启停意图和投放窗口计算保存状态。
     */
    public function resolveStatus(
        int $requestedStatus,
        ?string $startTime,
        ?string $endTime,
        ?CarbonInterface $now = null
    ): int {
        if ($requestedStatus === PositionRecord::STATUS_DISABLED) {
            return PositionRecord::STATUS_DISABLED;
        }

        $now ??= CarbonImmutable::now();

        try {
            $start = $startTime ? CarbonImmutable::parse($startTime) : null;
            $end = $endTime ? CarbonImmutable::parse($endTime) : null;
        } catch (\Throwable) {
            return $requestedStatus;
        }

        if ($end !== null && $end->lessThanOrEqualTo($now)) {
            return PositionRecord::STATUS_DISABLED;
        }

        if ($start !== null && $start->greaterThan($now)) {
            return PositionRecord::STATUS_PENDING;
        }

        return PositionRecord::STATUS_ENABLED;
    }

    /**
     * 将数据库状态同步到当前投放窗口。
     *
     * @return array{activated:int, disabled:int}
     */
    public function sync(?CarbonInterface $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $currentTime = $now->format('Y-m-d H:i:s');

        return [
            'activated' => $this->recordRepo->enableStarted($currentTime),
            'disabled' => $this->recordRepo->disableExpired($currentTime),
        ];
    }
}
