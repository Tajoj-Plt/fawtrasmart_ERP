<?php

namespace Modules\Installer\Http\Controllers;

use App\Models\ColorSitting;
use App\Models\SystemCurrency;
use App\Models\SystemLanguage;
use App\Services\Fawtra\FawtraCredentials;
use App\Services\Fawtra\SubscriptionSync;
use App\Support\OperationLog;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\SystemCurrenciesSeeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Installer\Services\DatabaseInstaller;
use Modules\Installer\Services\EnvironmentManager;
use Modules\Installer\Services\InstallationRunner;
use Modules\Installer\Services\InstallationState;
use Modules\Installer\Services\RequirementsChecker;
use Modules\Installer\Services\SettingsInstaller;
use Throwable;

/**
 * معالج التثبيت خطوةً خطوة.
 *
 * ما يجمعه المستخدم يُحفظ في الجلسة لا في قاعدة البيانات — للسبب البديهي
 * أن القاعدة لم تُنشأ بعد في نصف الخطوات. والكتابة الفعلية كلّها تقع في
 * خطوةٍ واحدة أخيرة (`run`)، فالتراجع قبلها لا يترك أثراً.
 *
 * ولا مصادقة على أيٍّ من هذه المسارات — يستحيل أن تكون قبل وجود جدول
 * المستخدمين. الحماية أن الوسيط RedirectIfInstalled يقفل الباب بمجرد تمام
 * التثبيت.
 */
class InstallerController extends Controller
{
    /** مفتاح تجميع مدخلات المعالج في الجلسة. */
    protected const SESSION_KEY = 'installer.payload';

    public function __construct(
        protected RequirementsChecker $requirements,
        protected EnvironmentManager $environment,
        protected DatabaseInstaller $database,
        protected SettingsInstaller $settings,
        protected InstallationRunner $runner,
    ) {
        // القاعدة المختارة تُطبَّق على كل طلبٍ من الجلسة.
        //
        // ما دامت بياناتها لا تُكتب في `.env` إلا في آخر التنفيذ (كي لا يُعاد
        // تشغيل خادم التطوير في منتصف المعالج)، فالإعداد يعيش في الذاكرة —
        // والذاكرة تموت مع الطلب. فيُعاد ضبطها هنا قبل أي عمل
        if ($database = $this->payload()['database'] ?? null) {
            $this->environment->applyRuntimeDatabase($database);
        }
    }

    /* ==================== 1. الترحيب ==================== */

    public function welcome()
    {
        return view('installer::steps.welcome', [
            'step' => 'welcome',
            'appName' => config('app.name'),
        ]);
    }

    /* ==================== 2. المتطلبات ==================== */

    public function requirements()
    {
        return view('installer::steps.requirements', [
            'step' => 'requirements',
            'groups' => $this->requirements->all(),
            'passes' => $this->requirements->passes(),
        ]);
    }

    /* ==================== 3. تفعيل الاشتراك ==================== */

    /**
     * ربط النظام بلوحة تحكّم فوترة.
     *
     * موضعها قبل قاعدة البيانات عمداً: الربط أوّل ما يُدخله صاحب النظام،
     * ولا يعتمد على قاعدة بيانات أصلاً — بياناته تُكتب في `.env`. ووضعُها
     * بعد الهجرات كان يعني أن يُنهي المستخدم تثبيتاً كاملاً ثم يكتشف أن
     * مفتاحه مرفوض.
     */
    public function activation()
    {
        return view('installer::steps.activation', [
            'step' => 'activation',
            'values' => $this->payload()['fawtra'] ?? app(FawtraCredentials::class)->public(),
            'suggestedDomain' => request()->getHost(),
        ]);
    }

    /**
     * فحص بيانات الاعتماد حيّاً قبل الحفظ — يُستدعى من زرّ «تحقّق».
     *
     * `/ping` أخفّ المسارات ولا يلمس الاشتراك، وهو المقصود: هل المفتاح
     * والتوقيع والدومين مقبولة عند فوترة؟
     */
    public function verifyActivation(Request $request)
    {
        $data = $request->validate(
            FawtraCredentials::rules(),
            FawtraCredentials::ruleMessages(),
            FawtraCredentials::attributes()
        );

        return response()->json(app(SubscriptionSync::class)->verify($data));
    }

