# 安装与初始化

## 系统要求

### PHP 版本
- **PHP 8.0+**（推荐 PHP 8.1+）
- 必须启用 `ext-json` 扩展
- 建议启用 `ext-curl` 扩展（用于 HTTP 客户端）

### 依赖包
- Workerman 4.0+
- webonyx/graphql-php 15.0+
- PSR-3 Logger（可选，用于日志中间件）

## 安装步骤

### 1. 通过 Composer 安装

```bash
composer require pfinalclub/workerman-graphql
```

### 2. 验证安装

安装完成后，可以通过以下方式验证：

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('PFinalClub\WorkermanGraphQL\Server') ? 'OK' : 'FAIL';"
```

### 3. 选择运行模式

本项目支持两种运行模式：

#### 模式 1: Workerman 独立服务（推荐用于微服务）

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use PFinalClub\WorkermanGraphQL\Server;

$server = new Server([
    'server' => [
        'host' => '0.0.0.0',
        'port' => 8080,
        'worker_count' => 4,
    ],
]);

$server->start();
```

**优势：**
- ✅ 高性能，支持多进程
- ✅ 独立部署，易于扩展
- ✅ 资源隔离

**适用场景：**
- 微服务架构
- 独立 GraphQL 服务
- 高性能要求场景

#### 模式 2: 框架集成模式（推荐用于传统应用）

将 GraphQL 引擎集成到现有的 Laravel 或 ThinkPHP 应用中。

**优势：**
- ✅ 复用现有框架生态
- ✅ 统一认证和权限
- ✅ 共享数据库连接

**适用场景：**
- 现有 Laravel/ThinkPHP 项目
- 需要与框架深度集成
- 快速集成 GraphQL 功能

详细集成步骤请参考 [框架集成指南](./integration.md)

## 目录结构建议

### 独立服务模式

```
project/
├── graphql/
│   ├── schema/              # Schema 定义
│   │   ├── schema.graphql   # SDL 方式
│   │   └── types.php        # 类型定义
│   ├── resolvers/           # Resolver 实现
│   │   ├── QueryResolver.php
│   │   ├── MutationResolver.php
│   │   └── UserResolver.php
│   └── config.php           # GraphQL 配置
├── config/
│   └── server.php           # 服务器配置
├── public/
│   └── index.php            # 入口文件
└── vendor/
```

### 框架集成模式

#### Laravel

```
app/
├── GraphQL/
│   ├── Schema/
│   │   └── schema.graphql
│   └── Resolvers/
│       └── QueryResolver.php
config/
└── workerman-graphql.php
```

#### ThinkPHP

```
app/
├── graphql/
│   ├── schema/
│   └── resolvers/
config/
└── workerman_graphql.php
```

## 权限配置

### Workerman 独立服务

如果使用 Workerman 独立模式，需要确保：

1. **进程管理权限**：需要 root 权限或使用 Supervisor/systemd 管理
2. **端口绑定权限**：绑定 1024 以下端口需要 root 权限
3. **文件权限**：确保日志和临时文件目录可写

### 推荐部署方式

使用 Supervisor 或 systemd 管理 Workerman 进程：

**Supervisor 配置示例** (`/etc/supervisor/conf.d/graphql.conf`):

```ini
[program:graphql]
command=php /path/to/project/server.php
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/graphql.log
```

## 环境配置

### 开发环境

```php
$server = new Server([
    'debug' => true,
    'graphiql' => true,
    'server' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'worker_count' => 1,
    ],
]);
```

### 生产环境

```php
$server = new Server([
    'debug' => false,
    'graphiql' => false,  // 生产环境自动禁用
    'server' => [
        'host' => '0.0.0.0',
        'port' => 8080,
        'worker_count' => 4,  // 根据 CPU 核心数调整
        'name' => 'graphql-server',
    ],
]);

// 设置环境变量
putenv('APP_ENV=production');
```

**重要提示：**
- 生产环境会自动检测 `APP_ENV=production` 并禁用 GraphiQL
- 建议设置 `worker_count` 为 CPU 核心数的 2-4 倍
- 使用进程管理器（Supervisor/systemd）确保服务稳定运行

## 下一步

安装完成后，你可以：

1. 📖 查看 [快速开始指南](./quickstart.md)
2. 📖 学习 [Schema 定义方式](./schema.md)
3. 📖 了解 [中间件使用](./middleware.md)
4. 📖 查看 [配置选项](./configuration.md)

