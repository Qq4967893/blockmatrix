<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\CreditFlow\CreditFlowListRequest;
use App\Http\Requests\User\UserProfileUpdateRequest;
use App\Services\Api\CreditFlowService;
use App\Services\Api\UserService;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserInfoController extends BaseController
{
    public function __construct(
        private UserService $userService,
        private CreditFlowService $creditFlowService,
    ) {}

    /**
     * 客户信息概览
     *
     * Path:
     * - user_code:int 客户编码，必填
     *
     * Response.data.fields:
     * - id:int 记录ID
     * - user_code:int 客户编码
     * - username:string 客户昵称
     * - email:string 邮箱
     * - wallet_address:string 钱包地址
     * - network:string 网络（用户最新 chain_id）
     * - country:string 国家/地区
     * - type:int 账号类型（0:真实,1:测试,2:黑名单）
     * - type_text:string 账号类型文案（多语言）
     * - created_at:string 注册时间
     * - updated_at:string 更新时间
     * - register_mode:string 注册方式
     * - register_mode_name:string 注册方式名称
     * - total_deposit_amount:number 累计充值金额
     * - total_withdraw_amount:number 累计提现金额
     * - net_deposit_amount:number 净充值金额
     * - total_profit_amount:number 累计盈利金额
     * - total_profit_rate:number 累计盈利率(小数，例如 0.12 = 12%)
     * - total_order_count:int 总订单数(持仓+挂单+已平仓)
     * - has_open_bonus_fund:bool 是否已开通赠金账户（trade_account 中存在该客户的赠金类型交易账号）
     * - bonus_amount:number 赠金金额（最新 user_credit_record.grant_amount）
     * - unlocked_bonus_amount:number 已解锁赠金金额（user_credit_ledger UNLOCK 流水累计）
     * - bonus_status:int 赠金状态（1:可用，0:不可用；无记录时视为不可用）
     * - bonus_status_text:string 赠金状态文案
     * - bounds:array 已绑定的社媒
     * bounds[].fields:
     * - provider:string 社媒类型
     * - provider_user_id 第三方用户ID
     *
     * @param  int  $userCode  用户编码
     * @return JsonResponse
     */
    public function info($userCode)
    {
        $userInfo = $this->userService->getOverviewInfo($userCode);

        if (empty($userInfo)) {
            return $this->fail(trans('user.message.not_exist'));
        }

        return $this->succeed($userInfo);
    }

    /**
     * 客户交易账号列表
     *
     * Path:
     * - user_code:int 客户编码，必填
     *
     * Query:
     * - account_types:string|null 账号类型筛选，多个类型用英文逗号分隔：0:统一交易账号，1:跟单账号，2:比赛账号，3:赠金账户；缺省为全部
     *
     * Response.data[].fields:
     * - trade_account_id:int 交易账号ID
     * - opened_at:string 开通时间
     * - trade_account_type:int 交易账号类型（0:统一交易账号，1:跟单账号，2:比赛账号，3:赠金账户）
     * - trade_account_type_name:string 交易账号类型名称/展示
     * - total_profit_amount:number 累计盈利(数值)
     * - total_profit_amount_text:string 累计盈利展示（示例：+ 123.45 USD）
     * - total_profit_rate:number 累计盈利率(小数)
     * - total_profit_rate_text:string 累计盈利率展示（示例：+ 12.50%）
     * - total_order_count:int 总订单（持仓+挂单+已平仓）
     * - total_deposit_amount:number 总充值金额
     * - total_deposit_amount_text:string 总充值金额展示
     * - total_withdraw_amount:number 总提现金额
     * - total_withdraw_amount_text:string 总提现金额展示
     * - net_deposit_amount:number 净充值金额
     * - net_deposit_amount_text:string 净充值金额展示
     *
     * @param  int  $userCode  用户编码
     * @return JsonResponse
     */
    public function tradeAccounts(Request $request, $userCode)
    {
        $user = $this->userService->getByUserCode($userCode, ['user_code']);
        if (empty($user)) {
            return $this->fail(trans('user.message.not_exist'));
        }

        $accountTypes = $this->parseAccountTypes($request->input('account_types'));

        $accounts = $this->userService->getUserTradeAccounts($userCode, $accountTypes);

        return $this->succeed($accounts);
    }

    #[PathParameter('user_code', description: '用户ID', type: 'integer')]
    #[QueryParameter(name: 'trade_ticket', description: '交易单号', type: 'string', required: false)]
    #[QueryParameter(name: 'created_start', description: '生成开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'created_end', description: '生成结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'fund_type', description: '流水类型 code；1=发放 2=过期扣除 3=赠金清除 4=交易亏损 5=解锁', type: 'integer', required: false)]
    #[QueryParameter(name: 'credit_type', description: '赠金类型 code；0=通用赠金 1=限制型赠金', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页数据量', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '客户信用赠金流水列表',
        type: 'array{data: array<int, array{id:int,trade_ticket:string,fund_type:int,fund_type_text:string,credit_type:?int,credit_type_text:string,created_at:?string,amount:float,after_balance:float}>, total_count:int}'
    )]
    /**
     * 客户信用赠金流水
     *
     * @param  CreditFlowListRequest  $request  request
     * @param  int  $userCode  用户ID
     */
    public function creditFlows(CreditFlowListRequest $request, int $userCode): JsonResponse
    {
        $user = $this->userService->getByUserCode($userCode, ['user_code']);
        if (empty($user)) {
            return $this->fail(trans('user.message.not_exist'));
        }

        $limit = max(1, (int) $request->input('limit', 20));
        $offset = max(0, (int) $request->input('offset', 0));

        return $this->succeed(
            $this->creditFlowService->getList($userCode, [
                'trade_ticket' => trim((string) $request->input('trade_ticket', '')),
                'created_start' => $request->input('created_start'),
                'created_end' => $request->input('created_end'),
                'fund_type' => $request->input('fund_type'),
                'credit_type' => $request->input('credit_type'),
            ], $limit, $offset)
        );
    }

    #[PathParameter('user_code', description: '用户ID', type: 'integer')]
    #[ScrambleResponse(
        description: '客户信用赠金流水选项',
        type: 'array{fund_type_options: array<int, array{code:int,label:string}>, credit_type_options: array<int, array{code:int,label:string}>, summary: array{total_bonus_amount:float,historical_unlocked_amount:float,current_bonus_amount:float,current_unlocked_amount:float}}'
    )]
    /**
     * 客户信用赠金流水选项
     *
     * @param  int  $userCode  用户ID
     */
    public function creditFlowOptions(int $userCode): JsonResponse
    {
        $user = $this->userService->getByUserCode($userCode, ['user_code']);
        if (empty($user)) {
            return $this->fail(trans('user.message.not_exist'));
        }

        return $this->succeed($this->creditFlowService->options($userCode));
    }

    /**
     * @return array<int>
     */
    private function parseAccountTypes(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : explode(',', (string) $value);

        return collect($values)
            ->filter(static fn ($type): bool => is_scalar($type) && trim((string) $type) !== '')
            ->map(static fn ($type): int => (int) $type)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * 更新用户基础资料
     *
     * Query:
     * - user_code:int 用户ID，必填
     * - first_name:string 名
     * - last_name:string 姓
     * - country:string 国家编码
     *
     * @param  UserProfileUpdateRequest  $request  request
     */
    public function updateProfile(UserProfileUpdateRequest $request): JsonResponse
    {
        $payload = array_filter([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'country' => $request->input('country') ? strtoupper($request->input('country')) : null,
        ], static fn ($v) => $v !== null && $v !== '');

        if (empty($payload)) {
            return $this->fail(trans('public.message.data_error'));
        }

        $this->userService->updateUserProfile(
            (int) $request->input('user_code'),
            $payload,
            auth()->id() ?? 0
        );

        return $this->succeed();
    }
}
