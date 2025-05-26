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
use PTAdmin\Easy\Contracts\IDocxField;
use PTAdmin\Easy\Engine\Docx\Docx;

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
     * @param Docx $docx
     */
    protected function createTable(Docx $docx): void
    {
        Schema::create($docx->getRawTable(), function (Blueprint $table) use ($docx): void {
            $table->increments($docx->getPrimaryKey());
            foreach ($docx->getFields() as $field) {
                $this->createField($field, $table);
            }
            if ($docx->allowRecycle()) {
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
     * @param IDocxField $field
     * @param Blueprint  $table
     */
    protected function createField(IDocxField $field, Blueprint $table): void
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
    }
}
