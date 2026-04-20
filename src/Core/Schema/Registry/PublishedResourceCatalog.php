<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Registry;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaNormalizer;
use PTAdmin\Easy\Core\Schema\Definition\FieldDefinition;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;

/**
 * 已发布资源目录.
 *
 * 负责维护 `mods` 与 `mod_fields` 两张运行时读模型表：
 * 1. `mods` 保存资源主状态与当前发布版本指针
 * 2. `mod_fields` 保存当前发布版本的字段编译缓存
 *
 * `mod_versions` 仍然是真实 schema 来源：
 * - 资源级配置从 `mod_versions.schema_json` 读取
 * - 字段级配置从 `mod_fields` 缓存读取
 */
final class PublishedResourceCatalog
{
    /** @var array<string, mixed> */
    private $config;

    /** @var SchemaNormalizer */
    private $normalizer;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('easy.schema', []);
        $this->normalizer = new SchemaNormalizer();
    }

    /**
     * 确保资源主记录存在，并校验资源名称唯一性。
     *
     * 保存草稿阶段不会覆盖已发布资源的当前状态，避免草稿直接污染运行时。
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function ensureResource(string $resource, array $schema, string $module = ''): array
    {
        $modsTable = $this->modsTable();
        if (!Schema::hasTable($modsTable)) {
            return [
                'id' => 0,
                'name' => $resource,
                'module' => '' !== $module ? $module : (string) ($schema['module'] ?? 'App'),
                'current_version_id' => 0,
            ];
        }

        $module = '' !== $module ? $module : (string) ($schema['module'] ?? 'App');
        $existing = $this->findResource($resource);
        if (\is_array($existing)) {
            $existingModule = (string) ($existing['module'] ?? 'App');
            if ('' !== $module && $existingModule !== $module) {
                throw new \InvalidArgumentException('Schema name ['.$resource.'] already exists in module ['.$existingModule.'].');
            }

            if ((int) ($existing['current_version_id'] ?? 0) > 0) {
                return $existing;
            }

            $payload = $this->buildResourceSeedPayload($resource, $schema, $module);
            if (0 !== \count($payload)) {
                DB::table($modsTable)->where('id', $existing['id'])->update($payload);
            }

            return $this->findResource($resource) ?? $existing;
        }

        $id = DB::table($modsTable)->insertGetId($this->buildResourceSeedPayload($resource, $schema, $module));

        return $this->findResourceById((int) $id) ?? [
            'id' => (int) $id,
            'name' => $resource,
            'module' => $module,
            'current_version_id' => 0,
        ];
    }

    /**
     * 将当前发布版本同步到 `mods` 与 `mod_fields`。
     *
     * @param array<string, mixed> $version
     *
     * @return array<string, mixed>
     */
    public function syncPublishedResource(SchemaDefinition $definition, array $version): array
    {
        $modsTable = $this->modsTable();
        if (!Schema::hasTable($modsTable)) {
            return $version;
        }

        $resource = $definition->name();
        $module = $definition->module();
        $record = $this->ensureResource($resource, $definition->toArray(), $module);
        $modId = (int) ($record['id'] ?? 0);
        $versionId = (int) ($version['id'] ?? 0);

        DB::transaction(function () use ($modsTable, $record, $definition, $versionId, $modId): void {
            DB::table($modsTable)
                ->where('id', $record['id'])
                ->update($this->buildPublishedResourcePayload($definition, $versionId));

            $this->rebuildFieldCache($modId, $versionId, $definition);
        });

        return $this->findResourceById($modId) ?? $record;
    }

    /**
     * 查询资源主记录。
     *
     * @return array<string, mixed>|null
     */
    public function findPublishedResource(string $resource, string $module = ''): ?array
    {
        $modsTable = $this->modsTable();
        if (!Schema::hasTable($modsTable)) {
            return null;
        }

        $query = DB::table($modsTable)->where('name', $resource);
        if ('' !== $module && Schema::hasColumn($modsTable, 'module')) {
            $query->where('module', $module);
        }
        if (Schema::hasColumn($modsTable, 'current_version_id')) {
            $query->where('current_version_id', '>', 0);
        }
        if (Schema::hasColumn($modsTable, 'is_publish')) {
            $query->where('is_publish', 1);
        }

        $record = (array) ($query->first() ?? []);

        return 0 === \count($record) ? null : $record;
    }

    /**
     * 构建运行时使用的当前发布 schema。
     *
     * @return array<string, mixed>|null
     */
    public function buildPublishedSchema(string $resource, string $module = ''): ?array
    {
        $mod = $this->findPublishedResource($resource, $module);
        if (null === $mod) {
            return null;
        }

        $schema = $this->loadCurrentVersionSchema((int) ($mod['current_version_id'] ?? 0));
        if (0 === \count($schema)) {
            return null;
        }
        $schema = $this->normalizer->normalize($schema);

        $schema['title'] = $schema['title'] ?? (string) ($mod['title'] ?? $resource);
        $schema['name'] = $schema['name'] ?? $resource;
        $schema['module'] = $schema['module'] ?? (string) ($mod['module'] ?? (''
            !== $module ? $module : 'App'));
        $schema['mod_id'] = $schema['mod_id'] ?? (int) ($mod['id'] ?? 0);
        $schema['current_version_id'] = $schema['current_version_id'] ?? (int) ($mod['current_version_id'] ?? 0);
        $schema['intro'] = $schema['intro'] ?? ($mod['intro'] ?? null);
        $titleField = $schema['title_field'] ?? ($mod['title_field'] ?? null);
        if (\is_string($titleField) && '' !== trim($titleField)) {
            $schema['title_field'] = trim($titleField);
        }
        $coverField = $schema['cover_field'] ?? ($mod['cover_field'] ?? null);
        if (\is_string($coverField) && '' !== trim($coverField)) {
            $schema['cover_field'] = trim($coverField);
        }
        $schema['allow_import'] = $schema['allow_import'] ?? (int) ($mod['allow_import'] ?? 0);
        $schema['allow_export'] = $schema['allow_export'] ?? (int) ($mod['allow_export'] ?? 0);
        $schema['allow_copy'] = $schema['allow_copy'] ?? (int) ($mod['allow_copy'] ?? 0);
        $schema['allow_rename'] = $schema['allow_rename'] ?? (int) ($mod['allow_rename'] ?? 1);
        $schema['allow_recycle'] = $schema['allow_recycle'] ?? (int) ($mod['allow_recycle'] ?? 1);
        $schema['track_changes'] = $schema['track_changes'] ?? (int) ($mod['track_changes'] ?? 0);
        $schema['is_tree'] = $schema['is_tree'] ?? (int) ($mod['is_tree'] ?? 0);
        $schema['is_table'] = $schema['is_table'] ?? (int) ($mod['is_table'] ?? 0);
        $schema['fields'] = $this->loadPublishedFields((int) $mod['id'], (int) ($mod['current_version_id'] ?? 0));

        return $schema;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadPublishedFields(int $modId, int $versionId): array
    {
        $modFieldsTable = $this->modFieldsTable();
        if (!Schema::hasTable($modFieldsTable) || $modId <= 0 || $versionId <= 0) {
            return [];
        }

        return DB::table($modFieldsTable)
            ->where('mod_id', $modId)
            ->where('version_id', $versionId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($row): ?array {
                $record = (array) $row;
                $compiled = self::decodeJsonColumn($record['compiled_json'] ?? null);
                if (\is_array($compiled) && 0 !== \count($compiled)) {
                    return $this->normalizeFieldCacheRecord($compiled, $record);
                }

                $field = self::decodeJsonColumn($record['field_json'] ?? null);
                if (\is_array($field) && 0 !== \count($field)) {
                    return $this->normalizeFieldCacheRecord($field, $record);
                }

                return null;
            })
            ->filter(static function ($field): bool {
                return \is_array($field);
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function normalizeFieldCacheRecord(array $field, array $record): array
    {
        if (!array_key_exists('defaultValue', $field)) {
            if (array_key_exists('default', $field)) {
                $field['defaultValue'] = $field['default'];
            } elseif (isset($record['default_val'])) {
                $field['defaultValue'] = $record['default_val'];
            }
        }

        if (!array_key_exists('help', $field)) {
            if (isset($field['comment'])) {
                $field['help'] = $field['comment'];
            } elseif (isset($record['comment'])) {
                $field['help'] = (string) $record['comment'];
            }
        }

        if (!isset($field['maxlength']) && isset($record['length']) && is_numeric($record['length'])) {
            $field['maxlength'] = (int) $record['length'];
        }

        if (!isset($field['required']) && isset($record['is_required'])) {
            $field['required'] = 1 === (int) $record['is_required'];
        }

        return $field;
    }

    /**
     * 重建当前已发布字段缓存。
     */
    private function rebuildFieldCache(int $modId, int $versionId, SchemaDefinition $definition): void
    {
        $modFieldsTable = $this->modFieldsTable();
        if (!Schema::hasTable($modFieldsTable) || $modId <= 0 || $versionId <= 0) {
            return;
        }

        DB::table($modFieldsTable)->where('mod_id', $modId)->delete();

        $rows = [];
        $index = 0;
        foreach (array_values($definition->fields()) as $field) {
            $rows[] = $this->buildFieldCacheRow($modId, $versionId, $field, $index);
            ++$index;
        }

        if (0 !== \count($rows)) {
            DB::table($modFieldsTable)->insert($rows);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFieldCacheRow(int $modId, int $versionId, FieldDefinition $field, int $index): array
    {
        $mapping = $field->mapping();
        $metadata = $field->metadata();
        $relation = $field->relation();
        $payload = [
            'mod_id' => $modId,
            'version_id' => $versionId,
            'name' => $field->name(),
            'type' => $field->type(),
            'label' => $field->label(),
            'sort_order' => $index,
            'length' => (int) data_get($mapping, 'storage.length', data_get($metadata, 'length', 0)),
            'default_val' => $this->stringify($field->defaultValue()),
            'comment' => $field->comment(),
            'is_virtual' => $field->isVirtual() ? 1 : 0,
            'is_append' => $field->isAppend() ? 1 : 0,
            'is_relation' => 0 !== \count($relation) ? 1 : 0,
            'is_required' => $field->isRequired() ? 1 : 0,
            'is_unique' => $field->isUnique() ? 1 : 0,
            'is_search' => (int) data_get($mapping, 'query.searchable', false) ? 1 : 0,
            'is_table' => (int) data_get($field->display(), 'table.visible', true) ? 1 : 0,
            'field_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'compiled_json' => json_encode($field->toArray(), JSON_UNESCAPED_UNICODE),
            'mapping_json' => json_encode($mapping, JSON_UNESCAPED_UNICODE),
            'relation_json' => 0 !== \count($relation) ? json_encode($relation, JSON_UNESCAPED_UNICODE) : null,
            'rules_json' => json_encode($field->rules(), JSON_UNESCAPED_UNICODE),
            'extra' => json_encode([
                'capabilities' => $field->capabilities(),
                'display' => $field->display(),
                'rule_messages' => $field->ruleMessages(),
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => time(),
            'updated_at' => time(),
        ];

        return $payload;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function buildResourceSeedPayload(string $resource, array $schema, string $module): array
    {
        $payload = [
            'title' => (string) ($schema['title'] ?? $resource),
            'name' => $resource,
            'module' => $module,
            'intro' => isset($schema['intro']) ? (string) $schema['intro'] : null,
            'title_field' => isset($schema['title_field']) ? (string) $schema['title_field'] : null,
            'cover_field' => isset($schema['cover_field']) ? (string) $schema['cover_field'] : null,
            'is_tree' => $this->boolToInt($schema['is_tree'] ?? data_get($schema, 'table.tree', false)),
            'is_table' => $this->boolToInt($schema['is_table'] ?? isset($schema['table'])),
            'allow_import' => $this->boolToInt($schema['allow_import'] ?? false),
            'allow_export' => $this->boolToInt($schema['allow_export'] ?? false),
            'allow_copy' => $this->boolToInt($schema['allow_copy'] ?? false),
            'allow_rename' => $this->boolToInt($schema['allow_rename'] ?? true),
            'allow_recycle' => $this->boolToInt($schema['allow_recycle'] ?? true),
            'track_changes' => $this->boolToInt($schema['track_changes'] ?? false),
            'weight' => (int) ($schema['weight'] ?? 99),
            'status' => 0,
            'is_publish' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        return $this->filterKnownColumns($this->modsTable(), $payload);
    }

    /**
     * 构建资源发布后的主表同步数据.
     *
     * @return array<string, mixed>
     */
    private function buildPublishedResourcePayload(SchemaDefinition $definition, int $versionId): array
    {
        $schema = $definition->toArray();
        $schema['fields'] = [];

        $payload = [
            'title' => $definition->title(),
            'name' => $definition->name(),
            'module' => $definition->module(),
            'intro' => $definition->comment(),
            'title_field' => $schema['title_field'] ?? null,
            'cover_field' => $schema['cover_field'] ?? null,
            'sort' => \is_scalar($schema['sort'] ?? null) ? (string) $schema['sort'] : null,
            'icon' => isset($schema['icon']) ? (string) $schema['icon'] : null,
            'cover' => isset($schema['cover']) ? (string) $schema['cover'] : null,
            'route' => isset($schema['route']) ? (string) $schema['route'] : null,
            'current_version_id' => $versionId,
            'quick_entry' => $this->boolToInt($schema['quick_entry'] ?? false),
            'read_only' => $this->boolToInt($schema['read_only'] ?? false),
            'is_publish' => 1,
            'is_tree' => $this->boolToInt($schema['is_tree'] ?? data_get($schema, 'table.tree', false)),
            'is_table' => $this->boolToInt($schema['is_table'] ?? isset($schema['table'])),
            'allow_import' => $this->boolToInt($schema['allow_import'] ?? false),
            'allow_export' => $this->boolToInt($schema['allow_export'] ?? false),
            'allow_copy' => $this->boolToInt($schema['allow_copy'] ?? false),
            'allow_rename' => $this->boolToInt($schema['allow_rename'] ?? true),
            'allow_recycle' => $this->boolToInt($schema['allow_recycle'] ?? true),
            'track_changes' => $this->boolToInt($schema['track_changes'] ?? false),
            'status' => 1,
            'updated_at' => time(),
        ];

        return $this->filterKnownColumns($this->modsTable(), $payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findResource(string $resource): ?array
    {
        $modsTable = $this->modsTable();
        if (!Schema::hasTable($modsTable)) {
            return null;
        }

        $record = (array) (DB::table($modsTable)->where('name', $resource)->first() ?? []);

        return 0 === \count($record) ? null : $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findResourceById(int $id): ?array
    {
        $modsTable = $this->modsTable();
        if (!Schema::hasTable($modsTable) || $id <= 0) {
            return null;
        }

        $record = (array) (DB::table($modsTable)->where('id', $id)->first() ?? []);

        return 0 === \count($record) ? null : $record;
    }

    /**
     * @param mixed $value
     */
    private function boolToInt($value): int
    {
        return true === (bool) $value ? 1 : 0;
    }

    /**
     * @param mixed $value
     */
    private function stringify($value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function filterKnownColumns(string $table, array $payload): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        return array_filter($payload, static function ($value, string $column) use ($table): bool {
            return Schema::hasColumn($table, $column);
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function modsTable(): string
    {
        return (string) ($this->config['tables']['mods'] ?? 'mods');
    }

    private function modFieldsTable(): string
    {
        return (string) ($this->config['tables']['mod_fields'] ?? 'mod_fields');
    }

    private function versionTable(): string
    {
        return (string) ($this->config['version']['table'] ?? 'mod_versions');
    }

    /**
     * 从当前发布版本中读取完整 schema 快照。
     *
     * @return array<string, mixed>
     */
    private function loadCurrentVersionSchema(int $versionId): array
    {
        $table = $this->versionTable();
        if ($versionId <= 0 || !Schema::hasTable($table)) {
            return [];
        }

        $schemaColumn = (string) ($this->config['version']['schema_column'] ?? 'schema_json');
        if (!Schema::hasColumn($table, $schemaColumn)) {
            return [];
        }

        $record = (array) (DB::table($table)->where('id', $versionId)->first() ?? []);
        if (0 === \count($record) || !\is_string($record[$schemaColumn] ?? null)) {
            return [];
        }

        $decoded = json_decode((string) $record[$schemaColumn], true);

        return JSON_ERROR_NONE === json_last_error() && \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param mixed $value
     *
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private static function decodeJsonColumn($value): ?array
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        $decoded = json_decode($value, true);

        return JSON_ERROR_NONE === json_last_error() && \is_array($decoded) ? $decoded : null;
    }
}
