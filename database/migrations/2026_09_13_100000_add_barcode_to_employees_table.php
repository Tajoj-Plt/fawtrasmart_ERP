<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بطاقةُ الموظف: الباركود الذي يُمسَح على جهاز الفرع، ومفتاحُ تعطيله.
 *
 * `BarcodeAttendanceController` كان يستعلم عن `employees.barcode` و
 * `employees.barcode_enabled` منذ كُتِب — وهما عمودان **لا وجود لهما** في
 * أيّ هجرة: لا في `create_employees_table` ولا فيما تلاها. فكلُّ مسحةِ
 * باركودٍ كانت تنتهي بـ«Unknown column 'barcode'»، وشاشةُ توليد الباركود
 * الجماعي تكتب في العدم. هذه الهجرة هي العمودُ الفقري الغائب.
 *
 * و`attendance_channel` يفصل موظفَ المقر عن الموظف الميداني: الأول يمسح
 * بطاقتَه على الجهاز الثابت، والثاني يسجّل من جواله داخل نطاق المرفق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'barcode')) {
                $table->string('barcode', 64)->nullable()->after('shift_id')
                    ->comment('باركود بطاقة الموظف — يُمسَح على جهاز الفرع');
            }

            if (! Schema::hasColumn('employees', 'barcode_enabled')) {
                $table->boolean('barcode_enabled')->default(true)->after('barcode')
                    ->comment('تعطيلُ البطاقة دون حذف الباركود — لبطاقةٍ مفقودة مثلاً');
            }

            if (! Schema::hasColumn('employees', 'attendance_channel')) {
                $table->string('attendance_channel', 16)->default('branch')->after('barcode_enabled')
                    ->comment('branch = جهاز الفرع | field = الجوال الميداني | both');
            }
        });

        // الباركودُ هويّةٌ: تكرارُه يعني تسجيلَ حضورِ موظفٍ باسم آخر.
        if (! SchemaGuard::hasIndex('employees', 'employees_barcode_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unique('barcode', 'employees_barcode_unique');
            });
        }
    }

    public function down(): void
    {
        if (SchemaGuard::hasIndex('employees', 'employees_barcode_unique')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique('employees_barcode_unique');
            });
        }

        Schema::table('employees', function (Blueprint $table) {
            foreach (['barcode', 'barcode_enabled', 'attendance_channel'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
