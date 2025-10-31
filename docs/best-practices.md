# 最佳实践

本文档提供使用本项目的最佳实践和建议。

## 架构设计

### 1. Schema 组织

#### 推荐结构

```
graphql/
├── schema/
│   ├── schema.graphql          # 主 Schema 文件（SDL）
│   ├── types/
│   │   ├── User.graphql
│   │   ├── Post.graphql
│   │   └── Comment.graphql
│   └── scalars/
│       └── Date.graphql
├── resolvers/
│   ├── QueryResolver.php
│   ├── MutationResolver.php
│   ├── UserResolver.php
│   └── PostResolver.php
└── config.php
```

#### Schema 文件组织

**主 Schema 文件** (`schema.graphql`):

```graphql
# 引入类型定义
# import User from "./types/User.graphql"
# import Post from "./types/Post.graphql"

type Query {
  users: [User!]!
  user(id: ID!): User
  posts: [Post!]!
}

type Mutation {
  createUser(input: CreateUserInput!): User!
  createPost(input: CreatePostInput!): Post!
}
```

**类型文件** (`types/User.graphql`):

```graphql
type User {
  id: ID!
  name: String!
  email: String!
  posts: [Post!]!
}
```

### 2. Resolver 组织

#### 使用 Resolver 类

```php
<?php

namespace App\GraphQL\Resolvers;

use PFinalClub\WorkermanGraphQL\Context;

final class UserResolver
{
    public function getUserById($rootValue, array $args, Context $context): ?array
    {
        $db = $context->get('db');
        return $db->query('SELECT * FROM users WHERE id = ?', [$args['id']]);
    }
    
    public function getUsers($rootValue, array $args, Context $context): array
    {
        $db = $context->get('db');
        return $db->query('SELECT * FROM users');
    }
}

// 使用
$resolver = new UserResolver();
$builder->addQuery('user', [
    'type' => $userType,
    'args' => ['id' => ['type' => Type::nonNull(Type::id())]],
    'resolve' => [$resolver, 'getUserById'],
]);
```

#### 使用闭包（简单场景）

```php
$builder->addQuery('hello', [
    'type' => Type::string(),
    'resolve' => static fn() => 'Hello World',
]);
```

## 性能优化

### 1. Schema 缓存

```php
// 生产环境启用缓存
if (getenv('APP_ENV') === 'production') {
    $server->setSchemaCacheTTL(3600);
}

// Schema 变更时清除缓存
$server->clearSchemaCache();
```

### 2. 避免 N+1 查询问题

#### 问题示例

```php
// ❌ 错误：会导致 N+1 查询
$builder->addQuery('posts', [
    'type' => Type::listOf($postType),
    'resolve' => fn() => getAllPosts(),
]);

$postType->fields['author'] = [
    'type' => $userType,
    'resolve' => fn($post) => getUserById($post['author_id']), // N+1 问题
];
```

#### 解决方案：使用 DataLoader

```php
use GraphQL\Utils\BuildSchema;

class PostLoader
{
    private array $cache = [];
    
    public function load(string $id): ?array
    {
        if (!isset($this->cache[$id])) {
            $this->cache[$id] = $this->fetchPost($id);
        }
        return $this->cache[$id];
    }
    
    public function loadMany(array $ids): array
    {
        $missing = array_diff($ids, array_keys($this->cache));
        if (!empty($missing)) {
            $posts = $this->fetchPosts($missing);
            foreach ($posts as $post) {
                $this->cache[$post['id']] = $post;
            }
        }
        return array_map(fn($id) => $this->cache[$id] ?? null, $ids);
    }
    
    private function fetchPost(string $id): ?array
    {
        // 数据库查询
    }
    
    private function fetchPosts(array $ids): array
    {
        // 批量查询
    }
}

// 在 Context 中注入
$server->setContextFactory(function ($request) use ($postLoader) {
    return new Context($request, [
        'postLoader' => $postLoader,
    ]);
});

// 在 Resolver 中使用
'resolve' => static function ($post, array $args, Context $context) {
    $loader = $context->get('postLoader');
    return $loader->load($post['author_id']);
}
```

### 3. 查询复杂度限制

```php
$server->addMiddleware(new QueryComplexityMiddleware(
    maxDepth: 10,
    maxComplexity: 1000
));
```

### 4. 请求大小限制

```php
class RequestSizeLimitMiddleware implements MiddlewareInterface
{
    private const MAX_SIZE = 1024 * 1024; // 1MB
    
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        if (strlen($request->getBody()) > self::MAX_SIZE) {
            return JsonResponse::fromData([
                'errors' => [['message' => '请求体过大']],
            ], 413);
        }
        return $next($request);
    }
}
```

