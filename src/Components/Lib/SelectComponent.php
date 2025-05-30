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

namespace PTAdmin\Easy\Components\Lib;

use PTAdmin\Easy\Components\AbstractComponent;

/**
 * 选项类型组件.
 */
class SelectComponent extends AbstractComponent
{
    protected $column_type = 'tinyinteger';
    private $component;
    private $args;

    public function getColumnArguments(): array
    {
        if (null !== $this->component) {
            $args = $this->component->getColumnArguments();
            array_shift($args);
            array_unshift($args, $this->filed->getName());

            return array_values($args);
        }
        if (null !== $this->args && \count($this->args) > 0) {
            $args = $this->args;
            array_unshift($args, $this->filed->getName());

            return array_values($args);
        }

        return [$this->filed->getName(), false, true];
    }

    /**
     * @return string
     */
    public function getColumnType(): string
    {
        if (null !== $this->component) {
            return $this->component->getColumnType();
        }

        return $this->column_type;
    }

    protected function initialize(): void
    {
        /** @var mixed $field */
        $field = $this->filed;
        // 当选项为多选类型时，存储为string类型，使用逗号分割的方式存储
        if ($field->isMultiple()) {
            $this->setColumnTypeString();

            return;
        }
        if ($field->isSourceDocx()) {
            $docx = $field->getOptionDocx();
            if (null === $docx) {
                return;
            }
            $this->component = $docx->getField(
                $this->filed->getMetadata('extends.value')
            )->getComponent();

            return;
        }
        $options = $this->filed->getOptions();
        if (\count($options) > 0) {
            $this->options($options);
        }
    }

    protected function options(array $options): void
    {
        $isString = false;
        foreach ($options as $value) {
            if (!isset($value['value'])) {
                continue;
            }
            if (!is_numeric($value['value'])) {
                $isString = true;

                break;
            }
        }
        if ($isString) {
            $this->setColumnTypeString();
        } else {
            $this->column_type = 'tinyinteger';
            // 存储为数字无符号类型
            $this->args = [false, true];
        }
    }

    private function setColumnTypeString(): void
    {
        $length = (int) $this->filed->getMetadata('length');
        $this->column_type = 'string';
        $this->args = ['length' => max($length, 30)];
    }
}
