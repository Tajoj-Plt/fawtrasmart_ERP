<?php

namespace Modules\OnlineStore\Http\Controllers;

use App\Constants\permissions\PermissionSources;
use App\Constants\SystemAccountIds;
use App\Enums\LinkingType;
use App\Enums\PurchaseDocumentType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Branch;
use App\Models\ClientRelation;
use App\Models\DocumentTemplate;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\PaymentsProcess;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseInvoice;
use App\Models\StoreHouse;
use App\Models\Supplier;
use App\Models\TaxInvoice;
use App\Models\TaxSitting;
use App\Models\Treasury;
use App\Models\User;
use App\Models\WarehousePermits;
use App\Models\WarehousePermitsProducts;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Services\OnlineStoreSettingsService;
use App\Services\Purchases\PurchaseDocumentTotals;
use App\Services\Purchases\PurchaseTaxCalculator;
use App\Support\AccountingPeriodGuard;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\DocumentPrintPage;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use App\Support\Scopes;
use App\Support\StockEligibility;
use App\Support\TaxCalculator;
use App\Support\TreasuryAccessGuard;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\OnlineStore\Http\Controllers\Concerns\GeneratesDocumentNumbers;
use Modules\OnlineStore\Http\Controllers\Concerns\ResolvesStoreDefaults;
use Modules\OnlineStore\Http\Controllers\Concerns\ScopesStoreWarehouses;
use Modules\OnlineStore\Support\StoreAssignmentScope;
use Modules\Purchases\Services\PurchaseSettings;
use Modules\Purchases\Support\SupplierGuard;
use Modules\Stock\Http\Controllers\Concerns\ResolvesAccountingAccounts;
use Modules\Stock\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use TCPDF;

class StorePurchaseInvoiceController extends Controller
{
    // حساب المشتريات من «الحسابات المحاسبية» لا من ثابتٍ مكتوب — نفس ما
    // تفعله Modules\Purchases\Support\ProductPurchaseLedger لهذا المفتاح
    use GeneratesDocumentNumbers, ResolvesAccountingAccounts, ResolvesStoreDefaults;

    // أيُّ مستودعات المتجر يراها المستخدم (المسنَدة له ولتابعيه ∩ فرع الشريط) — المصدر نفسه
    // الذي تقرؤه شاشات المستودعات والأذون، فلا يفترق منتقي المستودع هنا عن قائمة المستودعات
    use ScopesStoreWarehouses, ScopesToEmployeeHierarchy;

    /** نوع المستند في «قوالب المستندات» — settings.doc_templates.types.purchase_invoice */
    private const DOCUMENT_TYPE = 'purchase_invoice';

    /*
     * كلُّ إجراءٍ هنا يعمل على **فاتورة شراءٍ تخصّ المتجر داخل نطاق المستخدم**
     * (`findStoreInvoice()`): جدول `purchase_invoices` مشتركٌ مع وحدة
     * المشتريات، وكانت شاشات العرض/التعديل/الحذف/الطباعة تقبل معرّف أيّ
     * فاتورةٍ في النظام.
     */
    public function __construct()
    {
        // صلاحيات العرض - عرض فواتير الشراء وطباعتها
        $this->middleware('permission.role:purchases.invoices.view', ['only' => ['index', 'show', 'print', 'thermal', 'compact', 'exportPDF', 'notesPdf']]);

        // صلاحية الإضافة
        $this->middleware('permission.role:purchases.invoices.create', ['only' => ['create', 'store']]);

        // التعديل والحذف — كلٌّ بصلاحيته (كانا «تعديل أو حذف» معاً للثلاثة)
        $this->middleware('permission.role:purchases.invoices.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:purchases.invoices.delete', ['only' => ['destroy']]);

        // سداد فاتورة آجلة — نفس صلاحية سندات دفع الموردين في المشتريات
        $this->middleware('permission.role:purchases.payments.create', ['only' => ['pay']]);
    }

    public function index(Request $request)
    {
        // فلترٌ مرسَلٌ مصفوفةً ليس فلتراً — يُسقَط بدل أن يُسقِط الصفحة
        foreach (array_keys($request->query()) as $key) {
            if (is_array($request->query($key))) {
                $request->query->remove($key);
            }
        }

        $suppliers = $this->supplierOptions();
        $users = User::whereIn('id', $this->storeInvoiceQuery()->select('created_by')->distinct())->get();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        // إذا كان الطلب Ajax، نعيد البيانات فقط
        if ($request->ajax()) {
            return $this->getFilteredData($request);
        }

        // في البداية نعيد الصفحة مع البيانات الأولية
        $purchaseData = $this->getFilteredData($request, false);

        return view('onlinestore::purchases.invoices_purchase.index', array_merge(
            compact('purchaseData', 'suppliers', 'users', 'account_setting'),
            [
                'documentTemplates' => PrintSettings::templatesFor(self::DOCUMENT_TYPE),
                'prefersPdf' => PrintSettings::prefersPdf(),
            ]
        ));
    }

    private function getFilteredData(Request $request, $returnJson = true)
    {
        // فواتير المتجر داخل نطاق المستخدم (الفرع المختار + نطاق البيانات)
        $query = $this->storeInvoiceQuery()
            ->with(['supplier', 'creator', 'warehousePermit', 'branch']);

        // تطبيق الفلاتر
        $this->applyFilters($query, $request);

        // استبعاد الفواتير التي لها فواتير مرتجع
        $query->whereDoesntHave('returns', function ($subQuery) {
            $subQuery->where('type', 'Return');
        });

        // ترتيب النتائج
        $query->orderBy('created_at', 'desc');

        // الحصول على النتائج مع التقسيم إلى صفحات
        $purchaseData = $query->paginate(30)->appends($request->query());

        if ($returnJson) {
            $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

            return response()->json([
                'success' => true,
                'data' => view('onlinestore::purchases.invoices_purchase.partials.table', compact('purchaseData', 'account_setting'))->render(),
                'total' => $purchaseData->total(),
                'current_page' => $purchaseData->currentPage(),
                'last_page' => $purchaseData->lastPage(),
                'from' => $purchaseData->firstItem(),
                'to' => $purchaseData->lastItem(),
            ]);
        }

        return $purchaseData;
    }

