<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 资源版本表.
 *
 * 用于保存草稿、已发布版本与历史快照。
 * 运行时主链不直接读取该表，而是通过 `mods + mod_fields`
 * 读取当前已发布缓存；该表仍然是真实版本历史来源。
 */
class CreateModelVersionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mod_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mod_id')->default(0)->comment('资源主表 ID');
            $table->string('name', 100)->comment('资源名称');
            $table->string('module', 50)->default('App')->comment('所属模块');
            $table->unsignedInteger('version_no')->default(1)->comment('版本号');
            $table->json('schema_json')->comment('完整 schema 快照');
            $table->string('status', 20)->default('published')->comment('版本状态：draft/published');
            $table->unsignedTinyInteger('is_current')->default(1)->comment('是否当前发布版本');
            $table->unsignedInteger('published_at')->default(0)->comment('发布时间');
            $table->string('remark', 255)->nullable()->comment('发布备注');
            $table->unsignedInteger('created_at')->default(0)->comment('创建时间');
            $table->unsignedInteger('updated_at')->default(0)->comment('更新时间');

            $table->index(['name', 'module'], 'idx_mod_versions_resource');
            $table->index(['mod_id', 'status'], 'idx_mod_versions_mod_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_versions');
    }
}
