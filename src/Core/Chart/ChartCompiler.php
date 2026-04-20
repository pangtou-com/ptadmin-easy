<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Chart;

use Illuminate\Support\Str;
use PTAdmin\Easy\Core\Chart\Definition\ChartDefinition;

/**
 * 图表定义编译器.
 *
 * 负责把 schema 中松散的 charts 配置收口为统一的
 * ChartDefinition，并翻译为聚合查询 DSL。
 */
class ChartCompiler
{
    /** @var ChartDefinitionValidator */
    private $validator;

    public function __construct(?ChartDefinitionValidator $validator = null)
    {
        $this->validator = $validator ?? new ChartDefinitionValidator();
    }

    /**
     * @param array<int, mixed> $charts
     *
     * @return ChartDefinition[]
     */
    public function compileMany(array $charts): array
    {
        $definitions = [];
        $nameCounters = [];

        foreach ($charts as $index => $chart) {
            if (!\is_array($chart)) {
                continue;
            }

            $definition = $this->compile($chart, (int) $index);
            $name = $this->uniqueName($definition->name(), $nameCounters);
            if ($name !== $definition->name()) {
                $definition = new ChartDefinition(
                    $name,
                    $definition->title(),
                    $definition->type(),
                    $definition->index(),
                    $definition->query(),
                    $definition->raw(),
                    $definition->description(),
                    $definition->dimensions(),
                    $definition->metrics()
                );
            }

            $definitions[] = $definition;
        }

        return $definitions;
    }

    /**
     * 编译单个图表定义.
     *
     * @param array<string, mixed> $definition
     */
    public function compile(array $definition, int $index = 0): ChartDefinition
    {
        $title = \is_string($definition['title'] ?? null) && '' !== $definition['title']
            ? (string) $definition['title']
            : 'chart_'.$index;

        $name = \is_string($definition['name'] ?? null) && '' !== $definition['name']
            ? (string) $definition['name']
            : Str::snake($title);

        $dimensions = $this->compileDimensions($definition);
        $metrics = $this->compileMetrics($definition);

        $type = \is_string($definition['type'] ?? null) && '' !== $definition['type']
            ? strtolower((string) $definition['type'])
            : (0 !== \count($dimensions) ? 'bar' : 'metric');

        $description = $this->compileDescription($definition);
        $query = $this->compileQuery($definition, $dimensions, $metrics);

        $this->validator->validate($definition, $dimensions, $metrics, $query);

        return new ChartDefinition(
            $name,
            $title,
            $type,
            $index,
            $query,
            $definition,
            $description,
            $dimensions,
            $metrics
        );
    }

    /**
     * 将图表定义翻译为聚合查询 DSL.
     *
     * @param array<string, mixed> $definition
     * @param array<int, array<string, mixed>> $dimensions
     * @param array<int, array<string, mixed>> $metrics
     *
     * @return array<string, mixed>
     */
    private function compileQuery(array $definition, array $dimensions, array $metrics): array
    {
        $query = (array) ($definition['query'] ?? []);

        foreach (['filters', 'sorts', 'sort', 'groups', 'aggregates', 'metrics', 'keyword', 'keyword_fields', 'limit', 'page', 'paginate'] as $key) {
            if (!isset($query[$key]) && isset($definition[$key])) {
                $query[$key] = $definition[$key];
            }
        }

        $filters = $this->normalizeFilters($query['filters'] ?? []);
        if (0 !== \count($filters)) {
            $query['filters'] = $filters;
        }

        $sorts = $this->normalizeSorts($query['sorts'] ?? ($query['sort'] ?? []));
        if (0 !== \count($sorts)) {
            $query['sorts'] = $sorts;
        }

        $groups = $this->normalizeGroups($query['groups'] ?? ($query['group_by'] ?? ($query['group'] ?? [])));
        if (0 === \count($groups)) {
            $groups = array_values(array_map(static function (array $dimension): string {
                return (string) $dimension['field'];
            }, $dimensions));
        }
        if (0 !== \count($groups)) {
            $query['groups'] = $groups;
        }

        $aggregates = $this->normalizeAggregates($query['aggregates'] ?? ($query['metrics'] ?? []));
        if (0 === \count($aggregates)) {
            $aggregates = array_values(array_map(static function (array $metric): array {
                return [
                    'type' => $metric['type'],
                    'field' => $metric['field'],
                    'as' => $metric['as'],
                ];
            }, $metrics));
        }
        if (0 !== \count($aggregates)) {
            $query['aggregates'] = $aggregates;
        }

        unset($query['sort'], $query['group'], $query['group_by'], $query['metrics']);

        return $query;
    }

