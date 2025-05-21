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

trait SelectComponentTrait
{
    protected function getRadioRule(): ?string
    {
        return 'in:'.implode(',', $this->getOptionRules());
    }

    protected function getCheckboxRule(): ?string
    {
        return 'array|in:'.implode(',', $this->getOptionRules());
    }

    protected function getSelectRule(): ?string
    {
        if ($this->isMultiple()) {
            return 'array|in:'.implode(',', $this->getOptionRules());
        }

        return 'in:'.implode(',', $this->getOptionRules());
    }
}
