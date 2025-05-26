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

interface IDocxField
{
    /**
     * 获取字段名称.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * 获取字段类型.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * 获取字段选项信息.
     *
     * @return array
     */
    public function getOptions(): array;

    // 是否关联字段
    public function isRelation(): bool;

    // 是否追加字段
    public function isAppend(): bool;

    // 获取追加字段名称
    public function getAppendName(): string;

    /**
     * 获取关联关系.
     *
     * @return array
     */
    public function getRelation(): array;

    /**
     * 字段评论备注.
     *
     * @return string
     */
    public function getComment(): string;

    public function getLabel(): string;

    public function getRules($id): array;

    /**
     * 读取组件可变属性值，.
     *
     * @param mixed $val   要转化的数据
     * @param mixed $model
     *
     * @return mixed
     */
    public function getComponentAttributeValue($model, $val);

    /**
     * 设置组件可变属性值.
     *
     * @param $val
     * @param mixed $model
     *
     * @return mixed
     */
    public function setComponentAttributeValue($model, $val);

    /**
     * 字段默认值
     */
    public function getDefault();
    public function required(): ?string;

    /**
     * 获取元数据.
     *
     * @param $key
     * @param $default
     *
     * @return mixed
     */
    public function getMetadata($key = null, $default = null);

    /**
     * 字段是否存在.
     *
     * @return bool
     */
    public function exists(): bool;

    /**
     * 是否为虚拟字段.
     *
     * @return bool
     */
    public function isVirtual(): bool;

    public function getDocx(): IDocx;

    public function getComponent(): IComponent;
}
