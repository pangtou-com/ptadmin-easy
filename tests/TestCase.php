<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2025 【重庆胖头网络技术有限公司】。
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
        $app['config']->set('test_path', \dirname(__DIR__).\DIRECTORY_SEPARATOR.'tests'.\DIRECTORY_SEPARATOR.'Stubs');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'mysql',
            'database' => 'test',
            'prefix' => '',
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => 'root',
            'port' => 3306,
        ]);
    }
}
