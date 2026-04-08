<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Loader\Sources;

use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Schema\Loader\Contracts\SchemaSourceInterface;

/**
 * 已构建资源定义实例的 schema source.
 */
class ResourceInstanceSchemaSource implements SchemaSourceInterface
{
    public function load($resource, string $module = ''): ?IResource
    {
        return $resource instanceof IResource ? $resource : null;
    }
}
