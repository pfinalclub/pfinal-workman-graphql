# 中间件使用指南

中间件提供了一种优雅的方式来处理请求和响应。本项目的中间件系统基于管道模式（Pipeline Pattern）实现。

## 中间件概念

中间件在请求处理流程中的位置：

```
Request → Middleware 1 → Middleware 2 → ... → GraphQL Engine → Response
         ↑                                                      ↓
         └──────────────────────────────────────────────────────┘
```

## 内置中间件

### 1. ErrorHandlerMiddleware

统一异常处理和错误响应格式化。

```php
use PFinalClub\WorkermanGraphQL\Middleware\ErrorHandlerMiddleware;

// 启用调试模式（显示详细错误信息）
$server->addMiddleware(new ErrorHandlerMiddleware(true));

// 生产模式（隐藏敏感信息）
$server->addMiddleware(new ErrorHandlerMiddleware(false));
```

**功能：**
- ✅ 捕获所有异常
- ✅ 统一 JSON 错误格式
- ✅ 调试模式下显示堆栈跟踪
- ✅ 生产模式下隐藏敏感信息

### 2. CorsMiddleware

处理跨域资源共享（CORS）。

```php
use PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware;

// 默认配置（允许所有来源）
$server->addMiddleware(new CorsMiddleware());

// 自定义配置
$server->addMiddleware(new CorsMiddleware([
    'allow_origin' => ['https://example.com', 'https://app.example.com'],
    'allow_methods' => ['GET', 'POST', 'OPTIONS'],
    'allow_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'expose_headers' => ['X-Request-ID'],
    'allow_credentials' => true,
    'max_age' => 86400,  // 24 小时
]));
```

**配置选项：**

| 选项 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `allow_origin` | `string[]` | `['*']` | 允许的来源列表 |
| `allow_methods` | `string[]` | `['GET', 'POST', 'OPTIONS']` | 允许的 HTTP 方法 |
| `allow_headers` | `string[]` | `['Content-Type', 'Authorization']` | 允许的请求头 |
| `expose_headers` | `string[]` | `[]` | 暴露给客户端的响应头 |
| `allow_credentials` | `bool` | `false` | 是否允许携带凭证 |
| `max_age` | `int` | `86400` | 预检请求缓存时间（秒） |

**生产环境建议：**

```php
$server->addMiddleware(new CorsMiddleware([
    'allow_origin' => [
        'https://yourdomain.com',
        'https://app.yourdomain.com',
    ],
    'allow_credentials' => true,
]));
```

### 3. LoggingMiddleware

基于 PSR-3 记录请求日志。

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PFinalClub\WorkermanGraphQL\Middleware\LoggingMiddleware;

// 创建 PSR-3 Logger
$logger = new Logger('graphql');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/graphql.log', Logger::INFO));

$server->addMiddleware(new LoggingMiddleware($logger));
```

**日志信息包含：**
- 请求方法
- 请求路径
- 响应状态码
- 请求处理时间（毫秒）
- 时间戳（ISO 8601 格式）

## 创建自定义中间件

### 中间件接口

所有中间件必须实现 `MiddlewareInterface`：

```php
interface MiddlewareInterface {
    public function process(RequestInterface $request, callable $next): ResponseInterface;
}
```

### 示例 1: 认证中间件

```php
<?php

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\Response;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewareInterface;

final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private array $publicPaths = []
    ) {
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        // 检查是否为公开路径
        if (in_array($request->getPath(), $this->publicPaths, true)) {
            return $next($request);
        }

        // 验证 Token
        $token = $this->extractToken($request);
        if (!$token || !$this->validateToken($token)) {
            return Response::create(401, [
                'Content-Type' => 'application/json',
            ], json_encode([
                'errors' => [['message' => '未授权访问']],
            ]));
        }

        // 将用户信息添加到请求属性中
        $user = $this->getUserFromToken($token);
        $request = $request->withAttribute('user', $user);

        return $next($request);
    }

    private function extractToken(RequestInterface $request): ?string
    {
        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader) {
            return null;
        }

        // 支持 "Bearer <token>" 格式
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function validateToken(string $token): bool
    {
        // 实现 Token 验证逻辑
        return true; // 示例
    }

    private function getUserFromToken(string $token): array
    {
        // 实现从 Token 获取用户信息
        return ['id' => '1', 'name' => 'User']; // 示例
    }
}

