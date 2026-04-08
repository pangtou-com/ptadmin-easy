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

    protected function getCheckboxRule(): array
    {
        return [];
    }

    protected function getSelectRule()
    {
        if ($this->isMultiple()) {
            return $this->getCheckboxRule();
        }

        return 'in:'.implode(',', $this->getOptionRules());
    }

    protected function getSelectAttribute($value)
    {
        if ($this->isMultiple()) {
            return $this->getCheckboxAttribute($value);
        }

        return $value;
    }

    protected function setSelectAttribute($value): ?string
    {
        if ($this->isMultiple()) {
            return $this->setCheckboxAttribute($value);
        }

        if (null === $value || '' === $value) {
            return null;
        }

        return (string) $value;
    }

    protected function getCheckboxAttribute($value)
    {
        if (null === $value) {
            return null;
        }
        if (\is_array($value)) {
            return $value;
        }

        return explode(',', trim($value, ','));
    }

    protected function setCheckboxAttribute($value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (\is_array($value)) {
            return ','.implode(',', $value).',';
        }

        return ','.trim((string) $value, ',').',';
    }
}
