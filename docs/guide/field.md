# 模型字段管理
::: tip
对模型字段的支持，包括：字段新增、修改、、删除、详情、列表等操作
:::
通过：``Easy::field()``获取模型字段对象，与模型字段相关的操作都在这里 

## `store` 添加字段
### 参数说明
- ``$data``
  - 说明：添加字段的数据内容
  - 类型：`array`
  - 数据结构
    | 参数名称     | 数据类型 | 是否必填 | 描述信息 |
    | ----------- | ------ | :-----: | -------- |
    | title       | string | 是      | 字段标题 |
    | name        | string | 是      | 字段名称，当前模型唯一，不可重复 |
    | type        | string | 是      | 字段类型，参考：[字段类型](/docs/field/type) |
    | mod_id      | int    | 是      | 模型ID |
    | default_val | string | 否      | 默认值 |
    | is_release  | int    | 否      | 是否支持投稿 |
    | is_search   | int    | 否      | 是否支持搜素 |
    | is_table    | int    | 否      | 是否在列表展示 |
    | is_required | int    | 否      | 是否必填 |
    | extra       | array  | 否      | 模块扩展字段信息 |
    | weight      | int    | 否      | 权重 |
    | status      | int    | 否      | 状态 |
    | intro       | string | 否      | 描述备注信息 |

### 示例
```php
$data = []
$data['title'] = '标题';
$data['name'] = 'title';
$data['type'] = 'text';
$data['mod_id'] = 1;
$data['is_release'] = 1;
$data['is_search'] = 1;
$data['is_table'] = 1;
$data['is_required'] = 1;
$data['extra'] = [];
$data['weight'] = 1;
$data['status'] = 1;
$field = Easy::field()->store($data);
```

## `edit` 修改字段
### 参数说明
- ``$data`` 修改字段信息，只能修改部分字段，参数参考新增字段
- ``$id`` 字段ID

### 示例
```php
$field = Easy::field()->edit($data, $id);
```

## `lists` 获取字段列表
### 参数说明
- ``$search`` 查询参数
- ``$mod_id`` 模型ID

### 示例
```php
$field = Easy::field()->lists($search, $mod_id);
```

## `find` 获取字段详情
### 参数说明
- ``$id`` 字段ID

### 示例
```php
$field = Easy::field()->find($id);
```

## 删除数据
### `delete`
#### 参数说明
- ``$id`` 字段ID

#### 示例
```php
$field = Easy::field()->delete($id);
```

### `thoroughDel`
#### 参数说明
- ``$id`` 字段ID

#### 示例
```php
$field = Easy::field()->thoroughDel($id);
```
### `restore`
#### 参数说明
- ``$id`` 字段ID

#### 示例
```php
$field = Easy::field()->restore($id);
```
