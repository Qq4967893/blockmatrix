# MX Blockmatrix Backend 项目文档

## 1. 项目概述

### 1.1 基本信息

| 项目项 | 内容 |
|---|---|
| 项目名称 | MX Blockmatrix |
| 项目版本 | `v1.0.0` |
| 交接日期 | `2026-04-13` |
| 交接人 | 待补充 |
| 所属团队 | 应用开发组 |
| 代码仓库 | `git@github.com:Global-MongoX/backend-blockmatrix.git` |
| 默认分支 | `main` |
| 发布分支 | `main` |

### 1.2 项目背景

**项目定位：**
面向 Blockmatrix 业务的后台管理服务，统一承载权限、用户/IB、资金审核、赛事配置、运营配置等后台能力。

**核心价值：**
- 降低多系统分散操作成本
- 统一权限治理与菜单访问控制
- 标准化资金审核链路与异常回溯
- 支撑活动运营与风控名单管理

**当前阶段：**
维护迭代中。

### 1.3 项目范围

#### 已实现功能

| 模块 | 功能说明 |
|---|---|
| 后台权限 | 登录、角色、菜单、管理员管理 |
| 用户/IB | IB 申请、IB 账户树、等级、佣金规则、变更日志 |
| 资金管理 | 入金/出金审核、资金流水、账户信息 |
| 交易/赛事 | 交易数据查询、资格赛/正式赛配置与管理 |
| 运营能力 | 积分规则、徽章、消息模板、黑白名单、素材上传 |

#### 上下游系统

| 系统 | 角色 | 说明 |
|---|---|---|
| 后台管理前端 | 上游 | 统一调用 `/api/*` |
| MySQL 多库 | 下游 | `blockmatrix / api / trading_system_user` |
| Redis | 下游 | 会话、缓存、验证码等 |
| TradeSystem (TS/ET6) | 下游 | 交易数据查询 |
| Flux / Funding / Payment | 下游 | 资金审核、通道编排 |
| S3 对象存储 | 下游 | 文件签名与回显 |

### 1.4 使用对象

| 对象 | 说明 |
|---|---|
| 内部用户 | 运营、风控、财务、客服、合规、技术支持 |
| 外部用户 | 终端客户与 IB 不直接访问本服务 |
| 客户端类型 | 本服务定位为内部后台 API，不开放终端直连 |

---

## 2. 技术架构

### 2.1 技术栈

| 类别 | 技术 | 说明 |
|---|---|---|
| 语言 | PHP 8.4 | 运行时版本要求 |
| 框架 | Laravel `v13.4.0` | 基于 `composer.lock` |
| 数据库 | MySQL | 多连接 + 读写分离 + sticky |
| 缓存 | Redis (phpredis) | 会话、缓存、临时数据 |
| 队列 | Laravel Queue | 默认 Redis，可扩展其他连接 |
| 文档 | `dedoc/scramble` | 文档 UI 默认暴露 `/api/docs` |
| 基础组件 | `shimmer/scaffold` | Response、TS、Funding、S3 等封装 |

### 2.2 架构说明

**架构类型：** 单体后端 + 前后端分离。

**分层模型：**
`Middleware -> Controller -> Service -> Repository/Model`

**业务域划分：**

| 业务域 | 责任 |
|---|---|
| Blockmatrix | 登录、角色、菜单、管理员、上传 |
| User & IB | 用户、代理、层级关系、佣金规则 |
| Funds | 入金/出金审核、流水、账户 |
| Contest | 资格赛/正式赛规则与管理 |
| Ops | 积分、徽章、模板、名单、消息 |
| Trade | 订单/挂单/交易品种相关查询 |

### 2.3 架构逻辑

