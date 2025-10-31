# SDL 方式示例

这个示例展示了如何使用 SDL（Schema Definition Language）文件定义 GraphQL Schema。

## 运行步骤

### 1. 启动服务器

```bash
cd examples/02-sdl
php server.php
```

### 2. 测试查询

访问 http://127.0.0.1:8081/graphql 或使用 cURL：

```bash
curl -X POST http://127.0.0.1:8081/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ ping }"}'
```

## 文件说明

- `schema.graphql` - GraphQL Schema 定义文件（SDL 格式）
- `server.php` - 服务器入口文件

## Schema 定义

### schema.graphql

```graphql
type Query {
  ping: String!
}
```

### 设置 Resolver

```php
$builder = (new SdlSchemaBuilder())
    ->fromFile(__DIR__ . '/schema.graphql')
    ->setResolver('Query', 'ping', static fn(): string => 'pong via SDL');
```

## SDL 方式的优势

1. **清晰的 Schema 定义**: Schema 以声明式的方式定义，易于阅读
2. **团队协作**: 前端和后端可以共享同一个 Schema 文件
3. **工具支持**: IDE 插件可以提供语法高亮和验证
4. **版本控制**: Schema 文件可以独立管理，便于版本控制

## 扩展示例

### 添加更多类型和字段

编辑 `schema.graphql`:

```graphql
type User {
  id: ID!
  name: String!
  email: String!
}

type Query {
  ping: String!
  users: [User!]!
  user(id: ID!): User
}
```

在 `server.php` 中添加 Resolver:

```php
$builder
    ->setResolver('Query', 'ping', fn() => 'pong')
    ->setResolver('Query', 'users', fn() => getAllUsers())
    ->setResolver('Query', 'user', fn($rootValue, array $args) => getUserById($args['id']))
    ->setResolver('User', 'email', fn($user) => $user['email_address']);
```

## 与代码式的区别

| 特性 | 代码式 | SDL 方式 |
|------|--------|----------|
| Schema 定义 | PHP 代码 | GraphQL 文件 |
| 类型安全 | ✅ 编译时检查 | ⚠️ 运行时检查 |
| IDE 支持 | ✅ 代码提示 | ✅ 语法高亮 |
| 团队协作 | ⚠️ 需要 PHP 知识 | ✅ 前端也能理解 |
| 灵活性 | ✅ 高度灵活 | ⚠️ 相对固定 |

## 选择建议

- **代码式**: 适合动态生成 Schema、需要复杂逻辑的场景
- **SDL 方式**: 适合静态 Schema、团队协作、前端对接的场景

## 下一步

- 📖 查看 [00-minimal](../00-minimal/) 了解最简示例
- 📖 查看 [01-basic](../01-basic/) 了解代码式完整示例
- 📖 阅读 [Schema 定义指南](../../docs/schema.md) 深入学习

