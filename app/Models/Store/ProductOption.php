<?php

namespace App\Models\Store;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * خيار منتج (مثال: اللون، المقاس) — مملوكٌ للمنتج مباشرةً، لا كتالوجٌ عامّ.
 *
 * كل منتجٍ يعرّف خياراته بنفسه (`product_id` هنا، لا جدول ربطٍ وسيط)،
 * خلافاً لنظام الخصائص العام السابق. البنية مُقتبَسة من قاعدة بيانات CRM
 * مرجعية (product_options/product_option_values/product_variants/
 * variant_option_values) — انظر ملف الترحيل
 * 2026_08_30_230000_create_product_options_and_variants_tables.php.
 */
class ProductOption extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'name',
        'label_ar',
        'label_en',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class, 'option_id')->orderBy('position');
    }

    /** الاسم كما يُعرض للعميل — العربية أولاً، ثم الإنجليزية، ثم المفتاح الخام. */
    public function displayLabel(): string
    {
        return $this->label_ar ?: ($this->label_en ?: $this->name);
    }
}
