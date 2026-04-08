<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Compiler;

use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Core\Support\FieldTypeRegistry;

/**
 * Schema 编译器.
 *
 * 负责在资源定义对象与只读 SchemaDefinition 之间做转换，并在
 * 编译前执行基础 schema 校验。
 */
class SchemaCompiler
{
    /** @var FieldTypeRegistry */
    private $fieldTypeRegistry;

    /** @var SchemaValidator */
    private $validator;

    public function __construct(?FieldTypeRegistry $fieldTypeRegistry = null, ?SchemaValidator $validator = null)
    {
        $this->fieldTypeRegistry = $fieldTypeRegistry ?? new FieldTypeRegistry();
        $this->validator = $validator ?? new SchemaValidator();
    }

    public function compile(IResource $resource): SchemaDefinition
    {
        $this->validator->validate((array) $resource->toArray());

        return new SchemaDefinition($resource, $this->fieldTypeRegistry);
    }

    public function fieldTypeRegistry(): FieldTypeRegistry
    {
        return $this->fieldTypeRegistry;
    }

    /**
     * 返回 schema 校验器实例.
     */
    public function validator(): SchemaValidator
    {
        return $this->validator;
    }
}
