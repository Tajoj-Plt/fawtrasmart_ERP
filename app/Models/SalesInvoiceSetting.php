<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إعدادات الفواتير وعروض الأسعار — صفّ واحد للمنشأة كلّها.
 *
 * Usage:
 *   SalesInvoiceSetting::current();                  // الصفّ، يُنشأ إن لم يوجد
 *   SalesInvoiceSetting::current()->preview_before_save;
 *
 * @see \Modules\Sales\Http\Controllers\SittingInvoiceController::invoice()
 */
class SalesInvoiceSetting extends Model
{
    protected $table = 'sales_invoice_settings';

    /**
     * الإعدادات المنطقية. مصدر واحد لقائمتها: منه تُبنى قواعد التحقّق في
     * `SalesInvoiceSettingsRequest`، ومنه يُحسب عدد المفعَّل في الشاشة —
     * فإضافة إعداد لا تستلزم تذكّر ثلاثة مواضع.
     */
    public const FLAGS = [
        'no_sell_below_cost',
        'enable_max_discount',
        'select_price_list',
        'show_invoice_profit',
        'preview_before_save',
        'default_paid_status',
        'auto_pay_if_balance',

        /* رصيدان لا واحد: `auto_pay_if_balance` يقرأ ذمّة العميل الدائنة
           (مقاصّةٌ يتمّها قيدُ الفاتورة وحده)، وهذا يقرأ **المحفظة** —
           حساباً مستقلاً تحت الخصوم يحتاج قيداً حقيقياً. انظر الترحيل
           `add_auto_pay_from_wallet_to_sales_invoice_settings`. */
        'auto_pay_from_wallet',
        'manual_invoice_status',
        'disable_delivery_options',

        /* الخدمي والرقمي بلا رصيد، فلا يمرّان من تصفية أصناف الموظّف على
           مستودعه — انظر `stocklessTypesForEmployees()`. */
        'show_service_products',
        'show_digital_products',
        'disable_quotes',
        'manual_quote_status',
        'enable_sales_orders',
        'manual_sales_order_status',
        'apply_offers_to_quotes',
        /* «كوبونات الخصم» — جدولُ `store_coupons` وشاشتُه قائمان منذ
           المتجر الإلكتروني، ولم يكن يقرؤهما مستندُ مبيعاتٍ واحد. */
        'enable_coupons',
        'copy_notes_on_conversion',
        'custom_journal_description',
        'send_on_social',
    ];

    /** إعدادات رقمية — تُعرَض حقولَ إدخال لا مفاتيحَ تبديل. */
    public const NUMBERS = [
        'max_discount_percent',
        'max_discount_amount',
    ];

    /**
     * وحدة السقف الأقصى للخصم — نسبةٌ **أو** مبلغ، لا الاثنان.
     *
     * الأسماء نفسُها التي يستعملها خصم الفاتورة في `store()`
     * (`discount_type`)، فلا يتعلّم القارئ مفردتين لمعنى واحد.
     */
    public const MAX_DISCOUNT_TYPES = ['percentage', 'amount'];

    /**
     * مصدرُ مستودع البيع — من أين تخرج البضاعة في مستندات المبيعات.
     *
     *   • `employee` — المستودعات المربوطة بالموظّف في «المستودعات
     *     الافتراضية»، والمستودعُ الرئيسي عند غيابها. وهو السلوك الذي كان
     *     مثبَّتاً في الشيفرة قبل هذا الإعداد.
     *
     *   • `main` — المستودع الرئيسي (`major = 1`) وحده، وتُتجاوَز
     *     مستودعات الموظّف.
     *
     * والقراءة عبر `warehouseSource()` لا من العمود مباشرةً: صفٌّ قديم قد
     * يحمل `null` قبل الترحيل، والنصُّ لا يُقارَن في مواضع متفرّقة.
     *
     * @see \Modules\Sales\Support\SalesWarehouse
     */
    public const WAREHOUSE_SOURCES = ['employee', 'main'];

    protected $fillable = [
        'max_discount_type',
        'warehouse_source',
        ...self::NUMBERS,
        ...self::FLAGS,
        'updated_by',
    ];

    /**
     * القيم الافتراضية على الكائن لا على القاعدة وحدها.
     *
     * `firstOrCreate([])` يُدرِج صفّاً تملؤه القاعدة بقيمها الافتراضية، لكن
     * الكائن العائد لا يحملها — فيعود حقل «أقصى نسبة خصم» فارغاً عند أوّل
     * زيارة، ويُقرأ الفراغُ صفراً: خصمٌ ممنوعٌ بلا أن يُختار.
     */
    protected $attributes = [
        'max_discount_percent' => 10,
        'warehouse_source' => 'employee',
        'show_service_products' => true,
        'show_digital_products' => true,
    ];

    protected function casts(): array
    {
        return array_fill_keys(self::FLAGS, 'boolean') + [
            'max_discount_percent' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
        ];
    }

