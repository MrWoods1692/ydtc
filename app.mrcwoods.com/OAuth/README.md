# OAuth 2.0 授权服务

一个轻量级的 PHP OAuth 2.0 授权服务器实现，支持授权码模式 (Authorization Code Flow)。

## 功能特性

- ✅ OAuth 2.0 授权码模式
- ✅ 标准的错误响应格式 (RFC 6749)
- ✅ 美观的授权确认页面（支持应用图标、描述、主页）
- ✅ 开发者控制台（创建/管理应用）
- ✅ Cookie 用户认证
- ✅ 操作日志记录
- ✅ 环境变量配置
- ✅ 统一异常处理

## 目录结构

```
OAuth/
├── .env                    # 环境配置文件
├── composer.json           # PHP 依赖管理
├── database/
│   └── oauth_tables.sql    # 数据库建表脚本
├── public/
│   ├── authorize.php       # 授权端点
│   ├── token.php           # 令牌端点
│   ├── resource.php        # 资源端点（用户信息）
│   └── console.php         # 开发者控制台
├── src/
│   ├── Core/
│   │   ├── Config.php      # 配置管理
│   │   ├── Database.php    # 数据库连接
│   │   ├── Logger.php      # 日志记录
│   │   └── OAuthException.php  # 异常处理
│   ├── Controller/
│   │   ├── AuthorizeController.php   # 授权控制器
│   │   ├── TokenController.php       # 令牌控制器
│   │   ├── ResourceController.php    # 资源控制器
│   │   └── ConsoleController.php     # 控制台控制器
│   └── OAuth/
│       └── AuthorizationServer.php   # 授权服务器核心
└── views/
    ├── authorize.phtml     # 授权确认页面
    ├── console.phtml       # 开发者控制台页面
    └── login_required.phtml # 登录提示页面
```

## 安装步骤

### 1. 安装依赖

```bash
composer install
```

### 2. 创建数据库表

执行 `database/oauth_tables.sql` 中的 SQL 脚本创建必要的表：

```bash
mysql -u user -p user < database/oauth_tables.sql
```

### 3. 配置环境变量

编辑 `.env` 文件，配置数据库连接信息：

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=user
DB_USER=user
DB_PASS=your_password
DB_CHARSET=utf8mb4

OAUTH_AUTH_CODE_EXPIRE=600
OAUTH_ACCESS_TOKEN_EXPIRE=86400
```

### 4. 使用开发者控制台

访问 `/public/console.php` 即可进入开发者控制台。

**功能：**
- 创建 OAuth 应用（每个用户只能创建一个）
- 填写应用信息：
  - **应用名称**：显示在授权页面
  - **应用主页**：应用的官方网站
  - **应用描述**：在授权页面展示给用户
  - **应用图标**：推荐 128x128 尺寸
  - **回调地址**：用户授权后重定向地址
- 获取 Client ID 和 Client Secret
- 修改应用信息
- 重新生成密钥
- 删除应用

**认证：** 控制台读取 cookie 中的 `user_token` 验证用户身份。

## API 端点

### 1. 授权端点 `/public/authorize.php`

用户访问此端点进行授权。

**请求参数 (GET):**

| 参数 | 必填 | 描述 |
|------|------|------|
| client_id | 是 | 客户端ID |
| redirect_uri | 是 | 回调地址 |
| response_type | 是 | 固定值 `code` |
| state | 否 | 用于防止CSRF攻击的状态值 |

**示例:**

```
GET /public/authorize.php?client_id=your_client_id&redirect_uri=https://your-app.com/callback&response_type=code&state=xyz
```

**流程:**
1. 检查用户是否登录（通过 cookie 中的 `user_token`）
2. 未登录则重定向到回调地址并返回错误
3. 已登录则显示授权确认页面（包含应用名称、图标、描述）
4. 用户同意后生成授权码并重定向

### 2. 令牌端点 `/public/token.php`

客户端使用授权码换取访问令牌。

**请求参数 (POST):**

| 参数 | 必填 | 描述 |
|------|------|------|
| grant_type | 是 | 固定值 `authorization_code` |
| client_id | 是 | 客户端ID |
| client_secret | 是 | 客户端密钥 |
| code | 是 | 授权码 |
| redirect_uri | 否 | 回调地址（需与授权请求一致）|

**示例:**

```bash
curl -X POST http://localhost/public/token.php \
  -d "grant_type=authorization_code" \
  -d "client_id=your_client_id" \
  -d "client_secret=your_client_secret" \
  -d "code=authorization_code"
```

**成功响应:**

```json
{
  "access_token": "a1b2c3d4...",
  "token_type": "Bearer",
  "expires_in": 86400
}
```

### 3. 资源端点 `/public/resource.php`

获取用户信息。

**请求头:**

```
Authorization: Bearer {access_token}
```

**示例:**

```bash
curl -H "Authorization: Bearer your_access_token" \
  http://localhost/public/resource.php
