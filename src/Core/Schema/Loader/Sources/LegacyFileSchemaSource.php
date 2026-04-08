<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Loader\Sources;

use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Schema\Loader\Contracts\SchemaSourceInterface;
use PTAdmin\Easy\Engine\Resource\ResourceNameParser;

class LegacyFileSchemaSource implements SchemaSourceInterface
{
    public function load($resource, string $module = ''): ?IResource
    {
        if (!\is_string($resource) || '' === $resource) {
            return null;
        }

        $parser = ResourceNameParser::handle($resource, $module);
        $path = $parser->getResourceJsonPath();
        if (!is_readable($path) || !is_file($path)) {
            return null;
        }

        return app(IResource::class, ['resource' => $resource, 'module' => $module]);
    }
}
