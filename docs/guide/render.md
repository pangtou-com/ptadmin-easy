# 渲染处理【render】
::: tip
render 当前主要用于返回 `table`、`form` 组件的配置项，方便用户快速配置。
搭配 [PTAdmin/Build](https://www.pangtou.com) 构建表单的页面。
搭配 [PTAdmin/Admin](https://www.pangtou.com) 可完成`table`、`form`的快速构建。
:::
## `toFormHtml` 表单页面渲染
::: tip
需要 [PTAdmin/Build](https://www.pangtou.com) 支持。
:::

### 参数
- `$is_release`
    - 类型：`bool`
    - 默认：`false`
    - 描述：是否用于用户投稿，默认为 `false`，表示返回所有表单字段，`true` 表示只返回用户投稿字段。
### 示例
```PHP
// 获取表单页面
echo Easy::render($table_name)->toFormHtml();
```
## `toFormArray` 表单数据
### 参数
- `$is_release`
  - 类型：`bool`
  - 默认：`false`
  - 描述：是否用于用户投稿，默认为 `false`，表示返回所有表单字段，`true` 表示只返回用户投稿字段。

### 示例
```php
// 返回所有表单字段
$form_data = Easy::render($table_name)->toFormArray();
// 返回用户投稿字段
$form_data = Easy::render($table_name)->toFormArray(true);
```

## `getTableField` 返回列表字段
### 示例
```php
$search_field = Easy::render($table_name)->getTableField();
return $search_field;
```

## `getSearchField` 返回列表搜索字段
### 示例
```php
$search_field = Easy::render($table_name)->getSearchField();
return $search_field;
```














