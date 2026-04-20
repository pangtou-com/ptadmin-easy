<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('保存草稿时会创建资源记录并校验资源名称唯一性', function (): void {
    $table = easyRuntimeTable('draft_mod');
    $schema = easyRuntimeSchema($table, [
        'module' => 'cms',
        'table' => [
            'tree' => true,
        ],
        'allow_import' => true,
    ]);

    $draft = Easy::release($table)->saveDraft($schema, ['remark' => '初始草稿']);
    $mod = DB::table('mods')->where('name', $table)->first();
    $version = DB::table('mod_versions')->where('id', $draft['id'])->first();

    expect($draft['status'])->toBe('draft')
        ->and($draft['persisted'])->toBeTrue()
        ->and(data_get($draft, 'name'))->toBe($table)
        ->and(data_get($draft, 'schema.name'))->toBe($table)
        ->and($mod)->not->toBeNull()
        ->and($mod->module)->toBe('cms')
        ->and((int) $mod->current_version_id)->toBe(0)
        ->and((int) $mod->is_publish)->toBe(0)
        ->and($version)->not->toBeNull()
        ->and((int) $version->mod_id)->toBe((int) $mod->id)
        ->and(function () use ($table, $schema): void {
            $conflict = $schema;
            $conflict['module'] = 'blog';
            Easy::release($table)->saveDraft($conflict);
        })->toThrow(InvalidArgumentException::class, 'Schema name ['.$table.'] already exists in module [cms].');
});

it('发布后会同步 mods 与 mod_fields 当前缓存', function (): void {
    $table = easyRuntimeTable('publish_mod');
    $schema = easyRuntimeSchema($table, [
        'module' => 'cms',
        'title' => '文章管理',
        'allow_import' => true,
        'allow_export' => true,
        'allow_copy' => true,
        'allow_recycle' => true,
        'track_changes' => true,
        'table' => [
            'tree' => true,
            'columns' => ['title', 'status'],
        ],
    ]);

    Easy::release($table)->saveDraft($schema, ['remark' => '发布前草稿']);
    $published = Easy::release($table)->publish($schema, ['remark' => '发布版本']);

    $mod = DB::table('mods')->where('name', $table)->first();
    $fields = DB::table('mod_fields')
        ->where('mod_id', $mod->id)
        ->orderBy('sort_order')
        ->get();
    $titleField = $fields->firstWhere('name', 'title');
    $titleMapping = json_decode((string) $titleField->mapping_json, true);

    expect($published->version()['status'])->toBe('published')
        ->and($mod)->not->toBeNull()
        ->and((int) $mod->current_version_id)->toBe((int) $published->version()['id'])
        ->and((int) $mod->is_publish)->toBe(1)
        ->and((int) $mod->is_tree)->toBe(1)
        ->and((int) $mod->allow_import)->toBe(1)
        ->and((int) $mod->allow_export)->toBe(1)
        ->and((int) $mod->allow_copy)->toBe(1)
        ->and((int) $mod->allow_recycle)->toBe(1)
        ->and((int) $mod->track_changes)->toBe(1)
        ->and($fields)->toHaveCount(3)
        ->and($fields->pluck('name')->all())->toBe(['title', 'tenant_id', 'status'])
        ->and((int) $titleField->version_id)->toBe((int) $published->version()['id'])
        ->and(data_get($titleMapping, 'storage.column_definition'))->toBe('varchar(100)')
        ->and(Schema::hasColumn('mods', 'schema_json'))->toBeFalse()
        ->and(Schema::hasColumn('mods', 'setup'))->toBeFalse()
        ->and(Schema::hasColumn('mods', 'extra'))->toBeFalse();
});

it('回滚后会重建 mod_fields 当前版本缓存', function (): void {
    $table = easyRuntimeTable('rollback_mod');
    $schemaV1 = easyRuntimeSchema($table);
    $schemaV2 = easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'maxlength' => 120,
            ],
        ]),
    ]);

    $publishedV1 = Easy::release($table)->publish($schemaV1, ['remark' => 'v1']);
    $publishedV2 = Easy::release($table)->publish($schemaV2, ['remark' => 'v2']);

    expect(DB::table('mod_fields')->where('version_id', $publishedV2->version()['id'])->where('name', 'excerpt')->exists())->toBeTrue();

    Easy::release($table)->rollbackTo((int) $publishedV1->version()['id']);

    $mod = DB::table('mods')->where('name', $table)->first();
    $fields = DB::table('mod_fields')->where('mod_id', $mod->id)->orderBy('sort_order')->get();

    expect((int) $mod->current_version_id)->toBe((int) $publishedV1->version()['id'])
        ->and($fields->pluck('version_id')->unique()->all())->toBe([(int) $publishedV1->version()['id']])
        ->and($fields->pluck('name')->all())->toBe(['title', 'tenant_id', 'status'])
        ->and($fields->firstWhere('name', 'excerpt'))->toBeNull();
});
