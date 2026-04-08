<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2026 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use PTAdmin\Easy\Providers\EasyServiceProviders;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected static $migration;

    protected function getPackageProviders($app): array
    {
        return [EasyServiceProviders::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('test_path', \dirname(__DIR__).\DIRECTORY_SEPARATOR.'tests'.\DIRECTORY_SEPARATOR.'Stubs');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'mysql',
            'database' => 'test',
            'prefix' => '',
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => '989912',
            'port' => 3306,
        ]);
    }

    /**
     * 以更稳妥的方式重置测试数据库.
     *
     * 当前测试环境使用真实 MySQL，历史残留的 migrations 记录
     * 可能导致 `migrate:fresh` 在删除不存在的数据表时报错。
     * 这里直接按数据库当前真实存在的表做清理，再执行 migrate。
     */
    protected function refreshTestDatabase(): void
    {
        $this->dropAllTables();
        $this->ensureMigrationRepositoryExists();
        $this->artisan('migrate', $this->migrateUsing());
        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * 删除当前连接中实际存在的全部数据表.
     */
    protected function dropAllTables(): void
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
     * 重建 Laravel 迁移仓库表.
     */
    protected function ensureMigrationRepositoryExists(): void
    {
        DB::connection()->statement('CREATE TABLE IF NOT EXISTS `migrations` (`id` int unsigned NOT NULL AUTO_INCREMENT, `migration` varchar(255) NOT NULL, `batch` int NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}
