<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Migration;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as LaravelSchema;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Engine\Schema\Schema;

/**
 * 将迁移计划同步到数据库结构.
 *
 * 当前策略偏保守：
 * 1. 新表直接创建
 * 2. 已有表仅自动处理新增和字段变更
 * 3. 删除字段仅在 force 模式下执行
 */
class SchemaSynchronizer
{
    /**
     * 将迁移计划应用到目标表结构.
     */
    public function sync(SchemaDefinition $definition, MigrationPlan $plan, bool $force = false): void
    {
        $plan->ensureSupportedForSync();

        $schema = new Schema($definition->raw());
        if (!$schema->tableExists($definition->raw()->getRawTable())) {
            $schema->create();

            return;
        }

        $operations = $plan->operations();
        $rename = (array) ($operations['rename_fields'] ?? []);
        $add = (array) ($operations['add_fields'] ?? []);
        $change = (array) ($operations['change_fields'] ?? []);
        $drop = (array) ($operations['drop_fields'] ?? []);
        $addUnique = (array) ($operations['add_unique'] ?? []);
        $dropUnique = (array) ($operations['drop_unique'] ?? []);

        if (
            0 === \count($rename)
            && 0 === \count($add)
            && 0 === \count($change)
            && 0 === \count($drop)
            && 0 === \count($addUnique)
            && 0 === \count($dropUnique)
        ) {
            return;
        }

        if (0 !== \count($rename)) {
            LaravelSchema::table($definition->raw()->getRawTable(), function (Blueprint $table) use ($rename): void {
                foreach ($rename as $from => $to) {
                    $table->renameColumn((string) $from, (string) $to);
                }
            });
        }

        LaravelSchema::table($definition->raw()->getRawTable(), function (Blueprint $table) use ($definition, $add, $change): void {
            foreach (array_merge($add, $change) as $fieldName) {
                $field = $definition->raw()->getField((string) $fieldName);
                if (null === $field) {
                    continue;
                }
                $component = $field->getComponent();
                if ($component->isVirtual() || $field->isVirtual()) {
                    continue;
                }

                $column = $table->{$component->getColumnType()}(...$component->getColumnArguments());
                if (null === $field->required()) {
                    $column->nullable();
                }
                $column->comment($field->getComment());
                if (null !== $field->getDefault()) {
                    $column->default($field->getDefault());
                }
                if (\in_array($fieldName, $change, true)) {
                    $column->change();
                }
            }
        });

        if (0 !== \count($dropUnique) || 0 !== \count($addUnique)) {
            $tableName = $definition->raw()->getRawTable();
            LaravelSchema::table($tableName, function (Blueprint $table) use ($tableName, $dropUnique, $addUnique): void {
                foreach ($dropUnique as $fieldName) {
                    $table->dropUnique($this->uniqueIndexName($tableName, (string) $fieldName));
                }
                foreach ($addUnique as $fieldName) {
                    $table->unique((string) $fieldName, $this->uniqueIndexName($tableName, (string) $fieldName));
                }
            });
        }

        if ($force && 0 !== \count($drop)) {
            LaravelSchema::table($definition->raw()->getRawTable(), function (Blueprint $table) use ($drop): void {
                foreach ($drop as $fieldName) {
                    $table->dropColumn((string) $fieldName);
                }
            });
        }
    }

    /**
     * 生成唯一索引名称.
     */
    private function uniqueIndexName(string $tableName, string $fieldName): string
    {
        return "{$tableName}_{$fieldName}_unique";
    }
}
