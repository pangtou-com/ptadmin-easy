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

namespace PTAdmin\Easy\Engine\Model\Traits;

use Illuminate\Support\Str;

trait AttributesTrait
{
    /**
     * 设置可变属性.
     *
     * @param $key
     * @param $val
     * @param mixed $model
     *
     * @return mixed
     */
    public function setMutatedAttributeValue($model, $key, $val)
    {
        $field = $this->docx->getField($key);
        if (null !== $field) {
            $val = $field->setComponentAttributeValue($model, $val);
        }
        $control = $this->getControl();
        if (null === $control) {
            return $val;
        }
        $method = 'set'.Str::studly($key).'Attribute';
        if (method_exists($control, $method)) {
            return $control->{$method}($val, $this);
        }

        return $val;
    }

    /**
     * 数据读取时执行属性修改.
     *
     * @param mixed $key
     * @param mixed $val
     * @param mixed $model
     */
    public function getMutatedAttributeValue($model, $key, $val)
    {
        $field = $this->docx->getField($key);
        if (null !== $field) {
            $val = $field->getComponentAttributeValue($model, $val);
        }
        $control = $this->getControl();
        if (null === $control) {
            return $val;
        }
        $method = 'get'.Str::studly($key).'Attribute';
        if (method_exists($control, $method)) {
            return $control->{$method}($val, $this);
        }

        return $val;
    }

    /**
     * 获取追加字段的值.
     *
     * @param $model
     *
     * @return mixed
     */
    public function getAppendsValue($model)
    {
        return $this->docx->getAppendsValue($model);
    }

    /**
     * 获取追加字段的值.
     *
     * @param $model
     * @param $key
     *
     * @return mixed
     */
    public function getAppendValue($model, $key)
    {
        $values = $this->getAppendsValue($model);

        return $values[$key] ?? null;
    }

    /**
     * 设置属性值.
     *
     * @param mixed $data
     * @param mixed $model
     *
     * @return mixed
     */
    protected function setAttributeValue($model, $data)
    {
        foreach ($data as $key => &$value) {
            $value = $this->setMutatedAttributeValue($model, $key, $value);
        }
        unset($value);

        return $data;
    }
}
