<?php

namespace App\Services\Sales;

use App\Models\Offer;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * عروض الحسم — مطابقةُ البند بالعرض واحتسابُ حسمه.
 *
 * **لماذا وُجدت هذه الخدمة؟**
 *
 * شاشة «عروض الحسم» تُنشئ العروض وتحفظها وتعرضها منذ البداية، ونصُّها
 * يَعِد بأنّ الحسم «يُطبَّق تلقائياً على بنود الفاتورة» — ولم يكن يُطبَّق
 * على شيء. المنطق الوحيد المكتوب كان `Invoice::applyOffers()` وتوابعها
 * الثلاثة في النموذج، **ولا مستدعيَ لها في المشروع كلّه**: لا في إنشاء
 * الفاتورة، ولا في عرض السعر، ولا في المرتجع. فالعروض بياناتٌ تُدخَل
 * وتُخزَّن ولا أثر لها في مبلغٍ واحد. (عروضُ الهدايا في المقابل مطبَّقةٌ
 * فعلاً داخل `InvoicesController::store()` و`QuoteController`، وهذا سببُ
 * قول المستخدم «الهدايا تُطبَّق والحسومات لا».)
 *
 * والمنطق الميّت نفسه كان يحمل ثلاثة عيوب لو استُدعي:
 *   • يقرأ `$offer->product_id` و`category_id` المفردَين ويتجاهل الجداول
 *     الوسيطة التي تكتبها الشاشة فعلاً (`offer_products`, `offer_categories`).
 *   • يقارن `unit_type` بمعانٍ غير التي في الشاشة.
 *   • يُطبِّق الحسم على `unit_price` لا على مجموع البند.
 *
 * **الاصطلاح كما تعرضه الشاشة** (`sales_settings.offers.*`):
 *   - `type`: ١ = «حسم على البند» (لكل وحدة)، ٢ = «اشترِ كميةً واحصل على حسم».
 *   - `discount_type`: ١ = مبلغ ثابت، ٢ = نسبة مئوية.
 *   - `unit_type`: ١ = كل المنتجات، ٢ = تصنيفات محدَّدة، ٣ = منتجات محدَّدة.
 *   - العملاء: قائمةٌ فارغة تعني «كل العملاء».
 *   - التاريخان اختياريّان، وغيابُ أحدهما يعني «بلا حدٍّ من تلك الجهة» —
 *     نفس ما تحسبه بطاقة «سارٍ اليوم» في شاشة القائمة.
 *
 * @see \Modules\Sales\Http\Controllers\Concerns\AppliesDiscountOffers نقطةُ الوصل بالمستندات
 */
class OfferDiscountService
{
    /** تصنيفُ كلّ منتجٍ سُئل عنه — استعلامٌ واحد لكلّ منتج في الطلب. */
    private array $categoryOf = [];

    /**
     * العروض السارية اليوم على عميلٍ معيّن.
     *
     * `client_id` فارغاً (بيعٌ نقديّ بلا عميل) يعني العروضَ العامّة وحدها.
     */
    public function activeFor(?int $clientId, string|CarbonInterface|null $on = null): Collection
    {
        $date = $on ? Carbon::parse($on)->toDateString() : Carbon::today()->toDateString();

        return Offer::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->with(['clients:id', 'products:id', 'categories:id'])
            ->get()
            ->filter(fn (Offer $offer) => $this->coversClient($offer, $clientId))
            ->values();
    }

