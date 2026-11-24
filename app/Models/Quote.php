<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * عرض سعر.
 *
 * ── الحالة نصّ لا رقم ──
 *
 * العمود `status` في `quotes` من نوع
 * `enum('Draft','Pending','Approved','Converted_to_Invoice','Cancelled')`.
 * وكان المتحكّم يكتب فيه `1` و`4`، فتقبلهما MySQL على أنّهما فهرسان في
 * التعداد وتخزّن `'Draft'` و`'Converted_to_Invoice'` — ثم تقرأ الشاشة
 * `$quote->status == 1` فتقارن `'Draft'` بـ`1`. وفي PHP 8 هذه المقارنة
 * **خطأ** دائماً (تغيّر قواعد مقارنة النصّ بالعدد)، فكان كل عرضٍ يُعرض
 * «مغلق» مهما كانت حالته الحقيقية.
 *
 * الثوابت أدناه هي القيم المخزَّنة نفسها، و`normalizeStatus()` تقبل ما
 * كُتب سابقاً بالأرقام فلا تحتاج الصفوف القديمة ترحيلاً.
 */
class Quote extends Model
{
    use \App\Traits\BelongsToBranch;
    use HasFactory;

    /**
     * ── نوع المستند ──
     *
     * الجدول يحمل مستندين: عرض السعر وأمر البيع. وهما في البنية واحد —
     * عميلٌ وبنودٌ وأسعارٌ ثمّ تحويلٌ إلى فاتورة — ويفترقان في الدلالة:
     * العرضُ اقتراحٌ يُرسَل، والأمرُ التزامٌ قُبِل. انظر الهجرة
     * `2026_08_27_110000_add_type_to_quotes_for_sales_orders`.
     */
    public const TYPE_QUOTE = 'quote';

    public const TYPE_SALES_ORDER = 'sales_order';

    public const TYPES = [self::TYPE_QUOTE, self::TYPE_SALES_ORDER];

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_CONVERTED = 'Converted_to_Invoice';

    public const STATUS_CANCELLED = 'Cancelled';

    /**
     * انقضت مدّة الصلاحية — يكتبها الأمر `quotations:expire` وحده.
     *
     * غير «ملغى»: الإلغاء قرارٌ يتّخذه أحد، والانتهاء وقتٌ مضى. والفرق
     * يظهر في التقارير: عرضٌ ضاع بالإهمال غير عرضٍ رفضه العميل.
     */
    public const STATUS_EXPIRED = 'Expired';

    /** ترتيب التعداد في القاعدة — به تُترجَم الأرقام المكتوبة سابقاً */
    public const STATUSES = [
        1 => self::STATUS_DRAFT,
        2 => self::STATUS_PENDING,
        3 => self::STATUS_APPROVED,
        4 => self::STATUS_CONVERTED,
        5 => self::STATUS_CANCELLED,
        6 => self::STATUS_EXPIRED,
    ];

