# 安装
::: tip laravel 版本要求
PTAdmin/Easy 是基于 laravel 8.5 以上版本开发的，请确保你的 laravel 版本符合要求。
:::
> PTAdmin/Easy 提供的数据层面操作。配合使用 [PTAdmin/Admin](https://www.pangtou.com)可完成表单页面和列表页面的渲染处理

## 使用composer 安装
```bash
composer require ptadmin/easy
```
## 资源发布
> 发布相关迁移文件至项目目录中

```shell
# 发布全部文件
php artisan vendor:publish --provider="PTAdmin\Easy\Providers\EasyServiceProviders"
# 1、指定发布迁移文件
php artisan vendor:publish --provider="PTAdmin\Easy\Providers\EasyServiceProviders" --tag="migrations"
# 2、指定发布配置文件
php artisan vendor:publish --provider="PTAdmin\Easy\Providers\EasyServiceProviders" --tag="config"
```
## 执行迁移
> 使用 `php artisan migrate` 命令，执行迁移文件生成相关数据表信息
