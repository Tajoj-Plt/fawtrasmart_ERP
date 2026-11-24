<?php

namespace App\Services;

use App\Models\notifications;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * تنبيهات النظام: فشل مهمة، أو نسخة احتياطية، أو توقّف المجدول.
 *
 * لا ينشئ قناة إشعارات جديدة — يكتب في جدول `notifications` نفسه، فيمرّ
 * على بوابة `NotificationSetting` الموجودة أصلاً وتنطبق عليه إعدادات
 * التفعيل والقنوات والمستلمين من شاشة إعدادات الإشعارات دون أي تعديل فيها.
 */
class SystemAlertService
{
    // مفاتيح الأنواع — مسجَّلة في `notification_settings` تحت فئة `system`
    public const CRON_FAILED = 'cron_job_failed';

    public const CRON_STUCK = 'cron_job_stuck';

    public const CRON_SUCCEEDED = 'cron_job_succeeded';

    public const SCHEDULER_DOWN = 'scheduler_down';

    // خلاصة كلّ تشغيلٍ مستحقّ لتسوية الأهداف — صرفٌ أو لا مستحقّ أو تخطٍّ
    public const TARGET_SETTLEMENT_RUN = 'target_settlement_run';

    public const BACKUP_COMPLETED = 'backup_completed';

    public const BACKUP_FAILED = 'backup_failed';

    public const BACKUP_CLEANUP_FAILED = 'backup_cleanup_failed';

    public const BACKUP_STORAGE_FULL = 'backup_storage_full';

    public const BACKUP_RESTORE_COMPLETED = 'backup_restore_completed';

    public const BACKUP_RESTORE_FAILED = 'backup_restore_failed';

    // تنبيهات الاشتراك عند فوترة — مصدرها المزامنة الدورية لا نداء لحظي
    public const SUBSCRIPTION_USAGE_THRESHOLD = 'subscription_usage_threshold';

    public const SUBSCRIPTION_USAGE_EXHAUSTED = 'subscription_usage_exhausted';

    public const SUBSCRIPTION_STATUS = 'subscription_status';

    /**
     * كل الأنواع التي يعرّفها هذا النظام، لتسجيلها في إعدادات الإشعارات.
     *
     * @return array<string, array<string, string>>
     */
    public static function types(): array
    {
        return [
            self::CRON_FAILED => [
                'label' => 'فشل مهمة مجدولة',
                'description' => 'يُرسَل عند فشل تنفيذ مهمة من مهام Cron.',
            ],
            self::CRON_STUCK => [
                'label' => 'مهمة مجدولة متعثّرة',
                'description' => 'يُرسَل عند فشل المهمة نفسها ثلاث مرّات متتالية.',
            ],
            self::CRON_SUCCEEDED => [
                'label' => 'نجاح مهمة مجدولة',
                'description' => 'يُرسَل عند نجاح مهمة فُعِّل لها «إشعار عند النجاح» — موقوف افتراضياً لأنه كثير التكرار.',
            ],
            self::SCHEDULER_DOWN => [
                'label' => 'توقّف المجدول',
                'description' => 'يُرسَل عند انقطاع نبض المجدول لفترة تتجاوز الحدّ المسموح.',
            ],
            self::TARGET_SETTLEMENT_RUN => [
                'label' => 'نتيجة التسوية المجدولة للأهداف',
                'description' => 'يُرسَل عند كلّ تشغيلٍ مستحقّ لتسوية أهداف المبيعات أو التحصيلات: ما صُرف، أو أنّه لا مستحقّ، أو سبب التخطّي.',
            ],
            self::BACKUP_COMPLETED => [
                'label' => 'اكتمال نسخة احتياطية',
                'description' => 'يُرسَل عند نجاح إنشاء نسخة احتياطية.',
            ],
            self::BACKUP_FAILED => [
                'label' => 'فشل نسخة احتياطية',
                'description' => 'يُرسَل عند فشل إنشاء نسخة احتياطية.',
            ],
            self::BACKUP_CLEANUP_FAILED => [
                'label' => 'فشل تنظيف النسخ القديمة',
                'description' => 'يُرسَل عند تعذّر حذف النسخ التي تجاوزت سياسة الاحتفاظ.',
            ],
            self::BACKUP_STORAGE_FULL => [
                'label' => 'امتلاء مساحة النسخ الاحتياطية',
                'description' => 'يُرسَل عند تجاوز حجم النسخ الحدّ المضبوط في الإعدادات.',
            ],
            self::BACKUP_RESTORE_COMPLETED => [
                'label' => 'اكتمال استرجاع نسخة',
                'description' => 'يُرسَل عند نجاح استرجاع قاعدة البيانات من نسخة احتياطية.',
            ],
            self::BACKUP_RESTORE_FAILED => [
                'label' => 'فشل استرجاع نسخة',
                'description' => 'يُرسَل عند فشل استرجاع قاعدة البيانات.',
            ],
            self::SUBSCRIPTION_USAGE_THRESHOLD => [
                'label' => 'بلوغ حدّ استخدام في الاشتراك',
                'description' => 'يُرسَل عند بلوغ ميزة ٨٠٪ من حدّها المسموح في باقة فوترة.',
            ],
            self::SUBSCRIPTION_USAGE_EXHAUSTED => [
                'label' => 'استنفاد حدّ استخدام في الاشتراك',
                'description' => 'يُرسَل عند استنفاد حدّ ميزة بالكامل — العمليات التالية عليها تُرفَض.',
            ],
            self::SUBSCRIPTION_STATUS => [
                'label' => 'حالة الاشتراك',
                'description' => 'يُرسَل عند قرب انتهاء الاشتراك أو انتهائه أو إيقافه أو وجود دفعة مستحقّة.',
            ],
        ];
    }

