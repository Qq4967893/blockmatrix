<?php

namespace App\Services\Api;

use App\Models\Api\SystemMessage;
use App\Repositories\Api\SystemMessageRepository;
use App\Repositories\Api\MessageSystemReadRepository;
use App\Repositories\Api\AreaRepository;

class SystemMessageService
{
    public function __construct(
        private SystemMessageRepository    $repo,
        private AreaRepository             $areaRepo,
        private MessageSystemReadRepository $messageSystemReadRepo

    ) {
    }

    /**
     * 系统消息列表
     *
     * @param int $limit  每页条数
     * @param int $offset 偏移量
     *
     * @return array
     */
    public function getList($limit, $offset)
    {
        [$messages, $count] = $this->repo->getList($limit, $offset);

        $messageIds = array_column($messages, 'id');
        $readStatus = $this->messageSystemReadRepo->getLatestStatusesByMessageIds($messageIds);

        $areaInfos = $this->areaRepo->getAllAreaNames();

        foreach ($messages as &$item) {
            $areaId            = $item['area_id'] ?? null;
            $item['area_name'] = $areaId === null ? '-' : ($areaInfos[$areaId] ?? '-');
            //发送类型
            $item['resource'] = empty($item['user_code']) ? trans('user.msg_type.all') : trans('user.msg_type.select');

            $readInfo          = $readStatus[$item['id']] ?? ['is_read' => false, 'status' => 0];
            $item['is_read']   = $readInfo['is_read'];
            $item['status']    = $readInfo['status'];
        }

        return [$messages, $count];
    }

    /**
     * 发送消息
     *
     * @param string $title   标题
     * @param string $content 内容
     * @param int    $areaId  大区id
     *
     * @return void
     */
    public function send($type, $title, $content, $areaId, $user_codes)
    {
        $data['title']   = $title;
        $data['content'] = $content;
        //$data['area_id'] = $areaId;
        if ($type == SystemMessage::TYPE_ASSIGN) {
            $data['user_code'] = implode(',', $user_codes);
        }

        $this->repo->sendMsg($data);
    }
}
