<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دعم رفع أكثر من ملف ترجمة للغة الواحدة.
 *
 * العمود القديم translation_file يبقى كما هو ويحمل الملف الأحدث، حتى لا
 * تفقد السجلات المحفوظة قيمتها ولا تنكسر أي شاشة تقرأه، بينما يحمل
 * translation_files قائمة المسارات كاملة بصيغة JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_languages')) {
            return;
        }

        Schema::table('system_languages', function (Blueprint $table) {
            if (! Schema::hasColumn('system_languages', 'translation_files')) {
                $table->text('translation_files')->nullable()->after('translation_file');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_languages')) {
            return;
        }

        Schema::table('system_languages', function (Blueprint $table) {
            if (Schema::hasColumn('system_languages', 'translation_files')) {
                $table->dropColumn('translation_files');
            }
        });
    }
};
