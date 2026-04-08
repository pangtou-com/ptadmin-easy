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

namespace PTAdmin\Easy\Components;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Type;
use PTAdmin\Easy\Components\Extend\TinyInteger;
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Contracts\IResourceField;

abstract class AbstractComponent implements IComponent
{
    /** @var string 字段存储类型 */
    protected $column_type;

    /** @var array 字段新增保存时规则 */
    protected $rules = [];

    /** @var array 数据新增时需要保存的额外数据 */
    protected $extra;

    /** @var array 数据新增时需要保存的额外数据：如rules、style、class、prop等 */
    protected $setup;

    /** @var bool 是否为选项类型组件 */
    protected $option = false;

    /** @var bool 是否存储为数字类型 */
    protected $number = false;

    /** @var IResourceField 字段类型 */
    protected $filed;

    /**
     * @param IResourceField $field
     */
    public function __construct(IResourceField $field)
    {
        if (!Type::hasType('tinyinteger')) {
            try {
                Type::addType('tinyinteger', TinyInteger::class);
            } catch (Exception $e) {
            }
        }
        $this->filed = $field;
        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }
    }

    /**
     * 数据表存储类型.
     *
     * @return string
     */
    public function getColumnType(): string
    {
        return $this->column_type;
    }

    public function getColumnArguments(): array
    {
        return [$this->filed->getName()];
    }

    public function parserSetup($data)
    {
        return $data;
    }

    public function isOption(): bool
    {
        return $this->option;
    }

    /**
     * 是否为数字类型.
     *
     * @return bool
     */
    public function isNumber(): bool
    {
        return $this->number;
    }

    public function getColumnOptions($data)
    {
        return $data;
    }

    public function saveFormat($value)
    {
        return $value;
    }

    public function toFormat($value)
    {
        return $value;
    }

    public function isVirtual(): bool
    {
        return false;
    }
}
