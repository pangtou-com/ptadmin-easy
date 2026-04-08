<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Versioning;

use PTAdmin\Easy\Core\Migration\MigrationPlanner;
use PTAdmin\Easy\Core\Migration\MigrationPlan;
use PTAdmin\Easy\Core\Migration\SchemaSynchronizer;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaCompiler;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaNormalizer;
use PTAdmin\Easy\Core\Schema\Loader\SchemaLoader;
use PTAdmin\Easy\Core\Schema\Registry\PublishedResourceCatalog;
use PTAdmin\Easy\Core\Schema\Versioning\Contracts\SchemaVersionStoreInterface;
use PTAdmin\Easy\Core\Schema\Versioning\Stores\DatabaseSchemaVersionStore;

/**
 * Schema 发布编排器.
 *
 * 负责将 publish 流程串联为固定生命周期：
 * 1. 解析当前已发布 schema
 * 2. 编译待发布 schema
 * 3. 生成迁移计划
 * 4. 同步表结构
 * 5. 持久化新版本
 */
class SchemaPublisher
{
    /** @var SchemaLoader */
    private $loader;

    /** @var SchemaCompiler */
    private $compiler;

    /** @var MigrationPlanner */
    private $planner;

    /** @var SchemaSynchronizer */
    private $synchronizer;

    /** @var SchemaVersionStoreInterface */
    private $versionStore;

    /** @var PublishedResourceCatalog */
    private $catalog;

    /** @var SchemaNormalizer */
    private $normalizer;

    public function __construct(
        ?SchemaLoader $loader = null,
        ?SchemaCompiler $compiler = null,
        ?MigrationPlanner $planner = null,
        ?SchemaSynchronizer $synchronizer = null,
        ?SchemaVersionStoreInterface $versionStore = null,
        ?PublishedResourceCatalog $catalog = null
    ) {
        $this->loader = $loader ?? new SchemaLoader();
        $this->compiler = $compiler ?? new SchemaCompiler();
        $this->planner = $planner ?? new MigrationPlanner();
        $this->synchronizer = $synchronizer ?? new SchemaSynchronizer();
        $this->versionStore = $versionStore ?? $this->versionStoreFromConfig();
        $this->catalog = $catalog ?? new PublishedResourceCatalog();
        $this->normalizer = new SchemaNormalizer();
    }

    /**
     * 预览发布计划，不执行结构同步和版本持久化.
     *
     * @param array<string, mixed> $schema
     */
    public function plan(string $resource, array $schema, string $module = ''): MigrationPlan
    {
        $schema = $this->normalizeManagedSchema($resource, $schema, $module);

        $current = $this->resolveCurrent($resource, $module);
        $next = $this->compiler->compile($this->loader->load($schema, $module));

        return $this->planner->plan($current, $next);
    }

    /**
     * 按草稿版本 ID 预览发布计划。
     *
     * 该方法用于正式发布前的确认页，避免前端再次回传整份 schema。
     */
    public function planVersion(int $versionId, string $resource = '', string $module = ''): MigrationPlan
    {
        $record = $this->resolveVersionRecord($versionId, $resource, $module, 'draft');

        return $this->plan(
            (string) $record['resource'],
            (array) $record['schema'],
            (string) $record['module']
        );
    }

    /**
     * 保存草稿版本，不执行数据库结构同步.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function saveDraft(string $resource, array $schema, string $module = '', array $options = []): array
    {
        $schema = $this->normalizeManagedSchema($resource, $schema, $module);

        $definition = $this->compiler->compile($this->loader->load($schema, $module));
        $resourceRecord = $this->catalog->ensureResource($resource, $definition->toArray(), (string) $schema['module']);
        $options['mod_id'] = (int) ($resourceRecord['id'] ?? 0);

        return $this->versionStore->saveDraft($resource, $schema, $module, $options);
    }

    /**
     * 返回当前已发布版本记录.
     *
     * @return array<string, mixed>|null
     */
    public function currentVersion(string $resource, string $module = ''): ?array
    {
        return $this->versionStore->current($resource, $module);
    }

    /**
     * 返回最新草稿版本记录.
     *
     * @return array<string, mixed>|null
     */
    public function latestDraft(string $resource, string $module = ''): ?array
    {
        return $this->versionStore->latestDraft($resource, $module);
    }

