# Schema 生命周期

## 当前状态

当前 schema 管理、发布、回滚、版本管理、`doc` 运行时主链已完成，可进入联调和实际接入阶段。

当前已经可稳定使用的范围包括：

- `Easy::schema(...)`
- `Easy::release(...)`
- `Easy::doc(...)`
- 草稿保存 / 更新
- 按版本 ID 预览发布
- 按版本 ID 发布
- 版本历史 / 草稿列表 / 版本详情 / 版本对比
- 回滚到指定版本
- 发布后运行时 CRUD / detail / lists / aggregate

当前仍在继续深化但不阻塞主链使用的范围包括：

- 前端拖拽 schema 协议最终收口
- 更复杂迁移策略与风险分类
- 更复杂展示值映射协议
- 更复杂关联协议

## 推荐使用顺序

1. 前端生成 schema JSON
2. 使用 `Easy::schema($schema)` 做编译、校验、蓝图预览
3. 使用 `Easy::release($resource, $module)` 保存草稿
4. 使用草稿版本 ID 预览发布计划
5. 使用草稿版本 ID 正式发布
6. 发布成功后，使用 `Easy::doc($resource, $module)` 执行运行时数据操作

## 入口职责

### `Easy::schema($schema)`
- 负责 schema 编译、校验、蓝图预览、字段映射查看

### `Easy::release($resource, $module)`
- 负责 schema 草稿、发布、回滚、版本历史、版本详情、版本对比

### `Easy::doc($resource, $module)`
- 负责已发布资源的数据 CRUD、查询、关联加载、聚合

## schema 协议说明

前后端对接时，资源名字段统一使用 `name`。

```php
[
    'name' => 'article',
]
```

当前主链已经统一使用 `name`，因此：

- 对外协议统一写 `name`
- 数据库存储字段也统一为 `name`

## 完整使用示例

下面的示例覆盖一条最常见的接入链路：定义 schema、保存草稿、预览发布、正式发布、然后进入运行时。

```php
use PTAdmin\Easy\Easy;

$schema = [
    'title' => '文章管理',
    'name' => 'article',
    'module' => 'cms',
    'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => '标题', 'is_required' => 1, 'length' => 100],
        ['name' => 'status', 'type' => 'switch', 'label' => '状态', 'default' => 1],
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
    ],
];

// 1. 编译与校验 schema
$definition = Easy::schema($schema)->validate();
$blueprint = Easy::schema($schema)->blueprint();
$mappings = Easy::schema($schema)->fieldMappings();

// 2. 保存草稿
$release = Easy::release('article', 'cms');

$draft = $release->saveDraft($schema, [
    'remark' => '初始化文章模型',
]);

$draftId = (int) $draft['id'];
$version = $release->version($draftId);
$history = $release->history();
$current = $release->current();

// 3. 预览发布计划
$plan = $release->planVersion($draftId);

// 4. 正式发布
$publishResult = $release->publishVersion($draftId, [
    'sync' => true,
    'force' => false,
]);

// 5. 进入运行时
$doc = Easy::doc('article', 'cms');

$created = $doc->create([
    'title' => '第一篇文章',
    'status' => 1,
    'category_id' => 3,
]);

$detail = $doc->detail($created['id']);

$list = $doc->lists([
    'page' => 1,
    'limit' => 20,
    'filters' => [
        ['field' => 'status', 'operator' => '=', 'value' => 1],
    ],
]);

$updated = $doc->update($created['id'], [
    'title' => '第一篇文章-已更新',
]);

$stats = $doc->aggregate([
    'groups' => ['status'],
    'metrics' => [
        ['type' => 'count', 'field' => 'id', 'as' => 'total'],
    ],
]);

// $version: 指定版本详情
// $history: 版本历史列表
// $current: 当前已发布版本
```

## 1. schema 校验与预览

在保存草稿前，推荐先做 schema 校验和预览。

```php
$definition = Easy::schema($schema)->validate();
$blueprint = Easy::schema($schema)->blueprint();
$mappings = Easy::schema($schema)->fieldMappings();
$array = Easy::schema($schema)->toArray();
```

### 常用方法

