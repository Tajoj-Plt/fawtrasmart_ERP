<?php

namespace Modules\Sales\Support;

use App\Models\NotificationSetting;
use App\Models\PaymentMethod;
use App\Support\AccountingPeriodGuard;
use App\Support\AccountingSettings;
use App\Support\BusinessInfo;
use App\Support\CurrencySettings;
use App\Support\LanguageSettings;
use App\Support\ThemeSettings;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * إعدادات النظام التي تعمل بها شاشات إعدادات المبيعات.
 *
 * **لماذا هذا الصنف؟**
 *
 * شاشات إعدادات المبيعات تقرأ من إعدادات النظام في ستّة مواضع: العملة
 * تُنسّق كل مبلغ، واللغة تضبط الاتجاه وتنسيق التاريخ، والمظهر يحقن
 * `--app-*` التي بُني عليها نظام `sl-*` كلّه، والحسابات المحاسبية تستقبل
 * قيود الفاتورة، والفترة المحاسبية قد تردّ الحفظ، وطرق الدفع هي ما يظهر
 * في سند القبض. كلّ ذلك كان يجري في الخلفية بلا ذِكر في الشاشة، فيرى
 * المستخدم مبلغاً بلا رمز أو حفظاً مرفوضاً ولا يعرف أين يُصلحه.
 *
 * هنا تُجمع القراءات الستّ في نداء واحد يُعرَض شريطاً في رأس الشاشة، كل
 * بندٍ فيه رابطٌ إلى الشاشة التي تضبطه.
 *
 * **لماذا كلّ قراءة داخل `try`؟** الشريط سياقٌ لا وظيفة: وحدة الإعدادات
 * قد تكون معطَّلة في `modules_statuses.json`، أو تكون الهجرة لم تُشغَّل
 * بعد، فيسقط `PaymentMethod::count()` باستثناء. شريطٌ ناقص أهون من
 * شاشة إعدادات لا تُفتح.
 *
 * القيم مُذكَّرة (memoized) للطلب الواحد: الشريط قد يُستدعى من القالب
 * ومن المتحكّم معاً، ولا داعي لضرب القاعدة مرّتين.
 *
 * @see \Modules\Sales\Http\Controllers\SittingInvoiceController
 * @see resources/views/components/system-context.blade.php
 */
class SalesSettingsContext
{
    /** @var array<string, mixed>|null */
    protected static ?array $cache = null;

    /**
     * البنود المعروضة في الشريط، بعد إسقاط ما لا مسار له.
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
                'label' => __('sales_settings.context.appearance'),
                'value' => $s['theme'],
                'tone' => 'neutral',
                'route' => 'Appearance.index',
            ],
            [
                'key' => 'language',
                'icon' => 'globe',
                'label' => __('sales_settings.context.language'),
                'value' => $s['language'],
                'tone' => 'neutral',
                'route' => 'SittingAccount.language',
            ],
            [
                'key' => 'currency',
                'icon' => 'dollar-sign',
                'label' => __('sales_settings.context.currency'),
                'value' => $s['currency'],
                'tone' => 'neutral',
                'route' => 'SittingAccount.currency',
            ],
            [
                'key' => 'payments',
                'icon' => 'credit-card',
                'label' => __('sales_settings.context.payments'),
                'value' => $s['payments'] > 0
                    ? __('sales_settings.context.payments_value', ['count' => CurrencySettings::number($s['payments'], 0)])
                    : __('sales_settings.context.payments_none'),
                'tone' => $s['payments'] > 0 ? 'on' : 'off',
                'route' => 'PaymentMethods.index',
            ],
            [
                'key' => 'accounts',
                'icon' => 'book-open',
                'label' => __('sales_settings.context.accounts'),
                'value' => $s['accounts'] > 0
                    ? __('sales_settings.context.accounts_value', ['count' => CurrencySettings::number($s['accounts'], 0)])
                    : __('sales_settings.context.accounts_none'),
                'tone' => $s['accounts'] > 0 ? 'on' : 'off',
                'route' => 'AccountingSettings.index',
            ],
            [
                'key' => 'period',
                'icon' => 'lock',
                'label' => __('sales_settings.context.period'),
                'value' => $s['period_open']
                    ? __('sales_settings.context.period_open')
                    : __('sales_settings.context.period_blocked'),
                'tone' => $s['period_open'] ? 'on' : 'off',
                'route' => 'AccountingPeriods.index',
            ],
            [
                'key' => 'business',
                'icon' => 'briefcase',
                'label' => __('sales_settings.context.business'),
                'value' => $s['business_complete']
                    ? __('sales_settings.context.business_ok')
                    : __('sales_settings.context.business_missing'),
                'tone' => $s['business_complete'] ? 'on' : 'off',
                'route' => 'SittingAccount.index',
            ],
            [
                'key' => 'notifications',
                'icon' => 'bell',
                'label' => __('sales_settings.context.notifications'),
                // فئة «المبيعات» وحدها: عددُ إشعارات النظام كلّه لا يقول
                // لصاحب هذه الشاشة شيئاً عن قسمه
                'value' => $s['notifications_total'] > 0
                    ? __('sales_settings.context.notifications_value', [
                        'enabled' => CurrencySettings::number($s['notifications'], 0),
                        'total' => CurrencySettings::number($s['notifications_total'], 0),
                    ])
                    : __('sales_settings.context.payments_none'),
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

            // اليوم لا تاريخ الفاتورة: الشريط يقول «هل يُقبَل الحفظ الآن؟»،
            // والفحص الفعلي لتاريخ المستند يبقى في المتحكّم عند الحفظ
            'period_open' => static::safe(fn () => AccountingPeriodGuard::allows(now()), true),

            'business_complete' => static::safe(fn () => static::businessComplete(), true),
            'notifications' => static::safe(
                fn () => NotificationSetting::query()
                    ->where('category', 'sales')->where('is_enabled', true)->count(),
                0
            ),
            'notifications_total' => static::safe(
                fn () => NotificationSetting::query()->where('category', 'sales')->count(),
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
     * `ThemeSettings` هي مصدر `--app-*` التي يقوم عليها نظام `sl-*`، فذِكرها
     * في الشريط يجيب عن «لماذا صارت الشاشة بهذا اللون؟».
     */
    protected static function themeLabel(): string
    {
        // مفاتيح اللوحة هي أسماء متغيّرات CSS نفسها — `--app-primary` هي
        // اللون الذي يقرأه نظام `sl-*` في كل زرّ وبطاقة على هذه الشاشة
        $primary = ThemeSettings::palette()['--app-primary'] ?? null;

        // مفتاحان كاملان لا وصلٌ بمفتاح: المفتاح المبنيّ بالوصل لا يجده
        // فاحص الترجمة ولا `grep`، فيُحذف من ملفّ اللغة ولا يُلاحَظ
        $mode = ThemeSettings::isDark()
            ? __('sales_settings.context.mode_dark')
            : __('sales_settings.context.mode_light');

        return $primary ? $mode.' — '.$primary : $mode;
    }

    /**
     * هل معلومات المنشأة كافية لطباعة مستند مبيعات؟
     *
     * الاسم والرقم الضريبي أدنى ما تتطلّبه فاتورة ضريبية، فغيابهما ليس
     * نقصاً في التزيين بل مستنداً لا يصحّ.
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