    /**
     * 返回指定版本详情，并校验资源归属。
     *
     * @return array<string, mixed>|null
     */
    public function version(int $versionId, string $resource = '', string $module = ''): ?array
    {
        return $this->resolveVersionRecord($versionId, $resource, $module, null, false);
    }

    /**
     * 返回适合后台版本管理页直接消费的列表面板结构。
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function versionPanel(string $resource, string $module = '', int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $filters['page'] = $page;

        $items = $this->history($resource, $module, $pageSize, $filters);
        $total = \count($this->history($resource, $module, 0, $this->panelFilterSubset($filters)));
        $allVersions = $this->history($resource, $module, 0);

        return [
            'summary' => $this->buildPanelSummary($resource, $module, $allVersions),
            'stats' => $this->buildPanelStats($allVersions),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'last_page' => max(1, (int) ceil($total / $pageSize)),
            ],
            'filters' => [
                'name' => $resource,
                'resource' => $resource,
                'module' => $module,
                'applied' => $this->panelFilterSubset($filters),
            ],
            'items' => array_values(array_map(function (array $item) use ($resource, $module): array {
                return $this->buildPanelItem($item, $resource, $module);
            }, $items)),
        ];
    }

    /**
     * 返回适合版本详情抽屉/页面使用的结构。
     *
     * @return array<string, mixed>|null
     */
    public function versionDetail(int $versionId, string $resource = '', string $module = ''): ?array
    {
        $record = $this->version($versionId, $resource, $module);
        if (null === $record) {
            return null;
        }

        $plan = $this->buildVersionPlanAgainstPrevious($record, $resource, $module);

        return [
            'version' => $record,
            'schema' => (array) ($record['schema'] ?? []),
            'actions' => $this->versionActions($record),
            'change_summary' => $this->changeSummaryFromPlan($plan),
            'plan' => $plan->toArray(),
        ];
    }

    /**
     * 返回版本历史列表.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $resource, string $module = '', int $limit = 20, array $filters = []): array
    {
        return $this->versionStore->history($resource, $module, $limit, $filters);
    }

    /**
     * 返回当前资源的草稿列表。
     *
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function drafts(string $resource, string $module = '', int $limit = 20, array $filters = []): array
    {
        $filters['status'] = 'draft';

        return $this->versionStore->history($resource, $module, $limit, $filters);
    }

    /**
     * 按版本 ID 更新草稿内容。
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function updateDraft(int $versionId, array $schema, string $resource = '', string $module = '', array $options = []): array
    {
        $record = $this->resolveVersionRecord($versionId, $resource, $module, 'draft');
        $resource = (string) $record['resource'];
        $module = (string) $record['module'];

        $schema = $this->normalizeManagedSchema($resource, $schema, $module);

        $definition = $this->compiler->compile($this->loader->load($schema, $module));
        $resourceRecord = $this->catalog->ensureResource($resource, $definition->toArray(), $module);
        $options['mod_id'] = (int) ($resourceRecord['id'] ?? 0);

        $updated = $this->versionStore->updateDraft($versionId, $schema, $options);
        if (null === $updated) {
            throw new \RuntimeException("Schema draft [{$versionId}] could not be updated.");
        }

        return $updated;
    }

    /**
     * 删除指定草稿版本。
     */
    public function deleteDraft(int $versionId, string $resource = '', string $module = ''): bool
    {
        $this->resolveVersionRecord($versionId, $resource, $module, 'draft');

        return $this->versionStore->deleteDraft($versionId);
    }

    /**
     * 对比两个版本之间的结构差异。
     *
     * 当 `$toVersionId` 为空时，默认与当前发布版本对比。
     *
     * @return array<string, mixed>
     */
    public function diffVersions(int $fromVersionId, ?int $toVersionId = null, string $resource = '', string $module = ''): array
    {
        $from = $this->resolveVersionRecord($fromVersionId, $resource, $module);
        if (null === $from) {
            throw new \InvalidArgumentException("Schema version [{$fromVersionId}] not found.");
        }

        if (null === $toVersionId) {
            $current = $this->currentVersion(
                (string) $from['resource'],
                (string) $from['module']
            );
            if (null === $current) {
                throw new \InvalidArgumentException('Current published version does not exist.');
            }

            $to = $current;
            $toVersionId = (int) ($current['id'] ?? 0);
        } else {
            $to = $this->resolveVersionRecord($toVersionId, (string) $from['resource'], (string) $from['module']);
            if (null === $to) {
                throw new \InvalidArgumentException("Schema version [{$toVersionId}] not found.");
            }
        }

        $fromDefinition = $this->compiler->compile(
            $this->loader->load((array) ($from['schema'] ?? []), (string) ($from['module'] ?? ''))
        );
        $toDefinition = $this->compiler->compile(
            $this->loader->load((array) ($to['schema'] ?? []), (string) ($to['module'] ?? ''))
        );
        $plan = $this->planner->plan($fromDefinition, $toDefinition);

        return [
            'from_version' => $from,
            'to_version' => $to,
            'plan' => $plan->toArray(),
        ];
    }

