<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Exceptions\SchemaFieldReferenceException;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('可以按插件 code 查询模型列表并创建空字段模型草稿', function (): void {
    $table = easyRuntimeTable('resource_draft');

    $draft = Easy::resources()->createDraft([
        'title' => '文章管理',
        'name' => $table,
        'module' => 'cms',
        'allow_recycle' => 1,
        'track_changes' => 1,
    ], [
        'remark' => '创建模型草稿',
    ]);

    $list = Easy::resources()->lists([
        'module' => 'cms',
        'published' => false,
    ]);
    $all = Easy::resources()->all('cms', [
        'published' => false,
    ]);
    $detail = Easy::resources()->detail($table, 'cms');

    expect($draft['status'])->toBe('draft')
        ->and(data_get($draft, 'schema.fields'))->toBe([])
        ->and($list['total'])->toBe(1)
        ->and(data_get($list, 'stats.total'))->toBe(1)
        ->and(data_get($list, 'stats.published'))->toBe(0)
        ->and(data_get($list, 'stats.draft'))->toBe(1)
        ->and(data_get($list, 'stats.unpublished'))->toBe(1)
        ->and(data_get($list, 'data.0.name'))->toBe($table)
        ->and(data_get($list, 'data.0.module'))->toBe('cms')
        ->and($all)->toHaveCount(1)
        ->and(data_get($detail, 'resource.name'))->toBe($table)
        ->and(data_get($detail, 'latest_draft.id'))->toBe($draft['id'])
        ->and(data_get($detail, 'field_count'))->toBe(0)
        ->and(data_get($detail, 'summary.published'))->toBeFalse()
        ->and(data_get($detail, 'summary.has_draft'))->toBeTrue()
        ->and(data_get($detail, 'summary.latest_draft_id'))->toBe($draft['id'])
        ->and(data_get($detail, 'summary.editing_field_count'))->toBe(0);
});

it('资源目录与字段草稿接口返回结构保持稳定', function (): void {
    $table = easyRuntimeTable('contract_shape');

    $draft = Easy::resources()->createDraft([
        'title' => '文章管理',
        'name' => $table,
        'module' => 'cms',
    ]);

    $list = Easy::resources()->lists([
        'module' => 'cms',
        'published' => false,
    ]);
    $all = Easy::resources()->all('cms', [
        'published' => false,
    ]);
    $detail = Easy::resources()->detail($table, 'cms');
    $fields = Easy::release($table, 'cms')->fields();

    expect($draft)->toHaveKeys(['persisted', 'id', 'status', 'resource', 'module', 'mod_id', 'schema'])
        ->and($list)->toHaveKeys(['data', 'current_page', 'per_page', 'total', 'stats'])
        ->and($list['stats'])->toHaveKeys(['total', 'published', 'draft', 'unpublished'])
        ->and(data_get($list, 'data.0'))->toHaveKeys([
            'id',
            'title',
            'name',
            'module',
            'intro',
            'current_version_id',
            'is_publish',
            'status',
            'allow_recycle',
            'track_changes',
            'title_field',
            'created_at',
            'updated_at',
        ])
        ->and(data_get($all, '0'))->toHaveKeys([
            'id',
            'title',
            'name',
            'module',
            'current_version_id',
            'is_publish',
        ])
        ->and($detail)->toHaveKeys([
            'resource',
            'current_version',
            'latest_draft',
            'field_count',
            'published_field_count',
            'summary',
        ])
        ->and($detail['summary'])->toHaveKeys([
            'published',
            'has_current_version',
            'has_draft',
            'pending_changes',
            'current_version_id',
            'latest_draft_id',
            'editing_field_count',
            'draft_field_count',
            'published_field_count',
            'latest_draft_updated_at',
            'current_version_updated_at',
        ])
        ->and($fields)->toBe([]);
});

it('模型列表统计会返回发布与草稿分布', function (): void {
    $publishedTable = easyRuntimeTable('stats_published');
    $draftOnlyTable = easyRuntimeTable('stats_draft');

    $publishedRelease = Easy::release($publishedTable, 'cms');
    $publishedDraft = $publishedRelease->saveDraft(easyRuntimeSchema($publishedTable, [
        'module' => 'cms',
    ]));
    $publishedRelease->publishVersion((int) $publishedDraft['id']);
    $publishedRelease->addField([
        'name' => 'excerpt',
        'type' => 'text',
        'label' => '摘要',
        'maxlength' => 120,
    ]);

    Easy::resources()->createDraft([
        'title' => '仅草稿模型',
        'name' => $draftOnlyTable,
        'module' => 'cms',
    ]);

    $list = Easy::resources()->lists([
        'module' => 'cms',
    ]);
    $detail = Easy::resources()->detail($publishedTable, 'cms');

    expect(data_get($list, 'stats.total'))->toBe(2)
        ->and(data_get($list, 'stats.published'))->toBe(1)
        ->and(data_get($list, 'stats.draft'))->toBe(2)
        ->and(data_get($list, 'stats.unpublished'))->toBe(1)
        ->and(data_get($detail, 'summary.published'))->toBeTrue()
        ->and(data_get($detail, 'summary.has_current_version'))->toBeTrue()
        ->and(data_get($detail, 'summary.has_draft'))->toBeTrue()
        ->and(data_get($detail, 'summary.pending_changes'))->toBeTrue()
        ->and(data_get($detail, 'summary.current_version_id'))->not->toBeNull()
        ->and(data_get($detail, 'summary.latest_draft_id'))->not->toBeNull()
        ->and(data_get($detail, 'summary.draft_field_count'))->toBe(4)
        ->and(data_get($detail, 'summary.published_field_count'))->toBe(3)
        ->and(data_get($detail, 'summary.editing_field_count'))->toBe(4);
});

