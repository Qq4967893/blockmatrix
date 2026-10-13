<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Entries\PositionEntriesRequest;
use App\Models\Api\AppRelease;
use App\Models\Api\PositionRecord;
use App\Services\Api\PositionEntryRecordService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Scaffold\Framework\Enums\TypePage;

#[Group('Position Record', '广告记录管理')]
class PositionRecordController extends BaseController
{
    public function __construct(
        private PositionEntryRecordService $positionRecordService,
    ) {}

    /**
     * 广告列表
     *
     * Query:
     * - identifier:string 广告位标识
     * - platform:string|array 平台筛选，支持单值、数组、逗号分隔字符串
     * - name:string 名称筛选
     * - status:int 状态（0下架，1上架，2待上架）
     *
     * Response.data:
     * - identifier:string 当前筛选的广告位标识
     * - total_count:int 总数
     * - data[].id:int 广告记录ID
     * - data[].identifier:string 广告位标识
     * - data[].platform:array<string> 投放平台列表，如 ["webtrade","ios"]
     * - data[].name:string 名称
     * - data[].description:string 描述
     * - data[].start_time:string 开始时间
     * - data[].end_time:string 结束时间
     * - data[].status:int 状态（0下架，1上架，2待上架）
     * - data[].status_name:string 状态文案
     * - data[].locale_configs:array 多平台多语言配置，格式 [{platform,lang,img_url,link,target_url,target_type,target_param}]
     *
     * @param  Request  $request  request
     * @return JsonResponse
     *
     * @throws \Exception
     */
    #[QueryParameter(name: 'identifier', description: '广告位标识，必填时建议先从广告位列表获取，例如 home_banner', type: 'string', required: false, example: 'home_banner')]
    #[QueryParameter(name: 'platform', description: '投放平台筛选；支持数组、单值、JSON数组字符串、逗号分隔字符串。可选：miniapp,testflight,ios,android,webtrade', type: 'array<int, string>|string', required: false, example: ['ios', 'webtrade'])]
    #[QueryParameter(name: 'name', description: '广告名称筛选，按当前语言展示值模糊匹配', type: 'string', required: false, example: '首页活动')]
    #[QueryParameter(name: 'status', description: '广告状态：0=下架，1=上架，2=待上架', type: 'integer', required: false, example: 1)]
    #[Response(
        description: '广告记录列表。locale_configs 是前端编辑广告的核心字段：每一项代表某个平台某个语言下的图片和跳转配置；target_type=1 表示内部模块跳转，target_url/link 填模块 code，如 900002；target_type=2 表示外链。',
        type: 'array{identifier:string,total_count:int,data:array<int,array{id:int,identifier:string,platform:array<int,string>,name:string,description:string,start_time:string,end_time:string,status:int,status_name:string,expired:bool,operate_user_name:string,locale_configs:array<int,array{platform:string,lang:string,img_url:string,link:string,link_type:?int,target_url:string,target_type:?int,target_type_name:string,target_param:string}>}>}',
        examples: [[
            'identifier' => 'home_banner',
            'total_count' => 1,
            'data' => [[
                'id' => 12,
                'identifier' => 'home_banner',
                'platform' => ['ios', 'webtrade'],
                'name' => '首页活动',
                'description' => '首页顶部 banner',
                'start_time' => '2026-07-01 00:00:00',
                'end_time' => '2026-07-31 23:59:59',
                'status' => 1,
                'status_name' => '上架',
                'expired' => false,
                'operate_user_name' => 'admin',
                'locale_configs' => [[
                    'platform' => 'ios',
                    'lang' => 'en_US',
                    'img_url' => 'https://static.example.com/ad/ios-en.png',
                    'link' => '900002',
                    'link_type' => 1,
                    'target_url' => '900002',
                    'target_type' => 1,
                    'target_type_name' => '内部模块',
                    'target_param' => '{"code":900002}',
                ], [
                    'platform' => 'webtrade',
                    'lang' => 'en_US',
                    'img_url' => 'https://static.example.com/ad/web-en.png',
                    'link' => 'https://example.com/promo',
                    'link_type' => 2,
                    'target_url' => 'https://example.com/promo',
                    'target_type' => 2,
                    'target_type_name' => '外链',
                    'target_param' => '',
                ]],
            ]],
        ]]
    )]
    public function recordList(Request $request)
    {
        $filters = [
            'identifier' => $this->normalizeIdentifierInput($request->input('identifier', '')),
            'platform' => $this->normalizePlatformInput($request->input('platform', [])),
            'name' => trim((string) $request->input('name', '')),
            'status' => $this->normalizeStatus($request->input('status')),
        ];

        $data = $this->positionRecordService->eList($filters);

        return $this->succeed($data);
    }

    /**
     * 广告记录筛选选项
     *
     * Response.data:
     * - platform: array<{code:string,label:string}>
     * - status: array<{code:int,label:string}>
     *
     * @return JsonResponse
     */
    #[Response(
        description: '广告记录筛选选项。platform 用于列表筛选和新增/修改 platform 字段；status 用于上下架状态。',
        type: 'array{platform:array<int,array{code:string,label:string}>,status:array<int,array{code:int,label:string}>}',
        examples: [[
            'platform' => [
                ['code' => 'miniapp', 'label' => 'miniapp'],
                ['code' => 'testflight', 'label' => 'TestFlight'],
                ['code' => 'ios', 'label' => 'iOS'],
                ['code' => 'android', 'label' => 'Android'],
                ['code' => 'webtrade', 'label' => 'WebTrade'],
            ],
            'status' => [
                ['code' => 0, 'label' => '下架'],
                ['code' => 1, 'label' => '上架'],
                ['code' => 2, 'label' => '待上架'],
            ],
        ]]
    )]
    public function options()
    {
        return $this->succeed([
            'platform' => AppRelease::platformOptions(),
            'status' => PositionRecord::statusOptions(),
        ]);
    }

    /**
     * 删除条目内广告
     *
     * @param  Request  $request  request
     * @return JsonResponse
     */
    public function del(Request $request)
    {
        /** 广告记录id */
        $recId = $request->get('record_id', '');
        if (empty($recId)) {
            return $this->fail(trans('public.message.id_empty'));
        }

        $rowNum = $this->positionRecordService->delRecord($recId);
        if ($rowNum) {
            return $this->succeed();
        } else {
            return $this->fail(trans('public.message.del_error'));
        }
    }

    /**
     * 模块列表
     *
     * @param  Request  $request  request
     * @return JsonResponse
     */
    #[QueryParameter(name: 'lang', description: '模块名称语言；支持 en_US,ja_JP,ru_RU,vi_VN,zh_CN,zh_TW，默认当前语言/兜底语言', type: 'string', required: false, example: 'zh_CN')]
    #[QueryParameter(name: 'platform', description: '平台筛选；支持数组、单值、JSON数组字符串、逗号分隔字符串。当前模块列表会返回所有支持平台。', type: 'array<int, string>|string', required: false, example: ['ios'])]
    #[Response(
        description: '内部模块跳转选项。新增/修改广告记录时，locale_configs.*.target_type 传 1，locale_configs.*.target_url 或 link 传这里返回的 code；target_param 可传 {"code":900002} 这类 JSON 字符串，后端会兼容提取 code。',
        type: 'array<int,array{code:int,platforms:array<int,string>,name:string}>',
        examples: [[
            ['code' => 900001, 'platforms' => ['miniapp', 'testflight', 'ios', 'android', 'webtrade'], 'name' => '去入金'],
            ['code' => 900002, 'platforms' => ['miniapp', 'testflight', 'ios', 'android', 'webtrade'], 'name' => '去交易'],
        ]]
    )]
    public function modules(Request $request)
    {
        $lang = $this->normalizeLang($request->input('lang'));
        $platformFilter = $this->normalizePlatformInput($request->input('platform', []));
        $platforms = AppRelease::supportedPlatforms();

        $result = [];
        foreach (TypePage::cases() as $page) {
            if (! empty($platformFilter) && ! $this->matchPlatforms($platformFilter, $platforms)) {
                continue;
            }

            $result[] = [
                'code' => $page->value,
                'platforms' => $platforms,
                'name' => $this->resolvePageName($page->value, $lang),
            ];
        }

        return $this->succeed($result);
    }

    /**
     * 新增条目内广告
     *
     * Body:
     * - identifier:string 广告位标识
     * - platform:array<string> 投放平台列表，如 ["webtrade","ios"]
     * - name:string 名称
     * - description:string|null 描述
     * - locale_configs:array 多平台多语言配置
     *   - platform:string|null 平台；不传时该语言配置会应用到本次选择的所有平台
     *   - lang:string 语言，如 en_US/zh_CN
     *   - img_url:string 图片地址
     *   - link:string|null 跳转；兼容 target_url
     *   - target_url:string|null 跳转地址
     *   - target_type:int|null 跳转类型（1内部模块，2外链）
     *   - target_param:string|null 跳转参数
     * - start_time:string 开始时间
     * - end_time:string 结束时间
     * - status:int|null 状态（0下架，1上架，2待上架；默认1，未来开始的排期自动保存为2）
     *
     * Storage:
     * - platform 以逗号分隔存储，如 webtrade,ios
     * - image/link 以平台 -> 语言的 JSON 存储，如 {"ios":{"en_US":"ios-en.png"},"webtrade":{"en_US":"web-en.png"}}
     *
     * @param  PositionEntriesRequest  $request  request
     * @return JsonResponse
     */
    #[BodyParameter(name: 'identifier', description: '广告位标识，必填；关联 position_entry.identifier，例如 home_banner', type: 'string', required: true, example: 'home_banner')]
    #[BodyParameter(name: 'platform', description: '投放平台列表，必填；可选：miniapp,testflight,ios,android,webtrade。后端按数组接收，并以逗号分隔存储。', type: 'array<int, string>', required: true, example: ['ios', 'webtrade'])]
    #[BodyParameter(name: 'name', description: '广告名称，必填；当前接口保存为普通字符串，列表按当前语言直接展示该值', type: 'string', required: true, example: '首页七月活动')]
    #[BodyParameter(name: 'description', description: '广告描述，选填，最多255字符', type: 'string', required: false, example: '首页顶部 banner')]
    #[BodyParameter(name: 'locale_configs', description: '多平台多语言配置，必填。每一项代表一个平台+语言；platform 为空时会应用到本次 platform 列表中的所有平台。必须至少包含 en_US 默认语言；如按平台分别配置，则每个平台都需要 en_US。', type: 'array<int, array{platform?:string,lang:string,img_url:string,link?:string,target_url?:string,target_type?:int,target_param?:string}>', required: true, example: [
        [
            'platform' => 'ios',
            'lang' => 'en_US',
            'img_url' => 'https://static.example.com/ad/ios-en.png',
            'target_type' => 1,
            'target_url' => '900002',
            'target_param' => '{"code":900002}',
        ],
        [
            'platform' => 'webtrade',
            'lang' => 'en_US',
            'img_url' => 'https://static.example.com/ad/web-en.png',
            'target_type' => 2,
            'target_url' => 'https://example.com/promo',
        ],
    ])]
    #[BodyParameter(name: 'locale_configs.*.platform', description: '配置所属平台；可省略，省略时同一语言图片/跳转会复制到本次选择的全部 platform', type: 'string', required: false, example: 'ios')]
    #[BodyParameter(name: 'locale_configs.*.lang', description: '语言，必填；支持 en_US,ja_JP,ru_RU,vi_VN,zh_CN,zh_TW。en_US 是默认语言，必须配置。', type: 'string', required: true, example: 'en_US')]
    #[BodyParameter(name: 'locale_configs.*.img_url', description: '当前平台当前语言的广告图片地址，必填', type: 'string', required: true, example: 'https://static.example.com/ad/ios-en.png')]
    #[BodyParameter(name: 'locale_configs.*.target_type', description: '跳转类型：1=内部模块，2=外链。为空时后端会按 target_url/link 自动识别：纯数字识别为内部模块，URL 识别为外链。', type: 'integer', required: false, example: 1)]
    #[BodyParameter(name: 'locale_configs.*.target_url', description: '跳转目标；target_type=1 时传 position_modules 返回的 code 字符串，如 900002；target_type=2 时传完整 URL。兼容旧字段 link。', type: 'string', required: false, example: '900002')]
    #[BodyParameter(name: 'locale_configs.*.link', description: '旧版跳转字段，含义同 target_url；target_url 与 link 同时传时优先使用 link', type: 'string', required: false, example: '900002')]
    #[BodyParameter(name: 'locale_configs.*.target_param', description: '内部模块扩展参数，建议传 JSON 字符串，例如 {"code":900002}；也可不传，仅用 target_url/link 的模块 code。', type: 'string', required: false, example: '{"code":900002}')]
    #[BodyParameter(name: 'start_time', description: '广告开始时间，格式 Y-m-d H:i:s；同一广告位、同一平台、上架状态下时间段不可重叠', type: 'string', required: true, example: '2026-07-01 00:00:00')]
    #[BodyParameter(name: 'end_time', description: '广告结束时间，格式 Y-m-d H:i:s，必须大于等于 start_time', type: 'string', required: true, example: '2026-07-31 23:59:59')]
    #[BodyParameter(name: 'status', description: '广告状态：0=下架，1=上架，2=待上架；不传默认1，未来开始的排期自动保存为2', type: 'integer', required: false, example: 1)]
    #[Response(description: '新增成功时返回空 data', type: 'array{}')]
    public function add(PositionEntriesRequest $request)
    {
        $identifier = $this->normalizeIdentifierInput($request->input('identifier', ''));
        $platforms = $this->normalizePlatformInput($request->input('platform', []));
        $name = $request->input('name');
        $description = $request->input('description', '');
        $startTime = $request->input('start_time', '');
        $endTime = $request->input('end_time', '');
        $status = $this->normalizeStatus($request->input('status'), false);
        $localeConfigs = $request->input('locale_configs', []);

        $localizedPayloads = $this->prepareLocalizedPayloads($localeConfigs, $platforms);

        $imgUrl = $localizedPayloads['encoded']['img_url'];
        $link = $localizedPayloads['encoded']['link'];

        if (! $this->crossTime($identifier, $platforms, $startTime, $endTime, 0, $status)) {
            return $this->fail(trans('ad.position.cross_time_error'));
        }

        $result = $this->positionRecordService->add(
            $identifier,
            $platforms,
            $name,
            $description,
            $imgUrl,
            $link,
            $startTime,
            $endTime,
            $status
        );

        if ($result) {
            return $this->succeed();
        } else {
            return $this->fail(trans('public.message.add_error'));
        }
    }

    /**
     * 修改条目内广告
     *
     * Body:
     * - record_id:int 广告记录ID
     * - identifier:string 广告位标识
     * - platform:array<string>|null 投放平台列表；不传时沿用原记录
     * - name:string 名称
     * - description:string|null 描述
     * - locale_configs:array 多平台多语言配置，格式同新增接口
     * - start_time:string 开始时间
     * - end_time:string 结束时间
     * - status:int|null 状态（0下架，1上架，2待上架；不传时沿用原记录）
     *
     * 注意：该接口现在是一条记录承载多个平台，修改后会整体覆盖该记录的 platform/image/link 配置。
     *
     * @param  PositionEntriesRequest  $request  request
     * @return JsonResponse
     */
    #[BodyParameter(name: 'record_id', description: '广告记录ID，必填', type: 'integer', required: true, example: 12)]
    #[BodyParameter(name: 'identifier', description: '广告位标识，必填；关联 position_entry.identifier，例如 home_banner', type: 'string', required: true, example: 'home_banner')]
    #[BodyParameter(name: 'platform', description: '投放平台列表；修改时建议传完整目标列表。接口会整体覆盖该记录的 platform/image/link 配置。', type: 'array<int, string>', required: false, example: ['ios', 'webtrade'])]
    #[BodyParameter(name: 'name', description: '广告名称，必填', type: 'string', required: true, example: '首页七月活动')]
    #[BodyParameter(name: 'description', description: '广告描述，选填，最多255字符', type: 'string', required: false, example: '首页顶部 banner')]
    #[BodyParameter(name: 'locale_configs', description: '多平台多语言配置，必填，格式同新增接口；修改时会整体覆盖该记录的图片和跳转配置。列表接口返回的 locale_configs 可作为编辑回显后再提交。', type: 'array<int, array{platform?:string,lang:string,img_url:string,link?:string,target_url?:string,target_type?:int,target_param?:string}>', required: true, example: [
        [
            'platform' => 'ios',
            'lang' => 'en_US',
            'img_url' => 'https://static.example.com/ad/ios-en-v2.png',
            'target_type' => 1,
            'target_url' => '900002',
            'target_param' => '{"code":900002}',
        ],
        [
            'platform' => 'webtrade',
            'lang' => 'en_US',
            'img_url' => 'https://static.example.com/ad/web-en-v2.png',
            'target_type' => 2,
            'target_url' => 'https://example.com/promo',
        ],
    ])]
    #[BodyParameter(name: 'locale_configs.*.platform', description: '配置所属平台；可省略，省略时同一语言图片/跳转会复制到本次选择的全部 platform', type: 'string', required: false, example: 'ios')]
    #[BodyParameter(name: 'locale_configs.*.lang', description: '语言，必填；支持 en_US,ja_JP,ru_RU,vi_VN,zh_CN,zh_TW。en_US 是默认语言，必须配置。', type: 'string', required: true, example: 'en_US')]
    #[BodyParameter(name: 'locale_configs.*.img_url', description: '当前平台当前语言的广告图片地址，必填', type: 'string', required: true, example: 'https://static.example.com/ad/ios-en-v2.png')]
    #[BodyParameter(name: 'locale_configs.*.target_type', description: '跳转类型：1=内部模块，2=外链。内部模块 code 从 GET position_modules 获取。', type: 'integer', required: false, example: 1)]
    #[BodyParameter(name: 'locale_configs.*.target_url', description: '跳转目标；target_type=1 时传模块 code 字符串，如 900002；target_type=2 时传完整 URL。兼容旧字段 link。', type: 'string', required: false, example: '900002')]
    #[BodyParameter(name: 'locale_configs.*.link', description: '旧版跳转字段，含义同 target_url；target_url 与 link 同时传时优先使用 link', type: 'string', required: false, example: '900002')]
    #[BodyParameter(name: 'locale_configs.*.target_param', description: '内部模块扩展参数，建议传 JSON 字符串，例如 {"code":900002}', type: 'string', required: false, example: '{"code":900002}')]
    #[BodyParameter(name: 'start_time', description: '广告开始时间，格式 Y-m-d H:i:s', type: 'string', required: true, example: '2026-07-01 00:00:00')]
    #[BodyParameter(name: 'end_time', description: '广告结束时间，格式 Y-m-d H:i:s，必须大于等于 start_time', type: 'string', required: true, example: '2026-07-31 23:59:59')]
    #[BodyParameter(name: 'status', description: '广告状态：0=下架，1=上架，2=待上架；修改投放时间时按新时间自动计算', type: 'integer', required: false, example: 1)]
    #[Response(description: '修改成功时返回空 data', type: 'array{}')]
    public function update(PositionEntriesRequest $request)
    {
        $recId = $request->input('record_id', '');
        if (empty($recId)) {
            return $this->fail(trans('ad.position.validation.record_id_required'));
        }

        $recArr = $this->positionRecordService->recordInfoById($recId);
        if (empty($recArr)) {
            return $this->fail(trans('public.message.data_empty'));
        }
        $identifier = $this->normalizeIdentifierInput($request->input('identifier', ''));
        $platforms = $this->normalizePlatformInput($request->input('platform', $recArr->platform ?? []));

        $name = $request->input('name');
        $description = $request->input('description', '');
        $localeConfigs = $request->input('locale_configs', []);
        $localizedPayloads = $this->prepareLocalizedPayloads($localeConfigs, $platforms);

        $imgUrl = $localizedPayloads['encoded']['img_url'];
        $link = $localizedPayloads['encoded']['link'];
        $startTime = $request->input('start_time', $recArr->start_time ?? '');
        $endTime = $request->input('end_time', $recArr->end_time ?? '');
        $scheduleChanged = strtotime($startTime) !== strtotime((string) ($recArr->start_time ?? ''))
            || strtotime($endTime) !== strtotime((string) ($recArr->end_time ?? ''));
        $status = $scheduleChanged
            ? PositionRecord::STATUS_ENABLED
            : ($request->has('status')
                ? $this->normalizeStatus($request->input('status'), false)
                : (isset($recArr->status) ? (int) $recArr->status : null));

        // 判断是否有交叉时间
        $crossTime = $this->crossTime($identifier, $platforms, $startTime, $endTime, $recId, $status);
        if (! $crossTime) {
            return $this->fail(trans('ad.position.cross_time_error'));
        }

        $rowNum = $this->positionRecordService->update(
            $recId,
            $identifier,
            $platforms,
            $name,
            $description,
            $imgUrl,
            $link,
            $startTime,
            $endTime,
            $status
        );
        if ($rowNum) {
            return $this->succeed();
        } else {
            return $this->fail(trans('public.message.upd_error'));
        }
    }

    /**
     * 判断广告时间是否符合
     *
     * @param  string  $identifier  广告位标识
     * @param  array  $platforms  平台
     * @param  string  $startTime  开始时间
     * @param  string  $endTime  结束时间
     * @param  int  $recId  修改操作时广告id
     * @param  int|null  $status  当前记录状态
     * @return bool
     */
    private function crossTime($identifier, array $platforms, $startTime, $endTime, $recId = 0, ?int $status = null)
    {
        if ($status === PositionRecord::STATUS_DISABLED) {
            return true;
        }

        $platforms = array_values(array_unique(array_filter($platforms, function ($item) {
            return $item !== null && $item !== '';
        })));

        if (empty($identifier) || empty($platforms)) {
            return false;
        }

        foreach ($platforms as $platform) {
            $recordArr = $this->positionRecordService->entryRecordList(
                $identifier,
                $platform,
                $recId,
                [PositionRecord::STATUS_ENABLED, PositionRecord::STATUS_PENDING],
            );
            if (! $recordArr) {
                continue;
            }

            foreach ($recordArr as $key => $value) {
                // 如果是修改广告,去除掉本身
                if ($recId && $value['id'] == $recId) {
                    continue;
                }
                $bool = $this->isTimeCross($startTime, $endTime, $value['start_time'], $value['end_time']);
                if (! $bool) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 计算两个时间段是否有交集
     *
     * @param  string  $beginTime1  开始时间
     * @param  string  $endTime1  结束时间
     * @param  string  $beginTime2  开始时间
     * @param  string  $endTime2  结束时间
     * @return bool
     */
    protected function isTimeCross($beginTime1 = '', $endTime1 = '', $beginTime2 = '', $endTime2 = '')
    {
        $beginTime1 = strtotime($beginTime1);
        $endTime1 = strtotime($endTime1);
        $beginTime2 = strtotime($beginTime2);
        $endTime2 = strtotime($endTime2);

        $status = $beginTime2 - $beginTime1;
        if ($status >= 0) { // 2起始时间大于1的起始时间
            $status2 = $beginTime2 - $endTime1;
            if ($status2 >= 0) { // 2起始时间大于1的结束时间
                return true; // 2的起始时间大于1的结束时间无交集；
            } else {
                return false; // 2起始时间大于1的起始时间；2的起始时间小于或等于1的结束时间，有交集；
                // 或是2的起始时间等于1的结束时间；
            }
        } else {
            $status2 = $beginTime1 - $endTime2;
            if ($status2 >= 0) { // 1的开始时间大于2的结束时间
                return true; // 2起始时间小于或等于1的起始时间；无交集
            } else {
                return false; // 1的开始时间小于等于2的结束时间；
                // 2起始时间小于等于1的起始时间
            }
        }
    }

    /**
     * 统一平台参数格式
     *
     * @param  mixed  $platform  平台参数
     */
    private function normalizePlatformInput(mixed $platform): array
    {
        if (is_array($platform)) {
            return array_values(array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $platform), function ($item) {
                return $item !== '';
            }));
        }

        if (is_object($platform)) {
            return $this->normalizePlatformInput((array) $platform);
        }

        if (is_string($platform) && $platform !== '') {
            $trimmed = trim($platform);
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizePlatformInput($decoded);
            }

            $trimmed = trim($trimmed, "[] \t\n\r\0\x0B");

            return array_values(array_filter(array_map('trim', explode(',', $trimmed)), function ($item) {
                return $item !== '';
            }));
        }

        return [];
    }

    /**
     * 统一广告位标识
     *
     * @param  mixed  $identifier  标识
     */
    private function normalizeIdentifierInput(mixed $identifier): string
    {
        if (is_array($identifier) || is_object($identifier)) {
            return '';
        }

        return trim((string) $identifier);
    }

    /**
     * 组装多语言字段
     *
     * @param  array  $localeConfigs  前端传递的配置
     */
    private function prepareLocalizedPayloads(array $localeConfigs, array $platforms): array
    {
        $platforms = array_values(array_unique(array_filter($platforms, function ($item) {
            return $item !== null && $item !== '';
        })));

        $fields = [
            'img_url' => [],
            'link' => [],
            'target_url' => [],
            'target_param' => [],
            'target_type' => [],
        ];

        foreach ($localeConfigs as $config) {
            if (! is_array($config)) {
                continue;
            }
            $lang = $config['lang'] ?? null;
            if ($lang === null || $lang === '') {
                continue;
            }

            $configPlatforms = $this->normalizePlatformInput($config['platform'] ?? []);
            if (empty($configPlatforms)) {
                $configPlatforms = $platforms;
            }

            foreach ($configPlatforms as $platform) {
                if ($platform === '') {
                    continue;
                }

                if (array_key_exists('img_url', $config)) {
                    $fields['img_url'][$platform][$lang] = (string) $config['img_url'];
                }
                $rawLink = null;
                if (array_key_exists('link', $config)) {
                    $rawLink = $config['link'];
                } elseif (array_key_exists('target_url', $config)) {
                    $rawLink = $config['target_url'];
                }
                if ($rawLink !== null) {
                    $fields['link'][$platform][$lang] = is_scalar($rawLink) ? (string) $rawLink : '';
                    $fields['target_url'][$platform][$lang] = $fields['link'][$platform][$lang];
                }
                if (array_key_exists('target_param', $config) && $config['target_param'] !== null) {
                    $fields['target_param'][$platform][$lang] = (string) $config['target_param'];
                    if (! isset($fields['link'][$platform][$lang])) {
                        $codeFromParam = $this->extractCodeFromTargetParam($config['target_param']);
                        if ($codeFromParam !== null) {
                            $fields['link'][$platform][$lang] = $codeFromParam;
                            $fields['target_url'][$platform][$lang] = $codeFromParam;
                        }
                    }
                }
                if (array_key_exists('target_type', $config) && $config['target_type'] !== null) {
                    $fields['target_type'][$platform][$lang] = is_numeric($config['target_type'])
                        ? (int) $config['target_type']
                        : null;
                } elseif (isset($fields['link'][$platform][$lang])) {
                    $fields['target_type'][$platform][$lang] = $this->detectLinkType($fields['link'][$platform][$lang]);
                }
            }
        }

        foreach ($fields as $field => &$values) {
            $values = $this->filterLocalizedValues($values);
        }
        unset($values);

        $encoded = [];
        foreach ($fields as $field => $values) {
            $encoded[$field] = $this->encodeLocalizedMap($values);
        }

        // 兼容前端旧字段：如果仅有 link，补充 target_url/target_type
        if (empty($fields['target_url']) && ! empty($fields['link'])) {
            $encoded['target_url'] = $encoded['link'];
        }
        if (empty($fields['target_type']) && ! empty($fields['link'])) {
            $detectedTypes = [];
            foreach ($fields['link'] as $platform => $links) {
                if (! is_array($links)) {
                    continue;
                }
                foreach ($links as $lang => $value) {
                    $type = $this->detectLinkType((string) $value);
                    if ($type !== null) {
                        $detectedTypes[$platform][$lang] = $type;
                    }
                }
            }
            $encoded['target_type'] = $this->encodeLocalizedMap($detectedTypes);
            $fields['target_type'] = $detectedTypes;
        }

        return [
            'raw' => $fields,
            'encoded' => $encoded,
        ];
    }

    private function resolvePageName(int $pageId, string $lang): string
    {
        $key = sprintf('scaffold::type_page.%d.description', $pageId);
        $value = trans($key, [], $lang);

        if ($value === $key) {
            $value = trans($key);
        }

        return $value === $key ? (string) $pageId : (string) $value;
    }

    /**
     * 过滤多语言内容
     *
     * @param  array  $values  值
     */
    private function filterLocalizedValues(array $values): array
    {
        $filtered = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = $this->filterLocalizedValues($value);
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * 编码多语言内容
     *
     * @param  array  $values  值
     */
    private function encodeLocalizedMap(array $values): string
    {
        if (empty($values)) {
            return '';
        }

        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function detectLinkType(string $value): ?int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            return PositionRecord::TARGET_TYPE_INTERNAL;
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return PositionRecord::TARGET_TYPE_EXTERNAL;
        }

        return null;
    }

    private function extractCodeFromTargetParam(mixed $param): ?string
    {
        if (is_array($param)) {
            $code = $param['code'] ?? null;
        } elseif (is_string($param) && $param !== '') {
            $decoded = json_decode($param, true);
            $code = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? ($decoded['code'] ?? null)
                : null;
        } else {
            $code = null;
        }

        if ($code === null) {
            return null;
        }

        $codeStr = is_scalar($code) ? (string) $code : '';
        if ($codeStr === '' || ! ctype_digit($codeStr)) {
            return null;
        }

        return $codeStr;
    }

    /**
     * 统一status
     *
     * @param  mixed  $status  状态
     * @param  bool  $nullable  是否允许null
     */
    private function normalizeStatus(mixed $status, bool $nullable = true): ?int
    {
        if (is_numeric($status)) {
            $status = (int) $status;

            return in_array($status, PositionRecord::STATUSES, true) ? $status : null;
        }

        if (is_string($status) && $status !== '') {
            $decoded = json_decode($status, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    $first = array_values(array_filter($decoded, function ($value) {
                        return $value !== null && $value !== '';
                    }));
                    $candidate = $first[0] ?? null;
                } else {
                    $candidate = $decoded;
                }
            } else {
                $candidate = $status;
            }

            if (is_numeric($candidate)) {
                $candidate = (int) $candidate;

                return in_array($candidate, PositionRecord::STATUSES, true) ? $candidate : null;
            }

            return null;
        }

        if (is_array($status)) {
            $first = array_values(array_filter($status, function ($value) {
                return $value !== null && $value !== '';
            }));
            $candidate = $first[0] ?? null;
            if (is_numeric($candidate)) {
                $candidate = (int) $candidate;

                return in_array($candidate, PositionRecord::STATUSES, true) ? $candidate : null;
            }
        }

        if (! $nullable) {
            return PositionRecord::STATUS_ENABLED;
        }

        return null;
    }

    /**
     * 平台是否匹配
     *
     * @param  array  $filter  筛选平台
     * @param  array  $platforms  模块平台
     */
    private function matchPlatforms(array $filter, array $platforms): bool
    {
        if (empty($filter)) {
            return true;
        }

        if (empty($platforms)) {
            return true;
        }

        return (bool) array_intersect($filter, $platforms);
    }

    /**
     * 规范语言
     *
     * @param  string|null  $lang  lang
     */
    private function normalizeLang(?string $lang): string
    {
        $supported = array_keys(config('lang.languages', []));
        $fallback = config('app.locale', 'en_US');

        if ($lang && in_array($lang, $supported, true)) {
            return $lang;
        }

        $default = config('app.locale', 'en_US');

        return in_array($fallback, $supported, true)
            ? $fallback
            : ($supported[0] ?? $default);
    }
}
