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

namespace PTAdmin\Easy\Service\Traits;

/**
 * 数据格式化功能.
 */
trait ModFormat
{
    /** @var bool 设置是否需要执行格式化方法 */
    protected $is_format = true;

    /** @var callable 自定义格式化方法 */
    protected $format_callback;

    /**
     * 开始格式化.
     *
     * @param mixed $callback 自定义格式化回调方法
     *
     * @return ModFormat|\PTAdmin\Easy\Service\Handler
     */
    public function format($callback = null): self
    {
        if (null !== $callback && \is_callable($callback)) {
            $this->format_callback = $callback;
        }
        $this->is_format = true;

        return $this;
    }

    /**
     * 关闭格式化功能.
     *
     * @return mixed
     */
    public function cancelFormatting()
    {
        $this->is_format = false;

        return $this;
    }

    /**
     * 执行格式化方法.
     *
     * @param $data
     * @param bool $readOrStore 格式化时是存储数据格式化还是读取数据格式化 false 读取数据，true 存储数据
     *
     * @return mixed
     */
    protected function actionFormat($data, bool $readOrStore = false)
    {
        if (!$this->is_format) {
            return $data;
        }
        // 自定义格式化方法，只有在读取数据时才允许使用自定义方法
        if (null !== $this->format_callback && !$readOrStore) {
            $callback = $this->format_callback;
            $data = $callback($data);
        } else {
            // 根据字段类型给每个字段格式化内容
            // todo 待处理key可能重复的情况
            foreach ($data as $key => &$item) {
                $temp = $this->formatField($key, $item, $readOrStore);
                if ($item !== $temp) {
                    $item[$key.'_text'] = $item;
                }
            }
            unset($item);
        }

        return $data;
    }

    /**
     * 存储数据格式化.
     *
     * @param $data
     *
     * @return array
     */
    protected function saveFormat($data): array
    {
        return $this->actionFormat($data, true);
    }

    /**
     * 读取数据格式化.
     *
     * @param $data
     *
     * @return array
     */
    protected function readFormat($data): array
    {
        return $this->actionFormat($data);
    }

    /**
     * 根据字段信息格式化数据.
     *
     * @param $name
     * @param $val
     * @param bool $readOrStore
     *
     * @return mixed
     */
    protected function formatField($name, $val, bool $readOrStore = false)
    {
        // 如果当前字段不存在与我们的模型管理中，则不进行数据转换处理
        if (!$this->isFieldModField($name)) {
            return $val;
        }
        $component = $this->getComponent($name);

        return $readOrStore ? $component->saveFormat($val) : $component->toFormat($val);
    }

    /**
     * 批量执行格式化方法，列表数据的格式化方法执行.
     *
     * @param $data
     *
     * @return mixed
     */
    protected function actionsFormat($data)
    {
        foreach ($data as &$datum) {
            $datum = $this->actionFormat($datum);
        }
        unset($datum);

        return $data;
    }
}
