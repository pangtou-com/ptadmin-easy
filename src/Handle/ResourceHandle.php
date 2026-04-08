<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Handle;

use PTAdmin\Easy\Core\Action\ActionRegistry;
use PTAdmin\Easy\Core\Migration\MigrationPlanner;
use PTAdmin\Easy\Core\Migration\SchemaSynchronizer;
use PTAdmin\Easy\Core\Query\QueryEngine;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Runtime\Runtime;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaCompiler;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Core\Schema\Loader\SchemaLoader;
use PTAdmin\Easy\Core\Schema\Versioning\Contracts\SchemaVersionStoreInterface;
use PTAdmin\Easy\Core\Schema\Versioning\PublishResult;
use PTAdmin\Easy\Core\Schema\Versioning\RollbackResult;
use PTAdmin\Easy\Core\Schema\Versioning\SchemaPublisher;
use PTAdmin\Easy\Core\Support\FieldTypeRegistry;
use PTAdmin\Easy\Engine\Schema\Schema;

/**
 * 内部资源聚合句柄.
 *
 * 负责统一编排 schema、runtime、query 和 versioning 能力，
 * 供公开的 `doc/schema/release/charts` 入口复用。
 */
class ResourceHandle
{
    /** @var array|string */
    private $resource;

    /** @var string */
    private $module;

    /** @var Runtime */
    private $runtime;

    /** @var SchemaLoader */
    private $loader;

    /** @var SchemaCompiler */
    private $compiler;

    /** @var QueryEngine */
    private $queryEngine;

    /** @var SchemaDefinition|null */
    private $definition;

    /** @var SchemaPublisher */
    private $publisher;

    /** @var string[] */
    private $with = [];

    /**
     * @param array|string $resource
     */
    public function __construct(
        $resource,
        string $module = '',
        ?Runtime $runtime = null,
        ?SchemaLoader $loader = null,
        ?SchemaCompiler $compiler = null,
        ?QueryEngine $queryEngine = null,
        ?FieldTypeRegistry $fieldTypeRegistry = null,
        ?ActionRegistry $actionRegistry = null,
        ?SchemaVersionStoreInterface $versionStore = null
    ) {
        $this->resource = $resource;
        $this->module = $module;
        $this->loader = $loader ?? new SchemaLoader();
        $this->compiler = $compiler ?? new SchemaCompiler($fieldTypeRegistry);
        $this->queryEngine = $queryEngine ?? new QueryEngine();
        $this->runtime = $runtime ?? new Runtime($this->loader, $this->compiler, $this->queryEngine, null, $actionRegistry);
        $this->publisher = new SchemaPublisher(
            $this->loader,
            $this->compiler,
            new MigrationPlanner(),
            new SchemaSynchronizer(),
            $versionStore
        );
    }

    /**
     * 创建一条资源数据.
     */
    public function create(array $data, ?ExecutionContext $context = null)
    {
        return $this->runtime->execute('create', $this->resource, $data, $this->module, $context)->data();
    }

    /**
     * 更新一条资源数据.
     */
    public function update($id, array $data, ?ExecutionContext $context = null)
    {
        return $this->runtime->execute('update', $this->resource, ['id' => $id, 'data' => $data], $this->module, $context)->data();
    }

    /**
     * 删除一条资源数据.
     */
    public function delete($id, ?ExecutionContext $context = null)
    {
        return $this->runtime->execute('delete', $this->resource, ['id' => $id], $this->module, $context)->data();
    }

    /**
     * 查询单条详情.
     */
    public function detail($id, ?ExecutionContext $context = null)
    {
        return $this->runtime->execute(
            'detail',
            $this->resource,
            ['id' => $id],
            $this->module,
            $this->contextWithRelations($context)
        )->data();
    }

    /**
     * 查询资源列表.
     *
     * @param array<string, mixed> $query
     */
    public function lists(array $query = [], ?ExecutionContext $context = null): array
    {
        return $this->runtime->execute(
            'list',
            $this->resource,
            $this->mergeRelationQuery($query),
            $this->module,
            $this->contextWithRelations($context)
        )->data();
    }

    /**
     * 执行资源聚合查询.
     *
     * @param array<string, mixed> $query
     *
     * @return array<int, array<string, mixed>>
     */
    public function aggregate(array $query = [], ?ExecutionContext $context = null): array
    {
        return $this->queryEngine->aggregate($this->schema(), $this->mergeRelationQuery($query), $this->contextWithRelations($context));
    }

