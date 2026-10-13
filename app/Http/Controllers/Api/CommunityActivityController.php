<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\CommunityActivity\CommunityActivityStoreRequest;
use App\Http\Requests\CommunityActivity\CommunityActivityUpdateRequest;
use App\Services\Api\CommunityActivityService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Community Activity', '活动管理')]
class CommunityActivityController extends BaseController
{
    private const LOCALE_CONFIG_TYPE = 'array<int, array{lang:string,name:string,summary:string,cover_image:string,bg_image:string,introduction:string,content:string}>';

    private const ACTIVITY_RESPONSE_TYPE = 'array{id:int,name_text:string,name:array,type:int,type_text:string,city:string,city_text:string,activity_time:string,start_at:string,end_at:string,apply_start_at:string,apply_end_at:string,status:int,status_text:string,status_code:int,publish_type:int,publish_type_text:string,operator_id:int,operator_name:string,audience:array,audience_text:string,summary:array,summary_text:string,cover_image:array,bg_image:array,introduction:array,introduction_text:string,content:array,content_text:string,locale_configs:array<int,array{lang:string,name:string,summary:string,cover_image:string,bg_image:string,introduction:string,content:string}>,created_at:string,updated_at:string}';

    private const STORE_LOCALE_CONFIG_EXAMPLE = [
        [
            'lang' => 'en_US',
            'name' => 'London Web3 Trading Meetup',
            'summary' => 'Meet local traders and learn practical Web3 trading strategies.',
            'cover_image' => 'https://static.example.com/community_activity/20260820/cover-en.jpg',
            'bg_image' => 'https://static.example.com/community_activity/20260820/background-en.jpg',
            'introduction' => 'An in-person meetup for traders who want to exchange market insights.',
            'content' => '<p>Join our analysts and local community leaders for market sharing, live strategy sessions, and networking.</p>',
        ],
        [
            'lang' => 'zh_CN',
            'name' => '伦敦 Web3 交易者见面会',
            'summary' => '与本地交易者交流并学习实用的 Web3 交易策略。',
            'cover_image' => 'https://static.example.com/community_activity/20260820/cover-zh.jpg',
            'bg_image' => 'https://static.example.com/community_activity/20260820/background-zh.jpg',
            'introduction' => '面向希望交流市场观点的交易者举办的线下见面会。',
            'content' => '<p>与分析师及本地社区领袖共同参与市场分享、实盘策略讲解和自由交流。</p>',
        ],
    ];

    private const UPDATE_LOCALE_CONFIG_EXAMPLE = [
        [
            'lang' => 'en_US',
            'name' => 'London Web3 Trading Meetup 2026',
            'summary' => 'Updated agenda with live market analysis and community networking.',
            'cover_image' => 'https://static.example.com/community_activity/20260820/cover-en-v2.jpg',
            'bg_image' => 'https://static.example.com/community_activity/20260820/background-en-v2.jpg',
            'introduction' => 'The updated London meetup for active Web3 and copy traders.',
            'content' => '<p>The updated program includes live market analysis, strategy workshops, and an extended networking session.</p>',
        ],
        [
            'lang' => 'zh_CN',
            'name' => '2026 伦敦 Web3 交易者见面会',
            'summary' => '更新后的议程包含实时行情分析和社区交流。',
            'cover_image' => 'https://static.example.com/community_activity/20260820/cover-zh-v2.jpg',
            'bg_image' => 'https://static.example.com/community_activity/20260820/background-zh-v2.jpg',
            'introduction' => '面向活跃 Web3 及跟单交易者的伦敦线下见面会。',
            'content' => '<p>更新后的活动包含实时行情分析、策略工作坊以及延长的交流环节。</p>',
        ],
    ];

