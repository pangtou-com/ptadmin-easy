<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2025 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy;

use Illuminate\Support\Facades\Facade;
use PTAdmin\Easy\Components\Component;
use PTAdmin\Easy\Contracts\IDocx;
use PTAdmin\Easy\Contracts\IEasyManager;
use PTAdmin\Easy\Engine\Model\Document;
use PTAdmin\Easy\Engine\Schema\Schema;

/**
 * @method static IDocx docx(string|array $docx, string $module = '')        文档对象
 * @method static Schema schema($docx, string $module = "")            数据表处理对象，用于新增表结构，更新表结构，删除表结构等
 * @method static bool hasDocx(string $docx)                           文档是否存在
 * @method static Component component()                                组件管理器
 * @method static mixed hooks()                                        事件处理钩子对象
 * @method static Document document(string $docx, string $module = '') 文档模型
 * @method static mixed charts(string $docx)                           统计模块
 * @method static bool isDevelop()                                     是否为开发模式
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
