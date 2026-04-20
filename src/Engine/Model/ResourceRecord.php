<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Engine\Model;

use PTAdmin\Easy\Core\Runtime\ExecutionContext;

/**
 * 轻量资源记录对象.
 *
 * 运行时读写主链路优先返回该对象，避免直接依赖 Eloquent Model，
 * 同时保留属性访问、`toArray()` 和追加字段能力。
 */
class ResourceRecord
{
    /** @var array<string, mixed> */
    private $attributes = [];

    /** @var array<string, mixed> 运行时附加缓存，不参与持久化 */
    private $runtime = [];

    /** @var Document|null */
    private $document;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [], ?Document $document = null)
    {
        $this->attributes = $attributes;
        $this->document = $document;
    }

    public function setDocument(?Document $document): self
    {
        $this->document = $document;

        return $this;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function rawAttributes(): array
    {
        return $this->attributes;
    }

    public function currentContext(): ?ExecutionContext
    {
        return null !== $this->document ? $this->document->currentContext() : null;
    }

    /**
     * 写入运行时缓存值，用于批量预加载 append 等场景。
     *
     * @param mixed $value
     */
    public function setRuntimeValue(string $key, $value): self
    {
        $this->runtime[$key] = $value;

        return $this;
    }

    public function hasRuntimeValue(string $key): bool
    {
        return array_key_exists($key, $this->runtime);
    }

    /**
     * 读取运行时缓存值.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function getRuntimeValue(string $key, $default = null)
    {
        return $this->runtime[$key] ?? $default;
    }

    /**
     * 返回可直接暴露给外部的运行时字段。
     *
     * 以 `append:` 开头的键仅作为内部缓存，不应透出给调用方。
     *
     * @return array<string, mixed>
     */
    public function visibleRuntimeValues(): array
    {
        return array_filter($this->runtime, static function ($value, string $key): bool {
            return 0 !== strpos($key, 'append:');
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * 获取主键值.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * 将记录导出为运行时数组结构.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (null === $this->document) {
            return $this->attributes;
        }

        $attributes = $this->attributes;
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->document->getMutatedAttributeValue($this, (string) $key, $value);
        }

        return array_merge($attributes, $this->visibleRuntimeValues(), $this->document->getAppendsValue($this));
    }

    /**
     * 按属性读取运行时值.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];

            return null !== $this->document
                ? $this->document->getMutatedAttributeValue($this, $key, $value)
                : $value;
        }

        if ($this->hasRuntimeValue($key)) {
            return $this->getRuntimeValue($key);
        }

        if (null !== $this->document) {
            return $this->document->getAppendValue($this, $key);
        }

        return null;
    }

    /**
     * 直接写入原始属性值.
     *
     * @param mixed $value
     */
    public function __set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->attributes) || null !== $this->__get($key);
    }
}