#### `validate()`
- 作用：触发 schema 编译与校验
- 返回：`SchemaDefinition`

#### `blueprint()`
- 作用：返回标准化后的 schema 蓝图
- 返回：`array`

#### `fieldMappings()`
- 作用：返回字段映射结果
- 返回：`array`

#### `toArray()`
- 作用：导出编译后的 schema 定义数组
- 返回：`array`

## 2. 保存草稿

schema 生命周期从草稿开始。推荐所有正式发布都基于草稿版本进行。

```php
$draft = Easy::release('article', 'cms')->saveDraft($schema, [
    'remark' => '初始化文章模型',
]);
```

#### `saveDraft(array $schema, array $options = [])`
- 作用：保存 schema 草稿，不执行数据库结构变更
- 返回：版本记录数组

支持的 `$options`：

- `remark`
  版本备注

### 返回示例

```php
[
    'persisted' => true,
    'id' => 12,
    'status' => 'draft',
    'resource' => 'article',
    'module' => 'cms',
    'mod_id' => 3,
    'schema' => [...],
]
```

## 3. 查询当前版本、草稿与详情

```php
$release = Easy::release('article', 'cms');

$current = $release->current();
$latestDraft = $release->latestDraft();
$version = $release->version(12);
$drafts = $release->drafts();
$history = $release->history();
$detail = $release->versionDetail(12);
```

### 常用方法

#### `current()`
- 作用：返回当前已发布版本
- 返回：`array|null`

#### `latestDraft()`
- 作用：返回最新草稿版本
- 返回：`array|null`

#### `version(int $versionId)`
- 作用：按版本 ID 返回标准版本记录
- 返回：`array|null`

#### `drafts(int $limit = 20, array $filters = [])`
- 作用：返回草稿列表
- 返回：版本记录数组

#### `history(int $limit = 20, array $filters = [])`
- 作用：返回版本历史
- 返回：版本记录数组

#### `versionDetail(int $versionId)`
- 作用：返回版本详情页或抽屉需要的结构
- 返回字段：
  - `version`
  - `schema`
  - `actions`
  - `change_summary`
  - `plan`

### 标准版本记录常用字段

- `persisted`
- `id`
- `mod_id`
- `resource`
- `module`
- `version_no`
- `status`
- `is_current`
- `created_at`
- `updated_at`
- `published_at`
- `remark`
- `schema`

## 4. 更新草稿

只有 `draft` 状态允许更新。

```php
$updated = Easy::release('article', 'cms')->updateDraft($draft['id'], $schema, [
    'remark' => '补充 SEO 字段',
]);
```

#### `updateDraft(int $versionId, array $schema, array $options = [])`
- 作用：按版本 ID 更新草稿
- 限制：仅允许 `draft`
- 返回：更新后的版本记录

## 5. 查看发布计划

推荐在正式发布前先看迁移计划。

```php
$plan = Easy::release('article', 'cms')->planVersion($draft['id']);
```

兼容调用：

```php
$plan = Easy::release('article', 'cms')->plan($draft['id']);
```

#### `planVersion(int $versionId)`
- 作用：按草稿版本 ID 生成发布计划
- 限制：仅允许 `draft`
- 返回：`MigrationPlan`

#### `plan(array|int $schema)`
- 作用：
  - 传入数组时，直接预览一份 schema 的发布计划
  - 传入整数时，按草稿版本 ID 预览

## 6. 正式发布

推荐通过版本 ID 发布，而不是重复传入整份 schema。

```php
$result = Easy::release('article', 'cms')->publishVersion($draft['id']);
```

兼容调用：

```php
$result = Easy::release('article', 'cms')->publish($draft['id']);
```

#### `publishVersion(int $versionId, array $options = [])`
- 作用：发布指定草稿版本
- 限制：仅允许 `draft`
- 返回：`PublishResult`

#### `publish(array|int $schema, array $options = [])`
- 作用：
  - 传入数组时，直接发布一份 schema
  - 传入整数时，按版本 ID 发布
- 建议：正式业务优先使用 `publishVersion($versionId)`

支持的 `$options`：

