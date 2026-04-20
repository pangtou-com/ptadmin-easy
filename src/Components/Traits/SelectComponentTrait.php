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

        if (method_exists($this, 'isSourceResource') && $this->isSourceResource()) {
            return $this->getResourceSelectRule();
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

    protected function getResourceSelectRule()
    {
        $resource = $this->getOptionResource();
        if (null === $resource) {
            return null;
        }

        $extends = (array) $this->getMetadata('extends', []);
        $valueColumn = (string) ($extends['value'] ?? $resource->getPrimaryKey());
        $filters = (array) ($extends['filter'] ?? []);
        $rule = \Illuminate\Validation\Rule::exists($resource->getRawTable(), $valueColumn);

        $rule->where(function ($query) use ($filters, $resource): void {
            if ($resource->allowRecycle()) {
                $query->whereNull('deleted_at');
            }

            foreach ($filters as $key => $filter) {
                if (\is_string($key) && !\is_array($filter)) {
                    $query->where($key, $filter);

                    continue;
                }

                if (!\is_array($filter)) {
                    continue;
                }

                $field = $filter['field'] ?? $filter['name'] ?? null;
                if (!\is_string($field) || '' === $field) {
                    continue;
                }

                $operator = strtolower((string) ($filter['operator'] ?? '='));
                $value = $filter['value'] ?? null;
                if ('in' === $operator && \is_array($value)) {
                    $query->whereIn($field, $value);

                    continue;
                }

                $query->where($field, $operator, $value);
            }
        });

        return $rule;
    }
}
