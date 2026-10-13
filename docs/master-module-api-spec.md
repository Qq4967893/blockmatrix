# 高手模块接口梳理（前端字段 + 用表 + 逻辑 + 待确认问题）

> 范围：仅高手后台原型相关功能。  
> 依据：`storage/logs/master/后台原型图`、`storage/logs/master/Database Definitions.docx`。  
> Mock 风格：严格按当前项目风格，使用 `snake_case`、列表接口使用 `data + total_count`、分页使用 `limit + offset`、数字状态值 + `_text` 文案。  
> 说明：以下 Mock 展示的是 controller `succeed($result)` 里的业务数据结构，和当前项目 Scramble 示例保持一致，不额外包 `code/message`。
> 路由前缀：当前项目后台接口统一挂在 `/api` 下，通过 `auth + permission` 中间件控制后台权限，不额外加 `/admin`。

## 接口精简原则

- 编辑回显优先使用列表行数据，避免额外详情接口。
- 导出使用独立导出接口，不复用列表接口。
- 客户详情中“当前/历史”两个表格合并到同一个接口返回，减少页面请求数。
- 删除校验并入删除接口，失败时直接返回错误文案。

---

## 状态枚举约定

| 场景 | 字段 | 值 |
|---|---|---|
| 高手状态 | status | 0=申请中，1=成功，2=拒绝，3=下榜，4=隐藏 |
| 跟随状态 | follow_status | 0=跟随中，1=已结束 |
| 分润状态 | settlement_status | 0=待结算，1=已分润 |
| 通用布尔 | is_xxx/can_xxx | 0=否，1=是 |

### 跟随状态映射规则（数据库到接口）
- 交易端 `copy_history.status=0`：接口状态返回 `0=跟随中`。
- 交易端 `copy_history.status=1`：接口状态返回 `1=已结束`。
- `copy_history.op_time` 按 `status` 解释：`status=0` 不返回结束时间，`status=1` 作为投资结束时间。

---

## 1. 高手等级配置（查询/新增/修改/删除）- Edgar

### 接口
- GET `/api/copytrading/master_levels`
- POST `/api/copytrading/master_levels`
- PUT `/api/copytrading/master_levels/{level_id}`
- DELETE `/api/copytrading/master_levels/{level_id}`

### 查询参数
| 参数 | 含义 |
|---|---|
| level_pos | 高手等级排序 |
| name | 等级名称 |
| is_initial | 是否初始默认等级，1是/0否 |
| is_default_level | 是否默认等级（固定为第一级），1是/0否 |
| limit | 每页条数，默认20 |
| offset | 分页偏移量，默认0 |

### 前端字段
| 字段 | 含义 |
|---|---|
| id | 等级ID |
| level_code | 高手等级，如 `Level1` |
| level_pos | 等级排序值，数值越大等级越高 |
| is_initial | 是否初始默认等级，1是/0否 |
| is_initial_text | 是否初始默认等级文案，是/否 |
| is_default_level | 是否默认等级（固定为第一级），1是/0否 |
| is_default_level_text | 是否默认等级文案，是/否 |
| name | 当前登录语言下的等级名称 |
| locale_configs | 多语言配置，格式 `[{lang,name}]` |
| balance_threshold | 余额门槛（可选，未传为 null） |
| order_count_30d_threshold | 近30天交易次数门槛（可选，未传为 null） |
| profit_rate_30d_threshold | 近30天收益率门槛（可选，未传为 null） |
| follower_fund_scale_threshold | 投资规模门槛（可选，未传为 null） |
| follower_count_threshold | 投资人数门槛（可选，未传为 null） |
| commission_rate | 分润比例 |
| max_investable_amount | 可投资金额 |
| created_at | 创建时间 |
| updated_at | 修改时间 |

