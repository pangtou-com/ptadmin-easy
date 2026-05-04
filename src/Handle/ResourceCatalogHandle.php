<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Handle;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Core\Schema\Registry\PublishedResourceCatalog;
use PTAdmin\Easy\Core\Schema\Versioning\Contracts\SchemaVersionStoreInterface;
use PTAdmin\Easy\Core\Schema\Versioning\SchemaPublisher;

/**
 * 资源目录句柄.
 *
 * 面向“插件模型列表 + 先建模型再维护字段”的后台场景，
 * 只处理资源元数据、草稿创建和列表查询，不负责运行时 CRUD。
 */
final class ResourceCatalogHandle
{
    /** @var SchemaVersionStoreInterface */
    private $versionStore;

    /** @var array<string, mixed> */
    private $config;

    public function __construct(SchemaVersionStoreInterface $versionStore, ?array $config = null)
    {
        $this->versionStore = $versionStore;
        $this->config = $config ?? (array) config('easy.schema', []);
    }

    /**
     * 查询资源列表.
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function lists(array $query = []): array
    {
        $table = $this->modsTable();
        if (!Schema::hasTable($table)) {
            return [
                'data' => [],
                'current_page' => max(1, (int) ($query['page'] ?? 1)),
                'per_page' => max(1, (int) ($query['limit'] ?? $query['per_page'] ?? 20)),
                'total' => 0,
                'stats' => [
                    'total' => 0,
                    'published' => 0,
                    'draft' => 0,
                    'unpublished' => 0,
                ],
            ];
        }

        $builder = DB::table($table);
        $this->applyFilters($builder, $table, $query);
        $builder->orderBy('weight')->orderByDesc('id');

        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = max(1, (int) ($query['limit'] ?? $query['per_page'] ?? 20));
        $total = (clone $builder)->count();
        $stats = $this->buildListStats($table, $query);
        $items = $builder
            ->forPage($page, $limit)
            ->get()
            ->map(function ($row): array {
                return $this->normalizeResourceRecord((array) $row);
            })
            ->all();

        return [
            'data' => $items,
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'stats' => $stats,
        ];
    }

    /**
     * 查询全部资源，适合关联选择等轻量场景.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(string $module = '', array $filters = []): array
    {
        $table = $this->modsTable();
        if (!Schema::hasTable($table)) {
            return [];
        }

        if ('' !== $module) {
            $filters['module'] = $module;
        }
        $filters['limit'] = 0;

        $builder = DB::table($table);
        $this->applyFilters($builder, $table, $filters);

        return $builder
            ->orderBy('weight')
            ->orderByDesc('id')
            ->get()
            ->map(function ($row): array {
                $record = $this->normalizeResourceRecord((array) $row);

                return [
                    'id' => $record['id'],
                    'title' => $record['title'],
                    'name' => $record['name'],
                    'module' => $record['module'],
                    'current_version_id' => $record['current_version_id'],
                    'is_publish' => $record['is_publish'],
                ];
            })
            ->all();
    }

    /**
     * 查询资源详情.
     *
     * @return array<string, mixed>|null
     */
    public function detail(string $resource, string $module = ''): ?array
    {
        $table = $this->modsTable();
        if (!Schema::hasTable($table)) {
            return null;
        }

        $builder = DB::table($table)->where('name', $resource);
        if ('' !== $module && Schema::hasColumn($table, 'module')) {
            $builder->where('module', $module);
        }

        $record = (array) ($builder->first() ?? []);
        if (0 === \count($record)) {
            return null;
        }

        $resourceRecord = $this->normalizeResourceRecord($record);
        $resolvedModule = (string) ($resourceRecord['module'] ?? $module);
        $current = $this->versionStore->current($resource, $resolvedModule);
        $latestDraft = $this->versionStore->latestDraft($resource, $resolvedModule);
        $fieldCount = \count((array) data_get($latestDraft ?? $current ?? [], 'schema.fields', []));
        $publishedFieldCount = \count((array) data_get($current ?? [], 'schema.fields', []));

        return [
            'resource' => $resourceRecord,
            'current_version' => $current,
            'latest_draft' => $latestDraft,
            'field_count' => $fieldCount,
            'published_field_count' => $publishedFieldCount,
            'summary' => [
                'published' => 1 === (int) ($resourceRecord['is_publish'] ?? 0) && (int) ($resourceRecord['current_version_id'] ?? 0) > 0,
                'has_current_version' => null !== $current,
                'has_draft' => null !== $latestDraft,
                'pending_changes' => null !== $latestDraft,
                'current_version_id' => null !== $current ? (int) ($current['id'] ?? 0) : null,
                'latest_draft_id' => null !== $latestDraft ? (int) ($latestDraft['id'] ?? 0) : null,
                'editing_field_count' => $fieldCount,
                'draft_field_count' => \count((array) data_get($latestDraft ?? [], 'schema.fields', [])),
                'published_field_count' => $publishedFieldCount,
                'latest_draft_updated_at' => null !== $latestDraft ? (int) ($latestDraft['updated_at'] ?? 0) : null,
                'current_version_updated_at' => null !== $current ? (int) ($current['updated_at'] ?? 0) : null,
            ],
        ];
    }

