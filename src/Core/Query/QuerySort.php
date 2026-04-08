<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Query;

/**
 * 单个排序条件.
 */
final class QuerySort
{
    /** @var string */
    private $field;

    /** @var string */
    private $direction;

    public function __construct(string $field, string $direction = 'asc')
    {
        $this->field = $field;
        $this->direction = 'desc' === strtolower($direction) ? 'desc' : 'asc';
    }

    /**
     * 返回排序字段.
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * 返回排序方向.
     */
    public function direction(): string
    {
        return $this->direction;
    }
}
