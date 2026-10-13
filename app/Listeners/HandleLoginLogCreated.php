<?php

namespace App\Listeners;

use App\Events\ModelEvent;
use App\Models\Api\UserProhibition;
use App\Services\Api\BlackListService;
use Illuminate\Support\Facades\Log;
use Scaffold\Framework\Models\Api\LoginLog;

class HandleLoginLogCreated
{
    private const int SHARED_DEVICE_USER_THRESHOLD = 3;

    private const string SHARED_DEVICE_BLACKLIST_REASON = '同一 IP 和设备标识在 1 小时内登录用户达到 3 人，系统自动限制出金';

    public function __construct(private readonly BlackListService $blackListService) {}

    /**
     * Put the business processing required for a new login record here.
     *
     * Throwing an exception deliberately prevents the Kafka offset from being
     * committed, so the message can be retried by the consumer supervisor.
     */
    public function handle(ModelEvent $event): void
    {
        if (! $event->is('login_log', 'created')) {
            return;
        }

        Log::info('Received a new login log event.', [
            'login_log_id' => $event->data['id'] ?? null,
            'source_app' => $event->message['app_name'] ?? null,
            'login_log' => $event->data,
        ]);

        $ip = trim((string) ($event->data['ip'] ?? ''));
        $userAgent = trim((string) ($event->data['user_agent'] ?? ''));
        $deviceUuid = trim((string) ($event->data['device_uuid'] ?? ''));
        $userCode = $event->data['user_code'] ?? null;

        if ($ip === '' || $userAgent === '' || ! is_numeric($userCode)) {
            Log::warning('Skipped shared-device withdrawal restriction because the login event is incomplete.', [
                'login_log_id' => $event->data['id'] ?? null,
                'has_ip' => $ip !== '',
                'has_user_agent' => $userAgent !== '',
                'user_code' => $userCode,
            ]);

            return;
        }

        $sharedUserCodes = LoginLog::query()
            ->where('ip', $ip)
            ->where('user_agent', $userAgent)
            ->where('device_uuid', $deviceUuid)
            ->where('created_at', '>=', now()->subHour())
            ->select('user_code')
            ->distinct()
            ->pluck('user_code')
            ->filter(static fn (mixed $code): bool => is_numeric($code))
            ->map(static fn (mixed $code): int => (int) $code)
            ->unique()
            ->values();

        // The current login has already been persisted when its created event is published.
        if ($sharedUserCodes->count() < self::SHARED_DEVICE_USER_THRESHOLD) {
            return;
        }

        foreach ($sharedUserCodes as $sharedUserCode) {
            // This service changes the user to blacklist status, writes the operation log,
            // and creates the withdrawal restriction for users not already blacklisted.
            $this->blackListService->add(
                $sharedUserCode,
                self::SHARED_DEVICE_BLACKLIST_REASON,
                false,
                [UserProhibition::TYPE_WITHDRAW],
            );

            // 用户已在黑名单：add() 会直接返回，不会补充新的限制类型；先查询该用户是否已有 user_prohibition 记录，如果没有，插入一条出金限制；
            if (! UserProhibition::query()
                ->where('user_code', $sharedUserCode)
                ->where('type', UserProhibition::TYPE_WITHDRAW)
                ->exists()) {
                UserProhibition::query()->create([
                    'user_code' => $sharedUserCode,
                    'type' => UserProhibition::TYPE_WITHDRAW,
                ]);
            }
        }

        Log::warning('Blacklisted users sharing a login device and applied withdrawal restrictions.', [
            'login_log_id' => $event->data['id'] ?? null,
            'user_codes' => $sharedUserCodes->all(),
            'shared_user_count' => $sharedUserCodes->count(),
        ]);
    }
}
