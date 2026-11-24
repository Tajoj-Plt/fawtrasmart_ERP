<?php

namespace Modules\RentalManagement\Models;

use App\Models\Account;
use App\Models\StoreHouse;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Modules\RentalManagement\Services\PropertyAccountService;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class Building extends RealEstateModel
{
    use SoftDeletes;

    protected $table = 're_buildings';

    protected $casts = [
        'deed_date' => 'date',
    ];

    /** حساب المبنى الرئيسي في دليل الحسابات يولد معه — من أيّ مسارٍ أُنشئ. */
    protected static function booted(): void
    {
        static::created(fn (self $building) => app(PropertyAccountService::class)->ensureBuildingAccount($building));
        static::updated(fn (self $building) => app(PropertyAccountService::class)->syncBuilding($building));

        static::deleted(function (self $building) {
            $service = app(PropertyAccountService::class);
            $building->units()->withTrashed()->get()->each(fn (Unit $unit) => $service->release($unit));
            $service->release($building);
        });
    }

    public function complex()
    {
        return $this->belongsTo(Complex::class, 'complex_id');
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id')->withTrashed();
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /** مستودع المبنى من مستودعات المخزون (قطع الصيانة والمواد). */
    public function storeHouse()
    {
        return $this->belongsTo(StoreHouse::class, 'store_house_id');
    }

    /** الخزينة الافتراضية للمبنى — تُقترح تلقائياً عند تسجيل دفعةٍ لأيّ وحدةٍ فيه. */
    public function defaultTreasuryAccount()
    {
        return $this->belongsTo(Account::class, 'default_treasury_account_id');
    }

    /** الحساب الرئيسي للمبنى — تحته حسابات وحداته. */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function floors()
    {
        return $this->hasMany(Floor::class, 'building_id')->orderBy('number');
    }

    public function units()
    {
        return $this->hasMany(Unit::class, 'building_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'building_id');
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'building_id');
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class, 'building_id');
    }

    /** الوحدة التلقائية لعقارٍ وحيد الوحدة (فيلا، أرض، منشأة مستقلة). */
    public function autoUnit()
    {
        return $this->hasOne(Unit::class, 'building_id')->where('is_auto', true);
    }

    public function isSingleUnit(): bool
    {
        return V::isSingleUnit($this->entity_type);
    }

    /** الأرض بلا طوابق؛ وما عداها له طوابق. */
    public function hasFloors(): bool
    {
        return $this->entity_type !== 'land';
    }

    /** مملوكٌ للمنشأة؟ العمود `ownership` إن وُجد، وإلا «بلا مالك = مملوك». */
    public function isOwn(): bool
    {
        return ($this->getAttribute('ownership') ?: ($this->owner_id ? 'managed' : 'own')) === 'own';
    }

    /**
     * يطابق صفوف `re_floors` مع «عدد الطوابق» فلا يفترقان أبداً.
     *
     * - `$target = null`: يكتب العدد من الصفوف الموجودة فقط.
     * - رفعُ العدد يُنشئ الصفوف الناقصة (من الأرضي 0 صعوداً، متجاوزاً الأرقام المأخوذة).
     * - إنقاصُه يحذف أعلى الطوابق الخالية من الوحدات، ويُرفض إن لم تكفِ.
     * - الأرض لا طوابق لها: تُحذف صفوفها ويُصفَّر العدد.
     *
     * @return int عدد الطوابق بعد المطابقة
     */
    public function syncFloors(?int $target = null): int
    {
        if (! $this->hasFloors()) {
            Unit::withTrashed()->where('building_id', $this->id)->whereNotNull('floor_id')->update(['floor_id' => null]);
            Floor::where('building_id', $this->id)->delete();

            return $this->writeFloorsCount(0);
        }

        $floors = Floor::where('building_id', $this->id)->withCount('units')->orderBy('number')->get();
        $current = $floors->count();

        if ($target === null || $target === $current) {
            return $this->writeFloorsCount($current);
        }

        if ($target > $current) {
            $taken = $floors->pluck('number')->map(fn ($n) => (int) $n)->all();

            for ($number = 0; $current < $target; $number++) {
                if (in_array($number, $taken, true)) {
                    continue;
                }

                Floor::create(['building_id' => $this->id, 'number' => $number]);
                $current++;
            }

            return $this->writeFloorsCount($current);
        }

        $removable = $floors->where('units_count', 0)->sortByDesc('number')->take($current - $target);

        if ($removable->count() < $current - $target) {
            throw ValidationException::withMessages(['floors_count' => sprintf(
                'لا يمكن إنقاص عدد الطوابق إلى %d: في المبنى %d طوابق عليها وحدات. انقل وحداتها أو احذفها أولاً.',
                $target, $floors->where('units_count', '>', 0)->count()
            )]);
        }

        Floor::whereIn('id', $removable->pluck('id'))->delete();

        return $this->writeFloorsCount($target);
    }

    private function writeFloorsCount(int $count): int
    {
        if ((int) $this->floors_count !== $count) {
            $this->forceFill(['floors_count' => $count])->saveQuietly();
        }

        $this->unsetRelation('floors');

        return $count;
    }

    /**
     * الوحدة التلقائية لعقارٍ وحيد الوحدة: تُنشأ إن غابت، وتُحدَّث بما في
     * `$attributes` (المساحة، السعر، المواصفات) إن وُجدت. نوعها واستخدامها
     * يتبعان العقار دائماً.
     */
    public function ensureAutoUnit(array $attributes = []): ?Unit
    {
        if (! $this->isSingleUnit()) {
            return null;
        }

        $units = $this->units()->get();
        $unit = $units->firstWhere('is_auto', true) ?? ($units->count() === 1 ? $units->first() : null);

        if (! $unit && $units->isNotEmpty()) {
            throw ValidationException::withMessages(['entity_type' => 'في العقار أكثر من وحدة، فلا يكون عقاراً وحيد الوحدة.']);
        }

        $isNew = ! $unit;
        $unit ??= new Unit([
            'building_id' => $this->id, 'unit_no' => '1', 'occupancy_status' => 'vacant', 'vacant_since' => now()->toDateString(),
            'rent_period' => 'yearly', 'furnished_status' => 'unfurnished', 'unit_condition' => 'good', 'is_marketable' => true,
            'branch_id' => $this->branch_id,
        ]);

        $standaloneType = $attributes['unit_type'] ?? null;
        unset($attributes['unit_type']);

        $unit->fill($attributes);
        $unit->is_auto = true;
        $unit->floor_id = null;
        $unit->unit_type = match ($this->entity_type) {
            'villa' => 'villa',
            'land' => 'land',
            default => in_array($standaloneType, V::STANDALONE_UNIT_TYPES, true) ? $standaloneType
                : (in_array($unit->unit_type, V::STANDALONE_UNIT_TYPES, true) ? $unit->unit_type : 'warehouse'),
        };

        // وحدةٌ عليها عقدٌ سارٍ لا يتغيّر استخدامها من تحته
        if ($isNew || ! $unit->usage_type || ! $unit->activeContract()->exists()) {
            $unit->usage_type = $this->usage_type === 'mixed' ? ($unit->usage_type ?: 'residential') : $this->usage_type;
        }

        // مواصفات استخدامٍ لا تبقى على وحدةٍ من الاستخدام الآخر (ولا غرف لأرض)
        if ($unit->usage_type !== 'residential' || $unit->unit_type === 'land') {
            $unit->forceFill(['bedrooms' => 0, 'bathrooms' => 0, 'living_rooms' => 0, 'kitchens' => 0, 'furnished_status' => 'unfurnished']);
        }

        if ($unit->usage_type !== 'commercial') {
            $unit->forceFill(['frontage_m' => null, 'ceiling_height_m' => null, 'openings_count' => null, 'permitted_activity' => null]);
        }

        $unit->save();
        $this->unsetRelation('autoUnit');

        return $unit;
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() !== 'ar' && filled($this->name_en) ? $this->name_en : $this->name_ar;
    }

    public function getStatusMetaAttribute(): array
    {
        return $this->vocab(V::BUILDING_STATUSES, 'status');
    }
}