### Mock
```json
{
  "data": [
    {
      "id": 1,
      "level_code": "Level1",
      "level_pos": 1,
      "is_initial": 1,
      "is_initial_text": "是",
      "is_default_level": 1,
      "is_default_level_text": "是",
      "name": "黄金",
      "locale_configs": [
        { "lang": "zh_CN", "name": "黄金" },
        { "lang": "en_US", "name": "Gold" }
      ],
      "balance_threshold": "5000.000000",
      "order_count_30d_threshold": 50,
      "profit_rate_30d_threshold": "0.200000",
      "follower_fund_scale_threshold": "10000.000000",
      "follower_count_threshold": 10,
      "commission_rate": "0.200000",
      "max_investable_amount": "200000.000000",
      "created_at": "2026-03-26 23:55:48",
      "updated_at": "2026-03-26 23:55:48"
    }
  ],
  "total_count": 1
}
```

### 用到的表
- `master_level`
- `master_account`：删除等级前校验是否被高手使用

### 实现逻辑
- 查询支持 `level_pos`、`name`、`is_initial`、`is_default_level`、`limit`、`offset`。
- 新增/修改时校验等级条件递增关系。
- 新增/修改时 `is_initial` 非必填；传 `1` 时校验当前仅存在一条初始默认等级。
- `max_investable_amount` 必须大于等于当前等级投资规模门槛。
- 编辑回显直接使用列表行数据，不单独提供详情接口。

### 默认规则
- 默认等级固定为第一级（`level_pos = 1`），接口派生返回 `is_default_level`，不可配置。
- 初始默认等级由 `master_level.is_initial` 配置（新增/修改时传 `is_initial`）；同一时间只允许一条记录为 `1`。
- 若新增/修改时已存在初始默认等级，报错：“已存在初始默认等级：等级Levelxxx。”
- `GET /api/copytrading/master_levels/options` 返回 `is_initial_options`，格式为 `[{key,value}]`，key 为 `1/0`，value 为 `是/否`。
- 删除等级默认走逻辑禁用，即更新 `master_level.status=0`，避免历史高手和分润记录断链。
- 已有高手使用该等级时允许修改门槛和分润比例，修改后只影响后续等级评估和后续分润快照。
- 分润比例接口统一用小数，`0.200000` 表示 20%，前端展示时再转成百分比。

---

## 2. 高手管理（查询/状态展示/手动下榜）- Edgar

### 接口
- GET `/api/copytrading/masters`
- GET `/api/copytrading/masters/export`
- POST `/api/copytrading/masters/{master_user_code}/delist`

### 查询参数
| 参数 | 含义 |
|---|---|
| master_user_code | 高手ID |
| level_pos | 高手等级 |
| trade_account_type | 账号类型：0统一交易账号，3赠金账户；不传默认展示 0/3 |
| status | 状态：0申请中，1成功，2拒绝，3下榜，4隐藏 |
| limit | 每页条数，默认20 |
| offset | 分页偏移量，默认0 |

### 前端字段
| 字段 | 含义 |
|---|---|
| master_user_code | 高手ID |
| trade_account_type | 账号类型 |
| trade_account_type_name | 账号类型文案 |
| level_code | 高手等级，如 `Level1` |
| level_pos | 等级排序值 |
| account_balance | 余额 |
| profit_rate_30d | 近30天收益率 |
| fund_scale | 投资规模 |
| follower_count | 投资人数 |
| commission_amount | 已获得分润金额 |
| commission_rate | 分润比例 |
| current_hidden_days | 当前隐藏天数 |
| level_protection_started_at | 等级保护开始时间 |
| level_protection_ended_at | 等级保护截止时间 |
| protection_status | 保护状态（0未生效，1保护中，2已结束） |
| status | 状态值 |
| status_text | 状态文案 |

### Mock
```json
{
  "data": [
    {
      "master_user_code": 20192,
      "trade_account_type": 0,
      "trade_account_type_name": "统一交易账号",
      "level_code": "Level1",
      "level_pos": 1,
      "account_balance": "28600.000000",
      "profit_rate_30d": "0.100000",
      "fund_scale": "50.000000",
      "follower_count": 68,
      "commission_amount": "50.000000",
      "commission_rate": "0.100000",
      "current_hidden_days": 10,
      "status": 4,
      "status_text": "已隐藏"
    }
  ],
  "total_count": 1
}
```

### 用到的表
- `master_account`
- `master_level`
- `master_status_change_log`

