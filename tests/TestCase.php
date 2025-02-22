<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2025 【重庆胖头网络技术有限公司】，并保留所有权利。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $path = __DIR__.'/../database/migrations/2024_06_13_154934_mod_init.php';

        include_once $path;
        self::$migration = new \ModInit();
        self::$migration->up();
    }
}
