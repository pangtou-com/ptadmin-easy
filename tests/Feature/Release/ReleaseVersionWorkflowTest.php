<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('草稿版本可以按ID预览并发布', function (): void {
    $table = easyRuntimeTable('draft_publish_id');
    $schema = easyRuntimeSchema($table, [
        'module' => 'cms',
        'title' => '文章管理',
    ]);
    $release = Easy::release($table, 'cms');

    $draft = $release->saveDraft($schema, ['remark' => '草稿版本']);
    $plan = $release->planVersion((int) $draft['id']);
    $published = $release->publish((int) $draft['id'], ['remark' => '不会覆盖草稿备注']);
    $history = $release->history();

    expect($draft['status'])->toBe('draft')
        ->and($plan->operations()['create_table'])->toBeTrue()
        ->and($published->version()['id'])->toBe((int) $draft['id'])
        ->and($published->version()['status'])->toBe('published')
        ->and($release->current()['id'])->toBe((int) $draft['id'])
        ->and($release->latestDraft())->toBeNull()
        ->and($history)->toHaveCount(1)
        ->and(DB::table('mod_versions')->where('name', $table)->count())->toBe(1)
        ->and(Schema::hasTable($table))->toBeTrue();
});

it('发布时会校验草稿版本归属与状态', function (): void {
    $articleTable = easyRuntimeTable('article_release_id');
    $categoryTable = easyRuntimeTable('category_release_id');

    $articleDraft = Easy::release($articleTable)->saveDraft(easyRuntimeSchema($articleTable), ['remark' => '文章草稿']);
    $categoryDraft = Easy::release($categoryTable)->saveDraft(easyRuntimeSchema($categoryTable), ['remark' => '栏目草稿']);

    expect(function () use ($articleTable, $categoryDraft): void {
        Easy::release($articleTable)->publish((int) $categoryDraft['id']);
    })->toThrow(InvalidArgumentException::class, 'Schema version ['.$categoryDraft['id'].'] does not belong to resource ['.$articleTable.'].');

    Easy::release($articleTable)->publishVersion((int) $articleDraft['id']);

    expect(function () use ($articleTable, $articleDraft): void {
        Easy::release($articleTable)->publishVersion((int) $articleDraft['id']);
    })->toThrow(InvalidArgumentException::class, 'Schema version ['.$articleDraft['id'].'] status must be [draft].');
});

it('回退时不允许传入草稿版本ID', function (): void {
    $table = easyRuntimeTable('rollback_reject_draft');
    $release = Easy::release($table);

    $publishedV1Draft = $release->saveDraft(easyRuntimeSchema($table), ['remark' => 'v1 草稿']);
    $release->publish((int) $publishedV1Draft['id']);

    $draftV2 = $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'maxlength' => 100,
            ],
        ]),
    ]), ['remark' => 'v2 草稿']);

    expect(function () use ($release, $draftV2): void {
        $release->rollbackTo((int) $draftV2['id']);
    })->toThrow(InvalidArgumentException::class, 'Schema version ['.$draftV2['id'].'] is a draft and cannot be rolled back.');
});

it('可以查看指定版本详情并按ID更新草稿', function (): void {
    $table = easyRuntimeTable('draft_update_id');
    $release = Easy::release($table, 'cms');
    $draft = $release->saveDraft(easyRuntimeSchema($table, [
        'module' => 'cms',
        'title' => '初始标题',
    ]), ['remark' => '初始草稿']);

    $detail = $release->version((int) $draft['id']);
    $updated = $release->updateDraft((int) $draft['id'], easyRuntimeSchema($table, [
        'module' => 'cms',
        'title' => '更新后标题',
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'maxlength' => 120,
            ],
        ]),
    ]), ['remark' => '草稿已更新']);
    $plan = $release->plan((int) $draft['id']);
    $published = $release->publish((int) $draft['id']);

    expect(data_get($detail, 'schema.title'))->toBe('初始标题')
        ->and(data_get($updated, 'schema.title'))->toBe('更新后标题')
        ->and($updated['remark'])->toBe('草稿已更新')
        ->and($updated['id'])->toBe((int) $draft['id'])
        ->and($plan->operations()['add_fields'])->toContain('excerpt')
        ->and($published->version()['id'])->toBe((int) $draft['id'])
        ->and(Schema::hasColumn($table, 'excerpt'))->toBeTrue()
        ->and(data_get($release->current(), 'schema.title'))->toBe('更新后标题');
});

it('已发布版本不允许再次作为草稿更新', function (): void {
    $table = easyRuntimeTable('reject_update_published');
    $release = Easy::release($table);

    $draft = $release->saveDraft(easyRuntimeSchema($table), ['remark' => '草稿']);
    $release->publish((int) $draft['id']);

    expect(function () use ($release, $draft, $table): void {
        $release->updateDraft((int) $draft['id'], easyRuntimeSchema($table, [
            'title' => '不允许更新',
        ]));
    })->toThrow(InvalidArgumentException::class, 'Schema version ['.$draft['id'].'] status must be [draft].');
});
