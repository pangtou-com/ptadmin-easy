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

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Support\SensitiveFieldHelper;

trait TextComponentTrait
{
    protected function getTextAttribute($val, $model = null)
    {
        return $this->transformSensitiveTextValue($val, $model);
    }

    protected function setTextAttribute($val)
    {
        return $this->prepareSensitiveTextValue($val);
    }

    protected function getTextareaAttribute($val, $model = null)
    {
        return $this->transformSensitiveTextValue($val, $model);
    }

    protected function setTextareaAttribute($val)
    {
        return $this->prepareSensitiveTextValue($val);
    }

    protected function getTextRule(): ?string
    {
        $length = $this->getMetadata('maxlength', $this->getMetadata('length', 255));

        return 'max:'.$length;
    }

    protected function getTextareaRule(): ?string
    {
        $length = $this->getMetadata('maxlength', $this->getMetadata('length', 255));

        return 'max:'.$length;
    }

    protected function getPasswordRule(): array
    {
        $length = $this->getMetadata('maxlength', $this->getMetadata('length', 255));
        $min = $this->getMetadata('min', 6);

        return ['min:'.$min, 'max:'.$length];
    }

    protected function getColorRule(): string
    {
        $length = $this->getMetadata('maxlength', $this->getMetadata('length', 255));

        return 'max:'.$length;
    }

    protected function getIconRule(): string
    {
        $length = $this->getMetadata('maxlength', $this->getMetadata('length', 255));

        return 'max:'.$length;
    }

    protected function getDateRule(): array
    {
        return ['date'];
    }

    protected function getDatetimeRule(): array
    {
        return ['date'];
    }

    protected function transformSensitiveTextValue($val, $model)
    {
        if (!$this->isSecretField() && !$this->isMaskedField()) {
            return $val;
        }

        if (null === $val || '' === $val) {
            return $val;
        }

        if ($this->isSecretField()) {
            if (!$this->canRevealSensitive($model)) {
                return null;
            }

            return $this->decryptSensitiveValue((string) $val);
        }

        if ($this->canRevealSensitive($model)) {
            return $val;
        }

        $config = SensitiveFieldHelper::maskConfigFromMetadata((array) $this->getMetadata());

        return null === $config ? $val : SensitiveFieldHelper::applyMask((string) $val, $config);
    }

    protected function prepareSensitiveTextValue($val)
    {
        if (!$this->isSecretField()) {
            return $val;
        }

        if (null === $val || '' === $val) {
            return $val;
        }

        return Crypt::encryptString((string) $val);
    }

    protected function isSecretField(): bool
    {
        return SensitiveFieldHelper::isSecret((array) $this->getMetadata());
    }

    protected function isMaskedField(): bool
    {
        return null !== SensitiveFieldHelper::maskConfigFromMetadata((array) $this->getMetadata());
    }

    protected function canRevealSensitive($model): bool
    {
        $context = $this->resolveExecutionContext($model);
        if (!$context instanceof ExecutionContext) {
            return false;
        }

        return $context->canAccessSensitive($this->getName());
    }

    protected function resolveExecutionContext($model): ?ExecutionContext
    {
        if (\is_object($model) && method_exists($model, 'currentContext')) {
            $context = $model->currentContext();

            return $context instanceof ExecutionContext ? $context : null;
        }

        return null;
    }

    protected function decryptSensitiveValue(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return $value;
        }
    }
}