    private function applyFilters($query, $request)
    {
        // البحث بواسطة المورد
        if ($request->filled('employee_search')) {
            $query->where('supplier_id', $request->employee_search);
        }

        // البحث برقم الفاتورة
        if ($request->filled('number_invoice')) {
            $query->where('code', 'LIKE', '%'.$request->number_invoice.'%');
        }

        // البحث بالحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // البحث بحالة الدفع
        if ($request->filled('payment_status')) {
            $this->applyPaymentStatusFilter($query, $request->payment_status);
        }

        // البحث بواسطة المنشئ
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        // البحث في الحقل المخصص
        if ($request->filled('contract')) {
            $query->where('reference_number', 'LIKE', '%'.$request->contract.'%');
        }

        // البحث في وصف البنود
        if ($request->filled('description')) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('description', 'LIKE', '%'.$request->description.'%');
            });
        }

        // البحث بنوع المستند (فاتورة / مرتجع)
        if ($request->filled('source')) {
            $query->where('type', $request->source);
        }

        // فلتر الفرع — مقصوصٌ على نطاق المستخدم، ويشمل ما تحت الفرع المختار
        if ($request->filled('branch') && is_numeric($request->branch)) {
            $query->forBranch(DataScope::clampNode((int) $request->branch) ?: -1);
        }

        // البحث بالتاريخ
        if ($request->filled('start_date_from')) {
            $query->whereDate('date', '>=', $request->start_date_from);
        }
        if ($request->filled('start_date_to')) {
            $query->whereDate('date', '<=', $request->start_date_to);
        }

        // البحث بتاريخ الإنشاء
        if ($request->filled('created_at_from')) {
            $query->whereDate('created_at', '>=', $request->created_at_from);
        }
        if ($request->filled('created_at_to')) {
            $query->whereDate('created_at', '<=', $request->created_at_to);
        }

        // البحث بحالة الاستلام
        if ($request->filled('receiving_status')) {
            $query->where('receiving_status', $request->receiving_status);
        }

        // البحث برقم الحساب
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }
    }

    // دالة مساعدة لفلترة حالة الدفع
    private function applyPaymentStatusFilter($query, $paymentStatus)
    {
        switch ($paymentStatus) {
            case 'paid':
                $query->where('payment_status', 'paid');
                break;
            case 'partial':
                $query->where('payment_status', 'partially_paid');
                break;
            case 'unpaid':
                $query->where('payment_status', 'unpaid');
                break;
            case 'returned':
                $query->where('payment_status', 'returned');
                break;
            case 'overpaid':
                $query->where('payment_status', 'overpaid');
                break;
            case 'draft':
                $query->where('payment_status', 'draft');
                break;
        }
    }

    public function create(Request $request)
    {
        $user = auth()->user();

        // المورد المُمرَّر في الرابط يُحدَّد مسبقاً ويبقى في القائمة ولو خالف فرعَ الشريط — لكن لا يُكشف مورّدٌ خارج نطاق إسناد المستخدم
        $selectedSupplierId = is_numeric($request->supplier_id) && $this->supplierInAssignmentScope((int) $request->supplier_id) ? (int) $request->supplier_id : null;
        $suppliers = $this->supplierOptions($selectedSupplierId);
        $items = $this->productOptions();
        $taxs = TaxSitting::all();
        $account_setting = AccountSetting::where('user_id', $user->id)->first();

        // مستودعات المتجر النشطة داخل نطاق المستخدم — إليها تُستلم البضاعة
        $storeHouses = $this->storehouseOptions();

        // خزائن وبنوك المتجر التي يجوز لهذا المستخدم السحب منها — تُستعمل
        // للجزء المدفوع فقط؛ الفاتورة الآجلة لا تحتاج خزينة
        $treasuries = $this->payableTreasuries();

        $MainTreasury = $this->defaultPayableTreasury();
        $treasury_id = $MainTreasury->id ?? null;
        $isAdmin = $user && $user->hasRolePermission('data_scope_all'); // ✅ hasRolePermission() بدلاً من عمود role النصي

        // المستودع الافتراضي من إعدادات المتجر — تُختار بضاعة الفاتورة إليه
        // مبدئياً في الفورم، ونفس هذا الافتراضي يُستعمل احتياطاً عند الحفظ
        $defaultStoreHouseId = $this->getDefaultStorehouse()?->id;
        if ($defaultStoreHouseId && ! $storeHouses->contains('id', $defaultStoreHouseId)) {
            $defaultStoreHouseId = null;
        }
        $defaultStoreHouseId ??= $storeHouses->count() === 1 ? $storeHouses->first()->id : null;

        /*
            إنشاء مرتجع من فاتورة قائمة — نفس مدخل شاشة Purchases الحقيقية
            (ReturnsInvoiceController::createFromInvoice)، لكن بإعادة استعمال
            فورم الإنشاء نفسه بدل شاشة منفصلة: الفاتورة الأصلية تُملأ الفورم
            بالكامل (المورد والبنود)، ورقم المستند من تسلسل «مرتجعات
            المشتريات» المستقل، لا تسلسل الفواتير.
        */
        $originalInvoice = null;
        if ($request->filled('return_from')) {
            $originalInvoice = $this->storeInvoiceQuery()
                ->with(['supplier', 'items.product'])
                ->where('type', 'invoice')
                ->find((int) $request->return_from);

            if ($originalInvoice) {
                $existingReturn = PurchaseInvoice::where('type', PurchaseDocumentType::RETURN->value)
                    ->where('reference_id', $originalInvoice->id)
                    ->first();

                if ($existingReturn) {
                    return redirect()->route('store_purchase_invoices.show', $existingReturn->id)
                        ->with('error', 'يوجد مرتجع بالفعل لفاتورة الشراء رقم '.$originalInvoice->code.' — رقمه '.$existingReturn->code.'.');
                }

                // مورد الفاتورة الأصلية يبقى في القائمة ولو كان خارج الفرع المختار
                $suppliers = $this->supplierOptions($originalInvoice->supplier_id);
            }
        }

        // الرقم من إعدادات «تسلسل أرقام المستندات» — معاينة بلا استهلاك،
        // ولمرتجع المشتريات تسلسله المستقل لا تسلسل فواتير الشراء
        $code = $this->previewDocumentNumber($originalInvoice ? 'purchase-return' : 'purchase-invoice');

        // تنبيه الفترة المقفلة قبل ملء الفاتورة لا بعد الضغط على حفظ
        $periodBlock = AccountingPeriodGuard::blockReason(now());

        return view('onlinestore::purchases.invoices_purchase.create', compact(
            'suppliers',
            'code',
            'items',
            'taxs',
            'storeHouses',
            'account_setting',
            'selectedSupplierId',
            'treasuries',
            'MainTreasury',
            'treasury_id',
            'isAdmin',
            'defaultStoreHouseId',
            'originalInvoice',
            'periodBlock'
        ));
    }

    /**
     * حفظ فاتورة شراء للمتجر.
     *
     * ══════════ القيد ══════════
     *
     *   آجلة:   مدين المشتريات (+ ضريبة المدخلات) / دائن المورد — ولا خزينة.
     *   نقدية:  القيد نفسه، ثم قيد سداد: مدين المورد / دائن الخزينة بالمدفوع.
     *   جزئية:  كالنقدية بمقدار الدفعة، والباقي ذمّةٌ على المورد.
     *
     * والبضاعة تدخل المخزون باعتماد الإذن المخزني المعلَّق الذي يُنشأ هنا
     * (مدين المستودع / دائن المشتريات) — انظر StorePermitsController::approve().
     *
     * **الخزينة للجزء المدفوع فقط.** كان الحفظ يطلب خزينةً لكل فاتورة ولو
     * آجلة، فتُرفض بـ«لا توجد خزينة متاحة» متى لم تُضبط خزينة افتراضية
     * للمتجر — والآجلة لا يخرج فيها نقدٌ أصلاً.
     */
    public function store(Request $request)
    {
        if ($request->type === 'return') {
            return $this->storeReturn($request);
        }

        // الضريبة الشاملة على المستند (`document_tax_id`) تُفرَض على كل بند — كما في المشتريات
        if (is_array($request->input('items'))) {
            PurchaseTaxCalculator::applyComprehensive($request);
        }

        $this->validateInvoiceRequest($request);

        // الفترة المحاسبية — فاتورة الشراء تُنشئ قيداً بتاريخها
        if ($blocked = AccountingPeriodGuard::blockReason($request->date)) {
            throw ValidationException::withMessages(['date' => $blocked]);
        }

        $this->assertSupplierAllowed($request->supplier_id);

        $supplier = Supplier::find($request->supplier_id);
        // «منع المستندات على مورد موقوف» — إعداد المشتريات نفسه
        try {
            SupplierGuard::assertActive($supplier);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['supplier_id' => $e->getMessage()]);
        }

        // المستودع: المُرسَل إن كان مستودع متجرٍ داخل النطاق، وإلا افتراضي المتجر
        $storeHouse = $this->resolveStorehouse($request->input('store_house_id'));

        $user = Auth::user();

        try {
            DB::beginTransaction();

            // جلب إعدادات فواتير الشراء
            $autoPaidSetting = PurchaseSettings::enabled('default_paid_invoices');
            $autoReceivedSetting = PurchaseSettings::enabled('default_received_invoices');
            $updatePricesSetting = PurchaseSettings::enabled('update_product_prices');

            $calc = $this->computeDocument($request, $storeHouse->id);

            $total_with_tax = $calc['grand_total'];

            // حالة الاستلام — تأشير المستخدم أو خيار «مستلمة افتراضياً»
            $receiving_status = ($autoReceivedSetting || $request->input('is_received') == '1') ? 'received' : 'not_received';

            // ══════════ الدفع ══════════
            $paid_amount = 0.0;
            $payment_status = 'unpaid';

            if ($autoPaidSetting || $request->input('is_paid') == '1' || $request->boolean('is_full_advance_payment')) {
                $paid_amount = $total_with_tax;
            } elseif ((float) $request->input('advance_payment', 0) > 0) {
                $paid_amount = (float) $request->advance_payment;
                if ($request->input('advance_payment_type') === 'percentage') {
                    $paid_amount = round(($total_with_tax * min($paid_amount, 100)) / 100, 2);
                }
            } elseif ((float) $request->input('paid_amount', 0) > 0) {
                $paid_amount = (float) $request->paid_amount;
            }

            // لا يُدفع أكثر من قيمة الفاتورة — الزائد ليس سداداً لها
            $paid_amount = round(min($paid_amount, $total_with_tax), 2);

            if ($paid_amount > 0) {
                $payment_status = $paid_amount >= $total_with_tax ? 'paid' : 'partially_paid';
            }

            $due_value = round($total_with_tax - $paid_amount, 2);

            // ══════════ الخزينة — للجزء المدفوع فقط ══════════
            $treasuryAccount = null;
            if ($paid_amount > 0) {
                $treasuryAccount = $this->resolvePaymentTreasury(
                    $request->input('advance_payment_treasury_id') ?: $request->input('treasury_id'),
                    $paid_amount
                );
            }

            $updateLog = [];
            if ($updatePricesSetting) {
                foreach ($calc['items'] as $item) {
                    Product::where('id', $item['product_id'])->update(['purchase_price' => $item['unit_price']]);
                }
                $updateLog[] = 'تحديث أسعار الشراء مفعَّل';
            }

            $notes = trim((string) $request->notes);
            if ($treasuryAccount) {
                $notes = trim($notes."\nالخزينة المستخدمة: ".$treasuryAccount->name);
            }

            // إنشاء الفاتورة — رقمها من «تسلسل أرقام المستندات» وحده، وما
            // يرسله النموذج في `code` يُتجاهل
            $purchaseInvoice = new PurchaseInvoice([
                'supplier_id' => $supplier->id,
                'code' => $this->issueDocumentNumber('purchase-invoice'),
                'type' => 'invoice',
                'date' => $request->date,
                'terms' => (int) ($request->terms ?? 0),
                'notes' => $notes ?: null,
                'payment_status' => $payment_status,
                'receiving_status' => $receiving_status,
                'created_by' => Auth::id(),
                // الفاتورة تخصّ فرع المستودع الذي تُستلم فيه بضاعتها
                'branch_id' => $storeHouse->branch_id ?: Scopes::chosenBranchId($user, $request->input('branch_id')),
                'account_id' => $treasuryAccount?->id,
                'discount_amount' => $calc['invoice_discount'],
                // الخصم العام يُحفظ مبلغاً محسوباً — فنوعه «مبلغ» دائماً
                'discount_type' => 1,
                'shipping_cost' => $calc['shipping_cost'],
                'shipping_tax' => $calc['shipping_tax'],
                'tax_type' => 1,
                'payment_method' => $paid_amount > 0 ? ($request->input('payment_method') ?: $request->input('advance_payment_method')) : null,
                'reference_number' => $request->input('reference_number') ?: $request->input('advance_reference_number'),
                'received_date' => $receiving_status === 'received' ? ($request->input('received_date') ?: $request->date) : null,
                'is_paid' => $payment_status === 'paid',
                'is_received' => $receiving_status === 'received',
                'subtotal' => $calc['subtotal'],
                'total_discount' => $calc['total_discount'],
                'total_tax' => round($calc['item_tax'] + $calc['shipping_tax'], 2),
                'grand_total' => $total_with_tax,
                'due_value' => $due_value,
                'advance_payment' => $paid_amount,
            ]);
            $this->stampCurrency($purchaseInvoice);
            $purchaseInvoice->save();

            // إذنٌ مخزني واحد معلَّق — البضاعة تدخل المخزون باعتماده
            $warehousePermit = $this->createWarehousePermit($purchaseInvoice, $storeHouse->id);

            $this->writeInvoiceLines($purchaseInvoice, $warehousePermit, $calc);

            OperationLog::record(
                'created',
                'purchase_invoice',
                $purchaseInvoice,
                sprintf(
                    'تم انشاء فاتورة شراء رقم **%s** للمورد **%s** بقيمة **%s** (%s بنداً) وإذن مخزني رقم **%s**',
                    $purchaseInvoice->code,
                    $supplier->trade_name ?? '',
                    CurrencySettings::number($total_with_tax).' '.$this->invoiceCurrency($purchaseInvoice),
                    count($calc['items']),
                    $warehousePermit->number
                )
            );

            // معالجة المرفقات
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.preg_replace('/[^\w.\-]+/u', '_', $file->getClientOriginalName());
                    $file->move(public_path('assets/uploads/'), $filename);
                    $purchaseInvoice->attachments = $filename;
                    $purchaseInvoice->save();
                }
            }

            // القيود المحاسبية: قيد الشراء دائماً، وقيد السداد للمدفوع فقط
            $this->createPurchaseJournalEntry($purchaseInvoice);

            if ($paid_amount > 0) {
                $this->recordSupplierPayment(
                    $purchaseInvoice,
                    $treasuryAccount,
                    $paid_amount,
                    $request->date,
                    $request->input('payment_method') ?: $request->input('advance_payment_method'),
                    ($payment_status === 'paid' ? 'دفعة كاملة' : 'دفعة مقدمة').' لفاتورة المشتريات رقم '.$purchaseInvoice->code
                );
            }

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Throwable $e) {
            // ⚠️ rollback() قبل failure() لا بعده: التسجيل يكتب عبر نفس اتصال
            // قاعدة البيانات، فإن كُتب داخل معاملةٍ لم تُنهَ بعد يُلغى سجلّه
            // هو نفسه حين تُلغى المعاملة بأثرٍ رجعي (تحقّقتُ من هذا مباشرةً)
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StorePurchaseInvoice', 'store');

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'تعذّر حفظ فاتورة المشتريات. '.$this->readableError($e));
        }

        try {
            notifications::create([
                'type' => 'purchase_creation',
                'title' => $user->name.' أنشأ فاتورة شراء جديدة',
                'description' => 'تم إنشاء فاتورة الشراء رقم '.$purchaseInvoice->code.' للمورد '.($supplier->trade_name ?? '').' بقيمة '.CurrencySettings::number($purchaseInvoice->grand_total).' '.$this->invoiceCurrency($purchaseInvoice),
            ]);
        } catch (\Throwable $e) {
            // الإشعار تابعٌ — فشله لا يُسقط فاتورةً حُفظت
            OperationLog::failure($e, 'OnlineStore.StorePurchaseInvoice', 'store.notification');
        }

        $message = match ($payment_status) {
            'paid' => 'تم إنشاء فاتورة المشتريات وسدادها بالكامل من «'.$treasuryAccount->name.'».',
            'partially_paid' => 'تم إنشاء فاتورة المشتريات — دُفع '.CurrencySettings::number($paid_amount).' من «'.$treasuryAccount->name.'» والمتبقي '.CurrencySettings::number($due_value).' آجل على المورد.',
            default => 'تم إنشاء فاتورة المشتريات الآجلة — قُيّدت كاملةً على حساب المورد.',
        };

        return redirect()
            ->route('store_purchase_invoices.show', $purchaseInvoice->id)
            ->with('success', $message.' الإذن المخزني رقم '.$warehousePermit->number.' بانتظار الاعتماد لإدخال البضاعة إلى المخزون.');
    }

    /**
     * مرتجع شراء — سجلٌّ في نفس جدول فواتير الشراء بـ`type = 'return'`،
     * مربوطٌ بالفاتورة الأصلية عبر `reference_id`. نفس نمط
     * Purchases\ReturnsInvoiceController لكن بخطوتين محاسبيتين فقط
     * (لا استرداد نقدي عبر خزينة، ولا تعديل/نسخ/ملاحظات مرتجع منفصلة):
     *
     *   1. عند الإنشاء: تخفيض ما ندين به للمورّد فوراً (createReturnSupplierJournalEntry) —
     *      عكس قيد الشراء تماماً.
     *   2. عند اعتماد الإذن المخزني (StorePermitsController::approve): إخراج
     *      البضاعة فعلياً من المخزون وقيدها — نفس تأجيل استلام فاتورة الشراء
     *      العادية إلى حين اعتماد إذنها.
     */
    private function storeReturn(Request $request)
    {
        $request->validate([
            'return_from' => 'required|integer',
            'date' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'store_house_id' => 'nullable|integer',
            'items' => 'required|array|min:1|max:500',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|gt:0|max:99999999',
            'items.*.unit_price' => 'nullable|numeric|min:0|max:999999999',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_1' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_2' => 'nullable|numeric|min:0|max:100',
        ], [
            'items.required' => 'يجب إضافة بند واحد على الأقل للمرتجع',
        ], $this->attributeNames());

        // الفاتورة الأصلية فاتورة متجرٍ داخل نطاق المستخدم — لا أيُّ فاتورةٍ بمعرّفها
        $originalInvoice = $this->storeInvoiceQuery()
            ->with(['supplier', 'items.product'])
            ->where('type', 'invoice')
            ->findOrFail((int) $request->return_from);

        try {

            $existingReturn = PurchaseInvoice::where('type', PurchaseDocumentType::RETURN->value)
                ->where('reference_id', $originalInvoice->id)
                ->first();

            if ($existingReturn) {
                return redirect()->route('store_purchase_invoices.show', $existingReturn->id)
                    ->with('error', 'يوجد مرتجع بالفعل لفاتورة الشراء رقم '.$originalInvoice->code.' — رقمه '.$existingReturn->code.'.');
            }

            $returnDate = $request->date ?: now()->toDateString();

            if ($blocked = AccountingPeriodGuard::blockReason($returnDate)) {
                return redirect()->back()->withInput()->withErrors(['date' => $blocked]);
            }

            // لا يُردّ من الصنف أكثر ممّا اشتُري في الفاتورة الأصلية
            $purchased = $originalInvoice->items->groupBy('product_id')->map(fn ($rows) => (float) $rows->sum('quantity'));
            $returning = collect($request->items)->filter(fn ($row) => ! blank($row['product_id'] ?? null))
                ->groupBy('product_id')->map(fn ($rows) => (float) $rows->sum(fn ($row) => (float) ($row['quantity'] ?? 0)));

            foreach ($returning as $productId => $quantity) {
                if ($quantity > ($purchased[$productId] ?? 0) + 0.0001) {
                    $name = Product::where('id', $productId)->value('name') ?: ('#'.$productId);

                    return redirect()->back()->withInput()->withErrors(['items' => 'كمية المرتجع من «'.$name.'» ('.$quantity.') أكبر من المشتراة في الفاتورة الأصلية ('.($purchased[$productId] ?? 0).').']);
                }
            }

            DB::beginTransaction();

            $total_amount = 0;
            $items_data = [];

            foreach ($request->items ?? [] as $item) {
                if (blank($item['product_id'] ?? null)) {
                    continue;
                }

                $product = Product::findOrFail($item['product_id']);
                $quantity = floatval($item['quantity']);
                $unit_price = floatval($item['unit_price']);
                $item_total = $quantity * $unit_price;

                $item_discount = 0;
                if (isset($item['discount']) && $item['discount'] > 0) {
                    $item_discount = (isset($item['discount_type']) && $item['discount_type'] === 'percentage')
                        ? ($item_total * floatval($item['discount'])) / 100
                        : floatval($item['discount']);
                }

                $total_amount += $item_total - $item_discount;

                $items_data[] = [
                    'product_id' => $item['product_id'],
                    'item' => $product->name ?? 'المنتج '.$item['product_id'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'discount' => $item_discount,
                    'discount_type' => isset($item['discount_type']) && $item['discount_type'] === 'percentage' ? 2 : 1,
                    'tax_1' => floatval($item['tax_1'] ?? 0),
                    'tax_2' => floatval($item['tax_2'] ?? 0),
                    'total' => $item_total - $item_discount,
                    'store_house_id' => $item['store_house_id'] ?? null,
                ];
            }

            if (empty($items_data)) {
                throw new \Exception('يجب إضافة بند واحد على الأقل للمرتجع');
            }

            $total_tax = 0;
            foreach ($items_data as $item) {
                $item_total = $item['quantity'] * $item['unit_price'];
                $total_tax += $item_total * TaxCalculator::fraction($item['tax_1'])
                    + $item_total * TaxCalculator::fraction($item['tax_2']);
            }

            $grand_total = $total_amount + $total_tax;

            // الرقم من تسلسل «مرتجعات المشتريات» وحده — ما يرسله النموذج يُتجاهل
            $purchaseReturn = new PurchaseInvoice([
                'supplier_id' => $originalInvoice->supplier_id,
                'code' => $this->issueDocumentNumber('purchase-return'),
                'type' => PurchaseDocumentType::RETURN->value,
                'reference_id' => $originalInvoice->id,
                // المرتجع يتبع فرع فاتورته الأصلية
                'branch_id' => $originalInvoice->branch_id,
                'date' => $returnDate,
                'notes' => $request->notes ?: ('مرتجع لفاتورة الشراء رقم '.$originalInvoice->code),
                'payment_status' => 'unpaid',
                'receiving_status' => 'not_received',
                'created_by' => Auth::id(),
                'subtotal' => $total_amount,
                'total_discount' => 0,
                'total_tax' => $total_tax,
                'grand_total' => $grand_total,
                'due_value' => 0,
            ]);
            // المرتجع بعملة فاتورته الأصلية
            if (Schema::hasColumn('purchase_invoices', 'currency')) {
                $purchaseReturn->setAttribute('currency', $this->invoiceCurrency($originalInvoice));
            }
            $purchaseReturn->save();

            foreach ($items_data as $item) {
                $item['purchase_invoice_id'] = $purchaseReturn->id;
                InvoiceItem::create($item);
            }

            $this->createReturnSupplierJournalEntry($purchaseReturn);

            $permit = $this->createWarehousePermitForReturn($purchaseReturn, $request);

            OperationLog::record(
                'created',
                'purchase_invoice',
                $purchaseReturn,
                sprintf('تم إنشاء مرتجع شراء رقم **%s** لفاتورة الشراء رقم **%s**', $purchaseReturn->code, $originalInvoice->code)
            );

            DB::commit();

            return redirect()->route('store_purchase_invoices.show', $purchaseReturn->id)
                ->with('success', 'تم إنشاء مرتجع الشراء بنجاح — بانتظار اعتماد الإذن المخزني رقم '.($permit->number ?? '').' لإخراج البضاعة فعلياً من المخزون');
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في store() أعلاه
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StorePurchaseInvoice', 'storeReturn');

            return redirect()->back()->withInput()
                ->with('error', 'تعذّر إنشاء المرتجع. '.$this->readableError($e));
        }
    }

    /**
     * قيد تخفيض المديونية عند إنشاء المرتجع — عكس createPurchaseJournalEntry()
     * تماماً: مدين المورّد (يقلّ ما ندين له به)، دائن المشتريات وضريبتها.
     */
    private function createReturnSupplierJournalEntry(PurchaseInvoice $purchaseReturn): void
    {
        $supplierAccount = $this->supplierLedgerAccount($purchaseReturn->supplier_id);

        $mainStore = Account::find(OnlineStoreSettingsService::storeAccountId('purchases_account_id'))
            ?? $this->requireAccountingAccount('purchases', SystemAccountIds::PURCHASES);

        $taxAccount = null;
        if ($purchaseReturn->total_tax > 0) {
            $taxAccount = Account::find(OnlineStoreSettingsService::storeAccountId('vat_paid_account_id'));
            if (! $taxAccount) {
                throw new \Exception('حساب القيمة المضافة المحصلة برقم 37 غير موجود في النظام!');
            }
        }

        $description = 'مرتجع شراء # '.$purchaseReturn->code;

        $journalEntry = $this->createSupplierJournalEntry([
            'reference_number' => $purchaseReturn->code,
            'purchase_invoice_id' => $purchaseReturn->id,
            'date' => $purchaseReturn->date,
            'description' => $description,
            'status' => 1,
            'currency' => $this->invoiceCurrency($purchaseReturn),
            'branch_id' => $purchaseReturn->branch_id,
            'client_id' => $purchaseReturn->supplier_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::PURCHASE_RETURN,
            'linking_type' => LinkingType::SINGLE,
        ]);

        $subtotal = $purchaseReturn->grand_total - $purchaseReturn->total_tax;

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $supplierAccount->id,
            'description' => $description,
            'debit' => $purchaseReturn->grand_total,
            'credit' => 0,
            'is_debit' => true,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $mainStore->id,
            'description' => $description,
            'debit' => 0,
            'credit' => $subtotal,
            'is_debit' => false,
        ]);

        if ($purchaseReturn->total_tax > 0 && $taxAccount) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $taxAccount->id,
                'description' => $description,
                'debit' => 0,
                'credit' => $purchaseReturn->total_tax,
                'is_debit' => false,
            ]);

            // لا تحديث يدوي للرصيد: JournalEntryDetail::created() يعيد حسابه
            // تلقائياً من القيود الفعلية عند إنشاء كل سطر أعلاه — الزيادة
            // اليدوية تُطبَّق فوق قيمةٍ محدَّثة أصلاً فتُضاعِف الأثر
            Account::updateBalancesHierarchically($taxAccount->id);
        }

        Account::updateBalancesHierarchically($supplierAccount->id);
        Account::updateBalancesHierarchically($mainStore->id);
    }

    /**
     * إذن مخزني (صرف) لإخراج البضاعة المرتجعة — معلَّق حتى يُعتمَد، كما
     * تفعل createWarehousePermit() لفاتورة الشراء العادية. permission_type
     * صريح هنا (2 = صرف) خلافاً لِـ ReturnsInvoiceController المرجعية التي
     * تتركه فارغاً فيسقط اعتمادها صامتاً — نفس العلة التي أُصلحت هذا الجلسة
     * لإذن استلام فاتورة الشراء.
     */
    private function createWarehousePermitForReturn(PurchaseInvoice $purchaseReturn, Request $request): WarehousePermits
    {
        // البضاعة تخرج من المستودع الذي استُلمت فيه — إذن الفاتورة الأصلية
        $originalStoreHouseId = WarehousePermits::where('reference_type', 'purchase_invoice')
            ->where('reference_id', $purchaseReturn->reference_id)
            ->value('store_houses_id');

        $storeHouseId = $originalStoreHouseId ?: $this->resolveStorehouse($request->input('store_house_id'))->id;

        $permitNumber = $this->issueDocumentNumber('warehouse-dispose');

        $warehousePermit = WarehousePermits::create([
            'permission_type' => 2,
            'permission_source_id' => PermissionSources::PURCHASE_RETURN,
            'permission_date' => $purchaseReturn->date,
            'sub_account' => $purchaseReturn->supplier_id,
            'number' => $permitNumber,
            'store_houses_id' => $storeHouseId,
            'from_store_houses_id' => $storeHouseId,
            'to_store_houses_id' => null,
            'grand_total' => $purchaseReturn->grand_total,
            'details' => 'إذن إخراج بضاعة لمرتجع شراء رقم: '.$purchaseReturn->code,
            'attachments' => null,
            'created_by' => Auth::id(),
            'status' => 'pending',
            'reference_type' => 'purchase_return',
            'reference_id' => $purchaseReturn->id,
        ]);

        foreach ($purchaseReturn->items as $item) {
            WarehousePermitsProducts::create([
                'warehouse_permits_id' => $warehousePermit->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
                'store_house_id' => $item->store_house_id ?: $storeHouseId,
            ]);
        }

        return $warehousePermit;
    }

    private function createWarehousePermit(PurchaseInvoice $purchaseInvoice, int $storeHouseId): WarehousePermits
    {
        // نفس تسلسل «إذن إضافة» الذي يستعمله StorePermitsController::store()
        // لهذا النوع من الأذون — لا ترقيم يدوي يتجاوز إعدادات التسلسل
        return WarehousePermits::create([
            'permission_type' => 1, // إضافة — استلام بضاعة لفاتورة شراء
            'permission_source_id' => PermissionSources::PURCHASE_INVOICE,
            'permission_date' => $purchaseInvoice->date,
            'sub_account' => $purchaseInvoice->supplier_id,
            'number' => $this->issueDocumentNumber('warehouse-add'),
            'store_houses_id' => $storeHouseId,
            'from_store_houses_id' => null,
            'to_store_houses_id' => $storeHouseId,
            'grand_total' => $purchaseInvoice->grand_total,
            'details' => 'إذن إدخال بضاعة لفاتورة شراء رقم: '.$purchaseInvoice->code,
            'attachments' => null,
            'created_by' => Auth::id(),
            'status' => 'pending',
            'reference_type' => 'purchase_invoice',
            'reference_id' => $purchaseInvoice->id,
        ]);
    }

    /**
     * بنود الفاتورة وبنود إذنها المخزني وتفاصيل ضرائبها — من الحساب نفسه
     * الذي بُنيت عليه الإجماليات. بند الإذن يحمل **التكلفة الصافية** (بعد
     * الخصمين وبلا ضريبة): هي ما يُقيَّد على المخزون عند اعتماد الإذن.
     */
    private function writeInvoiceLines(PurchaseInvoice $purchaseInvoice, ?WarehousePermits $permit, array $calc): void
    {
        foreach ($calc['tax_rows'] as $detail) {
            $tax = PurchaseTaxCalculator::find($detail['id'] ?? null);

            if (! $tax) {
                continue;
            }

            TaxInvoice::create([
                'name' => $tax->name,
                'invoice_id' => $purchaseInvoice->id,
                'type' => $tax->type,
                'rate' => $detail['rate'],
                'value' => $detail['amount'],
                'type_invoice' => 'purchase',
            ]);
        }

        foreach ($calc['items'] as $key => $item) {
            $item['purchase_invoice_id'] = $purchaseInvoice->id;
            InvoiceItem::create($item);

            if ($permit) {
                WarehousePermitsProducts::create(array_merge([
                    'warehouse_permits_id' => $permit->id,
                    'product_id' => $item['product_id'],
                    'notes' => $item['description'],
                    'store_house_id' => $permit->store_houses_id,
                ], WarehousePermitsProducts::inventoryLineFromInvoiceItem(
                    array_merge($item, ['total' => $calc['line_costs'][$key] ?? $item['total']])
                )));
            }
        }
    }

    /**
     * قيد الشراء وحده (مدين مشتريات+ضريبة / دائن مورّد) — من إجماليات
     * الفاتورة المحفوظة مباشرة. بتاريخ الفاتورة وفرعها وعملتها: كان يُؤرَّخ
     * بـ`now()`، ففاتورةٌ بتاريخ الشهر الماضي يقع قيدها في هذا الشهر
     * ويختلف دفتر الفترة عن مستنداتها.
     */
    private function createPurchaseJournalEntry(PurchaseInvoice $purchaseInvoice): JournalEntry
    {
        $supplierAccount = $this->supplierLedgerAccount($purchaseInvoice->supplier_id);

        $purchasesAccount = Account::find(OnlineStoreSettingsService::storeAccountId('purchases_account_id'))
            ?? $this->requireAccountingAccount('purchases', SystemAccountIds::PURCHASES);

        $totalTax = round((float) $purchaseInvoice->total_tax, 2);
        $grandTotal = round((float) $purchaseInvoice->grand_total, 2);

        $taxAccount = null;
        if ($totalTax > 0) {
            $taxAccount = Account::find(OnlineStoreSettingsService::storeAccountId('vat_paid_account_id'));
            if (! $taxAccount) {
                throw new \RuntimeException('حساب ضريبة القيمة المضافة على المشتريات غير مضبوط — راجع «الحسابات المحاسبية» في الإعدادات.');
            }
        }

        $description = 'فاتورة شراء # '.$purchaseInvoice->code;

        $journalEntry = $this->createSupplierJournalEntry([
            'reference_number' => $purchaseInvoice->code,
            'purchase_invoice_id' => $purchaseInvoice->id,
            'date' => $purchaseInvoice->date,
            'description' => $description,
            'status' => 1,
            'currency' => $this->invoiceCurrency($purchaseInvoice),
            'branch_id' => $purchaseInvoice->branch_id,
            'client_id' => $purchaseInvoice->supplier_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::PURCHASE_INVOICE,
            'linking_type' => LinkingType::SINGLE,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $purchasesAccount->id,
            'description' => $description,
            'debit' => round($grandTotal - $totalTax, 2),
            'credit' => 0,
            'is_debit' => true,
        ]);

        if ($taxAccount) {
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $taxAccount->id,
                'description' => 'ضريبة المدخلات — '.$description,
                'debit' => $totalTax,
                'credit' => 0,
                'is_debit' => true,
            ]);
        }

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $supplierAccount->id,
            'description' => $description,
            'debit' => 0,
            'credit' => $grandTotal,
            'is_debit' => false,
        ]);

        // لا تحديث يدوي للأرصدة — JournalEntryDetail::created() يعيد حسابها
        // من القيود الفعلية؛ هذه النداءات ترفع الأثر إلى الحسابات الآباء
        foreach (array_filter([$purchasesAccount->id, $taxAccount?->id, $supplierAccount->id]) as $accountId) {
            Account::updateBalancesHierarchically($accountId);
        }

        return $journalEntry;
    }

    /**
     * سداد للمورد على فاتورة: سند دفع (`payments_process`) + قيده
     * (مدين المورد / دائن الخزينة). يُستعمل للدفعة عند الإنشاء ولسداد
     * الفاتورة الآجلة لاحقاً من شاشتها — فالقيدان واحدٌ شكلاً ومرجعاً.
     */
    private function recordSupplierPayment(PurchaseInvoice $purchaseInvoice, Account $treasuryAccount, float $amount, $date, $method, string $notes): PaymentsProcess
    {
        $supplierAccount = $this->supplierLedgerAccount($purchaseInvoice->supplier_id);

        $payment = PaymentsProcess::create([
            'purchases_id' => $purchaseInvoice->id,
            'supplier_id' => $purchaseInvoice->supplier_id,
            'branch_id' => $purchaseInvoice->branch_id,
            'amount' => $amount,
            'payment_date' => $date ?: now()->toDateString(),
            'payment_method' => $method ?: 1,
            'type' => 'supplier payments',
            'payment_status' => 1,
            'employee_id' => Auth::id(),
            'treasury_id' => $treasuryAccount->id,
            'notes' => $notes,
        ]);

        $description = 'دفع للمورد # '.$purchaseInvoice->code;

        $journalEntry = $this->createSupplierJournalEntry([
            // رقم سند الدفع من «تسلسل أرقام المستندات» (دفع فاتورة شراء)
            'reference_number' => $this->issueDocumentNumber('purchase-refund'),
            'purchase_invoice_id' => $purchaseInvoice->id,
            'reference_id' => $payment->id,
            'date' => $date ?: now()->toDateString(),
            'description' => $description,
            'status' => 1,
            'currency' => $this->invoiceCurrency($purchaseInvoice),
            'branch_id' => $purchaseInvoice->branch_id,
            'client_id' => $purchaseInvoice->supplier_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
            'linking_type' => LinkingType::SINGLE,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $supplierAccount->id,
            'description' => $description,
            'debit' => $amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $treasuryAccount->id,
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
            'is_debit' => false,
        ]);

        Account::updateBalancesHierarchically($treasuryAccount->id);
        Account::updateBalancesHierarchically($supplierAccount->id);

        return $payment;
    }

    /**
     * قيدٌ طرفه مورد: `journal_entries.client_id` يحمل معرّف المورد في قيود
     * المشتريات (انظر JournalEntry::supplier())، وفي بعض القواعد ما زال على
     * العمود قيدٌ خارجيّ إلى `clients` — فيُعطَّل فحصه للإدراج وحده ويُعاد
     * حتماً (`finally`)، لا أن يبقى معطَّلاً على الاتصال إن سقط الإدراج.
     */
    private function createSupplierJournalEntry(array $data): JournalEntry
    {
        if (empty($data['branch_id'])) {
            unset($data['branch_id']);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            return JournalEntry::create($data);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /** حساب ذمّة المورد (لا محفظته) — يُنشأ تحت حساب الموردين إن غاب */
    private function supplierLedgerAccount($supplierId): Account
    {
        $account = Account::ledger()->where('supplier_id', $supplierId)->first();

        if ($account) {
            return $account;
        }

        $supplier = Supplier::findOrFail($supplierId);
        $parent = Account::find($this->accountingAccountId('suppliers', SystemAccountIds::SUPPLIERS));

        if (! $parent) {
            throw new \RuntimeException('حساب الموردين الرئيسي غير مضبوط — راجع «الحسابات المحاسبية» في الإعدادات.');
        }

        // أوّل كودٍ شاغر تحت كود حساب الموردين
        $sequence = Account::where('parent_id', $parent->id)->count();
        do {
            $sequence++;
            $candidate = $parent->code.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
        } while (Account::where('code', $candidate)->exists());

        return Account::create([
            'name' => $supplier->trade_name ?: ('مورد '.$supplier->id),
            'code' => $candidate,
            'supplier_id' => $supplier->id,
            'parent_id' => $parent->id,
            'account_type' => $parent->account_type,
            'balance_type' => 'credit',
            'balance' => 0,
            'is_active' => 1,
        ]);
    }

    /**
     * حذف قيد الشراء الحالي وإعادة حساب أرصدة حساباته — تمهيداً لإعادة
     * إنشائه بإجماليات جديدة عند تعديل الفاتورة، أو عند حذفها. قيد الشراء
     * وحده: قيود السداد تحمل `purchase_invoice_id` نفسه بنوعٍ آخر.
     */
    private function reversePurchaseJournalEntry(PurchaseInvoice $purchaseInvoice): void
    {
        $entries = JournalEntry::with('details')
            ->where('purchase_invoice_id', $purchaseInvoice->id)
            ->where('reference_type', ReferenceType::PURCHASE_INVOICE)
            ->get();

        foreach ($entries as $journalEntry) {
            $this->deleteJournalEntry($journalEntry);
        }
    }

    private function deleteJournalEntry(JournalEntry $journalEntry): void
    {
        $affectedAccountIds = $journalEntry->details->pluck('account_id')->unique();

        // حذف جماعي عبر Query Builder لا يُطلق JournalEntryDetail::deleted()،
        // فيُعاد حساب الأرصدة صراحةً بعد الحذف الفعلي
        JournalEntryDetail::where('journal_entry_id', $journalEntry->id)->delete();
        $journalEntry->delete();

        foreach ($affectedAccountIds as $accountId) {
            Account::updateBalancesHierarchically($accountId);
        }
    }

    public function edit($id)
    {
        $invoice = $this->findStoreInvoice($id);

        // المرتجع مستندٌ آخر ببنية محاسبية مختلفة — هذا الفورم مبنيٌّ لفاتورة
        // الشراء العادية وحدها، وتعديل مرتجعٍ به يفسد قيوده
        if ($invoice->isPurchaseReturn()) {
            return redirect()->route('store_purchase_invoices.show', $invoice->id)
                ->with('error', 'لا يمكن تعديل مرتجع الشراء من هذه الشاشة.');
        }

        if ($reason = $this->editLockReason($invoice)) {
            return redirect()->route('store_purchase_invoices.show', $invoice->id)->with('error', $reason);
        }

        $invoice->load(['items', 'supplier']);
        $suppliers = $this->supplierOptions($invoice->supplier_id);
        $items = $this->productOptions($invoice->items->pluck('product_id')->all());
        $taxs = TaxSitting::all();
        $storeHouses = $this->storehouseOptions($invoice->warehousePermit?->store_houses_id);
        $periodBlock = AccountingPeriodGuard::blockReason($invoice->date);

        return view('onlinestore::purchases.invoices_purchase.edit', compact('invoice', 'taxs', 'suppliers', 'items', 'storeHouses', 'periodBlock'));
    }

    /**
     * تعديل الفاتورة يعني تعديل قيدها المحاسبي والإذن المخزني المرتبط به
     * معاً — لا الفاتورة وحدها. وبمجرد اعتماد ذلك الإذن (البضاعة استُلمت
     * فعلاً والقيد استقرّ) يُمنع التعديل نهائياً بدل أن يُترك القيد
     * والمخزون على حالٍ لا يطابق الفاتورة المعدَّلة.
     *
     * المدفوع الفعلي (سندات الدفع) لا يُعاد حسابه هنا — تعديل بنود الفاتورة
     * لا يغيّر ما دُفع بالفعل؛ المُعاد إنشاؤه هنا هو قيد الشراء وحده. ورقم
     * الفاتورة وعملتها وفرعها لا تُمسّ.
     */
    public function update(Request $request, $id)
    {
        $purchaseInvoice = $this->findStoreInvoice($id);

        if ($purchaseInvoice->isPurchaseReturn()) {
            return redirect()->route('store_purchase_invoices.show', $purchaseInvoice->id)
                ->with('error', 'لا يمكن تعديل مرتجع الشراء من هذه الشاشة.');
        }

        if ($reason = $this->editLockReason($purchaseInvoice)) {
            return redirect()->route('store_purchase_invoices.show', $purchaseInvoice->id)->with('error', $reason);
        }

        if (is_array($request->input('items'))) {
            PurchaseTaxCalculator::applyComprehensive($request);
        }
        $this->validateInvoiceRequest($request);
        $this->assertSupplierAllowed($request->supplier_id, $purchaseInvoice->supplier_id);

        // الفترة: تاريخ الفاتورة الحالي (قيدها يُحذف) والتاريخ الجديد (قيدها يُكتب)
        foreach ([$purchaseInvoice->date, $request->date] as $date) {
            if ($blocked = AccountingPeriodGuard::blockReason($date)) {
                throw ValidationException::withMessages(['date' => $blocked]);
            }
        }

        $permit = $purchaseInvoice->warehousePermit;
        // مستودعُ الفاتورة الحاليّ يمرّ ولو خرج من النطاق بعد إنشائها (لا يُفرض تبديلُه)
        $storeHouse = $this->resolveStorehouse($request->input('store_house_id') ?: $permit?->store_houses_id, $permit?->store_houses_id);

        try {
            DB::beginTransaction();

            $calc = $this->computeDocument($request, $storeHouse->id);
            $total_with_tax = $calc['grand_total'];

            // المدفوع فعلياً لا يتغيّر بتعديل البنود — يبقى كما سجّلته سندات الدفع
            $paid_amount = round((float) $purchaseInvoice->payments()->sum('amount'), 2);

            if ($paid_amount > $total_with_tax + 0.005) {
                throw ValidationException::withMessages(['items' => 'إجمالي الفاتورة بعد التعديل ('.CurrencySettings::number($total_with_tax).') أقل من المدفوع عليها فعلاً ('.CurrencySettings::number($paid_amount).').']);
            }

            $payment_status = $paid_amount <= 0
                ? 'unpaid'
                : ($paid_amount >= $total_with_tax ? 'paid' : 'partially_paid');

            // حالة الاستلام كما في الإنشاء: تأشير المستخدم أو خيار «مستلمة افتراضياً»
            $receiving_status = (PurchaseSettings::enabled('default_received_invoices') || $request->input('is_received') == '1') ? 'received' : 'not_received';

            // حذف القيد المحاسبي القديم قبل أي تعديل
            $this->reversePurchaseJournalEntry($purchaseInvoice);

            $purchaseInvoice->update([
                'supplier_id' => $request->supplier_id,
                'date' => $request->date,
                'terms' => (int) ($request->terms ?? 0),
                'notes' => $request->has('notes') ? $request->notes : $purchaseInvoice->notes,
                'discount_amount' => $calc['invoice_discount'],
                // الخصم العام يُحفظ مبلغاً محسوباً — فنوعه «مبلغ» دائماً
                'discount_type' => 1,
                'shipping_cost' => $calc['shipping_cost'],
                'shipping_tax' => $calc['shipping_tax'],
                'reference_number' => $request->reference_number ?? $purchaseInvoice->reference_number,
                'subtotal' => $calc['subtotal'],
                'total_discount' => $calc['total_discount'],
                'total_tax' => round($calc['item_tax'] + $calc['shipping_tax'], 2),
                'grand_total' => $total_with_tax,
                'due_value' => round($total_with_tax - $paid_amount, 2),
                'advance_payment' => $paid_amount,
                'payment_status' => $payment_status,
                'is_paid' => $payment_status === 'paid',
                'receiving_status' => $receiving_status,
                'is_received' => $receiving_status === 'received',
                'received_date' => $receiving_status === 'received' ? ($request->input('received_date') ?: ($purchaseInvoice->received_date ?: $request->date)) : null,
                'updated_by' => Auth::id(),
            ]);

            // استبدال بنود الفاتورة وضرائبها وبنود إذنها المعلَّق
            $purchaseInvoice->items()->delete();
            TaxInvoice::where('invoice_id', $purchaseInvoice->id)->where('type_invoice', 'purchase')->delete();

            if ($permit) {
                $permit->warehousePermitsProducts()->delete();
                $permit->update([
                    'grand_total' => $total_with_tax,
                    'permission_date' => $request->date,
                    'sub_account' => $request->supplier_id,
                    'store_houses_id' => $storeHouse->id,
                    'to_store_houses_id' => $storeHouse->id,
                ]);
            }

            $this->writeInvoiceLines($purchaseInvoice, $permit, $calc);

            // إعادة إنشاء قيد الشراء بالإجماليات الجديدة
            $this->createPurchaseJournalEntry($purchaseInvoice->fresh());

            OperationLog::record(
                'updated',
                'purchase_invoice',
                $purchaseInvoice,
                sprintf('تم تعديل فاتورة الشراء رقم **%s**', $purchaseInvoice->code)
            );

            DB::commit();

            return redirect()->route('store_purchase_invoices.show', $purchaseInvoice->id)
                ->with('success', 'تم تعديل فاتورة الشراء بنجاح');
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Throwable $e) {
            // ⚠️ rollBack() قبل failure() لا بعده — انظر التعليق في store() أعلاه
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StorePurchaseInvoice', 'update', $id);

            return redirect()->back()->withInput()
                ->with('error', 'تعذّر تعديل الفاتورة. '.$this->readableError($e));
        }
    }

    public function show($id)
    {
        $purchaseInvoice = $this->findStoreInvoice($id);
        $purchaseInvoice->load(['supplier', 'items.product', 'payments', 'user', 'branch']);
        $TaxsInvoice = TaxInvoice::where('invoice_id', $purchaseInvoice->id)->where('type_invoice', 'purchase')->get();

        $logs = ModelsLog::where('type', 'purchase_invoice')
            ->where('type_id', $purchaseInvoice->id)
            ->whereHas('purchase_invoice')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        // فاتورة عادية: هل لها مرتجع بالفعل؟ — مرتجع: ما فاتورته الأصلية؟
        $existingReturn = null;
        $originalInvoice = null;
        $returnPermit = null;

        if ($purchaseInvoice->isPurchaseReturn()) {
            $originalInvoice = PurchaseInvoice::find($purchaseInvoice->reference_id);
            $returnPermit = $purchaseInvoice->warehousePermit;
        } else {
            $existingReturn = PurchaseInvoice::where('type', PurchaseDocumentType::RETURN->value)
                ->where('reference_id', $purchaseInvoice->id)
                ->first();
        }

        $documentType = $this->purchaseDocumentType($purchaseInvoice);

        // زر «تعديل» يُخفى لا يُترك ليُرفض بعد فتح الفورم — نفس حارس edit()
        $invoicePermit = $purchaseInvoice->warehousePermit;
        $canEdit = ! $purchaseInvoice->isPurchaseReturn() && $this->editLockReason($purchaseInvoice) === null;
        $canDelete = $this->deleteLockReason($purchaseInvoice) === null;

        // السداد اللاحق لفاتورة آجلة/جزئية — من خزائن المتجر التي يجوز السحب منها
        $remainingAmount = $purchaseInvoice->isPurchaseReturn() ? 0.0 : max(0.0, round((float) $purchaseInvoice->grand_total - (float) $purchaseInvoice->payments->sum('amount'), 2));
        $canPay = $remainingAmount > 0 && ! $existingReturn;
        $treasuries = $canPay ? $this->payableTreasuries() : collect();
        $defaultTreasuryId = $canPay ? $this->defaultPayableTreasury()?->id : null;
        $currencyCode = $this->invoiceCurrency($purchaseInvoice);
        $journalEntries = JournalEntry::with('details.account')
            ->where('purchase_invoice_id', $purchaseInvoice->id)
            ->orderBy('id')
            ->get();

        return view('onlinestore::purchases.invoices_purchase.show', array_merge(
            compact('purchaseInvoice', 'logs', 'TaxsInvoice', 'existingReturn', 'originalInvoice', 'returnPermit', 'invoicePermit', 'canEdit', 'canDelete', 'canPay', 'remainingAmount', 'treasuries', 'defaultTreasuryId', 'currencyCode', 'journalEntries'),
            [
                'documentTemplates' => PrintSettings::templatesFor($documentType),
                'defaultTemplate' => PrintSettings::defaultTemplateFor($documentType),
                'prefersPdf' => PrintSettings::prefersPdf(),
            ]
        ));
    }

    /**
     * سداد فاتورة آجلة أو متبقّي فاتورة جزئية — من شاشة الفاتورة.
     *
     * لم يكن في المتجر مسارٌ لذلك: زرّ «إضافة دفعة» كان يشير إلى مسارٍ غير
     * معرَّف (`store_payment_supplier.createPurchase`) فتسقط به صفحة الفاتورة
     * الآجلة كلّها.
     */
    public function pay(Request $request, $id)
    {
        $purchaseInvoice = $this->findStoreInvoice($id);

        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:999999999999',
            'payment_date' => 'required|date',
            'treasury_id' => 'required|integer',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ], [], $this->attributeNames());

        if ($purchaseInvoice->isPurchaseReturn()) {
            return back()->with('error', 'مرتجع الشراء لا يُسدَّد.');
        }

        if (PurchaseInvoice::where('type', PurchaseDocumentType::RETURN->value)->where('reference_id', $purchaseInvoice->id)->exists()) {
            return back()->with('error', 'لا يمكن سداد فاتورةٍ صدر لها مرتجع.');
        }

        if ($blocked = AccountingPeriodGuard::blockReason($request->payment_date)) {
            throw ValidationException::withMessages(['payment_date' => $blocked]);
        }

        try {
            DB::beginTransaction();

            // قفل صفّ الفاتورة: دفعتان متزامنتان لا تتجاوزان المتبقّي معاً
            $purchaseInvoice = PurchaseInvoice::whereKey($purchaseInvoice->id)->lockForUpdate()->first();

            $paid = round((float) $purchaseInvoice->payments()->sum('amount'), 2);
            $remaining = round((float) $purchaseInvoice->grand_total - $paid, 2);
            $amount = round((float) $request->amount, 2);

            if ($remaining <= 0) {
                throw ValidationException::withMessages(['amount' => 'الفاتورة مسدَّدة بالكامل.']);
            }

            if ($amount > $remaining + 0.005) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من المتبقّي على الفاتورة ('.CurrencySettings::number($remaining).' '.$this->invoiceCurrency($purchaseInvoice).').']);
            }

            $treasuryAccount = $this->resolvePaymentTreasury($request->treasury_id, $amount, 'treasury_id');

            $this->recordSupplierPayment(
                $purchaseInvoice,
                $treasuryAccount,
                $amount,
                $request->payment_date,
                $request->payment_method,
                trim('سداد لفاتورة المشتريات رقم '.$purchaseInvoice->code.($request->filled('notes') ? ' — '.$request->notes : ''))
            );

            $newPaid = round($paid + $amount, 2);
            $purchaseInvoice->update([
                'advance_payment' => $newPaid,
                'due_value' => round((float) $purchaseInvoice->grand_total - $newPaid, 2),
                'payment_status' => $newPaid >= (float) $purchaseInvoice->grand_total - 0.005 ? 'paid' : 'partially_paid',
                'is_paid' => $newPaid >= (float) $purchaseInvoice->grand_total - 0.005,
                'account_id' => $purchaseInvoice->account_id ?: $treasuryAccount->id,
            ]);

            OperationLog::record(
                'updated',
                'purchase_invoice',
                $purchaseInvoice,
                sprintf('تم سداد **%s** من خزينة **%s** لفاتورة الشراء رقم **%s**', CurrencySettings::number($amount).' '.$this->invoiceCurrency($purchaseInvoice), $treasuryAccount->name, $purchaseInvoice->code)
            );

            DB::commit();

            return redirect()->route('store_purchase_invoices.show', $purchaseInvoice->id)
                ->with('success', 'تم تسجيل الدفعة وقيدها المحاسبي بنجاح.');
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StorePurchaseInvoice', 'pay', $id);

            return back()->withInput()->with('error', 'تعذّر تسجيل الدفعة. '.$this->readableError($e));
        }
    }

    /** نوع مستند الفاتورة في «قوالب المستندات» — فاتورة أو مرتجع */
    private function purchaseDocumentType(PurchaseInvoice $purchaseInvoice): string
    {
        return $purchaseInvoice->isPurchaseReturn() ? 'purchase_return' : self::DOCUMENT_TYPE;
    }

    /**
     * حذف فاتورة شراء — مع كلّ ما كتبته: قيد الشراء، وسندات الدفع وقيودها،
     * والإذن المخزني المعلَّق. كان يحذف الفاتورة وبنودها ويترك قيودها
     * وسنداتها وإذنها يتامى: ذمّة المورد ورصيد الخزينة يبقيان متأثّرين
     * بفاتورةٍ لم تعد موجودة.
     */
    public function destroy($id)
    {
        $purchaseInvoice = $this->findStoreInvoice($id);

        if ($reason = $this->deleteLockReason($purchaseInvoice)) {
            return redirect()->back()->with('error', $reason);
        }

        try {
            DB::beginTransaction();

            $invoiceCode = $purchaseInvoice->code;

            // قيود الفاتورة كلّها (الشراء والسداد/المرتجع) — تُحذف ويُعاد حساب الأرصدة
            foreach (JournalEntry::with('details')->where('purchase_invoice_id', $purchaseInvoice->id)->get() as $journalEntry) {
                $this->deleteJournalEntry($journalEntry);
            }

            foreach ($purchaseInvoice->payments()->get() as $payment) {
                $payment->delete();
            }

            if ($permit = $purchaseInvoice->warehousePermit) {
                $permit->warehousePermitsProducts()->delete();
                $permit->delete();
            }

            TaxInvoice::where('invoice_id', $purchaseInvoice->id)->where('type_invoice', 'purchase')->delete();
            $purchaseInvoice->items()->delete();

            $notes = ClientRelation::where('quotation_id', $purchaseInvoice->id)
                ->where('type', 'purchase_invoice')
                ->get();

            foreach ($notes as $note) {
                if ($note->attachment && Storage::disk('public')->exists($note->attachment)) {
                    Storage::disk('public')->delete($note->attachment);
                }
                $note->delete();
            }

            $purchaseInvoice->delete();

            OperationLog::record(
                'deleted',
                'purchase_invoice',
                $id,
                sprintf('تم حذف فاتورة الشراء رقم **%s** وقيودها وإذنها المخزني', $invoiceCode)
            );

            DB::commit();

            return redirect()->route('store_purchase_invoices.index')->with('success', 'تم حذف الفاتورة وقيودها بنجاح');
        } catch (ValidationException $e) {
            DB::rollBack();

            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            DB::rollBack();
            OperationLog::failure($e, 'OnlineStore.StorePurchaseInvoice', 'destroy');

            return redirect()->back()->with('error', 'تعذّر حذف الفاتورة. '.$this->readableError($e));
        }
    }

    /** ملاحظات الفاتورة PDF — المسار كان معرَّفاً والدالّة غائبة (500) */
    public function notesPdf($id)
    {
        $purchaseInvoice = $this->findStoreInvoice($id);
        $purchaseInvoice->load('supplier');

        try {
            $notes = ClientRelation::where('quotation_id', $purchaseInvoice->id)
                ->where('type', 'purchase_invoice')
                ->with('employee')
                ->orderBy('created_at', 'desc')
                ->get();

            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle('ملاحظات فاتورة الشراء - '.$purchaseInvoice->code);
            $pdf->SetMargins(15, 15, 15);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->setRTL(true);
            $pdf->AddPage();
            $pdf->SetFont('aealarabiya', '', 12);
            $pdf->writeHTML(view('onlinestore::purchases.invoices_purchase.partials.notes_pdf', compact('purchaseInvoice', 'notes'))->render(), true, false, true, false, '');

            return $pdf->Output('ملاحظات_فاتورة_شراء_'.$purchaseInvoice->code.'.pdf', 'I');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'OnlineStore.StorePurchaseInvoice', 'notesPdf');

            return back()->with('error', 'تعذّر توليد ملف الملاحظات.');
        }
    }

    // ==================== النطاق وقوائم الاختيار ====================

    /**
     * فواتير ومرتجعات شراء المتجر التي يراها المستخدم الحالي.
     *
     * `purchase_invoices` جدولٌ مشترك مع Modules\Purchases — الإذن المخزني
     * على مستودع متجرٍ هو الرابط الموثوق (يُنشأ مع كل فاتورة/مرتجع من هذا
     * المتحكّم). ثم الفرع: العقدة المختارة في الشريط وما تحتها (الرئيسي
     * «الكل» لمن لا قيد عليه وحده)، ونطاق بيانات المستخدم — فرعاً/منطقةً
     * أو مستنداته ومستندات فريقه.
     */
    private function storeInvoiceQuery()
    {
        $query = PurchaseInvoice::query()
            ->whereIn('type', ['invoice', 'Return', PurchaseDocumentType::RETURN->value])
            // إذنُ الفاتورة على مستودعٍ من مستودعات المتجر **التي يراها المستخدم** (المسنَدة له ولتابعيه،
            // ومن يرى الكل يرى الجميع) — لا كلُّ مستودعات المتجر
            ->whereHas('warehousePermit', function ($q) {
                $q->whereIn('store_houses_id', $this->storeWarehouseScopeIds() ?: [0]);
            });

        // فرعُ الشريط وما تحته (الرئيسيُّ «الكل» لمن يرى الكل). والنطاقُ هو إسنادُ المستودع أعلاه:
        // كان يُضاف قيدُ فرع المستخدم ومنشئ الفاتورة، فلا يرى من عُهد إليه مستودعٌ فواتيرَ زميله عليه
        if ($node = StoreAssignmentScope::headerNode()) {
            $query->forBranch($node);
        }

        return $query;
    }

    /** فاتورة متجرٍ داخل النطاق — وإلا 404 */
    private function findStoreInvoice($id): PurchaseInvoice
    {
        return $this->storeInvoiceQuery()->findOrFail((int) $id);
    }

    /**
     * الموردون الذين يراهم المستخدم: المُسنَدون إليه وإلى تابعيه (ومن يرى الكل يرى الجميع)
     * ضمن فرع الشريط وما تحته — والمورد المُمرَّر يبقى ولو كان خارجه (مورد الفاتورة المفتوحة).
     */
    private function supplierOptions($keepId = null)
    {
        $query = Supplier::query()->orderBy('trade_name');
        $node = StoreAssignmentScope::headerNode();

        $query->where(function ($q) use ($node, $keepId) {
            $q->where(function ($scoped) use ($node) {
                if ($node) {
                    $scoped->forBranch($node);
                }

                StoreAssignmentScope::constrainSuppliers($scoped);
            });

            if ($keepId) {
                $q->orWhere('id', $keepId);
            }
        });

        return $query->get();
    }

    /** هل المورد ضمن نطاق إسناد المستخدم (بصرف النظر عن فرع الشريط)؟ */
    private function supplierInAssignmentScope(int $supplierId): bool
    {
        return StoreAssignmentScope::constrainSuppliers(Supplier::query())->whereKey($supplierId)->exists();
    }

    /**
     * المنتقي وعدٌ لا حارس: المعرّف يُبدَّل في المتصفّح. مورّدٌ خارج ما يراه المستخدم
     * (غير مُسنَد إليه ولا إلى تابعيه، أو خارج فرع الشريط) خطأ تحقّق لا قبولٌ صامت —
     * ومورد الفاتورة الحاليّ يمرّ عند تعديلها.
     */
    private function assertSupplierAllowed($supplierId, ?int $currentSupplierId = null): void
    {
        if (! is_numeric($supplierId)) {
            return;
        }

        $supplierId = (int) $supplierId;

        if ($currentSupplierId && $supplierId === (int) $currentSupplierId) {
            return;
        }

        if (! $this->supplierOptions()->contains('id', $supplierId)) {
            throw ValidationException::withMessages(['supplier_id' => 'المورد المحدد ليس من الموردين المتاحين لك (المسنَدين إليك أو إلى فريقك في هذا الفرع).']);
        }
    }

    /** الأصناف المخزنية — فاتورة شراء المتجر تُدخل بضاعةً إلى مستودعه */
    private function productOptions(array $keepIds = [])
    {
        return Product::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product) => in_array($product->id, $keepIds) || StockEligibility::isStockable($product))
            ->values();
    }

    /**
     * مستودعات المتجر النشطة التي يراها المستخدم: المسنَدة له ولتابعيه (ومن يرى الكل يرى
     * الجميع) ∩ فرع الشريط — {@see ScopesStoreWarehouses::storeWarehouseIds()}. ومستودع
     * الفاتورة المفتوحة (`$keepId`) يبقى ولو خرج من النطاق.
     */
    private function storehouseOptions($keepId = null)
    {
        $ids = $this->storeWarehouseIds();

        return StoreHouse::onlineStore()
            ->where(function ($q) use ($ids, $keepId) {
                $q->where(function ($scoped) use ($ids) {
                    $scoped->where('status', 'active')->whereIn('id', $ids ?: [0]);
                });

                if ($keepId) {
                    $q->orWhere('id', $keepId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    /** المستودع الذي تُستلم فيه الفاتورة — من القائمة المسموحة، وإلا افتراضي المتجر */
    private function resolveStorehouse($requestedId, $keepId = null): StoreHouse
    {
        $options = $this->storehouseOptions($keepId);

        if ($requestedId) {
            $storeHouse = $options->firstWhere('id', (int) $requestedId);

            if (! $storeHouse) {
                throw ValidationException::withMessages(['store_house_id' => 'المستودع المختار ليس من مستودعات المتجر المتاحة لك.']);
            }

            return $storeHouse;
        }

        $default = $this->getDefaultStorehouse();
        $storeHouse = $default ? $options->firstWhere('id', $default->id) : null;
        $storeHouse ??= $options->count() === 1 ? $options->first() : null;

        if (! $storeHouse) {
            throw ValidationException::withMessages(['store_house_id' => 'اختر المستودع الذي تُستلم فيه البضاعة — لا مستودع افتراضي للمتجر داخل الفرع الحالي.']);
        }

        return $storeHouse;
    }

    /**
     * خزائن وبنوك المتجر النشطة داخل نطاق المستخدم التي يجوز له **السحب**
     * منها (`TreasuryAccessGuard`) — قائمة الاختيار وفحص الحفظ يسألان هذا
     * المصدر نفسه، فلا يُعرض خيارٌ يُرفض ولا يُقبل حسابٌ لم يُعرض.
     */
    private function payableTreasuries()
    {
        $query = Account::query()
            ->onlineStore()
            ->where('is_active', 1)
            ->whereIn('parent_id', array_filter([
                AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT),
                AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT),
            ]));

        $node = HeaderBranchScope::nodeId();

        foreach ([$node ? $this->branchSubtree((int) $node) : null, DataScope::branchIds()] as $ids) {
            if ($ids !== null) {
                $query->where(function ($q) use ($ids) {
                    $q->whereIn('branch_id', $ids)->orWhereNull('branch_id');
                });
            }
        }

        $accounts = $query->orderBy('name')->get()
            ->filter(fn (Account $account) => TreasuryAccessGuard::withdrawBlockReason($account->id) === null)
            ->values();

        $currencies = Treasury::whereIn('account_id', $accounts->pluck('id'))->pluck('currency', 'account_id');
        $accounts->each(fn (Account $account) => $account->setAttribute('currency_code', ($currencies[$account->id] ?? null) ?: CurrencySettings::code()));

        return $accounts;
    }

    /** الخزينة المقترحة للدفع: افتراضية المتجر (إعداداته) إن جاز السحب منها */
    private function defaultPayableTreasury(): ?Account
    {
        $options = $this->payableTreasuries();
        $default = $this->getDefaultTreasuryAccount();

        return ($default ? $options->firstWhere('id', $default->id) : null)
            ?? ($options->count() === 1 ? $options->first() : null);
    }

    /**
     * خزينة الدفع: المختارة إن كانت من خزائن المتجر المسموح السحب منها،
     * وإلا افتراضية المتجر — ولا «أيّ حساب». ويُفحص رصيدها للمبلغ المدفوع.
     */
    private function resolvePaymentTreasury($requestedId, float $amount, string $field = 'advance_payment_treasury_id'): Account
    {
        $options = $this->payableTreasuries();

        if ($requestedId) {
            $account = $options->firstWhere('id', (int) $requestedId);

            if (! $account) {
                $denied = is_numeric($requestedId) ? TreasuryAccessGuard::withdrawBlockReason((int) $requestedId) : null;

                throw ValidationException::withMessages([$field => $denied ?: 'الخزينة المختارة ليست من خزائن المتجر المتاحة لك للسحب.']);
            }
        } else {
            $account = $this->defaultPayableTreasury();

            if (! $account) {
                throw ValidationException::withMessages([$field => 'اختر الخزينة أو الحساب البنكي الذي يُدفع منه — لا خزينة افتراضية للمتجر. (الفاتورة الآجلة لا تحتاج خزينة: اترك الدفعة فارغة.)']);
            }
        }

        if ((float) $account->balance < $amount) {
            throw ValidationException::withMessages([$field => 'رصيد «'.$account->name.'» غير كافٍ. الرصيد الحالي: '.CurrencySettings::number($account->balance).' والمطلوب: '.CurrencySettings::number($amount).'.']);
        }

        return $account;
    }

    /** @return array<int, int> العقدة وكلُّ ما تحتها */
    private function branchSubtree(int $branchId): array
    {
        $branch = Branch::findCached($branchId);

        if (! $branch) {
            return [$branchId];
        }

        return $branch->descendantBranchIds()->push($branchId)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    // ==================== الحساب والتحقّق ====================

    /**
     * بنود المستند وإجمالياته — بالحسّاب المشترك مع وحدة المشتريات
     * (`PurchaseTaxCalculator` + `PurchaseDocumentTotals`) فلا يختلف ما
     * يُحفظ عمّا يعرضه النموذج: الخصم العام على الصافي، والضريبة على
     * الوعاء بعد الخصمين، والمتضمَّنة تُستخرج من السعر ولا تُضاف فوقه.
     */
    private function computeDocument(Request $request, int $storeHouseId): array
    {
        $settlementEnabled = PurchaseSettings::enabled('enable_settlement');

        $products = Product::whereIn('id', collect($request->items)->pluck('product_id')->filter()->unique())->get()->keyBy('id');

        $items = [];
        $lineNets = [];
        $lineTaxes = [];
        $documentTaxIds = [];
        $gross = 0.0;
        $lineDiscounts = 0.0;

        foreach ($request->items as $key => $item) {
            $product = $products->get($item['product_id']);

            if (! StockEligibility::isStockable($product)) {
                throw ValidationException::withMessages(['items' => 'الصنف «'.$product->name.'» خدمي/رقمي لا يدخل مخزوناً — فواتير شراء المتجر للأصناف المخزنية.']);
            }

            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineTotal = $quantity * $unitPrice;

            $discount = (float) ($item['discount'] ?? 0);
            if ($discount > 0 && ($item['discount_type'] ?? null) === 'percentage') {
                $discount = ($lineTotal * min($discount, 100)) / 100;
            }
            $discount = min(max($discount, 0), $lineTotal);

            $tax1Id = ! empty($item['tax_1_id']) ? $item['tax_1_id'] : null;
            $tax2Id = ! empty($item['tax_2_id']) ? $item['tax_2_id'] : null;
            $tax1Rate = $tax1Id ? PurchaseTaxCalculator::rate($tax1Id) : (float) ($item['tax_1'] ?? 0);
            $tax2Rate = $tax2Id ? PurchaseTaxCalculator::rate($tax2Id) : (float) ($item['tax_2'] ?? 0);

            $line = PurchaseTaxCalculator::line($quantity, $unitPrice, $discount, [$tax1Id, $tax2Id], [$tax1Rate, $tax2Rate]);

            $lineNets[$key] = $line['base'];
            $lineTaxes[$key] = ['ids' => [$tax1Id, $tax2Id], 'rates' => [$tax1Rate, $tax2Rate]];
            $documentTaxIds[] = $tax1Id;
            $documentTaxIds[] = $tax2Id;

            $gross += $lineTotal;
            $lineDiscounts += $discount;

            // الوحدة: معامل التحويل من وحدة المنتج إن اختيرت، وإلا الوحدة الأساسية
            $unitId = isset($item['product_unit_id']) && is_numeric($item['product_unit_id']) ? (int) $item['product_unit_id'] : null;
            $factor = 1.0;
            $unitName = null;
            if ($unitId && ($unit = ProductUnit::with('categoryUnit.unit')->where('product_id', $product->id)->find($unitId))) {
                $factor = (float) ($unit->categoryUnit->conversion_factor ?? 1) ?: 1.0;
                $unitName = $unit->categoryUnit->unit->name ?? null;
            } else {
                $unitId = null;
            }

            $items[$key] = [
                'purchase_invoice_id' => null,
                'product_id' => $product->id,
                'item' => $product->name,
                'description' => isset($item['description']) && is_string($item['description']) ? $item['description'] : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => round($discount, 2),
                'discount_type' => ($item['discount_type'] ?? null) === 'percentage' ? 2 : 1,
                'tax_1' => $tax1Rate,
                'tax_1_id' => $tax1Id,
                'tax_2' => $tax2Rate,
                'tax_2_id' => $tax2Id,
                // صافي البند بلا ضريبة — كما في كل موضعٍ يقرأ invoice_items.total
                'total' => $line['net'],
                'store_house_id' => $storeHouseId,
                'product_unit_id' => $unitId,
                'conversion_factor' => $factor,
                'quantity_in_base_unit' => $quantity * $factor,
                'unit_name' => $unitName,
            ];
        }

        $totals = PurchaseDocumentTotals::compute($request, $lineNets, $lineTaxes, $settlementEnabled, $documentTaxIds);

        // الأسعار المُدخلة شاملةً ضريبةً متضمَّنة: تُطرح من المجموع فلا تُحسب مرّتين
        $subtotal = round($gross - $totals['included_item_tax'], 2);
        $totalDiscount = round($lineDiscounts + $totals['invoice_discount'], 2);
        $grandTotal = round($subtotal - $totalDiscount + $totals['item_tax'] + $totals['shipping_cost'] + $totals['shipping_tax'], 2);

        if ($grandTotal <= 0) {
            throw ValidationException::withMessages(['items' => 'إجمالي الفاتورة يجب أن يكون أكبر من صفر.']);
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'total_discount' => $totalDiscount,
            'invoice_discount' => $totals['invoice_discount'],
            'item_tax' => $totals['item_tax'],
            'shipping_cost' => round((float) $totals['shipping_cost'], 2),
            'shipping_tax' => round((float) $totals['shipping_tax'], 2),
            'tax_rows' => $totals['tax_rows'],
            'line_costs' => $totals['line_costs'],
            'grand_total' => $grandTotal,
        ];
    }

    /** تحقّقٌ واحد للإنشاء والتعديل — برسائل عربية وأسماء حقول عربية */
    private function validateInvoiceRequest(Request $request): void
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'date' => 'required|date',
            'terms' => 'nullable|integer|min:0|max:3650',
            'notes' => 'nullable|string|max:5000',
            'store_house_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'items' => 'required|array|min:1|max:500',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|gt:0|max:99999999',
            'items.*.unit_price' => 'required|numeric|min:0|max:999999999',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|string|in:amount,percentage',
            'items.*.tax_1' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_2' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_1_id' => 'nullable|integer|exists:tax_sittings,id',
            'items.*.tax_2_id' => 'nullable|integer|exists:tax_sittings,id',
            'items.*.description' => 'nullable|string|max:1000',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string|in:amount,percentage',
            'shipping_cost' => 'nullable|numeric|min:0|max:999999999',
            'tax_id' => 'nullable|integer|exists:tax_sittings,id',
            'advance_payment' => 'nullable|numeric|min:0|max:999999999999',
            'advance_payment_type' => 'nullable|string|in:amount,percentage',
            'paid_amount' => 'nullable|numeric|min:0|max:999999999999',
            'advance_payment_treasury_id' => 'nullable|integer',
            'treasury_id' => 'nullable|integer',
            'payment_method' => 'nullable|string|max:50',
            'advance_payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'advance_reference_number' => 'nullable|string|max:100',
            'received_date' => 'nullable|date',
            'attachments' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx,zip|max:5120',
        ], [
            'supplier_id.required' => 'يجب تحديد المورد.',
            'supplier_id.exists' => 'المورد المحدد غير موجود.',
            'items.required' => 'أضف بنداً واحداً على الأقل إلى الفاتورة.',
            'items.min' => 'أضف بنداً واحداً على الأقل إلى الفاتورة.',
            'items.*.product_id.required' => 'اختر الصنف في كل بند.',
            'items.*.product_id.exists' => 'أحد الأصناف المختارة غير موجود.',
            'items.*.quantity.gt' => 'كمية كل بند يجب أن تكون أكبر من صفر.',
        ], $this->attributeNames());
    }

    private function attributeNames(): array
    {
        return [
            'supplier_id' => 'المورد',
            'date' => 'تاريخ الفاتورة',
            'terms' => 'مهلة السداد',
            'notes' => 'الملاحظات',
            'store_house_id' => 'المستودع',
            'branch_id' => 'الفرع',
            'items' => 'البنود',
            'items.*.product_id' => 'الصنف',
            'items.*.quantity' => 'الكمية',
            'items.*.unit_price' => 'سعر الوحدة',
            'items.*.discount' => 'خصم البند',
            'items.*.discount_type' => 'نوع خصم البند',
            'items.*.tax_1' => 'نسبة الضريبة',
            'items.*.tax_2' => 'نسبة الضريبة الثانية',
            'items.*.tax_1_id' => 'الضريبة',
            'items.*.tax_2_id' => 'الضريبة الثانية',
            'items.*.description' => 'وصف البند',
            'discount_amount' => 'الخصم العام',
            'discount_type' => 'نوع الخصم',
            'shipping_cost' => 'تكلفة الشحن',
            'tax_id' => 'ضريبة الشحن',
            'advance_payment' => 'الدفعة المقدمة',
            'advance_payment_type' => 'نوع الدفعة المقدمة',
            'paid_amount' => 'المبلغ المدفوع',
            'advance_payment_treasury_id' => 'خزينة الدفع',
            'treasury_id' => 'الخزينة',
            'payment_method' => 'طريقة الدفع',
            'advance_payment_method' => 'طريقة الدفع',
            'reference_number' => 'الرقم المرجعي',
            'advance_reference_number' => 'الرقم المرجعي',
            'received_date' => 'تاريخ الاستلام',
            'attachments' => 'المرفق',
            'amount' => 'المبلغ',
            'payment_date' => 'تاريخ الدفع',
            'return_from' => 'الفاتورة الأصلية',
        ];
    }

    /** سبب منع تعديل الفاتورة — أو null */
    private function editLockReason(PurchaseInvoice $invoice): ?string
    {
        $permit = $invoice->warehousePermit;

        if ($permit && $permit->status === 'apporod') {
            return 'لا يمكن تعديل الفاتورة بعد اعتماد الإذن المخزني رقم '.$permit->number.' المرتبط بها — البضاعة استُلمت فعلاً.';
        }

        if (PurchaseInvoice::where('type', PurchaseDocumentType::RETURN->value)->where('reference_id', $invoice->id)->exists()) {
            return 'لا يمكن تعديل فاتورةٍ صدر لها مرتجع.';
        }

        return null;
    }

    /** سبب منع حذف الفاتورة/المرتجع — أو null */
    private function deleteLockReason(PurchaseInvoice $invoice): ?string
    {
        $permit = $invoice->warehousePermit;

        if ($permit && $permit->status === 'apporod') {
            return 'لا يمكن حذف المستند بعد اعتماد إذنه المخزني رقم '.$permit->number.' — البضاعة تحرّكت فعلاً في المخزون. '.($invoice->isPurchaseReturn() ? '' : 'أنشئ مرتجع شراء بدل الحذف.');
        }

        if (! $invoice->isPurchaseReturn()
            && PurchaseInvoice::where('type', PurchaseDocumentType::RETURN->value)->where('reference_id', $invoice->id)->exists()) {
            return 'لا يمكن حذف فاتورةٍ صدر لها مرتجع — احذف المرتجع أولاً.';
        }

        if ($blocked = AccountingPeriodGuard::blockReason($invoice->date)) {
            return $blocked;
        }

        return null;
    }

    /** عملة الفاتورة المحفوظة معها — وإلا الافتراضية (فواتير أقدم من العمود) */
    private function invoiceCurrency(PurchaseInvoice $invoice): string
    {
        $stored = $invoice->getAttribute('currency');

        return is_string($stored) && $stored !== '' ? $stored : CurrencySettings::code();
    }

    /** ختم عملة المستند عند إنشائه — إن كان العمود موجوداً (ترحيل 2026_10_07_100000) */
    private function stampCurrency(PurchaseInvoice $invoice): void
    {
        static $hasColumn = null;
        $hasColumn ??= Schema::hasColumn('purchase_invoices', 'currency');

        if ($hasColumn && blank($invoice->getAttribute('currency'))) {
            $invoice->setAttribute('currency', CurrencySettings::code());
        }
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
     * معاينة طباعة الفاتورة بقالبٍ مضبوط في «قوالب المستندات» — وإلا
     * القالب المدمج. نفس نمط Purchases\InvoicesPurchaseController::printView().
     */
    public function print($id, $template = 'default')
    {
        $purchaseInvoice = $this->findStoreInvoice($id)->load(['supplier', 'items.product']);
        $id = $purchaseInvoice->id;

        OperationLog::record(
            'printed',
            'purchase_invoice',
            $purchaseInvoice,
            sprintf('تم طباعة فاتورة الشراء رقم **%s**', $purchaseInvoice->code)
        );

        $html = $this->renderConfiguredPurchaseInvoiceTemplate($id, $template);

        if ($html !== null) {
            return response(DocumentPrintPage::wrap($html));
        }

        return view($this->builtinPurchaseInvoiceTemplateView($template), compact('purchaseInvoice'));
    }

    public function exportPDF($id, $template = null)
    {
        $purchaseInvoice = $this->findStoreInvoice($id)->load(['supplier', 'items.product']);
        $id = $purchaseInvoice->id;

        try {
            $configuredHtml = $this->renderConfiguredPurchaseInvoiceTemplate($id, $template);

            $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Fawtra');
            $pdf->SetAuthor('Fawtra System');
            $pdf->SetTitle('فاتورة شراء - '.$purchaseInvoice->code);

            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(0);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->setRTL(true);
            $pdf->AddPage();
            $pdf->SetFont('aealarabiya', '', 12);

            // قالب الطباعة يحمل سكربت `window.print()` فيخرج نصّه صفحةً ثانية في الملف — للـPDF قالبه الخاص
            $html = $configuredHtml ?? view('onlinestore::purchases.invoices_purchase.pdf', compact('purchaseInvoice'))->render();
            $pdf->writeHTML($html, true, false, true, false, '');

            $filename = 'فاتورة-شراء-'.$purchaseInvoice->code.'.pdf';

            OperationLog::record(
                'exported',
                'purchase_invoice',
                $purchaseInvoice,
                sprintf('تم تصدير فاتورة الشراء رقم **%s** PDF', $purchaseInvoice->code)
            );

            return $pdf->Output($filename, 'D');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'OnlineStore.StorePurchaseInvoice', 'exportPDF');

            return back()->with('error', 'تعذّر تصدير ملف PDF.');
        }
    }

    public function thermal($id)
    {
        $purchaseInvoice = $this->findStoreInvoice($id)->load(['supplier', 'items.product']);

        return view('onlinestore::purchases.invoices_purchase.thermal', compact('purchaseInvoice'));
    }

    public function compact($id)
    {
        $purchaseInvoice = $this->findStoreInvoice($id)->load(['supplier', 'items.product']);

        return view('onlinestore::purchases.invoices_purchase.compact', compact('purchaseInvoice'));
    }

    /** القالب المدمج المقابل للمفتاح — الافتراضي هو الفاتورة العادية */
    private function builtinPurchaseInvoiceTemplateView($template): string
    {
        return match ($template) {
            'thermal' => 'onlinestore::purchases.invoices_purchase.thermal',
            'compact' => 'onlinestore::purchases.invoices_purchase.compact',
            default => 'onlinestore::purchases.invoices_purchase.print',
        };
    }

    /** يصيّر قالباً مصمَّماً في الإعدادات — أو null فيتولّى القالب المدمج */
    private function renderConfiguredPurchaseInvoiceTemplate($documentId, $template): ?string
    {
        if (! is_numeric($template)) {
            return null;
        }

        $purchaseInvoice = PurchaseInvoice::find($documentId);
        $documentType = $purchaseInvoice ? $this->purchaseDocumentType($purchaseInvoice) : self::DOCUMENT_TYPE;

        $model = DocumentTemplate::where('id', (int) $template)
            ->where('document_type', $documentType)
            ->first();

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)->getDocumentData($documentType, $documentId);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'OnlineStore.StorePurchaseInvoice', 'render_template', $documentId);

            return null;
        }
    }
}
