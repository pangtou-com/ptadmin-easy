<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2026 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy\Core\Migration;

use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;

/**
 * 负责比较两个 schema 定义并输出结构差异.
 */
class SchemaDiff
{
    /**
     * 对比当前已发布 schema 与待发布 schema.
     */
    public function diff(?SchemaDefinition $current, SchemaDefinition $next): MigrationPlan
    {
        if (null === $current) {
            $operations = [
                'create_table' => true,
                'rename_fields' => [],
                'add_fields' => array_keys($next->fields()),
                'change_fields' => [],
                'drop_fields' => [],
                'add_unique' => $this->extractUniqueFields($next->fields()),
                'drop_unique' => [],
            ];

            return new MigrationPlan($operations, $this->buildExplanation($current, $next, $operations));
        }

        $currentFields = $current->fields();
        $nextFields = $next->fields();

        $rename = [];
        $add = [];
        $change = [];
        $drop = [];
        $addUnique = [];
        $dropUnique = [];

        foreach ($nextFields as $name => $field) {
            $renameFrom = $field->renameFrom();
            if (null === $renameFrom || !isset($currentFields[$renameFrom]) || isset($currentFields[$name])) {
                continue;
            }

            $rename[$renameFrom] = $name;
        }

        foreach ($nextFields as $name => $field) {
            $sourceName = $this->resolveSourceFieldName($name, $rename);
            if (!isset($currentFields[$sourceName])) {
                $add[] = $name;
                if ($field->isUnique()) {
                    $addUnique[] = $name;
                }

                continue;
            }

            if ($field->toArray() !== $currentFields[$sourceName]->toArray()) {
                $change[] = $name;
            }

            $currentUnique = $currentFields[$sourceName]->isUnique();
            $nextUnique = $field->isUnique();
            if ($nextUnique && !$currentUnique) {
                $addUnique[] = $name;
            }
            if (!$nextUnique && $currentUnique) {
                $dropUnique[] = $name;
            }
        }

        foreach ($currentFields as $name => $field) {
            if (isset($rename[$name])) {
                if ($field->isUnique() && !$nextFields[$rename[$name]]->isUnique()) {
                    $dropUnique[] = $name;
                }

                continue;
            }
            if (!isset($nextFields[$name])) {
                $drop[] = $name;
                if ($field->isUnique()) {
                    $dropUnique[] = $name;
                }
            }
        }

        $operations = [
            'create_table' => false,
            'rename_fields' => $rename,
            'add_fields' => $add,
            'change_fields' => $change,
            'drop_fields' => $drop,
            'add_unique' => array_values(array_unique($addUnique)),
            'drop_unique' => array_values(array_unique($dropUnique)),
        ];

        return new MigrationPlan($operations, $this->buildExplanation($current, $next, $operations));
    }

    /**
     * @param array<string, \PTAdmin\Easy\Core\Schema\Definition\FieldDefinition> $fields
     *
     * @return string[]
     */
    private function extractUniqueFields(array $fields): array
    {
        $results = [];
        foreach ($fields as $name => $field) {
            if ($field->isUnique()) {
                $results[] = $name;
            }
        }

        return $results;
    }

    /**
     * @param array<string, string> $renameMap
     */
    private function resolveSourceFieldName(string $fieldName, array $renameMap): string
    {
        foreach ($renameMap as $source => $target) {
            if ($target === $fieldName) {
                return $source;
            }
        }

        return $fieldName;
    }

