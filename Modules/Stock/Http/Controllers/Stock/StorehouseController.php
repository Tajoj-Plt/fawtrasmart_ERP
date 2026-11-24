<?php

namespace Modules\Stock\Http\Controllers\Stock;

use App\Constants\permissions\PermissionSources;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorehouseRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DefaultWarehouses;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\Product;
use App\Models\ProductDetails;
use App\Models\Role;
use App\Models\StoreHouse;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;
use TCPDF;

class StorehouseController extends Controller
{
    use ResolvesAccountingAccounts, RespectsAccountingPeriods, ScopesToEmployeeHierarchy, UsesDocumentCurrency;

    /**
     * حماية على مستوى المتحكّم بجانب الراوت — نفس تعيينات
     * `Modules/Stock/routes/web.php`.
     *
     * نطاق الرؤية هنا مبنيٌّ على الربط الفعلي موظفٍ↔مستودع في جدول
     * `default_warehouses` (لا فرع المستودع ولا نطاقات `data_scope_*`
     * الهرمية): موظفٌ عاديٌّ يرى مستودعاته المعيَّنة له فقط، ومن يدير
     * غيره (عبر `Employee::direct_manager_id`) يرى مستودعاته ومستودعات كل
     * من يديرهم مباشرة أو عبر التسلسل الإداري كاملاً. مستخدمٌ ليس موظفاً
     * (`employee_id` فارغ) يرى وفق صلاحياته فقط بلا قيدٍ هنا.
     *
     * فرع المستودع نفسه أمرٌ منفصل عن هذا النطاق: موظفٌ يُقفَل على فرعه
     * تلقائياً عند الإنشاء/التعديل، ومن ليس موظفاً يختاره بحرية.
     */
    public function __construct()
    {
        $this->middleware('permission.role:stock.warehouses.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:stock.warehouses.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:stock.warehouses.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:stock.warehouses.delete', ['only' => ['delete']]);
        $this->middleware('permission.role:stock.reports.summary_movement', ['only' => ['summary_inventory_operations', 'searchSummaryInventoryOperations']]);
        $this->middleware('permission.role:stock.reports.export', ['only' => ['exportSummaryInventoryOperations', 'exportInventoryValue']]);
        $this->middleware('permission.role:stock.reports.value', ['only' => ['inventory_value', 'searchInventoryValue']]);
        $this->middleware('permission.role:stock.reports.inventory_sheet', ['only' => ['inventory_sheet']]);
        $this->middleware('permission.role:stock.warehouses.opening_balance', ['only' => ['generateBulkOpeningBalance', 'generateAllWarehousesOpeningBalance']]);
        $this->middleware('permission.role:stock.warehouses.toggle_status', ['only' => ['toggleStatus']]);
    }

    /**
     * إنشاء أرصدة افتتاحية بشكل جماعي لجميع المستودعات النشطة
     */
    public function generateAllWarehousesOpeningBalance(Request $request)
    {
        /*
            حارس: توليد أرصدة لكل المستودعات بضغطةٍ واحدة يُرحّل قيوداً بأرقامٍ
            لم يراجعها أحد. لا يُنفَّذ إلا بتأكيدٍ صريح (`confirm=1`)، والمسار
            المعتاد شاشة «رصيد افتتاحي» حيث تُحدَّد الأصناف وكمياتها.
        */
        if (! $request->boolean('confirm')) {
            return redirect()
                ->route('store_permits_management.opening_balance')
                ->with('error', 'حدّد الأصناف وكمياتها وأسعارها من هذه الشاشة — التوليد الجماعي بلا تحديد أُوقف.');
        }

        // الفترة المحاسبية: الأرصدة الافتتاحية تُرحِّل قيوداً بتاريخ اليوم
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $storehouses = StoreHouse::where('status', 'active')->get();

        if ($storehouses->isEmpty()) {
            return redirect()->back()->with('error', 'لا توجد مستودعات نشطة لإنشاء أرصدة افتتاحية لها.');
        }

        DB::beginTransaction();
        try {
            $totalCreated = 0;
            $warehousesCount = 0;

            foreach ($storehouses as $storehouse) {
                $productDetails = ProductDetails::where('store_house_id', $storehouse->id)
                    ->where('quantity', '>', 0)
                    ->get();

                if ($productDetails->isEmpty()) {
                    continue;
                }

                $warehousesCount++;

                // 1. إنشاء رأس الإذن المخزني للمستودع الحالي
                $permit = WarehousePermits::create([
                    'store_houses_id' => $storehouse->id,
                    'permission_type' => 4, // رصيد افتتاحي
                    'permission_date' => now(),
                    'number' => 'OPB-ALL-'.$storehouse->id.'-'.time(),
                    'details' => 'إنشاء تلقائي جماعي للأرصدة الافتتاحية للمستودع: '.$storehouse->name,
                    'permission_source_id' => PermissionSources::WAREHOUSE_OPENING_BALANCE,
                    'created_by' => Auth::id(),
                    'grand_total' => 0,
                ]);

                $grandTotal = 0;

                foreach ($productDetails as $detail) {
                    $product = Product::find($detail->product_id);
                    if (! $product) {
                        continue;
                    }

                    $unitPrice = $product->averageCost() ?? $product->purchase_price ?? 0;
                    $rowTotal = $detail->quantity * $unitPrice;
                    $grandTotal += $rowTotal;

                    // 2. إنشاء تفاصيل الإذن المخزني
                    $permitProduct = WarehousePermitsProducts::create([
                        'warehouse_permits_id' => $permit->id,
                        'product_id' => $detail->product_id,
                        'quantity' => $detail->quantity,
                        'unit_price' => $unitPrice,
                        'total' => $rowTotal,
                        'stock_before' => 0,
                        'stock_after' => $detail->quantity,
                    ]);

                    // 3. إنشاء القيود المحاسبية
                    $this->createBulkOpeningBalanceJournalEntry($permit, $detail, $unitPrice, $storehouse, $permitProduct);
                    $totalCreated++;
                }

                $permit->update(['grand_total' => $grandTotal]);
            }

            if ($warehousesCount == 0) {
                DB::rollBack();

                return redirect()->back()->with('error', 'لم يتم العثور على أي كميات في أي مستودع نشط.');
            }

            DB::commit();

            return redirect()->back()->with('success', "تم إنشاء الأرصدة الافتتاحية بنجاح لـ {$warehousesCount} مستودع، بإجمالي {$totalCreated} حركات مخزنية.");

        } catch (\Exception $e) {
            OperationLog::failure($e, 'storehouse_log', 'generateAllWarehousesOpeningBalance');
            DB::rollBack();
            Log::error('Error generating all warehouses opening balance: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء إنشاء الأرصدة الافتتاحية الجماعية: '.$e->getMessage());
        }
    }

