<?php

declare(strict_types=1);

use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('可以对比两个版本之间的结构差异', function (): void {
    $table = easyRuntimeTable('version_diff');
    $release = Easy::release($table);

    $draftV1 = $release->saveDraft(easyRuntimeSchema($table), ['remark' => 'v1 草稿']);
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
    ]), ['remark' => 'v2 草稿']);

    $diff = $release->diffVersions((int) $draftV1['id'], (int) $draftV2['id']);

    expect(data_get($diff, 'from_version.id'))->toBe((int) $draftV1['id'])
        ->and(data_get($diff, 'to_version.id'))->toBe((int) $draftV2['id'])
        ->and(data_get($diff, 'plan.operations.add_fields'))->toContain('excerpt')
        ->and(data_get($diff, 'plan.explanation.fields.add.0.name'))->toBe('excerpt');
});

it('可以默认与当前发布版本对比', function (): void {
    $table = easyRuntimeTable('version_diff_current');
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

    $diff = $release->diffVersions((int) $draftV2['id']);

    expect(data_get($diff, 'from_version.id'))->toBe((int) $draftV2['id'])
        ->and(data_get($diff, 'to_version.id'))->toBe((int) $publishedV1->version()['id'])
        ->and(data_get($diff, 'plan.operations.drop_fields'))->toContain('excerpt');
});
