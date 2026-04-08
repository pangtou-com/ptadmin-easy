<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2026 【重庆胖头网络技术有限公司】。
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
    /**
     * 创建资源主表与已发布字段缓存表.
     *
     * `mods` 保存当前资源的主状态与已发布配置摘要，
     * `mod_fields` 保存当前已发布版本编译后的字段缓存，
     * 供运行时直接读取，避免 doc 侧再去扫历史版本快照。
     */
    public function up(): void
    {
        Schema::create('mods', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150)->comment('模型表单名称');
            $table->string('name', 100)->unique()->comment('资源名称');
            $table->string('parent_table_name', 50)->nullable()->comment('父级表名称');
            $table->string('module', 50)->default('App')->comment('所属模块名称');
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
            $table->unsignedBigInteger('current_version_id')->default(0)->comment('当前发布版本 ID');
            $table->unsignedTinyInteger('weight')->default(99)->comment('权重');
            $table->unsignedTinyInteger('quick_entry')->default(0)->comment('是否快速入口');
            $table->unsignedTinyInteger('read_only')->default(0)->comment('是否只读');
            $table->unsignedTinyInteger('is_publish')->default(0)->comment('是否发布 0 未发布，1 已发布，在发布状态下无法进行表单配置修改');
            $table->unsignedTinyInteger('is_tree')->default(0)->comment('是否树形结构');
            $table->unsignedTinyInteger('is_table')->default(0)->comment('是否表格');
            $table->unsignedTinyInteger('allow_import')->default(0)->comment('是否允许导入');
            $table->unsignedTinyInteger('allow_export')->default(0)->comment('是否允许导出');
            $table->unsignedTinyInteger('allow_copy')->default(0)->comment('是否允许拷贝');
            $table->unsignedTinyInteger('allow_rename')->default(1)->comment('是否重命名');
            $table->unsignedTinyInteger('allow_recycle')->default(1)->comment('是否支持回收站');
            $table->unsignedTinyInteger('track_changes')->default(0)->comment('是否跟踪修改记录');
            $table->unsignedTinyInteger('status')->default(0)->comment('是否已经使用，0：未使用，1:已使用	');
            $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间');
            $table->unsignedInteger('created_at')->default(0)->comment('创建时间');
            $table->unsignedInteger('updated_at')->default(0)->comment('更新时间');

            $table->index(['module', 'is_publish'], 'idx_mods_module_publish');
        });

        Schema::create('mod_fields', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mod_id')->default(0);
            $table->unsignedBigInteger('version_id')->default(0)->comment('当前缓存所属版本 ID');
            $table->string('name', 100)->comment('字段名称');
            $table->string('type', 50)->comment('字段类型');
            $table->string('label', 255)->comment('字段标题');
            $table->unsignedInteger('sort_order')->default(0)->comment('字段排序');
            $table->unsignedInteger('length')->default(0)->comment('字段长度');
            $table->string('default_val', 255)->nullable()->comment('默认值');
            $table->string('comment', 255)->nullable()->comment('字段说明');
            $table->unsignedTinyInteger('is_virtual')->default(0)->comment('是否虚拟字段');
            $table->unsignedTinyInteger('is_append')->default(0)->comment('是否追加字段');
            $table->unsignedTinyInteger('is_relation')->default(0)->comment('是否关联字段');
            $table->unsignedTinyInteger('is_required')->default(0)->comment('是否必填');
            $table->unsignedTinyInteger('is_unique')->default(0)->comment('是否唯一');
            $table->unsignedTinyInteger('is_search')->default(0)->comment('是否搜索字段');
            $table->unsignedTinyInteger('is_table')->default(0)->comment('是否列表展示');
            $table->json('field_json')->nullable()->comment('运行时使用的字段元数据缓存');
            $table->json('compiled_json')->nullable()->comment('编译后的字段结果缓存');
            $table->json('mapping_json')->nullable()->comment('字段映射缓存');
            $table->json('relation_json')->nullable()->comment('关联配置缓存');
            $table->json('rules_json')->nullable()->comment('字段规则缓存');
            $table->json('extra')->nullable()->comment('扩展信息');
            $table->unsignedInteger('created_at')->default(0)->comment('创建时间');
            $table->unsignedInteger('updated_at')->default(0)->comment('更新时间');

            $table->unique(['mod_id', 'version_id', 'name'], 'uniq_mod_fields_version_name');
            $table->index(['mod_id', 'version_id', 'sort_order'], 'idx_mod_fields_runtime');
        });

        app('cache')->store('default' !== config('easy.cache.store') ? config('easy.cache.store') : null)->forget(config('easy.cache.key'));
    }

    public function down(): void
    {
        Schema::dropIfExists('mods');
        Schema::dropIfExists('mod_fields');
    }
}
