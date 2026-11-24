<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\PayrollDisbursementService;
use App\Support\PayrollSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * عملية صرف راتب (سجل مستقل لكل شهر).
 *
 * كل قسيمة (SalarySlip) تولّد payroll_run شهرياً.
 * الـ run يبدأ pending وينتقل إلى disbursed عند الصرف الفعلي.
 *
 * لا يتم إنشاء قيود محاسبية أو سند صرف إلا في حالة disbursed.
 *
 * ══════════════════ مراحلُ سجل الصرف (للعرض والتصفية) ══════════════════
 *
 * عمودُ `status` ثلاثُ قيم (pending/disbursed/cancelled)، لكنّ «المعلّقة»
 * تخفي ثلاثةَ أحوالٍ مختلفةٍ لمن يقرأ الشاشة: لم تُعتمد بعد، أو اعتُمدت
 * وتنتظر خروجَ النقد، أو أُوقفت بقرار. فالمرحلةُ مشتقّةٌ بلا عمودٍ جديد:
 *
 *   awaiting_approval  بانتظار اعتماد مسير الرواتب — مسيرُ قسيمتها لم يُعتمد
 *   scheduled          معتمدة — قبل يوم الصرف     — مسيرُها معتمد ولم يحلّ `scheduled_date`
 *   ready              مستحقة الصرف               — مسيرُها معتمد وحلّ يومُ الصرف
 *   held               موقوفة                     — `held_at` قائم
 *   paid               مدفوعة                     — `disbursed`: خرج النقدُ وقُيِّد
 *   cancelled          ملغاة
 *
 * **اعتمادٌ واحد**: اعتمادُ «مسير الرواتب» (`payrolls.status = active`) هو
 * التصريحُ الوحيد بخروج النقد. كان يُشترط فوقه اعتمادُ «مسير صرفات»
 * (`payroll_batches`) لكلّ دفعة، فيعتمد المستخدمُ المسيرَ ثمّ يعود في يوم
 * الصرف ليعتمد دفعةً ثانية للموظفين أنفسهم. أُلغي ذلك: جدولُ الدفعات وعمودُ
 * `payroll_batch_id` باقيان تاريخاً لا شرطاً. ولا يُكتب شيءٌ عند الاعتماد
 * غيرُ حالة المسير — فمصدرُ الحقيقة واحد.
 *
 * والصرفُ قبل يوم الصرف «صرفٌ مبكر» بتأكيدٍ صريح (`$allowEarly`)، لا يمرّ به
 * الكرون ولا الصرفُ الجماعيّ.
 *
 * ══════════════════ «صرف الراتب» و«صرف القسيمة» ══════════════════
 *
 * **عمليةٌ واحدة لا اثنتان.** زرُّ «صرف راتب YYYY-MM» في شاشة القسيمة
 * وزرُّ «صرف الراتب الآن» في شاشة سجل الصرف يُرسلان كلاهما إلى المسار
 * نفسِه `payroll-runs.disburse` على الصرفة المستحقّة، فيمرّان على
 * `PayrollDisbursementService::disburse()` نفسِها بالحراس نفسِها والقيود
 * نفسِها (SAL-ACC استحقاق، SAL-PAY صرف، SAL-GOSI حصة المنشأة). والقسيمةُ
 * نفسُها **لا تُصرف**: «اعتماد القسيمة» يجعلها تولّد صرفةً شهرية فقط.
 * و`SalarySlipController::testDisburse` مسارُ اختبارٍ مجمَّد (404) لا يصل
 * إليه أحد. فلا ازدواجَ يُزال — والتسميةُ في الشاشات «صرف الراتب» وحدها.
 */
class PayrollRun extends Model
{
    protected $table = 'payroll_runs';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_DISBURSED = 'disbursed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_CRON   = 'cron';

    protected $fillable = [
        'code',
        'salary_slip_id', 'employee_id', 'contract_id',
        'period_from', 'period_to', 'scheduled_date',
        'basic_amount', 'total_additions', 'total_deductions',
        'total_advances_deducted', 'net_salary',
        'status', 'trigger_source',
        'disbursed_at', 'disbursed_by', 'expense_id', 'journal_entry_id',
        'accrual_journal_entry_id',
        'treasury_id', 'cancellation_reason', 'notes',
        'reversed_at', 'reversed_by', 'reversal_reason', 'reversal_journal_entry_id',
        'held_at', 'held_by', 'hold_reason',
        'payroll_batch_id',
    ];

