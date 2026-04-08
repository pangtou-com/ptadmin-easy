<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Loader;

use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Schema\Loader\Contracts\SchemaRepositoryInterface;
use PTAdmin\Easy\Core\Schema\Loader\Contracts\SchemaSourceInterface;
use PTAdmin\Easy\Core\Schema\Loader\Repositories\DatabaseSchemaRepository;
use PTAdmin\Easy\Core\Schema\Loader\Repositories\NullSchemaRepository;
use PTAdmin\Easy\Core\Schema\Loader\Sources\ArraySchemaSource;
use PTAdmin\Easy\Core\Schema\Loader\Sources\LegacyFileSchemaSource;
use PTAdmin\Easy\Core\Schema\Loader\Sources\RepositorySchemaSource;
use PTAdmin\Easy\Core\Schema\Loader\Sources\ResourceInstanceSchemaSource;
use PTAdmin\Easy\Exceptions\EasyException;

/**
 * Schema 加载器.
 *
 * 内部通过 source 链实现多来源读取，默认顺序为：
 * 1. 已有资源定义实例
 * 2. 直接传入的数组 schema
 * 3. 数据库 schema 仓库
 * 4. legacy json 文件
 */
class SchemaLoader
{
    /** @var SchemaSourceInterface[] */
    private $sources;

    public function __construct(array $sources = [], ?SchemaRepositoryInterface $repository = null)
    {
        $repository = $repository ?? $this->repositoryFromConfig();
        $this->sources = 0 === \count($sources) ? [
            new ResourceInstanceSchemaSource(),
            new ArraySchemaSource(),
            new RepositorySchemaSource($repository),
            new LegacyFileSchemaSource(),
        ] : $sources;
    }

    /**
     * @param array|string|IResource $resource
     */
    public function load($resource, string $module = ''): IResource
    {
        foreach ($this->sources as $source) {
            $resolvedResource = $source->load($resource, $module);
            if ($resolvedResource instanceof IResource) {
                return $resolvedResource;
            }
        }

        $name = \is_string($resource) ? $resource : 'array';

        throw new EasyException("Schema resource [{$name}] not found.");
    }

    /**
     * 从配置中创建 schema 仓库.
     */
    private function repositoryFromConfig(): SchemaRepositoryInterface
    {
        $repository = config('easy.schema.repository');
        if (\is_string($repository) && class_exists($repository)) {
            $instance = app($repository);
            if ($instance instanceof SchemaRepositoryInterface) {
                return $instance;
            }
        }
        if (null === $repository) {
            return new DatabaseSchemaRepository();
        }

        return new NullSchemaRepository();
    }
}
