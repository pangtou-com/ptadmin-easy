<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2026 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy;

use Illuminate\Support\Facades\Facade;
use PTAdmin\Easy\Components\Component;
use PTAdmin\Easy\Contracts\IEasyManager;
use PTAdmin\Easy\Core\Query\BuilderQueryApplier;
use PTAdmin\Easy\Engine\Model\Document;
use PTAdmin\Easy\Engine\Schema\Schema;
use PTAdmin\Easy\Handle\ChartHandle;
use PTAdmin\Easy\Handle\DocHandle;
use PTAdmin\Easy\Handle\ReleaseHandle;
use PTAdmin\Easy\Handle\SchemaHandle;

/**
 * @method static DocHandle doc(array|string $resource, string $module = '')      资源运行时句柄
 * @method static SchemaHandle schema(array|string $resource, string $module = '') schema 配置句柄
 * @method static ReleaseHandle release(array|string $resource, string $module = '') 资源发布句柄
 * @method static Schema table($resource, string $module = "")                    数据表处理对象，用于新增表结构，更新表结构，删除表结构等
 * @method static bool hasResource(string $resource)                              资源是否存在
 * @method static Component component()                                       组件管理器
 * @method static mixed hooks()                                               事件处理钩子对象
 * @method static mixed scopes()                                              数据范围管理器
 * @method static Document document(string $resource, string $module = '')    文档模型
 * @method static BuilderQueryApplier builderQuery()                          通用 Builder 查询 DSL 适配器
 * @method static ChartHandle charts(array|string $resource, string $module = '') 统计模块句柄
 * @method static bool isDevelop()                                            是否为开发模式
 *
 * @see IEasyManager
 * @see 文档地址 https://docs.pangtou.com/easy-forms
 */
class Easy extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return 'easy';
    }
}