    /**
     * حفظ بيانات الربط — **بعد التحقّق منها حيّاً، ولا تخطّي**.
     *
     * كان التخطّي مسموحاً بحجّة أن الربط ميزةٌ تُضاف. وهو ليس كذلك في هذا
     * النظام: الباقة تحدّد الشاشات المتاحة وحدود الاستخدام، فتركيبٌ بلا
     * اشتراك متحقَّق منه يعمل بلا حدود يعرفها أحد.
     */
    public function storeActivation(Request $request)
    {
        $data = $request->validate(
            FawtraCredentials::rules(),
            FawtraCredentials::ruleMessages(),
            FawtraCredentials::attributes()
        );

        // لا تُحفظ بيانات لم تُقبل: حفظُ مفتاحٍ مرفوض يعني تثبيتاً ينتهي
        // بنظامٍ يظنّ نفسه مربوطاً وهو ليس كذلك
        $check = app(SubscriptionSync::class)->verify($data);

        if (! $check['ok']) {
            return back()->withInput()->with('error', $check['message']);
        }

        $this->remember(['fawtra' => $data]);

        return redirect()->route('installer.database')
            ->with('success', $check['message']);
    }

    /* ==================== 4. قاعدة البيانات ==================== */

    public function database()
    {
        return view('installer::steps.database', [
            'step' => 'database',
            'config' => $this->payload()['database'] ?? $this->environment->currentDatabaseConfig(),
        ]);
    }

    /**
     * اختبار الاتصال دون حفظ — يُستدعى من زرّ «اختبار» عبر AJAX.
     */
    public function testDatabase(Request $request)
    {
        $data = $this->validateDatabase($request);

        return response()->json($this->environment->testConnection($data));
    }

    /**
     * حفظ إعدادات القاعدة: اختبار ثم إنشاء (إن طُلب) ثم كتابة `.env`.
     */
    public function storeDatabase(Request $request)
    {
        $data = $this->validateDatabase($request);

        $result = $this->environment->testConnection($data);

        // «القاعدة غير موجودة» ليست فشلاً نهائياً — يُعرض إنشاؤها ويُعاد الاختبار
        if (! $result['ok'] && ($result['can_create'] ?? false) && $request->boolean('create_database')) {
            $created = $this->environment->createDatabase($data);

            if (! $created['ok']) {
                return back()->withInput()->with('error', $created['message']);
            }

            $result = $this->environment->testConnection($data);
        }

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        // **لا كتابة في `.env` هنا.** كانت هذه الخطوة تكتب بيانات القاعدة
        // فوراً، فيلتقط `php artisan serve` تغيّر الملف ويعيد تشغيل الخادم في
        // منتصف الطلب: تسقط الاستجابة، ويرى المستخدم صفحةً لا تُحمَّل ويظنّ
        // المعالج معلَّقاً. الإعداد يكفي في الذاكرة حتى نهاية التنفيذ
        $this->environment->applyRuntimeDatabase($data);

        $this->ensureAppKey();

        $this->remember(['database' => $data]);

        return redirect()->route('installer.settings');
    }

    /* ==================== 4. الإعدادات الأساسية ==================== */

    public function settings()
    {
        return view('installer::steps.settings', [
            'step' => 'settings',
            'values' => $this->payload()['settings'] ?? [],
            'company' => $this->payload()['company'] ?? [],
            'timezones' => $this->timezones(),
            'themes' => ColorSitting::PRESETS,
            'currencies' => $this->currencyOptions(),
            'languages' => $this->languageOptions(),
        ]);
    }

