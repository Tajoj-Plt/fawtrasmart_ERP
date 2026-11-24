<?php

namespace App\Models;

use App\Http\Requests\AttendanceSheetsRequest;
use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Department;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    /*
        الأرشفةُ بدل الحذف — وليست ترفاً.

        جدولُ الموظفين تشير إليه إحدى وثلاثون علاقة، **خمسٌ وعشرون منها
        `ON DELETE CASCADE`**: فحذفٌ صلبٌ لموظفٍ واحد يمحو عقودَه وخصمياتِه
        وسجلَّ حضوره وتسوياتِ أهدافه ونقاطَه. وفيها `direct_manager_id` —
        **فحذفُ مديرٍ يحذف شجرةَ مرؤوسيه كلَّها**.

        و`SoftDeletes` يحوّل `delete()` إلى تحديثٍ لعمود `deleted_at`، فلا
        يُطلق شلّالاً واحداً.
    */
    use HasFactory, SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        # معلومات الموظف
        'first_name',
        'middle_name',
        'nickname',
        'employee_photo',
        'notes',
        'email',
        // رقمُ الموظف من «الترقيم المتسلسل» — يُولَّد في `store()` لا يُرسَل من النموذج
        'employee_code',
        'employee_type',
        'status',
        'allow_system_access',
        'send_credentials',
        'language',
        'Job_role_id',
        'access_branches_id',
        #معلومات شخصية
        'date_of_birth',
        'gender',
        'nationality_status',
        'country',
        # التأمينات الاجتماعية — `gosi_subject`: فارغ = حسب حالة المواطنة، 1 = خاضع، 0 = معفى
        'gosi_subject',
        'gosi_number',
        'gosi_start_date',
        #معلومات تواصل
        'mobile_number',
        'phone_number',
        'personal_email',
        #الحساب البنكي — يقرؤه التحويل البنكي حين يُبنى
        'bank_name',
        'bank_account_holder',
        'iban',
        #جهة الطوارئ
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        #العنوان الحالي
        'current_address_1',
        'current_address_2',
        'city',
        'region',
        'postal_code',
        #معلومات وظيفة
        'job_title_id',
        'department_id',
        'job_level_id',
        'job_type_id',
        'branch_id',
        'direct_manager_id',
        'hire_date',
        # إنهاء الخدمة
        'termination_date',
        'last_working_day',
        'termination_reason',
        'shift_id',
        # بطاقةُ الحضور وقناتُه
        'barcode',
        'barcode_enabled',
        'attendance_channel',
        'attendance_geofence_id',
        'custom_financial_month',
        'custom_financial_day',
        'group_id',
        'created_by',
        'daily_qty_limit',
        'credit_limit',
        'salary_payable_account_id',
        'employee_advance_account_id',
    ];

    protected $casts = [
        'barcode_enabled' => 'boolean',
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'last_working_day' => 'date',
        /*
            الحالةُ تُصَبّ إلى [\App\Enums\EmploymentStatus] — والصبُّ آمنٌ
            على البيانات القائمة: `tryFrom` يردّ `null` لما هو خارج المجال
            (`0` الذي كتبه التبديلُ المعطوب سابقاً)، ولارافل يمرّره كما هو.
            فيُقرأ بـ`employmentStatus()` أدناه لا بالصبّ مباشرةً.
        */
    ];

    /**
     * حالةُ الموظف كنوعٍ لا كرقم.
     *
     * وما هو خارج المجال — `0` الذي كتبه التبديلُ المعطوب سابقاً — يُقرأ
     * «غير نشط»، وهو ما يفعله كلُّ فحصٍ في النظام أصلاً.
     */
    public function employmentStatus(): EmploymentStatus
    {
        return EmploymentStatus::fromValue($this->status);
    }

    /**
     * أعلى رأس العمل؟
     *
     * **الشيفرةُ الجديدة تسأل هذه لا `status == 1`.** والفحوصُ القائمة
     * (`in_array($employee->status, [1, '1', 'active'])` في مسار الصرف
     * والأهداف) تبقى صحيحةً كما هي: كلُّ حالةٍ غيرِ النشط ليست `1`.
     */
    public function isActive(): bool
    {
        return $this->employmentStatus()->isActive();
    }

    /** أانتهت خدمتُه — استقالةً أو إنهاءً؟ */
    public function hasEndedService(): bool
    {
        return $this->employmentStatus()->isEnded();
    }

    /**
     * الحقولُ التي لا تُعرَض إلا بصلاحية `employees.sensitive`.
     *
     * البياناتُ البنكية بياناتُ دفع: من يطّلع على ملفّ الموظف ليس بالضرورة
     * من يجوز له رؤيةُ حسابه. وهي قائمةٌ واحدة يقرؤها المتحكّمُ ليُسقطها
     * **قبل** بلوغ القالب، فلا يُترك المنعُ لإخفاء كتلةٍ في الشاشة وحده.
     *
     * @return array<int, string>
     */
    public static function sensitiveAttributes(): array
    {
        return ['bank_name', 'bank_account_holder', 'iban'];
    }

    /**
     * الآيبان مقنَّعاً — آخرُ أربعة أرقامٍ وحدها.
     *
     * تكفي للتأكّد من أن الحساب هو المقصود، ولا تكفي لاستعماله.
     */
    public function maskedIban(): ?string
    {
        $iban = trim((string) $this->iban);

        if ($iban === '') {
            return null;
        }

        return mb_strlen($iban) <= 4
            ? str_repeat('•', mb_strlen($iban))
            : str_repeat('•', 4).' '.mb_substr($iban, -4);
    }

    /**
     * الموظفون على رأس العمل — النشطون ومن هم تحت التجربة.
     *
     * كان خمسةَ عشرَ موضعاً يكتب `Employee::where('status', 1)` بيده، فلا
     * موضعَ واحد يقول «من على رأس العمل؟». وإضافةُ «تحت التجربة» كانت
     * تُسقطهم من كل قائمةٍ منها.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', EmploymentStatus::activeValues());
    }

    /** من انتهت خدمتُهم — استقالةً أو إنهاءً. */
    public function scopeEndedService($query)
    {
        return $query->whereIn(
            'status',
            array_map(fn (EmploymentStatus $s) => $s->value, EmploymentStatus::ending())
        );
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
    public function user()
    {
        return $this->hasOne(User::class);
    }


    public function job_role()
    {
        return $this->belongsTo(Role::class, 'Job_role_id');
    }

    public function job_level()
    {
        return $this->belongsTo(FunctionalLevels::class, 'job_level_id');
    }

    public function job_type()
    {
        return $this->belongsTo(TypesJobs::class, 'job_type_id');
    }

    public function job_title()
    {
        return $this->belongsTo(JopTitle::class, 'job_title_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function direct_manager()
    {
        return $this->belongsTo(Employee::class, 'direct_manager_id');
    }

    /**
     * الموظفون التابعون مباشرة (الأبناء) لهذا الموظف.
     */
    public function children()
    {
        return $this->hasMany(Employee::class, 'direct_manager_id');
    }

    /**
     * تحميل شجرة الأبناء بشكل تكراري مع المسمى الوظيفي.
     */
    public function childrenRecursive()
    {
        return $this->children()->with(['childrenRecursive', 'job_title']);
    }

    /**
     * جلب كل المعرفات الفرعية (الأحفاد) لهذا الموظف لمنع الحلقات في اختيار المدير.
     */
    public function getDescendantIds(): array
    {
        $ids = [];
        $stack = $this->children()->pluck('id')->all();
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (in_array($current, $ids, true)) {
                continue;
            }
            $ids[] = $current;
            $childIds = self::where('direct_manager_id', $current)->pluck('id')->all();
            foreach ($childIds as $cid) {
                if (!in_array($cid, $ids, true)) {
                    $stack[] = $cid;
                }
            }
        }
        return $ids;
    }

    /**
     * عنوانُ صورة الموظف — أو `null` إن لم تكن له صورةٌ أو فُقد ملفُّها.
     *
     * ══════════ لماذا على الموديل؟ ══════════
     *
     * لأن المسار كان يُكتب بيده في كل شاشةٍ على حدة، فاختلف:
     * الرفعُ يذهب إلى `assets/uploads/employee/` (انظر
     * `EmployeeController::store/update`)، وأكثرُ الشاشات تقرأ من هناك —
     * لكنّ **واجهة جهاز الفرع** و**شاشة التسجيل الميداني** كانتا تقرآن
     * من `storage/`، وهو وصلةٌ إلى `storage/app/public`: مجلّدٌ آخر لا
     * توجد فيه صورةُ موظفٍ واحدة. فالنتيجةُ صورةٌ مكسورةٌ في «معلومات
     * الموظف» عند كل مسحة.
     *
     * والمسارُ المكتوبُ بيده يتفرّق حتماً؛ فمن هنا وحده يُقرأ.
     *
     * ══════════ ولماذا يُفحَص وجودُ الملفّ؟ ══════════
     *
     * لأن الصفَّ قد يحمل اسمَ ملفٍّ حُذف من القرص — نقلُ استضافةٍ أو
     * تنظيفُ مجلّد. وإرجاعُ عنوانٍ لملفٍّ غير موجودٍ يُخرج أيقونةَ «صورة
     * معطوبة»، وهي على بطاقةٍ تُطبَع ورقةٌ ذاهبةٌ سُدى. و`null` تُتيح
     * للواجهة أن تعرض البديلَ المقصود.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->employee_photo) {
            return null;
        }

        $relative = 'assets/uploads/employee/'.$this->employee_photo;

        foreach ([public_path($relative), base_path($relative)] as $candidate) {
            if (is_file($candidate)) {
                return asset($relative);
            }
        }

        return null;
    }

    /*
     * طريقة للحصول على الاسم الكامل للموظف.
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->nickname}");
    }
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_employee', 'employee_id', 'client_id')->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Region_groub::class, 'employee_group')
            ->withPivot('expires_at')
            ->withTimestamps();
    }
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
    public function shift()
    {
        return $this->hasMany(AttendanceSheetsEmployees::class, 'employee_id');
    }
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function attendanceConfig()
    {
        return $this->hasOne(EmployeeAttendanceConfig::class, 'employee_id');
    }

    /** أجهزةُ الفرع التي يُقبل عليها تسجيلُه — لا صفوف = أيُّ جهاز */
    public function attendanceKiosks(): BelongsToMany
    {
        return $this->belongsToMany(AttendanceKiosk::class, 'attendance_kiosk_employee', 'employee_id', 'kiosk_id')->withTimestamps();
    }

    public function attendanceDays()
    {
        return $this->hasMany(AttendanceDays::class, 'employee_id');
    }

    /**
     * العلاقة مع العقود
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class, 'employee_id');
    }

    /**
     * الحصول على العقد النشط الحالي للموظف
     */
    public function activeContract()
    {
        return $this->hasOne(Contract::class, 'employee_id')
            ->where('start_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->orderBy('start_date', 'desc');
    }

    /**
     * العلاقة مع السلف
     */
    public function salaryAdvances()
    {
        return $this->hasMany(SalaryAdvance::class, 'employee_id');
    }

    /**
     * كل خصميات الموظف.
     */
    public function deductions()
    {
        return $this->hasMany(Deduction::class, 'employee_id');
    }

    /**
     * الخصميات المعتمدة وغير المسوّاة (مرشحة للخصم من الراتب).
     */
    public function unsettledDeductions()
    {
        return $this->hasMany(Deduction::class, 'employee_id')->unsettled();
    }

    /**
     * إجمالي المتبقي على الموظف من خصميات معتمدة لم تُسوَّ بعد.
     */
    public function getPendingDeductionsTotal(): float
    {
        return (float) $this->deductions()
            ->unsettled()
            ->selectRaw('SUM(amount - settled_amount) as total')
            ->value('total');
    }

    /**
     * العلاقة مع قسائم الرواتب
     */
    public function salarySlips()
    {
        return $this->hasMany(SalarySlip::class, 'employee_id');
    }

    public function endOfServiceBenefits()
    {
        return $this->hasMany(EndOfServiceBenefit::class);
    }

    /**
     * العلاقة مع الخزينة المرتبطة بالموظف
     */
    public function treasuryEmployee()
    {
        return $this->hasOne(TreasuryEmployee::class, 'employee_id');
    }

    /**
     * مرفقات الموظف (صورة، هوية، أي ملف إضافي)
     */
    public function attachments()
    {
        return $this->hasMany(EmployeeAttachment::class, 'employee_id')->orderByDesc('id');
    }

    /**
     * حساب الرواتب المستحقة للموظف (شجرة الخصوم).
     */
    public function salaryPayableAccount()
    {
        return $this->belongsTo(Account::class, 'salary_payable_account_id');
    }

    /**
     * حساب سلف الموظف (شجرة الأصول).
     */
    public function advanceAccount()
    {
        return $this->belongsTo(Account::class, 'employee_advance_account_id');
    }

    // ==================== نظام السلف المحاسبي ====================

    /**
     * هل الموظف يملك حساب سلف نشط في شجرة الحسابات؟
     */
    public function hasAdvanceAccount(): bool
    {
        if (empty($this->employee_advance_account_id)) {
            return false;
        }
        return Account::whereKey($this->employee_advance_account_id)->exists();
    }

    /**
     * إرجاع حساب السلف الخاص بالموظف، أو رمي استثناء واضح إن لم يكن موجودًا.
     * يُستخدم في كل عمليات السلف لضمان عدم استعمال حساب عام.
     */
    public function getAdvanceAccountOrFail(): Account
    {
        $name = $this->getDisplayNameForError();

        if (empty($this->employee_advance_account_id)) {
            throw new \RuntimeException(
                "الموظف \"{$name}\" لا يملك حساب سلف في شجرة الحسابات. " .
                "افتح بطاقة الموظف ثم اضغط حفظ لإنشاء/ربط حساب السلف الخاص به قبل تنفيذ أي عملية."
            );
        }

        $account = Account::find($this->employee_advance_account_id);
        if (!$account) {
            throw new \RuntimeException(
                "حساب السلف المرتبط بالموظف \"{$name}\" (ID: {$this->employee_advance_account_id}) " .
                "غير موجود في شجرة الحسابات. تم حذفه أو لم يُنشأ بشكل صحيح."
            );
        }

        return $account;
    }

    /**
     * الرصيد الحالي لحساب سلف الموظف — محسوب ديناميكياً من القيود المحاسبية.
     * (مدين - دائن) لأن حساب السلف من الأصول.
     */
    public function getAdvanceAccountBalance(): float
    {
        if (empty($this->employee_advance_account_id)) {
            return 0.0;
        }

        $debit = (float) \App\Models\JournalEntryDetail::where('account_id', $this->employee_advance_account_id)->sum('debit');
        $credit = (float) \App\Models\JournalEntryDetail::where('account_id', $this->employee_advance_account_id)->sum('credit');

        return $debit - $credit;
    }

    /**
     * إجمالي ما صُرف للموظف من سلف (مجموع الجانب المدين على حساب السلف).
     */
    public function getTotalAdvancesIssued(): float
    {
        if (empty($this->employee_advance_account_id)) {
            return 0.0;
        }
        return (float) \App\Models\JournalEntryDetail::where('account_id', $this->employee_advance_account_id)->sum('debit');
    }

    /**
     * إجمالي ما سدّده الموظف من سلف (مجموع الجانب الدائن على حساب السلف).
     */
    public function getTotalAdvancesSettled(): float
    {
        if (empty($this->employee_advance_account_id)) {
            return 0.0;
        }
        return (float) \App\Models\JournalEntryDetail::where('account_id', $this->employee_advance_account_id)->sum('credit');
    }

    /**
     * ملخص سلف الموظف في مصفوفة جاهزة للعرض.
     */
    public function getAdvanceSummary(): array
    {
        $issued   = $this->getTotalAdvancesIssued();
        $settled  = $this->getTotalAdvancesSettled();
        $balance  = $issued - $settled;

        return [
            'issued'      => $issued,
            'settled'     => $settled,
            'remaining'   => $balance,
            'has_account' => $this->hasAdvanceAccount(),
            'account_id'  => $this->employee_advance_account_id,
        ];
    }

    private function getDisplayNameForError(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->middle_name ?? '') . ' ' . ($this->nickname ?? ''))
            ?: ('#' . $this->id);
    }

    // ==================== علاقات نظام النقاط ====================

    /**
     * العلاقة مع معاملات النقاط
     */
    public function pointsTransactions()
    {
        return $this->hasMany(\App\Models\Ecosystem\PointsTransaction::class, 'employee_id');
    }
}
