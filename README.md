<p align="center">
    <a href="https://www.pangtou.com"><img src="./public/logo.jpg" style="height: 500px" alt="PTAdmin"></a>
</p>

# ptadmin/easy
[![Version](https://img.shields.io/packagist/v/ptadmin/easy?label=version)](https://packagist.org/packages/ptadmin/easy)
[![Downloads](https://img.shields.io/packagist/dt/ptadmin/easy)](https://packagist.org/packages/ptadmin/easy)
[![License](https://img.shields.io/packagist/l/ptadmin/easy)](https://packagist.org/packages/ptadmin/easy)
[![Sponsor](https://img.shields.io/static/v1?label=Sponsor&message=%E2%9D%A4)](https://www.pangtou.com/easy)
[![PTAdmin](https://img.shields.io/static/v1?label=Docs&message=PTAdmin&logo=readthedocs)](https://www.pangtou.com)

> PTAdmin 模型处理板块，可扩展自定义模型类型，扩展开发模型,需基于[PTAdmin](https://www.pangtou.com)使用

## 介绍
> 在日常开发中多数的CURD操作都是重复的，ptadmin-easy提供了一套通用的CURD操作方法，以及一套可扩展组件，可以帮助快速开发自定义模型，减少开发成本
> 结合[PTAdmin](https://www.pangtou.com)中功能模块可实现管理后台的快速搭建，搭配着我们的[插件市场](https://www.pangtou.com/addon.html)
> [模版市场](https://www.pangtou.com/templates.html)可以快速丰富系统功能。
> - [PTAdmin/Admin](https://gitee.com/ptadmin/ptadmin-admin) 管理后台
> - [PTAdmin/Build](https://gitee.com/ptadmin/ptadmin-build) 基于layui的表单构建工具
> - [PTAdmin/Addon](https://gitee.com/ptadmin/ptadmin-addon) PTAdmin插件应用管理
> - [PTAdmin/Html](https://gitee.com/ptadmin/ptadmin-html) 基于PHP生成html标签

## 安装
```shell
composer require ptadmin/easy
```

## 使用
> 详情操作手册请查看[文档地址](https://www.pangtou.com/docs/ptadmin/easy/index.html)

```php
use PTAdmin\Easy\Easy;
# 模型构建,提供了模型创建的CURD方法
$mod = Easy::mod();

# 模型字段构建,提供了字段创建的CURD方法
$field = Easy::field();

# 表单处理
$modTableName = ""; // 创建模型时的标识名称
$form = \PTAdmin\Easy\EasyForm::handler($modTableName);
```

## 任务列表
> 以下是当前支持的组件和功能信息，并在未来期望达到的功能
#### 表单组件支持
- [ ] 文本类型
  - [x] 单行文本
  - [x] 多行文本
  - [ ] 富文本
  - [x] 密码
  - [ ] 邮箱
  - [ ] 手机
  - [ ] 链接
  - [ ] 身份证
  - [ ] 颜色
- [ ] 附件类型
  - [ ] 单文件上传
  - [ ] 多文件上传
  - [ ] 单图上传
  - [ ] 多图上传
  - [ ] 单视频上传
  - [ ] 多视频上传
- [ ] 数值类型
  - [ ] 整数
  - [ ] 小数
  - [ ] 货币
  - [ ] 百分比
  - [ ] 计数器
  - [ ] 滑块
  - [ ] 评分
- [ ] 选项类型
  - [ ] 单选框
  - [ ] 多选框
  - [ ] 单选下拉框
  - [ ] 多选下拉框
  - [ ] 切换按钮
- [ ] 时间日期类型
  - [ ] 日期选择
  - [ ] 时间选择
  - [ ] 日期时间选择
  - [ ] 年选择
  - [ ] 月选择
  - [ ] 年月选择
  - [ ] 区间选择
- [ ] 对象类型
  - [ ] 树选择
  - [ ] 联动选择
  - [ ] 级联选择
  - [ ] 搜索选择
  - [ ] 键值对类型

#### 扩展支持
- [ ] 自定义组件
- [ ] 自定义扩展

#### 功能支持
- [ ] 导入Excel数据
- [ ] 导出Excel数据
- [ ] 批量删除
- [ ] 批量修改
- [ ] 子表单加载
- [ ] 搜索功能
- [ ] 列表排序
- [x] CURD


问题梳理：
1、模型新增时
 - 扩展字段
   > 新增模型时可新增字段，如模型中需要设定某个权限限制时需要新增字段。可以通过扩展字段的方式实现
 - 扩展规则
   > 可设置模型规则，如模型生成的表单类型，表单扩展