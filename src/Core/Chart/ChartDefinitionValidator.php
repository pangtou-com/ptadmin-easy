<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Chart;

/**
 * 图表定义校验器.
 *
 * 负责在 schema 编译阶段尽早拦截非法的 charts 配置，
 * 避免运行到统计查询时才暴露错误。
 */
final class ChartDefinitionValidator
{
    private const AGGREGATE_TYPES = ['count', 'sum', 'avg', 'min', 'max'];

    /**
     * 校验编译后的图表配置.
     *
     * @param array<string, mixed>               $definition
     * @param array<int, array<string, mixed>>   $dimensions
     * @param array<int, array<string, mixed>>   $metrics
     * @param array<string, mixed>               $query
     */
    public function validate(array $definition, array $dimensions, array $metrics, array $query): void
    {
        foreach ($dimensions as $index => $dimension) {
            $field = $dimension['field'] ?? null;
            if (!\is_string($field) || '' === trim($field)) {
                throw new \InvalidArgumentException('Chart dimension field is required at index ['.$index.'].');
            }
        }

        foreach ($metrics as $index => $metric) {
            $type = $metric['type'] ?? null;
            if (!\is_string($type) || !\in_array($type, self::AGGREGATE_TYPES, true)) {
                throw new \InvalidArgumentException('Unsupported chart aggregate type ['.(string) $type.'] at index ['.$index.'].');
            }

            $field = $metric['field'] ?? null;
            if ('count' !== $type && (!\is_string($field) || '' === trim($field))) {
                throw new \InvalidArgumentException('Chart metric field is required for aggregate ['.$type.'] at index ['.$index.'].');
            }

            $alias = $metric['as'] ?? null;
            if (!\is_string($alias) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
                throw new \InvalidArgumentException('Chart metric alias is invalid at index ['.$index.'].');
            }
        }

        $groups = array_values(array_filter((array) ($query['groups'] ?? []), 'is_string'));
        foreach ($groups as $group) {
            if ('' === trim($group)) {
                throw new \InvalidArgumentException('Chart group field cannot be empty.');
            }
        }

        $chartName = $definition['name'] ?? ($definition['title'] ?? 'chart');
        if (!\is_string($chartName) || '' === trim($chartName)) {
            throw new \InvalidArgumentException('Chart name cannot be empty.');
        }
    }
}
