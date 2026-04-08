<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Support;

use PTAdmin\Easy\Core\Schema\Definition\FieldDefinition;

/**
 * 字段类型注册表.
 *
 * 统一维护字段类型对应的数据库、过滤、排序等能力描述。
 */
class FieldTypeRegistry
{
    /** @var array<string, array<string, mixed>> */
    private $types = [];

    public function __construct(array $types = [])
    {
        $this->types = 0 === \count($types) ? $this->defaults() : $types;
    }

    /**
     * 注册新的字段类型定义.
     */
    public function register(string $type, array $definition): void
    {
        $this->types[$type] = $definition;
    }

    /**
     * 指定字段类型是否已注册.
     */
    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * 获取字段类型定义，未知类型回退到 text.
     */
    public function get(string $type): array
    {
        return $this->types[$type] ?? $this->types['text'];
    }

    /**
     * 基于字段定义生成最终能力描述.
     */
    public function resolve(FieldDefinition $field): array
    {
        $base = $this->get($field->type());

        return array_merge($base, [
            'type' => $field->type(),
            'name' => $field->name(),
            'label' => $field->label(),
            'required' => $field->isRequired(),
        ]);
    }

    /**
     * 默认支持的字段类型集合.
     */
    private function defaults(): array
    {
        return [
            'text' => $this->definition('text', 'string', 'string', ['=', 'like', 'in'], true, true, 'string'),
            'textarea' => $this->definition('text', 'text', 'string', ['like'], true, false, 'string'),
            'number' => $this->definition('number', 'integer', 'int', ['=', '>', '>=', '<', '<=', 'between', 'in'], true, true, 'int'),
            'amount' => $this->definition('number', 'integer', 'float', ['=', '>', '>=', '<', '<=', 'between'], true, true, 'float'),
            'radio' => $this->definition('select', 'tinyinteger', 'mixed', ['=', 'in'], true, true, 'mixed'),
            'switch' => $this->definition('select', 'tinyinteger', 'bool', ['=', 'in'], true, true, 'bool'),
            'checkbox' => $this->definition('select', 'string', 'array', ['contains'], true, false, 'array'),
            'select' => $this->definition('select', 'mixed', 'mixed', ['=', 'in', 'contains'], true, true, 'mixed'),
            'date' => $this->definition('date', 'unsignedInteger', 'string', ['=', '>', '>=', '<', '<=', 'between'], true, true, 'string'),
            'datetime' => $this->definition('date', 'unsignedInteger', 'string', ['=', '>', '>=', '<', '<=', 'between'], true, true, 'string'),
            'json' => $this->definition('func', 'json', 'array', ['contains'], false, false, 'array'),
            'cascader' => $this->definition('select', 'json', 'array', ['contains'], false, false, 'array'),
            'file' => $this->definition('file', 'string', 'mixed', ['='], false, false, 'mixed'),
            'resource' => $this->definition('file', 'string', 'mixed', ['='], false, false, 'mixed'),
            'image' => $this->definition('file', 'string', 'mixed', ['='], false, false, 'mixed'),
        ];
    }

    /**
     * 创建标准字段类型定义.
     *
     * @param string[] $operators
     *
     * @return array<string, mixed>
     */
    private function definition(
        string $family,
        string $dbType,
        string $phpType,
        array $operators,
        bool $filterable,
        bool $sortable,
        string $runtimeCast
    ): array {
        return [
            'family' => $family,
            'db_type' => $dbType,
            'php_type' => $phpType,
            'operators' => $operators,
            'filterable' => $filterable,
            'sortable' => $sortable,
            'storage' => [
                'db_type' => $dbType,
                'php_type' => $phpType,
                'runtime_cast' => $runtimeCast,
            ],
            'query' => [
                'filterable' => $filterable,
                'sortable' => $sortable,
                'operators' => $operators,
            ],
        ];
    }
}