    /**
     * إرسال تنبيه لمسؤولي النظام.
     *
     * الإرسال لا يجب أن يُسقط ما استدعاه: مهمة نجحت ثم تعذّر إشعار المسؤول
     * تبقى مهمة ناجحة. لذلك كل شيء هنا محاط بـ try/catch مع تسجيل.
     */
    public static function send(string $type, string $title, string $description = '', ?int $clientId = null): int
    {
        try {
            if (! NotificationSetting::allows($type)) {
                return 0;
            }

            $receivers = static::receiversFor($type);

            if ($receivers === []) {
                Log::warning('تنبيه نظام بلا مستلمين', ['type' => $type, 'title' => $title]);

                return 0;
            }

            $created = 0;

            foreach ($receivers as $receiverId) {
                $notification = notifications::create([
                    'user_id' => auth()->id(),
                    'receiver_id' => $receiverId,
                    'client_id' => $clientId,
                    'title' => $title,
                    'description' => $description,
                    'type' => $type,
                    'read' => 0,
                ]);

                // البوابة قد ترفض الإنشاء فتُرجع نموذجاً غير محفوظ
                if ($notification->exists) {
                    $created++;
                }
            }

            return $created;
        } catch (\Throwable $e) {
            Log::error('تعذّر إرسال تنبيه النظام', [
                'type' => $type,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * معرّفات المستخدمين الذين يستلمون هذا النوع.
     *
     * تُقرأ من إعداد «من يستلم» في شاشة إعدادات الإشعارات. تنبيهات النظام
     * لا مستلم «مخصَّص» لها، فإن لم يُختَر شيء صالح رجعنا إلى المدراء
     * وأصحاب النظام — تنبيه بلا مستلم يساوي عدم وجوده.
     *
     * @return array<int, int>
     */
    protected static function receiversFor(string $type): array
    {
        $targets = NotificationSetting::recipientsFor($type);
        $ids = [];

        if (in_array('managers', $targets, true)) {
            $ids = array_merge($ids, User::where('role', 'manager')->pluck('id')->all());
        }

        if (in_array('owners', $targets, true)) {
            $ids = array_merge($ids, static::ownerIds());
        }

        if ($ids === []) {
            $ids = array_merge(
                User::where('role', 'manager')->pluck('id')->all(),
                static::ownerIds()
            );
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** @return array<int, int> */
    protected static function ownerIds(): array
    {
        try {
            return User::role('owner')->pluck('id')->all();
        } catch (\Throwable) {
            // الدور قد لا يكون معرَّفاً في تركيب آخر — لا يبرّر إسقاط التنبيه
            return [];
        }
    }
}
