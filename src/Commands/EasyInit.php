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

namespace PTAdmin\Easy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Engine\Resource\ResourceNamespace;

class EasyInit extends Command
{
    protected $signature = 'easy:init';
    protected $description = '项目初始化';

    public function handle(): int
    {
        $resourceSchemas = [];
        $resourceTables = ['docx', 'docx_field', 'docx_log'];
        $this->info('开始加载模块文件...');
        foreach ($resourceTables as $item) {
            $resourceSchemas[$item] = Easy::schema($item, ResourceNamespace::INTERNAL_NAMESPACE)->raw();
        }

        $this->info('模块文件加载完成, 开始初始化数据库表结构...');
        foreach ($resourceSchemas as $key => $item) {
            $this->info("数据表：【{$key}】创建中...");
            $resourceSchemas[$key] = Easy::table($item)->forceCreate();
        }

        $this->info('数据库表结构初始化完成, 开始初始化数据...');
        sleep(1);

        try {
            DB::transaction(function () use ($resourceSchemas): void {
                foreach ($resourceSchemas as $item) {
                    $item->save();
                }
            });
            $this->info('数据初始化完成');
        } catch (\Exception $e) {
            $this->error('初始化数据失败：'.$e->getMessage());
        }

        return 0;
    }
}
