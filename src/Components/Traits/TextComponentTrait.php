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

namespace PTAdmin\Easy\Components\Traits;

trait TextComponentTrait
{
    protected function getTextRule(): ?string
    {
        $length = $this->getMetadata('length', 255);

        return 'max:'.$length;
    }

    protected function getTextareaRule(): ?string
    {
        $length = $this->getMetadata('length', 255);

        return 'max:'.$length;
    }

    protected function getPasswordRule(): array
    {
        $length = $this->getMetadata('length', 255);
        $min = $this->getMetadata('min', 6);

        return ['min:'.$min, 'max:'.$length];
    }

    protected function getEmailRule(): string
    {
        $length = $this->getMetadata('length', 255);

        return 'email|max:'.$length;
    }

    protected function getUrlRule(): string
    {
        $length = $this->getMetadata('length', 255);

        return 'url|max:'.$length;
    }

    protected function getColorRule(): string
    {
        $length = $this->getMetadata('length', 255);

        return 'max:'.$length;
    }

    protected function getIconRule(): string
    {
        $length = $this->getMetadata('length', 255);

        return 'max:'.$length;
    }
}
