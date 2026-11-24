<?php

namespace Modules\Purchases\Http\Controllers\Concerns;

use App\Models\DefaultWarehouses;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseInvoice;
use App\Models\StoreHouse;
use App\Models\User;
use App\Support\CurrencySettings;
use App\Support\StockEligibility;
use App\Support\StorehouseAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * الخطوات المشتركة بين مستندات المشتريات الثلاثة قبل كتابة أيّ سطر.
 *
 * **لماذا هنا؟** فاتورة الشراء والمرتجع والإشعار المدين تصنع الشيء نفسه:
 * تقرأ بنوداً فيها منتجٌ ووحدةٌ ومستودع، وتكتب مستنداً وإذناً مخزنياً
 * وقيداً. وكانت كلٌّ منها تكتب ذلك على حِدة، فاختلفت في ثلاثة مواضع:
 *
 *   • *الاستعلامات*: `Product::findOrFail()` داخل الحلقة، مرّةً لكل بند
 *     في حلقة الحساب ومرّةً في حلقة الكتابة — ضِعفُ عدد البنود استعلاماً
 *     على صفوفٍ مقروءةٍ سلفاً.
 *   • *صلاحية المستودع*: `crate_invoices_permissions` تُحفَظ في شاشة
 *     المستودع ولا يقرؤها أحد، فالضبط موجود وأثرُه معدوم.
 *   • *المستودع الافتراضي*: تُقفَز من المُرسَل إلى `major` مباشرةً
 *     ويُتجاهَل المستودع المربوط بالموظّف، فيخرج المرتجع من مستودعٍ لم
 *     تدخله البضاعة أصلاً ويصير رصيده سالباً.
 *
 * فجُمعت هنا مرّةً واحدة: من يضيف مستنداً رابعاً يرث الخطوات نفسها بدل
 * أن يعيد اكتشافها ناقصة.
 */
trait PreparesPurchaseDocumentLines
{
    /**
     * تحميل ما تحتاجه البنود دفعةً واحدة — لا استعلام داخل الحلقة.
     *
     * @return array{
     *     products: Collection,
     *     productUnits: Collection,
     *     storeHouses: Collection
     * }
     */
    protected function preloadLineData(Request $request): array
    {
        $items = collect($request->items ?? []);

        $productIds = $items->pluck('product_id')->unique()->filter()->toArray();
        $productUnitIds = $items->pluck('product_unit_id')->unique()->filter()
            ->filter(fn ($id) => is_numeric($id))->toArray();
        $storeHouseIds = $items->pluck('store_house_id')->unique()->filter()->toArray();

        return [
            'products' => Product::with([
                'productUnits' => function ($query) {
                    $query->where('is_active', true)->with([
                        'categoryUnit' => function ($q) {
                            $q->with('unit');
                        },
                    ]);
                },
            ])->whereIn('id', $productIds)->get()->keyBy('id'),

            'productUnits' => ProductUnit::with([
                'categoryUnit' => function ($q) {
                    $q->with('unit');
                },
            ])->whereIn('id', $productUnitIds)->get()->keyBy('id'),

            'storeHouses' => StoreHouse::whereIn('id', $storeHouseIds)->get()->keyBy('id'),
        ];
    }

    /**
     * صلاحية «إنشاء فاتورة» المضبوطة على المستودع — تُفحص قبل أيّ كتابة.
     *
     * وتصفيةُ القائمة في الشاشة وحدها لا تكفي: من يبدّل قيمة
     * `store_house_id` في المتصفّح يتجاوزها.
     *
     * @throws \Exception إن كان أحد المستودعات ممنوعاً على هذا المستخدم
     */
    protected function assertStorehouseAccess(Collection $storeHouses, Request $request, ?User $user = null): void
    {
        $user = $user ?: auth()->user();

        foreach ($storeHouses as $candidate) {
            if (! StorehouseAccess::canCreateInvoices($candidate, $user)) {
                throw new \Exception(StorehouseAccess::message($candidate));
            }
        }

        if ($request->store_house_id
            && ! StorehouseAccess::canCreateInvoices($request->store_house_id, $user)) {
            throw new \Exception(StorehouseAccess::message($request->store_house_id));
        }
    }

