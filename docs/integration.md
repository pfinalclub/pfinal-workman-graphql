# 框架集成指南

## Laravel

1. 注册服务提供者：
   ```php
   // config/app.php
   'providers' => [
       PFinalClub\WorkermanGraphQL\Integration\Laravel\GraphQLServiceProvider::class,
   ];
   ```

2. 发布配置文件：
   ```bash
   php artisan vendor:publish --tag=workerman-graphql-config
   ```

3. 在 `config/workerman-graphql.php` 中配置 Schema 与中间件。

4. 在路由文件启用：
   ```php
   Route::workermanGraphQL('/graphql');
   ```

## ThinkPHP 6

1. 注册服务：
   ```php
   // app/service.php
   return [
       PFinalClub\WorkermanGraphQL\Integration\ThinkPHP\GraphQLService::class,
   ];
   ```

2. 按需调整 `config/workerman_graphql.php`。

3. 默认注册 `GET|POST /graphql`，如需自定义路由，可在 `GraphQLService::boot` 中扩展。

## 自定义中间件

配置文件 `middleware` 字段支持以下形式：

- 直接填写中间件类名（容器自动实例化）
- 提供闭包 `fn($app) => new CustomMiddleware()`
- 预先绑定 `MiddlewareInterface` 实例

所有中间件均需实现 `MiddlewareInterface`，方法签名：

```php
public function process(RequestInterface $request, callable $next): ResponseInterface;
```

