# 字段草稿维护

## 说明

字段草稿维护用于传统“模型列表 + 字段列表”的配置后台。

它解决的问题是：不要求前端一次性提交完整 schema，而是允许先创建模型草稿，再逐个新增、编辑、删除和排序字段。

字段操作只修改 `mod_versions` 中的草稿 schema，不会直接修改：

- `mod_fields`
- 业务数据表

只有发布成功后，才会统一同步表结构并重建 `mod_fields` 当前字段缓存。

## 入口

字段草稿属于待发布 schema 的一部分，因此入口放在 `Easy::release(...)` 下。

```php
use PTAdmin\Easy\Easy;

$release = Easy::release('articles', 'cms');
```

## 1. 读取草稿 schema

```php
$schema = Easy::release('articles', 'cms')->draftSchema();
```

读取优先级：

1. 指定草稿版本 ID
2. 最新草稿
3. 当前已发布版本
4. 空模型 schema

指定草稿版本：

```php
$schema = Easy::release('articles', 'cms')->draftSchema($draftId);
```

## 2. 读取字段列表

```php
$fields = Easy::release('articles', 'cms')->fields();
```

指定草稿版本：

```php
$fields = Easy::release('articles', 'cms')->fields($draftId);
```

## 3. 新增字段

```php
$draft = Easy::release('articles', 'cms')->addField([
    'name' => 'title',
    'type' => 'text',
    'label' => '标题',
    'is_required' => 1,
    'length' => 100,
], [
    'remark' => '新增标题字段',
]);
```

规则：

- 字段名必须唯一
- 字段名不能使用 `__` 前缀
- 字段 type 必须是已注册组件
- 新增字段默认追加到最后
- 方法返回更新后的草稿版本记录

## 4. 更新字段

```php
$draft = Easy::release('articles', 'cms')->updateField('title', [
    'label' => '文章标题',
    'length' => 150,
], [
    'remark' => '调整标题字段',
]);
```

规则：

- `updateField()` 默认不支持修改字段名
- 字段类型、长度、必填、唯一等变化会在发布计划中体现
- 若需要字段重命名，请使用 `renameField()`

## 5. 删除字段

```php
$draft = Easy::release('articles', 'cms')->deleteField('excerpt', [
    'remark' => '删除摘要字段',
]);
```

规则：

- 删除字段只影响草稿
- 发布计划会标记字段删除风险
- 如果字段被资源配置引用，会阻止删除

如果你明确希望“清理引用后继续删除”，可以显式开启：

```php
$draft = Easy::release('articles', 'cms')->deleteField('title', [
    'cleanup_references' => true,
]);
```

此时返回中会附带：

```php
[
    'summary' => [
        'type' => 'delete_field',
        'field' => 'title',
        'cleanup_applied' => true,
        'references_removed' => [...],
    ],
]
```

当前会自动清理：

- `title_field`
- `cover_field`
- `search_fields`
- `order`
- `table.columns`
- `charts`
- `relation.local_key`

当前会检查的引用：

- `title_field`
- `cover_field`
- `search_fields`
- `order`
- `table.columns`
- `charts`
- `relation.local_key`

如果字段仍被引用，会抛出带引用明细的异常对象：

```php
try {
    Easy::release('articles', 'cms')->deleteField('title');
} catch (\PTAdmin\Easy\Exceptions\SchemaFieldReferenceException $e) {
    $payload = $e->toArray();
}
```

返回结构示例：

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

## 6. 字段排序

```php
$draft = Easy::release('articles', 'cms')->reorderFields([
    'title',
    'status',
    'category_id',
]);
```

规则：

- 传入字段名顺序
- 未传入的已有字段会追加到末尾
- 排序不产生数据库结构变更
- 发布后会影响 schema 字段顺序和 `mod_fields.sort_order`

## 7. 重命名字段

```php
$draft = Easy::release('articles', 'cms')->renameField('title', 'headline', [
    'remark' => '标题字段重命名',
]);
```

返回中会额外附带一段摘要，便于前端直接提示：

```php
[
    'summary' => [
        'type' => 'rename_field',
        'field' => 'headline',
        'from' => 'title',
        'to' => 'headline',
        'rename_from' => 'title',
        'references_updated' => [...],
    ],
]
```

规则：

- 目标字段名必须不存在
- 如果当前发布版本中存在原字段，草稿会自动写入 `rename_from`
- `title_field`、`cover_field`、`search_fields`、`order`、`table.columns`、`charts` 等引用会自动同步
- 发布计划会尽量识别为 `rename_fields`，而不是 `drop + add`

## 8. 预览当前草稿发布计划

```php
$plan = Easy::release('articles', 'cms')->planDraft();
```

指定草稿版本：

```php
$plan = Easy::release('articles', 'cms')->planDraft($draftId);
```

`planDraft()` 是 `planVersion()` 的便捷封装。

## 9. 发布当前草稿

```php
$result = Easy::release('articles', 'cms')->publishDraft(null, [
    'sync' => true,
    'force' => false,
]);
```

指定草稿版本：

```php
$result = Easy::release('articles', 'cms')->publishDraft($draftId, [
    'sync' => true,
    'force' => false,
]);
```

发布成功后：

- 草稿版本变为当前 `published`
- 旧发布版本归档
- 业务表结构按计划同步
- `mods.current_version_id` 指向当前版本
- `mod_fields` 重建为当前发布字段缓存

## 完整示例

```php
Easy::resources()->createDraft([
    'title' => '文章管理',
    'name' => 'articles',
    'module' => 'cms',
]);

$release = Easy::release('articles', 'cms');

$release->addField([
    'name' => 'title',
    'type' => 'text',
    'label' => '标题',
    'length' => 100,
]);

$release->addField([
    'name' => 'status',
    'type' => 'radio',
    'label' => '状态',
    'default' => 1,
    'options' => [
        ['label' => '启用', 'value' => 1],
        ['label' => '禁用', 'value' => 0],
    ],
]);

$release->updateField('title', [
    'label' => '文章标题',
]);

$release->renameField('title', 'headline');

$release->reorderFields([
    'headline',
    'status',
]);

$plan = $release->planDraft();
$result = $release->publishDraft();
```

## 与整体 schema 提交的关系

字段草稿维护和整体 schema 提交可以并存：

- 拖拽器、模板导入、复制模型适合整体提交 `saveDraft($schema)`
- 传统后台适合 `createDraft()` 后逐个维护字段
- 两种方式最终都写入 `mod_versions.schema_json`
- 两种方式最终都通过 `publishVersion()` 或 `publishDraft()` 发布

## 边界说明

- 空字段模型可以保存为草稿，但不能发布
- 字段操作不会直接改业务表
- 字段操作不会直接写 `mod_fields`
- 正式结构变更必须经过发布流程
- 字段重命名请使用 `renameField()`，不要通过 `updateField()` 修改 `name`
