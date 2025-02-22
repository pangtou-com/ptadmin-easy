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

namespace PTAdmin\Easy;

use PTAdmin\Easy\Components\ComponentManager;
use PTAdmin\Easy\Exceptions\InvalidDataException;
use PTAdmin\Easy\Model\Mod;
use PTAdmin\Easy\Service\Handler;
use PTAdmin\Easy\Service\ModFieldService;
use PTAdmin\Easy\Service\ModService;

/**
 * @method static ModService mod()        模块管理对象
 * @method static ModFieldService field() 模块字段对象
 *
 * @see https://docs.pangtou.com/docs/easy-forms
 */
class Easy
{
    private static $handler = [
        'mod' => ModService::class,
        'field' => ModFieldService::class,
    ];

    /**
     * @param mixed $name
     * @param mixed $arguments
     *
     * @throws InvalidDataException
     */
    public static function __callStatic($name, $arguments)
    {
        if (!isset(self::$handler[$name])) {
            throw new InvalidDataException("【{$name}】未定义");
        }

        return new self::$handler[$name](...$arguments);
    }

    /**
     * 获取数据处理对象
     *
     * @param int|Mod|string $code  支持传入模型、模型名称、模型ID
     * @param bool           $force 是否强制更新
     *
     * @return Handler
     */
    public static function handler($code, bool $force = false): Handler
    {
        return Handler::make($code, $force);
    }

    /**
     * 获取组件选项类型.
     *
     * @return array
     */
    public static function getComponentsOptions(): array
    {
        return ComponentManager::getComponentOptions();
    }
}
