<?php

namespace Modules\Pos\Models;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\CashierDevice;
use App\Models\JournalEntry;
use App\Models\PosSession;
use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * حركةُ درجٍ نقدي داخل جلسة — إيداعٌ أو سحبٌ أو ترحيلٌ أو عدّ.
 *
 * كلُّ حركةٍ تمسّ النقدَ الفعلي في الدرج، فتدخل في «الرصيد المتوقَّع» عند
 * الإقفال. وكان الفرقُ عند الإقفال يقيس هذه الحركاتِ غيرَ المسجَّلة بدل أن
 * يقيس الخطأ.
 */
class PosCashMovement extends Model
{
    use BelongsToAccountingPeriod;
    use HasDocumentCurrency;
    use SoftDeletes;
    use StampsOwnerContext;

    /** الحركةُ تُنسَب إلى فترةِ وقوعها لا وقتِ تسجيلها. */
    public function accountingDateColumn(): string
    {
        return 'movement_time';
    }

    /** الدرجُ يتبع جلستَه، والجلسةُ تتبع جهازَها. */
    protected function ownerBranchFromDocument(): ?int
    {
        return $this->branchOfSession($this->session_id) ?: $this->branchOfDevice($this->device_id);
    }

    protected $table = 'pos_cash_movements';

    protected $fillable = [
        'reference_number', 'session_id', 'device_id', 'branch_id',
        'type', 'amount', 'currency', 'counterpart_account_id', 'reason',
        'status', 'approved_by', 'approved_at',
        'journal_entry_id', 'accounting_period_id',
        'created_by', 'movement_time',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'movement_time' => 'datetime',
    ];

    /** إدخالُ نقدٍ إلى الدرج — فكّة. */
    public const TYPE_PAY_IN = 'pay_in';

    /** إخراجُ نقدٍ منه — مصروفٌ نثري. */
    public const TYPE_PAY_OUT = 'pay_out';

    /** ترحيلٌ إلى الخزينة الرئيسية. */
    public const TYPE_DROP = 'drop';

    /** عدُّ الدرج بلا حركة مال. */
    public const TYPE_COUNT = 'count';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * أثرُ كلّ نوعٍ على نقد الدرج: موجبٌ يزيده وسالبٌ ينقصه.
     *
     * خريطةٌ واحدة بدل `if/else` يتكرّر في الإقفال والتقرير والشاشة — وهو
     * موضعٌ إن اختلّت إشارتُه في أحدها اختلّ الرصيدُ المتوقَّع وحده بلا خطأ يظهر.
     */
    public const CASH_EFFECT = [
        self::TYPE_PAY_IN => 1,
        self::TYPE_PAY_OUT => -1,
        self::TYPE_DROP => -1,
        self::TYPE_COUNT => 0,
    ];

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            self::TYPE_PAY_IN => 'إيداع في الدرج',
            self::TYPE_PAY_OUT => 'سحب من الدرج',
            self::TYPE_DROP => 'ترحيل إلى الخزينة',
            self::TYPE_COUNT => 'عدّ الدرج',
            default => (string) $type,
        };
    }

    /* ═══════════════ العلاقات ═══════════════ */

    public function session()
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function device()
    {
        return $this->belongsTo(CashierDevice::class, 'device_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function counterpartAccount()
    {
        return $this->belongsTo(Account::class, 'counterpart_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function accountingPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* ═══════════════ النطاقات ═══════════════ */

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForSession(Builder $query, $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /** الحركاتُ التي تُغيّر النقد فعلاً — يُستثنى منها العدّ. */
    public function scopeAffectsCash(Builder $query): Builder
    {
        return $query->whereIn('type', [self::TYPE_PAY_IN, self::TYPE_PAY_OUT, self::TYPE_DROP]);
    }

    /* ═══════════════ مشتقّات ═══════════════ */

    /** أثرُ هذه الحركة على نقد الدرج — موجبٌ أو سالبٌ أو صفر. */
    public function cashEffect(): float
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return 0.0;
        }

        return (float) $this->amount * (self::CASH_EFFECT[$this->type] ?? 0);
    }
}
