<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Action\Builtin;

use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Core\Action\Contracts\ActionInterface;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Core\Scope\ScopeManager;

/**
 * 删除操作执行器.
 */
class DeleteAction implements ActionInterface
{
    public function operation(): string
    {
        return 'delete';
    }

    /**
     * 在事务中删除单条记录.
     */
    public function execute(SchemaDefinition $definition, array $payload = [], ?ExecutionContext $context = null)
    {
        return DB::transaction(function () use ($definition, $payload, $context) {
            $id = $payload['id'] ?? null;
            $document = $definition->document();
            $builder = $document->query();
            $this->applyScope($builder, $definition, $context);
            $model = $builder->where($definition->primaryKey(), $id)->first();
            if (null === $model) {
                return false;
            }

            if (false === $document->trigger('before_deleting', [$model])) {
                return false;
            }

            $result = $document->deleteRecord($model, function ($query) use ($definition, $context): void {
                $this->applyScope($query, $definition, $context);
            });
            $document->trigger('after_deleting', [$model, $result]);

            return $result;
        });
    }

    /**
     * 将 delete 操作对应的 scope 应用到查询构建器.
     *
     * @param mixed $builder
     */
    private function applyScope($builder, SchemaDefinition $definition, ?ExecutionContext $context = null): void
    {
        if (null === $context) {
            return;
        }

        $scopeManager = $context->get('scope.manager');
        if (!$scopeManager instanceof ScopeManager) {
            return;
        }

        $scopeManager->apply($builder, $definition, 'delete', $context);
    }
}
