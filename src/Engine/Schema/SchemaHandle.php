<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2025 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy\Engine\Schema;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Contracts\IResourceField;
use PTAdmin\Easy\Engine\Resource\ResourceDefinition;

/**
 * 模块数据表处理.
 */
trait SchemaHandle
{
    /**
     * 判断数据表是否存在.
     *
     * @param $tableName
     *
     * @return bool
     */
    public function tableExists($tableName): bool
    {
        return Schema::hasTable($tableName);
    }

    /**
     * 数据表删除.
     *
     * @param $tableName
     */
    public function dropTable($tableName): void
    {
        Schema::dropIfExists($tableName);
    }

    /**
     * 创建数据表.
     *
     * @param ResourceDefinition $resource
     */
    protected function createTable(ResourceDefinition $resource): void
    {
        Schema::create($resource->getRawTable(), function (Blueprint $table) use ($resource): void {
            $table->increments($resource->getPrimaryKey());
            foreach ($resource->getFields() as $field) {
                $this->createField($field, $table);
            }
            if ($resource->allowRecycle()) {
                $table->integer('deleted_at')->unsigned()->nullable()->default(null);
            }
            $table->integer(Model::CREATED_AT)->unsigned()->default(0);
            $table->integer(Model::UPDATED_AT)->unsigned()->default(0);

            $table->engine = 'InnoDB';
        });
    }

    protected function setTableComment(string $tableName, string $comment): void
    {
        if ('' === $comment) {
            return;
        }
        DB::statement("ALTER TABLE `{$tableName}` comment '{$comment}'");
    }

    /**
     * 创建表字段.
     *
     * @param IResourceField $field
     * @param Blueprint  $table
     */
    protected function createField(IResourceField $field, Blueprint $table): void
    {
        $component = $field->getComponent();

        if ($component->isVirtual() || $field->isVirtual()) {
            return;
        }
        $column = $table->{$component->getColumnType()}(...$component->getColumnArguments());

        if (null === $field->required()) {
            $column->nullable();
        }
        $column->comment($field->getComment());

        if (null !== $field->getDefault()) {
            $column->default($field->getDefault());
        }

        if ($field->exists()) {
            $column->change();
        }

        if (1 === (int) $field->getMetadata('is_unique', 0) && !$field->exists()) {
            $table->unique($field->getName(), $this->getUniqueIndexName($field->getResource()->getRawTable(), $field->getName()));
        }
    }

    /**
     * 生成唯一索引名称.
     */
    protected function getUniqueIndexName(string $tableName, string $fieldName): string
    {
        return "{$tableName}_{$fieldName}_unique";
    }
}
