<?php

declare(strict_types=1);

/**
 * 最小完整示例
 * 
 * 这是一个最简单的 GraphQL 服务器示例，演示了：
 * - 如何创建一个基本的 GraphQL 服务器
 * - 如何定义一个简单的查询
 * - 如何启动服务器
 * 
 * 运行方式：
 *   php server.php
 * 
 * 测试方式：
 *   浏览器访问: http://127.0.0.1:8080/graphql
 *   或使用 curl:
 *   curl -X POST http://127.0.0.1:8080/graphql \
 *     -H "Content-Type: application/json" \
 *     -d '{"query": "{ hello }"}'
 */

require __DIR__ . '/../../vendor/autoload.php';

use GraphQL\Type\Definition\Type;
use PFinalClub\WorkermanGraphQL\Schema\CodeSchemaBuilder;
use PFinalClub\WorkermanGraphQL\Server;

// 创建服务器实例
$server = new Server([
    'server' => [
        'host' => '127.0.0.1',  // 监听地址
        'port' => 8080,          // 监听端口
        'worker_count' => 1,    // Worker 进程数（开发环境使用 1 即可）
    ],
    'debug' => true,             // 启用调试模式
    'graphiql' => true,         // 启用 GraphiQL（开发工具）
]);

// 配置 GraphQL Schema
$server->configureSchema(function (CodeSchemaBuilder $builder): void {
    // 定义一个简单的查询
    $builder->addQuery('hello', [
        'type' => Type::nonNull(Type::string()),  // 返回类型：非空字符串
        'resolve' => static fn(): string => 'Hello, World!',  // Resolver 函数
    ]);
});

// 启动服务器
echo "GraphQL 服务器启动中...\n";
echo "访问地址: http://127.0.0.1:8080/graphql\n";
echo "按 Ctrl+C 停止服务器\n\n";

$server->start();

