<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Loader\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Core\Schema\Loader\Contracts\SchemaRepositoryInterface;
use PTAdmin\Easy\Core\Schema\Registry\PublishedResourceCatalog;

/**
 * 数据库 schema 仓库.
 *
 * 优先通过 `mods.current_version_id + mod_versions + mod_fields`
 * 读取当前发布 schema；未命中时再回退到历史版本表查询。
 */
class DatabaseSchemaRepository implements SchemaRepositoryInterface
{
    /** @var array<string, mixed> */
    private $config;

    /** @var PublishedResourceCatalog */
    private $catalog;

    public function __construct(?array $config = null, ?PublishedResourceCatalog $catalog = null)
    {
        $this->config = $config ?? (array) config('easy.schema', []);
        $this->catalog = $catalog ?? new PublishedResourceCatalog($this->config);
    }

    /**
     * 查询资源的当前发布 schema.
     */
    public function find(string $resource, string $module = ''): ?array
    {
        $metadata = $this->findPublishedCatalog($resource, $module);
        if (\is_array($metadata)) {
            return $metadata;
        }

        $metadata = $this->findVersioned($resource, $module);
        if (\is_array($metadata)) {
            return $metadata;
        }

        return null;
    }

    /**
     * 从 `mods + mod_fields` 中读取当前发布 schema。
     */
    private function findPublishedCatalog(string $resource, string $module = ''): ?array
    {
        $metadata = $this->catalog->buildPublishedSchema($resource, $module);
        if (!\is_array($metadata) || 0 === \count($metadata)) {
            return null;
        }

        return $this->normalizeMetadata($metadata, $resource, $module);
    }

    /**
     * 从版本表中读取已发布 schema.
     */
    private function findVersioned(string $resource, string $module = ''): ?array
    {
        $version = (array) ($this->config['version'] ?? []);
        $table = (string) ($version['table'] ?? 'mod_versions');
        if (!$this->hasTable($table)) {
            return null;
        }

        $resourceColumn = $this->resolveVersionResourceColumn($table, $version);
        $moduleColumn = (string) ($version['module_column'] ?? 'module');
        $schemaColumn = (string) ($version['schema_column'] ?? 'schema_json');
        $publishedColumn = (string) ($version['published_column'] ?? 'is_current');
        if (
            !$this->hasColumn($table, $resourceColumn)
            || !$this->hasColumn($table, $schemaColumn)
        ) {
            return null;
        }

        $query = DB::table($table)->where($resourceColumn, $resource);
        if ('' !== $module && $this->hasColumn($table, $moduleColumn)) {
            $query->where($moduleColumn, $module);
        }
        if ($this->hasColumn($table, $publishedColumn)) {
            $query->where($publishedColumn, 1);
        }
        if ($this->hasColumn($table, 'status')) {
            $query->where('status', 'published');
        }

        $record = (array) ($query->orderByDesc('id')->first() ?? []);
        if (0 === \count($record)) {
            return null;
        }

        $payload = $record[$schemaColumn] ?? null;
        if (\is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (!\is_array($payload) || 0 === \count($payload)) {
            return null;
        }

        return $this->normalizeMetadata($payload, $resource, $module);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function normalizeMetadata(array $metadata, string $resource, string $module = ''): array
    {
        $metadata = $this->decodeKnownJson($metadata, [
            'charts', 'order', 'search_fields', 'export_fields', 'import_fields', 'tabs', 'setup', 'extra', 'rules', 'appends',
        ]);

        if (!isset($metadata['module']) || '' === (string) $metadata['module']) {
            $metadata['module'] = '' !== $module ? $module : 'App';
        }

        if (!isset($metadata['name'])) {
            $metadata['name'] = isset($metadata['table']) ? (string) $metadata['table'] : $resource;
        }

        $fields = [];
        foreach ((array) ($metadata['fields'] ?? []) as $field) {
            if (!\is_array($field)) {
                continue;
            }
            $fields[] = $this->mapFieldRecord($field);
        }
        $metadata['fields'] = $fields;

        return $metadata;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function mapFieldRecord(array $field): array
    {
        $field = $this->decodeKnownJson($field, ['setup', 'extends', 'rules', 'options', 'extra']);

        if (!isset($field['label']) && isset($field['title'])) {
            $field['label'] = $field['title'];
        }
        if (!array_key_exists('defaultValue', $field)) {
            if (array_key_exists('default', $field)) {
                $field['defaultValue'] = $field['default'];
            } elseif (isset($field['default_val'])) {
                $field['defaultValue'] = $field['default_val'];
            }
        }
        if (!array_key_exists('help', $field)) {
            if (isset($field['comment'])) {
                $field['help'] = (string) $field['comment'];
            } elseif (isset($field['intro'])) {
                $field['help'] = (string) $field['intro'];
            }
        }
        if (!isset($field['maxlength']) && isset($field['length']) && is_numeric($field['length'])) {
            $field['maxlength'] = (int) $field['length'];
        }
        if (!isset($field['required']) && isset($field['is_required'])) {
            $field['required'] = 1 === (int) $field['is_required'];
        }
        if (!isset($field['extends']) && isset($field['extra'])) {
            $field['extends'] = $field['extra'];
        }

        return $field;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[]             $keys
     *
     * @return array<string, mixed>
     */
    private function decodeKnownJson(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (!isset($payload[$key]) || !\is_string($payload[$key])) {
                continue;
            }
            $decoded = json_decode($payload[$key], true);
            if (JSON_ERROR_NONE === json_last_error()) {
                $payload[$key] = $decoded;
            }
        }

        return $payload;
    }

    /**
     * 安全检查目标表是否存在.
     */
    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * 安全检查目标列是否存在.
     */
    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    /**
     * @param array<string, mixed> $version
     */
    private function resolveVersionResourceColumn(string $table, array $version): string
    {
        $configured = (string) ($version['resource_column'] ?? 'name');
        if ($this->hasColumn($table, $configured)) {
            return $configured;
        }
        if ($this->hasColumn($table, 'name')) {
            return 'name';
        }

        return 'name';
    }
}
