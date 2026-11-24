<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\RecursAsVoucher;
use App\Models\Concerns\StampsOwnerContext;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    /*
        الفترةُ المحاسبية تُختَم لحظةَ الإنشاء من تاريخ المستند، فتثبت معه
        ما بقي — بدل أن تُستنتَج بمطابقة التاريخ في كلّ شاشة.
    */
    use BelongsToAccountingPeriod;

    /**
     * فرعُ المستند: يُختَم عند الإنشاء (`StampsOwnerContext`) ويُصفّى عند
     * القراءة (`BelongsToBranch::scopeForBranch`).
     *
     * كان العمود موجوداً في الجدول وفارغاً في كل صفّ — لا كاتبَ له ولا قارئ.
     * فتبديلُ الفرع في الشريط العلوي لا يغيّر شيئاً في شاشات الحسابات: يقف
     * المستخدم على فرعٍ ويرى قيودَ الفروع كلّها. والصفوفُ السابقة تبقى ظاهرةً
     * في كل الفروع (`orWhereNull`) فلا يختفي تاريخٌ عند التشغيل.
     */
    use BelongsToBranch, StampsOwnerContext;

    use HasDocumentCurrency;

    // «سند مكرَّر»: السلسلة ونسخها وموعد التالية — انظر السِّمة
    use RecursAsVoucher;

    protected $fillable = ['id', 'code', 'amount', 'discount_amount', 'description', 'date', 'incomes_category_id', 'seller', 'client_id', 'account_id', 'is_recurring', 'recurring_frequency', 'end_date', 'tax1', 'tax2', 'tax1_amount', 'tax2_amount', 'tax1_account_id', 'tax2_account_id', 'total_amount', 'attachments', 'cost_centers_enabled', 'treasury_id', 'branch_id', 'created_at', 'updated_at', 'created_by', 'status', 'notes', 'cancelled_at', 'cancelled_by', 'currency', 'payment_method_id', 'is_taxable', 'accounting_period_id'];

    // العلاقة مع الموظف
    public function incomes_category()
    {
        return $this->belongsTo(ReceiptCategory::class, 'incomes_category_id');
    }

    // العلاقة مع الخزينة
    public function treasury()
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    // العلاقة مع الحساب
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    // العلاقة مع العميل
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // العلاقة مع المستخدم الذي ألغى السند
    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Scope للبحث عن السندات حسب الحالة
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Scope للسندات النشطة فقط
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    // Scope للسندات الملغاة فقط
    public function scopeCancelled($query)
    {
        return $query->where('status', 0);
    }

    // Scope للبحث عن السندات في فترة معينة
    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    // دالة لإنشاء رقم سند تلقائي
    public static function generateCode()
    {
        // جلب آخر كود مستخدم
        $lastCode = self::orderBy('id', 'desc')->value('code');

        // إذا لم يكن هناك كود، نبدأ من 1
        if (! $lastCode) {
            return 'REC-0001';
        }

        // استخراج الرقم من الكود (بعد REC-)
        $number = intval(substr($lastCode, 4)) + 1;

        // إضافة الأصفار لجعل الرقم مكون من 4 خانات
        $newCode = 'REC-'.str_pad($number, 4, '0', STR_PAD_LEFT);

        return $newCode;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /** طريقة الدفع كما عُرّفت في شاشة «طرق الدفع» بالإعدادات */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /*
        الضريبةُ المختارة — `tax1`/`tax2` تحملان **معرّف** الضريبة لا نسبتَها
        (والعمودُ `tinyint` فلا يسع كسراً أصلاً). والنسبةُ تُقرأ من السجلّ
        المرتبط، والمبلغُ المحسوب مخزَّنٌ في `tax1_amount`.
    */
    public function tax1Setting()
    {
        return $this->belongsTo(TaxSitting::class, 'tax1');
    }

    public function tax2Setting()
    {
        return $this->belongsTo(TaxSitting::class, 'tax2');
    }
}
