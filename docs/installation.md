# 安装与初始化

## 要求

- PHP 8.0+
- Composer
- 建议安装 `ext-json`

## 安装步骤

1. 通过 Composer 引入：

   ```bash
   composer require pfinalclub/workerman-graphql
   ```

2. 若需运行 Workerman 独立服务，请确保开启必要的进程权限，并根据环境调整 `host` 与 `port`。

3. 在项目入口中创建服务器实例并配置 Schema：

   ```php
   use PFinalClub\WorkermanGraphQL\Server;

   $server = new Server();
   $server->configureSchema(fn($builder) => require __DIR__ . '/graphql.php');
   $server->start();
   ```

## 常用目录结构建议

```
project/
├── app/
├── config/
├── graphql/
│   ├── schema.graphql
│   └── resolvers.php
├── public/
└── vendor/
```

在 `graphql/resolvers.php` 中返回闭包或解析器对象，用于绑定到 `CodeSchemaBuilder` 或 `SdlSchemaBuilder`。

