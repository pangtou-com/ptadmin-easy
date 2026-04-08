<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Chart\Definition;

/**
 * 编译后的图表定义对象.
 *
 * 统一承载 schema 中的单个 charts 配置，避免图表运行时
 * 直接处理松散数组结构。
 */
final class ChartDefinition
{
    /** @var string */
    private $name;

    /** @var string */
    private $title;

    /** @var string */
    private $type;

    /** @var int */
    private $index;

    /** @var string|null */
    private $description;

    /** @var array<int, array<string, mixed>> */
    private $dimensions;

    /** @var array<int, array<string, mixed>> */
    private $metrics;

    /** @var array<string, mixed> */
    private $query;

    /** @var array<string, mixed> */
    private $raw;

    /**
     * @param array<int, array<string, mixed>> $dimensions
     * @param array<int, array<string, mixed>> $metrics
     * @param array<string, mixed> $query
     * @param array<string, mixed> $raw
     */
    public function __construct(
        string $name,
        string $title,
        string $type,
        int $index,
        array $query = [],
        array $raw = [],
        ?string $description = null,
        array $dimensions = [],
        array $metrics = []
    )
    {
        $this->name = $name;
        $this->title = $title;
        $this->type = $type;
        $this->index = $index;
        $this->description = $description;
        $this->dimensions = $dimensions;
        $this->metrics = $metrics;
        $this->query = $query;
        $this->raw = $raw;
    }

    /**
     * 返回图表标识.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * 返回图表标题.
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * 返回图表类型.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * 返回图表在 schema 中的顺序索引.
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * 返回图表描述信息.
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * 返回图表维度定义.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dimensions(): array
    {
        return $this->dimensions;
    }

    /**
     * 返回图表指标定义.
     *
     * @return array<int, array<string, mixed>>
     */
    public function metrics(): array
    {
        return $this->metrics;
    }

    /**
     * 返回编译后的聚合查询 DSL.
     *
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * 返回原始图表定义.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * 导出为数组结构，便于对外兼容。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->raw, [
            'name' => $this->name,
            'title' => $this->title,
            'type' => $this->type,
            'index' => $this->index,
            'description' => $this->description,
            'dimensions' => $this->dimensions,
            'metrics' => $this->metrics,
            'query' => $this->query,
        ]);
    }
}
