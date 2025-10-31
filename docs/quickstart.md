# 快速开始

本指南将帮助你快速搭建一个 GraphQL 服务器。

## 5 分钟快速上手

### 步骤 1: 创建入口文件

创建 `server.php` 文件：

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use GraphQL\Type\Definition\Type;
use PFinalClub\WorkermanGraphQL\Middleware\CorsMiddleware;
use PFinalClub\WorkermanGraphQL\Middleware\ErrorHandlerMiddleware;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Server;

// 创建服务器实例
$server = new Server([
    'server' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'worker_count' => 1,
    ],
    'debug' => true,
    'graphiql' => true,
]);

// 添加中间件
$server
    ->addMiddleware(new ErrorHandlerMiddleware(true))
    ->addMiddleware(new CorsMiddleware());

// 配置 Schema
$server->configureSchema(function (CodeSchemaBuilder $builder): void {
    // 添加一个简单的查询
    $builder->addQuery('hello', [
        'type' => Type::nonNull(Type::string()),
        'args' => [
            'name' => [
                'type' => Type::string(),
                'description' => '要问候的名字',
            ],
        ],
        'resolve' => static fn($rootValue, array $args): string => 
            'Hello ' . ($args['name'] ?? 'World'),
        'description' => '一个简单的问候查询',
    ]);
});

// 启动服务器
$server->start();
```

### 步骤 2: 启动服务器

```bash
php server.php
```

### 步骤 3: 测试查询

#### 使用 GraphiQL（浏览器）

打开浏览器访问 `http://127.0.0.1:8080/graphql`，会自动加载 GraphiQL 界面。

在查询编辑器中输入：

```graphql
query {
  hello(name: "GraphQL")
}
```

点击执行，应该看到：

```json
{
  "data": {
    "hello": "Hello GraphQL"
  }
}
```

#### 使用 cURL

```bash
curl -X POST http://127.0.0.1:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ hello(name: \"GraphQL\") }"}'
```

#### 使用 GET 请求

```bash
curl "http://127.0.0.1:8080/graphql?query={hello(name:\"GraphQL\")}"
```

## 完整示例

### 示例 1: 用户查询系统

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Server;

// 模拟数据
$users = [
    '1' => ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com'],
    '2' => ['id' => '2', 'name' => 'Bob', 'email' => 'bob@example.com'],
];

// 定义 User 类型
$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::nonNull(Type::id()),
        'name' => Type::nonNull(Type::string()),
        'email' => Type::nonNull(Type::string()),
    ],
]);

$server = new Server(['debug' => true]);

$server->configureSchema(function (CodeSchemaBuilder $builder) use ($users, $userType): void {
    // 注册 User 类型
    $builder->registerType('User', $userType);
    
    // 查询：获取用户列表
    $builder->addQuery('users', [
        'type' => Type::listOf($builder->getTypeRegistry()->get('User')),
        'resolve' => static fn(): array => array_values($users),
    ]);
    
    // 查询：根据 ID 获取用户
    $builder->addQuery('user', [
        'type' => $builder->getTypeRegistry()->get('User'),
        'args' => [
            'id' => ['type' => Type::nonNull(Type::id())],
        ],
        'resolve' => static fn($rootValue, array $args) => $users[$args['id']] ?? null,
    ]);
    
    // 变更：创建用户
    $builder->addMutation('createUser', [
        'type' => $builder->getTypeRegistry()->get('User'),
        'args' => [
            'name' => ['type' => Type::nonNull(Type::string())],
            'email' => ['type' => Type::nonNull(Type::string())],
        ],
        'resolve' => static function ($rootValue, array $args) use (&$users): array {
            $id = (string) (count($users) + 1);
            $user = [
                'id' => $id,
                'name' => $args['name'],
                'email' => $args['email'],
            ];
            $users[$id] = $user;
            return $user;
        },
    ]);
});

$server->start();
```

**测试查询：**

```graphql
# 获取所有用户
query {
  users {
    id
    name
    email
  }
}

# 根据 ID 获取用户
query {
  user(id: "1") {
    id
    name
    email
  }
}

# 创建用户
mutation {
  createUser(name: "Charlie", email: "charlie@example.com") {
    id
    name
    email
  }
}
```

### 示例 2: 使用 SDL 方式

创建 `schema.graphql` 文件：

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

type Mutation {
  createUser(name: String!, email: String!): User!
}
```

创建 `server.php`：

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use PFinalClub\WorkermanGraphQL\Schema\SdlSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Server;

$users = [
    '1' => ['id' => '1', 'name' => 'Alice', 'email' => 'alice@example.com'],
    '2' => ['id' => '2', 'name' => 'Bob', 'email' => 'bob@example.com'],
];

$builder = (new SdlSchemaBuilder())
    ->fromFile(__DIR__ . '/schema.graphql')
    ->setResolver('Query', 'users', fn() => array_values($users))
    ->setResolver('Query', 'user', fn($rootValue, array $args) => $users[$args['id']] ?? null)
    ->setResolver('Mutation', 'createUser', function ($rootValue, array $args) use (&$users) {
        $id = (string) (count($users) + 1);
        $user = ['id' => $id, 'name' => $args['name'], 'email' => $args['email']];
        $users[$id] = $user;
        return $user;
    });

$server = new Server();
$server->useSchemaBuilder($builder);
$server->start();
```

## 使用 Context 传递数据

在 Resolver 中访问请求信息：

```php
$server->setContextFactory(function ($request) {
    // 从请求中提取用户信息（例如从 JWT Token）
    $authHeader = $request->getHeader('Authorization');
    $user = $authHeader ? parseToken($authHeader) : null;
    
    return new Context($request, [
        'user' => $user,
        'ip' => $request->getHeader('X-Real-IP'),
    ]);
});

// 在 Resolver 中使用
$builder->addQuery('me', [
    'type' => $userType,
    'resolve' => function ($rootValue, array $args, Context $context) {
        $user = $context->get('user');
        if (!$user) {
            throw new \Exception('未认证');
        }
        return $user;
    },
]);
```

## 自定义错误处理

```php
$server->setErrorFormatter(function ($error, $debug) {
    return [
        'message' => $error->getMessage(),
        'code' => $error->getCode(),
        // 只在调试模式下显示详细信息
        'trace' => $debug ? $error->getTraceAsString() : null,
    ];
});
```

## Schema 缓存配置

```php
// 设置缓存 TTL（秒）
$server->setSchemaCacheTTL(3600); // 1 小时

// 手动清除缓存
$server->clearSchemaCache();
```

## 下一步

- 📖 深入学习 [Schema 定义](./schema.md)
- 📖 了解 [中间件系统](./middleware.md)
- 📖 查看 [框架集成](./integration.md)
- 📖 阅读 [最佳实践](./best-practices.md)

