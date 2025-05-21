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
use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Easy;

class DocxController extends EasyController
{
    protected $docx = 'docx';
    protected $module = '__easy__';

    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        DB::transaction(function () use ($request): void {
            Easy::document($this->docx)->store($request->all());
            Easy::schema($request->all())->create();
        });

        return $this->response();
    }

    public function edit($id, Request $request): \Illuminate\Http\JsonResponse
    {
        DB::transaction(function () use ($request): void {
            Easy::document($this->docx)->update($request->all());
            //  Easy::schema($request->all())->update();
        });

        return $this->response();
    }
}
