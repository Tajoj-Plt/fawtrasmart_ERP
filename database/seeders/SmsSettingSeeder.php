<?php

namespace Database\Seeders;

use App\Models\SmsSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * صفّ الإعدادات الافتراضي لبوابة الرسائل.
 *
 * الشاشة كانت تفتح على جدول فارغ، فكل حقل بلا قيمة والمستخدم لا يعرف
 * ما المعقول فيه. هذا السيدر يضع صفّاً بقيم آمنة: البوابة **معطّلة**،
 * ووضع الاختبار **مفعَّل** — أي أن النظام بعد التنصيب يسجّل الرسائل ولا
 * يرسل واحدة حتى يُدخل المسؤول بياناته ويطفئ وضع الاختبار عن قصد.
 *
 * بيانات الاعتماد لا تُلمس إن كان الصفّ موجوداً: إعادة تشغيل السيدر
 * يجب ألّا تمحو مفتاح واجهة يعمل.
 *
 *     php artisan db:seed --class=SmsSettingSeeder
 */
class SmsSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('sms_settings')) {
            $this->command?->error('جدول sms_settings غير موجود — شغّل php artisan migrate أولاً.');

            return;
        }

        if (! Schema::hasColumn('sms_settings', 'provider')) {
            $this->command?->error('جدول sms_settings لم يُوسَّع بعد — شغّل php artisan migrate أولاً.');

            return;
        }

        if (SmsSetting::query()->exists()) {
            $this->command?->info('إعدادات الرسائل موجودة — لم تُلمس.');

            return;
        }

        SmsSetting::create(array_merge(SmsSetting::DEFAULTS, [
            // احتياطات لا تُترك للصدفة عند أول تنصيب
            'test_mode' => true,
            'is_active' => false,

            'timezone' => config('app.timezone', 'Asia/Riyadh'),

            // نافذة هدوء ليلية: رسالة فاتورة في الثالثة فجراً شكوى لا خدمة
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
            'quiet_hours_action' => 'defer',

            // سقوف ابتدائية تحدّ من ضرر خطأ برمجي يرسل في حلقة
            'rate_limit_per_minute' => 60,
            'daily_cap' => 2000,
            'per_recipient_daily_cap' => 5,
            'duplicate_window_minutes' => 10,

            'max_segments' => 4,
            'strip_emoji' => true,

            'log_enabled' => true,
            'log_body' => true,
            'log_retention_days' => 180,

            'notify_on_failure' => true,
            'failure_threshold' => 5,

            'cost_currency' => 'SAR',
        ]));

        SmsSetting::flush();

        $this->command?->info('أُنشئ صفّ إعدادات الرسائل — البوابة معطّلة ووضع الاختبار مفعَّل.');
    }
}
