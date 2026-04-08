<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Versioning\Stores;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaNormalizer;
use PTAdmin\Easy\Core\Schema\Versioning\Contracts\SchemaVersionStoreInterface;

/**
 * 数据库版本仓库.
 *
 * 负责将草稿与发布后的 schema 快照写入 `mod_versions`，
 * 同时维护当前激活版本标记。
 */
class DatabaseSchemaVersionStore implements SchemaVersionStoreInterface
{
    private const STATUS_DRAFT = 'draft';
    private const STATUS_PUBLISHED = 'published';
    private const STATUS_ARCHIVED = 'archived';
    private const STATUS_SUPERSEDED = 'superseded';

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
     * 将 schema 快照保存为草稿版本.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function saveDraft(string $resource, array $schema, string $module = '', array $options = []): array
    {
        return $this->store($resource, $schema, $module, self::STATUS_DRAFT, false, $options);
    }

    /**
     * 查询当前已发布版本.
     *
     * @return array<string, mixed>|null
     */
    public function current(string $resource, string $module = ''): ?array
    {
        return $this->firstMatching($resource, $module, self::STATUS_PUBLISHED, true);
    }

    /**
     * 查询最新草稿版本.
     *
     * @return array<string, mixed>|null
     */
    public function latestDraft(string $resource, string $module = ''): ?array
    {
        return $this->firstMatching($resource, $module, self::STATUS_DRAFT, false);
    }

    /**
     * 返回资源版本历史.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $resource, string $module = '', int $limit = 20, array $filters = []): array
    {
        $table = $this->versionTable();
        if (!Schema::hasTable($table)) {
            return [];
        }

        $query = $this->baseResourceQuery($table, $resource, $module);
        $this->applyHistoryFilters($query, $table, $filters);
        $query->orderByDesc('id');
        $page = max(1, (int) ($filters['page'] ?? 1));
        if ($limit > 0 && $page > 1) {
            $query->offset(($page - 1) * $limit);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($row): array {
            return $this->normalizeRecord((array) $row);
        })->all();
    }

    /**
     * 按版本 ID 查询记录.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $versionId): ?array
    {
        $table = $this->versionTable();
        if (!Schema::hasTable($table)) {
            return null;
        }

        $record = (array) (DB::table($table)->where('id', $versionId)->first() ?? []);

        return 0 === \count($record) ? null : $this->normalizeRecord($record);
    }

    /**
     * 更新指定草稿版本.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    public function updateDraft(int $versionId, array $schema, array $options = []): ?array
    {
        $table = $this->versionTable();
        if (!Schema::hasTable($table)) {
            return null;
        }

        $record = $this->find($versionId);
        if (null === $record || self::STATUS_DRAFT !== (string) ($record['status'] ?? '')) {
            return null;
        }

        $version = (array) ($this->config['version'] ?? []);
        $resourceColumn = $this->resolveResourceColumn($table, $version);
        $moduleColumn = (string) ($version['module_column'] ?? 'module');
        $schemaColumn = (string) ($version['schema_column'] ?? 'schema_json');

        $payload = [];
        if (Schema::hasColumn($table, $schemaColumn)) {
            $payload[$schemaColumn] = json_encode($schema, JSON_UNESCAPED_UNICODE);
        }
        if (Schema::hasColumn($table, 'remark') && array_key_exists('remark', $options)) {
            $payload['remark'] = null === $options['remark'] ? null : (string) $options['remark'];
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = time();
        }
        if (Schema::hasColumn($table, $resourceColumn)) {
            $payload[$resourceColumn] = (string) ($record['resource'] ?? '');
        }
        if (Schema::hasColumn($table, $moduleColumn)) {
            $payload[$moduleColumn] = (string) ($record['module'] ?? '');
        }

        DB::table($table)->where('id', $versionId)->update($payload);

        return $this->find($versionId);
    }

    /**
     * 删除指定草稿版本.
     */
    public function deleteDraft(int $versionId): bool
    {
        $table = $this->versionTable();
        if (!Schema::hasTable($table)) {
            return false;
        }

        $record = $this->find($versionId);
        if (null === $record || self::STATUS_DRAFT !== (string) ($record['status'] ?? '')) {
            return false;
        }

        return DB::table($table)->where('id', $versionId)->delete() > 0;
    }

