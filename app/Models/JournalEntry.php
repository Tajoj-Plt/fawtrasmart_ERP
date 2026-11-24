<?php

namespace App\Models;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class JournalEntry extends Model
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

    use HasDocumentCurrency, HasFactory;

    protected $fillable = [
        'reference_number',
        'date',
        'description',
        'status',
        'is_reversed',
        'currency',
        'attachment',
        'client_id',
        'employee_id',
        'purchase_invoice_id',
        'invoice_id',
        'salary_id',
        'created_by_employee',
        'approved_by_employee',
        'reference_type',
        'branch_id',
        'is_posted',
        'linking_type',
        'batch_reference',
        'reference_id',
        'is_taxable',

        'accounting_period_id',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => 'integer',
        'is_reversed' => 'boolean',
        'is_posted' => 'integer',
        'reference_type' => ReferenceType::class,
        'linking_type' => LinkingType::class,
        'is_taxable' => 'boolean',
    ];

    // Automatically set branch_id based on authenticated user
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($journalEntry) {
            if (empty($journalEntry->branch_id) && Auth::check()) {
                $journalEntry->branch_id = Auth::user()->branch_id;
            }

            /*
                رقم المرجع حين لا يُمرَّر.

                **ما كان**

                `generateReferenceNumber()` تبني الرقم بصيغة
                `TYPE-YYYY-0001` **مكتوبةً في هذا الموديل**: البادئة كود
                النوع، والسنة ميلادية، وأربع خانات ثابتة. فما يُضبط في
                شاشة «ترقيم المستندات» — البادئة وعدد الخانات والتصفير
                الدوري — لا أثر له على القيود المولَّدة من هنا.

                **ما صار**

                القيد اليدوي يأخذ رقمه من القسم `entry` في الإعدادات.
                وبقيّة الأنواع تبقى على المولّد القديم: أرقامها مرتبطة
                بمستنداتها الأصلية ولها أقسامها هي، ولا قسم في الشاشة
                يقابل «قيدُ فاتورةٍ» أو «قيدُ إذنِ مخزن».
            */
            if (empty($journalEntry->reference_number) && ! empty($journalEntry->reference_type)) {
                $type = $journalEntry->reference_type instanceof ReferenceType
                    ? $journalEntry->reference_type->value
                    : $journalEntry->reference_type;

                $journalEntry->reference_number = $type === ReferenceType::JOURNAL_VOUCHER->value
                    ? next_document_number('entry')
                    : static::generateReferenceNumber($journalEntry->reference_type);
            }
        });

    }

    /**
     * Generate automatic reference number based on reference type
     * توليد رقم مرجعي تلقائي حسب نوع المرجع
     *
     * @param  string|ReferenceType  $referenceType
     */
    public static function generateReferenceNumber($referenceType): string
    {
        // الحصول على كود نوع المرجع
        $typeCode = $referenceType instanceof ReferenceType
            ? $referenceType->value
            : $referenceType;

        // الحصول على السنة الحالية
        $year = date('Y');

        // البحث عن آخر رقم مرجعي لهذا النوع في هذه السنة
        $lastEntry = static::where('reference_type', $typeCode)
            ->where('reference_number', 'like', $typeCode.'-'.$year.'-%')
            ->orderByRaw('CAST(SUBSTRING_INDEX(reference_number, "-", -1) AS UNSIGNED) DESC')
            ->first();

        // استخراج الرقم التسلسلي
        if ($lastEntry && $lastEntry->reference_number) {
            $parts = explode('-', $lastEntry->reference_number);
            $lastNumber = (int) end($parts);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // تنسيق الرقم بأصفار بادئة (5 أرقام)
        $formattedNumber = str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

        // إنشاء رقم المرجع: TYPE-YEAR-XXXXX
        return $typeCode.'-'.$year.'-'.$formattedNumber;
    }

    /**
     * Get next reference number preview (for display purposes)
     * معاينة الرقم المرجعي التالي
     */
    public static function getNextReferenceNumber(string $referenceType): string
    {
        return static::generateReferenceNumber($referenceType);
    }

    /**
     * Get reference type details
     *
     * @param  string|null  $type
     * @return array|null
     */
    public static function getReferenceTypeDetails($type)
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof ReferenceType) {
            $refType = $type;
        } else {
            $refType = ReferenceType::fromValue($type);
        }

        if ($refType) {
            return [
                'code' => $refType->value,
                'name' => $refType->getName(),
                'description' => $refType->getDescription(),
            ];
        }

        return null;
    }

    /**
     * Get all reference types
     *
     * @return array
     */
    public static function getReferenceTypes()
    {
        return ReferenceType::getAllWithDetails();
    }

    /**
     * Get reference type name
     *
     * @param  string|null  $type
     * @return string|null
     */
    public static function getReferenceTypeName($type)
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof ReferenceType) {
            return $type->getName();
        }

        $refType = ReferenceType::fromValue($type);

        return $refType ? $refType->getName() : null;
    }

    /**
     * Get the reference type name for this journal entry
     *
     * @return string
     */
    public function getReferenceTypeNameAttribute()
    {
        if ($this->reference_type === null) {
            return 'غير محدد';
        }

        if ($this->reference_type instanceof ReferenceType) {
            return $this->reference_type->getName();
        }

        $refType = ReferenceType::fromValue($this->reference_type);

        return $refType ? $refType->getName() : 'غير محدد';
    }

    // العلاقة مع التفاصيل
    public function details()
    {
        return $this->hasMany(JournalEntryDetail::class, 'journal_entry_id');
    }

    // العلاقة مع العميل
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // العلاقة مع الموظف
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // العلاقة مع الفاتورة
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // العلاقة مع فاتورة الشراء
    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /**
     * Relationship with WarehousePermits
     */
    public function warehousePermits()
    {
        return $this->belongsTo(WarehousePermits::class, 'reference_id');
    }

    public function payment()
    {
        return $this->hasOne(PaymentsProcess::class);
    }

    // العلاقة مع مركز التكلفة - تم نقلها إلى JournalEntryDetail
    // يمكن الوصول لمراكز التكلفة عبر التفاصيل: $entry->details->pluck('costCenter')
    public function costCenters()
    {
        return $this->hasManyThrough(
            CostCenter::class,
            JournalEntryDetail::class,
            'journal_entry_id', // Foreign key on journal_entry_details
            'id', // Foreign key on cost_centers
            'id', // Local key on journal_entries
            'cost_center_id' // Local key on journal_entry_details
        );
    }

    // العلاقة مع الموظف الذي أنشأ القيد
    public function createdByEmployee()
    {
        return $this->belongsTo(User::class, 'created_by_employee');
    }

    // العلاقة مع الموظف الذي اعتمد القيد
    public function approvedByEmployee()
    {
        return $this->belongsTo(User::class, 'approved_by_employee');
    }

    // العلاقة مع المدفوعات
    public function payments()
    {
        return $this->hasMany(PaymentsProcess::class, 'reference_number', 'id');
    }

    // العلاقة مع الحسابات من خلال تفاصيل القيد
    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'journal_entry_details', 'journal_entry_id', 'account_id');
    }

    // العلاقة مع الحساب لقيد الرصيد الافتتاحي
    public function openingBalanceAccount()
    {
        return $this->belongsTo(Account::class, 'reference_number', 'id');
    }

    // العلاقة مع الفرع الرئيسي (فرع واحد)
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // العلاقة مع الفروع الموزع عليها القيد (عدة فروع)
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'journal_entry_branches', 'journal_entry_id', 'branch_id')
            ->withPivot('allocation_amount', 'allocation_percentage')
            ->withTimestamps();
    }

    // العلاقة مع الفواتير المرتبطة
    public function linkedInvoices()
    {
        return $this->belongsToMany(Invoice::class, 'journal_entry_invoices', 'entry_id', 'invoice_id')
            ->withPivot('allocation_amount', 'allocation_percentage');
    }

    // دالة مساعدة لحالة القيد
    public function getStatusTextAttribute()
    {
        return match ($this->status) {
            0 => 'معلق',
            1 => 'معتمد',
            2 => 'مرفوض',
            default => 'غير معروف'
        };
    }

    // دالة للتحقق من توازن القيد
    public function isBalanced()
    {
        $totalDebit = $this->details()->sum('debit');
        $totalCredit = $this->details()->sum('credit');

        return $totalDebit == $totalCredit;
    }

    /**
     * Get total debit amount for this journal entry
     *
     * @return float
     */
    public function getTotalDebit()
    {
        return $this->details()->sum('debit');
    }

    /**
     * Get total credit amount for this journal entry
     *
     * @return float
     */
    public function getTotalCredit()
    {
        return $this->details()->sum('credit');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'client_id');
    }

    /**
     * Scope للحصول على القيود الخاصة بحساب معين
     * Get journal entries for a specific account
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->whereHas('details', function ($q) use ($accountId) {
            $q->where('account_id', $accountId);
        });
    }

    /**
     * Scope للحصول على القيود حسب الفترة الزمنية
     * Get journal entries within date range
     */
    public function scopeDateRange($query, $fromDate, $toDate)
    {
        if ($fromDate && $toDate) {
            return $query->whereBetween('date', [$fromDate, $toDate]);
        }

        return $query;
    }

    /*
        `scopeForBranch` محذوفةٌ من هنا عمداً — تأتي من `BelongsToBranch`.

        كانت نسخةً خاصّة بالصنف، ودالّةُ الصنف تغلب دالّةَ السِّمة، فبقيت
        هي العاملة: `where('branch_id', $id)` صارمة بلا `orWhereNull`.
        وعمودُ الفرع فارغٌ في كل قيدٍ قائم (لم يكن أحدٌ يكتبه)، فأيُّ فرعٍ
        يُختار يُفرغ شاشة القيود تماماً. ونسخةُ السِّمة — وهي عقدُ المبيعات
        والمشتريات نفسه — تُبقي القيود السابقة ظاهرةً في كل الفروع.
    */

    /**
     * Get journal entries with account details for ledger
     * دفتر الأستاذ - جلب القيود مع تفاصيل الحساب
     */
    public static function getAccountLedger($accountId, $fromDate = null, $toDate = null, $branchId = null)
    {
        return static::with(['details' => function ($query) use ($accountId) {
            $query->where('account_id', $accountId);
        }, 'details.costCenter', 'client', 'employee', 'branch'])
            ->forAccount($accountId)
            ->dateRange($fromDate, $toDate)
            ->forBranch($branchId)
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');
    }
}
