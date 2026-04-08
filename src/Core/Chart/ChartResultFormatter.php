<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Chart;

use PTAdmin\Easy\Core\Chart\Definition\ChartDefinition;

/**
 * 图表结果格式化器.
 *
 * 负责把底层聚合查询结果转换为前端更容易消费的
 * categories/series/summary 结构。
 */
final class ChartResultFormatter
{
    /**
     * 格式化图表执行结果.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    public function format(ChartDefinition $definition, array $rows): array
    {
        $dimensions = $definition->dimensions();
        $metrics = $definition->metrics();
        $categories = $this->categories($rows, $dimensions);
        $series = $this->series($rows, $metrics);

        return [
            'definition' => $definition->toArray(),
            'rows' => $rows,
            'categories' => $categories,
            'series' => $series,
            'summary' => $this->summary($rows, $metrics),
        ];
    }

    /**
     * 构建分类轴信息.
     *
     * @param array<int, array<string, mixed>>             $rows
     * @param array<int, array<string, mixed>>             $dimensions
     *
     * @return array<int, array<string, mixed>>
     */
    private function categories(array $rows, array $dimensions): array
    {
        if (0 === \count($dimensions)) {
            return [];
        }

        $categories = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($dimensions as $dimension) {
                $field = (string) $dimension['field'];
                $values[$field] = $row[$field] ?? null;
            }

            $labels = array_map(static function ($value): string {
                if (null === $value) {
                    return '';
                }
                if (\is_scalar($value)) {
                    return (string) $value;
                }

                return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
            }, array_values($values));

            $categories[] = [
                'key' => implode('::', $labels),
                'label' => implode(' / ', array_filter($labels, static function (string $label): bool {
                    return '' !== $label;
                })),
                'value' => 1 === \count($values) ? reset($values) : $values,
                'values' => $values,
            ];
        }

        return $categories;
    }

    /**
     * 构建图表序列信息.
     *
     * @param array<int, array<string, mixed>>             $rows
     * @param array<int, array<string, mixed>>             $metrics
     *
     * @return array<int, array<string, mixed>>
     */
    private function series(array $rows, array $metrics): array
    {
        $series = [];
        foreach ($metrics as $metric) {
            $alias = (string) $metric['as'];
            $series[] = [
                'key' => $alias,
                'name' => $metric['label'] ?? $alias,
                'type' => $metric['type'] ?? 'count',
                'field' => $metric['field'] ?? null,
                'data' => array_map(static function (array $row) use ($alias) {
                    return $row[$alias] ?? null;
                }, $rows),
            ];
        }

        return $series;
    }

    /**
     * 提取摘要指标.
     *
     * 对于无分组场景，通常第一行就是总览结果。
     *
     * @param array<int, array<string, mixed>>             $rows
     * @param array<int, array<string, mixed>>             $metrics
     *
     * @return array<string, mixed>
     */
    private function summary(array $rows, array $metrics): array
    {
        $first = $rows[0] ?? [];
        $summary = [];
        foreach ($metrics as $metric) {
            $alias = (string) $metric['as'];
            $summary[$alias] = $first[$alias] ?? null;
        }

        return $summary;
    }
}
