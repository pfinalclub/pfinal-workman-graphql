# 常见问题解答

本文档收集了使用过程中的常见问题和解决方案。

## 安装问题

### Q: Composer 安装失败

**问题**: `composer require pfinalclub/workerman-graphql` 报错

**解决方案**:
1. 检查 PHP 版本：需要 PHP 8.0+
   ```bash
   php -v
   ```
2. 检查扩展：确保 `ext-json` 已安装
   ```bash
   php -m | grep json
   ```
3. 更新 Composer：
   ```bash
   composer self-update
   ```

### Q: 找不到类或命名空间错误

**问题**: `Class 'PFinalClub\WorkermanGraphQL\Server' not found`

**解决方案**:
1. 确保已运行 `composer install` 或 `composer update`
2. 检查 `composer.json` 中的 autoload 配置
3. 重新生成 autoload 文件：
   ```bash
   composer dump-autoload
   ```

## 运行问题

### Q: Workerman 启动失败

**问题**: `Address already in use` 或 `Permission denied`

**解决方案**:

1. **端口被占用**:
   ```bash
   # 检查端口占用
   lsof -i :8080
   # 或
   netstat -tulpn | grep 8080
   
   # 更改端口或停止占用进程
   ```

2. **权限不足**（绑定 1024 以下端口）:
   ```bash
   # 使用 root 权限
   sudo php server.php
   
   # 或使用 1024 以上端口
   'port' => 8080,
   ```

3. **检查防火墙**:
   ```bash
   # Linux
   sudo ufw allow 8080
   ```

### Q: 进程无法在后台运行

**问题**: 启动后立即退出

**解决方案**:
1. 使用进程管理器（Supervisor/systemd）
2. 检查错误日志
3. 确保代码中没有 `exit()` 或致命错误

### Q: Worker 进程数设置无效

**问题**: 设置的 `worker_count` 没有生效

**解决方案**:
```php
// 确保配置正确传递
$server = new Server([
    'server' => [
        'worker_count' => 4, // 确保这个值被正确设置
    ],
]);

// 检查实际进程数
ps aux | grep graphql
```

## Schema 问题

### Q: Schema 必须定义至少一个 Query 字段

**错误**: `GraphQL schema must define at least one query field.`

**解决方案**:
```php
// 确保至少添加一个 Query
$builder->addQuery('hello', [
    'type' => Type::string(),
    'resolve' => fn() => 'Hello',
]);
```

### Q: 类型未注册错误

**错误**: `Type "User" is not registered.`

**解决方案**:
```php
// 先注册类型
$builder->registerType('User', $userType);

// 再使用
$builder->addQuery('user', [
    'type' => $builder->getTypeRegistry()->get('User'),
    // ...
]);
```

### Q: Resolver 未设置（SDL 方式）

**错误**: 查询返回 `null` 或错误

**解决方案**:
```php
// 确保为所有字段设置 Resolver
$builder->setResolver('Query', 'users', fn() => getUsers());
$builder->setResolver('Query', 'user', fn($rootValue, array $args) => getUserById($args['id']));
$builder->setResolver('User', 'posts', fn($user) => getPostsByUserId($user['id']));
```

### Q: Schema 缓存不生效

**问题**: Schema 修改后没有变化

**解决方案**:
```php
// 清除缓存
$server->clearSchemaCache();

// 或禁用缓存（开发环境）
$server->setSchemaCacheTTL(0);
```

## 请求处理问题

### Q: 请求返回 404

**问题**: 访问 `/graphql` 返回 404

**解决方案**:
1. 检查端点配置：
   ```php
   $server = new Server([
       'endpoint' => '/graphql', // 确保路径正确
   ]);
   ```
2. 检查请求路径：
   ```bash
   curl http://127.0.0.1:8080/graphql
   ```

### Q: POST 请求返回 400

**问题**: `Invalid GraphQL request payload`

**解决方案**:
1. 检查 Content-Type：
   ```bash
   curl -X POST http://127.0.0.1:8080/graphql \
     -H "Content-Type: application/json" \
     -d '{"query": "{ hello }"}'
   ```
2. 检查请求体格式：
   ```json
   {
     "query": "{ hello }",
     "variables": {},
     "operationName": "MyQuery"
   }
   ```

### Q: GET 请求参数解析失败

**问题**: GET 请求的 query 参数未正确解析

**解决方案**:
```bash
# 正确格式
curl "http://127.0.0.1:8080/graphql?query={hello}"

# variables 需要 URL 编码
curl "http://127.0.0.1:8080/graphql?query={user(id:\$id){name}}&variables={\"id\":\"1\"}"
```

## 中间件问题

### Q: 中间件未执行

**问题**: 添加的中间件没有被调用

**解决方案**:
1. 检查添加顺序：
   ```php
   // 确保在 start() 之前添加
   $server
       ->addMiddleware(new Middleware1())
       ->addMiddleware(new Middleware2())
       ->start(); // start() 之后添加无效
   ```
2. 检查中间件是否实现接口：
   ```php
   class MyMiddleware implements MiddlewareInterface {
       // ...
   }
   ```

### Q: CORS 跨域问题

**问题**: 浏览器报 CORS 错误

**解决方案**:
```php
// 配置 CORS 中间件
$server->addMiddleware(new CorsMiddleware([
    'allow_origin' => ['https://yourdomain.com'],
    'allow_methods' => ['GET', 'POST', 'OPTIONS'],
    'allow_headers' => ['Content-Type', 'Authorization'],
    'allow_credentials' => true,
]));
```

### Q: 日志中间件不工作

**问题**: LoggingMiddleware 没有记录日志

