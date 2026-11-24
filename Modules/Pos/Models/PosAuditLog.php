<?php

namespace Modules\Pos\Models;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\CashierDevice;
use App\Models\Invoice;
use App\Models\PosSession;
use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * أثرُ فعلٍ حسّاس في نقطة البيع — ومن أذن به.
 *
 * `App\Support\OperationLog` يكفي لتعديل السجلات في بقيّة الوحدات. وهنا
 * يلزم عمودٌ إضافي لا يوجد هناك: `approved_by`. فأفعالُ نقطة البيع
 * الحسّاسة ليست تعديلاتٍ بل **تنازلاتٍ عن ضوابط** — خصمٌ فوق الحدّ، سعرٌ
 * مُعدَّل، فاتورةٌ مُلغاة، نقدٌ مسحوب — وكلٌّ منها يمرّ بموافقة مشرف. وبلا
 * عمودٍ يحملها تصير الموافقةُ كلاماً على الشاشة لا أثراً يُراجَع.
 */
class PosAuditLog extends Model
{
    /*
        سجلُّ التدقيق أولى ما يُختَم: هو نفسُه الدليلُ على «من فعل، وأين،
        ومتى». وكانت `record()` تملأ `user_id` و`ip` وتترك الفرعَ والفترةَ
        لمن يستدعيها — فيُكتب من ثلاثة مواضع، ويخرج من أحدها بلا فرع.
    */
    use BelongsToAccountingPeriod;
    use HasDocumentCurrency;
    use StampsOwnerContext;

    protected $table = 'pos_audit_logs';

    /** السجلُّ يُنسَب إلى فترةِ كتابته — لا عمودَ تاريخٍ غيره في الجدول. */
    public function accountingDateColumn(): string
    {
        return 'created_at';
    }

    /**
     * يُختم ولا يُمنع: سجلُّ التدقيق يصف ما حدث ولا يُرحِّل شيئاً، ومنعُه في
     * فترةٍ مقفلة يُسقط أثرَ عمليةٍ قد جرت فعلاً.
     */
    public function guardsAccountingPeriod(): bool
    {
        return false;
    }

    /** السجلُّ يتبع جلستَه أو جهازَه — لا فرعَ من كتبه. */
    protected function ownerBranchFromDocument(): ?int
    {
        return $this->branchOfSession($this->session_id) ?: $this->branchOfDevice($this->device_id);
    }

    protected $fillable = [
        'action', 'session_id', 'invoice_id', 'device_id', 'branch_id',
        'user_id', 'approved_by', 'amount', 'currency',
        'before', 'after', 'reason', 'ip_address', 'accounting_period_id',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'amount' => 'decimal:2',
    ];

    public const ACTION_DISCOUNT_OVERRIDE = 'discount_override';

    public const ACTION_PRICE_OVERRIDE = 'price_override';

    public const ACTION_VOID_INVOICE = 'void_invoice';

    public const ACTION_REFUND_OVER_LIMIT = 'refund_over_limit';

    public const ACTION_CASH_PAY_OUT = 'cash_pay_out';

    public const ACTION_CASH_DROP = 'cash_drop';

    public const ACTION_SESSION_CLOSE_VARIANCE = 'session_close_variance';

    public const ACTION_SESSION_CONFIRM = 'session_confirm';

    public const ACTION_REPRINT = 'reprint';

    public static function actionLabel(?string $action): string
    {
        return match ($action) {
            self::ACTION_DISCOUNT_OVERRIDE => 'تجاوز حدّ الخصم',
            self::ACTION_PRICE_OVERRIDE => 'تعديل سعر',
            self::ACTION_VOID_INVOICE => 'إلغاء فاتورة',
            self::ACTION_REFUND_OVER_LIMIT => 'مرتجع فوق الحدّ',
            self::ACTION_CASH_PAY_OUT => 'سحب نقد',
            self::ACTION_CASH_DROP => 'ترحيل نقد',
            self::ACTION_SESSION_CLOSE_VARIANCE => 'إقفال بفرق',
            self::ACTION_SESSION_CONFIRM => 'تأكيد إقفال',
            self::ACTION_REPRINT => 'إعادة طباعة',
            default => (string) $action,
        };
    }

    /**
     * تسجيلُ فعلٍ — لا يرمي أبداً.
     *
     * فشلُ الكتابة في سجلّ التدقيق يجب ألّا يُبطل عمليةَ بيعٍ صحيحة: العميل
     * واقفٌ عند الصندوق. لكنّ الفشلَ يُكتب في اللوغ ولا يُبتلع صامتاً.
     */
    public static function record(string $action, array $attributes = []): ?self
    {
        try {
            return self::create(array_merge([
                'action' => $action,
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
            ], $attributes));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('تعذّر كتابة سجل تدقيق نقاط البيع', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /* ═══════════════ العلاقات ═══════════════ */

    public function session()
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function device()
    {
        return $this->belongsTo(CashierDevice::class, 'device_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function accountingPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    /* ═══════════════ النطاقات ═══════════════ */

    public function scopeOfAction(Builder $query, string|array $action): Builder
    {
        return $query->whereIn('action', (array) $action);
    }

    /** ما يلزمه إذنٌ ولم يُؤذَن — أوّلُ ما يُراجَع في التدقيق. */
    public function scopeUnapproved(Builder $query): Builder
    {
        return $query->whereNull('approved_by');
    }
}
