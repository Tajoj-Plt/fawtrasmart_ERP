<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\StampsOwnerContext;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PaymentsProcess extends Model
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
    use StampsOwnerContext;

    /**
     * «مَن سجّل السند» يُختَم بـ**`users.id`** في `employee_id`.
     *
     * عمودُ `employee_id` في هذا الجدول يحمل معرّفَ **المستخدم** لا الموظف:
     * كلُّ كاتبٍ صريحٍ له يكتب `auth()->id()`، وكلُّ قارئٍ — شاشةُ المدفوعات
     * ونطاقُ الرؤية فيها ومنسدلةُ «سجَّلها» وشاشاتُ سندات المشتريات — يقابله
     * بـ`users.id`.
     *
     * لكنّ الختمَ الافتراضيّ في `StampsOwnerContext` يملأ عموداً اسمُه
     * `employee_id` بـ`employees.id`. فكلُّ مسارٍ يترك العمودَ فارغاً — توزيعُ
     * سند القبض على الفواتير، وتسويةُ الإشعار الدائن، وتحصيلُ الشيك — كان
     * يُختَم برقم **موظفٍ** في عمودِ **مستخدم**. والجدولان متسلسلان مستقلّان،
     * فالرقم يقع على مستخدمٍ آخر: سندٌ سجّله محمد (موظف ٢) يظهر باسم مشتاق
     * (مستخدم ٢)، ويظهر لمشتاق في نطاق «سنداتي» ولا يظهر لمحمد.
     *
     * فيُصرَف الختمُ المنطقيّ `user_id` إلى هذا العمود، ويُستثنى ختمُ الموظف.
     * الصفوفُ التي خُتمت قبل ذلك يُصلحها `payments:repair-recorded-by`.
     */
    protected array $ownerContextMap = ['user_id' => 'employee_id'];

    protected array $ownerContextExcept = ['employee_id'];

    /** التاريخُ الحاكم لفترة هذا المستند. */
    public function accountingDateColumn(): string
    {
        return 'payment_date';
    }

    use BelongsToBranch;

    protected $table = 'payments_process';

    protected $primaryKey = 'id';

    public $timestamps = true;

    /* `Payment_method` بحرفه الكبير مقبولٌ أيضاً: هو اسم العمود في القاعدة،
       وكاتبوه بهذا الاسم كانت كتابتُهم تُسقَط بصمت. */
    protected $fillable = ['client_id', 'branch_id', 'purchases_id', 'invoice_id', 'employee_id', 'treasury_id', 'installments_id', 'supplier_id', 'type', 'payment_date', 'amount', 'credit_settled', 'payment_status', 'payment_method', 'Payment_method', 'reference_number', 'receipt_id', 'payment_data', 'notes', 'attachments', 'accounting_period_id'];

    protected $casts = [
        'invoice_id' => 'integer',
        'amount' => 'float',
        // ما غطّاه رصيد المورد من `amount` — النقد الفعليّ هو الفرق بينهما
        'credit_settled' => 'float',
        'payment_date' => 'datetime',
        'employee_id' => 'integer',
        'client_id' => 'integer',
        'purchases_id' => 'integer',
        'treasury_id' => 'integer',
        'payment_status' => 'integer',
        // `payment_method` لا قالبَ له: هو سمةٌ مُشتقّة، انظر `payment_type()`
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * اسمُ مَن سجّل السند — بما هو، لا بما يُظنّ أنه.
     *
     * `employee_id` هنا يحمل **`users.id`** لا `employees.id` (كلُّ كاتبٍ
     * لهذا العمود يكتب `auth()->id()`). وكانت شاشةُ المدفوعات تعرض
     * `employee->name` سطراً صغيراً **تحت اسم العميل** بلا عنوان، فيقرأ
     * القارئ اسماً ثانياً ولا يدري أيَّ اسمٍ يقرأ.
     *
     * الاسمُ يُؤخذ من ملفّ الموظف حين يكون للمستخدم ملفّ — وهو الاسم الذي
     * تعرفه شاشاتُ الموارد البشرية — ويقع على `users.name` وإلّا.
     */
    public function getRecordedByNameAttribute(): ?string
    {
        $user = $this->employee;

        /* حسابُ بوابة العملاء ليس مُسجِّلاً.
         *
         * لا شاشةَ في النظام تُنشئ سنداً باسم عميل: كلُّ كاتبٍ لهذا الجدول
         * يكتب `auth()->id()` من شاشات الإدارة. فمعرّفٌ يقع على حساب بوابة
         * ليس مُسجِّلاً، بل رقمُ موظفٍ قديمٌ وقع على حساب عميلٍ يحمل الرقم
         * نفسه — و`users` و`employees` متسلسلان مستقلّان، فالتصادم فيهما
         * قاعدةٌ لا استثناء. وكانت الشاشة تطبع اسم ذلك العميل في موضع
         * الموظف، فيقرأ المستخدم اسم عميلٍ لا صلة له بالسند.
         *
         * والتمييزُ بـ`users.client_id` لا بعمود `role` النصّي: الأخير
         * مخلَّفٌ قديمٌ لا يُضمَن اتّساقُه (`role_id` هو مصدرُ الحقيقة)،
         * و`client_id` هو نفسُه رابطُ البوّابة.
         */
        if ($user && $user->client_id) {
            $user = null;
        }

        if ($user) {
            return ($user->employee?->full_name ?: null) ?? $user->name;
        }

        // القراءةُ القديمة للعمود: رقمُ موظفٍ لا رقمُ مستخدم
        if ($this->employee_id) {
            return Employee::find($this->employee_id)?->full_name ?: null;
        }

        return null;
    }

    public function purchase_invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchases_id');
    }

    public function treasury()
    {
        return $this->belongsTo(Treasury::class, 'treasury_id');
    }

    /**
     * حسابُ الخزينة في شجرة الحسابات.
     *
     * `payments_process.treasury_id` معرّفُ **حساب** (`accounts.id`) لا
     * معرّفَ صفٍّ في `treasuries`: كلُّ كاتبٍ لهذا العمود يكتب
     * `$treasuryAccount->id`، ومُنتقي الخزينة في شاشة التسجيل يُبنى من
     * شجرة الحسابات. بينما كانت الشاشات تقرؤه عبر `treasury()` — أي في
     * جدول `treasuries` — فيخرج «—» في كلّ سند (معرّفات الحسابات بالآلاف
     * ولا صفَّ خزينةٍ يحمل رقمها)، أو اسمُ خزينةٍ أخرى حين يتصادم الرقمان.
     */
    public function treasuryAccount()
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    /** اسمُ الخزينة للعرض — من الحسابات، ثم من `treasuries` للصفوف القديمة. */
    public function getTreasuryNameAttribute(): ?string
    {
        return $this->treasuryAccount?->name ?: $this->treasury?->name;
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * طريقةُ الدفع من «طرق الدفع».
     *
     * **العمود في القاعدة اسمه `Payment_method` بحرفٍ كبير.** وMySQL لا
     * يفرّق بين حالتَي الحرف في أسماء الأعمدة، فالكتابةُ بـ`payment_method`
     * تصل الصفَّ سليمة — أمّا إلوكنت فيفهرس السماتِ بالاسم كما عاد من
     * الاستعلام حرفاً بحرف. فكان `$payment->payment_method` يساوي `null`
     * **في كلّ صفٍّ من الجدول**، ومعه هذه العلاقةُ الموقوفةُ عليه، ومعهما
     * قالبُ `'payment_method' => 'integer'` الذي لا سمةَ له يعمل عليها.
     *
     * والأثرُ المرئيّ: عمود «طريقة الدفع» في شاشة مدفوعات العملاء وفي سند
     * القبض المطبوع يعرض «—» دائماً، مهما اختار المستخدمُ عند التسجيل.
     *
     * بقيّةُ النظام (تقارير الموارد البشرية، شاشات المشتريات، مُطبِّعُ
     * قوالب المستندات) تقرأ `Payment_method` بحرفه الكبير ولا تتأثّر.
     */
    public function payment_type()
    {
        return $this->belongsTo(PaymentMethod::class, 'Payment_method');
    }

    /** `payment_method` اسمٌ مرادفٌ للعمود — يقرأ ويكتب `Payment_method`. */
    public function getPaymentMethodAttribute()
    {
        $value = $this->attributes['Payment_method'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function setPaymentMethodAttribute($value): void
    {
        $this->attributes['Payment_method'] = $value;
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'reference_number', 'id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function installment()
    {
        return $this->belongsTo(Installment::class, 'installments_id');
    }

    public function purchase()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchases_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
