<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Versioning;

use PTAdmin\Easy\Core\Migration\MigrationPlan;

/**
 * 回滚执行结果对象.
 */
final class RollbackResult
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
     * 返回被激活的版本记录.
     */
    public function version(): array
    {
        return $this->version;
    }

    /**
     * 返回回滚对应的迁移计划.
     */
    public function plan(): MigrationPlan
    {
        return $this->plan;
    }

    /**
     * 本次回滚是否已同步表结构.
     */
    public function synced(): bool
    {
        return $this->synced;
    }

    /**
     * 将回滚结果导出为数组.
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
