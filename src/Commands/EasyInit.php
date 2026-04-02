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
use PTAdmin\Easy\Engine\Docx\Common;

class EasyInit extends Command
{
    protected $signature = 'easy:init';
    protected $description = '项目初始化';

    public function handle(): int
    {
        $docxLists = ['docx', 'docx_field', 'docx_log'];
        $docx = [];
        $this->info('开始加载模块文件...');
        foreach ($docxLists as $item) {
            $docx[$item] = Easy::docx($item, Common::INTERNAL_NAMESPACE);
        }

        $this->info('模块文件加载完成, 开始初始化数据库表结构...');
        foreach ($docx as $key => $item) {
            $this->info("数据表：【{$key}】创建中...");
            $docx[$key] = Easy::schema($item)->forceCreate();
        }

        $this->info('数据库表结构初始化完成, 开始初始化数据...');
        sleep(1);

        try {
            DB::transaction(function () use ($docx): void {
                foreach ($docx as $item) {
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