it('空字段模型草稿不允许发布', function (): void {
    $table = easyRuntimeTable('empty_publish');
    Easy::resources()->createDraft([
        'title' => '空模型',
        'name' => $table,
        'module' => 'cms',
    ]);

    expect(function () use ($table): void {
        Easy::release($table, 'cms')->publishDraft();
    })->toThrow(InvalidArgumentException::class, 'Schema fields are required.');
});

it('可以逐个维护字段草稿并统一发布', function (): void {
    $table = easyRuntimeTable('field_draft');
    Easy::resources()->createDraft([
        'title' => '文章管理',
        'name' => $table,
        'module' => 'cms',
    ]);

    $release = Easy::release($table, 'cms');
    $release->addField([
        'name' => 'title',
        'type' => 'text',
        'label' => '标题',
        'maxlength' => 100,
    ], [
        'remark' => '新增标题',
    ]);
    $release->addField([
        'name' => 'status',
        'type' => 'radio',
        'label' => '状态',
        'defaultValue' => 1,
        'options' => [
            ['label' => '启用', 'value' => 1],
            ['label' => '禁用', 'value' => 0],
        ],
    ]);
    $updated = $release->updateField('title', [
        'label' => '文章标题',
        'maxlength' => 150,
    ]);
    $release->reorderFields(['status', 'title']);
    $plan = $release->planDraft();
    $published = $release->publishDraft();

    $mod = DB::table('mods')->where('name', $table)->first();
    $fields = DB::table('mod_fields')->where('mod_id', $mod->id)->orderBy('sort_order')->pluck('name')->all();

    expect(data_get($updated, 'schema.fields.0.label'))->toBe('文章标题')
        ->and(array_column($release->fields(), 'name'))->toBe(['status', 'title'])
        ->and($plan->operations()['create_table'])->toBeTrue()
        ->and($published->version()['status'])->toBe('published')
        ->and(Schema::hasTable($table))->toBeTrue()
        ->and(Schema::hasColumn($table, 'title'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'status'))->toBeTrue()
        ->and($fields)->toBe(['status', 'title']);
});

it('字段删除只影响草稿并在发布后同步结构', function (): void {
    $table = easyRuntimeTable('field_delete');
    $release = Easy::release($table, 'cms');
    $draft = $release->saveDraft(easyRuntimeSchema($table, [
        'module' => 'cms',
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '标题',
                'maxlength' => 100,
            ],
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'maxlength' => 120,
            ],
        ],
    ]));
    $release->publishVersion((int) $draft['id']);

    $updated = $release->deleteField('excerpt');
    $plan = $release->planDraft();
    $release->publishDraft(null, ['force' => true]);

    expect(array_column((array) data_get($updated, 'schema.fields', []), 'name'))->toBe(['title', 'status'])
        ->and($plan->operations()['drop_fields'])->toContain('excerpt')
        ->and(Schema::hasColumn($table, 'excerpt'))->toBeFalse();
});

it('删除被引用字段时会抛出带引用明细的异常', function (): void {
    $table = easyRuntimeTable('field_ref');
    Easy::resources()->createDraft([
        'title' => '文章管理',
        'name' => $table,
        'module' => 'cms',
        'title_field' => 'title',
        'search_fields' => ['title'],
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '标题',
            ],
        ],
    ]);

    try {
        Easy::release($table, 'cms')->deleteField('title');
        $this->fail('Expected SchemaFieldReferenceException was not thrown.');
    } catch (SchemaFieldReferenceException $exception) {
        expect($exception->field())->toBe('title')
            ->and($exception->operation())->toBe('delete')
            ->and(array_column($exception->references(), 'path'))->toContain('title_field', 'search_fields.0')
            ->and(data_get($exception->toArray(), 'ok'))->toBeFalse();
    }
});

