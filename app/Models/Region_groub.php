<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;

/**
 * تمثيل «مجموعة المنطقة» كعقدة في شجرة الفروع (جدول branches، is_territory = true).
 * parent_id يحدد الأب في الشجرة (مثلاً: فرع الرياض ← الشمال ← مجموعة ← حي ← منفذ).
 *
 * يجب أن يكون الجدول `branches` (لا جدول region_groubs المنفصل) حتى لا يُطبَّق النطاق على عمود غير موجود.
 */
class Region_groub extends Branch
{
    use HasFactory;

    protected $table = 'branches';

    protected $fillable = [
        'name',
        'code',
        'phone',
        'mobile',
        'address1',
        'address2',
        'is_main',
        'city',
        'status',
        'region',
        'country',
        'work_hours',
        'description',
        'credit_limit',
        'location',
        'parent_id',
        'directions_id',
        'is_territory',
        'territory_office_id',
        'branch_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('territory', function (Builder $builder) {
            $builder->where($builder->getModel()->getTable() . '.is_territory', true);
        });

        static::creating(function (Region_groub $model) {
            $model->is_territory = true;
            if (empty($model->code)) {
                $model->code = 'TR-' . uniqid('', true);
            }
            $parentId = $model->parent_id ?? $model->attributes['parent_id'] ?? null;
            $parent = $parentId ? Branch::withoutGlobalScopes()->find($parentId) : null;
            if ($parent) {
                $model->phone = $model->phone ?: ($parent->phone ?: '-');
                $model->mobile = $model->mobile ?: ($parent->mobile ?: '-');
                $model->address1 = $model->address1 ?: ($parent->address1 ?: '-');
                $model->city = $model->city ?: ($parent->city ?: '-');
                $model->country = $model->country ?: ($parent->country ?: '-');
                $model->region = $model->region ?? $parent->region;
                $model->address2 = $model->address2 ?? $parent->address2;
                $model->status = $model->status ?? $parent->status ?? 0;
            }
            if (empty($model->territory_office_id) && $parent) {
                $model->territory_office_id = $parent->is_territory
                    ? $parent->territory_office_id
                    : $parent->id;
            }
        });
    }

    protected static function newFactory()
    {
        return \Database\Factories\RegionGroubFactory::new();
    }

    /**
     * كل عقد المناطق المرتبطة بفرع محاسبي (territory_office_id).
     */
    public static function territoryIdsUnderBranch(int $branchId): Collection
    {
        return static::query()->where('territory_office_id', $branchId)->pluck('id')->values();
    }

    /** مطابق قديماً: branch_id = الفرع المحاسبي الذي تتبع له المنطقة */
    public function setBranchIdAttribute(?int $value): void
    {
        $this->attributes['territory_office_id'] = $value;
    }

    public function getBranchIdAttribute(): ?int
    {
        return isset($this->attributes['territory_office_id'])
            ? (int) $this->attributes['territory_office_id']
            : null;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'territory_office_id');
    }

    public function parentNode()
    {
        return $this->belongsTo(Branch::class, 'parent_id');
    }

    public function direction()
    {
        return $this->belongsTo(Direction::class, 'directions_id');
    }

    public function neighborhoods()
    {
        return $this->hasMany(Neighborhood::class, 'region_id');
    }
}
