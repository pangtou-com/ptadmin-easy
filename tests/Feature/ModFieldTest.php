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

beforeEach(function (): void {
    app()->setBasePath(config('test_path'));
});

// 测试用例示例
it('【docx】allowRecycle', function (): void {
    $docx = \PTAdmin\Easy\Easy::docx('docx_allow_recycle');

    $this->assertTrue($docx->allowRecycle());
    $this->assertTrue($docx->trackChanges());
    $this->assertTrue($docx->trackChanges());
});

// 测试用例示例
it('【docx】trackChanges', function (): void {
    $docx = \PTAdmin\Easy\Easy::docx('docx');
    $this->assertTrue($docx->trackChanges());
});
