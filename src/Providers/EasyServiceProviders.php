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

namespace PTAdmin\Easy\Providers;

use Illuminate\Support\ServiceProvider;
use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Contracts\IResourceField;
use PTAdmin\Easy\Engine\EasyManager;
use PTAdmin\Easy\Engine\Resource\ResourceDefinition;
use PTAdmin\Easy\Engine\Resource\ResourceField;

class EasyServiceProviders extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('easy', function () {
            return new EasyManager();
        });
        $this->app->bind(IResource::class, ResourceDefinition::class);
        $this->app->bind(IResourceField::class, ResourceField::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'ptadmin-easy');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishing();
    }

    public function publishing(): void
    {
        if ($this->app->runningInConsole()) {
            $configPaths = [
                __DIR__.'/../../config/easy.php' => config_path('easy.php'),
            ];
            $migrationPaths = [
                __DIR__.'/../../database/migrations' => database_path('migrations'),
            ];
            $langPaths = [
                __DIR__.'/../../lang' => resource_path('lang/vendor/ptadmin-easy'),
            ];

            $this->publishes($configPaths, 'ptadmin');
            $this->publishes($configPaths, 'ptadmin-config');
            $this->publishes($configPaths, 'ptadmin-easy');
            $this->publishes($configPaths, 'config');

            $this->publishes($migrationPaths, 'ptadmin');
            $this->publishes($migrationPaths, 'ptadmin-migrations');
            $this->publishes($migrationPaths, 'ptadmin-easy-migrations');
            $this->publishes($migrationPaths, 'migrations');

            $this->publishes($langPaths, 'ptadmin');
            $this->publishes($langPaths, 'ptadmin-lang');
            $this->publishes($langPaths, 'ptadmin-easy-lang');
        }
    }
}
