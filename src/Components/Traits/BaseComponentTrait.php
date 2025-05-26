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

namespace PTAdmin\Easy\Components\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

trait BaseComponentTrait
{
    use SelectComponentTrait;
    use TextComponentTrait;

    /**
     * 读取时判断是否支持属性修改.
     *
     * @return bool
     */
    public function hasGetMutator(): bool
    {
        return method_exists($this, $this->getGetMutatorMethod());
    }

    /**
     * 设置时判断是否支持属性修改.
     *
     * @return bool
     */
    public function hasSetMutator(): bool
    {
        return method_exists($this, $this->getSetMutatorMethod());
    }

    /**
     * 获取属性修改器方法.
     *
     * @return string
     */
    public function getGetMutatorMethod(): string
    {
        return 'get'.ucfirst($this->getType()).'Attribute';
    }

    /**
     * 获取属性修改器方法.
     *
     * @return string
     */
    public function getSetMutatorMethod(): string
    {
        return 'set'.ucfirst($this->getType()).'Attribute';
    }

    /**
     * 密码组件设置属性值.
     *
     * @param $val
     *
     * @return string
     */
    protected function setPasswordAttribute($val): string
    {
        if ('' !== $val && null !== $val) {
            return Hash::make($val);
        }

        return $val;
    }


    /**
     * 日期组件获取属性值.
     *
     * @param $val
     * @return false|string
     */
    public function getDateAttribute($val)
    {
        if (0 === (int)$val) {
            return  '';
        }

        $format = $this->getMetadata("extends.format", "Y-m-d");

        return date($format, $val);
    }


    /**
     * 日期组件设置属性值.
     *
     * @param $val
     * @return float|int|string
     */
    public function setDateAttribute($val)
    {
        if (null === $val || '' === $val) {
            return 0;
        }

        return Carbon::make($val)->timestamp;
    }


    /**
     * 时间日期组件获取属性值.
     *
     * @param $val
     *
     * @return string
     */
    protected function getDatetimeAttribute($val): string
    {
        if ((int)$val === 0) {
            return '';
        }
        $format = $this->getMetadata("extends.format", "Y-m-d H:i:s");

        return date($format, $val);
    }


    /**
     * 时间日期组件设置属性值.
     *
     * @param $val
     *
     * @return int
     */
    protected function setDatetimeAttribute($val): int
    {
        if (null === $val || '' === $val) {
            return 0;
        }

        return Carbon::make($val)->timestamp;
    }


    /**
     * 金额组件获取属性值.
     *
     * @param $val
     * @return float
     */
    protected function getAmountAttribute($val): float
    {
        return (float) bcdiv((string) $val, '100', 2);
//        return number_format($val/100, 2, '.', '');
    }


    /**
     * 金额组件设置属性值.
     *
     * @param $val
     * @return int
     */
    protected function setAmountAttribute($val): int
    {
//        return (int)(round($val, 2) * 100);
        return (int)bcmul((string)$val, '100');
    }


    /**
     * 文件组件获取属性值.
     *
     * @param $val
     * @return mixed|string|null
     */
    protected function getFileAttribute($val)
    {
        if (null === $val) {
            return null;
        }

        return 1 === $this->getMetadata("extends.limit",1) ? $val : json_decode($val, true);
    }


    /**
     * 文件组件设置属性值.
     *
     * @param $val
     * @return false|string|null
     */
    protected function setFileAttribute($val)
    {
        if (null === $val || '' === $val) {
            return null;
        }
        if (is_array($val)) {
            return json_encode($val);
        }

        // 若存在分隔符，则进行分割
        $separator = $this->getMetadata("extends.separator", ',');
        $val = explode($separator, (string)$val);
        if (0 === count($val)) {
            return null;
        }
        if (1 === $this->getMetadata("extends.limit",1)) {
            return $val[0];
        }

        return 1 === $this->getMetadata("extends.limit",1) ? $val[0] : json_encode($val);
    }

}
