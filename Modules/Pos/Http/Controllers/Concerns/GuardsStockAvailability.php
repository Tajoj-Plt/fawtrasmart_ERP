<?php

namespace Modules\Pos\Http\Controllers\Concerns;

use App\Enums\ProductType;
use App\Models\CompiledProducts;
use App\Models\GeneralSettings;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\ProductUnit;
use Illuminate\Support\Collection;

/**
 * لا يخرج من المخزن ما ليس فيه.
 *
 * ═════════════════════ أربعةُ ثقوبٍ في الحارس القديم ═════════════════════
 *
 * كان الفحصُ حلقةً على سطور الفاتورة، كلُّ سطرٍ يُقارَن بالرصيد وحدَه:
 *
 *     foreach ($validated['products'] as $productData) {
 *         $available = ProductDetails::where(...)->first()?->quantity ?? 0;
 *         if ($required > $available && ! $negativeAllowed) { return ...; }
 *     }
 *
 * **١. السطرُ لا الصنف.** والشاشةُ تسمح بإضافة الصنف الواحد في سطرين
 * (بوحدتين مختلفتين — وهو مقصودٌ، انظر `unique()` في `store()`). فصنفٌ
 * رصيدُه مئتان يُباع في سطرين بمئةٍ وخمسين: كلُّ سطرٍ يمرّ وحدَه،
 * والمجموعُ ثلاثُمئة. فيُخصم ثلاثُمئة من مئتين ويصير الرصيدُ سالباً —
 * والحارسُ يقول إنّه منع ذلك.
 *
 * **٢. المكوّناتُ تُفحص ولا تُجمَع.** المجمَّعُ الفوريُّ يستهلك مكوّناته،
 * وثلاثةُ سطورٍ من ساندويتشٍ واحد تستهلك ثلاثةَ أضعاف الخبز — والفحصُ
 * يقارن كلَّ سطرٍ بالرصيد الكامل.
 *
 * **٣. لا قفل.** بين القراءةِ والخصم فجوةٌ يمرّ منها جهازُ كاشيرٍ ثانٍ:
 * كلاهما يقرأ «متاحٌ عشرة»، وكلاهما يخصم عشرة. وهي الحالةُ التي يقع فيها
 * السالبُ فعلاً في ساعات الذروة، ولا تظهر في اختبارٍ يدوي.
 *
 * **٤. `return` داخل المعاملة.** الرفضُ كان `return response()->json(...)`
 * بعد `DB::beginTransaction()` وقبل `commit`/`rollBack` — فتُترك المعاملةُ
 * مفتوحةً، والأقفالُ معها، حتى يُغلق الاتّصال.
 *
 * ═════════════════════ القاعدة هنا ═════════════════════
 *
 *   **يُجمَع المطلوبُ لكل صنفٍ من الفاتورة كلِّها، ثم يُقارَن مرّةً واحدة،
 *   على صفوفٍ مقفلة.**
 *
 * وما يُجمَع هو **ما يُخصم فعلاً** لا ما يُذكر في السطر:
 *
 *   • الصنفُ العاديّ ⇐ كمّيتُه بالوحدة الأساسية.
 *   • المجمَّعُ غيرُ الفوري ⇐ نفسُه (`handleCompiledProduct` يخصمه هو،
 *     ومكوّناتُه تُفحص عنده ولا تُخصم).
 *   • المجمَّعُ الفوريُّ ⇐ **مكوّناتُه وحدها**: هو يُضاف ثم يُخصم في
 *     حركتين متعاكستين، فأثرُه على رصيده صفر.
 *   • الخدمةُ والرقميُّ ⇐ لا شيء، لا رصيدَ لهما أصلاً.
 *
 * ═════════════════════ والرسالةُ تجمع النقص كلَّه ═════════════════════
 *
 * الحارسُ القديم يردّ عند أوّل نقص. فكاشيرٌ بسلّةٍ فيها ثلاثةُ أصنافٍ ناقصة
 * يُصلح واحداً ويُردّ بالثاني ثم بالثالث. وجمعُها في رسالةٍ واحدة يوفّر
 * دورتين.
 */
