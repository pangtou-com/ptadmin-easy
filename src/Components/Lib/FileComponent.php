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

class FileComponent extends AbstractComponent
{
    protected function initialize(): void
    {
        $type = $this->filed->getType();
        // 当上传数据为多文件时，使用json保存
        if ('images' === $type || !$this->isSingle()) {
            $this->column_type = 'json';

            return;
        }
        $this->column_type = 'string';
    }

    private function isSingle(): bool
    {
        return 1 === (int) $this->filed->getMetadata('extends.limit', 1);
    }
}
