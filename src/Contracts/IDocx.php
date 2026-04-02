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

namespace PTAdmin\Easy\Contracts;

use PTAdmin\Easy\Engine\Model\Document;

interface IDocx
{
    /**
     * 有前缀的数据表名称，.
     *
     * @return string
     */
    public function getTable(): string;

    /**
     * 所有字段属性.
     *
     * @return array
     */
    public function getAttributes(): array;

    /**
     * 获取可填充字段.
     *
     * @return array
     */
    public function getFillable(): array;

    /**
     * 原始数据表名称，没有增加前缀的表名称.
     *
     * @return string
     */
    public function getRawTable(): string;

    /**
     * 校验规则.
     *
     * @param mixed $id 当ID不为0时表示在修改状态下获取校验规则
     */
    public function getRules($id): array;

    /**
     * 备注信息.
     *
     * @return mixed
     */
    public function getComment();

    /**
     * 主健ID.
     *
     * @return string
     */
    public function getPrimaryKey(): string;

    /**
     * 返回所有字段对象数组.
     *
     * @return array
     */
    public function getFields(): array;

    /**
     * 获取文档字段对象
     *
     * @param string $name
     *
     * @return null|IDocxField
     */
    public function getField(string $name): ?IDocxField;

    /**
     * 获取文档对象.
     *
     * @return Document
     */
    public function document(): Document;

    /**
     * 获取自定义控制器.
     *
     * @return mixed
     */
    public function getControl();

    /**
     * 获取关联关系.
     *
     * @return mixed
     */
    public function getRelations();

    public function getAppends();

    /**
     * 允许导入.
     *
     * @return bool
     */
    public function allowImport(): bool;

    /**
     * 允许导出.
     *
     * @return bool
     */
    public function allowExport(): bool;

    /**
     * 允许拷贝.
     *
     * @return bool
     */
    public function allowCopy(): bool;

    /**
     * 允许回收站.
     *
     * @return bool
     */
    public function allowRecycle(): bool;

    /**
     * 允许追踪修改.
     *
     * @return bool
     */
    public function trackChanges(): bool;

    /**
     * 获取追加字段的值.
     *
     * @param $model
     *
     * @return array
     */
    public function getAppendsValue($model): array;

    public function toArray(): array;
}