    /**
     * 删除指定版本记录.
     */
    public function deleteVersion(int $versionId): bool
    {
        $table = $this->versionTable();
        if (!Schema::hasTable($table)) {
            return false;
        }

        return DB::table($table)->where('id', $versionId)->delete() > 0;
    }

    /**
     * 将指定版本切换为当前已发布版本.
     *
     * @return array<string, mixed>|null
     */
    public function markAsCurrent(int $versionId): ?array
    {
        $table = $this->versionTable();
        if (!Schema::hasTable($table)) {
            return null;
        }

        $current = $this->find($versionId);
        if (null === $current) {
            return null;
        }

        $resourceColumn = $this->resolveResourceColumn($table, (array) ($this->config['version'] ?? []));
        $moduleColumn = (string) ($this->config['version']['module_column'] ?? 'module');
        $publishedColumn = (string) ($this->config['version']['published_column'] ?? 'is_current');
        DB::transaction(function () use ($table, $current, $versionId, $publishedColumn): void {
            $resourceQuery = $this->baseResourceQuery($table, (string) $current['resource'], (string) $current['module']);

            $archivePayload = [$publishedColumn => 0];
            if (Schema::hasColumn($table, 'status')) {
                $archivePayload['status'] = self::STATUS_ARCHIVED;
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $archivePayload['updated_at'] = time();
            }

            $resourceQuery
                ->where($publishedColumn, 1)
                ->where('id', '!=', $versionId)
                ->update($archivePayload);

            if (Schema::hasColumn($table, 'status')) {
                $draftPayload = ['status' => self::STATUS_SUPERSEDED];
                if (Schema::hasColumn($table, 'updated_at')) {
                    $draftPayload['updated_at'] = time();
                }

                $this->baseResourceQuery($table, (string) $current['resource'], (string) $current['module'])
                    ->where('id', '!=', $versionId)
                    ->where('status', self::STATUS_DRAFT)
                    ->update($draftPayload);
            }

            $payload = [$publishedColumn => 1];
            if (Schema::hasColumn($table, 'status')) {
                $payload['status'] = self::STATUS_PUBLISHED;
            }
            if (Schema::hasColumn($table, 'published_at')) {
                $payload['published_at'] = time();
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $payload['updated_at'] = time();
            }

            DB::table($table)->where('id', $versionId)->update($payload);
        });

        return $this->find($versionId);
    }

