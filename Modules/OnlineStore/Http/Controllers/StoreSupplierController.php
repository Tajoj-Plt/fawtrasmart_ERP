<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\PaymentsProcess;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SystemCurrency;
use App\Support\AccountingPeriodGuard;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\OperationLog;
use App\Support\Scopes;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\OnlineStore\Support\StoreAssignmentScope;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;

class StoreSupplierController extends Controller
{
    /*
     * نفس صلاحيات `purchases.suppliers.*` التي تحرس
     * Purchases\SupplierManagementController — هذا الكنترولر يدير نفس
     * موديل/جدول `Supplier`، فليس قسماً مستقلاً.
     *
     * والموردون يُعرضون بقاعدة المالك: المُسنَد إلى المستخدم وإلى من يتبعه
     * (`suppliers.employee_id`)، مضيَّقاً بفرع الشريط؛ ومن يرى الكل (الفرع الرئيسي،
     * `data_scope_all`، غير الموظف) يرى الجميع (`supplierQuery()`). وصفحة مورد
     * خارج النطاق 404.
     */
    // حسابات القيود من «الحسابات المحاسبية» لا من ثوابت مكتوبة
    use ResolvesAccountingAccounts;

    public function __construct()
    {
        $this->middleware('permission.role:purchases.suppliers.view', ['only' => ['index', 'show', 'getStats']]);
        $this->middleware('permission.role:purchases.suppliers.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:purchases.suppliers.edit', ['only' => ['edit', 'update', 'updateStatus']]);
        $this->middleware('permission.role:purchases.suppliers.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:purchases.suppliers.manage_opening_balance', ['only' => ['updateSupplierOpeningBalance']]);
    }

