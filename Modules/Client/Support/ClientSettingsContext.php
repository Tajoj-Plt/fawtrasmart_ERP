<?php

namespace Modules\Client\Support;

use App\Models\CronJob;
use App\Models\DocumentTemplate;
use App\Models\NotificationSetting;
use App\Models\PaymentMethod;
use App\Services\Fawtra\SubscriptionGate;
use App\Support\AccountingPeriodGuard;
use App\Support\AccountingSettings;
use App\Support\BusinessInfo;
use App\Support\CurrencySettings;
use App\Support\LanguageSettings;
use App\Support\ThemeSettings;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * إعدادات النظام التي تعمل بها شاشات وحدة العملاء.
 *
 * نظيرُ `Modules\Sales\Support\SalesSettingsContext` لوحدة العملاء، وبنودُه
 * أوسع لأنّ ما تعمل به شاشاتُ العملاء أوسع:
 *
 *   • *المظهر*   ← يحقن `--app-*` التي بُني عليها نظام `cl-*` كلّه.
 *   • *اللغة*    ← الاتجاه وتنسيقُ التاريخ في كل شاشة عميل.
 *   • *العملة*   ← رصيد العميل وحدُّه الائتماني وكشفُ حسابه.
 *   • *طرق الدفع*← ما يظهر في سند قبض العميل ودفعاته.
 *   • *الحسابات* ← قيدُ الدين المشكوك فيه والشطبِ والتسويةِ من شاشة العميل.
 *   • *الفترات*  ← تاريخٌ في فترةٍ مقفلة يردّ قيدَ الدين والشطب.
 *   • *المنشأة*  ← رأسُ كشف الحساب المطبوع وتقارير العميل.
 *   • *القوالب*  ← قوالب كشف الحساب وتقارير العميل المصمَّمة في الإعدادات.
 *   • *المهام*   ← الزياراتُ التلقائية وتذكيرُ المواعيد وقبولُ التحويلات
 *                  التلقائي — كلّها مهامٌّ مجدولة، فإن لم تكن مفعَّلةً بقيت
 *                  الإعداداتُ حبراً على ورق.
 *   • *الإشعارات*← إشعارُ الموظف عند التعيين وعند تحويل الفاتورة.
 *   • *الاشتراك* ← نشاطُه وحده: كتالوج «فوترة» لا يحمل مفتاح حدٍّ للعملاء،
 *                  فلا سقفَ يُعرَض — انظر `ClientsPlanFeatures`.
 *
 * كان ذلك كلّه يجري في الخلفية، فيرى المستخدمُ حفظاً مرفوضاً أو مبلغاً بلا
 * رمز ولا يعرف أين يُصلحه. وكلُّ بندٍ هنا رابطٌ إلى شاشته.
 *
 * **لماذا كلّ قراءة داخل `try`؟** الشريط سياقٌ لا وظيفة: وحدة الإعدادات قد
 * تكون معطَّلة في `modules_statuses.json`، أو تكون هجرةٌ لم تُشغَّل بعد،
 * فيسقط `CronJob::count()` باستثناء. شريطٌ ناقص أهون من شاشةٍ لا تُفتح.
 *
 * @see \Modules\Sales\Support\SalesSettingsContext النسخة الأمّ
 * @see Modules/Client/resources/views/components/system-context.blade.php
 */
class ClientSettingsContext
{
    /**
     * مفاتيح المهام المجدولة التي تُشغّل إعدادات هذه الوحدة.
     *
     * تُقرأ من `cron_jobs` بأسمائها لا بعددها الكلّي: «٣ مهامّ مفعّلة» في
     * النظام لا يقول لصاحب هذه الشاشة إن كان تذكيرُ مواعيده يعمل.
     *
     * `clients:generate-visits` (لا أمرَ بهذا الاسم أصلاً) و
     * `clients:auto-accept-transfers` أُزيلا مع ما هو تلقائيّ في 2026-09-14.
     */
    public const CRON_KEYS = [
        'clients:appointment-reminders',
    ];

    /** أنواع المستندات التي تخصّ هذه الوحدة في «قوالب المستندات». */
    public const TEMPLATE_TYPES = [
        'client_statement',
    ];

    /** @var array<string, mixed>|null */
    protected static ?array $cache = null;

