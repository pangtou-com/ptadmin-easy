# 最小接入 Demo

## 说明

本示例用于帮助第一次接入 PTAdmin/Easy 的项目快速跑通主链。

示例目标：

- 定义并发布 4 个资源
  - `article_categories`
  - `article_seo`
  - `article_comments`
  - `articles`
- 演示发布后如何执行 CRUD
- 演示如何加载关联、过滤、排序、聚合
- 演示如何基于版本草稿升级 schema

本文档采用当前主链推荐流程：

1. 写 schema
2. 保存草稿
3. 按版本 ID 发布
4. 发布后进入 `Easy::doc(...)`

## schema 协议约定

本 Demo 中资源名字段统一使用 `name`。

```php
[
    'name' => 'articles',
]
```

说明：

- `name` 是当前前端已确认的正式协议字段
- 后端与数据库存储统一使用 `name`
- 文档示例统一按 `name` 编写，避免前后端协议再次分叉

## 前置条件

开始前默认你已经完成：

- 安装 PTAdmin/Easy
- 执行基础迁移
- 可以正常调用 `Easy` Facade

如果还没有完成安装，请先看 [install.md](/guide/install.md)。

## 1. 准备一个发布辅助方法

为了避免每个资源都重复写同样的发布流程，可以先准备一个小辅助方法。

```php
use PTAdmin\Easy\Easy;

function publishSchema(string $resource, string $module, array $schema, string $remark = '初始化版本'): void
{
    $release = Easy::release($resource, $module);
    $draft = $release->saveDraft($schema, [
        'remark' => $remark,
    ]);

    $release->publishVersion((int) $draft['id'], [
        'sync' => true,
        'force' => false,
    ]);
}
```

## 2. 定义并发布分类资源

```php
$categorySchema = [
    'title' => '文章分类',
    'module' => 'cms',
    'name' => 'article_categories',
    'allow_recycle' => 0,
    'track_changes' => 1,
    'title_field' => 'title',
    'search_fields' => ['title'],
    'fields' => [
        [
            'name' => 'title',
            'type' => 'text',
            'label' => '分类标题',
            'is_required' => 1,
            'length' => 100,
        ],
        [
            'name' => 'status',
            'type' => 'radio',
            'label' => '状态',
            'default' => 1,
            'options' => [
                ['label' => '启用', 'value' => 1],
                ['label' => '禁用', 'value' => 0],
            ],
        ],
    ],
];

publishSchema('article_categories', 'cms', $categorySchema, '初始化分类资源');
```

### 写入分类数据

```php
$categoryDoc = Easy::doc('article_categories', 'cms');

$newsCategory = $categoryDoc->create([
    'title' => '新闻',
    'status' => 1,
]);

$noticeCategory = $categoryDoc->create([
    'title' => '公告',
    'status' => 1,
]);
```

## 3. 定义并发布 SEO 子资源

```php
$seoSchema = [
    'title' => '文章SEO',
    'module' => 'cms',
    'name' => 'article_seo',
    'allow_recycle' => 0,
    'track_changes' => 1,
    'fields' => [
        [
            'name' => 'article_id',
            'type' => 'number',
            'label' => '文章ID',
            'is_required' => 1,
        ],
        [
            'name' => 'summary',
            'type' => 'text',
            'label' => 'SEO描述',
            'is_required' => 1,
            'length' => 255,
        ],
        [
            'name' => 'keywords',
            'type' => 'text',
            'label' => 'SEO关键词',
            'length' => 255,
        ],
    ],
];

publishSchema('article_seo', 'cms', $seoSchema, '初始化 SEO 子资源');
```

## 4. 定义并发布评论子资源

