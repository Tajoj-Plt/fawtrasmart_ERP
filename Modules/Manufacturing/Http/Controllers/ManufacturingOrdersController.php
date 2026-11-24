<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Enums\ManufacturOrderStatus;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManufacturOrderRequest;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientRelation;
use App\Models\Employee;
use App\Models\IndirectCost;
use App\Models\Log as ModelsLog;
use App\Models\ManufacturOrders;
use App\Models\notifications;
use App\Models\Product;
use App\Models\ProductionMaterials;
use App\Models\ProductionPath;
use App\Models\ProductionStage;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WorkStations;
use App\Support\OperationLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Manufacturing\Constants\ManufacturingSources;
use Modules\Manufacturing\Http\Controllers\Concerns\BuildsOrderItems;
use Modules\Manufacturing\Http\Controllers\Concerns\ManufacturingOrderLifecycle;
use Modules\Manufacturing\Http\Controllers\Concerns\RecalculatesTotals;
use Modules\Manufacturing\Http\Controllers\Concerns\ReservesOrderMaterials;
use Modules\Manufacturing\Http\Controllers\Concerns\ResolvesExpenseAccounts;
use Modules\Manufacturing\Http\Controllers\Concerns\SafeListing;
use Modules\Manufacturing\Http\Controllers\Concerns\ScopesToBranch;
use Modules\Manufacturing\Services\ReservationService;
use Modules\Manufacturing\Support\OrderFormSettings;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

class ManufacturingOrdersController extends Controller
{
    use AuthorizesRequests,
        BuildsOrderItems,
        GeneratesDocumentNumbers,
        ManufacturingOrderLifecycle,
        RecalculatesTotals,
        ReservesOrderMaterials,
        ResolvesAccountingAccounts,
        ResolvesExpenseAccounts,
        RespectsAccountingPeriods,
        SafeListing,
        ScopesToBranch,
        UsesDocumentCurrency;

    /**
     * حارسٌ داخل المتحكّم إلى جانب حارس المسار — نمط
     * [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController].
     *
     * دوالُّ دورة الحياة ([ManufacturingOrderLifecycle]) مدرجةٌ هنا وإن
     * كانت محروسةً في المسارات: هي التي تصرف مخزوناً وتُرحّل قيوداً، وهي
     * الأبعدُ عن ملفّ المسارات — تعيش في سمةٍ في ملفٍّ آخر، فربطُ صلاحيتها
     * بموضعها في الملفّ وحده يجعل نقلَها أو إعادةَ تسجيلها تفتحها.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.manufacturing_orders.view')
            ->only(['index', 'getData', 'getFilterOptions']);
        $this->middleware('permission.role:stock.manufacturing_orders.show')->only(['show']);
        $this->middleware('permission.role:stock.manufacturing_orders.create')->only(['create', 'store']);
        $this->middleware('permission.role:stock.manufacturing_orders.edit')->only(['edit', 'update']);
        $this->middleware('permission.role:stock.manufacturing_orders.delete')->only(['destroy']);
        $this->middleware('permission.role:stock.manufacturing_orders.notes')
            ->only(['addNote', 'getNotes', 'deleteNote']);

        // دورة الحياة — كلٌّ بصلاحيته
        $this->middleware('permission.role:stock.manufacturing_orders.receive_materials')
            ->only(['showReceiveMaterialsForm', 'receiveMaterials']);
        $this->middleware('permission.role:stock.manufacturing_orders.return_materials')
            ->only(['showReturnMaterialsForm', 'returnMaterials']);
        $this->middleware('permission.role:stock.manufacturing_orders.finish')->only(['completeOrder']);
        $this->middleware('permission.role:stock.manufacturing_orders.undo_finish')->only(['undoCompletion']);
        $this->middleware('permission.role:stock.manufacturing_orders.close')->only(['closeOrder']);
        $this->middleware('permission.role:stock.manufacturing_orders.reopen')->only(['reopenOrder']);
        $this->middleware('permission.role:stock.manufacturing_orders.cancel')->only(['cancelOrder']);
    }

    public function index(Request $request)
    {
        // جلب البيانات الأساسية للفلاتر
        $products = Product::select('id', 'name')->get();
        $clients = Client::select('id', 'trade_name', 'code')->get();
        // قوائمُ المواد من الفرع النشط — فلترٌ بقائمةٍ لا تخصّني بلا معنى
        $materialLists = $this->scopeToActiveBranch(ProductionMaterials::query())
            ->select('id', 'name')->get();
        $productionStages = ProductionStage::select('id', 'stage_name')->get();

        return view('manufacturing::orders.index', [
            'products' => $products,
            'clients' => $clients,
            'materialLists' => $materialLists,
            'productionStages' => $productionStages,
            // فروعُ التصفية والعملات والفترة — الإعدادات العامّة
            'branches' => $this->branchFilterOptions(),
            'currencies' => $this->activeCurrencies(),
            'selectedCurrency' => $this->filterCurrency($request),
            'period' => $this->stockPeriod($request),
        ]);
    }

    public function getData(Request $request)
    {
        try {
            /*
                أوامرُ الفرع النشط وحدها.

                كانت `ManufacturOrders::with(...)` عاريةً: أوامرُ الفروع
                كلِّها في شاشةٍ واحدة، وتبديلُ الفرع في الشريط العلوي بلا
                أثرٍ فيها. ولا شيء في الصفّ يميّز الغريبَ من المحلّي —
                فيُقرأ حِملُ مصنعٍ آخر على أنه حِملُ المصنع الذي يقف عليه
                صاحبُ الشاشة، ويُبنى عليه جدولُ إنتاج.
            */
            $query = $this->applyBranchFilter(ManufacturOrders::with(['product', 'client']), $request);

            // العملة — الافتراضية ما لم يُطلب غيرها، والفراغ «كل العملات»
            // العملة تُطبَّق حين تُطلب صراحةً — الحقلُ لا يُعرض إلّا مع أكثر من عملة
            if ($request->filled('currency')) {
                $query->where('currency', $request->input('currency'));
            }

