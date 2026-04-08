<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * 初始化运行时测试所需的基础表。
 */
function migrateEasyRuntimeTables(): void
{
    easyDropAllTables();
    easyEnsureMigrationRepositoryExists();
    Artisan::call('migrate', [
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
    ]);
}

/**
 * 删除当前测试库里真实存在的全部数据表。
 */
function easyDropAllTables(): void
{
    $connection = DB::connection();
    $tables = array_map(static function ($row): string {
        $values = array_values((array) $row);

        return (string) ($values[0] ?? '');
    }, $connection->select('SHOW TABLES'));
    if (0 === \count($tables)) {
        return;
    }

    $connection->statement('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $connection->statement('DROP TABLE IF EXISTS `'.$table.'`');
    }
    $connection->statement('SET FOREIGN_KEY_CHECKS=1');
    DB::purge('testing');
    DB::reconnect('testing');
}

/**
 * 重建迁移仓库表，供 `migrate` 写入批次记录。
 */
function easyEnsureMigrationRepositoryExists(): void
{
    DB::connection()->statement('CREATE TABLE IF NOT EXISTS `migrations` (`id` int unsigned NOT NULL AUTO_INCREMENT, `migration` varchar(255) NOT NULL, `batch` int NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

/**
 * 生成当前测试用的资源名称，避免静态句柄缓存相互污染。
 */
function easyRuntimeTable(string $prefix): string
{
    return $prefix.'_'.strtolower(substr(md5(uniqid((string) mt_rand(), true)), 0, 10));
}

/**
 * 构建运行时测试使用的最小 schema。
 *
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function easyRuntimeSchema(string $table, array $overrides = []): array
{
    $schema = [
        'title' => 'Runtime '.$table,
        'module' => 'App',
        'name' => $table,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'extends' => [
                    'max' => 999999,
                ],
            ],
            [
                'name' => 'status',
                'type' => 'radio',
                'label' => '状态',
                'default' => 1,
                'options' => [
                    ['label' => '启用', 'value' => 1],
                    ['label' => '禁用', 'value' => 0],
                ],
            ],
        ],
    ];

    return array_replace_recursive($schema, $overrides);
}
