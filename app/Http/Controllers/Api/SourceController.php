<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Source\SourceUpdateRequest;
use App\Services\Api\SourceService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Source', '品种管理')]
class SourceController extends BaseController
{
    public function __construct(private SourceService $sourceService)
    {
    }

    /**
     * 获取全部品种代码
     *
     * Response.data:array<string> 品种代码列表
     */
    public function sources(): JsonResponse
    {
        return $this->succeed($this->sourceService->getAllSources());
    }

    /**
     * 获取品种列表
     *
     * Query:
     * - source:string 品种代码
     * - name:string 品种名称
     * - source_type:string 品种分类
     * - status:int 上下架状态
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * @param Request $request request
     *
     * @return JsonResponse
     */
    #[QueryParameter(name: 'source', description: '品种代码，模糊查询', type: 'string', required: false)]
    #[QueryParameter(name: 'name', description: '品种名称（多语言）模糊查询', type: 'string', required: false)]
    #[QueryParameter(name: 'source_type', description: '品种分类过滤', type: 'string', required: false)]
    #[QueryParameter(name: 'status', description: '上下架状态：0=下架，1=上架', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '分页大小，默认20', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '偏移量', type: 'integer', required: false, default: 0)]
    public function index(Request $request): JsonResponse
    {
        $sourceCode = trim((string) $request->input('source', ''));
        $name       = trim((string) $request->input('name', ''));
        $sourceType = trim((string) $request->input('source_type', ''));
        $status     = $request->filled('status') ? (int) $request->input('status') : null;
        $limit      = (int) $request->input('limit', 20);
        $offset     = (int) $request->input('offset', 0);

        $data = $this->sourceService->getList($sourceCode, $name, $sourceType, $status, $limit, $offset);

        return $this->succeed($data);
    }

    /**
     * 更新品种
     *
     * @param SourceUpdateRequest $request request
     *
     * @return JsonResponse
     */
    #[BodyParameter(name: 'id', description: '品种ID', type: 'integer')]
    #[BodyParameter(name: 'icon', description: '图标URL', type: 'string')]
    #[BodyParameter(name: 'source_type', description: '品种分类', type: 'string')]
    #[BodyParameter(name: 'locale_configs', description: '多语言名称配置[{lang,name}]', type: 'array')]
    public function update(SourceUpdateRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $result  = $this->sourceService->updateSource((int) $payload['id'], $payload);

        return $this->succeed($result);
    }

    /**
     * 获取分类选项
     *
     * @return JsonResponse
     */
    public function types(): JsonResponse
    {
        return $this->succeed($this->sourceService->getTypeOptions());
    }
}