    /**
     * عرض قائمة الموردين
     * Display suppliers list
     */
    public function index(Request $request)
    {
        // فلترٌ مرسَلٌ مصفوفةً ليس فلتراً — يُسقَط بدل أن يُسقِط الصفحة
        foreach (array_keys($request->query()) as $key) {
            if (is_array($request->query($key))) {
                $request->query->remove($key);
            }
        }

        $query = $this->supplierQuery();

        // البحث بالاسم أو رقم المورد — النموذج يرسل `search` (كان المتحكّم
        // يقرأ `employee_search` وحده، فلا يعمل البحث من الشاشة)
        $search = $request->input('search', $request->input('employee_search'));
        if (filled($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('trade_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('number_suply', 'LIKE', '%'.$search.'%')
                    ->orWhere('first_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search.'%');
            });
        }

        // البحث برقم المورد
        if ($request->filled('supplier_number')) {
            $query->where('number_suply', 'LIKE', '%'.$request->supplier_number.'%');
        }

        // البحث بالبريد الإلكتروني
        if ($request->filled('email')) {
            $query->where('email', 'LIKE', '%'.$request->email.'%');
        }

        // البحث برقم الجوال أو الهاتف
        if ($request->filled('mobile')) {
            $query->where(function ($q) use ($request) {
                $q->where('mobile', 'LIKE', '%'.$request->mobile.'%')
                    ->orWhere('phone', 'LIKE', '%'.$request->mobile.'%');
            });
        }

        if ($request->filled('phone')) {
            $query->where('phone', 'LIKE', '%'.$request->phone.'%');
        }

        // البحث بالعنوان — في أعمدته الفعلية (`full_address` ليس عموداً)
        if ($request->filled('address')) {
            $address = $request->address;
            $query->where(function ($q) use ($address) {
                foreach (['street1', 'street2', 'city', 'region'] as $column) {
                    $q->orWhere($column, 'LIKE', '%'.$address.'%');
                }
            });
        }

        // البحث بالحالة
        if (in_array($request->status, ['active', 'inactive', '1', '0'], true)) {
            $query->where('status', in_array($request->status, ['active', '1'], true) ? 1 : 0);
        }

        // البحث بالرقم الضريبي
        if ($request->filled('tax_number')) {
            $query->where('tax_number', 'LIKE', '%'.$request->tax_number.'%');
        }

        // فلتر الفرع — مقصوصٌ على نطاق المستخدم، ويشمل ما تحت الفرع المختار
        if ($request->filled('branch') && is_numeric($request->branch)) {
            $query->forBranch(DataScope::clampNode((int) $request->branch) ?: -1);
        }

        // جلب الموردين (الأحدث أولاً)
        $suppliers = $query->with(['account', 'branch', 'employee'])->orderBy('created_at', 'desc')->paginate(10);

        // للحفاظ على parameters البحث في روابط الترقيم
        $suppliers->appends($request->query());

        // إذا كان الطلب عبر AJAX، إرجاع البيانات فقط
        if ($request->ajax()) {
            return response()->json([
                'html' => view('onlinestore::suppliers.partials.suppliers_table', compact('suppliers'))->render(),
                'pagination' => view('onlinestore::suppliers.partials.pagination', compact('suppliers'))->render(),
            ]);
        }

        $branches = DataScope::branchOptions();
        $stats = $this->stats();

        return view('onlinestore::suppliers.index', compact('suppliers', 'branches', 'stats'));
    }

    /**
     * عرض صفحة إنشاء مورد جديد
     * Show create supplier form
     */
    public function create()
    {
        // معاينة الرقم التالي من «تسلسل أرقام المستندات» — بلا استهلاك
        try {
            $nextNumber = peek_document_number('supplier');
        } catch (\Throwable $e) {
            $nextNumber = '';
        }

        $currencies = $this->currencyOptions();
        $defaultCurrency = CurrencySettings::code();
        $branches = DataScope::branchOptions();
        $defaultBranchId = current_branch_id();

        // المسؤول عن المورد: من يحقّ له إسنادُهم، والموظفُ المقيَّد مُحدَّدٌ مسبقاً (موردُه يبقى ظاهراً له)
        $employees = StoreAssignmentScope::assignableEmployees();
        $selectedEmployeeId = old(StoreAssignmentScope::SUPPLIER_FIELD, StoreAssignmentScope::seesAll() ? null : (StoreAssignmentScope::selfEmployeeId() ?: null));

        return view('onlinestore::suppliers.create', compact('nextNumber', 'currencies', 'defaultCurrency', 'branches', 'defaultBranchId', 'employees', 'selectedEmployeeId'));
    }

    /**
     * حفظ مورد جديد
     * Store new supplier
     */
    public function store(Request $request)
    {
        $this->validateSupplier($request);

        // المسؤول عن المورد — خارج ما يحقّ له إسنادُه خطأ تحقّق، ولا مسؤولَ يعني الموظفَ المقيَّد نفسَه
        $assigneeId = StoreAssignmentScope::resolveSupplierAssignee($request->input(StoreAssignmentScope::SUPPLIER_FIELD), $request->has(StoreAssignmentScope::SUPPLIER_FIELD));

        try {
            DB::beginTransaction();

            $supplier = new Supplier([
                'trade_name' => $request->trade_name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'phone' => $request->phone,
                // العنوان حقلٌ واحد في هذه الشاشة — يُحفظ في `street1`
                // (كان يُرسَل باسم `full_address` وليس عموداً، فيضيع)
                'street1' => $request->full_address,
                'tax_number' => $request->tax_number,
                'commercial_registration' => $request->commercial_registration,
                'notes' => $request->notes,
                // رقم المورد من «تسلسل أرقام المستندات» وحده — ما يرسله النموذج يُتجاهل
                'number_suply' => next_document_number('supplier'),
                'currency' => $request->currency ?: CurrencySettings::code(),
                'branch_id' => Scopes::chosenBranchId(auth()->user(), $request->input('branch_id')) ?: current_branch_id(),
                'created_by' => auth()->id(),
                'employee_id' => $assigneeId,
            ]);
            // `status` ليس في fillable الموديل — يُسنَد مباشرة
            $supplier->status = 1;
            $supplier->save();

            // إنشاء الحساب المحاسبي للمورد
            $this->createSupplierAccount($supplier);

            OperationLog::record('created', 'supplier', $supplier, 'تم إضافة مورد **'.$supplier->trade_name.'**');

            DB::commit();

            return redirect()->route('store_suppliers.index')->with('success', 'تم إضافة المورد بنجاح');

        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — التسجيل يكتب عبر نفس
            // اتصال قاعدة البيانات، فيُلغى سجلّه هو نفسه مع المعاملة إن كُتب
            // قبل انتهائها (تحقّقتُ من هذا مباشرةً على قاعدة محلية)
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StoreSupplier', 'store');

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'تعذّر حفظ المورد. '.$this->readableError($e));
        }
    }

