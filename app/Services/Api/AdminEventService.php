<?php

namespace App\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\AdminEvent;
use App\Repositories\Api\AdminEventRepository;
use Illuminate\Support\Facades\Auth;
use Scaffold\Framework\Enums\TypeAdminEvent;

class AdminEventService
{
    public function __construct(private AdminEventRepository $repo) {}

    /**
     * 判断用户是否已开通赠金账户（admin_event 中存在 open_bonus_fund 事件记录，任意状态）。
     */
    public function existsOpenBonusFundByUserCode(int $userCode): bool
    {
        return $this->repo->existsByEventKeyAndUserCode(TypeAdminEvent::OpenBonusFund->value, $userCode);
    }

    public function createOpenBonusFund(int $userCode, float $amount, ?int $operatorId = null): int
    {
        return $this->create([
            'event_key' => TypeAdminEvent::OpenBonusFund,
            'event_params' => [
                'amount' => $amount,
                'user_code' => $userCode,
            ],
        ], $operatorId);
    }

    public function create(array $payload, ?int $operatorId = null): int
    {
        $operatorId ??= (int) ($payload['operator_id'] ?? Auth::id() ?? 0);
        if ($operatorId <= 0) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $eventKey = $payload['event_key'] ?? '';
        if ($eventKey instanceof TypeAdminEvent) {
            $eventKey = $eventKey->value;
        }

        $eventParams = $payload['event_params'] ?? [];
        $userCode = is_array($eventParams) ? (int) ($eventParams['user_code'] ?? 0) : 0;
        if ($userCode > 0 && $this->repo->existsByEventKeyAndUserCode((string) $eventKey, $userCode)) {
            throw new BusinessException(trans('public.message.not_repeat'));
        }

        $event = $this->repo->create([
            'event_key' => (string) $eventKey,
            'event_params' => $eventParams,
            'operator_id' => $operatorId,
            'status' => (int) ($payload['status'] ?? AdminEvent::STATUS_PENDING),
            'executed_at' => $payload['executed_at'] ?? null,
        ]);

        return (int) ($event->id ?? 0);
    }
}
