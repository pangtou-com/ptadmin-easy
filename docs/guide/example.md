# 开发示例
> 首先我们通过一个开发示例来演示如何完成一个自定模型的`CURD`操作。
> 需求为一个企业宣传站点，需要发布企业宣传文章。
## 1、创建模型
> 创建一个【article】分组的文章模型

示例代码：
```php
$data = [
    "title" => "文章模型", // 模型标题
    "table_name" => "article", // 模型标识名称(数据表名称)   
    "extra" => [], // 扩展字段
    "weight" => 1 // 排序值
    "intro" => "这个是一个文章模型用于发布文章内容" // 描述信息
];
Easy::mod()->store($data, "article");
```
## 2、创建字段
> 创建模型关联的字段信息
示例代码：
```php
$data = [
    "title" => "标题", // 字段标题
    "name" => "field1", // 字段名称   
    "type" => "", // 字段类型
    "default_val" => "", // 默认值
    "is_release" => 0 // 是否投稿
    "is_search" => 0 // 是否用于搜索
    "is_table" => 0 // 是否在列表展示
    "is_required" => 0 // 是否必填
    "status" => 0 // 是否有效
    "weight" => 99 // 排序权重
    "mod_id" => 1 // 所属模型ID
    "intro" => "这个是一个文章模型用于发布文章内容" // 描述信息
];
Easy::field()->store($data);
```
## 3、根据新增的模型完成 `CURD` 操作
> ``EasyForm::handler($table_name)`` 数据处理器用于处理创建模型的`CURD`操作
- 新增数据
```php
# 根据设置的模型规则自动验证数据并处理新增
Easy::handler($table_name)->store($data)
```
- 读取数据

1、返回`laravel`的`query`对象
```php
// 返回模型的构建对象可使用`laravel`的模型方法进行操作
$filterMap = Easy::handler($type)->newQuery()
$filterMap->where("status", 0)
$data = $filterMap->paginate();
```
2、内置方法
```php
// 返回翻页数据列表
$data = Easy::handler($table_name)->lists($search);
// 返回单条数据
$data = Easy::handler($table_name)->show(1);
```
- 更新数据
```php
Easy::handler($table_name)->edit($data, $id);
```
- 删除数据
```php
Easy::handler($table_name)->delete($ids);
```

::: tip
后续将开发更多的功能模块
:::

