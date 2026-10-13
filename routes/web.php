<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AdminEventController;
use App\Http\Controllers\Api\AppReleaseController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\BalanceFlowController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CommunityActivityController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\DataOverviewController;
use App\Http\Controllers\Api\DataOverviewChartController;
use App\Http\Controllers\Api\FundingApplyController;
use App\Http\Controllers\Api\FundingNetworkConfigController;
use App\Http\Controllers\Api\GrantRuleController;
use App\Http\Controllers\Api\TemplateNoticeController;
use App\Http\Controllers\Api\TemplateLayoutController;
use App\Http\Controllers\Api\WalletImportController;
use App\Http\Controllers\Api\WalletImportDetailController;
use App\Http\Controllers\Api\PointRuleController;
use App\Http\Controllers\Api\PositionEntryController;
use App\Http\Controllers\Api\PositionRecordController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\SystemMessageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserInfoController;
use App\Http\Controllers\Api\UserPointLogController;
use App\Http\Controllers\Api\FundingLedgerController;
use App\Http\Controllers\Api\FundingAccountController;
use App\Http\Controllers\Api\IbAccountController;
use App\Http\Controllers\Api\IbApplicationController;
use App\Http\Controllers\Api\IbCommissionRuleController;
use App\Http\Controllers\Api\IbCommissionRecordController;
use App\Http\Controllers\Api\IbLevelController;
use App\Http\Controllers\Api\IbLevelChangeLogController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\CopyTradingController;
use App\Http\Controllers\Api\UserOperationLogController;
use App\Http\Controllers\Api\WhiteListController;
use App\Http\Controllers\Api\BlackListController;
use App\Http\Controllers\Blockmatrix\AdminUserController;
use App\Http\Controllers\Blockmatrix\AdminUserLevelController;
use App\Http\Controllers\Blockmatrix\LanguageController;
use App\Http\Controllers\Blockmatrix\LoginController;
use App\Http\Controllers\Blockmatrix\MenuController;
use App\Http\Controllers\Blockmatrix\RoleController;
use App\Http\Controllers\Blockmatrix\UserMenuFavoriteController;
use App\Http\Controllers\Blockmatrix\CloudFileController;
use App\Http\Controllers\Et6\TradeController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api')
    ->middleware(['api', 'throttle:global'])
    ->group(function () {
        Route::prefix('auth')
            ->group(function () {
                Route::post('login', [LoginController::class, 'login'])
                    ->middleware('throttle:login')
                    ->withoutMiddleware([PreventRequestForgery::class]);
                Route::get('captcha', [LoginController::class, 'captcha']);
                // 使用 POST 避免 GET 触发的被动退出风险
                Route::post('logout', [LoginController::class, 'logout']);
                Route::get('csrf-token', [LoginController::class, 'csrfToken'])->middleware('auth');
            });

        Route::group(
            ['middleware' => ['auth', 'permission']],
            function () {
                //角色
                Route::get('roles', [RoleController::class, 'list']); //角色列表
                Route::get('roles/{role_id}', [RoleController::class, 'info'])->where('role_id', '[0-9]+'); //角色详情
                Route::post('roles/add', [RoleController::class, 'add']); //添加角色
                Route::put('roles/{role_id}', [RoleController::class, 'modify'])->where('role_id', '[0-9]+'); //修改角色
                Route::put('roles/{role_id}/status', [RoleController::class, 'updateStatus'])->where('role_id', '[0-9]+'); //修改角色状态
                Route::get('roles/area_roles', [RoleController::class, 'getAreaRoles']); //获取大区角色

                //菜单
                Route::get('menus', [MenuController::class, 'list']); //菜单列表
                Route::get('menus/{menu_id}', [MenuController::class, 'info'])->where('menu_id', '[0-9]+'); //菜单详情
                Route::post('menus', [MenuController::class, 'add']); //添加菜单
                Route::put('menus/{menu_id}', [MenuController::class, 'modify'])->where('menu_id', '[0-9]+'); //修改菜单
                Route::put('menus/{menu_id}/status', [MenuController::class, 'updateStatus'])->where('menu_id', '[0-9]+'); //修改菜单状态

                //菜单收藏
                Route::get('favorites/menus', [UserMenuFavoriteController::class, 'list']); //我的收藏菜单
                Route::post('favorites/menus', [UserMenuFavoriteController::class, 'add']); //收藏菜单
                Route::delete('favorites/menus', [UserMenuFavoriteController::class, 'remove']); //取消收藏菜单

                //后台账户
                Route::get('managers', [AdminUserController::class, 'list']); //用户列表
                Route::get('managers/permissions', [AdminUserController::class, 'getPermissions']); //获取用户权限数据
                Route::post('managers', [AdminUserController::class, 'add']); //添加用户
                Route::post('managers/modify', [AdminUserController::class, 'modify'])->where('manager_id', '[0-9]+'); //修改用户
                Route::put('managers/reset', [AdminUserController::class, 'resetPassword']); //重置密码
                Route::put('managers/change/{manager_id}/{status}', [AdminUserController::class, 'changeStatus']);  //修改用户状态
                Route::get('managers/generate_password', [AdminUserController::class, 'generatePassword']); //生成随机密码
                Route::put('managers/password', [AdminUserController::class, 'changePassword']); //用户修改密码

                //层级
                Route::get('user_levels', [AdminUserLevelController::class, 'list']); //层级列表
                Route::post('user_levels', [AdminUserLevelController::class, 'add']); //添加层级
                Route::put('user_levels/{level_id}', [AdminUserLevelController::class, 'modify'])->where('level_id', '[0-9]+'); //修改层级
                Route::delete('user_levels/{level_id}', [AdminUserLevelController::class, 'delete'])->where('level_id', '[0-9]+'); //删除层级

                //获取语言选项
                Route::get('get_lang', [LanguageController::class, 'getLang']);

                //广告位管理
                Route::get('area/list', [AreaController::class, 'areaList']);//大区列表
                Route::get('area/countries', [AreaController::class, 'areaCountries']);//大区下国家地区列表
                Route::get('position_entry', [PositionEntryController::class, 'entryList']);//广告位列表
                Route::post('position_entry/add', [PositionEntryController::class, 'addEntry']);//新增广告位
                Route::post('position_entry/update', [PositionEntryController::class, 'updateEntry']);//修改广告位
                Route::delete('position_entry/del', [PositionEntryController::class, 'deleteEntry']); //删除广告位

                //广告记录管理
                Route::get('position_modules', [PositionRecordController::class, 'modules']);//模块列表
                Route::get('position_record/options', [PositionRecordController::class, 'options']);//广告记录选项
                Route::get('position_record', [PositionRecordController::class, 'recordList']);//广告记录列表
                Route::post('position_record/add', [PositionRecordController::class, 'add']);//新增广告记录
                Route::post('position_record/update', [PositionRecordController::class, 'update']);//修改广告记录
                Route::delete('position_record/del', [PositionRecordController::class, 'del']);//删除广告记录

                //文章/新闻管理
                Route::get('articles/options', [ArticleController::class, 'options']);//文章选项
                Route::get('articles', [ArticleController::class, 'index']);//文章列表
                Route::post('articles', [ArticleController::class, 'store']);//新增文章
                Route::put('articles/{article}', [ArticleController::class, 'update'])->where('article', '[0-9]+');//修改文章
                Route::put('articles/{article}/status', [ArticleController::class, 'updateStatus'])->where('article', '[0-9]+');//发布/下线/删除文章

                //发送消息
                Route::get('message', [SystemMessageController::class, 'index']); //系统消息列表
                Route::post('message/send', [SystemMessageController::class, 'send']); //发送消息

                //客户相关
                Route::get('user/list', [UserController::class, 'list']); //客户列表
                Route::get('user/list/export', [UserController::class, 'export']); //客户列表导出
                Route::get('user/options', [UserController::class, 'options']); //客户划转校验
                Route::get('user/login_logs', [UserController::class, 'loginLogs']); //用户登录日志列表
                Route::get('user/login_logs/export', [UserController::class, 'exportLoginLogs']); //用户登录日志导出
                Route::get('user/login_logs/options', [UserController::class, 'loginLogOptions']); //用户登录日志筛选项

                //IB相关
                Route::get('ib_applications', [IbApplicationController::class, 'index']); //IB申请列表
                Route::get('ib_applications/pending_count', [IbApplicationController::class, 'pendingCount']); //IB待审核申请数量
                Route::get('ib_applications/export', [IbApplicationController::class, 'export']); //IB申请列表导出
                Route::post('ib_applications/approve', [IbApplicationController::class, 'approve']); //通过IB申请
                Route::post('ib_applications/reject', [IbApplicationController::class, 'reject']); //拒绝IB申请

                Route::get('ib_accounts', [IbAccountController::class, 'index']); //IB代理商列表
                Route::get('ib_accounts/export', [IbAccountController::class, 'export']); //IB代理商列表导出
                Route::post('ib_accounts', [IbAccountController::class, 'store']); //添加IB代理商
                Route::put('ib_accounts/level', [IbAccountController::class, 'updateLevel']); //修改IB代理等级
                Route::put('ib_accounts/{ib_id}/self_commission', [IbAccountController::class, 'updateSelfCommission'])->where('ib_id', '[0-9]+'); //修改IB自己交易反佣开关
                Route::get('ib_accounts/{ib_id}/children', [IbAccountController::class, 'children']); //IB代理结构子层级节点
                Route::get('ib_accounts/{ib_id}/traders', [IbAccountController::class, 'traders']); //IB代理节点下Trader列表
                Route::get('ib_accounts/{user_code}/current', [IbAccountController::class, 'current'])->where('user_code', '[0-9]+'); //IB代理商当前信息

                Route::get('ib_levels', [IbLevelController::class, 'index']); //IB代理商等级列表
                Route::get('ib_levels/export', [IbLevelController::class, 'export']); //IB代理商等级列表导出
                Route::get('ib_levels/commission_rules', [IbLevelController::class, 'commissionRules']); //IB佣金配置列表
                Route::post('ib_levels', [IbLevelController::class, 'store']); //新增IB代理等级
                Route::put('ib_levels/{level_id}', [IbLevelController::class, 'update'])->where('level_id', '[0-9]+'); //修改IB代理等级
                Route::delete('ib_levels/{level_id}', [IbLevelController::class, 'destroy'])->where('level_id', '[0-9]+'); //删除IB代理等级
                Route::get('ib_level_change_logs/{user_code}', [IbLevelChangeLogController::class, 'index'])->where('user_code', '[0-9]+'); //IB等级变更记录

                Route::get('ib_commission_rules', [IbCommissionRuleController::class, 'index']); //IB佣金规则列表
                Route::get('ib_commission_rules/{rule_id}/detail', [IbCommissionRuleController::class, 'detail'])->where('rule_id', '[0-9]+'); //IB佣金规则详情列表
                Route::post('ib_commission_rules', [IbCommissionRuleController::class, 'store']); //新增IB佣金规则
                Route::put('ib_commission_rules/{rule_id}', [IbCommissionRuleController::class, 'update'])->where('rule_id', '[0-9]+'); //修改IB佣金规则
                Route::post('ib_commission_rules/{rule_id}/review', [IbCommissionRuleController::class, 'review'])->where('rule_id', '[0-9]+'); //审核IB佣金规则
                Route::delete('ib_commission_rules/{rule_id}', [IbCommissionRuleController::class, 'destroy'])->where('rule_id', '[0-9]+'); //删除IB佣金规则

                Route::get('ib_commission_records', [IbCommissionRecordController::class, 'index']); //IB佣金记录列表
                Route::post('ib_commission_records/reject', [IbCommissionRecordController::class, 'reject']); //拒绝IB佣金结算
                Route::get('ib_commission_records/sources', [IbCommissionRecordController::class, 'sources']); //IB佣金交易品种列表
                Route::get('ib_commission_records/{commission_record_id}/allocations', [IbCommissionRecordController::class, 'allocations'])->where('commission_record_id', '[0-9]+'); //IB佣金分配详情
                Route::get('ib_commission_records/{user_code}/statistics', [IbCommissionRecordController::class, 'statistics'])->where('user_code', '[0-9]+'); //IB佣金统计

                Route::get('user_operation_logs/{user_code}/ib_change_logs', [UserOperationLogController::class, 'ibChangeLogs'])->where('user_code', '[0-9]+'); //IB变更记录

                Route::get('deposits/filters', [FundingApplyController::class, 'types']); //入金筛选项
                Route::get('deposits', [FundingApplyController::class, 'index']); //资金申请入金列表（funding_apply）
                Route::get('deposits/export', [FundingApplyController::class, 'export']); //资金申请入金列表导出（funding_apply）
                Route::get('captcha/email_code', [CaptchaController::class, 'emailCode']); //查询邮件验证码
                Route::get('captcha/email_code_logs', [CaptchaController::class, 'emailCodeLogs']); //查询邮件验证码记录
                Route::get('withdrawals/options', [FundingApplyController::class, 'withdrawalOptions']); //出金风控/财务筛选项（funding_apply）
                Route::get('withdrawals/risk_apply', [FundingApplyController::class, 'riskApply']); //出金风控列表（funding_apply）
                Route::get('withdrawals/risk_apply/export', [FundingApplyController::class, 'exportRisk']); //出金风控列表导出（funding_apply）
                Route::get('withdrawals/finance_apply', [FundingApplyController::class, 'financeApply']); //出金财务列表（funding_apply）
                Route::get('withdrawals/finance_apply/export', [FundingApplyController::class, 'exportFinance']); //出金财务列表导出（funding_apply）
                Route::post('withdrawals/risk/audit', [FundingApplyController::class, 'riskAudit']); //出金风控审核（funding_apply）
                Route::post('withdrawals/finance/audit', [FundingApplyController::class, 'financeAudit']); //出金财务审核通过（处理中/失败状态人工确认hash）
                Route::post('withdrawals/finance/reject', [FundingApplyController::class, 'financeReject']); //出金财务审核驳回（处理中/失败状态，funding_apply）
                Route::post('withdrawals/finance/retry', [FundingApplyController::class, 'financeRetry']); //财务重新发起钱包出金：重试窗口内立即重试；已耗尽失败单重置后重新发起
                Route::get('fund_flows', [FundingLedgerController::class, 'index']); //资金流水列表
                Route::get('fund_flows/export', [FundingLedgerController::class, 'export']); //资金流水列表导出
                Route::get('fund_flows/management', [FundingLedgerController::class, 'management']); //资金管理列表
                Route::get('fund_flows/types', [FundingLedgerController::class, 'types']); //资金类型列表
                Route::get('fund_flows/management/types', [FundingLedgerController::class, 'managementTypes']); //资金管理类型列表
                Route::post('fund_flows', [FundingLedgerController::class, 'add'])->middleware('route.lock'); //新增资金流水
                Route::get('funding_network_configs/options', [FundingNetworkConfigController::class, 'options']); //出入金网络配置筛选项
                Route::get('funding_network_configs/supported_assets', [FundingNetworkConfigController::class, 'supportedAssets']); //钱包服务支持的全部资产配置
                Route::get('funding_network_configs', [FundingNetworkConfigController::class, 'index']); //出入金网络配置列表
                Route::post('funding_network_configs', [FundingNetworkConfigController::class, 'store']); //新增出入金网络配置
                Route::put('funding_network_configs/{id}', [FundingNetworkConfigController::class, 'update'])->where('id', '[0-9]+'); //修改出入金网络配置
                Route::put('funding_network_configs/{id}/status', [FundingNetworkConfigController::class, 'updateStatus'])->where('id', '[0-9]+'); //启用/停用出入金网络配置
                Route::delete('funding_network_configs/{id}', [FundingNetworkConfigController::class, 'destroy'])->where('id', '[0-9]+'); //删除出入金网络配置
                Route::get('wallet_import/template', [WalletImportController::class, 'getTemplate']); //批量操作-模板下载
                Route::post('wallet_import/import', [WalletImportController::class, 'import']); //批量操作-上传
                Route::get('wallet_import/list', [WalletImportController::class, 'getList']); //批量操作-列表
                Route::get('wallet_import/detail/{job_id}', [WalletImportDetailController::class, 'getList'])->where('job_id', '[0-9]+'); //批量操作-列表-详情
                Route::get('wallet_import/detail/{job_id}/export', [WalletImportDetailController::class, 'export'])->where('job_id', '[0-9]+'); //批量操作-列表-详情导出
                Route::post('wallet_import/execute', [WalletImportController::class, 'execute']); //批量操作-执行批量处理
                Route::get('balance_flow/types', [BalanceFlowController::class, 'types']); //子账号资金类型列表
                Route::get('balance_flow/{user_code}', [BalanceFlowController::class, 'index'])->where('user_code', '[0-9]+'); //子账号资金记录
                Route::get('users/funding_account/{user_code}', [FundingAccountController::class, 'show'])->where('user_code', '[0-9]+'); //用户资金账户信息
                Route::get('users/funding_account/bonus/{user_code}', [FundingAccountController::class, 'bonus']); //获取账户赠金信息
                Route::get('user/{user_code}', [UserInfoController::class, 'info'])->where('user_code', '[0-9]+'); //客户信息概览
                Route::get('user/{user_code}/trade_accounts', [UserInfoController::class, 'tradeAccounts'])->where('user_code', '[0-9]+'); //客户真实账户
                Route::get('user/{user_code}/credit_flows/options', [UserInfoController::class, 'creditFlowOptions'])->where('user_code', '[0-9]+'); //客户信用赠金流水选项
                Route::get('user/{user_code}/credit_flows', [UserInfoController::class, 'creditFlows'])->where('user_code', '[0-9]+'); //客户信用赠金流水
                Route::get('user/{user_code}/points', [UserPointLogController::class, 'userPoints'])->where('user_code', '[0-9]+'); //客户积分明细
                Route::put('user/profile', [UserInfoController::class, 'updateProfile']); //更新客户基础信息
                Route::put('user/transfer', [UserController::class, 'transfer']); //客户划转
                Route::put('user/down_line', [UserController::class, 'downLine']); //强制用户下线

                //积分规则
                Route::post('point_rules', [PointRuleController::class, 'store']); //新增积分规则
                Route::put('point_rules/{point_rule}', [PointRuleController::class, 'update'])->where('point_rule', '[0-9]+'); //修改积分规则
                Route::put('point_rules/{point_rule}/status', [PointRuleController::class, 'updateStatus'])->where('point_rule', '[0-9]+'); //修改规则状态
                Route::get('point_rules', [PointRuleController::class, 'index']); //积分规则列表
                Route::get('point_rules/options', [PointRuleController::class, 'options']); //积分规则选项
                Route::get('point_rules/events', [PointRuleController::class, 'events']); //积分事件选项
                //积分明细
                Route::get('point_logs', [UserPointLogController::class, 'index']); //积分明细（支持用户ID筛选）

                //优惠券
                Route::get('coupons/options', [CouponController::class, 'options']); //优惠券选项
                Route::get('coupons', [CouponController::class, 'index']); //优惠券列表
                Route::post('coupons', [CouponController::class, 'store']); //新增优惠券
                Route::put('coupons/{coupon}', [CouponController::class, 'update'])->where('coupon', '[0-9]+'); //修改优惠券
                Route::put('coupons/{coupon}/status', [CouponController::class, 'updateStatus'])->where('coupon', '[0-9]+'); //启用/停用/作废优惠券
                Route::get('user_coupons', [CouponController::class, 'userCoupons']); //优惠券核销用户记录
                Route::get('user_coupons/{user_coupon}/trade_cashback_details', [CouponController::class, 'tradeCashbackDetails'])->where('user_coupon', '[0-9]+'); //交易返现券/手续费减免券使用明细
                Route::get('user_coupons/{user_coupon}/credit_bonus_details', [CouponController::class, 'creditBonusDetails'])->where('user_coupon', '[0-9]+'); //信用赠金券详情

                //活动
                Route::get('community_activities/options', [CommunityActivityController::class, 'options']); //活动选项
                Route::get('community_activities', [CommunityActivityController::class, 'index']); //活动列表
                Route::post('community_activities', [CommunityActivityController::class, 'store']); //新增活动
                Route::put('community_activities/{community_activity}', [CommunityActivityController::class, 'update'])->where('community_activity', '[0-9]+'); //修改活动
                Route::put('community_activities/{community_activity}/void', [CommunityActivityController::class, 'voidActivity'])->where('community_activity', '[0-9]+'); //作废活动
                Route::put('community_activities/{community_activity}/publish', [CommunityActivityController::class, 'publish'])->where('community_activity', '[0-9]+'); //发布活动
                Route::get('community_activity_applications', [CommunityActivityController::class, 'applications']); //活动报名列表

                //投递规则
                Route::get('grant_rules/options', [GrantRuleController::class, 'options']); //投递规则选项
                Route::post('grant_rules/users/validate', [GrantRuleController::class, 'validateUsers']); //验证手工投递用户
                Route::get('grant_rules/users/template', [GrantRuleController::class, 'template']); //手工投递模板下载
                Route::post('grant_rules/users/import', [GrantRuleController::class, 'importUsers']); //手工投递批量导入
                Route::get('grant_rules', [GrantRuleController::class, 'index']); //投递规则列表
                Route::post('grant_rules', [GrantRuleController::class, 'store']); //新增投递规则
                Route::put('grant_rules/{grant_rule}', [GrantRuleController::class, 'update'])->where('grant_rule', '[0-9]+'); //修改投递规则
                Route::put('grant_rules/{grant_rule}/status', [GrantRuleController::class, 'updateStatus'])->where('grant_rule', '[0-9]+'); //启用/作废投递规则
                Route::get('grant_records', [GrantRuleController::class, 'records']); //投递用户记录

                //币种点差配置
                Route::get('currency_spreads', [CurrencyController::class, 'index']); //列表
                Route::post('currency_spreads', [CurrencyController::class, 'store']); //新增
                Route::put('currency_spreads', [CurrencyController::class, 'update']); //修改

                //后台同步事件
                Route::post('open_bonus_fund', [AdminEventController::class, 'openBonusFund']); //开通赠金账户事件

                //徽章
                Route::get('badges', [BadgeController::class, 'index']); //徽章列表
                Route::post('badges', [BadgeController::class, 'store']); //新增徽章
                Route::put('badges/{badge}', [BadgeController::class, 'update'])->where('badge', '[0-9]+'); //修改徽章
                Route::put('badges/{badge}/status', [BadgeController::class, 'updateStatus'])->where('badge', '[0-9]+'); //启用/禁用徽章
                Route::get('badges/events', [BadgeController::class, 'events']); //徽章事件配置

                //白名单
                Route::get('white_list', [WhiteListController::class, 'list']); //白名单列表
                Route::get('white_list/export', [WhiteListController::class, 'export']); //白名单列表导出
                Route::post('white_list', [WhiteListController::class, 'add']); //添加白名单
                Route::delete('white_list', [WhiteListController::class, 'remove']); //移除白名单

                //黑名单
                Route::get('black', [BlackListController::class, 'list']); //黑名单列表
                Route::get('black/export', [BlackListController::class, 'export']); //黑名单列表导出
                Route::post('black', [BlackListController::class, 'add']); //添加黑名单
                Route::put('black/privilege', [BlackListController::class, 'updatePrivilege']); //修改黑名单权限
                Route::delete('black/{id}', [BlackListController::class, 'delete']); //删除黑名单
                Route::get('black/privileges', [BlackListController::class, 'getPrivileges']); //获取黑名单权限

                //模版管理
                Route::get('template_layout', [TemplateLayoutController::class, 'list']); //获取layout模版列表
                Route::get('template_layout/all', [TemplateLayoutController::class, 'all']); //获取已发布layout模版
                Route::get('template_layout/{id}', [TemplateLayoutController::class, 'info']); //获取layout模版
                Route::put('template_layout', [TemplateLayoutController::class, 'update']); //修改layout模版
                Route::post('template_layout', [TemplateLayoutController::class, 'add']); //新增layout模版
                Route::delete('template_layout/{id}', [TemplateLayoutController::class, 'delete']); //删除layout模版

                Route::get('template_notice/events', [TemplateNoticeController::class, 'events']); //获取所有触发事件
                Route::get('template_notice/pages', [TemplateNoticeController::class, 'pages']); //获取内部跳转页
                Route::get('template_notice/langs', [TemplateNoticeController::class, 'langs']); //获取所有语言
                Route::post('template_notice/test', [TemplateNoticeController::class, 'test']); //测试消息模版
                Route::get('template_notice', [TemplateNoticeController::class, 'list']); //获取消息模版列表
                Route::get('template_notice/{id}', [TemplateNoticeController::class, 'info']); //获取消息模版
                Route::post('template_notice', [TemplateNoticeController::class, 'add']); //新增消息模版
                Route::put('template_notice', [TemplateNoticeController::class, 'update']); //修改消息模版
                Route::delete('template_notice/{id}', [TemplateNoticeController::class, 'delete']); //删除消息模版

                //图片上传
                Route::post('cloud_file/sign', [CloudFileController::class, 'sign']); //签名

                //订单管理
                Route::get('trades/types', [TradeController::class, 'types']); //订单筛选类型列表
                Route::get('trades', [TradeController::class, 'list']); //订单列表
                Route::get('trades/export', [TradeController::class, 'export']); //订单列表导出
                Route::get('pending_trades', [TradeController::class, 'pendingList']); //挂单列表
                Route::get('pending_trades/export', [TradeController::class, 'exportPending']); //挂单列表导出

                //数据概览
                Route::get('data_overview/chart_date', [DataOverviewChartController::class, 'index']); //数据概览单个图表
                Route::get('data_overview', [DataOverviewController::class, 'index']); //数据概览

                //source 品种相关
                Route::get('sources', [SourceController::class, 'sources']); //全部品种名称
                Route::get('source/list', [SourceController::class, 'index']); //品种列表
                Route::post('source/update', [SourceController::class, 'update']); //设置品种
                Route::get('source/types', [SourceController::class, 'types']); //品类

                //高手/跟单
                Route::prefix('copytrading')->group(function () {
                    Route::get('master_levels', [CopyTradingController::class, 'masterLevels']); //高手等级配置列表
                    Route::get('master_levels/options', [CopyTradingController::class, 'masterLevelOptions']); //高手等级配置选项
                    Route::get('master_applications', [CopyTradingController::class, 'masterApplications']); //高手申请列表
                    Route::get('master_applications/export', [CopyTradingController::class, 'exportMasterApplications']); //高手申请列表导出
                    Route::get('master_applications/options', [CopyTradingController::class, 'masterApplicationOptions']); //高手申请筛选项
                    Route::post('master_applications/{id}/review', [CopyTradingController::class, 'reviewMasterApplication'])->where('id', '[0-9]+'); //审核高手申请
                    Route::post('master_levels', [CopyTradingController::class, 'createMasterLevel']); //新增高手等级
                    Route::put('master_levels/{level_id}', [CopyTradingController::class, 'updateMasterLevel'])->where('level_id', '[0-9]+'); //修改高手等级
                    Route::delete('master_levels/{level_id}', [CopyTradingController::class, 'deleteMasterLevel'])->where('level_id', '[0-9]+'); //逻辑删除高手等级

                    Route::get('masters', [CopyTradingController::class, 'masters']); //高手管理列表
                    Route::get('masters/export', [CopyTradingController::class, 'exportMasters']); //高手管理导出
                    Route::get('potential-masters', [CopyTradingController::class, 'potentialMasters']); //潜在高手列表
                    Route::get('potential-masters/export', [CopyTradingController::class, 'exportPotentialMasters']); //潜在高手导出
                    Route::post('potential-masters/{trade_account_id}/change-status', [CopyTradingController::class, 'changePotentialMasterStatus'])->where('trade_account_id', '[0-9]+'); //潜在高手操作（公开高手/设为私密）
                    Route::post('masters/{trade_account_id}/change-status', [CopyTradingController::class, 'changeStatus'])->where('trade_account_id', '[0-9]+'); //高手管理操作（设为公开/设为私密/移除高手）
                    Route::put('masters/protection-time', [CopyTradingController::class, 'updateMasterProtectionTime']); //修改高手等级保护时间

                    Route::get('follow_relations', [CopyTradingController::class, 'followRelations']); //跟随关系列表
                    Route::get('follow_relations/export', [CopyTradingController::class, 'exportFollowRelations']); //跟随关系导出
                    Route::get('customers/{user_code}/followed_masters/current', [CopyTradingController::class, 'customerCurrentFollowedMasters'])->where('user_code', '[0-9]+'); //客户详情-当前跟随高手
                    Route::get('customers/{user_code}/followed_masters/history', [CopyTradingController::class, 'customerHistoryFollowedMasters'])->where('user_code', '[0-9]+'); //客户详情-历史跟随高手
                    Route::get('masters/{master_user_code}/followers/current', [CopyTradingController::class, 'masterCurrentFollowers'])->where('master_user_code', '[0-9]+'); //客户详情-当前投资人
                    Route::get('masters/{master_user_code}/followers/history', [CopyTradingController::class, 'masterHistoryFollowers'])->where('master_user_code', '[0-9]+'); //客户详情-历史投资人

                    Route::get('commission_records', [CopyTradingController::class, 'commissionRecords']); //分润结算列表
                    Route::get('commission_records/export', [CopyTradingController::class, 'exportCommissionRecords']); //分润结算导出
                });

                //客户端版本配置
                Route::get('app_releases', [AppReleaseController::class, 'index']); //版本列表
                Route::get('app_releases/platform_options', [AppReleaseController::class, 'platformOptions']); //平台选项
                Route::post('app_releases', [AppReleaseController::class, 'store']); //新增版本
                Route::put('app_releases/{id}', [AppReleaseController::class, 'update'])->where('id', '[0-9]+'); //修改版本

            }
        );
    });
