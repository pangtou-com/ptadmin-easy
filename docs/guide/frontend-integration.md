# 前端联调建议

## 目标

当前后端已经支持“模型目录 + 字段草稿 + 发布版本”完整主链。

前端接入时，不建议先做一套新的聚合接口或临时状态机，而是直接围绕现有接口组织页面：

1. 模型列表页
2. 模型详情页
3. 字段维护区
4. 发布预览与发布
5. 版本历史区

## 推荐页面结构

### 模型列表页

直接使用：

```php
Easy::resources()->lists([
    'module' => 'cms',
    'keyword' => '文章',
    'status' => 1,
    'published' => true,
    'page' => 1,
    'limit' => 20,
]);
```

建议映射：

- 列表表格：`data`
- 分页：`current_page` / `per_page` / `total`
- 顶部统计：`stats.total` / `stats.published` / `stats.draft` / `stats.unpublished`

建议动作：

- 创建模型
- 查看详情
- 进入字段维护
- 查看版本历史

列表页不建议直接做发布动作。

### 模型详情页

直接使用：

```php
Easy::resources()->detail('articles', 'cms');
```

建议映射：

- 基础信息：`resource`
- 顶部状态卡：`summary`
- 当前编辑字段数：`summary.editing_field_count`
- 已发布字段数：`summary.published_field_count`
- 是否存在待发布变更：`summary.pending_changes`
- 当前发布版本：`summary.current_version_id`
- 最新草稿版本：`summary.latest_draft_id`

建议动作：

- 新增字段
- 预览发布
- 发布当前草稿
- 查看版本历史

详情页不建议依赖该接口返回完整 schema，字段列表请单独读取。

## 字段维护

### 字段列表

优先使用：

```php
Easy::release('articles', 'cms')->fields();
```

原因：

- 返回轻量
- 更适合表格渲染
- 不必每次都读取整份 schema

### 读取当前草稿

字段编辑抽屉或复杂配置表单可使用：

```php
Easy::release('articles', 'cms')->draftSchema();
```

适合场景：

- 需要读取完整字段配置
- 需要读取字段外层 schema 配置
- 需要本地暂存并做字段编辑回显

### 字段操作

```php
Easy::release('articles', 'cms')->addField([...]);
Easy::release('articles', 'cms')->updateField('title', [...]);
Easy::release('articles', 'cms')->renameField('title', 'headline');
Easy::release('articles', 'cms')->deleteField('title');
Easy::release('articles', 'cms')->reorderFields([...]);
```

建议约定：

- 新增字段后刷新 `fields()` 和 `detail()`
- 编辑字段后刷新 `fields()` 和 `detail()`
- 删除字段后刷新 `fields()` 和 `detail()`
- 排序字段后刷新 `fields()` 和 `detail()`
- 不要通过 `updateField()` 修改字段名
- 字段重命名必须走 `renameField()`

## 删除与重命名交互

### 重命名字段

`renameField()` 成功后会返回：

- `summary.from`
- `summary.to`
- `summary.references_updated`

前端建议直接展示“已自动同步的引用项”，避免用户误以为还要手工修改配置。

### 删除字段

默认先调用：

```php
Easy::release('articles', 'cms')->deleteField('title');
```

如果返回 `SchemaFieldReferenceException`，建议：

1. 弹窗展示 `references`
2. 用户确认后再调用 `cleanup_references => true`

```php
Easy::release('articles', 'cms')->deleteField('title', [
    'cleanup_references' => true,
]);
```

这样可以保持默认行为安全，只有显式确认才执行自动清理。

## 发布区

### 预览发布

使用：

```php
$plan = Easy::release('articles', 'cms')->planDraft();
```

建议展示：

- `operations`
- `summary`
- `explanation`

重点关注：

- 是否建表
- 是否新增字段
- 是否删除字段
- 是否字段重命名
- 是否 destructive

### 正式发布

使用：

```php
$result = Easy::release('articles', 'cms')->publishDraft(null, [
    'sync' => true,
    'force' => false,
]);
```

建议流程：

1. 先调用 `planDraft()`
2. 有风险时做二次确认
3. 再调用 `publishDraft()`

发布成功后建议刷新：

1. `resources()->detail()`
2. `fields()`
3. `versionPanel()`

## 版本历史区

直接复用已有版本面板：

```php
Easy::release('articles', 'cms')->versionPanel(1, 20, [
    'status' => 'draft',
    'keyword' => '标题',
]);
```

建议映射：

- 顶部摘要：`summary`
- 状态统计：`stats`
- 分页：`pagination`
- 列表动作：`items[].actions`
- 变更概览：`items[].change_summary`

版本详情使用：

```php
Easy::release('articles', 'cms')->versionDetail($versionId);
```

可直接展示：

- `schema`
- `actions`
- `change_summary`
- `plan`

## 推荐联调顺序

### 第一阶段

1. 跑通模型列表页
2. 跑通新建模型
3. 跑通模型详情顶部摘要

### 第二阶段

1. 跑通字段列表
2. 跑通新增字段
3. 跑通编辑字段
4. 跑通重命名字段
5. 跑通删除字段
6. 跑通字段排序

### 第三阶段

1. 跑通预览发布
2. 跑通正式发布
3. 跑通版本历史和版本详情抽屉

## 当前结论

前端最合理的实现方式是：

- 模型列表页消费 `resources()->lists()`
- 模型详情页消费 `resources()->detail()`
- 字段维护消费 `fields()/draftSchema()/字段增改删排`
- 发布面板消费 `planDraft()/publishDraft()`
- 版本历史消费 `versionPanel()/versionDetail()`

这样可以最小化前后端协议反复调整的成本。