    /**
     * 将 schema 快照保存为最新版本.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function publish(string $resource, array $schema, string $module = '', array $options = []): array
    {
        return $this->store($resource, $schema, $module, self::STATUS_PUBLISHED, true, $options);
    }

    /**
     * 通用版本写入逻辑.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function store(string $resource, array $schema, string $module, string $status, bool $current, array $options = []): array
    {
        $schema = $this->normalizeSchemaSnapshot($schema, $resource);
        $version = (array) ($this->config['version'] ?? []);
        $table = (string) ($version['table'] ?? 'mod_versions');
        if (!Schema::hasTable($table)) {
            if (self::STATUS_DRAFT === $status) {
                return (new NullSchemaVersionStore())->saveDraft($resource, $schema, $module, $options);
            }

            return (new NullSchemaVersionStore())->publish($resource, $schema, $module, $options);
        }

        $resourceColumn = $this->resolveResourceColumn($table, $version);
        $moduleColumn = (string) ($version['module_column'] ?? 'module');
        $schemaColumn = (string) ($version['schema_column'] ?? 'schema_json');
        $publishedColumn = (string) ($version['published_column'] ?? 'is_current');

        $id = DB::transaction(function () use ($table, $resource, $schema, $module, $status, $current, $options, $resourceColumn, $moduleColumn, $schemaColumn, $publishedColumn) {
            $resolvedModule = '' !== $module ? $module : (string) ($schema['module'] ?? 'App');
            $payload = [];
            if (Schema::hasColumn($table, 'mod_id') && isset($options['mod_id'])) {
                $payload['mod_id'] = (int) $options['mod_id'];
            }
            if (Schema::hasColumn($table, $resourceColumn)) {
                $payload[$resourceColumn] = $resource;
            }
            if (Schema::hasColumn($table, $moduleColumn)) {
                $payload[$moduleColumn] = $resolvedModule;
            }
            if (Schema::hasColumn($table, $schemaColumn)) {
                $payload[$schemaColumn] = json_encode($schema, JSON_UNESCAPED_UNICODE);
            }
            if (Schema::hasColumn($table, 'version_no')) {
                $payload['version_no'] = $this->nextVersionNumber($table, $resource, $resolvedModule, $resourceColumn, $moduleColumn);
            }
            if (Schema::hasColumn($table, 'remark') && isset($options['remark'])) {
                $payload['remark'] = (string) $options['remark'];
            }
            if (Schema::hasColumn($table, 'status')) {
                $payload['status'] = $status;
            }
            if (self::STATUS_DRAFT === $status) {
                $this->expireDrafts($table, $resource, $resolvedModule);
            }
            if (Schema::hasColumn($table, $publishedColumn)) {
                if ($current) {
                    $this->archivePublishedVersions($table, $resource, $resolvedModule, $publishedColumn);
                    $this->expireDrafts($table, $resource, $resolvedModule);
                }

                $payload[$publishedColumn] = $current ? 1 : 0;
            }
            if (Schema::hasColumn($table, 'published_at')) {
                $payload['published_at'] = $current ? time() : 0;
            }
            if (Schema::hasColumn($table, 'created_at') && !isset($payload['created_at'])) {
                $payload['created_at'] = time();
            }
            if (Schema::hasColumn($table, 'updated_at')) {
                $payload['updated_at'] = time();
            }

            return DB::table($table)->insertGetId($payload);
        });

        return [
            'persisted' => true,
            'id' => $id,
            'name' => $resource,
            'status' => $status,
            'resource' => $resource,
            'module' => '' !== $module ? $module : (string) ($schema['module'] ?? 'App'),
            'mod_id' => isset($options['mod_id']) ? (int) $options['mod_id'] : null,
            'schema' => $schema,
        ];
    }

    /**
     * 查询首条匹配版本记录.
     *
     * @return array<string, mixed>|null
     */
    private function firstMatching(string $resource, string $module, string $status, bool $current): ?array
    {
        $table = $this->versionTable();
        if (!Schema::hasTable($table)) {
            return null;
        }

        $query = $this->baseResourceQuery($table, $resource, $module)->orderByDesc('id');
        if (Schema::hasColumn($table, 'status')) {
            $query->where('status', $status);
        }
        $publishedColumn = (string) ($this->config['version']['published_column'] ?? 'is_current');
        if (Schema::hasColumn($table, $publishedColumn)) {
            $query->where($publishedColumn, $current ? 1 : 0);
        }

        $record = (array) ($query->first() ?? []);

        return 0 === \count($record) ? null : $this->normalizeRecord($record);
    }

    /**
     * 构建资源维度查询.
     */
    private function baseResourceQuery(string $table, string $resource, string $module)
    {
        $resourceColumn = $this->resolveResourceColumn($table, (array) ($this->config['version'] ?? []));
        $moduleColumn = (string) ($this->config['version']['module_column'] ?? 'module');

        return DB::table($table)
            ->where($resourceColumn, $resource)
            ->when('' !== $module && Schema::hasColumn($table, $moduleColumn), function ($query) use ($moduleColumn, $module) {
                return $query->where($moduleColumn, $module);
            });
    }

    /**
     * 统一版本记录结构.
     *
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function normalizeRecord(array $record): array
    {
        $table = $this->versionTable();
        $schemaColumn = (string) ($this->config['version']['schema_column'] ?? 'schema_json');
        $resourceColumn = $this->resolveResourceColumn($table, (array) ($this->config['version'] ?? []));
        $moduleColumn = (string) ($this->config['version']['module_column'] ?? 'module');

        $schema = $record[$schemaColumn] ?? null;
        if (\is_string($schema)) {
            $decoded = json_decode($schema, true);
            if (JSON_ERROR_NONE === json_last_error()) {
                $schema = $decoded;
            }
        }

        $resource = (string) ($record[$resourceColumn] ?? '');
        $schema = $this->normalizeSchemaSnapshot(\is_array($schema) ? $schema : [], $resource);

        return [
            'persisted' => true,
            'id' => $record['id'] ?? null,
            'mod_id' => $record['mod_id'] ?? null,
            'name' => '' !== $resource ? $resource : null,
            'resource' => $record[$resourceColumn] ?? null,
            'module' => $record[$moduleColumn] ?? '',
            'version_no' => $record['version_no'] ?? null,
            'status' => $record['status'] ?? null,
            'is_current' => $record['is_current'] ?? null,
            'created_at' => $record['created_at'] ?? null,
            'updated_at' => $record['updated_at'] ?? null,
            'published_at' => $record['published_at'] ?? null,
            'remark' => $record['remark'] ?? null,
            'schema' => $schema,
        ];
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function normalizeSchemaSnapshot(array $schema, string $resource): array
    {
        if (0 === \count($schema)) {
            return [];
        }

        if (!isset($schema['name']) || '' === (string) $schema['name']) {
            $schema['name'] = $resource;
        }

        return $this->normalizer->normalize($schema);
    }

    /**
     * @param array<string, mixed> $version
     */
    private function resolveResourceColumn(string $table, array $version): string
    {
        $configured = (string) ($version['resource_column'] ?? 'name');
        if (Schema::hasColumn($table, $configured)) {
            return $configured;
        }
        if (Schema::hasColumn($table, 'name')) {
            return 'name';
        }

        throw new \RuntimeException("Version table [{$table}] requires resource column [name].");
    }