    /**
     * المستودع الافتراضي للمستند: المُرسَل، ثمّ مستودع الموظّف، ثمّ الرئيسي.
     *
     * نفس ترتيب `InvoicesController::store()` في المبيعات، وهو ما تتبعه
     * فاتورة الشراء.
     */
    protected function resolveDefaultStoreHouse(Request $request, ?User $user = null): ?StoreHouse
    {
        $user = $user ?: auth()->user();

        if ($request->store_house_id) {
            $selected = StoreHouse::find($request->store_house_id);

            if ($selected) {
                return $selected;
            }
        }

        // مستودعُ الموظّف يسبق الرئيسيَّ — ما لم يُضبط «استلام المشتريات في المستودع الرئيسي»
        if ($user && $user->employee_id && ! \Modules\Purchases\Support\PurchaseWarehouse::mainFirst()) {
            $assigned = DefaultWarehouses::where('employee_id', $user->employee_id)->first();

            if ($assigned && $assigned->storehouse_id) {
                $storeHouse = StoreHouse::find($assigned->storehouse_id);

                if ($storeHouse) {
                    return $storeHouse;
                }
            }
        }

        /* منشأةٌ لم تَسِم مستودعاً بـ«رئيسي» لا يُرَدّ مستندُها بـ«لا يوجد
           مستودع» وفيها مستودعات: يُختار أوّلُ مستودعٍ نشط — كما تفعل
           `PurchaseWarehouse::defaultId()` في الشاشة. */
        return StoreHouse::where('major', 1)->first()
            ?? StoreHouse::where('status', 'active')->orderBy('id')->first()
            ?? StoreHouse::orderBy('id')->first();
    }

    /**
     * مستودعات بنود الفاتورة الأصلية: معرّف المنتج ← معرّف المستودع.
     *
     * **لماذا للمرتجع والإشعار خاصّة؟** البضاعة خرجت من مستودعٍ بعينه حين
     * دخلت، فإرجاعها من غيره يُنقص رصيداً لم يزد قطّ ويُبقي الزيادة في
     * المستودع الأوّل — رصيدان خاطئان من حركةٍ واحدة. ولهذا يسبق مستودعُ
     * الأصل الافتراضَ العام.
     *
     * @return array<int, int>
     */
    protected function originalLineStoreHouses(?PurchaseInvoice $originalInvoice): array
    {
        if (! $originalInvoice) {
            return [];
        }

        return $originalInvoice->items()
            ->whereNotNull('store_house_id')
            ->pluck('store_house_id', 'product_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * منتج البند من الدفعة المحمَّلة — أو رسالةٌ تسمّي ما نقص.
     *
     * `findOrFail` كانت ترمي `ModelNotFoundException` خاماً، فيرى المستخدم
     * «No query results for model [Product]» مكان سببٍ يفهمه.
     *
     * @throws \Exception
     */
    protected function resolveLineProduct(Collection $products, array $item): Product
    {
        if (! isset($item['product_id'])) {
            throw new \Exception('الرجاء اختيار المنتج');
        }

        $product = $products->get($item['product_id']);

        if (! $product) {
            throw new \Exception('المنتج غير موجود: '.$item['product_id']);
        }

        return $product;
    }

    /**
     * مستودع البند: المُرسَل معه، ثمّ مستودع الأصل، ثمّ افتراض المستند.
     *
     * والمستودع شرطٌ للأصناف المخزنية وحدها — الخدمي والرقمي لا يدخل
     * مستودعاً أصلاً، فمنشأةُ خدماتٍ بلا مستودعٍ رئيسي تُصدر مستنداتها.
     *
     * @param  array<int, int>  $originalStoreHouses  خريطة مستودعات الأصل
     *
     * @throws \Exception إن كان الصنف مخزنياً ولا مستودع له
     */
    protected function resolveLineStoreHouse(
        array $item,
        Collection $storeHouses,
        ?StoreHouse $default,
        array $originalStoreHouses = []
    ): ?int {
        $submitted = $item['store_house_id'] ?? null;

        if ($submitted && $storeHouses->has($submitted)) {
            return (int) $storeHouses->get($submitted)->id;
        }

        $fromOriginal = $originalStoreHouses[$item['product_id']] ?? null;

        if ($fromOriginal) {
            return (int) $fromOriginal;
        }

        if ($default) {
            return (int) $default->id;
        }

        if (StockEligibility::isStockable($item['product_id'])) {
            throw new \Exception('لا يوجد أي مستودع في النظام. الرجاء إضافة مستودع واحد على الأقل.');
        }

        return null;
    }

    /**
     * عملة المستند من شاشة «العملات» — لا «SAR» مكتوبةً في الشيفرة.
     *
     * كانت مواضع في كل متحكّم تكتب `'currency' => 'SAR'` نصّاً في القيد،
     * فمن ضبط عملة النظام على غير الريال يرى مستنده بعملته في الشاشة
     * وبـ`SAR` في القيد.
     */
    protected function documentCurrency(): string
    {
        return CurrencySettings::code();
    }
}
