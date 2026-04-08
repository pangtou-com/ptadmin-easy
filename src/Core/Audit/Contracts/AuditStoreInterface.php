<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Audit\Contracts;

interface AuditStoreInterface
{
    /**
     * 写入一条审计日志.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    public function write(array $entry): array;
}
