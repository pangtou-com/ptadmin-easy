<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Migration;

use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;

/**
 * 迁移计划生成器.
 *
 * 当前主要是对 SchemaDiff 的轻量封装，后续可以在这里扩展
 * 字段重命名识别、索引计划、回滚计划等能力。
 */
class MigrationPlanner
{
    /** @var SchemaDiff */
    private $diff;

    public function __construct(?SchemaDiff $diff = null)
    {
        $this->diff = $diff ?? new SchemaDiff();
    }

    /**
     * 生成待执行的迁移计划.
     */
    public function plan(?SchemaDefinition $current, SchemaDefinition $next): MigrationPlan
    {
        return $this->diff->diff($current, $next);
    }
}
