<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Constants\SystemAccountIds;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\JournalEntryDetail;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\OnlineStore\Http\Controllers\Concerns\ScopesStoreWarehouses;
use Modules\OnlineStore\Support\StoreAssignmentScope;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class StoreStorehouseController extends Controller
{
    // حسابات القيود من «الحسابات المحاسبية» لا من ثوابت مكتوبة — نفس
    // السمة التي توحّد الحلّ في Stock\StorehouseController
    use ResolvesAccountingAccounts;

    // أيُّ مخازن المتجر يراها المستخدم: نطاقُه ∩ فرعُ الشريط — مصدرٌ واحد
    // للقائمة والفلاتر والتقارير وحراسة الروابط المباشرة
    use ScopesStoreWarehouses;

    // نطاق الفرع/التسلسل الإداري نفسه المستخدم في Stock\StorehouseController
    use ScopesToEmployeeHierarchy;

    /*
     * نفس صلاحيات `stock.warehouses.*` التي تحرس Stock\StorehouseController —
     * هذا الكنترولر يدير نفس موديل/جدول `StoreHouse`، فليس قسماً مستقلاً.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.warehouses.view', ['only' => [
            'index', 'getStats', 'summaryInventoryOperations', 'inventoryValue', 'inventorySheet',
            'searchSummaryInventoryOperations', 'searchInventoryValue', 'exportSummaryPdf', 'exportInventoryValuePdf',
        ]]);
        $this->middleware('permission.role:stock.warehouses.show', ['only' => ['show']]);
        $this->middleware('permission.role:stock.warehouses.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:stock.warehouses.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.warehouses.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:stock.warehouses.toggle_status', ['only' => ['updateStatus']]);
    }

    /** نصٌّ من الطلب — أو فارغ إن وصل مصفوفةً أو ما لا يُقرأ نصّاً */
    private function textInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * عرض قائمة المخازن
     *
     * مخازنُ المتجر وحدَها، داخل نطاق المستخدم وفرع الشريط
     * ({@see ScopesStoreWarehouses::storeWarehouseIds()}). كانت تُقاس على
     * `default_warehouses` (عهدةُ الموظف) فلا يظهر مخزنُ متجرٍ لغير حامل
     * `data_scope_all`، وكانت حقولُ الفلترة في الشاشة (`search`/`type`) لا
     * يقرؤها أحد (المتحكّم يقرأ `name`/`major`) فالبحثُ والنوعُ بلا أثر.
     */
    public function index(Request $request)
    {
        $query = $this->storeWarehousesQuery()->with(['account', 'branch']);

        // البحث بالاسم أو العنوان — `name` اسمُ الحقل القديم يبقى مقروءاً
        $term = $this->textInput($request, 'search') ?: $this->textInput($request, 'name');
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('shipping_address', 'LIKE', '%'.$term.'%');
            });
        }

        $status = $this->textInput($request, 'status');
        if (in_array($status, ['active', 'inactive', 'stopped'], true)) {
            $query->where('status', $status);
        }

        // النوع (رئيسي/فرعي)
        $type = $this->textInput($request, 'type');
        if ($type === '') {
            $type = $this->textInput($request, 'major');
        }
        if (in_array($type, ['0', '1'], true)) {
            $query->where('major', (int) $type);
        }

        // الفرع المختار في الفلتر وما تحته — مقصوراً على ما يحقّ للمستخدم
        $branchIds = $this->requestedStoreBranchIds($request->input('branch_id'));
        if ($branchIds !== null) {
            $query->whereIn('id', $this->storeWarehouseIdsInBranches($branchIds) ?: [0]);
        }

        // بطاقات الإحصاء على نتيجة الفلترة كلِّها — لا على صفحة الترقيم الحالية
        $accountIds = (clone $query)->pluck('account_id')->filter()->all();
        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'major' => (clone $query)->where('major', 1)->count(),
            'balance' => $accountIds ? (float) Account::whereIn('id', $accountIds)->sum('balance') : 0.0,
        ];

        $storehouses = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
        $branches = $this->storeBranchOptions();

        // المسند إليهم لكل مخزن في الصفحة — استعلامٌ واحد لا استعلامٌ لكل صف
        $assignees = StoreAssignmentScope::warehouseAssignees($storehouses->pluck('id'));

        return view('onlinestore::storehouses.index', compact('storehouses', 'branches', 'stats', 'assignees'));
    }

    /**
     * عرض صفحة إنشاء مخزن جديد
     */
    public function create()
    {
        // الفروع التي يحقّ للمستخدم إسنادُ المخزن إليها، والافتراضيُّ فرعُ الشريط
        $branches = $this->storeBranchOptions();
        $defaultBranchId = $this->resolveStoreWarehouseBranchId(null);

        // المسند إليهم: من يحقّ له إسنادُهم، والموظفُ المقيَّد مُحدَّدٌ مسبقاً (مخزنُه يبقى ظاهراً له)
        $selectedAssignees = $this->storeWarehouseSelectedAssignees();
        $employees = $this->storeWarehouseAssignableEmployees($selectedAssignees);

        return view('onlinestore::storehouses.create', compact('branches', 'defaultBranchId', 'employees', 'selectedAssignees'));
    }

    /** قواعد التحقّق المشتركة بين الإنشاء والتعديل */
    private function validateStorehouse(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255|unique:store_houses,name'.($ignoreId ? ','.$ignoreId : ''),
            'shipping_address' => 'nullable|string|max:500',
            'status' => 'required|in:active,inactive,stopped',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'assigned_employee_ids' => 'nullable|array',
            'assigned_employee_ids.*' => 'integer',
        ], [
            'name.required' => 'اسم المخزن مطلوب',
            'assigned_employee_ids.array' => 'المسند إليهم غير صالحين',
            'assigned_employee_ids.*.integer' => 'أحد الموظفين المسندين غير صالح',
            'name.unique' => 'اسم المخزن موجود مسبقاً',
            'status.required' => 'حالة المخزن مطلوبة',
            'status.in' => 'حالة المخزن غير صالحة',
            'branch_id.exists' => 'الفرع المحدَّد غير موجود',
        ], [
            'name' => 'اسم المخزن',
            'shipping_address' => 'عنوان الشحن',
            'status' => 'الحالة',
            'branch_id' => 'الفرع',
            'assigned_employee_ids' => 'المسند إليهم',
        ]);
    }

    /**
     * حفظ مخزن جديد
     */
    public function store(Request $request)
    {
        $data = $this->validateStorehouse($request);

        /*
            فرعُ المخزن: ما اختاره المستخدم (إن كان ممّا يحقّ له)، وإلّا فرعُ
            الشريط. كان يُكتب «الفرع الرئيسي» دائماً مهما كان الفرعُ المختار،
            فمخزنٌ أُنشئ لفرع المدينة يُحفظ على الرئيسي ولا يظهر إلّا لمن يقف
            على الرئيسي — وهو أصلُ بلاغ «الفلترة تعرض الرئيسي فقط».
        */
        $branchId = $this->resolveStoreWarehouseBranchId($data['branch_id'] ?? null);

        /*
            المسند إليهم (قاعدة المالك: يرى الموظفُ المخازنَ المسندة له ولتابعيه).
            يُحسم قبل المعاملة: معرّفٌ خارج ما يحقّ له إسنادُه خطأ تحقّق لا قبولٌ
            صامت، والموظفُ المقيَّد الذي لم يُسنِد لأحدٍ من فريقه يُسنَد إليه هو —
            وإلا اختفى عنه المخزنُ الذي أنشأه للتوّ.
        */
        $assignees = StoreAssignmentScope::resolveWarehouseAssignees($request->input(StoreAssignmentScope::WAREHOUSE_FIELD));

        DB::beginTransaction();

        try {
            // إنشاء المخزن — يُنشأ من شاشة المتجر، فهو تابعٌ له دائماً
            $storehouse = StoreHouse::create([
                'name' => $data['name'],
                'shipping_address' => $data['shipping_address'] ?? null,
                'is_store_storehouse' => true,
                'status' => $data['status'],
                'branch_id' => $branchId,
                'view_permissions' => $request->view_permissions ?? 0,
                'crate_invoices_permissions' => $request->crate_invoices_permissions ?? 0,
                'edit_stock_permissions' => $request->edit_stock_permissions ?? 0,
                'value_of_view_permissions' => $this->getPermissionValue($request, 'view'),
                'value_of_crate_invoices_permissions' => $this->getPermissionValue($request, 'crate'),
                'value_of_edit_stock_permissions' => $this->getPermissionValue($request, 'edit'),
            ]);

            // إنشاء الحساب المحاسبي للمخزن
            $this->createStorehouseAccount($storehouse);

            StoreAssignmentScope::syncWarehouseAssignees($storehouse, $assignees);

            DB::commit();

            return redirect()->route('store_storehouses.index')
                ->with('success', 'تم إنشاء المخزن بنجاح');

        } catch (\Exception $e) {
            // ⚠️ rollBack() قبل failure() لا بعده: التسجيل يكتب عبر نفس اتصال
            // قاعدة البيانات، فإن كُتب داخل معاملةٍ لم تُنهَ بعد يُلغى سجلّه
            // هو نفسه حين تُلغى المعاملة بأثرٍ رجعي (تحقّقتُ من هذا مباشرةً)
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StoreStorehouse', 'store');
            Log::error('Store Storehouse Error: '.$e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * عرض تفاصيل المخزن
     */
    public function show($id)
    {
        // خارج النطاق أو ليس مخزنَ متجر ⇒ 404
        $storehouse = $this->findStoreWarehouseOrFail($id, ['account', 'branch', 'productDetails.product']);

        // جلب سجل النشاطات
        $logs = \App\Models\Log::where('type', 'storehouse_log')
            ->where('type_id', $storehouse->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        $accounting = $this->buildAccountingOverview($storehouse);

        $assignees = StoreAssignmentScope::warehouseAssignees([$storehouse->id])[$storehouse->id] ?? [];

        return view('onlinestore::storehouses.show', array_merge(
            compact('storehouse', 'logs', 'assignees'),
            $accounting
        ));
    }

    /**
     * الكميات والحسابات المحاسبية لكل صنف في هذا المخزن — نفس منطق
     * Stock\StorehouseController::buildAccountingOverview() حرفياً، منقولةً
     * هنا لأن مخازن المتجر الإلكتروني موديل StoreHouse نفسه ولا فرق محاسبي
     * بينها وبين مخازن Stock.
     *
     * لكل منتج مرّ بحركةٍ في هذا المخزن: الكمية الحالية، الوارد والمنصرف
     * بتفاصيله، والمباع من الفواتير. وقيمة المخزون تُحسب من متوسط تكلفة
     * الصنف *في هذا المخزن تحديداً* — لا من سعر الشراء الحالي — كي تُقارَن
     * بمعنى برصيد حساب المخزن في شجرة الحسابات لا برقم لحظي متغيّر.
     */
    private function buildAccountingOverview(StoreHouse $storehouse): array
    {
        $id = $storehouse->id;

        $account = Account::where('storehouse_id', $id)->first();

        // الكمية الحالية الفعلية لكل صنف في هذا المخزن (المصدر الوحيد الموثوق للرصيد الحالي)
        $currentQuantities = ProductDetails::where('store_house_id', $id)
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id');

        // الأذون المعتمَدة التي لمست هذا المخزن (إضافة/صرف/تحويل وارد أو صادر/رصيد افتتاحي)
        $warehousePermits = WarehousePermits::where('status', 'apporod')
            ->where(function ($query) use ($id) {
                $query->where('store_houses_id', $id)
                    ->orWhere('from_store_houses_id', $id)
                    ->orWhere('to_store_houses_id', $id);
            })
            ->with('warehousePermitsProducts')
            ->get();

        // الفواتير التي صرفت من هذا المخزن (مباع ومرتجع)
        $invoices = Invoice::with('items')
            ->whereHas('items', function ($query) use ($id) {
                $query->where('store_house_id', $id);
            })
            ->when(auth()->user()->employee_id, function ($query) {
                $query->where('created_by', auth()->id());
            })
            ->get();

        $soldByProduct = [];
        $salesReturnsByProduct = [];

        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                if ($item->store_house_id != $id) {
                    continue;
                }

                if ($invoice->type === 'normal' || $invoice->type === 'pos') {
                    $soldByProduct[$item->product_id] = ($soldByProduct[$item->product_id] ?? 0) + $item->quantity;
                }

                if ($invoice->type === 'returned') {
                    $salesReturnsByProduct[$item->product_id] = ($salesReturnsByProduct[$item->product_id] ?? 0) + $item->quantity;
                }
            }
        }

        $rows = [];

        foreach ($warehousePermits as $permit) {
            foreach ($permit->warehousePermitsProducts as $line) {
                $productId = $line->product_id;

                if (! isset($rows[$productId])) {
                    $rows[$productId] = [
                        'incoming_manual' => 0,
                        'incoming_opening' => 0,
                        'incoming_transfer' => 0,
                        'outgoing_manual' => 0,
                        'outgoing_transfer' => 0,
                    ];
                }

                $quantity = $line->quantity;

                switch ($permit->permission_type) {
                    case 1: // إضافة يدوية (وارد)
                        $rows[$productId]['incoming_manual'] += $quantity;
                        break;
                    case 2: // صرف يدوي (منصرف)
                        $rows[$productId]['outgoing_manual'] += $quantity;
                        break;
                    case 3: // تحويل بين المستودعات
                        if ($permit->to_store_houses_id == $id) {
                            $rows[$productId]['incoming_transfer'] += $quantity;
                        }
                        if ($permit->from_store_houses_id == $id) {
                            $rows[$productId]['outgoing_transfer'] += $quantity;
                        }
                        break;
                    case 4: // رصيد افتتاحي (وارد)
                        $rows[$productId]['incoming_opening'] += $quantity;
                        break;
                }
            }
        }

        // أصناف لها كمية حالية بلا إذنٍ مسجَّل (مثلاً حركة مصدرها فاتورة مباشرة) — تبقى ظاهرة في الجدول
        foreach ($currentQuantities as $productId => $quantity) {
            if (! isset($rows[$productId])) {
                $rows[$productId] = [
                    'incoming_manual' => 0,
                    'incoming_opening' => 0,
                    'incoming_transfer' => 0,
                    'outgoing_manual' => 0,
                    'outgoing_transfer' => 0,
                ];
            }
        }

        $products = Product::whereIn('id', array_keys($rows))->get()->keyBy('id');

        $items = [];
        $totalStockValue = 0;

        foreach ($rows as $productId => $movement) {
            $product = $products->get($productId);

            if (! $product) {
                continue;
            }

            $currentQuantity = (float) ($currentQuantities[$productId] ?? 0);
            $sold = (float) ($soldByProduct[$productId] ?? 0);
            $salesReturns = (float) ($salesReturnsByProduct[$productId] ?? 0);

            $incomingTotal = $movement['incoming_manual'] + $movement['incoming_opening'] + $movement['incoming_transfer'];
            $outgoingTotal = $movement['outgoing_manual'] + $movement['outgoing_transfer'];
            $withdrawnTotal = $outgoingTotal + $sold; // كل ما غادر المخزن فعلياً: صرف/تحويل صادر + مبيعات

            $unitCost = $product->averageCostForStorehouse((int) $id)
                ?? $product->averageCost()
                ?? $product->purchase_price
                ?? 0;

            $stockValue = $currentQuantity * $unitCost;
            $totalStockValue += $stockValue;

            $items[] = [
                'id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode ?? null,
                'current_quantity' => $currentQuantity,
                'incoming_manual' => $movement['incoming_manual'],
                'incoming_opening' => $movement['incoming_opening'],
                'incoming_transfer' => $movement['incoming_transfer'],
                'incoming_total' => $incomingTotal,
                'outgoing_manual' => $movement['outgoing_manual'],
                'outgoing_transfer' => $movement['outgoing_transfer'],
                'sold' => $sold,
                'sales_returns' => $salesReturns,
                'withdrawn_total' => $withdrawnTotal,
                'unit_cost' => $unitCost,
                'stock_value' => $stockValue,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        $accountBalance = $account ? (float) $account->balance : null;
        $valueDifference = $accountBalance !== null ? round($totalStockValue - $accountBalance, 2) : null;
        $valuesMatch = $valueDifference !== null && abs($valueDifference) < 0.01;

        // إجمالي كل عمود كمّي — ليس مجرّد إجمالي القيمة، بل الكميات نفسها أيضاً
        $quantityColumns = [
            'current_quantity', 'incoming_manual', 'incoming_opening', 'incoming_transfer', 'incoming_total',
            'outgoing_manual', 'outgoing_transfer', 'sold', 'sales_returns', 'withdrawn_total',
        ];
        $totals = array_fill_keys($quantityColumns, 0.0);
        foreach ($items as $item) {
            foreach ($quantityColumns as $column) {
                $totals[$column] += $item[$column];
            }
        }

        return [
            'accountingAccount' => $account,
            'accountingItems' => $items,
            'accountingTotals' => $totals,
            'accountingTotalStockValue' => $totalStockValue,
            'accountingBalance' => $accountBalance,
            'accountingDifference' => $valueDifference,
            'accountingValuesMatch' => $valuesMatch,
        ];
    }

    /**
     * عرض صفحة تعديل المخزن
     */
    public function edit($id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id, ['account', 'branch']);
        $branches = $this->storeBranchOptions();

        // فرعُ المخزن الحالي يبقى في المنسدلة ولو خرج عن خيارات المستخدم — كي لا يُبدَّل بصمتٍ عند الحفظ
        if ($storehouse->branch && ! $branches->contains('id', $storehouse->branch_id)) {
            $branches = $branches->push($storehouse->branch);
        }

        // المسندون الحاليون يبقون في الحقل ولو خرجوا عمّن يحقّ له إسنادُهم — لا يُسقَطون بصمت
        $selectedAssignees = $this->storeWarehouseSelectedAssignees($storehouse);
        $employees = $this->storeWarehouseAssignableEmployees(StoreAssignmentScope::warehouseAssigneeIds((int) $storehouse->id));

        return view('onlinestore::storehouses.edit', compact('storehouse', 'branches', 'employees', 'selectedAssignees'));
    }

    /**
     * تحديث بيانات المخزن
     */
    public function update(Request $request, $id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);
        $data = $this->validateStorehouse($request, (int) $storehouse->id);

        // الفرع: ما اختاره المستخدم إن جاز له، وإلّا ما كان عليه المخزن
        // (والمخزنُ القديم بلا فرع يُسنَد عند أوّل حفظ)
        $branchId = $this->resolveStoreWarehouseBranchId(
            $data['branch_id'] ?? null,
            $storehouse->branch_id ? (int) $storehouse->branch_id : null
        );

        // المسند إليهم: ما لا يحقّ له نزعُه يبقى كما كان — انظر resolveWarehouseAssignees
        $assignees = StoreAssignmentScope::resolveWarehouseAssignees(
            $request->input(StoreAssignmentScope::WAREHOUSE_FIELD),
            StoreAssignmentScope::warehouseAssigneeIds((int) $storehouse->id)
        );

        DB::beginTransaction();

        try {
            $oldName = $storehouse->name;

            $storehouse->update([
                'name' => $data['name'],
                'shipping_address' => $data['shipping_address'] ?? null,
                'status' => $data['status'],
                'branch_id' => $branchId,
                'view_permissions' => $request->view_permissions ?? $storehouse->view_permissions,
                'crate_invoices_permissions' => $request->crate_invoices_permissions ?? $storehouse->crate_invoices_permissions,
                'edit_stock_permissions' => $request->edit_stock_permissions ?? $storehouse->edit_stock_permissions,
                'value_of_view_permissions' => $this->getPermissionValue($request, 'view') ?? $storehouse->value_of_view_permissions,
                'value_of_crate_invoices_permissions' => $this->getPermissionValue($request, 'crate') ?? $storehouse->value_of_crate_invoices_permissions,
                'value_of_edit_stock_permissions' => $this->getPermissionValue($request, 'edit') ?? $storehouse->value_of_edit_stock_permissions,
            ]);

            // تحديث اسم الحساب المحاسبي إذا تغير اسم المخزن
            if ($oldName !== $data['name']) {
                $this->updateStorehouseAccount($storehouse);
            }

            // إن لم يُرسَل الحقل أصلاً (نموذجٌ قديم) لا يتغيّر الإسناد
            if ($request->has(StoreAssignmentScope::WAREHOUSE_FIELD) || $request->has('assignees_present')) {
                StoreAssignmentScope::syncWarehouseAssignees($storehouse, $assignees);
            }

            DB::commit();

            return redirect()->route('store_storehouses.index')
                ->with('success', 'تم تحديث المخزن بنجاح');

        } catch (\Exception $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في store() أعلاه
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StoreStorehouse', 'update', $id);
            Log::error('Update Storehouse Error: '.$e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * حذف المخزن
     */
    public function destroy($id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);

        try {
            // التحقق من أن المخزن ليس رئيسياً
            if ($storehouse->major == 1) {
                return redirect()->back()
                    ->with('error', 'لا يمكن حذف المخزن الرئيسي');
            }

            // التحقق من عدم وجود منتجات في المخزن
            $hasProducts = ProductDetails::where('store_house_id', $storehouse->id)
                ->where('quantity', '>', 0)
                ->exists();

            if ($hasProducts) {
                return redirect()->back()
                    ->with('error', 'لا يمكن حذف المخزن لأنه يحتوي على منتجات');
            }

            /*
                مخزنٌ مرّت به أذونٌ أو قيود لا يُحذف: حذفُه يترك أذوناً بلا
                مخزن وحساباً في الشجرة بحركاتٍ لا يفسّرها شيء. يُوقَف بدل ذلك.
            */
            $hasPermits = WarehousePermits::where('store_houses_id', $storehouse->id)
                ->orWhere('from_store_houses_id', $storehouse->id)
                ->orWhere('to_store_houses_id', $storehouse->id)
                ->exists();

            $accountIds = Account::where('storehouse_id', $storehouse->id)->pluck('id')
                ->push($storehouse->account_id)->filter()->unique()->all();

            $hasEntries = $accountIds && JournalEntryDetail::whereIn('account_id', $accountIds)->exists();

            if ($hasPermits || $hasEntries) {
                return redirect()->back()
                    ->with('error', 'لا يمكن حذف المخزن لوجود أذون مخزنية أو قيود محاسبية مرتبطة به — أوقِفه بدلاً من حذفه.');
            }

            $storehouseName = $storehouse->name;
            $storehouse->delete();

            return redirect()->route('store_storehouses.index')
                ->with('success', "تم حذف المخزن \"{$storehouseName}\" بنجاح");

        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StoreStorehouse', 'destroy', $id);
            Log::error('Delete Storehouse Error: '.$e->getMessage());

            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء حذف المخزن');
        }
    }

    /**
     * تغيير حالة المخزن
     */
    public function updateStatus(Request $request, $id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);

        // خارج try: خطأُ التحقّق يعود 422 برسالته، لا «خطأ غير متوقع» 500
        $request->validate([
            'status' => 'required|in:active,inactive,stopped',
        ], [
            'status.required' => 'حالة المخزن مطلوبة',
            'status.in' => 'حالة المخزن غير صالحة',
        ], ['status' => 'الحالة']);

        try {
            $storehouse->status = $request->status;
            $storehouse->save();

            $statusLabels = [
                'active' => 'نشط',
                'inactive' => 'غير نشط',
                'stopped' => 'متوقف',
            ];

            $message = "تم تغيير حالة المخزن \"{$storehouse->name}\" إلى {$statusLabels[$request->status]}";

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'storehouse_id' => $storehouse->id,
                    'new_status' => $storehouse->status,
                ]);
            }

            return redirect()->route('store_storehouses.index')->with('success', $message);

        } catch (\Exception $e) {
            OperationLog::failure($e, 'OnlineStore.StoreStorehouse', 'updateStatus', $id);
            Log::error('Update Storehouse Status Error: '.$e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ غير متوقع',
                ], 500);
            }

            return redirect()->back()->with('error', 'حدث خطأ غير متوقع');
        }
    }

    /**
     * أصنافُ المخزن وحركاتُها من الأذون **المعتمَدة** — مصدرٌ واحد للشاشة
     * وللبحث فيها.
     *
     * كانت الحركاتُ تُحسب بمقارنة `permission_type` بنصوصٍ (`incoming`/
     * `outgoing`/`transfer`) والعمودُ رقميّ (1 إضافة، 2 صرف، 3 تحويل،
     * 4 رصيد افتتاحي) — فكلُّ الأعمدة صفرٌ دائماً، وباثني عشر استعلاماً لكل
     * صنف. والبحثُ كان يُرجع أصفاراً مكتوبةً باليد. صار استعلاماً مجمَّعاً
     * واحداً، والمبيعاتُ ومرتجعاتُها تُفرز بمصدر الإذن.
     *
     * @return array<int, array<string, mixed>>
     */
    private function inventoryMovementRows(StoreHouse $storehouse, Request $request): array
    {
        $id = (int) $storehouse->id;

        $detailsQuery = ProductDetails::where('store_house_id', $id);
        $this->applyProductFilters($detailsQuery, $request);

        $quantities = $detailsQuery
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id');

        if ($quantities->isEmpty()) {
            return [];
        }

        $movements = DB::table('warehouse_permits_products as line')
            ->join('warehouse_permits as permit', 'permit.id', '=', 'line.warehouse_permits_id')
            ->where('permit.status', 'apporod')
            ->whereIn('line.product_id', $quantities->keys())
            ->where(function ($q) use ($id) {
                $q->where('permit.store_houses_id', $id)
                    ->orWhere('permit.from_store_houses_id', $id)
                    ->orWhere('permit.to_store_houses_id', $id);
            })
            ->groupBy('line.product_id', 'permit.permission_type', 'permit.permission_source_id', 'permit.reference_type', 'permit.from_store_houses_id', 'permit.to_store_houses_id')
            ->get([
                'line.product_id', 'permit.permission_type', 'permit.permission_source_id', 'permit.reference_type',
                'permit.from_store_houses_id', 'permit.to_store_houses_id',
                DB::raw('SUM(ABS(line.quantity)) as quantity'),
            ]);

        $blank = [
            'incoming_purchase' => 0.0, 'incoming_manual' => 0.0, 'incoming_transfer' => 0.0, 'sales_return_quantity' => 0.0,
            'outgoing_purchase_return' => 0.0, 'outgoing_manual' => 0.0, 'outgoing_transfer' => 0.0, 'sold_quantity' => 0.0,
        ];
        $byProduct = [];

        foreach ($movements as $move) {
            $row = $byProduct[$move->product_id] ?? $blank;
            $quantity = (float) $move->quantity;
            $source = (int) $move->permission_source_id;

            switch ((int) $move->permission_type) {
                case 1: // إضافة
                case 4: // رصيد افتتاحي
                    if ($move->reference_type === 'purchase_invoice') {
                        $row['incoming_purchase'] += $quantity;
                    } elseif (in_array($source, [PermissionSources::SALES_RETURN, PermissionSources::SALES_CREDIT_NOTE], true)) {
                        $row['sales_return_quantity'] += $quantity;
                    } else {
                        $row['incoming_manual'] += $quantity;
                    }
                    break;
                case 2: // صرف
                    if ($move->reference_type === 'purchase_return') {
                        $row['outgoing_purchase_return'] += $quantity;
                    } elseif ($source === PermissionSources::SALES_INVOICE) {
                        $row['sold_quantity'] += $quantity;
                    } else {
                        $row['outgoing_manual'] += $quantity;
                    }
                    break;
                case 3: // تحويل — واردٌ لهذا المخزن أو صادرٌ منه
                    if ((int) $move->to_store_houses_id === $id) {
                        $row['incoming_transfer'] += $quantity;
                    }
                    if ((int) $move->from_store_houses_id === $id) {
                        $row['outgoing_transfer'] += $quantity;
                    }
                    break;
            }

            $byProduct[$move->product_id] = $row;
        }

        $products = Product::with('category')->whereIn('id', $quantities->keys())->orderBy('name')->get();
        $rows = [];

        foreach ($products as $product) {
            $row = $byProduct[$product->id] ?? $blank;
            $incomingTotal = $row['incoming_purchase'] + $row['incoming_manual'] + $row['incoming_transfer'] + $row['sales_return_quantity'];
            $outgoingTotal = $row['outgoing_purchase_return'] + $row['outgoing_manual'] + $row['outgoing_transfer'] + $row['sold_quantity'];

            $rows[] = [
                'id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'category' => $product->category->name ?? 'غير مصنف',
                'current_quantity' => (float) $quantities[$product->id],
            ] + $row + [
                'incoming_total' => $incomingTotal,
                'outgoing_total' => $outgoingTotal,
                'movement_total' => $incomingTotal - $outgoingTotal,
            ];
        }

        return $rows;
    }

    /** فلترا البحث والتصنيف على أصناف المخزن — للتقارير الثلاثة */
    private function applyProductFilters($detailsQuery, Request $request): void
    {
        $term = $this->textInput($request, 'search');
        $categoryId = $request->input('category_id');

        if ($term !== '') {
            $detailsQuery->whereHas('product', function ($q) use ($term) {
                $q->where(function ($w) use ($term) {
                    $w->where('name', 'LIKE', '%'.$term.'%')
                        ->orWhere('barcode', 'LIKE', '%'.$term.'%');
                });
            });
        }

        if (is_numeric($categoryId)) {
            $detailsQuery->whereHas('product', function ($q) use ($categoryId) {
                $q->where('category_id', (int) $categoryId);
            });
        }
    }

    /**
     * أصنافُ المخزن ذاتُ الرصيد بقيمتها — للشاشة وللبحث فيها.
     *
     * الرصيدُ مجموعُ صفوف الصنف في المخزن (المنتج الأساسي — المتغيّرات لا
     * تحمل مخزوناً)، لا صفّاً لكل سجلّ.
     *
     * @return array<int, array<string, mixed>>
     */
    private function inventoryValueRows(StoreHouse $storehouse, Request $request): array
    {
        $detailsQuery = ProductDetails::where('store_house_id', $storehouse->id);
        $this->applyProductFilters($detailsQuery, $request);

        $quantities = $detailsQuery
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('product_id')
            ->havingRaw('SUM(quantity) > 0')
            ->pluck('quantity', 'product_id');

        $rows = [];

        foreach (Product::with('category')->whereIn('id', $quantities->keys())->orderBy('name')->get() as $product) {
            $quantity = (float) $quantities[$product->id];
            $purchasePrice = (float) ($product->purchase_price ?? 0);
            $salePrice = (float) ($product->sale_price ?? 0);

            $rows[] = [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'category' => $product->category->name ?? 'غير مصنف',
                'quantity' => $quantity,
                'purchase_price' => $purchasePrice,
                'sale_price' => $salePrice,
                'purchase_total' => $purchasePrice * $quantity,
                'sale_total' => $salePrice * $quantity,
                'expected_profit' => ($salePrice - $purchasePrice) * $quantity,
            ];
        }

        return $rows;
    }

    /**
     * ملخص عمليات المخزون
     */
    public function summaryInventoryOperations(Request $request, $id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);

        $products = $this->inventoryMovementRows($storehouse, $request);
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('onlinestore::storehouses.summary_inventory_operations', compact('storehouse', 'products', 'categories'));
    }

    /**
     * قيمة المخزون التقديرية
     */
    public function inventoryValue(Request $request, $id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);

        $products = $this->inventoryValueRows($storehouse, $request);
        $categories = Category::orderBy('name')->get(['id', 'name']);

        // حساب الإجماليات
        $totalPurchaseValue = array_sum(array_column($products, 'purchase_total'));
        $totalSaleValue = array_sum(array_column($products, 'sale_total'));
        $totalExpectedProfit = $totalSaleValue - $totalPurchaseValue;

        return view('onlinestore::storehouses.inventory_value', compact(
            'storehouse',
            'products',
            'categories',
            'totalPurchaseValue',
            'totalSaleValue',
            'totalExpectedProfit'
        ));
    }

    /**
     * ورقة الجرد
     */
    public function inventorySheet($id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);

        // صفٌّ لكل صنف برصيده المجموع في هذا المخزن
        $quantities = ProductDetails::where('store_house_id', $storehouse->id)
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id');

        $products = Product::with('category')->whereIn('id', $quantities->keys())->orderBy('name')->get()
            ->map(fn ($product) => (object) [
                'product' => $product,
                'quantity' => (float) $quantities[$product->id],
            ]);

        return view('onlinestore::storehouses.inventory_sheet', compact('storehouse', 'products'));
    }

    /**
     * البحث في ملخص عمليات المخزون (AJAX)
     */
    public function searchSummaryInventoryOperations(Request $request, $id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);

        return response()->json([
            'success' => true,
            'products' => $this->inventoryMovementRows($storehouse, $request),
        ]);
    }

    /**
     * البحث في قيمة المخزون (AJAX)
     */
    public function searchInventoryValue(Request $request, $id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);

        return response()->json([
            'success' => true,
            'products' => $this->inventoryValueRows($storehouse, $request),
        ]);
    }

    /**
     * طباعة ملخص عمليات المخزون.
     *
     * كان يصيّر قالبَ PDF لا وجودَ له (`storehouses.pdf.*`) فينتهي الرابطُ
     * بخطأ 500. والطباعةُ والتصدير في شاشات المتجر من المتصفّح
     * (`printTable` + `exportToExcelProfessional`) — فيُفتح التقريرُ نفسُه
     * ويُطلق طباعتَه.
     */
    public function exportSummaryPdf($id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);

        return redirect()->route('store_storehouses.summary-inventory-operations', ['storehouse' => $storehouse->id, 'print' => 1]);
    }

    /**
     * طباعة قيمة المخزون — انظر {@see exportSummaryPdf()}.
     */
    public function exportInventoryValuePdf($id)
    {
        $storehouse = $this->findStoreWarehouseOrFail($id);

        return redirect()->route('store_storehouses.inventory-value', ['storehouse' => $storehouse->id, 'print' => 1]);
    }

    /**
     * الحصول على إحصائيات المخازن — مخازن المتجر المرئيّة للمستخدم وحدها
     */
    public function getStats()
    {
        try {
            $query = $this->storeWarehousesQuery();

            return response()->json([
                'success' => true,
                'stats' => [
                    'active' => (clone $query)->where('status', 'active')->count(),
                    'inactive' => (clone $query)->where('status', 'inactive')->count(),
                    'stopped' => (clone $query)->where('status', 'stopped')->count(),
                    'total' => (clone $query)->count(),
                    'major' => (clone $query)->where('major', 1)->count(),
                ],
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreStorehouse', 'getStats');

            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الإحصائيات',
            ], 500);
        }
    }

    // ==================== Private Methods ====================

    /**
     * إنشاء حساب محاسبي للمخزن
     */
    private function createStorehouseAccount($storehouse)
    {
        // الحساب الأب للمخازن — كما ضُبط في «الحسابات المحاسبية»، وإلا الثابت الاحتياطي
        $inventoryAccountId = $this->accountingAccountId('inventory', SystemAccountIds::INVENTORY);
        $parentAccount = Account::find($inventoryAccountId);

        if (! $parentAccount) {
            Log::warning('لم يتم العثور على حساب المخزون الرئيسي');

            return;
        }

        // توليد كود الحساب
        $newCode = $this->generateAccountCode($parentAccount);

        $account = Account::create([
            'name' => 'مخزن: '.$storehouse->name,
            'code' => $newCode,
            'parent_id' => $parentAccount->id,
            'account_type' => $parentAccount->account_type ?? 'assets',
            'balance_type' => 'debit',
            'is_active' => true,
            'balance' => 0,
            'storehouse_id' => $storehouse->id,
        ]);

        // ربط الحساب بالمخزن
        $storehouse->update(['account_id' => $account->id]);
    }

    /**
     * تحديث حساب المخزن
     */
    private function updateStorehouseAccount($storehouse)
    {
        $account = Account::where('storehouse_id', $storehouse->id)->first();

        if ($account) {
            $account->update([
                'name' => 'مخزن: '.$storehouse->name,
            ]);
        }
    }

    /**
     * توليد كود الحساب
     */
    private function generateAccountCode($parentAccount)
    {
        $lastChild = Account::where('parent_id', $parentAccount->id)
            ->orderBy('code', 'desc')
            ->first();

        if (! $lastChild) {
            return $parentAccount->code.'01';
        }

        $lastNumber = (int) substr($lastChild->code, strlen($parentAccount->code));
        $newNumber = $lastNumber + 1;
        $newCode = $parentAccount->code.str_pad($newNumber, 2, '0', STR_PAD_LEFT);

        while (Account::where('code', $newCode)->exists()) {
            $newNumber++;
            $newCode = $parentAccount->code.str_pad($newNumber, 2, '0', STR_PAD_LEFT);
        }

        return $newCode;
    }

    /**
     * الحصول على قيمة الصلاحية
     */
    private function getPermissionValue($request, $type)
    {
        switch ($type) {
            case 'view':
                if ($request->view_permissions == 1) {
                    return $request->employee_id;
                }
                if ($request->view_permissions == 2) {
                    return $request->functional_role_id;
                }
                if ($request->view_permissions == 3) {
                    return $request->branch_id;
                }
                break;
            case 'crate':
                if ($request->crate_invoices_permissions == 1) {
                    return $request->input('1employee_id');
                }
                if ($request->crate_invoices_permissions == 2) {
                    return $request->input('1functional_role_id');
                }
                if ($request->crate_invoices_permissions == 3) {
                    return $request->input('1branch_id');
                }
                break;
            case 'edit':
                if ($request->edit_stock_permissions == 1) {
                    return $request->input('2employee_id');
                }
                if ($request->edit_stock_permissions == 2) {
                    return $request->input('2functional_role_id');
                }
                if ($request->edit_stock_permissions == 3) {
                    return $request->input('2branch_id');
                }
                break;
        }

        return null;
    }
}
