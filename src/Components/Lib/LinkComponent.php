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
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Exceptions\EasyException;

class LinkComponent extends AbstractComponent
{
    /** @var IComponent 关联字段的组件对象 */
    private $component;

    /** @var string|null 关联字段不存在时的兜底存储类型 */
    private $columnType;

    /** @var array<int, mixed> 关联字段不存在时的兜底列参数 */
    private $columnArguments = [];

    public function getColumnType(): string
    {
        if (null !== $this->component) {
            return $this->component->getColumnType();
        }

        if (null !== $this->columnType) {
            return $this->columnType;
        }

        return $this->component->getColumnType();
    }

    public function getColumnArguments(): array
    {
        if (null === $this->component) {
            return $this->columnArguments;
        }

        $args = $this->component->getColumnArguments();
        array_shift($args);
        array_unshift($args, $this->filed->getName());

        return $args;
    }

    protected function initialize(): void
    {
        $extends = $this->filed->getRelation();
        $resource = $extends['table'] === $this->filed->getResource()->getRawTable()
            ? $this->filed->getResource()
            : Easy::schema($extends['table'])->raw();

        if ($extends['value'] === $resource->getPrimaryKey()) {
            // 关联字段使用目标资源主键时，无需在 schema 中重复声明 id 字段。
            $this->columnType = 'integer';
            $this->columnArguments = [$this->filed->getName(), false, true];

            return;
        }

        if (null !== ($field = $resource->getField($extends['value']))) {
            $this->component = $field->getComponent();

            return;
        }

        throw new EasyException(__('ptadmin-easy::messages.errors.link_relation_field_missing', [
            'table' => $extends['table'],
            'field' => $extends['value'],
        ]));
    }
}
