<?php

namespace Modules\Client\Reservations\Services;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Client\Reservations\Exceptions\ReservationException;

/**
 * مصدرُ الخدمات القابلة للحجز — **جدول المنتجات نفسه**، النوعُ «خدمي».
 *
 * ═══════════ لماذا لا كتالوج خدماتٍ مستقلّ؟ ═══════════
 *
 * المنتجُ الخدمي يحمل أصلاً الاسمَ والسعرَ والضريبةَ وحسابَ المبيعات وحالةَ
 * التفعيل، وتقرؤه الفواتير والتقارير والمتجر ونقطة البيع. وكتالوجٌ ثانٍ
 * للخدمات يعني اسمين وسعرين لشيءٍ واحد يفترقان بعد أوّل تعديل.
 *
 * فالخدمةُ في الحجز = `products.id` بنوع `ProductType::SERVICES`. وما لا
 * يعرفه المنتج — المدّة ووقتا التحضير والتنظيف والموظفون المؤهَّلون — يسكن
 * «ملفَّ حجزٍ» اختياريّاً له في `reservation_services` (صفٌّ واحد لكل منتج).
 * ومنتجٌ بلا ملفّ يُحجَز بالمدّة الافتراضية من إعدادات الحجوزات.
 *
 * ═══════════ ولماذا `services` وحدَه لا `stocklessValues()`؟ ═══════════
 *
 * المنتجُ الرقمي بلا مخزونٍ أيضاً، لكنّه يُسلَّم تنزيلاً ولا يُؤدّى في موعد.
 */
class ServiceProductCatalog
{
    /** المنتجاتُ الخدمية المفعَّلة — ما يُعرض في منتقي الخدمة. */
    public static function query(): Builder
    {
        return Product::query()
            ->where('type', ProductType::SERVICES->value)
            ->active();
    }

    /**
     * قائمةُ الاختيار — بالأعمدة التي يحتاجها النموذج وحدها.
     *
     * @return Collection<int, Product>
     */
    public static function options(?string $term = null, int $limit = 300): Collection
    {
        return static::query()
            ->when(filled($term), function (Builder $q) use ($term) {
                $like = '%'.trim((string) $term).'%';
                // `sku` في `$fillable` المنتج لكنّه ليس عموداً في الجدول — فلا يُسأل عنه
                $q->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhere('barcode', 'like', $like));
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'sale_price', 'tax1', 'barcode']);
    }

    /**
     * المنتجُ الخدمي بمعرّفه — أو استثناءٌ يقول لماذا لا يُحجَز.
     *
     * الفحصُ هنا لا في قاعدة `exists:` وحدها: `exists:products,id` تقبل سلعةً
     * مخزنية، وحجزُ سلعةٍ يُنتج في الفاتورة بنداً يصرف إذنَ مستودعٍ لموعد.
     */
    public static function resolve($productId): Product
    {
        $product = Product::query()->find($productId);

        if (! $product) {
            throw ReservationException::because(__('reservations.errors.service_missing'));
        }

        $type = $product->type instanceof ProductType ? $product->type : ProductType::from_value($product->type);

        if ($type !== ProductType::SERVICES) {
            throw ReservationException::because(__('reservations.errors.product_not_service', ['product' => $product->name]));
        }

        if (method_exists($product, 'isActive') ? ! $product->isActive() : false) {
            throw ReservationException::because(__('reservations.errors.service_inactive', ['service' => $product->name]));
        }

        return $product;
    }
}
