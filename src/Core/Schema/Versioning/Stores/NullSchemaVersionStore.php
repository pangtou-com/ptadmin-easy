<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Versioning\Stores;

use PTAdmin\Easy\Core\Schema\Versioning\Contracts\SchemaVersionStoreInterface;

/**
 * 空版本仓库.
 *
 * 当数据库版本表尚未创建时，允许 publish 流程继续执行，
 * 但不会真正持久化发布记录。
 */
class NullSchemaVersionStore implements SchemaVersionStoreInterface
{
    /**
     * 返回一条未持久化的草稿结果.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function saveDraft(string $resource, array $schema, string $module = '', array $options = []): array
    {
        return [
            'persisted' => false,
            'status' => 'draft',
            'resource' => $resource,
            'module' => $module,
            'schema' => $schema,
        ];
    }

    public function current(string $resource, string $module = ''): ?array
    {
        return null;
    }

    public function latestDraft(string $resource, string $module = ''): ?array
    {
        return null;
    }

    public function history(string $resource, string $module = '', int $limit = 20, array $filters = []): array
    {
        return [];
    }

    public function find(int $versionId): ?array
    {
        return null;
    }

    public function updateDraft(int $versionId, array $schema, array $options = []): ?array
    {
        return null;
    }

    public function deleteDraft(int $versionId): bool
    {
        return false;
    }

    public function deleteVersion(int $versionId): bool
    {
        return false;
    }

    public function markAsCurrent(int $versionId): ?array
    {
        return null;
    }

    /**
     * 返回一条未持久化的发布结果.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function publish(string $resource, array $schema, string $module = '', array $options = []): array
    {
        return [
            'persisted' => false,
            'status' => 'published',
            'resource' => $resource,
            'module' => $module,
            'schema' => $schema,
        ];
    }
}
