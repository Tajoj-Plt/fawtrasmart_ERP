<?php

use App\Support\SchemaGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * قفلُ صفٍّ واحد ليوم الموظف — لا فهرسٌ فحسب.
 *
 * `idx_att_emp_date` كان موجوداً منذ الإنشاء لكن غير Unique، فمصدرا
 * الحضور المتوازيان (الباركود ونموذج الإدخال اليدوي) قادران على كتابة
 * أكثر من صفٍّ لنفس (employee_id, attendance_date) — وهو ما يُوحّد عليه
 * مسارُ الصرف لاحقاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('attendance_days')
            ->select('employee_id', 'attendance_date', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('employee_id', 'attendance_date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $pairs = $duplicates
                ->map(fn ($row) => "employee_id={$row->employee_id} attendance_date={$row->attendance_date} (×{$row->duplicate_count})")
                ->implode(', ');

            throw new RuntimeException(
                "لا يمكن تطبيق القيد الفريد: توجد صفوف حضورٍ مكررة ليوم الموظف نفسه — {$pairs}. ".
                'راجع البيانات ووحّد كل مجموعةٍ إلى صفٍّ واحد قبل إعادة تشغيل هذه الهجرة.'
            );
        }

        // ترتيبٌ إلزامي: `idx_att_emp_date` يغطّي المفتاح الأجنبي على
        // employee_id، وMySQL يرفض حذف الفهرس الذي يغطّي مفتاحاً أجنبياً
        // ما لم يوجد فهرسٌ بديلٌ عليه أوّلاً — فالفهرسُ الفريد يُنشأ قبل
        // حذف القديم لا بعده.
        Schema::table('attendance_days', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('attendance_days'))->pluck('name')->toArray();

            if (! in_array('attendance_days_employee_date_unique', $indexes)) {
                if (! SchemaGuard::hasIndex('attendance_days', 'attendance_days_employee_date_unique')) {
                    $table->unique(['employee_id', 'attendance_date'], 'attendance_days_employee_date_unique');
                }
            }
        });

        Schema::table('attendance_days', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('attendance_days'))->pluck('name')->toArray();

            if (in_array('idx_att_emp_date', $indexes)) {
                if (SchemaGuard::hasIndex('attendance_days', 'idx_att_emp_date')) {
                    $table->dropIndex('idx_att_emp_date');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('attendance_days'))->pluck('name')->toArray();

            if (! in_array('idx_att_emp_date', $indexes)) {
                $table->index(['employee_id', 'attendance_date'], 'idx_att_emp_date');
            }
        });

        Schema::table('attendance_days', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('attendance_days'))->pluck('name')->toArray();

            if (in_array('attendance_days_employee_date_unique', $indexes)) {
                $table->dropUnique('attendance_days_employee_date_unique');
            }
        });
    }
};