// 使用
$server->addMiddleware(new AuthenticationMiddleware(['/graphql/health']));
```

### 示例 2: 请求限流中间件

```php
<?php

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\JsonResponse;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewareInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private array $requests = [];
    
    public function __construct(
        private int $maxRequests = 100,
        private int $windowSeconds = 60
    ) {
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $clientId = $this->getClientId($request);
        $now = time();
        
        // 清理过期记录
        $this->cleanup($now);
        
        // 检查限流
        if (!$this->checkLimit($clientId, $now)) {
            return JsonResponse::fromData([
                'errors' => [[
                    'message' => '请求过于频繁，请稍后再试',
                    'code' => 'RATE_LIMIT_EXCEEDED',
                ]],
            ], 429);
        }
        
        // 记录请求
        $this->recordRequest($clientId, $now);
        
        return $next($request);
    }

    private function getClientId(RequestInterface $request): string
    {
        // 使用 IP 地址或用户 ID
        return $request->getHeader('X-Forwarded-For') 
            ?: $request->getAttribute('user')['id'] 
            ?? 'anonymous';
    }

    private function checkLimit(string $clientId, int $now): bool
    {
        $key = $clientId . '_' . floor($now / $this->windowSeconds);
        $count = $this->requests[$key] ?? 0;
        
        return $count < $this->maxRequests;
    }

    private function recordRequest(string $clientId, int $now): void
    {
        $key = $clientId . '_' . floor($now / $this->windowSeconds);
        $this->requests[$key] = ($this->requests[$key] ?? 0) + 1;
    }

    private function cleanup(int $now): void
    {
        $currentWindow = floor($now / $this->windowSeconds);
        foreach ($this->requests as $key => $value) {
            $window = (int) explode('_', $key)[1];
            if ($window < $currentWindow - 1) {
                unset($this->requests[$key]);
            }
        }
    }
}

// 使用
$server->addMiddleware(new RateLimitMiddleware(100, 60)); // 每分钟最多 100 次请求
```

### 示例 3: 请求 ID 中间件

```php
<?php

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewareInterface;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        // 获取或生成请求 ID
        $requestId = $request->getAttribute('request_id') 
            ?? $request->getHeader('X-Request-ID')
            ?? bin2hex(random_bytes(8));
        
        // 添加到请求属性
        $request = $request->withAttribute('request_id', $requestId);
        
        // 处理请求
        $response = $next($request);
        
        // 添加到响应头
        return $response->withHeader('X-Request-ID', $requestId);
    }
}

// 使用
$server->addMiddleware(new RequestIdMiddleware());
```

### 示例 4: 查询复杂度限制中间件

```php
<?php

use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PFinalClub\WorkermanGraphQL\Http\JsonResponse;
use PFinalClub\WorkermanGraphQL\Http\ResponseInterface;
use PFinalClub\WorkermanGraphQL\Middleware\MiddlewareInterface;

