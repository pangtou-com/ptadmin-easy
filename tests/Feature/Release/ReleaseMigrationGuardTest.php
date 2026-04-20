<?php

declare(strict_types=1);

use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('迁移计划会识别当前阶段不支持的字段存储类型转换', function (): void {
    $table = easyRuntimeTable('unsupported_storage_change');
    $release = Easy::release($table);

    $schemaV1 = easyRuntimeSchema($table, [
        'fields' => [
            [
                'name' => 'code',
                'type' => 'text',
                'label' => '编码',
                'maxlength' => 100,
            ],
        ],
    ]);

    $schemaV2 = easyRuntimeSchema($table, [
        'fields' => [
            [
                'name' => 'code',
                'type' => 'number',
                'label' => '编码',
                'extends' => [
                    'max' => 999999,
                ],
            ],
        ],
    ]);

    $release->publish($schemaV1);
    $plan = $release->plan($schemaV2);

    expect($plan->hasUnsupportedChanges())->toBeTrue()
        ->and(data_get($plan->toArray(), 'unsupported'))->toBeTrue()
        ->and(data_get($plan->explanation(), 'summary.unsupported_count'))->toBe(1)
        ->and(array_column((array) $plan->risks(), 'code'))->toContain('unsupported_storage_change')
        ->and(data_get($plan->explanation(), 'fields.change.0.risks.0.code'))->toBe('unsupported_storage_change');
});

it('发布时会阻止当前阶段不支持的字段存储类型转换并给出友好提示', function (): void {
    $table = easyRuntimeTable('unsupported_publish_guard');
    $release = Easy::release($table);

    $draftV1 = $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => [
            [
                'name' => 'code',
                'type' => 'text',
                'label' => '编码',
                'maxlength' => 100,
            ],
        ],
    ]), ['remark' => '文本编码']);
    $release->publishVersion((int) $draftV1['id']);

    $draftV2 = $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => [
            [
                'name' => 'code',
                'type' => 'number',
                'label' => '编码',
                'extends' => [
                    'max' => 999999,
                ],
            ],
        ],
    ]), ['remark' => '改为数字编码']);

    expect(function () use ($release, $draftV2): void {
        $release->publishVersion((int) $draftV2['id']);
    })->toThrow(
        InvalidArgumentException::class,
        'Direct automatic conversion is not supported in current stage. Please create a new field and migrate data manually.'
    );
});
