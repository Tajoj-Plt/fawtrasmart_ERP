<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * خصمية (حسم/جزاء) على موظف.
 *
 * **تُقيَّد عند اعتمادها** (مدين ذمّة الموظف / دائن حساب نوع الخصمية)، ثمّ
 * تُقتطع من الراتب عند صرفه دون قيدٍ ثانٍ — انظر `DeductionAccountingService`.
 * و`posted_amount` ما غطّته القيودُ من مبلغها خارج قيد الراتب.
 */
class Deduction extends Model
{
    use HasFactory;

    protected $table = 'deductions';

    // ============== أنواع الخصمية ==============
    public const TYPE_ABSENCE        = 'absence';
    public const TYPE_LATE           = 'late';
    public const TYPE_EARLY_LEAVE    = 'early_leave';
    public const TYPE_HALF_DAY       = 'half_day';
    public const TYPE_VIOLATION      = 'violation';
    public const TYPE_CUSTODY_DAMAGE = 'custody_damage';
    public const TYPE_ADMIN_PENALTY  = 'admin_penalty';
    public const TYPE_OTHER          = 'other';

    // ============== الحالات ==============
    public const STATUS_PENDING            = 'pending';
    public const STATUS_APPROVED           = 'approved';
    public const STATUS_REJECTED           = 'rejected';
    public const STATUS_SETTLED            = 'settled';
    public const STATUS_PARTIALLY_SETTLED  = 'partially_settled';
    public const STATUS_CANCELLED          = 'cancelled';

    public static function types(): array
    {
        return [
            self::TYPE_ABSENCE        => 'غياب',
            self::TYPE_LATE           => 'تأخير',
            self::TYPE_EARLY_LEAVE    => 'انصراف مبكر',
            self::TYPE_HALF_DAY       => 'نصف يوم',
            self::TYPE_VIOLATION      => 'مخالفة',
            self::TYPE_CUSTODY_DAMAGE => 'تلف عهدة',
            self::TYPE_ADMIN_PENALTY  => 'عقوبة إدارية',
            self::TYPE_OTHER          => 'أخرى',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING            => 'قيد الانتظار',
            self::STATUS_APPROVED           => 'معتمدة',
            self::STATUS_REJECTED           => 'مرفوضة',
            self::STATUS_SETTLED            => 'مسوّاة',
            self::STATUS_PARTIALLY_SETTLED  => 'مسوّاة جزئياً',
            self::STATUS_CANCELLED          => 'ملغاة',
        ];
    }

    public static function statusBadgeMap(): array
    {
        return [
            self::STATUS_PENDING            => 'warning',
            self::STATUS_APPROVED           => 'info',
            self::STATUS_REJECTED           => 'danger',
            self::STATUS_SETTLED            => 'success',
            self::STATUS_PARTIALLY_SETTLED  => 'primary',
            self::STATUS_CANCELLED          => 'secondary',
        ];
    }

    protected $fillable = [
        'code',
        'employee_id',
        'deduction_type',
        'amount',
        'settled_amount',
        'status',
        'date',
        'reason',
        'notes',
        'created_by_user',
        'approved_by_user',
        'approved_at',
        'rejected_by_user',
        'rejected_at',
        'rejection_reason',
        'cancelled_by_user',
        'cancelled_at',
        'cancellation_reason',
        'settled_at',
        'posted_amount',
        'journal_entry_id',
    ];

    protected $casts = [
        'date'           => 'date',
        'approved_at'    => 'datetime',
        'rejected_at'    => 'datetime',
        'cancelled_at'   => 'datetime',
        'settled_at'     => 'datetime',
        'amount'         => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'posted_amount'  => 'decimal:2',
    ];

    /**
     * القيدُ يتبع الحالة أينما تغيّرت — لا في متحكّمٍ واحد.
     *
     * الخصمياتُ تُعتمد وتُلغى من أكثر من موضع: شاشةُ الخصميات، ومعالجُ
     * الحضور (يُنشئها معتمدةً مباشرةً بلا جلسة)، واعتمادُ تصحيح الحضور
     * (يُلغيها). فلو ربط القيدَ متحكّمُ الخصميات وحده لبقيت خصمياتُ الحضور
     * بلا قيد، ولبقي قيدُ الملغاة من التصحيح قائماً على ذمّة الموظف.
     *
     *   • **اعتمادٌ من مسارٍ آليّ**: يُحاوَل القيد، وإن تعذّر (حسابٌ غير
     *     مربوط) سُجِّل التحذيرُ ولم يُكسر معالجُ الحضور — ويُقيَّد لاحقاً
     *     عند صرف الراتب، أو يُرفض الصرفُ بالسبب. أمّا الاعتمادُ من الشاشة
     *     فيقيّد **قبل** تغيير الحالة ويُظهر السببَ للمستخدم.
     *   • **الإلغاء**: يُعكس ما قُيِّد ولم يُقتطع — وإن تعذّر العكسُ رُفض
     *     الإلغاءُ كلُّه، فلا تبقى ذمّةُ الموظف مدينةً بخصميةٍ ملغاة.
     */
    protected static function booted(): void
    {
        static::created(function (Deduction $d) {
            if ($d->status === self::STATUS_APPROVED) {
                $d->tryPosting();
            }
        });

        static::updated(function (Deduction $d) {
            if (! $d->wasChanged('status')) {
                return;
            }

            if ($d->status === self::STATUS_APPROVED && (float) $d->posted_amount + 0.009 < (float) $d->amount) {
                $d->tryPosting();
            }

            if ($d->status === self::STATUS_CANCELLED && (float) $d->posted_amount - (float) $d->settled_amount > 0.009) {
                app(\App\Services\DeductionAccountingService::class)
                    ->reverseUnsettled($d, $d->cancellation_reason);
            }
        });
    }

