<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Action\Builtin;

use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Core\Action\Contracts\ActionInterface;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Core\Scope\ScopeManager;
use PTAdmin\Easy\Engine\Model\FormDTO;
use PTAdmin\Easy\Engine\Model\Validate;

/**
 * 更新操作执行器.
 */
class UpdateAction implements ActionInterface
{
    public function operation(): string
    {
        return 'update';
    }

    /**
     * 在事务中执行单条记录更新.
     */
    public function execute(SchemaDefinition $definition, array $payload = [], ?ExecutionContext $context = null)
    {
        return DB::transaction(function () use ($definition, $payload, $context) {
            $id = $payload['id'] ?? null;
            $data = $payload['data'] ?? [];
            $document = $definition->document();
            $builder = $document->query();
            $this->applyScope($builder, $definition, $context);
            $model = $builder->where($definition->primaryKey(), $id)->first();
            if (null === $model) {
                return null;
            }

            $dto = FormDTO::make($data, $model);
            (new Validate($dto, $document, $model))->validate();

            if (false === $document->trigger('before_updating', [$model, $dto])) {
                return null;
            }

            $updated = $document->updateRecord($model, $dto->getData(), function ($query) use ($definition, $context): void {
                $this->applyScope($query, $definition, $context);
            });
            if (null === $updated) {
                return null;
            }

            $document->trigger('after_updating', [$updated, $dto]);

            return $updated;
        });
    }

    /**
     * 将 update 操作对应的 scope 应用到查询构建器.
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

        $scopeManager->apply($builder, $definition, 'update', $context);
    }
}