trait GuardsStockAvailability
{
    /** يُقرأ مرّةً لكل طلب: إعدادٌ عامٌّ لا يتغيّر داخل الفاتورة الواحدة. */
    private ?bool $negativeStockAllowed = null;

    /**
     * أيُسمح بأن يهبط الرصيدُ تحت الصفر؟
     *
     * إعدادٌ عامٌّ في `general_settings` تعمل به وحداتُ النظام كلُّها —
     * فلا تنفرد نقطةُ البيع بقاعدةٍ تخالفه. ومطفأً (وهو الافتراض) لا يخرج
     * من المخزن ما ليس فيه.
     */
    protected function negativeStockAllowed(): bool
    {
        if ($this->negativeStockAllowed === null) {
            $this->negativeStockAllowed = (bool) (GeneralSettings::first()?->enable_negative_stock ?? 0);
        }

        return $this->negativeStockAllowed;
    }

    /**
     * الكمّياتُ التي ستُخصم فعلاً من المستودع — مجموعةً لكل صنف.
     *
     * @param  array<int, array{id:int|string, quantity:float|string, product_unit_id?:int|null}>  $lines
     * @param  Collection<int, Product>  $products  الأصنافُ المحمَّلة مسبقاً
     * @return array<int, float> معرّفُ الصنف ⇒ الكمّيةُ بالوحدة الأساسية
     */
    protected function stockDemandOf(array $lines, Collection $products): array
    {
        $demand = [];

        $add = function (int $productId, float $quantity) use (&$demand) {
            if ($quantity > 0) {
                $demand[$productId] = ($demand[$productId] ?? 0) + $quantity;
            }
        };

        foreach ($lines as $line) {
            $product = $products->firstWhere('id', $line['id']);

            if (! $product || ! ProductType::from_value($product->type)->tracksStock()) {
                continue;
            }

            $baseQuantity = $this->baseQuantityOf($line);

            $isCompiled = $product->type === 'compiled';
            $isInstant = $isCompiled && $product->compile_type === 'Instant';

            /*
                الفوريُّ لا يُخصم رصيدُه: يُضاف ثم يُخصم في حركتين
                متعاكستين (`handleInstantCompiledProduct`). فالمستهلَكُ
                مكوّناتُه هو.
            */
            if (! $isInstant) {
                $add((int) $product->id, $baseQuantity);
            }

            if ($isInstant) {
                foreach (CompiledProducts::where('compile_id', $product->id)->get() as $component) {
                    $add((int) $component->product_id, (float) $component->qyt * $baseQuantity);
                }
            }
        }

        return $demand;
    }

    /**
     * الكمّيةُ بالوحدة الأساسية = الكمّيةُ × معاملُ التحويل.
     *
     * ومعاملٌ صفريٌّ أو سالب يُعامَل واحداً: بياناتٌ فاسدةٌ في جدول الوحدات
     * لا يجوز أن تجعل المطلوبَ صفراً فيمرّ كلُّ شيء.
     *
     * @param  array{quantity:float|string, product_unit_id?:int|null}  $line
     */
    protected function baseQuantityOf(array $line): float
    {
        $quantity = (float) $line['quantity'];

        if (empty($line['product_unit_id'])) {
            return $quantity;
        }

        $productUnit = ProductUnit::with('categoryUnit')->find($line['product_unit_id']);

        if (! $productUnit || ! $productUnit->categoryUnit) {
            return $quantity;
        }

        $factor = (float) ($productUnit->categoryUnit->conversion_factor ?? 1);

        return $quantity * ($factor > 0 ? $factor : 1);
    }

