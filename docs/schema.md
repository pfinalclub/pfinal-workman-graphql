# Schema 定义指南

GraphQL Schema 定义了 API 的结构，本项目支持两种 Schema 定义方式：代码式和 SDL（Schema Definition Language）。

## 两种定义方式对比

| 特性 | 代码式 (CodeSchemaBuilder) | SDL (SdlSchemaBuilder) |
|------|---------------------------|------------------------|
| **适用场景** | 动态生成、复杂逻辑 | 静态定义、团队协作 |
| **类型安全** | ✅ 编译时检查 | ⚠️ 运行时检查 |
| **IDE 支持** | ✅ 代码提示 | ⚠️ 需要插件 |
| **可读性** | ⚠️ 较复杂 | ✅ 清晰直观 |
| **灵活性** | ✅ 高度灵活 | ⚠️ 相对固定 |

## 代码式定义 (CodeSchemaBuilder)

### 基础用法

```php
use GraphQL\Type\Definition\Type;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;

$builder = new CodeSchemaBuilder();

// 添加 Query
$builder->addQuery('hello', [
    'type' => Type::nonNull(Type::string()),
    'args' => [
        'name' => ['type' => Type::string()],
    ],
    'resolve' => static fn($rootValue, array $args): string => 
        'Hello ' . ($args['name'] ?? 'World'),
    'description' => '问候查询',
]);

// 添加 Mutation
$builder->addMutation('createUser', [
    'type' => $userType,
    'args' => [
        'name' => ['type' => Type::nonNull(Type::string())],
        'email' => ['type' => Type::nonNull(Type::string())],
    ],
    'resolve' => static fn($rootValue, array $args) => createUser($args),
]);

// 构建 Schema
$schema = $builder->build();
```

### 自定义类型定义

```php
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

// 定义 User 类型
$userType = new ObjectType([
    'name' => 'User',
    'fields' => [
        'id' => Type::nonNull(Type::id()),
        'name' => Type::nonNull(Type::string()),
        'email' => Type::nonNull(Type::string()),
        'createdAt' => [
            'type' => Type::nonNull(Type::string()),
            'resolve' => static fn($user) => $user['created_at']->format('Y-m-d H:i:s'),
        ],
    ],
]);

// 注册到 TypeRegistry
$builder->registerType('User', $userType);

// 使用注册的类型
$builder->addQuery('user', [
    'type' => $builder->getTypeRegistry()->get('User'),
    'args' => [
        'id' => ['type' => Type::nonNull(Type::id())],
    ],
    'resolve' => static fn($rootValue, array $args) => getUserById($args['id']),
]);
```

### 复杂类型示例

#### 列表和嵌套类型

```php
// 定义 Post 类型
$postType = new ObjectType([
    'name' => 'Post',
    'fields' => [
        'id' => Type::nonNull(Type::id()),
        'title' => Type::nonNull(Type::string()),
        'content' => Type::string(),
        'author' => [
            'type' => $userType,  // 嵌套 User 类型
            'resolve' => static fn($post) => getUserById($post['author_id']),
        ],
    ],
]);

$builder->registerType('Post', $postType);

// 返回列表
$builder->addQuery('posts', [
    'type' => Type::listOf($builder->getTypeRegistry()->get('Post')),
    'args' => [
        'limit' => ['type' => Type::int(), 'defaultValue' => 10],
        'offset' => ['type' => Type::int(), 'defaultValue' => 0],
    ],
    'resolve' => static function ($rootValue, array $args) {
        return getPosts($args['limit'], $args['offset']);
    },
]);
```

#### 枚举类型

```php
use GraphQL\Type\Definition\EnumType;

$statusType = new EnumType([
    'name' => 'UserStatus',
    'values' => [
        'ACTIVE' => ['value' => 'active'],
        'INACTIVE' => ['value' => 'inactive'],
        'SUSPENDED' => ['value' => 'suspended'],
    ],
]);

$builder->registerType('UserStatus', $statusType);

$builder->addQuery('usersByStatus', [
    'type' => Type::listOf($userType),
    'args' => [
        'status' => ['type' => $builder->getTypeRegistry()->get('UserStatus')],
    ],
    'resolve' => static fn($rootValue, array $args) => 
        getUsersByStatus($args['status'] ?? 'active'),
]);
```

#### 输入类型

