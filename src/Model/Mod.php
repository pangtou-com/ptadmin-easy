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

namespace PTAdmin\Easy\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

/**
 * @property int         $id
 * @property string      $title      模型表单名称
 * @property string      $table_name 表名称
 * @property string      $mod_name   所属类型
 * @property string      $intro      描述信息
 * @property array|mixed $setup      表单启动安装信息【扩展字段主要期望用于前端处理的配置】
 * @property array|mixed $extra      扩展信息【部分自定义字段的扩展配置值内容】
 * @property int         $status     状态 0 无效，1 有效
 * @property int         $is_publish 是否发布 0 未发布，1 已发布，在发布状态下无法进行表单配置修改
 * @property int         $weight     权重
 * @property ?string     $deleted_at 删除时间
 * @property string      $created_at 创建时间
 * @property string      $updated_at 修改时间
 */
class Mod extends Model
{
    use SoftDeletes;

    protected $dateFormat = 'U';

    protected $fillable = ['title', 'intro', 'setup', 'extra', 'weight'];
    protected $casts = ['setup' => 'array', 'extra' => 'array'];

    public function field(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ModField::class, 'mod_id', 'id');
    }

    public function freshTimestamp(): int
    {
        return time();
    }

    public function fromDateTime($value): int
    {
        return (int) $value;
    }

    public function getCreatedAtAttribute()
    {
        return date('Y-m-d H:i:s', $this->attributes['created_at'] ?? 0);
    }

    public function getUpdatedAtAttribute()
    {
        return date('Y-m-d H:i:s', $this->attributes['updated_at'] ?? 0);
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function getPerPage(): int
    {
        $limit = (int) App::make('request')->get('limit');
        if ($limit > 0) {
            return $limit;
        }

        return parent::getPerPage();
    }
}
