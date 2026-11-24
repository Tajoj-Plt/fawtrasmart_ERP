<?php

namespace App\Providers;

use App\Models\ClientRelation;
use App\Models\ColorSitting;
use App\Models\JournalEntry;
use App\Models\ProductDetails;
use App\Models\SystemLanguage;
use App\Models\Visit;
use App\Models\WarehousePermitsProducts;
use App\Observers\JournalEntryObserver;
use App\Observers\PlanLimitObserver;
use App\Observers\StockMovementGuardObserver;
use App\Services\MailerConfigurator;
use App\Support\LanguageSettings;
use App\Support\ThemeSettings;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
            Spatie يُسجّل `Gate::before()` خاصّاً به فور أوّل استحصالٍ لـ
            `Gate` من الحاوية (`callAfterResolving`) — وأول خطّافٍ `before`
            يُعيد قيمةً غير null يفوز فوراً، فلا فرصة لأي خطّافٍ يُسجَّل بعده.
            التسجيل هنا عبر `resolving()` لا `boot()` مباشرة: `resolving()`
            يُنفَّذ قبل `afterResolving()` لنفس الاستحصال (انظر
            `Container::fireResolvingCallbacks()`)، و`register()` تُنفَّذ
            لكل المزوّدين قبل أي `boot()` — فيسبق خطّافي خطّاف Spatie مهما
            كان ترتيب تسجيل المزوّدين.
        */
        $this->app->resolving(\Illuminate\Contracts\Auth\Access\Gate::class, function ($gate) {
            $gate->before(function ($user, string $ability) {
                if (! method_exists($user, 'hasRolePermission')) {
                    return null;
                }

                return $user->hasRolePermission($ability);
            });
        });
    }

    /**
     * جعل جدول system_languages هو مصدر لغات الواجهة.
     *
     * يعمل على الإعداد نفسه الذي يقرأه mcamara/laravel-localization، فيسري
     * على كل مبدّلات اللغة في النظام دون تعديل أي قالب:
     *
     * - اللغة المفعّلة في الجدول **وغير الموجودة** في ملف الإعداد → تُضاف
     *   ببياناتها من الجدول، فتظهر فور إضافتها من شاشة الإدارة.
     * - اللغة المفعّلة **والموجودة** في الملف → تبقى ببيانات الملف مع تفضيل
     *   الاسم الأصلي المخزَّن في الجدول إن وُجد.
     * - اللغة المعطّلة أو المحذوفة → تختفي من المبدّل (وبياناتها تبقى في الجدول).
     *
     * وحمايتان تمنعان كسر التوجيه:
     * - لا يُطبَّق إن كانت النتيجة فارغة أو خالية من لغة التطبيق الافتراضية
     * - أي خطأ (قاعدة بيانات غير مهيّأة، جدول مفقود) يتركه بلا أثر
     */
    protected function restrictSupportedLocalesToActiveLanguages(): void
    {
        try {
            $configured = config('laravellocalization.supportedLocales', []);

            $resolved = Cache::get('system_languages_supported_locales');

            if ($resolved === null) {
                $active = SystemLanguage::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['code', 'name', 'native_name', 'direction']);

                $resolved = [];

                foreach ($active as $language) {
                    $code = (string) $language->code;

                    if ($code === '') {
                        continue;
                    }

                    // بيانات الملف أدق (script/regional) فتُتَّخذ أساساً حين تتوفر
                    $entry = $configured[$code] ?? [
                        'name' => $language->name,
                        // الاتجاه يحدد النص التقريبي: لا يُخزَّن في الجدول
                        'script' => $language->direction === 'rtl' ? 'Arab' : 'Latn',
                        'native' => $language->native_name ?: $language->name,
                        'regional' => '',
                    ];

                    // الاسم الأصلي المُدار من الشاشة يتقدّم على ما في الملف
                    if (filled($language->native_name)) {
                        $entry['native'] = $language->native_name;
                    }
                    if (filled($language->name)) {
                        $entry['name'] = $language->name;
                    }

                    $resolved[$code] = $entry;
                }

                // النتيجة الفارغة لا تُخزَّن: جدول لم يُزرع بعد كان سيُجمّد الحالة ساعة كاملة
                if (! empty($resolved)) {
                    Cache::put('system_languages_supported_locales', $resolved, 3600);
                }
            }

            $fallback = config('app.locale');

            if (empty($resolved)) {
                return;
            }

            /* لغةُ الاحتياط تُضاف إن غابت، ولا يُترك التقييدُ كلُّه لأجلها.

               كان غيابُها يُلغي التقييد: فمن عطّل الإنجليزية — وهي لغةُ
               الاحتياط — عادت اللغاتُ المعرَّفةُ في الملفّ كلُّها إلى كل
               مبدِّل، ومنها ما عطّله للتوّ، بلا رسالة. */
            if (! array_key_exists($fallback, $resolved)) {
                $configured = config('laravellocalization.supportedLocales.'.$fallback);

                if (! is_array($configured)) {
                    return;
                }

                $resolved[$fallback] = $configured;
            }

            config(['laravellocalization.supportedLocales' => $resolved]);
        } catch (\Throwable $e) {
            // قبل تشغيل الهجرات أو عند تعذّر الاتصال: يبقى إعداد الملف كما هو
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
            قالب الترقيم المعتمد للنظام كلّه.

            المشروع لا يسجّل قالباً ولا يحمل `resources/views/vendor/pagination/`،
            فكل نداءٍ لـ`$paginator->links()` — وهي سبعةٌ وأربعون موضعاً
            موزّعة على الوحدات — كان يُصيّر قالب لارافيل الافتراضي المكتوب
            **بترميز Tailwind**، وأصنافُه غير موجودة في هذا المشروع. فيخرج
            شريطٌ عارٍ لا يشبه شيئاً في بقيّة الشاشات.

            السطران هنا يسريان على المواضع كلّها بلا تعديل سطرٍ فيها.
        */
        Paginator::defaultView('pagination.stk');
        Paginator::defaultSimpleView('pagination.stk-simple');

        // في boot لا في register: مزوّد laravel-localization يدمج إعداده دمجاً
        // عميقاً أثناء register فيعيد كل اللغات، وأي تصفية هناك تُمحى
        $this->restrictSupportedLocalesToActiveLanguages();

        // إذنٌ مخزني رُحِّل قيده يصير معتمَداً تلقائياً — تفصيل السبب في المراقب
        JournalEntry::observe(JournalEntryObserver::class);

        // الفاتورة الإلكترونية: كلّ مسارٍ يُنشئ مستنداً ضريبياً يبلغ الهيئة
        \App\Models\Invoice::observe(\App\Observers\ZatcaSubmissionObserver::class);
        \App\Models\CreditNotification::observe(\App\Observers\ZatcaSubmissionObserver::class);

        /*
            الخدمات والمنتجات الرقمية لا رصيد لها.

            الحارس على الموديل لا على الكنترولر: نقاط الكتابة في المخزون
            خمس عشرة موزَّعة على كنترولرات التصنيع والأذون والجرد
            والمنتجات، وحارسٌ في كلٍّ منها يُنسى في السادسة عشرة.
        */
        WarehousePermitsProducts::observe(StockMovementGuardObserver::class);
        ProductDetails::observe(StockMovementGuardObserver::class);

        /*
            حدود باقة فوترة — بالمنطق نفسه المشروح فوقه تماماً.

            وسيط `plan.limit` على المسار يحرس الباب الذي يمرّ منه المستخدم،
            لكن الصفوف تُنشأ من أبوابٍ أخرى: توليد الأرصدة الافتتاحية بالجملة
            يُنشئ إذناً لكل مستودع، واعتماد الجرد يُنشئ إذناً، والاستيراد
            يُنشئ منتجاتٍ بعدد أسطر الملفّ. وأوامر الطرفية والمهامّ في الطابور
            لا تمرّ بمسارٍ أصلاً.

            فالسؤال هنا على الصفّ نفسه — لا يهمّ من أنشأه ولا من أين.
            والكلفة صفر على ميزةٍ بلا حدّ: الفحص يخرج من اللقطة بلا استعلام.
        */
        foreach (PlanLimitObserver::models() as $model) {
            $model::observe(PlanLimitObserver::class);
        }

        // إعدادات البريد المحفوظة في شاشة الإعدادات هي التي يُرسل بها النظام.
        // كانت تُحفظ ولا يقرؤها أحد، فيبقى الإرسال على مُرسِل `.env` دائماً.
        // القيمة مخزَّنة مؤقتاً، فالتكلفة استعلام واحد كل عشر دقائق.
        app(MailerConfigurator::class)->apply();

        /*
            Custom Blade Directives للصلاحيات — عبر User::hasRolePermission()
            (دور المستخدم عبر role_id وصلاحياته من role_has_permissions)،
            لا Spatie::hasPermissionTo() (model_has_roles/model_has_permissions).
            كانت الواجهة تسأل Spatie بينما صار الخادم (middleware،
            DataScopeService) يسأل النظام الجديد حصراً — فيظهر زرٌّ لا يعمل.
        */
        \Blade::directive('permission', function ($permission) {
            return "<?php if(auth()->check() && auth()->user()->hasRolePermission({$permission})): ?>";
        });

        \Blade::directive('endpermission', function () {
            return '<?php endif; ?>';
        });

        \Blade::directive('haspermission', function ($permission) {
            return "<?php echo auth()->check() && auth()->user()->hasRolePermission({$permission}) ? 'true' : 'false'; ?>";
        });

        /*
            موجّه حدود الباقة — `@plan('stock_products') … @endplan`.

            **طبقةٌ ثانية لا بديلٌ عن `@permission`**: تلك تسأل «هل يملك هذا
            المستخدم الصلاحية؟»، وهذه تسأل «هل اشترت المنشأة الميزة أصلاً؟».
            فبند القائمة يُعرض حين يجتاز السؤالين معاً، وتُكتبان متداخلتين لا
            مختارتين.

            يقبل مفتاحاً واحداً أو مصفوفةً — والمصفوفة تعني «تكفي واحدة»، وهي
            صيغة البنود الجامعة التي تفتح على عدّة شاشات.

            وميزةٌ لا يعرفها كتالوج فوترة تُعرَض (انظر `SubscriptionGate::allows()`)
            — فالموجّه لا يُخفي ما لم يقصد أحدٌ إخفاءه.
        */
        \Blade::directive('plan', function ($features) {
            return "<?php if(\\App\\Services\\Fawtra\\SubscriptionGate::permits({$features})): ?>";
        });

        \Blade::directive('endplan', function () {
            return '<?php endif; ?>';
        });

        // إعدادات اللغة الحالية متاحة في كل القوالب: الاتجاه والتنسيقات والخط.
        // محلولة مرة واحدة لكل طلب داخل LanguageSettings فلا تُكلّف استعلاماً متكرراً.
        View::composer('*', function ($view) {
            LanguageSettings::apply();

            $view->with([
                'currentLanguage' => LanguageSettings::current(),
                'languageDirection' => LanguageSettings::direction(),
                'languageIsRtl' => LanguageSettings::isRtl(),
                'languageDateFormat' => LanguageSettings::dateFormat(),
                'languageTimeFormat' => LanguageSettings::timeFormat(),
                'languageCss' => LanguageSettings::cssVariables(),
            ]);
        });

        // مشاركة زيارات اليوم في جميع الـ views.
        //
        // محاطٌ بـ try لأن `View::composer('*')` يعني *كل* قالب — بما فيها
        // قوالب معالج التثبيت التي تُعرض قبل وجود قاعدة بيانات أصلاً. كان
        // الاستعلام مكشوفاً فيرمي QueryException عند أول عرض على تركيبٍ
        // جديد، أي أن الصفحة التي وُجدت لإنشاء القاعدة تعجز عن الظهور
        // لغيابها. وأي عطل اتصالٍ لاحق يُسقط النظام كلّه لا شاشة الزيارات.
        View::composer('*', function ($view) {
            try {
                $todayVisits = Visit::with(['employee', 'client'])
                    ->whereDate('visit_date', Carbon::today())
                    ->orderBy('visit_date', 'desc')
                    ->get();
            } catch (\Throwable $e) {
                $todayVisits = collect();
            }

            $view->with('todayVisits', $todayVisits);
        });

        // مشاركة مظهر النظام في جميع الـ views.
        // كان هنا `ColorSitting::first()` يُنفَّذ عند كل رندر لكل صفحة —
        // صار الحل مخزَّناً في الكاش ومحلولاً مرة واحدة لكل طلب.
        View::composer('*', function ($view) {
            $view->with([
                'backgroundColorr' => ThemeSettings::backgroundColor(),
                'systemTheme' => ThemeSettings::current(),
            ]);
        });
    }
}
