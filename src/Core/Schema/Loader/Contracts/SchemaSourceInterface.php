<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Loader\Contracts;

use PTAdmin\Easy\Contracts\IResource;

interface SchemaSourceInterface
{
    /**
     * @param array|string|IResource $resource
     */
    public function load($resource, string $module = ''): ?IResource;
}