    /**
     * 声明当前查询需要预加载的关系。
     *
     * 示例：`with('comments:id,content')`
     *
     * @param string|string[] $relations
     */
    public function with($relations): self
    {
        $clone = clone $this;
        $clone->with = array_values(array_unique(array_merge($this->with, $this->normalizeWith($relations))));

        return $clone;
    }

    /**
     * 返回当前资源对应的图表句柄.
     */
    public function charts(): ChartHandle
    {
        return new ChartHandle($this);
    }

    /**
     * 获取编译后的 schema 定义.
     */
    public function schema(): SchemaDefinition
    {
        if (null !== $this->definition) {
            return $this->definition;
        }

        $this->definition = $this->compiler->compile($this->loader->load($this->resource, $this->module));

        return $this->definition;
    }

    /**
     * 返回当前资源的标准化 schema 蓝图.
     *
     * @return array<string, mixed>
     */
    public function blueprint(): array
    {
        return $this->schema()->blueprint();
    }

    /**
     * 返回当前资源的字段映射集合.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fieldMappings(): array
    {
        return $this->schema()->fieldMappings();
    }

    /**
     * 直接将当前 schema 同步到数据库结构.
     */
    public function sync(): Schema
    {
        $schema = new Schema($this->schema()->raw());
        if (!$schema->tableExists($this->schema()->raw()->getRawTable())) {
            $schema->create();
        }

        return $schema;
    }

    /**
     * 预览 publish 对应的迁移计划.
     *
     * @param array<string, mixed>|int $schema
     */
    public function planPublish($schema)
    {
        if (\is_int($schema)) {
            return $this->publisher->planVersion($schema, $this->resourceName(), $this->module);
        }

        return $this->publisher->plan($this->resourceName($schema), $schema, $this->module);
    }

    /**
     * 按草稿版本 ID 预览发布计划。
     */
    public function planVersion(int $versionId)
    {
        return $this->publisher->planVersion($versionId, $this->resourceName(), $this->module);
    }

    /**
     * 保存 schema 草稿.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function saveDraft(array $schema, array $options = []): array
    {
        return $this->publisher->saveDraft($this->resourceName($schema), $schema, $this->module, $options);
    }

    /**
     * 返回当前已发布版本.
     *
     * @return array<string, mixed>|null
     */
    public function currentVersion(): ?array
    {
        return $this->publisher->currentVersion($this->resourceName(), $this->module);
    }

    /**
     * 返回最新草稿版本.
     *
     * @return array<string, mixed>|null
     */
    public function latestDraftVersion(): ?array
    {
        return $this->publisher->latestDraft($this->resourceName(), $this->module);
    }

    /**
     * 返回指定版本详情。
     *
     * @return array<string, mixed>|null
     */
    public function version(int $versionId): ?array
    {
        return $this->publisher->version($versionId, $this->resourceName(), $this->module);
    }

    /**
     * 返回版本详情页结构。
     *
     * @return array<string, mixed>|null
     */
    public function versionDetail(int $versionId): ?array
    {
        return $this->publisher->versionDetail($versionId, $this->resourceName(), $this->module);
    }

    /**
     * 返回版本历史.
     *
     * @return array<int, array<string, mixed>>
     */
    public function versionHistory(int $limit = 20, array $filters = []): array
    {
        return $this->publisher->history($this->resourceName(), $this->module, $limit, $filters);
    }

    /**
     * 返回草稿列表。
     *
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function draftHistory(int $limit = 20, array $filters = []): array
    {
        return $this->publisher->drafts($this->resourceName(), $this->module, $limit, $filters);
    }

    /**
     * 返回后台版本管理页面板数据。
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function versionPanel(int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        return $this->publisher->versionPanel($this->resourceName(), $this->module, $page, $pageSize, $filters);
    }

    /**
     * 对比两个版本的结构差异。
     *
     * @return array<string, mixed>
     */
    public function diffVersions(int $fromVersionId, ?int $toVersionId = null): array
    {
        return $this->publisher->diffVersions($fromVersionId, $toVersionId, $this->resourceName(), $this->module);
    }