```php
use GraphQL\Type\Definition\InputObjectType;

$createUserInput = new InputObjectType([
    'name' => 'CreateUserInput',
    'fields' => [
        'name' => Type::nonNull(Type::string()),
        'email' => Type::nonNull(Type::string()),
        'password' => Type::nonNull(Type::string()),
    ],
]);

$builder->registerType('CreateUserInput', $createUserInput);

$builder->addMutation('createUser', [
    'type' => $userType,
    'args' => [
        'input' => ['type' => Type::nonNull($builder->getTypeRegistry()->get('CreateUserInput'))],
    ],
    'resolve' => static function ($rootValue, array $args) {
        $input = $args['input'];
        return createUser($input['name'], $input['email'], $input['password']);
    },
]);
```

### Resolver 参数说明

Resolver 函数接收三个参数：

```php
'resolve' => function (
    $rootValue,        // 父级解析器的返回值，Query 的 rootValue 为 null
    array $args,       // 字段的参数
    Context $context   // 请求上下文，包含请求信息和自定义数据
) {
    // $rootValue: 用于嵌套查询
    // $args: 字段参数，例如 { user(id: "1") } 中的 id
    // $context: 包含 $request 和自定义值（如 $context->get('user')）
    
    return $result;
}
```

### 示例：嵌套查询

```php
$userType->fields['posts'] = [
    'type' => Type::listOf($postType),
    'resolve' => static function ($user, array $args, Context $context) {
        // $user 是父级 User 对象
        return getPostsByUserId($user['id']);
    },
];

// 查询示例
// query {
//   user(id: "1") {
//     name
//     posts {
//       title
//     }
//   }
// }
```

## SDL 方式定义 (SdlSchemaBuilder)

### 基础用法

创建 `schema.graphql` 文件：

```graphql
type User {
  id: ID!
  name: String!
  email: String!
  posts: [Post!]!
}

type Post {
  id: ID!
  title: String!
  content: String
  author: User!
}

type Query {
  users: [User!]!
  user(id: ID!): User
  posts: [Post!]!
}

type Mutation {
  createUser(name: String!, email: String!): User!
  createPost(title: String!, content: String, authorId: ID!): Post!
}
```

使用 SdlSchemaBuilder：

```php
use PFinalClub\WorkermanGraphQL\Schema\SdlSchemaBuilder;

$builder = (new SdlSchemaBuilder())
    ->fromFile(__DIR__ . '/schema.graphql')
    ->setResolver('Query', 'users', fn() => getAllUsers())
    ->setResolver('Query', 'user', fn($rootValue, array $args) => getUserById($args['id']))
    ->setResolver('Query', 'posts', fn() => getAllPosts())
    ->setResolver('Mutation', 'createUser', fn($rootValue, array $args) => createUser($args))
    ->setResolver('Mutation', 'createPost', fn($rootValue, array $args) => createPost($args))
    ->setResolver('User', 'posts', fn($user) => getPostsByUserId($user['id']))
    ->setResolver('Post', 'author', fn($post) => getUserById($post['author_id']));
```

### 从字符串加载

```php
$sdl = <<<'GRAPHQL'
type Query {
  hello: String!
}
GRAPHQL;

$builder = (new SdlSchemaBuilder())
    ->fromString($sdl)
    ->setResolver('Query', 'hello', fn() => 'Hello World');
```

### 批量设置 Resolver

```php
$builder->setResolvers([
    'Query' => [
        'users' => fn() => getAllUsers(),
        'user' => fn($rootValue, array $args) => getUserById($args['id']),
    ],
    'Mutation' => [
        'createUser' => fn($rootValue, array $args) => createUser($args),
    ],
    'User' => [
        'posts' => fn($user) => getPostsByUserId($user['id']),
    ],
]);
```

### Type Decorator

允许在构建 Schema 时修改类型配置：

```php
$builder->setTypeDecorator(function (array $typeConfig) {
    // 可以修改类型配置
    if ($typeConfig['name'] === 'User') {
        $typeConfig['description'] = '用户类型';
    }
    return $typeConfig;
});
```

## 混合使用

你可以同时使用两种方式：

```php
// 使用 SDL 定义基础结构
$sdlBuilder = (new SdlSchemaBuilder())
    ->fromFile(__DIR__ . '/schema.graphql');

// 使用代码式添加动态字段
$codeBuilder = new CodeSchemaBuilder();
$codeBuilder->addQuery('dynamicQuery', [
    'type' => Type::string(),
    'resolve' => fn() => getDynamicData(),
]);

// 然后根据需要选择使用哪个
$server->useSchemaBuilder($sdlBuilder);
// 或
$server->useSchemaBuilder($codeBuilder);
```

