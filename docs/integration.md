# 框架集成指南

本项目提供了 Laravel 和 ThinkPHP 的官方集成，让你可以快速在现有框架项目中集成 GraphQL 功能。

## Laravel 集成

### 1. 安装

```bash
composer require pfinalclub/workerman-graphql
```

### 2. 注册服务提供者

在 `config/app.php` 中注册服务提供者：

```php
'providers' => [
    // ...
    PFinalClub\WorkermanGraphQL\Integration\Laravel\GraphQLServiceProvider::class,
],
```

### 3. 发布配置文件

```bash
php artisan vendor:publish --tag=workerman-graphql-config
```

这会在 `config/` 目录下创建 `workerman-graphql.php` 配置文件。

### 4. 配置 Schema

编辑 `config/workerman-graphql.php`:

```php
<?php

return [
    'debug' => env('APP_DEBUG', false),
    'graphiql' => env('APP_ENV') !== 'production',
    
    // Schema 配置（三选一）
    'schema' => [
        // 方式 1: SDL 文件路径
        'file' => base_path('graphql/schema.graphql'),
        
        // 方式 2: Schema Builder 类
        // 'class' => \App\GraphQL\SchemaBuilder::class,
        
        // 方式 3: 直接提供 Schema 实例
        // 'instance' => $schemaInstance,
    ],
    
    // 中间件配置
    'middleware' => [
        // Laravel 中间件
        \App\Http\Middleware\Authenticate::class,
        
        // 项目内置中间件
        \PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware::class => [
            'allow_origin' => ['https://yourdomain.com'],
        ],
        \PFinalClub\WorkermanGraphQL\Middleware\LoggingMiddleware::class => [
            // 需要 PSR-3 Logger 实例
        ],
    ],
    
    // Context Factory
    'context_factory' => function ($request) {
        return new \PFinalClub\WorkermanGraphQL\Context($request, [
            'user' => auth()->user(),
            'db' => DB::connection(),
        ]);
    },
];
```

### 5. 定义 Schema

#### 方式 1: SDL 文件

创建 `graphql/schema.graphql`:

```graphql
type User {
  id: ID!
  name: String!
  email: String!
}

type Query {
  users: [User!]!
  user(id: ID!): User
}
```

在配置文件中指定：

```php
'schema' => [
    'file' => base_path('graphql/schema.graphql'),
],
```

然后创建 Resolver 类或使用配置绑定。

#### 方式 2: Schema Builder 类

创建 `app/GraphQL/SchemaBuilder.php`:

```php
<?php

namespace App\GraphQL;

use GraphQL\Type\Definition\Type;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;

final class SchemaBuilder
{
    public function build(): CodeSchemaBuilder
    {
        $builder = new CodeSchemaBuilder();
        
        $builder->addQuery('users', [
            'type' => Type::listOf($this->getUserType()),
            'resolve' => fn() => \App\Models\User::all()->toArray(),
        ]);
        
        return $builder;
    }
    
    private function getUserType()
    {
        // 返回 User 类型定义
    }
}
```

在配置中指定：

```php
'schema' => [
    'class' => \App\GraphQL\SchemaBuilder::class,
],
```

### 6. 注册路由

在 `routes/web.php` 或 `routes/api.php` 中：

```php
use Illuminate\Support\Facades\Route;

// 标准路径
Route::workermanGraphQL('/graphql');

// 自定义路径
Route::workermanGraphQL('/api/graphql');

// 带中间件
Route::workermanGraphQL('/graphql')->middleware('auth');
```

### 7. 创建 Resolver（SDL 方式）

如果使用 SDL 文件，需要设置 Resolver。可以在服务提供者中配置：

```php
// app/Providers/GraphQLServiceProvider.php
public function boot(): void
{
    $this->app->booted(function () {
        $engine = $this->app->make(\PFinalClub\WorkermanGraphQL\GraphQLEngine::class);
        $builder = $engine->getSchemaBuilder(); // 需要扩展支持
        
        if ($builder instanceof \PFinalClub\WorkermanGraphQL\Schema\SdlSchemaBuilder) {
            $builder->setResolver('Query', 'users', fn() => \App\Models\User::all()->toArray());
        }
    });
}
```

## ThinkPHP 集成

### 1. 安装

```bash
composer require pfinalclub/workerman-graphql
```

### 2. 注册服务

在 `app/service.php` 中注册：

```php
<?php

return [
    \PFinalClub\WorkermanGraphQL\Integration\ThinkPHP\GraphQLService::class,
];
```

### 3. 配置文件

创建 `config/workerman_graphql.php`:

```php
<?php

return [
    'debug' => env('app_debug', false),
    'graphiql' => env('app_env') !== 'production',
    
    'schema' => [
        'file' => app_path('graphql/schema.graphql'),
    ],
    
    'middleware' => [
        \app\middleware\Auth::class,
        \PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware::class,
    ],
];
```

### 4. 定义 Schema

创建 `app/graphql/schema.graphql`:

```graphql
type User {
  id: ID!
  name: String!
}

type Query {
  users: [User!]!
}
```

### 5. 设置 Resolver

在服务启动时设置 Resolver：

