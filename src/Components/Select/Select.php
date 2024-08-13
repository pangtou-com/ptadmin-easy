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

namespace PTAdmin\Easy\Components\Select;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Type;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use PTAdmin\Easy\Components\AbstractComponent;
use PTAdmin\Easy\Components\Extend\TinyInteger;

class Select extends AbstractComponent
{
    /** @var string 键值对 */
    public const KEY_VAL = 'key-val';

    /** @var string 默认的多行文本 */
    public const TEXTAREA = 'textarea';

    /** @var string 基于配置信息 */
    public const CONFIG = 'config';
    protected $option = true;
    protected $type = 'tinyInteger';

    /**
     * @return string
     */
    public function getColumnType(): string
    {
        if (!Type::hasType('tinyinteger')) {
            try {
                Type::addType('tinyinteger', TinyInteger::class);
            } catch (Exception $e) {
            }
        }

        return $this->type;
    }

    /**
     * 排除在创建过程中多余的数据.
     *
     * @param $data
     *
     * @return mixed
     */
    public function parserSetup($data)
    {
        if (self::KEY_VAL === $data['type']) {
            $keys = $data['key'];
            $value = $data['value'];
            $temp = [];
            foreach ($keys as $key => $val) {
                $temp[] = ['value' => $val, 'label' => $value[$key] ?? ''];
            }
            $data['data'] = $temp;
            unset($data['key'], $data['value'], $data['config'], $data['content']);
        } elseif (self::TEXTAREA === $data['type']) {
            unset($data['key'], $data['value'], $data['config']);
        } else {
            unset($data['key'], $data['value'], $data['content']);
        }

        return $data;
    }

    public function getColumnOptions($data): array
    {
        if (isset($data['type'])) {
            switch ($data['type']) {
                case self::KEY_VAL:
                    return $data['data'] ?? [];

                case self::TEXTAREA:
                    return $this->parserOptionsTextarea($data['content'] ?? '');

                case self::CONFIG:
                default:
                    return $this->parserOptionsConfig($data['config'] ?? '');
            }
        }

        return [];
    }

    protected function isAllowUpdate(): bool
    {
        return false;
    }

    /**
     * 解析选项配置为 多行文本类型.
     * 支持格式有： key=value 或 value
     * 1. 1=男
     *    2=女.
     * 2. 男
     *    女.
     *
     * @param $content
     *
     * @return array
     */
    private function parserOptionsTextarea($content): array
    {
        if (blank($content)) {
            return [];
        }
        $content = explode("\n", $content);
        $res = [];
        foreach ($content as $key => $datum) {
            $temp = explode('=', $datum);
            if (1 === \count($temp)) {
                $res[] = [
                    'label' => $temp[0],
                    'value' => $key,
                ];
            }
            if (2 <= \count($temp)) {
                $res[] = [
                    'label' => $temp[1],
                    'value' => $temp[0],
                ];
            }
        }

        return $res;
    }

    /**
     * 解析基于配置类型.
     *
     * @param $keyword
     *
     * @return array
     */
    private function parserOptionsConfig($keyword): array
    {
        // 首先判断是否为 $开头，当参数为$开头时以系统配置为准
        if (Str::startsWith($keyword, '$')) {
            $keyword = Str::after($keyword, '$');

            return $this->parserOptionsTextarea(App::make('setting')->get($keyword, []));
        }

        $val = App::make('config')->get($keyword);
        if (blank($val)) {
            $val = $this->parserOptionsTextarea(App::make('setting')->get($keyword, []));
        }

        return (array) $val;
    }
}
