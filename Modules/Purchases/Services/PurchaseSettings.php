<?php

namespace Modules\Purchases\Services;

use App\Models\PurchaseInvoiceSetting;
use Illuminate\Support\Facades\Cache;

/**
 * إعدادات قسم المشتريات — المصدر الواحد لمفاتيحها ولقراءتها.
 *
 * كانت المفاتيح الثلاثة عشر مكتوبةً في ثلاثة مواضع لا يعرف أحدها الآخر:
 * ترحيلٌ يزرعها في `purchase_invoice_settings`، وقالبٌ يعرضها بالتكرار على
 * الجدول، ومتحكّماتٌ تسأل عن كلّ مفتاح باسمه النصّي. فلم يكن في النظام موضع
 * يُقال فيه ما الإعدادات ولا أين تسري — والنتيجة أن ثمانيةً منها بقيت في
 * الشاشة بلا أثر في أي سطر: ستةٌ لا يذكرها ملفّ، واثنتان تُحمَّلان في متغيّر
 * ثم يُهمَل.
 *
 * الآن `catalog()` هو المصدر: منه تُبنى الشاشة، وعليه يُصادَق الحفظ، ومنه
 * يُعرف موضع سريان كلّ مفتاح. ومن يضيف إعداداً يضيف سطراً واحداً هنا.
 *
 * القراءة عبر `enabled()` لا عبر الموديل مباشرةً: حفظ فاتورة شراء واحدة كان
 * يُطلق سبعة استعلامات — واحداً لكل مفتاح — ومرّتين (في الإنشاء والتعديل).
 * هنا استعلامٌ واحد يُخزَّن ساعةً، ويُبطَل عند كلّ حفظ.
 *
 * التقسيم بالمستند لا بنوع الإعداد: من يفتح الشاشة يبحث عن «المرتجع» أو
 * «الموردين»، لا عن «الحالات اليدوية». فالحالة اليدوية تسكن مع مستندها.
 *
 * @see PurchaseSettingsNavigation روابط شاشات المشتريات المرتبطة
 */
class PurchaseSettings
{
    private const CACHE_KEY = 'purchase_settings.map';

    private const CACHE_TTL = 3600;

    /** @var array<string, bool>|null الخريطة المحلولة داخل الطلب الواحد */
    private static ?array $memo = null;

    /**
     * الإعدادات مجموعةً بالمستند.
     *
     * `default` قيمة المفتاح عند إعادة التعيين وعند زرعه أول مرّة.
     * `applies` وصف موضع سريانه — يُعرض تحت الإعداد في الشاشة، فمن يبدّله
     * يعرف ما الذي سيتغيّر قبل أن يبدّله لا بعده.
     *
     * قاعدة الافتراضيات: الافتراض يصف ما يفعله النظام **اليوم**، لا ما نراه
     * صواباً. مفتاحٌ يُنفَّذ لأول مرّة يجب ألّا يُسقط سلوكاً قائماً بمجرّد
     * تشغيل الترحيل — ومن أرادَ غيره بدّله بنفسه.
     *
     * حُذف `custom_daily_entries` («وضع مخصص للقيود اليومية»): لم يكن له
     * معنى محدَّد ولا سطرٌ يقرؤه، ومفتاحٌ يُبدَّل بلا أثر أسوأ من غيابه —
     * لأنه يوهم بضبطٍ لم يحدث.
     *
     * @return array<string, array{icon:string,items:array<string, array{label:string,hint:string,applies:string,default:bool}>}>
     */
    /** مفتاحُ حصر موظّفي المشتريات بأقسامٍ بعينها — قيمتُه في عمود `value` */
    public const EMPLOYEE_DEPARTMENTS_KEY = 'restrict_employees_to_departments';

