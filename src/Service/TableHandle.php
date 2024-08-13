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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 模块数据表处理.
 */
class TableHandle
{
    /**
     * 确保数据表存在.
     *
     * @param mixed $tableName
     */
    public static function ensureTableExists($tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            self::createTable($tableName);
        }
    }

    /**
     * 判断数据表是否存在.
     *
     * @param $tableName
     *
     * @return bool
     */
    public static function tableExists($tableName): bool
    {
        return Schema::hasTable($tableName);
    }

    /**
     * 数据表删除.
     *
     * @param $tableName
     */
    public static function dropTable($tableName): void
    {
        Schema::dropIfExists($tableName);
    }

    /**
     * 数据表修改名称.
     *
     * @param $oldTableName
     * @param $newTableName
     */
    public static function renameTable($oldTableName, $newTableName): void
    {
        Schema::rename($oldTableName, $newTableName);
    }

    /**
     * 创建数据表.
     *
     * @param $tableName
     */
    public static function createTable($tableName): void
    {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('deleted_at')->unsigned()->nullable()->default(null);
            $table->integer(Model::CREATED_AT)->unsigned()->default(0);
            $table->integer(Model::UPDATED_AT)->unsigned()->default(0);
        });
    }
}