    private const ACTIVITY_RESPONSE_EXAMPLE = [
        'id' => 101,
        'name_text' => 'London Web3 Trading Meetup',
        'name' => ['en_US' => 'London Web3 Trading Meetup', 'zh_CN' => '伦敦 Web3 交易者见面会'],
        'type' => 1,
        'type_text' => 'Offline Event',
        'city' => 'london',
        'city_text' => 'London',
        'activity_time' => '2026-09-12 14:00:00 - 2026-09-12 18:00:00',
        'start_at' => '2026-09-12 14:00:00',
        'end_at' => '2026-09-12 18:00:00',
        'apply_start_at' => '2026-09-12 10:00:00',
        'apply_end_at' => '2026-09-12 13:30:00',
        'status' => 0,
        'status_text' => 'Pending',
        'status_code' => 0,
        'publish_type' => 0,
        'publish_type_text' => 'Scheduled',
        'operator_id' => 1,
        'operator_name' => 'admin',
        'audience' => ['web3_users', 'local_trading_community'],
        'audience_text' => 'Web3 Users, Local Trading Community',
        'summary' => ['en_US' => 'Meet local traders and learn practical Web3 trading strategies.', 'zh_CN' => '与本地交易者交流并学习实用的 Web3 交易策略。'],
        'summary_text' => 'Meet local traders and learn practical Web3 trading strategies.',
        'cover_image' => ['en_US' => 'https://static.example.com/community_activity/20260820/cover-en.jpg', 'zh_CN' => 'https://static.example.com/community_activity/20260820/cover-zh.jpg'],
        'bg_image' => ['en_US' => 'https://static.example.com/community_activity/20260820/background-en.jpg', 'zh_CN' => 'https://static.example.com/community_activity/20260820/background-zh.jpg'],
        'introduction' => ['en_US' => 'An in-person meetup for traders who want to exchange market insights.', 'zh_CN' => '面向希望交流市场观点的交易者举办的线下见面会。'],
        'introduction_text' => 'An in-person meetup for traders who want to exchange market insights.',
        'content' => ['en_US' => '<p>Join our analysts and local community leaders for market sharing, live strategy sessions, and networking.</p>', 'zh_CN' => '<p>与分析师及本地社区领袖共同参与市场分享、实盘策略讲解和自由交流。</p>'],
        'content_text' => '<p>Join our analysts and local community leaders for market sharing, live strategy sessions, and networking.</p>',
        'locale_configs' => self::STORE_LOCALE_CONFIG_EXAMPLE,
        'created_at' => '2026-08-20 10:30:00',
        'updated_at' => '2026-08-20 10:30:00',
    ];

    private const UPDATED_ACTIVITY_RESPONSE_EXAMPLE = [
        ...self::ACTIVITY_RESPONSE_EXAMPLE,
        'name_text' => 'London Web3 Trading Meetup 2026',
        'name' => ['en_US' => 'London Web3 Trading Meetup 2026', 'zh_CN' => '2026 伦敦 Web3 交易者见面会'],
        'activity_time' => '2026-09-12 13:30:00 - 2026-09-12 18:30:00',
        'start_at' => '2026-09-12 13:30:00',
        'end_at' => '2026-09-12 18:30:00',
        'apply_start_at' => '2026-09-12 10:00:00',
        'apply_end_at' => '2026-09-12 13:00:00',
        'publish_type' => 1,
        'publish_type_text' => 'Manual',
        'operator_id' => 1,
        'operator_name' => 'admin',
        'audience' => ['web3_users', 'local_trading_community', 'copy_trading_learners'],
        'audience_text' => 'Web3 Users, Local Trading Community, Copy Trading Learners',
        'summary' => ['en_US' => 'Updated agenda with live market analysis and community networking.', 'zh_CN' => '更新后的议程包含实时行情分析和社区交流。'],
        'summary_text' => 'Updated agenda with live market analysis and community networking.',
        'cover_image' => ['en_US' => 'https://static.example.com/community_activity/20260820/cover-en-v2.jpg', 'zh_CN' => 'https://static.example.com/community_activity/20260820/cover-zh-v2.jpg'],
        'bg_image' => ['en_US' => 'https://static.example.com/community_activity/20260820/background-en-v2.jpg', 'zh_CN' => 'https://static.example.com/community_activity/20260820/background-zh-v2.jpg'],
        'introduction' => ['en_US' => 'The updated London meetup for active Web3 and copy traders.', 'zh_CN' => '面向活跃 Web3 及跟单交易者的伦敦线下见面会。'],
        'introduction_text' => 'The updated London meetup for active Web3 and copy traders.',
        'content' => ['en_US' => '<p>The updated program includes live market analysis, strategy workshops, and an extended networking session.</p>', 'zh_CN' => '<p>更新后的活动包含实时行情分析、策略工作坊以及延长的交流环节。</p>'],
        'content_text' => '<p>The updated program includes live market analysis, strategy workshops, and an extended networking session.</p>',
        'locale_configs' => self::UPDATE_LOCALE_CONFIG_EXAMPLE,
        'updated_at' => '2026-08-20 11:15:00',
    ];