    /**
     * إنشاء أرصدة افتتاحية بشكل جماعي لجميع المنتجات في المستودع
     */
    public function generateBulkOpeningBalance(Request $request, $id)
    {
        // مستودعٌ خارج نطاق المستخدم لا يُقرأ ولا يُعدَّل — حتى بتخمين معرّفه في الرابط
        $this->authorizeWarehouseAccess((int) $id);

        /*
            حارس: هذه العملية تُرحّل قيوداً بأرقامٍ لم يراجعها أحد — بلا اختيار
            صنفٍ ولا كمية ولا سعر. لا تُنفَّذ إلا بتأكيدٍ صريح (`confirm=1`)،
            والمسار المعتاد صار شاشة «رصيد افتتاحي» حيث تُحدَّد الأصناف.
        */
        if (! $request->boolean('confirm')) {
            return redirect()
                ->route('store_permits_management.opening_balance')
                ->with('error', 'حدّد الأصناف وكمياتها وأسعارها من هذه الشاشة — التوليد الجماعي بلا تحديد أُوقف.');
        }

        // الفترة المحاسبية: الأرصدة الافتتاحية تُرحِّل قيوداً بتاريخ اليوم
        if ($block = $this->accountingPeriodBlock(now())) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $storehouse = StoreHouse::findOrFail($id);
        $productDetails = ProductDetails::where('store_house_id', $id)
            ->where('quantity', '>', 0)
            ->with('product')
            ->get();

        if ($productDetails->isEmpty()) {
            return redirect()->back()->with('error', 'لا يوجد منتجات بكميات في هذا المستودع لإنشاء أرصدة افتتاحية لها.');
        }

        DB::beginTransaction();
        try {
            // 1. إنشاء رأس الإذن المخزني
            $permit = WarehousePermits::create([
                'store_houses_id' => $id,
                'permission_type' => 4, // رصيد افتتاحي
                'permission_date' => now(),
                'number' => 'OPB-BULK-'.time(),
                'details' => 'إنشاء تلقائي للأرصدة الافتتاحية بناءً على الكميات الحالية في المستودع: '.$storehouse->name,
                'permission_source_id' => PermissionSources::WAREHOUSE_OPENING_BALANCE,
                'created_by' => Auth::id(),
                'grand_total' => 0, // سيتم تحديثه لاحقاً
                /*
                    هذا الإذن يُرحّل قيوده هنا مباشرةً والكميات موجودة أصلاً
                    في المستودع، فحالته «معتمَد» لا «معلَّق»: إذنٌ معلَّق بقيدٍ
                    مُرحَّل يعني أنّ اعتماده لاحقاً سيُرحّل القيد مرّةً ثانية.
                */
                'status' => 'apporod',
            ]);

            $grandTotal = 0;

            foreach ($productDetails as $detail) {
                $unitPrice = $detail->product->averageCost() ?? $detail->product->purchase_price ?? 0;
                $rowTotal = $detail->quantity * $unitPrice;
                $grandTotal += $rowTotal;

                // 2. إنشاء تفاصيل الإذن المخزني
                $permitProduct = WarehousePermitsProducts::create([
                    'warehouse_permits_id' => $permit->id,
                    'product_id' => $detail->product_id,
                    'quantity' => $detail->quantity,
                    'unit_price' => $unitPrice,
                    'total' => $rowTotal,
                    'stock_before' => 0, // بما أنه رصيد افتتاحي، نعتبره يبدأ من الصفر في السجلات التاريخية
                    'stock_after' => $detail->quantity,
                ]);

                // 3. إنشاء القيود المحاسبية لكل منتج (أو يمكن دمجها، لكن للتبسيط وللمطابقة مع StorePermitsManagementController)
                $this->createBulkOpeningBalanceJournalEntry($permit, $detail, $unitPrice, $storehouse, $permitProduct);
            }

            // تحديث الإجمالي الكلي للإذن
            $permit->update(['grand_total' => $grandTotal]);

            DB::commit();

            return redirect()->back()->with('success', 'تم إنشاء الأرصدة الافتتاحية والقيود المحاسبية بنجاح لـ '.$productDetails->count().' منتج.');

        } catch (\Exception $e) {
            OperationLog::failure($e, 'storehouse_log', 'generateBulkOpeningBalance');
            DB::rollBack();
            Log::error('Error generating bulk opening balance: '.$e->getMessage());

            return redirect()->back()->with('error', 'حدث خطأ أثناء إنشاء الأرصدة الافتتاحية: '.$e->getMessage());
        }
    }