    public function storeSettings(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:190'],
            'company_email' => ['nullable', 'email', 'max:190'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_city' => ['nullable', 'string', 'max:120'],
            'company_country' => ['nullable', 'string', 'max:120'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'commercial_registration' => ['nullable', 'string', 'max:60'],
            'website' => ['nullable', 'string', 'max:190'],

            // قوائم بيضاء لا أطوالٌ فقط: هذه الثلاث تُكتب في `.env` وفي قاعدة
            // البيانات، وقيمةٌ خارج المتاح تُنتج نظاماً بمنطقة زمنية لا وجود
            // لها أو عملةٍ رمزُها وسمٌ برمجي. والقائمة مشتقّة من نفس مصدر
            // خيارات النموذج، فما لا يُعرض لا يُقبل
            'language' => ['required', 'string', Rule::in(array_keys($this->languageOptions()))],
            'currency' => ['required', 'string', Rule::in(array_keys($this->currencyOptions()))],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'business_type' => ['required', 'in:products,services,both'],
            'date_format' => ['required', 'string', Rule::in(['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'])],
            'time_format' => ['required', 'string', Rule::in(['H:i', 'h:i A'])],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_type' => ['required', 'in:included,excluded'],
            'theme' => ['required', 'string', Rule::in(array_keys(ColorSitting::PRESETS))],
        ], [], [
            'company_name' => 'اسم المنشأة',
            'language' => 'اللغة',
            'currency' => 'العملة',
            'timezone' => 'المنطقة الزمنية',
            'tax_percent' => 'نسبة الضريبة',
        ]);

        $this->remember([
            'company' => [
                'name' => $validated['company_name'],
                'email' => $validated['company_email'] ?? null,
                'phone' => $validated['company_phone'] ?? null,
                'city' => $validated['company_city'] ?? null,
                'country' => $validated['company_country'] ?? null,
                'tax_number' => $validated['tax_number'] ?? null,
                'commercial_registration' => $validated['commercial_registration'] ?? null,
                'website' => $validated['website'] ?? null,
            ],
            'settings' => [
                'language' => $validated['language'],
                'currency' => $validated['currency'],
                'timezone' => $validated['timezone'],
                'business_type' => $validated['business_type'],
                'date_format' => $validated['date_format'],
                'time_format' => $validated['time_format'],
                'tax_percent' => $validated['tax_percent'],
                'tax_type' => $validated['tax_type'],
                'theme' => $validated['theme'],
            ],
        ]);

        return redirect()->route('installer.admin');
    }

    /* ==================== 5. حساب المدير ==================== */

