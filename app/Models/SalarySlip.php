<?php

namespace App\Models;

use Carbon\Carbon;
use App\Support\PayrollSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * قسيمة راتب = اتفاق راتب مستمر للموظف.
 *
 * كل قسيمة تولّد عدة payroll_runs (واحد لكل فترة صرف — شهري عادةً).
 * لا يتم الصرف هنا — الصرف في PayrollRun.
 *
 * Lifecycle:
 *   draft → approved → active → stopped/expired
 */
class SalarySlip extends Model
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_STOPPED  = 'stopped';
    public const STATUS_EXPIRED  = 'expired';

    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_WEEKLY  = 'weekly';

    protected $fillable = [
        'code',
        'employee_id', 'contract_id', 'payroll_id', 'basic_amount', 'currency', 'treasury_id',
        'start_date', 'end_date', 'frequency', 'scheduled_payment_day',
        'status', 'notes', 'attachments',
        'created_by',
        'approved_at', 'approved_by',
        'stopped_at', 'stopped_by', 'stop_reason',
        // الحقول القديمة تبقى للتوافق:
        'slip_date', 'from_date', 'to_date', 'total_salary', 'total_deductions', 'net_salary',
    ];

    protected $casts = [
        'slip_date'    => 'date',
        'from_date'    => 'date',
        'to_date'      => 'date',
        'start_date'   => 'date',
        'end_date'     => 'date',
        'approved_at'  => 'datetime',
        'stopped_at'   => 'datetime',
        'basic_amount' => 'decimal:2',
        'total_salary' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary'   => 'decimal:2',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT    => 'مسودة',
            self::STATUS_APPROVED => 'معتمدة',
            self::STATUS_ACTIVE   => 'نشطة',
            self::STATUS_STOPPED  => 'موقوفة',
            self::STATUS_EXPIRED  => 'منتهية',
        ];
    }

    public static function statusBadgeMap(): array
    {
        return [
            self::STATUS_DRAFT    => 'secondary',
            self::STATUS_APPROVED => 'info',
            self::STATUS_ACTIVE   => 'success',
            self::STATUS_STOPPED  => 'warning',
            self::STATUS_EXPIRED  => 'dark',
        ];
    }

    // ============= العلاقات =============

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /** المسير الذي تنتمي إليه القسيمة — مظلة زمنية، لا عملية الصرف. */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function stopper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stopped_by');
    }

    /**
     * مَن أنشأ القسيمة — أي مَن حدّد أجرَ الموظف.
     *
     * كان الجواب لا يُعرف إلا من جدول `logs`، وهو قابلٌ للحذف من شاشته.
     * فصار عموداً على المستند نفسه، لأنّ عليه يقوم فصلُ المهام:
     * `wasApprovedByItsCreator()`.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * هل اعتمد القسيمةَ مَن أنشأها؟
     *
     * أوّلُ ما يسأل عنه المدقّق في دورةٍ تُخرج نقداً: مَن حدّد الأجر ومَن
     * صادق عليه. ويُجاب بـ`false` عند غياب أيِّ الطرفَين — فالمجهولُ لا
     * يُبنى عليه منع.
     */
    public function wasApprovedByItsCreator(?int $approverId): bool
    {
        return $this->created_by !== null
            && $approverId !== null
            && (int) $this->created_by === (int) $approverId;
    }

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class, 'salary_slip_id');
    }

    /**
     * هل بقي في القسيمة صرف لم يُنفَّذ بعد؟
     *
     * القسيمة لا تُنهى تلقائياً وفيها رواتب معلَّقة — تبقى نشطة حتى تُصرَف
     * أو تُلغى كلُّ عملياتها المعلَّقة.
     */
    public function hasUndisbursedRuns(): bool
    {
        return $this->payrollRuns()
            ->where('status', PayrollRun::STATUS_PENDING)
            ->exists();
    }

    /**
     * أعد القسيمة المنتهية إلى نشطة إن بقي فيها صرف معلَّق.
     */
    public function reviveWhileUndisbursed(): bool
    {
        if (! $this->isExpired() || ! $this->hasUndisbursedRuns()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'stop_reason' => null,
        ]);

        return true;
    }

    public function salaryItem(): HasMany
    {
        return $this->hasMany(SalaryItem::class, 'salary_slips_id');
    }

    public function salaryItems(): HasMany
    {
        return $this->hasMany(SalaryItem::class, 'salary_slips_id');
    }

    /*
        الرقمان كانا مكتوبَين هنا خامَّين (`1` و`2`)، وثابتا [SalaryItem]
        يسمّيانهما بالعكس. فصار المرجعُ واحداً: ما تقرؤه هاتان العلاقتان
        هو ما يعنيه الاسم، ولا يفترقان بعد اليوم صامتَين.
    */
    public function additionItems(): HasMany
    {
        return $this->hasMany(SalaryItem::class, 'salary_slips_id')
            ->where('type', SalaryItem::TYPE_ADDITION);
    }

    public function deductionItems(): HasMany
    {
        return $this->hasMany(SalaryItem::class, 'salary_slips_id')
            ->where('type', SalaryItem::TYPE_DEDUCTION);
    }

    // ============= شروط الحالة =============

    public function isDraft(): bool     { return $this->status === self::STATUS_DRAFT; }
    public function isApproved(): bool  { return $this->status === self::STATUS_APPROVED; }
    public function isActive(): bool    { return $this->status === self::STATUS_ACTIVE; }
    public function isStopped(): bool   { return $this->status === self::STATUS_STOPPED; }
    public function isExpired(): bool   { return $this->status === self::STATUS_EXPIRED; }

    // التعديل مسموح في حالة المسودة + المعتمدة + النشطة.
    // الـ runs المصروفة سابقاً لا تتأثر (قيودها المحاسبية محفوظة بمبلغها القديم)،
    // والـ runs الجديدة المُولَّدة بعد التعديل ستستخدم المبلغ المُحدَّث.
    public function canBeEdited(): bool    { return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED, self::STATUS_ACTIVE]); }
    public function canBeApproved(): bool  { return $this->isDraft(); }
    public function canBeStopped(): bool   { return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_ACTIVE]); }
    public function canBeDeleted(): bool   { return $this->isDraft(); }

    /**
     * هل يمكن توليد runs جديدة من هذه القسيمة؟
     * يفحص:
     *   - الحالة (approved/active)
     *   - نهاية القسيمة
     *   - الموظف نشط
     *   - العقد فعّال (إن وُجد)
     */
    public function canGenerateRuns(): bool
    {
        if (!in_array($this->status, [self::STATUS_APPROVED, self::STATUS_ACTIVE])) {
            return false;
        }
        if ($this->end_date && now()->toDateString() > $this->end_date->toDateString()) {
            return false;
        }

        // فحص الموظف
        $employee = $this->employee;
        if (!$employee || !$employee->isActive()) {
            return false;
        }

        // فحص العقد إن كان مرتبطاً
        if ($this->contract_id) {
            $contract = $this->contract;
            if (!$contract) return false;
            if ($contract->end_date && now()->gt($contract->end_date)) return false;
        }

        // فحص مسير الرواتب — لا بد أن يكون فعّالاً (نشط + ضمن الفترة)
        if ($this->payroll_id) {
            $payroll = $this->payroll;
            if (!$payroll || !$payroll->isRunning()) {
                return false;
            }
        }

        return true;
    }

    /**
     * هل القسيمة منتهية؟ (يستحق تحويلها إلى expired تلقائياً)
     *
     * إن بقي فيها صرف معلَّق لا تُنهى — تبقى نشطة حتى تُصرَف الرواتب المتبقية.
     * توليد فترات جديدة يُمنَع عبر `canGenerateRuns()` عند انتهاء التاريخ.
     */
    public function shouldExpire(): ?string
    {
        if ($this->hasUndisbursedRuns()) {
            return null;
        }

        // نهاية القسيمة
        if ($this->end_date && now()->gt($this->end_date)) {
            return 'انتهت فترة القسيمة';
        }

        // موظف غير نشط
        $employee = $this->employee;
        if (!$employee || !$employee->isActive()) {
            return 'الموظف غير نشط';
        }

        // عقد انتهى
        if ($this->contract_id) {
            $contract = $this->contract;
            if (!$contract) return 'العقد محذوف';
            if ($contract->end_date && now()->gt($contract->end_date)) {
                return 'انتهى العقد';
            }
        }

        // مسير رواتب غير فعّال
        if ($this->payroll_id) {
            $payroll = $this->payroll;
            if (!$payroll) return 'مسير الرواتب محذوف';
            if (!$payroll->isActive()) return 'مسير الرواتب غير نشط';
            if ($payroll->end_date && now()->gt($payroll->end_date)) {
                return 'انتهت فترة مسير الرواتب';
            }
        }

        return null;
    }

    // ============= العرض =============

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return self::statusBadgeMap()[$this->status] ?? 'light';
    }

    // ============= حساب يوم الصرف الفعّال =============

    /**
     * يوم الصرف الفعلي للقسيمة:
     *   1) من القسيمة نفسها (scheduled_payment_day)
     *   2) من الموظف (employees.scheduled_payment_day)
     *   3) من الإعدادات (payroll_settings.default_payment_day)
     *   4) 1 افتراضياً
     */
    public function getEffectivePaymentDay(): int
    {
        if ($this->scheduled_payment_day) {
            return (int) $this->scheduled_payment_day;
        }
        if ($this->employee && $this->employee->scheduled_payment_day) {
            return (int) $this->employee->scheduled_payment_day;
        }
        $default = (int) PayrollSettings::value('default_payment_day');
        return $default ?: 1;
    }

    /**
     * يحسب الفترة التالية التي تحتاج payroll_run.
     * يرجع null إذا انتهت القسيمة أو لا يوجد فترة جديدة.
     *
     * @return array{period_from: Carbon, period_to: Carbon, scheduled_date: Carbon}|null
     */
    public function generateNextPeriod(): ?array
    {
        if (!$this->canGenerateRuns()) return null;

        $start = $this->start_date ?? $this->approved_at ?? $this->created_at;
        if (!$start) return null;

        // ابحث عن آخر run لتحديد الفترة التالية
        $lastRun = $this->payrollRuns()->orderByDesc('period_to')->first();

        if ($this->frequency === self::FREQUENCY_WEEKLY) {
            $periodFrom = $lastRun
                ? Carbon::parse($lastRun->period_to)->addDay()
                : Carbon::parse($start)->startOfWeek();
            $periodTo = $periodFrom->copy()->addDays(6);
        } else {
            // monthly (افتراضي)
            $periodFrom = $lastRun
                ? Carbon::parse($lastRun->period_to)->addDay()->startOfMonth()
                : Carbon::parse($start)->startOfMonth();
            $periodTo = $periodFrom->copy()->endOfMonth();
        }

        // إذا تجاوزت نهاية القسيمة → لا توليد
        if ($this->end_date && $periodFrom->gt(Carbon::parse($this->end_date))) {
            return null;
        }

        // قص الفترة عند نهاية القسيمة إن لزم
        if ($this->end_date && $periodTo->gt(Carbon::parse($this->end_date))) {
            $periodTo = Carbon::parse($this->end_date);
        }

        // ⛔ احترام نهاية العقد (إن وُجد)
        if ($this->contract_id && $this->contract && $this->contract->end_date) {
            $contractEnd = Carbon::parse($this->contract->end_date);
            if ($periodFrom->gt($contractEnd)) {
                return null;
            }
            if ($periodTo->gt($contractEnd)) {
                $periodTo = $contractEnd;
            }
        }

        // تاريخ الصرف = يوم الصرف من نفس شهر الفترة (مقدماً) بدل الشهر التالي.
        // مثال: قسيمة أُنشئت 6/1 ويوم الصرف = 2 → الصرف 6/2 (وليس 7/2).
        // لو يوم الصرف أصغر من بداية الفترة (نادر: عند تغيير اليوم لشيء أصغر منتصف الشهر)،
        // نُبقيه في نفس الشهر لكن نضمن أنه ≥ period_from.
        $effectiveDay = $this->getEffectivePaymentDay();
        $scheduled = $periodFrom->copy();
        $maxDay = $scheduled->daysInMonth;
        $scheduled->day = min($effectiveDay, $maxDay);
        // ضمان: لا يقع قبل بداية الفترة (لو effective_day < period_from->day)
        if ($scheduled->lt($periodFrom)) {
            $scheduled = $periodFrom->copy();
        }

        return [
            'period_from'    => $periodFrom,
            'period_to'      => $periodTo,
            'scheduled_date' => $scheduled,
        ];
    }
}
