# 数据结构

## 文本
- 类型：text
- 描述：文本类型，用于存储文本数据，如文章内容、评论内容等。

## 邮件

## 多行文本
## 密码
## 颜色
## 链接
## 富文本
## ICON
## 自动生成

## 数值
## 金额
## 文件
## 单选框
## 多选框
## 下拉选择
## 日期
## 日期时间
## 块
## 关联
> 一对一链接类型
## json
## 表
> 一对多的关联关系，会创建数据表字段

## 克隆数据
> 镜像数据: 不会创建数据表字段，按照数据中存在 link 类型的字段，创建新的字段，并复制数据，会跟随link关联表改动

## 镜像数据
> 镜像数据: 会创建新的字段，按照数据中存在 link 类型的字段拷贝数据，不会根据link关联表改动



```json 扩展信息展示
{"name": "status", "type": "radio", "label": "状态", "options": [
    {"label": "已启用", "value": 1, "color": "#ccc"},
    {"label": "未启用", "value": 0, "color": "#ccc"}
], "default":  0, "extends":  {
    "type": "config",
    "key": "constant.status",
    "intro": "这个配置来源与laravel的系统配置信息，配置个格式与options一致"
}, "extends1":  {
    "type": "resource",
    "table": "articles",
    "label": "展示的值",
    "value": "读取的值",
    "intro": "这个配置来源与laravel的系统配置信息，配置个格式与options一致"
}, "extends2":  {
    "type": "textarea",
    "content": "aa=d",
    "intro": "这个配置来源与laravel的系统配置信息，配置个格式与options一致"
}}
```

