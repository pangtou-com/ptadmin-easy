# 基础配置信息
通过 ``easy.php`` 文件配置, 文件路径在 ``config`` 目录下

## `table_name` 
数据表名称设置
- `mod`
  - 类型: string
  - 默认值: `mods`
  - 描述: 存储模型数据的表名称
- `mod_field`
  - 类型: string
  - 默认值: `mod_fields`
  - 描述: 存储模型字段的表名称

## `cache`
缓存配置信息
- `key`: 缓存键值，默认为 ``__ptadmin.easy.cache__``
- `store`: 缓存存储引擎，默认为 ``default``
- `expiration_time`: 缓存过期时间，默认为 ``\DateInterval::createFromDateString('30 days')``

## `extend`
组件扩展配置,用于对不同组件的配置进行扩展，具体参数请阅读 [扩展](/extend)。
### 配置参考
以下配置代表 `text` 组件配置了字符长度约束
```php
$extend = [
    'text' => [
        ['type' => 'number', 'title' => '长度', 'name' => 'length', 'default' => 255],
    ]
]
```