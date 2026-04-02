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

use Illuminate\Database\Eloquent\Model;
use PTAdmin\Easy\Contracts\IDocx;

class EasyModel extends Model
{
    // 限制延迟加载避免N+1问题
    protected static $modelsShouldPreventLazyLoading = true;
    protected $hidden = ['password', 'remember_token'];

    /** @var IDocx 文档管理对象 */
    protected $docx;
    protected $document;

    public function setAttribute($key, $value)
    {
        if (null !== $this->document) {
            $value = $this->document->setMutatedAttributeValue($this, $key, $value);
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if (null !== $this->document) {
            $value = $this->document->getMutatedAttributeValue($this, $key, $value);
        }

        return $value;
    }

    public function newInstance($attributes = [], $exists = false): self
    {
        $model = parent::newInstance($attributes, $exists);
        $model->docx = $this->docx;
        $model->document = $this->document;

        return $model;
    }

    public static function make(IDocx $docx, $document): self
    {
        $instance = new static();
        $instance->docx = $docx;
        $instance->document = $document;
        $instance->setTable($docx->getRawTable());

        return $instance;
    }

    public function attributesToArray(): array
    {
        $attributes = $this->getArrayableAttributes();
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->getAttribute($key);
        }
        if ($this->docx instanceof IDocx) {
            foreach ($this->docx->getAppends() as $key => $val) {
                $attributes[$key] = $this->getAppendValue($key);
            }
        }

        return $attributes;
    }

    /**
     * 获取追加字段的值.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getAppendValue(string $key, $default = null)
    {
        if (null === $this->document) {
            return $default;
        }
        $value = $this->document->getAppendValue($this, $key);

        return null === $value ? $default : $value;
    }

    /**
     * 获取全部追加字段的值.
     *
     * @return mixed
     */
    public function getAppendsValue()
    {
        if (null === $this->document) {
            return [];
        }

        return $this->document->getAppendsValue($this);
    }

    public function getFillable(): array
    {
        if (null === $this->docx) {
            return parent::getFillable();
        }

        return $this->docx->getFillable();
    }

    public function getCreatedAtAttribute($value)
    {
        if (0 === (int) $value) {
            return '';
        }

        return date('Y-m-d H:i:s', $value);
    }

    public function getUpdatedAtAttribute($value)
    {
        if (0 === (int) $value) {
            return '';
        }

        return date('Y-m-d H:i:s', $value);
    }

    public function getDeletedAtAttribute($value)
    {
        if (0 === (int) $value) {
            return '';
        }

        return date('Y-m-d H:i:s', $value);
    }

    public function freshTimestamp()
    {
        return time();
    }

    public function fromDateTime($value)
    {
        return $value;
    }

    public function getPerPage(): int
    {
        $limit = (int) (request()->get('limit', 20));
        if (0 !== $limit) {
            return $limit;
        }

        return parent::getPerPage();
    }
}