```php
$commentSchema = [
    'title' => '文章评论',
    'module' => 'cms',
    'name' => 'article_comments',
    'allow_recycle' => 0,
    'track_changes' => 1,
    'fields' => [
        [
            'name' => 'article_id',
            'type' => 'number',
            'label' => '文章ID',
            'is_required' => 1,
        ],
        [
            'name' => 'content',
            'type' => 'text',
            'label' => '评论内容',
            'is_required' => 1,
            'length' => 255,
        ],
        [
            'name' => 'status',
            'type' => 'radio',
            'label' => '状态',
            'default' => 1,
            'options' => [
                ['label' => '显示', 'value' => 1],
                ['label' => '隐藏', 'value' => 0],
            ],
        ],
    ],
];

publishSchema('article_comments', 'cms', $commentSchema, '初始化评论子资源');
```

## 5. 定义并发布文章主资源

```php
$articleSchema = [
    'title' => '文章管理',
    'module' => 'cms',
    'name' => 'articles',
    'allow_recycle' => 1,
    'track_changes' => 1,
    'title_field' => 'title',
    'search_fields' => ['title'],
    'fields' => [
        [
            'name' => 'title',
            'type' => 'text',
            'label' => '文章标题',
            'is_required' => 1,
            'length' => 100,
        ],
        [
            'name' => 'tenant_id',
            'type' => 'number',
            'label' => '租户',
            'is_required' => 1,
        ],
        [
            'name' => 'status',
            'type' => 'radio',
            'label' => '状态',
            'default' => 1,
            'options' => [
                ['label' => '启用', 'value' => 1],
                ['label' => '禁用', 'value' => 0],
            ],
        ],
        [
            'name' => 'category_id',
            'type' => 'select',
            'label' => '所属分类',
            'relation' => [
                'type' => 'belongs_to',
                'resource' => 'article_categories',
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

publishSchema('articles', 'cms', $articleSchema, '初始化文章主资源');
```

## 6. 创建文章并同时写入关联数据

```php
$articleDoc = Easy::doc('articles', 'cms');

$created = $articleDoc->create([
    'title' => '第一篇文章',
    'tenant_id' => 1,
    'status' => 1,
    'category_id' => $newsCategory->id,
    'seo' => [
        'summary' => '这是一篇用于联调的测试文章',
        'keywords' => 'easy,cms,article',
    ],
    'comments' => [
        ['content' => '第一条评论', 'status' => 1],
        ['content' => '第二条评论', 'status' => 1],
    ],
]);
```

### 此时可以期待的结果

- 主表 `articles` 会创建一条文章记录
- 子表 `article_seo` 会写入一条 `article_id = 文章ID` 的记录
- 子表 `article_comments` 会写入两条 `article_id = 文章ID` 的记录

## 7. 读取详情

### 读取基础详情

```php
$detail = $articleDoc->detail($created->id);
```

这时你通常会看到：

- `category_id` 仍然是原始外键值
- `__category_id_text` 是分类展示文本
- `__status_text` 是本地 options 的展示文本

### 读取详情并显式加载关联

```php
$detailWithRelations = $articleDoc
    ->with([
        'category:id,title',
        'seo:id,summary,keywords',
        'comments:id,content,status',
    ])
    ->detail($created->id);
```

### 预期可读字段示例

```php
$detailWithRelations->title;
$detailWithRelations->__category_id_text;
$detailWithRelations->__status_text;
$detailWithRelations->category['title'];
$detailWithRelations->seo['summary'];
$detailWithRelations->comments[0]['content'];
```

## 8. 列表查询

### 基础分页

```php
$list = $articleDoc->lists([
    'page' => 1,
    'limit' => 20,
]);
```

### 带关联对象

```php
$listWithRelations = $articleDoc->lists([
    'page' => 1,
    'limit' => 20,
    'with' => [
        'category:id,title',
        'seo:id,summary',
    ],
]);
```

### 一次性加载全部关联

```php
$listAllRelations = $articleDoc->lists([
    'page' => 1,
    'limit' => 20,
    'with_relations' => true,
]);
```

## 9. 过滤与排序

### 按普通字段过滤

