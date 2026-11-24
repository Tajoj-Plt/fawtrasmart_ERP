<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `App\Helpers\TaskSettingsHelper` يستعلم عن `is_global` منذ إنشائه، لكن
 * هذا العمود لم يكن موجوداً فعلياً في جدول `task_settings` — ما يفشل كل
 * استدعاء يمرّ عبر `EnsureProjectSettings` (مطبَّق على كل مسارات الوحدة).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_settings') || Schema::hasColumn('task_settings', 'is_global')) {
            return;
        }

        Schema::table('task_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('task_settings', 'is_global')) {
                $table->boolean('is_global')->default(false)->after('project_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('task_settings') || ! Schema::hasColumn('task_settings', 'is_global')) {
            return;
        }

        Schema::table('task_settings', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });
    }
};