    public function admin()
    {
        return view('installer::steps.admin', [
            'step' => 'admin',
            'values' => $this->payload()['admin'] ?? [],
            'mail' => $this->payload()['mail'] ?? [],
            'hasData' => $this->safeHasData(),
        ]);
    }

    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            'mail_host' => ['nullable', 'string', 'max:190'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:190'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
            'mail_from' => ['nullable', 'email', 'max:190'],
            'mail_sender_name' => ['nullable', 'string', 'max:120'],
        ], [], [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
        ]);

        $this->remember([
            'admin' => [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
            ],
            'mail' => array_filter([
                'host' => $validated['mail_host'] ?? null,
                'port' => $validated['mail_port'] ?? null,
                'username' => $validated['mail_username'] ?? null,
                'password' => $validated['mail_password'] ?? null,
                'encryption' => $validated['mail_encryption'] ?? null,
                'from' => $validated['mail_from'] ?? null,
                'sender_name' => $validated['mail_sender_name'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'options' => [
                'demo_data' => $request->boolean('demo_data'),
                'fresh' => $request->boolean('fresh'),
            ],
        ]);

        return redirect()->route('installer.review');
    }

    /* ==================== 6. المراجعة والتنفيذ ==================== */

    public function review()
    {
        $payload = $this->payload();

        // الوصول المباشر إلى المراجعة دون المرور بالخطوات يعطي شاشة فارغة
        // مضلّلة — الأوضح إعادته إلى حيث توقّف
        if (empty($payload['admin']) || empty($payload['settings'])) {
            return redirect()->route('installer.welcome')
                ->with('error', 'أكمل خطوات المعالج بالترتيب قبل التنفيذ.');
        }

        // ولا تركيب بلا تفعيل متحقَّق منه: منعُ التخطّي في شاشة التفعيل وحده
        // يترك بابَ الدخول المباشر على `/install/database` مفتوحاً، فينتهي
        // التركيب بنظامٍ لا يعرف باقته ولا حدوده
        if (empty($payload['fawtra'])) {
            return redirect()->route('installer.activation')
                ->with('error', 'فعّل النظام بلوحة فوترة قبل التنفيذ.');
        }

        return view('installer::steps.review', [
            'step' => 'review',
            'payload' => $payload,
            'hasData' => $this->safeHasData(),
            'coreCount' => count(DatabaseInstaller::CORE_SEEDERS),
        ]);
    }

    /* ==================== 6. التنفيذ الحيّ ==================== */

    /**
     * شاشة التنفيذ — لا تُنفّذ شيئاً بنفسها.
     *
     * كان التنفيذ كلّه في طلبٍ واحد: أكثر من 470 هجرة وأربعين بذرة في نداء
     * HTTP واحد. وهو يتجاوز مهلة PHP والوسيط والمتصفّح، فينقطع في منتصفه
     * تاركاً قاعدةً نصف مبنية وشاشةً لا تقول أين توقّف.
     *
     * صار النداء يعرض الصفحة فقط، والمتصفّح ينادي `run/step` مهمةً مهمة —
     * فلا يتجاوز أي طلبٍ ثوانيَ معدودة، ويرى المستخدم كل خطوة لحظة وقوعها.
     */
    public function run(Request $request)
    {
        $payload = $this->payload();

        if (empty($payload['admin'])) {
            return redirect()->route('installer.welcome')
                ->with('error', 'انتهت الجلسة قبل التنفيذ. أعد إدخال البيانات.');
        }

        if (empty($payload['fawtra'])) {
            return redirect()->route('installer.activation')
                ->with('error', 'فعّل النظام بلوحة فوترة قبل التنفيذ.');
        }

        return view('installer::steps.run', [
            'step' => 'review',
            'resumable' => $this->runner->hasResumableRun(),
        ]);
    }

    /**
     * بناء خطة التنفيذ وإرجاع عدد مهامها.
     */
    public function runStart(Request $request)
    {
        $payload = $this->payload();

        if (empty($payload['admin'])) {
            return response()->json([
                'ok' => false,
                'message' => 'انتهت جلسة المعالج. أعد إدخال البيانات من البداية.',
            ], 422);
        }

        // الحارس الأخير قبل التنفيذ: الشاشات تُتخطّى بطلبٍ مباشر، وهذا
        // المسار هو ما يبني الخطة فعلاً
        if (empty($payload['fawtra'])) {
            return response()->json([
                'ok' => false,
                'message' => 'لا يبدأ التركيب قبل تفعيل النظام بلوحة فوترة.',
            ], 422);
        }

        // **الاستئناف يكمل ولا يعيد البناء.** كان الضغط على «استئناف» يستدعي
        // هذا المسار فيبني خطةً جديدة بمؤشّرٍ صفر — أي يعيد التثبيت من أوّله
        // بدل أن يكمل من المهمة المتعثّرة، وهو عكس ما يقوله الزرّ
        if ($this->runner->hasResumableRun()) {
            $state = $this->runner->load();

            return response()->json([
                'ok' => true,
                'resumed' => true,
                'total' => count($state['tasks']),
                'migrations' => count(array_filter($state['tasks'], fn ($t) => $t['type'] === 'migration')),
                'seeders' => count(array_filter($state['tasks'], fn ($t) => $t['type'] === 'seeder')),
                'index' => (int) $state['cursor'],
            ]);
        }

        // عنوان التطبيق يُلتقط هنا لا في المهمة: مهامّ `.env` تعمل خارج سياق
        // الطلب الذي يعرف المضيف
        $payload['app_url'] = $request->getSchemeAndHttpHost();

        $state = $this->runner->plan($payload);

        $migrations = count(array_filter($state['tasks'], fn ($t) => $t['type'] === 'migration'));
        $seeders = count(array_filter($state['tasks'], fn ($t) => $t['type'] === 'seeder'));

        return response()->json([
            'ok' => true,
            'total' => count($state['tasks']),
            'migrations' => $migrations,
            'seeders' => $seeders,
        ]);
    }

    /**
     * تنفيذ مهمة واحدة — نبضة واحدة من المتصفّح.
     */
    public function runStep()
    {
        $result = $this->runner->step();

        // ختمُ الجلسة عند آخر مهمة: به وحده تُفتح شاشة الإتمام بعد أن يقفل
        // الوسيطُ المعالجَ — فيرى صاحب التثبيت نتيجته ورابط دخوله
        if (($result['done'] ?? false) === true && ($result['ok'] ?? false) === true) {
            session(['installer.completed' => true]);
        }

        return response()->json($result);
    }

    public function finished()
    {
        return view('installer::steps.finished', [
            'step' => 'finished',
            'log' => session('installer.log', []),
            'meta' => InstallationState::meta(),
        ]);
    }

    /* ==================== أدوات داخلية ==================== */

    protected function validateDatabase(Request $request): array
    {
        return $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'string', 'max:6'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
        ], [
            'database.regex' => 'اسم قاعدة البيانات يقبل الحرف والرقم والشرطة السفلية فقط.',
        ], [
            'host' => 'الخادم',
            'port' => 'المنفذ',
            'database' => 'اسم قاعدة البيانات',
            'username' => 'اسم المستخدم',
        ]);
    }

    /** مدخلات المعالج المجمّعة حتى الآن. */
    protected function payload(): array
    {
        return session(self::SESSION_KEY, []);
    }

    /** دمج مدخلات خطوة على المجمّع — الدمج سطحي عمداً ليُستبدل القسم كاملاً. */
    protected function remember(array $data): void
    {
        session([self::SESSION_KEY => array_merge($this->payload(), $data)]);
    }

    /**
     * توليد APP_KEY إن كان غائباً.
     *
     * بدونه تفشل كل عملية تشفير — بما فيها كوكي الجلسة، فلا يستطيع
     * المستخدم إكمال المعالج نفسه.
     */
    protected function ensureAppKey(): void
    {
        if (! empty(config('app.key'))) {
            return;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));

        $this->environment->write(['APP_KEY' => $key]);

        config(['app.key' => $key]);
    }

    /** فحص وجود بيانات — لا يجوز أن يرمي حين لا تكون هناك قاعدة أصلاً. */
    protected function safeHasData(): bool
    {
        try {
            return $this->database->hasData();
        } catch (Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Installer.Installer', 'safeHasData');

            return false;
        }
    }

    /** المناطق الزمنية الشائعة في نطاق النظام، وباقي مناطق العالم بعدها. */
    protected function timezones(): array
    {
        $preferred = [
            'Asia/Riyadh' => 'الرياض (GMT+3)',
            'Asia/Aden' => 'عدن (GMT+3)',
            'Asia/Kuwait' => 'الكويت (GMT+3)',
            'Asia/Qatar' => 'الدوحة (GMT+3)',
            'Asia/Bahrain' => 'المنامة (GMT+3)',
            'Asia/Dubai' => 'دبي (GMT+4)',
            'Asia/Muscat' => 'مسقط (GMT+4)',
            'Asia/Baghdad' => 'بغداد (GMT+3)',
            'Asia/Amman' => 'عمّان (GMT+3)',
            'Africa/Cairo' => 'القاهرة (GMT+2)',
            'Africa/Khartoum' => 'الخرطوم (GMT+2)',
            'UTC' => 'التوقيت العالمي (UTC)',
        ];

        foreach (timezone_identifiers_list() as $tz) {
            if (! isset($preferred[$tz])) {
                $preferred[$tz] = $tz;
            }
        }

        return $preferred;
    }

    /**
     * العملات: تُقرأ من الجدول إن كان مزروعاً، وإلا قائمةٌ ثابتة.
     *
     * خطوة الإعدادات تسبق الهجرات، فالجدول غالباً غير موجود بعد — والقائمة
     * الثابتة هي ما يجعل الخطوة قابلة للعمل أصلاً.
     */
    /**
     * العملات المعروضة — من قاعدة البيانات إن كانت مزروعة، وإلا من السيدر نفسه.
     *
     * **المصدر هو `SystemCurrenciesSeeder::currencies()` لا قائمةٌ مكتوبة هنا.**
     * كانت هنا إحدى عشرة عملة مكتوبة يدوياً بينما يزرع السيدر عشرين — أي أن
     * تسع عملات لا تظهر وقت التثبيت رغم وجودها في النظام بعده. وبعد إضافة
     * التحقّق بقائمة بيضاء صار الأمر أسوأ: ما لا يُعرض **يُرفض**.
     *
     * وخطوة الإعدادات تسبق الهجرات، فالجدول غالباً غير موجود بعد — ولهذا
     * تُقرأ القائمة من تعريف السيدر لا من الجدول.
     */
    protected function currencyOptions(): array
    {
        try {
            $rows = SystemCurrency::orderBy('code')->pluck('name', 'code')->toArray();

            if ($rows !== []) {
                return $rows;
            }
        } catch (Throwable $e) {
            // الجدول غير موجود بعد — يُقرأ التعريف أدناه
        }

        $options = [];

        foreach (SystemCurrenciesSeeder::currencies() as $currency) {
            $options[$currency['code']] = $currency['name'].' ('.$currency['code'].')';
        }

        return $options;
    }

    /**
     * اللغات المعروضة — من قاعدة البيانات إن كانت مزروعة، وإلا من
     * `LanguageSeeder::languages()`. العلّة نفسها الموصوفة في `currencyOptions`.
     */
    protected function languageOptions(): array
    {
        try {
            $rows = SystemLanguage::orderBy('sort_order')->pluck('name', 'code')->toArray();

            if ($rows !== []) {
                return $rows;
            }
        } catch (Throwable $e) {
            // الجدول غير موجود بعد — يُقرأ التعريف أدناه
        }

        $options = [];

        foreach (LanguageSeeder::languages() as $language) {
            $native = $language['native_name'] ?? $language['name'];

            $options[$language['code']] = $language['name'] === $native
                ? $language['name']
                : $language['name'].' — '.$native;
        }

        return $options;
    }
}
