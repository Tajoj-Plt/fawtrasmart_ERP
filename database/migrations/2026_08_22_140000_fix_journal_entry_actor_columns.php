<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إصلاح عمودَي «من أنشأ» و«من اعتمد» في القيود.
 *
 * **علّتان كُتبتا في البيانات:**
 *
 * ١) `created_by_employee` علاقتُه `belongsTo(User::class)` رغم اسمه، وكان
 *    إلغاءُ سند الصرف يكتب فيه `$user->employee_id`. فيُخزَّن رقم موظّفٍ
 *    في عمودٍ يُقرأ كرقم مستخدم، ولا مستخدم بذلك الرقم — فتطبع شاشة
 *    القيود «غير محدد» في عمود «بواسطة».
 *
 * ٢) القيود العكسية تُنشأ بحالة «معتمد» بلا `approved_by_employee`، فتقول
 *    شاشة عرض القيد «اعتمد بواسطة: -» عن قيدٍ معتمَد.
 *
 * الشيفرة صُحّحت في الكنترولرين؛ هذه الترحيلة تُصلح ما كُتب قبلها.
 *
 * الإصلاح محافظ: لا يُبدَّل معرّفٌ إلّا إن كان **لا يقابله مستخدم** ويقابله
 * موظّفٌ لمستخدمٍ واحدٍ بعينه — فلا يُنسب قيدٌ إلى غير صاحبه بالتخمين.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journal_entries') || ! Schema::hasTable('users')) {
            return;
        }

        $userIds = DB::table('users')->pluck('id')->all();

        // موظّف ← مستخدم، لمن له ربطٌ واحد لا لبس فيه
        $employeeToUser = DB::table('users')
            ->whereNotNull('employee_id')
            ->select('employee_id', DB::raw('COUNT(*) n'), DB::raw('MIN(id) uid'))
            ->groupBy('employee_id')
            ->having('n', '=', 1)
            ->pluck('uid', 'employee_id');

        foreach (['created_by_employee', 'approved_by_employee'] as $column) {
            if (! Schema::hasColumn('journal_entries', $column)) {
                continue;
            }

            $orphans = DB::table('journal_entries')
                ->whereNotNull($column)
                ->whereNotIn($column, $userIds)
                ->pluck($column, 'id');

            foreach ($orphans as $entryId => $value) {
                if (! isset($employeeToUser[$value])) {
                    continue;   // لا يقابله مستخدمٌ واحد — يُترك كما هو
                }

                DB::table('journal_entries')
                    ->where('id', $entryId)
                    ->update([$column => $employeeToUser[$value]]);
            }
        }

        /*
            قيدٌ معتمَد بلا معتمِد: يُنسب إلى منشئه.
            وهذا صحيحٌ في نظامٍ يُنشئ القيود معتمَدةً — من أنشأه هو من
            اعتمده، ولا خطوة اعتمادٍ منفصلة بينهما.
        */
        if (Schema::hasColumn('journal_entries', 'approved_by_employee')) {
            DB::table('journal_entries')
                ->where('status', 1)
                ->whereNull('approved_by_employee')
                ->whereNotNull('created_by_employee')
                ->update(['approved_by_employee' => DB::raw('created_by_employee')]);
        }
    }

    public function down(): void
    {
        // لا عكس: إرجاع معرّفاتٍ لا تقابل مستخدمين يُعيد «غير محدد».
    }
};
