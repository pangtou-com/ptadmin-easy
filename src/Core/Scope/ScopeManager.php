<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Scope;

use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;

/**
 * 数据范围管理器.
 *
 * 通过全局或资源级 scope 回调对查询构建器施加限制。
 */
class ScopeManager
{
    /** @var array<string, array<int, callable>> */
    private $global = [];

    /** @var array<string, array<string, array<int, callable>>> */
    private $resources = [];

    /**
     * 注册全局 scope.
     */
    public function on(string $operation, callable $scope): void
    {
        $this->global[$operation][] = $scope;
    }

    /**
     * 注册资源级 scope.
     */
    public function onResource(string $resource, string $operation, callable $scope): void
    {
        $this->resources[$resource][$operation][] = $scope;
    }

    /**
     * 将 scope 应用到查询构建器.
     *
     * @param mixed $builder
     */
    public function apply($builder, SchemaDefinition $definition, string $operation, ?ExecutionContext $context = null): void
    {
        $context = $context ?? new ExecutionContext();
        foreach ($this->listeners($definition->name(), $operation) as $listener) {
            $listener($builder, $definition, $context, $operation);
        }
    }

    /**
     * 返回当前操作对应的全部监听器.
     *
     * @return array<int, callable>
     */
    private function listeners(string $resource, string $operation): array
    {
        return array_merge(
            $this->global['*'] ?? [],
            $this->global[$operation] ?? [],
            $this->resources[$resource]['*'] ?? [],
            $this->resources[$resource][$operation] ?? []
        );
    }
}
