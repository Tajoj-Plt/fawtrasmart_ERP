<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosHeldOrder;
use Modules\Pos\Models\PosOrderPayment;

class PosSession extends Model
{
    /*
        السياقُ يُختَم هنا لا في [PosSessionService].

        الجلسةُ تُفتح اليوم من الخدمة، وقد تُفتح غداً من شاشةٍ أخرى أو من
        أمرِ كرونٍ يفتح ورديةَ الصباح تلقائياً — وما مُلئ في الخدمة وحدها
        يفلت من كل طريقٍ آخر، فتخرج جلسةٌ بلا فرعٍ ولا فترة، ثم تغيب عن
        كل تقريرٍ يحصر بالفرع بلا خطأٍ يظهر.
    */
    use BelongsToAccountingPeriod;
    use HasDocumentCurrency;
    use SoftDeletes;
    use StampsOwnerContext;

    /** الجلسةُ تُنسَب إلى فترةِ فتحها لا إغلاقها. */
    public function accountingDateColumn(): string
    {
        return 'started_at';
    }

    /**
     * فرعُ الجلسة هو فرعُ **الجهاز** لا فرعُ الكاشير.
     *
     * البيعُ يقع حيث وقف العميل. ومشرفٌ من فرعٍ يفتح جلسةً على جهازٍ في
     * فرعٍ آخر تُنسَب مبيعاتُها إلى فرع الجهاز — وإلّا ظهر في تقرير الفروع
     * بيعٌ لم يقع في ذلك الفرع.
     */
    protected function ownerBranchFromDocument(): ?int
    {
        return $this->branchOfDevice($this->device_id);
    }

    protected $table = 'pos_sessions';

    protected $fillable = [
        'session_number',
        'user_id',
        'device_id',
        'shift_id',
        // سياقُ المستند — الفرعُ والفترةُ والعملة. كان الفرعُ يُستنتج بقفزتين
        // (جلسة ← جهاز ← فرع)، فنقلُ الجهاز بين الفروع يُعيد كتابة تاريخ
        // الجلسات القديمة
        'branch_id',
        'accounting_period_id',
        'currency',
        'started_at',
        'ended_at',
        'opening_balance',
        'closing_balance',
        'expected_cash',
        'actual_closing_balance',
        'difference',
        'variance_journal_entry_id',
        'total_transactions',
        'total_sales',
        'total_cash',
        'total_card',
        'total_returns',
        'status',
        'closing_notes',
        // دورةُ التأكيد: من أقفل غيرُ من أكّد — وهذا أصل الضبط الداخلي
        'closed_by',
        'confirmed_by',
        'confirmed_at',
        'confirmation_notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_closing_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'total_card' => 'decimal:2',
        'total_returns' => 'decimal:2',
    ];

    /* ═══════════════ الحالات ═══════════════ */

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** أُقفلت وتنتظر تأكيد المشرف — الحالةُ التي لم تكن. */
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_CLOSED = 'closed';

