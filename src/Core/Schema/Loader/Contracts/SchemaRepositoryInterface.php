<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Loader\Contracts;

interface SchemaRepositoryInterface
{
    public function find(string $resource, string $module = ''): ?array;
}
