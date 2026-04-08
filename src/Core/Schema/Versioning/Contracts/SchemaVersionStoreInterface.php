<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Versioning\Contracts;

/**
 * Schema 版本持久化接口.
 */
interface SchemaVersionStoreInterface
{
    /**
     * 保存一份草稿 schema.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function saveDraft(string $resource, array $schema, string $module = '', array $options = []): array;

    /**
     * 返回当前已发布版本.
     *
     * @return array<string, mixed>|null
     */
    public function current(string $resource, string $module = ''): ?array;

    /**
     * 返回最新草稿版本.
     *
     * @return array<string, mixed>|null
     */
    public function latestDraft(string $resource, string $module = ''): ?array;

    /**
     * 返回版本历史列表.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $resource, string $module = '', int $limit = 20, array $filters = []): array;

    /**
     * 按版本 ID 查询记录.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $versionId): ?array;

    /**
     * 更新指定草稿版本.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    public function updateDraft(int $versionId, array $schema, array $options = []): ?array;

    /**
     * 删除指定草稿版本.
     */
    public function deleteDraft(int $versionId): bool;

    /**
     * 删除指定版本记录.
     */
    public function deleteVersion(int $versionId): bool;

    /**
     * 将指定版本切换为当前发布版本.
     *
     * @return array<string, mixed>|null
     */
    public function markAsCurrent(int $versionId): ?array;

    /**
     * 保存一次新的已发布版本.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function publish(string $resource, array $schema, string $module = '', array $options = []): array;
}