### 实现逻辑
- 支持高手ID、高手等级、状态筛选。
- 导出走独立接口 `/masters/export`。
- 手动下榜更新 `master_account.status`，并写 `master_status_change_log`。
- 下榜后禁止新增跟随，已有跟随关系不强制平仓。

### 默认规则
- 手动下榜允许从正常/已隐藏直接变为已下榜。
- 下榜后仍在后台列表展示，状态显示“已下榜”。
- `current_hidden_days` 从最近一次变更为已隐藏的 `status_updated_at` 开始计算。
- 导出使用独立接口，默认同步导出，格式沿用项目现有 `export_ext`。

---

## 3. 跟随关系管理（查询/状态展示/导出）- Tyson

### 接口
- GET `/api/copytrading/follow_relations`
- GET `/api/copytrading/follow_relations/export`

### 查询参数
| 参数 | 含义 |
|---|---|
| master_user_code | 高手ID |
| master_trade_account_id | 高手交易账号ID |
| master_trade_account_type | 高手交易账号类型 |
| follower_user_code | 跟单者ID |
| follow_status | 跟随状态：0跟随中，1已结束 |
| copy_start_time_start | 复制开始时间起 |
| copy_start_time_end | 复制开始时间止 |
| copy_end_time_start | 复制结束时间起 |
| copy_end_time_end | 复制结束时间止 |
| limit | 每页条数，默认20 |
| offset | 分页偏移量，默认0 |

### 前端字段
| 字段 | 含义 |
|---|---|
| master_user_code | 高手ID |
| follower_user_code | 跟单者ID |
| master_trade_account_id | 高手交易账号ID |
| follower_trade_account_id | 跟单者交易账号ID |
| master_trade_account_type_name | 高手交易账号类型 |
| follower_trade_account_type_name | 跟单者交易账号类型 |
| invest_amount | 跟随金额 |
| current_invest_amount | 跟随金额（含盈亏） |
| level_code | 高手等级 |
| copy_start_time | 复制开始时间 |
| copy_end_time | 复制结束时间 |
| follow_status | 跟随状态 |
| follow_status_text | 跟随状态文案 |

### Mock
```json
{
  "data": [
    {
      "master_user_code": 20192,
      "follower_user_code": 1,
      "master_trade_account_id": 880001,
      "follower_trade_account_id": 880002,
      "master_trade_account_type_name": "统一交易账号",
      "follower_trade_account_type_name": "赠金账户",
      "invest_amount": "50.000000",
      "current_invest_amount": "55.000000",
      "level_code": "Level1",
      "copy_start_time": "2026-03-26 23:55:48",
      "copy_end_time": null,
      "follow_status": 0,
      "follow_status_text": "跟随中"
    }
  ],
  "total_count": 1
}
```

### 用到的表
- `copy`
- `copy_history`
- `master_account`

### 实现逻辑
- 当前跟随关系读取 `copy`。
- 历史关系读取 `copy_history`。
- 支持高手ID、跟单者ID、状态、复制开始时间、复制结束时间筛选。
- 导出走独立接口 `/follow_relations/export`。

### 默认规则
- 跟随金额 `invest_amount` 展示建立关系时的初始跟随金额。
- 跟随金额（含盈亏）`current_invest_amount`：跟随中取 grpc 当前净值；已结束取 `copy_history.amount - balance_flow` 净入金。
- 导出与列表使用同一查询条件和同一状态文案。
- `copy_history.op_time` 直接作为开始/结束时间来源，不再额外补 `copy_start_time/copy_end_time`。

---

## 4. 客户详情-跟随的高手（当前投资/历史投资）- Tyson

### 接口
- GET `/api/copytrading/customers/{user_code}/followed_masters`

### 前端字段
| 字段 | 含义 |
|---|---|
| master_nickname | 高手昵称 |
| invest_current_amount | 投资当前资金 |
| invest_initial_amount | 投资初始资金 |
| invest_end_amount | 投资结束资金，仅历史展示 |
| invest_start_time | 投资开始时间 |
| invest_end_time | 投资结束时间，仅历史展示 |
| yield_rate | 收益率，仅历史展示 |

