<?php

namespace Modules\Client\Reservations\Models;

use App\Models\AccountingPeriod;
use App\Models\Client;
use App\Models\Concerns\BelongsToAccountingPeriod;
use App\Models\Concerns\HasDocumentCurrency;
use App\Models\Concerns\StampsOwnerContext;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TaxSitting;
use App\Models\User;
use App\Support\DataScope;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Client\Reservations\Enums\ReservationStatus;

/**
 * مستند الحجز.
 *
 * الجدول اسمه `reservations` منذ هجرة تصحيح التسمية — وكان `bookings`
 * بينما `reservations` جدولُ الإعدادات. راجع
 * `2026_09_08_000001_rename_reservation_tables`.
 *
 * ═══════════ السِّمات الأربع ولمَ كلٌّ منها ═══════════
 *
 * `StampsOwnerContext`        الفرعُ والموظّفُ والمُنشئ تُختَم لحظةَ الإنشاء.
 *                             الحجزُ يُنشأ من الشاشة ومن التقويم ومن الـAPI
 *                             ومن نقطة البيع — ووضعُ الختم في متحكّمٍ واحد
 *                             يعني حجوزاً بلا فرعٍ من الطرق الأخرى.
 *
 * `BelongsToBranch`           التصفيةُ عند القراءة: `forBranch()` تشمل
 *                             العقدةَ وما تحتها في شجرة الفروع.
 *
 * `BelongsToAccountingPeriod` الفترةُ تُختَم من `reservation_date` وتُعاد
 *                             نسبتُها إن تغيّر التاريخ.
 *
 * `HasDocumentCurrency`       العملةُ تُملأ مرّةً ثمّ تُجمَّد.
 *
 * ═══════════ `employee_id` يشير إلى `employees` ═══════════
 *
 * لا إلى `users` كما كان النموذجُ القديم `Booking`. سؤالُ التوفّر يمرّ على
 * دوام الموظف وإجازاته، وكلاهما في سجلّ الموظف لا في حساب المستخدم.
 *
 * @property ReservationStatus $status
 */
class Reservation extends Model
{
    use BelongsToAccountingPeriod;
    use BelongsToBranch;
    use HasDocumentCurrency;
    use SoftDeletes;
    use StampsOwnerContext;

    protected $table = 'reservations';

    protected $fillable = [
        'code', 'branch_id', 'client_id', 'employee_id', 'product_id', 'service_id',
        'reservation_date', 'start_time', 'end_time', 'blocked_start', 'blocked_end', 'duration',
        'status', 'currency', 'exchange_rate', 'tax_id',
        'unit_price', 'subtotal', 'discount_value', 'discount_type', 'discount_amount',
        'tax_amount', 'total', 'paid_amount', 'remaining_amount', 'cancellation_fee',
        'payment_method_id', 'invoice_id', 'accounting_period_id',
        'notes', 'source',
        'confirmed_at', 'checked_in_at', 'started_at', 'completed_at', 'cancelled_at', 'no_show_at',
        'cancelled_by', 'cancellation_reason',
        'reschedule_count', 'rescheduled_from_date', 'rescheduled_from_start',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'status' => ReservationStatus::class,
        'reservation_date' => 'date',
        'duration' => 'integer',
        'exchange_rate' => 'decimal:6',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'cancellation_fee' => 'decimal:2',
        'reschedule_count' => 'integer',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'no_show_at' => 'datetime',
        'rescheduled_from_date' => 'date',
    ];

    /** التاريخُ الحاكم لفترة هذا المستند — لا `date` الافتراضي. */
    public function accountingDateColumn(): string
    {
        return 'reservation_date';
    }

    /**
     * يُختم ولا يُمنع: الحجزُ موعدٌ لا ترحيل — عربونُه ورسمُ إلغائه وردُّه
     * قيودٌ مستقلّة مختومةٌ ومحروسة. ومنعُه يوقف إعادةَ جدولة موعدٍ قديم أو
     * إلغاءه لأنّ شهرَه أُقفل، ولا شيءَ في الدفتر يتغيّر بذلك.
     */
    public function guardsAccountingPeriod(): bool
    {
        return false;
    }

    // ════════════════════ العلاقات ════════════════════

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /** سجلّ الموظف — لا حساب المستخدم. انظر ترويسة الصنف. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ReservationService::class, 'service_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(TaxSitting::class, 'tax_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function accountingPeriodRelation(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReservationStatusHistory::class, 'reservation_id')->latest('id');
    }

    /** دفترُ حركات المال على الحجز — قبضٌ وردٌّ ورسمٌ وتسوية. */
    public function payments(): HasMany
    {
        return $this->hasMany(ReservationPayment::class, 'reservation_id')->latest('id');
    }

