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

namespace PTAdmin\Easy\Engine\Model;

/**
 * 表单数据传输.
 */
class FormDTO
{
    /** @var array 表单数据 */
    protected $data;
    /** @var mixed 当为修改时对应的修改模型数据 */
    protected $model;

    protected $rules;
    protected $attributes;
    protected $messages;

    public static function make(array $data, $model = null): self
    {
        $self = new self();
        $self->model = $model;
        $self->data = array_filter($data, function ($value) {
            return null !== $value;
        }, ARRAY_FILTER_USE_KEY);

        return $self;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 过滤规则.
     */
    public function filterRules(): void
    {
        $this->rules = array_filter($this->getRules(), function ($value, $key) {
            return isset($this->data[$key]);
        }, ARRAY_FILTER_USE_BOTH);

        $availableFields = array_keys($this->rules);
        $this->messages = array_filter($this->getMessages(), static function ($value, $key) use ($availableFields) {
            if (!\is_string($key) || '' === trim($key)) {
                return false;
            }

            foreach ($availableFields as $field) {
                if (0 === strpos($key, $field.'.')) {
                    return true;
                }
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH);
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setRules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function setMessages(array $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    public function getRules(): array
    {
        return $this->rules ?? [];
    }

    public function getAttributes(): array
    {
        return $this->attributes ?? [];
    }

    public function getMessages(): array
    {
        return $this->messages ?? [];
    }
}
