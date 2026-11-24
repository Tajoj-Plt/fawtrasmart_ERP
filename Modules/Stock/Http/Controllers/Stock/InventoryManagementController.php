<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Constants\permissions\PermissionSources;
use App\Constants\planFeatures\StockPlanFeatures;
use App\Constants\SystemAccountIds;
use App\Enums\LinkingType;
use App\Enums\ProductType;
use App\Enums\ReferenceType;
use App\Exports\InventoryAdjustmentsExport;
use App\Exports\WarehousePermitsExport;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Product;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\Fawtra\UsagePush;
use App\Support\AccountingPeriodFilter;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Stock\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

class InventoryManagementController extends Controller
{
    use GeneratesDocumentNumbers, ResolvesAccountingAccounts, RespectsAccountingPeriods, ScopesToEmployeeHierarchy, UsesDocumentCurrency;

    /**
     * حماية على مستوى المتحكّم بجانب الراوت — نفس أسماء `stock.adjustments.*`
     * المستعملة في `Modules/Stock/routes/web.php`، لا الأسماء القديمة
     * `inv_manage_inventory_permission_*` التي كانت هنا فتُضاف شرطاً ثانياً
     * فوق شرط الراوت الجديد بلا داعٍ.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.adjustments.view', ['only' => ['index', 'show', 'doStock']]);
        $this->middleware('permission.role:stock.adjustments.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:stock.adjustments.count', ['only' => ['saveFinal']]);
        $this->middleware('permission.role:stock.adjustments.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.adjustments.delete', ['only' => ['delete']]);
        $this->middleware('permission.role:stock.adjustments.approve', ['only' => ['adjustment', 'updateStatus']]);
        $this->middleware('permission.role:stock.adjustments.cancel', ['only' => ['Canceladjustment']]);
        $this->middleware('permission.role:stock.adjustments.export', ['only' => ['export', 'exportWarehousePermits', 'exportAllWarehousePermits']]);
    }

    /**
     * عرض قائمة الجردات
     */
    public function index(Request $request)
    {
        // بناء الاستعلام مع الفلاتر
        $query = InventoryAdjustment::with(['storeHouse', 'items.product.category']);

        // نطاق البيانات: منشئو ورقة الجرد ضمن ما يسمح به نطاق المستخدم،
        // ومستودعها (stock_id) ضمن مستودعات فرعه أيضاً — الجرد مرتبطٌ بمستودعٍ
        // بعينه، فمن يرى «بيانات فرعه» يراها عبر مستودعات فرعه لا عبر من أنشأها فقط
        $this->scopeToCreator($query);
        $this->scopeToWarehouse($query, 'stock_id');

        /*
            **الفلترة بالعلاقة لا بالتاريخ.**

            ورقةُ الجرد تُختَم بفترتها لحظةَ إنشائها، والنطاقُ الافتراضي
            السنةُ المالية الجارية — فلا تُعرض جرودُ سنواتٍ مضت في صفحةٍ
            واحدة، ولا تُقارَن تسويةٌ بأخرى من سنةٍ مقفلة.
        */
        $period = $this->stockPeriod($request);
        AccountingPeriodFilter::apply($query, $period);

        // تطبيق الفلاتر
        if ($request->has('storehouse_id') && $request->storehouse_id) {
            $query->where('stock_id', $request->storehouse_id);
        }

        if ($request->has('product_id') && $request->product_id) {
            $query->whereHas('items.product', function ($q) use ($request) {
                $q->where('id', $request->product_id);
            });
        }

        if ($request->has('category_id') && $request->category_id) {
            $query->whereHas('items.product.category', function ($q) use ($request) {
                $q->where('id', $request->category_id);
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // جلب البيانات
        $adjustments = $query->get();

        // جلب البيانات الإضافية للفلاتر
        $storehouses = StoreHouse::query()
            ->when($this->allowedWarehouseIds(), fn ($q, $ids) => $q->whereIn('id', $ids ?: [0]))
            ->get();
        $products = Product::all();
        $categories = Category::all();

        // If it's an AJAX request, return JSON data
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => view('stock::inventory_management.partials.table', compact('adjustments'))->render(),
            ]);
        }

        // تسجيل النشاط
        $this->logActivity('عرض قائمة الجردات', 'inventory', null, 'list');

        return view('stock::inventory_management.index', compact('adjustments', 'storehouses', 'products', 'categories', 'period'));
    }

    /**
     * صفحة إنشاء جرد جديد
     */
    public function create()
    {
        $storehouses = StoreHouse::query()
            ->when($this->allowedWarehouseIds(), fn ($q, $ids) => $q->whereIn('id', $ids ?: [0]))
            ->get();

        // تسجيل النشاط
        $this->logActivity('فتح صفحة إنشاء جرد جديد', 'inventory', null, 'create');

        return view('stock::inventory_management.create_step1', compact('storehouses'));
    }

