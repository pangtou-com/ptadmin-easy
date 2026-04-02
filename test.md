# Easy执行过程

## 开发环境
> 可以通过json动态调整内容
> 
### 自定义调用方式
```php
    // 全局事件管理模块 
    Easy::events("addon::erp.niche", [
    
    ])  
    Easy::docx("addon::erp.niche")->call("save") 
    Easy::docx("addon::erp.niche")->setControl()->call("save")
    Easy::docx("addon::erp.niche")->events([
        "verify_before" => function($data){}
        "verify_after" => function($data){}
        "before" => function($data){}
        "after" => function($data){}
    ])->call("save")
    
    Easy::docx("addon::erp.niche")->verify_before()->verify_after()->call("save")
    // 执行之前 
    Easy::docx("addon::erp.niche")->before()->after()->call("save")
    Easy::docx("addon::erp.niche")->save()
    Easy::docx("addon::erp.niche")->edit()
    Easy::docx("addon::erp.niche")->delete()
    Easy::docx("addon::erp.niche")->batch_delete() // 批量删除
    Easy::docx("addon::erp.niche")->page()
    Easy::docx("addon::erp.niche")->next()
    Easy::docx("addon::erp.niche")->prev()
    Easy::docx("addon::erp.niche")->find()
    Easy::docx("addon::erp.niche")->detail()
    Easy::docx("addon::erp.niche")->import()
    Easy::docx("addon::erp.niche")->export()
    Easy::docx("addon::erp.niche")->print()
    Easy::docx("addon::erp.niche")->copy()
    Easy::docx("addon::erp.niche")->generatPDF()
 ```


### 模型管理工具
```php
    Easy::mod()->find("id");
    Easy::mod()->detail("id");
    Easy::field()->detail("id");
    Easy::field()->detail("id");
    // 导出
    Easy::mod()->export()
    // 导入
    Easy::mod()->import()
    // 生成缓存
    Easy::mod()->generatCache()
    // 生成json
    Easy::mod()->generateJson()
    // 通过json生成数据表
    Easy::mod()->jsonToTable()
    // 通过json创建数据记录
    Easy::mod()->jsonCreate()
```

### 统一的路由控制器方法调用
```php
    $data = [
        'namespace' => 'app',
        'docx' => "app::erp.niche",
        'method' => "save",
        "args" => [] 
    ];
```

## 生产环境
> 生产环境时应通过缓存或者数据库配置的方式加载表结构信息

### 1、缓存方式

### 2、数据库