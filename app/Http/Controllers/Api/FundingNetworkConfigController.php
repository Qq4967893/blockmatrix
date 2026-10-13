<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FundingNetworkConfig\FundingNetworkConfigListRequest;
use App\Http\Requests\FundingNetworkConfig\FundingNetworkConfigStatusRequest;
use App\Http\Requests\FundingNetworkConfig\FundingNetworkConfigStoreRequest;
use App\Http\Requests\FundingNetworkConfig\FundingNetworkConfigUpdateRequest;
use App\Services\Api\FundingNetworkConfigService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('Funds', '出入金网络配置')]
class FundingNetworkConfigController extends BaseController
{
    public function __construct(
        private FundingNetworkConfigService $fundingNetworkConfigService,
    ) {
    }

    #[Response(
        description: '列表筛选项和表单下拉项',
        type: 'array{config_types: array<int, array{code:string,name:string,settings:array{relations:array<int, array{currency:string,network:string,network_text:string,network_icon_url:string,channels:array<int, array{code:string,name:string,enabled:bool}>}>}}>, networks: array<int, array{code:string,name:string}>, statuses: array<int, array{code:string,name:string}>, channels: array<int, array{code:string,name:string}>, currencies: array<int, array{code:string,name:string}>}',
        examples: [[
            'config_types' => [
                [
                    'code' => 'withdrawal',
                    'name' => '出金',
                    'settings' => [
                        'relations' => [
                            [
                                'currency' => 'USDT',
                                'network' => 'tron:0xcd8690dc',
                                'network_text' => '波场 Shasta 测试网',
                                'network_icon_url' => 'https://mangox-inc.s3.ap-northeast-1.amazonaws.com/init/chain/tron_0xcd8690dc.png',
                                'channels' => [
                                    ['code' => 'cregis', 'name' => 'cregis', 'enabled' => true],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'deposit',
                    'name' => '入金',
                    'settings' => [
                        'relations' => [
                            [
                                'currency' => 'USDT',
                                'network' => 'eip155:11155111',
                                'network_text' => '以太坊 Sepolia 测试网',
                                'network_icon_url' => 'https://mangox-inc.s3.ap-northeast-1.amazonaws.com/init/chain/eip155_11155111.png',
                                'channels' => [
                                    ['code' => 'cobo', 'name' => 'cobo', 'enabled' => true],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'networks' => [
                ['code' => 'eip155:11155111', 'name' => '以太坊 Sepolia 测试网'],
                ['code' => 'eip155:97', 'name' => 'BNB 智能链测试网'],
                ['code' => 'tron:0x94a9059e', 'name' => '波场 Nile 测试网'],
                ['code' => 'tron:0xcd8690dc', 'name' => '波场 Shasta 测试网'],
            ],
            'statuses' => [
                ['code' => 'enabled', 'name' => '启用'],
                ['code' => 'disabled', 'name' => '停用'],
            ],
            'channels' => [
                ['code' => 'cobo', 'name' => 'cobo'],
                ['code' => 'cregis', 'name' => 'cregis'],
            ],
            'currencies' => [
                ['code' => 'USDT', 'name' => 'USDT'],
            ],
        ]]
    )]
    /**
     * 获取出入金网络配置选项
     *
     * Response.data:
     * - config_types: 配置类型（withdrawal=出金，deposit=入金），settings 只返回该类型下可选组合关系
     * - config_types[].settings.relations: 可选组合关系，前端按 currency + network 找到 channels，再按 channels[].enabled 过滤可用通道
     * - networks: 钱包服务返回的网络列表，name 按当前语言从 chain_names 解析
     * - statuses: 配置状态（enabled/disabled）
     * - channels: PSP 通道列表
     * - currencies: 钱包服务返回的币种列表
     */
    public function options(): JsonResponse
    {
        return $this->succeed($this->getOptions());
    }

    #[Response(
        description: '钱包服务 ListSupportedAssets 原始数据',
        type: 'array<int, array{provider_id:string,chain_id:string,chain_names:array<string,string>,icon_url:string,token_symbol:string,deposit_supported:bool,withdraw_supported:bool,enabled:bool}>',
        examples: [[
            [
                'provider_id' => 'PSP-001',
                'chain_id' => 'TRON',
                'chain_names' => [
                    'en_US' => 'TRON',
                    'zh_CN' => 'TRON',
                ],
                'icon_url' => 'https://example.com/tron.png',
                'token_symbol' => 'USDT',
                'deposit_supported' => true,
                'withdraw_supported' => true,
                'enabled' => true,
            ],
        ]]
    )]
    /**
     * 获取钱包服务支持的全部资产配置
     *
     * Response.data:
     * - 返回钱包服务 gRPC ListSupportedAssets 的全部 ProviderCapability 映射字段
     * - 不做去重、过滤或下拉项转换
     */
    public function supportedAssets(): JsonResponse
    {
        return $this->succeed($this->fundingNetworkConfigService->listSupportedAssets());
    }

    #[QueryParameter(name: 'config_type', description: '配置类型：withdrawal=出金，deposit=入金', type: 'string', required: false)]
    #[QueryParameter(name: 'network', description: '网络编码，取 options.networks[].code', type: 'string', required: false)]
    #[QueryParameter(name: 'status', description: '配置状态：enabled=启用，disabled=停用', type: 'string', required: false)]
    #[QueryParameter(name: 'channel', description: 'PSP 通道编码，取 options.channels[].code', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'page_after_id', description: '游标分页：上一页最后一条配置 ID，第一页传 0 或不传', type: 'integer', required: false, default: 0)]
    #[QueryParameter(name: 'offset', description: '兼容旧前端字段，等同 page_after_id', type: 'integer', required: false, default: 0)]
    #[Response(
        description: '出入金网络配置列表',
        type: 'array{data: array<int, array{id:int,currency:string,config_type:string,config_type_text:string,network:string,network_text:string,channel:string,channel_text:string,status:string,status_text:string,updated_at:string,operator_name:string,can_delete:bool,can_enable:bool,can_disable:bool,can_modify:bool}>, total_count:int, has_more:bool, next_page_after_id:int}',
        examples: [[
            'data' => [
                [
                    'id' => 101,
                    'currency' => 'USDT',
                    'config_type' => 'withdrawal',
                    'config_type_text' => '出金',
                    'network' => 'TRON',
                    'network_text' => 'TRON',
                    'channel' => 'PSP-001',
                    'channel_text' => 'PSP-001',
                    'status' => 'enabled',
                    'status_text' => '启用',
                    'updated_at' => '2026-07-17 15:30:00',
                    'operator_name' => 'admin',
                    'can_delete' => false,
                    'can_enable' => false,
                    'can_disable' => true,
                    'can_modify' => true,
                ],
                [
                    'id' => 100,
                    'currency' => 'USDT',
                    'config_type' => 'deposit',
                    'config_type_text' => '入金',
                    'network' => 'ETH',
                    'network_text' => 'Ethereum',
                    'channel' => 'PSP-002',
                    'channel_text' => 'PSP-002',
                    'status' => 'not_enabled',
                    'status_text' => '未启用',
                    'updated_at' => '2026-07-17 15:20:00',
                    'operator_name' => 'admin',
                    'can_delete' => true,
                    'can_enable' => true,
                    'can_disable' => false,
                    'can_modify' => true,
                ],
            ],
            'total_count' => 2,
            'has_more' => true,
            'next_page_after_id' => 100,
        ]]
    )]
    /**
     * 获取出入金网络配置列表
     *
     * Query:
     * - config_type:string 配置类型
     * - network:string 网络编码，取 options.networks[].code
     * - status:string 配置状态
     * - channel:string PSP 通道编码，取 options.channels[].code
     * - limit:int 每页条数
     * - page_after_id:int 游标分页，上一页最后一条配置 ID，第一页传 0 或不传
     * - offset:int 兼容旧前端字段，等同 page_after_id
     *
     * Response.data[].fields:
     * - id: 配置ID
     * - currency: 币种
     * - config_type/config_type_text: 配置类型及文案
     * - network/network_text: 网络编码及当前语言文案
     * - channel/channel_text: 通道编码及展示文案
     * - status/status_text: 状态及文案
     * - updated_at: 更新时间
     * - operator_name: 操作人
     * - can_delete: 未启用或已关闭时可删除
     * - can_enable: 非启用状态可启用
     * - can_disable: 已启用状态可关闭
     * - can_modify: 可进入修改页切换 PSP 通道
     *
     * Response.pagination:
     * - total_count:int 当前页返回条数，不代表全量总数
     * - has_more: 是否还有下一页
     * - next_page_after_id: 下一页请求使用的 page_after_id
     */
    public function index(FundingNetworkConfigListRequest $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);

        return $this->succeed($this->fundingNetworkConfigService->list($request->validated(), $limit));
    }

    #[BodyParameter('configs', '批量新增配置列表', required: true, type: 'array<int, array{config_type:string,currency:string,network:string,channel:string}>')]
    #[BodyParameter('configs.*.config_type', '配置类型：withdrawal=出金，deposit=入金', required: true, type: 'string')]
    #[BodyParameter('configs.*.currency', '币种，取 options.currencies[].code', required: true, type: 'string')]
    #[BodyParameter('configs.*.network', '网络编码，取 options.networks[].code', required: true, type: 'string')]
    #[BodyParameter('configs.*.channel', 'PSP 通道编码，取 options.channels[].code', required: true, type: 'string')]
    #[Response(
        description: '批量新增出入金网络配置，默认状态由钱包服务创建为未启用',
        type: 'array<int, array{id:int,currency:string,config_type:string,config_type_text:string,network:string,network_text:string,channel:string,channel_text:string,status:string,status_text:string,updated_at:string,operator_name:string,can_delete:bool,can_enable:bool,can_disable:bool,can_modify:bool}>',
        examples: [[
            [
                'id' => 101,
                'currency' => 'USDT',
                'config_type' => 'withdrawal',
                'config_type_text' => '出金',
                'network' => 'TRON',
                'network_text' => 'TRON',
                'channel' => 'PSP-001',
                'channel_text' => 'PSP-001',
                'status' => 'not_enabled',
                'status_text' => '未启用',
                'updated_at' => '2026-07-20 12:00:00',
                'operator_name' => 'admin',
                'can_delete' => true,
                'can_enable' => true,
                'can_disable' => false,
                'can_modify' => true,
            ],
        ]]
    )]
    /**
     * 新增出入金网络配置
     *
     * Body:
     * - configs: 批量新增配置列表
     * - configs[].config_type: 配置类型
     * - configs[].currency: 币种
     * - configs[].network: 网络编码
     * - configs[].channel: PSP 通道编码
     *
     * 规则：
     * - 支持一次新增一条或多条配置
     * - 新增后默认状态由钱包服务创建为未启用
     * - 新增时不校验同配置类型、币种、网络下是否已有启用配置，启用时再校验冲突
     * - 钱包服务返回 network route conflict 时转换为友好提示：完全重复返回"当前配置已存在"，同类型币种及网络已存在其他通道返回对应冲突提示
     * - 实际调用钱包服务 CreateNetworkRoutes
     */
    public function store(FundingNetworkConfigStoreRequest $request): JsonResponse
    {
        return $this->succeed(
            $this->fundingNetworkConfigService->create($request->validated()['configs']),
            trans('public.message.add_success')
        );
    }

    #[PathParameter(name: 'id', description: '配置ID', type: 'integer', required: true)]
    #[BodyParameter('channel', 'PSP 通道编码，取 options.channels[].code', required: true, type: 'string')]
    #[Response(
        description: '修改出入金网络配置，仅允许切换 PSP 通道',
        type: 'array{id:int,currency:string,config_type:string,config_type_text:string,network:string,network_text:string,channel:string,channel_text:string,status:string,status_text:string,updated_at:string,operator_name:string,can_delete:bool,can_enable:bool,can_disable:bool,can_modify:bool}'
    )]
    /**
     * 修改出入金网络配置
     *
     * 说明：
     * - 仅支持切换 PSP 通道
     * - 不支持修改、删除或新增网络
     * - 当前配置已启用时，修改后立即生效
     * - 实际调用钱包服务 SwitchNetworkRoute
     */
    public function update(FundingNetworkConfigUpdateRequest $request, int $id): JsonResponse
    {
        $data = $this->fundingNetworkConfigService->switchProvider(
            $id,
            (string) $request->validated()['channel']
        );

        return $this->succeed($data, trans('public.message.upd_success'));
    }

    #[PathParameter(name: 'id', description: '配置ID', type: 'integer', required: true)]
    #[BodyParameter('status', '目标状态：enabled=启用，disabled=停用', required: true, type: 'string')]
    #[Response(
        description: '启用或关闭单条配置',
        type: 'array{id:int,currency:string,config_type:string,config_type_text:string,network:string,network_text:string,channel:string,channel_text:string,status:string,status_text:string,updated_at:string,operator_name:string,can_delete:bool,can_enable:bool,can_disable:bool,can_modify:bool}'
    )]
    /**
     * 启用或关闭出入金网络配置
     *
     * 规则：
     * - 每次仅操作单条配置
     * - 停用 -> 启用 时仍需校验同配置类型、币种、网络下仅允许一个启用中的 PSP 通道
     * - 已启用的配置不可重复启用
     * - 已停用的配置不可重复停用
     */
    public function updateStatus(FundingNetworkConfigStatusRequest $request, int $id): JsonResponse
    {
        $data = $this->fundingNetworkConfigService->updateStatus(
            $id,
            (string) $request->validated()['status']
        );

        return $this->succeed($data, trans('public.message.upd_success'));
    }

    #[PathParameter(name: 'id', description: '配置ID', type: 'integer', required: true)]
    #[Response(
        description: '删除（Archive）出入金网络配置',
        type: 'array{}'
    )]
    /**
     * 删除出入金网络配置
     *
     * 规则：
     * - 仅允许删除停用和未启用状态的配置
     * - 启用中的配置不允许删除
     */
    public function destroy(int $id): JsonResponse
    {
        $this->fundingNetworkConfigService->destroy($id);

        return $this->succeed([], trans('public.message.del_success'));
    }

    private function getOptions(): array
    {
        return $this->fundingNetworkConfigService->options();
    }
}
