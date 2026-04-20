<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('审计日志会记录执行时使用的schema版本ID', function (): void {
    $table = easyRuntimeTable('audit_version');
    $release = Easy::release($table);

    $draftV1 = $release->saveDraft(easyRuntimeSchema($table), ['remark' => 'v1 草稿']);
    $publishedV1 = $release->publish((int) $draftV1['id']);

    $createdV1 = Easy::doc($table)->create([
        'title' => '第一版数据',
        'tenant_id' => 1,
        'status' => 1,
    ]);

    $draftV2 = $release->saveDraft(easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'maxlength' => 120,
            ],
        ]),
    ]), ['remark' => 'v2 草稿']);
    $publishedV2 = $release->publish((int) $draftV2['id']);

    Easy::doc($table)->update((int) $createdV1->id, [
        'title' => '第二版更新',
        'excerpt' => '已进入第二版',
    ]);

    $audits = DB::table('audit_logs')
        ->where('resource', $table)
        ->orderBy('id')
        ->get(['operation', 'schema_version_id'])
        ->map(static function ($row): array {
            return [
                'operation' => $row->operation,
                'schema_version_id' => (int) $row->schema_version_id,
            ];
        })
        ->all();

    expect($audits)->toBe([
        [
            'operation' => 'create',
            'schema_version_id' => (int) $publishedV1->version()['id'],
        ],
        [
            'operation' => 'update',
            'schema_version_id' => (int) $publishedV2->version()['id'],
        ],
    ]);
});
