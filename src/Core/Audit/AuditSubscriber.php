<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Audit;

use PTAdmin\Easy\Core\Audit\Contracts\AuditStoreInterface;
use PTAdmin\Easy\Core\Audit\Stores\DatabaseAuditStore;
use PTAdmin\Easy\Core\Hook\HookManager;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Core\Scope\ScopeManager;

/**
 * 审计订阅器.
 *
 * 通过订阅运行时 hook 自动记录 create/update/delete 审计日志。
 */
class AuditSubscriber
{
    /** @var AuditStoreInterface */
    private $store;

    public function __construct(?AuditStoreInterface $store = null)
    {
        $this->store = $store ?? $this->storeFromConfig();
    }

    /**
     * 将审计订阅器挂载到 HookManager.
     */
    public function subscribe(HookManager $hooks): void
    {
        $hooks->on('before.update', function (SchemaDefinition $definition, array $payload, ExecutionContext $context): void {
            $this->captureBefore($definition, $payload, $context, 'update');
        });
        $hooks->on('before.delete', function (SchemaDefinition $definition, array $payload, ExecutionContext $context): void {
            $this->captureBefore($definition, $payload, $context, 'delete');
        });
        $hooks->on('after.create', function (SchemaDefinition $definition, array $payload, $data, ExecutionContext $context): void {
            if (null === $data) {
                return;
            }

            $this->write($definition, 'create', $payload, null, $data);
        });
        $hooks->on('after.update', function (SchemaDefinition $definition, array $payload, $data, ExecutionContext $context): void {
            if (null === $data) {
                return;
            }

            $before = $context->get('audit.before');
            $this->write($definition, 'update', $payload, $before, $data);
        });
        $hooks->on('after.delete', function (SchemaDefinition $definition, array $payload, $data, ExecutionContext $context): void {
            if (true !== (bool) $data) {
                return;
            }

            $before = $context->get('audit.before');
            $this->write($definition, 'delete', $payload, $before, null);
        });
    }

    /**
     * 捕获操作前快照.
     */
    private function captureBefore(SchemaDefinition $definition, array $payload, ExecutionContext $context, string $operation): void
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $builder = $definition->document()->query();
        $this->applyScope($builder, $definition, $context, $operation);
        $model = $builder->where($definition->primaryKey(), $id)->first();
        if (null === $model) {
            return;
        }

        $context->set('audit.before', $this->normalizeData($model));
    }

    /**
     * 写入审计日志.
     *
     * @param mixed $after
     * @param mixed $before
     */
    private function write(SchemaDefinition $definition, string $operation, array $payload, $before, $after): void
    {
        $beforeData = $this->normalizeData($before);
        $afterData = $this->normalizeData($after);

        $this->store->write([
            'resource' => $definition->name(),
            'module' => (string) ($definition->toArray()['module'] ?? 'App'),
            'schema_version_id' => (int) ($definition->toArray()['current_version_id'] ?? 0),
            'operation' => $operation,
            'record_id' => $this->resolveRecordId($payload, $afterData, $beforeData),
            'payload' => $payload,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'diff_data' => $this->diff($beforeData, $afterData),
        ]);
    }

    /**
     * 标准化模型/数组为数组结构.
     *
     * @param mixed $data
     */
    private function normalizeData($data): ?array
    {
        if (null === $data || false === $data) {
            return null;
        }
        if (\is_array($data)) {
            return $data;
        }
        if (\is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        return ['value' => $data];
    }

    /**
     * 基于前后数据生成差异.
     */
    private function diff(?array $before, ?array $after): ?array
    {
        if (null === $before && null === $after) {
            return null;
        }

        $before = $before ?? [];
        $after = $after ?? [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $diff = [];
        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;
            if ($beforeValue === $afterValue) {
                continue;
            }
            $diff[$key] = [
                'before' => $beforeValue,
                'after' => $afterValue,
            ];
        }

        return $diff;
    }

    /**
     * 推断记录 ID.
     */
    private function resolveRecordId(array $payload, ?array $after, ?array $before): int
    {
        if (isset($payload['id'])) {
            return (int) $payload['id'];
        }
        if (isset($after['id'])) {
            return (int) $after['id'];
        }
        if (isset($before['id'])) {
            return (int) $before['id'];
        }

        return 0;
    }

    /**
     * 将当前操作的数据范围规则应用到审计前快照查询.
     *
     * @param mixed $builder
     */
    private function applyScope($builder, SchemaDefinition $definition, ExecutionContext $context, string $operation): void
    {
        $scopeManager = $context->get('scope.manager');
        if (!$scopeManager instanceof ScopeManager) {
            return;
        }

        $scopeManager->apply($builder, $definition, $operation, $context);
    }

    /**
     * 从配置构造审计存储.
     */
    private function storeFromConfig(): AuditStoreInterface
    {
        $store = config('easy.audit.store');
        if (\is_string($store) && class_exists($store)) {
            $instance = app($store);
            if ($instance instanceof AuditStoreInterface) {
                return $instance;
            }
        }

        return new DatabaseAuditStore();
    }
}
