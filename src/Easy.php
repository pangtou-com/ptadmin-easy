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

namespace PTAdmin\Easy;

use PTAdmin\Easy\Components\ComponentManager;
use PTAdmin\Easy\Exceptions\InvalidDataException;
use PTAdmin\Easy\Model\Mod;
use PTAdmin\Easy\Service\Handler;
use PTAdmin\Easy\Service\ModFieldService;
use PTAdmin\Easy\Service\ModService;
use PTAdmin\Easy\Service\Render;

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
     * 获取渲染器对象
     *
     * @param int|Mod|string $code  支持传入模型、模型名称、模型ID
     * @param bool           $force 是否强制更新
     *
     * @return Render
     */
    public static function render($code, bool $force = false): Render
    {
        return Render::make($code, $force);
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