    /**
     * 删除指定历史版本。
     *
     * 仅允许删除：
     * - draft
     * - archived
     * - superseded
     *
     * 不允许删除当前发布版本。
     */
    public function deleteVersion(int $versionId, string $resource = '', string $module = ''): bool
    {
        $record = $this->resolveVersionRecord($versionId, $resource, $module);
        if (null === $record) {
            throw new \InvalidArgumentException("Schema version [{$versionId}] not found.");
        }

        $status = (string) ($record['status'] ?? '');
        if ('published' === $status) {
            throw new \InvalidArgumentException("Schema version [{$versionId}] is the current published version and cannot be deleted.");
        }
        if (!\in_array($status, ['draft', 'archived', 'superseded'], true)) {
            throw new \InvalidArgumentException("Schema version [{$versionId}] status [{$status}] cannot be deleted.");
        }

        return $this->versionStore->deleteVersion($versionId);
    }

    /**
     * 执行 schema 发布.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     */
    public function publish(string $resource, array $schema, string $module = '', array $options = []): PublishResult
    {
        $schema = $this->normalizeManagedSchema($resource, $schema, $module);

        $plan = $this->plan($resource, $schema, $module);
        $next = $this->compiler->compile($this->loader->load($schema, $module));
        $resourceRecord = $this->catalog->ensureResource($resource, $next->toArray(), (string) $schema['module']);
        $options['mod_id'] = (int) ($resourceRecord['id'] ?? 0);

        $force = true === (bool) ($options['force'] ?? false);
        $sync = false !== (bool) ($options['sync'] ?? true);
        $syncApplied = false;
        if ($sync) {
            $this->synchronizer->sync($next, $plan, $force);
            $syncApplied = true;
        }

        $version = $this->versionStore->publish($resource, $schema, $module, $options);
        $this->catalog->syncPublishedResource($next, $version);

        return new PublishResult($version, $plan, $syncApplied);
    }

    /**
     * 按草稿版本 ID 执行发布。
     *
     * 该流程会直接激活现有 draft 记录，不再额外创建一条新的发布快照。
     *
     * @param array<string, mixed> $options
     */
    public function publishVersion(int $versionId, string $resource = '', string $module = '', array $options = []): PublishResult
    {
        $record = $this->resolveVersionRecord($versionId, $resource, $module, 'draft');
        $resource = (string) $record['resource'];
        $module = (string) $record['module'];
        $schema = (array) $record['schema'];

        $plan = $this->plan($resource, $schema, $module);
        $next = $this->compiler->compile($this->loader->load($schema, $module));

        $force = true === (bool) ($options['force'] ?? false);
        $sync = false !== (bool) ($options['sync'] ?? true);
        $syncApplied = false;
        if ($sync) {
            $this->synchronizer->sync($next, $plan, $force);
            $syncApplied = true;
        }

        $version = $this->versionStore->markAsCurrent($versionId);
        if (null === $version) {
            throw new \RuntimeException("Schema version [{$versionId}] could not be activated.");
        }

        $this->catalog->syncPublishedResource($next, $version);

        return new PublishResult($version, $plan, $syncApplied);
    }

