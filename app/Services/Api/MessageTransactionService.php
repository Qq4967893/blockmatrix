<?php

namespace App\Services\Api;

use App\Repositories\Api\MessageTransactionRepository;

class MessageTransactionService
{
    public function __construct(
        private MessageTransactionRepository    $repo,
    ) {
    }

    /**
     * 添加交易消息
     *
     * @param int $userCode
     * @param string $event_key 触发事件关键字
     * @param string $param 参数
     *
     * @return bool
     */
    public function add($userCode, $event_key, $param)
    {
        $data = [
            'user_code' => $userCode,
            'event_key' => $event_key,
            'param' => $param,
        ];
        return $this->repo->insert($data);
    }
}