    protected $casts = [
        'period_from'             => 'date',
        'period_to'               => 'date',
        'scheduled_date'          => 'date',
        'disbursed_at'            => 'datetime',
        'reversed_at'             => 'datetime',
        'held_at'                 => 'datetime',
        'basic_amount'            => 'decimal:2',
        'total_additions'         => 'decimal:2',
        'total_deductions'        => 'decimal:2',
        'total_advances_deducted' => 'decimal:2',
        'net_salary'              => 'decimal:2',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING   => 'قيد الانتظار',
            self::STATUS_DISBURSED => 'مدفوع',
            self::STATUS_CANCELLED => 'ملغي',
        ];
    }

    public const STAGE_AWAITING_APPROVAL = 'awaiting_approval';
    public const STAGE_SCHEDULED = 'scheduled';
    public const STAGE_READY = 'ready';
    public const STAGE_HELD = 'held';
    public const STAGE_PAID = 'paid';
    public const STAGE_CANCELLED = 'cancelled';

    /** مراحلُ العرض — انظر رأس الصنف */
    public static function stages(): array
    {
        return [
            self::STAGE_AWAITING_APPROVAL => 'بانتظار اعتماد مسير الرواتب',
            self::STAGE_SCHEDULED => 'معتمدة — قبل يوم الصرف',
            self::STAGE_READY => 'مستحقة الصرف',
            self::STAGE_HELD => 'موقوفة',
            self::STAGE_PAID => 'مدفوعة',
            self::STAGE_CANCELLED => 'ملغاة',
        ];
    }

    /** نبرةُ `stk-state--*` لكلّ مرحلة */
    public static function stageTones(): array
    {
        return [
            self::STAGE_AWAITING_APPROVAL => 'warn',
            self::STAGE_SCHEDULED => 'neutral',
            self::STAGE_READY => 'on',
            self::STAGE_HELD => 'warn',
            self::STAGE_PAID => 'on',
            self::STAGE_CANCELLED => 'off',
        ];
    }

    public function stage(): string
    {
        if ($this->isDisbursed()) {
            return self::STAGE_PAID;
        }
        if ($this->isCancelled()) {
            return self::STAGE_CANCELLED;
        }
        if ($this->isHeld()) {
            return self::STAGE_HELD;
        }

        if ($this->disbursementApprovalBlockReason() !== null) {
            return self::STAGE_AWAITING_APPROVAL;
        }

        return $this->isDueByDate() ? self::STAGE_READY : self::STAGE_SCHEDULED;
    }

    /** هل حلّ يومُ الصرف؟ (`scheduled_date` ≤ اليوم) */
    public function isDueByDate(): bool
    {
        return $this->scheduled_date !== null
            && now()->toDateString() >= $this->scheduled_date->toDateString();
    }

    public function getStageLabelAttribute(): string
    {
        return self::stages()[$this->stage()] ?? $this->status;
    }

    public function getStageToneAttribute(): string
    {
        return self::stageTones()[$this->stage()] ?? 'neutral';
    }

    /**
     * هل أُقفلت هذه الصرفة ماليّاً؟ — مدفوعةٌ أو ملغاة.
     *
     * لا يُضاف إليها بعد ذلك مبلغٌ ولا تسوية ولا إيقاف: التصحيحُ الوحيد
     * لمدفوعةٍ هو **العكس** (قيدٌ عكسيٌّ يعيدها معلّقة)، ثمّ يُعمل عليها.
     */
    public function isFinanciallyClosed(): bool
    {
        return $this->isDisbursed() || $this->isCancelled();
    }

    /**
     * رقم مستند الصرف من «الترقيم المتسلسل».
     *
     * الإخفاق لا يمنع إنشاء السجل — يُحفظ بلا رقم كالقائمين قبله.
     */
    public static function issueDocumentCode(): ?string
    {
        try {
            return next_document_number('payroll-run');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ============= العلاقات =============

    public function slip(): BelongsTo
    {
        return $this->belongsTo(SalarySlip::class, 'salary_slip_id');
    }

    public function salarySlip(): BelongsTo
    {
        return $this->slip();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class, 'payroll_batch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class, 'payroll_run_id');
    }

    public function additions(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class, 'payroll_run_id')
            ->where('item_type', PayrollRunItem::TYPE_ADDITION);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class, 'payroll_run_id')
            ->where('item_type', PayrollRunItem::TYPE_DEDUCTION);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class, 'payroll_run_id')
            ->where('item_type', PayrollRunItem::TYPE_ADVANCE);
    }

    public function retros(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class, 'payroll_run_id')
            ->where('item_type', PayrollRunItem::TYPE_RETRO);
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'treasury_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function accrualJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'accrual_journal_entry_id');
    }

    // ============= شروط الحالة =============

    public function isPending(): bool    { return $this->status === self::STATUS_PENDING; }
    public function isDisbursed(): bool  { return $this->status === self::STATUS_DISBURSED; }
    public function isCancelled(): bool  { return $this->status === self::STATUS_CANCELLED; }
    public function isHeld(): bool       { return $this->isPending() && $this->held_at !== null; }

    /**
     * هل يشترط النظامُ اعتمادَ مسير الرواتب قبل خروج النقد؟
     *
     * الافتراضُ نعم — المفتاحُ في «إعدادات الرواتب»
     * (`require_disbursement_approval`). عند تعطيله يكفي اعتمادُ القسيمة.
     */
    public static function requiresDisbursementApproval(): bool
    {
        return (bool) (PayrollSettings::value('require_disbursement_approval') ?? true);
    }

    /**
     * سببُ منعِ الصرف من جهة الاعتماد، أو `null` إن كان مصرَّحاً به.
     *
     * **ما كان**: الصرفةُ لا تُصرف إلا ضمن «مسير صرفات» (`payroll_batches`)
     * معتمد — اعتمادٌ ثانٍ فوق اعتماد مسير الرواتب لكلّ دفعة.
     *
     * **ما صار**: اعتمادُ **مسير الرواتب** الذي تنتمي إليه القسيمة هو التصريح
     * الوحيد. فالمسودةُ «بانتظار اعتماد المسير»، والموقوفُ يُعاد تفعيله،
     * والقسيمةُ بلا مسيرٍ تُربط بمسيرٍ معتمد. والدفعةُ القديمة (`payroll_batch_id`)
     * لا تُسأل: صرفةٌ في دفعةٍ لم تُعتمد تُصرف متى اعتُمد مسيرُ رواتبها.
     */
    public function disbursementApprovalBlockReason(): ?string
    {
        if (! self::requiresDisbursementApproval()) {
            return null;
        }

        $slip = $this->slip;
        if (! $slip) {
            return 'القسيمة المرتبطة بهذه الصرفة غير موجودة';
        }

        if (! $slip->payroll_id) {
            return 'قسيمة هذه الصرفة ليست ضمن مسير رواتب. اعتمادُ مسير الرواتب شرطُ الصرف: '.
                'اربط القسيمة بمسير رواتب معتمد.';
        }

        $payroll = $slip->payroll;
        if (! $payroll) {
            return 'مسير الرواتب المرتبط بالقسيمة محذوف.';
        }

        if ($payroll->isDraft()) {
            return 'مسير الرواتب «'.$payroll->name.'» لم يُعتمد بعد. اعتمِده من شاشة المسير ثمّ اصرف.';
        }

        if ($payroll->isInactive()) {
            return 'مسير الرواتب «'.$payroll->name.'» موقوف. أعِد تفعيله ليُصرف.';
        }

        return null;
    }

    /**
     * هل يمكن صرف هذا الـ run الآن؟
     * فقط إذا:
     *   - status = pending وغير موقوف
     *   - مسيرُ رواتب قسيمته معتمد — إلا إن أُطفئ الاشتراط
     *   - today >= scheduled_date — إلا «صرفاً مبكراً» مؤكَّداً (`$allowEarly`)
     *   - الموظف نشط
     *   - القسيمة ليست مسودة/موقوفة/منتهية
     *   - العقد فعّال (إن مرتبط بعقد)
     */
    public function canBeDisbursed(bool $allowEarly = false): bool
    {
        return $this->disbursementBlockReason($allowEarly) === null;
    }

    /**
     * هل يمنعها يومُ الصرف وحده؟ — فيُعرض «صرف مبكر» بتأكيد.
     */
    public function canBeDisbursedEarly(): bool
    {
        return ! $this->canBeDisbursed() && $this->canBeDisbursed(true);
    }

    /**
     * سبب منع الصرف بلغة المستخدم — لا مجرّد «قيد الانتظار» حين المنع من القسيمة/العقد.
     */
    public function disbursementBlockReason(bool $allowEarly = false): ?string
    {
        if (! $this->isPending()) {
            return 'الحالة: '.$this->status_label;
        }

        if ($this->isHeld()) {
            return 'هذه الصرفة موقوفة'.($this->hold_reason ? ': '.$this->hold_reason : '');
        }

        if ($approvalBlock = $this->disbursementApprovalBlockReason()) {
            return $approvalBlock;
        }

        if (! $this->scheduled_date) {
            return 'لا يوجد تاريخ صرف مجدول لهذه العملية';
        }

        if (! $allowEarly && ! $this->isDueByDate()) {
            return 'يوم الصرف لم يحل بعد: '.$this->scheduled_date->toDateString().
                ' — الصرفُ قبله «صرفٌ مبكر» بتأكيدٍ صريح.';
        }

        $emp = $this->employee;
        if (! $emp || ! $emp->isActive()) {
            return 'الموظف غير نشط';
        }

        $slip = $this->slip;
        if (! $slip) {
            return 'القسيمة المرتبطة بهذه الصرفة غير موجودة';
        }

        if ($slip->status === SalarySlip::STATUS_STOPPED) {
            return 'القسيمة موقوفة — لا يمكن الصرف';
        }

        if ($slip->status === SalarySlip::STATUS_EXPIRED) {
            // منتهية وبقي صرف معلَّق: أعدها نشطة وأتمم الصرف
            if ($slip->reviveWhileUndisbursed()) {
                $slip->refresh();
            } else {
                return 'القسيمة منتهية — لا يمكن الصرف';
            }
        }

        if ($slip->status === SalarySlip::STATUS_DRAFT) {
            return 'القسيمة ما زالت مسودة — يجب اعتمادها أولاً';
        }

        if ($this->contract_id) {
            $contract = $this->contract;
            if (! $contract) {
                return 'العقد المرتبط بالقسيمة محذوف';
            }
            if ($contract->end_date && now()->gt($contract->end_date)) {
                return 'انتهت مدة العقد المرتبط بالقسيمة';
            }
        }

        return null;
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    public function canBeReversed(): bool
    {
        return $this->isDisbursed();
    }

    public function canBeHeld(): bool
    {
        return $this->isPending() && ! $this->isHeld();
    }

    public function canBeReleased(): bool
    {
        return $this->isHeld();
    }

    /**
     * التسويةُ الرجعية على صرفةٍ معلّقة غير موقوفة — لا على مدفوعةٍ ولا ملغاة.
     *
     * والموقوفةُ مستثناةٌ كذلك: الإيقافُ قرارٌ إداريٌّ بتجميد الصرفة،
     * فلا تُغيَّر مبالغُها حتى يُرفع.
     */
    public function canReceiveRetro(): bool
    {
        return $this->isPending() && ! $this->isHeld();
    }

    /**
     * إيقاف صرفة معلّقة بقرار إداري: تبقى pending ولا تُصرف يدوياً ولا بالكرون.
     */
    public function hold(string $reason, ?int $heldBy = null): self
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('سبب الإيقاف مطلوب.');
        }

        return DB::transaction(function () use ($reason, $heldBy) {
            $run = static::query()->lockForUpdate()->findOrFail($this->id);
            if (! $run->canBeHeld()) {
                throw new \RuntimeException('لا يُوقف إلا راتب معلّق غير موقوف.');
            }

            $run->update([
                'held_at' => now(),
                'held_by' => $heldBy ?: Auth::id(),
                'hold_reason' => $reason,
            ]);

            return $run->fresh();
        });
    }

    /**
     * رفع الإيقاف لإعادة الصرف بعد زوال السبب الإداري.
     */
    public function release(): self
    {
        return DB::transaction(function () {
            $run = static::query()->lockForUpdate()->findOrFail($this->id);
            if (! $run->canBeReleased()) {
                throw new \RuntimeException('هذه الصرفة ليست موقوفة.');
            }

            $run->update([
                'held_at' => null,
                'held_by' => null,
                'hold_reason' => null,
            ]);

            return $run->fresh();
        });
    }

    /**
     * عكس صرف مصروف: قيد عكسي بلا حذف الأصلي، ثم إعادة الـ run إلى pending.
     */
    public function reverse(?string $reason = null, ?int $reversedBy = null): self
    {
        return app(PayrollDisbursementService::class)->reverse($this, (string) $reason, $reversedBy);
    }

    public function reversalJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }

    // ============= خصائص للعرض =============

    public function getStatusLabelAttribute(): string
    {
        if ($this->isHeld()) {
            return 'موقوف';
        }

        return self::statuses()[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->isHeld()) {
            return 'danger';
        }

        return match ($this->status) {
            self::STATUS_PENDING   => 'warning',
            self::STATUS_DISBURSED => 'success',
            self::STATUS_CANCELLED => 'secondary',
            default                => 'light',
        };
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->period_from->format('Y-m-d') . ' → ' . $this->period_to->format('Y-m-d');
    }

    // ============= Scopes =============

    public function scopePending($q)    { return $q->where('status', self::STATUS_PENDING); }
    public function scopeDisbursed($q)  { return $q->where('status', self::STATUS_DISBURSED); }
    public function scopeCancelled($q)  { return $q->where('status', self::STATUS_CANCELLED); }
    public function scopeHeld($q)
    {
        return $q->where('status', self::STATUS_PENDING)->whereNotNull('held_at');
    }

    /**
     * قسيمتُها ضمن مسير رواتب معتمد (نشط) — شرطُ الصرف ما دام الاشتراطُ قائماً.
     * و`status` الفارغ في المسيرات القديمة يُعدّ نشطاً كما في `Payroll::isActive()`.
     */
    public function scopePayrollApproved($q)
    {
        if (! self::requiresDisbursementApproval()) {
            return $q;
        }

        return $q->whereHas('slip.payroll', fn ($p) => $p->where(
            fn ($w) => $w->where('payrolls.status', Payroll::STATUS_ACTIVE)->orWhereNull('payrolls.status')
        ));
    }

    /** معلّقةٌ غير موقوفة ومسيرُ رواتب قسيمتها لم يُعتمد (أو لا مسير لها). */
    public function scopeAwaitingApproval($q)
    {
        $q->where('status', self::STATUS_PENDING)->whereNull('held_at');

        if (! self::requiresDisbursementApproval()) {
            return $q->whereRaw('1 = 0');
        }

        return $q->whereDoesntHave('slip.payroll', fn ($p) => $p->where(
            fn ($w) => $w->where('payrolls.status', Payroll::STATUS_ACTIVE)->orWhereNull('payrolls.status')
        ));
    }

    /** معتمدةٌ لم يحلّ يومُ صرفها بعد. */
    public function scopeScheduledAhead($q)
    {
        return $q->where('status', self::STATUS_PENDING)
            ->whereNull('held_at')
            ->payrollApproved()
            ->whereDate('scheduled_date', '>', now()->toDateString());
    }

    /** «مستحقة الصرف»: مسيرُها معتمد وحلّ يومُ الصرف. */
    public function scopeReadyToPay($q)
    {
        return $q->due()->payrollApproved();
    }

    /** تصفيةٌ بالمرحلة — مفتاحٌ من `stages()`. */
    public function scopeStage($q, string $stage)
    {
        return match ($stage) {
            self::STAGE_AWAITING_APPROVAL => $q->awaitingApproval(),
            self::STAGE_SCHEDULED => $q->scheduledAhead(),
            self::STAGE_READY => $q->readyToPay(),
            self::STAGE_HELD => $q->held(),
            self::STAGE_PAID => $q->where('status', self::STATUS_DISBURSED),
            self::STAGE_CANCELLED => $q->where('status', self::STATUS_CANCELLED),
            default => $q,
        };
    }

    /**
     * runs مستحقة للصرف اليوم أو قبل — الموقوفة مستبعدة حتى يُرفع الإيقاف.
     */
    public function scopeDue($q)
    {
        return $q->where('status', self::STATUS_PENDING)
                 ->whereNull('held_at')
                 ->whereDate('scheduled_date', '<=', now()->toDateString());
    }
}