    /**
     * 回滚到指定版本.
     */
    public function rollback(int $versionId, array $options = []): RollbackResult
    {
        $record = $this->versionStore->find($versionId);
        if (null === $record) {
            throw new \InvalidArgumentException("Schema version [{$versionId}] not found.");
        }

        $resource = (string) ($record['resource'] ?? '');
        $module = (string) ($record['module'] ?? '');
        $schema = (array) ($record['schema'] ?? []);
        if ('' === $resource || 0 === \count($schema)) {
            throw new \InvalidArgumentException("Schema version [{$versionId}] is invalid.");
        }

        $plan = $this->plan($resource, $schema, $module);
        $next = $this->compiler->compile($this->loader->load($schema, $module));

        $force = true === (bool) ($options['force'] ?? false);
        $sync = false !== (bool) ($options['sync'] ?? true);
        $syncApplied = false;
        if ($sync) {
            $this->synchronizer->sync($next, $plan, $force);
            $syncApplied = true;
        }

        $version = $this->versionStore->markAsCurrent($versionId);
        if (null === $version) {
            throw new \RuntimeException("Schema version [{$versionId}] could not be activated.");
        }
        $this->catalog->syncPublishedResource($next, $version);

        return new RollbackResult($version, $plan, $syncApplied);
    }

    /**
     * 按版本 ID 回滚，并校验资源归属。
     *
     * @param array<string, mixed> $options
     */
    public function rollbackVersion(int $versionId, string $resource = '', string $module = '', array $options = []): RollbackResult
    {
        $record = $this->resolveVersionRecord($versionId, $resource, $module);
        if ('draft' === (string) ($record['status'] ?? '')) {
            throw new \InvalidArgumentException("Schema version [{$versionId}] is a draft and cannot be rolled back.");
        }

        return $this->rollback($versionId, $options);
    }

    /**
     * 返回指定版本记录，并执行资源归属与状态校验。
     *
     * @return array<string, mixed>
     */
    private function resolveVersionRecord(int $versionId, string $resource = '', string $module = '', ?string $requiredStatus = null, bool $throwIfMissing = true): ?array
    {
        $record = $this->versionStore->find($versionId);
        if (null === $record) {
            if ($throwIfMissing) {
                throw new \InvalidArgumentException("Schema version [{$versionId}] not found.");
            }

            return null;
        }

        $recordResource = (string) ($record['resource'] ?? '');
        $recordModule = (string) ($record['module'] ?? '');
        if ('' !== $resource && $resource !== $recordResource) {
            throw new \InvalidArgumentException("Schema version [{$versionId}] does not belong to resource [{$resource}].");
        }
        if ('' !== $module && $module !== $recordModule) {
            throw new \InvalidArgumentException("Schema version [{$versionId}] does not belong to module [{$module}].");
        }
        if (null !== $requiredStatus && $requiredStatus !== (string) ($record['status'] ?? '')) {
            throw new \InvalidArgumentException("Schema version [{$versionId}] status must be [{$requiredStatus}].");
        }

        return $record;
    }

