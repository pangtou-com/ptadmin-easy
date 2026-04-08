<?php

declare(strict_types=1);

use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('版本管理页面板会返回 summary stats pagination 和带动作标记的列表项', function (): void {
    $table = easyRuntimeTable('version_panel');
    $release = Easy::release($table);

    $draftV1 = $release->saveDraft(easyRuntimeSchema($table), ['remark' => 'v1 草稿']);
    $publishedV1 = $release->publish((int) $draftV1['id']);

    $draftV2 = $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'length' => 120,
            ],
        ]),
    ]), ['remark' => 'v2 草稿']);

    $panel = $release->versionPanel(1, 10);
    $draftItem = collect((array) ($panel['items'] ?? []))->firstWhere('id', (int) $draftV2['id']);
    $publishedItem = collect((array) ($panel['items'] ?? []))->firstWhere('id', (int) $publishedV1->version()['id']);

    expect(data_get($panel, 'summary.current_version_id'))->toBe((int) $publishedV1->version()['id'])
        ->and(data_get($panel, 'summary.latest_draft_id'))->toBe((int) $draftV2['id'])
        ->and(data_get($panel, 'summary.has_unpublished_draft'))->toBeTrue()
        ->and(data_get($panel, 'stats.all'))->toBe(2)
        ->and(data_get($panel, 'stats.draft'))->toBe(1)
        ->and(data_get($panel, 'stats.published'))->toBe(1)
        ->and(data_get($panel, 'pagination.total'))->toBe(2)
        ->and(data_get($draftItem, 'change_summary.add_fields'))->toBe(1)
        ->and(data_get($draftItem, 'actions.publish'))->toBeTrue()
        ->and(data_get($draftItem, 'actions.edit_draft'))->toBeTrue()
        ->and(data_get($draftItem, 'actions.rollback'))->toBeFalse()
        ->and(data_get($publishedItem, 'actions.delete'))->toBeFalse()
        ->and(data_get($publishedItem, 'actions.compare'))->toBeTrue();
});

it('版本详情结构会返回 schema actions 和 plan 信息', function (): void {
    $table = easyRuntimeTable('version_detail_panel');
    $release = Easy::release($table);

    $draft = $release->saveDraft(easyRuntimeSchema($table, [
        'title' => '栏目配置',
    ]), ['remark' => '初始草稿']);
    $detail = $release->versionDetail((int) $draft['id']);

    expect(data_get($detail, 'version.id'))->toBe((int) $draft['id'])
        ->and((int) data_get($detail, 'version.created_at'))->toBeGreaterThan(0)
        ->and((int) data_get($detail, 'version.updated_at'))->toBeGreaterThan(0)
        ->and(data_get($detail, 'schema.title'))->toBe('栏目配置')
        ->and(data_get($detail, 'actions.publish'))->toBeTrue()
        ->and(data_get($detail, 'change_summary.create_table'))->toBeTrue()
        ->and(data_get($detail, 'plan.operations.create_table'))->toBeTrue();
});

it('版本管理页面板支持分页和筛选后的总数统计', function (): void {
    $table = easyRuntimeTable('version_panel_page');
    $release = Easy::release($table);

    $draftV1 = $release->saveDraft(easyRuntimeSchema($table), ['remark' => '第一页']);
    $release->publish((int) $draftV1['id']);
    $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'length' => 120,
            ],
        ]),
    ]), ['remark' => '第二页']);
    $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'seo_title',
                'type' => 'text',
                'label' => 'SEO标题',
                'length' => 120,
            ],
        ]),
    ]), ['remark' => '筛选关键字']);

    $panel = $release->versionPanel(1, 1, ['status' => 'draft', 'keyword' => '筛选']);

    expect(data_get($panel, 'pagination.total'))->toBe(1)
        ->and(data_get($panel, 'pagination.page'))->toBe(1)
        ->and(data_get($panel, 'pagination.page_size'))->toBe(1)
        ->and(data_get($panel, 'pagination.last_page'))->toBe(1)
        ->and(count((array) ($panel['items'] ?? [])))->toBe(1)
        ->and(data_get($panel, 'items.0.remark'))->toBe('筛选关键字');
});
