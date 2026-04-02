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

namespace PTAdmin\Easy\Controller;

use Illuminate\Http\Request;
use PTAdmin\Easy\Utils\ResponseVo;

class DocxLogController extends EasyController
{
    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        return ResponseVo::success();
    }

    public function edit($id, Request $request): \Illuminate\Http\JsonResponse
    {
        return ResponseVo::success();
    }
}
