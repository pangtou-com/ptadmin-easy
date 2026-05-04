# 资源与字段接口契约

## 说明

本页用于固定当前“模型目录 + 字段草稿维护”的后端返回结构，方便前端联调时直接对照使用。

适用范围：

- `Easy::resources()`
- `Easy::release(...)->draftSchema()/fields()/addField()/updateField()/renameField()/deleteField()/reorderFields()/planDraft()/publishDraft()`
- `SchemaFieldReferenceException`

## 1. 模型列表

```php
$list = Easy::resources()->lists([
    'module' => 'cms',
    'keyword' => '文章',
    'published' => true,
    'page' => 1,
    'limit' => 20,
]);
```

返回结构：

```php
[
    'data' => [
        [
            'id' => 1,
            'title' => '文章管理',
            'name' => 'articles',
            'module' => 'cms',
            'intro' => null,
            'current_version_id' => 3,
            'is_publish' => 1,
            'status' => 1,
            'allow_recycle' => 1,
            'track_changes' => 1,
            'title_field' => 'title',
            'created_at' => 1710000000,
            'updated_at' => 1710000000,
        ],
    ],
    'current_page' => 1,
    'per_page' => 20,
    'total' => 1,
    'stats' => [
        'total' => 1,
        'published' => 0,
        'draft' => 1,
        'unpublished' => 1,
    ],
]
```

说明：

- `stats.total` 是当前查询作用域下的模型总数
- `stats.published` 是已发布模型数
- `stats.draft` 是存在最新草稿的模型数
- `stats.unpublished` 是尚未发布的模型数
- `stats` 不受分页和 `published` 筛选影响

## 2. 模型详情

```php
$detail = Easy::resources()->detail('articles', 'cms');
```

返回结构：

```php
[
    'resource' => [...],
    'current_version' => [...],
    'latest_draft' => [...],
    'field_count' => 2,
    'published_field_count' => 2,
    'summary' => [
        'published' => true,
        'has_current_version' => true,
        'has_draft' => true,
        'pending_changes' => true,
        'current_version_id' => 3,
        'latest_draft_id' => 4,
        'editing_field_count' => 2,
        'draft_field_count' => 2,
        'published_field_count' => 2,
        'latest_draft_updated_at' => 1710000000,
        'current_version_updated_at' => 1710000000,
    ],
]
```

说明：

- `resource` 是 `mods` 主记录
- `current_version` 是当前发布版本
- `latest_draft` 是最新草稿版本
- `summary` 是详情页可直接使用的状态摘要
- `field_count` 与 `summary.editing_field_count` 含义一致，优先统计最新草稿字段数

## 3. 创建模型草稿

```php
$draft = Easy::resources()->createDraft([
    'title' => '文章管理',
    'name' => 'articles',
    'module' => 'cms',
], [
    'remark' => '创建模型',
]);
```

返回结构：

```php
[
    'persisted' => true,
    'id' => 12,
    'status' => 'draft',
    'resource' => 'articles',
    'module' => 'cms',
    'mod_id' => 1,
    'schema' => [...],
]
```

## 4. 字段草稿读取

### 读取当前草稿 schema

```php
$schema = Easy::release('articles', 'cms')->draftSchema();
```

### 读取当前草稿字段

```php
$fields = Easy::release('articles', 'cms')->fields();
```

返回结构：

```php
[
    [
        'name' => 'title',
        'type' => 'text',
        'label' => '标题',
    ],
]
```

## 5. 字段草稿更新

以下方法当前统一返回“更新后的草稿版本记录”：

- `addField()`
- `updateField()`
- `renameField()`
- `deleteField()`
- `reorderFields()`

返回结构：

```php
[
    'persisted' => true,
    'id' => 12,
    'status' => 'draft',
    'resource' => 'articles',
    'module' => 'cms',
    'mod_id' => 1,
    'schema' => [...],
]
```

补充说明：

- `renameField()` 会附带 `summary.references_updated`
- `deleteField()` 在 `cleanup_references=true` 时会附带 `summary.references_removed`

### 新增字段

```php
$draft = Easy::release('articles', 'cms')->addField([
    'name' => 'title',
    'type' => 'text',
    'label' => '标题',
]);
```

### 更新字段

```php
$draft = Easy::release('articles', 'cms')->updateField('title', [
    'label' => '文章标题',
]);
```

### 重命名字段

```php
$draft = Easy::release('articles', 'cms')->renameField('title', 'headline');
```

除标准草稿结构外，还会附带：

```php
[
    'summary' => [
        'type' => 'rename_field',
        'field' => 'headline',
        'from' => 'title',
        'to' => 'headline',
        'rename_from' => 'title',
        'references_updated' => [
            ['type' => 'title_field', 'path' => 'title_field'],
            ['type' => 'search_fields', 'path' => 'search_fields.0'],
        ],
    ],
]
```

### 删除字段

```php
$draft = Easy::release('articles', 'cms')->deleteField('excerpt');
```

显式允许清理引用后删除：

```php
$draft = Easy::release('articles', 'cms')->deleteField('title', [
    'cleanup_references' => true,
]);
```

如果开启清理引用，返回中会附带：

```php
[
    'summary' => [
        'type' => 'delete_field',
        'field' => 'title',
        'cleanup_applied' => true,
        'references_removed' => [
            ['type' => 'title_field', 'path' => 'title_field'],
            ['type' => 'search_fields', 'path' => 'search_fields.0'],
        ],
    ],
]
```

### 重排字段

```php
$draft = Easy::release('articles', 'cms')->reorderFields([
    'headline',
    'status',
]);
```

## 6. 当前草稿预览与发布

### 预览当前草稿计划

```php
$plan = Easy::release('articles', 'cms')->planDraft();
```

重点使用：

```php
$plan->operations();
$plan->summary();
$plan->explanation();
$plan->toArray();
```

### 发布当前草稿

```php
$result = Easy::release('articles', 'cms')->publishDraft(null, [
    'sync' => true,
    'force' => false,
]);
```

重点使用：

```php
$result->version();
$result->plan();
$result->syncApplied();
$result->toArray();
```

## 7. 删除字段冲突异常

删除被引用字段时会抛出：

```php
\PTAdmin\Easy\Exceptions\SchemaFieldReferenceException
```

前端可直接读取：

```php
$exception->field();
$exception->operation();
$exception->references();
$exception->toArray();
```

`toArray()` 返回结构：

```php
[
    'ok' => false,
    'field' => 'title',
    'operation' => 'delete',
    'message' => 'Schema field [title] is referenced and cannot be delete.',
    'references' => [
        ['type' => 'title_field', 'path' => 'title_field'],
        ['type' => 'search_fields', 'path' => 'search_fields.0'],
    ],
]
```

## 8. 当前默认约定

- `module` 就是插件 code
- 字段操作默认作用于最新 draft
- 没有 draft 时会自动创建一个可编辑 draft
- 空字段模型可以保存草稿，但不能发布
- 字段重命名请使用 `renameField()`，不要通过 `updateField()` 修改 `name`
