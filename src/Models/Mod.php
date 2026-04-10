<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Easy 已发布资源运行时读模型。
 *
 * 对应 `mods` 主表，
 * 供业务侧按 `mod_id` 查询已发布资源元数据。
 *
 * @property int $id ID
 * @property string $title 模型标题
 * @property string $name 资源名称
 * @property string|null $parent_table_name 父级表名称
 * @property string $module 所属模块
 * @property string|null $intro 描述信息
 * @property string|null $auto_name 自动名称
 * @property string|null $naming_rule 命名规则
 * @property string|null $title_field 标题字段
 * @property string|null $cover_field 封面字段
 * @property string|null $sort 排序规则
 * @property string|null $color 颜色
 * @property string|null $icon 图标
 * @property string|null $cover 封面
 * @property string|null $route 路由
 * @property string|null $migrate_hash 迁移哈希
 * @property int $current_version_id 当前发布版本ID
 * @property int $weight 权重
 * @property int $quick_entry 是否快速入口
 * @property int $read_only 是否只读
 * @property int $is_publish 是否已发布
 * @property int $is_tree 是否树形结构
 * @property int $is_table 是否表格
 * @property int $allow_import 是否允许导入
 * @property int $allow_export 是否允许导出
 * @property int $allow_copy 是否允许复制
 * @property int $allow_rename 是否允许重命名
 * @property int $allow_recycle 是否支持回收站
 * @property int $track_changes 是否跟踪修改
 * @property int $status 状态
 * @property int|null $deleted_at 删除时间
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 */
class Mod extends Model
{
    protected $table = 'mods';

    protected $guarded = ['id'];

    protected $casts = [
        'current_version_id' => 'integer',
        'weight' => 'integer',
        'quick_entry' => 'integer',
        'read_only' => 'integer',
        'is_publish' => 'integer',
        'is_tree' => 'integer',
        'is_table' => 'integer',
        'allow_import' => 'integer',
        'allow_export' => 'integer',
        'allow_copy' => 'integer',
        'allow_rename' => 'integer',
        'allow_recycle' => 'integer',
        'track_changes' => 'integer',
        'status' => 'integer',
        'deleted_at' => 'integer',
        'created_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query
            ->where('is_publish', 1)
            ->where('current_version_id', '>', 0)
            ->where('status', 1);
    }
}