    /**
     * عرض تفاصيل المورد
     * Show supplier details
     */
    public function show($id)
    {
        $supplier = $this->findSupplier($id)->load(['account', 'branch', 'employee']);

        // فواتير ومدفوعات المورد الأخيرة — لمحةٌ في صفحته
        $recentInvoices = PurchaseInvoice::where('supplier_id', $supplier->id)
            ->whereIn('type', ['invoice', 'Return'])
            ->orderByDesc('date')->orderByDesc('id')
            ->limit(10)
            ->get();

        $ledgerAccount = Account::ledger()->where('supplier_id', $supplier->id)->first();
        $hasMovements = $ledgerAccount && JournalEntryDetail::where('account_id', $ledgerAccount->id)->exists();

        return view('onlinestore::suppliers.show', compact('supplier', 'recentInvoices', 'ledgerAccount', 'hasMovements'));
    }

    /**
     * عرض صفحة تعديل المورد
     * Show edit supplier form
     */
    public function edit($id)
    {
        $supplier = $this->findSupplier($id)->load(['account']);
        $branches = DataScope::branchOptions();

        // المسؤول الحالي يبقى في الحقل ولو خرج عمّن يحقّ له إسنادُهم — لا يُبدَّل بصمت
        $employees = StoreAssignmentScope::assignableEmployees([$supplier->employee_id]);
        $selectedEmployeeId = old(StoreAssignmentScope::SUPPLIER_FIELD, $supplier->employee_id);

        return view('onlinestore::suppliers.edit', compact('supplier', 'branches', 'employees', 'selectedEmployeeId'));
    }

    /**
     * تحديث بيانات المورد
     * Update supplier
     */
    public function update(Request $request, $id)
    {
        $supplier = $this->findSupplier($id);

        $this->validateSupplier($request, $supplier);

        $assigneeId = StoreAssignmentScope::resolveSupplierAssignee(
            $request->input(StoreAssignmentScope::SUPPLIER_FIELD),
            $request->has(StoreAssignmentScope::SUPPLIER_FIELD),
            $supplier->employee_id ? (int) $supplier->employee_id : null
        );

        try {
            DB::beginTransaction();

            $oldTradeName = $supplier->trade_name;

            $data = [
                'trade_name' => $request->trade_name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'phone' => $request->phone,
                'tax_number' => $request->tax_number,
                'commercial_registration' => $request->commercial_registration,
                'notes' => $request->notes,
                'updated_by' => auth()->id(),
            ];

            // العنوان: إن لم يتغيّر عمّا يُعرض (المركَّب من أجزائه) يبقى كما
            // هو؛ وإن أُعيدت كتابته يُحفظ نصّاً واحداً في `street1` وتُفرَّغ
            // بقيّة الأجزاء — وإلا تكرّرت عند العرض التالي
            if (trim((string) $request->full_address) !== trim((string) $supplier->full_address)) {
                $data += ['street1' => $request->full_address, 'street2' => null, 'city' => null, 'region' => null, 'postal_code' => null];
            }

            if ($request->filled('branch_id')) {
                $data['branch_id'] = Scopes::chosenBranchId(auth()->user(), $request->input('branch_id'), $supplier->branch_id);
            }

            // الغيابُ = لا تغيير (نموذجٌ قديم)؛ والموجود فُحص قبل أن يُكتب
            if ($request->has(StoreAssignmentScope::SUPPLIER_FIELD)) {
                $data['employee_id'] = $assigneeId;
            }

            $supplier->update($data);

            // تحديث الحساب المحاسبي إذا كان موجوداً
            $this->handleSupplierAccount($supplier, $oldTradeName, 0);

            OperationLog::record('updated', 'supplier', $supplier, 'تم تعديل بيانات المورد **'.$supplier->trade_name.'**');

            DB::commit();

            return redirect()->route('store_suppliers.index')->with('success', 'تم تحديث المورد بنجاح');

        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في store() أعلاه
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StoreSupplier', 'update', $id);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'تعذّر تحديث المورد. '.$this->readableError($e));
        }
    }

