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
use PTAdmin\Easy\Easy;

abstract class EasyController
{
    protected $docx;
    protected $module;

    /**
     * 详情接口.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $filter = $request->get('filter', []);
        $with = $request->get('with', []);
        $filterMap = Easy::document($this->docx, $this->module);

        $data = $filterMap->page();

        return $this->response($data);
    }

    /**
     * 详情.
     *
     * @param $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail($id, Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->response([]);
    }

    /**
     * 树形结构.
     *
     * @param $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tree($id, Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->response([]);
    }

    /**
     * 获取层级结构.
     *
     * @param $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function level($id, Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->response([]);
    }

    /**
     * 编辑.
     *
     * @param $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id, Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->response([]);
    }

    /**
     * 新增.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        Easy::document($this->docx, $this->module)->store($request->all());

        return $this->response([]);
    }

    /**
     * @param $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id, Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->response([]);
    }

    protected function response($data = []): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $data,
            'message' => 'success',
        ]);
    }
}
