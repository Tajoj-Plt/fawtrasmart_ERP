<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * موعدُ الحجز — `appointments.reservation_id`.
 *
 * الحجزُ المؤكَّد يُكتب موعداً في شاشة «المواعيد» (`ReservationAppointmentSync`).
 * والعمودُ هو ما يجعل الحجزَ وموعدَه صفّاً واحداً يُتابَع: تُنقل إعادةُ
 * الجدولة إلى الموعد نفسِه، ويُغلق الإلغاءُ الموعدَ نفسَه، ولا يُنشئ تأكيدٌ
 * ثانٍ بعد إعادة الجدولة موعداً مكرّراً — فهو فريد.
 *
 * بلا مفتاحٍ أجنبي: جدولُ المواعيد قديمٌ ومتفاوتُ البنية بين الاستضافات،
 * والحجزُ يُحذف حذفاً ناعماً فيبقى المرجعُ صالحاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointments') || Schema::hasColumn('appointments', 'reservation_id')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('reservation_id')->nullable()->after('client_id')->unique();
        });
    }

    /** لا حذفَ في التراجع — الأعمدةُ تُتقاعَد ولا تُسقَط. */
    public function down(): void {}
};