    /**
     * الأرصدةُ الحاليةُ في مستودعٍ بعينه — **مقفلةً** حتى نهاية المعاملة.
     *
     * `lockForUpdate` هي الفرقُ بين حارسٍ يعمل وحارسٍ يبدو عاملاً: بدونها
     * يقرأ جهازان الرصيدَ نفسَه ويخصمان منه معاً.
     *
     * والصفوفُ المفقودة لا تعني خطأً بل رصيداً صفراً — صنفٌ لم يدخل هذا
     * المخزن قطّ لا صفَّ له في `product_details`.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, float>
     */
    protected function lockedStockOf(array $productIds, int $storeHouseId): array
    {
        if (! $productIds) {
            return [];
        }

        return ProductDetails::query()
            ->where('store_house_id', $storeHouseId)
            ->whereIn('product_id', $productIds)
            ->lockForUpdate()
            ->pluck('quantity', 'product_id')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();
    }

    /**
     * لا يمضي البيعُ إن نقص صنفٌ واحد — والرسالةُ تسمّي النقصَ كلَّه.
     *
     * @param  array<int, array{id:int|string, quantity:float|string, product_unit_id?:int|null}>  $lines
     * @param  Collection<int, Product>  $products
     *
     * @throws \RuntimeException
     */
    protected function assertStockAvailable(array $lines, int $storeHouseId, Collection $products): void
    {
        $demand = $this->stockDemandOf($lines, $products);

        if (! $demand) {
            return;
        }

        // الأرصدةُ تُقفل في الحالين: المنعُ يحتاجها، والسماحُ بالسالب يحتاج
        // القفلَ نفسَه لئلّا يتزاحم جهازان على الصفّ ذاته
        $available = $this->lockedStockOf(array_keys($demand), $storeHouseId);

        if ($this->negativeStockAllowed()) {
            return;
        }

        $shortages = [];

        foreach ($demand as $productId => $required) {
            $onHand = $available[$productId] ?? 0.0;

            // هامشُ التقريب: كسورُ الكمّيات المخزَّنة بأربع منازل لا يصحّ
            // أن تردّ بيعاً بفارق جزءٍ من عشرة آلاف
            if ($required - $onHand <= 0.0001) {
                continue;
            }

            $name = $products->firstWhere('id', $productId)?->name
                ?? Product::whereKey($productId)->value('name')
                ?? "#{$productId}";

            $shortages[] = sprintf(
                '«%s»: المطلوب %s والمتاح %s',
                $name,
                $this->formatQuantity($required),
                $this->formatQuantity($onHand)
            );
        }

        if ($shortages) {
            throw new \RuntimeException(
                'الكمّيةُ غيرُ كافية في مستودع نقطة البيع — '.implode('، ', $shortages).'.'
            );
        }
    }

    /**
     * الحارسُ الأخير، عند الخصم نفسِه.
     *
     * `assertStockAvailable()` تفحص الفاتورةَ كلَّها قبل أن يُكتب شيء، وهي
     * الحارسُ الذي يعطي الرسالةَ المفهومة. وهذا الفحصُ يقف على حافّة
     * `decrement()` مباشرةً — لأنّ الخصمَ يقع في ستّة مواضع (العاديُّ
     * والمجمَّعُ ومكوّناتُه)، وموضعٌ جديدٌ يُضاف غداً قد يُنسى تزويدُه
     * بالفحص الأوّل.
     *
     * فالأوّلُ يمنع بلطف، وهذا يمنع الوقوعَ أصلاً.
     *
     * @throws \RuntimeException
     */
    protected function assertCanDecrement($product, $productDetails, float $quantity): void
    {
        if ($this->negativeStockAllowed()) {
            return;
        }

        $onHand = (float) $productDetails->quantity;

        if ($quantity - $onHand <= 0.0001) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'الكمّيةُ غيرُ كافية في مستودع نقطة البيع لـ«%s»: المطلوب %s والمتاح %s.',
            $product->name ?? '#'.($product->id ?? '?'),
            $this->formatQuantity($quantity),
            $this->formatQuantity($onHand)
        ));
    }

    /** رقمٌ يقرؤه المستخدم: بلا أصفارٍ لاحقةٍ بلا معنى. */
    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.') ?: '0';
    }
}