### Mock
```json
{
  "current": {
    "data": [
      {
        "master_nickname": "侯森布鲁滋老爷",
        "invest_current_amount": "200.000000",
        "invest_initial_amount": "200.000000",
        "invest_start_time": "2026-03-27 16:42:51"
      }
    ],
    "total_count": 1
  },
  "history": {
    "data": [
      {
        "master_nickname": "Mcl",
        "invest_end_amount": "200.000000",
        "invest_initial_amount": "200.000000",
        "invest_start_time": "2025-08-26 11:39:23",
        "invest_end_time": "2025-08-27 23:56:25",
        "yield_rate": "0.000000"
      }
    ],
    "total_count": 1
  }
}
```

### 用到的表
- `copy`
- `copy_history`
- `master_account`
- 资金流水或交易账户数据：用于当前资金、结束资金、收益率

### 实现逻辑
- 当前投资展示用户仍在跟随的高手。
- 历史投资展示已结束的跟随关系。
- 当前投资和历史投资由同一个接口一次返回，减少客户详情页请求数。
- 收益率建议统一按 `(投资结束资金 - 投资初始资金) / 投资初始资金` 计算。
- 初始资金为 0 时 `yield_rate` 返回 `null`。
- 金额口径优先使用 `copy.amount` 作为当前进行中金额，`copy_history.amount` 作为历史变更金额参考。
- `copy_history.op_time` 作为投资开始时间或结束时间的唯一来源，避免额外时间字段。

### 默认规则
- 高手昵称从现有用户基础信息来源补齐，不在高手模块重复存储。
- 当前投资默认展示跟单账户当前资金。
- 投资结束资金默认展示取消跟单完成后回划 Funding 前的跟单账户结余。
- 历史收益率按最终结余资金计算，即默认已扣除手续费和分润。

---

## 5. 客户详情-投资人（当前投资人/历史投资人）- Tyson

### 接口
- GET `/api/copytrading/customers/{master_user_code}/followers`

### 前端字段
| 字段 | 含义 |
|---|---|
| follower_nickname | 投资人昵称 |
| invest_current_amount | 投资当前资金，仅当前投资人展示 |
| invest_initial_amount | 投资初始资金 |
| invest_end_amount | 投资结束资金，仅历史投资人展示 |
| invest_start_time | 投资开始时间 |
| invest_end_time | 投资结束时间，仅历史投资人展示 |

### Mock
```json
{
  "current": {
    "data": [
      {
        "follower_nickname": "侯森布鲁滋老爷",
        "invest_current_amount": "200.000000",
        "invest_initial_amount": "200.000000",
        "invest_start_time": "2026-03-27 16:42:51"
      }
    ],
    "total_count": 1
  },
  "history": {
    "data": [
      {
        "follower_nickname": "Mcl",
        "invest_end_amount": "200.000000",
        "invest_initial_amount": "200.000000",
        "invest_start_time": "2025-08-26 11:39:23",
        "invest_end_time": "2025-08-27 23:56:25"
      }
    ],
    "total_count": 1
  }
}
```

### 用到的表
- `copy`
- `copy_history`
- 用户基础信息表：用于投资人昵称
- 资金流水或交易账户数据：用于当前资金、结束资金

### 实现逻辑
- 当前投资人展示仍在跟随该高手的投资人。
- 历史投资人展示曾经跟随但已结束的投资人。
- 当前投资人和历史投资人由同一个接口一次返回，减少客户详情页请求数。
- 同一投资人多次跟随同一高手，需要按多段关系展示多条记录。
- 金额口径优先使用 `copy.amount` 和 `copy_history.amount`：前端显示的投资金额、变更前金额、结余金额优先从这两张表取快照，避免只靠资金流水反推。
- `copy_history.op_time` 作为当前/历史投资时间的唯一来源，`status=0` 表示开始时间，`status=1` 表示结束时间。

### 默认规则
- 投资人昵称从现有用户基础信息来源补齐，不在高手模块重复存储。
- 当前投资人列表展示“跟随中”，历史投资人列表只展示“已结束”。
- 历史投资人不展示收益率，按当前原型字段实现。
- 同一投资人重复跟随同一高手时按投资开始时间倒序展示。

---

## 6. 分润结算（查询/状态展示/导出）- Edgar

### 接口
- GET `/api/copytrading/commission_records`
- GET `/api/copytrading/commission_records/export`

