<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('资源级配置从 mod_versions 读取，字段定义从 mod_fields 缓存读取', function (): void {
    $table = easyRuntimeTable('runtime_catalog');
    $schema = easyRuntimeSchema($table, [
        'module' => 'cms',
        'title' => '栏目管理',
    ]);

    $published = Easy::release($table)->publish($schema, ['remark' => '初始发布']);

    DB::table('mod_versions')
        ->where('id', $published->version()['id'])
        ->update([
            'schema_json' => json_encode([
                'title' => '版本标题已变更',
                'name' => $table,
                'module' => 'cms',
                'fields' => [],
            ], JSON_UNESCAPED_UNICODE),
        ]);

    $blueprint = Easy::schema($table, 'cms')->blueprint();
    $created = Easy::doc($table, 'cms')->create([
        'title' => '目录读取',
        'tenant_id' => 1,
        'status' => 1,
    ]);

    expect(data_get($blueprint, 'resource.title'))->toBe('版本标题已变更')
        ->and(array_column((array) data_get($blueprint, 'fields', []), 'name'))->toBe(['title', 'tenant_id', 'status'])
        ->and($created->title)->toBe('目录读取')
        ->and(Easy::doc($table, 'cms')->detail($created->id)->title)->toBe('目录读取');
});

it('preserves table search and form root protocol after publish', function (): void {
    $table = easyRuntimeTable('runtime_root_protocol');
    $schema = easyRuntimeSchema($table, [
        'module' => 'cms',
        'table' => [
            'title' => '栏目列表',
            'help' => [
                'title' => '列表说明',
                'message' => '这里用于展示栏目数据。',
            ],
            'tree' => true,
            'toolbar' => ['create', 'refresh'],
            'search' => [
                'operator' => true,
                'buttons' => ['submit', 'reset'],
                'fields' => ['title', 'status'],
            ],
            'selection' => true,
            'index' => 'desc',
            'pagination' => [
                'layout' => 'total, prev, pager, next',
                'pageSizes' => [20, 50, 100],
                'background' => true,
            ],
            'operate' => ['edit', 'delete'],
            'columns' => ['title', 'status'],
        ],
        'form' => [
            'title' => '栏目表单',
            'help' => [
                'title' => '表单说明',
                'message' => '这里用于编辑栏目。',
            ],
            'labelWidth' => 120,
            'labelPosition' => 'right',
            'col' => 12,
            'gutter' => 16,
            'wrapper' => [
                'type' => 'dialog',
                'title' => '编辑栏目',
                'width' => 720,
            ],
            'footer' => false,
        ],
    ]);

    Easy::release($table)->publish($schema, ['remark' => 'root protocol']);

    $schemaHandle = Easy::schema($table, 'cms');
    $raw = $schemaHandle->raw()->toArray();
    $blueprint = $schemaHandle->blueprint();

    expect(data_get($raw, 'table.search.operator'))->toBeTrue()
        ->and(data_get($raw, 'table.search.buttons'))->toBe(['submit', 'reset'])
        ->and(data_get($raw, 'table.pagination.pageSizes'))->toBe([20, 50, 100])
        ->and(data_get($raw, 'form.wrapper.type'))->toBe('dialog')
        ->and(data_get($raw, 'form.wrapper.width'))->toBe(720)
        ->and(data_get($raw, 'form.footer'))->toBeFalse()
        ->and(data_get($blueprint, 'views.table.title'))->toBe('栏目列表')
        ->and(data_get($blueprint, 'views.table.help.message'))->toBe('这里用于展示栏目数据。')
        ->and(data_get($blueprint, 'views.table.search.fields'))->toBe(['title', 'status'])
        ->and(data_get($blueprint, 'views.table.selection'))->toBeTrue()
        ->and(data_get($blueprint, 'views.table.index'))->toBe('desc')
        ->and(data_get($blueprint, 'views.table.pagination.background'))->toBeTrue()
        ->and(data_get($blueprint, 'views.table.columns'))->toBe(['title', 'status'])
        ->and(data_get($blueprint, 'views.form.title'))->toBe('栏目表单')
        ->and(data_get($blueprint, 'views.form.help.message'))->toBe('这里用于编辑栏目。')
        ->and(data_get($blueprint, 'views.form.labelWidth'))->toBe(120)
        ->and(data_get($blueprint, 'views.form.wrapper.title'))->toBe('编辑栏目')
        ->and(data_get($blueprint, 'views.form.footer'))->toBeFalse();
});
