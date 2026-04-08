<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Runtime;

final class ExecutionContext
{
    /** @var array */
    private $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function get(string $key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * 设置上下文属性.
     *
     * @param mixed $value
     */
    public function set(string $key, $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * 判断上下文属性是否存在.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }
}
