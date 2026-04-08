<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Action\Contracts;

use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;

interface ActionInterface
{
    public function operation(): string;

    public function execute(SchemaDefinition $definition, array $payload = [], ?ExecutionContext $context = null);
}
