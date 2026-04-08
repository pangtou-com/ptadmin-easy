<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Tests\Unit\Chart;

use PHPUnit\Framework\TestCase;
use PTAdmin\Easy\Core\Chart\ChartCompiler;

/**
 * 图表编译器测试.
 *
 * 用于确认前端更自然的统计 schema 配置会被收口为
 * 稳定、可执行的图表定义结构。
 */
final class ChartCompilerTest extends TestCase
{
    public function test_it_normalizes_modern_chart_schema_conventions(): void
    {
        $compiler = new ChartCompiler();
        $definitions = $compiler->compileMany([
            [
                'title' => 'Status Overview',
                'dimension' => [
                    'field' => 'status',
                    'label' => '状态',
                ],
                'metrics' => [
                    [
                        'type' => 'count',
                        'label' => '总数',
                    ],
                    [
                        'type' => 'sum',
                        'field' => 'tenant_id',
                        'label' => '租户和值',
                    ],
                ],
                'filter' => [
                    'status' => 1,
                ],
                'order' => [
                    'status' => 'desc',
                ],
            ],
            [
                'title' => 'Status Overview',
                'query' => [
                    'keyword' => 'alpha',
                    'aggregates' => [
                        ['type' => 'count'],
                    ],
                ],
            ],
        ]);

        $first = $definitions[0]->toArray();
        $second = $definitions[1]->toArray();

        $this->assertSame('status_overview', $first['name']);
        $this->assertSame('bar', $first['type']);
        $this->assertSame([
            ['field' => 'status', 'label' => '状态'],
        ], $first['dimensions']);
        $this->assertSame([
            ['type' => 'count', 'field' => null, 'as' => 'total', 'label' => '总数', 'index' => 0],
            ['type' => 'sum', 'field' => 'tenant_id', 'as' => 'sum_tenant_id', 'label' => '租户和值', 'index' => 1],
        ], $first['metrics']);
        $this->assertSame([
            ['field' => 'status', 'operator' => '=', 'value' => 1],
        ], $first['query']['filters']);
        $this->assertSame([
            ['field' => 'status', 'direction' => 'desc'],
        ], $first['query']['sorts']);
        $this->assertSame(['status'], $first['query']['groups']);
        $this->assertSame([
            ['type' => 'count', 'field' => null, 'as' => 'total'],
            ['type' => 'sum', 'field' => 'tenant_id', 'as' => 'sum_tenant_id'],
        ], $first['query']['aggregates']);

        $this->assertSame('status_overview_2', $second['name']);
        $this->assertSame('metric', $second['type']);
        $this->assertSame([
            ['type' => 'count', 'field' => null, 'as' => 'total', 'label' => 'total', 'index' => 0],
        ], $second['metrics']);
        $this->assertSame([
            ['type' => 'count', 'field' => null, 'as' => 'total'],
        ], $second['query']['aggregates']);
        $this->assertSame('alpha', $second['query']['keyword']);
    }

    public function test_it_keeps_legacy_fields_chart_config_compatible(): void
    {
        $compiler = new ChartCompiler();
        $definition = $compiler->compile([
            'title' => 'Legacy Summary',
            'type' => 'pie',
            'group_by' => 'module',
            'fields' => [
                ['name' => 'id', 'type' => 'count', 'label' => '数量'],
                ['name' => 'tenant_id', 'type' => 'sum', 'label' => '租户和值'],
            ],
        ])->toArray();

        $this->assertSame('legacy_summary', $definition['name']);
        $this->assertSame('pie', $definition['type']);
        $this->assertSame([
            ['field' => 'module', 'label' => 'module'],
        ], $definition['dimensions']);
        $this->assertSame([
            ['type' => 'count', 'field' => 'id', 'as' => 'count_id', 'label' => '数量', 'index' => 0],
            ['type' => 'sum', 'field' => 'tenant_id', 'as' => 'sum_tenant_id', 'label' => '租户和值', 'index' => 1],
        ], $definition['metrics']);
        $this->assertSame(['module'], $definition['query']['groups']);
        $this->assertSame([
            ['type' => 'count', 'field' => 'id', 'as' => 'count_id'],
            ['type' => 'sum', 'field' => 'tenant_id', 'as' => 'sum_tenant_id'],
        ], $definition['query']['aggregates']);
    }

    public function test_it_rejects_invalid_metric_configuration_early(): void
    {
        $compiler = new ChartCompiler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chart metric field is required for aggregate [sum] at index [0].');

        $compiler->compile([
            'title' => 'Broken Summary',
            'metrics' => [
                ['type' => 'sum'],
            ],
        ]);
    }
}
