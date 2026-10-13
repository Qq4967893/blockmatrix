<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\IbLevel\IbLevelListRequest;
use App\Http\Requests\IbLevel\IbLevelStoreRequest;
use App\Http\Requests\IbLevel\IbLevelUpdateRequest;
use App\Services\Api\IbLevelService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('IB Level', 'IB等级（ib_level）')]
class IbLevelController extends BaseController
{
    public function __construct(private IbLevelService $ibLevelService) {}

    #[QueryParameter(name: 'level_pos', description: '代理等级排序', type: 'integer', required: false)]
    #[QueryParameter(name: 'name', description: '等级名称', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "代理等级列表（分页；按当前生效保底佣金比例升序排序）\n- name: 当前登录语言下的等级名称\n- commission_rule_name: 当前登录语言下的佣金规则名称\n- commission_rule_base_commission_rate: 佣金规则当前生效的保底佣金比例（百分比）\n- is_initial: 是否为初始默认等级，1=是，0=否\n- is_initial_text: 是否为初始默认等级文案\n- rule_locked: 是否锁定等级考核规则编辑，1=锁定，0=可编辑\n- 第一级等级的考核规则固定为：活跃客户数>=1，团队持仓量>=1",
        type: 'array{data: array<int, array{id:int,level_pos:int,name:string,locale_configs:array<int, array{lang:string,name:string}>,commission_rule_id:int,commission_rule_name:string,commission_rule_base_commission_rate:string,is_initial:int,is_initial_text:string,active_trader_threshold:int,team_position_size_threshold:string,assessment_rule_text:string,rule_locked:int,created_at:string,updated_at:string}>, total_count:int, next_level_id:int}',
        examples: [[
            'data' => [
                [
                    'id' => 1,
                    'level_pos' => 1,
                    'name' => '黄金',
                    'locale_configs' => [
                        ['lang' => 'zh_CN', 'name' => '黄金'],
                        ['lang' => 'en_US', 'name' => 'Gold'],
                    ],
                    'commission_rule_id' => 101,
                    'commission_rule_name' => '黄金佣金配置',
                    'commission_rule_base_commission_rate' => '2.5',
                    'is_initial' => 1,
                    'is_initial_text' => '是',
                    'active_trader_threshold' => 1,
                    'team_position_size_threshold' => '1.000000',
                    'assessment_rule_text' => '活跃客户数>=1，团队持仓量>=1',
                    'rule_locked' => 1,
                    'created_at' => '2026-03-26 23:55:48',
                    'updated_at' => '2026-03-26 23:55:48',
                ],
            ],
            'total_count' => 1,
            'next_level_id' => 2,
        ]]
    )]
    /**
     * 获取代理等级列表
     *
     * Query:
     * - level_pos:int 代理等级排序
     * - name:string 等级名称
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - id:int 等级配置ID
     * - level_pos:int 代理等级排序
     * - name:string 当前登录语言下的等级名称
     * - locale_configs:array 多语言配置[{lang,name}]
     * - commission_rule_id:int 佣金配置ID
     * - is_initial:int 是否为初始默认等级
     * - commission_rule_name:string 当前登录语言下的佣金配置名称
     * - commission_rule_base_commission_rate:string 佣金配置当前生效的保底佣金比例（百分比）
     * - active_trader_threshold:int 活跃客户数门槛
     * - team_position_size_threshold:string 团队持仓量门槛
     * - assessment_rule_text:string 等级考核规则文案
     * - rule_locked:int 是否锁定等级考核规则编辑，1=锁定，0=可编辑
     * - created_at:string 创建时间
     * - updated_at:string 修改时间
     * - next_level_id:int 下一个追加序号（仅展示；保存后实际 level_pos 按当前生效保底佣金比例自动排序）
     *
     * @param  IbLevelListRequest  $request  request
     */
    public function index(IbLevelListRequest $request): JsonResponse
    {
        return $this->succeed($this->getLevelList(
            $request,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        ));
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function export(IbLevelListRequest $request): \Symfony\Component\HttpFoundation\Response|JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getLevelList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $result = $this->getLevelList($request, (int) ($preview['total_count'] ?? 0), 0);
        $rows = array_map(static function (array $item): array {
            return [
                'level_pos' => $item['level_pos'] ?? '',
                'name' => $item['name'] ?? '',
                'commission_rule_name' => $item['commission_rule_name'] ?? '',
                'is_initial' => $item['is_initial'] ?? '',
                'is_initial_text' => $item['is_initial_text'] ?? '',
                'active_trader_threshold' => $item['active_trader_threshold'] ?? '',
                'team_position_size_threshold' => $item['team_position_size_threshold'] ?? '',
                'assessment_rule_text' => $item['assessment_rule_text'] ?? '',
                'rule_locked' => $item['rule_locked'] ?? '',
                'created_at' => $item['created_at'] ?? '',
                'updated_at' => $item['updated_at'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('ib-levels-%s', now()->format('YmdHis')),
            trans('ib_level.export.ib_levels.headers'),
            $rows,
            $exportExtension
        );
    }

    private function getLevelList(IbLevelListRequest $request, int $limit, int $offset): array
    {
        return $this->ibLevelService->getList(
            [
                'level_pos' => $request->input('level_pos'),
                'name' => $request->input('name'),
            ],
            $limit,
            $offset
        );
    }

    #[Response(
        description: '佣金配置列表（仅返回已生效）',
        type: 'array<int, array{id:int,name:string}>',
        examples: [[
            ['id' => 101, 'name' => '黄金佣金配置'],
            ['id' => 102, 'name' => '白银佣金配置'],
        ]]
    )]
    /**
     * 获取佣金配置列表
     *
     * Response.fields:
     * - id:int 佣金配置ID
     * - name:string 当前登录语言下的佣金配置名称
     */
    public function commissionRules(): JsonResponse
    {
        return $this->succeed($this->ibLevelService->getCommissionRuleOptions());
    }

    #[BodyParameter(name: 'locale_configs', description: '多语言配置[{lang,name}]，必填', type: 'array', required: true)]
    #[BodyParameter(name: 'commission_rule_id', description: '佣金配置ID，必填', type: 'integer', required: true)]
    #[BodyParameter(name: 'is_initial', description: '是否为初始默认等级，非必填', type: 'integer', required: false)]
    #[BodyParameter(name: 'active_trader_threshold', description: '活跃客户数门槛，必填；需大于上一级，不要求小于下一级；第一级固定为 1', type: 'integer', required: true)]
    #[BodyParameter(name: 'team_position_size_threshold', description: '团队持仓量门槛，必填；需大于上一级，不要求小于下一级；第一级固定为 1', type: 'number', required: true)]
    /**
     * 新增代理等级
     *
     * Body:
     * - locale_configs:array 多语言配置[{lang,name}]，必填；等级名称不能重复
     * - commission_rule_id:int 佣金配置ID，必填
     * - is_initial:int 是否为初始默认等级，非必填
     * - active_trader_threshold:int 活跃客户数门槛，必填；需大于上一级，不要求小于下一级；第一级固定为 1
     * - team_position_size_threshold:number 团队持仓量门槛，必填；需大于上一级，不要求小于下一级；第一级固定为 1
     *
     * @param  IbLevelStoreRequest  $request  request
     */
    public function store(IbLevelStoreRequest $request): JsonResponse
    {
        $result = $this->ibLevelService->store($request->validated());
        if (! $result['is_succ']) {
            return $this->fail($result['message']);
        }

        return $this->succeed();
    }

    #[PathParameter(name: 'level_id', description: '等级配置ID（路由参数，必填）', type: 'integer', required: true)]
    #[BodyParameter(name: 'locale_configs', description: '多语言配置[{lang,name}]，必填', type: 'array', required: true)]
    #[BodyParameter(name: 'commission_rule_id', description: '佣金配置ID，必填', type: 'integer', required: true)]
    #[BodyParameter(name: 'is_initial', description: '是否为初始默认等级，非必填', type: 'integer', required: false)]
    #[BodyParameter(name: 'active_trader_threshold', description: '活跃客户数门槛，必填；需大于上一级，不要求小于下一级；第一级固定为 1', type: 'integer', required: true)]
    #[BodyParameter(name: 'team_position_size_threshold', description: '团队持仓量门槛，必填；需大于上一级，不要求小于下一级；第一级固定为 1', type: 'number', required: true)]
    /**
     * 修改代理等级
     *
     * Path:
     * - level_id:int 等级配置ID（路由参数，必填）
     *
     * Body:
     * - locale_configs:array 多语言配置[{lang,name}]，必填；等级名称不能重复
     * - commission_rule_id:int 佣金配置ID，必填
     * - is_initial:int 是否为初始默认等级，非必填
     * - active_trader_threshold:int 活跃客户数门槛，必填；需大于上一级，不要求小于下一级；第一级固定为 1
     * - team_position_size_threshold:number 团队持仓量门槛，必填；需大于上一级，不要求小于下一级；第一级固定为 1
     *
     * @param  IbLevelUpdateRequest  $request  request
     * @param  int  $level_id  等级配置ID（路由参数）
     */
    public function update(IbLevelUpdateRequest $request, int $level_id): JsonResponse
    {
        $result = $this->ibLevelService->update($level_id, $request->validated());
        if (! $result['is_succ']) {
            return $this->fail($result['message']);
        }

        return $this->succeed();
    }

    #[PathParameter(name: 'level_id', description: '等级配置ID（路由参数，必填）', type: 'integer', required: true)]
    /**
     * 删除代理等级
     *
     * Path:
     * - level_id:int 等级配置ID（路由参数，必填）
     *
     * @param  int  $level_id  等级配置ID（路由参数）
     */
    public function destroy(int $level_id): JsonResponse
    {
        $this->ibLevelService->destroy($level_id);

        return $this->succeed();
    }
}
