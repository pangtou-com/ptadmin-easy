<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('允许删除 archived 和 superseded 历史版本', function (): void {
    $table = easyRuntimeTable('delete_history');
    $release = Easy::release($table);

    $draftV1 = $release->saveDraft(easyRuntimeSchema($table), ['remark' => 'v1 草稿']);
    $publishedV1 = $release->publish((int) $draftV1['id']);

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
    $publishedV2 = $release->publish((int) $draftV2['id']);

    $draftV3 = $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'seo_title',
                'type' => 'text',
                'label' => 'SEO 标题',
                'maxlength' => 100,
            ],
        ]),
    ]), ['remark' => 'v3 草稿']);
    $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'seo_keyword',
                'type' => 'text',
                'label' => 'SEO 关键词',
                'maxlength' => 100,
            ],
        ]),
    ]), ['remark' => 'v4 草稿']);

    expect($release->deleteVersion((int) $publishedV1->version()['id']))->toBeTrue()
        ->and(DB::table('mod_versions')->where('id', $publishedV1->version()['id'])->exists())->toBeFalse()
        ->and($release->deleteVersion((int) $draftV3['id']))->toBeTrue()
        ->and(DB::table('mod_versions')->where('id', $draftV3['id'])->exists())->toBeFalse();

    expect(function () use ($release, $publishedV2): void {
        $release->deleteVersion((int) $publishedV2->version()['id']);
    })->toThrow(InvalidArgumentException::class, 'Schema version ['.$publishedV2->version()['id'].'] is the current published version and cannot be deleted.');
});
