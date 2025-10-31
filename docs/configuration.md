# 配置选项说明

本文档详细说明所有可用的配置选项。

## Server 配置

### 基础配置

```php
$server = new Server([
    'endpoint' => '/graphql',      // GraphQL 端点路径
    'graphiql' => true,            // 是否启用 GraphiQL
    'debug' => false,              // 调试模式
    'server' => [                  // Workerman 服务器配置
        'host' => '0.0.0.0',
        'port' => 8080,
        'name' => 'workerman-graphql',
        'worker_count' => 4,
    ],
]);
```

### 配置项说明

#### `endpoint` (string)

GraphQL API 的端点路径。

- **默认值**: `'/graphql'`
- **示例**: 
  - `'/graphql'` - 标准路径
  - `'/api/graphql'` - 自定义路径
  - `'/v1/graphql'` - 版本化路径

#### `graphiql` (bool)

是否启用 GraphiQL 调试界面。

- **默认值**: `true`
- **注意**: 
  - 生产环境（`APP_ENV=production`）会自动禁用
  - 需要在浏览器中访问且 `Accept: text/html` 才会显示

#### `debug` (bool)

是否启用调试模式。

- **默认值**: `false`
- **功能**:
  - 显示详细的错误信息
  - 包含堆栈跟踪
  - 显示 Schema 验证错误

**生产环境警告**: 必须设置为 `false`，避免泄露敏感信息。

#### `server` (array)

Workerman 服务器配置。

```php
'server' => [
    'host' => '0.0.0.0',          // 监听地址
    'port' => 8080,                // 监听端口
    'name' => 'workerman-graphql', // 进程名称
    'worker_count' => 4,           // Worker 进程数
    'context' => [],               // SSL 上下文（可选）
    'ssl' => [...],                // SSL 配置（可选）
    'reuse_port' => false,        // 是否启用端口复用
    'on_worker_start' => fn() => ..., // Worker 启动回调
]
```

**配置说明**:

| 选项 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `host` | `string` | `'0.0.0.0'` | 监听地址，`0.0.0.0` 表示监听所有网络接口 |
| `port` | `int` | `8080` | 监听端口，1024 以下需要 root 权限 |
| `name` | `string` | `'workerman-graphql'` | 进程名称，用于进程管理 |
| `worker_count` | `int` | `4` | Worker 进程数，建议设置为 CPU 核心数的 2-4 倍 |
| `context` | `array` | `[]` | SSL 上下文配置 |
| `ssl` | `array` | - | SSL 证书配置 |
| `reuse_port` | `bool` | `false` | 启用端口复用，提升性能 |
| `on_worker_start` | `callable` | - | Worker 进程启动时的回调函数 |

**Worker 数量建议**:

```php
// CPU 密集型任务
'worker_count' => 1,  // 与 CPU 核心数相同

// I/O 密集型任务（推荐）
'worker_count' => 4,  // CPU 核心数的 2-4 倍

// 高并发场景
'worker_count' => 8,  // 根据实际情况调整
```

### SSL/HTTPS 配置

```php
$server = new Server([
    'server' => [
        'host' => '0.0.0.0',
        'port' => 443,
        'ssl' => [
            'local_cert' => '/path/to/cert.pem',
            'local_pk' => '/path/to/key.pem',
            'verify_peer' => false,
        ],
    ],
]);
```

## Schema 缓存配置

### 缓存 TTL

```php
// 设置缓存生存时间（秒）
$server->setSchemaCacheTTL(3600); // 1 小时

// 禁用缓存
$server->setSchemaCacheTTL(0);

// 手动清除缓存
$server->clearSchemaCache();
```

**建议**:
- 开发环境: `0`（禁用缓存，每次重新构建）
- 生产环境: `3600`（1 小时）或更长

## Context 配置

### 自定义 Context Factory

```php
$server->setContextFactory(function (RequestInterface $request): Context {
    // 从请求中提取用户信息
    $authHeader = $request->getHeader('Authorization');
    $user = $authHeader ? parseToken($authHeader) : null;
    
    // 获取数据库连接
    $db = getDatabaseConnection();
    
    // 创建 Context
    return new Context($request, [
        'user' => $user,
        'db' => $db,
        'ip' => $request->getHeader('X-Real-IP') ?? $request->getHeader('X-Forwarded-For'),
    ]);
});
```

在 Resolver 中使用:

```php
'resolve' => static function ($rootValue, array $args, Context $context) {
    $user = $context->get('user');
    $db = $context->get('db');
    
    if (!$user) {
        throw new \Exception('未认证');
    }
    
    return $db->query('SELECT * FROM users WHERE id = ?', [$args['id']]);
}
```

## 错误格式化配置

### 自定义错误格式

```php
$server->setErrorFormatter(function (\GraphQL\Error\Error $error, bool $debug): array {
    $formatted = [
        'message' => $error->getMessage(),
        'code' => $error->getCode() ?: 'GRAPHQL_ERROR',
    ];
    
    if ($debug) {
        $formatted['extensions'] = [
            'category' => $error->getCategory(),
            'locations' => $error->getLocations(),
            'path' => $error->getPath(),
        ];
    }
    
    return $formatted;
});
```

## 框架集成配置

### Laravel 配置