    public static function catalog(): array
    {
        return [
            /* موظّفو المشتريات: أيُّ الأقسام تُغذّي قوائمَ «الموظّف» في
               مستندات الشراء والموردين والتقارير. المفتاحُ يُشغِّل الحصر،
               والأقسامُ نفسُها تُختار في الشاشة وتُحفَظ في `value`. */
            'employees' => [
                'icon' => 'fa-users',
                'items' => [
                    self::EMPLOYEE_DEPARTMENTS_KEY => self::item(self::EMPLOYEE_DEPARTMENTS_KEY, 'employee_pickers', false),
                ],
            ],

            'invoices' => [
                'icon' => 'fa-file-invoice-dollar',
                'items' => [
                    'default_received_invoices' => self::item('default_received_invoices', 'invoice_save', true),
                    // مصدرُ مستودع الاستلام — الرئيسيّ أو مستودع الموظّف، نظيرُ «مصدر مستودع البيع» في المبيعات
                    'receive_in_main_warehouse' => self::item('receive_in_main_warehouse', 'warehouse_default', false),
                    'default_paid_invoices' => self::item('default_paid_invoices', 'invoice_save', false),
                    'auto_payment' => self::item('auto_payment', 'invoice_save', true),
                    'update_product_prices' => self::item('update_product_prices', 'invoice_save', false),
                    'manual_invoice_status' => self::item('manual_invoice_status', 'invoice_save', false),
                ],
            ],

            'orders' => [
                'icon' => 'fa-cart-plus',
                'items' => [
                    'manual_purchase_orders' => self::item('manual_purchase_orders', 'order_save', false),
                    'manual_purchase_orders_status' => self::item('manual_purchase_orders_status', 'order_request_save', false),
                ],
            ],

            'quotations' => [
                'icon' => 'fa-file-signature',
                'items' => [
                    'manual_quote_orders' => self::item('manual_quote_orders', 'quotation_request_save', false),
                    'manual_purchase_quotes' => self::item('manual_purchase_quotes', 'quotation_save', false),
                ],
            ],

            'returns' => [
                'icon' => 'fa-undo',
                'items' => [
                    // مطفأ افتراضياً من جديد: تفعيله كان يُخرج المرتجع البضاعة
                    // من المخزن فور الحفظ حتى لفاتورةٍ أصليةٍ لم تُستلَم بعد —
                    // أي بلا بضاعةٍ في المخزون أصلاً لتخرج. الإخراج الآن لا
                    // يحدث إلا بتأشير «تم الاستلام» عند الحفظ، أو بزيارة شاشة
                    // العرض لاحقاً والضغط على «تأكيد الاستلام». انظر ترحيل
                    // `disable_return_default_received_again` الذي يهبط
                    // بالقيمة المزروعة سابقاً في القاعدة لمن رفعها.
                    'return_default_received' => self::item('return_default_received', 'return_save', false),
                    'allow_multiple_returns' => self::item('allow_multiple_returns', 'return_save', false),
                ],
            ],

            'notices' => [
                'icon' => 'fa-file-invoice',
                // الشاشات تعمل اليوم بلا شرط، فالقيمة المزروعة `false` لم تكن
                // تعني تعطيلاً — لم يقرأها أحد. الافتراض يتبع الواقع
                'items' => [
                    'enable_debit_notice' => self::item('enable_debit_notice', 'debit_notice', true),
                    // نظير `return_default_received` أعلاه ونفس سببه: مطفأ
                    // افتراضياً فلا يُخرج الإشعار المدين شيئاً من المخزون إلا
                    // بتأشيرٍ صريح — عند الحفظ أو من زرّ «تأكيد الاستلام» في
                    // شاشة العرض.
                    'notice_default_received' => self::item('notice_default_received', 'debit_notice', false),
                ],
            ],

            'suppliers' => [
                'icon' => 'fa-truck',
                // كلّها مطفأة افتراضياً: `SupplierRequest` لا يُلزم بحقل واحد
                // ولا يمنع تكراراً، فأيّ تفعيل تلقائي كان سيُبطل شاشة إضافة
                // المورد على من يملأ ما اعتاد ملأه
                'items' => [
                    'supplier_require_name' => self::item('supplier_require_name', 'supplier_save', false),
                    'supplier_require_mobile' => self::item('supplier_require_mobile', 'supplier_save', false),
                    'supplier_require_tax_number' => self::item('supplier_require_tax_number', 'supplier_save', false),
                    'supplier_require_commercial_registration' => self::item('supplier_require_commercial_registration', 'supplier_save', false),
                    'supplier_unique_tax_number' => self::item('supplier_unique_tax_number', 'supplier_save', false),
                    'supplier_unique_mobile' => self::item('supplier_unique_mobile', 'supplier_save', false),
                    'supplier_unique_email' => self::item('supplier_unique_email', 'supplier_save', false),
                    'supplier_block_inactive' => self::item('supplier_block_inactive', 'supplier_documents', false),
                ],
            ],

            'documents' => [
                'icon' => 'fa-layer-group',
                'items' => [
                    'enable_settlement' => self::item('enable_settlement', 'document_form', true),
                    // سطر الخصومات يظهر اليوم في كلّ مستند، فافتراضٌ بالإطفاء
                    // كان سيُخفيه عمّن لم يطلب ذلك
                    'total_discounts' => self::item('total_discounts', 'document_totals', true),
                ],
            ],
        ];
    }

    /**
     * بند واحد في السجلّ — النصوص تُشتقّ من المفتاح باصطلاح ثابت.
     *
     * الاصطلاح يمنع الخطأ الصامت الذي وقع من قبل: مفتاحٌ يُضاف ونصّه يُنسى،
     * فتظهر في الشاشة عبارة `purchase_settings.options.xxx` حرفيّةً.
     *
     * @return array{label:string,hint:string,applies:string,default:bool}
     */
    private static function item(string $key, string $applies, bool $default): array
    {
        return [
            'label' => __('purchase_settings.options.'.$key),
            'hint' => __('purchase_settings.options.'.$key.'_hint'),
            'applies' => __('purchase_settings.applies.'.$applies),
            'default' => $default,
        ];
    }

    /**
     * المفاتيح المعروفة كلّها مسطَّحةً.
     *
     * @return array<string, array{label:string,hint:string,applies:string,default:bool}>
     */
    public static function keys(): array
    {
        $keys = [];

        foreach (static::catalog() as $group) {
            $keys += $group['items'];
        }

        return $keys;
    }

