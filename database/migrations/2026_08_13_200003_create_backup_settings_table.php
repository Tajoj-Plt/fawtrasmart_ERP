<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات النسخ الاحتياطي — صفّ واحد لكل النظام.
 *
 * كان في `account_settings` عمودان `backup_auto` و `backup_frequency` لا
 * يقرأهما أي كود، وهما لكل مستخدم بينما النسخ الاحتياطي عملية على مستوى
 * النظام كلّه. الجدول هنا يستبدلهما بمصدر واحد فعليّ التأثير.
 *
 * ما يبقى في `.env` عمداً: كلمة تعشية الأرشيف وبيانات اعتماد التخزين
 * السحابي — أسرار لا مكان لها في قاعدة بيانات تُنسَخ احتياطياً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backup_settings')) {
            Schema::create('backup_settings', function (Blueprint $table) {
                $table->id();

                $table->boolean('is_enabled')->default(false);

                // database | files | both
                $table->string('backup_type', 20)->default('database');
                // اسم قرص من `config/filesystems.php` — يُتحقَّق منه قبل الحفظ
                $table->string('disk', 60)->default('local');
                $table->json('included_paths')->nullable();
                $table->json('excluded_paths')->nullable();

                // manual | hourly | daily | weekly | monthly | custom
                $table->string('frequency', 20)->default('daily');
                $table->string('cron_expression', 120)->nullable();
                $table->time('run_time')->default('02:00:00');
                $table->unsignedTinyInteger('run_day')->nullable();

                // count = احتفظ بآخر N نسخة، days = احتفظ بما عمره أقل من N يوماً
                $table->string('retention_strategy', 20)->default('count');
                $table->unsignedInteger('retention_count')->default(7);
                $table->unsignedInteger('retention_days')->default(30);
                // حدّ تنبيهي لا حدّ مانع — يُطلق إشعار «التخزين ممتلئ»
                $table->unsignedInteger('max_storage_mb')->nullable();

                $table->boolean('notify_on_success')->default(false);
                $table->boolean('notify_on_failure')->default(true);
                $table->boolean('notify_on_cleanup_failure')->default(true);
                $table->boolean('notify_on_storage_full')->default(true);
                $table->json('notification_recipients')->nullable();

                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
