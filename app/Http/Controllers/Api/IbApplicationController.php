<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\IbApplication\IbApplicationApproveRequest;
use App\Http\Requests\IbApplication\IbApplicationListRequest;
use App\Http\Requests\IbApplication\IbApplicationRejectRequest;
use App\Services\Api\IbApplicationService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('IB Application', 'IB申请（ib_application）')]
class IbApplicationController extends BaseController
{
    public function __construct(private IbApplicationService $ibApplicationService)
    {
    }

    #[QueryParameter(name: 'user_code', description: '申请用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'applied_at_start', description: '申请开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'applied_at_end', description: '申请结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'status', description: '审核状态：0待审核，1已通过，2已拒绝', type: 'integer', required: false)]
    #[QueryParameter(name: 'operator_name', description: '操作人', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "IB申请列表（分页）\n- user_code: 申请用户ID\n- contact_type/contact_value: 联系方式\n- influence_proof: 社交媒体链接列表\n- primary_exchange: 交易平台\n- position_size_last_3m: 平台下近三月交易量\n- promotion_description: 推广模式",
        type: 'array{data: array<int, array{id:int,user_code:int,contact_type:int,contact_value:string,influence_proof:array<int,string>,primary_exchange:int,primary_exchange_text:string,position_size_last_3m:string,promotion_description:?string,applied_at:string,operator_name:string,status:int,status_text:string,rejection_reason:?string}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'id' => 1,
                    'user_code' => 1000001,
                    'contact_type' => 2,
                    'contact_value' => '@ib_demo',
                    'influence_proof' => ['https://example.com/profile', 'https://example.com/image.png'],
                    'primary_exchange' => 1,
                    'primary_exchange_text' => 'Bybit',
                    'position_size_last_3m' => '12345.670000',
                    'promotion_description' => '社群推广',
                    'applied_at' => '2026-03-26 23:55:48',
                    'operator_name' => 'admin',
                    'status' => 0,
                    'status_text' => '待审核',
                    'rejection_reason' => null,
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 获取 IB 申请列表
     *
     * Query:
     * - user_code:int 申请用户ID
     * - applied_at_start:string 申请开始时间
     * - applied_at_end:string 申请结束时间
     * - status:int 审核状态：0待审核，1已通过，2已拒绝
     * - operator_name:string 操作人
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - id:int 申请记录ID
     * - user_code:int 申请用户ID
     * - contact_type:int 联系方式类型
     * - contact_value:string 联系方式内容
     * - influence_proof:array 社交媒体链接/图片列表
     * - primary_exchange:int 主要交易平台
     * - primary_exchange_text:string 主要交易平台文案
     * - position_size_last_3m:string 平台下近三月交易量
     * - promotion_description:?string 推广模式说明
     * - applied_at:string 申请时间
     * - operator_name:string 操作人
     * - status:int 审核状态
     * - status_text:string 审核状态文案
     * - rejection_reason:?string 拒绝原因
     *
     * @param IbApplicationListRequest $request request
     *
     * @return JsonResponse
     */
    public function index(IbApplicationListRequest $request): JsonResponse
    {
        return $this->succeed($this->getApplicationList(
            $request,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        ));
    }

    #[Response(
        description: "IB待审核申请数量\n- pending_count: 待审核申请数量",
        type: 'array{pending_count:int}',
        examples: [[
            'pending_count' => 1,
        ]]
    )]
    /**
     * 获取 IB 待审核申请数量
     *
     * Response.fields:
     * - pending_count:int 待审核申请数量
     *
     * @return JsonResponse
     */
    public function pendingCount(): JsonResponse
    {
        return $this->succeed($this->ibApplicationService->getPendingCount());
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function export(IbApplicationListRequest $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getApplicationList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $result = $this->getApplicationList($request, (int) ($preview['total_count'] ?? 0), 0);
        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'contact_value' => $item['contact_value'] ?? '',
                'primary_exchange_text' => $item['primary_exchange_text'] ?? '',
                'position_size_last_3m' => $item['position_size_last_3m'] ?? '',
                'promotion_description' => $item['promotion_description'] ?? '',
                'applied_at' => $item['applied_at'] ?? '',
                'operator_name' => $item['operator_name'] ?? '',
                'status_text' => $item['status_text'] ?? '',
                'rejection_reason' => $item['rejection_reason'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('ib-applications-%s', now()->format('YmdHis')),
            trans('ib_level.export.ib_applications.headers'),
            $rows,
            $exportExtension
        );
    }

    private function getApplicationList(IbApplicationListRequest $request, int $limit, int $offset): array
    {
        return $this->ibApplicationService->getList(
            [
                'user_code' => $request->input('user_code'),
                'applied_at_start' => $request->input('applied_at_start'),
                'applied_at_end' => $request->input('applied_at_end'),
                'status' => $request->input('status'),
                'operator_name' => $request->input('operator_name'),
            ],
            $limit,
            $offset
        );
    }

    #[BodyParameter('id', '申请记录ID', required: true, type: 'integer')]
    /**
     * 通过 IB 申请
     *
     * Query:
     * - id:int 申请记录ID，必填
     *
     * @param IbApplicationApproveRequest $request request
     *
     * @return JsonResponse
     */
    public function approve(IbApplicationApproveRequest $request): JsonResponse
    {
        $this->ibApplicationService->approve((int) $request->input('id'));

        return $this->succeed();
    }

    #[BodyParameter('id', '申请记录ID', required: true, type: 'integer')]
    #[BodyParameter('rejection_reason', '拒绝原因', required: true, type: 'string')]
    /**
     * 拒绝 IB 申请
     *
     * Query:
     * - id:int 申请记录ID，必填
     * - rejection_reason:string 拒绝原因，必填
     *
     * @param IbApplicationRejectRequest $request request
     *
     * @return JsonResponse
     */
    public function reject(IbApplicationRejectRequest $request): JsonResponse
    {
        $this->ibApplicationService->reject(
            (int) $request->input('id'),
            (string) $request->input('rejection_reason')
        );

        return $this->succeed();
    }
}
