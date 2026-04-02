<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2025 【重庆胖头网络技术有限公司】，并保留所有权利。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModInit extends Migration
{
    public function up(): void
    {
        Schema::create('mods', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 50)->comment('模型表单名称');
            $table->string('table_name', 50)->comment('表名称');
            $table->string('parent_table_name', 50)->nullable()->comment('父级表名称');
            $table->string('module', 50)->comment('所属模块名称');
            $table->string('intro', 255)->nullable()->comment('描述信息');
            $table->string('auto_name', 140)->nullable()->comment('自动名称');
            $table->string('naming_rule', 140)->nullable()->comment('自动名称：命名规则');
            $table->string('title_field', 140)->nullable()->comment('标题字段');
            $table->string('cover_field', 140)->nullable()->comment('封面图片字段');
            $table->string('sort', 140)->nullable()->comment('排序规则');
            $table->string('color', 140)->nullable()->comment('颜色');
            $table->string('icon', 50)->nullable()->comment('图标');
            $table->string('cover', 255)->nullable()->comment('封面图');
            $table->string('route', 140)->nullable()->comment('路由地址');
            $table->string('migrate_hash', 32)->nullable()->comment('迁移文件哈希');
            $table->json('setup')->nullable()->comment('表单构建扩展信息如：style、class等');
            $table->json('extra')->nullable()->comment('扩展信息【部分自定义字段的扩展配置值内容】');
            $table->unsignedTinyInteger('weight')->default(99)->comment('权重');
            $table->unsignedTinyInteger('quick_entry')->default(0)->comment('是否快速入口');
            $table->unsignedTinyInteger('read_only')->default(0)->comment('是否只读');
            $table->unsignedTinyInteger('is_publish')->default(0)->comment('是否发布 0 未发布，1 已发布，在发布状态下无法进行表单配置修改');
            $table->unsignedTinyInteger('is_single')->default(0)->comment('是否单页');
            $table->unsignedTinyInteger('is_tree')->default(0)->comment('是否树形结构');
            $table->unsignedTinyInteger('is_table')->default(0)->comment('是否表格');
            $table->unsignedTinyInteger('allow_import')->default(0)->comment('是否允许导入');
            $table->unsignedTinyInteger('allow_copy')->default(0)->comment('是否允许拷贝');
            $table->unsignedTinyInteger('allow_rename')->default(1)->comment('是否重命名');
            $table->unsignedTinyInteger('allow_recycle')->default(1)->comment('是否支持回收站');
            $table->unsignedTinyInteger('track_changes')->default(0)->comment('是否跟踪修改记录');
            $table->unsignedTinyInteger('status')->default(0)->comment('是否已经使用，0：未使用，1:已使用	');
            $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间');
            $table->unsignedInteger('created_at')->default(0)->comment('创建时间');
            $table->unsignedInteger('updated_at')->default(0)->comment('更新时间');
        });

        Schema::create('mod_fields', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mod_id')->default(0);
            $table->string('title', 255)->comment('标题');
            $table->string('subtitle', 255)->nullable()->comment('副标题');
            $table->string('name', 255)->comment('名称');
            $table->string('type', 50)->comment('类型');
            $table->string('default_val', 50)->nullable()->comment('默认值');
            $table->string('tips', 255)->nullable()->comment('描述信息');
            $table->string('intro', 255)->nullable()->comment('描述信息');
            $table->unsignedInteger('length')->default(0)->comment('字段长度');
            $table->unsignedTinyInteger('is_release')->default(0)->comment('用户投稿');
            $table->unsignedTinyInteger('is_search')->default(0)->comment('搜索');
            $table->unsignedTinyInteger('is_table')->default(0)->comment('列表展示');
            $table->unsignedTinyInteger('is_export')->default(0)->comment('是否支持字段导出');
            $table->unsignedTinyInteger('is_import')->default(0)->comment('是否支持字段导入');
            $table->unsignedTinyInteger('is_required')->default(0)->comment('是否必填');
            $table->unsignedTinyInteger('is_unique')->default(0)->comment('是否唯一');
            $table->unsignedTinyInteger('is_edit')->default(0)->comment('是否允许编辑，对字段类型等编辑修改');
            $table->json('setup')->nullable()->comment('表单构建扩展信息如：style、class等');
            $table->json('rules')->nullable()->comment('字段规则');
            $table->json('extra')->nullable()->comment('扩展字段信息');
            $table->unsignedTinyInteger('weight')->default(99)->comment('权重');
            $table->unsignedTinyInteger('status')->default(0)->comment('是否已经使用，0：未使用，1:已使用	');
            $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间');
            $table->unsignedInteger('created_at')->default(0)->comment('创建时间');
            $table->unsignedInteger('updated_at')->default(0)->comment('更新时间');
            // $table->text('options');
        });

        app('cache')->store('default' !== config('easy.cache.store') ? config('easy.cache.store') : null)->forget(config('easy.cache.key'));
    }

    public function down(): void
    {
        Schema::dropIfExists('mods');
        Schema::dropIfExists('mod_fields');
    }
}
