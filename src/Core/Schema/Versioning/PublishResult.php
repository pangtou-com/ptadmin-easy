<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Versioning;

use PTAdmin\Easy\Core\Migration\MigrationPlan;

/**
 * publish 执行结果对象.
 */
final class PublishResult
{
    /** @var array<string, mixed> */
    private $version;

    /** @var MigrationPlan */
    private $plan;

    /** @var bool */
    private $synced;

    /**
     * @param array<string, mixed> $version
     */
    public function __construct(array $version, MigrationPlan $plan, bool $synced)
    {
        $this->version = $version;
        $this->plan = $plan;
        $this->synced = $synced;
    }

    /**
     * 返回版本持久化结果.
     */
    public function version(): array
    {
        return $this->version;
    }

    /**
     * 返回本次 publish 对应的迁移计划.
     */
    public function plan(): MigrationPlan
    {
        return $this->plan;
    }

    /**
     * 本次 publish 是否已经同步表结构.
     */
    public function synced(): bool
    {
        return $this->synced;
    }

    /**
     * 将 publish 结果导出为数组.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version(),
            'plan' => $this->plan()->toArray(),
            'synced' => $this->synced(),
        ];
    }
}