### 查询参数
| 参数 | 含义 |
|---|---|
| ticket | 订单ID |
| master_user_code | 高手ID |
| follower_user_code | 跟单者ID |
| level_pos | 高手等级 |
| close_time_start | 订单平仓开始时间 |
| close_time_end | 订单平仓结束时间 |
| settled_at_start | 分润开始时间 |
| settled_at_end | 分润结束时间 |
| settlement_status | 结算状态：0待结算，1已分润 |
| limit | 每页条数，默认20 |
| offset | 分页偏移量，默认0 |

### 前端字段
| 字段 | 含义 |
|---|---|
| ticket | 订单ID，建议使用跟单订单 `follower_ticket` |
| follower_user_code | 跟单者ID |
| master_user_code | 高手ID |
| profit_amount | 本次盈利 |
| commission_amount | 分润金额 |
| commission_rate | 分润比例 |
| level_code | 分润时高手等级 |
| close_time | 订单平仓时间 |
| settled_at | 分润时间 |
| settlement_status | 分润状态 |
| settlement_status_text | 分润状态文案 |

### Mock
```json
{
  "data": [
    {
      "ticket": "243497832021",
      "follower_user_code": 1,
      "master_user_code": 20192,
      "profit_amount": "50.000000",
      "commission_amount": "5.000000",
      "commission_rate": "0.100000",
      "level_code": "Level1",
      "close_time": "2026-03-26 23:55:48",
      "settled_at": "2026-03-26 23:55:48",
      "settlement_status": 1,
      "settlement_status_text": "已分润"
    }
  ],
  "total_count": 1
}
```

### 用到的表
- `master_commission_record`
- `copy_order`

### 实现逻辑
- 支持订单ID、高手ID、跟单者ID、高手等级、订单平仓时间、分润时间筛选。
- 同一 `follower_ticket` 唯一，避免重复分润。
- 订单平仓时间从 `master_commission_record.copy_order_info` 中解析。
- 订单平仓判定规则：同一 `login_master + login_follower` 维度下，`copy_order.status=0` 视为未全部平仓，`copy_order.status=1` 视为已平仓。
- 分润比例使用结算时等级快照，不随高手后续等级变化改变。
- 导出走独立接口 `/commission_records/export`。

### 默认规则
- `ticket` 前端展示跟单单 `follower_ticket`。
- 分润状态按现有表设计只做待结算/已分润。
- `close_time` 从 `copy_order_info` 读取，不新增 `order_close_time` 字段。
- 分润比例使用结算时快照，不随高手后续等级变化改变。
- `profit_amount` 按产品文档口径理解为参与分润的绝对盈利金额。

### 实现风险说明
- 当前 `close_time_start/close_time_end` 依赖从 `copy_order_info` 解析平仓时间进行筛选。
- 风险：大数据量下 JSON 解析筛选会带来查询性能下降，并且可能出现解析失败导致的筛选口径不一致。
- 建议：后续增加冗余字段（如 `close_time`）并建立索引，列表与导出统一按冗余字段筛选。

---

## 7. 删除确认与规则校验（等级删除）- Edgar

### 接口复用
- 复用 `DELETE /api/copytrading/master_levels/{level_id}`，不新增删除校验接口。

### 返回规则
- 成功：返回空数据，沿用当前项目 `succeed()`。
- 失败：直接返回错误文案，例如“该等级已被使用，无法删除”。
- 语义：`DELETE /master_levels/{level_id}` 为逻辑删除，不做物理删除。

### 用到的表
- `master_level`
- `master_account`

### 实现逻辑
- 删除前统计 `master_account.level_id` 引用数量。
- 有引用时直接拦截删除并返回错误文案，不单独提供删除校验接口。
- 无引用时允许删除或禁用等级。

### 默认规则
- 删除接口按逻辑禁用处理，更新 `master_level.status=0`。
- 历史分润记录使用 `level_pos` 快照展示，不依赖等级表当前状态。
- 删除弹窗文案按引用情况动态展示：无引用展示确认删除，有引用展示不可删除原因。

---

## 8. 下榜确认与状态流转（正常/已隐藏/已下榜）- Edgar

