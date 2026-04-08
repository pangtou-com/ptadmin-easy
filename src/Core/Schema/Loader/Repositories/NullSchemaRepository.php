<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Loader\Repositories;

use PTAdmin\Easy\Core\Schema\Loader\Contracts\SchemaRepositoryInterface;

class NullSchemaRepository implements SchemaRepositoryInterface
{
    public function find(string $resource, string $module = ''): ?array
    {
        return null;
    }
}
