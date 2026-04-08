<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Engine\Resource;

use PTAdmin\Easy\Engine\Resource\Traits\BaseResourceTrait;

/**
 * 资源名称解析器.
 */
class ResourceNameParser
{
    use BaseResourceTrait;

    public static function handle(string $resource, $namespace = ''): self
    {
        $instance = new self();
        $instance->parser($resource, $namespace);

        return $instance;
    }
}
