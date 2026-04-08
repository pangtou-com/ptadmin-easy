<?php

declare(strict_types=1);

use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('可以按状态与关键字筛选版本列表和草稿列表', function (): void {
    $table = easyRuntimeTable('version_filters');
    $release = Easy::release($table);

    $draftV1 = $release->saveDraft(easyRuntimeSchema($table), ['remark' => '第一版草稿']);
    $release->publish((int) $draftV1['id']);

    $draftV2 = $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'length' => 120,
            ],
        ]),
    ]), ['remark' => '第二版草稿']);

    $currentPublished = $release->history(20, ['status' => 'published']);
    $drafts = $release->drafts(20, ['keyword' => '第二版']);
    $byVersionNo = $release->history(20, ['version_no' => 2]);

    expect(array_column($currentPublished, 'status'))->toBe(['published'])
        ->and(array_column($drafts, 'status'))->toBe(['draft'])
        ->and(array_column($drafts, 'remark'))->toBe(['第二版草稿'])
        ->and(array_column($byVersionNo, 'id'))->toBe([(int) $draftV2['id']]);
});

it('可以删除指定草稿版本', function (): void {
    $table = easyRuntimeTable('delete_draft');
    $release = Easy::release($table);

    $draft = $release->saveDraft(easyRuntimeSchema($table), ['remark' => '待删除草稿']);

    expect($release->deleteDraft((int) $draft['id']))->toBeTrue()
        ->and($release->version((int) $draft['id']))->toBeNull()
        ->and($release->drafts())->toBe([]);
});

it('不允许删除已发布版本', function (): void {
    $table = easyRuntimeTable('delete_published');
    $release = Easy::release($table);

    $draft = $release->saveDraft(easyRuntimeSchema($table), ['remark' => '发布前草稿']);
    $release->publish((int) $draft['id']);

    expect(function () use ($release, $draft): void {
        $release->deleteDraft((int) $draft['id']);
    })->toThrow(InvalidArgumentException::class, 'Schema version ['.$draft['id'].'] status must be [draft].');
});
