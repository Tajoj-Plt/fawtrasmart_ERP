<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الإعداد العامّ للمهام صفٌّ بلا مشروع — والعمود كان يرفضه.
 *
 * `task_settings.project_id` أُنشئ إلزامياً، ثم أُضيف `is_global` دون أن
 * يُرخى القيد. والإعدادات العامّة تُحفظ بـ`project_id = null`
 * ([\App\Models\TaskSettings::setSetting()])، فكان كل حفظٍ من شاشة
 * «الإعدادات العامّة للمهام» ينتهي باستثناء SQL: الشاشة تُعرض، والزرّ
 * يُضغط، ولا يُحفظ شيء.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_settings') || ! Schema::hasColumn('task_settings', 'project_id')) {
            return;
        }

        Schema::table('task_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('task_settings') || ! Schema::hasColumn('task_settings', 'project_id')) {
            return;
        }

        // الصفوف العامّة لا تعيش تحت القيد القديم
        DB::table('task_settings')->whereNull('project_id')->delete();

        Schema::table('task_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });
    }
};
