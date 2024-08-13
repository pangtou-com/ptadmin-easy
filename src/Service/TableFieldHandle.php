<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2024 重庆胖头网络技术有限公司，并保留所有权利。
 *  网站地址: https://www.pangtou.com
 *  ----------------------------------------------------------------------------
 *  尊敬的用户，
 *     感谢您对我们产品的关注与支持。我们希望提醒您，在商业用途中使用我们的产品时，请务必前往官方渠道购买正版授权。
 *  购买正版授权不仅有助于支持我们不断提供更好的产品和服务，更能够确保您在使用过程中不会引起不必要的法律纠纷。
 *  正版授权是保障您合法使用产品的最佳方式，也有助于维护您的权益和公司的声誉。我们一直致力于为客户提供高质量的解决方案，并通过正版授权机制确保产品的可靠性和安全性。
 *  如果您有任何疑问或需要帮助，我们的客户服务团队将随时为您提供支持。感谢您的理解与合作。
 *  诚挚问候，
 *  【重庆胖头网络技术有限公司】
 *  ============================================================================
 *  Author:    Zane
 *  Homepage:  https://www.pangtou.com
 *  Email:     vip@pangtou.com
 */

namespace PTAdmin\Easy\Service;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Components\ComponentManager;
use PTAdmin\Easy\Model\ModField;

/**
 * 数据表字段处理字段对象
 */
class TableFieldHandle
{
    public const DEFAULT_AFTER = 'id';

    /** @var ModField 当前字段对象 */
    public $model;

    /** @var false 是否编辑字段 */
    private $edit;

    private function __construct($field, bool $edit = false)
    {
        $this->model = $field;
        $this->edit = $edit;
    }

    /**
     * 数据表的字段处理.
     */
    public function handle(): void
    {
        $after = $this->model->after ?? self::DEFAULT_AFTER;
        $mod = $this->model->mod()->firstOrFail();
        $column = ComponentManager::build($this->model->type, $this->model);
        $tableName = $mod->table_name;
        Schema::table($tableName, function (Blueprint $table) use ($column, $after): void {
            // 组件存在自定义字段创建方法时，执行自定义方法
            if (method_exists($column, 'insertColumn')) {
                $column->insertColumn($table);

                return;
            }
            $args = $column->getColumnArguments();
            /** @var ColumnDefinition */
            $field = $table->{$column->getColumnType()}(...$args);
            if (!$this->model->is_required) {
                $field->nullable();
            }
            $field->comment($this->model->intro ?? $this->model->title);
            if (null !== $this->model->default_val) {
                $field->default($this->model->default_val);
            }
            $this->edit ? $field->change() : $field->after($after);
        });
    }

    /**
     * 创建字段.
     *
     * @param $field
     *
     * @return static
     */
    public static function createField($field): self
    {
        return new self($field);
    }

    /**
     * 编辑字段.
     *
     * @param $field
     * @param $data
     *
     * @return static
     */
    public static function editField($field, $data): self
    {
        if ($data['name'] !== $field->name) {
            $mod = $field->mod()->firstOrFail();

            self::renameTableColumn($mod->table_name, $data['name'], $field->name);
        }

        return new self($field, true);
    }

    /**
     * 数据表字段是否存在.
     *
     * @param $tableName
     * @param $columnName
     *
     * @return bool
     */
    public static function existsTableColumn($tableName, $columnName): bool
    {
        return Schema::hasColumn($tableName, $columnName);
    }

    /**
     * 修改字段名称.
     *
     * @param $tableName
     * @param $oldColumnName
     * @param $newColumnName
     */
    public static function renameTableColumn($tableName, $oldColumnName, $newColumnName): void
    {
        if (Schema::hasColumn($tableName, $oldColumnName)) {
            Schema::table($tableName, function (Blueprint $table) use ($oldColumnName, $newColumnName): void {
                $table->renameColumn($oldColumnName, $newColumnName);
            });
        }
    }

    /**
     * 删除数据表字段.
     *
     * @param $tableName
     * @param $columnName
     */
    public static function dropTableColumn($tableName, $columnName): void
    {
        if (Schema::hasColumn($tableName, $columnName)) {
            Schema::dropColumns($tableName, [$columnName]);
        }
    }
}
