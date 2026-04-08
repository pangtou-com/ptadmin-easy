<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Loader\Sources;

use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Schema\Loader\Contracts\SchemaSourceInterface;

class ArraySchemaSource implements SchemaSourceInterface
{
    public function load($resource, string $module = ''): ?IResource
    {
        if (!\is_array($resource)) {
            return null;
        }

        return app(IResource::class, ['resource' => $resource, 'module' => $module]);
    }
}
