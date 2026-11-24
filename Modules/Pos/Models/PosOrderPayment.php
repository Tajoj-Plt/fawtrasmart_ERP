<?php

namespace Modules\Pos\Models;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\PosSession;
use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * دفعةٌ واحدة على فاتورة نقطة بيع.
 *
 * فاتورةٌ واحدة قد تُدفع بثلاث طرق: نقدٌ وشبكةٌ ورصيدُ محفظة. وكان ذلك
 * يُضغط في عمود `invoices.payment_method` نصَّ JSON — فلا يُستعلَم عنه ولا
 * يُجمَع. هنا صفٌّ لكل دفعة.
 */
class PosOrderPayment extends Model
{
    /*
        السياقُ يُختَم في المودل لا في الخدمة.

        الدفعةُ تُنشأ اليوم من [PosPaymentRecorder]، وغداً من بوابةِ دفعٍ
        تردّ إشعاراً، ومن تسويةٍ يدوية، ومن استيراد. وما مُلئ في الخدمة
        يفلت من الثلاثة الباقية.
    */
    use BelongsToAccountingPeriod;
    use HasDocumentCurrency;
    use SoftDeletes;
    use StampsOwnerContext;

    /** الدفعةُ تُنسَب إلى فترةِ لحظةِ استلامها. */
    public function accountingDateColumn(): string
    {
        return 'created_at';
    }

    /** الدفعةُ تتبع جلستَها. */
    protected function ownerBranchFromDocument(): ?int
    {
        return $this->branchOfSession($this->session_id);
    }

    protected $table = 'pos_order_payments';

    protected $fillable = [
        'invoice_id', 'session_id', 'payment_method_id',
        'amount', 'currency', 'tendered_amount', 'change_amount',
        'gateway_reference', 'card_last4', 'status', 'settled_at',
        'journal_entry_id', 'accounting_period_id', 'branch_id',
        'created_by', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tendered_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    /* ═══════════════ العلاقات ═══════════════ */

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function session()
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function accountingPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ═══════════════ النطاقات ═══════════════ */

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereNull('settled_at')->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForSession(Builder $query, $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /* ═══════════════ مشتقّات ═══════════════ */

    /**
     * أهذه دفعةٌ نقدية؟
     *
     * تُقرأ من طريقة الدفع نفسها (`is_online`) لا من رقمٍ مكتوب. كانت
     * الشيفرة تفترض `method_id == 1` نقداً في ثلاثة مواضع — رقمٌ يصير خطأً
     * بأول إعادة ترتيبٍ لجدول طرق الدفع.
     */
    public function isCash(): bool
    {
        return ! ($this->paymentMethod?->is_online);
    }

    /**
     * الحسابُ الذي تُرحَّل إليه هذه الدفعة.
     *
     * الدفعُ الإلكتروني يذهب للبنك والنقديُّ للخزينة — والقرارُ هنا لا في
     * مولّد القيد، فلا يُكرَّر في البيع والمرتجع وحركة الدرج.
     */
    public function destinationAccount(): ?Account
    {
        $method = $this->paymentMethod;

        if ($method?->account_id) {
            return Account::find($method->account_id);
        }

        return null;
    }
}