```php
$enabledArticles = $articleDoc->lists([
    'filters' => [
        ['field' => 'status', 'operator' => '=', 'value' => 1],
    ],
]);
```

### 按展示文本过滤

```php
$newsArticles = $articleDoc->lists([
    'filters' => [
        ['field' => '__category_id_text', 'operator' => 'like', 'value' => '%新闻%'],
    ],
]);
```

### 按关联字段过滤

```php
$seoMatched = $articleDoc->lists([
    'filters' => [
        ['field' => 'seo.summary', 'operator' => 'like', 'value' => '%联调%'],
    ],
    'with' => [
        'seo:id,summary',
    ],
]);
```

### 按关联字段排序

```php
$sorted = $articleDoc->lists([
    'sorts' => [
        ['field' => 'category.title', 'direction' => 'asc'],
    ],
    'with' => [
        'category:id,title',
    ],
]);
```

## 10. 聚合统计

```php
$aggregate = $articleDoc->aggregate([
    'groups' => ['status'],
    'metrics' => [
        ['type' => 'count', 'field' => 'id', 'as' => 'total'],
    ],
]);
```

### 常见用途

- 按状态统计文章数量
- 作为后续 `charts` 模块的数据来源

## 11. 更新文章与关联

```php
$updated = $articleDoc->update($created->id, [
    'title' => '第一篇文章-已更新',
    'category_id' => $noticeCategory->id,
    'seo' => [
        'summary' => 'SEO 描述已更新',
        'keywords' => 'easy,article,updated',
    ],
    'comments' => [
        ['content' => '保留一条最新评论', 'status' => 1],
        ['content' => '新增评论', 'status' => 1],
    ],
]);
```

### 更新规则

- `seo` 不传：不处理 SEO 子记录
- `seo = null`：清空当前 SEO 子记录
- `comments` 不传：不处理评论子记录
- `comments = []`：清空当前评论子记录
- `comments` 中带主键：按主键更新
- `comments` 中不带主键：视为新增

## 12. 删除文章

```php
$deleted = $articleDoc->delete($created->id);
```

如果 `articles` 中的关联配置使用的是：

```php
'on_delete' => 'cascade'
```

那么删除文章时：

- `article_seo` 下对应子记录会一起删除
- `article_comments` 下对应子记录会一起删除

## 13. 基于草稿升级 schema

下面示例演示如何给 `articles` 新增一个 `excerpt` 字段。

```php
$release = Easy::release('articles', 'cms');
$current = $release->current();

$schemaV2 = $current['schema'];
$schemaV2['fields'][] = [
    'name' => 'excerpt',
    'type' => 'text',
    'label' => '摘要',
    'length' => 255,
];

$draftV2 = $release->saveDraft($schemaV2, [
    'remark' => '新增摘要字段',
]);

$planV2 = $release->planVersion((int) $draftV2['id']);
$publishV2 = $release->publishVersion((int) $draftV2['id']);
```

### 继续查看版本信息

```php
$history = $release->history();
$drafts = $release->drafts();
$detail = $release->versionDetail((int) $draftV2['id']);
$diff = $release->diffVersions((int) $draftV2['id']);
```

## 14. 联调时最常用的检查点

如果你已经按本文档跑通，建议至少检查以下几点：

- `mods` 中存在 `article_categories / article_seo / article_comments / articles`
- `mod_versions` 中能看到每个资源的版本记录
- `mod_fields` 中能看到当前发布版本的字段编译缓存
- `articles` 表已真实创建
- `detail()` 能返回 `__category_id_text` 和 `__status_text`
- `with(...)` 能正确返回 `category / seo / comments`
- `history()`、`versionDetail()`、`diffVersions()` 能正常返回

## 推荐继续阅读

- [Schema 生命周期](/guide/schema-lifecycle.md)
- [关联使用](/guide/relation.md)
- [待办事项](/guide/todo.md)
