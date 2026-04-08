<?php

declare(strict_types=1);

use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('本地选项字段会输出展示文本附加字段', function (): void {
    $table = easyRuntimeTable('option_append_text');
    $schema = easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'type',
                'type' => 'select',
                'label' => '类型',
                'options' => [
                    ['label' => 'Alpha', 'value' => 'alpha'],
                    ['label' => 'Beta', 'value' => 'beta'],
                ],
            ],
            [
                'name' => 'enabled',
                'type' => 'switch',
                'label' => '启用状态',
            ],
        ]),
    ]);

    Easy::release($table)->publish($schema);

    $created = Easy::doc($table)->create([
        'title' => 'option text article',
        'tenant_id' => 1,
        'status' => 1,
        'type' => 'alpha',
        'enabled' => 1,
    ]);

    $detail = Easy::doc($table)->detail($created->id);
    $listed = Easy::doc($table)->lists([
        'sorts' => [
            ['field' => 'id', 'direction' => 'asc'],
        ],
    ]);

    expect($detail->__status_text)->toBe('启用')
        ->and($detail->__type_text)->toBe('Alpha')
        ->and($detail->__enabled_text)->toBe('是')
        ->and(data_get($listed, '0.__status_text'))->toBe('启用')
        ->and(data_get($listed, '0.__type_text'))->toBe('Alpha')
        ->and(data_get($listed, '0.__enabled_text'))->toBe('是');
});

it('本地选项展示文本字段支持过滤和排序', function (): void {
    $table = easyRuntimeTable('option_append_query');
    $schema = easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'type',
                'type' => 'select',
                'label' => '类型',
                'options' => [
                    ['label' => 'Alpha', 'value' => 'alpha'],
                    ['label' => 'Beta', 'value' => 'beta'],
                    ['label' => 'Gamma', 'value' => 'gamma'],
                ],
            ],
        ]),
    ]);

    Easy::release($table)->publish($schema);

    Easy::doc($table)->create([
        'title' => 'article beta',
        'tenant_id' => 1,
        'status' => 1,
        'type' => 'beta',
    ]);
    Easy::doc($table)->create([
        'title' => 'article alpha',
        'tenant_id' => 1,
        'status' => 0,
        'type' => 'alpha',
    ]);
    Easy::doc($table)->create([
        'title' => 'article gamma',
        'tenant_id' => 1,
        'status' => 1,
        'type' => 'gamma',
    ]);

    $filtered = Easy::doc($table)->lists([
        'filters' => [
            ['field' => '__type_text', 'operator' => 'like', 'value' => '%Alpha%'],
            ['field' => '__status_text', 'operator' => '=', 'value' => '禁用'],
        ],
    ]);
    $ascending = Easy::doc($table)->lists([
        'sorts' => [
            ['field' => '__type_text', 'direction' => 'asc'],
        ],
    ]);
    $descending = Easy::doc($table)->lists([
        'sorts' => [
            ['field' => '__type_text', 'direction' => 'desc'],
        ],
    ]);

    expect($filtered)->toHaveCount(1)
        ->and(data_get($filtered, '0.title'))->toBe('article alpha')
        ->and(data_get($filtered, '0.__type_text'))->toBe('Alpha')
        ->and(data_get($filtered, '0.__status_text'))->toBe('禁用')
        ->and(array_column($ascending, '__type_text'))->toBe(['Alpha', 'Beta', 'Gamma'])
        ->and(array_column($descending, '__type_text'))->toBe(['Gamma', 'Beta', 'Alpha']);
});
