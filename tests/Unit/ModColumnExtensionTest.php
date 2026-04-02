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

use PTAdmin\Easy\Exceptions\InvalidDataException;
use PTAdmin\Easy\Service\Extensions\ModColumnExtension;

// 字段扩展测试
beforeEach(function (): void {
    $this->modName = 'test';
});

it('【column】新增扩展', function (): void {
    ModColumnExtension::setExtension($this->modName, [
        ['type' => 'connect', 'name' => 'archive_id', 'title' => '主表内容', 'default_val' => 0],
    ]);
    $extend = ModColumnExtension::getExtension($this->modName);

    expect($extend)->toHaveCount(1);
});

it('【column】重置扩展', function (): void {
    $extend = ModColumnExtension::getExtension($this->modName);
    expect($extend)->toHaveCount(1);
    ModColumnExtension::setExtension($this->modName, [
        ['type' => 'connect11', 'name' => 'archive_id', 'title' => '主表内容', 'default_val' => 0],
    ]);

    expect(ModColumnExtension::getExtension($this->modName))->toHaveCount(1);
});

it('【column】插入扩展', function (): void {
    ModColumnExtension::setExtension($this->modName, [
        ['type' => 'connect', 'name' => 'archive_id', 'title' => '主表内容', 'default_val' => 0],
    ]);
    expect(ModColumnExtension::getExtension($this->modName))->toHaveCount(1);

    ModColumnExtension::insertExtension($this->modName, [
        ['type' => 'connect', 'name' => 'archive_1_id', 'title' => '主表内容', 'default_val' => 0],
    ]);

    expect(ModColumnExtension::getExtension($this->modName))->toHaveCount(2);
    ModColumnExtension::setExtension($this->modName, [
        ['type' => 'connect', 'name' => 'archive_id', 'title' => '主表内容', 'default_val' => 0],
        ['type' => 'connect1', 'name' => 'archive_id', 'title' => '主表内容', 'default_val' => 0],
    ]);

    expect(ModColumnExtension::getExtension($this->modName))->toHaveCount(2)
        ->and(ModColumnExtension::getExtension('ccc'))->toHaveCount(0);
});

it('【column】校验异常', function (): void {
    $extend = ModColumnExtension::getExtension($this->modName);

    expect($extend)->toHaveCount(2);

    try {
        ModColumnExtension::checkExtension($extend);
        $this->assertTrue(false);
    } catch (InvalidDataException $e) {
        $this->assertTrue(true);
    }
});

it('【column】清理数据', function (): void {
    ModColumnExtension::setExtension($this->modName, []);
    $this->assertTrue(true);
});