1. **接入层**：管理前端通过 `/api` 访问后端。
2. **API 入口层**：统一经过 `api + throttle:global + auth + permission`。
3. **应用层（Laravel）**：请求按 `Controller -> Service -> Repository` 处理。
4. **数据层**：访问 `MySQL(blockmatrix/api/trading_system_user)` 与 `Redis`。
5. **平台组件层**：通过 `shimmer/scaffold` 与队列消费者承接通用能力。
6. **外部依赖层**：对接 `TS / Flux / Funding / Payment / S3 / 邮件告警`。

**主调用链：**
`Client -> /api -> auth/permission/throttle -> Controller -> Service -> Repository -> DB/Facade -> JSON`

### 2.4 调用关系

```text
/api Route
  -> Middleware (api, throttle:global, auth, permission)
  -> Controller
  -> Service
  -> Repository / Model
  -> MySQL / Redis
  -> TS / Flux / Funding / Payment / S3
  -> Unified JSON Response
```

### 2.5 关键设计说明

| 设计点 | 说明 |
|---|---|
| 可用性 | DB 读写分离 + sticky，Redis 外置 |
| 容错 | 全局异常处理 + 统一 JSON 输出 |
| 限流 | `RateLimiter::for('global')` 每分钟 100 次 |
| 安全 | Session 鉴权 + Permission 菜单权限 + 验证码 |
| 告警 | 关键异常触发邮件 `EmailReportError::sendExceptionEmail` |

---

## 3. 构建与发布

### 3.1 构建命令

```bash

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan view:cache
```

> 说明：当前路由文件存在 Closure 路由（如 `/`），生产不建议直接执行 `php artisan route:cache`。

### 3.2 发布流程

1. 从 `main` 拉取目标提交。
2. 更新 `.env` 与环境密钥。
3. 安装依赖并执行迁移。
4. 重启 Web 与常驻消费者进程。
5. 冒烟验证：登录、权限、资金核心接口。

### 3.3 回滚流程

1. 回退到上一稳定提交。
2. 重新安装对应依赖。
3. 清理并重建配置缓存。
4. 重启服务并验证核心链路。

### 3.4 发布注意事项

- `storage/`、日志目录必须可写。
- 发布前确认 MySQL、Redis、TS/Flux/Payment/S3 网络可达。
- 资金与 IB 关键流程需单独回归验证。

---

## 4. 配置说明

### 4.1 配置文件

| 文件 | 路径 | 说明 |
|---|---|---|
| `.env` | 项目根目录 | 运行时环境配置 |
| `.env.example` | 项目根目录 | 配置模板 |
| `config/*.php` | `config/` | 应用、数据库、队列、第三方配置 |

### 4.2 核心配置项

| 配置项 | 用途 | 敏感 |
|---|---|---|
| `DB_CONNECTION` / `MYSQL_*` | MySQL 多连接读写配置 | 是 |
| `REDIS_*` | Redis 连接 | 是 |
| `FLUX_GRPC_HOST` | Flux 下游连接 | 否 |
| `MEPAY_CALL_BACK` | 支付回调地址 | 否 |
| `AWS_*` | S3 对象存储 | 是 |
| `API_VERSION` | 接口版本标识 | 否 |
| `NO_CAPTCHA` | 验证码开关控制 | 否 |

### 4.3 配置治理要求

- 敏感配置仅存放在环境侧，不入库。
- 生产变更建议审批 + 双人复核。
- 配置变更后需重启相关常驻进程。

---

## 5. 数据库文档

### 5.1 数据库信息

| 项目 | 内容 |
|---|---|
| 数据库类型 | MySQL |
| 版本参考 | 8.0.x（schema dump 显示 8.0.45） |
| 核心实例 | `blockmatrix`、`api`、`trading_system_user` |
| 连接模式 | 读写分离 + sticky |

### 5.2 核心表与结构来源

- 结构变更来源：`database/migrations`
- 结构快照参考：`database/schema/blockmatrix-schema.sql`

| 领域 | 核心表 |
|---|---|
| 后台权限 | `admin_user`、`role`、`menu`、`role_menu_rel` |
| 用户/IB | `ib_account`、`ib_level`、`ib_application` |
| 资金 | `funding_apply`、`funding_ledger` |
| 运营/消息 | `template_notice` |
| 审计 | `user_operation_log` |

