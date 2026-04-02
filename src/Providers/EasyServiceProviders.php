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

namespace PTAdmin\Easy\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PTAdmin\Easy\Contracts\IDocx;
use PTAdmin\Easy\Contracts\IDocxField;
use PTAdmin\Easy\Engine\Docx\Docx;
use PTAdmin\Easy\Engine\Docx\DocxField;
use PTAdmin\Easy\Engine\EasyManager;

class EasyServiceProviders extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('easy', function () {
            return new EasyManager();
        });
        $this->app->bind(IDocx::class, Docx::class);
        $this->app->bind(IDocxField::class, DocxField::class);
    }

    public function boot(): void
    {
        // $this->publishing();
        $this->easyRoute();
        $this->commands([
            \PTAdmin\Easy\Commands\EasyInit::class,
        ]);
    }

    public function publishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/easy.php' => config_path('easy.php'),
            ], 'ptadmin-easy');
        }
    }

    private function easyRoute(): void
    {
        Route::middleware(['api'])->prefix(config('app.prefix', 'system'))->group(\dirname(__DIR__).\DIRECTORY_SEPARATOR.'Route/admin.php');
    }
}
