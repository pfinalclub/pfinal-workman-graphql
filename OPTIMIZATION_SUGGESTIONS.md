# 代码优化建议

本文档基于资深程序员的代码审查，提出的优化建议按优先级和类别组织。

## 🔴 高优先级（关键问题）

### 1. 异常处理与自定义异常类

**问题：**
- `GraphQLEngine.php` 中使用了内联定义的 `RuntimeException`（第272-274行），应该使用独立的异常类
- `Exception/` 目录为空，缺少项目特定的异常类型

**建议：**
```php
// src/Exception/GraphQLException.php
namespace PFinalClub\WorkermanGraphQL\Exception;

class GraphQLException extends \RuntimeException
{
}

// src/Exception/SchemaException.php
class SchemaException extends GraphQLException
{
}

// src/Exception/ConfigurationException.php
class ConfigurationException extends GraphQLException
{
}
```

**优化代码：**
- 移除 `GraphQLEngine.php` 中内联的 `RuntimeException`
- 创建专门的异常类层次结构
- 使用更具体的异常类型替代通用异常

---

### 2. Schema 缓存机制

**问题：**
- `GraphQLEngine::resolveSchema()` 每次请求都可能调用 factory，即使 schema 已缓存
- 缺少 Schema 验证和缓存失效机制

**建议：**
```php
// 在 GraphQLEngine 中添加
private ?\DateTimeImmutable $schemaCacheTime = null;
private int $schemaCacheTTL = 3600; // 可配置

private function resolveSchema(): Schema
{
    // 如果 schema 已设置且缓存有效，直接返回
    if ($this->schema instanceof Schema) {
        if ($this->schemaCacheTime === null || 
            (time() - $this->schemaCacheTime->getTimestamp()) < $this->schemaCacheTTL) {
            return $this->schema;
        }
    }
    
    // 重新构建 schema...
}
```

---

### 3. GraphiQL 安全性问题

**问题：**
- `Server::graphiqlHtml()` 中直接使用 CDN 资源，存在 XSS 风险
- 在生产环境应该禁用 GraphiQL，但缺少明确的环境检查

**建议：**
```php
private function shouldServeGraphiQL(RequestInterface $request): bool
{
    // 生产环境检查
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
        return false;
    }
    
    if (!$this->config['graphiql']) {
        return false;
    }

    $accept = $request->getHeader('accept', '');
    return str_contains((string) $accept, 'text/html');
}

// 使用 CSP 头部防止 XSS
private function graphiqlHtml(): string
{
    $cspNonce = base64_encode(random_bytes(16));
    // 添加 CSP 头部...
}
```

---

## 🟡 中优先级（重要改进）

### 4. 请求大小限制

**问题：**
- 缺少请求体大小限制，可能导致内存溢出攻击
- `GraphQLEngine::parseGraphQLRequest()` 直接读取整个请求体

**建议：**
```php
// 在 WorkermanAdapter 或 Server 配置中添加
private const MAX_REQUEST_SIZE = 1024 * 1024; // 1MB

private function transformRequest(WorkermanRequest $request): RequestInterface
{
    $rawBody = $request->rawBody() ?? '';
    
    if (strlen($rawBody) > self::MAX_REQUEST_SIZE) {
        throw new RequestTooLargeException(
            sprintf('Request body exceeds maximum size of %d bytes', self::MAX_REQUEST_SIZE)
        );
    }
    
    // ...
}
```

---

### 5. GraphQL 查询复杂度限制

**问题：**
- 缺少查询深度和复杂度限制，容易受到恶意复杂查询攻击

**建议：**
```php
// 添加查询分析中间件
class QueryComplexityMiddleware implements MiddlewareInterface
{
    private int $maxDepth;
    private int $maxComplexity;
    
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $payload = $this->parsePayload($request);
        if ($payload && isset($payload['query'])) {
            $depth = $this->calculateDepth($payload['query']);
            $complexity = $this->calculateComplexity($payload['query']);
            
            if ($depth > $this->maxDepth) {
                return JsonResponse::fromData([
                    'errors' => [['message' => "Query depth {$depth} exceeds maximum {$this->maxDepth}"]]
                ], 400);
            }
            // ...
        }
        return $next($request);
    }
}
```

---

### 6. 类型安全改进

**问题：**
- 多处使用 `mixed` 类型，降低了类型安全性
- `callable` 类型缺少更具体的类型定义

**建议：**
```php
// 使用 PHP 8.2+ 的 Closure 类型或更具体的接口
interface SchemaFactoryInterface
{
    public function build(): Schema;
}

interface ContextFactoryInterface
{
    public function create(RequestInterface $request): Context;
}

interface ErrorFormatterInterface
{
    /**
     * @param Error $error
     * @param bool $debug
     * @return array<string, mixed>
     */
    public function format(Error $error, bool $debug): array;
}
```