发布配置文件:

```bash
php artisan vendor:publish --tag=workerman-graphql-config
```

配置文件 `config/workerman-graphql.php`:

```php
return [
    'debug' => env('APP_DEBUG', false),
    'graphiql' => env('APP_ENV') !== 'production',
    
    'schema' => [
        // Schema 配置
        // 可以是 Schema 实例、Builder 实例或文件路径
        'file' => base_path('graphql/schema.graphql'),
        // 或
        'class' => \App\GraphQL\SchemaBuilder::class,
    ],
    
    'middleware' => [
        \App\Http\Middleware\Authenticate::class,
        \PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware::class,
        \PFinalClub\WorkermanGraphQL\Middleware\LoggingMiddleware::class => [
            // 中间件配置
        ],
    ],
    
    'context_factory' => function ($request) {
        return new \PFinalClub\WorkermanGraphQL\Context($request, [
            'user' => auth()->user(),
        ]);
    },
];
```

### ThinkPHP 配置

配置文件 `config/workerman_graphql.php`:

```php
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

## 环境变量配置

### 推荐环境变量

```bash
# .env 文件

# 应用环境
APP_ENV=production

# 调试模式
APP_DEBUG=false

# GraphQL 端点
GRAPHQL_ENDPOINT=/graphql

# 服务器配置
GRAPHQL_HOST=0.0.0.0
GRAPHQL_PORT=8080
GRAPHQL_WORKER_COUNT=4

# Schema 缓存
GRAPHQL_CACHE_TTL=3600
```

在代码中使用:

```php
$server = new Server([
    'debug' => (bool) getenv('APP_DEBUG'),
    'graphiql' => getenv('APP_ENV') !== 'production',
    'endpoint' => getenv('GRAPHQL_ENDPOINT') ?: '/graphql',
    'server' => [
        'host' => getenv('GRAPHQL_HOST') ?: '0.0.0.0',
        'port' => (int) (getenv('GRAPHQL_PORT') ?: 8080),
        'worker_count' => (int) (getenv('GRAPHQL_WORKER_COUNT') ?: 4),
    ],
]);

if ($ttl = getenv('GRAPHQL_CACHE_TTL')) {
    $server->setSchemaCacheTTL((int) $ttl);
}
```

## 性能优化配置

### 1. Worker 进程数优化

```php
// 根据 CPU 核心数自动设置
$cpuCount = (int) shell_exec('nproc') ?: 4;
$workerCount = $cpuCount * 2;

$server = new Server([
    'server' => [
        'worker_count' => $workerCount,
    ],
]);
```

### 2. 端口复用

```php
$server = new Server([
    'server' => [
        'reuse_port' => true, // 启用端口复用
    ],
]);
```

### 3. Schema 缓存

```php
// 生产环境启用长时间缓存
if (getenv('APP_ENV') === 'production') {
    $server->setSchemaCacheTTL(86400); // 24 小时
}
```

## 安全配置

### 1. 生产环境设置

```php
// 生产环境配置
if (getenv('APP_ENV') === 'production') {
    $server = new Server([
        'debug' => false,
        'graphiql' => false,  // 自动禁用
        'server' => [
            'host' => '127.0.0.1',  // 只监听本地
            'port' => 8080,
        ],
    ]);
    
    // 使用 Nginx 反向代理提供 HTTPS
}
```

### 2. CORS 配置

```php
// 生产环境 CORS
$server->addMiddleware(new CorsMiddleware([
    'allow_origin' => [
        'https://yourdomain.com',
        'https://app.yourdomain.com',
    ],
    'allow_credentials' => true,
    'max_age' => 86400,
]));
```

### 3. 请求大小限制

```php
// 通过中间件限制请求大小
class RequestSizeLimitMiddleware implements MiddlewareInterface {
    private const MAX_SIZE = 1024 * 1024; // 1MB
    
    public function process(RequestInterface $request, callable $next): ResponseInterface {
        $size = strlen($request->getBody());
        if ($size > self::MAX_SIZE) {
            return JsonResponse::fromData([
                'errors' => [['message' => '请求体过大']],
            ], 413);
        }
        return $next($request);
    }
}
```

## 完整配置示例

### 开发环境

```php
$server = new Server([
    'endpoint' => '/graphql',
    'debug' => true,
    'graphiql' => true,
    'server' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'name' => 'graphql-dev',
        'worker_count' => 1,
    ],
]);

$server->setSchemaCacheTTL(0); // 禁用缓存
```

### 生产环境

```php
$server = new Server([
    'endpoint' => '/graphql',
    'debug' => false,
    'graphiql' => false,
    'server' => [
        'host' => '0.0.0.0',
        'port' => 8080,
        'name' => 'graphql-prod',
        'worker_count' => 8,
        'reuse_port' => true,
    ],
]);

$server
    ->setSchemaCacheTTL(3600)
    ->addMiddleware(new CorsMiddleware([
        'allow_origin' => ['https://yourdomain.com'],
    ]))
    ->addMiddleware(new RateLimitMiddleware(100, 60));
```

## 下一步

- 📖 阅读 [最佳实践](./best-practices.md)
- 📖 查看 [常见问题](./troubleshooting.md)
- 📖 了解 [框架集成](./integration.md)

