<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\IbAccount\IbAccountListRequest;
use App\Http\Requests\IbAccount\IbAccountLevelUpdateRequest;
use App\Http\Requests\IbAccount\IbAccountSelfCommissionUpdateRequest;
use App\Http\Requests\IbAccount\IbAccountStoreRequest;
use App\Services\Api\IbAccountService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('IB Account', 'IB代理账户（ib_account）')]
class IbAccountController extends BaseController
{
    public function __construct(private IbAccountService $ibAccountService)
    {
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'ib_id', description: '代理ID', type: 'string', required: false)]
    #[QueryParameter(name: 'parent_ib_id', description: '上级代理ID', type: 'string', required: false)]
    #[QueryParameter(name: 'level_name', description: '代理等级', type: 'string', required: false)]
    #[QueryParameter(name: 'commission_status', description: '分佣状态：0=未分佣，1=已分佣', type: 'integer', required: false)]
    #[QueryParameter(name: 'user_type', description: '用户类型（Partner/Sub Partner，由 is_primary 推导）', type: 'string', required: false)]
    #[QueryParameter(name: 'protection_status', description: '保护期筛选：0=非保护期，1=保护期中', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "代理商列表（分页）\n- is_primary: 是否主代理\n- is_self_commission_enabled: 是否开启自己交易反佣\n- user_type: 用户类型（is_primary=1 为 Partner，否则为 Sub Partner）\n- team_size: 团队人数\n- team_position_size: 团队持仓量\n- protection_status: 保护期状态（0=未生效，1=保护中，2=已结束）",
        type: 'array{data: array<int, array{user_code:int,ib_id:string,is_primary:int,is_self_commission_enabled:int,is_self_commission_enabled_text:string,level_id:int,level_name:string,parent_ib_id:string,user_type:string,team_size:int,team_position_size:string,activated_at:string,updated_at:?string,commission_rule_id:int,commission_rule_name:string,commission_status:int,commission_status_text:string,protection_status:int,protection_status_text:string,level_protection_started_at:?string,level_protection_ended_at:?string}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'user_code' => 1000001,
                    'ib_id' => '000001',
                    'is_primary' => 1,
                    'is_self_commission_enabled' => 0,
                    'is_self_commission_enabled_text' => '否',
                    'level_id' => 3,
                    'level_name' => '黄金',
                    'parent_ib_id' => '-',
                    'user_type' => 'Partner',
                    'team_size' => 23,
                    'team_position_size' => '100000000',
                    'activated_at' => '2026-03-26 23:55:48',
                    'updated_at' => '2026-03-26 23:55:48',
                    'commission_rule_id' => 101,
                    'commission_rule_name' => '黄金代理佣金规则',
                    'commission_status' => 1,
                    'commission_status_text' => '已分佣',
                    'protection_status' => 1,
                    'protection_status_text' => '保护中',
                    'level_protection_started_at' => '2026-03-26 23:55:48',
                    'level_protection_ended_at' => '2026-04-26 23:55:48',
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 获取代理商列表
     *
     * Query:
     * - user_code:int 用户ID
     * - ib_id:string 代理ID
     * - parent_ib_id:string 上级代理ID
     * - level_name:string 代理等级
     * - commission_status:int 分佣状态：0未分佣，1已分佣
     * - user_type:string 用户类型（Partner/Sub Partner，由 is_primary 推导）
     * - protection_status:int 保护期筛选：0非保护期，1保护期中
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - user_code:int 用户ID
     * - ib_id:string 代理ID
     * - is_primary:int 是否主代理（1是/0否）
     * - is_self_commission_enabled:int 是否开启自己交易反佣（1是/0否）
     * - is_self_commission_enabled_text:string 是否开启自己交易反佣文案
     * - level_id:int 代理等级ID
     * - level_name:string 代理等级
     * - parent_ib_id:string 上级代理ID
     * - user_type:string 用户类型（is_primary=1 为 Partner，否则为 Sub Partner）
     * - team_size:int 团队人数
     * - team_position_size:string 团队持仓量
     * - activated_at:string 成为代理时间
     * - updated_at:?string 最后更新时间
     * - commission_rule_id:int 佣金规则ID
     * - commission_rule_name:string 佣金规则名称
     * - commission_status:int 分佣状态（0未分佣，1已分佣）
     * - commission_status_text:string 分佣状态文案
     * - protection_status:int 保护期状态（0未生效，1保护中，2已结束）
     * - protection_status_text:string 保护期状态文案
     * - level_protection_started_at:?string 等级保护开始时间
     * - level_protection_ended_at:?string 等级保护结束时间
     *
     * @param IbAccountListRequest $request request
     *
     * @return JsonResponse
     */
    public function index(IbAccountListRequest $request): JsonResponse
    {
        $result = $this->getIbAccountList(
            $request,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function export(IbAccountListRequest $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getIbAccountList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $exportCount = (int) ($preview['total_count'] ?? 0);
        $result = $this->getIbAccountList($request, $exportCount, 0);

        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'ib_id' => $item['ib_id'] ?? '',
                'is_self_commission_enabled_text' => $item['is_self_commission_enabled_text'] ?? '',
                'level_name' => $item['level_name'] ?? '',
                'parent_ib_id' => $item['parent_ib_id'] ?? '',
                'user_type' => $item['user_type'] ?? '',
                'team_size' => $item['team_size'] ?? '',
                'team_position_size' => $item['team_position_size'] ?? '',
                'commission_rule_name' => $item['commission_rule_name'] ?? '',
                'commission_status_text' => $item['commission_status_text'] ?? '',
                'protection_status_text' => $item['protection_status_text'] ?? '',
                'activated_at' => $item['activated_at'] ?? '',
                'updated_at' => $item['updated_at'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('ib-accounts-%s', now()->format('YmdHis')),
            trans('ib_level.export.ib_accounts.headers'),
            $rows,
            $exportExtension
        );
    }

    private function getIbAccountList(Request $request, int $limit, int $offset): array
    {
        return $this->ibAccountService->getList(
            [
                'user_code' => $request->input('user_code'),
                'ib_id' => $request->input('ib_id'),
                'parent_ib_id' => $request->input('parent_ib_id'),
                'level_name' => $request->input('level_name'),
                'commission_status' => $request->input('commission_status'),
                'user_type' => $request->input('user_type'),
                'protection_status' => $request->input('protection_status'),
            ],
            $limit,
            $offset
        );
    }

    #[BodyParameter('user_code', '要添加为代理商的用户ID', required: true, type: 'integer')]
    /**
     * 添加代理商
     *
     * Query:
     * - user_code:int 要添加为代理商的用户ID，必填
     *
     * @param IbAccountStoreRequest $request request
     *
     * @return JsonResponse
     */
    public function store(IbAccountStoreRequest $request): JsonResponse
    {
        $result = $this->ibAccountService->store((int) $request->input('user_code'));

        return $this->succeed($result);
    }

    #[BodyParameter('ib_id', '代理商ID（单个更新时传）', required: false, type: 'string')]
    #[BodyParameter('ib_ids', '代理商ID列表（批量更新时传）', required: false, type: 'array<int, string>')]
    #[BodyParameter('level_id', '新代理等级ID，必填', required: true, type: 'integer')]
    #[BodyParameter('level_protection_started_at', '等级保护开始时间，选填，格式：YYYY-MM-DD HH:MM:SS', required: false, type: 'string')]
    #[BodyParameter('level_protection_ended_at', '等级保护截止时间，选填，格式：YYYY-MM-DD HH:MM:SS', required: false, type: 'string')]
    /**
     * 修改代理等级，支持单个或批量。
     *
     * Body:
     * - ib_id:string 代理商ID（单个更新时传）
     * - ib_ids:array<int,string> 代理商ID列表（批量更新时传）
     * - level_id:int 新代理等级ID，必填
     * - level_protection_started_at:?string 等级保护开始时间，选填，格式：YYYY-MM-DD HH:MM:SS；不传、null 或空字符串时保存为 null
     * - level_protection_ended_at:?string 等级保护截止时间，选填，格式：YYYY-MM-DD HH:MM:SS；不传、null 或空字符串时保存为 null
     *
     * @param IbAccountLevelUpdateRequest $request request
     *
     * @return JsonResponse
     */
    public function updateLevel(IbAccountLevelUpdateRequest $request): JsonResponse
    {
        $this->ibAccountService->updateLevel(
            $request->input('ib_id'),
            $request->input('ib_ids') ?? [],
            (int) $request->input('level_id'),
            $request->input('level_protection_started_at'),
            $request->input('level_protection_ended_at')
        );

        return $this->succeed();
    }

    #[PathParameter(name: 'ib_id', description: '代理商ID（路由参数，必填）', type: 'string', required: true)]
    #[BodyParameter('is_self_commission_enabled', '是否开启自己交易反佣：0=否，1=是', required: true, type: 'integer')]
    #[Response(
        description: '设置代理商自己交易反佣开关',
        type: 'array{ib_id:string,is_self_commission_enabled:int,is_self_commission_enabled_text:string}',
        examples: [[
            'ib_id' => '000001',
            'is_self_commission_enabled' => 1,
            'is_self_commission_enabled_text' => '是',
        ]]
    )]
    /**
     * 设置代理商自己交易反佣开关
     *
     * Path:
     * - ib_id:string 代理商ID（路由参数）
     *
     * Body:
     * - is_self_commission_enabled:int 是否开启自己交易反佣（1是/0否），必填
     *
     * Response.fields:
     * - ib_id:string 代理商ID
     * - is_self_commission_enabled:int 是否开启自己交易反佣（1是/0否）
     * - is_self_commission_enabled_text:string 是否开启自己交易反佣文案
     *
     * @param IbAccountSelfCommissionUpdateRequest $request request
     * @param string $ib_id 代理商ID（路由参数）
     *
     * @return JsonResponse
     */
    public function updateSelfCommission(IbAccountSelfCommissionUpdateRequest $request, string $ib_id): JsonResponse
    {
        $result = $this->ibAccountService->updateSelfCommission(
            $ib_id,
            (int) $request->input('is_self_commission_enabled')
        );

        return $this->succeed($result);
    }

    #[PathParameter(name: 'ib_id', description: '代理商ID（路由参数，必填）', type: 'string', required: true)]
    #[Response(
        description: "代理结构子层级节点\n- 当前接口既用于首层加载，也用于继续展开下一层\n- 返回数组中的每个元素都是代理节点\n- has_children=1 表示该节点还有下一级代理，可继续调用 children\n- trader_count>0 表示该代理节点下存在直属 Trader，可进入 Trader 列表",
        type: 'array{current: array{user_code:int,level:string,ib_id:string,is_primary:int,user_type:string,team_size:int,team_position_size:string,activated_at:string,trader_count:int}, data: array<int, array{user_code:int,level:string,ib_id:string,is_primary:int,user_type:string,team_size:int,team_position_size:string,activated_at:string,has_children:int,trader_count:int}>}',
        examples: [[
            'current' => [
                'user_code' => 1000002,
                'level' => 'L1',
                'ib_id' => '000002',
                'is_primary' => 1,
                'user_type' => 'Partner',
                'team_size' => 23,
                'team_position_size' => '100000000',
                'activated_at' => '2026-03-26 23:55:48',
                'trader_count' => 6,
            ],
            'data' => [
                [
                    'user_code' => 1000003,
                    'level' => 'L2',
                    'ib_id' => '000003',
                    'is_primary' => 0,
                    'user_type' => 'Sub Partner',
                    'team_size' => 8,
                    'team_position_size' => '50000000',
                    'activated_at' => '2026-03-25 23:55:48',
                    'has_children' => 0,
                    'trader_count' => 25,
                ],
                [
                    'user_code' => 1000004,
                    'level' => 'L2',
                    'ib_id' => '000004',
                    'is_primary' => 0,
                    'user_type' => 'Sub Partner',
                    'team_size' => 12,
                    'team_position_size' => '80000000',
                    'activated_at' => '2026-03-24 23:55:48',
                    'has_children' => 1,
                    'trader_count' => 0,
                ],
                [
                    'user_code' => 1000007,
                    'level' => 'L2',
                    'ib_id' => '000007',
                    'is_primary' => 0,
                    'user_type' => 'Sub Partner',
                    'team_size' => 5,
                    'team_position_size' => '23000000',
                    'activated_at' => '2026-03-24 09:55:48',
                    'has_children' => 0,
                    'trader_count' => 8,
                ],
            ],
        ]]
    )]
    /**
     * 获取代理结构子层级节点
     *
     * Path:
     * - ib_id:string 代理商ID（路由参数，必填）
     *
     * Response.fields:
     * - current:array 当前查看代理节点信息
     * - current.user_code:int 当前代理用户ID
     * - current.level:string 当前层级
     * - current.ib_id:string 当前代理ID
     * - current.is_primary:int 是否主代理（1是/0否）
     * - current.user_type:string 当前用户类型（Partner/Sub Partner）
     * - current.team_size:int 当前团队人数
     * - current.team_position_size:string 当前团队交易量
     * - current.activated_at:string 当前成为代理时间
     * - current.trader_count:int 当前直属 Trader 总人数
     * - data:array 当前代理的直属下级代理列表
     * - user_code:int 代理用户ID
     * - level:string 当前层级
     * - ib_id:string 代理商ID
     * - is_primary:int 是否主代理（1是/0否）
     * - user_type:string 用户类型（Partner/Sub Partner）
     * - team_size:int 团队人数
     * - team_position_size:string 团队交易量
     * - activated_at:string 成为代理时间
     * - has_children:int 是否还有下一级代理（1是/0否）
     * - trader_count:int 直属 Trader 总人数；大于 0 时可进入 Trader 列表
     *
     * @param string $ib_id 代理商ID（路由参数）
     *
     * @return JsonResponse
     */
    public function children(string $ib_id): JsonResponse
    {
        return $this->succeed($this->ibAccountService->getChildren($ib_id));
    }

    #[PathParameter(name: 'ib_id', description: '上级代理ID（路由参数，必填）', type: 'string', required: true)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "Trader 列表（分页）",
        type: 'array{data: array<int, array{user_code:int,total_position_size:string,registered_at:string}>, total_count:int}'
    )]
    /**
     * 获取 Trader 列表
     *
     * Path:
     * - ib_id:string 上级代理ID（路由参数，必填）
     *
     * Query:
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.fields:
     * - data:array Trader 列表
     * - data[].user_code:int 用户ID
     * - data[].total_position_size:string 总持仓量
     * - data[].registered_at:string 注册时间
     * - total_count:int 总条数
     *
     * @param Request $request request
     * @param string $ib_id 上级代理ID（路由参数）
     *
     * @return JsonResponse
     */
    public function traders(Request $request, string $ib_id): JsonResponse
    {
        return $this->succeed($this->ibAccountService->getTraders(
            $ib_id,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        ));
    }

    #[PathParameter(name: 'user_code', description: '用户ID（ib_account.user_code）', type: 'integer', required: true)]
    #[Response(
        description: "代理商当前信息（单条）\n- is_primary: 是否主代理\n- user_type: 用户类型（is_primary=1 为 Partner，否则为 Sub Partner）\n- user_level: 用户层级\n- is_protection_period: 是否保护期",
        type: 'array{is_ib:int,ib_id:?string,is_primary:int,user_type:string,user_level:string,parent_ib_id:?string,ib_level_name:string,activated_at:?string,is_protection_period:int}',
        examples: [[
            'is_ib' => 1,
            'ib_id' => '000001',
            'is_primary' => 1,
            'user_type' => 'Partner',
            'user_level' => 'L1',
            'parent_ib_id' => null,
            'ib_level_name' => '黄金',
            'activated_at' => '2026-03-26 23:55:48',
            'is_protection_period' => 1,
        ]]
    )]
    /**
     * 获取代理商当前信息（单条）
     *
     * Path:
     * - user_code:int 用户ID（路由参数，必填）
     *
     * Response.fields:
     * - is_ib:int 是否代理商（1是/0否）
     * - ib_id:?string 代理ID
     * - is_primary:int 是否主代理（1是/0否）
     * - user_type:string 用户类型（is_primary=1 为 Partner，否则为 Sub Partner）
     * - user_level:string 用户层级（L1/L2/-）
     * - parent_ib_id:?string 上级代理ID
     * - ib_level_name:string 代理等级
     * - activated_at:?string 成为代理时间
     * - is_protection_period:int 是否保护期（1是/0否）
     *
     * @param int $user_code 用户ID（路由参数）
     *
     * @return JsonResponse
     */
    public function current(int $user_code): JsonResponse
    {
        $result = $this->ibAccountService->getCurrentInfo([
            'user_code' => $user_code,
        ]);

        return $this->succeed($result);
    }
}