it('可以重命名草稿字段并生成 rename 发布计划', function (): void {
    $table = easyRuntimeTable('field_rename');
    $release = Easy::release($table, 'cms');
    $draft = $release->saveDraft(easyRuntimeSchema($table, [
        'module' => 'cms',
        'title_field' => 'title',
        'search_fields' => ['title'],
        'order' => [
            'title' => 'asc',
        ],
    ]));
    $release->publishVersion((int) $draft['id']);

    $renamed = $release->renameField('title', 'headline');
    $plan = $release->planDraft();
    $release->publishDraft();

    expect(array_column((array) data_get($renamed, 'schema.fields', []), 'name'))->toContain('headline')
        ->and(data_get($renamed, 'schema.title_field'))->toBe('headline')
        ->and(data_get($renamed, 'schema.search_fields.0'))->toBe('headline')
        ->and(data_get($renamed, 'schema.order.headline'))->toBe('asc')
        ->and(data_get($renamed, 'summary.type'))->toBe('rename_field')
        ->and(data_get($renamed, 'summary.from'))->toBe('title')
        ->and(data_get($renamed, 'summary.to'))->toBe('headline')
        ->and(data_get($renamed, 'summary.rename_from'))->toBe('title')
        ->and(array_column((array) data_get($renamed, 'summary.references_updated', []), 'path'))->toContain('title_field', 'search_fields.0', 'order.title')
        ->and($plan->operations()['rename_fields'])->toBe(['title' => 'headline'])
        ->and(Schema::hasColumn($table, 'title'))->toBeFalse()
        ->and(Schema::hasColumn($table, 'headline'))->toBeTrue();
});

it('可以显式清理引用后删除字段并继续发布', function (): void {
    $table = easyRuntimeTable('field_cleanup');
    $related = easyRuntimeTable('field_cleanup_related');

    Easy::release($related, 'cms')->publish(easyRuntimeSchema($related, [
        'module' => 'cms',
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '标题',
                'maxlength' => 100,
            ],
            [
                'name' => 'article_title',
                'type' => 'text',
                'label' => '文章标题快照',
                'maxlength' => 100,
            ],
        ],
    ]));

    $schema = easyRuntimeSchema($table, [
        'module' => 'cms',
        'title_field' => 'title',
        'search_fields' => ['title'],
        'order' => [
            'title' => 'asc',
        ],
        'table' => [
            'columns' => ['title', 'status'],
        ],
        'charts' => [
            [
                'name' => 'title_summary',
                'title' => '标题统计',
                'groups' => ['title'],
                'metrics' => [
                    ['type' => 'count', 'field' => 'title', 'as' => 'total'],
                ],
                'query' => [
                    'filters' => [
                        ['field' => 'title', 'operator' => 'like', 'value' => '%A%'],
                    ],
                    'sorts' => [
                        ['field' => 'title', 'direction' => 'asc'],
                    ],
                ],
            ],
        ],
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '标题',
                'maxlength' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'rules' => ['required'],
                'max' => 999999,
            ],
            [
                'name' => 'status',
                'type' => 'radio',
                'label' => '状态',
                'defaultValue' => 1,
                'options' => [
                    ['label' => '启用', 'value' => 1],
                    ['label' => '禁用', 'value' => 0],
                ],
                'relation' => [
                    'type' => 'has_many',
                    'resource' => $related,
                    'foreign_key' => 'article_title',
                    'local_key' => 'title',
                ],
            ],
        ],
    ]);

    $release = Easy::release($table, 'cms');
    $draft = $release->saveDraft($schema);
    $release->publishVersion((int) $draft['id']);

    $updated = $release->deleteField('title', [
        'cleanup_references' => true,
    ]);
    $release->publishDraft(null, ['force' => true]);

    expect(array_column((array) data_get($updated, 'schema.fields', []), 'name'))->toBe(['tenant_id', 'status'])
        ->and(data_get($updated, 'schema.title_field'))->toBeNull()
        ->and(data_get($updated, 'schema.search_fields'))->toBeNull()
        ->and(data_get($updated, 'schema.order'))->toBeNull()
        ->and(data_get($updated, 'schema.table.columns'))->toBe(['status'])
        ->and(data_get($updated, 'schema.charts.0.groups'))->toBeNull()
        ->and(data_get($updated, 'schema.charts.0.query.filters'))->toBeNull()
        ->and(data_get($updated, 'schema.charts.0.query.sorts'))->toBeNull()
        ->and(data_get($updated, 'schema.fields.1.relation.local_key'))->toBeNull()
        ->and(data_get($updated, 'summary.type'))->toBe('delete_field')
        ->and(data_get($updated, 'summary.field'))->toBe('title')
        ->and(data_get($updated, 'summary.cleanup_applied'))->toBeTrue()
        ->and(array_column((array) data_get($updated, 'summary.references_removed', []), 'path'))->toContain(
            'title_field',
            'search_fields.0',
            'order.title',
            'table.columns.0',
            'charts.0.groups.0',
            'charts.0.query.filters.0.field',
            'charts.0.query.sorts.0.field',
            'fields.2.relation.local_key'
        )
        ->and(Schema::hasColumn($table, 'title'))->toBeFalse();
});
