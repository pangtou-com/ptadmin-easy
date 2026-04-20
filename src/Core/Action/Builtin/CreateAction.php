<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Action\Builtin;

use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Core\Action\Contracts\ActionInterface;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;

/**
 * 新增操作执行器.
 */
class CreateAction implements ActionInterface
{
    public function operation(): string
    {
        return 'create';
    }

    /**
     * 在事务中创建一条新数据.
     */
    public function execute(SchemaDefinition $definition, array $payload = [], ?ExecutionContext $context = null)
    {
        return DB::transaction(function () use ($definition, $payload, $context) {
            return $definition->document()->useContext($context)->store($payload);
        });
    }
}
