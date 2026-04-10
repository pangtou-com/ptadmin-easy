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
        // $this->publishing();
    }

    public function publishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/easy.php' => config_path('easy.php'),
            ], 'ptadmin-easy');
        }
    }
}
