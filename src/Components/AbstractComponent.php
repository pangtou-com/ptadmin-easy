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

namespace PTAdmin\Easy\Components;

use Illuminate\Support\Arr;
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Exceptions\EasyException;
use PTAdmin\Easy\Exceptions\InvalidDataException;
use PTAdmin\Easy\Model\ModField;

abstract class AbstractComponent implements IComponent
{
    /** @var string 组件类型 */
    protected $type;

    /** @var string 字段类型 */
    protected $column_type;

    /** @var array 字段新增保存时规则 */
    protected $rules = [];

    /** @var array 数据新增时需要保存的额外数据 */
    protected $extra;

    /** @var array 数据新增时需要保存的额外数据：如rules、style、class、prop等 */
    protected $setup;

    /** @var array|ModField */
    protected $data;

    /** @var bool 是否为选项类型组件 */
    protected $option = false;

    /** @var bool 是否存储为数字类型 */
    protected $number = false;

    /** @var ComponentExtendManager */
    protected $extend;

    /**
     * @param null|mixed $data
     *
     * @throws InvalidDataException
     */
    public function __construct($data = null)
    {
        if (null !== $data) {
            $this->setData($data);
        }
    }

    /**
     * 加载扩展数据.
     *
     * @return ComponentExtendManager
     */
    public function withExtend(): ComponentExtendManager
    {
        if (null === $this->extend) {
            $this->extend = new ComponentExtendManager($this);
        }

        return $this->extend;
    }

    /**
     * 数据表存储类型.
     *
     * @return string
     */
    public function getColumnType(): string
    {
        return $this->column_type;
    }

    public function getExtra(): array
    {
        if (null === $this->extra) {
            $this->extra = $this->getModField('extra');
        }

        return Arr::wrap($this->extra);
    }

    public function getSetup(): array
    {
        if (null === $this->setup) {
            $this->setup = $this->getModField('setup');
        }

        return Arr::wrap($this->setup);
    }

    /**
     * 设置字段数据.
     *
     * @param array|ModField $field
     *
     * @throws InvalidDataException
     *
     * @return IComponent
     */
    public function setData($field): IComponent
    {
        if (\is_array($field) || $field instanceof ModField) {
            $this->data = $field;

            return $this;
        }

        throw new InvalidDataException('无效的数据格式，格式应为数组或ModField对象');
    }

    public function getColumnArguments(): array
    {
        return [$this->getModField('name')];
    }

    /**
     * 获取模型数据.
     *
     * @param $key
     *
     * @return array|mixed|ModField
     */
    public function getModField($key = null)
    {
        if (null === $this->data) {
            throw new EasyException('数据对象未设置');
        }

        if (null !== $key) {
            if ($this->data instanceof ModField) {
                return $this->data->getAttribute($key);
            }

            return data_get($this->data, $key);
        }

        return $this->data;
    }

    public function parserSetup($data)
    {
        return $data;
    }

    public function isOption(): bool
    {
        return $this->option;
    }

    /**
     * 是否为数字类型.
     *
     * @return bool
     */
    public function isNumber(): bool
    {
        return $this->number;
    }

    public function getColumnOptions($data)
    {
        return $data;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function saveFormat($value)
    {
        return $value;
    }

    public function toFormat($value)
    {
        return $value;
    }
}
