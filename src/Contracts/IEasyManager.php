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

namespace PTAdmin\Easy\Contracts;

use PTAdmin\Easy\Core\Query\BuilderQueryApplier;

interface IEasyManager
{
    /**
     * 通用 Builder 查询 DSL 适配器.
     */
    public function builderQuery(): BuilderQueryApplier;

    /**
     * 是否为开发模式.
     *
     * @return bool
     */
    public function isDevelop(): bool;
}