    /**
     * حسمُ العرض على بندٍ واحد — أو `null` إن لم ينطبق عليه عرض.
     *
     * حين ينطبق أكثر من عرض يُختار **الأكبر حسماً**: العرضُ منفعةٌ للعميل،
     * فاختيارُ الأصغر يجعل ترتيب الصفوف في الجدول هو من يقرّر ما يدفعه.
     *
     * @return array{offer_id:int, name:string, amount:float}|null
     */
    public function lineDiscount(Collection $offers, ?int $productId, float $quantity, float $lineTotal): ?array
    {
        if (! $productId || $quantity <= 0 || $lineTotal <= 0 || $offers->isEmpty()) {
            return null;
        }

        $best = null;

        foreach ($offers as $offer) {
            if (! $this->coversProduct($offer, (int) $productId)) {
                continue;
            }

            $amount = $this->amountFor($offer, $quantity, $lineTotal);

            if ($amount <= 0) {
                continue;
            }

            if ($best === null || $amount > $best['amount']) {
                $best = [
                    'offer_id' => (int) $offer->id,
                    'name' => $offer->name ?: '#'.$offer->id,
                    'amount' => $amount,
                ];
            }
        }

        return $best;
    }

    /**
     * العروضُ كما تحتاجها الشاشة — لتعرض ما سيُحفَظ لا ما دونه.
     *
     * تصلُ كاملةً بلا تصفيةٍ بالعميل (العميل يتبدّل في الشاشة فتصفيتُه في
     * الجافاسكربت)، ومنتجاتُ العروض المحصورة بتصنيفاتٍ مُوسَّعةٌ إلى
     * معرّفات منتجاتها — فلا يحتاج السكربت إلى تصنيف كل منتج.
     *
     * @return list<array<string, mixed>>
     */
    public function screenPayload(string|CarbonInterface|null $on = null): array
    {
        $date = $on ? Carbon::parse($on)->toDateString() : Carbon::today()->toDateString();

        $offers = Offer::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->with(['clients:id', 'products:id', 'categories:id'])
            ->get();

        if ($offers->isEmpty()) {
            return [];
        }

        // تصنيفاتُ العروض كلُّها في استعلامٍ واحد
        $categoryIds = $offers->flatMap(fn (Offer $o) => $o->categories->pluck('id')->all())
            ->merge($offers->pluck('category_id')->filter())
            ->unique()
            ->values();

        $productsByCategory = $categoryIds->isEmpty()
            ? collect()
            : Product::query()
                ->whereIn('category_id', $categoryIds)
                ->get(['id', 'category_id'])
                ->groupBy('category_id')
                ->map(fn ($rows) => $rows->pluck('id')->map('intval')->all());

        return $offers->map(function (Offer $offer) use ($productsByCategory) {
            $unit = (int) $offer->unit_type;

            $products = [];

            if ($unit === 3) {
                $products = $offer->products->pluck('id')->map('intval')->all();

                if ($offer->product_id) {
                    $products[] = (int) $offer->product_id;
                }
            } elseif ($unit === 2) {
                $ids = $offer->categories->pluck('id')->map('intval')->all();

                if ($offer->category_id) {
                    $ids[] = (int) $offer->category_id;
                }

                foreach ($ids as $categoryId) {
                    $products = array_merge($products, $productsByCategory[$categoryId] ?? []);
                }
            }

            $clients = $offer->clients->pluck('id')->map('intval')->all();

            if ($offer->client_id) {
                $clients[] = (int) $offer->client_id;
            }

            return [
                'id' => (int) $offer->id,
                'name' => $offer->name ?: '#'.$offer->id,
                'type' => (int) $offer->type,
                'quantity' => (float) ($offer->quantity ?: 1),
                'discount_type' => (int) $offer->discount_type,
                'discount_value' => (float) $offer->discount_value,
                /* `null` = كل المنتجات، والمصفوفة الفارغة = لا منتج —
                   عرضٌ نطاقُه «منتجات محدَّدة» ولم يُختَر له منتج لا يُطبَّق
                   على شيء، تماماً كما تفعل `coversProduct()` أعلاه. */
                'products' => $unit === 1 ? null : array_values(array_unique($products)),
                // العملاء: الفارغة تعني كلَّ العملاء (كما في `coversClient()`)
                'clients' => array_values(array_unique($clients)),
            ];
        })->values()->all();
    }

    // ─────────────────────────────────────────────────────────────────
    // الداخل
    // ─────────────────────────────────────────────────────────────────

