<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * إعداد نوع إشعار واحد: مفعّل أم لا، وبأي قناة، ولمن.
 *
 * تُقرأ هذه الإعدادات قبل كل إشعار يُنشَأ، أي آلاف المرات يومياً، فهي
 * محفوظة في الذاكرة المؤقتة ككتلة واحدة وتُسقَط عند أي حفظ أو حذف.
 */
class NotificationSetting extends Model
{
    protected $table = 'notification_settings';

    protected $fillable = [
        'key', 'label', 'description', 'category', 'is_enabled',
        'channels', 'recipients', 'sms_template_code', 'sort_order', 'is_discovered',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_discovered' => 'boolean',
        'channels' => 'array',
        'recipients' => 'array',
    ];

    protected const CACHE_KEY = 'notification_settings.map';

    /** الفئات التي تُجمَّع تحتها الأنواع في شاشة الإعدادات */
    public const CATEGORIES = [
        'sales' => ['label' => 'المبيعات والفواتير', 'icon' => 'file-text', 'color' => 'primary'],
        'purchases' => ['label' => 'المشتريات والموردين', 'icon' => 'shopping-cart', 'color' => 'info'],
        'clients' => ['label' => 'العملاء والزيارات', 'icon' => 'users', 'color' => 'success'],
        'hr' => ['label' => 'الموارد البشرية', 'icon' => 'user-check', 'color' => 'warning'],
        'stock' => ['label' => 'المخزون والتصنيع', 'icon' => 'package', 'color' => 'secondary'],
        'realestate' => ['label' => 'إدارة العقارات', 'icon' => 'home', 'color' => 'info'],
        'system' => ['label' => 'النظام والتنبيهات', 'icon' => 'bell', 'color' => 'danger'],
        'other' => ['label' => 'أنواع غير مصنّفة', 'icon' => 'help-circle', 'color' => 'dark'],
    ];

    /**
     * القنوات. `database` هي القناة الأصلية للنظام (جدول `notifications`)
     * ولا يمكن إيقافها من هنا — إيقافها يساوي تعطيل الإشعار نفسه.
     */
    public const CHANNELS = [
        'database' => ['label' => 'داخل النظام', 'icon' => 'bell', 'hint' => 'يظهر في جرس الإشعارات — القناة الأساسية'],
        'mail' => ['label' => 'البريد الإلكتروني', 'icon' => 'mail', 'hint' => 'يتطلب ضبط إعدادات SMTP'],
        'sms' => ['label' => 'رسالة نصية', 'icon' => 'message-square', 'hint' => 'يتطلب ضبط بوابة الرسائل'],
    ];

    /**
     * جهات الاستلام. مبنية على الأدوار الموجودة فعلاً في قاعدة البيانات
     * (عمود `users.role` فيه manager/employee/client، وأدوار Spatie فيها
     * owner) — لا يوجد دور باسم admin في هذا النظام.
     */
    public const RECIPIENTS = [
        'receiver' => ['label' => 'المستلم المحدَّد', 'hint' => 'الموظف الذي يخصّه الإشعار — السلوك الحالي'],
        'managers' => ['label' => 'المدراء', 'hint' => 'كل مستخدم دوره manager'],
        'owners' => ['label' => 'أصحاب النظام', 'hint' => 'أصحاب دور owner في الصلاحيات'],
        'client' => ['label' => 'العميل نفسه', 'hint' => 'عبر البريد أو الرسائل النصية فقط'],
    ];

    /**
     * القوائم الثلاث بتسمياتها المترجَمة.
     *
     * الثوابت أعلاه تبقى مرجع المفاتيح المقبولة في التحقّق، وقيمها العربية
     * احتياطٌ يظهر إن نقصت الترجمة.
     */
    public static function categories(): array
    {
        return self::withLabels(self::CATEGORIES, 'notify.categories.', false);
    }

    public static function channels(): array
    {
        return self::withLabels(self::CHANNELS, 'notify.channel_labels.');
    }

    public static function recipients(): array
    {
        return self::withLabels(self::RECIPIENTS, 'notify.recipient_labels.');
    }

    private static function withLabels(array $list, string $prefix, bool $hints = true): array
    {
        $out = [];

        foreach ($list as $key => $meta) {
            $row = $meta;
            $row['label'] = self::tr($prefix.$key, $meta['label']);

            if ($hints && isset($meta['hint'])) {
                $row['hint'] = self::tr($prefix.$key.'_hint', $meta['hint']);
            }

            $out[$key] = $row;
        }

        return $out;
    }

    private static function tr(string $key, string $fallback): string
    {
        $full = 'settings.'.$key;
        $translated = __($full);

        return is_string($translated) && $translated !== $full ? $translated : $fallback;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * كل الإعدادات مفهرسةً بالمفتاح.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function map(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            // الجدول قد لا يكون موجوداً أثناء التهجير أو في اختبارات لا
            // تشغّل هذه الهجرة — يجب ألا يُسقط ذلك النظام كلّه
            try {
                return static::query()->get()->keyBy('key')
                    ->map(fn (self $s) => [
                        'is_enabled' => $s->is_enabled,
                        'channels' => $s->channels ?: ['database'],
                        'recipients' => $s->recipients ?: ['receiver'],
                        'label' => $s->label,
                        'sms_template_code' => $s->sms_template_code,
                    ])->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * هل يُسمح بإرسال هذا النوع؟
     *
     * نوع غير معرَّف يُسمح به: النظام كان يعمل قبل وجود هذا الجدول، ومنعُ
     * ما لم يُسجَّل بعد يعني إسكات إشعارات تعمل اليوم.
     */
    public static function allows(?string $type): bool
    {
        if ($type === null || $type === '') {
            return true;
        }

        $setting = static::map()[$type] ?? null;

        return $setting === null ? true : (bool) $setting['is_enabled'];
    }

    /** @return array<int, string> */
    public static function channelsFor(?string $type): array
    {
        return static::map()[$type]['channels'] ?? ['database'];
    }

    /** @return array<int, string> */
    public static function recipientsFor(?string $type): array
    {
        return static::map()[$type]['recipients'] ?? ['receiver'];
    }

    public static function labelFor(?string $type): ?string
    {
        return static::map()[$type]['label'] ?? null;
    }

    public function categoryLabel(): string
    {
        return isset(self::CATEGORIES[$this->category])
            ? self::tr('notify.categories.'.$this->category, self::CATEGORIES[$this->category]['label'])
            : $this->category;
    }
}
