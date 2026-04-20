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
    private $richText = 'rich_text';

    public function getColumnArguments(): array
    {
        if ($this->richText === $this->filed->getType() || true === (bool) $this->filed->getMetadata('secret', false)) {
            return [$this->filed->getName()];
        }
        $length = (int) $this->filed->getMetadata('maxlength', $this->filed->getMetadata('length', 255));
        // 当为密码字段时最小长度应为64
        if ('password' === $this->filed->getType()) {
            $length = max($length, 64);
        }

        return [$this->filed->getName(), $length];
    }

    public function getColumnType(): string
    {
        // 富文本编辑时使用text
        if ($this->richText === $this->filed->getType() || true === (bool) $this->filed->getMetadata('secret', false)) {
            return 'text';
        }

        return $this->column_type;
    }
}