    private function tryPosting(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('deductions', 'posted_amount')) {
            return;
        }

        try {
            app(\App\Services\DeductionAccountingService::class)->ensurePosted($this);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Deduction posting deferred to payroll', [
                'deduction_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** قيدُ الاعتماد (DED-{id}) */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /** ما قُيِّد ولم يُقتطع بعد — مدينٌ على ذمّة الموظف ينتظر الراتب */
    public function getPostedOutstandingAttribute(): float
    {
        return max(0, (float) $this->posted_amount - (float) $this->settled_amount);
    }

    // ============== العلاقات ==============

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user');
    }

    /**
     * مَن أنشأ الخصمَ كما يُعرض — أو «النظام» إن أنشأه النظامُ نفسه.
     *
     * خصمياتُ الغياب والتأخير يولّدها `AttendanceProcessorService` في مسارٍ
     * بلا جلسة، فلا مستخدمَ لها. وكانت تُنسَب إلى المستخدم رقم `1` مكتوباً
     * في الشيفرة — وهو شخصٌ حقيقيٌّ يظهر اسمُه في شاشة الخصميات على خصمٍ لم
     * يُنشئه. فصار الحقلُ فارغاً، ويُقال للفراغ اسمُه: النظام لا شرطة.
     */
    public function getCreatorLabelAttribute(): string
    {
        return optional($this->creator)->name ?: 'النظام';
    }

    /** ومَن اعتمده كذلك. */
    public function getApproverLabelAttribute(): string
    {
        return optional($this->approver)->name ?: 'النظام';
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DeductionHistory::class, 'deduction_id')->orderByDesc('id');
    }

    // ============== الحسابات الديناميكية ==============

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - (float) $this->settled_amount);
    }

    public function getProgressPercentageAttribute(): float
    {
        if (!$this->amount || (float) $this->amount <= 0) return 0;
        return min(100, round(((float) $this->settled_amount / (float) $this->amount) * 100, 2));
    }

    public function getTypeLabelAttribute(): string
    {
        return self::types()[$this->deduction_type] ?? $this->deduction_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return self::statusBadgeMap()[$this->status] ?? 'secondary';
    }

    // ============== شروط الحالة ==============

    public function isPending(): bool      { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool     { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool     { return $this->status === self::STATUS_REJECTED; }
    public function isSettled(): bool      { return $this->status === self::STATUS_SETTLED; }
    public function isPartiallySettled(): bool { return $this->status === self::STATUS_PARTIALLY_SETTLED; }
    public function isCancelled(): bool    { return $this->status === self::STATUS_CANCELLED; }

    public function canBeEdited(): bool    { return $this->isPending(); }
    public function canBeDeleted(): bool   { return $this->isPending() || $this->isRejected() || $this->isCancelled(); }
    public function canBeApproved(): bool  { return $this->isPending(); }
    public function canBeRejected(): bool  { return $this->isPending(); }
    public function canBeCancelled(): bool { return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PARTIALLY_SETTLED]); }
    public function canBeSettled(): bool   { return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIALLY_SETTLED]) && $this->remaining_amount > 0; }

    // ============== Scopes ==============

    public function scopePending($q)            { return $q->where('status', self::STATUS_PENDING); }
    public function scopeApproved($q)           { return $q->where('status', self::STATUS_APPROVED); }
    public function scopeRejected($q)           { return $q->where('status', self::STATUS_REJECTED); }
    public function scopeSettled($q)            { return $q->where('status', self::STATUS_SETTLED); }
    public function scopePartiallySettled($q)   { return $q->where('status', self::STATUS_PARTIALLY_SETTLED); }
    public function scopeCancelled($q)          { return $q->where('status', self::STATUS_CANCELLED); }

    /**
     * المعتمدة وغير المسواة بالكامل (مرشحة للخصم من الراتب).
     */
    public function scopeUnsettled($q)
    {
        return $q->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PARTIALLY_SETTLED]);
    }
}