    /**
     * حذف المورد
     * Delete supplier
     *
     * موردٌ عليه فواتير أو مدفوعات أو حركاتٌ على حسابه لا يُحذف — حذفه
     * يُيتّم مستنداته وقيوده؛ يُوقَف بدل ذلك. وحسابه المحاسبي الفارغ يُحذف معه.
     */
    public function destroy($id)
    {
        $supplier = $this->findSupplier($id);
        $supplierName = $supplier->trade_name;

        if (PurchaseInvoice::where('supplier_id', $supplier->id)->exists()
            || PaymentsProcess::where('supplier_id', $supplier->id)->exists()) {
            return redirect()->back()->with('error', "لا يمكن حذف المورد \"{$supplierName}\": عليه فواتير أو مدفوعات. يمكنك إيقافه بدل حذفه.");
        }

        $accounts = Account::where('supplier_id', $supplier->id)->get();

        if (JournalEntryDetail::whereIn('account_id', $accounts->pluck('id'))->exists()) {
            return redirect()->back()->with('error', "لا يمكن حذف المورد \"{$supplierName}\": على حسابه حركات مالية. يمكنك إيقافه بدل حذفه.");
        }

        try {
            DB::transaction(function () use ($supplier, $accounts, $supplierName) {
                OperationLog::record('deleted', 'supplier', $supplier, 'تم حذف مورد **'.$supplierName.'**');

                foreach ($accounts as $account) {
                    $account->delete();
                }

                $supplier->delete();
            });

            return redirect()->route('store_suppliers.index')
                ->with('success', "تم حذف المورد \"{$supplierName}\" بنجاح");

        } catch (\Throwable $e) {
            OperationLog::failure($e, 'OnlineStore.StoreSupplier', 'destroy', $id);

            return redirect()->back()
                ->with('error', "تعذّر حذف المورد \"{$supplierName}\": مرتبط بمستندات أخرى. يمكنك إيقافه بدل حذفه.");
        }
    }