    /**
     * 构建可读的迁移说明结构.
     *
     * @param array<string, mixed> $operations
     *
     * @return array<string, mixed>
     */
    private function buildExplanation(?SchemaDefinition $current, SchemaDefinition $next, array $operations): array
    {
        $currentFields = null === $current ? [] : $current->fields();
        $nextFields = $next->fields();

        $fields = [
            'create_table' => [],
            'rename' => [],
            'add' => [],
            'change' => [],
            'drop' => [],
        ];
        $indexes = [
            'add_unique' => [],
            'drop_unique' => [],
        ];
        $risks = [];

        if (true === (bool) ($operations['create_table'] ?? false)) {
            foreach ($nextFields as $field) {
                $fields['create_table'][] = $this->fieldSnapshot($field);
            }
        }

        foreach ((array) ($operations['rename_fields'] ?? []) as $from => $to) {
            if (!isset($currentFields[(string) $from], $nextFields[(string) $to])) {
                continue;
            }

            $changeSet = $this->compareFields($currentFields[(string) $from], $nextFields[(string) $to]);
            $item = [
                'from' => (string) $from,
                'to' => (string) $to,
                'preserved_data' => true,
                'field' => $this->fieldSnapshot($nextFields[(string) $to]),
                'changes' => $changeSet['changes'],
                'destructive' => $changeSet['destructive'],
                'risks' => $changeSet['risks'],
            ];
            $fields['rename'][] = $item;
            $risks = array_merge($risks, $item['risks']);
        }

        foreach ((array) ($operations['add_fields'] ?? []) as $name) {
            if (!isset($nextFields[(string) $name])) {
                continue;
            }

            $fields['add'][] = $this->fieldSnapshot($nextFields[(string) $name]);
        }

        foreach ((array) ($operations['change_fields'] ?? []) as $name) {
            $name = (string) $name;
            $sourceName = $this->resolveSourceFieldName($name, (array) ($operations['rename_fields'] ?? []));
            if (!isset($currentFields[$sourceName], $nextFields[$name])) {
                continue;
            }

            $changeSet = $this->compareFields($currentFields[$sourceName], $nextFields[$name]);
            $item = [
                'name' => $name,
                'field' => $this->fieldSnapshot($nextFields[$name]),
                'changes' => $changeSet['changes'],
                'destructive' => $changeSet['destructive'],
                'risks' => $changeSet['risks'],
            ];
            $fields['change'][] = $item;
            $risks = array_merge($risks, $item['risks']);
        }

        foreach ((array) ($operations['drop_fields'] ?? []) as $name) {
            $name = (string) $name;
            if (!isset($currentFields[$name])) {
                continue;
            }

            $risk = [
                'severity' => 'high',
                'code' => 'drop_field',
                'field' => $name,
                'message' => "Field [{$name}] will be dropped when force sync is enabled.",
            ];
            $fields['drop'][] = [
                'name' => $name,
                'field' => $this->fieldSnapshot($currentFields[$name]),
                'destructive' => true,
                'risks' => [$risk],
            ];
            $risks[] = $risk;
        }

        foreach ((array) ($operations['add_unique'] ?? []) as $name) {
            $name = (string) $name;
            if (!isset($nextFields[$name])) {
                continue;
            }

            $indexes['add_unique'][] = [
                'field' => $name,
                'label' => $nextFields[$name]->label(),
                'index' => $next->name().'_'.$name.'_unique',
            ];
            $risks[] = [
                'severity' => 'medium',
                'code' => 'add_unique',
                'field' => $name,
                'message' => "Field [{$name}] will add a unique constraint. Existing duplicate data may block sync.",
            ];
        }

        foreach ((array) ($operations['drop_unique'] ?? []) as $name) {
            $name = (string) $name;
            $field = $currentFields[$name] ?? ($nextFields[$name] ?? null);
            $indexes['drop_unique'][] = [
                'field' => $name,
                'label' => null !== $field ? $field->label() : $name,
                'index' => $next->name().'_'.$name.'_unique',
            ];
        }

        $risks = array_values(array_map('unserialize', array_unique(array_map('serialize', $risks))));

        return [
            'summary' => [
                'create_table' => true === (bool) ($operations['create_table'] ?? false),
                'rename_count' => \count($fields['rename']),
                'add_count' => \count($fields['add']),
                'change_count' => \count($fields['change']),
                'drop_count' => \count($fields['drop']),
                'add_unique_count' => \count($indexes['add_unique']),
                'drop_unique_count' => \count($indexes['drop_unique']),
                'risk_count' => \count($risks),
                'unsupported_count' => \count(array_filter($risks, static function (array $risk): bool {
                    return true === (bool) ($risk['unsupported'] ?? false)
                        || 0 === strpos((string) ($risk['code'] ?? ''), 'unsupported_');
                })),
                'destructive' => $this->containsDestructiveChanges($fields, $risks),
                'empty' => $this->isOperationSetEmpty($operations),
            ],
            'fields' => $fields,
            'indexes' => $indexes,
            'risks' => $risks,
        ];
    }

