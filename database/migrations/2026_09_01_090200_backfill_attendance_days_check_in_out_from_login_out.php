<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تمهيدٌ لتوحيد مصدر الحقيقة: نسخُ ما كُتب سابقاً في login_time/logout_time
 * إلى check_in_time/check_out_time قبل أن تتوقّف الشاشةُ اليدوية عن كتابة
 * الأوليين.
 *
 * الشاشةُ اليدوية (`AttendanceDaysController`) كانت تكتب login_time/logout_time
 * فقط، بينما معالجُ الكرون (`AttendanceProcessorService`) وشاشةُ الباركود
 * لا يقرآن سوى check_in_time/check_out_time — فكل يوم حضورٍ أُدخل يدوياً كان
 * يُعامَل غياباً في المعالجة التلقائية رغم تسجيله. هذه الهجرة نسخٌ فقط، لا
 * حذف: العمودان القديمان يبقيان كما هما لأي قراءةٍ تاريخية.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_days')) {
            return;
        }

        DB::table('attendance_days')
            ->whereNull('check_in_time')
            ->whereNotNull('login_time')
            ->update(['check_in_time' => DB::raw('login_time')]);

        DB::table('attendance_days')
            ->whereNull('check_out_time')
            ->whereNotNull('logout_time')
            ->update(['check_out_time' => DB::raw('logout_time')]);
    }

    public function down(): void
    {
        // نسخٌ إلى الأمام فقط؛ لا شيء يُعكس — العمودان القديمان لم يُمسَّا.
    }
};