    // العلاقات
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(CashierDevice::class, 'device_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(shifts_pos::class, 'shift_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PosSessionDetail::class, 'session_id');
    }

    // دوال مساعدة
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function getDurationAttribute(): string
    {
        $start = $this->started_at;
        $end = $this->ended_at ?? now();

        return $start->diffForHumans($end, true);
    }

    /**
     * الرصيدُ النقدي المتوقَّع في الدرج.
     *
     * **المحفوظُ يتقدّم على المحسوب**: بعد الإقفال يُقرأ العمود `expected_cash`
     * كما جُمّد لحظتَها. فتغييرٌ لاحق في تفاصيل الجلسة لا يُعيد كتابة ما
     * «كان متوقَّعاً» — وإلّا صار الفرقُ المحفوظ لا يساوي فرقَ ما يُعرض.
     *
     * وحركاتُ الدرج تدخل في الحساب: كانت الصيغةُ
     * `opening + total_cash - total_returns` مكتوبةً في خمسة مواضع، بلا
     * أيّ اعتبارٍ للإيداع والسحب — لأنّ الميزة لم تكن موجودة أصلاً.
     */
    public function getExpectedCashAttribute()
    {
        $stored = $this->attributes['expected_cash'] ?? null;

        if ($stored !== null) {
            return (float) $stored;
        }

        return $this->computeExpectedCash();
    }

    /** الحسابُ الحيّ للرصيد المتوقَّع — أثناء الجلسة، قبل التجميد. */
    public function computeExpectedCash(): float
    {
        /*
            ═══════════ يُخصم ما صُرف نقداً لا كلُّ مرتجع ═══════════

            كان الطرحُ بـ`total_returns` — وهو مجموعُ **قيم** المرتجعات
            كلِّها. فمرتجعٌ لم يخرج له ريالٌ من الدرج (فاتورةٌ آجلة، أو
            رصيدٌ للعميل، أو فرعٌ يمنع الردّ النقدي) كان يُنقص المتوقَّع
            دفترياً، فيظهر عند العدّ **فائضٌ** بقيمته يُحقَّق فيه بلا سبب.

            و`total_returns` يبقى كما هو للعرض والتقارير — المطروحُ هنا هو
            النقدُ وحده.
        */
        $base = (float) $this->opening_balance
            + (float) $this->total_cash
            - $this->cashRefundsTotal();

        return round($base + $this->cashMovementsEffect(), 2);
    }

    /** ما رُدّ **نقداً** من المرتجعات في هذه الجلسة. */
    public function cashRefundsTotal(): float
    {
        try {
            return round((float) PosSessionDetail::query()
                ->where('session_id', $this->id)
                ->where('transaction_type', 'return')
                ->sum('cash_amount'), 2);
        } catch (\Throwable $e) {
            return (float) $this->total_returns;
        }
    }

    /** صافي أثر حركات الدرج المعتمدة على النقد. */
    public function cashMovementsEffect(): float
    {
        try {
            $rows = PosCashMovement::query()
                ->forSession($this->id)
                ->approved()
                ->affectsCash()
                ->get(['type', 'amount']);

            return round($rows->sum(
                fn ($row) => (float) $row->amount * (PosCashMovement::CASH_EFFECT[$row->type] ?? 0)
            ), 2);
        } catch (\Throwable $e) {
            // جدولُ الحركات غير مُرحَّل بعد يجب ألّا يكسر شاشة الجلسة
            return 0.0;
        }
    }

    /**
     * رقمُ الجلسة من إعدادات الترقيم المتسلسل.
     *
     * **ما كان**: `Ymd` + تسلسلٌ مشتقٌّ من آخر جلسةٍ اليوم بـ`substr(-3)`.
     * وفيه ثلاثة عيوب — لا قفلَ فيتصادم جهازان يفتحان معاً، والتسلسلُ
     * يُقرأ من نصّ الرقم نفسه فينكسر بأول تغييرٍ في صيغته، والإعداداتُ
     * المضبوطة في شاشة «تسلسل المستندات» لا تُقرأ إطلاقاً.
     *
     * والاحتياطُ يُبقي الصيغةَ القديمة حين لا يكون قسمُ `pos-session`
     * مضبوطاً بعد — فلا تتعطّل جلسةٌ لأن الإعداد لم يُزرع.
     */
    public static function generateSessionNumber(): string
    {
        try {
            $number = next_document_number('pos-session');

            if ($number !== '' && $number !== null) {
                return $number;
            }
        } catch (\Throwable $e) {
            // يسقط إلى الصيغة القديمة أدناه
        }

        $date = now()->format('Ymd');
        $lastSession = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastSession ? (intval(substr($lastSession->session_number, -3)) + 1) : 1;

        return $date.sprintf('%03d', $sequence);
    }

    // نطاقات
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * الجلساتُ **المفتوحة**: النشطةُ والمعلَّقة معاً.
     *
     * ═══════════ الفرقُ الذي أوقع العطل ═══════════
     *
     * `active()` تعني «تبيع الآن». و«مفتوحة» تعني «عهدتُها في يد صاحبها
     * ولم تُسلَّم بعد» — والمعلَّقةُ كذلك: درجُها لم يُعَدّ، ورصيدُها لم
     * يُسلَّم، وفواتيرُها قائمة. تعليقُها إيقافُ بيعٍ لا تسليمُ عهدة.
     *
     * وكان مانعُ «جلسةٍ ثانية للمستخدم» يفحص `active()` وحدها — فمن
     * علّق جلستَه فتح أخرى، وبقيت الأولى عهدةً مُعلَّقةً لا تُحصى ولا
     * تُقفل. وكذلك مانعُ الجهاز: درجٌ واحدٌ بجلستين مفتوحتين.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_SUSPENDED]);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }

    /** أُقفلت وتنتظر تأكيد المشرف. */
    public function scopePendingReview($query)
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /* ═══════════════ العلاقات المضافة ═══════════════ */

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function accountingPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cashMovements()
    {
        return $this->hasMany(PosCashMovement::class, 'session_id');
    }

    public function payments()
    {
        return $this->hasMany(PosOrderPayment::class, 'session_id');
    }

    public function heldOrders()
    {
        return $this->hasMany(PosHeldOrder::class, 'session_id');
    }

    /* ═══════════════ التحقق من صلاحيات العمليات ═══════════════ */

    public function canBeResumed()
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * أيمكن إقفالُ هذه الجلسة؟
     *
     * كانت النشطةَ وحدها — والمعلَّقةُ لا تُقفل. فمن علّق جلستَه وقع في
     * طريقٍ مسدود: لا يُقفلها، ولا يفتح غيرَها (بعد سدّ ثغرة `open()`).
     * وعهدتُه معلَّقةٌ إلى الأبد.
     *
     * والتعليقُ إيقافُ بيعٍ لا حالةٌ نهائية — فالإقفالُ منه مشروع.
     */
    public function canBeClosed()
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_SUSPENDED], true);
    }

    /** تنتظر تأكيداً — أُقفلت ولم تُعتمد بعد. */
    public function needsConfirmation(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /**
     * البيعُ يُقفل بإقفال الجلسة — لا بحالةٍ واحدة.
     *
     * كانت الشيفرة تفحص `status === 'active'` في اثني عشر موضعاً. وبإضافة
     * `pending_review` صار السؤالُ «أتقبل بيعاً؟» لا «أهي نشطة؟».
     */
    public function acceptsSales(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canBeDeleted()
    {
        return $this->status === self::STATUS_ACTIVE && $this->total_transactions === 0;
    }
}