final class QueryComplexityMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxDepth = 10,
        private int $maxComplexity = 1000
    ) {
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $payload = $this->parsePayload($request);
        
        if ($payload && isset($payload['query'])) {
            $depth = $this->calculateDepth($payload['query']);
            $complexity = $this->calculateComplexity($payload['query']);
            
            if ($depth > $this->maxDepth) {
                return JsonResponse::fromData([
                    'errors' => [[
                        'message' => "查询深度 {$depth} 超过最大限制 {$this->maxDepth}",
                        'code' => 'QUERY_TOO_DEEP',
                    ]],
                ], 400);
            }
            
            if ($complexity > $this->maxComplexity) {
                return JsonResponse::fromData([
                    'errors' => [[
                        'message' => "查询复杂度 {$complexity} 超过最大限制 {$this->maxComplexity}",
                        'code' => 'QUERY_TOO_COMPLEX',
                    ]],
                ], 400);
            }
        }
        
        return $next($request);
    }

    private function parsePayload(RequestInterface $request): ?array
    {
        $body = $request->getBody();
        if (empty($body)) {
            return null;
        }
        
        return json_decode($body, true);
    }

    private function calculateDepth(string $query): int
    {
        // 简单的深度计算实现
        $depth = 0;
        $maxDepth = 0;
        
        foreach (str_split($query) as $char) {
            if ($char === '{') {
                $depth++;
                $maxDepth = max($maxDepth, $depth);
            } elseif ($char === '}') {
                $depth--;
            }
        }
        
        return $maxDepth;
    }

    private function calculateComplexity(string $query): int
    {
        // 简单的复杂度计算（字段数量）
        return substr_count($query, '{') + substr_count($query, '}');
    }
}

// 使用
$server->addMiddleware(new QueryComplexityMiddleware(10, 1000));
```

## 中间件执行顺序

中间件的执行顺序按照添加顺序：

```php
// 执行顺序：ErrorHandler → Cors → Authentication → RateLimit → GraphQL Engine
$server
    ->addMiddleware(new ErrorHandlerMiddleware(true))
    ->addMiddleware(new CorsMiddleware())
    ->addMiddleware(new AuthenticationMiddleware())
    ->addMiddleware(new RateLimitMiddleware());
```

**建议顺序：**
1. 错误处理（最外层，捕获所有异常）
2. CORS（处理跨域）
3. 认证/授权
4. 限流
5. 日志（记录完整请求）
6. 其他业务中间件

## 在中间件中修改请求

中间件可以修改请求对象（通过不可变模式）：

```php
public function process(RequestInterface $request, callable $next): ResponseInterface
{
    // 添加请求属性
    $request = $request->withAttribute('start_time', microtime(true));
    
    // 修改请求体（如果需要）
    $parsedBody = $request->getParsedBody();
    if ($parsedBody) {
        $parsedBody['processed_by'] = 'middleware';
        $request = $request->withParsedBody($parsedBody);
    }
    
    return $next($request);
}
```

## 在中间件中修改响应

中间件可以修改响应对象：

```php
public function process(RequestInterface $request, callable $next): ResponseInterface
{
    $response = $next($request);
    
    // 添加响应头
    $response = $response->withHeader('X-Processed-By', 'MyMiddleware');
    
    // 修改响应状态码（如果需要）
    if ($someCondition) {
        $response = $response->withStatus(202);
    }
    
    return $response;
}
```

## 中间件中访问 Context

在 Resolver 中可以访问通过中间件设置的请求属性：

```php
// 在中间件中设置
$request = $request->withAttribute('user', $user);

// 在 Resolver 中访问
'resolve' => static function ($rootValue, array $args, Context $context) {
    $request = $context->getRequest();
    $user = $request->getAttribute('user');
    
    return $user;
}
```

## 框架集成中的中间件

### Laravel

在 `config/workerman-graphql.php` 中配置：

```php
return [
    'middleware' => [
        \App\Http\Middleware\Authenticate::class,
        \PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware::class,
    ],
];
```

### ThinkPHP

在 `config/workerman_graphql.php` 中配置：

```php
return [
    'middleware' => [
        \app\middleware\Auth::class,
        \PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware::class,
    ],
];
```

## 最佳实践

1. **错误处理放在最外层**：确保所有异常都被捕获
2. **认证放在业务逻辑之前**：尽早验证身份
3. **限流放在认证之后**：避免无效请求消耗资源
4. **日志放在最后**：记录完整的请求和响应
5. **使用不可变对象**：通过 `with*` 方法修改请求/响应
6. **保持中间件职责单一**：每个中间件只做一件事

## 下一步

- 📖 查看 [配置选项](./configuration.md)
- 📖 阅读 [最佳实践](./best-practices.md)
- 📖 了解 [框架集成](./integration.md)