    /**
     * 按版本 ID 更新草稿。
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function updateDraft(int $versionId, array $schema, array $options = []): array
    {
        return $this->publisher->updateDraft($versionId, $schema, $this->resourceName(), $this->module, $options);
    }

    /**
     * 删除指定草稿版本。
     */
    public function deleteDraft(int $versionId): bool
    {
        return $this->publisher->deleteDraft($versionId, $this->resourceName(), $this->module);
    }

    /**
     * 删除指定历史版本。
     */
    public function deleteVersion(int $versionId): bool
    {
        return $this->publisher->deleteVersion($versionId, $this->resourceName(), $this->module);
    }

    /**
     * 发布新的 schema 版本.
     *
     * @param array<string, mixed>|int $schema
     * @param array<string, mixed> $options
     */
    public function publish($schema, array $options = []): PublishResult
    {
        if (\is_int($schema)) {
            return $this->publisher->publishVersion($schema, $this->resourceName(), $this->module, $options);
        }

        return $this->publisher->publish($this->resourceName($schema), $schema, $this->module, $options);
    }

    /**
     * 按草稿版本 ID 发布。
     */
    public function publishVersion(int $versionId, array $options = []): PublishResult
    {
        return $this->publisher->publishVersion($versionId, $this->resourceName(), $this->module, $options);
    }

    /**
     * 回滚到指定版本.
     */
    public function rollbackTo(int $versionId, array $options = []): RollbackResult
    {
        return $this->publisher->rollbackVersion($versionId, $this->resourceName(), $this->module, $options);
    }

    /**
     * 将链式声明的关系加载配置合并到查询参数中。
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function mergeRelationQuery(array $query): array
    {
        if (0 === \count($this->with)) {
            return $query;
        }

        $existing = $this->normalizeWith($query['with'] ?? []);
        $query['with'] = array_values(array_unique(array_merge($existing, $this->with)));

        return $query;
    }

    /**
     * 将链式关系加载信息写入上下文，供 detail 等无查询参数接口复用。
     */
    private function contextWithRelations(?ExecutionContext $context = null): ExecutionContext
    {
        $context = $context ?? new ExecutionContext();
        if (0 === \count($this->with)) {
            return $context;
        }

        $existing = $this->normalizeWith($context->get('runtime.with', []));
        $context->set('runtime.with', array_values(array_unique(array_merge($existing, $this->with))));

        return $context;
    }

    /**
     * @param mixed $relations
     *
     * @return string[]
     */
    private function normalizeWith($relations): array
    {
        $items = \is_array($relations) ? $relations : [$relations];

        return array_values(array_filter(array_map(static function ($item): ?string {
            if (!\is_string($item) || '' === trim($item)) {
                return null;
            }

            return trim($item);
        }, $items)));
    }

    /**
     * 返回底层 Document 对象，便于兼容旧能力.
     */
    public function document()
    {
        return $this->schema()->document();
    }

    /**
     * 返回底层原始资源定义对象.
     */
    public function raw()
    {
        return $this->schema()->raw();
    }

    /**
     * 为当前资源注册 hook.
     */
    public function on(string $event, callable $listener): self
    {
        $this->runtime->hooks()->on("resource.{$this->resourceName()}.{$event}", $listener);

        return $this;
    }

    /**
     * 为当前资源注册数据范围限制.
     */
    public function scope(string $operation, callable $scope): self
    {
        $this->runtime->scopes()->onResource($this->resourceName(), $operation, $scope);

        return $this;
    }

    /**
     * 兼容透传到 definition/raw 资源定义上的旧接口调用.
     */
    public function __call($name, $arguments)
    {
        $definition = $this->schema();
        if (method_exists($definition, $name)) {
            return \call_user_func_array([$definition, $name], $arguments);
        }
        $raw = $definition->raw();
        if (method_exists($raw, $name)) {
            return \call_user_func_array([$raw, $name], $arguments);
        }

        throw new \BadMethodCallException("Method [{$name}] does not exist.");
    }

    /**
     * 从输入 schema 或资源标识中推断资源名称.
     *
     * @param array<string, mixed> $schema
     */
    private function resourceName(array $schema = []): string
    {
        if (isset($schema['name']) && '' !== (string) $schema['name']) {
            return (string) $schema['name'];
        }
        if (\is_array($this->resource)) {
            return (string) ($this->resource['name'] ?? '');
        }

        return (string) $this->resource;
    }
}
