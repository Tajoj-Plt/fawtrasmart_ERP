<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $fillable = ['employee_id', 'job_title_id', 'job_level_id', 'code', 'description', 'parent_contract_id', 'start_date', 'end_date', 'type_contract', 'duration_unit', 'duration', 'amount', 'join_date', 'probation_end_date', 'contract_date', 'receiving_cycle', 'currency', 'attachments', 'last_alert_sent_at', 'created_by', 'approval_status', 'approved_at', 'approved_by'];

    /*
        Laravel 11 أسقط دعم `protected $dates` نهائياً — كان يُتجاهَل بصمت،
        فتبقى هذه الحقول نصوصاً لا كائنات Carbon، وأولُ `->format()` عليها
        يرمي «Call to a member function format() on string».
        التحويلُ إلى $casts هو البديل المعتمد.
    */

    /*
        الراتب الأساسي `decimal(15,2)` بعد أن كان `integer` يقصّ الكسور.

        والصبُّ صريحٌ لأن المحرّكين لا يتّفقان على نوع المُرجَع: MySQL
        يردّ العمودَ العشري **نصّاً** (`"7500.50"`) وsqlite يردّه عدداً
        عشرياً (`7500.5`). فبلا صبٍّ يختلف ما يراه التشغيلُ عمّا يراه
        الاختبار في النوع لا في القيمة — وهو فرقٌ يظهر في مقارنةٍ صارمة
        ولا يظهر قبلها.

        وهو الصبُّ نفسُه على [SalaryItem::$casts] لعمود المبلغ.
    */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'join_date' => 'date',
        'probation_end_date' => 'date',
        'contract_date' => 'date',
        'amount' => 'decimal:2',
        // ختمُ آخر تذكيرٍ بانتهاء العقد — يقرؤه `contracts:check-expirations`
        'last_alert_sent_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /*
        حالةُ الاعتماد (`contracts.approve`) — عمودُها `approval_status` لا
        `status`: مُحوِّلُ `status` أدناه يحسب «ساري/منتهٍ» من التواريخ.
        والاعتمادُ حالةٌ إدارية لا شرطٌ على قراءة الراتب — مسيرُ الرواتب
        ونهايةُ الخدمة يقرآن العقدَ معتمَداً كان أو مسودّة.
    */
    const APPROVAL_DRAFT = 'draft';

    const APPROVAL_APPROVED = 'approved';

    // ثوابت لأنواع العقود
    const TYPE_PERIOD = 1;

    const TYPE_END_DATE = 2;

    // ثوابت لوحدات المدة
    const DURATION_DAY = 1;

    const DURATION_MONTH = 2;

    const DURATION_YEAR = 3;

    // ثوابت لدورة القبض
    const CYCLE_MONTHLY = 1;

    const CYCLE_WEEKLY = 2;

    const CYCLE_YEARLY = 3;

    const CYCLE_QUARTERLY = 4;

    const CYCLE_ONCE_WEEK = 5;

    // العلاقة مع الموظف
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // العلاقة مع المسمى الوظيفي
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JopTitle::class, 'job_title_id');
    }

    // العلاقة مع المستوى الوظيفي
    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(FunctionalLevels::class, 'job_level_id');
    }

    /*
        «قوالب الرواتب» أُلغيت (2026-09-24): `salary_temp_id` لم يعد يُكتب (أُخرج من
        `$fillable`)، والعمودُ وقيمُه القديمة باقيان. لا يقرأ هذه العلاقةَ المسيرُ ولا
        القسيمةُ ولا نهايةُ الخدمة — العقدُ يحمل نُسخَ بنوده (`salaryItems`).
    */
    public function salaryTemplate(): BelongsTo
    {
        return $this->belongsTo(SalaryTemplate::class, 'salary_temp_id');
    }

    // العلاقة مع العقد الأساسي
    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'parent_contract_id');
    }

    // العقود الفرعية
    public function childContracts()
    {
        return $this->hasMany(Contract::class, 'parent_contract_id');
    }

    public function salaryItems()
    {
        return $this->hasMany(SalaryItem::class, 'contracts_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    /**
     * إجماليات العقد من الأساسي وبنوده — انظر `ContractService::totals()`.
     *
     * تقرأ علاقة `salaryItems` إن كانت محمَّلة، فلا تضيف استعلاماً لكل سطر
     * في القائمة حين تُحمَّل البنود مسبقاً.
     *
     * @return array{basic: float, additions: float, deductions: float, gross: float, net: float}
     */
    public function salaryTotals(): array
    {
        $items = $this->relationLoaded('salaryItems') ? $this->salaryItems : $this->salaryItems()->get();

        return \Modules\HR\Services\ContractService::totals(
            $this->amount !== null ? (float) $this->amount : null,
            $items->where('type', SalaryItem::TYPE_ADDITION),
            $items->where('type', SalaryItem::TYPE_DEDUCTION)
        );
    }
    // Accessors & Mutators

    /**
     * Get the contract status based on dates
     * Returns: 'active' if contract is still valid, 'expired' if ended
     */
    public function getStatusAttribute()
    {
        // إذا لم يكن هناك تاريخ انتهاء، العقد نشط
        if (is_null($this->end_date)) {
            return 'active';
        }

        // إذا كان تاريخ الانتهاء في المستقبل أو اليوم، العقد نشط
        if ($this->end_date >= now()->startOfDay()) {
            return 'active';
        }

        // إذا كان تاريخ الانتهاء في الماضي، العقد منتهي
        return 'expired';
    }

    public function getTypeContractTextAttribute()
    {
        return match ($this->type_contract) {
            self::TYPE_PERIOD => 'مدة محددة',
            self::TYPE_END_DATE => 'تاريخ انتهاء محدد',
            default => 'غير محدد',
        };
    }

    public function getDurationUnitTextAttribute()
    {
        return match ($this->duration_unit) {
            self::DURATION_DAY => 'يوم',
            self::DURATION_MONTH => 'شهر',
            self::DURATION_YEAR => 'سنة',
            default => 'غير محدد',
        };
    }

    public function getReceivingCycleTextAttribute()
    {
        return match ($this->receiving_cycle) {
            self::CYCLE_MONTHLY => 'شهري',
            self::CYCLE_WEEKLY => 'أسبوعي',
            self::CYCLE_YEARLY => 'سنوي',
            self::CYCLE_QUARTERLY => 'ربع سنوي',
            // هكذا تسمّيه شاشتا الإنشاء والتعديل منذ البداية — والقيمة واحدة
            self::CYCLE_ONCE_WEEK => 'مرة كل أسبوعين',
            default => 'غير محدد',
        };
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('end_date')->orWhere('end_date', '>=', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', now());
    }

    // Methods
    public function isActive(): bool
    {
        return is_null($this->end_date) || $this->end_date >= now();
    }

    public function isExpired(): bool
    {
        return ! is_null($this->end_date) && $this->end_date < now();
    }

    public function getDurationText(): string
    {
        // العمود `tinyint` بلا صبّ — فالمقارنةُ الصارمة بالثابت كانت لا تصدق أبداً
        if ((int) $this->type_contract === self::TYPE_END_DATE || ! $this->duration) {
            return $this->end_date ? 'حتى '.$this->end_date->format('Y-m-d') : 'غير محددة';
        }

        return $this->duration.' '.$this->duration_unit_text;
    }
}