    /**
     * 创建资源级草稿，允许 fields 为空.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function createDraft(array $payload, array $options = []): array
    {
        $resource = (string) ($payload['name'] ?? '');
        $module = (string) ($payload['module'] ?? 'App');
        $schema = array_replace([
            'title' => $resource,
            'name' => $resource,
            'module' => $module,
            'fields' => [],
        ], $payload);
        $schema['fields'] = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));

        return (new SchemaPublisher(null, null, null, null, $this->versionStore, new PublishedResourceCatalog($this->config)))
            ->saveResourceDraft($resource, $schema, $module, $options);
    }

    /**
     * @param mixed                $builder
     * @param array<string, mixed> $query
     */
    private function applyFilters($builder, string $table, array $query): void
    {
        $module = $query['module'] ?? null;
        if (\is_string($module) && '' !== trim($module) && Schema::hasColumn($table, 'module')) {
            $builder->where('module', trim($module));
        }

        if (array_key_exists('published', $query)) {
            $published = true === (bool) $query['published'];
            if (Schema::hasColumn($table, 'is_publish')) {
                $builder->where('is_publish', $published ? 1 : 0);
            }
            if ($published && Schema::hasColumn($table, 'current_version_id')) {
                $builder->where('current_version_id', '>', 0);
            }
        }

        if (array_key_exists('status', $query) && is_numeric($query['status']) && Schema::hasColumn($table, 'status')) {
            $builder->where('status', (int) $query['status']);
        }

        $keyword = $query['keyword'] ?? null;
        if (\is_string($keyword) && '' !== trim($keyword)) {
            $keyword = trim($keyword);
            $builder->where(function ($subQuery) use ($table, $keyword): void {
                $subQuery->orWhere('name', 'like', '%'.$keyword.'%');
                if (Schema::hasColumn($table, 'title')) {
                    $subQuery->orWhere('title', 'like', '%'.$keyword.'%');
                }
                if (Schema::hasColumn($table, 'intro')) {
                    $subQuery->orWhere('intro', 'like', '%'.$keyword.'%');
                }
            });
        }
    }

    /**
     * 构建列表页统计信息。
     *
     * 统计范围会复用当前 module/keyword/status 作用域，
     * 但不受分页和 `published` 筛选影响，便于页面展示全量分布。
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, int>
     */
    private function buildListStats(string $table, array $query): array
    {
        $statsQuery = $query;
        unset($statsQuery['page'], $statsQuery['limit'], $statsQuery['per_page'], $statsQuery['published']);

        $builder = DB::table($table);
        $this->applyFilters($builder, $table, $statsQuery);
        $records = $builder
            ->get(['name', 'module', 'is_publish', 'current_version_id'])
            ->map(static function ($row): array {
                return [
                    'name' => (string) ($row->name ?? ''),
                    'module' => (string) ($row->module ?? 'App'),
                    'is_publish' => (int) ($row->is_publish ?? 0),
                    'current_version_id' => (int) ($row->current_version_id ?? 0),
                ];
            })
            ->all();

        $total = \count($records);
        $published = 0;
        $draft = 0;
        foreach ($records as $record) {
            if (1 === (int) ($record['is_publish'] ?? 0) && (int) ($record['current_version_id'] ?? 0) > 0) {
                ++$published;
            }
            if (null !== $this->versionStore->latestDraft((string) ($record['name'] ?? ''), (string) ($record['module'] ?? 'App'))) {
                ++$draft;
            }
        }

        return [
            'total' => $total,
            'published' => $published,
            'draft' => $draft,
            'unpublished' => max(0, $total - $published),
        ];
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function normalizeResourceRecord(array $record): array
    {
        return [
            'id' => isset($record['id']) ? (int) $record['id'] : null,
            'title' => (string) ($record['title'] ?? ''),
            'name' => (string) ($record['name'] ?? ''),
            'module' => (string) ($record['module'] ?? 'App'),
            'intro' => $record['intro'] ?? null,
            'current_version_id' => (int) ($record['current_version_id'] ?? 0),
            'is_publish' => (int) ($record['is_publish'] ?? 0),
            'status' => (int) ($record['status'] ?? 0),
            'allow_recycle' => (int) ($record['allow_recycle'] ?? 0),
            'track_changes' => (int) ($record['track_changes'] ?? 0),
            'title_field' => $record['title_field'] ?? null,
            'created_at' => isset($record['created_at']) ? (int) $record['created_at'] : null,
            'updated_at' => isset($record['updated_at']) ? (int) $record['updated_at'] : null,
        ];
    }

    private function modsTable(): string
    {
        return (string) ($this->config['tables']['mods'] ?? 'mods');
    }
}
