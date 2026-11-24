<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * اختيار جداول بعينها في النسخة الاحتياطية.
 *
 * كانت النسخة إمّا كاملة أو لا شيء. الحاجة العملية أضيق: جداول الحركات
 * الكبيرة (السجلّات والإشعارات وسجلّ التنفيذ) تضخّم النسخة اليومية بلا
 * فائدة تُذكر، وجداول أخرى يُراد نسخها وحدها كل ساعة.
 *
 * الاختيار محفوظ في مكانين لا مكان واحد:
 * - في `backup_settings` — سياسة دائمة تسري على النسخ الدورية.
 * - في `backups` — ما استُعمل فعلاً في تلك النسخة بعينها، لأن النسخة
 *   اليدوية قد تخالف السياسة، ولأن معرفة ما تحويه نسخة قديمة شرطٌ لمعرفة
 *   ما يمكن استرجاعه منها. بدون هذا العمود تبدو نسخة جزئية كاملةً تماماً
 *   في السجلّ — وهو أخطر التباس ممكن في شاشة نسخ احتياطي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            // all | include | exclude
            if (! Schema::hasColumn('backup_settings', 'table_mode')) {
                $table->string('table_mode', 20)->default('all')->after('backup_type');
            }
            if (! Schema::hasColumn('backup_settings', 'included_tables')) {
                $table->json('included_tables')->nullable()->after('table_mode');
            }
            if (! Schema::hasColumn('backup_settings', 'excluded_tables')) {
                $table->json('excluded_tables')->nullable()->after('included_tables');
            }
        });

        Schema::table('backups', function (Blueprint $table) {
            if (! Schema::hasColumn('backups', 'table_mode')) {
                $table->string('table_mode', 20)->nullable()->after('type');
            }
            // أسماء الجداول كما مُرِّرت لأمر النسخ — لا معرّفات، فالجدول
            // قد يُحذف من القاعدة ويبقى اسمه في السجلّ دليلاً على ما نُسخ
            if (! Schema::hasColumn('backups', 'tables')) {
                $table->json('tables')->nullable()->after('table_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->dropColumn(['table_mode', 'included_tables', 'excluded_tables']);
        });

        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn(['table_mode', 'tables']);
        });
    }
};
