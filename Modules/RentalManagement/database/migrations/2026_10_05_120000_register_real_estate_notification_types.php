<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RentalManagement\Support\RealEstateNotifications;

/**
 * أنواع إشعارات العقارات في «إعدادات الإشعارات»، وخاتمُ تنبيه المهمة الفائتة.
 *
 * ١. صفٌّ في `notification_settings` لكلّ نوعٍ من
 *    {@see RealEstateNotifications::catalog()} تحت فئة «إدارة العقارات» — بلا
 *    الصفّ يمرّ الإشعارُ (النوعُ المجهول مسموح) لكنه لا يظهر للمسؤول فلا يملك
 *    إيقافه ولا توجيهه إلى بريدٍ أو رسالة.
 *    الصفُّ الموجود لا يُمَسّ منه إلا التسميةُ والوصفُ والفئة: التفعيلُ
 *    والقنواتُ والمستلمون خيارُ المسؤول.
 * ٢. `re_tasks.due_alert_sent_at`: متى نُبِّه عن فوات موعد المهمة — فلا يتكرّر
 *    التنبيه كلّ يوم (`realestate:alerts`).
 *
 * قابلةٌ للتكرار: كلُّ خطوةٍ تفحص قبل أن تكتب.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('re_tasks') && ! Schema::hasColumn('re_tasks', 'due_alert_sent_at')) {
            Schema::table('re_tasks', function (Blueprint $table) {
                $table->timestamp('due_alert_sent_at')->nullable()->after('completed_at');
            });
        }

        if (! Schema::hasTable('notification_settings')) {
            return;
        }

        $order = (int) DB::table('notification_settings')
            ->where('category', '!=', RealEstateNotifications::CATEGORY)->max('sort_order');

        foreach (RealEstateNotifications::catalog() as $key => $meta) {
            $order += 10;
            $existing = DB::table('notification_settings')->where('key', $key)->first();

            if ($existing) {
                DB::table('notification_settings')->where('id', $existing->id)->update([
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                    'category' => RealEstateNotifications::CATEGORY,
                    'is_discovered' => false,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('notification_settings')->insert([
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'category' => RealEstateNotifications::CATEGORY,
                'is_enabled' => true,
                'channels' => json_encode(['database']),
                'recipients' => json_encode(['receiver']),
                'sort_order' => $order,
                'is_discovered' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // الإعدادات محفوظةٌ في الذاكرة المؤقتة ككتلة واحدة — انظر `NotificationSetting::flush()`
        Cache::forget('notification_settings.map');
    }

    public function down(): void
    {
        // صفوف الإعدادات تبقى: حذفُها يمحو ما ضبطه المسؤول من قنواتٍ ومستلمين
        if (Schema::hasTable('re_tasks') && Schema::hasColumn('re_tasks', 'due_alert_sent_at')) {
            Schema::table('re_tasks', function (Blueprint $table) {
                $table->dropColumn('due_alert_sent_at');
            });
        }
    }
};
