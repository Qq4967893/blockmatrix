<?php

namespace App\Services\Api;

use App\Models\Api\TemplateNotice;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\CountryRepository;
use App\Repositories\Api\TemplateNoticeRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Utils\Util;
use App\Utils\Email;
use Scaffold\Framework\Enums\TypeNoticeEvent;
use Scaffold\Framework\Utils\S3;
use App\Services\Api\Traits\ContentImageNormalizer;

class TemplateNoticeService
{
    use ContentImageNormalizer;

    public function __construct(
        private TemplateNoticeRepository   $repo,
        private AdminUserRepository        $adminUserRepo,
        private UserOperationLogRepository $userOperationLogRepo,
        private CountryRepository          $countryRepo,
        private S3                         $s3,
    ) {
    }

    /**
     * 消息模型列表
     *
     * @param int $type 类型
     * @param string $eventKey 触发事件关键字
     * @param string $lang 语言关键字
     * @param string $title 标题
     * @param string $sort
     * @param string $order
     * @param int $limit
     * @param int $offset
     *
     * @return array
     */
    public function list($type, $eventKey, $lang, $title, $sort, $order, $limit, $offset)
    {
        [$data, $count] = $this->repo->list($type, $eventKey, $lang, $title, $sort, $order, $limit, $offset);
        $events = TypeNoticeEvent::details();
        $eventArr = array_column($events, 'description', 'value');
        foreach ($data as $key => $item) {
            $data[$key]['event_text'] = $eventArr[$item['event_key']] ?? '';
        }
        return [$data, $count];
    }

    /**
     * 获取一条模版记录
     *
     * @oaram int $id ID
     * @param int $layout_id layout模版ID
     * @param int $type 类型
     * @param string $eventKey 触发事件关键字
     * @param string $lang 语言关键字
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getFirst($id, $layout_id, $type = TemplateNotice::TYPE_ALL, $eventKey = '', $lang = '')
    {
        return $this->repo->getFirst($id, $layout_id, $type, $eventKey, $lang);
    }

    /**
     * 新增消息模型
     *
     * @param int $type 消息模型类型
     * @param string $eventKey 触发事件关键字
     * @param string $title 标题
     * @param int $layoutId layout模型id
     * @param string $content 内容
     * @param string $link 内部链接
     *
     * @return int
     */
    public function add($type, $eventKey, $title, $layoutId, $lang, $content, $link)
    {
        // content内如果有image，判断image是本地上传图片（email/img.png）还是域名地址（https://image.com/email/img.png）
        // 如果是本地上传图片需要补全成域名地址存储
        $content = Util::htmlImageSrc($content, Email::s3ImageAddress().'/');
        $insert = [
            'type' => $type,
            'event_key' => $eventKey,
            'title' => $title,
            'lang' => $lang,
            'content' => $content,
        ];
        if ($type == TemplateNotice::TYPE_EMAIL) {
            $insert['layout_id'] = $layoutId;
        }
        if ($type == TemplateNotice::TYPE_IN_APP && $link != '') {
            $insert['link'] = $link;
        }
        $templateNoticeId = $this->repo->insertGetId($insert);
        $this->userOperationLogRepo->addRecord($templateNoticeId, UserOperationLog::TARGET_TYPE_TEMPLATE_NOTICE, json_encode($insert, JSON_UNESCAPED_UNICODE));
        return $templateNoticeId;
    }

    /**
     * 修改邮件模板
     *
     * @param int $id ID
     * @param int $type 消息模型类型
     * @param string $eventKey 触发事件关键字
     * @param string $title 标题
     * @param int $layoutId layout模型id
     * @param string $content 内容
     * @param string $link 内部链接
     *
     * @return bool
     */
    public function update($id, $type, $eventKey, $title, $layoutId, $lang, $content, $link)
    {
        // content内如果有image，判断image是本地上传图片（email/img.png）还是域名地址（https://image.com/email/img.png）
        // 如果是本地上传图片需要补全成域名地址存储
        $content = Util::htmlImageSrc($content, Email::s3ImageAddress().'/');
        $update = [
            'type' => $type,
            'event_key' => $eventKey,
            'title' => $title,
            'lang' => $lang,
            'content' => $content,
        ];
        if ($type == TemplateNotice::TYPE_EMAIL) {
            $update['layout_id'] = $layoutId;
        }
        if ($type == TemplateNotice::TYPE_IN_APP && $link != '') {
            $update['link'] = $link;
        }

        $res = $this->repo->update($update, $id);
        $this->userOperationLogRepo->addRecord($id, UserOperationLog::TARGET_TYPE_TEMPLATE_NOTICE, json_encode($update, JSON_UNESCAPED_UNICODE), UserOperationLog::ACTION_TYPE_UPDATE);
        return $res;
    }

    /**
     * 删除邮件模板
     *
     * @param int $id ID
     *
     * @return int
     */
    public function delete($id)
    {
        $res = $this->repo->delete($id);
        $this->userOperationLogRepo->addRecord($id, UserOperationLog::TARGET_TYPE_TEMPLATE_NOTICE, UserOperationLog::DEFAULT_CONTENT, UserOperationLog::ACTION_TYPE_DELETE);
        return $res;
    }
}
