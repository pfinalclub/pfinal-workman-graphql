# Workerman GraphQL

基于 Workerman 与 [`webonyx/graphql-php`](https://github.com/webonyx/graphql-php) 的通用 GraphQL API 扩展包，提供独立运行、Laravel 与 ThinkPHP 集成等多种使用方式。

## 特性概览

- Workerman 高性能 HTTP 服务器适配器
- 与框架无关的 GraphQL 引擎，可复用在任意 PHP 环境
- 支持代码式与 SDL 两种 Schema 构建方式
- 中间件管线：CORS、错误处理、日志等可扩展能力
- Laravel / ThinkPHP 官方适配，开箱即用
- GraphiQL 工具内置，便于开发调试

## 安装

```bash
composer require pfinalclub/workerman-graphql
```

安装完成后，可按需选择 Workerman 独立模式或集成到现有框架中。

## 快速开始（独立服务）

```php
<?php

use GraphQL\Type\Definition\Type;
use PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware;
use PFinalClub\WorkermanGraphQL\Middleware\ErrorHandlerMiddleware;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Server;

$server = new Server([
    'server' => [
        'host' => '0.0.0.0',
        'port' => 8080,
        'worker_count' => 4,
    ],
    'debug' => true,
]);

$server
    ->addMiddleware(new ErrorHandlerMiddleware(true))
    ->addMiddleware(new CorsMiddleware());

$server->configureSchema(function (CodeSchemaBuilder $builder): void {
    $builder->addQuery('hello', [
        'type' => Type::nonNull(Type::string()),
        'args' => [
            'name' => ['type' => Type::string()],
        ],
        'resolve' => static fn($root, array $args): string => 'Hello ' . ($args['name'] ?? 'World'),
    ]);
});

$server->start();
```

访问 `http://127.0.0.1:8080/graphql` 可进行 GraphQL 请求；若在浏览器中访问并且 `Accept: text/html`，会自动加载 GraphiQL 调试页面。

更多示例见 `examples/` 目录：

- `examples/01-basic`：Workerman 独立服务示例

## Schema 定义方式

### 代码式（CodeSchemaBuilder）

通过 `CodeSchemaBuilder` 按函数式配置 Query/Mutation/Subscription：

```php
$builder->addQuery('user', [
    'type' => $typeRegistry->get('User'),
    'args' => ['id' => ['type' => Type::nonNull(Type::id())]],
    'resolve' => fn($root, array $args) => $repository->find($args['id']),
]);
```

### SDL 方式（SdlSchemaBuilder）

```php
use PFinalClub\WorkermanGraphQL\Schema\SdlSchemaBuilder;

$builder = (new SdlSchemaBuilder())
    ->fromFile(__DIR__ . '/schema.graphql')
    ->setResolver('Query', 'hello', fn() => 'Hello via SDL');

$server->useSchemaBuilder($builder);
```

## 中间件

中间件需实现 `PFinalClub\WorkermanGraphQL\Middleware\MiddlewareInterface`，可通过 `Server::addMiddleware()` 注册。

内置中间件包括：

- `CorsMiddleware`：处理 CORS 预检与响应头
- `ErrorHandlerMiddleware`：捕获异常并统一输出 JSON 错误
- `LoggingMiddleware`：基于 PSR-3 记录请求日志

## 框架集成

### Laravel

1. 在 `config/app.php` 中注册服务提供者：
   ```php
   'providers' => [
       // ...
       PFinalClub\WorkermanGraphQL\Integration\Laravel\GraphQLServiceProvider::class,
   ];
   ```
2. 发布配置文件并调整 Schema/Middleware：
   ```bash
   php artisan vendor:publish --tag=workerman-graphql-config
   ```
3. 在路由中启用：
   ```php
   Route::workermanGraphQL('/graphql');
   ```

### ThinkPHP 6

1. 在 `app/service.php` 中注册服务：
   ```php
   return [
       PFinalClub\WorkermanGraphQL\Integration\ThinkPHP\GraphQLService::class,
   ];
   ```
2. 在 `config` 下新增/调整 `workerman_graphql.php`。
3. 默认已注册 `GET|POST /graphql` 路由。

## 开发进度

- [x] Phase 1: 项目初始化
- [x] Phase 2: 核心层实现
- [x] Phase 3: Schema 构建系统
- [x] Phase 4: Workerman 适配器
- [x] Phase 5: 中间件系统
- [x] Phase 6: 框架集成
- [ ] Phase 7: 工具与辅助
- [ ] Phase 8: 示例与文档
- [ ] Phase 9: 测试

## 许可协议

本项目基于 MIT 协议开源。

