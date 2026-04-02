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

class TextComponent extends AbstractComponent
{
    protected $column_type = 'string';
    private $editor = 'editor';

    public function getColumnArguments(): array
    {
        if ($this->editor === $this->filed->getType()) {
            return [$this->filed->getName()];
        }
        $length = (int) $this->filed->getMetadata('length', 255);
        // 当为密码字段时最小长度应为64
        if ('password' === $this->filed->getType()) {
            $length = max($length, 64);
        }

        return [$this->filed->getName(), $length];
    }

    public function getColumnType(): string
    {
        // 富文本编辑时使用text
        if ($this->editor === $this->filed->getType()) {
            return 'text';
        }

        return $this->column_type;
    }
}
