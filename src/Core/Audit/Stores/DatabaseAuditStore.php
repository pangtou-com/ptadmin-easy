<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Audit\Stores;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Core\Audit\Contracts\AuditStoreInterface;

/**
 * 数据库审计存储.
 */
class DatabaseAuditStore implements AuditStoreInterface
{
    /** @var array<string, mixed> */
    private $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('easy.audit', []);
    }

    public function write(array $entry): array
    {
        $table = (string) ($this->config['table'] ?? 'audit_logs');
        if (!Schema::hasTable($table)) {
            return (new NullAuditStore())->write($entry);
        }

        $payload = [
            'resource' => $entry['resource'] ?? '',
            'module' => $entry['module'] ?? 'App',
            'schema_version_id' => (int) ($entry['schema_version_id'] ?? 0),
            'operation' => $entry['operation'] ?? '',
            'record_id' => (int) ($entry['record_id'] ?? 0),
            'payload' => json_encode($entry['payload'] ?? null, JSON_UNESCAPED_UNICODE),
            'before_data' => json_encode($entry['before_data'] ?? null, JSON_UNESCAPED_UNICODE),
            'after_data' => json_encode($entry['after_data'] ?? null, JSON_UNESCAPED_UNICODE),
            'diff_data' => json_encode($entry['diff_data'] ?? null, JSON_UNESCAPED_UNICODE),
            'created_at' => time(),
        ];

        $id = DB::table($table)->insertGetId($payload);

        return array_merge(['persisted' => true, 'id' => $id], $entry);
    }
}