### 5.3 数据关系说明

- 权限模型：管理员 -> 角色 -> 菜单。
- 代理模型：用户与 IB 形成树状层级（`parent_id / ib_path / depth`）。
- 资金模型：申请单与流水按业务单号关联。
- 约束策略：以应用层约束为主，数据库外键依赖较弱。

### 5.4 数据风险

| 风险点 | 说明 |
|---|---|
| 大表增长 | `funding_*`、`ib_*`、`user_operation_log` |
| 慢查询 | 多条件筛选 + 多表关联 |
| 历史兼容 | 存在 `Schema::hasTable/hasColumn` 动态兼容 |

---

## 6. API 接口文档

### 6.1 接口分类

| 分类 | 说明 |
|---|---|
| 前端接口 | 统一 `/api/*`，当前路由约 150+ |
| 文档入口 | `/api/docs`（可由网关映射 `/api/doc`） |
| 内部服务接口 | Service 层调用 TS/Flux/Funding/Payment/S3 |
| 回调接口 | 资金回调通过 `MEPAY_CALL_BACK` 管理 |
| 定时任务 | 未发现 Scheduler，异步以队列消费者为主 |

### 6.2 基础约定

- 请求方式：`GET / POST / PUT / DELETE`
- 认证方式：Session + `auth` + `permission`
- 限流策略：`throttle:global`（100/min）

```http
Content-Type: application/json
Accept: application/json
Cookie: <laravel_session=...>
X-CSRF-TOKEN: <token>
```

### 6.3 请求参数示例

```http
GET /api/ib_accounts?user_code=1000001&limit=20&offset=0
GET /api/ib_accounts/{ib_id}/children
```

```http
POST /api/ib_accounts
```

```json
{"user_code": 1000001}
```

```http
X-CSRF-TOKEN: <token>
```

### 6.4 返回结构示例

```json
{
  "is_succ": true,
  "message": "",
  "code": 0,
  "request_id": "...",
  "data": {}
}
```

```json
{
  "is_succ": false,
  "message": "参数错误",
  "code": 422,
  "request_id": "..."
}
```

### 6.5 错误码说明

| 错误码 | 含义 | 典型场景 |
|---|---|---|
| `0` | 成功 | 正常处理完成 |
| `422` | 参数校验失败 | Request 规则不通过 |
| `10000` | 未登录 | `Authenticate` 中间件拦截 |
| `4xx/5xx` | HTTP 异常 | 业务/系统异常 |

### 6.6 联调建议

1. 先登录鉴权，再联调受保护接口。
2. 优先联调查询接口，再联调写接口。
3. 资金链路最后联调（依赖最多）。
4. 问题排查优先使用 `request_id`。

---

## 7. 代码结构说明

### 7.1 仓库信息

| 项目 | 内容 |
|---|---|
| 仓库名称 | `backend-blockmatrix` |
| 仓库地址 | `git@github.com:Global-MongoX/backend-blockmatrix.git` |
| 默认分支 | `main` |
| 发布分支 | `main` |

### 7.2 目录结构