```

**成功响应:**

```json
{
  "user_id": 1,
  "email": "user@example.com",
  "created_at": 1708123456
}
```

## 日志记录

### 操作日志

系统会自动记录用户的操作日志到 `../open-platform_log/` 目录：

- 日志文件：`{urlencode(用户邮箱)}.log`
- 日志内容：操作时间、操作类型、操作详情

**记录的操作类型：**
- 创建应用
- 更新应用
- 删除应用
- 重新生成密钥
- 同意授权（包含 IP、User-Agent）
- 拒绝授权（包含 IP、User-Agent）

**日志格式示例：**
```
[2024-02-23 14:20:00] 操作：创建应用 | 详情：应用名称: 我的博客, Client ID: client_abc123
[2024-02-23 14:25:00] 操作：同意授权 | 详情：应用: 我的博客 (Client ID: client_abc123), 授权码: xyz789 | IP: 192.168.1.1 | UA: Mozilla/5.0...
```

### 授权日志表

授权操作同时记录到 `oauth_authorization_logs` 数据库表，便于统计和查询。

## 错误响应

所有错误响应遵循 OAuth 2.0 标准格式：

```json
{
  "error": "invalid_request",
  "error_description": "错误描述"
}
```

**错误类型:**

| 错误码 | HTTP状态码 | 描述 |
|--------|-----------|------|
| invalid_request | 400 | 请求缺少必要参数 |
| invalid_client | 401 | 客户端认证失败 |
| invalid_grant | 400 | 授权码无效或过期 |
| unauthorized_client | 400 | 客户端无权限 |
| unsupported_grant_type | 400 | 不支持的授权类型 |
| invalid_scope | 400 | 无效的权限范围 |
| access_denied | 400 | 用户拒绝授权 |
| unsupported_response_type | 400 | 不支持的response_type |
| server_error | 500 | 服务器内部错误 |

## 用户认证

用户需要先登录，系统通过 cookie 中的 `user_token` 字段验证用户身份。

`user_token` 对应 `users` 表中的 `token` 字段。

## 数据库表结构

### oauth_clients（客户端应用表）

| 字段 | 类型 | 描述 |
|------|------|------|
| id | int | 主键 |
| client_id | varchar(64) | 客户端ID（唯一）|
| client_secret | varchar(128) | 客户端密钥 |
| name | varchar(128) | 应用名称 |
| homepage | varchar(512) | 应用主页 |
| description | text | 应用描述 |
| icon | varchar(512) | 应用图标URL |
| redirect_uri | varchar(512) | 回调地址 |
| user_id | int | 所属用户ID（唯一，每人只能创建一个应用）|
| created_at | int | 创建时间戳 |

### oauth_authorization_logs（授权日志表）

| 字段 | 类型 | 描述 |
|------|------|------|
| id | int | 主键 |
| client_id | varchar(64) | 客户端ID |
| user_id | int | 用户ID |
| action | varchar(32) | 操作类型：authorize/deny |
| ip | varchar(45) | IP地址 |
| user_agent | varchar(512) | 用户代理 |
| created_at | int | 创建时间戳 |

## 授权流程图

```
┌─────────┐                    ┌─────────┐                    ┌─────────┐
│  用户   │                    │ 授权服务 │                    │ 客户端  │
└────┬────┘                    └────┬────┘                    └────┬────┘
     │                              │                              │
     │  1. 点击"使用XXX登录"        │                              │
     │ ◄────────────────────────────┼──────────────────────────────┤
     │                              │                              │
     │  2. 重定向到授权页面          │                              │
     ├─────────────────────────────►│                              │
     │                              │                              │
     │  3. 检查登录状态              │                              │
     │ ◄────────────────────────────┤                              │
     │                              │                              │
     │  4. 显示授权确认页面          │                              │
     │    (应用名称、图标、描述)     │                              │
     │ ◄────────────────────────────┤                              │
     │                              │                              │
     │  5. 用户点击"同意授权"        │                              │
     ├─────────────────────────────►│                              │
     │                              │                              │
     │                              │  6. 记录授权日志             │
     │                              ├─────────────────────────────►│
     │                              │                              │
     │  7. 重定向到回调地址(带code)  │                              │
     │ ◄────────────────────────────┼─────────────────────────────►│
     │                              │                              │
     │                              │  8. 用code换access_token     │
     │                              │ ◄─────────────────────────────┤
     │                              │                              │
     │                              │  9. 返回access_token         │
     │                              ├─────────────────────────────►│
     │                              │                              │
     │                              │  10. 使用token获取用户信息    │
     │                              │ ◄─────────────────────────────┤
     │                              │                              │
     │                              │  11. 返回用户信息             │
     │                              ├─────────────────────────────►│
     │                              │                              │
```

## 许可证

MIT License