    /** الافتراضيات — يقرؤها زرّ «إعادة التعيين» والزرع الأوّل. @return array<string, bool> */
    public static function defaults(): array
    {
        return array_map(fn (array $item) => $item['default'], static::keys());
    }

    /**
     * خريطة المفتاح إلى حالته.
     *
     * ما في الجدول ولا في السجلّ يُتجاهَل، وما في السجلّ ولا في الجدول يأخذ
     * افتراضيّه — فلا تنكسر الشاشة ولا المتحكّمات قبل تشغيل الترحيل.
     *
     * @return array<string, bool>
     */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            $stored = Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL,
                fn () => PurchaseInvoiceSetting::pluck('is_active', 'setting_key')
                    ->map(fn ($value) => (bool) $value)
                    ->all()
            );
        } catch (\Throwable $e) {
            // قاعدة بيانات غير مهيّأة (أثناء الترحيلات مثلاً): تبقى الافتراضيات
            $stored = [];
        }

        return self::$memo = array_merge(static::defaults(), array_intersect_key($stored, static::keys()));
    }

    /** هل الإعداد مفعَّل؟ مفتاحٌ مجهول يعود بـ`false` لا باستثناء. */
    public static function enabled(string $key): bool
    {
        return static::all()[$key] ?? false;
    }

    /** يُبطل الكاش والذاكرة معاً — يُستدعى بعد كلّ حفظ. */
    public static function flush(): void
    {
        self::$memo = null;
        self::$departmentsMemo = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'.departments');
    }

    /** @var array<int, int>|null */
    private static ?array $departmentsMemo = null;

    /**
     * أقسامُ موظّفي المشتريات المحفوظة — فارغةٌ تعني «بلا حصر».
     *
     * تُقرأ من عمود `value` على صفّ المفتاح؛ والمفتاحُ نفسُه يُشغِّل الحصر
     * أو يُطفئه ({@see enabled()}) فتبقى الأقسامُ محفوظةً وهو مطفأ.
     *
     * @return array<int, int>
     */
    public static function departmentIds(): array
    {
        if (self::$departmentsMemo !== null) {
            return self::$departmentsMemo;
        }

        try {
            $ids = Cache::remember(self::CACHE_KEY.'.departments', self::CACHE_TTL, function () {
                $raw = PurchaseInvoiceSetting::where('setting_key', self::EMPLOYEE_DEPARTMENTS_KEY)->value('value');
                $decoded = $raw ? json_decode($raw, true) : [];

                return is_array($decoded) ? array_values(array_unique(array_map('intval', $decoded))) : [];
            });
        } catch (\Throwable $e) {
            $ids = [];
        }

        return self::$departmentsMemo = $ids;
    }

    /**
     * الأقسامُ النافذة فعلاً على قوائم الموظفين — أو `null` بلا حصر.
     *
     * `null` حين يكون المفتاحُ مطفأً **أو** لم يُختر قسم: حصرٌ بلا أقسام
     * كان سيُفرغ كلَّ قائمةٍ في الوحدة، وليس ذلك ما يقصده من نسي الاختيار.
     *
     * @return array<int, int>|null
     */
    public static function employeeDepartments(): ?array
    {
        if (! static::enabled(self::EMPLOYEE_DEPARTMENTS_KEY)) {
            return null;
        }

        $ids = static::departmentIds();

        return $ids === [] ? null : $ids;
    }

    /** @param array<int, int|string> $ids */
    public static function saveDepartmentIds(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));

        PurchaseInvoiceSetting::where('setting_key', self::EMPLOYEE_DEPARTMENTS_KEY)
            ->update(['value' => json_encode($ids)]);

        static::flush();
    }

    /**
     * يُوائم الجدول مع السجلّ: يزرع الناقص ويُسقط المجهول.
     *
     * يُستدعى من شاشة الإعدادات قبل العرض، فإضافة مفتاح إلى `catalog()`
     * تكفي وحدها — بلا ترحيلٍ يرافقها.
     */
    public static function sync(): void
    {
        $known = static::keys();

        $existing = PurchaseInvoiceSetting::pluck('setting_key')->all();

        foreach ($known as $key => $item) {
            if (! in_array($key, $existing, true)) {
                PurchaseInvoiceSetting::create([
                    'setting_key' => $key,
                    'setting_name' => $item['label'],
                    'is_active' => $item['default'],
                ]);
            }
        }

        $unknown = array_diff($existing, array_keys($known));

        if ($unknown !== []) {
            PurchaseInvoiceSetting::whereIn('setting_key', $unknown)->delete();
        }

        static::flush();
    }

    /** عدد المفعَّل من المفاتيح — للعدّاد أعلى الشاشة. */
    public static function activeCount(): int
    {
        return count(array_filter(static::all()));
    }

    /**
     * عدد المفعَّل داخل قسم واحد — لشارة القسم في شاشة الإعدادات.
     *
     * @param  array<string, mixed>  $items
     */
    public static function activeIn(array $items): int
    {
        return count(array_filter(array_intersect_key(static::all(), $items)));
    }
}