    /**
     * تغيير حالة المورد
     * Toggle supplier status
     */
    public function updateStatus(Request $request, $id)
    {
        $supplier = $this->findSupplier($id);

        // التحقّق خارج try: كان `catch (\Exception)` يلتقط خطأ التحقّق نفسه
        // فيعود «خطأ غير متوقع» 500 بدل رسالة الحقل
        $request->validate([
            'status' => 'required|in:0,1',
        ], [], ['status' => 'الحالة']);

        try {
            $supplier->status = (int) $request->status;
            $supplier->save();

            $statusText = (int) $request->status === 1 ? 'تم تفعيل' : 'تم إيقاف';
            $message = "{$statusText} المورد \"{$supplier->trade_name}\" بنجاح";

            OperationLog::record('updated', 'supplier', $supplier, $statusText.' المورد **'.$supplier->trade_name.'**');

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'supplier_id' => $supplier->id,
                    'new_status' => $supplier->status,
                    'supplier_name' => $supplier->trade_name,
                ]);
            }

            return redirect()->route('store_suppliers.index')->with('success', $message);

        } catch (\Throwable $e) {
            OperationLog::failure($e, 'OnlineStore.StoreSupplier', 'updateStatus', $id);

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
     * الحصول على إحصائيات الموردين
     * Get suppliers statistics
     */
    public function getStats()
    {
        try {
            return response()->json([
                'success' => true,
                'stats' => $this->stats(),
            ]);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StoreSupplier', 'getStats');

            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الإحصائيات',
            ], 500);
        }
    }

    // ==================== النطاق والتحقّق ====================

    /**
     * الموردون الذين يراهم المستخدم الحالي — قاعدة المالك:
     *
     *   • **المُسنَد** إليه أو إلى من يتبعه (`suppliers.employee_id`)، ومن يرى
     *     الكل (غير الموظف، `data_scope_all`، فرعُه الأساسيّ الرئيسي) يرى الجميع،
     *     ومورّدٌ بلا مسؤولٍ لا يراه إلا هو ({@see StoreAssignmentScope}).
     *   • ثم **الفرع**: العقدة المختارة في الشريط وما تحتها (والرئيسيُّ «الكل»
     *     لمن يرى الكل)، والمورد القديم بلا فرعٍ يبقى ظاهراً.
     *
     * كان القيدُ فرعاً ونطاقَ بياناتٍ فقط، فموظفٌ في فرعٍ يرى كلَّ موردي فرعه.
     */
    private function supplierQuery()
    {
        $query = Supplier::query();

        if ($node = StoreAssignmentScope::headerNode()) {
            $query->forBranch($node);
        }

        return StoreAssignmentScope::constrainSuppliers($query);
    }

    /** مورد داخل النطاق — وإلا 404 */
    private function findSupplier($id): Supplier
    {
        return $this->supplierQuery()->findOrFail((int) $id);
    }

    /** @return array{active:int, inactive:int, total:int} بنفس نطاق القائمة */
    private function stats(): array
    {
        $active = $this->supplierQuery()->where('status', 1)->count();
        $total = $this->supplierQuery()->count();

        return ['active' => $active, 'inactive' => $total - $active, 'total' => $total];
    }

    /** العملات المفعَّلة في «العملات» — لا قائمة مكتوبة في القالب */
    private function currencyOptions()
    {
        try {
            return SystemCurrency::where('is_active', true)->orderBy('code')->get(['code', 'name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function validateSupplier(Request $request, ?Supplier $supplier = null): void
    {
        $request->validate([
            'trade_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'full_address' => 'nullable|string|max:500',
            'tax_number' => 'nullable|string|max:50',
            'commercial_registration' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:5000',
            'currency' => ['nullable', 'string', 'size:3', Rule::exists('system_currencies', 'code')->where('is_active', true)],
            'branch_id' => 'nullable|integer|exists:branches,id',
            'employee_id' => 'nullable|integer',
        ], [
            'trade_name.required' => 'الاسم التجاري مطلوب',
            'trade_name.max' => 'الاسم التجاري يجب أن لا يتجاوز 255 حرف',
            'email.email' => 'البريد الإلكتروني غير صالح',
            'currency.exists' => 'العملة المختارة غير مفعَّلة في إعدادات النظام.',
        ], [
            'trade_name' => 'الاسم التجاري',
            'email' => 'البريد الإلكتروني',
            'mobile' => 'رقم الجوال',
            'phone' => 'رقم الهاتف',
            'full_address' => 'العنوان',
            'tax_number' => 'الرقم الضريبي',
            'commercial_registration' => 'السجل التجاري',
            'notes' => 'الملاحظات',
            'currency' => 'العملة',
            'branch_id' => 'الفرع',
            'employee_id' => 'المسؤول عن المورد',
        ]);
    }

    /** رسالة تُعرض للمستخدم — بلا نصّ SQL ولا أسماء جداول */
    private function readableError(\Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return 'حدث خطأ في قاعدة البيانات — سُجّل في سجلّ الأخطاء. راجع مسؤول النظام.';
        }

        return $e->getMessage();
    }

    /**
     * إنشاء حساب محاسبي للمورد
     */
    private function createSupplierAccount($supplier)
    {
        // جلب الحساب الأب "الموردون" من كلاس SystemAccountIds
        $creditorsAccount = Account::find($this->accountingAccountId('suppliers', SystemAccountIds::SUPPLIERS));

        // إذا لم يتم العثور على الحساب الأب (في حال كان محذوفًا)
        if (! $creditorsAccount) {
            // إنشاء حساب الموردون الرئيسي إذا لم يكن موجودًا
            $creditorsAccount = Account::create([
                'id' => $this->accountingAccountId('suppliers', SystemAccountIds::SUPPLIERS),
                'name' => 'الموردون',
                'code' => '2100',
                'account_type' => 'liability',
                'balance_type' => 'credit',
                'is_active' => true,
                'parent_id' => null,
            ]);
        }

        // إنشاء حساب المورد الجديد كحساب فرعي
        $supplierAccount = new Account;
        $supplierAccount->name = $supplier->trade_name;
        $supplierAccount->supplier_id = $supplier->id;

        // تعيين نوع الحساب المالي ليكون مطابقًا للحساب الأب
        $supplierAccount->account_type = $creditorsAccount->account_type;

        // توليد كود جديد للحساب
        $newCode = $this->generateSupplierAccountCode($creditorsAccount->id, $creditorsAccount->code);

        $supplierAccount->code = $newCode;
        $supplierAccount->balance_type = 'credit';
        $supplierAccount->parent_id = $creditorsAccount->id;
        $supplierAccount->is_active = true;
        $supplierAccount->balance = 0; // الرصيد يُحسب تلقائياً من القيود

        $supplierAccount->save();
    }

    /**
     * معالجة الحساب المحاسبي للمورد
     */
    private function handleSupplierAccount($supplier, $oldTradeName, $oldOpeningBalance)
    {
        // البحث عن الحساب المحاسبي للمورد
        $supplierAccount = Account::ledger()->where('supplier_id', $supplier->id)->first();

        if ($supplierAccount) {
            // تحديث اسم الحساب إذا تغير الاسم التجاري
            if ($oldTradeName !== $supplier->trade_name) {
                $supplierAccount->name = $supplier->trade_name;
            }

            // التأكد من أن الحساب فرعي لحساب الموردون
            $creditorsAccount = Account::find($this->accountingAccountId('suppliers', SystemAccountIds::SUPPLIERS));
            if ($creditorsAccount && $supplierAccount->parent_id != $creditorsAccount->id) {
                $supplierAccount->parent_id = $creditorsAccount->id;

                // تحديث الكود ليكون فرعياً من كود الحساب الأب
                $newCode = $this->generateSupplierAccountCode($creditorsAccount->id, $creditorsAccount->code);
                $supplierAccount->code = $newCode;
            }

            $supplierAccount->save();
        } else {
            // إذا لم يكن هناك حساب محاسبي، إنشاء واحد جديد
            $this->createSupplierAccount($supplier);
        }
    }

    /**
     * تحديث الرصيد الافتتاحي للمورد
     */
    public function updateSupplierOpeningBalance(Request $request, $id)
    {
        $supplier = $this->findSupplier($id);

        $request->validate([
            'opening_balance' => 'required|numeric|min:0|max:999999999999',
            'balance_type' => 'nullable|in:credit,debit',
        ], [], [
            'opening_balance' => 'الرصيد الافتتاحي',
            'balance_type' => 'نوع الرصيد',
        ]);

        // الفترة المحاسبية — يُنشئ قيد رصيد افتتاحي بتاريخ اليوم
        if ($blocked = AccountingPeriodGuard::blockReason(now()->toDateString())) {
            return redirect()->back()->with('error', $blocked);
        }

        // الحصول على الحساب المرتبط بالمورد
        $supplierAccount = Account::ledger()->where('supplier_id', $supplier->id)->first();

        if (! $supplierAccount) {
            return redirect()->back()->with('error', 'لا يوجد حساب مرتبط بهذا المورد');
        }

        try {
            DB::beginTransaction();

            $openingBalance = round((float) $request->opening_balance, 2);
            $balanceType = $request->balance_type ?: 'credit'; // دائن افتراضياً

            // إنشاء أو تحديث قيد الرصيد الافتتاحي
            if ($openingBalance != 0) {
                $this->createOpeningBalanceEntry($supplier, $supplierAccount, $openingBalance, $balanceType);
            } else {
                // إذا كان الرصيد يساوي صفر، احذف القيد القديم
                $this->deleteOpeningBalanceEntry($supplierAccount->id);
            }

            // ما تقرؤه صيغة رصيد المورد (Supplier::current_balance) وتقاريره
            $supplier->update(['opening_balance' => $openingBalance, 'opening_balance_date' => now()->toDateString()]);
            $supplierAccount->forceFill([
                'opening_balance' => $openingBalance,
                'opening_balance_type' => $openingBalance != 0 ? $balanceType : null,
            ])->save();

            OperationLog::record('updated', 'supplier', $supplier, 'تم تحديث الرصيد الافتتاحي للمورد **'.$supplier->trade_name.'** إلى '.CurrencySettings::number($openingBalance).' ('.($balanceType === 'credit' ? 'دائن' : 'مدين').')');

            DB::commit();

            return redirect()->route('store_suppliers.show', $supplier->id)->with('success', 'تم تحديث الرصيد الافتتاحي بنجاح');
        } catch (ValidationException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في store() أعلاه
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StoreSupplier', 'updateSupplierOpeningBalance', $id);

            return redirect()->back()->with('error', 'تعذّر تحديث الرصيد الافتتاحي. '.$this->readableError($e));
        }
    }

    /**
     * إنشاء قيد الرصيد الافتتاحي للمورد
     * Opening balance is a journal entry only - balance is calculated automatically
     *
     * @param  float  $amount  - المبلغ
     * @param  string  $balanceType  - نوع الرصيد (credit دائن / debit مدين)
     */
    private function createOpeningBalanceEntry(Supplier $supplier, Account $supplierAccount, $amount, $balanceType = 'credit')
    {
        // حذف القيود الافتتاحية السابقة
        $this->deleteOpeningBalanceEntry($supplierAccount->id);

        // حساب الأرصدة الافتتاحية المقابل
        $openingBalanceAccountId = $this->requireAccountingAccountId('opening_balance', SystemAccountIds::OPENING_BALANCE);

        // إنشاء قيد يومية جديد للرصيد الافتتاحي
        $journalEntry = new JournalEntry;
        // من «تسلسل أرقام المستندات» (قسم القيود) لا سلسلةً مركّبة من الوقت
        $journalEntry->reference_number = next_document_number('entry');
        $journalEntry->date = now()->toDateString();
        $balanceTypeAr = $balanceType == 'credit' ? 'دائن' : 'مدين';
        $journalEntry->description = 'قيد رصيد افتتاحي '.$balanceTypeAr.' للمورد: '.$supplierAccount->name;
        $journalEntry->status = 1; // معتمد
        $journalEntry->currency = $supplier->currency ?: CurrencySettings::code();
        if ($supplier->branch_id) {
            $journalEntry->branch_id = $supplier->branch_id;
        }
        $journalEntry->reference_type = ReferenceType::OPENING_BALANCE;
        $journalEntry->created_by_employee = auth()->id() ?? 1;
        $journalEntry->approved_by_employee = auth()->id() ?? 1;
        $journalEntry->save();

        // دائن: من حـ/ الأرصدة الافتتاحية إلى حـ/ المورد (له علينا)
        // مدين: من حـ/ المورد إلى حـ/ الأرصدة الافتتاحية (لنا عنده)
        [$debitAccountId, $creditAccountId] = $balanceType == 'credit'
            ? [$openingBalanceAccountId, $supplierAccount->id]
            : [$supplierAccount->id, $openingBalanceAccountId];

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $debitAccountId,
            'description' => 'رصيد افتتاحي '.$balanceTypeAr.' للمورد: '.$supplierAccount->name,
            'debit' => $amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $creditAccountId,
            'description' => 'رصيد افتتاحي '.$balanceTypeAr.' للمورد: '.$supplierAccount->name,
            'debit' => 0,
            'credit' => $amount,
            'is_debit' => false,
        ]);

        // الرصيد يُحدَّث عبر JournalEntryDetail::created() — وهذه ترفعه إلى الآباء
        Account::updateBalancesHierarchically($supplierAccount->id);
        Account::updateBalancesHierarchically($openingBalanceAccountId);
    }

    /**
     * حذف قيد الرصيد الافتتاحي للمورد
     */
    private function deleteOpeningBalanceEntry($accountId)
    {
        // البحث عن القيود الافتتاحية المرتبطة بهذا الحساب
        $journalEntries = JournalEntry::with('details')
            ->where('reference_type', ReferenceType::OPENING_BALANCE)
            ->whereHas('details', function ($q) use ($accountId) {
                $q->where('account_id', $accountId);
            })
            ->get();

        foreach ($journalEntries as $entry) {
            $affected = $entry->details->pluck('account_id')->unique();

            // الحذف الجماعي لا يُطلق أحداث الموديل — فيُعاد حساب الأرصدة صراحةً
            // (كان يُحذف القيد ويبقى رصيد المورد والأرصدة الافتتاحية على حاله)
            $entry->details()->delete();
            $entry->delete();

            foreach ($affected as $affectedAccountId) {
                Account::updateBalancesHierarchically($affectedAccountId);
            }
        }
    }

    /**
     * توليد كود الحساب للمورد
     */
    private function generateSupplierAccountCode($parentAccountId, $parentCode)
    {
        // البحث عن آخر حساب فرعي تحت حساب الموردون
        $lastChild = Account::where('parent_id', $parentAccountId)
            ->orderBy('code', 'desc')
            ->first();

        // إذا لم يوجد أي حساب فرعي، ابدأ بـ 01
        if (! $lastChild) {
            return $parentCode.'01';
        }

        // استخراج الرقم من آخر الكود وزيادته
        $lastNumber = (int) substr($lastChild->code, strlen($parentCode));
        $newNumber = $lastNumber + 1;
        $newCode = $parentCode.str_pad($newNumber, 2, '0', STR_PAD_LEFT);

        // التأكد من أن الكود غير مستخدم
        while (Account::where('code', $newCode)->exists()) {
            $newNumber++;
            $newCode = $parentCode.str_pad($newNumber, 2, '0', STR_PAD_LEFT);
        }

        return $newCode;
    }
}