    /**
     * حفظ بيانات الجرد الأساسية
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'storehouse_id' => 'required|exists:store_houses,id',
            'inventory_time' => 'required|date',
            'calculation_type' => 'required|in:dated,undated',
            'type' => 'required|in:full,partial,spot,category',
            'categories' => 'required_if:type,category|array',
            'reference_number' => 'nullable|string|max:50',
            'note' => 'nullable|string',
        ]);

        $this->assertWarehouseScope((int) $validated['storehouse_id']);

        $adjustment = InventoryAdjustment::create([
            'stock_id' => $validated['storehouse_id'],
            'inventory_time' => $validated['inventory_time'],
            'type' => $validated['type'],
            'status' => 'draft',
            'calculation_type' => $validated['calculation_type'],
            'categories' => $validated['type'] === 'category' ? $validated['categories'] : null,
            'reference_number' => $validated['reference_number'],
            'note' => $validated['note'],
            'created_by' => Auth::id(),
        ]);

        // تسجيل النشاط
        $this->logActivity(
            'إنشاء ورقة جرد جديدة - المستودع: '.$adjustment->storeHouse->name.' - النوع: '.$adjustment->type,
            'inventory',
            $adjustment->id,
            'create'
        );

        return redirect()->route('inventory.do_stock', $adjustment->id);
    }

    /**
     * صفحة إدخال بيانات الجرد (الكميات)
     */
    public function doStock($id)
    {
        $adjustment = InventoryAdjustment::findOrFail($id);
        $this->authorizeCreatorScope($adjustment->created_by);
        $this->authorizeWarehouseScope($adjustment->stock_id);

        $products = $this->countableProducts($adjustment);

        // تسجيل النشاط
        $this->logActivity(
            'فتح صفحة إدخال كميات الجرد - ورقة الجرد رقم: '.$adjustment->id,
            'inventory',
            $adjustment->id,
            'view'
        );

        return view('stock::inventory_management.doStock', compact('adjustment', 'products'));
    }

    /**
     * صفحة تعديل عناصر الجرد
     */
    public function edit($id)
    {
        $adjustment = InventoryAdjustment::findOrFail($id);
        $this->authorizeCreatorScope($adjustment->created_by);
        $this->authorizeWarehouseScope($adjustment->stock_id);

        /*
            التعديل قبل الإكمال فقط — نفس حدّ `saveFinal` الذي تُحفظ إليه هذه
            الشاشة. ورقةٌ «مكتملة» تعاد إلى «قيد التنفيذ» من شاشة العرض أولاً،
            وورقةٌ «مُسوّاة» ارتبطت بأذونٍ معتمدة فلا تُمَسّ إطلاقاً.
        */
        if (! in_array($adjustment->status, ['draft', 'in_progress'], true)) {
            return redirect()
                ->route('inventory.show', $adjustment->id)
                ->with('error', $adjustment->status === 'adjusted'
                    ? 'لا يمكن تعديل ورقة جردٍ تمت تسويتها — ارتبطت بأذونٍ معتمدة وقيود مرحَّلة.'
                    : 'لا يمكن تعديل جردٍ مكتمل — أعده إلى «قيد التنفيذ» أولاً من شاشة العرض.');
        }

        $item = InventoryItem::where('adjustment_id', $id)->get();

        /*
            القالب يحتاج `$adjustment` (وجهة الحفظ) و`$products` (قائمة الأصناف
            وأرصدتها)، وكان لا يستقبل إلا `$item` — فتسقط الصفحة على «متغيّر غير
            معرَّف» قبل أن تُعرض. الأرصدة تُقرأ كما تقرؤها `doStock` تماماً.
        */
        $products = $this->countableProducts($adjustment);

        // تسجيل النشاط
        $this->logActivity(
            'فتح صفحة تعديل عناصر الجرد - ورقة الجرد رقم: '.$id,
            'inventory',
            $id,
            'edit'
        );

        return view('stock::inventory_management.edit', compact('adjustment', 'item', 'products'));
    }

    /**
     * أصناف شاشة الجرد — المخزنيّ القابل للعدّ ضمن نطاق المستخدم.
     *
     * كانت القائمة `Product::leftJoin(...)` بلا شرط: كلُّ ما في النظام،
     * والخدماتُ والمنتجات الرقمية بينها (ولا جردَ لما لا يُخزَّن)، وأصنافُ
     * مستودعاتٍ خارج نطاق الموظف كذلك. صارت:
     *
     *   • الأنواع المخزنية وحدها (`ProductType::stockedValues()`).
     *   • وللمقيَّد: ما له رصيدٌ (سجلّ `product_details`) في مستودعاته أو
     *     مستودعات من يديرهم — `accessibleWarehouseIds()` نفسها، والفارغة
     *     تعني لا شيء لا «بلا قيد». غيرُ المقيَّد (`data_scope_all`/غير
     *     الموظف) يرى الأصناف المخزنية كلَّها.
     *
     * والرصيد المعروض يبقى رصيدَ مستودعِ الورقة نفسِه — فهو ما يُجرَد.
     */
    private function countableProducts(InventoryAdjustment $adjustment)
    {
        $warehouseIds = $this->accessibleWarehouseIds();

        return Product::query()
            ->whereIn('products.type', ProductType::stockedValues())
            ->when($warehouseIds !== null, fn ($q) => $q->whereHas(
                'productDetails',
                fn ($d) => $d->whereIn('store_house_id', $warehouseIds ?: [0])
            ))
            ->leftJoin('product_details', function ($join) use ($adjustment) {
                $join->on('products.id', '=', 'product_details.product_id')
                    ->where('product_details.store_house_id', $adjustment->stock_id);
            })
            ->select('products.*', DB::raw('COALESCE(product_details.quantity, 0) as current_quantity'))
            ->get();
    }

