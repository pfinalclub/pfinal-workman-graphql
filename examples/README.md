# 示例目录

本目录包含多个 GraphQL 服务器示例，从最简单的开始，逐步展示更多功能。

## 示例列表

### [00-minimal](./00-minimal/) - 最小完整示例 ⭐ 推荐新手

**最简单的 GraphQL 服务器示例**

- ✅ 最少的代码（约 50 行）
- ✅ 完整的可运行示例
- ✅ 包含详细注释
- ✅ 适合快速上手

**特性：**
- 基本的服务器配置
- 一个简单的查询（hello）
- 自动启用 GraphiQL

**运行：**
```bash
cd examples/00-minimal
php server.php
```

### [01-basic](./01-basic/) - 基础示例

**完整的 GraphQL 服务器示例**

- ✅ 使用 CodeSchemaBuilder 定义 Schema
- ✅ 带参数的查询
- ✅ 中间件配置（错误处理、CORS）
- ✅ 完整的服务器配置

**特性：**
- 错误处理中间件
- CORS 中间件
- 查询参数支持
- 描述信息

**运行：**
```bash
cd examples/01-basic
php server.php
```

### [02-sdl](./02-sdl/) - SDL 方式示例

**使用 Schema Definition Language 定义 Schema**

- ✅ SDL 文件定义 Schema
- ✅ SdlSchemaBuilder 使用
- ✅ Resolver 绑定
- ✅ 展示 SDL 方式的优势

**特性：**
- Schema 文件（schema.graphql）
- Resolver 设置
- 类型定义

**运行：**
```bash
cd examples/02-sdl
php server.php
```

## 示例对比

| 示例 | 代码量 | 功能 | 适用场景 |
|------|--------|------|----------|
| 00-minimal | ~50 行 | 基础查询 | 快速上手、学习入门 |
| 01-basic | ~40 行 | 查询+中间件 | 完整功能、生产参考 |
| 02-sdl | ~30 行 | SDL Schema | SDL 方式、团队协作 |

## 快速开始

### 对于新手

推荐从 `00-minimal` 开始：

```bash
cd examples/00-minimal
php server.php
```

然后访问 http://127.0.0.1:8080/graphql 查看 GraphiQL 界面。

### 测试查询

使用 cURL 测试：

```bash
# 最小示例
curl -X POST http://127.0.0.1:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ hello }"}'

# 基础示例（带参数）
curl -X POST http://127.0.0.1:8080/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "query(\$name: String) { hello(name: \$name) }", "variables": {"name": "World"}}'

# SDL 示例
curl -X POST http://127.0.0.1:8081/graphql \
  -H "Content-Type: application/json" \
  -d '{"query": "{ ping }"}'
```

## 端口说明

为了避免冲突，不同示例使用不同端口：

- `00-minimal`: 8080
- `01-basic`: 8080
- `02-sdl`: 8081

同时运行多个示例时，需要确保端口不冲突。

## 代码结构

每个示例都包含：

```
example-name/
├── server.php      # 服务器入口文件
└── README.md       # 示例说明文档
```

部分示例还包含：

```
example-name/
├── schema.graphql  # SDL Schema 文件（SDL 方式）
├── config.php      # 配置文件（如有）
└── ...
```

## 学习路径

1. **第一步**: 运行 `00-minimal`，理解基本概念
2. **第二步**: 查看 `01-basic`，了解中间件和完整配置
3. **第三步**: 尝试 `02-sdl`，学习 SDL 方式
4. **第四步**: 根据需求修改和扩展示例

## 常见问题

### Q: 如何同时运行多个示例？

A: 修改端口配置，确保每个示例使用不同的端口。

### Q: 如何停止服务器？

A: 在终端中按 `Ctrl+C`

### Q: 端口被占用怎么办？

A: 修改 `server.php` 中的端口配置：

```php
'port' => 8082,  // 改为其他可用端口
```

### Q: 如何添加更多功能？

A: 参考文档和示例：
- Schema 定义：查看 [01-basic](./01-basic/) 和 [文档](../../docs/schema.md)
- 中间件：查看 [01-basic](./01-basic/) 和 [文档](../../docs/middleware.md)
- 配置：查看 [文档](../../docs/configuration.md)

## 下一步

- 📖 阅读 [完整文档](../docs/) 了解更多功能
- 📖 查看 [快速开始指南](../docs/quickstart.md)
- 📖 学习 [Schema 定义](../docs/schema.md)
- 📖 了解 [中间件使用](../docs/middleware.md)

---

**提示**: 建议按照示例编号顺序学习，从简单到复杂，逐步掌握 GraphQL 服务器的使用方法。

