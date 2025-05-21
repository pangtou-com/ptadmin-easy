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
}
