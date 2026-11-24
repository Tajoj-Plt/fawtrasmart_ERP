<?php

namespace App\Models\Store;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * متغيّر منتج — تركيبة خيارات محدَّدة (مثال: أحمر + L) بسعرها ومخزونها
 * الخاصّين. انظر تعليق ProductOption للسياق العام لهذا النظام.
 *
 * `variant_options` لقطة JSON جاهزة للعرض المباشر بلا استعلامٍ إضافي
 * (مثال: {"اللون":"أحمر","المقاس":"L"})، بجانب `optionValues()` المُطبَّعة
 * عبر جدول الربط للاستعلام والفلترة الفعليين — كلاهما يُحدَّثان معاً عند
 * الحفظ، لا أحدهما بديلاً عن الآخر.
 */
class ProductVariant extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'variant_options',
        'image_url',
        'price_modifier',
        'override_price',
        'compare_at_price',
        'cost_price',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'variant_options' => 'array',
        'price_modifier' => 'decimal:2',
        'override_price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'variant_option_values', 'variant_id', 'option_value_id');
    }

    /**
     * السعر الفعلي لهذا المتغيّر: سعرٌ مُجاوَز صراحةً، وإلا سعر المنتج
     * الأساسي مضافاً إليه فرق السعر.
     */
    public function finalPrice(): float
    {
        if (! is_null($this->override_price)) {
            return (float) $this->override_price;
        }

        $basePrice = (float) ($this->product?->getFinalPrice() ?? $this->product?->sale_price ?? 0);

        return $basePrice + (float) $this->price_modifier;
    }

    public function inStock(): bool
    {
        return $this->is_active && $this->stock_quantity > 0;
    }

    /** لقطة القيم للعرض — من عمود variant_options مباشرةً، بلا استعلام علاقات. */
    public function displayLabel(): string
    {
        return collect($this->variant_options ?? [])
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode(' / ');
    }
}
