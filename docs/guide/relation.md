# 关联使用

## 当前状态

当前关联基础能力已完成，可进入联调和实际接入阶段。

当前已经可稳定使用的范围包括：

- `belongsTo`
- `hasOne`
- `hasMany`
- 创建时写入关联数据
- 更新时同步关联数据
- `detail()` / `lists()` 显式加载关联对象
- 关联字段过滤
- 部分关联字段排序
- 展示文本字段 `__field_text`
- 删除策略 `cascade / restrict / set_null`

当前仍在继续深化但不阻塞主链使用的范围包括：

- 更复杂关系协议
- 更完整边界约束
- 更完整排序策略
- 更复杂展示值映射协议

## 适用场景

- `belongsTo`
  当前资源保存外键，指向另一张资源表
- `hasOne`
  当前资源对应一条扩展子记录
- `hasMany`
  当前资源对应多条子记录

## schema 协议说明

前后端对接时，资源名字段统一使用 `name`，不要继续沿用旧字段名。

当前主链与数据库存储已经统一为 `name`，不再保留旧协议兼容写法。

## 完整使用示例

下面示例以“文章、分类、SEO、评论”为例，覆盖 schema 定义、发布、创建、更新、加载、过滤、排序。

关联运行时能力仅对已发布资源生效，因此示例会先保存草稿并发布，再进入 `Easy::doc(...)`。

```php
use PTAdmin\Easy\Easy;

$articleSchema = [
    'title' => '文章管理',
    'name' => 'article',
    'module' => 'cms',
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => '标题', 'is_required' => 1, 'length' => 100],
        [
            'name' => 'category_id',
            'type' => 'select',
            'label' => '所属分类',
            'relation' => [
                'type' => 'belongs_to',
                'resource' => 'categories',
                'value_field' => 'id',
                'label_field' => 'title',
            ],
        ],
        [
            'name' => 'seo',
            'type' => 'table',
            'label' => 'SEO配置',
            'relation' => [
                'type' => 'has_one',
                'resource' => 'article_seo',
                'foreign_key' => 'article_id',
                'local_key' => 'id',
                'on_delete' => 'cascade',
            ],
        ],
        [
            'name' => 'comments',
            'type' => 'table',
            'label' => '评论列表',
            'relation' => [
                'type' => 'has_many',
                'resource' => 'article_comments',
                'foreign_key' => 'article_id',
                'local_key' => 'id',
                'on_delete' => 'cascade',
            ],
        ],
    ],
];

Easy::schema($articleSchema)->validate();

$release = Easy::release('article', 'cms');
$draft = $release->saveDraft($articleSchema, [
    'remark' => '初始化文章关联模型',
]);

$release->publishVersion((int) $draft['id']);
```

### 创建主记录并同时写入关联

```php
$created = Easy::doc('article', 'cms')->create([
    'title' => '第一篇文章',
    'category_id' => 3,
    'seo' => [
        'summary' => 'SEO 描述',
        'keywords' => 'easy,article',
    ],
    'comments' => [
        ['content' => '第一条评论'],
        ['content' => '第二条评论'],
    ],
]);
```

### 加载详情与关联对象

```php
$detail = Easy::doc('article', 'cms')
    ->with([
        'category:id,title',
        'seo:id,summary,keywords',
        'comments:id,content',
    ])
    ->detail($created['id']);
```

### 列表查询、展示文本过滤、关联字段排序

```php
$list = Easy::doc('article', 'cms')->lists([
    'page' => 1,
    'limit' => 20,
    'with' => [
        'category:id,title',
    ],
    'filters' => [
        ['field' => '__category_id_text', 'operator' => 'like', 'value' => '%新闻%'],
        ['field' => 'seo.summary', 'operator' => 'like', 'value' => '%SEO%'],
    ],
    'sorts' => [
        ['field' => 'category.title', 'direction' => 'asc'],
    ],
]);
```

### 更新关联

```php
$updated = Easy::doc('article', 'cms')->update($created['id'], [
    'category_id' => 5,
    'seo' => [
        'id' => 11,
        'summary' => 'SEO 描述已更新',
    ],
    'comments' => [
        ['id' => 21, 'content' => '第一条评论-已修改'],
        ['content' => '新增评论'],
    ],
]);
```

## 1. belongsTo

`belongsTo` 适用于当前表保存外键，展示或加载另一张资源表的数据。

### schema 示例

```php
[
    'name' => 'category_id',
    'type' => 'select',
    'label' => '所属分类',
    'relation' => [
        'type' => 'belongs_to',
        'resource' => 'categories',
        'value_field' => 'id',
        'label_field' => 'title',
    ],
]
```

旧版 `extends` 写法：

```php
[
    'name' => 'category_id',
    'type' => 'select',
    'label' => '所属分类',
    'extends' => [
        'type' => 'resource',
        'table' => 'categories',
        'value' => 'id',
        'label' => 'title',
    ],
]
```

### 数据存储规则

- 主表只存真实外键值，例如 `category_id = 3`
- 详情和列表可额外输出展示字段，例如 `__category_id_text = 分类标题`

### 加载示例

```php
$detail = Easy::doc('article', 'cms')->with('category:id,title')->detail($id);

$list = Easy::doc('article', 'cms')->lists([
    'with' => ['category:id,title'],
]);
```

也支持：

```php
$list = Easy::doc('article', 'cms')->lists([
    'with_relations' => true,
]);
```

## 2. hasOne

`hasOne` 适用于主表对应一条子表数据，例如文章对应一条 SEO 信息。

### schema 示例

