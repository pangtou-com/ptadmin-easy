<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Permission\Contracts;

use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;

interface PermissionCheckerInterface
{
    /**
     * 校验当前上下文是否允许执行指定操作.
     *
     * @param array<string, mixed> $payload
     */
    public function authorize(string $operation, SchemaDefinition $definition, array $payload = [], ?ExecutionContext $context = null): void;
}
