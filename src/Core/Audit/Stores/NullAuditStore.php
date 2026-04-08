<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Audit\Stores;

use PTAdmin\Easy\Core\Audit\Contracts\AuditStoreInterface;

/**
 * 空审计存储.
 */
class NullAuditStore implements AuditStoreInterface
{
    public function write(array $entry): array
    {
        return array_merge(['persisted' => false], $entry);
    }
}