---

### 7. 性能优化 - Schema 构建

**问题：**
- `CodeSchemaBuilder::build()` 每次调用都创建新的 Schema 对象
- `TypeRegistry` 缺少类型查找优化

**建议：**
```php
// 在 CodeSchemaBuilder 中添加缓存
private ?GraphQLSchema $cachedSchema = null;

public function build(): GraphQLSchema
{
    if ($this->cachedSchema !== null && !$this->isDirty()) {
        return $this->cachedSchema;
    }
    
    // 构建逻辑...
    $this->cachedSchema = new GraphQLSchema($schemaConfig);
    return $this->cachedSchema;
}

// 标记变更
public function addQuery(string $name, array $config): self
{
    $this->cachedSchema = null; // 清除缓存
    $this->queries[$name] = $this->normalizeFieldConfig($name, $config);
    return $this;
}
```

---

### 8. 日志增强

**问题：**
- `LoggingMiddleware` 日志信息不够详细
- 缺少请求 ID 追踪
- 没有记录 GraphQL 查询内容（可能包含敏感信息，需谨慎）

**建议：**
```php
// 添加请求 ID
final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $requestId = $request->getAttribute('request_id') ?? bin2hex(random_bytes(8));
        $request = $request->withAttribute('request_id', $requestId);
        
        $response = $next($request);
        return $response->withHeader('X-Request-ID', $requestId);
    }
}

// 改进 LoggingMiddleware
$this->logger->info('GraphQL request handled', [
    'request_id' => $request->getAttribute('request_id'),
    'method' => $request->getMethod(),
    'path' => $request->getPath(),
    'query_hash' => hash('sha256', $payload['query'] ?? ''), // 不记录原始查询
    'operation_name' => $payload['operationName'] ?? null,
    'status' => $response->getStatusCode(),
    'duration_ms' => (int) round($duration * 1000),
    'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
]);
```

---

## 🟢 低优先级（代码质量提升）

### 9. 代码重复消除

**问题：**
- `WorkermanAdapter::decodeJson()` 和 `GraphQLEngine::decodeJson()` 代码重复

**建议：**
```php
// 创建工具类
final class JsonUtil
{
    public static function decode(string $json): ?array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }
}
```

---

### 10. 配置验证

**问题：**
- 配置项缺少验证，可能导致运行时错误
- 默认配置可能不安全（如 `allow_origin: ['*']`）

**建议：**
```php
final class ConfigValidator
{
    public static function validate(array $config): void
    {
        if (isset($config['server']['port']) && 
            ($config['server']['port'] < 1 || $config['server']['port'] > 65535)) {
            throw new ConfigurationException('Invalid port number');
        }
        
        if (isset($config['server']['worker_count']) && $config['server']['worker_count'] < 1) {
            throw new ConfigurationException('Worker count must be at least 1');
        }
        
        // 生产环境警告
        if (($config['cors']['allow_origin'] ?? ['*']) === ['*'] && 
            ($_ENV['APP_ENV'] ?? '') === 'production') {
            trigger_error('CORS allow_origin is set to * in production', E_USER_WARNING);
        }
    }
}
```

---

### 11. 内存优化

**问题：**
- `SdlSchemaBuilder::build()` 可能处理大型 SDL 文件时占用过多内存

**建议：**
```php
public function fromFile(string $path): self
{
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('SDL file "%s" does not exist.', $path));
    }
    
    // 检查文件大小
    $fileSize = filesize($path);
    if ($fileSize > 1024 * 1024) { // 1MB limit
        throw new RuntimeException('SDL file too large');
    }
    
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException(sprintf('Failed to read SDL file "%s".', $path));
    }
    
    $this->sdl = $contents;
    return $this;
}
```

---

### 12. 中间件优先级

**问题：**
- 中间件执行顺序完全依赖添加顺序，缺少优先级机制

**建议：**
```php
interface MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface;
    
    /**
     * 返回中间件优先级，数字越小优先级越高
     */
    public function getPriority(): int
    {
        return 100; // 默认优先级
    }
}

// 在 MiddlewarePipeline 中排序
public function add(MiddlewareInterface $middleware): void
{
    $this->middleware[] = $middleware;
    usort($this->middleware, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
}
```

---

### 13. CORS 安全性增强

**问题：**
- `CorsMiddleware` 默认允许所有来源（`['*']`）
- 缺少 Origin 验证的安全检查

