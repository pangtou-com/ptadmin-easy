<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Versioning;

use Illuminate\Support\Arr;
use PTAdmin\Easy\Core\Migration\MigrationPlanner;
use PTAdmin\Easy\Core\Migration\MigrationPlan;
use PTAdmin\Easy\Core\Migration\SchemaSynchronizer;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaCompiler;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaNormalizer;
use PTAdmin\Easy\Core\Schema\Loader\SchemaLoader;
use PTAdmin\Easy\Core\Schema\Registry\PublishedResourceCatalog;
use PTAdmin\Easy\Core\Schema\Versioning\Contracts\SchemaVersionStoreInterface;
use PTAdmin\Easy\Core\Schema\Versioning\Stores\DatabaseSchemaVersionStore;
use PTAdmin\Easy\Exceptions\SchemaFieldReferenceException;

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
    /** @var string[] */
    private const LAYOUT_CHILD_KEYS = ['children', 'nodes', 'items', 'body', 'columns', 'tabs', 'fields', 'schemas'];

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
     * 保存资源级草稿。
     *
     * 该路径服务于“先建模型，后维护字段”的后台场景，允许
     * `fields` 为空；正式发布仍会走严格 schema 校验。
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function saveResourceDraft(string $resource, array $schema, string $module = '', array $options = []): array
    {
        $schema = $this->normalizeManagedSchema($resource, $schema, $module);
        $this->validateDraftSchema($schema);

        $resourceRecord = $this->ensureDraftResource($resource, $schema, (string) $schema['module']);
        $options['mod_id'] = (int) ($resourceRecord['id'] ?? 0);

        return $this->versionStore->saveDraft($resource, $schema, (string) $schema['module'], $options);
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
     * 以草稿校验规则更新版本，允许字段为空.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function updateResourceDraft(int $versionId, array $schema, string $resource = '', string $module = '', array $options = []): array
    {
        $record = $this->resolveVersionRecord($versionId, $resource, $module, 'draft');
        $resource = (string) $record['resource'];
        $module = (string) $record['module'];
        $schema = $this->normalizeManagedSchema($resource, $schema, $module);
        $this->validateDraftSchema($schema);

        $resourceRecord = $this->ensureDraftResource($resource, $schema, $module);
        $options['mod_id'] = (int) ($resourceRecord['id'] ?? 0);

        $updated = $this->versionStore->updateDraft($versionId, $schema, $options);
        if (null === $updated) {
            throw new \RuntimeException("Schema draft [{$versionId}] could not be updated.");
        }

        return $updated;
    }

    /**
     * 返回可编辑草稿 schema；不存在草稿时返回当前发布 schema 或空 schema。
     *
     * @return array<string, mixed>
     */
    public function draftSchema(string $resource, string $module = '', ?int $draftId = null): array
    {
        if (null !== $draftId) {
            $record = $this->resolveVersionRecord($draftId, $resource, $module, 'draft');

            return (array) ($record['schema'] ?? []);
        }

        $draft = $this->latestDraft($resource, $module);
        if (null !== $draft) {
            return (array) ($draft['schema'] ?? []);
        }

        $current = $this->currentVersion($resource, $module);
        if (null !== $current) {
            return (array) ($current['schema'] ?? []);
        }

        return $this->normalizeManagedSchema($resource, [
            'title' => $resource,
            'fields' => [],
        ], $module);
    }

    /**
     * 返回当前可编辑草稿字段.
     *
     * @return array<int, array<string, mixed>>
     */
    public function draftFields(string $resource, string $module = '', ?int $draftId = null): array
    {
        return array_values(array_filter((array) ($this->draftSchema($resource, $module, $draftId)['fields'] ?? []), 'is_array'));
    }

    /**
     * 添加字段到当前草稿.
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function addDraftField(string $resource, string $module, array $field, array $options = []): array
    {
        $record = $this->editableDraftRecord($resource, $module, $options);
        $schema = (array) ($record['schema'] ?? []);
        $fields = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));
        $name = $this->fieldName($field);
        if (null !== $this->findFieldIndex($fields, $name)) {
            throw new \InvalidArgumentException("Schema field [{$name}] is duplicated.");
        }

        $fields[] = $field;
        $schema['fields'] = $fields;

        return $this->updateResourceDraft((int) $record['id'], $schema, $resource, $module, $options);
    }

    /**
     * 更新草稿字段.
     *
     * @param array<string, mixed> $patch
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function updateDraftField(string $resource, string $module, string $field, array $patch, array $options = []): array
    {
        if (isset($patch['name']) && (string) $patch['name'] !== $field) {
            throw new \InvalidArgumentException('Field rename is not supported by updateField().');
        }
        unset($patch['name']);

        $record = $this->editableDraftRecord($resource, $module, $options);
        $schema = (array) ($record['schema'] ?? []);
        $fields = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));
        $index = $this->findFieldIndex($fields, $field);
        if (null === $index) {
            throw new \InvalidArgumentException("Schema field [{$field}] does not exist.");
        }

        $fields[$index] = array_replace_recursive($fields[$index], $patch);
        $schema['fields'] = $fields;

        return $this->updateResourceDraft((int) $record['id'], $schema, $resource, $module, $options);
    }

    /**
     * 重命名草稿字段。
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function renameDraftField(string $resource, string $module, string $from, string $to, array $options = []): array
    {
        $to = trim($to);
        if ('' === $to) {
            throw new \InvalidArgumentException('Schema field name is invalid.');
        }
        if ($from === $to) {
            return $this->editableDraftRecord($resource, $module, $options);
        }

        $record = $this->editableDraftRecord($resource, $module, $options);
        $schema = (array) ($record['schema'] ?? []);
        $fields = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));
        $index = $this->findFieldIndex($fields, $from);
        if (null === $index) {
            throw new \InvalidArgumentException("Schema field [{$from}] does not exist.");
        }
        if (null !== $this->findFieldIndex($fields, $to)) {
            throw new \InvalidArgumentException("Schema field [{$to}] is duplicated.");
        }

        $current = $this->currentVersion($resource, $module);
        $currentFields = array_values(array_filter((array) data_get($current ?? [], 'schema.fields', []), 'is_array'));
        $renameFrom = $this->resolveRenameFrom($fields[$index], $from, $to, $currentFields);
        $references = $this->collectFieldReferences($schema, $from);

        $fields[$index]['name'] = $to;
        if (null !== $renameFrom) {
            $fields[$index]['rename_from'] = $renameFrom;
        } else {
            unset($fields[$index]['rename_from']);
            if (isset($fields[$index]['extends']) && \is_array($fields[$index]['extends'])) {
                unset($fields[$index]['extends']['rename_from']);
            }
        }

        $schema['fields'] = $fields;
        $schema = $this->renameFieldReferences($schema, $from, $to);
        $schema = $this->renameFieldInLayout($schema, $from, $to);

        $updated = $this->updateResourceDraft((int) $record['id'], $schema, $resource, $module, $options);
        $updated['summary'] = [
            'type' => 'rename_field',
            'field' => $to,
            'from' => $from,
            'to' => $to,
            'rename_from' => $renameFrom,
            'references_updated' => $references,
        ];

        return $updated;
    }

    /**
     * 删除草稿字段.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function deleteDraftField(string $resource, string $module, string $field, array $options = []): array
    {
        $record = $this->editableDraftRecord($resource, $module, $options);
        $schema = (array) ($record['schema'] ?? []);
        $references = $this->collectFieldReferences($schema, $field);
        if (0 !== \count($references)) {
            if (true === (bool) ($options['cleanup_references'] ?? false)) {
                $schema = $this->cleanupFieldReferences($schema, $field);
            } else {
                throw new SchemaFieldReferenceException($field, 'delete', $references);
            }
        }

        $fields = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));
        $index = $this->findFieldIndex($fields, $field);
        if (null === $index) {
            throw new \InvalidArgumentException("Schema field [{$field}] does not exist.");
        }

        unset($fields[$index]);
        $schema['fields'] = array_values($fields);
        $schema = $this->removeFieldFromLayout($schema, $field);

        $updated = $this->updateResourceDraft((int) $record['id'], $schema, $resource, $module, $options);
        $updated['summary'] = [
            'type' => 'delete_field',
            'field' => $field,
            'cleanup_applied' => true === (bool) ($options['cleanup_references'] ?? false),
            'references_removed' => $references,
        ];

        return $updated;
    }

    /**
     * 重排草稿字段.
     *
     * @param string[]             $fieldNames
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function reorderDraftFields(string $resource, string $module, array $fieldNames, array $options = []): array
    {
        $record = $this->editableDraftRecord($resource, $module, $options);
        $schema = (array) ($record['schema'] ?? []);
        $fields = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));
        $byName = [];
        foreach ($fields as $field) {
            $byName[(string) ($field['name'] ?? '')] = $field;
        }

        $ordered = [];
        foreach ($fieldNames as $name) {
            if (!\is_string($name) || !isset($byName[$name])) {
                continue;
            }
            $ordered[] = $byName[$name];
            unset($byName[$name]);
        }

        $schema['fields'] = array_values(array_merge($ordered, array_values($byName)));

        return $this->updateResourceDraft((int) $record['id'], $schema, $resource, $module, $options);
    }

    /**
     * 预览当前草稿发布计划.
     */
    public function planDraft(string $resource, string $module = '', ?int $draftId = null): MigrationPlan
    {
        $record = null === $draftId ? $this->latestDraft($resource, $module) : $this->resolveVersionRecord($draftId, $resource, $module, 'draft');
        if (null === $record) {
            throw new \InvalidArgumentException('Latest schema draft does not exist.');
        }

        return $this->planVersion((int) $record['id'], $resource, $module);
    }

    /**
     * 发布当前草稿.
     */
    public function publishDraft(string $resource, string $module = '', ?int $draftId = null, array $options = []): PublishResult
    {
        $record = null === $draftId ? $this->latestDraft($resource, $module) : $this->resolveVersionRecord($draftId, $resource, $module, 'draft');
        if (null === $record) {
            throw new \InvalidArgumentException('Latest schema draft does not exist.');
        }

        return $this->publishVersion((int) $record['id'], $resource, $module, $options);
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
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function editableDraftRecord(string $resource, string $module, array $options = []): array
    {
        $draftId = isset($options['draft_id']) && is_numeric($options['draft_id']) ? (int) $options['draft_id'] : null;
        if (null !== $draftId) {
            return $this->resolveVersionRecord($draftId, $resource, $module, 'draft');
        }

        $draft = $this->latestDraft($resource, $module);
        if (null !== $draft) {
            return $draft;
        }

        $base = (string) ($options['base'] ?? 'latest_draft');
        $current = 'empty' === $base ? null : $this->currentVersion($resource, $module);
        $schema = null !== $current ? (array) ($current['schema'] ?? []) : [
            'title' => $resource,
            'name' => $resource,
            'module' => '' !== $module ? $module : 'App',
            'fields' => [],
        ];

        return $this->saveResourceDraft($resource, $schema, $module, [
            'remark' => $options['remark'] ?? null,
        ]);
    }

    /**
     * 草稿级校验：允许空字段，但字段非空时仍复用完整编译校验。
     *
     * @param array<string, mixed> $schema
     */
    private function validateDraftSchema(array $schema): void
    {
        $resource = $schema['name'] ?? null;
        if (!\is_string($resource) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $resource)) {
            throw new \InvalidArgumentException('Schema name is required and must use letters, numbers, and underscores.');
        }

        $fields = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));
        if (0 === \count($fields)) {
            return;
        }

        $schema['fields'] = $fields;
        $this->compiler->compile($this->loader->load($schema, (string) ($schema['module'] ?? '')));
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function ensureDraftResource(string $resource, array $schema, string $module): array
    {
        $fields = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));
        if (0 !== \count($fields)) {
            $definition = $this->compiler->compile($this->loader->load($schema, $module));

            return $this->catalog->ensureResource($resource, $definition->toArray(), $module);
        }

        return $this->catalog->ensureResource($resource, $schema, $module);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function fieldName(array $field): string
    {
        $name = $field['name'] ?? null;
        if (!\is_string($name) || '' === trim($name)) {
            throw new \InvalidArgumentException('Schema field name is invalid.');
        }

        return trim($name);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    private function findFieldIndex(array $fields, string $name): ?int
    {
        foreach ($fields as $index => $field) {
            if ((string) ($field['name'] ?? '') === $name) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function assertFieldNotReferenced(array $schema, string $field, string $operation = 'delete'): void
    {
        $references = $this->collectFieldReferences($schema, $field);
        if (0 !== \count($references)) {
            throw new SchemaFieldReferenceException($field, $operation, $references);
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param array<int, array<string, mixed>> $currentFields
     */
    private function resolveRenameFrom(array $field, string $from, string $to, array $currentFields): ?string
    {
        $renameFrom = $field['rename_from'] ?? data_get($field, 'extends.rename_from');
        if (\is_string($renameFrom) && '' !== trim($renameFrom)) {
            $renameFrom = trim($renameFrom);

            return $renameFrom === $to ? null : $renameFrom;
        }

        if (null !== $this->findFieldIndex($currentFields, $from)) {
            return $from === $to ? null : $from;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function renameFieldReferences(array $schema, string $from, string $to): array
    {
        foreach (['title_field', 'cover_field'] as $key) {
            if ((string) ($schema[$key] ?? '') === $from) {
                $schema[$key] = $to;
            }
        }

        foreach ((array) ($schema['search_fields'] ?? []) as $index => $name) {
            if ((string) $name === $from) {
                $schema['search_fields'][$index] = $to;
            }
        }

        $order = (array) ($schema['order'] ?? []);
        if (array_key_exists($from, $order)) {
            $direction = $order[$from];
            unset($order[$from]);
            $order[$to] = $direction;
        }
        if (0 !== \count($order)) {
            $schema['order'] = $order;
        }

        foreach ((array) data_get($schema, 'table.columns', []) as $index => $column) {
            if ((string) $column === $from) {
                data_set($schema, 'table.columns.'.$index, $to);
            }
        }

        $schema['charts'] = $this->renameChartReferences((array) ($schema['charts'] ?? []), $from, $to);

        foreach ((array) ($schema['fields'] ?? []) as $index => $candidate) {
            if (!\is_array($candidate)) {
                continue;
            }

            if ((string) data_get($candidate, 'relation.local_key', '') === $from) {
                data_set($schema, 'fields.'.$index.'.relation.local_key', $to);
            }
        }

        return $schema;
    }

    /**
     * @param array<int, mixed> $charts
     *
     * @return array<int, mixed>
     */
    private function renameChartReferences(array $charts, string $from, string $to): array
    {
        foreach ($charts as $chartIndex => $chart) {
            if (!\is_array($chart)) {
                continue;
            }

            foreach ((array) ($chart['groups'] ?? []) as $index => $group) {
                if ((string) $group === $from) {
                    $charts[$chartIndex]['groups'][$index] = $to;
                }
            }
            if ((string) ($chart['group_by'] ?? '') === $from) {
                $charts[$chartIndex]['group_by'] = $to;
            }
            foreach ((array) ($chart['metrics'] ?? []) as $index => $metric) {
                if (!\is_array($metric)) {
                    continue;
                }
                if ((string) ($metric['field'] ?? '') === $from) {
                    $charts[$chartIndex]['metrics'][$index]['field'] = $to;
                }
            }
            foreach ((array) data_get($chart, 'query.groups', []) as $index => $group) {
                if ((string) $group === $from) {
                    data_set($charts, $chartIndex.'.query.groups.'.$index, $to);
                }
            }
            foreach ((array) data_get($chart, 'query.metrics', []) as $index => $metric) {
                if (!\is_array($metric)) {
                    continue;
                }
                if ((string) ($metric['field'] ?? '') === $from) {
                    data_set($charts, $chartIndex.'.query.metrics.'.$index.'.field', $to);
                }
            }
            foreach ((array) data_get($chart, 'query.filters', []) as $index => $filter) {
                if (!\is_array($filter)) {
                    continue;
                }
                if ((string) ($filter['field'] ?? '') === $from) {
                    data_set($charts, $chartIndex.'.query.filters.'.$index.'.field', $to);
                }
            }
            foreach ((array) data_get($chart, 'query.sorts', []) as $index => $sort) {
                if (!\is_array($sort)) {
                    continue;
                }
                if ((string) ($sort['field'] ?? '') === $from) {
                    data_set($charts, $chartIndex.'.query.sorts.'.$index.'.field', $to);
                }
            }
        }

        return $charts;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function renameFieldInLayout(array $schema, string $from, string $to): array
    {
        $nodes = data_get($schema, 'layout.nodes');
        if (!\is_array($nodes)) {
            return $schema;
        }

        data_set($schema, 'layout.nodes', $this->mutateLayoutNodes($nodes, static function (array $node) use ($from, $to): ?array {
            if ((string) ($node['name'] ?? '') === $from) {
                $node['name'] = $to;
            }

            return $node;
        }));

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function removeFieldFromLayout(array $schema, string $field): array
    {
        $nodes = data_get($schema, 'layout.nodes');
        if (!\is_array($nodes)) {
            return $schema;
        }

        data_set($schema, 'layout.nodes', $this->mutateLayoutNodes($nodes, static function (array $node) use ($field): ?array {
            if ((string) ($node['name'] ?? '') === $field) {
                return null;
            }

            return $node;
        }));

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function cleanupFieldReferences(array $schema, string $field): array
    {
        foreach (['title_field', 'cover_field'] as $key) {
            if ((string) ($schema[$key] ?? '') === $field) {
                unset($schema[$key]);
            }
        }

        $searchFields = array_values(array_filter((array) ($schema['search_fields'] ?? []), static function ($name) use ($field): bool {
            return (string) $name !== $field;
        }));
        if (0 !== \count($searchFields)) {
            $schema['search_fields'] = $searchFields;
        } else {
            unset($schema['search_fields']);
        }

        $order = (array) ($schema['order'] ?? []);
        if (array_key_exists($field, $order)) {
            unset($order[$field]);
        }
        if (0 !== \count($order)) {
            $schema['order'] = $order;
        } else {
            unset($schema['order']);
        }

        $tableColumns = array_values(array_filter((array) data_get($schema, 'table.columns', []), static function ($column) use ($field): bool {
            return (string) $column !== $field;
        }));
        if (0 !== \count($tableColumns)) {
            data_set($schema, 'table.columns', $tableColumns);
        } else {
            if (\is_array(data_get($schema, 'table', null))) {
                Arr::forget($schema, 'table.columns');
            }
        }

        $schema['charts'] = $this->cleanupChartReferences((array) ($schema['charts'] ?? []), $field);
        if (0 === \count((array) $schema['charts'])) {
            unset($schema['charts']);
        }

        foreach ((array) ($schema['fields'] ?? []) as $index => $candidate) {
            if (!\is_array($candidate)) {
                continue;
            }
            if ((string) data_get($candidate, 'relation.local_key', '') === $field) {
                Arr::forget($schema, 'fields.'.$index.'.relation.local_key');
            }
            if ((string) data_get($candidate, 'extends.local_key', '') === $field) {
                Arr::forget($schema, 'fields.'.$index.'.extends.local_key');
            }
        }

        return $schema;
    }

    /**
     * @param array<int, mixed> $charts
     *
     * @return array<int, mixed>
     */
    private function cleanupChartReferences(array $charts, string $field): array
    {
        foreach ($charts as $chartIndex => $chart) {
            if (!\is_array($chart)) {
                continue;
            }

            $groups = array_values(array_filter((array) ($chart['groups'] ?? []), static function ($group) use ($field): bool {
                return (string) $group !== $field;
            }));
            if (0 !== \count($groups)) {
                $charts[$chartIndex]['groups'] = $groups;
            } else {
                unset($charts[$chartIndex]['groups']);
            }

            if ((string) ($chart['group_by'] ?? '') === $field) {
                unset($charts[$chartIndex]['group_by']);
            }

            $metrics = [];
            foreach ((array) ($chart['metrics'] ?? []) as $metric) {
                if (!\is_array($metric) || (string) ($metric['field'] ?? '') !== $field) {
                    $metrics[] = $metric;
                }
            }
            if (0 !== \count($metrics)) {
                $charts[$chartIndex]['metrics'] = array_values($metrics);
            } else {
                unset($charts[$chartIndex]['metrics']);
            }

            $queryGroups = array_values(array_filter((array) data_get($chart, 'query.groups', []), static function ($group) use ($field): bool {
                return (string) $group !== $field;
            }));
            if (0 !== \count($queryGroups)) {
                data_set($charts, $chartIndex.'.query.groups', $queryGroups);
            } else {
                Arr::forget($charts, $chartIndex.'.query.groups');
            }

            if ((string) data_get($chart, 'query.group_by', '') === $field) {
                Arr::forget($charts, $chartIndex.'.query.group_by');
            }

            $queryMetrics = [];
            foreach ((array) data_get($chart, 'query.metrics', []) as $metric) {
                if (!\is_array($metric) || (string) ($metric['field'] ?? '') !== $field) {
                    $queryMetrics[] = $metric;
                }
            }
            if (0 !== \count($queryMetrics)) {
                data_set($charts, $chartIndex.'.query.metrics', array_values($queryMetrics));
            } else {
                Arr::forget($charts, $chartIndex.'.query.metrics');
            }

            $queryAggregates = [];
            foreach ((array) data_get($chart, 'query.aggregates', []) as $metric) {
                if (!\is_array($metric) || (string) ($metric['field'] ?? '') !== $field) {
                    $queryAggregates[] = $metric;
                }
            }
            if (0 !== \count($queryAggregates)) {
                data_set($charts, $chartIndex.'.query.aggregates', array_values($queryAggregates));
            } else {
                Arr::forget($charts, $chartIndex.'.query.aggregates');
            }

            $queryFilters = [];
            foreach ((array) data_get($chart, 'query.filters', []) as $filter) {
                if (!\is_array($filter) || (string) ($filter['field'] ?? '') !== $field) {
                    $queryFilters[] = $filter;
                }
            }
            if (0 !== \count($queryFilters)) {
                data_set($charts, $chartIndex.'.query.filters', array_values($queryFilters));
            } else {
                Arr::forget($charts, $chartIndex.'.query.filters');
            }

            $querySorts = [];
            foreach ((array) data_get($chart, 'query.sorts', []) as $sort) {
                if (!\is_array($sort) || (string) ($sort['field'] ?? '') !== $field) {
                    $querySorts[] = $sort;
                }
            }
            if (0 !== \count($querySorts)) {
                data_set($charts, $chartIndex.'.query.sorts', array_values($querySorts));
            } else {
                Arr::forget($charts, $chartIndex.'.query.sorts');
            }
        }

        return array_values($charts);
    }

    /**
     * @param array<int, mixed> $nodes
     * @param callable(array<string, mixed>): ?array<string, mixed> $mutator
     *
     * @return array<int, mixed>
     */
    private function mutateLayoutNodes(array $nodes, callable $mutator): array
    {
        $result = [];
        foreach ($nodes as $node) {
            if (!\is_array($node)) {
                $result[] = $node;

                continue;
            }

            foreach (self::LAYOUT_CHILD_KEYS as $key) {
                if (!isset($node[$key]) || !\is_array($node[$key])) {
                    continue;
                }
                $node[$key] = $this->mutateLayoutNodes($node[$key], $mutator);
            }

            $mutated = $mutator($node);
            if (null !== $mutated) {
                $result[] = $mutated;
            }
        }

        return array_values($result);
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectFieldReferences(array $schema, string $field): array
    {
        $references = [];

        foreach (['title_field', 'cover_field'] as $key) {
            if ((string) ($schema[$key] ?? '') === $field) {
                $references[] = [
                    'type' => $key,
                    'path' => $key,
                ];
            }
        }

        foreach ((array) ($schema['search_fields'] ?? []) as $index => $name) {
            if ((string) $name === $field) {
                $references[] = [
                    'type' => 'search_fields',
                    'path' => 'search_fields.'.$index,
                ];
            }
        }

        foreach (array_keys((array) ($schema['order'] ?? [])) as $name) {
            if ((string) $name === $field) {
                $references[] = [
                    'type' => 'order',
                    'path' => 'order.'.$name,
                ];
            }
        }

        foreach ((array) data_get($schema, 'table.columns', []) as $index => $name) {
            if ((string) $name === $field) {
                $references[] = [
                    'type' => 'table.columns',
                    'path' => 'table.columns.'.$index,
                ];
            }
        }

        foreach ((array) ($schema['charts'] ?? []) as $chartIndex => $chart) {
            if (!\is_array($chart)) {
                continue;
            }
            foreach ((array) ($chart['groups'] ?? []) as $index => $group) {
                if ((string) $group === $field) {
                    $references[] = [
                        'type' => 'charts.groups',
                        'path' => 'charts.'.$chartIndex.'.groups.'.$index,
                    ];
                }
            }
            if ((string) ($chart['group_by'] ?? '') === $field) {
                $references[] = [
                    'type' => 'charts.group_by',
                    'path' => 'charts.'.$chartIndex.'.group_by',
                ];
            }
            foreach ((array) data_get($chart, 'query.groups', []) as $index => $group) {
                if ((string) $group === $field) {
                    $references[] = [
                        'type' => 'charts.query.groups',
                        'path' => 'charts.'.$chartIndex.'.query.groups.'.$index,
                    ];
                }
            }
            foreach ((array) data_get($chart, 'query.metrics', []) as $index => $metric) {
                if (\is_array($metric) && (string) ($metric['field'] ?? '') === $field) {
                    $references[] = [
                        'type' => 'charts.query.metrics',
                        'path' => 'charts.'.$chartIndex.'.query.metrics.'.$index.'.field',
                    ];
                }
            }
            foreach ((array) data_get($chart, 'query.filters', []) as $index => $filter) {
                if (\is_array($filter) && (string) ($filter['field'] ?? '') === $field) {
                    $references[] = [
                        'type' => 'charts.query.filters',
                        'path' => 'charts.'.$chartIndex.'.query.filters.'.$index.'.field',
                    ];
                }
            }
            foreach ((array) data_get($chart, 'query.sorts', []) as $index => $sort) {
                if (\is_array($sort) && (string) ($sort['field'] ?? '') === $field) {
                    $references[] = [
                        'type' => 'charts.query.sorts',
                        'path' => 'charts.'.$chartIndex.'.query.sorts.'.$index.'.field',
                    ];
                }
            }
        }

        foreach ((array) ($schema['fields'] ?? []) as $index => $candidate) {
            if (!\is_array($candidate)) {
                continue;
            }
            if ((string) data_get($candidate, 'relation.local_key', '') === $field) {
                $references[] = [
                    'type' => 'relation.local_key',
                    'path' => 'fields.'.$index.'.relation.local_key',
                ];
            }
        }

        return $references;
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
