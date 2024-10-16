<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2024 重庆胖头网络技术有限公司，并保留所有权利。
 *  网站地址: https://www.pangtou.com
 *  ----------------------------------------------------------------------------
 *  尊敬的用户，
 *     感谢您对我们产品的关注与支持。我们希望提醒您，在商业用途中使用我们的产品时，请务必前往官方渠道购买正版授权。
 *  购买正版授权不仅有助于支持我们不断提供更好的产品和服务，更能够确保您在使用过程中不会引起不必要的法律纠纷。
 *  正版授权是保障您合法使用产品的最佳方式，也有助于维护您的权益和公司的声誉。我们一直致力于为客户提供高质量的解决方案，并通过正版授权机制确保产品的可靠性和安全性。
 *  如果您有任何疑问或需要帮助，我们的客户服务团队将随时为您提供支持。感谢您的理解与合作。
 *  诚挚问候，
 *  【重庆胖头网络技术有限公司】
 *  ============================================================================
 *  Author:    Zane
 *  Homepage:  https://www.pangtou.com
 *  Email:     vip@pangtou.com
 */

namespace PTAdmin\Easy\Contracts;

use PTAdmin\Easy\Model\ModField;

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
     * 组件类型.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * 添加字段时需要设置的参数信息.
     *
     * @return array
     */
    public function getColumnArguments(): array;

    /**
     * 设置字段对象.
     *
     * @param array|ModField $field
     *
     * @return $this
     */
    public function setData($field): self;

    /**
     * 获取组件配置.
     */
    public function getSetup(): array;

    /**
     * 获取扩展配置.
     *
     * @return array
     */
    public function getExtra(): array;

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
}
