<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Loader\Sources;

use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Schema\Loader\Contracts\SchemaRepositoryInterface;
use PTAdmin\Easy\Core\Schema\Loader\Contracts\SchemaSourceInterface;

class RepositorySchemaSource implements SchemaSourceInterface
{
    /** @var SchemaRepositoryInterface */
    private $repository;

    public function __construct(SchemaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function load($resource, string $module = ''): ?IResource
    {
        if (!\is_string($resource) || '' === $resource) {
            return null;
        }

        $metadata = $this->repository->find($resource, $module);
        if (null === $metadata) {
            return null;
        }

        return app(IResource::class, ['resource' => $metadata, 'module' => $module]);
    }
}
