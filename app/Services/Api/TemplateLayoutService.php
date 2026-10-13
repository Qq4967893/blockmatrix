<?php

namespace App\Services\Api;

use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\TemplateLayoutRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Utils\Email;
use App\Utils\Util;

class TemplateLayoutService
{
    public function __construct(
        private TemplateLayoutRepository   $repo,
        private UserOperationLogRepository $userOperationLogRepo,
    ) {
    }

    /**
     * 获取layout模版列表
     *
     * @param string $sort
     * @param string $order
     * @param int $limit
     * @param int $offset
     *
     * @return array
     */
    public function getList($sort, $order, $limit, $offset)
    {
        return $this->repo->getList($sort, $order, $limit, $offset);
    }

    /**
     * 获取所有layout模版
     *
     * @param int $status 状态：0未发布，1已发布
     *
     * @return array
     */
    public function getAll($status){
        return $this->repo->getAll($status);
    }

    /**
     * 获取一条layout模版信息
     *
     * @param int $id layout模板ID
     *
     * @return object|null
     */
    public function getFirst($id)
    {
        return $this->repo->first($id);
    }

    /**
     * 添加layout模版
     *
     * @param string $title 模板title
     * @param string $content 模板内容
     * @param int $status 状态
     *
     * @return array
     */
    public function add($title, $content, $status)
    {
        // content内如果有image，判断image是本地上传图片（email/img.png）还是域名地址（https://image.com/email/img.png）
        // 如果是本地上传图片需要补全成域名地址存储
        $content = Util::htmlImageSrc($content, Email::s3ImageAddress().'/');
        $insert = ['name' => $title, 'content' => $content, 'status' => $status];
        $this->repo->insert($insert);
        $this->userOperationLogRepo->addRecord(0, UserOperationLog::TARGET_TYPE_TEMPLATE_LAYOUT, json_encode($insert, JSON_UNESCAPED_UNICODE));
        return ['is_succ' => true, 'message' => trans('public.message.add_success')];
    }

    /**
     * 修改layout模版
     *
     * @param int    $id      邮件模板框架
     * @param string $name
     * @param string $content 修改的内容
     * @param int $status 状态
     *
     * @return bool
     */
    public function update($id, $name, $content, $status)
    {
        // content内如果有image，判断image是本地上传图片（email/img.png）还是域名地址（https://image.com/email/img.png）
        // 如果是本地上传图片需要补全成域名地址存储
        $content = Util::htmlImageSrc($content, Email::s3ImageAddress().'/');
        $update = ['name' => $name, 'content' => $content, 'status' => $status];
        $res = $this->repo->update($update, $id);
        $this->userOperationLogRepo->addRecord($id, UserOperationLog::TARGET_TYPE_TEMPLATE_LAYOUT, json_encode($update, JSON_UNESCAPED_UNICODE), UserOperationLog::ACTION_TYPE_UPDATE);
        return $res;
    }

    /**
     * 删除layout模版
     *
     * @param int $id id
     *
     * @return int
     */
    public function delete($id)
    {
        $res = $this->repo->delete($id);
        $this->userOperationLogRepo->addRecord($id, UserOperationLog::TARGET_TYPE_TEMPLATE_LAYOUT, '{}', UserOperationLog::ACTION_TYPE_DELETE);
        return $res;
    }
}
