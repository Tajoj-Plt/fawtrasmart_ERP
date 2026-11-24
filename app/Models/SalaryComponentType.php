<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * نوع بند راتب من الكتالوج — بدل سكن / مواصلات / طبيعة عمل، أو استقطاع مسمّى.
 *
 * البند الحرّ (`salary_items`) يبقى كما هو؛ هذا الصفّ يصنّفه. علمُ
 * `is_insurable` يحدّد ما يدخل الأجر الخاضع للتأمينات عند الصرف.
 */
class SalaryComponentType extends Model
{
    public const CODE_HOUSING = 'housing';

    public const CODE_TRANSPORT = 'transport';

    public const CODE_NATURE_OF_WORK = 'nature_of_work';

    public const CODE_OVERTIME = 'overtime';

    public const CODE_END_OF_SERVICE = 'end_of_service';

    public const CODE_SOCIAL_INSURANCE = 'social_insurance';

    public const STATUS_ACTIVE = 1;

    public const STATUS_INACTIVE = 2;

    protected $fillable = [
        'code',
        'name',
        'kind',
        'is_insurable',
        'is_default',
        'is_system',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'kind' => 'integer',
        'is_insurable' => 'boolean',
        'is_default' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
        'status' => 'integer',
    ];

    /**
     * الأنواع المعيارية المزروعة. السكن والمواصلات افتراضيّان وخاضعان
     * للتأمينات؛ طبيعة العمل اختيارية وغير خاضعة.
     *
     * @return list<array<string, mixed>>
     */
    public static function seededRows(): array
    {
        return [
            [
                'code' => self::CODE_HOUSING,
                'name' => 'بدل سكن',
                'kind' => SalaryItem::TYPE_ADDITION,
                'is_insurable' => true,
                'is_default' => true,
                'is_system' => true,
                'sort_order' => 10,
                'status' => self::STATUS_ACTIVE,
            ],
            [
                'code' => self::CODE_TRANSPORT,
                'name' => 'بدل مواصلات',
                'kind' => SalaryItem::TYPE_ADDITION,
                'is_insurable' => true,
                'is_default' => true,
                'is_system' => true,
                'sort_order' => 20,
                'status' => self::STATUS_ACTIVE,
            ],
            [
                'code' => self::CODE_NATURE_OF_WORK,
                'name' => 'بدل طبيعة عمل',
                'kind' => SalaryItem::TYPE_ADDITION,
                'is_insurable' => false,
                'is_default' => false,
                'is_system' => true,
                'sort_order' => 30,
                'status' => self::STATUS_ACTIVE,
            ],
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalaryItem::class, 'component_type_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDefaults(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeInsurable(Builder $query): Builder
    {
        return $query->where('is_insurable', true);
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_system;
    }

    public function kindLabel(): string
    {
        return $this->kind === SalaryItem::TYPE_DEDUCTION ? 'مستقطع' : 'مستحق';
    }

    /**
     * رمز لاتيني فريد. الأسماء العربية لا تُنتج slug، فيُولَّد رمز ثابت.
     */
    public static function uniqueCode(string $name, ?string $requested = null, ?int $ignoreId = null): string
    {
        $base = Str::slug((string) ($requested !== null && $requested !== '' ? $requested : $name), '_');
        if ($base === '') {
            $base = 'comp_'.substr(sha1($name), 0, 10);
        }

        $code = $base;
        $i = 1;
        while (static::query()
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->where('code', $code)
            ->exists()) {
            $code = $base.'_'.$i++;
        }

        return $code;
    }
}