    /**
     * حفظ البيانات النهائية للجرد
     * يحفظ الكميات والصور والملاحظات
     */
    public function saveFinal(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $adjustment = InventoryAdjustment::findOrFail($id);
            $this->authorizeCreatorScope($adjustment->created_by);
            $this->authorizeWarehouseScope($adjustment->stock_id);

            // التحقق من أن الجرد في الحالة الصحيحة
            if ($adjustment->status !== 'draft' && $adjustment->status !== 'in_progress') {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا يمكن تعديل جرد تم اكتماله أو تسويته',
                    ], 422);
                }

                return back()->with('error', 'لا يمكن تعديل جرد تم اكتماله أو تسويته');
            }

            $items = $request->input('items', []);

            // تحديث حالة الجرد إلى "مكتمل"
            $adjustment->status = 'completed';
            $adjustment->save();

            $savedCount = 0;

            foreach ($items as $productId => $itemData) {
                // البحث عن عنصر موجود أو إنشاء عنصر جديد
                $item = InventoryItem::firstOrNew([
                    'adjustment_id' => $adjustment->id,
                    'product_id' => $productId,
                ]);

                // تحديث أو تعيين البيانات
                $item->quantity_in_stock = $itemData['quantity_in_stock'] ?? 0;
                $item->quantity_in_system = $itemData['quantity_in_system'] ?? 0;
                $item->quantity_difference = ($itemData['quantity_in_stock'] ?? 0) - ($itemData['quantity_in_system'] ?? 0);
                $item->unit_price = $itemData['unit_price'] ?? 0;
                $item->image = $itemData['image'] ?? null;
                $item->note = $itemData['note'] ?? null;

                $item->save();
                $savedCount++;
            }

            DB::commit();

            // تسجيل النشاط
            $this->logActivity(
                'إكمال عملية الجرد - ورقة الجرد رقم: '.$adjustment->id.' - عدد المنتجات المحدثة: '.$savedCount,
                'inventory',
                $adjustment->id,
                'complete'
            );

            return redirect()
                ->route('inventory.show', $adjustment->id)
                ->with('success', "تم حفظ {$savedCount} منتج بنجاح. يمكنك الآن إجراء التسوية.");

        } catch (\Exception $e) {
            OperationLog::failure($e, 'inventory', 'saveFinal');
            DB::rollBack();

            return back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * عرض تفاصيل الجرد
     */
    public function show($id)
    {
        $adjustment = InventoryAdjustment::with([
            'storeHouse',
            'items.product.category',
            'items.product',
            'creator',
            'adjuster',
        ])->findOrFail($id);
        $this->authorizeCreatorScope($adjustment->created_by);
        $this->authorizeWarehouseScope($adjustment->stock_id);

        // جلب الأذون المخزنية المرتبطة بعملية الجرد
        /*
            الأذون بالمرجع لا بتفكيك نصّ الرقم: `LIKE 'INV-3-%'` يلتقط
            «INV-3-…» و«INV-30-…» و«INV-31-…» معاً — فتظهر أذون ورقةٍ أخرى
            في هذه الورقة متى تجاوزت المعرّفات عشرة.
        */
        $warehousePermits = WarehousePermits::query()
            ->where(function ($q) use ($adjustment) {
                $q->where(function ($q2) use ($adjustment) {
                    $q2->where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
                        ->where('reference_id', $adjustment->id);
                })->orWhere('number', 'LIKE', 'INV-'.$adjustment->id.'-%');   // أذونٌ قديمة قبل الربط بالمرجع
            })
            ->with(['warehousePermitsProducts.product', 'storeHouse', 'permissionSource'])
            ->get();

        // تسجيل النشاط
        $this->logActivity(
            'عرض تفاصيل الجرد - ورقة الجرد رقم: '.$adjustment->id,
            'inventory',
            $adjustment->id,
            'view'
        );

        return view('stock::inventory_management.show', compact('adjustment', 'warehousePermits'));
    }

    /**
     * تسوية الجرد وتحديث المخزون والحسابات
     */
    public function adjustment($id)
    {
        $adjustment = InventoryAdjustment::with(['items.product', 'storeHouse'])->findOrFail($id);
        $this->authorizeCreatorScope($adjustment->created_by);
        $this->authorizeWarehouseScope($adjustment->stock_id);

        // الفترة المحاسبية: التسوية تُرحِّل قيداً بتاريخ الجرد
        if ($block = $this->accountingPeriodBlock($adjustment->inventory_time ?? $adjustment->created_at)) {
            return redirect()->back()->with('error', $block);
        }

        // التحقق من أن الجرد مكتمل
        if ($adjustment->status !== 'completed') {
            // تسجيل النشاط
            $this->logActivity(
                'محاولة تسوية جرد غير مكتمل - ورقة الجرد رقم: '.$adjustment->id,
                'inventory',
                $adjustment->id,
                'error'
            );

            return redirect()->back()->with('error', 'يجب إكمال عملية الجرد قبل التسوية');
        }

        DB::beginTransaction();

        try {
            /*
                **تسويةٌ واحدة لا أكثر.**

                الورقة تبقى «مكتملة» بعد إصدار الأذون (لا تصير «مُسوّاة» إلا
                باعتمادها كلّها)، فكان زرّ «تسوية الآن» يبقى معروضاً والنقر
                الثاني يُصدر مجموعة أذونٍ ثانية لنفس الفروق — فرقُ جردٍ قدره
                10 يتحرّك 20 متى اعتُمد الكل.

                القفل هنا مزدوج: `lockForUpdate` على صفّ الورقة يُسلسل
                النقرتين المتزامنتين، وفحص الأذون القائمة يصدّ التكرار بعدهما.
                الأذون المرفوضة لا تُحتسب — رفضُ التسوية كلّها يفتح الباب
                لإصدارها من جديد.
            */
            InventoryAdjustment::whereKey($adjustment->id)->lockForUpdate()->first();

            $alreadyIssued = WarehousePermits::where(function ($q) use ($adjustment) {
                $q->where(function ($q2) use ($adjustment) {
                    $q2->where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
                        ->where('reference_id', $adjustment->id);
                })->orWhere('number', 'LIKE', 'INV-'.$adjustment->id.'-%');
            })
                ->whereNotIn('status', ['rejected', 'rejec'])
                ->exists();

            if ($alreadyIssued) {
                DB::rollBack();

                return redirect()
                    ->route('inventory.show', $adjustment->id)
                    ->with('error', 'سبق إصدار أذون تسويةٍ لهذه الورقة — لا تُنفَّذ التسوية مرتين. '
                        .'اعتمد الأذون القائمة من «إدارة الأذونات المخزنية»، أو اسحبها أولاً إن أردت إعادة التسوية.');
            }
            /*
                **التسوية تُصدر أذوناً، ولا تُرحِّل شيئاً.**

                كانت هذه الدالّة تفعل ثلاثة أشياء دفعةً واحدة: تُنشئ أذوناً
                مخزنية، وتُحرّك الكمّيات بنفسها، وتُرحّل قيد الجرد. والأذون
                تُولد بحالة `pending` (افتراض العمود) — أي **بانتظار
                الاعتماد**.

                فينشأ عيبان:

                  • *ازدواج*: مَن يعتمد تلك الأذون لاحقاً يحرّك الكمّيات
                    مرّةً ثانية، إذ `approve()` هو ما يُحرّكها أصلاً. فرقُ
                    جردٍ قدره 10 يصير 20.
                  • *التفاف على الاعتماد*: القيد المحاسبي يُرحَّل فوراً بلا
                    أن يوافق أحد — بينما كل حركةٍ مخزنية أخرى في النظام
                    لا تُرحَّل إلّا باعتماد إذنها.

                صارت التسوية تُصدر الأذون معلَّقةً وتقف. والاعتماد وحده
                يُحرّك الكمّية ويُرحّل القيد — كما في كل مستندٍ آخر. وورقة
                الجرد تبقى «مكتملة» حتى تُعتمد أذونها كلّها، فتصير «مُسوّاة».
            */
            $issued = 0;

            foreach ($adjustment->items as $item) {
                if ($item->quantity_difference == 0) {
                    continue;
                }

                $permit = new WarehousePermits;
                $permit->permission_date = now();
                // 1: إضافة (زيادة جرد)، 2: صرف (عجز جرد)
                $permit->permission_type = $item->quantity_difference > 0 ? 1 : 2;
                /*
                    الرقم من إعدادات «الترقيم المتسلسل» كسائر الأذون، لا صيغة
                    `INV-<ورقة>-<بند>` المخترعة هنا وحدها — فتسري البادئة
                    والطول المضبوطان في الإعدادات على أذون الجرد أيضاً.
                    والعودة من الإذن إلى ورقته بالمرجع أدناه لا بتفكيك الرقم.
                */
                $permit->number = $this->issueDocumentNumber(
                    $item->quantity_difference > 0 ? 'warehouse-add' : 'warehouse-dispose'
                );
                $permit->store_houses_id = $adjustment->stock_id;
                $permit->permission_source_id = PermissionSources::INVENTORY_ADJUSTMENT;

                /*
                    الربط بالمرجع لا بنصّ الرقم: كان السبيل الوحيد للعودة من
                    الإذن إلى ورقته هو تفكيك سلسلة `INV-<id>-<item>` — وهو
                    ربطٌ يتكسّر بأوّل تغيير في صيغة الترقيم.
                */
                $permit->reference_type = ReferenceType::STOCK_ADJUSTMENT;
                $permit->reference_id = $adjustment->id;

                $permit->created_by = Auth::id();
                $permit->status = 'pending';
                $permit->save();

                $unitPrice = $item->product->averageCost() ?? $item->product->purchase_price ?? 0;

                $permitItem = new WarehousePermitsProducts;
                $permitItem->warehouse_permits_id = $permit->id;
                $permitItem->product_id = $item->product_id;
                $permitItem->quantity = abs($item->quantity_difference);
                $permitItem->unit_price = $unitPrice;
                $permitItem->total = $permitItem->quantity * $unitPrice;
                $permitItem->save();

                $permit->grand_total = $permitItem->total;
                $permit->save();

                $issued++;
            }

            // الورقة تبقى «مكتملة»: لم تُسوَّ بعد، إذ لم يُعتمد شيء
            $adjustment->adjusted_by = Auth::id();
            $adjustment->save();

            DB::commit();

            // تسجيل النشاط
            $this->logActivity(
                'تسوية الجرد - ورقة الجرد رقم: '.$adjustment->id,
                'inventory',
                $adjustment->id,
                'adjust'
            );

            if ($issued === 0) {
                return redirect()
                    ->route('inventory.show', $adjustment->id)
                    ->with('info', 'لا فروق في هذه الورقة — لا إذن مطلوب ولا قيد.');
            }

            /*
                الرسالة تسمّي الخطوة التالية صراحةً. جملةُ «تمت التسوية
                بنجاح» القديمة كانت تُوهم أن العملية انتهت، بينما الكمّيات
                والقيد ما زالا موقوفَين على اعتمادٍ لم يعلم به أحد.
            */
            return redirect()
                ->route('inventory.show', $adjustment->id)
                ->with('warning', sprintf(
                    'أُصدر %d إذناً مخزنياً بانتظار الاعتماد. لن تتحرّك الكمّيات ولن يُرحَّل قيد الجرد '
                    .'قبل اعتمادها — انتقل إلى «إدارة الأذونات المخزنية» لاعتمادها.',
                    $issued
                ));

        } catch (\Exception $e) {
            OperationLog::failure($e, 'inventory', 'adjustment');
            DB::rollBack();

            // تسجيل الخطأ
            $this->logActivity(
                'خطأ في تسوية الجرد - ورقة الجرد رقم: '.$adjustment->id.' - الخطأ: '.$e->getMessage(),
                'inventory',
                $adjustment->id,
                'error'
            );

            return back()->with('error', 'حدث خطأ أثناء التسوية: '.$e->getMessage());
        }
    }

    /**
     * سحب أذون التسوية المعلَّقة — قبل الاعتماد فقط.
     *
     * **التسوية المعتمدة لا تُلغى من هنا.**
     *
     * كان في هذه الدالة مسارٌ كامل «يُلغي» تسويةً معتمدة: يعيد الكمّيات
     * بيده، **ويحذف** قيود اليومية حذفاً (لا قيداً عكسياً)، ويحذف الأذون
     * المعتمدة نفسها — فيختفي من الدفاتر أثرُ حركةٍ وقعت فعلاً، ويبقى
     * `email_logs`/سجل العمليات يشير إلى مستنداتٍ لا وجود لها. والورقة
     * تعود «مكتملة» فيُعاد تسويتها من جديد بأذونٍ جديدة.
     *
     * القاعدة الآن كسائر المستندات: ما اعتُمد إذنُه ارتبط به وارتبط بقيده،
     * فلا إلغاء ولا تعديل ولا حذف للورقة بعدها. ومن أراد التراجع فبابه
     * «إلغاء الموافقة» على الإذن نفسه من شاشة الأذونات — وهناك يُعكس القيد
     * قيداً عكسياً موثَّقاً وتُعاد الكمّيات، وتعود الورقة «مكتملة» تلقائياً.
     */
    public function Canceladjustment($id)
    {
        $adjustment = InventoryAdjustment::findOrFail($id);
        $this->authorizeCreatorScope($adjustment->created_by);
        $this->authorizeWarehouseScope($adjustment->stock_id);

        $permits = WarehousePermits::where(function ($q) use ($adjustment) {
            $q->where(function ($q2) use ($adjustment) {
                $q2->where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
                    ->where('reference_id', $adjustment->id);
            })->orWhere('number', 'LIKE', 'INV-'.$adjustment->id.'-%');
        })->get();

        $approved = $permits->whereIn('status', ['approved', 'apporod']);

        if ($adjustment->status === 'adjusted' || $approved->isNotEmpty()) {
            return redirect()->back()->with('error',
                'لا يمكن إلغاء التسوية: اعتُمد إذنٌ مخزني مرتبط بها وتحرّكت الكمّيات ورُحِّل القيد. '
                .'للتراجع استخدم «إلغاء الموافقة» على الإذن نفسه من شاشة إدارة الأذونات المخزنية.');
        }

        /*
            أذونٌ أُصدرت ولم تُعتمد لم تُحرّك شيئاً: لا كمّية ولا قيد.
            فسحبها حذفٌ للمعلَّق لا عكسٌ لحركةٍ لم تقع.
        */
        $pending = $permits->whereNotIn('status', ['approved', 'apporod']);

        if ($pending->isEmpty()) {
            return redirect()->back()->with('error', 'لا أذون تسويةٍ معلَّقة لهذه الورقة — لا شيء يُسحب.');
        }

        DB::transaction(function () use ($pending) {
            WarehousePermitsProducts::whereIn('warehouse_permits_id', $pending->pluck('id'))->delete();
            WarehousePermits::whereIn('id', $pending->pluck('id'))->delete();
        });

        // حذفٌ جماعي بلا أحداث نموذج — يُعلَن عنه ليُنقص الرقم عند فوترة
        UsagePush::markBulkChange(StockPlanFeatures::LIMIT_WAREHOUSE_PERMITS);

        $this->logActivity(
            'سحب أذون تسوية معلَّقة - ورقة الجرد رقم: '.$adjustment->id,
            'inventory',
            $adjustment->id,
            'cancel'
        );

        return redirect()->back()->with('success', sprintf(
            'سُحب %d إذناً معلَّقاً. لم تتحرّك كمّية ولم يُرحَّل قيد، فلا شيء يُعكَس — ويمكن إعادة التسوية.',
            $pending->count()
        ));
    }

    /**
     * حذف ورقة الجرد
     */
    public function delete($id)
    {
        DB::beginTransaction();

        try {
            $adjustment = InventoryAdjustment::findOrFail($id);
            $this->authorizeCreatorScope($adjustment->created_by);
            $this->authorizeWarehouseScope($adjustment->stock_id);

            // التحقق من أن الجرد في حالة مسودة فقط
            if ($adjustment->status !== 'draft') {
                // If it's an AJAX request, return JSON response
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا يمكن حذف الجرد إلا إذا كان في حالة مسودة',
                    ], 422);
                }

                return redirect()->back()->with('error', 'لا يمكن حذف الجرد إلا إذا كان في حالة مسودة');
            }

            // حذف عناصر الجرد أولاً
            InventoryItem::where('adjustment_id', $id)->delete();

            // حذف ورقة الجرد
            $adjustment->delete();

            DB::commit();

            // تسجيل النشاط
            $this->logActivity(
                'حذف ورقة الجرد - رقم: '.$id,
                'inventory',
                $id,
                'delete'
            );

            // If it's an AJAX request, return JSON response
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم حذف ورقة الجرد بنجاح!',
                ]);
            }

            return redirect()
                ->route('inventory_management.index')
                ->with('success', 'تم حذف ورقة الجرد بنجاح!');

        } catch (\Exception $e) {
            OperationLog::failure($e, 'inventory', 'delete');
            DB::rollBack();

            // تسجيل الخطأ
            $this->logActivity(
                'خطأ في حذف ورقة الجرد - رقم: '.$id.' - الخطأ: '.$e->getMessage(),
                'inventory',
                $id,
                'error'
            );

            // If it's an AJAX request, return JSON response
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حذف ورقة الجرد: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'حدث خطأ أثناء حذف ورقة الجرد: '.$e->getMessage());
        }
    }

    /**
     * إنشاء قيد محاسبي لتسوية الجرد
     */
    private function createAdjustmentJournalEntry($adjustment)
    {
        // جلب حساب المخزون
        // حساب المستودع من نقطة واحدة: تحت الأب المضبوط في
        // «الحسابات المحاسبية»، بنوعٍ وكودٍ موحَّدين
        $inventoryAccount = $this->warehouseAccount($adjustment->stock_id, $adjustment->storeHouse->name ?? null);

        // الطرف المقابل لفروق الجرد كما ضُبط في «الحسابات المحاسبية».
        // كان المعرّف 50 مكتوباً في الشيفرة وتعليقه يسمّيه «أطراف دائنة أخرى»،
        // وهو في الشجرة «تكلفة البضاعة المباعة» — والاسم الصحيح يظهر الآن من
        // الإعداد نفسه، ويستطيع المحاسب توجيهه إلى حساب عجز وزيادة المخزون
        $adjustmentAccount = $this->requireAccountingAccount(
            'inventory_adjustment',
            SystemAccountIds::COST_OF_SALES
        );

        // معالجة كل عنصر في الجرد
        foreach ($adjustment->items as $item) {
            if ($item->quantity_difference != 0) {
                $unitPrice = $item->product->averageCost() ?? $item->product->purchase_price ?? 0;
                $adjustmentValue = abs($item->quantity_difference) * $unitPrice;

                // إنشاء قيد اليومية
                $journalEntry = JournalEntry::create([
                    'reference_number' => 'INV_ADJ_'.$adjustment->id.'_'.$item->id.'_'.time(),
                    'date' => now(),
                    'description' => 'تسوية جرد - '.$item->product->name.' - ورقة الجرد رقم: '.$adjustment->reference_number,
                    'status' => 1,
                    'currency' => $this->documentCurrency($adjustment),
                    'created_by_employee' => Auth::id(),
                    'reference_type' => ReferenceType::STOCK_ADJUSTMENT,
                    'linking_type' => LinkingType::SINGLE,
                    'reference_id' => $adjustment->id,
                ]);

                // تحديد نوع الحركة (زيادة أو نقص في المخزون)
                if ($item->quantity_difference > 0) {
                    // زيادة في المخزون - مدين لحساب المخزون ودائن لحساب أطراف دائنة أخرى
                    // تفصيل القيد - حساب المخزون (مدين)
                    JournalEntryDetail::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $inventoryAccount->id,
                        'description' => 'زيادة في المخزون - '.$item->product->name,
                        'debit' => $adjustmentValue,
                        'credit' => 0,
                    ]);

                    // تفصيل القيد - الطرف المقابل لتسوية الجرد (دائن)
                    JournalEntryDetail::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $adjustmentAccount->id,
                        'description' => 'زيادة في المخزون - '.$item->product->name,
                        'debit' => 0,
                        'credit' => $adjustmentValue,
                    ]);

                    // الرصيد يُعاد احتسابه من القيود لا بجمع يدوي: الجمع اليدوي
                    // كان يزيد الطرف المقابل في الحالتين معاً — مديناً ودائناً —
                    // فينحرف رصيده ولا يتقاصّ أبداً
                    $inventoryAccount->updateBalanceFromJournalEntries();
                    $adjustmentAccount->updateBalanceFromJournalEntries();
                    Account::updateBalancesHierarchically($adjustmentAccount->id);
                } else {
                    // نقص في المخزون - دائن لحساب المخزون ومدين لحساب أطراف دائنة أخرى (كتعويض)
                    // تفصيل القيد - حساب المخزون (دائن)
                    JournalEntryDetail::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $inventoryAccount->id,
                        'description' => 'نقص في المخزون - '.$item->product->name,
                        'debit' => 0,
                        'credit' => $adjustmentValue,
                    ]);

                    // تفصيل القيد - الطرف المقابل لتسوية الجرد (مدين)
                    JournalEntryDetail::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $adjustmentAccount->id,
                        'description' => 'نقص في المخزون - '.$item->product->name,
                        'debit' => $adjustmentValue,
                        'credit' => 0,
                    ]);

                    $inventoryAccount->updateBalanceFromJournalEntries();
                    $adjustmentAccount->updateBalanceFromJournalEntries();
                    Account::updateBalancesHierarchically($adjustmentAccount->id);
                }

                // تحديث أرصدة الحسابات بشكل هرمي
                Account::updateBalancesHierarchically($inventoryAccount->id);
            }
        }
    }

    /**
     * تحديث حساب محاسبي
     */
    private function updateAccount($accountId, $amount, $type = 'debit')
    {
        $account = Account::find($accountId);
        if ($account) {
            if ($type === 'debit') {
                $account->increment('balance', $amount);
            } else {
                $account->decrement('balance', $amount);
            }
        }
    }

    /**
     * Export inventory adjustments
     */
    public function export(Request $request)
    {
        $fields = $request->input('fields', []);
        $format = $request->input('format', 'excel');

        // Get the current adjustment
        $adjustment = InventoryAdjustment::with(['storeHouse', 'items', 'creator'])->findOrFail($request->id);
        $this->authorizeCreatorScope($adjustment->created_by);
        $this->authorizeWarehouseScope($adjustment->stock_id);

        // Wrap in collection for export
        $adjustments = collect([$adjustment]);

        $export = new InventoryAdjustmentsExport($adjustments, $fields);

        $filename = 'inventory_adjustment_'.$adjustment->reference_number.'_'.now()->format('Y-m-d_H-i-s');

        switch ($format) {
            case 'csv':
                return Excel::download($export, $filename.'.csv');
            case 'pdf':
                return Excel::download($export, $filename.'.pdf');
            case 'excel':
            default:
                return Excel::download($export, $filename.'.xlsx');
        }
    }

    /**
     * Export warehouse permits
     */
    public function exportWarehousePermits(Request $request, $id)
    {
        $adjustment = InventoryAdjustment::findOrFail($id);
        $this->authorizeCreatorScope($adjustment->created_by);
        $this->authorizeWarehouseScope($adjustment->stock_id);

        // الأذون بالمرجع، والصيغة النصّية القديمة للأذون السابقة على الربط
        $warehousePermits = WarehousePermits::where(function ($q) use ($id) {
            $q->where(function ($q2) use ($id) {
                $q2->where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
                    ->where('reference_id', $id);
            })->orWhere('number', 'LIKE', 'INV-'.$id.'-%');
        })
            ->with(['warehousePermitsProducts.product', 'storeHouse', 'permissionSource'])
            ->get();

        if ($warehousePermits->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد أذون مخزنية لتصديرها',
            ], 404);
        }

        // You can create a separate export class for warehouse permits if needed
        // For now, returning a simple response
        return response()->json([
            'success' => true,
            'message' => 'تم العثور على '.$warehousePermits->count().' إذن مخزني',
            'data' => $warehousePermits,
        ]);
    }

    /**
     * Export all warehouse permits for an inventory adjustment
     */
    public function exportAllWarehousePermits(Request $request, $id)
    {
        $adjustment = InventoryAdjustment::findOrFail($id);
        $this->authorizeCreatorScope($adjustment->created_by);
        $this->authorizeWarehouseScope($adjustment->stock_id);

        // الأذون بالمرجع، والصيغة النصّية القديمة للأذون السابقة على الربط
        $warehousePermits = WarehousePermits::where(function ($q) use ($id) {
            $q->where(function ($q2) use ($id) {
                $q2->where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
                    ->where('reference_id', $id);
            })->orWhere('number', 'LIKE', 'INV-'.$id.'-%');
        })
            ->with(['warehousePermitsProducts.product', 'storeHouse', 'permissionSource'])
            ->get();

        if ($warehousePermits->isEmpty()) {
            return redirect()->back()->with('error', 'لا توجد أذون مخزنية لتصديرها');
        }

        // Create export instance
        $export = new WarehousePermitsExport($warehousePermits);

        $filename = 'all_warehouse_permits_inventory_'.$id.'_'.now()->format('Y-m-d_H-i-s');

        // Export as Excel file
        return Excel::download($export, $filename.'.xlsx');
    }

    /**
     * Update inventory adjustment status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,in_progress,completed,adjusted,cancelled',
        ]);

        $adjustment = InventoryAdjustment::findOrFail($id);
        $this->authorizeCreatorScope($adjustment->created_by);
        $this->authorizeWarehouseScope($adjustment->stock_id);

        // Check if status change is allowed
        if (! $this->isStatusChangeAllowed($adjustment->status, $validated['status'])) {
            return response()->json([
                'success' => false,
                'message' => 'غير مسموح بتغيير الحالة من '.$this->getStatusText($adjustment->status).' إلى '.$this->getStatusText($validated['status']),
            ], 422);
        }

        /*
            ورقةٌ أُصدرت أذونُ تسويتها لا تُعاد للتنفيذ وأذونُها قائمة:
            تعديلُ الكمّيات بعد الإصدار يجعل الأذون لا تطابق الورقة التي
            وُلدت منها. تُسحب الأذون المعلَّقة أولاً (زر إلغاء التسوية).
        */
        if ($adjustment->status === 'completed' && $validated['status'] === 'in_progress') {
            $hasPermits = WarehousePermits::where(function ($q) use ($adjustment) {
                $q->where(function ($q2) use ($adjustment) {
                    $q2->where('reference_type', ReferenceType::STOCK_ADJUSTMENT)
                        ->where('reference_id', $adjustment->id);
                })->orWhere('number', 'LIKE', 'INV-'.$adjustment->id.'-%');
            })->whereNotIn('status', ['rejected', 'rejec'])->exists();

            if ($hasPermits) {
                return response()->json([
                    'success' => false,
                    'message' => 'أُصدرت أذون تسويةٍ لهذه الورقة — اسحب الأذون المعلَّقة أولاً قبل إعادتها إلى قيد التنفيذ.',
                ], 422);
            }
        }

        $adjustment->status = $validated['status'];
        $adjustment->save();

        // تسجيل النشاط
        $this->logActivity(
            'تغيير حالة الجرد من '.$this->getStatusText($adjustment->status).' إلى '.$this->getStatusText($validated['status']),
            'inventory',
            $adjustment->id,
            'update'
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الحالة بنجاح',
            'status_text' => $this->getStatusText($validated['status']),
        ]);
    }

    /**
     * Check if status change is allowed
     */
    private function isStatusChangeAllowed($currentStatus, $newStatus)
    {
        $allowedTransitions = [
            'draft' => ['in_progress', 'cancelled'],
            'in_progress' => ['draft', 'completed', 'cancelled'],
            // «مُسوّاة» لا تُطلب يدوياً: يكتبها اعتمادُ آخر إذنٍ وحده
            // (closeAdjustmentIfSettled) — وإلا صارت ورقةٌ بلا أذونٍ أصلاً
            // «مُسوّاة» بنقرة قائمة
            'completed' => ['in_progress'],
            // «مُسوّاة» حالةٌ نهائية: ارتبطت بأذونٍ معتمدة وقيودٍ مرحَّلة،
            // وفتحُها من هنا كان التفافاً على قفل الإلغاء. التراجع الوحيد
            // «إلغاء الموافقة» على الإذن نفسه — وهو ما يعيدها «مكتملة»
            'adjusted' => [],
            'cancelled' => [],
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    /**
     * Get status text
     */
    private function getStatusText($status)
    {
        $texts = [
            'draft' => 'مسودة',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
            'adjusted' => 'تمت التسوية',
            'cancelled' => 'ملغي',
        ];

        return $texts[$status] ?? $status;
    }

    /**
     * إنشاء كود حساب فريد
     */
    private function generateAccountCode()
    {
        $prefix = 'ACC';
        $timestamp = time();
        $random = rand(1000, 9999);

        return $prefix.'_'.$timestamp.'_'.$random;
    }

    /**
     * تسجيل النشاط في سجل الأنشطة
     */
    /**
     * تسجيل نشاط الجرد — يمرّ الآن بنقطة التسجيل الموحّدة.
     *
     * كان يكتب في جدول `logs` وحده بسطر يدوي: بلا عنوان IP ولا متصفّح ولا
     * مسار ولا سطر في «سجلّ الأحداث». صار كل نداء من نداءاته السبعة عشر
     * يكتب في السجلّين معاً بالبيانات كاملة.
     *
     * المعامل الرابع كان يُسمّى `$icon` ويُمرَّر إليه فعلٌ (`create`،
     * `list`، `error`)؛ وهو في الحقيقة اسم العملية، ومنه تشتقّ
     * `ActivityLogService` الأيقونة. أُعيدت تسميته بلا تغيير في نداء واحد.
     *
     * لا try/catch هنا: `OperationLog` لا يرمي شيئاً أصلاً — فشل التسجيل
     * لا يُسقط العملية التي نجحت.
     */
    private function logActivity($description, $type = 'inventory', $typeId = null, $action = 'info')
    {
        OperationLog::record($action, $type, $typeId, $description);
    }
}
