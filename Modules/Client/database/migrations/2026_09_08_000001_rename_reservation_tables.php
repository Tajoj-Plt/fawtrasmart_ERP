<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * تصحيح اسمَي جدولَي الحجوزات — كانا مقلوبين.
 *
 * ما كان:
 *
 *   `bookings`     ← مستند الحجز نفسه (تاريخ، وقت، عميل، موظف).
 *   `reservations` ← **إعدادات** الحجز (`duration`, `payment_online`)،
 *                    يقرؤها `App\Models\BookingSetting`.
 *
 * فالاسم المتوقَّع للحجز محجوزٌ لشيء آخر: لا يستطيع أحد إنشاء جدول
 * `reservations` للحجوزات، ومن يقرأ الشيفرة يفترض أنّه الحجوزات فيبني
 * عليه استعلاماً يعود بصفٍّ واحد لا معنى له.
 *
 * ما صار:
 *
 *   `reservations`          ← مستند الحجز.
 *   `reservation_settings`  ← إعدادات الوحدة.
 *
 * **لماذا الآن ولماذا آمن؟** فُحص عددُ الصفوف في الجدولين قبل الكتابة:
 * صفرٌ في كليهما. فلا بيانات تُنقَل ولا شيء يُفقَد. ولو حملا صفوفاً لما
 * جاز هذا القرار أصلاً.
 *
 * والتوافق الخلفي محفوظ في النماذج: `App\Models\Booking` و
 * `App\Models\BookingSetting` يبقيان ويشيران بـ`$table` إلى الاسمين
 * الجديدين — فلا ينكسر `Client::bookings()` ولا تبويب حجوزات العميل.
 *
 * الترتيب حاسم: تُخلى التسمية `reservations` أوّلاً ثم تُشغَل.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) الإعدادات تُخلي الاسم
        if (Schema::hasTable('reservations') && ! Schema::hasTable('reservation_settings')) {
            // الجدول القديم يُعرَف بعموده `payment_online` — وهو ما لا وجود
            // له في جدول حجوزاتٍ بأي حال. الفحص يمنع إعادةَ تسميةِ جدول
            // حجوزاتٍ صحيحٍ أُنشئ بترتيبٍ مختلف.
            if (Schema::hasColumn('reservations', 'payment_online')) {
                Schema::rename('reservations', 'reservation_settings');
            }
        }

        // 2) الحجوزات تأخذ اسمها
        if (Schema::hasTable('bookings') && ! Schema::hasTable('reservations')) {
            Schema::rename('bookings', 'reservations');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reservations') && ! Schema::hasTable('bookings')) {
            Schema::rename('reservations', 'bookings');
        }

        if (Schema::hasTable('reservation_settings') && ! Schema::hasTable('reservations')) {
            Schema::rename('reservation_settings', 'reservations');
        }
    }
};
