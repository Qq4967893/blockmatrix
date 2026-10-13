<?php

namespace App\Services\Api;

use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Utils\BaseRedis;

class CaptchaLookupService
{
    public function __construct(
        private UserRepository $userRepo,
        private UserOperationLogRepository $userOperationLogRepo,
    ) {}

    /**
     * @return array{code:string}
     */
    public function getEmailCode(string $email, string $reason): array
    {
        $normalizedEmail = strtolower(trim($email));
        $cacheKey = sprintf('captcha:email:%s', $normalizedEmail);
        $code = (string) BaseRedis::getApiCacheRedis()->get($cacheKey);

        if ($code !== '') {
            $userCode = $this->userRepo->findUserCodeByEmail($normalizedEmail) ?? 0;
            $this->userOperationLogRepo->addRecord(
                $userCode,
                UserOperationLog::TARGET_TYPE_CAPTCHA_QUERY,
                json_encode([
                    'reason' => $reason,
                    'email' => $normalizedEmail,
                    'channel' => 'email',
                    'cache_key' => sprintf('api:cache:%s', $cacheKey),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        return [
            'code' => $code,
        ];
    }

    /**
     * @param array{
     *     email?:string|null,
     *     user_code?:int|null,
     *     limit:int,
     *     offset:int
     * } $filters
     * @return array{
     *     data: array<int, array{
     *         email:string,
     *         reason:string,
     *         user_code:int,
     *         operator_name:string,
     *         operated_at:string
     *     }>,
     *     total_count:int
     * }
     */
    public function getEmailCodeLogs(array $filters): array
    {
        [$logs, $count] = $this->userOperationLogRepo->getPaginatedLogs(
            [
                'target_type' => UserOperationLog::TARGET_TYPE_CAPTCHA_QUERY,
                'action_type' => UserOperationLog::ACTION_TYPE_ADD,
                'user_code' => $filters['user_code'] ?? null,
                'email' => isset($filters['email']) ? strtolower(trim((string) $filters['email'])) : null,
            ],
            $filters['limit'],
            $filters['offset']
        );

        $data = [];
        foreach ($logs as $log) {
            $content = json_decode((string) ($log['content'] ?? '{}'), true);
            $data[] = [
                'email' => (string) ($content['email'] ?? ''),
                'reason' => (string) ($content['reason'] ?? ''),
                'user_code' => (int) ($log['target_id'] ?? 0),
                'operator_name' => (string) ($log['login_name'] ?? ''),
                'operated_at' => (string) ($log['created_at'] ?? ''),
            ];
        }

        return [
            'data' => $data,
            'total_count' => $count,
        ];
    }
}