    // ════════════════════ القفل ════════════════════

    /**
     * يقفل صفَّ الحجز حتى نهاية المعاملة، ويُحدّث هذا الكائنَ بما في القاعدة.
     *
     * كلُّ فحصٍ ماليّ يقرأ رقماً ثمّ يكتب بناءً عليه («المتبقّي 100 إذن
     * تُقبل دفعة 100»). وبلا قفلٍ ينجح طلبان متزامنان — نقرتان على زرّ
     * «تسجيل الدفعة» — في الفحص معاً ثمّ يكتبان معاً: دفعتان وقيدان
     * ومقبوضٌ يتجاوز الإجمالي. فالقفلُ **قبل** الفحص، والفحصُ يقرأ ما خلفه.
     *
     * يُنادى داخل `DB::transaction` وحدَها — خارجها يُحرَّر القفلُ فوراً.
     */
    public function lockForWrite(): static
    {
        $fresh = static::withoutGlobalScopes()
            ->whereKey($this->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $this->setRawAttributes($fresh->getAttributes(), true);

        return $this;
    }

    // ════════════════════ النطاقات ════════════════════

    /**
     * الحجوزاتُ الشاغلةُ للمواعيد وحدها.
     *
     * الملغى ومن لم يحضر لا يشغلان شيئاً — وإلّا بقي الموعدُ محجوزاً بحجزٍ
     * ميّت. القاعدةُ معرَّفةٌ مرّةً في `ReservationStatus::occupiesSlot()`.
     */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereIn('status', ReservationStatus::occupyingValues());
    }

    public function scopeOnDate(Builder $query, $date): Builder
    {
        return $query->whereDate('reservation_date', Carbon::parse($date)->toDateString());
    }

    public function scopeBetweenDates(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('reservation_date', [
            Carbon::parse($from)->toDateString(),
            Carbon::parse($to)->toDateString(),
        ]);
    }

    /** الحجوزاتُ القادمة — من الآن فصاعداً وبحالةٍ حيّة. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->occupying()->whereDate('reservation_date', '>=', now()->toDateString());
    }

    /**
     * الحجوزاتُ المرئيّة للمستخدم الحاليّ — نطاقُ `DataScope` على الحجز.
     *
     * الموضعُ الوحيد الذي يعرف كيف يُقصّ الحجزُ بالنطاق: قائمةُ الحجوزات
     * وشاشاتُها (`ScopesReservations`) وشاشةُ فاتورة المبيعات التي تُفتح
     * بـ`?from_reservation=` تقرآن منه معاً — فلا يفتح أحدٌ من شاشة الفاتورة
     * حجزاً لا تعرضه له قائمتُه.
     *
     * محورُ الأشخاص (`team`/`own`) يُقيَّد بعمود **الموظّف** لا بالمُنشئ:
     * الحجز يخصّ مقدّمَ الخدمة، ومن أدخله موظّفُ استقبالٍ قد لا يكون طرفاً.
     */
    public function scopeVisibleToUser(Builder $query): Builder
    {
        DataScope::constrainBranch($query, 'branch_id');

        if (DataScope::isPeopleAxis()) {
            DataScope::constrainOwner($query, 'employee_id');
        }

        return $query;
    }

    // ════════════════════ مساعدات العرض ════════════════════

    /** لحظةُ بدء الحجز كاملةً — تاريخاً ووقتاً معاً. */
    public function getStartsAtAttribute(): ?Carbon
    {
        if (! $this->reservation_date || ! $this->start_time) {
            return null;
        }

        return Carbon::parse($this->reservation_date->toDateString().' '.$this->normalizeTime($this->start_time));
    }

    public function getEndsAtAttribute(): ?Carbon
    {
        if (! $this->reservation_date || ! $this->end_time) {
            return null;
        }

        return Carbon::parse($this->reservation_date->toDateString().' '.$this->normalizeTime($this->end_time));
    }

    /**
     * الوقتُ يعود من MySQL نصّاً `HH:MM:SS` ومن SQLite قد يعود كما كُتب.
     * التطبيعُ هنا فلا يفترق تفسيرُ اللحظة بين القاعدتين.
     */
    protected function normalizeTime($value): string
    {
        $value = (string) $value;

        return strlen($value) === 5 ? $value.':00' : $value;
    }

    /** هل بقي على العميل شيء؟ */
    public function getIsFullyPaidAttribute(): bool
    {
        return (float) $this->remaining_amount <= 0.009;
    }