## 安全实践

### 1. 认证和授权

```php
// 认证中间件
class AuthenticationMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $token = $this->extractToken($request);
        if (!$this->validateToken($token)) {
            return Response::create(401, [
                'Content-Type' => 'application/json',
            ], json_encode(['errors' => [['message' => '未授权']]]));
        }
        
        $user = $this->getUserFromToken($token);
        $request = $request->withAttribute('user', $user);
        
        return $next($request);
    }
}

// 在 Resolver 中检查权限
'resolve' => static function ($rootValue, array $args, Context $context) {
    $user = $context->getRequest()->getAttribute('user');
    if (!$user || !$user->isAdmin()) {
        throw new \Exception('权限不足');
    }
    return getAdminData();
}
```

### 2. 输入验证

```php
'resolve' => static function ($rootValue, array $args) {
    // 验证 ID 格式
    if (!preg_match('/^[a-f0-9]{32}$/i', $args['id'])) {
        throw new \InvalidArgumentException('无效的 ID 格式');
    }
    
    // 验证邮箱
    if (isset($args['email']) && !filter_var($args['email'], FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('无效的邮箱地址');
    }
    
    return performOperation($args);
}
```

### 3. 生产环境配置

```php
// 生产环境必须设置
if (getenv('APP_ENV') === 'production') {
    // 禁用调试模式
    $server->setDebug(false);
    
    // GraphiQL 会自动禁用，但建议显式设置
    // $server = new Server(['graphiql' => false]);
    
    // 限制 CORS
    $server->addMiddleware(new CorsMiddleware([
        'allow_origin' => ['https://yourdomain.com'],
        'allow_credentials' => true,
    ]));
    
    // 启用限流
    $server->addMiddleware(new RateLimitMiddleware(100, 60));
}
```

### 4. 错误信息处理

```php
// 自定义错误格式化，避免泄露敏感信息
$server->setErrorFormatter(function ($error, $debug) {
    $message = $error->getMessage();
    
    // 生产环境隐藏详细错误
    if (!$debug) {
        // 记录完整错误到日志
        error_log($error->getTraceAsString());
        
        // 返回通用错误信息
        if (str_contains($message, 'SQL')) {
            $message = '数据库操作失败';
        } elseif (str_contains($message, 'file')) {
            $message = '文件操作失败';
        }
    }
    
    return [
        'message' => $message,
        'code' => $error->getCode() ?: 'ERROR',
    ];
});
```

## 代码组织

### 1. 使用工厂模式组织代码

```php
<?php

namespace App\GraphQL;

use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Schema\TypeRegistry;

final class SchemaFactory
{
    public static function create(): CodeSchemaBuilder
    {
        $builder = new CodeSchemaBuilder();
        
        // 注册类型
        self::registerTypes($builder);
        
        // 注册查询
        self::registerQueries($builder);
        
        // 注册变更
        self::registerMutations($builder);
        
        return $builder;
    }
    
    private static function registerTypes(CodeSchemaBuilder $builder): void
    {
        $builder->registerType('User', UserType::create());
        $builder->registerType('Post', PostType::create());
    }
    
    private static function registerQueries(CodeSchemaBuilder $builder): void
    {
        $builder->addQuery('users', UserResolver::users());
        $builder->addQuery('user', UserResolver::user());
        $builder->addQuery('posts', PostResolver::posts());
    }
    
    private static function registerMutations(CodeSchemaBuilder $builder): void
    {
        $builder->addMutation('createUser', UserResolver::create());
        $builder->addMutation('createPost', PostResolver::create());
    }
}

// 使用
$server->useSchemaBuilder(SchemaFactory::create());
```

### 2. 使用配置驱动

```php
// config/graphql.php
return [
    'queries' => [
        'user' => \App\GraphQL\Resolvers\UserResolver::class,
        'posts' => \App\GraphQL\Resolvers\PostResolver::class,
    ],
    'mutations' => [
        'createUser' => \App\GraphQL\Resolvers\UserResolver::class,
    ],
];

// 自动注册
$config = require __DIR__ . '/config/graphql.php';
foreach ($config['queries'] as $name => $resolver) {
    $builder->addQuery($name, (new $resolver)->getQueryConfig($name));
}
```

## 测试实践

### 1. Schema 测试

