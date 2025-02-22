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

beforeEach(function (): void {
    $this->mod = \PTAdmin\Easy\Easy::mod()->store([
        'title' => '测试模块',
        'table_name' => 'test_table_'.time(),
        'intro' => '测试模块简介',
    ], 'test');
});

it('【field】字段新增', function (): void {
    \PTAdmin\Easy\Easy::field()->store([
        'mod_id' => $this->mod->id,
        'name' => 'test_field_'.time(),
        'title' => '测试字段',
        'type' => 'text',
        'default_val' => '默认值',
        'is_show' => 1,
    ]);

    $this->assertTrue(true);
});

it('【field】字段编辑', function (): void {
    $this->assertTrue(true);
});

it('【field】字段删除', function (): void {
    $this->assertTrue(true);
});