    /**
     * 返回版本表名称.
     */
    private function versionTable(): string
    {
        return (string) ($this->config['version']['table'] ?? 'mod_versions');
    }

    private function archivePublishedVersions(string $table, string $resource, string $module, string $publishedColumn): void
    {
        $payload = [$publishedColumn => 0];
        if (Schema::hasColumn($table, 'status')) {
            $payload['status'] = self::STATUS_ARCHIVED;
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = time();
        }

        $this->baseResourceQuery($table, $resource, $module)
            ->where($publishedColumn, 1)
            ->update($payload);
    }

    private function expireDrafts(string $table, string $resource, string $module): void
    {
        if (!Schema::hasColumn($table, 'status')) {
            return;
        }

        $payload = ['status' => self::STATUS_SUPERSEDED];
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = time();
        }

        $this->baseResourceQuery($table, $resource, $module)
            ->where('status', self::STATUS_DRAFT)
            ->update($payload);
    }

    private function nextVersionNumber(string $table, string $resource, string $module, string $resourceColumn, string $moduleColumn): int
    {
        $query = DB::table($table);
        if (Schema::hasColumn($table, $resourceColumn)) {
            $query->where($resourceColumn, $resource);
        }
        if ('' !== $module && Schema::hasColumn($table, $moduleColumn)) {
            $query->where($moduleColumn, $module);
        }

        return (int) $query->max('version_no') + 1;
    }

    /**
     * 为版本历史查询应用筛选条件。
     *
     * 支持：
     * - `status`: string|string[]
     * - `is_current`: bool|int
     * - `version_no`: int
     * - `keyword`: 备注或版本号模糊查询
     * - `ids`: int[]
     *
     * @param array<string, mixed> $filters
     */
    private function applyHistoryFilters($query, string $table, array $filters): void
    {
        $status = $filters['status'] ?? null;
        if (\is_string($status) && '' !== trim($status) && Schema::hasColumn($table, 'status')) {
            $query->where('status', trim($status));
        } elseif (\is_array($status) && 0 !== \count($status) && Schema::hasColumn($table, 'status')) {
            $statuses = array_values(array_filter(array_map(static function ($item): ?string {
                return \is_string($item) && '' !== trim($item) ? trim($item) : null;
            }, $status)));
            if (0 !== \count($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        if (array_key_exists('is_current', $filters) && Schema::hasColumn($table, 'is_current')) {
            $query->where('is_current', true === (bool) $filters['is_current'] ? 1 : 0);
        }

        if (isset($filters['version_no']) && \is_numeric($filters['version_no']) && Schema::hasColumn($table, 'version_no')) {
            $query->where('version_no', (int) $filters['version_no']);
        }

        if (isset($filters['ids']) && \is_array($filters['ids'])) {
            $ids = array_values(array_filter(array_map(static function ($item): ?int {
                return \is_numeric($item) ? (int) $item : null;
            }, $filters['ids']), static function (?int $id): bool {
                return null !== $id && $id > 0;
            }));
            if (0 !== \count($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        $keyword = $filters['keyword'] ?? null;
        if (\is_string($keyword) && '' !== trim($keyword)) {
            $keyword = trim($keyword);
            $query->where(function ($subQuery) use ($table, $keyword): void {
                if (Schema::hasColumn($table, 'remark')) {
                    $subQuery->orWhere('remark', 'like', '%'.$keyword.'%');
                }
                if (Schema::hasColumn($table, 'version_no') && ctype_digit($keyword)) {
                    $subQuery->orWhere('version_no', (int) $keyword);
                }
            });
        }
    }
}
