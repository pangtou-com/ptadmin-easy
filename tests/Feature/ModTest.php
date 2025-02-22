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

use Illuminate\Validation\ValidationException;
use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Model\Mod;

beforeEach(function (): void {
    $this->modName = 'test_mod_'.time();
});

it('【Mod】新增', function (): void {
    $data = ['title' => '测试模块', 'table_name' => 'test_table_'.time(), 'intro' => '测试模块简介'];
    $mod = Easy::mod()->store($data, $this->modName);
    $this->assertTrue(Mod::query()->where('title', $mod->title)->exists(), '新增模块失败');

    $this->expectException(ValidationException::class);
    Easy::mod()->store($data, $this->modName);
});

it('【Mod】缺少必填项', function (): void {
    $this->expectException(ValidationException::class);
    Easy::mod()->store([
        'table_name' => 'test_table_'.time(),
        'intro' => '测试模块简介',
    ], $this->modName);
});

it('【Mod】不允许修改表名称', function (): void {
    $data = ['title' => '测试模块', 'table_name' => 'test_table_'.time(), 'intro' => '测试模块简介'];
    $mod = Easy::mod()->store($data, $this->modName);
    Easy::mod()->edit([
        'table_name' => 'test_table_1'.time(),
        'intro' => '测试模块简介',
        'title' => '测试修改名称',
    ], $mod->id);
    $this->assertTrue(Mod::query()->where('table_name', $data['table_name'])->exists(), '不允许修改表名');
});

it('【Mod】编辑内容', function (): void {
    $data = ['title' => '测试模块', 'table_name' => 'test_table_'.time(), 'intro' => '测试模块简介'];
    $mod = Easy::mod()->store($data, $this->modName);

    Easy::mod()->edit(['title' => '测试是可以编辑的', 'intro' => '测试模块简介'], $mod->id);
    $this->assertTrue(Mod::query()->where('title', '测试是可以编辑的')->exists(), '编辑失败');
});
