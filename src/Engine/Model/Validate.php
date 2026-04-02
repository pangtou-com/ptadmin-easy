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

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PTAdmin\Easy\Exceptions\EasyValidateException;

class Validate
{
    protected $dto;
    protected $document;
    protected $model;

    public function __construct(FormDTO $formDTO, $document, $model = null)
    {
        $this->dto = $formDTO;
        $this->document = $document;
        $this->model = $model;
        $this->initFormDTORule();
    }

    public function validate(): void
    {
        // 当自定义事件明确返回false时可以将取消验证直接返回
        if (false === $this->document->trigger('validate', $this->dto)) {
            return;
        }

        try {
            Validator::make($this->dto->getData(), $this->dto->getRules(), $this->dto->getMessages(), $this->dto->getAttributes())->validate();
        } catch (ValidationException $e) {
            // 考虑是否需要包装
            $msg = $e->validator->getMessageBag()->toArray();
            $msg = collect($msg)->map(function ($item) {
                return implode('|', $item);
            })->toArray();
            $messages = implode(',', $msg);

            throw new EasyValidateException($messages, $e->getCode(), $e);
        }
    }

    protected function initFormDTORule(): void
    {
        $docx = $this->document->docx();
        list($rules, $attributes) = $docx->getRules(null !== $this->model ? $this->model->getKey() : null);
        $this->dto->setAttributes($attributes)->setRules($rules);
        if (null !== $this->model) {
            $this->dto->filterRules();
        }
    }
}
