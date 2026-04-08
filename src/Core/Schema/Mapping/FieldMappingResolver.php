<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Mapping;

use PTAdmin\Easy\Core\Schema\Definition\FieldDefinition;
use PTAdmin\Easy\Easy;

/**
 * 字段映射解析器.
 *
 * 负责把 schema 字段定义统一整理为一份可消费的映射结果，
 * 供前端编辑器、发布预览、迁移确认和后续统计模块复用。
 */
final class FieldMappingResolver
{
    /**
     * 解析字段的统一映射结构.
     *
     * @return array<string, mixed>
     */
    public function resolve(FieldDefinition $field): array
    {
        $component = $field->raw()->getComponent();
        $columnType = $component->getColumnType();
        $columnArguments = $component->getColumnArguments();
        $capabilities = $field->capabilities();

        return [
            'component' => $this->componentMapping($field),
            'storage' => $this->storageMapping($field, $capabilities, $columnType, $columnArguments),
            'validation' => [
                'required' => $field->isRequired(),
                'unique' => $field->isUnique(),
                'rules' => $field->rules(),
                'messages' => $field->ruleMessages(),
            ],
            'query' => [
                'filterable' => true === (bool) ($capabilities['filterable'] ?? false),
                'sortable' => true === (bool) ($capabilities['sortable'] ?? false),
                'operators' => array_values((array) ($capabilities['operators'] ?? [])),
                'searchable' => $this->isSearchable($field),
            ],
            'display' => $field->display(),
        ];
    }

    /**
     * 解析组件层映射.
     *
     * @return array<string, mixed>
     */
    private function componentMapping(FieldDefinition $field): array
    {
        $component = (array) (Easy::component()->getComponent($field->type()) ?? []);

        return [
            'type' => $field->type(),
            'origin_type' => $field->originType(),
            'group' => (string) ($component['group'] ?? ''),
            'virtual' => $field->isVirtual(),
            'append' => $field->isAppend(),
            'relation' => 0 !== \count($field->relation()),
            'multiple' => $this->isMultiple($field),
            'has_options' => 0 !== \count($field->options()),
        ];
    }

    /**
     * 解析存储层映射.
     *
     * @param array<string, mixed> $capabilities
     * @param array<int, mixed>    $columnArguments
     *
     * @return array<string, mixed>
     */
    private function storageMapping(FieldDefinition $field, array $capabilities, string $columnType, array $columnArguments): array
    {
        return [
            'column_type' => $columnType,
            'column_arguments' => array_values($columnArguments),
            'column_definition' => $this->columnDefinition($field, $columnType, $columnArguments),
            'db_type' => (string) ($capabilities['db_type'] ?? data_get($capabilities, 'storage.db_type', $columnType)),
            'php_type' => (string) ($capabilities['php_type'] ?? data_get($capabilities, 'storage.php_type', 'mixed')),
            'runtime_cast' => (string) data_get(
                $capabilities,
                'storage.runtime_cast',
                $this->defaultRuntimeCast($field, $columnType)
            ),
            'nullable' => !$field->isRequired(),
            'default' => $field->defaultValue(),
            'comment' => $field->comment(),
            'length' => $this->extractLength($field, $columnType, $columnArguments),
            'unsigned' => $this->isUnsigned($columnType, $columnArguments),
        ];
    }

    /**
     * 生成人类可读的列定义描述.
     */
    private function columnDefinition(FieldDefinition $field, string $columnType, array $columnArguments): string
    {
        switch ($columnType) {
            case 'string':
                $length = $this->extractLength($field, $columnType, $columnArguments) ?? 255;

                return 'varchar('.$length.')';

            case 'text':
                return 'text';

            case 'json':
                return 'json';

            case 'tinyinteger':
                return $this->isUnsigned($columnType, $columnArguments) ? 'tinyint unsigned' : 'tinyint';

            case 'integer':
                return $this->isUnsigned($columnType, $columnArguments) ? 'int unsigned' : 'int';

            case 'unsignedInteger':
                return 'int unsigned';

            default:
                return $columnType;
        }
    }

    /**
     * 推断字段长度.
     *
     * @param array<int, mixed> $columnArguments
     */
    private function extractLength(FieldDefinition $field, string $columnType, array $columnArguments): ?int
    {
        $length = $field->metadata()['length'] ?? null;
        if (\is_numeric($length)) {
            return (int) $length;
        }

        if ('string' !== $columnType) {
            return null;
        }

        $argument = $columnArguments[1] ?? null;

        return \is_numeric($argument) ? (int) $argument : null;
    }

    /**
     * 推断字段是否为无符号.
     *
     * @param array<int, mixed> $columnArguments
     */
    private function isUnsigned(string $columnType, array $columnArguments): bool
    {
        if ('unsignedInteger' === $columnType) {
            return true;
        }

        if (!\in_array($columnType, ['integer', 'tinyinteger'], true)) {
            return false;
        }

        return true === (bool) ($columnArguments[2] ?? false);
    }

    /**
     * 推断运行时 cast 类型.
     */
    private function defaultRuntimeCast(FieldDefinition $field, string $columnType): string
    {
        if ('amount' === $field->type()) {
            return 'float';
        }

        if ('switch' === $field->type()) {
            return 'bool';
        }

        if (\in_array($field->type(), ['date', 'datetime'], true)) {
            return 'string';
        }

        if ('json' === $columnType || \in_array($field->type(), ['checkbox', 'cascader', 'json', 'clone'], true)) {
            return 'array';
        }

        if (\in_array($columnType, ['integer', 'tinyinteger', 'unsignedInteger'], true)) {
            return 'int';
        }

        return 'mixed';
    }

    /**
     * 推断字段是否为多值组件.
     */
    private function isMultiple(FieldDefinition $field): bool
    {
        if (true === (bool) ($field->metadata()['multiple'] ?? false)) {
            return true;
        }

        if (\in_array($field->type(), ['checkbox', 'images', 'cascader', 'json', 'clone'], true)) {
            return true;
        }

        if (\in_array($field->type(), ['file', 'files', 'image', 'resource'], true)) {
            return (int) data_get($field->metadata(), 'extends.limit', 1) > 1;
        }

        return false;
    }

    /**
     * 判断字段是否处于 schema 搜索范围内.
     */
    private function isSearchable(FieldDefinition $field): bool
    {
        $resourceObject = $field->raw()->getResource();
        $resource = $resourceObject->toArray();
        $searchFields = array_values(array_filter((array) ($resource['search_fields'] ?? []), 'is_string'));
        $titleField = method_exists($resourceObject, 'getTitleField')
            ? (string) $resourceObject->getTitleField()
            : (string) ($resource['title_field'] ?? '');

        return \in_array($field->name(), $searchFields, true) || ('' !== $titleField && $field->name() === $titleField);
    }
}
