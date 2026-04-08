<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Handle;

use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Contracts\IResource;

/**
 * Schema 配置句柄.
 *
 * 只负责 schema 的编译、预览、映射和校验，不承担数据读写或版本发布职责。
 */
final class SchemaHandle
{
    /** @var ResourceHandle */
    private $handle;

    public function __construct(ResourceHandle $handle)
    {
        $this->handle = $handle;
    }

    /**
     * 返回编译后的 schema 定义对象.
     */
    public function definition(): SchemaDefinition
    {
        return $this->handle->schema();
    }

    /**
     * 返回标准化 schema 蓝图.
     *
     * @return array<string, mixed>
     */
    public function blueprint(): array
    {
        return $this->handle->blueprint();
    }

    /**
     * 返回字段映射集合.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fieldMappings(): array
    {
        return $this->handle->fieldMappings();
    }

    /**
     * 触发 schema 编译与校验，并返回定义对象.
     */
    public function validate(): SchemaDefinition
    {
        return $this->definition();
    }

    /**
     * 返回原始资源定义对象.
     */
    public function raw(): IResource
    {
        return $this->handle->raw();
    }

    /**
     * 导出 schema 定义数组.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->definition()->toArray();
    }
}
