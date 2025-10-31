# 最小完整示例

这是最简单的 GraphQL 服务器示例，展示了如何用最少的代码创建一个可运行的 GraphQL 服务器。

## 文件说明

- `server.php` - 服务器入口文件，包含完整的 GraphQL 服务器代码

## 运行步骤

### 1. 确保依赖已安装

```bash
cd /path/to/pfinal-workman-graphql
composer install
```

### 2. 启动服务器

```bash
cd examples/00-minimal
php server.php
```

看到以下输出表示启动成功：

```
GraphQL 服务器启动中...
访问地址: http://127.0.0.1:8080/graphql
按 Ctrl+C 停止服务器
```

### 3. 测试查询

#### 方式 1: 使用浏览器（GraphiQL）

打开浏览器访问：http://127.0.0.1:8080/graphql

在 GraphiQL 编辑器中输入：

```graphql
query {
  hello
}
```

点击执行按钮，应该看到结果：

```json
{
  "data": {
    "hello": "Hello, World!"
  }
}
```

#### 方式 2: 使用 cURL

```bash
curl -X POST http://127.0.0.1:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ hello }"}'
```

预期输出：

```json
{"data":{"hello":"Hello, World!"}}
```

#### 方式 3: 使用 GET 请求

```bash
curl "http://127.0.0.1:8080/graphql?query={hello}"
```

## 代码解析

### 1. 创建服务器

```php
$server = new Server([
    'server' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'worker_count' => 1,
    ],
    'debug' => true,
    'graphiql' => true,
]);
```

- `host`: 监听地址，`127.0.0.1` 表示只监听本地
- `port`: 监听端口
- `worker_count`: Worker 进程数，开发环境使用 1 即可
- `debug`: 启用调试模式，显示详细错误信息
- `graphiql`: 启用 GraphiQL 开发工具

### 2. 定义 Schema

```php
$server->configureSchema(function (CodeSchemaBuilder $builder): void {
    $builder->addQuery('hello', [
        'type' => Type::nonNull(Type::string()),
        'resolve' => static fn(): string => 'Hello, World!',
    ]);
});
```

- `addQuery`: 添加一个查询字段
- `type`: 定义返回类型（非空字符串）
- `resolve`: 定义解析函数，返回实际数据

### 3. 启动服务器

```php
$server->start();
```

调用 `start()` 方法后，服务器会开始监听请求。

## 扩展示例

### 添加带参数的查询

```php
$builder->addQuery('hello', [
    'type' => Type::nonNull(Type::string()),
    'args' => [
        'name' => ['type' => Type::string()],
    ],
    'resolve' => static fn($rootValue, array $args): string => 
        'Hello, ' . ($args['name'] ?? 'World') . '!',
]);
```

查询示例：

```graphql
query {
  hello(name: "GraphQL")
}
```

### 添加 Mutation

```php
$builder->addMutation('echo', [
    'type' => Type::nonNull(Type::string()),
    'args' => [
        'message' => ['type' => Type::nonNull(Type::string())],
    ],
    'resolve' => static fn($rootValue, array $args): string => $args['message'],
]);
```

Mutation 示例：

```graphql
mutation {
  echo(message: "Hello from GraphQL")
}
```

## 常见问题

### Q: 端口被占用怎么办？

A: 修改端口配置：

```php
'port' => 8081,  // 改为其他端口
```

### Q: 如何停止服务器？

A: 在终端中按 `Ctrl+C`

### Q: 为什么看不到 GraphiQL 界面？

A: 确保：
1. `graphiql` 配置为 `true`
2. 使用浏览器访问（不是 cURL）
3. 环境变量 `APP_ENV` 不是 `production`

## 下一步

- 📖 查看 [01-basic](../01-basic/) 了解更完整的示例
- 📖 查看 [02-sdl](../02-sdl/) 了解 SDL 方式定义 Schema
- 📖 阅读 [完整文档](../../docs/) 深入学习

