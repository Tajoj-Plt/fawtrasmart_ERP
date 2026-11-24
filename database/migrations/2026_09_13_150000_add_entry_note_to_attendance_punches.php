<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مبرِّرُ الإدخال اليدوي.
 *
 * الإدخالُ اليدوي استثناءٌ لا وسيلةُ تسجيل — والاستثناءُ بلا سببٍ مكتوب
 * لا يُراجَع. فمن سجّل يدوياً يكتب لماذا، ويبقى ذلك على الختم الذي لا
 * يُعدَّل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_punches', 'entry_note')) {
                $table->string('entry_note', 255)->nullable()->after('adjustment_code')
                    ->comment('سببُ الإدخال اليدوي — إلزاميٌّ في المسار اليدوي');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_punches', 'entry_note')) {
                $table->dropColumn('entry_note');
            }
        });
    }
};
