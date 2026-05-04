# 模型目录管理

## 说明

模型目录用于后台按插件查看和管理资源模型。

在 PTAdmin/Easy 中，插件 code 使用 `module` 表示，因此可以通过 `module` 获取某个插件下的模型列表。

模型目录能力适合以下场景：

- 插件后台展示模型列表
- 关联字段选择目标模型
- 先创建模型，再进入字段列表维护
- 查看模型当前发布版本与最新草稿

## 入口

```php
use PTAdmin\Easy\Easy;

$resources = Easy::resources();
```

## 1. 查询模型列表

```php
$list = Easy::resources()->lists([
    'module' => 'cms',
    'page' => 1,
    'limit' => 20,
    'keyword' => '文章',
    'published' => true,
]);
```

常用筛选：

- `module`
  插件 code
- `keyword`
  按模型名称、标题、描述搜索
- `published`
  是否只查询已发布或未发布模型
- `status`
  模型状态
- `page`
  当前页
- `limit`
  每页数量

返回示例：

```php
[
    'data' => [
        [
            'id' => 1,
            'title' => '文章管理',
            'name' => 'articles',
            'module' => 'cms',
            'intro' => null,
            'current_version_id' => 8,
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

`stats` 说明：

- 复用当前 `module` / `keyword` / `status` 查询作用域
- 不受分页影响
- 不受 `published` 筛选影响，便于页面直接展示整体分布
- `draft` 表示该模型存在最新草稿，即使它已经发布过

## 2. 查询插件下全部模型

```php
$models = Easy::resources()->all('cms');
```

该方法返回轻量结构，适合下拉选项和关联配置。

```php
[
    [
        'id' => 1,
        'title' => '文章管理',
        'name' => 'articles',
        'module' => 'cms',
        'current_version_id' => 8,
        'is_publish' => 1,
    ],
]
```

也可以传入筛选条件：

```php
$models = Easy::resources()->all('cms', [
    'published' => true,
]);
```

## 3. 查询模型详情

```php
$detail = Easy::resources()->detail('articles', 'cms');
```

返回结构：

```php
[
    'resource' => [...],
    'current_version' => [...],
    'latest_draft' => [...],
    'field_count' => 5,
    'published_field_count' => 4,
    'summary' => [
        'published' => true,
        'has_current_version' => true,
        'has_draft' => true,
        'pending_changes' => true,
        'current_version_id' => 8,
        'latest_draft_id' => 9,
        'editing_field_count' => 5,
        'draft_field_count' => 5,
        'published_field_count' => 4,
        'latest_draft_updated_at' => 1710000000,
        'current_version_updated_at' => 1710000000,
    ],
]
```

说明：

- `resource` 是 `mods` 中的模型主记录
- `current_version` 是当前已发布版本
- `latest_draft` 是最新草稿版本
- `field_count` 优先统计最新草稿字段数量
- `published_field_count` 统计当前已发布字段数量
- `summary` 是前端可直接消费的状态摘要
- `editing_field_count` 表示当前编辑态字段数，优先取最新草稿字段数量
- `pending_changes` 当前等价于“是否存在最新草稿”

## 4. 创建模型草稿

传统后台可以先创建模型，再维护字段。

```php
$draft = Easy::resources()->createDraft([
    'title' => '文章管理',
    'name' => 'articles',
    'module' => 'cms',
    'intro' => '文章内容管理',
    'allow_recycle' => 1,
    'track_changes' => 1,
], [
    'remark' => '创建文章模型',
]);
```

内部会生成一个允许空字段的 schema 草稿：

```php
[
    'title' => '文章管理',
    'name' => 'articles',
    'module' => 'cms',
    'intro' => '文章内容管理',
    'allow_recycle' => 1,
    'track_changes' => 1,
    'fields' => [],
]
```

注意：

- 空字段模型只允许保存草稿
- 空字段模型不允许发布
- 发布前至少需要添加一个真实字段
- 字段维护请参考 [字段草稿维护](/guide/field-draft.md)

## 推荐流程

```php
Easy::resources()->createDraft([
    'title' => '文章管理',
    'name' => 'articles',
    'module' => 'cms',
]);

Easy::release('articles', 'cms')->addField([
    'name' => 'title',
    'type' => 'text',
    'label' => '标题',
]);

$plan = Easy::release('articles', 'cms')->planDraft();
$result = Easy::release('articles', 'cms')->publishDraft();
```

## 边界说明

- `Easy::resources()` 不处理运行时数据 CRUD
- `Easy::resources()` 不直接同步业务表结构
- 草稿发布仍由 `Easy::release(...)` 负责
- `module` 就是插件 code