    /**
     * ما يجوز ردُّه للعميل: المقبوضُ ناقصَ رسم الإلغاء المقتطَع منه.
     *
     * الرسمُ لا يُنقص `paid_amount` — المالُ قُبض فعلاً — لكنّه نُقل من
     * التزام العرابين إلى الإيراد. فردُّ المقبوض كلِّه بعده يُخرج من
     * العرابين ما لم يبقَ فيها، ويتركها مدينةً بقدر الرسم.
     */
    public function refundableAmount(): float
    {
        return round(max(0.0, (float) $this->paid_amount - (float) $this->cancellation_fee), 2);
    }

    /**
     * هل للحجز فاتورةٌ **قائمة**؟
     *
     * من الفاتورة نفسِها لا من `invoice_id` وحده: فاتورةٌ حُذفت من القاعدة
     * تترك المعرّفَ معلَّقاً، فيبقى الحجزُ «مفوتَراً» لا يُلغى ولا يُحذف ولا
     * يُعدَّل سعرُه — وزرُّ «تحويل إلى فاتورة» معروضٌ في الوقت نفسه. الفاتورةُ
     * الغائبةُ تعني «غيرُ مفوتَر»، والتحويلُ يربطه بفاتورةٍ جديدة.
     *
     * والعلاقةُ تُحمَّل مرّةً وتبقى — فلا استعلامَ في كلّ نداء.
     */
    public function isInvoiced(): bool
    {
        if (! $this->invoice_id) {
            return false;
        }

        return $this->invoice !== null;
    }

    /**
     * هل يجوز «تحويل إلى فاتورة» الآن؟
     *
     * عميلٌ وخدمةٌ، وحالةٌ مؤكَّدةٌ أو بعدها ({@see ReservationStatus::isInvoiceable()})،
     * وبلا فاتورةٍ قائمة. القاعدةُ واحدةٌ لزرّ الشاشة ولحارس `toInvoice`
     * ولشاشة الفاتورة التي تُفتح بـ`?from_reservation=` وتربط عند الحفظ —
     * فلا يُعرَض زرٌّ يرفضه الحفظ، ولا يُفوتَر بطلبٍ معدَّل ما لا يُفوتَر بالزرّ.
     */
    public function canBeInvoiced(): bool
    {
        return $this->client_id
            && $this->product_id
            && $this->status->isInvoiceable()
            && ! $this->isInvoiced();
    }

    /**
     * لماذا لا يُفوتَر؟ — رسالةٌ للمستخدم، أو `null` إن جاز التحويل.
     */
    public function invoiceBlockReason(): ?string
    {
        return match (true) {
            $this->isInvoiced() => null,
            ! $this->client_id => __('reservations.errors.invoice_needs_client'),
            ! $this->product_id => __('reservations.errors.invoice_needs_service'),
            ! $this->status->isInvoiceable() => __('reservations.errors.invoice_bad_status', ['status' => $this->status->label()]),
            default => null,
        };
    }

    /**
     * هل يُقبَض على الحجز من شاشته؟
     *
     * لا بعد الفاتورة: الذمّةُ انتقلت إلى الفاتورة وحساب العميل، ودفعتُها
     * تُسجَّل من شاشة دفعات المبيعات كأيّ فاتورة. ولا على حجزٍ ملغى أو
     * مرفوض أو لم يحضر صاحبُه: قبضٌ عليه عربونٌ لا خدمةَ تُقفله.
     */
    public function acceptsPayments(): bool
    {
        return ! $this->isInvoiced()
            && ! in_array($this->status, [ReservationStatus::CANCELLED, ReservationStatus::REJECTED, ReservationStatus::NO_SHOW], true);
    }

    /**
     * ملفُّ حجز الخدمة — من المنتج (هويّةُ الخدمة)، وإلّا الملفُّ القديم المرتبط.
     *
     * الحجوزُ السابقة للتحويل تحمل `service_id` وحده؛ والجديدةُ تحمل
     * `product_id` دائماً و`service_id` حين يكون للمنتج ملفٌّ محفوظ.
     */
    public function bookingProfile(): ?ReservationService
    {
        if ($this->product_id && $this->product) {
            return ReservationService::profileFor($this->product);
        }

        return $this->service;
    }

    /** اسمُ الخدمة المعروض — المنتجُ أوّلاً. */
    public function getServiceNameAttribute(): string
    {
        return (string) ($this->product?->name ?? $this->service?->displayName() ?? '—');
    }

    /** اسمُ العميل المعروض — `Client::$name` فارغٌ دائماً، والاسمُ في `display_name`. */
    public function getClientNameAttribute(): string
    {
        return (string) ($this->client?->display_name ?? '—');
    }
}
