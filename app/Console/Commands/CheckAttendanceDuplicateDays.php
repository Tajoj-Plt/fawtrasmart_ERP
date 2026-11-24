<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فحصٌ للتشغيل قبل النشر — لا يكتب شيئاً.
 *
 * **المشكلة**
 *
 * هجرة `2026_09_01_090000_make_attendance_days_employee_date_unique`
 * تضيف قيداً فريداً على `attendance_days(employee_id, attendance_date)`،
 * وتفشل الدفعة كاملةً (لا تُصحِّح تلقائياً) لو وُجد أي زوجٍ مكرر — وكل
 * هجرةٍ بعدها في نفس الدفعة (تعديل enum، الترحيل من login_time، حذف
 * عمود، جدول طلبات التصحيح) تبقى غير مُطبَّقة معها.
 *
 * **ما يفعله هذا الأمر**
 *
 * يُشغِّل نفس استعلام الكشف عن التكرار الذي تُشغِّله الهجرة نفسها،
 * فيُعطي تحذيراً قبل النشر بدل اكتشاف الفشل أثناءه. لا يُعدِّل شيئاً —
 * القرار في دمج كل مجموعةٍ مكررة إلى صفٍّ واحد قرارٌ بشريٌّ يعتمد على
 * أيّ الصفوف يحمل البيانات الأصح (باركود أم إدخال يدوي، حقولٌ مكتملة
 * أم ناقصة).
 *
 *   php artisan attendance:check-duplicate-days
 *
 * كود الخروج غير صفري عند وجود تكرار — صالحٌ للربط في سكربت نشرٍ:
 *   php artisan attendance:check-duplicate-days && php artisan migrate --force
 */
class CheckAttendanceDuplicateDays extends Command
{
    protected $signature = 'attendance:check-duplicate-days';

    protected $description = 'فحص تكرار (employee_id, attendance_date) في attendance_days قبل هجرة القيد الفريد — بلا كتابة';

    public function handle(): int
    {
        if (! Schema::hasTable('attendance_days')) {
            $this->info('جدول attendance_days غير موجود بعد — لا شيء ليُفحص.');

            return self::SUCCESS;
        }

        $duplicates = DB::table('attendance_days')
            ->select('employee_id', 'attendance_date', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('employee_id', 'attendance_date')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('employee_id')
            ->orderBy('attendance_date')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('لا تكرار — القيد الفريد سيُطبَّق بأمان.');

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'وُجد %d زوج (موظف، تاريخ) مكرر — هجرة القيد الفريد ستفشل كاملةً على هذه الحالة:',
            $duplicates->count()
        ));
        $this->newLine();

        $this->table(
            ['employee_id', 'attendance_date', 'عدد الصفوف'],
            $duplicates->map(fn ($row) => [$row->employee_id, $row->attendance_date, $row->duplicate_count])
        );

        $this->newLine();
        $this->warn('وحِّد كل مجموعةٍ إلى صفٍّ واحد (احتفظ بالأدق بياناتٍ واحذف الباقي) قبل تشغيل php artisan migrate.');

        return self::FAILURE;
    }
}