```php
[
    'name' => 'seo',
    'type' => 'table',
    'label' => 'SEO配置',
    'relation' => [
        'type' => 'has_one',
        'resource' => 'article_seo',
        'foreign_key' => 'article_id',
        'local_key' => 'id',
        'on_delete' => 'cascade',
    ],
]
```

### 创建示例

```php
Easy::doc('article', 'cms')->create([
    'title' => '测试文章',
    'tenant_id' => 1,
    'seo' => [
        'summary' => 'SEO 描述',
        'status' => 1,
    ],
]);
```

### 更新规则

- `seo` 字段未出现在 payload 中：不处理子记录
- `seo = null` 或 `seo = []`：清空当前子记录
- `seo` 带主键：按主键更新
- `seo` 不带主键：替换为新的单条子记录

### 加载示例

```php
$detail = Easy::doc('article', 'cms')->with('seo:id,summary')->detail($id);
```

## 3. hasMany

`hasMany` 适用于主表对应多条子表数据，例如文章对应多条评论。

### schema 示例

```php
[
    'name' => 'comments',
    'type' => 'table',
    'label' => '评论列表',
    'relation' => [
        'type' => 'has_many',
        'resource' => 'article_comments',
        'foreign_key' => 'article_id',
        'local_key' => 'id',
        'on_delete' => 'cascade',
    ],
]
```

### 创建示例

```php
Easy::doc('article', 'cms')->create([
    'title' => '测试文章',
    'tenant_id' => 1,
    'comments' => [
        ['content' => '第一条评论'],
        ['content' => '第二条评论'],
    ],
]);
```

### 更新规则

- `comments` 字段未出现在 payload 中：不处理子记录
- `comments = []` 或 `comments = null`：清空当前主记录下已有子记录
- 子项带主键：按主键更新
- 子项不带主键：视为新增
- 当前主记录下数据库中存在、但 payload 中未出现的旧子记录：会被删除或回收

### 加载示例

```php
$detail = Easy::doc('article', 'cms')->with('comments:id,content')->detail($id);

$list = Easy::doc('article', 'cms')->lists([
    'with' => ['comments:id,content'],
]);
```

## 4. 展示文本字段

对于 `belongsTo` 和本地 options 字段，运行时会输出私有展示字段，统一使用 `__` 前缀。

常见示例：

- `__category_id_text`
- `__status_text`
- `__type_text`

### 读取示例

```php
$detail = Easy::doc('article', 'cms')->detail($id);

$detail->__category_id_text;
$detail->__status_text;
```

### 过滤示例

```php
$list = Easy::doc('article', 'cms')->lists([
    'filters' => [
        ['field' => '__category_id_text', 'operator' => 'like', 'value' => '%新闻%'],
        ['field' => '__status_text', 'operator' => '=', 'value' => '启用'],
    ],
]);
```

### 排序示例

```php
$list = Easy::doc('article', 'cms')->lists([
    'sorts' => [
        ['field' => '__category_id_text', 'direction' => 'asc'],
        ['field' => '__type_text', 'direction' => 'desc'],
    ],
]);
```

## 5. 关联字段过滤

除了展示文本过滤，也支持直接按关联对象字段过滤。

### belongsTo 过滤

```php
$list = Easy::doc('article', 'cms')->lists([
    'filters' => [
        ['field' => 'category.title', 'operator' => 'like', 'value' => '%新闻%'],
    ],
]);
```

### hasOne 过滤

```php
$list = Easy::doc('article', 'cms')->lists([
    'filters' => [
        ['field' => 'seo.summary', 'operator' => 'like', 'value' => '%SEO%'],
    ],
]);
```

### hasMany 过滤

```php
$list = Easy::doc('article', 'cms')->lists([
    'filters' => [
        ['field' => 'comments.content', 'operator' => 'like', 'value' => '%评论%'],
    ],
]);
```

## 6. 关联字段排序

当前支持：

- `belongsTo`
- `hasOne`
- 关联展示文本字段排序

当前不建议默认支持：

- `hasMany` 的隐式排序

### 排序示例

```php
$list = Easy::doc('article', 'cms')->lists([
    'sorts' => [
        ['field' => 'category.title', 'direction' => 'asc'],
        ['field' => 'seo.summary', 'direction' => 'desc'],
    ],
]);
```

## 7. 删除策略

`hasOne / hasMany` 当前支持 3 种删除策略，配置在 `relation.on_delete` 上。

### `cascade`

默认值。

删除主记录时：

- 子记录一起删除
- 如果子资源开启回收站，则级联回收

```php
'on_delete' => 'cascade'
```

### `restrict`

存在子记录时，禁止删除主记录。

```php
'on_delete' => 'restrict'
```

### `set_null`

删除主记录时，将子记录外键置空。

要求：

- 子资源外键字段必须允许为空

```php
'on_delete' => 'set_null'
```

## 当前推荐规则

- `belongsTo` 用于外键引用
- `hasOne` 用于单条扩展信息
- `hasMany` 用于子表明细数据
- 展示文本字段统一使用 `__field_text`
- 删除策略优先使用：
  - 普通业务明细：`cascade`
  - 强约束场景：`restrict`
  - 历史保留场景：`set_null`

关联主链和发布流程请参考：

- [schema-lifecycle.md](/guide/schema-lifecycle.md)
- [quickstart-demo.md](/guide/quickstart-demo.md)

## 当前边界

- `belongsTo` 删除策略当前不处理
- `hasMany` 的隐式排序语义仍不稳定，暂不默认开放
- 更复杂的关联协议仍会随着前端 schema 协议继续收口
