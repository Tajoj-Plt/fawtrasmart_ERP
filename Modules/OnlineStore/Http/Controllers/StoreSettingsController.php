<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Models\Account;
use App\Models\Store\OnlineStoreSetting;
use App\Models\StoreHouse;
use App\Support\OperationLog;
use Database\Seeders\OnlineStoreSettingsSeeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Controller لإدارة إعدادات المتجر الإلكتروني
 */
class StoreSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission.role:online_store.settings.view', ['only' => ['index', 'group', 'searchAccounts']]);
        $this->middleware('permission.role:online_store.settings.manage', ['only' => ['update', 'updateSingle', 'deleteFile', 'testEmail']]);
        $this->middleware('permission.role:online_store.settings.reset', ['only' => ['resetToDefault']]);
        $this->middleware('permission.role:online_store.settings.import_export', ['only' => ['export', 'import']]);
        $this->middleware('permission.role:online_store.settings.clear_cache', ['only' => ['clearCache']]);
    }

    /**
     * الحقول المنطقية لكل مجموعة — تُستعمل لتحديد قسم الإعداد عند إنشائه من
     * مفاتيح التبديل السريع فقط (`detectGroupFromKey`). ما يُطفأ عند حفظ قسمٍ
     * تحدّده `FIELDS` (ما يعرضه النموذج فعلاً) لا هذه القائمة.
     */
    protected $booleanFields = [
        'general' => [],
        'appearance' => [
            'enable_wishlist',
            'enable_compare',
            'enable_reviews',
            'enable_questions',
        ],
        'filters' => [
            'show_category_stock',
            'show_sub_categories',
            'show_category_description',
        ],
        'checkout' => [
            'enable_guest_checkout',
            'require_phone_verification',
            'require_email_verification',
            'allow_negative_stock',
        ],
        'accounting' => [],
        'shipping' => [],
        'seo' => [],
        'notifications' => [
            'enable_email_notifications',
            'enable_sms_notifications',
            'enable_push_notifications',
        ],
        'advanced' => [
            'maintenance_mode',
            'enable_multi_language',
            'enable_cache',
            'auto_approve_reviews',
        ],
        'social' => [],
    ];

    /**
     * مجموعات الإعدادات
     */
    protected $groups = [
        'general' => [
            'title' => 'الإعدادات العامة',
            'icon' => 'settings',
            'description' => 'إعدادات المتجر الأساسية مثل الاسم والعنوان ومعلومات الاتصال',
        ],
        'appearance' => [
            'title' => 'المظهر والعرض',
            'icon' => 'layout',
            'description' => 'إعدادات الألوان والتصميم وعرض المنتجات',
        ],
        'filters' => [
            'title' => 'الفلاتر والعرض',
            'icon' => 'filter',
            'description' => 'إعدادات فلاتر البحث وعرض الأصناف والمنتجات',
        ],
        'checkout' => [
            'title' => 'إعدادات الدفع',
            'icon' => 'shopping-cart',
            'description' => 'إعدادات سلة التسوق والحدود والضرائب',
        ],
        'accounting' => [
            'title' => 'الحسابات المحاسبية',
            'icon' => 'book',
            'description' => 'الحسابات التي تُرحَّل إليها قيود المتجر — المبيعات والمرتجعات والضريبة والمخزون والشحن، تُختار من شجرة الحسابات',
        ],
        'shipping' => [
            'title' => 'إعدادات الشحن',
            'icon' => 'truck',
            'description' => 'إعدادات تكاليف الشحن والتوصيل',
        ],
        'seo' => [
            'title' => 'إعدادات SEO',
            'icon' => 'search',
            'description' => 'إعدادات تحسين محركات البحث',
        ],
        'notifications' => [
            'title' => 'الإشعارات',
            'icon' => 'bell',
            'description' => 'إعدادات إشعارات البريد والرسائل',
        ],
        'advanced' => [
            'title' => 'إعدادات متقدمة',
            'icon' => 'sliders',
            'description' => 'إعدادات متقدمة للمطورين',
        ],
        'social' => [
            'title' => 'التواصل الاجتماعي',
            'icon' => 'share-2',
            'description' => 'روابط حسابات التواصل الاجتماعي',
        ],
    ];

    /**
     * عرض صفحة الإعدادات الرئيسية
     */
    public function index()
    {
        $groups = $this->groups;
        $settings = OnlineStoreSetting::all();
        $settingsByGroup = $settings->groupBy('group');

        return view('onlinestore::settings.index', compact('groups', 'settings', 'settingsByGroup'));
    }

    /**
     * عرض إعدادات مجموعة معينة
     */
    public function group($group)
    {
        if (! array_key_exists($group, $this->groups)) {
            abort(404);
        }

        $groups = $this->groups;
        $currentGroup = $this->groups[$group];

        // جلب جميع الإعدادات لأن بعض الإعدادات قد تكون في groups مختلفة
        $settings = OnlineStoreSetting::all();

        return view('onlinestore::settings.group', compact('groups', 'group', 'currentGroup', 'settings'));
    }

    /**
     * حقول كل قسمٍ كما يعرضها نموذجُه فعلاً — مع نوعها وقاعدة التحقق.
     *
     * هي مصدر الحقيقة لـ`update()`: ما ليس هنا (ولا هو إعدادٌ قائم في القسم
     * نفسه) لا يُكتب، وما لم يُرسَل لا يُمسّ. والحقل المنطقي يُطفأ بغيابه
     * **فقط** إن كان مربّعُه معروضاً في هذا القسم — كانت القائمة القديمة
     * (`$booleanFields`) تضم حقولاً لا يعرضها النموذج، فكان حفظُ «المظهر»
     * يُطفئ «البيع بالسالب» و«المقارنة»، وحفظُ «الفلاتر» يُطفئ ثلاثة خيارات
     * عرضٍ لا وجود لها في الشاشة.
     *
     * النوع: bool | int | decimal | json | file | string.
     */
    private const FIELDS = [
        'general' => [
            'store_name' => ['string', 'required|string|max:150', 'اسم المتجر'],
            'store_name_en' => ['string', 'nullable|string|max:150', 'اسم المتجر بالإنجليزية'],
            'store_description' => ['string', 'nullable|string|max:2000', 'وصف المتجر'],
            'store_logo' => ['file', 'nullable|file|mimes:jpeg,jpg,png,gif,webp,svg|max:2048', 'شعار المتجر'],
            'store_favicon' => ['file', 'nullable|file|mimes:jpeg,jpg,png,gif,webp,svg,ico|max:1024', 'أيقونة المتجر'],
            'store_email' => ['string', 'nullable|email|max:150', 'بريد المتجر'],
            'store_phone' => ['string', 'nullable|string|max:30', 'هاتف المتجر'],
            'store_address' => ['string', 'nullable|string|max:500', 'عنوان المتجر'],
            'store_timezone' => ['string', 'nullable|timezone', 'المنطقة الزمنية'],
            'google_maps_api_key' => ['string', 'nullable|string|max:255', 'مفتاح خرائط Google'],
        ],
        'appearance' => [
            'products_per_page' => ['int', 'nullable|integer|min:1|max:200', 'عدد المنتجات في الصفحة'],
            'enable_wishlist' => ['bool', 'nullable', 'قائمة الأمنيات'],
            'enable_reviews' => ['bool', 'nullable', 'التقييمات'],
            'enable_questions' => ['bool', 'nullable', 'أسئلة المنتجات'],
        ],
        'filters' => [
            'filter_max_categories' => ['int', 'nullable|integer|min:0|max:500', 'أقصى عدد للأصناف'],
            'filter_max_brands' => ['int', 'nullable|integer|min:0|max:500', 'أقصى عدد للعلامات'],
            'max_featured_products' => ['int', 'nullable|integer|min:0|max:500', 'عدد المنتجات المميزة'],
            'max_new_arrivals' => ['int', 'nullable|integer|min:0|max:500', 'عدد المنتجات الجديدة'],
            'max_top_rated_products' => ['int', 'nullable|integer|min:0|max:500', 'عدد الأعلى تقييماً'],
            'max_best_selling_products' => ['int', 'nullable|integer|min:0|max:500', 'عدد الأكثر مبيعاً'],
            'max_recently_viewed' => ['int', 'nullable|integer|min:0|max:500', 'عدد المشاهَد مؤخراً'],
            'price_ranges' => ['json', 'nullable|json', 'نطاقات الأسعار'],
        ],
        'checkout' => [
            'min_order_amount' => ['decimal', 'nullable|numeric|min:0|max:99999999', 'الحد الأدنى للطلب'],
            'max_order_amount' => ['decimal', 'nullable|numeric|min:0|max:99999999', 'الحد الأقصى للطلب'],
            'tax_rate' => ['decimal', 'nullable|numeric|min:0|max:100', 'نسبة الضريبة'],
            'cart_session_lifetime' => ['int', 'nullable|integer|min:1|max:525600', 'مدة صلاحية السلة'],
            'enable_guest_checkout' => ['bool', 'nullable', 'الشراء بلا تسجيل'],
            'require_phone_verification' => ['bool', 'nullable', 'التحقق من الجوال'],
            'require_email_verification' => ['bool', 'nullable', 'التحقق من البريد'],
            'default_payment_method_id' => ['int', 'nullable|integer|exists:payment_methods,id', 'طريقة الدفع الافتراضية'],
            'enabled_payment_methods' => ['json', 'nullable|array', 'طرق الدفع المتاحة'],
            'default_storehouse_id' => ['int', 'nullable|integer', 'المخزون الافتراضي للمتجر'],
            'allow_negative_stock' => ['bool', 'nullable', 'البيع بالسالب'],
            'default_treasury_id' => ['int', 'nullable|integer', 'الخزينة / البنك الافتراضي'],
        ],
        'accounting' => [
            'sales_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب المبيعات'],
            'sales_returns_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب مرتجعات المبيعات'],
            'allowed_discount_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب الخصم المسموح به'],
            'cost_of_sales_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب تكلفة المبيعات'],
            'purchases_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب المشتريات'],
            'cash_client_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب العميل النقدي'],
            'vat_payable_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب ضريبة المخرجات'],
            'vat_paid_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب ضريبة المدخلات'],
            'shipping_revenue_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب إيراد الشحن'],
            'shipping_revenue_returns_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب مرتجع إيراد الشحن'],
            'shipping_vat_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب ضريبة الشحن'],
            'free_shipping_expense_account_id' => ['int', 'nullable|integer|exists:accounts,id', 'حساب مصروف الشحن المجاني'],
        ],
        'shipping' => [
            'packaging_fee' => ['decimal', 'nullable|numeric|min:0|max:99999999', 'رسوم التغليف'],
            'shipping_revenue_percentage' => ['decimal', 'nullable|numeric|min:0|max:100', 'نسبة إيراد الشحن'],
        ],
        'seo' => [
            'meta_title' => ['string', 'nullable|string|max:255', 'عنوان SEO'],
            'meta_description' => ['string', 'nullable|string|max:500', 'وصف SEO'],
            'meta_keywords' => ['string', 'nullable|string|max:500', 'الكلمات المفتاحية'],
            'google_analytics_id' => ['string', 'nullable|string|max:50', 'معرّف Google Analytics'],
            'facebook_pixel_id' => ['string', 'nullable|string|max:50', 'معرّف Facebook Pixel'],
        ],
        'notifications' => [
            'enable_email_notifications' => ['bool', 'nullable', 'إشعارات البريد'],
            'enable_sms_notifications' => ['bool', 'nullable', 'إشعارات الرسائل'],
            'enable_push_notifications' => ['bool', 'nullable', 'الإشعارات الفورية'],
        ],
        'advanced' => [
            'maintenance_mode' => ['bool', 'nullable', 'وضع الصيانة'],
            'enable_cache' => ['bool', 'nullable', 'التخزين المؤقت'],
            'auto_approve_reviews' => ['bool', 'nullable', 'اعتماد التقييمات تلقائياً'],
        ],
        'social' => [
            'social_facebook' => ['string', 'nullable|url|max:255', 'فيسبوك'],
            'social_twitter' => ['string', 'nullable|url|max:255', 'تويتر'],
            'social_instagram' => ['string', 'nullable|url|max:255', 'إنستغرام'],
            'social_whatsapp' => ['string', 'nullable|string|max:30', 'واتساب'],
            'social_youtube' => ['string', 'nullable|url|max:255', 'يوتيوب'],
            'social_tiktok' => ['string', 'nullable|url|max:255', 'تيك توك'],
            'social_snapchat' => ['string', 'nullable|url|max:255', 'سناب شات'],
            'social_linkedin' => ['string', 'nullable|url|max:255', 'لينكدإن'],
            'social_telegram' => ['string', 'nullable|url|max:255', 'تيليجرام'],
        ],
    ];

    /** أنواع عمود `type` في الجدول مقابل أنواع الخريطة. */
    private const TYPE_COLUMN = ['bool' => 'boolean', 'int' => 'integer', 'decimal' => 'decimal', 'json' => 'json', 'file' => 'string', 'string' => 'string'];

    /** نصّ القيمة كما يُحفظ في الجدول، حسب نوع الحقل. */
    private function normalizeSettingValue(string $type, $value): string
    {
        return match ($type) {
            'bool' => in_array($value, [true, 1, '1', 'on', 'true', 'yes'], true) ? 'true' : 'false',
            'json' => is_array($value) ? json_encode(array_values($value)) : (string) ($value ?? ''),
            default => is_scalar($value) ? trim((string) $value) : '',
        };
    }

    /**
     * يكتب قيمة إعداد. الإعداد القائم يحتفظ بقسمه ونوعه — الحفظ كان ينقل
     * الإعداد إلى قسم النموذج المُرسِل مهما كان قسمه الأصلي.
     */
    private function writeSetting(string $key, string $value, string $group, string $type): void
    {
        $setting = OnlineStoreSetting::where('key', $key)->first();

        if ($setting) {
            $setting->update(['value' => $value]);

            return;
        }

        OnlineStoreSetting::create([
            'key' => $key,
            'value' => $value,
            'group' => $group,
            'type' => self::TYPE_COLUMN[$type] ?? 'string',
        ]);
    }

    /**
     * المستودع/الخزينة الافتراضيان يجب أن يكونا «تابعَين للمتجر» ونشطَين —
     * الطلبات تخصم من هذا المستودع وتودِع في هذه الخزينة، وقبولُ أيّ رقمٍ
     * يصل في الطلب كان يسمح بربط المتجر بمستودع فرعٍ أو خزينة موظف.
     *
     * @return array<string, string> أخطاء بالمفتاح
     */
    private function storeDefaultsErrors(array $values): array
    {
        $errors = [];

        if (! empty($values['default_storehouse_id'])
            && ! StoreHouse::onlineStore()->where('status', 'active')->whereKey($values['default_storehouse_id'])->exists()) {
            $errors['default_storehouse_id'] = 'المخزون المختار ليس مستودعاً نشطاً تابعاً للمتجر. علّم المستودع «تابع للمتجر» من شاشة المخازن أولاً.';
        }

        if (! empty($values['default_treasury_id'])
            && ! Account::onlineStore()->where('is_active', 1)->whereKey($values['default_treasury_id'])->exists()) {
            $errors['default_treasury_id'] = 'الخزينة المختارة ليست خزينة/حساباً بنكياً نشطاً تابعاً للمتجر. اربطها بالمتجر من شاشة الخزائن أولاً.';
        }

        return $errors;
    }

    /**
     * تحديث الإعدادات
     */
    public function update(Request $request)
    {
        $group = (string) $request->input('group', 'general');

        if (! array_key_exists($group, $this->groups)) {
            abort(404);
        }

        $fields = self::FIELDS[$group] ?? [];

        // إعداداتٌ قائمة في القسم نفسه لم تُذكر في الخريطة (أُضيفت لنموذجه لاحقاً)
        // تُقبل نصّاً — كي لا يُسقط التحديثُ حقلاً شرعياً؛ وما عداها يُتجاهل
        $legacy = OnlineStoreSetting::where('group', $group)
            ->whereNotIn('key', array_keys($fields))
            ->where('type', '!=', 'boolean')
            ->pluck('key')->all();

        $rules = [];
        $attributes = [];
        foreach ($fields as $key => [$type, $rule, $label]) {
            $rules[$key] = $rule;
            $attributes[$key] = $label;
        }
        foreach ($legacy as $key) {
            $rules[$key] = 'nullable|string|max:5000';
        }
        if (isset($fields['enabled_payment_methods'])) {
            $rules['enabled_payment_methods.*'] = 'integer|exists:payment_methods,id';
            $attributes['enabled_payment_methods.*'] = 'طريقة الدفع';
        }

        $validator = Validator::make($request->all(), $rules, [], $attributes);
        $validator->after(function ($v) use ($request, $group) {
            // حسابا ضريبة الشحن ومصروف الشحن المجاني: نوعٌ محاسبيّ صحيح وحسابٌ فرعيّ (حركيّ) لا تفرّع تحته
            if ($group === 'accounting') {
                foreach (['shipping_vat_account_id' => ['liabilities', 'حساب ضريبة الشحن يجب أن يكون من الالتزامات (كحساب ضريبة القيمة المضافة المحصَّلة).'],
                    'free_shipping_expense_account_id' => ['expenses', 'حساب مصروف الشحن المجاني يجب أن يكون من المصروفات.']] as $key => [$type, $message]) {
                    $id = $request->input($key);

                    if ($id && is_numeric($id)) {
                        $account = Account::find((int) $id);

                        if ($account && $account->account_type !== $type) {
                            $v->errors()->add($key, $message);
                        } elseif ($account && $account->children()->exists()) {
                            $v->errors()->add($key, 'اختر حساباً فرعياً (لا يتفرّع منه غيره) للترحيل عليه.');
                        }
                    }
                }

                return;
            }

            if ($group !== 'checkout') {
                return;
            }

            foreach ($this->storeDefaultsErrors($request->only(['default_storehouse_id', 'default_treasury_id'])) as $key => $message) {
                $v->errors()->add($key, $message);
            }

            $min = $request->input('min_order_amount');
            $max = $request->input('max_order_amount');
            if (is_numeric($min) && is_numeric($max) && (float) $max > 0 && (float) $max < (float) $min) {
                $v->errors()->add('max_order_amount', 'الحد الأقصى للطلب يجب ألا يقل عن الحد الأدنى.');
            }

            $enabled = array_map('intval', (array) $request->input('enabled_payment_methods', []));
            $default = $request->input('default_payment_method_id');
            if ($default && $enabled !== [] && ! in_array((int) $default, $enabled, true)) {
                $v->errors()->add('default_payment_method_id', 'طريقة الدفع الافتراضية يجب أن تكون ضمن طرق الدفع المتاحة في المتجر.');
            }
        });

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput($request->except(['store_logo', 'store_favicon']));
        }

        $txLevel = DB::transactionLevel();

        try {
            DB::beginTransaction();

            foreach ($fields as $key => [$type, $rule, $label]) {
                if ($type === 'file') {
                    // بلا ملفٍ مرفوع تبقى الصورة الحالية
                    if ($request->hasFile($key)) {
                        $old = OnlineStoreSetting::where('key', $key)->value('value');
                        $this->writeSetting($key, $this->uploadFile($request->file($key), $key), $group, $type);
                        $this->deleteOldImage($old);
                    }

                    continue;
                }

                // مربّع اختيار وقائمة مربّعات: الغياب عن الطلب = غير محدَّد (النموذج يعرضهما)
                if ($type === 'bool' || $key === 'enabled_payment_methods') {
                    $this->writeSetting($key, $this->normalizeSettingValue($type, $request->input($key, $type === 'bool' ? false : [])), $group, $type);

                    continue;
                }

                // ما لم يُرسَل لا يُمسّ
                if (! $request->exists($key)) {
                    continue;
                }

                $this->writeSetting($key, $this->normalizeSettingValue($type, $request->input($key)), $group, $type);
            }

            foreach ($legacy as $key) {
                if ($request->exists($key)) {
                    $this->writeSetting($key, $this->normalizeSettingValue('string', $request->input($key)), $group, 'string');
                }
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم حفظ الإعدادات بنجاح',
                ]);
            }

            return redirect()->back()->with('success', 'تم حفظ الإعدادات بنجاح');

        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — التسجيل يكتب عبر نفس
            // اتصال قاعدة البيانات، فيُلغى سجلّه هو نفسه مع المعاملة إن كُتب
            // قبل انتهائها (تحقّقتُ من هذا مباشرةً على قاعدة محلية)
            while (DB::transactionLevel() > $txLevel) {
                DB::rollBack();
            }

            OperationLog::failure($e, 'OnlineStore.StoreSettings', 'update');

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حفظ الإعدادات',
                ], 500);
            }

            return redirect()->back()->with('error', 'حدث خطأ أثناء حفظ الإعدادات');
        }
    }

    /**
     * تحديث إعداد واحد (AJAX) — مفاتيح التبديل السريع في صفحة الإعدادات.
     *
     * للإعدادات المنطقية وحدها: كانت تقبل أيّ مفتاحٍ وأيّ قيمة فتُنشئ إعداداً
     * باسمٍ عشوائي، وتحوّل قيمة أيّ إعدادٍ رقميّ إلى «true/false».
     */
    public function updateSingle(Request $request)
    {
        $request->validate([
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'value' => 'nullable',
        ], [], ['key' => 'الإعداد', 'value' => 'القيمة']);

        $key = $request->key;
        $setting = OnlineStoreSetting::where('key', $key)->first();
        $mapped = collect(self::FIELDS)->first(fn ($fields) => isset($fields[$key]));
        $isBoolean = ($setting && $setting->type === 'boolean') || ($mapped && $mapped[$key][0] === 'bool');

        if (! $isBoolean) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الإعداد لا يُعدَّل من مفاتيح التبديل السريع.',
            ], 422);
        }

        try {
            $value = $this->normalizeSettingValue('bool', is_string($request->value) ? strtolower($request->value) : $request->value);

            $this->writeSetting($key, $value, $this->detectGroupFromKey($key), 'bool');

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الإعداد بنجاح',
            ]);

        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreSettings', 'updateSingle');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الإعداد',
            ], 500);
        }
    }

    /**
     * تحديد المجموعة من اسم المفتاح
     */
    protected function detectGroupFromKey($key)
    {
        // البحث في الحقول المنطقية
        foreach ($this->booleanFields as $group => $fields) {
            if (in_array($key, $fields)) {
                return $group;
            }
        }

        // تحديد المجموعة حسب بادئة المفتاح
        $prefixMap = [
            'store_' => 'general',
            'theme_' => 'appearance',
            'secondary_' => 'appearance',
            'products_per_' => 'appearance',
            'enable_wishlist' => 'appearance',
            'enable_compare' => 'appearance',
            'enable_reviews' => 'appearance',
            'allow_negative' => 'appearance',
            'filter_' => 'filters',
            'max_' => 'filters',
            'show_' => 'filters',
            'price_' => 'filters',
            'min_order' => 'checkout',
            'max_order' => 'checkout',
            'tax_' => 'checkout',
            'cart_' => 'checkout',
            'enable_guest' => 'checkout',
            'require_phone' => 'checkout',
            'default_payment' => 'checkout',
            'enabled_payment' => 'checkout',
            'shipping_' => 'shipping',
            'free_shipping' => 'shipping',
            'default_shipping' => 'shipping',
            'express_shipping' => 'shipping',
            'cod_' => 'shipping',
            'packaging_' => 'shipping',
            'meta_' => 'seo',
            'google_' => 'seo',
            'facebook_' => 'seo',
            'enable_email' => 'notifications',
            'enable_sms' => 'notifications',
            'enable_push' => 'notifications',
            'maintenance_' => 'advanced',
            'enable_multi' => 'advanced',
            'enable_cache' => 'advanced',
            'auto_approve' => 'advanced',
            'social_' => 'social',
        ];

        foreach ($prefixMap as $prefix => $group) {
            if (strpos($key, $prefix) === 0 || $key === $prefix) {
                return $group;
            }
        }

        return 'general';
    }

    /**
     * رفع ملف (صورة) إلى المجلد العام
     */
    protected function uploadFile($file, $key)
    {
        $folder = 'assets/uploads/store/settings';
        $path = public_path($folder);

        if (! file_exists($path)) {
            mkdir($path, 0755, true);
        }

        // الامتداد من نوع الملف الفعلي لا من اسمه — ملفٌ باسم `x.php` لا يُحفظ `.php`
        $fileExtension = $file->extension() ?: 'png';
        $fileName = $key.'_'.time().rand(1, 99).'.'.$fileExtension;
        $file->move($path, $fileName);

        return $folder.'/'.$fileName;
    }

    /**
     * حذف الصورة القديمة من المجلد العام
     */
    protected function deleteOldImage($imagePath)
    {
        if ($imagePath && file_exists(public_path($imagePath))) {
            @unlink(public_path($imagePath));
        }
    }

    /**
     * حذف ملف (صورة)
     */
    public function deleteFile(Request $request)
    {
        // مفاتيح الصور وحدها — كان يقبل أيّ مفتاح فيمسح قيمة أيّ إعداد
        // (ويحاول حذف «ملفٍ» اسمُه تلك القيمة من المجلد العام)
        $request->validate([
            'key' => 'required|string|in:store_logo,store_favicon',
        ], [], ['key' => 'الملف']);

        try {
            $setting = OnlineStoreSetting::where('key', $request->key)->first();

            if ($setting && $setting->value) {
                // حذف الملف من المجلد العام
                $this->deleteOldImage($setting->value);

                // تحديث القيمة
                $setting->update(['value' => null]);

            }

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الملف بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreSettings', 'deleteFile');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * استعادة الإعدادات الافتراضية
     */
    public function resetToDefault(Request $request)
    {
        $group = $request->input('group');

        if ($group !== null && (! is_string($group) || ! array_key_exists($group, $this->groups))) {
            return response()->json(['success' => false, 'message' => 'قسم الإعدادات غير معروف'], 422);
        }

        // بلا البذرة لا استعادة: كان الحذف يسبقها، فإن غابت أو فشلت بقي المتجر بلا إعدادات
        if (! class_exists(OnlineStoreSettingsSeeder::class)) {
            return response()->json(['success' => false, 'message' => 'بذرة الإعدادات الافتراضية غير متوفرة — لم يُغيَّر شيء.'], 500);
        }

        $txLevel = DB::transactionLevel();

        try {
            DB::beginTransaction();

            // حذف إعدادات المجموعة
            $query = OnlineStoreSetting::query();

            if ($group) {
                $query->where('group', $group);
            }

            $query->delete();

            // إعادة تشغيل السيدر
            \Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\OnlineStoreSettingsSeeder',
                '--force' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم استعادة الإعدادات الافتراضية',
            ]);

        } catch (\Throwable $e) {
            while (DB::transactionLevel() > $txLevel) {
                DB::rollBack();
            }

            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreSettings', 'resetToDefault');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تصدير الإعدادات
     */
    public function export()
    {
        $settings = OnlineStoreSetting::all();

        $data = $settings->pluck('value', 'key')->toArray();

        $filename = 'store_settings_'.date('Y-m-d_H-i-s').'.json';

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Content-Type', 'application/json');
    }

    /**
     * استيراد الإعدادات
     */
    public function import(Request $request)
    {
        // ملف JSON يُكتشف غالباً `text/plain` — `mimes:json` كان يرفض ملف التصدير نفسه
        $request->validate([
            'file' => 'required|file|mimetypes:application/json,text/plain,text/json|max:2048',
        ], ['file.mimetypes' => 'الملف يجب أن يكون بصيغة JSON.'], ['file' => 'ملف الإعدادات']);

        $content = file_get_contents($request->file('file')->getRealPath());
        $settings = json_decode((string) $content, true);

        if (! is_array($settings) || $settings === [] || array_is_list($settings)) {
            return response()->json(['success' => false, 'message' => 'الملف ليس ملف إعدادات صالحاً (JSON بمفاتيح الإعدادات).'], 422);
        }

        // المستودع/الخزينة في الملف يخضعان لشرط «تابع للمتجر» نفسه
        $defaultsErrors = $this->storeDefaultsErrors(array_intersect_key($settings, array_flip(['default_storehouse_id', 'default_treasury_id'])));

        $txLevel = DB::transactionLevel();

        try {
            DB::beginTransaction();

            $imported = 0;
            foreach ($settings as $key => $value) {
                if (! is_string($key) || isset($defaultsErrors[$key])) {
                    continue;
                }

                // المفاتيح القائمة فقط؛ والقيمة نصٌّ دائماً (المصفوفة تُرمَّز JSON)
                $imported += OnlineStoreSetting::where('key', $key)->update([
                    'value' => is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : null)),
                ]);
            }

            DB::commit();

            $message = 'تم استيراد الإعدادات بنجاح ('.$imported.' إعداد).';
            if ($defaultsErrors) {
                $message .= ' تُرك المستودع/الخزينة الافتراضيان كما هما: القيمة في الملف ليست تابعةً للمتجر.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (\Throwable $e) {
            // rollBack() قبل failure() — انظر التعليق في update() أعلاه
            while (DB::transactionLevel() > $txLevel) {
                DB::rollBack();
            }

            OperationLog::failure($e, 'OnlineStore.StoreSettings', 'import');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * اختبار إعدادات البريد الإلكتروني
     */
    public function testEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ], [], ['email' => 'البريد الإلكتروني']);

        try {
            \Mail::raw('هذه رسالة اختبارية من متجرك الإلكتروني', function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('اختبار إعدادات البريد الإلكتروني');
            });

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال رسالة الاختبار بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreSettings', 'testEmail');

            return response()->json([
                'success' => false,
                'message' => 'فشل الإرسال: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * مسح جميع أنواع الكاش
     * Clear all cache types
     */
    public function clearCache()
    {
        try {
            // مسح كاش إعدادات المتجر

            // مسح كاش Laravel
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('view:clear');
            \Artisan::call('route:clear');

            return response()->json([
                'success' => true,
                'message' => 'تم مسح جميع أنواع الكاش بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreSettings', 'clearCache');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء مسح الكاش: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * بحث في شجرة الحسابات لاختيار حساب محاسبي في إعدادات المتجر.
     *
     * مسارٌ خاصٌّ بهذه الشاشة (لا يستدعيه متحكّم آخر) بدل استعمال
     * `Sitting\AccountingSettingsController::accounts()` مباشرة — تلك الشاشة
     * موثَّقة صراحةً بأنها مستقلة ولا يعتمد عليها متحكّمٌ خارجها.
     */
    public function searchAccounts(Request $request)
    {
        $term = is_string($request->get('q')) ? trim($request->get('q')) : '';

        $accounts = Account::query()
            ->select(['id', 'name', 'code', 'account_type', 'is_active'])
            ->where('is_active', true)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "{$term}%");
                });
            })
            ->orderBy('code')
            ->limit(50)
            ->get()
            ->map(fn ($account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => trim($account->code.' - '.$account->name, ' -'),
            ]);

        return response()->json($accounts);
    }
}