    /**
     * البنود المعروضة في الشريط، بعد ضبط روابطها.
     *
     * @return array<int, array{key:string,icon:string,label:string,value:string,tone:string,url:?string}>
     */
    public static function items(): array
    {
        $s = static::summary();

        $items = [
            [
                'key' => 'appearance',
                'icon' => 'droplet',
                'label' => __('client_settings.context.appearance'),
                'value' => $s['theme'],
                'tone' => 'neutral',
                'route' => 'Appearance.index',
            ],
            [
                'key' => 'language',
                'icon' => 'globe',
                'label' => __('client_settings.context.language'),
                'value' => $s['language'],
                'tone' => 'neutral',
                'route' => 'SittingAccount.language',
            ],
            [
                'key' => 'currency',
                'icon' => 'dollar-sign',
                'label' => __('client_settings.context.currency'),
                'value' => $s['currency'],
                'tone' => 'neutral',
                'route' => 'SittingAccount.currency',
            ],
            [
                'key' => 'payments',
                'icon' => 'credit-card',
                'label' => __('client_settings.context.payments'),
                'value' => $s['payments'] > 0
                    ? __('client_settings.context.count_value', ['count' => CurrencySettings::number($s['payments'], 0)])
                    : __('client_settings.context.none'),
                'tone' => $s['payments'] > 0 ? 'on' : 'off',
                'route' => 'PaymentMethods.index',
            ],
            [
                'key' => 'accounts',
                'icon' => 'book-open',
                'label' => __('client_settings.context.accounts'),
                'value' => $s['accounts'] > 0
                    ? __('client_settings.context.count_value', ['count' => CurrencySettings::number($s['accounts'], 0)])
                    : __('client_settings.context.none'),
                'tone' => $s['accounts'] > 0 ? 'on' : 'off',
                'route' => 'AccountingSettings.index',
            ],
            [
                'key' => 'period',
                'icon' => 'lock',
                'label' => __('client_settings.context.period'),
                'value' => $s['period_open']
                    ? __('client_settings.context.period_open')
                    : __('client_settings.context.period_blocked'),
                'tone' => $s['period_open'] ? 'on' : 'off',
                'route' => 'AccountingPeriods.index',
            ],
            [
                'key' => 'business',
                'icon' => 'briefcase',
                'label' => __('client_settings.context.business'),
                'value' => $s['business_complete']
                    ? __('client_settings.context.business_ok')
                    : __('client_settings.context.business_missing'),
                'tone' => $s['business_complete'] ? 'on' : 'off',
                'route' => 'SittingAccount.index',
            ],
            [
                'key' => 'templates',
                'icon' => 'file-text',
                'label' => __('client_settings.context.templates'),
                'value' => $s['templates'] > 0
                    ? __('client_settings.context.count_value', ['count' => CurrencySettings::number($s['templates'], 0)])
                    : __('client_settings.context.none'),
                'tone' => $s['templates'] > 0 ? 'on' : 'off',
                'route' => 'document-templates.index',
            ],
            [
                'key' => 'cron',
                'icon' => 'clock',
                'label' => __('client_settings.context.cron'),
                'value' => $s['cron_total'] > 0
                    ? __('client_settings.context.ratio_value', [
                        'enabled' => CurrencySettings::number($s['cron'], 0),
                        'total' => CurrencySettings::number($s['cron_total'], 0),
                    ])
                    : __('client_settings.context.none'),
                'tone' => $s['cron'] > 0 ? 'on' : 'off',
                'route' => 'CronJobs.index',
            ],
            [
                'key' => 'subscription',
                'icon' => 'award',
                'label' => __('client_settings.context.subscription'),
                'value' => $s['subscription_active']
                    ? __('client_settings.context.subscription_active')
                    : __('client_settings.context.subscription_inactive'),
                'tone' => $s['subscription_active'] ? 'on' : 'off',
                'route' => 'Subscription.index',
            ],
            [
                'key' => 'notifications',
                'icon' => 'bell',
                'label' => __('client_settings.context.notifications'),
                'value' => $s['notifications_total'] > 0
                    ? __('client_settings.context.ratio_value', [
                        'enabled' => CurrencySettings::number($s['notifications'], 0),
                        'total' => CurrencySettings::number($s['notifications_total'], 0),
                    ])
                    : __('client_settings.context.none'),
                'tone' => $s['notifications'] > 0 ? 'on' : 'off',
                'route' => 'NotificationSettings.index',
            ],
        ];

        // مسارٌ غير موجود يعني وحدة الإعدادات معطَّلة: يُعرَض البند بقيمته
        // ولا يُعرَض رابطه، فلا يرمي `route()` استثناءً يكسر الشاشة
        return array_map(function (array $item) {
            $item['url'] = Route::has($item['route']) ? route($item['route']) : null;

            return $item;
        }, $items);
    }