**解决方案**:
1. 确保提供了 PSR-3 Logger 实例：
   ```php
   use Monolog\Logger;
   use Monolog\Handler\StreamHandler;
   
   $logger = new Logger('graphql');
   $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/graphql.log'));
   
   $server->addMiddleware(new LoggingMiddleware($logger));
   ```
2. 检查文件权限：
   ```bash
   chmod 755 logs/
   chmod 644 logs/graphql.log
   ```

## GraphiQL 问题

### Q: GraphiQL 界面不显示

**问题**: 访问 `/graphql` 没有显示 GraphiQL

**解决方案**:
1. 检查配置：
   ```php
   $server = new Server([
       'graphiql' => true, // 确保启用
   ]);
   ```
2. 检查环境变量：
   ```php
   // 生产环境会自动禁用
   // 确保 APP_ENV !== 'production'
   ```
3. 检查 Accept 头：
   ```bash
   # 需要使用浏览器访问，或设置 Accept: text/html
   curl -H "Accept: text/html" http://127.0.0.1:8080/graphql
   ```

### Q: GraphiQL CSP 错误

**问题**: 浏览器控制台报 CSP 错误

**说明**: 这是正常的，GraphiQL 使用了 CSP nonce 防护。如果 CDN 资源加载失败，检查网络连接。

## 性能问题

### Q: 请求响应慢

**问题**: GraphQL 查询执行缓慢

**排查步骤**:
1. 检查 Schema 缓存：
   ```php
   $server->setSchemaCacheTTL(3600); // 启用缓存
   ```
2. 检查 N+1 查询问题：
   ```php
   // 使用 DataLoader 或批量查询
   ```
3. 检查 Worker 进程数：
   ```php
   'worker_count' => 4, // 根据 CPU 核心数调整
   ```
4. 启用查询日志：
   ```php
   $server->addMiddleware(new LoggingMiddleware($logger));
   ```

### Q: 内存占用过高

**问题**: 进程内存持续增长

**解决方案**:
1. 检查是否有内存泄漏（长时间运行的对象引用）
2. 限制请求大小
3. 优化 Resolver 实现，避免加载大量数据

## 框架集成问题

### Q: Laravel 路由未注册

**问题**: `Route::workermanGraphQL()` 报错

**解决方案**:
1. 确保服务提供者已注册
2. 清除路由缓存：
   ```bash
   php artisan route:clear
   ```
3. 检查路由文件是否正确引入

### Q: ThinkPHP 服务未启动

**问题**: GraphQL 路由 404

**解决方案**:
1. 确保服务已注册在 `app/service.php`
2. 检查配置文件 `config/workerman_graphql.php` 是否存在
3. 清除 ThinkPHP 缓存

### Q: 无法访问 Laravel 服务容器

**问题**: Resolver 中无法使用 `app()` 或依赖注入

**解决方案**:
```php
// 在 Context Factory 中注入服务
'context_factory' => function ($request) {
    return new Context($request, [
        'app' => app(), // Laravel 容器
    ]);
},

// 在 Resolver 中使用
'resolve' => static function ($rootValue, array $args, Context $context) {
    $app = $context->get('app');
    return $app->make(UserRepository::class)->find($args['id']);
}
```

## 错误处理问题

### Q: 错误信息不显示

**问题**: 即使有错误也只返回通用错误信息

**解决方案**:
```php
// 启用调试模式
$server->setDebug(true);

// 或自定义错误格式化
$server->setErrorFormatter(function ($error, $debug) {
    return [
        'message' => $error->getMessage(),
        'code' => $error->getCode(),
        'trace' => $debug ? $error->getTraceAsString() : null,
    ];
});
```

### Q: 异常没有被捕获

**问题**: 异常直接抛出，没有转换为 GraphQL 错误

**解决方案**:
1. 确保添加了 ErrorHandlerMiddleware：
   ```php
   $server->addMiddleware(new ErrorHandlerMiddleware(true));
   ```
2. 在 Resolver 中抛出标准异常：
   ```php
   throw new \Exception('错误信息');
   ```

## 调试技巧

### 1. 启用详细日志

```php
$server = new Server([
    'debug' => true,
]);

$server->addMiddleware(new ErrorHandlerMiddleware(true));
$server->addMiddleware(new LoggingMiddleware($logger));
```

### 2. 使用 GraphiQL 调试

在浏览器中访问 GraphiQL 界面，可以：
- 查看 Schema 文档
- 测试查询
- 查看错误详情

### 3. 检查请求和响应

```php
// 在中间件中记录
public function process(RequestInterface $request, callable $next): ResponseInterface
{
    error_log('Request: ' . $request->getMethod() . ' ' . $request->getPath());
    error_log('Body: ' . $request->getBody());
    
    $response = $next($request);
    
    error_log('Response: ' . $response->getStatusCode());
    error_log('Body: ' . $response->getBody());
    
    return $response;
}
```

### 4. 使用 cURL 测试

```bash
# 测试查询
curl -X POST http://127.0.0.1:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ hello }"}'

# 测试变量
curl -X POST http://127.0.0.1:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query($id: ID!) { user(id: $id) { name } }",
    "variables": {"id": "1"}
  }'
```

## 获取帮助

如果以上解决方案无法解决问题，可以：

1. 📖 查看 [完整文档](../README.md)
2. 📖 阅读 [最佳实践](./best-practices.md)
3. 🐛 提交 Issue（包含错误信息和环境信息）
4. 💬 参与社区讨论

## 环境信息收集

在报告问题时，请提供以下信息：

```bash
# PHP 版本
php -v

# 扩展列表
php -m

# Composer 版本
composer --version

# 项目依赖
composer show
```

