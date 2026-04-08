<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 审计日志表.
 *
 * 用于记录运行时 create/update/delete 等动作的前后快照。
 */
class CreateAuditLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('resource', 100)->comment('资源名称');
            $table->string('module', 50)->default('App')->comment('所属模块');
            $table->unsignedBigInteger('schema_version_id')->default(0)->comment('执行时使用的 schema 版本 ID');
            $table->string('operation', 30)->comment('操作类型');
            $table->unsignedBigInteger('record_id')->default(0)->comment('记录 ID');
            $table->json('payload')->nullable()->comment('请求载荷');
            $table->json('before_data')->nullable()->comment('操作前数据');
            $table->json('after_data')->nullable()->comment('操作后数据');
            $table->json('diff_data')->nullable()->comment('变更差异');
            $table->unsignedInteger('created_at')->default(0)->comment('记录时间');

            $table->index(['resource', 'operation'], 'idx_audit_logs_resource_operation');
            $table->index(['resource', 'schema_version_id'], 'idx_audit_logs_resource_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
}
