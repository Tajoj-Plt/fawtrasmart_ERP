<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `attendance_days.attendance_sheets_id` — العمود الذي يربط يومَ الحضور
 * بدفتر الحضور الذي وُلِّد منه.
 *
 * النموذجُ والمتحكّمُ يفترضانه منذ البداية:
 *   AttendanceDays::$fillable يذكره
 *   AttendanceSheetsController يُدرجه عند توليد أيام الدفتر (السطر ٢٨١)
 *   ويستعلم به عند حذف موظفٍ من الدفتر (السطر ٤٣٨)
 * لكنه لم يُخلَق في أي هجرة، فكان كل مسٍّ لدفاتر الحضور ينتهي بـ 1054:
 * صفحةُ العرض تسقط، وإنشاءُ دفترٍ يفشل.
 *
 * nullable لأن أيام الحضور تُنشأ أيضاً من الباركود ومن معالج الحضور
 * التلقائي بلا دفتر.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_days') || Schema::hasColumn('attendance_days', 'attendance_sheets_id')) {
            return;
        }

        Schema::table('attendance_days', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_days', 'attendance_sheets_id')) {
                $table->unsignedBigInteger('attendance_sheets_id')->nullable()->after('employee_id');
            }
            if (! SchemaGuard::hasIndex('attendance_days', 'idx_att_days_sheet')) {
                $table->index('attendance_sheets_id', 'idx_att_days_sheet');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_days') || ! Schema::hasColumn('attendance_days', 'attendance_sheets_id')) {
            return;
        }

        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropIndex('idx_att_days_sheet');
            $table->dropColumn('attendance_sheets_id');
        });
    }
};
