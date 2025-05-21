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
        foreach ($data as $key => $value) {
            if (null === $value) {
                unset($data[$key]);
            }
        }
        $self->data = $data;
        $self->model = $model;

        return $self;
    }

    public function getData(): array
    {
        return $this->data;
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