    /**
     * @param array<int, array<string, mixed>> $versions
     *
     * @return array<string, mixed>
     */
    private function buildPanelSummary(string $resource, string $module, array $versions): array
    {
        $current = $this->currentVersion($resource, $module);
        $latestDraft = $this->latestDraft($resource, $module);

        return [
            'name' => $resource,
            'resource' => $resource,
            'module' => '' !== $module ? $module : 'App',
            'current_version_id' => null !== $current ? (int) ($current['id'] ?? 0) : null,
            'current_version_no' => null !== $current ? (int) ($current['version_no'] ?? 0) : null,
            'latest_draft_id' => null !== $latestDraft ? (int) ($latestDraft['id'] ?? 0) : null,
            'latest_draft_version_no' => null !== $latestDraft ? (int) ($latestDraft['version_no'] ?? 0) : null,
            'has_unpublished_draft' => null !== $latestDraft,
            'version_count' => \count($versions),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $versions
     *
     * @return array<string, int>
     */
    private function buildPanelStats(array $versions): array
    {
        $stats = [
            'all' => \count($versions),
            'draft' => 0,
            'published' => 0,
            'archived' => 0,
            'superseded' => 0,
        ];

        foreach ($versions as $version) {
            $status = (string) ($version['status'] ?? '');
            if (isset($stats[$status])) {
                ++$stats[$status];
            }
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function buildPanelItem(array $record, string $resource, string $module): array
    {
        $schema = $this->normalizedSchema((array) ($record['schema'] ?? []));
        $plan = $this->buildVersionPlanAgainstPrevious($record, $resource, $module);

        return [
            'id' => (int) ($record['id'] ?? 0),
            'version_no' => (int) ($record['version_no'] ?? 0),
            'status' => (string) ($record['status'] ?? ''),
            'is_current' => (int) ($record['is_current'] ?? 0),
            'remark' => $record['remark'] ?? null,
            'created_at' => (int) ($record['created_at'] ?? 0),
            'updated_at' => (int) ($record['updated_at'] ?? 0),
            'published_at' => (int) ($record['published_at'] ?? 0),
            'schema_title' => (string) ($schema['title'] ?? ($record['resource'] ?? '')),
            'field_count' => \count((array) ($schema['fields'] ?? [])),
            'change_summary' => $this->changeSummaryFromPlan($plan),
            'actions' => $this->versionActions($record),
        ];
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, bool>
     */
    private function versionActions(array $record): array
    {
        $status = (string) ($record['status'] ?? '');

        return [
            'view' => true,
            'edit_draft' => 'draft' === $status,
            'publish' => 'draft' === $status,
            'rollback' => 'archived' === $status,
            'delete' => \in_array($status, ['draft', 'archived', 'superseded'], true),
            'compare' => true,
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function buildVersionPlanAgainstPrevious(array $record, string $resource, string $module): MigrationPlan
    {
        $previous = $this->previousVersionOf($record, $resource, $module);
        $currentDefinition = $this->compiler->compile(
            $this->loader->load((array) ($record['schema'] ?? []), (string) ($record['module'] ?? $module))
        );
        if (null === $previous) {
            return $this->planner->plan(null, $currentDefinition);
        }

        $previousDefinition = $this->compiler->compile(
            $this->loader->load((array) ($previous['schema'] ?? []), (string) ($previous['module'] ?? $module))
        );

        return $this->planner->plan($previousDefinition, $currentDefinition);
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>|null
     */
    private function previousVersionOf(array $record, string $resource, string $module): ?array
    {
        $currentVersionNo = (int) ($record['version_no'] ?? 0);
        $versions = $this->history($resource, $module, 0);
        $previous = null;

        foreach ($versions as $item) {
            $versionNo = (int) ($item['version_no'] ?? 0);
            if ($versionNo >= $currentVersionNo) {
                continue;
            }

            if (null === $previous || $versionNo > (int) ($previous['version_no'] ?? 0)) {
                $previous = $item;
            }
        }

        return $previous;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function normalizedSchema(array $schema): array
    {
        return 0 === \count($schema) ? [] : $this->normalizer->normalize($schema);
    }

    /**
     * 统一发布链路中的资源协议字段。
     *
     * 对外与内部统一使用 `name`。
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function normalizeManagedSchema(string $resource, array $schema, string $module = ''): array
    {
        $schema['name'] = $resource;
        $schema['module'] = $schema['module'] ?? ('' !== $module ? $module : 'App');

        return $this->normalizer->normalize($schema);
    }

    /**
     * @return array<string, mixed>
     */
    private function changeSummaryFromPlan(MigrationPlan $plan): array
    {
        $operations = $plan->operations();

        return [
            'create_table' => true === (bool) ($operations['create_table'] ?? false),
            'rename_fields' => \count((array) ($operations['rename_fields'] ?? [])),
            'add_fields' => \count((array) ($operations['add_fields'] ?? [])),
            'change_fields' => \count((array) ($operations['change_fields'] ?? [])),
            'drop_fields' => \count((array) ($operations['drop_fields'] ?? [])),
            'add_unique' => \count((array) ($operations['add_unique'] ?? [])),
            'drop_unique' => \count((array) ($operations['drop_unique'] ?? [])),
            'destructive' => $plan->isDestructive(),
            'empty' => $plan->isEmpty(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function panelFilterSubset(array $filters): array
    {
        return array_filter([
            'status' => $filters['status'] ?? null,
            'is_current' => $filters['is_current'] ?? null,
            'version_no' => $filters['version_no'] ?? null,
            'keyword' => $filters['keyword'] ?? null,
            'ids' => $filters['ids'] ?? null,
        ], static function ($value): bool {
            return null !== $value && [] !== $value && '' !== $value;
        });
    }

    /**
     * 读取当前已发布 schema.
     */
    private function resolveCurrent(string $resource, string $module = '')
    {
        try {
            return $this->compiler->compile($this->loader->load($resource, $module));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 从配置中创建版本仓库.
     */
    private function versionStoreFromConfig(): SchemaVersionStoreInterface
    {
        $store = config('easy.schema.version.store');
        if (\is_string($store) && class_exists($store)) {
            $instance = app($store);
            if ($instance instanceof SchemaVersionStoreInterface) {
                return $instance;
            }
        }

        return new DatabaseSchemaVersionStore();
    }
}
