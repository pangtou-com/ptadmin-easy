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

namespace PTAdmin\Easy\Components\Lib;

use PTAdmin\Easy\Components\AbstractComponent;

class NumberComponent extends AbstractComponent
{
    protected $column_type = 'integer';
    private $args = [];

    public function getColumnArguments(): array
    {
        return [$this->filed->getName(), $this->args[0], $this->args[1]];
    }

    protected function initialize(): void
    {
        $extends = $this->filed->getMetadata('extends', []);
        // 当数值类型为小于255时，存储为tinyinteger类型
        if (isset($extends['max']) && $extends['max'] <= 255) {
            $this->column_type = 'tinyinteger';
        }
        // 默认情况下存储为无符号整数
        $this->args = [false, true];
        if (isset($extends['signed']) && 1 === (int) $extends['signed']) {
            $this->args[1] = false;
        }
    }
}