```php
use PHPUnit\Framework\TestCase;
use GraphQL\Type\Schema;

final class SchemaTest extends TestCase
{
    public function testSchemaIsValid(): void
    {
        $builder = SchemaFactory::create();
        $schema = $builder->build();
        
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertNotNull($schema->getQueryType());
    }
    
    public function testQueryExists(): void
    {
        $builder = SchemaFactory::create();
        $schema = $builder->build();
        
        $this->assertTrue($schema->getQueryType()->hasField('user'));
    }
}
```

### 2. Resolver 测试

```php
use PHPUnit\Framework\TestCase;
use Mockery;
use PFinalClub\WorkermanGraphQL\Context;

final class UserResolverTest extends TestCase
{
    public function testGetUserById(): void
    {
        $db = Mockery::mock(Database::class);
        $db->shouldReceive('query')
            ->once()
            ->andReturn(['id' => '1', 'name' => 'Alice']);
        
        $context = new Context(
            Mockery::mock(RequestInterface::class),
            ['db' => $db]
        );
        
        $resolver = new UserResolver();
        $user = $resolver->getUserById(null, ['id' => '1'], $context);
        
        $this->assertEquals('Alice', $user['name']);
    }
}
```

## 日志和监控

### 1. 结构化日志

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

$logger = new Logger('graphql');
$handler = new StreamHandler(__DIR__ . '/logs/graphql.log', Logger::INFO);
$handler->setFormatter(new JsonFormatter());
$logger->pushHandler($handler);

$server->addMiddleware(new LoggingMiddleware($logger));
```

### 2. 请求追踪

```php
class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $requestId = bin2hex(random_bytes(8));
        $request = $request->withAttribute('request_id', $requestId);
        
        $response = $next($request);
        
        return $response->withHeader('X-Request-ID', $requestId);
    }
}
```

## 错误处理

### 1. 统一异常处理

```php
// 自定义异常类
namespace App\GraphQL\Exceptions;

class GraphQLException extends \Exception
{
    public function __construct(
        string $message,
        public readonly string $code = 'GRAPHQL_ERROR',
        int $statusCode = 400
    ) {
        parent::__construct($message, $statusCode);
    }
}

// 在 Resolver 中使用
'resolve' => static function ($rootValue, array $args) {
    if (!$user = getUserById($args['id'])) {
        throw new GraphQLException('用户不存在', 'USER_NOT_FOUND', 404);
    }
    return $user;
}
```

### 2. 错误分类

```php
$server->setErrorFormatter(function ($error, $debug) {
    $formatted = [
        'message' => $error->getMessage(),
    ];
    
    // 根据错误类型分类
    if ($error instanceof ValidationException) {
        $formatted['code'] = 'VALIDATION_ERROR';
        $formatted['fields'] = $error->getFields();
    } elseif ($error instanceof AuthenticationException) {
        $formatted['code'] = 'AUTHENTICATION_ERROR';
    } elseif ($error instanceof AuthorizationException) {
        $formatted['code'] = 'AUTHORIZATION_ERROR';
    }
    
    return $formatted;
});
```

## 部署实践

### 1. 使用进程管理器

**Supervisor 配置**:

```ini
[program:graphql]
command=php /path/to/server.php
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/log/graphql.log
stderr_logfile=/var/log/graphql-error.log
```

### 2. Nginx 反向代理

```nginx
server {
    listen 80;
    server_name api.example.com;
    
    location /graphql {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

### 3. 健康检查

```php
$builder->addQuery('health', [
    'type' => Type::nonNull(Type::string()),
    'resolve' => static fn() => 'OK',
]);

// 或添加独立健康检查端点
if ($request->getPath() === '/health') {
    return Response::create(200, ['Content-Type' => 'application/json'], 
        json_encode(['status' => 'OK']));
}
```

## 性能监控

### 1. 添加性能指标

```php
class MetricsMiddleware implements MiddlewareInterface
{
    private array $metrics = [];
    
    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;
        
        $this->recordMetric([
            'path' => $request->getPath(),
            'method' => $request->getMethod(),
            'status' => $response->getStatusCode(),
            'duration' => $duration,
        ]);
        
        return $response;
    }
    
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
```

## 总结

遵循这些最佳实践可以：

1. ✅ 提高代码质量和可维护性
2. ✅ 优化性能和资源使用
3. ✅ 增强安全性和稳定性
4. ✅ 便于测试和调试
5. ✅ 简化部署和运维

## 下一步

- 📖 查看 [配置选项](./configuration.md)
- 📖 阅读 [常见问题](./troubleshooting.md)
- 📖 了解 [框架集成](./integration.md)

