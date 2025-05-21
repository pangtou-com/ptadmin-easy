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

namespace PTAdmin\Easy\Components\Lib;

use PTAdmin\Easy\Components\AbstractComponent;
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Exceptions\EasyException;

class LinkComponent extends AbstractComponent
{
    /** @var IComponent 关联字段的组件对象 */
    private $component;

    public function getColumnType(): string
    {
        return $this->component->getColumnType();
    }

    public function getColumnArguments(): array
    {
        $args = $this->component->getColumnArguments();
        array_shift($args);
        array_unshift($args, $this->filed->getName());

        return $args;
    }

    protected function initialize(): void
    {
        $extends = $this->filed->getMetadata('extends');

        if ($extends['name'] === $this->filed->getDocx()->getRawTable()) {
            if (null === ($field = $this->filed->getDocx()->getField($extends['value']))) {
                throw new EasyException("关联文档：[{$extends['name']}]中不存在关联字段：{$extends['value']}");
            }
            $this->component = $field->getComponent();

            return;
        }
        // TODO 如果不在当前文档中需要查询文档对象
        throw new EasyException("关联文档：[{$extends['name']}]中不存在关联字段：{$extends['value']}");
    }
}
