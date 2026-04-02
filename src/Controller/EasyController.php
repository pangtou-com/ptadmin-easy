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
use PTAdmin\Easy\Exceptions\EasyException;
use PTAdmin\Easy\Utils\ResponseVo;

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
        $filterMap = Easy::document($this->getDocx(), $this->getModule());

        $data = $filterMap->page();

        return ResponseVo::pages($data);
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
        return ResponseVo::success();
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
        return ResponseVo::success();
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
        return ResponseVo::success();
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
        return ResponseVo::success();
    }

    /**
     * 新增.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        Easy::document($this->getDocx(), $this->getModule())->store($request->all());

        return ResponseVo::success();
    }

    /**
     * @param $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id, Request $request): \Illuminate\Http\JsonResponse
    {
        return ResponseVo::success();
    }

    protected function getDocx()
    {
        if (null === $this->docx) {
            throw new EasyException('请设置docx');
        }

        return $this->docx;
    }

    protected function getModule()
    {
        return $this->module;
    }
}