    /**
     * 编译图表描述文本.
     *
     * @param array<string, mixed> $definition
     */
    private function compileDescription(array $definition): ?string
    {
        foreach (['description', 'intro'] as $key) {
            if (\is_string($definition[$key] ?? null) && '' !== trim((string) $definition[$key])) {
                return trim((string) $definition[$key]);
            }
        }

        return null;
    }

    /**
     * 编译图表维度信息.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<int, array<string, mixed>>
     */
    private function compileDimensions(array $definition): array
    {
        $source = $definition['dimensions']
            ?? $definition['groups']
            ?? $definition['group_by']
            ?? $definition['group']
            ?? $definition['dimension']
            ?? data_get($definition, 'query.groups')
            ?? data_get($definition, 'query.group_by')
            ?? data_get($definition, 'query.group')
            ?? []
        ;

        $dimensions = [];
        $usedFields = [];

        foreach ($this->normalizeItems($source) as $item) {
            if (\is_string($item) && '' !== trim($item)) {
                $field = trim($item);
                if (isset($usedFields[$field])) {
                    continue;
                }

                $usedFields[$field] = true;
                $dimensions[] = [
                    'field' => $field,
                    'label' => $field,
                ];

                continue;
            }

            if (!\is_array($item)) {
                continue;
            }

            $field = $item['field'] ?? ($item['name'] ?? null);
            if (!\is_string($field) || '' === trim($field)) {
                continue;
            }

            $field = trim($field);
            if (isset($usedFields[$field])) {
                continue;
            }

            $usedFields[$field] = true;
            $dimensions[] = [
                'field' => $field,
                'label' => \is_string($item['label'] ?? null) && '' !== trim((string) $item['label'])
                    ? trim((string) $item['label'])
                    : (\is_string($item['title'] ?? null) && '' !== trim((string) $item['title']) ? trim((string) $item['title']) : $field),
            ];
        }

        return $dimensions;
    }

    /**
     * 编译图表指标信息.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<int, array<string, mixed>>
     */
    private function compileMetrics(array $definition): array
    {
        $source = data_get($definition, 'query.aggregates')
            ?? data_get($definition, 'query.metrics')
            ?? $definition['metrics']
            ?? $definition['aggregates']
            ?? $definition['fields']
            ?? []
        ;

        $metrics = [];
        foreach ($this->normalizeItems($source) as $index => $item) {
            if (!\is_array($item) || !isset($item['type'])) {
                continue;
            }

            $type = strtolower((string) $item['type']);
            if ('' === $type) {
                continue;
            }

            $field = $item['field'] ?? ($item['name'] ?? null);
            $field = \is_string($field) && '' !== trim($field) ? trim($field) : null;

            $alias = $item['as'] ?? ($item['alias'] ?? ($item['key'] ?? null));
            $alias = \is_string($alias) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)
                ? $alias
                : $this->defaultAggregateAlias($type, $field)
            ;

            $label = \is_string($item['label'] ?? null) && '' !== trim((string) $item['label'])
                ? trim((string) $item['label'])
                : (\is_string($item['title'] ?? null) && '' !== trim((string) $item['title']) ? trim((string) $item['title']) : $alias);

            $metrics[] = [
                'type' => $type,
                'field' => $field,
                'as' => $alias,
                'label' => $label,
                'index' => (int) $index,
            ];
        }

        if (0 !== \count($metrics)) {
            return $metrics;
        }

