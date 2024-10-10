# 模型
::: tip
当前完成了对模型的支持，包括：模型新增、模型修改、模型删除、模型详情、模型列表等操作
:::
通过：``Easy::mod()``获取模型对象，与模型相关的操作都在这里 

## ``store``【新增模型】

### 参数说明
- `$data` 存储的模型数据
    - 类型：`array`
    - 说明：存储的模块数据
    - 数组参数：
      | 参数名称 | 数据类型 | 是否必填 | 描述信息 | 
      | -------- | -------- | :--------: | -------- |
      | title   | string | 是      | 模型标题 |
      | table_name  | string | 是      | 模型数据表名称，默认为表名小写，如：`user` |
      | extra  | array | 否      | 模块扩展字段信息 |
      | weight  | int | 否      | 权重 |
      | intro  | string | 否      | 描述备注信息 |


- `$group` 模型分组名称
    - 类型：`string` 
    - 说明：用于区分不同应用模块模型数据


### 使用示例
```php
Easy::mod()->store($data, $group);
```

## ``edit``【编辑模型】
::: tip
编辑模型时，不能修改模型表名
:::
### 参数说明
- `$data` 参考新增时参数说明
- `$id` 模型ID
### 使用示例
```php
Easy::mod()->edit($data, $id);
```
## 模型删除
::: tip
模型的删除都是逻辑删除，不会删除数据表，只是将数据标记为删除状态。可以通过 ``restore``恢复，``thoroughDel``彻底删除
:::
### ``delete``【删除模型】
#### 参数说明
- `$id` 模型ID

#### 使用示例
```php
Easy::mod()->delete($id);
```
### ``restore``【恢复模型】
#### 参数说明
- `$id` 模型ID

#### 使用示例
```php
Easy::mod()->restore($id);
```

### ``thoroughDel``【彻底删除模型】
::: tip
注意：彻底删除后，会将数据表删除且无法恢复，请谨慎操作
:::
#### 参数说明
- `$id` 模型ID

#### 使用示例
```php
Easy::mod()->thoroughDel($id);
```

## ``lists``【模型列表】
### 参数说明
- `$search` 查询参数条件，类型为 `array`
- `$group` 所属分组
### 使用示例
```php
# 查询已删除（回收站）数据
$search['recycle'] = 1;
Easy::mod()->lists($search, $group);
```

## ``find``【模型详情】
### 参数说明
- `$id` 模型ID
### 使用示例
```php
Easy::mod()->find($id);
```

## ``publish``【发布模型】
::: tip 注意
发布后的模型才可以使用，且发布后无法修改，如需要修改需先取消发布
:::
### 参数说明
- `$id` 模型ID

### 使用示例
```php
Easy::mod()->publish($id);
```

## ``unPublish``【取消模型发布】
### 参数说明
- `$id` 模型ID

### 使用示例
```php
Easy::mod()->unPublish($id);
```
## ``preview``【预览模型】
::: tip 注意
预览模型需要[PTAdmin/Admin](https://wwww.pagtou.com)支持才可进行预览
:::
### 参数说明
- `$id` 模型ID
### 使用示例
```php
Easy::mod()->preview($id);
```

## ``byTableName``【基于table_name获取模型信息】
### 参数说明
- `$table_name` 模型table_name

### 使用示例
```php
Easy::mod()->byTableName($table_name);
```

