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

namespace PTAdmin\Easy\Components\Extend;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class TinyInteger extends Type
{
    public function getName(): string
    {
        return 'tinyinteger';
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return ' TINYINT(1) UNSIGNED ';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?int
    {
        return (null === $value) ? null : (int) $value;
    }

    public function getBindingType(): int
    {
        return \PDO::PARAM_INT;
    }
}
