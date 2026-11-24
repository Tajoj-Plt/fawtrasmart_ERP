<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\RecursAsVoucher;
use App\Models\Concerns\StampsOwnerContext;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
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

    // «سند مكرَّر»: السلسلة ونسخها وموعد التالية — انظر السِّمة
    use RecursAsVoucher;

    // اسم الجدول
    protected $table = 'expenses';

    // الحقول القابلة للتعبئة
    protected $fillable = ['id', 'code', 'amount', 'description', 'date', 'unit_id', 'created_by', 'expenses_category_id',
        'supplier_id', 'seller', 'treasury_id', 'account_id', 'branch_id', 'is_recurring', 'recurring_frequency', 'end_date',
        'tax1', 'tax2', 'tax1_amount', 'tax1_account_id', 'tax2_amount', 'tax2_account_id', 'attachments', 'cost_centers_enabled', 'created_at', 'updated_at', 'currency', 'payment_method_id', 'total_amount', 'is_taxable',
        'accounting_period_id', 'reversed_at', 'reversal_reason',
    ];

    protected $casts = [
        'reversed_at' => 'datetime',
    ];

    /**
     * السنداتُ غيرُ المعكوسة — أساسُ كلِّ إحصاءٍ وتقرير.
     *
     * سندُ صرف الراتب المعكوس يبقى في الجدول بكامل مبلغه عن قصد: المستندُ
     * لا يُمحى، و`PayrollReversalService` يرحّل قيداً عكسياً فيصحّ الدفتر.
     * لكنّ التقاريرَ كانت تستعلم الجدولَ بلا استثناء، فالعكسُ ثمّ إعادةُ
     * الصرف — وهو المسارُ المقصود لتصحيح خطأ — يُخلِّف صفَّي مصروفٍ لراتبٍ
     * واحد: فيضاعف التقريرُ بينما الدفترُ صحيح.
     *
     * فيُستثنى المعكوسُ من الجمع ويبقى في القائمة موسوماً.
     */
    public function scopeNotReversed($query)
    {
        return $query->whereNull('reversed_at');
    }

    /** هل عُكس هذا السند؟ */
    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    // العلاقات
    public function expenses_category()
    {
        return $this->belongsTo(ExpensesCategory::class, 'expenses_category_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * رقم سند الصرف التالي من «ترقيم المستندات» مع تخطّي المحجوز.
     *
     * كان يقرأ آخر صفّ بـ`id` ويزيد لاحقته: فيصطدم بـ`EXP-0008` إن وُجد
     * السند برقم أعلى أو إن تقدّم عدّاد الشاشة دون هذا المسار (رواتب/
     * أهداف). شاشة المصروفات تستخدم `next_document_number('expense')`
     * منذ زمن — وهذا يوحّد المسارين.
     */
    public static function generateCode(): string
    {
        if (function_exists('next_document_number')) {
            $code = next_document_number('expense');

            $safety = 0;
            while ($safety < 1000 && self::where('code', $code)->exists()) {
                if (! preg_match('/^(.*?)(\d+)$/', $code, $m)) {
                    $code .= '-1';
                } else {
                    $next = (string) ((int) $m[2] + 1);
                    $code = $m[1].str_pad($next, strlen($m[2]), '0', STR_PAD_LEFT);
                }
                $safety++;
            }

            return $code;
        }

        // مسارٌ احتياطي بلا خدمة الترقيم (اختبارات/تثبيت ناقص)
        $lastCode = self::orderByDesc('id')->value('code');
        if (! $lastCode || ! preg_match('/^(.*?)(\d+)$/', $lastCode, $m)) {
            return 'EXP-0001';
        }

        $next = (string) ((int) $m[2] + 1);

        return $m[1].str_pad($next, strlen($m[2]), '0', STR_PAD_LEFT);
    }

    public function treasury()
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    // public function employee(){
    //     return $this->belongsTo(Employee::class, 'employee_id');
    // }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function Supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
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
