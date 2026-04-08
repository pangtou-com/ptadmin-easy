<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Query;

/**
 * 单个查询过滤条件.
 */
final class QueryFilter
{
    /** @var string */
    private $field;

    /** @var string */
    private $operator;

    /** @var mixed */
    private $value;

    /**
     * @param mixed $value
     */
    public function __construct(string $field, string $operator, $value)
    {
        $this->field = $field;
        $this->operator = strtolower($operator);
        $this->value = $value;
    }

    /**
     * 返回过滤字段名.
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * 返回过滤操作符.
     */
    public function operator(): string
    {
        return $this->operator;
    }

    /**
     * 返回过滤值.
     */
    public function value()
    {
        return $this->value;
    }
}
