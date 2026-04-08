<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Handle;

use PTAdmin\Easy\Core\Runtime\ExecutionContext;

/**
 * 资源运行时句柄.
 *
 * 只负责对外暴露数据读写、查询、hook 和 scope 等运行时能力。
 */
final class DocHandle
{
    /** @var ResourceHandle */
    private $handle;

    public function __construct(ResourceHandle $handle)
    {
        $this->handle = $handle;
    }

    /**
     * 创建一条资源数据.
     */
    public function create(array $data, ?ExecutionContext $context = null)
    {
        return $this->handle->create($data, $context);
    }

    /**
     * 更新一条资源数据.
     */
    public function update($id, array $data, ?ExecutionContext $context = null)
    {
        return $this->handle->update($id, $data, $context);
    }

    /**
     * 删除一条资源数据.
     */
    public function delete($id, ?ExecutionContext $context = null)
    {
        return $this->handle->delete($id, $context);
    }

    /**
     * 查询资源详情.
     */
    public function detail($id, ?ExecutionContext $context = null)
    {
        return $this->handle->detail($id, $context);
    }

    /**
     * 查询资源列表.
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>|array<int, mixed>
     */
    public function lists(array $query = [], ?ExecutionContext $context = null): array
    {
        return $this->handle->lists($query, $context);
    }

    /**
     * 执行聚合查询.
     *
     * @param array<string, mixed> $query
     *
     * @return array<int, array<string, mixed>>
     */
    public function aggregate(array $query = [], ?ExecutionContext $context = null): array
    {
        return $this->handle->aggregate($query, $context);
    }

    /**
     * 返回图表句柄.
     */
    public function charts(): ChartHandle
    {
        return $this->handle->charts();
    }

    /**
     * 返回底层 Document 对象.
     */
    public function document()
    {
        return $this->handle->document();
    }

    /**
     * 返回底层原始资源定义对象.
     */
    public function raw()
    {
        return $this->handle->raw();
    }

    /**
     * 注册资源 hook.
     */
    public function on(string $event, callable $listener): self
    {
        $this->handle->on($event, $listener);

        return $this;
    }

    /**
     * 注册资源 scope.
     */
    public function scope(string $operation, callable $scope): self
    {
        $this->handle->scope($operation, $scope);

        return $this;
    }

    /**
     * 声明当前调用需要预加载的关系。
     *
     * @param string|string[] $relations
     */
    public function with($relations): self
    {
        return new self($this->handle->with($relations));
    }
}
