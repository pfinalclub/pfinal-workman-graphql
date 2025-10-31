# 基础示例

这是一个完整的 GraphQL 服务器示例，展示了：

- 如何使用 CodeSchemaBuilder 定义 Schema
- 如何添加带参数的查询
- 如何使用中间件（错误处理、CORS）
- 如何配置服务器选项

## 运行步骤

### 1. 启动服务器

```bash
cd examples/01-basic
php server.php
```

### 2. 测试查询

访问 http://127.0.0.1:8080/graphql 使用 GraphiQL，或使用 cURL：

```bash
# 基础查询
curl -X POST http://127.0.0.1:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ hello }"}'

# 带参数的查询
curl -X POST http://127.0.0.1:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "query(\$name: String) { hello(name: \$name) }", "variables": {"name": "GraphQL"}}'
```

## 代码特性

### 1. 中间件配置

```php
$server
    ->addMiddleware(new ErrorHandlerMiddleware(true))  // 错误处理
    ->addMiddleware(new CorsMiddleware());             // CORS 支持
```

### 2. 带参数的查询

```php
$builder->addQuery('hello', [
    'type' => Type::nonNull(Type::string()),
    'args' => [
        'name' => [
            'type' => Type::string(),
            'description' => 'Name to greet',
        ],
    ],
    'resolve' => static fn($rootValue, array $args): string => 
        'Hello ' . ($args['name'] ?? 'World'),
    'description' => 'A simple greeting field',
]);
```

### 3. 服务器配置

- 错误处理中间件：捕获异常并统一格式化
- CORS 中间件：处理跨域请求
- 调试模式：显示详细错误信息
- GraphiQL：开发工具界面

## 与最小示例的区别

- ✅ 添加了中间件支持
- ✅ 查询支持参数
- ✅ 包含描述信息
- ✅ 更完整的配置

