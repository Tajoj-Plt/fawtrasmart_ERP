<?php

namespace Modules\RentalManagement\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\RentalManagement\Services\PropertyAccountService;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class Unit extends RealEstateModel
{
    use SoftDeletes;

    protected $table = 're_units';

    public const ACTIVE_CONTRACT_STATUSES = ['active', 'renewing'];

    protected $casts = [
        'availability_from' => 'date',
        'vacant_since' => 'date',
        'is_marketable' => 'boolean',
        'is_auto' => 'boolean',
    ];

    /** حساب الوحدة الفرعي يولد معها تحت حساب مبناها. */
    protected static function booted(): void
    {
        static::created(fn (self $unit) => app(PropertyAccountService::class)->ensureUnitAccount($unit));
        static::updated(fn (self $unit) => app(PropertyAccountService::class)->syncUnit($unit));
        static::deleted(fn (self $unit) => app(PropertyAccountService::class)->release($unit));
    }

    /** الحساب الفرعي الذي يُقيَّد عليه إيراد إيجار الوحدة. */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class, 'floor_id');
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 're_unit_amenity', 'unit_id', 'amenity_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'unit_id');
    }

    /**
     * العقد الساري (أو قيد التجديد) على الوحدة.
     *
     * الشرط داخل `ofMany` لا قبله: `->whereIn(status)->latestOfMany()` كان
     * يختار أحدث عقدٍ على الوحدة أيّاً كانت حالته ثم يطبّق الشرط، فإن كان
     * الأحدث ملغىً أو مسودةً عاد null مع وجود عقدٍ سارٍ أقدم.
     */
    public function activeContract()
    {
        return $this->hasOne(Contract::class, 'unit_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->whereIn('status', self::ACTIVE_CONTRACT_STATUSES));
    }

    /** عروضٌ لم تُغلق بعد (مسودة، نشط، موقوف). */
    public function openListings()
    {
        return $this->listings()->whereNotIn('status', array_merge(V::LISTING_FINAL_STATUSES, ['expired']));
    }

    /** طلبات صيانة لم تُقفل (مفتوحة، بانتظار، قيد التنفيذ). */
    public function openMaintenanceRequests()
    {
        return $this->maintenanceRequests()->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function getUsageLabelAttribute(): string
    {
        return V::label(V::UNIT_USAGE_TYPES, $this->getAttribute('usage_type'));
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'unit_id');
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'unit_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return V::label(V::UNIT_TYPES, $this->unit_type);
    }

    /** الإيجار الفعلي الجاري من العقد السارية — بدورية الوحدة (شهري/سنوي)، أو null إن لم تكن مؤجرة. */
    public function getCurrentRentAttribute(): ?float
    {
        $contract = $this->activeContract;

        if (! $contract) {
            return null;
        }

        return round($this->rent_period === 'monthly' ? (float) $contract->annual_value / 12 : (float) $contract->annual_value, 2);
    }

    public function getOccupancyMetaAttribute(): array
    {
        return $this->vocab(V::OCCUPANCY, 'occupancy_status');
    }

    /** «شقة 12 — برج النخيل» — ما يُعرض في القوائم المنسدلة. */
    public function getFullLabelAttribute(): string
    {
        return trim($this->type_label.' '.$this->unit_no.' — '.($this->building->name ?? ''), ' —');
    }

    /**
     * مسودّة عرضٍ عقاري من مواصفات الوحدة — ما يملأ نموذج العرض عند «تحويل
     * الوحدة إلى عرض».
     *
     * @return array{title:string, description:string, features:array<int,string>, images_count:int}
     */
    public function listingDraft(): array
    {
        $building = $this->building;
        $location = implode('، ', array_filter([$building?->district, $building?->city]));

        $specs = array_filter([
            $this->area_sqm ? 'المساحة '.(float) $this->area_sqm.' م²' : null,
            $this->bedrooms ? $this->bedrooms.' غرف نوم' : null,
            $this->bathrooms ? $this->bathrooms.' دورات مياه' : null,
            $this->living_rooms ? $this->living_rooms.' صالة' : null,
            $this->kitchens ? $this->kitchens.' مطبخ' : null,
        ]);

        $description = trim(implode("\n", array_filter([
            trim($this->type_label.' رقم '.$this->unit_no.' في '.($building?->name ?? '')).($location ? ' — '.$location : '').'.',
            $this->floor ? 'الطابق: '.($this->floor->name ?: $this->floor->number).'.' : null,
            $specs ? implode(' · ', $specs).'.' : null,
            $this->description,
        ])));

        $features = array_values(array_filter(array_merge([
            $this->furnished_status ? V::label(V::FURNISHED, $this->furnished_status) : null,
            $this->orientation ? 'واجهة '.V::label(V::ORIENTATIONS, $this->orientation) : null,
            $this->parking_count ? 'مواقف: '.$this->parking_count : null,
            $this->electricity_meter ? 'عداد كهرباء مستقل' : null,
            $this->water_meter ? 'عداد مياه مستقل' : null,
        ], $this->amenities->pluck('name')->all())));

        return [
            'title' => $this->full_label,
            'description' => $description,
            'features' => $features,
            'images_count' => count($this->filePaths('images')),
        ];
    }

    /**
     * يحرّر رقم وحدةٍ تحمله وحدةٌ محذوفة في المبنى نفسه.
     *
     * الفهرس الفريد `re_units_building_id_unit_no_unique` يشمل الصفوف المحذوفة
     * ناعماً، فإعادة إنشاء وحدةٍ برقم محذوفةٍ كانت تسقط بخطأ 500. المحذوفة
     * يُلحق برقمها «~محذوفة-المعرّف» فيتحرّر الرقم ويبقى أصلها مقروءاً.
     */
    public static function releaseTrashedNumber(int $buildingId, string $unitNo, ?int $exceptId = null): void
    {
        static::onlyTrashed()->where('building_id', $buildingId)->where('unit_no', $unitNo)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get(['id', 'unit_no'])
            ->each(function (self $trashed) {
                $suffix = '~محذوفة-'.$trashed->id;

                static::onlyTrashed()->where('id', $trashed->id)
                    ->update(['unit_no' => mb_substr($trashed->unit_no, 0, 50 - mb_strlen($suffix)).$suffix]);
            });
    }

    /** يكتب حالة الإشغال ويؤرّخ بداية الشغور — أساسُ «شاغرة طويلاً». */
    public function markOccupancy(string $status): void
    {
        $this->occupancy_status = $status;
        $this->vacant_since = $status === 'vacant' ? ($this->vacant_since ?: now()->toDateString()) : null;
        $this->save();
    }
}