```text
backend-blockmatrix/
|- app/
|  |- Http/Controllers/
|  |- Http/Middleware/
|  |- Http/Requests/
|  |- Models/
|  |- Repositories/
|  |- Services/
|  `- Utils/
|- bootstrap/
|- config/
|- database/
|  |- migrations/
|  |- seeders/
|  `- schema/
|- docs/
|- public/
|- resources/
|- routes/
|- storage/
|- tests/Feature/
|- tests/Unit/
`- vendor/
```

### 7.3 模块与职责

| 模块 | 主要职责 |
|---|---|
| Blockmatrix | 登录、角色、菜单、管理员 |
| User/IB | 用户与代理体系 |
| Funds | 资金审核与流水 |
| Contest | 比赛配置与流程 |
| Ops | 运营配置与消息 |
| Trade | 交易查询能力 |

### 7.4 关键执行链路

`/api route -> middleware -> controller -> service -> repository/model -> response`

### 7.5 规范与约定

- 命名：类 PascalCase、方法 camelCase、字段 snake_case。
- 日志：关键操作建议携带 `request_id / user_code / order_no`。
- 规范参考：
  - Mango development conventions：<https://tjpls7trvp08.jp.larksuite.com/docx/JcHTdqO6Kocpk7xNa4IjJZJNp6c>
  - Suggestions for optimizing performance：<https://tjpls7trvp08.jp.larksuite.com/docx/RLDFd2ngWohNtox0fB0jduxEp2g>

---

## 8. 业务规则说明

### 8.1 核心流程

- 登录鉴权后进入权限控制。
- 按业务域执行查询、写入、审核。
- 关键写操作同步记录操作日志。

### 8.2 状态流转

- 涉及 IB、资金、比赛、黑白名单等状态机。
- 终态单据不允许重复流转。
- 资金审核链路需校验风控状态与财务状态。

### 8.3 权限模型

- 角色承载权限能力。
- 角色与菜单通过 `role_menu_rel` 关联。
- 数据范围由角色所属 `area` 约束。

### 8.4 业务风控规则

- 写接口统一 `ApiRequest` 校验。
- 通过唯一约束 + 存在性校验避免重复数据。
- 一级代理划转、终态审核等场景存在强限制。

---

## 9. 运维与排障

### 9.1 日志说明

| 项目 | 内容 |
|---|---|
| 日志路径 | `storage/logs/blockmatrix-YYYY-MM-DD.log` |
| 日志级别 | `LOG_LEVEL`（默认 debug） |
| 检索关键字 | `request_id / user_code / order_no / ib_id` |
| 异常告警 | `EmailReportError::sendExceptionEmail` |

### 9.2 排障建议

1. 根据接口返回拿到 `request_id`。
2. 检索应用日志与消费者日志。
3. 核对下游依赖返回与网络连通性。
4. 对资金链路优先检查状态回退逻辑。

---

## 10. 测试说明

### 10.1 测试范围

| 类型 | 现状 |
|---|---|
| Unit | 已配置，当前以示例用例为主 |
| Feature | 已配置，当前以示例用例为主 |
| 接口联调 | 依赖 Scramble + Postman/Apifox |
| 性能/安全 | 仓库内无内置压测与安全扫描脚本 |

### 10.2 测试命令

```bash
composer test
php artisan test
php artisan test --coverage
```

### 10.3 测试环境与数据

- 默认测试数据库：SQLite 内存（`phpunit.xml`）。
- 测试账号与权限需预置。
- 上传相关测试需准备可用 S3 测试桶或替代桩。

---

## 11. 权限与资源交接清单

| 资源类型 | 现状 |
|---|---|
| 邮件平台 | Laravel Mail，异常通知依赖 `EXCEPTION_EMAIL/REPORT_EMAIL` |
| 支付/资金 | 已接入 Payment（mepay）+ Funding/Flux |
| 回调白名单 | `MEPAY_CALL_BACK` 与第三方来源白名单需同步配置 |
| 短信/地图/OCR/AI | 未发现相关接入代码 |

---

## 12. 风险模块说明

| 风险模块 | 风险点 | 建议 |
|---|---|---|
| IB 体系 | 账户树与层级变更复杂 | 变更前后做路径一致性校验 |
| 资金审核链路 | 涉及多状态流转与下游依赖 | 增加关键状态监控与告警 |
| 权限模块 | 菜单与角色配置误操作风险高 | 生产变更走审批 + 双人复核 |
| 外部依赖 | TS/Flux/Payment/S3 不可用会放大故障 | 准备降级策略与重试补偿机制 |

---

## 附录 A：Mock 说明

当前仓库未提供独立 mock 服务。联调建议使用测试库真实数据，并对外部依赖配置可控测试环境。