    /**
     * 导出字段快照.
     *
     * @return array<string, mixed>
     */
    private function fieldSnapshot($field): array
    {
        return [
            'name' => $field->name(),
            'label' => $field->label(),
            'type' => $field->type(),
            'required' => $field->isRequired(),
            'unique' => $field->isUnique(),
            'default' => $field->defaultValue(),
            'mapping' => $field->mapping(),
        ];
    }

    /**
     * 对比字段变化.
     *
     * @return array{changes: array<int, array<string, mixed>>, destructive: bool, risks: array<int, array<string, mixed>>}
     */
    private function compareFields($before, $after): array
    {
        $changes = [];
        $risks = [];

        foreach ($this->comparisonValues($before, $after) as $path => $pair) {
            if ($pair['before'] === $pair['after']) {
                continue;
            }

            $changes[] = [
                'path' => $path,
                'before' => $pair['before'],
                'after' => $pair['after'],
            ];
        }

        $fieldName = $after->name();
        $beforeStorage = (array) data_get($before->mapping(), 'storage', []);
        $afterStorage = (array) data_get($after->mapping(), 'storage', []);
        $typeChanged = $this->valueChanged($before->mapping(), $after->mapping(), 'component.type');
        $storageChanged = $this->valueChanged($before->mapping(), $after->mapping(), 'storage.column_definition');
        $castChanged = $this->valueChanged($before->mapping(), $after->mapping(), 'storage.runtime_cast');
        $requiredTightened = false === $before->isRequired() && true === $after->isRequired();
        $uniqueAdded = false === $before->isUnique() && true === $after->isUnique();
        $unsupportedStorageChange = $this->isUnsupportedStorageChange($beforeStorage, $afterStorage);

        if ($unsupportedStorageChange) {
            $risks[] = [
                'severity' => 'high',
                'code' => 'unsupported_storage_change',
                'field' => $fieldName,
                'unsupported' => true,
                'before' => (string) ($beforeStorage['column_definition'] ?? ''),
                'after' => (string) ($afterStorage['column_definition'] ?? ''),
                'message' => "Field [{$fieldName}] storage type changes from [".(string) ($beforeStorage['column_definition'] ?? 'unknown')."] to [".(string) ($afterStorage['column_definition'] ?? 'unknown')."]. Direct automatic conversion is not supported in current stage. Please create a new field and migrate data manually.",
            ];
        } elseif ($typeChanged || $storageChanged || $castChanged) {
            $risks[] = [
                'severity' => 'high',
                'code' => 'storage_changed',
                'field' => $fieldName,
                'message' => "Field [{$fieldName}] storage definition changed. Existing data compatibility should be reviewed before sync.",
            ];
        }

        $beforeLength = $this->normalizeLength($beforeStorage['length'] ?? null);
        $afterLength = $this->normalizeLength($afterStorage['length'] ?? null);
        if (
            null !== $beforeLength
            && null !== $afterLength
            && $afterLength < $beforeLength
            && 'string' === $this->storageFamily((string) ($beforeStorage['column_type'] ?? ''))
            && 'string' === $this->storageFamily((string) ($afterStorage['column_type'] ?? ''))
        ) {
            $risks[] = [
                'severity' => 'high',
                'code' => 'length_reduced',
                'field' => $fieldName,
                'message' => "Field [{$fieldName}] length shrinks from [{$beforeLength}] to [{$afterLength}]. Existing data may be truncated during sync.",
            ];
        }

        if (
            $this->valueChanged($before->mapping(), $after->mapping(), 'storage.nullable')
            && true === (bool) ($beforeStorage['nullable'] ?? true)
            && false === (bool) ($afterStorage['nullable'] ?? false)
        ) {
            $risks[] = [
                'severity' => 'medium',
                'code' => 'nullable_changed',
                'field' => $fieldName,
                'message' => "Field [{$fieldName}] changes from nullable to non-nullable. Existing empty values should be cleaned before sync.",
            ];
        }

        if ($requiredTightened) {
            $risks[] = [
                'severity' => 'medium',
                'code' => 'required_changed',
                'field' => $fieldName,
                'message' => "Field [{$fieldName}] changed from optional to required. Historical records may fail validation after publish.",
            ];
        }

        if ($uniqueAdded) {
            $risks[] = [
                'severity' => 'medium',
                'code' => 'unique_changed',
                'field' => $fieldName,
                'message' => "Field [{$fieldName}] adds uniqueness. Existing duplicate data may block sync.",
            ];
        }

        return [
            'changes' => $changes,
            'destructive' => 0 !== \count(array_filter($risks, static function (array $risk): bool {
                return 'high' === ($risk['severity'] ?? null);
            })),
            'risks' => $risks,
        ];
    }