    public function __construct(private CommunityActivityService $service) {}

    /**
     * 活动筛选选项
     *
     * Response.data:
     * - status_options:array 活动状态，0=待上线，1=上线，2=作废；列表筛选与展示按活动时间实时计算有效状态
     * - type_options:array 活动类型
     * - city_options:array 举办城市选项
     * - audience_options:array 适合人群选项，key/value 结构
     */
    #[ScrambleResponse(
        description: '活动筛选选项',
        type: 'array{status_options: array<int, array{code:int,label:string}>, type_options: array<int, array{code:int,label:string}>, city_options: array<int, array{code:string,label:string}>, audience_options: array<int, array{key:string,value:string}>}'
    )]
    public function options(): JsonResponse
    {
        return $this->succeed($this->service->options());
    }

    /**
     * 活动列表
     *
     * Query:
     * - keyword:string 活动名称关键词
     * - type:int 活动类型
     * - status:int 活动状态，后台按活动开始/结束时间与作废最终状态实时计算
     * - city:string 举办城市
     * - limit:int 每页条数，默认 20
     * - offset:int 分页偏移量，默认 0
     *
     * Response.data:
     * - data[].id:int 活动ID
     * - data[].name_text:string 当前系统语言的活动名称
     * - data[].name:object 数据库多语言名称字段
     * - data[].type:int 活动类型
     * - data[].type_text:string 活动类型文案
     * - data[].city:string 举办城市
     * - data[].city_text:string 举办城市文案
     * - data[].activity_time:string 活动时间
     * - data[].start_at:string 活动开始时间
     * - data[].end_at:string 活动结束时间
     * - data[].apply_start_at:string 报名开始时间
     * - data[].apply_end_at:string 报名截止时间
     * - data[].status_text:string 活动状态文案，按当前时间推导
     * - data[].status:int 活动状态 code，按当前时间推导；已作废保持最终状态
     * - data[].status_code:int 活动状态 code
     * - data[].publish_type:int 发布类型（按状态与活动时间逻辑推导，不落库），0=定时，1=人工
     * - data[].publish_type_text:string 发布类型文案
     * - data[].operator_id:int 最后操作管理员ID，取自 user_operation_log 最后一条操作记录
     * - data[].operator_name:string 最后操作管理员登录名
     * - data[].audience:array 适合人群
     * - data[].audience_text:string 适合人群文案
     * - data[].summary:object 活动摘要多语言
     * - data[].summary_text:string 当前系统语言的活动摘要
     * - data[].cover_image:object 封面图片
     * - data[].bg_image:object 背景图片
     * - data[].introduction:object 多语言活动正文概览介绍
     * - data[].introduction_text:string 当前系统语言的活动正文概览介绍
     * - data[].content:object 多语言活动正文内容
     * - data[].content_text:string 当前系统语言的活动正文内容
     * - data[].locale_configs:array 编辑回显用多语言配置
     * - total_count:int 总数
     */
    #[QueryParameter(name: 'keyword', description: '活动名称关键词，按当前语言展示值过滤', type: 'string', required: false)]
    #[QueryParameter(name: 'type', description: '活动类型，取 options.type_options[].code', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '活动有效状态：0=待上线，1=上线，2=作废；服务端按活动时间实时判断，不依赖定时任务落库延迟', type: 'integer', required: false)]
    #[QueryParameter(name: 'city', description: '举办城市，取 options.city_options[].code', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '活动列表',
        type: 'array{data: array<int, array{id:int,name_text:string,name:array,type:int,type_text:string,city:string,city_text:string,activity_time:string,start_at:string,end_at:string,apply_start_at:string,apply_end_at:string,status:int,status_text:string,status_code:int,publish_type:int,publish_type_text:string,operator_id:int,operator_name:string,audience:array,audience_text:string,summary:array,summary_text:string,cover_image:array,bg_image:array,introduction:array,introduction_text:string,content:array,content_text:string,locale_configs:array<int,array{lang:string,name:string,summary:string,cover_image:string,bg_image:string,introduction:string,content:string}>,created_at:string,updated_at:string}>, total_count:int}'
    )]
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'keyword' => trim((string) $request->input('keyword', '')),
            'type' => $this->nullableInt($request->input('type')),
            'status' => $this->nullableInt($request->input('status')),
            'city' => $request->input('city'),
        ];

        return $this->succeed($this->service->index(
            $filters,
            max(1, (int) $request->input('limit', 20)),
            max(0, (int) $request->input('offset', 0))
        ));
    }

    /**
     * 新增活动
     *
     * Body:
     * - type:int 活动类型，取 options.type_options[].code
     * - city:string 线下城市，取 options.city_options[].code
     * - start_at:string 活动开始时间，格式 Y-m-d H:i:s
     * - end_at:string 活动结束时间，格式 Y-m-d H:i:s
     * - apply_start_at:string 报名开始时间，格式 Y-m-d H:i:s
     * - apply_end_at:string 报名截止时间，格式 Y-m-d H:i:s；报名时间段与活动时间段不可交叉，允许首尾无缝衔接或中间留空档
     * - audience:array 适合人群多选，取 options.audience_options[].key
     * - locale_configs:array 多语言配置，每项包含 lang/name/summary/cover_image/bg_image/introduction/content
     */
    #[BodyParameter(name: 'type', description: '活动类型，取 options.type_options[].code', type: 'integer', required: true, example: 1)]
    #[BodyParameter(name: 'city', description: '线下城市，取 options.city_options[].code', type: 'string', required: true, example: 'london')]
    #[BodyParameter(name: 'start_at', description: '活动开始时间，格式 Y-m-d H:i:s', type: 'string', required: true, example: '2026-09-12 14:00:00')]
    #[BodyParameter(name: 'end_at', description: '活动结束时间，格式 Y-m-d H:i:s', type: 'string', required: true, example: '2026-09-12 18:00:00')]
    #[BodyParameter(name: 'apply_start_at', description: '报名开始时间，格式 Y-m-d H:i:s；报名时间段（apply_start_at - apply_end_at）与活动时间段（start_at - end_at）不可交叉，允许首尾无缝衔接或中间留空档', type: 'string', required: true, example: '2026-09-12 10:00:00')]
    #[BodyParameter(name: 'apply_end_at', description: '报名截止时间，格式 Y-m-d H:i:s；报名时间段与活动时间段不可交叉，允许首尾无缝衔接或中间留空档', type: 'string', required: true, example: '2026-09-12 13:30:00')]
    #[BodyParameter(name: 'audience', description: '适合人群多选，取 options.audience_options[].key', type: 'array<int, string>', required: true, example: ['web3_users', 'local_trading_community'])]
    #[BodyParameter(name: 'locale_configs', description: '多语言配置，必填。每项代表一种语言，必须至少包含 en_US，语言不可重复；支持 en_US,ja_JP,ru_RU,vi_VN,zh_CN,zh_TW。', type: self::LOCALE_CONFIG_TYPE, required: true, example: self::STORE_LOCALE_CONFIG_EXAMPLE)]
    #[BodyParameter(name: 'locale_configs.*.lang', description: '语言编码，en_US 为必填默认语言', type: 'string', required: true, example: 'en_US')]
    #[BodyParameter(name: 'locale_configs.*.name', description: '当前语言的活动名称', type: 'string', required: true, example: 'London Web3 Trading Meetup')]
    #[BodyParameter(name: 'locale_configs.*.summary', description: '当前语言的活动摘要', type: 'string', required: true, example: 'Meet local traders and learn practical Web3 trading strategies.')]
    #[BodyParameter(name: 'locale_configs.*.cover_image', description: '当前语言的活动列表封面 URL 或对象 key，入库时仅保存 key', type: 'string', required: true, example: 'https://static.example.com/community_activity/20260820/cover-en.jpg')]
    #[BodyParameter(name: 'locale_configs.*.bg_image', description: '当前语言的活动背景图片 URL 或对象 key，入库时仅保存 key', type: 'string', required: true, example: 'https://static.example.com/community_activity/20260820/background-en.jpg')]
    #[BodyParameter(name: 'locale_configs.*.introduction', description: '当前语言的活动正文概览介绍', type: 'string', required: true, example: 'An in-person meetup for traders who want to exchange market insights.')]
    #[BodyParameter(name: 'locale_configs.*.content', description: '当前语言的活动正文内容，支持 HTML', type: 'string', required: true, example: '<p>Join our analysts and local community leaders for market sharing, live strategy sessions, and networking.</p>')]
    #[ScrambleResponse(
        description: '新增后的活动，locale_configs 可直接用于修改接口回显',
        type: self::ACTIVITY_RESPONSE_TYPE,
        examples: [self::ACTIVITY_RESPONSE_EXAMPLE]
    )]
    public function store(CommunityActivityStoreRequest $request): JsonResponse
    {
        return $this->succeed($this->service->create($request->validated()), trans('public.message.add_success'));
    }

    /**
     * 修改活动
     *
     * Body 与新增一致，全部字段必填。
     */
    #[PathParameter(name: 'community_activity', description: '活动ID', type: 'integer', example: 101)]
    #[BodyParameter(name: 'type', description: '活动类型，取 options.type_options[].code', type: 'integer', required: true, example: 1)]
    #[BodyParameter(name: 'city', description: '线下城市，取 options.city_options[].code', type: 'string', required: true, example: 'london')]
    #[BodyParameter(name: 'start_at', description: '活动开始时间，格式 Y-m-d H:i:s', type: 'string', required: true, example: '2026-09-12 13:30:00')]
    #[BodyParameter(name: 'end_at', description: '活动结束时间，格式 Y-m-d H:i:s', type: 'string', required: true, example: '2026-09-12 18:30:00')]
    #[BodyParameter(name: 'apply_start_at', description: '报名开始时间，格式 Y-m-d H:i:s；报名时间段（apply_start_at - apply_end_at）与活动时间段（start_at - end_at）不可交叉，允许首尾无缝衔接或中间留空档', type: 'string', required: true, example: '2026-09-12 10:00:00')]
    #[BodyParameter(name: 'apply_end_at', description: '报名截止时间，格式 Y-m-d H:i:s；报名时间段与活动时间段不可交叉，允许首尾无缝衔接或中间留空档', type: 'string', required: true, example: '2026-09-12 13:00:00')]
    #[BodyParameter(name: 'audience', description: '适合人群多选，取 options.audience_options[].key', type: 'array<int, string>', required: true, example: ['web3_users', 'local_trading_community', 'copy_trading_learners'])]
    #[BodyParameter(name: 'locale_configs', description: '完整多语言配置，必填；修改时会整体覆盖活动的各语言内容。活动列表或新增响应中的 locale_configs 可直接回显后提交。', type: self::LOCALE_CONFIG_TYPE, required: true, example: self::UPDATE_LOCALE_CONFIG_EXAMPLE)]
    #[BodyParameter(name: 'locale_configs.*.lang', description: '语言编码，en_US 为必填默认语言', type: 'string', required: true, example: 'en_US')]
    #[BodyParameter(name: 'locale_configs.*.name', description: '当前语言的活动名称', type: 'string', required: true, example: 'London Web3 Trading Meetup 2026')]
    #[BodyParameter(name: 'locale_configs.*.summary', description: '当前语言的活动摘要', type: 'string', required: true, example: 'Updated agenda with live market analysis and community networking.')]
    #[BodyParameter(name: 'locale_configs.*.cover_image', description: '当前语言的活动列表封面 URL 或对象 key，入库时仅保存 key', type: 'string', required: true, example: 'https://static.example.com/community_activity/20260820/cover-en-v2.jpg')]
    #[BodyParameter(name: 'locale_configs.*.bg_image', description: '当前语言的活动背景图片 URL 或对象 key，入库时仅保存 key', type: 'string', required: true, example: 'https://static.example.com/community_activity/20260820/background-en-v2.jpg')]
    #[BodyParameter(name: 'locale_configs.*.introduction', description: '当前语言的活动正文概览介绍', type: 'string', required: true, example: 'The updated London meetup for active Web3 and copy traders.')]
    #[BodyParameter(name: 'locale_configs.*.content', description: '当前语言的活动正文内容，支持 HTML', type: 'string', required: true, example: '<p>The updated program includes live market analysis, strategy workshops, and an extended networking session.</p>')]
    #[ScrambleResponse(
        description: '修改后的活动，locale_configs 可继续用于编辑回显',
        type: self::ACTIVITY_RESPONSE_TYPE,
        examples: [self::UPDATED_ACTIVITY_RESPONSE_EXAMPLE]
    )]
    public function update(CommunityActivityUpdateRequest $request, int $community_activity): JsonResponse
    {
        $result = $this->service->update($community_activity, $request->validated());
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($result, trans('public.message.upd_success'));
    }

    /**
     * 作废活动
     *
     * 当前仅已作废活动不允许再次作废，其他状态均可作废。
     */
    #[PathParameter(name: 'community_activity', description: '活动ID', type: 'integer', example: 101)]
    #[ScrambleResponse(
        description: '作废活动',
        type: 'bool'
    )]
    public function voidActivity(int $community_activity): JsonResponse
    {
        $result = $this->service->voidActivity($community_activity);
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($result, trans('community_activity.message.void_success'));
    }

    /**
     * 发布活动
     *
     * 管理员在活动开始时间前人工发布，状态直接置为上线；发布类型按逻辑推导展示为人工。
     * 仅待上线活动可发布：已上线、已作废、已结束的活动会返回对应错误。
     */
    #[PathParameter(name: 'community_activity', description: '活动ID', type: 'integer', example: 101)]
    #[ScrambleResponse(
        description: '发布后的活动',
        type: self::ACTIVITY_RESPONSE_TYPE,
        examples: [self::UPDATED_ACTIVITY_RESPONSE_EXAMPLE]
    )]
    public function publish(int $community_activity): JsonResponse
    {
        $result = $this->service->publish($community_activity);
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($result, trans('community_activity.message.publish_success'));
    }

    /**
     * 活动报名列表
     *
     * Query:
     * - community_activity_id:int 活动ID
     * - keyword:string 姓名/邮箱/留言关键词
     * - limit:int 每页条数，默认 20
     * - offset:int 分页偏移量，默认 0
     *
     * Response.data:
     * - data[].id:int 报名ID
     * - data[].community_activity_id:int 活动ID
     * - data[].user_code:int 用户ID
     * - data[].activity_name:string 活动名称
     * - data[].name:string 姓名
     * - data[].email:string 邮箱
     * - data[].message:string 留言
     * - data[].submitted_at:string 提交时间
     * - data[].created_at:string 提交时间
     * - data[].updated_at:string 更新时间
     * - total_count:int 总数
     */
    #[QueryParameter(name: 'community_activity_id', description: '活动ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'keyword', description: '姓名、邮箱、留言关键词', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '活动报名列表',
        type: 'array{data: array<int, array{id:int,community_activity_id:int,user_code:int,activity_name:string,name:string,email:string,message:string,submitted_at:string,created_at:string,updated_at:string}>, total_count:int}'
    )]
    public function applications(Request $request): JsonResponse
    {
        $filters = [
            'community_activity_id' => $this->nullableInt($request->input('community_activity_id')),
            'keyword' => trim((string) $request->input('keyword', '')),
        ];

        return $this->succeed($this->service->applications(
            $filters,
            max(1, (int) $request->input('limit', 20)),
            max(0, (int) $request->input('offset', 0))
        ));
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