## 类型系统

### 标量类型

GraphQL 内置标量类型：

- `String` - 字符串
- `Int` - 整数
- `Float` - 浮点数
- `Boolean` - 布尔值
- `ID` - 唯一标识符

```php
// 非空类型
Type::nonNull(Type::string())

// 列表类型
Type::listOf(Type::string())

// 非空列表
Type::nonNull(Type::listOf(Type::string()))

// 非空列表，元素非空
Type::listOf(Type::nonNull(Type::string()))
```

### 自定义标量类型

```php
use GraphQL\Type\Definition\ScalarType;

$dateType = new ScalarType([
    'name' => 'Date',
    'serialize' => static fn($value) => $value->format('Y-m-d'),
    'parseValue' => static fn($value) => new \DateTime($value),
    'parseLiteral' => static fn($node) => new \DateTime($node->value),
]);

$builder->registerType('Date', $dateType);
```

## 最佳实践

### 1. 使用描述信息

```php
$builder->addQuery('user', [
    'type' => $userType,
    'args' => [
        'id' => [
            'type' => Type::nonNull(Type::id()),
            'description' => '用户唯一标识符',
        ],
    ],
    'description' => '根据 ID 获取用户信息',
    'resolve' => static fn($rootValue, array $args) => getUserById($args['id']),
]);
```

### 2. 参数验证

```php
'resolve' => static function ($rootValue, array $args, Context $context) {
    $id = $args['id'];
    
    if (!isValidId($id)) {
        throw new \InvalidArgumentException('无效的用户 ID');
    }
    
    $user = getUserById($id);
    if (!$user) {
        throw new \Exception('用户不存在');
    }
    
    return $user;
}
```

### 3. 使用 Context 传递数据

```php
// 设置 Context Factory
$server->setContextFactory(function ($request) {
    return new Context($request, [
        'user' => getCurrentUser($request),
        'db' => getDatabaseConnection(),
    ]);
});

// 在 Resolver 中使用
'resolve' => static function ($rootValue, array $args, Context $context) {
    $user = $context->get('user');
    $db = $context->get('db');
    
    if (!$user || !$user->isAdmin()) {
        throw new \Exception('权限不足');
    }
    
    return $db->query('SELECT * FROM users');
}
```

### 4. 错误处理

```php
'resolve' => static function ($rootValue, array $args, Context $context) {
    try {
        return performOperation($args);
    } catch (\Exception $e) {
        // GraphQL 会自动将异常转换为错误响应
        throw new \Exception('操作失败: ' . $e->getMessage());
    }
}
```

### 5. 性能优化

```php
// 使用数据加载器避免 N+1 查询
use function GraphQL\Utils\BuildSchema;

// 在 Resolver 中使用批量加载
'resolve' => static function ($rootValue, array $args, Context $context) {
    $loader = $context->get('userLoader');
    return $loader->load($args['id']);
}
```

## 常见问题

### Q: 如何定义 Union 类型？

A: 使用 webonyx/graphql-php 的 UnionType：

```php
use GraphQL\Type\Definition\UnionType;

$searchResultType = new UnionType([
    'name' => 'SearchResult',
    'types' => [$userType, $postType],
    'resolveType' => static fn($value) => 
        isset($value['email']) ? $userType : $postType,
]);
```

### Q: 如何实现分页？

A: 定义分页类型：

```php
$pageInfoType = new ObjectType([
    'name' => 'PageInfo',
    'fields' => [
        'hasNextPage' => Type::nonNull(Type::boolean()),
        'hasPreviousPage' => Type::nonNull(Type::boolean()),
        'startCursor' => Type::string(),
        'endCursor' => Type::string(),
    ],
]);

$userConnectionType = new ObjectType([
    'name' => 'UserConnection',
    'fields' => [
        'edges' => Type::listOf($userEdgeType),
        'pageInfo' => Type::nonNull($pageInfoType),
    ],
]);
```

### Q: SDL 文件中如何使用自定义标量？

A: 在 SDL 中直接使用，然后通过 Type Decorator 或直接注册：

```graphql
scalar Date

type User {
  id: ID!
  createdAt: Date!
}
```

## 下一步

- 📖 学习 [中间件使用](./middleware.md)
- 📖 查看 [配置选项](./configuration.md)
- 📖 阅读 [最佳实践](./best-practices.md)

