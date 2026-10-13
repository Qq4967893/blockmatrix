<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\AppRelease\AppReleaseListRequest;
use App\Http\Requests\AppRelease\AppReleaseStoreRequest;
use App\Http\Requests\AppRelease\AppReleaseUpdateRequest;
use App\Models\Api\AppRelease;
use App\Services\Api\AppReleaseService;

class AppReleaseController extends BaseController
{
    public function __construct(private AppReleaseService $service)
    {
    }

    /**
     * 客户端版本列表
     *
     * Response.data:
     * - data: 各平台最新版本列表（每个平台仅一条）
     *   - id:int 主键
     *   - platform:string 平台
     *   - version:string 版本号
     *   - build_number:int 构建号（仅 testflight 有意义）
     *   - locale_configs:array 多语言配置[{lang,title,description}]
     *   - is_forced_update:int 是否强更(0/1)
     *   - download_url:string|null 下载地址
     *   - created_at:string|null 创建时间
     *   - updated_at:string|null 更新时间
     */
    public function index(AppReleaseListRequest $request)
    {
        $data = $this->service->list();

        return $this->succeed($data);
    }

    /**
     * 平台选项
     *
     * Response.data:
     * - platform_options: array<{code: string, label: string}>
     */
    public function platformOptions()
    {
        return $this->succeed([
            'platform_options' => AppRelease::platformOptions(),
        ]);
    }

    /**
     * 新增客户端版本
     *
     * Body:
     * - platform:string 必填（miniapp/testflight/ios/android/web）
     * - version:string 必填
     * - build_number:int 必填（testflight 必须 >0；其他平台必须 =0）
     * - locale_configs:array 必填，格式 [{lang,title,description}]
     * - is_forced_update:int 必填（0/1）
     * - download_url:string|null 选填
     *
     * 示例：
     * {
     *   "platform": "ios",
     *   "version": "1.0.3",
     *   "build_number": 0,
     *   "locale_configs": [
     *     {"lang":"en_US","title":"Update","description":"Bug fixes"},
     *     {"lang":"zh_CN","title":"版本更新","description":"修复已知问题"}
     *   ],
     *   "is_forced_update": 1,
     *   "download_url": "https://example.com/app"
     * }
     *
     * Response.data:
     * - id:int 新增记录ID
     */
    public function store(AppReleaseStoreRequest $request)
    {
        $result = $this->service->create($request->validated());
        if (! ($result['ok'] ?? false)) {
            return $this->fail((string) ($result['message'] ?? trans('public.message.data_error')));
        }

        return $this->succeed($result['data'] ?? []);
    }

    /**
     * 修改客户端版本
     *
     * 说明：
     * - platform/version/build_number 作为版本唯一键，不允许在修改接口变更。
     *
     * Body:
     * - locale_configs:array 必填，格式 [{lang,title,description}]
     * - is_forced_update:int 必填（0/1）
     * - download_url:string|null 选填
     *
     * Response.data:
     * - updated:bool 是否更新成功
     */
    public function update(AppReleaseUpdateRequest $request, int $id)
    {
        $updated = $this->service->update($id, $request->validated());
        if ($updated === null) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($updated);
    }
}
