<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مكانُ تسجيل الموظف الميداني من جواله.
 *
 * كان الموظفُ الميداني يُقاس على دائرة فرعه (أو أيّ مكانٍ عامّ) — فمندوبٌ
 * عند عميلٍ في حيٍّ آخر يُردّ تسجيلُه لأنه ليس في المكتب. صار لكلّ موظفٍ
 * ميدانيٍّ خيار: فارغ = يُقبل من أيّ مكانٍ ويُحفظ موقعُه على الخريطة،
 * أو مكانٌ محدَّد من «أماكن التسجيل» (موقع مشروع، منطقة عمل).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'attendance_geofence_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('attendance_geofence_id')->nullable()->after('attendance_channel')
                    ->comment('مكان التسجيل من الجوال — فارغ = أي مكان مع حفظ الموقع');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'attendance_geofence_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('attendance_geofence_id');
            });
        }
    }
};