    /**
     * دالة مساعدة لإنشاء القيد المحاسبي للأرصدة الافتتاحية الجماعية
     */
    private function createBulkOpeningBalanceJournalEntry($permit, $detail, $unitPrice, $store, $permitProduct)
    {
        $amount = $permitProduct->total;

        // حساب المستودع من نقطة واحدة: تحت الأب المضبوط في «الحسابات
        // المحاسبية»، بنوعٍ وكودٍ موحَّدين. كان النوع هنا `assetss` — وهي
        // قيمة ليست في enum العمود أصلاً، فيرفضها MySQL الصارم
        $storeAccount = $this->warehouseAccount($store->id, $store->name ?? null);

        // إنشاء قيد اليومية
        $journalEntry = JournalEntry::create([
            'reference_number' => 'OPB_'.$permit->number.'_'.$detail->product_id.'_'.time(),
            'date' => $permit->permission_date,
            'description' => 'رصيد افتتاحي (توليد آلي) للمنتج '.($detail->product->name ?? 'منتج غير معروف').
                             " - كمية {$detail->quantity} في المستودع {$store->name}",
            'status' => 1,
            'currency' => $this->documentCurrency($permit),
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::OPENING_BALANCE,
            'linking_type' => LinkingType::SINGLE,
            'reference_id' => $permit->id,
        ]);

        // تفصيل القيد - حساب المستودع (مدين)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $storeAccount->id,
            'description' => $journalEntry->description,
            'debit' => $amount,
            'credit' => 0,
        ]);

        // تفصيل القيد - حساب رأس المال / أرصدة افتتاحية (دائن)
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->requireAccountingAccountId('opening_balance'),
            'description' => $journalEntry->description,
            'debit' => 0,
            'credit' => $amount,
        ]);

        // تحديث رصيد حساب المستودع من القيود المحاسبية
        // هذا يحسب الرصيد بشكل صحيح من مجموع (المدين - الدائن) لجميع القيود
        $storeAccount->updateBalanceFromJournalEntries();

        // تحديث رصيد حساب الأرصدة الافتتاحية
        $openingBalanceAccount = AccountingSettings::account('opening_balance');
        if ($openingBalanceAccount) {
            $openingBalanceAccount->updateBalanceFromJournalEntries();
        }

        // تحديث أرصدة الحسابات الأب بشكل هرمي
        if ($storeAccount->parent_id) {
            Account::updateBalancesHierarchically($storeAccount->parent_id);
        }
    }

    public function index(Request $request)
    {
        $query = StoreHouse::query()->with('branch');

        // نطاق البيانات: مستودعاتي (أو مستودعات من أديرهم) عبر default_warehouses
        $allowedWarehouseIds = $this->accessibleWarehouseIds();
        if ($allowedWarehouseIds !== null) {
            $query->whereIn('id', $allowedWarehouseIds ?: [0]);
        }

        if ($request->filled('keywords')) {
            $keywords = $request->keywords;
            $query->where(function ($q) use ($keywords) {
                $q->where('name', 'like', "%{$keywords}%")
                    ->orWhere('shipping_address', 'like', "%{$keywords}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $storehouses = $query->orderBy('id', 'DESC')->paginate($perPage)->withQueryString();

        $storehouseMeta = $this->buildStorehouseListMeta($storehouses);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'html' => view('stock::storehouse.partials.storehouse_list', compact('storehouses', 'storehouseMeta'))->render(),
                'total' => $storehouses->total(),
            ]);
        }

        return view('stock::storehouse.index', compact('storehouses', 'storehouseMeta'));
    }

    /**
     * معلومات موجزة لكل مستودع في قائمة المستودعات: عدد الأصناف، الكمية،
     * قيمة المخزون التقديرية، الحساب المحاسبي ورصيده، الموظفون الذين هذا
     * مستودعهم الافتراضي (من شاشة «المستودع الافتراضي للموظف» — تعيينٌ فعليّ،
     * لا استنتاجٌ من الصلاحيات)، ونطاقات الصلاحيات الثلاثة بأسمائها الفعلية.
     *
     * كل الاستعلامات هنا مجمَّعة (batch) لا تُكرَّر لكل مستودع، كي لا تتحوّل
     * قائمة عشرين مستودعاً إلى عشرات الاستعلامات.
     *
     * @return array<int, array>
     */
    private function buildStorehouseListMeta($storehouses): array
    {
        $ids = $storehouses->pluck('id');

        $productStats = ProductDetails::whereIn('store_house_id', $ids)
            ->select('store_house_id',
                DB::raw('COUNT(DISTINCT product_id) as products_count'),
                DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('store_house_id')
            ->get()
            ->keyBy('store_house_id');

        // قيمة تقديرية بسعر الشراء الحالي — مثل شاشة «قيمة المخزون» تماماً،
        // لا بمتوسط التكلفة لكل مستودع الأثقل حساباً والمناسب لصفحة مستودعٍ واحد
        $stockValues = ProductDetails::whereIn('product_details.store_house_id', $ids)
            ->join('products', 'products.id', '=', 'product_details.product_id')
            ->select('product_details.store_house_id',
                DB::raw('SUM(product_details.quantity * products.purchase_price) as stock_value'))
            ->groupBy('product_details.store_house_id')
            ->get()
            ->keyBy('store_house_id');

        $accounts = Account::whereIn('storehouse_id', $ids)->get()->keyBy('storehouse_id');

        [$employees, $roles, $branches] = $this->loadPermissionAudiences($storehouses);

        // الموظفون "المسؤولون" الفعليون: مَن هذا مستودعهم الافتراضي، من
        // شاشة «المستودع الافتراضي للموظف» — لا مَن يملك صلاحية عليه. صلاحيات
        // العرض/التعديل/الفواتير مسألة أخرى تماماً، وقد لا تخصّ موظفاً بعينه أصلاً
        // (قد تكون مضبوطة على فرعٍ أو دورٍ أو الكلّ).
        $responsibleEmployees = DefaultWarehouses::whereIn('storehouse_id', $ids)
            ->with('employee')
            ->get()
            ->filter(fn ($default) => $default->employee)
            ->groupBy('storehouse_id')
            ->map(fn ($group) => $group->pluck('employee'));

        $meta = [];

        foreach ($storehouses as $storehouse) {
            $stats = $productStats->get($storehouse->id);

            $meta[$storehouse->id] = [
                'products_count' => (int) ($stats->products_count ?? 0),
                'total_quantity' => (float) ($stats->total_quantity ?? 0),
                'stock_value' => (float) (($stockValues->get($storehouse->id))->stock_value ?? 0),
                'account' => $accounts->get($storehouse->id),
                'responsible_employees' => $responsibleEmployees->get($storehouse->id, collect()),
                'permissions' => [
                    'view' => $this->resolveScopeLabel($storehouse->view_permissions, $storehouse->value_of_view_permissions, $employees, $roles, $branches),
                    'create_invoices' => $this->resolveScopeLabel($storehouse->crate_invoices_permissions, $storehouse->value_of_crate_invoices_permissions, $employees, $roles, $branches),
                    'edit_stock' => $this->resolveScopeLabel($storehouse->edit_stock_permissions, $storehouse->value_of_edit_stock_permissions, $employees, $roles, $branches),
                ],
            ];
        }

        return $meta;
    }

    /**
     * تحميل دفعةٍ واحدة من الموظفين والأدوار والفروع التي تظهر
     * كقيمةٍ لأحد نطاقات الصلاحية الثلاثة عبر مجموعة مستودعات.
     *
     * @return array{0: Collection, 1: Collection, 2: Collection}
     */
    private function loadPermissionAudiences($storehouses): array
    {
        $employeeIds = [];
        $roleIds = [];
        $branchIds = [];

        foreach ($storehouses as $storehouse) {
            $scopes = [
                [$storehouse->view_permissions, $storehouse->value_of_view_permissions],
                [$storehouse->crate_invoices_permissions, $storehouse->value_of_crate_invoices_permissions],
                [$storehouse->edit_stock_permissions, $storehouse->value_of_edit_stock_permissions],
            ];

            foreach ($scopes as [$type, $value]) {
                if (blank($value)) {
                    continue;
                }

                match ((int) $type) {
                    1 => $employeeIds[] = $value,
                    2 => $roleIds[] = $value,
                    3 => $branchIds[] = $value,
                    default => null,
                };
            }
        }

        return [
            Employee::whereIn('id', array_unique($employeeIds))->get()->keyBy('id'),
            Role::whereIn('id', array_unique($roleIds))->get()->keyBy('id'),
            Branch::whereIn('id', array_unique($branchIds))->get()->keyBy('id'),
        ];
    }

    /**
     * تسمية نطاق صلاحية بمعناه الفعلي — لا برقم حقلٍ خام.
     *
     * القيم الأربع كما في شاشتي الإنشاء والتعديل: ٠=الكل، ١=موظف محدَّد،
     * ٢=دور محدَّد (من `roles`)، ٣=فرع محدَّد. (تختلف عن `setPermissions()` التي
     * تُعامل ٠ و٣ معاً كفرعٍ عند الحفظ — هذه الدالة للعرض فتنقل المعنى
     * الحقيقي المختار في الشاشة، لا الحقل الخام الناتج عن ذلك الالتباس.)
     */
    private function resolveScopeLabel($type, $valueId, $employees, $roles, $branches): string
    {
        switch ((int) $type) {
            case 1:
                $employee = $employees->get($valueId);

                return $employee ? 'موظف: '.$employee->full_name : 'موظف محدَّد';
            case 2:
                $role = $roles->get($valueId);

                return $role ? 'دور: '.$role->name : 'دور محدَّد';
            case 3:
                $branch = $branches->get($valueId);

                return $branch ? 'فرع: '.$branch->name : 'فرع محدَّد';
            default:
                return 'الكل (كل الموظفين)';
        }
    }

    public function create()
    {
        $branches = Branch::select('id', 'name')->get();
        $employees = Employee::select()->get();
        $roles = Role::select('id', 'name')->get();

        // فرع المستودع نفسه: موظفٌ يُقفَل على فرعه تلقائياً، ومن ليس موظفاً يختار بحرية
        $canSelectBranch = $this->canSelectBranch();
        $selectableBranches = $canSelectBranch ? $branches : collect();
        $defaultBranchId = $this->currentEffectiveBranchId();

        return view('stock::storehouse.crate', compact(
            'employees', 'branches', 'roles', 'canSelectBranch', 'selectableBranches', 'defaultBranchId'
        ));
    }

    public function store(StorehouseRequest $request)
    {
        DB::beginTransaction();

        try {
            // التحقق من وجود الحساب الأب (المخزون)
            // الحساب الأب من شاشة «الحسابات المحاسبية» لا من رقم مكتوب
            // في الشيفرة — فمن غيّر شجرة حساباته يضبط المفتاح ويعمل النظام
            $inventoryParentAccount = $this->requireAccountingAccount('inventory');

            // إنشاء المستودع
            $storehouse = new StoreHouse;
            $storehouse->name = $request->name;
            $storehouse->shipping_address = $request->shipping_address;
            $storehouse->status = $request->status;
            // فرع المستودع: فرع الموظف تلقائياً، أو فرعٌ من الطلب إن كان نطاقه يسمح به (يتحقّق ويرفض غير المسموح)
            $storehouse->branch_id = $this->resolveWriteBranchId($request->filled('branch_id') ? (int) $request->branch_id : null);
            $storehouse->view_permissions = $request->view_permissions;
            $storehouse->crate_invoices_permissions = $request->crate_invoices_permissions;
            $storehouse->edit_stock_permissions = $request->edit_stock_permissions;

            // التعامل مع المستودع الرئيسي
            if ($request->has('major')) {
                StoreHouse::where('major', 1)->update(['major' => 0]);
                $storehouse->major = 1;
            } else {
                $storehouse->major = 0;
            }

            // إعداد الصلاحيات
            $this->setPermissions($storehouse, $request);

            $storehouse->save();
            // إنشاء الحساب المحاسبي للمستودع
            $account = new Account;
            $account->name = $request->name;
            $account->type_accont = 0; // نوع الحساب (مستودع)
            $account->is_active = $request->is_active ?? 1;
            $account->parent_id = $this->requireAccountingAccountId('inventory'); // من «الحسابات المحاسبية»
            $account->balance_type = 'debit'; // نوع الرصيد (مدين)
            $account->account_type = 'assets'; // نوع الحساب (الأصول)
            $account->balance = 0; // الرصيد الابتدائي
            $account->storehouse_id = $storehouse->id; // ربط الحساب بالمستودع
            // الرمز يُحسب من الحساب الأب قبل الحفظ. كان يُحفظ أولاً برمز مؤقّت
            // نصّي (`TEMP-...`) ثم يُستبدل — والعمود `accounts.code` من نوع
            // `int`، فكان MySQL يرفض الإدراج ويسقط إضافة المستودع كلّها
            $account->code = $this->generateAccountCode($account->parent_id);
            $account->save();

            // ربط المستودع بالحساب
            // $storehouse->account_id = $account->id;
            $storehouse->save();

            // تسجيل Log
            OperationLog::record(
                'created',
                'storehouse_log',
                $storehouse->id,
                'تم إضافة المستودع: **'.$storehouse->name.'** وإنشاء الحساب المحاسبي رقم: **'.$account->code.'**',
            );

            DB::commit();

            return redirect()
                ->route('storehouse.index')
                ->with('success', 'تم إضافة المستودع والحساب المحاسبي بنجاح!');

        } catch (\Exception $e) {
            OperationLog::failure($e, 'storehouse_log', 'store');
            DB::rollback();

            Log::error('خطأ في إضافة المستودع: '.$e->getMessage());

            return redirect()
                ->route('storehouse.index')
                ->with('error', 'حدث خطأ أثناء إضافة المستودع: '.$e->getMessage());
        }
    }

    /**
     * إعداد صلاحيات المستودع
     */
    private function setPermissions($storehouse, $request)
    {
        // صلاحيات العرض
        switch ($request->view_permissions) {
            case 1: // موظف محدد
                $storehouse->value_of_view_permissions = $request->v_employee_id;
                break;
            case 2: // دور وظيفي
                $storehouse->value_of_view_permissions = $request->v_functional_role_id;
                break;
            default: // فرع
                $storehouse->value_of_view_permissions = $request->v_branch_id;
                break;
        }

        // صلاحيات إنشاء الفواتير
        switch ($request->crate_invoices_permissions) {
            case 1: // موظف محدد
                $storehouse->value_of_crate_invoices_permissions = $request->c_employee_id;
                break;
            case 2: // دور وظيفي
                $storehouse->value_of_crate_invoices_permissions = $request->c_functional_role_id;
                break;
            default: // فرع
                $storehouse->value_of_crate_invoices_permissions = $request->c_branch_id;
                break;
        }

        // صلاحيات تعديل المخزون
        switch ($request->edit_stock_permissions) {
            case 1: // موظف محدد
                $storehouse->value_of_edit_stock_permissions = $request->e_employee_id;
                break;
            case 2: // دور وظيفي
                $storehouse->value_of_edit_stock_permissions = $request->e_functional_role_id;
                break;
            default: // فرع
                $storehouse->value_of_edit_stock_permissions = $request->e_branch_id;
                break;
        }
    }

    /**
     * توليد رمز الحساب بناءً على التسلسل الهرمي
     */

    /**
     * رمز حساب فريد تحت حسابٍ أب.
     *
     * العمود `accounts.code` من نوع `int` وفريد، فالرمز رقميّ دائماً: رمز
     * الأب (أو معرّفه) متبوعاً بترتيب تسلسلي. ولا يحتاج معرّف الحساب الجديد،
     * فيُحسب قبل الحفظ لا بعده.
     */
    private function generateAccountCode($parentId)
    {
        $parent = Account::find($parentId);

        // رمز أساسي بناءً على رمز الحساب الأب أو معرّفه
        $baseCode = $parent ? ($parent->code ?: $parent->id) : $parentId;

        // عدد الحسابات الابنة الموجودة للأب
        $childrenCount = Account::where('parent_id', $parentId)->count();

        // توليد رمز فريد: الرمز الأساسي + ترتيب تسلسلي
        $uniqueCode = $baseCode.str_pad($childrenCount + 1, 3, '0', STR_PAD_LEFT);

        // التأكد من أن الرمز فريد
        while (Account::where('code', $uniqueCode)->exists()) {
            $childrenCount++;
            $uniqueCode = $baseCode.str_pad($childrenCount + 1, 3, '0', STR_PAD_LEFT);
        }

        return $uniqueCode;
    }

    /**
     * تحديث رصيد المستودع
     */
    public function updateStorehouseBalance($storehouseId, $amount, $operation = 'add')
    {
        $storehouse = StoreHouse::with('account')->find($storehouseId);

        if (! $storehouse || ! $storehouse->account) {
            throw new \Exception('المستودع أو الحساب المحاسبي غير موجود');
        }

        $storehouse->account->updateBalance($amount, $operation);

        return $storehouse->account->balance;
    }

    /**
     * يمنع الوصول إلى مستودعٍ خارج مستودعات المستخدم (أو مَن يديرهم) — حتى
     * لو عرف معرّفه مباشرةً ولم يمرّ بقائمة `index()` المُصفّاة أصلاً.
     */
    private function authorizeStorehouseAccess(StoreHouse $storehouse): void
    {
        $allowed = $this->accessibleWarehouseIds();
        if ($allowed !== null && ! in_array($storehouse->id, $allowed, true)) {
            abort(403, 'لا تملك صلاحية الوصول إلى هذا المستودع.');
        }
    }

    public function edit($id)
    {
        $storehouse = StoreHouse::findOrFail($id);
        $this->authorizeStorehouseAccess($storehouse);

        $branches = Branch::select('id', 'name')->get();
        $employees = Employee::select()->get();
        $roles = Role::select('id', 'name')->get();

        $canSelectBranch = $this->canSelectBranch();
        $selectableBranches = $canSelectBranch ? $branches : collect();

        return view('stock::storehouse.edit', compact(
            'storehouse', 'employees', 'branches', 'roles', 'canSelectBranch', 'selectableBranches'
        ));
    }

    public function show($id)
    {
        $storehouse = StoreHouse::findOrFail($id);
        $this->authorizeStorehouseAccess($storehouse);

        $logs = ModelsLog::where('type', 'storehouse_log')
            ->where('type_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        $accounting = $this->buildAccountingOverview($storehouse);
        $permissionsMeta = $this->buildStorehouseListMeta(collect([$storehouse]))[$storehouse->id];

        return view('stock::storehouse.show', array_merge(
            compact('storehouse', 'logs', 'permissionsMeta'),
            $accounting
        ));
    }

    /**
     * الكميات والحسابات المحاسبية لكل صنف في هذا المستودع — لتبويب «الكميات والحسابات».
     *
     * لكل منتج مرّ بحركةٍ في هذا المستودع: الكمية الحالية، الوارد
     * والمنصرف بتفاصيله، والمباع من الفواتير. وقيمة المخزون تُحسب من متوسط
     * تكلفة الصنف *في هذا المستودع تحديداً* — لا من سعر الشراء الحالي — كي
     * تُقارَن بمعنى بحساب المستودع في شجرة الحسابات لا برقم لحظي متغيّر.
     *
     * المقارنة الناتجة (قيمة المخزون المحسوبة مقابل رصيد الحساب في القيود)
     * هي صلب طلب المستخدم: تكشف انحرافاً بين المخزون الفعلي وما يسجّله
     * دفتر الأستاذ، ولا تفترض تطابقهما.
     */
    private function buildAccountingOverview(StoreHouse $storehouse): array
    {
        $id = $storehouse->id;

        $account = Account::where('storehouse_id', $id)->first();

        // الكمية الحالية الفعلية لكل صنف في هذا المستودع (المصدر الوحيد الموثوق للرصيد الحالي)
        $currentQuantities = ProductDetails::where('store_house_id', $id)
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id');

        // الأذون المعتمَدة التي لمست هذا المستودع (إضافة/صرف/تحويل وارد أو صادر/رصيد افتتاحي)
        $warehousePermits = WarehousePermits::where('status', 'apporod')
            ->where(function ($query) use ($id) {
                $query->where('store_houses_id', $id)
                    ->orWhere('from_store_houses_id', $id)
                    ->orWhere('to_store_houses_id', $id);
            })
            ->with('warehousePermitsProducts')
            ->get();

        // الفواتير التي صرفت من هذا المستودع (مباع ومرتجع)
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
            $withdrawnTotal = $outgoingTotal + $sold; // كل ما غادر المستودع فعلياً: صرف/تحويل صادر + مبيعات

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

    public function update(StorehouseRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $storehouse = StoreHouse::findOrFail($id);
            $this->authorizeStorehouseAccess($storehouse);
            $oldName = $storehouse->name;

            // تحديث بيانات المستودع
            $storehouse->name = $request->name;
            $storehouse->shipping_address = $request->shipping_address;
            $storehouse->status = $request->status;
            // المستخدم بلا صلاحية اختيار الفرع لا يُغيَّر فرع المستودع من تحته
            // بمجرّد تعديله حقلاً آخر — يبقى فرعه كما هو إلا إن أرسل فرعاً صراحةً وهو مسموحٌ له باختياره
            if ($request->filled('branch_id')) {
                $storehouse->branch_id = $this->resolveWriteBranchId((int) $request->branch_id);
            }
            $storehouse->view_permissions = $request->view_permissions;
            $storehouse->crate_invoices_permissions = $request->crate_invoices_permissions;
            $storehouse->edit_stock_permissions = $request->edit_stock_permissions;

            // التعامل مع المستودع الرئيسي
            if ($request->has('major')) {
                StoreHouse::where('major', 1)->update(['major' => 0]);
                $storehouse->major = 1;
            } else {
                $storehouse->major = 0;
            }

            // إعداد الصلاحيات
            $this->setPermissions($storehouse, $request);

            $storehouse->save();

            // التحقق من الحساب المحاسبي المرتبط
            $account = Account::where('storehouse_id', $storehouse->id)->first();

            if ($account) {
                // تحديث الحساب الحالي
                $account->name = $request->name;
                $account->is_active = $request->is_active ?? 1;
                $account->account_type = 'assets'; // نوع الحساب (الأصول)
                $account->save();
            } else {
                // إنشاء حساب جديد إذا لم يكن موجود
                $account = new Account;
                $account->name = $request->name;
                $account->type_accont = 0; // نوع الحساب (مستودع)
                $account->is_active = $request->is_active ?? 1;
                $account->parent_id = $this->requireAccountingAccountId('inventory'); // من «الحسابات المحاسبية»
                $account->balance_type = 'debit';
                $account->account_type = 'assets'; // نوع الحساب (الأصول)
                $account->balance = 0;
                $account->storehouse_id = $storehouse->id;
                // انظر store(): الرمز رقمي ويُحسب قبل الحفظ
                $account->code = $this->generateAccountCode($account->parent_id);
                $account->save();
            }

            // تسجيل Log
            OperationLog::record(
                'updated',
                'storehouse_log',
                $storehouse->id,
                sprintf(
                    'تم تعديل المستودع من **%s** إلى **%s**',
                    $oldName,
                    $storehouse->name
                ),
            );

            DB::commit();

            return redirect()
                ->route('storehouse.index')
                ->with('success', 'تم تحديث المستودع والحساب المحاسبي بنجاح!');

        } catch (\Exception $e) {
            OperationLog::failure($e, 'storehouse_log', 'update');
            DB::rollBack();
            Log::error('خطأ في تعديل المستودع: '.$e->getMessage());

            return redirect()
                ->route('storehouse.index')
                ->with('error', 'حدث خطأ أثناء تعديل المستودع: '.$e->getMessage());
        }
    }

    public function delete($id)
    {
        $storehouse = StoreHouse::findOrFail($id);
        $this->authorizeStorehouseAccess($storehouse);
        OperationLog::record(
            'logged',
            'storehouse_log',
            $storehouse->id,
            'تم  حذف المستودع :  :  **'.$storehouse->name.'**',
        );
        // التحقق مما إذا كان المستودع يحتوي على أصناف
        if ($storehouse->productDetails()->count() > 0) {
            return back()->with('error', 'هذا المستودع لديه معاملات، يمكنك إلغاء تفعيله فقط لا حذفه');
        }

        // التحقق مما إذا كان المستودع مستخدمًا في عمليات تحويل
        if ($storehouse->transfersFrom()->count() > 0 || $storehouse->transfersTo()->count() > 0) {
            return back()->with('error', 'هذا المستودع لديه معاملات، يمكنك إلغاء تفعيله فقط لا حذفه');
        }

        $storehouse->delete();

        return redirect()->route('storehouse.index')->with(key: ['error' => 'تم حذف المستودع بنجاج !!']);
    }

    // public function summary_inventory_operations($id)
    // {
    //     $storehouse = StoreHouse::findOrFail($id);
    //     $warehousePermits = WarehousePermits::where('store_houses_id', $id)->with('warehousePermitsProducts')->get();

    //     $allProducts = collect();

    //     foreach ($warehousePermits as $storePermit) {
    //         foreach ($storePermit['products'] as $product) {
    //             $allProducts->push($product);
    //         }
    //     }

    //     $uniqueProducts = $allProducts->unique('id');

    //     $uniqueProductsArray = $uniqueProducts->values()->all();
    //     return $warehousePermits;

    //     return view('stock.storehouse.summary_inventory_operations', [
    //         'warehousePermits' => $warehousePermits,
    //         'storehouse' => $storehouse,
    //     ]);
    // }

    public function summary_inventory_operations($id)
    {
        // مستودعٌ خارج نطاق المستخدم لا يُقرأ ولا يُعدَّل — حتى بتخمين معرّفه في الرابط
        $this->authorizeWarehouseAccess((int) $id);

        // جلب بيانات المستودع
        $storehouse = StoreHouse::findOrFail($id);

        // جلب جميع التصاريح الخاصة بالمستودع
        /*
            المعتمَدة وحدها.

            كان الاستعلام يجمع الأذون كلّها بلا نظرٍ إلى حالتها، فيُحتسب إذنٌ
            «معلَّق» لم يمسّ المخزون بعدُ كأنّه حركةٌ وقعت — والعكس: أُضيف
            `case 4` أدناه لأنّ الرصيد الافتتاحي كان يسقط من التقرير كلّه.

            وأقواس المجموعة ضرورية: `where(...)->orWhere(...)` بلا تجميع
            تُبطل شرط الحالة.
        */
        $warehousePermits = WarehousePermits::where('status', 'apporod')
            ->where(function ($query) use ($id) {
                $query->where('store_houses_id', $id)
                    ->orWhere('from_store_houses_id', $id)
                    ->orWhere('to_store_houses_id', $id);
            })
            ->with('warehousePermitsProducts')
            ->get();

        // جلب الفواتير مع عناصرها - تصفية حسب المستودع والمستخدم الحالي
        $invoices = Invoice::with('items')
            ->whereHas('items', function ($query) use ($id) {
                $query->where('store_house_id', $id);
            })
            // تصفية حسب المستخدم الحالي إذا كان موظف
            ->when(auth()->user()->employee_id, function ($query) {
                $query->where('created_by', auth()->id());
            })
            ->get();

        // مصفوفات لتخزين الكميات
        $normalSalesArray = []; // الكميات المباعة (فواتير عادية)
        $salesReturnsArray = []; // الكميات المرتجعة (فواتير مرتجع)

        // معالجة الفواتير
        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                // التحقق من أن العنصر مرتبط بالمستودع الحالي
                if ($item->store_house_id != $id) {
                    continue;
                }

                $productId = $item->product_id;

                // إذا كانت الفاتورة من نوع "عادي" (normal)
                if ($invoice->type == 'normal' || $invoice->type == 'pos') {
                    $normalSalesArray[$productId] = ($normalSalesArray[$productId] ?? 0) + $item->quantity;
                }

                // إذا كانت الفاتورة من نوع "مرتجع" (return)
                if ($invoice->type == 'returned') {
                    $salesReturnsArray[$productId] = ($salesReturnsArray[$productId] ?? 0) + $item->quantity;
                }
            }
        }

        // مصفوفة لتخزين بيانات المنتجات
        $productsData = [];

        // معالجة التصاريح
        foreach ($warehousePermits as $permit) {
            foreach ($permit->warehousePermitsProducts as $product) {
                $productId = $product->product_id;
                $productInfo = Product::find($productId);

                // إذا لم يتم العثور على المنتج، يتم تخطيه
                if (! $productInfo) {
                    continue;
                }

                // إذا لم يتم إضافة المنتج إلى المصفوفة، يتم إضافته
                if (! isset($productsData[$productId])) {
                    $productsData[$productId] = [
                        'name' => $productInfo->name,
                        'id' => $productId,
                        'incoming_manual' => 0,
                        'incoming_opening' => 0,
                        'incoming_transfer' => 0,
                        'outgoing_manual' => 0,
                        'outgoing_transfer' => 0,
                        'incoming_total' => 0,
                        'outgoing_total' => 0,
                        'movement_total' => 0,
                        'sold_quantity' => $normalSalesArray[$productId] ?? 0, // الكمية المباعة
                        'sales_return_quantity' => $salesReturnsArray[$productId] ?? 0, // مرتجع المبيعات
                    ];
                }

                $quantity = $product->quantity;

                // حساب القيم بناءً على نوع الإذن
                switch ($permit->permission_type) {
                    case 1: // إضافة (يدوي - وارد)
                        $productsData[$productId]['incoming_manual'] += $quantity;
                        break;
                    case 2: // صرف (يدوي - منصرف)
                        $productsData[$productId]['outgoing_manual'] += $quantity;
                        break;
                    case 3: // تحويل
                        if ($permit->to_store_houses_id == $id) {
                            $productsData[$productId]['incoming_transfer'] += $quantity;
                        }
                        if ($permit->from_store_houses_id == $id) {
                            $productsData[$productId]['outgoing_transfer'] += $quantity;
                        }
                        break;

                    case 4: // رصيد افتتاحي — وارد، وكان بلا حالةٍ فيسقط من التقرير
                        $productsData[$productId]['incoming_opening'] += $quantity;
                        break;
                }

                // حساب الإجماليات
                $productsData[$productId]['incoming_total'] =
                    $productsData[$productId]['incoming_manual'] +
                    $productsData[$productId]['incoming_opening'] +
                    $productsData[$productId]['incoming_transfer'];

                $productsData[$productId]['outgoing_total'] =
                    $productsData[$productId]['outgoing_manual'] +
                    $productsData[$productId]['outgoing_transfer'];

                $productsData[$productId]['movement_total'] =
                    $productsData[$productId]['incoming_total'] -
                    $productsData[$productId]['outgoing_total'];
            }
        }

        $categories = Category::all();

        // إرجاع البيانات إلى الواجهة
        return view('stock::storehouse.summary_inventory_operations', [
            'products' => $productsData,
            'storehouse' => $storehouse,
            'categories' => $categories,
        ]);
    }

    // Add AJAX search method for summary inventory operations
    public function searchSummaryInventoryOperations(Request $request, $id)
    {
        // مستودعٌ خارج نطاق المستخدم لا يُقرأ ولا يُعدَّل — حتى بتخمين معرّفه في الرابط
        $this->authorizeWarehouseAccess((int) $id);

        // جلب بيانات المستودع
        $storehouse = StoreHouse::findOrFail($id);

        // جلب جميع التصاريح الخاصة بالمستودع
        /*
            المعتمَدة وحدها.

            كان الاستعلام يجمع الأذون كلّها بلا نظرٍ إلى حالتها، فيُحتسب إذنٌ
            «معلَّق» لم يمسّ المخزون بعدُ كأنّه حركةٌ وقعت — والعكس: أُضيف
            `case 4` أدناه لأنّ الرصيد الافتتاحي كان يسقط من التقرير كلّه.

            وأقواس المجموعة ضرورية: `where(...)->orWhere(...)` بلا تجميع
            تُبطل شرط الحالة.
        */
        $warehousePermits = WarehousePermits::where('status', 'apporod')
            ->where(function ($query) use ($id) {
                $query->where('store_houses_id', $id)
                    ->orWhere('from_store_houses_id', $id)
                    ->orWhere('to_store_houses_id', $id);
            })
            ->with('warehousePermitsProducts')
            ->get();

        // جلب الفواتير مع عناصرها - تصفية حسب المستودع والمستخدم الحالي
        $invoices = Invoice::with('items')
            ->whereHas('items', function ($query) use ($id) {
                $query->where('store_house_id', $id);
            })
            // تصفية حسب المستخدم الحالي إذا كان موظف
            ->when(auth()->user()->employee_id, function ($query) {
                $query->where('created_by', auth()->id());
            })
            ->get();

        // مصفوفات لتخزين الكميات
        $normalSalesArray = []; // الكميات المباعة (فواتير عادية)
        $salesReturnsArray = []; // الكميات المرتجعة (فواتير مرتجع)

        // معالجة الفواتير
        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                // التحقق من أن العنصر مرتبط بالمستودع الحالي
                if ($item->store_house_id != $id) {
                    continue;
                }

                $productId = $item->product_id;

                // إذا كانت الفاتورة من نوع "عادي" (normal)
                if ($invoice->type == 'normal' || $invoice->type == 'pos') {
                    $normalSalesArray[$productId] = ($normalSalesArray[$productId] ?? 0) + $item->quantity;
                }

                // إذا كانت الفاتورة من نوع "مرتجع" (return)
                if ($invoice->type == 'returned') {
                    $salesReturnsArray[$productId] = ($salesReturnsArray[$productId] ?? 0) + $item->quantity;
                }
            }
        }

        // مصفوفة لتخزين بيانات المنتجات
        $productsData = [];

        // معالجة التصاريح
        foreach ($warehousePermits as $permit) {
            foreach ($permit->warehousePermitsProducts as $product) {
                $productId = $product->product_id;
                $productInfo = Product::find($productId);

                // إذا لم يتم العثور على المنتج، يتم تخطيه
                if (! $productInfo) {
                    continue;
                }

                // إذا لم يتم إضافة المنتج إلى المصفوفة، يتم إضافته
                if (! isset($productsData[$productId])) {
                    $productsData[$productId] = [
                        'name' => $productInfo->name,
                        'id' => $productId,
                        'incoming_manual' => 0,
                        'incoming_opening' => 0,
                        'incoming_transfer' => 0,
                        'outgoing_manual' => 0,
                        'outgoing_transfer' => 0,
                        'incoming_total' => 0,
                        'outgoing_total' => 0,
                        'movement_total' => 0,
                        'sold_quantity' => $normalSalesArray[$productId] ?? 0, // الكمية المباعة
                        'sales_return_quantity' => $salesReturnsArray[$productId] ?? 0, // مرتجع المبيعات
                    ];
                }

                $quantity = $product->quantity;

                // حساب القيم بناءً على نوع الإذن
                switch ($permit->permission_type) {
                    case 1: // إضافة (يدوي - وارد)
                        $productsData[$productId]['incoming_manual'] += $quantity;
                        break;
                    case 2: // صرف (يدوي - منصرف)
                        $productsData[$productId]['outgoing_manual'] += $quantity;
                        break;
                    case 3: // تحويل
                        if ($permit->to_store_houses_id == $id) {
                            $productsData[$productId]['incoming_transfer'] += $quantity;
                        }
                        if ($permit->from_store_houses_id == $id) {
                            $productsData[$productId]['outgoing_transfer'] += $quantity;
                        }
                        break;

                    case 4: // رصيد افتتاحي — وارد، وكان بلا حالةٍ فيسقط من التقرير
                        $productsData[$productId]['incoming_opening'] += $quantity;
                        break;
                }

                // حساب الإجماليات
                $productsData[$productId]['incoming_total'] =
                    $productsData[$productId]['incoming_manual'] +
                    $productsData[$productId]['incoming_opening'] +
                    $productsData[$productId]['incoming_transfer'];

                $productsData[$productId]['outgoing_total'] =
                    $productsData[$productId]['outgoing_manual'] +
                    $productsData[$productId]['outgoing_transfer'];

                $productsData[$productId]['movement_total'] =
                    $productsData[$productId]['incoming_total'] -
                    $productsData[$productId]['outgoing_total'];
            }
        }

        // Apply filters if provided
        if ($request->has('keywords') && ! empty($request->keywords)) {
            $keywords = strtolower($request->keywords);
            $productsData = array_filter($productsData, function ($product) use ($keywords) {
                return strpos(strtolower($product['name']), $keywords) !== false;
            });
        }

        if ($request->has('category') && ! empty($request->category)) {
            $categoryId = $request->category;
            $productsData = array_filter($productsData, function ($product) use ($categoryId) {
                $productInfo = Product::find($product['id']);

                return $productInfo && $productInfo->category_id == $categoryId;
            });
        }

        if ($request->has('operation_type') && ! empty($request->operation_type)) {
            $operationType = $request->operation_type;
            switch ($operationType) {
                case 'incoming':
                    $productsData = array_filter($productsData, function ($product) {
                        return $product['incoming_total'] > 0;
                    });
                    break;
                case 'outgoing':
                    $productsData = array_filter($productsData, function ($product) {
                        return $product['outgoing_total'] > 0;
                    });
                    break;
                case 'transfer':
                    $productsData = array_filter($productsData, function ($product) {
                        return $product['incoming_transfer'] > 0 || $product['outgoing_transfer'] > 0;
                    });
                    break;
            }
        }

        // Convert associative array to indexed array for proper JSON encoding
        $productsData = array_values($productsData);

        return response()->json([
            'success' => true,
            'data' => $productsData,
            'storehouse' => $storehouse,
        ]);
    }

    // Add export methods for summary inventory operations
    public function exportSummaryInventoryOperations(Request $request, $id, $format = 'excel')
    {
        // مستودعٌ خارج نطاق المستخدم لا يُقرأ ولا يُعدَّل — حتى بتخمين معرّفه في الرابط
        $this->authorizeWarehouseAccess((int) $id);

        $storehouse = StoreHouse::findOrFail($id);

        // Get the same data as in the summary_inventory_operations method
        /*
            المعتمَدة وحدها.

            كان الاستعلام يجمع الأذون كلّها بلا نظرٍ إلى حالتها، فيُحتسب إذنٌ
            «معلَّق» لم يمسّ المخزون بعدُ كأنّه حركةٌ وقعت — والعكس: أُضيف
            `case 4` أدناه لأنّ الرصيد الافتتاحي كان يسقط من التقرير كلّه.

            وأقواس المجموعة ضرورية: `where(...)->orWhere(...)` بلا تجميع
            تُبطل شرط الحالة.
        */
        $warehousePermits = WarehousePermits::where('status', 'apporod')
            ->where(function ($query) use ($id) {
                $query->where('store_houses_id', $id)
                    ->orWhere('from_store_houses_id', $id)
                    ->orWhere('to_store_houses_id', $id);
            })
            ->with('warehousePermitsProducts')
            ->get();

        // جلب الفواتير مع عناصرها - تصفية حسب المستودع والمستخدم الحالي
        $invoices = Invoice::with('items')
            ->whereHas('items', function ($query) use ($id) {
                $query->where('store_house_id', $id);
            })
            // تصفية حسب المستخدم الحالي إذا كان موظف
            ->when(auth()->user()->employee_id, function ($query) {
                $query->where('created_by', auth()->id());
            })
            ->get();

        $normalSalesArray = [];
        $salesReturnsArray = [];

        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                // التحقق من أن العنصر مرتبط بالمستودع الحالي
                if ($item->store_house_id != $id) {
                    continue;
                }

                $productId = $item->product_id;

                if ($invoice->type == 'normal' || $invoice->type == 'pos') {
                    $normalSalesArray[$productId] = ($normalSalesArray[$productId] ?? 0) + $item->quantity;
                }

                if ($invoice->type == 'returned') {
                    $salesReturnsArray[$productId] = ($salesReturnsArray[$productId] ?? 0) + $item->quantity;
                }
            }
        }

        $productsData = [];

        foreach ($warehousePermits as $permit) {
            foreach ($permit->warehousePermitsProducts as $product) {
                $productId = $product->product_id;
                $productInfo = Product::find($productId);

                if (! $productInfo) {
                    continue;
                }

                if (! isset($productsData[$productId])) {
                    $productsData[$productId] = [
                        'name' => $productInfo->name,
                        'id' => $productId,
                        'incoming_manual' => 0,
                        'incoming_opening' => 0,
                        'incoming_transfer' => 0,
                        'outgoing_manual' => 0,
                        'outgoing_transfer' => 0,
                        'incoming_total' => 0,
                        'outgoing_total' => 0,
                        'movement_total' => 0,
                        'sold_quantity' => $normalSalesArray[$productId] ?? 0,
                        'sales_return_quantity' => $salesReturnsArray[$productId] ?? 0,
                    ];
                }

                $quantity = $product->quantity;

                switch ($permit->permission_type) {
                    case 1:
                        $productsData[$productId]['incoming_manual'] += $quantity;
                        break;
                    case 2:
                        $productsData[$productId]['outgoing_manual'] += $quantity;
                        break;
                    case 3:
                        if ($permit->to_store_houses_id == $id) {
                            $productsData[$productId]['incoming_transfer'] += $quantity;
                        }
                        if ($permit->from_store_houses_id == $id) {
                            $productsData[$productId]['outgoing_transfer'] += $quantity;
                        }
                        break;
                }

                $productsData[$productId]['incoming_total'] =
                    $productsData[$productId]['incoming_manual'] +
                    $productsData[$productId]['incoming_opening'] +
                    $productsData[$productId]['incoming_transfer'];

                $productsData[$productId]['outgoing_total'] =
                    $productsData[$productId]['outgoing_manual'] +
                    $productsData[$productId]['outgoing_transfer'];

                $productsData[$productId]['movement_total'] =
                    $productsData[$productId]['incoming_total'] -
                    $productsData[$productId]['outgoing_total'];
            }
        }

        // Apply filters if provided (for export)
        if ($request->has('keywords') && ! empty($request->keywords)) {
            $keywords = strtolower($request->keywords);
            $productsData = array_filter($productsData, function ($product) use ($keywords) {
                return strpos(strtolower($product['name']), $keywords) !== false;
            });
        }

        if ($request->has('category') && ! empty($request->category)) {
            $categoryId = $request->category;
            $productsData = array_filter($productsData, function ($product) use ($categoryId) {
                $productInfo = Product::find($product['id']);

                return $productInfo && $productInfo->category_id == $categoryId;
            });
        }

        if ($request->has('operation_type') && ! empty($request->operation_type)) {
            $operationType = $request->operation_type;
            switch ($operationType) {
                case 'incoming':
                    $productsData = array_filter($productsData, function ($product) {
                        return $product['incoming_total'] > 0;
                    });
                    break;
                case 'outgoing':
                    $productsData = array_filter($productsData, function ($product) {
                        return $product['outgoing_total'] > 0;
                    });
                    break;
                case 'transfer':
                    $productsData = array_filter($productsData, function ($product) {
                        return $product['incoming_transfer'] > 0 || $product['outgoing_transfer'] > 0;
                    });
                    break;
            }
        }

        if ($format === 'excel') {
            return $this->exportSummaryToExcel($productsData, $storehouse);
        } elseif ($format === 'pdf') {
            return $this->exportSummaryToPDF($productsData, $storehouse);
        }

        return redirect()->back()->with('error', 'Invalid export format');
    }

    private function exportSummaryToExcel($productsData, $storehouse)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="summary_inventory_operations_'.$storehouse->name.'.csv"',
        ];

        $callback = function () use ($productsData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['اسم المنتج', 'فواتير الشراء', 'مرتجع المبيعات', 'التحويل الوارد', 'يدوي الوارد', 'إجمالي الوارد', 'فواتير البيع', 'مرتجع مشتريات', 'التحويل المنصرف', 'يدوي المنصرف', 'إجمالي المنصرف', 'إجمالي الحركة']);

            foreach ($productsData as $product) {
                fputcsv($file, [
                    $product['name'],
                    0, // فواتير الشراء
                    $product['sales_return_quantity'], // مرتجع المبيعات
                    $product['incoming_transfer'], // التحويل الوارد
                    $product['incoming_manual'], // يدوي الوارد
                    $product['incoming_total'], // إجمالي الوارد
                    $product['sold_quantity'], // فواتير البيع
                    0, // مرتجع مشتريات
                    $product['outgoing_transfer'], // التحويل المنصرف
                    $product['outgoing_manual'], // يدوي المنصرف
                    $product['outgoing_total'], // إجمالي المنصرف
                    $product['movement_total'] - $product['sold_quantity'], // إجمالي الحركة
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportSummaryToPDF($productsData, $storehouse)
    {
        // Create new PDF document
        $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('ملخص عمليات المخزون - '.$storehouse->name);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set RTL
        $pdf->setRTL(true);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('aealarabiya', '', 12);

        // Prepare HTML content
        $html = view('stock::storehouse.pdf.summary_inventory_operations', [
            'products' => $productsData,
            'storehouse' => $storehouse,
        ])->render();

        // Print text using writeHTMLCell()
        $pdf->writeHTML($html, true, false, true, false, '');

        // Close and output PDF document
        return $pdf->Output('summary_inventory_operations_'.$storehouse->name.'.pdf', 'D');
    }

    private function exportInventoryValueToPDF($products, $storehouse)
    {
        // Create new PDF document
        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Fawtra');
        $pdf->SetAuthor('Fawtra System');
        $pdf->SetTitle('قيمة المخزون التقديرية - '.$storehouse->name);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set RTL
        $pdf->setRTL(true);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('aealarabiya', '', 12);

        // Prepare HTML content
        $html = view('stock::storehouse.pdf.inventory_value', [
            'products' => $products,
            'storehouse' => $storehouse,
        ])->render();

        // Print text using writeHTMLCell()
        $pdf->writeHTML($html, true, false, true, false, '');

        // Close and output PDF document
        return $pdf->Output('inventory_value_'.$storehouse->name.'.pdf', 'D');
    }

    public function inventory_value($id)
    {
        // مستودعٌ خارج نطاق المستخدم لا يُقرأ ولا يُعدَّل — حتى بتخمين معرّفه في الرابط
        $this->authorizeWarehouseAccess((int) $id);

        // تصفية حسب المستودع والمستخدم الحالي
        $query = ProductDetails::where('store_house_id', $id)
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->with('product')
            ->groupBy('product_id');

        // إذا كان المستخدم الحالي موظف، نعرض فقط البيانات المرتبطة به
        if (auth()->user()->employee_id) {
            // يمكن إضافة تصفية إضافية هنا إذا لزم الأمر
        }

        $products = $query->get();
        $storehouse = StoreHouse::findOrFail($id);

        return view('stock::storehouse.inventory_value', compact('products', 'storehouse'));
    }

    // Add AJAX search method for inventory value
    public function searchInventoryValue(Request $request, $id)
    {
        // مستودعٌ خارج نطاق المستخدم لا يُقرأ ولا يُعدَّل — حتى بتخمين معرّفه في الرابط
        $this->authorizeWarehouseAccess((int) $id);

        $storehouse = StoreHouse::findOrFail($id);

        // تصفية حسب المستودع والمستخدم الحالي
        $query = ProductDetails::where('store_house_id', $id)
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->with('product')
            ->groupBy('product_id');

        // إذا كان المستخدم الحالي موظف، نعرض فقط البيانات المرتبطة به
        if (auth()->user()->employee_id) {
            // يمكن إضافة تصفية إضافية هنا إذا لزم الأمر
        }

        // Apply filters if provided
        if ($request->has('keywords') && ! empty($request->keywords)) {
            $keywords = $request->keywords;
            $query->whereHas('product', function ($q) use ($keywords) {
                $q->where('name', 'LIKE', "%{$keywords}%")
                    ->orWhere('barcode', 'LIKE', "%{$keywords}%");
            });
        }

        if ($request->has('category') && ! empty($request->category)) {
            $categoryId = $request->category;
            $query->whereHas('product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        if ($request->has('quantity_filter') && ! empty($request->quantity_filter)) {
            $quantityFilter = $request->quantity_filter;
            switch ($quantityFilter) {
                case 'low_stock':
                    $query->havingRaw('SUM(quantity) <= 10 AND SUM(quantity) > 0');
                    break;
                case 'out_of_stock':
                    $query->havingRaw('SUM(quantity) = 0');
                    break;
                case 'high_stock':
                    $query->havingRaw('SUM(quantity) > 50');
                    break;
            }
        }

        // Price filters
        if ($request->has('min_sale_price') && ! empty($request->min_sale_price)) {
            $minSalePrice = $request->min_sale_price;
            $query->whereHas('product', function ($q) use ($minSalePrice) {
                $q->where('sale_price', '>=', $minSalePrice);
            });
        }

        if ($request->has('max_sale_price') && ! empty($request->max_sale_price)) {
            $maxSalePrice = $request->max_sale_price;
            $query->whereHas('product', function ($q) use ($maxSalePrice) {
                $q->where('sale_price', '<=', $maxSalePrice);
            });
        }

        if ($request->has('min_purchase_price') && ! empty($request->min_purchase_price)) {
            $minPurchasePrice = $request->min_purchase_price;
            $query->whereHas('product', function ($q) use ($minPurchasePrice) {
                $q->where('purchase_price', '>=', $minPurchasePrice);
            });
        }

        if ($request->has('max_purchase_price') && ! empty($request->max_purchase_price)) {
            $maxPurchasePrice = $request->max_purchase_price;
            $query->whereHas('product', function ($q) use ($maxPurchasePrice) {
                $q->where('purchase_price', '<=', $maxPurchasePrice);
            });
        }

        // Profit filters
        if ($request->has('min_profit') && ! empty($request->min_profit)) {
            // This would need a more complex query to calculate profit
        }

        if ($request->has('max_profit') && ! empty($request->max_profit)) {
            // This would need a more complex query to calculate profit
        }

        $products = $query->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'storehouse' => $storehouse,
        ]);
    }

    // Add export methods for inventory value
    public function exportInventoryValue(Request $request, $id, $format = 'excel')
    {
        // مستودعٌ خارج نطاق المستخدم لا يُقرأ ولا يُعدَّل — حتى بتخمين معرّفه في الرابط
        $this->authorizeWarehouseAccess((int) $id);

        $storehouse = StoreHouse::findOrFail($id);

        // تصفية حسب المستودع والمستخدم الحالي
        $query = ProductDetails::where('store_house_id', $id)
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->with('product')
            ->groupBy('product_id');

        // إذا كان المستخدم الحالي موظف، نعرض فقط البيانات المرتبطة به
        if (auth()->user()->employee_id) {
            // يمكن إضافة تصفية إضافية هنا إذا لزم الأمر
        }

        // Apply filters if provided (for export)
        if ($request->has('keywords') && ! empty($request->keywords)) {
            $keywords = $request->keywords;
            $query->whereHas('product', function ($q) use ($keywords) {
                $q->where('name', 'LIKE', "%{$keywords}%")
                    ->orWhere('barcode', 'LIKE', "%{$keywords}%");
            });
        }

        if ($request->has('category') && ! empty($request->category)) {
            $categoryId = $request->category;
            $query->whereHas('product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        if ($request->has('quantity_filter') && ! empty($request->quantity_filter)) {
            $quantityFilter = $request->quantity_filter;
            switch ($quantityFilter) {
                case 'low_stock':
                    $query->havingRaw('SUM(quantity) <= 10 AND SUM(quantity) > 0');
                    break;
                case 'out_of_stock':
                    $query->havingRaw('SUM(quantity) = 0');
                    break;
                case 'high_stock':
                    $query->havingRaw('SUM(quantity) > 50');
                    break;
            }
        }

        // Price filters
        if ($request->has('min_sale_price') && ! empty($request->min_sale_price)) {
            $minSalePrice = $request->min_sale_price;
            $query->whereHas('product', function ($q) use ($minSalePrice) {
                $q->where('sale_price', '>=', $minSalePrice);
            });
        }

        if ($request->has('max_sale_price') && ! empty($request->max_sale_price)) {
            $maxSalePrice = $request->max_sale_price;
            $query->whereHas('product', function ($q) use ($maxSalePrice) {
                $q->where('sale_price', '<=', $maxSalePrice);
            });
        }

        if ($request->has('min_purchase_price') && ! empty($request->min_purchase_price)) {
            $minPurchasePrice = $request->min_purchase_price;
            $query->whereHas('product', function ($q) use ($minPurchasePrice) {
                $q->where('purchase_price', '>=', $minPurchasePrice);
            });
        }

        if ($request->has('max_purchase_price') && ! empty($request->max_purchase_price)) {
            $maxPurchasePrice = $request->max_purchase_price;
            $query->whereHas('product', function ($q) use ($maxPurchasePrice) {
                $q->where('purchase_price', '<=', $maxPurchasePrice);
            });
        }

        $products = $query->get();

        if ($format === 'excel') {
            // Export to Excel
            return $this->exportInventoryValueToExcel($products, $storehouse);
        } elseif ($format === 'pdf') {
            // Export to PDF
            return $this->exportInventoryValueToPDF($products, $storehouse);
        }

        return redirect()->back()->with('error', 'Invalid export format');
    }

    private function exportInventoryValueToExcel($products, $storehouse)
    {
        // Create a simple CSV export for now
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inventory_value_'.$storehouse->name.'.csv"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['كود المنتج', 'اسم المنتج', 'الكمية', 'سعر البيع', 'سعر الشراء', 'إجمالي سعر البيع', 'إجمالي سعر الشراء', 'الربح المتوقع']);

            foreach ($products as $product) {
                $totalPurchasePrice = $product->product->purchase_price * $product->quantity;
                $totalSalePrice = $product->product->sale_price * $product->quantity;
                $expectedProfit = $totalSalePrice - $totalPurchasePrice;

                fputcsv($file, [
                    $product->product->barcode,
                    $product->product->name,
                    $product->quantity,
                    number_format($product->product->sale_price, 2),
                    number_format($product->product->purchase_price, 2),
                    number_format($totalSalePrice, 2),
                    number_format($totalPurchasePrice, 2),
                    number_format($expectedProfit, 2),
                ]);
            }

            // Add totals row
            $totalSale = $products->sum(fn ($p) => $p->product->sale_price * $p->quantity);
            $totalPurchase = $products->sum(fn ($p) => $p->product->purchase_price * $p->quantity);
            $totalProfit = $totalSale - $totalPurchase;

            fputcsv($file, ['', '', '', '', 'الإجمالي', number_format($totalSale, 2), number_format($totalPurchase, 2), number_format($totalProfit, 2)]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function inventory_sheet($id)
    {
        // مستودعٌ خارج نطاق المستخدم لا يُقرأ ولا يُعدَّل — حتى بتخمين معرّفه في الرابط
        $this->authorizeWarehouseAccess((int) $id);

        $products = ProductDetails::where('store_house_id', $id)->get();

        $storehouse = StoreHouse::findOrFail($id);

        return view('stock::storehouse.inventory_sheet', compact('products', 'storehouse'));
    }

    /**
     * Toggle storehouse status (activate/deactivate)
     */
    public function toggleStatus(Request $request, $id)
    {
        // مستودعٌ خارج نطاق المستخدم لا يُقرأ ولا يُعدَّل — حتى بتخمين معرّفه في الرابط
        $this->authorizeWarehouseAccess((int) $id);

        try {
            $storehouse = StoreHouse::findOrFail($id);

            // Validate the status value
            $validStatuses = ['active', 'inactive', 'stopped'];
            $newStatus = $request->input('status');

            if (! in_array($newStatus, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status value',
                ], 400);
            }

            // Update the status
            $storehouse->status = $newStatus;
            $storehouse->save();

            // Log the status change
            $statusLabels = [
                'active' => 'نشط',
                'inactive' => 'غير نشط',
                'stopped' => 'متوقف',
            ];

            OperationLog::record(
                'updated',
                'storehouse_log',
                $storehouse->id,
                'تم تغيير حالة المستودع إلى: **'.$statusLabels[$newStatus].'**',
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الحالة بنجاح',
                'status' => $newStatus,
                'status_label' => $statusLabels[$newStatus],
            ]);

        } catch (\Exception $e) {
            Log::error('Error toggling storehouse status: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الحالة',
            ], 500);
        }
    }
} // End of Controller
