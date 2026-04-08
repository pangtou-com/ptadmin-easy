<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Migration;

/**
 * 迁移计划对象.
 *
 * 只表达 schema 对比后的结构性变更，不直接负责执行。
 */
final class MigrationPlan
{
    /** @var array<string, mixed> */
    private $operations;

    /** @var array<string, mixed> */
    private $explanation;

    /**
     * @param array<string, mixed> $operations
     * @param array<string, mixed> $explanation
     */
    public function __construct(array $operations = [], array $explanation = [])
    {
        $this->operations = $operations;
        $this->explanation = $explanation;
    }

    /**
     * 返回当前计划中的原始操作集合.
     */
    public function operations(): array
    {
        return $this->operations;
    }

    /**
     * 返回当前计划的结构化说明.
     *
     * 适合直接提供给前端发布确认页或调试工具展示。
     *
     * @return array<string, mixed>
     */
    public function explanation(): array
    {
        return $this->explanation;
    }

    /**
     * 返回计划摘要.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return (array) ($this->explanation['summary'] ?? []);
    }

    /**
     * 返回聚合后的风险列表.
     *
     * @return array<int, array<string, mixed>>
     */
    public function risks(): array
    {
        return array_values(array_filter((array) ($this->explanation['risks'] ?? []), 'is_array'));
    }

    /**
     * 返回当前计划中不支持自动执行的风险列表。
     *
     * @return array<int, array<string, mixed>>
     */
    public function unsupportedRisks(): array
    {
        return array_values(array_filter($this->risks(), static function (array $risk): bool {
            return true === (bool) ($risk['unsupported'] ?? false)
                || 0 === strpos((string) ($risk['code'] ?? ''), 'unsupported_');
        }));
    }

    /**
     * 当前计划是否包含不支持自动执行的变更。
     */
    public function hasUnsupportedChanges(): bool
    {
        return 0 !== \count($this->unsupportedRisks());
    }

    /**
     * 校验当前计划是否允许自动同步到数据库。
     */
    public function ensureSupportedForSync(): void
    {
        $unsupported = $this->unsupportedRisks();
        if (0 === \count($unsupported)) {
            return;
        }

        $messages = array_values(array_filter(array_map(static function (array $risk): ?string {
            $message = trim((string) ($risk['message'] ?? ''));

            return '' !== $message ? $message : null;
        }, $unsupported)));

        throw new \InvalidArgumentException(
            0 !== \count($messages)
                ? implode(' ', $messages)
                : 'Migration plan contains unsupported schema changes for automatic sync.'
        );
    }

    /**
     * 是否包含破坏性操作.
     */
    public function isDestructive(): bool
    {
        if (true === (bool) ($this->summary()['destructive'] ?? false)) {
            return true;
        }

        return !empty($this->operations['drop_fields'] ?? [])
            || !empty($this->operations['drop_unique'] ?? []);
    }

    /**
     * 当前计划是否为空.
     */
    public function isEmpty(): bool
    {
        if (true === (bool) ($this->summary()['empty'] ?? false)) {
            return true;
        }

        foreach ($this->operations as $items) {
            if (\is_array($items) && \count($items) > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * 将计划导出为数组.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'operations' => $this->operations(),
            'summary' => $this->summary(),
            'explanation' => $this->explanation(),
            'destructive' => $this->isDestructive(),
            'unsupported' => $this->hasUnsupportedChanges(),
            'empty' => $this->isEmpty(),
        ];
    }
}
