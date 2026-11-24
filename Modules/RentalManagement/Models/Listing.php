<?php

namespace Modules\RentalManagement\Models;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class Listing extends RealEstateModel
{
    use SoftDeletes;

    protected $table = 're_listings';

    protected $casts = [
        'publish_date' => 'date',
        'expiry_date' => 'date',
        'features' => 'array',
        'area_sqm' => 'decimal:2',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function viewings()
    {
        return $this->hasMany(Viewing::class, 'listing_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'listing_id');
    }

    /**
     * حالات **الطلب** بتسمياتها هو — على القيم المخزَّنة نفسها: الطلب لا «يُؤجَّر»
     * ولا «يُشترى»، بل يُلبّى.
     */
    public const REQUEST_STATUSES = [
        'draft' => ['label' => 'جديد', 'tone' => 'info'],
        'active' => ['label' => 'نشط', 'tone' => 'success'],
        'paused' => ['label' => 'قيد المتابعة', 'tone' => 'warning'],
        'rented' => ['label' => 'تمت تلبيته', 'tone' => 'success'],
        'purchased' => ['label' => 'تمت تلبيته', 'tone' => 'success'],
        'expired' => ['label' => 'منتهي', 'tone' => 'danger'],
        'closed' => ['label' => 'مغلق', 'tone' => 'secondary'],
    ];

    public const REQUEST_OPEN = ['draft', 'active', 'paused'];

    public function getStatusMetaAttribute(): array
    {
        return $this->vocab($this->listing_kind === 'request' ? self::REQUEST_STATUSES : V::LISTING_STATUSES, 'status');
    }

    public function getKindMetaAttribute(): array
    {
        return $this->vocab(V::LISTING_KINDS, 'listing_kind');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /** «الطلب» أو «العرض» — للرسائل، فلا يُسمّى الطلب عرضاً. */
    public function getNounAttribute(): string
    {
        return $this->listing_kind === 'request' ? 'الطلب' : 'العرض';
    }

    /**
     * الحالات الجائزة لهذا السجلّ: «تم الإيجار» لعرض الإيجار وحده، و«تم الشراء»
     * لعرض البيع وحده، والطلب لا يقبل أيّاً منهما (يُغلق حين يُلبّى).
     *
     * @return array<string, array{label:string, tone:string}>
     */
    public function allowedStatuses(): array
    {
        if ($this->listing_kind === 'request') {
            $statuses = self::REQUEST_STATUSES;
            // «تمت تلبيته» تُخزَّن بقيمة غرض الطلب: إيجار ← rented، شراء ← purchased
            unset($statuses[$this->listing_type === 'sale' ? 'rented' : 'purchased']);

            return $statuses;
        }

        $statuses = V::LISTING_STATUSES;

        if ($this->listing_type !== 'rent') {
            unset($statuses['rented']);
        }

        if ($this->listing_type !== 'sale') {
            unset($statuses['purchased']);
        }

        return $statuses;
    }

    /** قيمة «تمت تلبيته» المخزَّنة لهذا الطلب. */
    public function fulfilledStatus(): string
    {
        return $this->listing_type === 'sale' ? 'purchased' : 'rented';
    }

    /** أيُعدّ الطلب مفتوحاً (لم يُلبَّ ولم يُغلق)؟ */
    public function isOpenRequest(): bool
    {
        return $this->listing_kind === 'request' && in_array($this->status, self::REQUEST_OPEN, true);
    }

    /** وصف ما يطلبه العميل: «شقة للإيجار — الرياض، النرجس». */
    public function getRequestSummaryAttribute(): string
    {
        return trim(collect([
            $this->property_type ? V::label(V::UNIT_TYPES, $this->property_type) : 'عقار',
            $this->listing_type === 'sale' ? 'للشراء' : 'للإيجار',
        ])->implode(' ').($this->city || $this->district ? ' — '.collect([$this->city, $this->district])->filter()->implode('، ') : ''));
    }

    /** اسم صاحب الطلب: العميل المسجَّل أو الزائر. */
    public function getRequesterNameAttribute(): string
    {
        return $this->client->display_name ?? ($this->contact_name ?: '—');
    }

    public function getIsRequestAttribute(): bool
    {
        return $this->listing_kind === 'request';
    }

    public function getTypeMetaAttribute(): array
    {
        return $this->vocab(V::LISTING_TYPES, 'listing_type');
    }
}
