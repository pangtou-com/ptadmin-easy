<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Query;

/**
 * 单个聚合指标定义.
 *
 * 用于表达 count/sum/avg/min/max 等统计指标。
 */
final class QueryAggregate
{
    /** @var string */
    private $type;

    /** @var string|null */
    private $field;

    /** @var string|null */
    private $alias;

    public function __construct(string $type, ?string $field = null, ?string $alias = null)
    {
        $this->type = strtolower($type);
        $this->field = null !== $field && '' !== $field ? $field : null;
        $this->alias = null !== $alias && '' !== $alias ? $alias : null;
    }

    /**
     * 返回聚合类型.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * 返回聚合字段名.
     */
    public function field(): ?string
    {
        return $this->field;
    }

    /**
     * 返回聚合结果别名.
     */
    public function alias(): ?string
    {
        return $this->alias;
    }
}
