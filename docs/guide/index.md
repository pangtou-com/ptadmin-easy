# PTAdmin/Easy

## 介绍
PTAdmin/Easy 是一个面向动态建模场景的开发库，用于通过 schema 定义资源结构，并快速生成可运行的 CRUD 能力。

搭配 PTAdmin 后台时，推荐将其理解为三条主链：

- `Easy::schema(...)`
  用于 schema 编译、校验、蓝图预览、字段映射查看
- `Easy::release(...)`
  用于 schema 草稿、发布、回滚、版本历史管理
- `Easy::doc(...)`
  用于已发布资源的数据 CRUD、查询、关联加载与聚合

## 当前状态

当前项目阶段可定义为：

- 核心主链已完成，可进入联调和实际接入阶段
- 高级协议和增强能力待后续继续深化

当前已经可稳定使用的范围包括：

- schema 管理主链
- 发布 / 回滚 / 版本管理主链
- doc 运行时主链
- 展示值映射基础能力
- 关联基础能力

## 基础数据结构

PTAdmin/Easy 当前围绕以下几类核心存储组织：

- `mods`
  资源主记录
- `mod_versions`
  schema 版本真源
- `mod_fields`
  当前发布版本的字段编译缓存

## 推荐接入顺序

1. 前端生成或维护 schema JSON
2. 使用 `Easy::schema($schema)` 做校验、蓝图预览、字段映射检查
3. 使用 `Easy::release($resource, $module)` 保存草稿
4. 基于版本 ID 预览发布计划
5. 基于版本 ID 正式发布
6. 发布后通过 `Easy::doc($resource, $module)` 执行 CRUD、查询、关联加载与聚合

## 推荐阅读

- [Schema 生命周期](/guide/schema-lifecycle.md)
- [关联使用](/guide/relation.md)
- [最小接入 Demo](/guide/quickstart-demo.md)
- [待办事项](/guide/todo.md)

## 使用建议

- 当前阶段应优先围绕“联调可落地”推进
- 前端 schema 协议未完全收口前，建议先稳定主链接入，不要过早固化更多增强协议
- 第一次接入时，建议先按 [最小接入 Demo](/guide/quickstart-demo.md) 跑通一遍
- 需要完整流程示例时，优先查看 [schema-lifecycle.md](/guide/schema-lifecycle.md)
- 需要关联写入、加载、过滤、排序示例时，优先查看 [relation.md](/guide/relation.md)
