# 数据处理【handler】
::: tip
数据处理对根据模型管理中自定义的模型进行`CURD`等操作。
:::
通过 ``Easy::handler($table_name)`` 获取数据处理对象。

## `store`【新增数据】
### 参数说明
- `$data`: 保存数据
  - 类型： `array`
  - 描述： 根据自定义模型设置的字段信息，如： `['name'=>'test','age'=>18]`
  - 必填： `是`
- `$isValidate`：是否校验
  - 类型： `bool`
  - 描述： 用于校验数据，如果不开启则不坐数据校验

### 示例
```php
# 保存数据，默认需要校验数据有效性
Easy::handler($table_name)->store($data)

# 保存数据，不校验数据有效性
Easy::handler($table_name)->store($data, false)
```

## 编辑数据
### 参数说明
- `$data`: 保存数据
- `$id`: 数据ID
- `$isValidate`：是否校验

### 示例
```php
Easy::handler($table_name)->edit($data, $id)
```

## 数据详情
### 参数说明
- `$id`: 数据ID

### 示例
```php
Easy::handler($table_name)->show($id)
```

## 数据列表
### 参数说明
- `$search`: 查询条件
- `$order`: 排序方式，默认排序方式为：`id desc`

### 示例
```php
Easy::handler($table_name)->list($search, $order)
```

## 删除数据
### 参数说明
- `$ids`: 数据ID
  - 类型： `array` ｜ `int`
  - 描述： 需要删除的数据ID，如： `[1,2,3]` 或 `1`

### 示例
```php
Easy::handler($table_name)->delete($ids)
```

