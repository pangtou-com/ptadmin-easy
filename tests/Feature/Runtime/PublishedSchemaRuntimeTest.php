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
