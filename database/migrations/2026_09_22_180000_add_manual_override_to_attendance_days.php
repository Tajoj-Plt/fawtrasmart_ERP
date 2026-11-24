<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قفل يوم حضور بعد اعتماد تصحيح يدوي — يمنع الكرون الليلي من إعادة كتابة
 * الحالة (مثلاً late → absent لأن الأوقات مُسحت أو أُعيد حسابها).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_days', 'manual_override')) {
                $table->boolean('manual_override')->default(false)->after('auto_generated')
                    ->comment('يوم مُثبَّت باعتماد تصحيح — لا يعيد الكرون كتابته');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_days', 'manual_override')) {
                $table->dropColumn('manual_override');
            }
        });
    }
};
