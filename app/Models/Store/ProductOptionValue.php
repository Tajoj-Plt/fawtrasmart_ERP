<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * قيمة خيار منتج (مثال: أحمر، أزرق — لخيار "اللون").
 *
 * انظر تعليق ProductOption للسياق العام لهذا النظام.
 */
class ProductOptionValue extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'option_id',
        'value',
        'label_ar',
        'label_en',
        'color_code',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
        'created_at' => 'datetime',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'option_id');
    }

    /** المتغيرات التي تحمل هذه القيمة — عبر جدول الربط variant_option_values. */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'variant_option_values', 'option_value_id', 'variant_id');
    }

    /** القيمة كما تُعرض للعميل — العربية أولاً، ثم الإنجليزية، ثم القيمة الخام. */
    public function displayLabel(): string
    {
        return $this->label_ar ?: ($this->label_en ?: $this->value);
    }
}