### 接口复用
- 复用 `POST /api/copytrading/masters/{master_user_code}/delist`，不新增状态流转接口。

### 前端字段
| 字段 | 含义 |
|---|---|
| master_user_code | 高手ID |
| before_status | 变更前状态 |
| before_status_text | 变更前状态文案 |
| after_status | 变更后状态 |
| after_status_text | 变更后状态文案 |
| status_updated_at | 状态更新时间 |

### Mock
```json
{
  "master_user_code": 20192,
  "before_status": 4,
  "before_status_text": "已隐藏",
  "after_status": 3,
  "after_status_text": "已下榜",
  "status_updated_at": "2026-04-28 17:12:21"
}
```

### 用到的表
- `master_account`
- `master_status_change_log`

### 实现逻辑
- 下榜操作需要事务：更新高手状态 + 写状态变更日志。
- 重复下榜返回成功，避免重复写脏日志。
- 下榜后禁止新增投资关系。

### 默认规则
- 手动下榜原因非必填，传了则写入 `master_status_change_log.remark`。
- 下榜后的高手重新满足条件时，按产品文档重新走系统邀请，不提供后台直接恢复。

### 需确认问题
- 下榜后是否由交易端同步取消已有跟随关系，并将后台状态落为“已结束”。产品文档写“取消已有投资关系、不强制平仓、只跟随平仓”，这里需要和交易端确认落库方式。

---

## 9. 联调与测试（前后端接口联调、Bug修复、SQL优化）- Edgar、Tyson

### 检查项
- 列表与导出筛选口径一致。
- 金额、比例、收益率均为字符串。
- 字段命名统一使用 `snake_case`。
- 状态枚举统一使用数字值 + `_text` 文案。
- 时间筛选边界统一为闭区间还是半开区间。
- SQL 索引覆盖常用筛选字段。

### 默认规则
- 分页默认 `limit=20`、`offset=0`，沿用当前项目风格。
- 导出默认同步导出，参数沿用 `export_ext`。
- 关键写操作写后台操作日志：等级新增/修改/删除、手动下榜。

---

## 10. 联调与测试-跟随关系业务逻辑验证 - Tyson

### 检查项
- 跟随关系创建、取消、已结束的状态展示。
- 客户详情当前/历史数据互斥。
- 下榜后新增跟随限制。

### 默认规则
- 原排期里的“代理业务逻辑验证”统一改名为“跟随关系业务逻辑验证”，避免和代理模块混淆。

---

## 11. 联调与测试-佣金计算逻辑验证 - Edgar

### 检查项
- 分润比例取等级快照。
- 同一跟单订单不能重复分润。
- 分润金额与盈利金额、比例可复算。
- 订单平仓时间和分润时间展示正确。

### 默认规则
- 分润只针对绝对盈利部分，按产品文档执行。
- 分润失败状态本期不扩展，仍按现有表设计只保留待结算/已分润。

---

## 数据库满足度简结论

- 满足：高手等级配置、高手管理、分润记录、等级删除校验、下榜日志。
- 部分满足：跟随关系管理、客户详情-跟随的高手、客户详情-投资人。
- 主要缺口：`copy_history` 缺结构化开始/结束时间；客户详情缺稳定的当前资金、结束资金、收益率来源。

---

## 已落地 Mock 路由（2026-04-28）

统一前缀：`/api/copytrading`

- `GET /master_levels`
- `POST /master_levels`
- `PUT /master_levels/{level_id}`
- `DELETE /master_levels/{level_id}`（逻辑删除）
- `GET /masters`
- `GET /masters/export`
- `POST /masters/{master_user_code}/delist`
- `GET /potential-masters`
- `GET /potential-masters/export`
- `POST /potential-masters/{user_code}/change-status`
- `GET /follow_relations`
- `GET /follow_relations/export`
- `GET /customers/{user_code}/followed_masters`
- `GET /masters/{master_user_code}/followers`
- `GET /commission_records`
- `GET /commission_records/export`

说明：
- 已按“列表接口 + 独立导出接口”拆分，未复用 `export` 查询参数。
- 返回风格保持现有项目风格：`snake_case`、分页 `data + total_count`。
