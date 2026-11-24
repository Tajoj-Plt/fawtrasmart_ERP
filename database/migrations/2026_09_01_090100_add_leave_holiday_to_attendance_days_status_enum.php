<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توسيعُ enum status ليشمل leave وholiday.
 *
 * `AttendanceDays::STATUS_LEAVE`/`STATUS_HOLIDAY`، ومعالِج `AttendanceProcessorService`
 * (`markAsHoliday`/`markAsWeeklyOff`/`markAsLeave`)، وقوالب دفتر الحضور
 * (`status == 'leave'`) — كلُّها مكتوبةٌ منذ زمن تفترض هاتين القيمتين. لكنّ
 * enum العمود بقي أربعةً فقط (`present/absent/late/half_day`)، وقاعدةُ
 * التطوير الحيّة على `STRICT_TRANS_TABLES`: فكلُّ يومِ عطلةٍ أو إجازةٍ
 * معتمدة يعالجه الكرون يومياً كان يفشل بخطأ SQL صريح، لا يُكتب صامتاً.
 *
 * فُحص المخطّط الحيّ قبل هذه الهجرة: العمود ما زال بقيمه الأربع الأصلية،
 * فلا توسيعٌ يدويٌّ سابقٌ يتعارض معها.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('attendance_days')) {
            return;
        }

        DB::statement(
            "ALTER TABLE attendance_days MODIFY COLUMN status ".
            "ENUM('present','absent','late','half_day','leave','holiday') ".
            "NOT NULL DEFAULT 'present'"
        );
    }

    public function down(): void
    {
        // SQLite (بيئة الاختبارات وحدها عبر phpunit.xml) لا يفهم
        // ALTER TABLE ... MODIFY، وقاعدةُ الاختبارات تُبنى من الصفر في كل مرة
        // فالعمودُ يخرج من هجرة الإنشاء بشكلٍ صالحٍ أصلاً.
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('attendance_days')) {
            return;
        }

        // القيمتان الجديدتان لا مكان لهما في enum الأصلي؛ تُعاد قراءتُهما
        // «غائب» قبل التضييق حتى لا يرفض MySQL التعديل بقيمٍ خارج المجال.
        DB::table('attendance_days')
            ->whereIn('status', ['leave', 'holiday'])
            ->update(['status' => 'absent']);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(
            "ALTER TABLE attendance_days MODIFY COLUMN status ".
            "ENUM('present','absent','late','half_day') ".
            "NOT NULL DEFAULT 'present'"
        );
        }
    }
};