    /**
     * الحالات التي ينقلها الانتهاء.
     *
     * المُحوَّل صار فاتورة فلا معنى لانتهائه، والملغى قرارٌ لا يُنسَخ عليه
     * قرارٌ آخر، والمنتهي منتهٍ. وما عداها — مسوّدةٌ وقيد انتظارٍ ومقبول —
     * كلُّها عروضٌ حيّة يقتلها مضيُّ الموعد.
     */
    public const EXPIRABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
    ];

    protected $primaryKey = 'id';

    /**
     * ── ثلاثة حقول أُضيفت، وثلاثة أسماء صُحّحت ──
     *
     * كان المتحكّم يمرّر `quotes_number` و`tax_total` و`shipping_tax`
     * إلى `Quote::create()`. الأوّلان اسمان لا وجود لهما (العمودان
     * `quote_number` و`total_tax`)، والثالث عمودٌ لم يكن مُنشأً. والتعيين
     * الجَماعي يُسقط ما ليس في هذه القائمة **بلا خطأ**، فكان رقم العرض
     * وضريبته لا يُحفظان أصلاً — ولذلك تظهر العروض في الشاشات بمعرّفها
     * الرقمي لا برقمها.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'client_id',
        'branch_id',
        'created_by',
        'quote_date',
        'expiry_date',
        'quote_number',
        /* مسؤولُ المبيعات ومهلةُ السداد.

           العمودُ `employee_id` كان في الجدول ولا في `$fillable`، فلا
           `create()` ولا `update()` يكتبه: يقرؤه `convertToInvoice()` فيجده
           فارغاً أبداً ويسقط إلى مُنشئ التحويل. و`payment_terms` عمودٌ
           جديدٌ نظيرُ `invoices.payment_terms`. */
        'employee_id',
        'payment_terms',
        'subtotal',
        'status',
        'total_discount',
        'due_value',
        'total_tax',
        'shipping_cost',
        'shipping_tax',
        'next_payment',
        'grand_total',
        'currency',
        'notes',
        'discount_type',
        'discount_amount',
        /* الكوبون كما طُبِّق لحظةَ الحفظ — الرمزُ والمبلغُ مجمَّدان لا
           يُعاد حسابُهما، انظر {@see \App\Services\Sales\SalesCouponService} */
        'coupon_id',
        'coupon_code',
        'coupon_discount',
        'tax_type',
        // الفاتورة التي صدرت عن هذا المستند — تُكتب عند التحويل
        'invoice_id',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'expiry_date' => 'date',
        'subtotal' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_tax' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | الحالة
    |--------------------------------------------------------------------------
    */

    /**
     * قيمة الحالة كما يفهمها التعداد — أيّاً كان المكتوب.
     *
     * تقبل النصّ المخزَّن، والرقم الذي كان يُكتب قبل هذا الإصلاح، وتردّ
     * `Draft` لما لا تعرفه بدل أن ترمي: صفٌّ بحالةٍ غريبة يجب أن يُعرَض
     * لا أن يُسقط الشاشة.
     */
    public static function normalizeStatus($value): string
    {
        if (is_numeric($value)) {
            return self::STATUSES[(int) $value] ?? self::STATUS_DRAFT;
        }

        $value = (string) $value;

        return in_array($value, self::STATUSES, true) ? $value : self::STATUS_DRAFT;
    }


    public function getStatusKeyAttribute(): string
    {
        return self::normalizeStatus($this->status);
    }

    public function isConverted(): bool
    {
        return $this->status_key === self::STATUS_CONVERTED;
    }

    /** المستند المُقفل: لا يُعدَّل ولا يُحوَّل مرّةً ثانية */
    public function isLocked(): bool
    {
        return in_array($this->status_key, [self::STATUS_CONVERTED, self::STATUS_CANCELLED], true);
    }

    public function isExpired(): bool
    {
        return $this->status_key === self::STATUS_EXPIRED;
    }

    /**
     * المنتهي **يُعدَّل ولا يُحوَّل**.
     *
     * فلو أُدخل في `isLocked()` لَما استطاع أحدٌ تمديد تاريخه — والتمديد هو
     * العلاج الطبيعي لعرضٍ انقضى: يُفتح، يُمدّ تاريخه، تُعاد حالته. أمّا
     * التحويل إلى فاتورة فممنوع: أسعارٌ انقضت مدّتها لا تصير فاتورة بضغطة،
     * وقد تغيّرت التكلفة من يومها.
     */
    public function blocksConversion(): bool
    {
        return $this->isLocked() || $this->isExpired();
    }

    /**
     * أيستحقّ هذا العرض الانتهاء اليوم؟
     *
     * `$graceDays` مهلةٌ بعد تاريخ الصلاحية: منشأةٌ تقبل التفاوض يوماً أو
     * يومين بعد الموعد لا تريد الحالة تنقلب في اللحظة نفسها.
     */
    public function shouldExpire(int $graceDays = 0): bool
    {
        if (! $this->expiry_date || ! in_array($this->status_key, self::EXPIRABLE_STATUSES, true)) {
            return false;
        }

        return $this->expiry_date->copy()->addDays(max(0, $graceDays))->endOfDay()->isPast();
    }

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    /**
     * الفاتورة التي صدرت عن هذا المستند — تُكتب لحظةَ التحويل.
     *
     * العمودُ كان في الجدول منذ إنشائه ولا يكتبه شيء: الحالة
     * (`Converted_to_Invoice`) وحدها كانت أثرَ التحويل، فلا سبيل من العرض
     * إلى فاتورته إلّا بالبحث في `notes`.
     */
    public function invoice()
    {
        return $this->belongsTo(\App\Models\Invoice::class, 'invoice_id');
    }
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** بنود العرض — في `invoice_items` عبر `quotation_id` */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'quotation_id');
    }

    /**
     * الموظّف المسؤول.
     *
     * مبنيّة على `created_by` — وهو معرّف **مستخدم** لا معرّف موظّف.
     * تُترك كما كانت لأنّ شاشاتٍ قائمة تقرأها، لكنّها علاقةٌ مشكوك فيها:
     * `employees.id` و`users.id` فضاءان مختلفان.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    /**
     * مسؤولُ المبيعات — على `employee_id` كما ينبغي.
     *
     * {@see employee()} أعلاه مبنيّةٌ على `created_by` وهو معرّفُ **مستخدم**
     * لا معرّفُ موظّف، فتردّ موظفاً لا علاقة له بالمستند كلّما اختلف
     * الفضاءان. وهي تُترك كما هي لأنّ شاشاتٍ قائمةً تقرؤها، وتُضاف هذه
     * إلى جانبها بالاسم الذي يصف ما تفعله.
     */
    public function salesPerson()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /*
    |--------------------------------------------------------------------------
    | النطاقات
    |--------------------------------------------------------------------------
    */

    public function scopeStatus($query, $status)
    {
        return $query->where('status', self::normalizeStatus($status));
    }

    /**
     * مستندات نوعٍ بعينه.
     *
     * الصفوف التي سبقت عمود `type` عروضُ أسعار — عبّأتها الهجرة بـ`quote`،
     * لكنّ `whereNull` تبقى احتياطاً لتثبيتٍ رُحِّل جزئياً: عرضُ سعرٍ
     * يختفي من شاشته أسوأ من صفٍّ زائد.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where(function ($scope) use ($type) {
            $scope->where('type', $type);

            if ($type === self::TYPE_QUOTE) {
                $scope->orWhereNull('type');
            }
        });
    }

    public function isSalesOrder(): bool
    {
        return $this->type === self::TYPE_SALES_ORDER;
    }
}
