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

namespace PTAdmin\Easy\Engine\Docx;

use PTAdmin\Easy\Engine\Docx\Traits\BaseDocxTrait;

class DocxNameParser
{
    use BaseDocxTrait;

    public static function handle(string $docx, $namespace = ''): self
    {
        $instance = new self();
        $instance->parser($docx, $namespace);

        return $instance;
    }
}
