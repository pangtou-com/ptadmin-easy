<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Handle;

use PTAdmin\Easy\Core\Chart\ChartCompiler;
use PTAdmin\Easy\Core\Chart\ChartResultFormatter;
use PTAdmin\Easy\Core\Chart\Definition\ChartDefinition;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;

/**
 * 图表句柄.
 *
 * 当前作为统计模块的轻量前置层，负责：
 * 1. 读取 schema 中的 charts 定义
 * 2. 将图表定义翻译为聚合查询 DSL
 * 3. 复用 ResourceHandle::aggregate() 执行统计
 */
class ChartHandle
{
    /** @var ResourceHandle */
    private $resource;

    /** @var ChartCompiler */
    private $compiler;

    /** @var ChartResultFormatter */
    private $formatter;

    /** @var ChartDefinition[]|null */
    private $definitions;

    public function __construct(
        ResourceHandle $resource,
        ?ChartCompiler $compiler = null,
        ?ChartResultFormatter $formatter = null
    )
    {
        $this->resource = $resource;
        $this->compiler = $compiler ?? new ChartCompiler();
        $this->formatter = $formatter ?? new ChartResultFormatter();
    }

    /**
     * 返回当前资源配置的全部图表定义.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return array_map(static function (ChartDefinition $definition): array {
            return $definition->toArray();
        }, $this->compiledDefinitions());
    }

    /**
     * 读取指定图表定义.
     *
     * @param int|string $chart
     *
     * @return array<string, mixed>|null
     */
    public function definition($chart): ?array
    {
        foreach ($this->compiledDefinitions() as $definition) {
            if (\is_int($chart) && $definition->index() === $chart) {
                return $definition->toArray();
            }
            if (\is_string($chart) && $definition->name() === $chart) {
                return $definition->toArray();
            }
        }

        return null;
    }

    /**
     * 执行指定图表定义.
     *
     * @param array<string, mixed> $query
     * @param int|string           $chart
     *
     * @return array<int, array<string, mixed>>
     */
    public function run($chart, array $query = [], ?ExecutionContext $context = null): array
    {
        $definition = \is_array($chart) ? $this->compiler->compile($chart, 0) : $this->compiledDefinition($chart);
        if (null === $definition) {
            throw new \InvalidArgumentException('Chart definition not found.');
        }

        return $this->resource->aggregate(array_replace_recursive($definition->query(), $query), $context);
    }

    /**
     * 执行图表并返回前端友好的数据集结构.
     *
     * @param array<string, mixed> $query
     * @param int|string           $chart
     *
     * @return array<string, mixed>
     */
    public function dataset($chart, array $query = [], ?ExecutionContext $context = null): array
    {
        $definition = \is_array($chart) ? $this->compiler->compile($chart, 0) : $this->compiledDefinition($chart);
        if (null === $definition) {
            throw new \InvalidArgumentException('Chart definition not found.');
        }

        $rows = $this->resource->aggregate(array_replace_recursive($definition->query(), $query), $context);

        return $this->formatter->format($definition, $rows);
    }

    /**
     * 返回当前资源的全部编译后图表定义.
     *
     * @return ChartDefinition[]
     */
    private function compiledDefinitions(): array
    {
        if (null !== $this->definitions) {
            return $this->definitions;
        }

        $this->definitions = $this->compiler->compileMany((array) data_get($this->resource->raw()->toArray(), 'charts', []));

        return $this->definitions;
    }

    /**
     * 读取单个编译后图表定义.
     *
     * @param int|string $chart
     *
     * @return ChartDefinition|null
     */
    private function compiledDefinition($chart): ?ChartDefinition
    {
        foreach ($this->compiledDefinitions() as $definition) {
            if (\is_int($chart) && $definition->index() === $chart) {
                return $definition;
            }
            if (\is_string($chart) && $definition->name() === $chart) {
                return $definition;
            }
        }

        return null;
    }
}