    /** أيشمل العرضُ هذا العميل؟ قائمةٌ فارغة = الكلّ. */
    private function coversClient(Offer $offer, ?int $clientId): bool
    {
        $ids = $offer->clients->pluck('id')->all();

        // العمود المفرد `client_id` من صفوفٍ قديمة سبقت الجدول الوسيط
        if ($offer->client_id) {
            $ids[] = (int) $offer->client_id;
        }

        if ($ids === []) {
            return true;
        }

        return $clientId !== null && in_array((int) $clientId, array_map('intval', $ids), true);
    }

    /** أيشمل العرضُ هذا المنتج؟ */
    private function coversProduct(Offer $offer, int $productId): bool
    {
        $unit = (int) $offer->unit_type;

        // ٣ = منتجات محدَّدة
        if ($unit === 3) {
            $ids = $offer->products->pluck('id')->map('intval')->all();

            if ($offer->product_id) {
                $ids[] = (int) $offer->product_id;
            }

            return in_array($productId, $ids, true);
        }

        // ٢ = تصنيفات محدَّدة
        if ($unit === 2) {
            $ids = $offer->categories->pluck('id')->map('intval')->all();

            if ($offer->category_id) {
                $ids[] = (int) $offer->category_id;
            }

            $categoryId = $this->categoryOf($productId);

            return $categoryId !== null && in_array($categoryId, $ids, true);
        }

        // ١ = كل المنتجات
        return true;
    }

    /**
     * قيمةُ الحسم على البند.
     *
     * **المبلغ الثابت**:
     *   - في «حسم على البند» لكلّ وحدة — نصُّ الحقل في الشاشة يقول ذلك:
     *     «الحسم يُطبَّق على كل وحدة».
     *   - وفي «اشترِ كميةً واحصل على حسم» **لكلّ مضاعفٍ من الكمّية**، كما
     *     تفعل عروضُ الهدايا تماماً (`floor(الكمية ÷ الحدّ)`): عرضٌ حدُّه
     *     ثلاثون وحسمُه خمسون، على بندٍ كمّيتُه مئة، يحسم مئةً وخمسين —
     *     ثلاث مرّات — لا خمسين. كانت تُحسب مرّةً واحدة، وهو خلافُ ما
     *     يفهمه من يضبط العرض ويخالف نظيرَه في الهدايا.
     *
     * **النسبة المئوية** لا تُضاعَف بحال: نسبةٌ من مجموع البند مرّةً واحدة
     * متى بلغت الكمّيةُ الحدَّ — ومضاعفتُها تعني ٣٠٪ تصير ٩٠٪.
     *
     * والحسم لا يتجاوز قيمة البند بحال — وإلّا انقلب إجماليُّ البند سالباً
     * وصار العميلُ دائناً بضاعةً باعها له النظام.
     */
    private function amountFor(Offer $offer, float $quantity, float $lineTotal): float
    {
        $value = (float) $offer->discount_value;

        if ($value <= 0) {
            return 0.0;
        }

        $type = (int) $offer->type;
        $threshold = max(1.0, (float) $offer->quantity);

        if ($type === 2 && $quantity < $threshold) {
            return 0.0;
        }

        if ((int) $offer->discount_type === 2) {
            $amount = $lineTotal * $value / 100;
        } elseif ($type === 1) {
            $amount = $value * $quantity;                       // لكلّ وحدة
        } else {
            $amount = $value * floor($quantity / $threshold);   // لكلّ مضاعف
        }

        return round(min($amount, $lineTotal), 2);
    }

    /** تصنيفُ المنتج، محفوظاً بعد أوّل سؤال. */
    private function categoryOf(int $productId): ?int
    {
        if (! array_key_exists($productId, $this->categoryOf)) {
            $this->categoryOf[$productId] = Product::whereKey($productId)->value('category_id');
        }

        return $this->categoryOf[$productId] ? (int) $this->categoryOf[$productId] : null;
    }
}