    /**
     * القراءات الخام — لمن أراد قيمةً بعينها دون بناء الشريط.
     *
     * @return array<string, mixed>
     */
    public static function summary(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        return static::$cache = [
            'theme' => static::safe(fn () => static::themeLabel(), '—'),
            'language' => static::safe(
                fn () => LanguageSettings::current()?->name ?? strtoupper(app()->getLocale()),
                strtoupper(app()->getLocale())
            ),
            'currency' => static::safe(
                fn () => CurrencySettings::code().' — '.CurrencySettings::symbol(),
                '—'
            ),
            'payments' => static::safe(
                fn () => PaymentMethod::query()->where('status', 'active')->count(),
                0
            ),
            'accounts' => static::safe(fn () => count(AccountingSettings::map()), 0),

            // اليوم لا تاريخ المستند: الشريط يقول «هل يُقبَل القيد الآن؟»،
            // وفحصُ تاريخ المستند نفسه يبقى في المتحكّم عند الحفظ
            'period_open' => static::safe(fn () => AccountingPeriodGuard::allows(now()), true),

            'business_complete' => static::safe(fn () => static::businessComplete(), true),

            'templates' => static::safe(
                fn () => DocumentTemplate::query()
                    ->whereIn('document_type', static::TEMPLATE_TYPES)
                    ->where('is_active', true)
                    ->count(),
                0
            ),

            'cron' => static::safe(
                fn () => CronJob::query()
                    ->whereIn('handler', static::CRON_KEYS)
                    ->where('status', CronJob::STATUS_ACTIVE)
                    ->count(),
                0
            ),
            'cron_total' => static::safe(
                fn () => CronJob::query()->whereIn('handler', static::CRON_KEYS)->count(),
                0
            ),

            /* نشاطُ الاشتراك وحده يُقرأ هنا — لا حدَّ لميزةٍ ولا سقفَ عدد.
               كتالوج فوترة لا يحمل مفتاحاً للعملاء أصلاً، وقراءةُ حدٍّ
               مخترَعٍ تعود «بلا حدّ» فتعرض رقماً لا يعنيه أحد.
               انظر `App\Constants\planFeatures\ClientsPlanFeatures`. */
            'subscription_active' => static::safe(
                fn () => app(SubscriptionGate::class)->active(),
                true
            ),

            'notifications' => static::safe(
                fn () => NotificationSetting::query()
                    ->where('category', 'clients')->where('is_enabled', true)->count(),
                0
            ),
            'notifications_total' => static::safe(
                fn () => NotificationSetting::query()->where('category', 'clients')->count(),
                0
            ),
        ];
    }

    /** يُنسى المُذكَّر — للاختبارات ولمن يغيّر إعداداً ثم يقرأ في نفس الطلب. */
    public static function forget(): void
    {
        static::$cache = null;
    }

    /**
     * وصفٌ مختصر للمظهر: الوضع واللون الأساسي.
     *
     * `ThemeSettings` هي مصدر `--app-*` التي يقوم عليها نظام `cl-*`، فذِكرها
     * في الشريط يجيب عن «لماذا صارت الشاشة بهذا اللون؟».
     */
    protected static function themeLabel(): string
    {
        $primary = ThemeSettings::palette()['--app-primary'] ?? null;

        $mode = ThemeSettings::isDark()
            ? __('client_settings.context.mode_dark')
            : __('client_settings.context.mode_light');

        return $primary ? $mode.' — '.$primary : $mode;
    }

    /**
     * هل معلومات المنشأة كافية لطباعة كشف حساب عميل؟
     *
     * الاسم والرقم الضريبي أدنى ما يتطلّبه مستندٌ يُسلَّم للعميل.
     */
    protected static function businessComplete(): bool
    {
        return BusinessInfo::name() !== '' && BusinessInfo::taxNumber() !== '';
    }

    /**
     * القراءة أو قيمة الاحتياط — الشريط لا يُسقط الشاشة أبداً.
     *
     * @template T
     *
     * @param  callable():T  $read
     * @param  T  $fallback
     * @return T
     */
    protected static function safe(callable $read, $fallback)
    {
        try {
            return $read();
        } catch (Throwable $e) {
            return $fallback;
        }
    }
}