```php
// app/GraphQL/Bootstrap.php
public function boot(): void
{
    $engine = app('graphql.engine');
    // 设置 Resolver...
}
```

### 6. 路由配置

默认已注册 `GET|POST /graphql` 路由。如需自定义，可以在 `GraphQLService::boot` 中扩展。

## 中间件配置

### Laravel

配置文件 `middleware` 字段支持以下形式：

```php
'middleware' => [
    // 1. 直接填写类名（容器自动实例化）
    \App\Http\Middleware\Authenticate::class,
    
    // 2. 带配置（数组形式）
    \PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware::class => [
        'allow_origin' => ['https://example.com'],
    ],
    
    // 3. 闭包（用于自定义实例化）
    function ($app) {
        return new \App\Http\Middleware\CustomMiddleware($app->make('config'));
    },
    
    // 4. 直接提供实例
    new \PFinalClub\WorkermanGraphQL\Middleware\ErrorHandlerMiddleware(true),
],
```

### ThinkPHP

```php
'middleware' => [
    // 类名
    \app\middleware\Auth::class,
    
    // 带命名空间的完整类名
    'app\\middleware\\RateLimit',
],
```

## 自定义中间件

所有中间件必须实现 `MiddlewareInterface`:

```php
<?php

namespace App\Http\Middleware;

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewareInterface;

final class CustomMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        // 请求前处理
        $request = $request->withAttribute('processed', true);
        
        // 调用下一个中间件
        $response = $next($request);
        
        // 响应后处理
        $response = $response->withHeader('X-Custom', 'value');
        
        return $response;
    }
}
```

## 在 Resolver 中使用框架功能

### Laravel

```php
'resolve' => static function ($rootValue, array $args, Context $context) {
    // 使用 Laravel 的 DB
    $db = $context->get('db');
    return $db->table('users')->where('id', $args['id'])->first();
    
    // 或使用 Eloquent（通过 Context）
    $user = \App\Models\User::find($args['id']);
    return $user;
}
```

### ThinkPHP

```php
'resolve' => static function ($rootValue, array $args, Context $context) {
    // 使用 ThinkPHP 的数据库
    return \think\facade\Db::table('users')
        ->where('id', $args['id'])
        ->find();
}
```

## 认证集成

### Laravel

```php
// 在 Context Factory 中获取认证用户
'context_factory' => function ($request) {
    return new \PFinalClub\WorkermanGraphQL\Context($request, [
        'user' => auth()->user(),
    ]);
},

// 在 Resolver 中使用
'resolve' => static function ($rootValue, array $args, Context $context) {
    $user = $context->get('user');
    if (!$user) {
        throw new \Exception('未认证');
    }
    return getDataForUser($user);
}
```

### ThinkPHP

```php
'context_factory' => function ($request) {
    return new \PFinalClub\WorkermanGraphQL\Context($request, [
        'user' => \think\facade\Request::user(),
    ]);
},
```

## 最佳实践

### 1. 使用服务容器

Laravel 和 ThinkPHP 都支持依赖注入，充分利用这个特性：

```php
// Laravel
class UserResolver
{
    public function __construct(
        private UserRepository $repository
    ) {
    }
    
    public function getUser($rootValue, array $args): ?array
    {
        return $this->repository->find($args['id']);
    }
}

// 在配置中指定
'middleware' => [
    fn($app) => $app->make(\App\GraphQL\Resolvers\UserResolver::class),
],
```

### 2. 使用配置分离

将 Schema 配置与业务逻辑分离：

```php
// config/graphql/schema.php
return [
    'queries' => [
        'users' => \App\GraphQL\Resolvers\UserResolver::class,
    ],
    'mutations' => [
        'createUser' => \App\GraphQL\Resolvers\UserResolver::class,
    ],
];
```

### 3. 环境区分

```php
'debug' => env('APP_DEBUG', false),
'graphiql' => env('APP_ENV') !== 'production',

'middleware' => array_merge(
    [
        \PFinalClub\WorkermanGraphQL\Middleware\ErrorHandlerMiddleware::class,
    ],
    env('APP_ENV') === 'production' ? [
        \PFinalClub\WorkermanGraphQL\Middleware\RateLimitMiddleware::class,
    ] : []
),
```

## 常见问题

### Q: 如何处理 Laravel 的路由缓存？

A: GraphQL 路由使用宏注册，不受路由缓存影响。但 Schema 变更后需要清除应用缓存：

```bash
php artisan config:clear
php artisan cache:clear
```

### Q: 如何与 Laravel Passport/Sanctum 集成？

A: 在 Context Factory 中获取认证用户：

```php
'context_factory' => function ($request) {
    $user = auth()->user(); // Laravel 会自动处理 Token
    return new Context($request, ['user' => $user]);
},
```

### Q: 如何在 ThinkPHP 中使用模型？

A: 在 Resolver 中直接使用 ThinkPHP 模型：

```php
'resolve' => static function ($rootValue, array $args) {
    return \app\model\User::find($args['id']);
}
```

## 下一步

- 📖 查看 [配置选项](./configuration.md)
- 📖 阅读 [最佳实践](./best-practices.md)
- 📖 了解 [常见问题](./troubleshooting.md)

