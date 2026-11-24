<?php

namespace Modules\RentalManagement\Models;

use App\Models\Client;
use App\Models\TaxSitting;
use App\Models\User;
use App\Support\CurrencySettings;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class Contract extends RealEstateModel
{
    use SoftDeletes;

    /** أنواع العقود المتاحة للإنشاء — «بيع» مخفيّ حتى يُبنى منطقه المستقل. */
    public const CREATABLE_TYPES = ['residential_lease', 'commercial_lease'];

    /** حالاتٌ أُغلق فيها العقد: لا يُعاد بناء جدوله ولا يُعدَّل. */
    public const CLOSED_STATUSES = ['cancelled', 'expired', 'terminated'];

    /** حالاتٌ ما زالت ديونها تُحصَّل: الساري، والمنتهي والمُنهى بما استحقّ قبل إغلاقهما. */
    public const COLLECTIBLE_STATUSES = ['active', 'renewing', 'expired', 'terminated'];

    /** حالاتٌ يعرّفها هذا النموذج إن غابت عن المفردات المشتركة. */
    private const EXTRA_STATUSES = [
        'terminated' => ['label' => 'مُنهى', 'tone' => 'dark'],
    ];

    protected $table = 're_contracts';

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'terminated_at' => 'date',
        'signed_at' => 'datetime',
        'approved_at' => 'datetime',
        'tax_included' => 'boolean',
        'is_historical' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Client::class, 'tenant_client_id');
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id')->withTrashed();
    }

    /** ضريبة العقد كما اختيرت من «إعدادات الضريبة» — النسبة والنوع محفوظان لقطةً في العقد. */
    public function tax()
    {
        return $this->belongsTo(TaxSitting::class, 'tax_id');
    }

    /** عقود مالكٍ: المسجَّل عليه المالك، أو عقدٌ بلا مالكٍ صريح على مبنىً يملكه. */
    public function scopeOfOwner($query, $ownerId)
    {
        return $query->where(fn ($w) => $w->where('owner_id', $ownerId)->orWhere(fn ($x) => $x->whereNull('owner_id')
            ->whereIn('building_id', Building::withTrashed()->where('owner_id', $ownerId)->select('id'))));
    }

    /** مالك العقد: المُسجَّل عليه، وإلا مالك مبنى وحدته. */
    public function effectiveOwner(): ?Owner
    {
        return $this->owner ?? $this->unit?->building?->owner ?? $this->building?->owner;
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id')->withTrashed();
    }

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id')->withTrashed();
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    public function previous()
    {
        return $this->belongsTo(self::class, 'previous_contract_id');
    }

    public function renewedInto()
    {
        return $this->hasOne(self::class, 'previous_contract_id');
    }

    public function terms()
    {
        return $this->hasMany(ContractTerm::class, 'contract_id')->orderBy('sort');
    }

    public function amendments()
    {
        return $this->hasMany(ContractAmendment::class, 'contract_id')->latest();
    }

    public function renewals()
    {
        return $this->hasMany(ContractRenewal::class, 'source_contract_id')->latest();
    }

    public function schedules()
    {
        return $this->hasMany(PaymentSchedule::class, 'contract_id')->orderBy('due_date')->orderBy('installment_no');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'contract_id')->latest('payment_date');
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'contract_id')->latest('id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getStatusMetaAttribute(): array
    {
        if (! isset(V::CONTRACT_STATUSES[$this->status]) && isset(self::EXTRA_STATUSES[$this->status])) {
            return self::EXTRA_STATUSES[$this->status];
        }

        return $this->vocab(V::CONTRACT_STATUSES, 'status');
    }

    /** تسمية حالةٍ بعينها — للفلاتر والعدّادات التي لا تملك نموذجاً. */
    public static function statusMeta(string $status): array
    {
        return V::CONTRACT_STATUSES[$status] ?? self::EXTRA_STATUSES[$status] ?? ['label' => $status, 'tone' => 'secondary'];
    }

    /** @return array<string, array{label:string, tone:string}> كل الحالات: المفردات المشتركة وما يضيفه هذا النموذج. */
    public static function statuses(): array
    {
        return V::CONTRACT_STATUSES + self::EXTRA_STATUSES;
    }

    public function getTypeLabelAttribute(): string
    {
        return V::label(V::CONTRACT_TYPES, $this->contract_type);
    }

    public function getDaysLeftAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->end_date, false);
    }

    /** عملة العقد المسجَّلة وقت إنشائه — وعملة النظام لما سبق العمود. */
    public function getCurrencyCodeAttribute(): string
    {
        return $this->currency ?: CurrencySettings::code();
    }

    /** مبلغٌ بعملة العقد المسجَّلة — لا بعملة النظام الحالية. */
    public function money($amount): string
    {
        $formatter = '\\Modules\\RentalManagement\\Support\\RealEstateMoney';

        if (class_exists($formatter) && method_exists($formatter, 'format')) {
            return (string) $formatter::format($amount, $this->currency);
        }

        return number_format((float) $amount, 2).' '.$this->currency_code;
    }

    /** «القيمة المضافة 15% — مضافة فوق القيمة» أو «بدون ضريبة». */
    public function getTaxLabelAttribute(): string
    {
        if ((float) $this->tax_rate <= 0) {
            return $this->tax_id && $this->tax ? $this->tax->name.' (0%)' : 'بدون ضريبة';
        }

        $rate = rtrim(rtrim(number_format((float) $this->tax_rate, 2), '0'), '.');
        $name = ($this->tax_id ? $this->tax?->name : null) ?: 'ضريبة';

        return sprintf('%s %s%% — %s', $name, $rate, $this->tax_included ? 'مشمولة في القيمة' : 'مضافة فوق القيمة');
    }

    /** معامل تحميل الضريبة على القيمة: 1.15 لضريبةٍ مضافة، و1 للمشمولة أو المعدومة. */
    public function taxFactor(): float
    {
        $rate = (float) $this->tax_rate / 100;

        return ($rate > 0 && ! $this->tax_included) ? 1 + $rate : 1.0;
    }

    /** إجمالي العقد شاملاً الضريبة — ما يساويه مجموع أقساط الإيجار. */
    public function getGrossTotalAttribute(): float
    {
        return round((float) $this->total_value * $this->taxFactor(), 2);
    }

    /** مقدار الضريبة داخل الإجمالي الشامل. */
    public function getTaxAmountAttribute(): float
    {
        $rate = (float) $this->tax_rate / 100;

        return $rate > 0 ? round($this->gross_total - $this->gross_total / (1 + $rate), 2) : 0.0;
    }

    /** «12 شهراً» أو «شهر و14 يوماً» أو «45 يوماً» — المدّة كما تُحسب بها القيمة. */
    public function getDurationLabelAttribute(): string
    {
        if (! $this->start_date || ! $this->end_date) {
            return '—';
        }

        $endExclusive = $this->end_date->copy()->addDay();
        $months = 0;

        while ($this->start_date->copy()->addMonthsNoOverflow($months + 1)->lte($endExclusive)) {
            $months++;
        }

        $days = (int) $this->start_date->copy()->addMonthsNoOverflow($months)->diffInDays($endExclusive);

        if ($months >= 1 && $days <= 1) {
            $days = 0;
        }

        return match (true) {
            $months === 0 => $days.' يوماً',
            $days === 0 => $months.' شهراً',
            default => $months.' شهراً و'.$days.' يوماً',
        };
    }

    /**
     * التأمين المحتجز فعلاً: المقبوض على هذا العقد + المرحَّل إليه من سابقه،
     * ناقصاً ما رُحِّل منه إلى عقد التجديد (فلا يُردّ مرّتين).
     */
    public function heldDeposit(): float
    {
        $paid = (float) $this->schedules()->where('kind', 'deposit')->reorder()->sum('amount_paid');
        $forwarded = (float) (self::withoutGlobalScopes()->whereNull('deleted_at')->where('previous_contract_id', $this->id)
            ->whereNotIn('status', ['cancelled'])->sum('deposit_carried'));

        return round(max(0, $paid + (float) $this->deposit_carried - $forwarded), 2);
    }

    /** ما يُحذف هو المسودة وحدها؛ ما عداها يُلغى ولا يُمحى. */
    public function isDeletable(): bool
    {
        return $this->status === 'draft' && ! $this->payments()->exists();
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval'], true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function outstanding(): float
    {
        // `toBase()`: الاسم `balance` مُلحِقٌ في PaymentSchedule، فقراءته عبر النموذج تُرجع صفراً دائماً
        return (float) $this->schedules()->where('status', '!=', 'cancelled')->reorder()->toBase()
            ->selectRaw('COALESCE(SUM(amount_due - amount_paid), 0) as outstanding_total')->value('outstanding_total');
    }
}