    /**
     * النسبة القصوى للخصم السارية على فرعٍ بعينه.
     *
     * قيمة المنشأة هي المخزَّنة هنا، ويتجاوزها الفرع متى ضبط
     * `max_discount_percent` بنفسه في «إعدادات الفروع» — أمّا الفرع الذي
     * لم يضبطه (`inherited` أو `default`) فيتبع هذه الشاشة. بلا هذا
     * التمييز كان افتراضُ كتالوج الفروع (١٠٪) يطمس ما يكتبه المستخدم هنا،
     * فيبدو الحقل كأنّه لا يُقرأ.
     */
    public function maxDiscountFor(?int $branchId = null): ?float
    {
        /* الوحدة تحسم أيّ سقفٍ يسري: المختارةُ مبلغاً تُلغي النسبة رأساً
           — لا سقفين معاً. والرقمان يبقيان محفوظين في عموديهما، فمن بدّل
           الوحدة ثمّ عاد وجد رقمه كما تركه. */
        if ($this->maxDiscountType() !== 'percentage') {
            return null;
        }

        $companyValue = (float) ($this->max_discount_percent ?? 10);

        if (! function_exists('branch_setting_source')) {
            return $companyValue;
        }

        $branchId = $branchId ?? (function_exists('current_branch_id') ? current_branch_id() : null);

        if ($branchId && branch_setting_source('max_discount_percent', $branchId) === 'own') {
            return (float) branch_setting('max_discount_percent', $companyValue, $branchId);
        }

        return $companyValue;
    }

    /**
     * وحدة السقف — `percentage` أو `amount`، والافتراض نسبة.
     *
     * القراءة عبر هذه الدالّة وحدها: عمودٌ نصّيّ قد يحمل قيمةً قديمة أو
     * فارغة، فلا يُقارَن نصُّه في مواضع متفرّقة.
     */
    public function maxDiscountType(): string
    {
        return in_array($this->max_discount_type, self::MAX_DISCOUNT_TYPES, true)
            ? $this->max_discount_type
            : 'percentage';
    }

    /**
     * السقف الأقصى للخصم **مبلغاً** — أو `null` حين كانت الوحدة نسبة.
     *
     * «لا يزيد الخصم على ثمانين ريالاً» لا تُقال نسبةً: نسبتُها تتبدّل
     * بقيمة كلّ فاتورة. فمن أرادها اختار «مبلغ»، وعندها لا تسري النسبة.
     *
     * والصفر هنا كاللا-شيء: **لا سقف**. وهو خلاف النسبة التي يعني صفرُها
     * «لا خصم البتّة» — والفرق مقصود، فمبلغٌ صفرٌ لم يُكتب لا يجوز أن
     * يمنع الخصم كلَّه.
     */
    public function maxDiscountAmount(): ?float
    {
        if ($this->maxDiscountType() !== 'amount') {
            return null;
        }

        $value = (float) ($this->max_discount_amount ?? 0);

        return $value > 0 ? $value : null;
    }

    /**
     * الرقم المعروض في حقل السقف — الموافق للوحدة المختارة.
     *
     * الشاشة حقلٌ واحدٌ بوحدةٍ تُختار، والقاعدة عمودان. هذه هي القراءة
     * التي تجمعهما، و`SalesInvoiceSettingsRequest::settings()` هي
     * الكتابة التي تفرّقهما.
     */
    public function maxDiscountValue(): ?float
    {
        return $this->maxDiscountType() === 'amount'
            ? (($this->max_discount_amount ?? null) !== null ? (float) $this->max_discount_amount : null)
            : (float) ($this->max_discount_percent ?? 10);
    }

    /**
     * مصدر مستودع البيع — `employee` أو `main`، والافتراض مستودعُ الموظّف.
     *
     * الافتراض هو السلوك الذي كان: من رقّى نظاماً قائماً لا يجد بيعَه قد
     * انتقل إلى مستودعٍ آخر من تلقائه.
     */
    public function warehouseSource(): string
    {
        return in_array($this->warehouse_source, self::WAREHOUSE_SOURCES, true)
            ? $this->warehouse_source
            : 'employee';
    }

    /**
     * أنواعُ الأصناف بلا رصيد التي تظهر للموظّف في بنود مستندات البيع.
     *
     * قائمةُ الموظّف مقصورةٌ على ما له رصيدٌ في مستودعه، والخدمةُ والرقميُّ
     * لا رصيدَ لهما — فكانا يغيبان عن الفاتورة وعرض السعر وأمر البيع
     * والمرتجع والإشعار الدائن بلا خيار. والمستخدمُ بلا سجلّ موظّف (المدير)
     * يرى الأصنافَ كلَّها ولا يمسّه هذا.
     *
     * @return array<int, string>
     */
    public function stocklessTypesForEmployees(): array
    {
        return array_values(array_filter([
            $this->show_service_products ? ProductType::SERVICES->value : null,
            $this->show_digital_products ? ProductType::DIGITAL->value : null,
        ]));
    }

    /** هل البيع من المستودع الرئيسي وحده؟ */
    public function sellsFromMainWarehouse(): bool
    {
        return $this->warehouseSource() === 'main';
    }

    /**
     * الصفّ الوحيد، يُنشأ بالقيم الافتراضية عند أوّل طلب.
     *
     * `firstOrCreate` بلا شرط مقصود: الجدول صفّ واحد بحكم الاستعمال لا
     * بحكم قيد، فأوّل صفّ هو الإعدادات مهما كان معرّفه.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /* البند الحرّ أُسقط بطلب المستخدم: كلّ بندٍ في الفاتورة يقابله منتجٌ
       في الكتالوج. وكانت هنا `freeEntryAllowed()` تقرأ `allow_free_entry`
       مقلوبةً — عنوانُه في الشاشة «إيقاف الإدخال الحرّ» فتفعيلُه منعٌ.

       العمود باقٍ في `sales_invoice_settings` ولم يعد يُقرأ ولا يُكتب:
       إسقاطُه هجرةً يكسر النسخ الاحتياطية القديمة، ولا يُكسَب منه شيء. */

    /** عدد الإعدادات المنطقية المفعَّلة — يُعرَض في بطاقة الإحصاء. */
    public function enabledCount(): int
    {
        return count(array_filter(self::FLAGS, fn (string $flag) => (bool) $this->{$flag}));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
