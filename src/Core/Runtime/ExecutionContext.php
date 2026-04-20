<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Runtime;

final class ExecutionContext
{
    private const SENSITIVE_FIELDS_KEY = 'runtime.sensitive.allowed_fields';
    private const SENSITIVE_ALL_KEY = 'runtime.sensitive.allow_all';

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

    /**
     * @param string|string[] $fields
     */
    public function allowSensitive($fields): self
    {
        $allowed = $this->allowedSensitiveFields();
        foreach ((array) $fields as $field) {
            if (!\is_string($field) || '' === trim($field)) {
                continue;
            }

            $allowed[] = trim($field);
        }

        $this->attributes[self::SENSITIVE_FIELDS_KEY] = array_values(array_unique($allowed));

        return $this;
    }

    public function allowAllSensitive(): self
    {
        $this->attributes[self::SENSITIVE_ALL_KEY] = true;

        return $this;
    }

    public function canAccessSensitive(string $field): bool
    {
        if (true === (bool) ($this->attributes[self::SENSITIVE_ALL_KEY] ?? false)) {
            return true;
        }

        return \in_array($field, $this->allowedSensitiveFields(), true);
    }

    /**
     * @return string[]
     */
    public function allowedSensitiveFields(): array
    {
        return array_values(array_filter((array) ($this->attributes[self::SENSITIVE_FIELDS_KEY] ?? []), 'is_string'));
    }
}
