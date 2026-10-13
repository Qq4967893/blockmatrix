<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Template\AddTemplateNoticeRequest;
use App\Http\Requests\Template\UpdateTemplateNoticeRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Services\Api\TemplateNoticeService;
use App\Services\Api\TemplateLayoutService;
use App\Services\Api\MessageTransactionService;
use App\Services\Api\UserService;
use \Scaffold\Framework\Enums\TypeNoticeEvent;
use \Scaffold\Framework\Enums\TypePage;
use \Scaffold\Email\Facades\Email;
use App\Models\Api\TemplateNotice;
use \App\Utils\Email as EmailUtil;

class TemplateNoticeController extends BaseController
{
    public function __construct(
        private TemplateNoticeService $templateNoticeService,
        private TemplateLayoutService $templateLayoutService,
        private UserService $userService,
        private MessageTransactionService $messageTransactionService,
    ) {
    }

    /**
     * 获取所有触发事件
     *
     * Query:
     * - type:int 事件类型：0所有，1系统事件，2交易事件
     *
     * Response.data[].fields:
     * - value:string 关键字
     * - description:string 事件描述
     * variable[].fields:
     * - variable:string 变量
     * - description:string 变量描述
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function events(Request $request)
    {
        $type = (int)$request->input('type', TemplateNotice::TYPE_ALL);

        $events = TypeNoticeEvent::details();
        $sysEvents = [];
        $tradingEvents = [];
        foreach ($events as $event) {
            if (TemplateNotice::isSystemEvent($event['value'])) {
                $sysEvents[] = $event;
            } else {
                $tradingEvents[] = $event;
            }
        }

        return match ($type) {
            TemplateNotice::EVENT_TYPE_SYSTEM => $this->succeed($sysEvents),
            TemplateNotice::EVENT_TYPE_TRADING => $this->succeed($tradingEvents),
            default => $this->succeed($events),
        };
    }

    /**
     * 获取所有内部跳转页
     *
     * Response.data[].fields:
     * - value:string 关键字
     * - description:string 内部跳转页描述
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pages(Request $request){
        $pages = TypePage::details();
        return $this->succeed($pages);
    }

    /**
     * 获取所有语言
     *
     * Response.data[].fields:
     * - value:string 关键字
     * - description:string 内部跳转页描述
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function langs(Request $request){
        $languages = config('lang.languages', []);
        return $this->succeed($languages);
    }

    /**
     * 消息模型列表
     *
     * Query:
     * - type:int 模型类型：1邮件模型，2站内信模型
     * - event_key:string 触发事件关键字
     * - lang:string 语言关键字（例：zh_CN，en_US）
     * - title:string 标题
     *
     * Response.data[].fields:
     * - id:int ID
     * - type:int 消息模版类型：1邮件模型，2站内信模型
     * - event_key:string 触发事件关键字
     * - lang:string 语言关键字
     * - title:string 标题
     * - layout_id:int layout模版ID
     * - content:string 内容
     * - link:string 内部链接
     * - layout_name:string layout模版名称
     * - created_at:string 创建时间
     * - updated_at:string 最后一次更新时间
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function list(Request $request)
    {
        $type = $request->input('type', 0);
        $eventKey = $request->input('event_key', '');
        $lang = $request->input('lang', '');
        $title = $request->input('title', '');
        $sort = $request->input('sort', 'created_at'); //默认创建时间
        $order = $request->input('order', 'desc'); //默认倒序
        $limit  = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        [$data, $count] = $this->templateNoticeService->list($type, $eventKey, $lang, $title, $sort, $order, $limit, $offset);
        foreach ($data as &$templateNotice) {
            if (is_null($templateNotice['link'])) {
                $templateNotice['link'] = '';
            }
            if (is_null($templateNotice['layout_name'])) {
                $templateNotice['layout_name'] = '';
            }
            $templateNotice['event_type'] = TemplateNotice::eventType($templateNotice['event_key']);
        }
        return $this->succeed(['data' => $data, 'total_count' => $count]);
    }

    /**
     * 获取消息模型
     *
     * Response.data[].fields:
     * - id:int ID
     * - type:int 消息模版类型：1邮件模型，2站内信模型
     * - event_key:string 触发事件关键字
     * - event_type:int 事件类型 1系统事件，2交易事件
     * - lang:string 语言关键字
     * - title:string 标题
     * - layout_id:int layout模版ID
     * - content:string 内容
     * - link:string 内部链接
     * - created_at:string 创建时间
     * - updated_at:string 最后一次更新时间
     *
     * @param int $id ID
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function info($id){
        $templateNotice = $this->templateNoticeService->getFirst($id, 0);
        if (is_null($templateNotice)) {
            return $this->fail(trans('public.message.param_non_existent', ['param' => 'id']));
        }

        if (is_null($templateNotice->link)) {
            $templateNotice->link = '';
        }

        $templateNotice->event_type = TemplateNotice::eventType($templateNotice->event_key);
        return $this->succeed($templateNotice);
    }

    /**
     * 新增消息模版
     *
     *  Query:
     * - type:int 类型：1邮件，2站内信
     * - event_key:string 触发事件关键字
     * - title:string 标题
     * - layout_id:int layout模版ID
     * - lang:string 语言关键字（例：zh_CN，en_US）
     * - content:string 内容
     * - link:string 站内信跳转
     *
     * @param AddTemplateNoticeRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(AddTemplateNoticeRequest $request)
    {
        $type     = $request->input('type');
        $eventKey = $request->input('event_key');
        $title    = $request->input('title');
        $layoutId = $request->input('layout_id', 0);
        $lang     = $request->input('lang');
        $content  = $request->input('content');
        $link     = $request->input('link', '');

        // 如果是邮件，判断layout id是否存在
        if ($type == TemplateNotice::TYPE_EMAIL) {
            $layoutObj = $this->templateLayoutService->getFirst($layoutId);
            if (is_null($layoutObj)){
                return $this->fail(trans('public.message.param_non_existent', ['param' => 'layout id']));
            }
        }

        // type,event_key和lang具有唯一性
        $noticeObj = $this->templateNoticeService->getFirst(0, 0, $type, $eventKey, $lang);
        if (! is_null($noticeObj)){
            return $this->fail(trans('template.message.type_event_key_lang_exist'));
        }

        $res = $this->templateNoticeService->add($type, $eventKey, $title, $layoutId, $lang, $content, $link);
        if (!$res) {
            return $this->fail(trans('public.message.add_error'));
        }
        return $this->succeed([], trans('public.message.add_success'));
    }

    /**
     * 修改消息模版
     *
     * Query:
     * - id:int 消息模版ID
     * - type:int 类型：1邮件，2站内信
     * - event_key:string 触发事件关键字
     * - title:string 标题
     * - layout_id:int layout模版ID
     * - lang:string 语言关键字（例：zh_CN，en_US）
     * - content:string 内容
     * - link:string 站内信跳转
     *
     * @param UpdateTemplateNoticeRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateTemplateNoticeRequest $request)
    {
        $id       = $request->input('id');
        $type     = $request->input('type');
        $eventKey = $request->input('event_key');
        $title    = $request->input('title');
        $layoutId = $request->input('layout_id', 0);
        $lang     = $request->input('lang');
        $content  = $request->input('content');
        $link     = $request->input('link', '');

        // 如果是邮件，判断layout id是否存在
        if ($type == TemplateNotice::TYPE_EMAIL) {
            $layoutObj = $this->templateLayoutService->getFirst($layoutId);
            if (is_null($layoutObj)){
                return $this->fail(trans('public.message.param_non_existent', ['param' => 'layout id']));
            }
        }

        // type,event_key和lang具有唯一性且排除当前id
        $noticeObj = $this->templateNoticeService->getFirst(0, 0, $type, $eventKey, $lang);
        if (! is_null($noticeObj) && $noticeObj->id != $id) {
            return $this->fail(trans('template.message.type_event_key_lang_exist'));
        }

        $res = $this->templateNoticeService->update($id, $type, $eventKey, $title, $layoutId, $lang, $content, $link);
        if (!$res) {
            return $this->fail(trans('public.message.upd_error'));
        }
        return $this->succeed([], trans('public.message.upd_success'));
    }

    /**
     * 删除邮件
     *
     * @param int $id ID
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        $count = $this->templateNoticeService->delete($id);
        if (! $count) {
            return $this->fail(trans('public.message.del_error'));
        }
        return $this->succeed([], trans('public.message.del_success'));
    }

    /**
     * 测试消息模版
     *
     * Query:
     * - id:int 消息模版ID
     * - emails:string 邮箱
     * - user_codes:string UserCode
     * - params:string 参数json
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function test(Request $request){
        $id = $request->input('id');
        $emails = $request->input('emails', '');
        $userCodes = $request->input('user_codes', '');
        $params= $request->input('params', '');

        $templateNotice = $this->templateNoticeService->getFirst($id, 0);
        if (is_null($templateNotice)){
            return $this->fail(trans('public.message.param_non_existent', ['param' => 'id']));
        }

        if ($templateNotice->type == TemplateNotice::TYPE_EMAIL) {
            $params = json_decode($params, true);
            $params['layout_id'] = $templateNotice->layout_id;

            $emails = json_decode($emails, true);
            foreach ($emails as $email) {
                if (! EmailUtil::is($email)) {
                    return  $this->fail(trans('manager.user.email_error'));
                }
            }
            Email::sendTemplateEmail($emails, TypeNoticeEvent::from($templateNotice->event_key), $templateNotice->lang, $params);
        } else {
            $userCodes = json_decode($userCodes, true);
            foreach ($userCodes as $userCode) {
                $userInfo = $this->userService->getByUserCode($userCode);
                if (is_null($userInfo)){
                    return $this->fail(trans('public.message.param_non_existent', ['param' => "user_code {$userCode}"]));
                }
            }
            foreach ($userCodes as $userCode) {
                $this->messageTransactionService->add($userCode, $templateNotice->event_key, $params);
            }
        }
        return $this->succeed([], trans('template.message.send_success'));
    }
}
