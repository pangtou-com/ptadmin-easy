<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2026 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy\Handle;

use PTAdmin\Easy\Core\Schema\Versioning\PublishResult;
use PTAdmin\Easy\Core\Schema\Versioning\RollbackResult;

/**
 * 资源发布句柄.
 *
 * 只负责 schema 的草稿、发布、回滚和版本历史等生命周期能力。
 */
final class ReleaseHandle
{
    /** @var ResourceHandle */
    private $handle;

    public function __construct(ResourceHandle $handle)
    {
        $this->handle = $handle;
    }

    /**
     * 预览发布计划.
     *
     * 支持两种调用：
     * 1. `plan($schema)` 直接预览某份 schema
     * 2. `plan($draftVersionId)` 预览某个草稿版本
     *
     * @param array<string, mixed>|int $schema
     */
    public function plan($schema)
    {
        return $this->handle->planPublish($schema);
    }

    /**
     * 按草稿版本 ID 预览发布计划。
     */
    public function planVersion(int $versionId)
    {
        return $this->handle->planVersion($versionId);
    }

    /**
     * 保存 schema 草稿.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function saveDraft(array $schema, array $options = []): array
    {
        return $this->handle->saveDraft($schema, $options);
    }

    /**
     * 发布 schema 或草稿版本。
     *
     * 推荐使用 `publish($draftVersionId)`，避免再次传整份 schema。
     *
     * @param array<string, mixed>|int $schema
     * @param array<string, mixed>     $options
     */
    public function publish($schema, array $options = []): PublishResult
    {
        return $this->handle->publish($schema, $options);
    }

    /**
     * 按草稿版本 ID 发布。
     */
    public function publishVersion(int $versionId, array $options = []): PublishResult
    {
        return $this->handle->publishVersion($versionId, $options);
    }

    /**
     * 返回当前已发布版本.
     *
     * @return null|array<string, mixed>
     */
    public function current(): ?array
    {
        return $this->handle->currentVersion();
    }

    /**
     * 返回最新草稿版本.
     *
     * @return null|array<string, mixed>
     */
    public function latestDraft(): ?array
    {
        return $this->handle->latestDraftVersion();
    }

    /**
     * 返回指定版本详情。
     *
     * @return null|array<string, mixed>
     */
    public function version(int $versionId): ?array
    {
        return $this->handle->version($versionId);
    }

    /**
     * 返回版本详情页结构。
     *
     * @return null|array<string, mixed>
     */
    public function versionDetail(int $versionId): ?array
    {
        return $this->handle->versionDetail($versionId);
    }

    /**
     * 返回版本历史.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 20, array $filters = []): array
    {
        return $this->handle->versionHistory($limit, $filters);
    }

    /**
     * 返回草稿列表。
     *
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function drafts(int $limit = 20, array $filters = []): array
    {
        return $this->handle->draftHistory($limit, $filters);
    }

    /**
     * 返回后台版本管理页面板数据。
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function versionPanel(int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        return $this->handle->versionPanel($page, $pageSize, $filters);
    }

    /**
     * 对比两个版本的结构差异。
     *
     * 当第二个版本为空时，默认与当前发布版本对比。
     *
     * @return array<string, mixed>
     */
    public function diffVersions(int $fromVersionId, ?int $toVersionId = null): array
    {
        return $this->handle->diffVersions($fromVersionId, $toVersionId);
    }

    /**
     * 按版本 ID 更新草稿。
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function updateDraft(int $versionId, array $schema, array $options = []): array
    {
        return $this->handle->updateDraft($versionId, $schema, $options);
    }

    /**
     * 删除指定草稿版本。
     */
    public function deleteDraft(int $versionId): bool
    {
        return $this->handle->deleteDraft($versionId);
    }

    /**
     * 删除指定历史版本。
     */
    public function deleteVersion(int $versionId): bool
    {
        return $this->handle->deleteVersion($versionId);
    }

    /**
     * 回滚到指定版本.
     */
    public function rollbackTo(int $versionId, array $options = []): RollbackResult
    {
        return $this->handle->rollbackTo($versionId, $options);
    }
}