- `sync`
  是否同步数据库结构，默认 `true`
- `force`
  是否强制执行存在破坏性操作的结构变更，默认 `false`

### 发布后的状态变化

- 当前草稿版本变为 `published`
- 旧的 `published` 版本变为 `archived`
- 其他 `draft` 版本变为 `superseded`
- `mods.current_version_id` 会切换到新版本
- `mod_fields` 会刷新为当前发布版本的字段编译结果

## 7. 版本管理页

```php
$panel = Easy::release('article', 'cms')->versionPanel(1, 20, [
    'status' => 'draft',
    'keyword' => 'SEO',
]);
```

#### `versionPanel(int $page = 1, int $pageSize = 20, array $filters = [])`
- 作用：返回后台版本管理页可直接消费的数据结构
- 返回字段：
  - `summary`
  - `stats`
  - `pagination`
  - `filters`
  - `items`

`filters.applied` 当前支持：

- `status`
- `is_current`
- `version_no`
- `keyword`
- `ids`

## 8. 版本对比

```php
$diff = Easy::release('article', 'cms')->diffVersions(12, 10);
```

如果第二个版本为空，则默认与当前已发布版本对比。

```php
$diff = Easy::release('article', 'cms')->diffVersions(12);
```

#### `diffVersions(int $fromVersionId, ?int $toVersionId = null)`
- 作用：对比两个版本之间的 schema 结构差异
- 返回字段：
  - `from_version`
  - `to_version`
  - `plan`

## 9. 回滚版本

回滚只能针对历史正式版本，不能对草稿执行回滚。

```php
$rollback = Easy::release('article', 'cms')->rollbackTo(10, [
    'sync' => true,
    'force' => false,
]);
```

#### `rollbackTo(int $versionId, array $options = [])`
- 作用：将指定版本重新设为当前发布版本
- 限制：不允许 `draft`
- 返回：`RollbackResult`

支持的 `$options`：

- `sync`
  是否同步数据库结构，默认 `true`
- `force`
  是否强制执行结构回滚，默认 `false`

## 10. 删除版本

```php
Easy::release('article', 'cms')->deleteDraft($draftId);
Easy::release('article', 'cms')->deleteVersion($versionId);
```

#### `deleteDraft(int $versionId)`
- 作用：删除草稿版本
- 限制：仅允许 `draft`
- 返回：`bool`

#### `deleteVersion(int $versionId)`
- 作用：删除历史版本
- 允许状态：
  - `draft`
  - `archived`
  - `superseded`
- 禁止删除：
  - 当前 `published`
- 返回：`bool`

## 11. 发布后运行时

只有发布后的资源，才应该进入 `doc` 运行时。

```php
$doc = Easy::doc('article', 'cms');

$created = $doc->create([
    'title' => '欢迎使用 Easy',
    'status' => 1,
]);

$detail = $doc->detail($created['id']);

$list = $doc->lists([
    'page' => 1,
    'limit' => 20,
]);

$updated = $doc->update($created['id'], [
    'title' => '文章标题已更新',
]);

$doc->delete($created['id']);
```

### 常用方法

- `create(array $data, ?ExecutionContext $context = null)`
- `update($id, array $data, ?ExecutionContext $context = null)`
- `delete($id, ?ExecutionContext $context = null)`
- `detail($id, ?ExecutionContext $context = null)`
- `lists(array $query = [], ?ExecutionContext $context = null)`
- `aggregate(array $query = [], ?ExecutionContext $context = null)`
- `with(string|array $relations)`
- `on(string $event, callable $listener)`
- `scope(string $operation, callable $scope)`

关联、展示文本字段和删除策略请参考：

- [relation.md](/guide/relation.md)
- [quickstart-demo.md](/guide/quickstart-demo.md)

## 当前推荐规则

- 正式流程统一采用“先草稿，后发布”
- 发布和回滚统一使用版本 ID
- schema 真源存储在 `mod_versions`
- 当前发布字段缓存存储在 `mod_fields`
- 运行时读取 `mods + mod_versions + mod_fields`
- 主业务数据表不记录 schema 版本号
- 审计日志记录执行时使用的 `schema_version_id`