    /**
     * 返回需要对比的字段值集合.
     *
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function comparisonValues($before, $after): array
    {
        return [
            'label' => ['before' => $before->label(), 'after' => $after->label()],
            'type' => ['before' => $before->type(), 'after' => $after->type()],
            'required' => ['before' => $before->isRequired(), 'after' => $after->isRequired()],
            'unique' => ['before' => $before->isUnique(), 'after' => $after->isUnique()],
            'default' => ['before' => $before->defaultValue(), 'after' => $after->defaultValue()],
            'comment' => ['before' => $before->comment(), 'after' => $after->comment()],
            'placeholder' => ['before' => $before->placeholder(), 'after' => $after->placeholder()],
            'options' => ['before' => $before->options(), 'after' => $after->options()],
            'rules' => ['before' => $before->rules(), 'after' => $after->rules()],
            'display' => ['before' => $before->display(), 'after' => $after->display()],
            'mapping.component.type' => ['before' => data_get($before->mapping(), 'component.type'), 'after' => data_get($after->mapping(), 'component.type')],
            'mapping.component.multiple' => ['before' => data_get($before->mapping(), 'component.multiple'), 'after' => data_get($after->mapping(), 'component.multiple')],
            'mapping.storage.column_definition' => ['before' => data_get($before->mapping(), 'storage.column_definition'), 'after' => data_get($after->mapping(), 'storage.column_definition')],
            'mapping.storage.runtime_cast' => ['before' => data_get($before->mapping(), 'storage.runtime_cast'), 'after' => data_get($after->mapping(), 'storage.runtime_cast')],
            'mapping.query.operators' => ['before' => data_get($before->mapping(), 'query.operators'), 'after' => data_get($after->mapping(), 'query.operators')],
        ];
    }

    /**
     * 判断映射路径是否发生变化.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function valueChanged(array $before, array $after, string $path): bool
    {
        return data_get($before, $path) !== data_get($after, $path);
    }

    /**
     * 判断当前操作集是否为空.
     *
     * @param array<string, mixed> $operations
     */
    private function isOperationSetEmpty(array $operations): bool
    {
        foreach ($operations as $items) {
            if (true === $items) {
                return false;
            }
            if (\is_array($items) && 0 !== \count($items)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 判断说明中是否包含破坏性变化.
     *
     * @param array<string, array<int, array<string, mixed>>> $fields
     * @param array<int, array<string, mixed>>                $risks
     */
    private function containsDestructiveChanges(array $fields, array $risks): bool
    {
        if (0 !== \count($fields['drop'])) {
            return true;
        }

        foreach ($fields['change'] as $item) {
            if (true === (bool) ($item['destructive'] ?? false)) {
                return true;
            }
        }

        foreach ($fields['rename'] as $item) {
            if (true === (bool) ($item['destructive'] ?? false)) {
                return true;
            }
        }

        foreach ($risks as $risk) {
            if ('high' === ($risk['severity'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断是否属于当前阶段不支持的存储转换。
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function isUnsupportedStorageChange(array $before, array $after): bool
    {
        $beforeType = (string) ($before['column_type'] ?? '');
        $afterType = (string) ($after['column_type'] ?? '');
        if ('' === $beforeType || '' === $afterType || $beforeType === $afterType) {
            return false;
        }

        return $this->storageFamily($beforeType) !== $this->storageFamily($afterType);
    }

    /**
     * 将底层列类型归类为更粗粒度的存储族。
     */
    private function storageFamily(string $columnType): string
    {
        if (\in_array($columnType, ['string', 'text'], true)) {
            return 'string';
        }

        if (\in_array($columnType, ['tinyinteger', 'integer', 'unsignedInteger'], true)) {
            return 'integer';
        }

        if ('json' === $columnType) {
            return 'json';
        }

        return $columnType;
    }

    private function normalizeLength($value): ?int
    {
        return \is_numeric($value) ? (int) $value : null;
    }
}