            // تطبيق الفلاتر
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%");
                });
            }

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            // العمودُ `production_material_id` — كان الفلتر يسأل عن
            // `material_list_id` غير الموجود فيسقط الطلبُ كلُّه بخطأ 500
            if ($request->filled('material_list_id')) {
                $query->where('production_material_id', $request->material_list_id);
            }

            // فترةُ الأمر ضمن النطاق المطلوب
            if ($request->filled('from_date')) {
                $query->whereDate('from_date', '>=', $request->from_date);
            }

            if ($request->filled('to_date')) {
                $query->whereDate('to_date', '<=', $request->to_date);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            // المرحلةُ على البنود لا على رأس الأمر — لا عمودَ `production_stage_id` فيه
            if ($request->filled('production_stage_id')) {
                $stageId = $request->production_stage_id;

                $query->whereHas('manufacturOrdersItem', function ($q) use ($stageId) {
                    $q->where('raw_production_stage_id', $stageId)
                        ->orWhere('expenses_production_stage_id', $stageId)
                        ->orWhere('manu_production_stage_id', $stageId)
                        ->orWhere('end_life_production_stage_id', $stageId);
                });
            }

            /*
                الترتيب من قائمة بيضاء والاتجاه من قيمتين لا ثالث لهما.

                كان `?sort_order=x` يرمي InvalidArgumentException — خطأ 500
                غير معالَج بحرفٍ واحد في العنوان. و`?sort_by=xyz` يعطي
                Unknown column يُعرض نصّه للمستخدم، فيُعدّ به أعمدةَ الجدول
                وجداولَ أخرى بفروق الاستجابة. و`?per_page=1000000` يحمّل
                الجدول كاملاً في الذاكرة.
            */
            $this->applySafeSort($query, $request, [
                'id', 'code', 'name', 'quantity', 'status', 'from_date', 'to_date', 'created_at',
            ]);

            $orders = $query->paginate($this->safePerPage($request, 10));

            // اسمُ الجهة لعمود «تابع لـ»
            $orders->getCollection()->each(function ($order) {
                $order->setAttribute('branch_name', \Modules\Manufacturing\Support\BranchTree::name($order->branch_id));
            });

            // تحضير البيانات للإرسال
            $data = [
                'orders' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'تم جلب البيانات بنجاح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'getData');

            return response()->json([
                'success' => false,
                'message' => $this->safeError($e, 'حدث خطأ في جلب البيانات.'),
            ], 500);
        }
    }

    public function getFilterOptions()
    {
        try {
            $data = [
                'products' => Product::select('id', 'name')->get(),
                'clients' => Client::select('id', 'trade_name', 'code')->get(),
                // قوائمُ المواد من الفرع النشط — كما في `index()`
                'material_lists' => $this->scopeToActiveBranch(ProductionMaterials::query())
                    ->select('id', 'name')->get(),
                'production_stages' => ProductionStage::select('id', 'stage_name')->get(),
                // فروعُ التصفية والعملات المفعَّلة — الإعدادات العامّة
                'branches' => $this->branchFilterOptions(),
                'currencies' => $this->activeCurrencies(),
                /*
                    الحالات من مصدرها الواحد.

                    كانت القائمة تعرض `active` — حالة لا يكتبها المتحكّم
                    إطلاقاً — وتُغفل `pending` و`materials_received`
                    و`closed`. أي خيارٌ بلا نتائج، وثلاثُ حالات حقيقية
                    لا يمكن الفلترة عليها.
                */
                'statuses' => ManufacturOrderStatus::options(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'getFilterOptions');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب خيارات الفلترة',
            ], 500);
        }
    }

    public function create()
    {
        // رقم المستند من إعدادات «الترقيم المتسلسل» — معاينة بلا استهلاك
        $serial_number = $this->previewDocumentNumber(ManufacturingSources::SECTION_ORDERS);

        // العملة الافتراضية من شاشة «العملات» — تُجمَّد مع الأمر عند الحفظ
        $currencies = $this->activeCurrencies();
        $defaultCurrency = $this->defaultCurrency();

        return view('manufacturing::orders.create', array_merge(
            $this->orderFormLookups(null),
            compact('serial_number', 'currencies', 'defaultCurrency')
        ));
    }

    /**
     * قوائمُ نموذج الأمر — مصدرٌ واحد للإنشاء والتعديل.
     *
     * ── لماذا دالّةٌ واحدة ───────────────────────────────────────────
     *
     * كانت `create()` تحصر قوائمَها بالفرع النشط و`edit()` تجلب الجداولَ
     * كاملةً: فشاشةُ التعديل تعرض مساراتِ ومحطّاتِ ومستودعاتِ الفروع
     * كلِّها، ثمّ يرفض الحفظُ ما اختير منها. الآن القائمتان واحدة، وما
     * يحمله الأمرُ المفتوح يُستبقى صراحةً كي لا يُفرَّغ حقلٌ محفوظ.
     *
     * ── ما تحصره ────────────────────────────────────────────────────
     *
     *   المنتج المصنَّع   المنتجاتُ التامّة ونصف المصنَّعة — لا المواد الخام.
     *   مواد البنود       المواد الخام ونصف المصنَّعة — لا المنتج التامّ.
     *   سعر المادة        متوسطُ تكلفتها في المخزون — لا سعرُ الشراء.
     *   حساب المصروف      حساباتُ مصروفات التصنيع المباشرة — لا الدليل كلُّه.
     *   المسار/القائمة/المحطّة/المستودع   جهةُ الشريط العلويّ وما تحتها.
     *
     * @return array<string, mixed>
     */
    protected function orderFormLookups(?ManufacturOrders $order): array
    {
        $items = $order ? $order->manufacturOrdersItem : collect();

        $keepLineProducts = $items->pluck('raw_product_id')
            ->merge($items->pluck('end_life_product_id'))
            ->filter()->map(fn ($id) => (int) $id)->unique()->all();

        /*
            «الخام» و«التامّ» من `material_type` في بطاقة الصنف.

            صنفٌ بلا تصنيفٍ (`null`) يظهر في القائمتين — لم يُحسم نوعُه بعد،
            وإخفاؤه من إحداهما يمنع استعمالَه حتى يُعدَّل. والخدماتُ خارج
            القائمتين: لا تُصنَّع ولا تُصرَف من مخزون.
        */
        $products = Product::query()
            ->where(function ($q) use ($order) {
                $q->where(function ($w) {
                    $w->where(function ($t) {
                        $t->whereNull('type')->orWhere('type', '!=', 'services');
                    })->where(function ($m) {
                        $m->whereNull('material_type')->orWhere('material_type', '!=', 'raw');
                    });
                });

                if ($order && $order->product_id) {
                    $q->orWhere('id', $order->product_id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $raw_materials = Product::with([
            'productUnits' => function ($query) {
                $query->where('is_active', true)
                    ->with(['categoryUnit' => function ($q) {
                        $q->with('unit');
                    }]);
            },
            'category.categoryUnits' => function ($query) {
                $query->where('is_active', true)->with('unit');
            },
        ])
            ->where(function ($q) use ($keepLineProducts) {
                $q->where(function ($w) {
                    $w->where(function ($t) {
                        $t->whereNull('type')->orWhere('type', '!=', 'services');
                    })->where(function ($m) {
                        $m->whereNull('material_type')->orWhere('material_type', '!=', 'finished');
                    });
                });

                if ($keepLineProducts !== []) {
                    $q->orWhereIn('id', $keepLineProducts);
                }
            })
            ->orderBy('name')
            ->get();

        $productsWithUnits = $this->productUnitsPayload($raw_materials);

        $employees = Employee::select('id', 'first_name', 'middle_name')->get();
        $clients = Client::select('id', 'trade_name')->get();

        // حسابُ رأس الأمر — الدليلُ بتدرّجه كما كان
        $accounts = $this->getAccountDropdownOptions();

        // حساباتُ بنود المصروف — المباشرةُ وحدها، ومعها ما حُفظ على الأمر
        $expenseAccounts = $this->manufacturingExpenseAccounts();
        $savedExpenseAccounts = $items->whereNull('indirect_cost_id')->pluck('expenses_account_id')
            ->filter()->map(fn ($id) => (int) $id)->unique()
            ->diff($expenseAccounts->pluck('id'))->all();

        if ($savedExpenseAccounts !== []) {
            $expenseAccounts = $expenseAccounts->concat(
                Account::whereIn('id', $savedExpenseAccounts)->get(['id', 'name', 'code'])
            );
        }

        /*
            نطاقُ الجهة + ما يحمله الأمر.

            يُطبَّق القيدُ فقط حين يكون للشريط جهةٌ فعلاً: على الفرع
            الرئيسيّ لا قيد، وإضافةُ «أو المحفوظ» وحدَها داخل القوس تحصر
            القائمةَ في المحفوظ بدل أن توسّعها.
        */
        $scoped = $this->activeBranchIds() !== null;

        $withinScope = function ($query, array $keepIds) use ($scoped) {
            if (! $scoped) {
                return $query;
            }

            return $query->where(function ($q) use ($keepIds) {
                $this->scopeToActiveBranch($q);

                if ($keepIds !== []) {
                    $q->orWhereIn($q->getModel()->getTable().'.id', $keepIds);
                }
            });
        };

        $paths = $withinScope(ProductionPath::query(), array_filter([$order?->production_path_id]))
            ->select('id', 'name')->get();

        // البنودُ مع القائمة — تُنسخ إلى الأمر عند اختيارها
        $production_materials = $withinScope(
            ProductionMaterials::with('ProductionMaterialsItem'),
            array_filter([$order?->production_material_id])
        )->get();

        // مراحلُ المسارات الظاهرة وحدها، ومعها ما حُفظ على بنود الأمر
        $keepStages = $items->flatMap(fn ($i) => [
            $i->raw_production_stage_id, $i->expenses_production_stage_id,
            $i->manu_production_stage_id, $i->end_life_production_stage_id,
        ])->filter()->unique()->all();

        $stages = ProductionStage::query()
            ->whereIn('production_paths_id', $paths->pluck('id'))
            ->when($keepStages !== [], fn ($q) => $q->orWhereIn('id', $keepStages))
            ->get(['id', 'stage_name', 'production_paths_id']);

        // `total_cost` مع المحطّة: القالبُ يضعه «تكلفة الساعة» عند اختيارها.
        // كان الاستعلام يجلب `id, name` وحدهما فتُحمَّل التكلفة صفراً دائماً
        $keepStations = $items->pluck('workstation_id')->filter()->unique()->all();

        $workstations = $withinScope(WorkStations::query(), $keepStations)
            ->select('id', 'name', 'total_cost')->get();

        // مستودعات الحجز الاختياري — انظر ReservesOrderMaterials
        $storehouses = $this->accessibleStorehouses($order?->storehouse_id);

        $indirectCosts = $this->selectableIndirectCosts(
            $items->pluck('indirect_cost_id')->filter()->map(fn ($id) => (int) $id)->all()
        );

        return compact(
            'products', 'raw_materials', 'productsWithUnits', 'employees', 'clients', 'accounts',
            'expenseAccounts', 'paths', 'production_materials', 'stages', 'workstations',
            'storehouses', 'indirectCosts'
        );
    }

    /**
     * متوسطُ تكلفة كل صنفٍ في المخزون — مرجَّحاً بالكميات.
     *
     * هو السعرُ الذي تُصرَف به المادةُ فعلاً عند الاستلام، فهو ما يُعرض
     * تقديراً في بند الأمر. كان البندُ يأخذ «سعر الشراء» من بطاقة الصنف —
     * رقمٌ يُكتب مرّةً ولا يتبع ما اشتُري به لاحقاً.
     *
     * @return array<int, float>
     */
    protected function averageCosts(): array
    {
        static $costs = null;

        if ($costs !== null) {
            return $costs;
        }

        // «سعر التكلفة» في النظام — متوسطُ أذون الإضافة، كبطاقة الصنف وتكلفة المبيعات
        $costs = app(\Modules\Manufacturing\Services\InventoryService::class)->averageCosts();

        // صنفٌ بلا أذون إضافةٍ مسعَّرة يُسعَّر بآخر تكلفةٍ عُرفت له في المخزون
        $stored = DB::table('product_details')
            ->groupBy('product_id')
            ->selectRaw('product_id, MAX(COALESCE(unit_price, 0)) AS cost')
            ->pluck('cost', 'product_id');

        foreach ($stored as $productId => $cost) {
            if (($costs[$productId] ?? 0) <= 0 && (float) $cost > 0) {
                $costs[$productId] = round((float) $cost, 6);
            }
        }

        return $costs;
    }

    /**
     * الأصنافُ بوحداتها وأسعارها — لجداول البنود.
     *
     * سعرُ كل وحدةٍ = متوسط تكلفة الوحدة الأساسية × معامل التحويل. كانت
     * وحداتُ التصنيف كلُّها تأخذ سعرَ الصنف نفسَه — الكرتون بسعر الحبّة.
     * وصنفٌ لا رصيدَ له في المخزون يرجع إلى سعر شرائه.
     */
    protected function productUnitsPayload($products)
    {
        $averages = $this->averageCosts();

        return $products->map(function ($product) use ($averages) {
            $baseCost = (float) ($averages[$product->id] ?? 0);

            if ($baseCost <= 0) {
                $baseCost = (float) $product->purchase_price;
            }

            $units = [];

            // وحداتُ الصنف الخاصّة به، وإلّا وحداتُ تصنيفه
            if ($product->productUnits && $product->productUnits->count() > 0) {
                foreach ($product->productUnits as $productUnit) {
                    if (! $productUnit->categoryUnit || ! $productUnit->categoryUnit->unit) {
                        continue;
                    }

                    $factor = (float) ($productUnit->categoryUnit->conversion_factor ?: 1);

                    $units[] = [
                        'id' => $productUnit->id,
                        'name' => $productUnit->categoryUnit->unit->name,
                        'conversion_factor' => $factor,
                        'is_base_unit' => $productUnit->categoryUnit->is_base_unit ?? false,
                        'is_default' => $productUnit->is_default,
                        'sale_price' => (float) $productUnit->sale_price ?: round((float) $product->sale_price * $factor, 6),
                        'purchase_price' => round($baseCost * $factor, 6),
                    ];
                }
            } elseif ($product->category && $product->category->categoryUnits) {
                foreach ($product->category->categoryUnits as $categoryUnit) {
                    if (! $categoryUnit->unit) {
                        continue;
                    }

                    $factor = (float) ($categoryUnit->conversion_factor ?: 1);

                    $units[] = [
                        // وحدةُ تصنيفٍ لا صفَّ لها في وحدات الصنف — تُعرَّف بمعاملها
                        'id' => null,
                        'category_unit_id' => $categoryUnit->id,
                        'name' => $categoryUnit->unit->name,
                        'conversion_factor' => $factor,
                        'is_base_unit' => $categoryUnit->is_base_unit ?? false,
                        'is_default' => $categoryUnit->is_base_unit ?? false,
                        'sale_price' => round((float) $product->sale_price * $factor, 6),
                        'purchase_price' => round($baseCost * $factor, 6),
                    ];
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sale_price' => (float) $product->sale_price,
                // «سعر الشراء» في القالب هو متوسطُ التكلفة هنا
                'purchase_price' => round($baseCost, 6),
                'units' => $units,
                'has_units' => count($units) > 0,
            ];
        });
    }

    /**
     * مستودعاتُ الفرع النشط — لقائمة «مستودع المواد» في شاشتَي الإنشاء
     * والتعديل.
     *
     * `StoreHouse` يحمل `branch_id` منذ نشأته ولا يحمل سمةَ
     * [\App\Traits\BelongsToBranch]، فيُطبَّق الشرطُ من
     * [ScopesToBranch::scopeBranchColumn] — انظر تعليقها.
     *
     * وحصرُه هنا ليس تجميلاً: المستودعُ المختار هو الذي **يُصرَف منه**
     * فعلاً عند استلام المواد، فاختيارُ مستودع فرعٍ آخر يخصم من رصيده
     * حقيقةً — جردٌ ناقص في فرعٍ وزائد في آخر، ولا شيء في الشاشة يشي به.
     */
    protected function accessibleStorehouses($keepId = null)
    {
        return StoreHouse::query()
            ->when($this->activeBranchIds() !== null, function ($query) use ($keepId) {
                $query->where(function ($q) use ($keepId) {
                    $this->scopeBranchColumn($q);

                    if ($keepId) {
                        $q->orWhere('id', $keepId);
                    }
                });
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    /**
     * حساباتُ بنود المصروف الواصلة في الطلب — من مصروفات التصنيع وحدها.
     *
     * القائمةُ في الشاشة محصورة، والطلبُ يُصنَع باليد. وما حُفظ على الأمر
     * من قبلُ يبقى مقبولاً عند تعديله.
     */
    protected function assertOrderExpenseAccounts(Request $request, ?ManufacturOrders $order = null): void
    {
        $submitted = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('expenses_account_id', []))
        )));

        if ($submitted === []) {
            return;
        }

        $allowed = $this->allowedExpenseAccountIds();

        if ($order) {
            $allowed = array_merge($allowed, $order->manufacturOrdersItem()
                ->whereNotNull('expenses_account_id')->pluck('expenses_account_id')->map(fn ($id) => (int) $id)->all());
        }

        $rejected = array_diff($submitted, $allowed);

        if ($rejected !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'expenses_account_id' => 'المصروفات: حساب المصروف يجب أن يكون من حسابات «مصروفات التصنيع» المباشرة. المرفوض: '
                    .Account::whereIn('id', $rejected)->pluck('name')->implode('، ').'.',
            ]);
        }
    }

    /**
     * التكاليفُ غير المباشرة المتاحة للاختيار في الأمر.
     *
     * ما في نطاق الجهة وله نصيبُ وحدةٍ محسوب. وما يحمله الأمرُ المفتوح
     * يُستبقى (`$keepIds`) كي لا يُفرَّغ صفٌّ محفوظ عند التعديل.
     *
     * @param  array<int, int>  $keepIds
     */
    protected function selectableIndirectCosts(array $keepIds = [])
    {
        $allocator = app(\Modules\Manufacturing\Services\IndirectCostAllocator::class);

        return $this->scopeToActiveBranch(IndirectCost::with('account:id,name'))
            ->where(function ($q) use ($keepIds) {
                $q->where('total', '>', 0);

                if ($keepIds !== []) {
                    $q->orWhereIn('id', $keepIds);
                }
            })
            ->orderByDesc('from_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            // النصيبُ الساري — المحفوظ، أو المحسوب على أوامر الفترة لتكلفةٍ
            // حُفظت بلا أوامر؛ `null` تعني أن الأمر يتحمّل الإجماليَّ كاملاً
            ->each(fn (IndirectCost $cost) => $cost->setAttribute('effective_rate', $allocator->effectiveRate($cost)));
    }

    /**
     * فحصُ مراجع الأمر الواصلة في الطلب — كلٌّ بفرعه.
     *
     * القوائمُ في الشاشة مصفّاةٌ بالفرع، لكنّ التصفيةَ في العرض ليست
     * حارساً: النموذجُ يُرسَل بمعرّفاتٍ يمكن تحريرُها، و`exists:` في
     * [\App\Http\Requests\ManufacturOrderRequest] تتحقّق من الوجود لا
     * من الفرع. فيُفحص كلُّ مرجعٍ هنا عند الكتابة.
     *
     * والخطأُ خطأُ تحقّقٍ لا 403: هو حقلٌ في نموذجٍ يُعاد عرضُه، فرسالةٌ
     * تحت الحقل أوضحُ من صفحة منعٍ عارية.
     */
    protected function assertOrderReferencesWithinBranch(Request $request, ?ManufacturOrders $order = null): void
    {
        $references = [
            'storehouse_id' => StoreHouse::class,
            'production_material_id' => ProductionMaterials::class,
            'production_path_id' => ProductionPath::class,
        ];

        foreach ($references as $field => $model) {
            if (! $request->filled($field)) {
                continue;
            }

            // مرجعٌ محفوظٌ على الأمر من قبلُ يبقى مقبولاً — لم يختره هذا التعديل
            if ($order && (int) $order->getAttribute($field) === (int) $request->input($field)) {
                continue;
            }

            $branchId = $model::whereKey($request->input($field))->value('branch_id');

            $this->assertBranchScope($branchId, $field);
        }
    }

    /**
     * Build hierarchical account labels for dropdown inputs.
     */
    private function getAccountDropdownOptions()
    {
        $accounts = Account::select('id', 'name', 'code', 'parent_id')
            ->orderBy('code')
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        $ordered = collect();

        $traverse = function ($parentId, $depth) use (&$traverse, &$ordered, $accounts) {
            $branch = $accounts->get($parentId, collect());

            foreach ($branch as $account) {
                $label = $account->code ? $account->code.' - '.$account->name : $account->name;
                $indent = $depth > 0 ? str_repeat('|-- ', $depth) : '';
                $account->setAttribute('dropdown_label', $indent.$label);
                $account->setAttribute('dropdown_text', $label);
                $account->setAttribute('dropdown_depth', $depth);
                $account->setAttribute('dropdown_code', $account->code);
                $ordered->push($account);
                $traverse($account->id, $depth + 1);
            }
        };

        $traverse(null, 0);

        return $ordered;
    }

    public function store(ManufacturOrderRequest $request)
    {
        /*
            الفترةُ المحاسبية على الإنشاء أيضاً.

            كان الفحص في `update()` وحده. وأمرُ التصنيع يُرحّل قيوده
            بتاريخ `from_date` — صرفُ المواد ثمّ إقفال WIP — فإنشاؤه في
            فترةٍ أقفلها المحاسب يضع حركةً في شهرٍ صدرت قوائمُه.
        */
        if ($block = $this->accountingPeriodBlock($request->from_date)) {
            return redirect()->back()->withInput()->with(['error' => $block]);
        }

        // المستودعُ والمراجعُ الواصلة في الطلب تُفحَص بالفرع كما تُصفّى
        // القوائمُ التي عُرضت — القائمةُ في الصفحة والمعرّفُ في الطلب
        $this->assertOrderReferencesWithinBranch($request);
        $this->assertOrderExpenseAccounts($request);
        $this->assertBomMatchesProduct($request);

        DB::beginTransaction();
        try {

            $order = ManufacturOrders::create([
                'name' => $request->name,
                /*
                    الرقم من إعدادات الترقيم لا من الشاشة.

                    كانت `create()` تعرض الرقم التالي بـ`previewDocumentNumber`
                    — معاينةٌ لا تستهلك — ثم يُحفظ ما في الحقل، والحقل قابل
                    للتحرير. فإعدادات البادئة والطول والعدّاد تحكم العرض لا
                    التخزين، والتسلسل ينكسر بأول تعديل يدوي.

                    وأثرٌ ثانٍ: التحقّق `unique` يقع في الطلب لا كقفل، فطلبان
                    متزامنان يقرآن الرقم المعروض نفسه ويجتازان التحقّق معاً.
                    و`issueDocumentNumber` تعمل داخل معاملة بقفل وتتخطّى
                    المحجوز، فلا تصادم.
                */
                'code' => $this->issueDocumentNumber(ManufacturingSources::SECTION_ORDERS),
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'account_id' => $this->headerField($request, 'account_id'),
                'employee_id' => $this->headerField($request, 'employee_id'),
                'client_id' => $this->headerField($request, 'client_id'),
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'production_material_id' => $this->headerField($request, 'production_material_id'),
                'production_path_id' => $this->headerField($request, 'production_path_id'),
                // مستودع المواد — اختياري؛ اختيارُه يحجز المواد فيه
                'storehouse_id' => $this->headerField($request, 'storehouse_id'),
                // يُشتقّ من البنود بعد بنائها — لا يُقبل من الطلب
                'last_total_cost' => 0,
                'created_by' => auth()->id(),
                // العملة والفرع يُختمان مع الأمر ولا يُعدَّلان بعده
            ] + $this->newDocumentCurrencyAttributes() + $this->newDocumentBranchAttributes());
            /*
                البنود ومبالغها من الخادم.

                كانت هنا أربع حلقات — مواد ومصروفات وتشغيل وهالك — تكتب
                `raw_total` و`expenses_total` و`manu_total` و`end_life_total`
                كما وصلت من الطلب. والحقول `readonly` في الواجهة وحدها، وهو
                حاجزٌ بصري لا أمني.

                والإجمالي كذلك: `last_total_cost` كان يُقبل من الطلب، و
                `completeOrder` يبني منه قيود WIP. الآن يُشتقّ من البنود.
            */
            $order->update([
                'last_total_cost' => $this->syncOrderItems($order, $request),
            ]);

            // الحجز بعد البناء — يقرأ البنود المحفوظة
            $shortages = $this->reserveOrderMaterials($order, $request);

            /*
                `created` لا `logged` — انظر التعليق في
                [\Modules\Manufacturing\Http\Controllers\BOMController::store()].
            */
            OperationLog::record(
                'created',
                ManufacturingSources::LOG_ORDER,
                $order->id,
                'تم إضافة أمر تصنيع **'.$order->name.'** برقم '.$order->code,
            );

            // تأكيد حفظ البيانات
            DB::commit();

            /*
                نقصُ المتاح لا يمنع الحفظ.

                التخطيطُ لأمرٍ قبل توفّر مواده عملٌ مشروع — المنعُ هنا
                يجعل النظام يرفض التخطيط لا الصرف. فيُحفظ الأمر ويُبلَّغ
                المستخدم أن الحجز لم يتمّ ولماذا.
            */
            if ($shortages !== []) {
                return redirect()->route('manufacturing.orders.index')
                    ->with('warning', $this->reservationShortageMessage($shortages));
            }

            return redirect()->route('manufacturing.orders.index')->with(['success' => 'تم حفظ البيانات بنجاح.']);

        } catch (\Exception $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'store');
            DB::rollBack();
            \Log::error('Manufacturing Order Creation Error: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with(['error' => $this->saveFailureMessage($e, 'حدث خطأ أثناء حفظ البيانات.')]);
        }
    }

    /**
     * رسالةُ فشل الحفظ — تقول **أين** الخطأ ما أمكن.
     *
     * كانت «حدث خطأ أثناء حفظ البيانات» وحدها، فلا يعرف المستخدم ما يصحّح.
     * أخطاءُ قاعدة البيانات الشائعة تُترجم إلى سببها؛ وما عداها يُعرض
     * برقم مرجعه في «سجلّ الأخطاء»، ونصُّه الكامل هناك لا في الشاشة.
     */
    protected function saveFailureMessage(\Throwable $e, string $fallback): string
    {
        $text = $e->getMessage();

        if ($e instanceof \Illuminate\Validation\ValidationException) {
            throw $e;
        }

        if ($e instanceof \RuntimeException && ! ($e instanceof \PDOException) && ! ($e instanceof \Illuminate\Database\QueryException)) {
            // استثناءاتُ المنطق في الوحدة مكتوبةٌ للمستخدم أصلاً
            return $fallback.' '.$text;
        }

        if (str_contains($text, 'foreign key constraint')) {
            return $fallback.' أحد المراجع المختارة (منتج، مرحلة، محطّة، حساب) لم يعد موجوداً — أعد اختياره.';
        }

        if (str_contains($text, 'Out of range') || str_contains($text, 'Numeric value out of range')) {
            return $fallback.' أحد الأرقام (كمية أو سعر أو إجمالي) أكبر من الحدّ المسموح — راجع الكميات والأسعار.';
        }

        if (str_contains($text, 'cannot be null') || str_contains($text, "doesn't have a default value")) {
            return $fallback.' حقلٌ إلزاميّ فارغ في أحد الصفوف — راجع المراحل والمنتجات والحسابات في الجداول.';
        }

        if (str_contains($text, 'Data too long')) {
            return $fallback.' أحد النصوص (اسم أو وصف) أطول من المسموح.';
        }

        return $this->safeError($e, $fallback.' سُجّل الخطأ في «سجلّ الأخطاء».');
    }

    public function edit($id)
    {
        $order = ManufacturOrders::with('manufacturOrdersItem')->findOrFail($id);
        // التصفيةُ في القائمة ليست عزلاً: `edit/{id}` يقبل المعرّف من العنوان
        $this->authorizeBranchScope($order->branch_id);

        // تصنيف العناصر حسب نوعها
        $allItems = $order->manufacturOrdersItem;

        // المواد الخام (لها raw_product_id)
        $rawMaterials = $allItems->filter(function ($item) {
            return ! empty($item->raw_product_id);
        })->map(function ($item) {
            return (object) [
                'product_id' => $item->raw_product_id,
                'unit_id' => $item->raw_unit_id ?? null,
                'unit_price' => $item->raw_unit_price,
                'quantity' => $item->raw_quantity,
                'total' => $item->raw_total,
                'production_stage_id' => $item->raw_production_stage_id,
                'conversion_factor' => $item->raw_conversion_factor ?? 1,
                'quantity_in_base_unit' => $item->raw_quantity_in_base_unit ?? $item->raw_quantity,
            ];
        });

        // المصروفات (لها expenses_account_id)
        $expenseItems = $allItems->filter(function ($item) {
            return ! empty($item->expenses_account_id);
        })->map(function ($item) {
            return (object) [
                'account_id' => $item->expenses_account_id,
                // بندٌ مصدرُه تكلفةٌ غير مباشرة — يُعرض في قسمه لا مع المصروفات
                'indirect_cost_id' => $item->indirect_cost_id,
                'cost_type' => $item->expenses_cost_type,
                'price' => $item->expenses_price,
                'description' => $item->expenses_description,
                'total' => $item->expenses_total,
                'production_stage_id' => $item->expenses_production_stage_id,
            ];
        });

        // عمليات التصنيع (لها workstation_id)
        $manufacturingItems = $allItems->filter(function ($item) {
            return ! empty($item->workstation_id);
        })->map(function ($item) {
            return (object) [
                'workstation_id' => $item->workstation_id,
                'cost_type' => $item->manu_cost_type,
                'operating_time' => $item->operating_time,
                'total_cost' => $item->manu_total_cost,
                'description' => $item->manu_description,
                'total' => $item->manu_total,
                'production_stage_id' => $item->manu_production_stage_id,
            ];
        });

        // المواد الهالكة (لها end_life_product_id)
        $endLifeItems = $allItems->filter(function ($item) {
            return ! empty($item->end_life_product_id);
        })->map(function ($item) {
            return (object) [
                'product_id' => $item->end_life_product_id,
                'unit_id' => $item->end_life_unit_id ?? null,
                'unit_price' => $item->end_life_unit_price,
                'quantity' => $item->end_life_quantity,
                'total' => $item->end_life_total,
                'production_stage_id' => $item->end_life_production_stage_id,
                'conversion_factor' => $item->end_life_conversion_factor ?? 1,
                'quantity_in_base_unit' => $item->end_life_quantity_in_base_unit ?? $item->end_life_quantity,
            ];
        });

        return view('manufacturing::orders.edit', array_merge(
            // القوائمُ نفسُها في الإنشاء — محصورةً بجهة الشريط، وما يحمله الأمر يُستبقى
            $this->orderFormLookups($order),
            compact('order', 'rawMaterials', 'expenseItems', 'manufacturingItems', 'endLifeItems')
        ));
    }

    /**
     * قيمةُ حقلٍ من رأس الأمر — بحسب إعدادات النموذج.
     *
     * حقلٌ أُخفي من الإعدادات لا يصل في الطلب: عند الإنشاء يُحفظ فارغاً،
     * وعند التعديل يبقى ما كان — إخفاءُ الحقل لا يمسح قيمتَه المحفوظة.
     */
    protected function headerField(Request $request, string $field, ?ManufacturOrders $order = null)
    {
        if (! OrderFormSettings::visible($field)) {
            return $order ? $order->getAttribute($field) : null;
        }

        return $request->input($field) ?: null;
    }

    /**
     * قائمةُ المواد المختارة تخصّ المنتجَ المصنَّع نفسَه.
     *
     * قائمةُ مواد «عطر عود» على أمرٍ ينتج «عطر مسك» تصرف موادَّ منتجٍ
     * وتُدخل آخر — وتكلفتُه محسوبةٌ على وصفةٍ ليست وصفتَه.
     */
    protected function assertBomMatchesProduct(Request $request): void
    {
        if (! $request->filled('production_material_id') || ! $request->filled('product_id')) {
            return;
        }

        $bomProduct = ProductionMaterials::whereKey($request->production_material_id)->value('product_id');

        if ($bomProduct && (int) $bomProduct !== (int) $request->product_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'production_material_id' => 'بيانات الأمر: قائمة المواد المختارة تخصّ منتجاً آخر («'
                    .(Product::whereKey($bomProduct)->value('name') ?: '#'.$bomProduct)
                    .'») — اختر قائمةً للمنتج المصنَّع نفسِه.',
            ]);
        }
    }

    public function update(ManufacturOrderRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $order = ManufacturOrders::findOrFail($id);
            $this->authorizeOrder($order, 'update');
            // الأمرُ نفسُه في نطاق الفرع، والمراجعُ الواصلة في الطلب كذلك
            $this->authorizeBranchScope($order->branch_id);
            $this->assertOrderReferencesWithinBranch($request, $order);
            $this->assertOrderExpenseAccounts($request, $order);
            $this->assertBomMatchesProduct($request);

            /*
                لا تعديل بعد مغادرة حالة «جديد».

                لم يكن ثمّة حارس إطلاقاً — بخلاف كل العمليات الأخرى. فأمكن
                تعديل كميات وتكاليف أمرٍ مكتمل ومُرحَّل محاسبياً، فتصف
                القيودُ المثبَّتة واقعاً لم يعد قائماً. والأدهى أن تغيير
                `raw_quantity` بعد استلام المواد يمحو ما صُرف فعلاً، فلا
                يبقى سبيل لعكسه ولا لمعرفة مقداره.

                وحذفُ البنود وإعادةُ إنشائها يبدّل معرّفاتها كلّها، فينكسر
                أي مرجع خارجي إليها.
            */
            $status = $this->statusOf($order);

            if (! $status->isEditable()) {
                DB::rollBack();

                return redirect()->back()->withInput()->with(
                    'error',
                    'لا يمكن تعديل أمر تصنيع في حالة «'.$status->label().'». أرجع المواد أولاً ثم عدّل.'
                );
            }

            // الفترة المحاسبية — كما في كل عملية أخرى
            if ($block = $this->accountingPeriodBlock($order->created_at ?? now())) {
                DB::rollBack();

                return redirect()->back()->withInput()->with('error', $block);
            }

            $before = $order->only(['name', 'from_date', 'to_date', 'product_id', 'quantity', 'storehouse_id']);

            // العملة والفرع مجمَّدان بعد الحفظ — انظر [ScopesToBranch] و[UsesDocumentCurrency]
            $order->update($this->withoutBranch($this->withoutCurrency([
                'name' => $request->name,
                /*
                    التعديل لا يُعيد الترقيم: الرقم مرجعٌ خارجي يحمله القيد
                    وسجلّ العمليات والمطبوعات. ولا يُصدَر تالٍ إلّا لأمرٍ قديم
                    وصل بلا رقم.
                */
                'code' => $this->keepDocumentNumber($order->code, ManufacturingSources::SECTION_ORDERS),
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
                'account_id' => $this->headerField($request, 'account_id', $order),
                'employee_id' => $this->headerField($request, 'employee_id', $order),
                'client_id' => $this->headerField($request, 'client_id', $order),
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'production_material_id' => $this->headerField($request, 'production_material_id', $order),
                'production_path_id' => $this->headerField($request, 'production_path_id', $order),
                // مستودع المواد — اختياري؛ اختيارُه يحجز المواد فيه
                'storehouse_id' => $this->headerField($request, 'storehouse_id', $order),
                'updated_by' => auth()->id(),
            ])));

            /*
                البنود ومبالغها من الخادم — كما في `store`.

                و`syncOrderItems` تتولّى الحذف وإعادة البناء معاً، فلا يبقى
                `delete()` معلّقاً خارجها يمكن أن يُنسى في مسارٍ ثالث.
            */
            $order->update([
                'last_total_cost' => $this->syncOrderItems($order, $request),
            ]);

            /*
                إعادةُ الحجز بعد التعديل.

                البنودُ تُمسح وتُبنى من جديد، فحجزٌ قائمٌ على بنودٍ زالت
                يصير وعداً بكمياتٍ لم تعد مطلوبة. و`reserve()` تفكّ
                القائم قبل أن تحجز، فلا يتراكم.
            */
            $shortages = $this->reserveOrderMaterials($order->fresh(), $request);

            // تسجيل اشعار نظام جديد
            OperationLog::record(
                'updated',
                ManufacturingSources::LOG_ORDER,
                $order->id,
                'تم تحديث أمر التصنيع **'.$order->name.'** برقم '.$order->code,
                [
                    'old' => $before,
                    'new' => $order->only(['name', 'from_date', 'to_date', 'product_id', 'quantity', 'storehouse_id']),
                ],
            );

            // تأكيد حفظ البيانات
            DB::commit();

            if ($shortages !== []) {
                return redirect()->route('manufacturing.orders.index')
                    ->with('warning', $this->reservationShortageMessage($shortages));
            }

            return redirect()->route('manufacturing.orders.index')->with(['success' => 'تم تحديث البيانات بنجاح.']);

        } catch (\Exception $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'update', $id);
            DB::rollBack();
            \Log::error('Manufacturing Order Update Error: '.$e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            // `withInput`: ما أدخله المستخدم يعود معه — لا يُعاد ملءُ النموذج
            return redirect()->route('manufacturing.orders.edit', $id)
                ->withInput()
                ->with(['error' => $this->saveFailureMessage($e, 'حدث خطأ أثناء تحديث البيانات.')]);
        }
    }

    public function show($id)
    {
        /*
            التحميل المسبق للبنود وعلاقاتها الخمس.

            `cost-section.blade.php` يقرأ rawProduct و rawProductionStage
            و expensesAccount و workStation و endLifeProduct لكل صفّ،
            والقالب يُدرَج أربع مرّات لكل أمر — فأمرٌ بعشرين بنداً كان
            يكلّف أربعين إلى ثمانين استعلاماً في صفحة واحدة.
        */
        $order = ManufacturOrders::with([
            'manufacturOrdersItem.rawProduct',
            'manufacturOrdersItem.rawProductionStage',
            'manufacturOrdersItem.expensesAccount',
            'manufacturOrdersItem.workStation',
            'manufacturOrdersItem.endLifeProduct',
            'product',
            'client',
        ])->findOrFail($id);
        $this->authorizeBranchScope($order->branch_id);

        $storehouse = $this->accessibleStorehouses();

        /*
            الأذونات من هنا لا من داخل القالب.

            `permits-tab.blade.php` كان يستعلم قاعدة البيانات مباشرةً
            بمعرّف سحري مكتوب باليد، ويُدرَج مرّتين لكل عرض.
        */
        /*
            التجميع بالمصدر لا بالنوع.

            كان `groupBy('permission_type')` — والنوع `1` (إضافة) يشمل
            **ثلاثة مصادر**: المنتج التام (3) والهالك (4) ومرتجع المواد (16).
            فتبويب «مرتجعات المواد» كان يعرض المنتج التام والهالك تحته:
            بياناتٌ صحيحة تحت عنوان يكذّبها، والمستخدم يبحث عن ناتج إنتاجه
            فيجده مصنَّفاً مرتجعاً — ولا تبويب باسمه أصلاً.

            والتقييد بـ`reference_type` لازم: `reference_id` يحمل معرّفات
            مستندات من أنواع شتّى، وقائمة مواد رقمها 5 كانت تُظهر إذنها في
            تبويبات أمر التصنيع رقم 5.
        */
        $permitsBySource = WarehousePermits::where('reference_type', ReferenceType::MANUFACTURING_ORDER->value)
            ->where('reference_id', $order->id)
            ->whereIn('permission_source_id', [
                ManufacturingSources::PERMIT_ISSUE_MATERIALS,
                ManufacturingSources::PERMIT_FINISHED_PRODUCT,
                ManufacturingSources::PERMIT_WASTE,
                ManufacturingSources::PERMIT_RETURNED_MATERIALS,
                15,   // مصدرٌ خاطئ كان يُكتب قبل الإصلاح — يبقى للعرض التاريخي
            ])
            ->with(['items.product', 'storeHouse'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('permission_source_id');

        $logs = ModelsLog::where('type', ManufacturingSources::LOG_ORDER)
            ->where('type_id', $id)
            ->whereHas('manufacturing_order')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        return view('manufacturing::orders.show', compact('order', 'logs', 'storehouse', 'permitsBySource'));
    }

    // ═══ الملاحظات ═══

    public function addNote(Request $request, $id)
    {
        try {
            $request->validate([
                'description' => 'required|string|max:1000',
                'process' => 'required|string|max:255',
                'date' => 'required|date',
                'time' => 'required|string',
                'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120', // 5MB max
            ]);

            $order = ManufacturOrders::findOrFail($id);
            // ملاحظاتُ أمرٍ في فرعٍ آخر لا تُكتب ولا تُقرأ
            $this->authorizeBranchScope($order->branch_id);

            // التعامل مع المرفق إذا تم رفعه
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time().'_'.$file->getClientOriginalName();
                $attachmentPath = $file->storeAs('purchase_invoices/notes', $fileName, 'public');
            }

            // إنشاء الملاحظة باستخدام ClientRelation
            $clientRelation = ClientRelation::create([
                'process' => $request->process,
                'time' => $request->time,
                'date' => $request->date,
                /*
                    الربط بالأمر — كان معلّقاً.

                    فلم يكن ثمّة ما يربط الملاحظة بأمرها، ونتيجته المباشرة
                    أن `getNotes` تعرض ملاحظات كل الأوامر في كل أمر.
                */
                'reference_id' => $order->id,
                'employee_id' => auth()->id(),
                'description' => $request->description,
                'attachment' => $attachmentPath,
                'type' => 'manufacturing_order', // نوع مختلف للفواتير
            ]);

            // إرسال إشعار
            notifications::create([
                // `user_id` عمودٌ لا يُكتب في أي مكان على أمر التصنيع،
                // فكان الإشعار يُنشأ بمستقبِلٍ فارغ لا يصل أحداً
                'user_id' => $order->created_by,
                'receiver_id' => $order->created_by,
                'title' => 'ملاحظة جديدة',
                'description' => 'تم إضافة ملاحظة جديدة  امر تصنيع رقم '.$order->code,
            ]);

            /*
                تسجيل النشاط — و«فاتورة الشراء» في النصّ كانت خطأ نسخٍ:
                الشاشةُ شاشةُ أمر تصنيع، فيقرأ المستخدم في الخطّ الزمني
                لأمره سطراً يتحدّث عن مستندٍ من وحدةٍ أخرى.
            */
            OperationLog::record(
                'created',
                ManufacturingSources::LOG_ORDER,
                $order->id,
                sprintf(
                    'تم إضافة ملاحظة جديدة لأمر التصنيع رقم **%s** بعنوان: %s',
                    $order->code ?? '',
                    $request->process
                ),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ الملاحظة بنجاح',
                'note' => [
                    'id' => $clientRelation->id,
                    'description' => $clientRelation->description,
                    'process' => $clientRelation->process,
                    'date' => $clientRelation->date,
                    'time' => $clientRelation->time,
                    'employee_name' => auth()->user()->name,
                    'has_attachment' => $attachmentPath ? true : false,
                    'attachment_url' => $attachmentPath ? asset('storage/'.$attachmentPath) : null,
                ],
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'addNote', $id);

            return response()->json([
                'success' => false,
                'message' => $this->safeError($e, 'حدث خطأ أثناء حفظ الملاحظة.'),
            ], 500);
        }
    }

    // جلب الملاحظات
    public function getNotes($id = null)
    {
        try {
            /*
                `$id` كان يُستقبَل ويُهمَل — فكل أمر تصنيع يعرض ملاحظات
                كل أوامر التصنيع في النظام، عبر كل الفروع.

                يبقى اختيارياً لأن المسار `/get-general-notes` يوجّه إلى
                هذه الدالة نفسها بلا معرّف.
            */
            $notes = ClientRelation::where('type', 'manufacturing_order')
                ->when($id, function ($q) use ($id) {
                    $order = ManufacturOrders::findOrFail($id);
                    $q->where('reference_id', $order->id);
                })
                ->with('employee')
                ->orderBy('created_at', 'desc')
                ->limit(200)
                ->get();

            // تنسيق البيانات لتتطابق مع JavaScript
            $formattedNotes = $notes->map(function ($note) {
                return [
                    'id' => $note->id,
                    'description' => $note->description,
                    'process' => $note->process,
                    'date' => $note->date,
                    'time' => $note->time,
                    'employee_name' => $note->employee->name ?? 'غير محدد',
                    'has_attachment' => ! empty($note->attachment),
                    'attachment_url' => $note->attachment ? asset('storage/'.$note->attachment) : null,
                    'created_at' => $note->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'notes' => $formattedNotes->toArray(),
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع هنا: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'getNotes', $id);

            return response()->json([
                'success' => false,
                'message' => $this->safeError($e, 'حدث خطأ أثناء جلب الملاحظات.'),
            ], 500);
        }
    }

    // حذف الملاحظة
    public function deleteNote($noteId)
    {
        try {
            /*
                القيد على النوع — كان `findOrFail` بلا شرط.

                `client_relations` جدول مشترك بين أنواع المستندات: عروض
                أسعار وفواتير وأوامر تصنيع. فمن يملك صلاحية ملاحظات
                التصنيع كان يحذف **أي ملاحظة في النظام** بتمرير معرّفها.
            */
            $note = ClientRelation::where('id', $noteId)
                ->where('type', 'manufacturing_order')
                ->firstOrFail();

            /*
                الأمر الحقيقي لا الملاحظة.

                كان `$order = $note;` ثم `$order->code` — و`ClientRelation`
                بلا عمود `code`، فكل سطر حذف يُكتب برقم أمر فارغ، ويُمرَّر
                معرّف الملاحظة في خانة معرّف الأمر فيُنسب الحدث لأمرٍ آخر.
            */
            $order = $note->reference_id ? ManufacturOrders::find($note->reference_id) : null;

            if ($order) {
                $this->authorizeOrder($order, 'update');
            }

            if ($note->attachment && Storage::disk('public')->exists($note->attachment)) {
                Storage::disk('public')->delete($note->attachment);
            }

            $process = $note->process;
            $note->delete();

            OperationLog::record(
                'deleted',
                ManufacturingSources::LOG_ORDER,
                $order?->id,
                $order
                    ? sprintf('تم حذف ملاحظة "%s" من أمر التصنيع رقم %s', $process, $order->code)
                    : sprintf('تم حذف ملاحظة "%s" غير مرتبطة بأمر', $process),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الملاحظة بنجاح',
            ]);
        } catch (\Exception $e) {
            OperationLog::failure($e, ManufacturingSources::LOG_ORDER, 'deleteNote');

            return response()->json([
                'success' => false,
                'message' => $this->safeError($e, 'حدث خطأ أثناء حذف الملاحظة.'),
            ], 500);
        }
    }

    /**
     * استلام المواد من المستودع
     * يتم خصم المواد الخام من المستودع وتحديث حالة الأمر
     */
    /**
     * عرض صفحة استلام المواد
     */
    public function showReceiveMaterialsForm($id)
    {
        $order = ManufacturOrders::with([
            'manufacturOrdersItem.rawProduct.productUnits.categoryUnit.unit',
            'manufacturOrdersItem.rawProduct.category.categoryUnits.unit',
            'manufacturOrdersItem.rawProductionStage',
            'manufacturOrdersItem.rawUnit.categoryUnit.unit',
        ])->findOrFail($id);

        /*
            الحالةُ تُسأل من تعدادها لا من النموذج.

            كان الاستدعاء `$order->canReceiveMaterials()` — دالّةٌ انتقلت
            إلى [\App\Enums\ManufacturOrderStatus] ولم يبقَ لها أثرٌ على
            النموذج. فترمي BadMethodCallException، **وشاشةُ استلام المواد
            لا تُفتح إطلاقاً**.

            وهي من عائلة `resolveDocumentNumber` التي عطّلت دورةَ الأمر:
            منطقُ الحالة نُقل إلى التعداد وبقي نداءٌ قديمٌ يشير إلى فراغ.
        */
        if (! $this->statusOf($order)->canReceiveMaterials()) {
            return redirect()->back()->with('error', 'لا يمكن استلام المواد لهذا الأمر في حالته الحالية');
        }

        // جلب المواد الخام من أمر التصنيع
        $rawMaterials = $order->manufacturOrdersItem()
            ->whereNotNull('raw_product_id')
            ->with([
                'rawProduct.productUnits.categoryUnit.unit',
                'rawProduct.category.categoryUnits.unit',
                'rawProductionStage',
                'rawUnit.categoryUnit.unit',
            ])
            ->get();

        // جلب المواد الخام مع وحداتها للجداول (نفس طريقة create)
        $raw_materials = Product::with([
            'productUnits' => function ($query) {
                $query->where('is_active', true)
                    ->with(['categoryUnit' => function ($q) {
                        $q->with('unit');
                    }]);
            },
            'category.categoryUnits' => function ($query) {
                $query->where('is_active', true)->with('unit');
            },
        ])->get();

        // تحضير بيانات المنتجات مع الوحدات للـ View
        $productsWithUnits = $raw_materials->map(function ($product) {
            $units = [];

            // إذا كان للمنتج وحدات خاصة به
            if ($product->productUnits && $product->productUnits->count() > 0) {
                foreach ($product->productUnits as $productUnit) {
                    if ($productUnit->categoryUnit && $productUnit->categoryUnit->unit) {
                        $units[] = [
                            'id' => $productUnit->id,
                            'name' => $productUnit->categoryUnit->unit->name,
                            'conversion_factor' => (float) $productUnit->categoryUnit->conversion_factor,
                            'is_base_unit' => $productUnit->categoryUnit->is_base_unit ?? false,
                            'is_default' => $productUnit->is_default,
                            'sale_price' => (float) $productUnit->sale_price,
                            'purchase_price' => (float) $productUnit->purchase_price,
                            'barcode' => $productUnit->barcode,
                        ];
                    }
                }
            }
            // إذا لم يكن للمنتج وحدات خاصة، نستخدم وحدات الصنف
            elseif ($product->category && $product->category->categoryUnits) {
                foreach ($product->category->categoryUnits as $categoryUnit) {
                    if ($categoryUnit->unit) {
                        $units[] = [
                            'id' => null,
                            'category_unit_id' => $categoryUnit->id,
                            'name' => $categoryUnit->unit->name,
                            'conversion_factor' => (float) $categoryUnit->conversion_factor,
                            'is_base_unit' => $categoryUnit->is_base_unit ?? false,
                            'is_default' => $categoryUnit->is_base_unit ?? false,
                            'sale_price' => (float) $product->sale_price,
                            'purchase_price' => (float) $product->purchase_price,
                            'barcode' => null,
                        ];
                    }
                }
            }

            // الحصول على الكمية المتاحة
            $availableQuantity = 0;
            if ($product->productDetails && $product->productDetails->count() > 0) {
                $availableQuantity = $product->productDetails->sum('quantity');
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku ?? '',
                'barcode' => $product->barcode,
                'sale_price' => (float) $product->sale_price,
                'purchase_price' => (float) $product->purchase_price,
                'available_quantity' => $availableQuantity,
                'type' => $product->type ?? '',
                'units' => $units,
                'has_units' => count($units) > 0,
            ];
        });

        // مستودعاتُ جهة الشريط ومعها مستودعُ الأمر — مستودعُ فرعٍ آخر يرفضه الصرف
        $storeHouses = $this->accessibleStorehouses($order->storehouse_id);

        /*
            الأرقام الثلاثة لكل مادّة في كل مستودع — الفعلي والمحجوز والمتاح.

            الشاشةُ كانت تعرض «المتوفر» بعد فشل المحاولة فقط. والقرارُ
            يُتَّخذ قبلها: أيُّ مستودعٍ يكفي؟ ورقمٌ واحد لا يجيب — مستودعٌ
            فيه ١٠٠ وتسعون منها محجوزةٌ لأمرٍ آخر يبدو كافياً وهو ليس كذلك.

            وحجزُ هذا الأمر يُستثنى، وإلا ظهر المتاحُ ناقصاً بما حجزه هو.
        */
        $reservations = app(ReservationService::class);

        $stockByWarehouse = [];

        foreach ($order->manufacturOrdersItem as $item) {
            if (! $item->raw_product_id || $item->raw_quantity <= 0) {
                continue;
            }

            foreach ($storeHouses as $house) {
                $stockByWarehouse[$house->id][$item->raw_product_id] = $reservations->breakdown(
                    (int) $item->raw_product_id,
                    (int) $house->id,
                    (int) $order->id
                );
            }
        }

        return view('manufacturing::orders.receive-materials', compact(
            'order', 'rawMaterials', 'storeHouses', 'productsWithUnits', 'stockByWarehouse'
        ));
    }

    /**
     * عرض نموذج إرجاع المواد (عكس استلام المواد)
     */
    public function showReturnMaterialsForm($id)
    {
        $order = ManufacturOrders::with([
            'manufacturOrdersItem.rawProduct',
            'manufacturOrdersItem.rawUnit.categoryUnit.unit',
        ])->findOrFail($id);

        /*
            الشرطُ من التعداد — وثابتُه لم يعد موجوداً.

            كان: `$order->status !== ManufacturOrders::STATUS_MATERIALS_RECEIVED
            && ! $order->isCompleted()`. والثابتُ والدالّةُ كلاهما انتقل
            إلى [\App\Enums\ManufacturOrderStatus]، فترمي الشاشة
            BadMethodCallException ولا تُفتح.

            والشرطُ الجديد ليس ترجمةً حرفية: كان يسمح بالإرجاع بعد
            **الإتمام**، وذلك ممنوعٌ منذ المرحلة ٤ — المواد استُهلكت
            وأُقفل «مخزون تحت التشغيل»، فإرجاعُها يفتحه بسالبٍ لا مصدر
            له. والطريق الصحيح: التراجع عن الإتمام ثم الإرجاع.
        */
        if (! $this->statusOf($order)->canReturnMaterials()) {
            return redirect()->back()->with(
                'error',
                'لا يمكن إرجاع المواد وأمر التصنيع في حالة «'.$this->statusOf($order)->label().'».'
            );
        }

        // مستودعاتُ جهة الشريط — ومعها مستودعُ الأمر نفسُه، وهو المختارُ ابتداءً
        $storehouses = $this->accessibleStorehouses($order->storehouse_id);

        /*
            سطرٌ لكل مادة — بما يحسبه الخادمُ عند الإرجاع: المصروفُ بالوحدة
            الأساسية، وما أُرجع قبلاً، والمتاح، والتكلفةُ الفعلية للوحدة.
        */
        $returnedBefore = $this->returnedSoFar($order);
        $products = Product::with(['productUnits.categoryUnit.unit', 'category.categoryUnits.unit'])
            ->whereIn('id', $order->manufacturOrdersItem->pluck('raw_product_id')->filter()->unique()->all())
            ->get()->keyBy('id');

        $returnLines = [];

        foreach ($this->issuedByProduct($order->manufacturOrdersItem) as $productId => $row) {
            $returned = (float) ($returnedBefore[$productId] ?? 0);
            $product = $products->get($productId);

            $returnLines[] = [
                'product_id' => $productId,
                'name' => $product->name ?? ('#'.$productId),
                'unit' => $this->baseUnitName($product),
                'issued' => $row['quantity'],
                'returned' => $returned,
                'remaining' => max(0.0, round($row['quantity'] - $returned, 4)),
                'unit_cost' => $row['unit_cost'],
            ];
        }

        return view('manufacturing::orders.return-materials', compact('order', 'storehouses', 'returnLines'));
    }

    /** اسمُ الوحدة الأساسية للصنف — ما يُمسَك به المخزون. */
    protected function baseUnitName(?Product $product): string
    {
        if (! $product) {
            return 'وحدة';
        }

        foreach ($product->productUnits ?? [] as $productUnit) {
            if ($productUnit->categoryUnit && ($productUnit->categoryUnit->is_base_unit ?? false) && $productUnit->categoryUnit->unit) {
                return $productUnit->categoryUnit->unit->name;
            }
        }

        $categoryUnits = optional($product->category)->categoryUnits ?? collect();
        $base = $categoryUnits->firstWhere('is_base_unit', true) ?? $categoryUnits->first();

        if ($base && $base->unit) {
            return $base->unit->name;
        }

        $first = collect($product->productUnits ?? [])->first();

        return optional(optional(optional($first)->categoryUnit)->unit)->name ?: 'وحدة';
    }
}
