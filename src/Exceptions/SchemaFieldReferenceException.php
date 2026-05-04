<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Exceptions;

/**
 * 字段仍被 schema 其他配置引用时抛出的异常。
 */
class SchemaFieldReferenceException extends InvalidDataException
{
    /** @var string */
    private $field;

    /** @var string */
    private $operation;

    /** @var array<int, array<string, mixed>> */
    private $references;

    /**
     * @param array<int, array<string, mixed>> $references
     */
    public function __construct(string $field, string $operation, array $references)
    {
        $this->field = $field;
        $this->operation = $operation;
        $this->references = array_values(array_filter($references, 'is_array'));

        parent::__construct(
            'Schema field ['.$field.'] is referenced and cannot be '.$operation.'.'
        );
    }

    public function field(): string
    {
        return $this->field;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function references(): array
    {
        return $this->references;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => false,
            'field' => $this->field,
            'operation' => $this->operation,
            'message' => $this->getMessage(),
            'references' => $this->references,
        ];
    }
}
