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

/**
 * 字段处理器.
 */
interface IComponent
{
    /**
     * 字段列类型.
     *
     * @return string
     */
    public function getColumnType(): string;

    /**
     * 添加字段时需要设置的参数信息.
     *
     * @return array
     */
    public function getColumnArguments(): array;

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    public function parserSetup($data);

    /**
     * 是否为选项类型组件.
     *
     * @return bool
     */
    public function isOption(): bool;

    /**
     * 是否为数字类型.
     *
     * @return bool
     */
    public function isNumber(): bool;

    /**
     * 当列表为选项类型时，通过方法可获取选项类型数据.
     *
     * @param $data
     *
     * @return mixed
     */
    public function getColumnOptions($data);

    /**
     * 数据读取格式化.
     *
     * @param $value
     *
     * @return mixed
     */
    public function toFormat($value);

    /**
     * 存储格式化.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function saveFormat($value);

    /**
     * 是否为虚拟字段.
     *
     * @return bool
     */
    public function isVirtual(): bool;
}