**建议：**
```php
private function resolveOrigin(?string $origin): string
{
    $allowedOrigins = (array) $this->options['allow_origin'];
    
    if (in_array('*', $allowedOrigins, true)) {
        // 生产环境不应该使用 *
        if (($_ENV['APP_ENV'] ?? '') === 'production') {
            trigger_error('Wildcard CORS origin in production', E_USER_WARNING);
        }
        return '*';
    }
    
    if ($origin !== null) {
        // 验证 Origin 格式
        if (!filter_var($origin, FILTER_VALIDATE_URL)) {
            return $allowedOrigins[0] ?? '';
        }
        
        if (in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }
    }
    
    return $allowedOrigins[0] ?? '';
}
```

---

### 14. 错误信息泄漏

**问题：**
- 调试模式下可能泄露敏感信息（文件路径、堆栈跟踪）

**建议：**
```php
private function createErrorResponse(Throwable $exception): ResponseInterface
{
    if ($this->debug) {
        // 在生产环境即使 debug=true 也要过滤敏感信息
        $trace = $this->filterStackTrace($exception->getTraceAsString());
        
        $error = [
            'errors' => [[
                'message' => $exception->getMessage(),
                'trace' => $trace,
            ]],
        ];
    } else {
        $error = ['errors' => [['message' => 'Internal server error']]];
    }
    
    // ...
}

private function filterStackTrace(string $trace): string
{
    // 移除文件系统路径
    return preg_replace(
        '/\/[^\s]+/',
        '[FILTERED]',
        $trace
    );
}
```

---

### 15. 响应压缩

**问题：**
- 缺少响应压缩，大型 GraphQL 响应可能占用带宽

**建议：**
```php
// 添加压缩中间件
final class CompressionMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $response = $next($request);
        
        $acceptEncoding = $request->getHeader('Accept-Encoding', '');
        if (str_contains($acceptEncoding, 'gzip')) {
            $body = gzencode($response->getBody());
            return $response
                ->withBody($body)
                ->withHeader('Content-Encoding', 'gzip');
        }
        
        return $response;
    }
}
```

---

### 16. 指标收集

**问题：**
- 缺少性能指标收集（请求数、响应时间、错误率等）

**建议：**
```php
final class MetricsMiddleware implements MiddlewareInterface
{
    private array $metrics = [
        'requests_total' => 0,
        'errors_total' => 0,
        'response_time_sum' => 0.0,
    ];
    
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $start = microtime(true);
        $this->metrics['requests_total']++;
        
        try {
            $response = $next($request);
            $duration = microtime(true) - $start;
            $this->metrics['response_time_sum'] += $duration;
            
            if ($response->getStatusCode() >= 400) {
                $this->metrics['errors_total']++;
            }
            
            return $response;
        } catch (\Throwable $e) {
            $this->metrics['errors_total']++;
            throw $e;
        }
    }
    
    public function getMetrics(): array
    {
        return [
            'requests_total' => $this->metrics['requests_total'],
            'errors_total' => $this->metrics['errors_total'],
            'avg_response_time' => $this->metrics['requests_total'] > 0 
                ? $this->metrics['response_time_sum'] / $this->metrics['requests_total']
                : 0,
        ];
    }
}
```

---

### 17. 文档改进

**问题：**
- PHPDoc 注释不够完整
- 缺少使用示例和最佳实践文档

**建议：**
- 为所有公共方法添加完整的 PHPDoc
- 添加 API 文档生成（使用 phpDocumentor）
- 创建最佳实践指南文档
- 添加性能调优指南

---

## 📊 总结

### 优先级排序

1. **立即修复：** 异常处理、Schema 缓存、GraphiQL 安全
2. **近期改进：** 请求大小限制、查询复杂度、类型安全
3. **持续优化：** 代码质量、性能监控、文档完善

### 代码质量评分

- **架构设计：** ⭐⭐⭐⭐ (4/5) - 整体架构良好，但可以更模块化
- **安全性：** ⭐⭐⭐ (3/5) - 基础安全措施存在，但需要加强
- **性能：** ⭐⭐⭐⭐ (4/5) - 性能良好，但有优化空间
- **可维护性：** ⭐⭐⭐⭐ (4/5) - 代码清晰，但缺少一些最佳实践
- **测试覆盖：** ⭐⭐⭐⭐⭐ (5/5) - 测试覆盖良好

### 总体评价

项目整体质量**优秀**，代码结构清晰，设计模式运用得当。主要改进方向是：
1. 增强安全性（生产环境配置、输入验证）
2. 性能优化（缓存、查询限制）
3. 错误处理规范化
4. 监控和可观测性

这些优化将进一步提升项目的生产就绪程度。