        return [[
            'type' => 'count',
            'field' => null,
            'as' => 'total',
            'label' => 'total',
            'index' => 0,
        ]];
    }

    /**
     * 统一标准化过滤条件.
     *
     * @param mixed $input
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFilters($input): array
    {
        if (!\is_array($input)) {
            return [];
        }

        if (isset($input['field'])) {
            return [[
                'field' => (string) $input['field'],
                'operator' => (string) ($input['operator'] ?? '='),
                'value' => $input['value'] ?? null,
            ]];
        }

        $filters = [];
        foreach ($input as $key => $item) {
            if (\is_array($item) && isset($item['field'])) {
                $field = $item['field'];
                if (!\is_string($field) || '' === trim($field)) {
                    continue;
                }

                $filters[] = [
                    'field' => trim($field),
                    'operator' => (string) ($item['operator'] ?? '='),
                    'value' => $item['value'] ?? null,
                ];

                continue;
            }

            if (!\is_string($key) || '' === trim($key)) {
                continue;
            }

            $filters[] = [
                'field' => trim($key),
                'operator' => \is_array($item) ? 'in' : '=',
                'value' => $item,
            ];
        }

        return $filters;
    }

    /**
     * 统一标准化排序条件.
     *
     * @param mixed $input
     *
     * @return array<int, array<string, string>>
     */
    private function normalizeSorts($input): array
    {
        if (!\is_array($input)) {
            return [];
        }

        if (isset($input['field'])) {
            return [[
                'field' => (string) $input['field'],
                'direction' => $this->normalizeDirection($input['direction'] ?? 'asc'),
            ]];
        }

        $sorts = [];
        foreach ($input as $key => $item) {
            if (\is_array($item) && isset($item['field'])) {
                $field = $item['field'];
                if (!\is_string($field) || '' === trim($field)) {
                    continue;
                }

                $sorts[] = [
                    'field' => trim($field),
                    'direction' => $this->normalizeDirection($item['direction'] ?? 'asc'),
                ];

                continue;
            }

            if (!\is_string($key) || '' === trim($key)) {
                continue;
            }

            $sorts[] = [
                'field' => trim($key),
                'direction' => $this->normalizeDirection($item),
            ];
        }

        return $sorts;
    }

    /**
     * 统一标准化分组字段.
     *
     * @param mixed $input
     *
     * @return string[]
     */
    private function normalizeGroups($input): array
    {
        $groups = [];
        foreach ($this->normalizeItems($input) as $item) {
            if (\is_string($item) && '' !== trim($item)) {
                $groups[] = trim($item);

                continue;
            }

            if (\is_array($item)) {
                $field = $item['field'] ?? ($item['name'] ?? null);
                if (\is_string($field) && '' !== trim($field)) {
                    $groups[] = trim($field);
                }
            }
        }

        return array_values(array_unique($groups));
    }

    /**
     * 统一标准化聚合指标配置.
     *
     * @param mixed $input
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAggregates($input): array
    {
        $aggregates = [];
        foreach ($this->normalizeItems($input) as $item) {
            if (!\is_array($item) || !isset($item['type'])) {
                continue;
            }

            $type = strtolower((string) $item['type']);
            if ('' === $type) {
                continue;
            }

            $field = $item['field'] ?? ($item['name'] ?? null);
            $field = \is_string($field) && '' !== trim($field) ? trim($field) : null;

            $alias = $item['as'] ?? ($item['alias'] ?? ($item['key'] ?? null));
            $aggregates[] = [
                'type' => $type,
                'field' => $field,
                'as' => \is_string($alias) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)
                    ? $alias
                    : $this->defaultAggregateAlias($type, $field),
            ];
        }

        return $aggregates;
    }

    /**
     * 将输入统一为可遍历的列表结构.
     *
     * @param mixed $input
     *
     * @return array<int, mixed>
     */
    private function normalizeItems($input): array
    {
        if (!\is_array($input)) {
            if (\is_string($input) && '' !== trim($input)) {
                return [trim($input)];
            }

            return [];
        }

        if ($this->isSingleDefinitionItem($input)) {
            return [$input];
        }

        return array_values($input);
    }

    /**
     * 判断数组是否为单个定义项，而不是列表.
     *
     * @param array<mixed> $input
     */
    private function isSingleDefinitionItem(array $input): bool
    {
        return isset($input['field'])
            || isset($input['name'])
            || isset($input['type']);
    }

    /**
     * 返回默认聚合别名，确保 definitions 与运行时返回字段一致.
     */
    private function defaultAggregateAlias(string $type, ?string $field): string
    {
        if ('count' === $type && null === $field) {
            return 'total';
        }

        if (null === $field || '*' === $field) {
            return $type.'_all';
        }

        return $type.'_'.$field;
    }

    /**
     * 标准化排序方向.
     *
     * @param mixed $direction
     */
    private function normalizeDirection($direction): string
    {
        return 'desc' === strtolower((string) $direction) ? 'desc' : 'asc';
    }

    /**
     * 生成唯一图表名称，避免重复标题导致运行时歧义.
     *
     * @param array<string, int> $nameCounters
     */
    private function uniqueName(string $name, array &$nameCounters): string
    {
        if (!isset($nameCounters[$name])) {
            $nameCounters[$name] = 1;

            return $name;
        }

        $nameCounters[$name]++;

        return $name.'_'.$nameCounters[$name];
    }
}
