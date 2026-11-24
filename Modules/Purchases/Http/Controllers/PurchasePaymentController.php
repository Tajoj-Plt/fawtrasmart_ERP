<?php

namespace Modules\Purchases\Http\Controllers;

use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ClientPaymentRequest;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\PaymentMethod;
use App\Models\PaymentsProcess;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Services\DocumentTemplateDataService;
use App\Services\DocumentTemplateRenderer;
use App\Support\AccountingSettings;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PrintSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class PurchasePaymentController extends Controller
{
    use Concerns\RespectsAccountingPeriods;
    use ScopesToEmployeeHierarchy;

    /** نوع المستند في شاشة «قوالب المستندات» — سند صرف لمورّد */
    public const DOCUMENT_TYPE = 'supplier_payment';

    /** قسم الترقيم المتسلسل في شاشة «تسلسل أرقام المستندات» */
    public const NUMBER_SECTION = 'purchase-refund';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للسند */
    public const LOG_MODULE = 'supplier_payment';

    /*
     * لم يكن هذا الكنترولر محروساً بأي صلاحية إطلاقاً — لا قديمة ولا من
     * كتالوج `PurchasesPermissions` رغم أن مجموعة `payments` معرَّفة فيه
     * أصلاً وتُشير إليه صراحةً. الحراسة هنا أول تطبيق فعلي لها.
     */
    public function __construct()
    {
        $this->middleware('permission:purchases.payments.view', ['only' => ['index', 'show', 'quickSearch', 'getInvoiceDetails', 'getSupplierUnpaidInvoices']]);
        $this->middleware('permission:purchases.payments.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:purchases.payments.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:purchases.payments.delete', ['only' => ['destroy']]);
        $this->middleware('permission:purchases.payments.cancel', ['only' => ['cancel']]);
        $this->middleware('permission:purchases.payments.bulk_create', ['only' => ['bulkPaymentCreate', 'bulkPaymentStore']]);
        $this->middleware('permission:purchases.payments.export', ['only' => ['export', 'printView', 'pdf', 'pdfReceipt', 'rereceipt']]);
    }

    /**
     * الفاتورة المرتجعة لا تُسدَّد.
     *
     * المرتجع يعكس أثر الفاتورة في المخزون وفي حساب المورّد، فتسديدُها بعده
     * يدفع للمورّد ثمنَ بضاعةٍ رُدَّت إليه. القاعدة نفسها التي تُخفي الفاتورة
     * من «الفواتير المستحقة» ومن قائمة فواتير الشراء.
     *
     * @return PurchaseInvoice|null المرتجع القائم إن وُجد
     */
    private function blockingReturnFor($invoiceId): ?PurchaseInvoice
    {
        return PurchaseInvoice::where('reference_id', $invoiceId)
            ->where('type', 'Return')
            ->first();
    }

    /** نصّ الرفض — يسمّي الفاتورة ومرتجعها فلا يبقى السبب مبهماً */
    private function returnedInvoiceMessage(PurchaseInvoice $invoice, PurchaseInvoice $return): string
    {
        return __('purchase_cycle.payment.blocked_by_return', [
            'invoice' => $invoice->code,
            'code' => $return->code,
        ]);
    }

    /**
     * الموظفون الذين يجوز لهذا المستخدم إسناد السند إليهم.
     *
     * كان القرارُ نفسه مكتوباً بطريقتين متناقضتين: `create()` تسأل
     * `isEmployee()`، و`edit()` تسأل `! hasRoleName('manager')`. فمستخدمٌ
     * ليس «employee» ولا «manager» — «مدير مبيعات» مثلاً — كان يرى **كل**
     * الموظفين في شاشة الإنشاء و**نفسه وحده** في شاشة التعديل. والسؤالان
     * كلاهما عن اسم دورٍ ثابت لا عن صلاحية.
     *
     * والمعيار الآن نطاق البيانات: من يملك نطاقاً أوسع من «نفسه» يُسند إلى
     * أي موظف، ومن لا يملك فإلى نفسه وحده — وهو ما تقرأه شاشات القسم كلّها.
     */
    private function selectableEmployees(User $user)
    {
        if (hasPermission(['data_scope_all', 'data_scope_region', 'data_scope_branch', 'data_scope_team'])) {
            // لا عمود `name` في جدول employees، والاسم المعروض full_name مُشتقّ لا عمود؛
            // فيُرتَّب بعد الجلب لا في الاستعلام.
            // وموظّفو فرع الشريط العلويّ وما يتبعه وحدهم — ومن بلا فرعٍ يبقى،
            // كقاعدة `forBranch()`. الرئيسيُّ يعني الكلّ ({@see \App\Support\HeaderBranchScope}).
            // عبر `HeaderPickers` لا استعلاماً يدوياً: القاعدةُ نفسُها للفرع،
            // ومعها حصرُ «أقسام موظّفي المشتريات» من الإعدادات
            return \Modules\Purchases\Support\HeaderPickers::employees()
                ->get()->sortBy('full_name')->values();
        }

        return Employee::where('id', $user->employee_id)->get();
    }

    public function index(Request $request)
    {
        /* نطاق التاريخ الافتراضي هو **الفترة المحاسبية الجارية** — نفس قاعدة
           شاشة فواتير المبيعات، على `payment_date` هنا. كانت الشاشة تفتح على
           كل سند صرف سُجّل منذ التأسيس. */
        $range = $this->reportDateRange($request, 'from_date', 'to_date');

        /*
         * قائمة «أضيف بواسطة» — من سجّل سند صرفٍ فعلاً، لا قائمةَ أدوارٍ ثابتة.
         *
         * كانت `User::where('role', ['employee', 'manager'])` — وفيها خطآن:
         *   • تمرير مصفوفة إلى `where()` بموضع القيمة لا `whereIn()`، فالشرط
         *     لا يعني ما يبدو أنه يعنيه.
         *   • قراءة عمود `users.role` النصّي بأسماءٍ مكتوبة في الشيفرة، فمن
         *     سجّل سنداً بدورٍ آخر (أو بدورٍ عربيّ الاسم) يسقط من القائمة.
         *
         * والقائمة الآن من البيانات نفسها ومقيَّدة بنطاق رؤية المستخدم.
         */
        // ومن سداداتِ فرع الشريط العلويّ وحده — كالقائمة التي يصفّيها
        $payerIds = $this->applyHeaderBranchScope($this->scopeToCreator(
            PaymentsProcess::where('type', 'supplier payments'),
            'employee_id'
        ))->whereNotNull('employee_id')->distinct()->pluck('employee_id');

        // وأقسامُ موظّفي المشتريات من الإعدادات — إن ضُبطت
        $employees = \Modules\Purchases\Support\HeaderPickers::constrainUsersToDepartments(User::whereIn('id', $payerIds))
            ->orderBy('name')->get(['id', 'name', 'employee_id']);
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();
        $suppliers = \Modules\Purchases\Support\HeaderPickers::suppliers()->get();

        // إذا كان الطلب Ajax، نعيد البيانات فقط
        if ($request->ajax()) {
            return $this->getFilteredPayments($request);
        }

        // في البداية نعيد الصفحة مع البيانات الأولية
        $payments = $this->getFilteredPayments($request, false);

        // ملخّص الشاشة — بطاقات على قياس بقيّة شاشات القسم، وعلى نطاق
        // الشاشة نفسِه لا على التاريخ كلّه
        $stats = $this->summary($range);

        return view('purchases::purchases.supplier_payments.index', compact('payments', 'employees', 'suppliers', 'account_setting', 'stats', 'range'));
    }

    /**
     * أعداد ومبالغ بطاقات ملخّص شاشة المدفوعات.
     *
     * @return array<string, float|int>
     */
    private function summary(?array $range = null): array
    {
        /* بطاقاتُ الملخّص على نطاق الجدول نفسِه: الفرعُ النشط وما أُقفل،
           وإلّا عدّت البطاقةُ سنداتٍ لا تظهر في الجدول تحتها. */
        $base = fn () => PaymentsProcess::where('type', 'supplier payments')
            ->when($range, fn ($q) => $q->whereDate('payment_date', '>=', $range['start'])
                ->whereDate('payment_date', '<=', $range['end']))
            // والفرعُ يصمت على محور الأشخاص — انظر `applyHeaderBranchScope()`
            ->tap(fn ($q) => $this->applyHeaderBranchScope($q))
            ->tap(fn ($q) => $this->excludeClosedRanges($q, 'payments_process.payment_date'));

        // الحالة تُشتقّ من الفاتورة لا من عمودٍ نكتبه: الدفعة «مكتملة» متى
        // لم يبقَ على فاتورتها شيء. والعدّان يتّفقان مع ما يظهر في الجدول.
        $live = fn () => $base()->where(function ($q) {
            $q->whereNull('payment_status')->orWhere('payment_status', '!=', 5);
        });

        return [
            'total' => $base()->count(),
            'amount' => (float) $base()->sum('amount'),
            'completed' => $live()->whereHas('purchase_invoice', fn ($q) => $q->where('due_value', '<=', 0))->count(),
            'incomplete' => $live()->whereHas('purchase_invoice', fn ($q) => $q->where('due_value', '>', 0))->count(),
            'cancelled' => $base()->where('payment_status', 5)->count(),
        ];
    }

    /**
     * معاينة سند الصرف بقالبٍ مضبوط في «قوالب المستندات».
     *
     * كانت المطبوعة تأتي من `receipt/pdf_receipt.blade.php` — قالبٌ مكتوبٌ
     * في الشيفرة لا يعرفه محرّر القوالب، ولا تسري عليه ألوان «مظهر النظام»،
     * ولا يقرأ بيانات المنشأة من إعداداتها.
     */
    public function printView($id, $template = 'default')
    {
        $payment = PaymentsProcess::with(['supplier', 'purchase_invoice'])->findOrFail($id);
        $this->authorizeCreatorScope($payment->employee_id);

        $html = $this->renderConfiguredTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderConfiguredTemplate($id, $fallback->id) : '';
        }

        OperationLog::record(
            'printed',
            self::LOG_MODULE,
            $payment,
            __('purchase_cycle.log.printed', [
                'document' => __('purchase_cycle.payment.document'),
                'code' => $payment->reference_number ?: $payment->id,
            ]),
        );

        return response(DocumentPrintPage::wrap($html));
    }

    /** تنزيل سند الصرف PDF بالقالب المختار، وإلا الافتراضي للنوع */
    public function pdf($id, $template = null)
    {
        $payment = PaymentsProcess::with(['supplier', 'purchase_invoice'])->findOrFail($id);
        $this->authorizeCreatorScope($payment->employee_id);

        $html = $this->renderConfiguredTemplate($id, $template);

        if ($html === null) {
            $fallback = PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE);
            $html = $fallback ? $this->renderConfiguredTemplate($id, $fallback->id) : null;
        }

        if ($html === null) {
            return redirect()->back()->with('error', __('purchase_cycle.messages.no_template'));
        }

        OperationLog::record(
            'exported',
            self::LOG_MODULE,
            $payment,
            __('purchase_cycle.log.exported', [
                'document' => __('purchase_cycle.payment.document'),
                'code' => $payment->reference_number ?: $payment->id,
            ]),
        );

        return Pdf::loadHTML($html)->download(($payment->reference_number ?: $payment->id).'.pdf');
    }

    /**
     * يصيّر قالباً من الإعدادات — أو `null` إن لم يكن `$template` معرّفَ
     * قالبٍ صالح لهذا النوع، فيتولّى النداءُ البديلَ.
     */
    private function renderConfiguredTemplate($documentId, $template): ?string
    {
        if (! is_numeric($template)) {
            return null;
        }

        $model = DocumentTemplate::where('id', (int) $template)
            ->where('document_type', self::DOCUMENT_TYPE)
            ->first();

        if (! $model) {
            return null;
        }

        try {
            $data = app(DocumentTemplateDataService::class)
                ->getDocumentData(self::DOCUMENT_TYPE, $documentId);

            return app(DocumentTemplateRenderer::class)->render($model, $data);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'render_template', $documentId);

            return null;
        }
    }

    private function getFilteredPayments(Request $request, $returnJson = true)
    {
        try {
            $query = PaymentsProcess::with(['purchase_invoice.supplier', 'employee', 'supplier'])->where('type', 'supplier payments');

            // نطاق البيانات من الصلاحيات لا من اسم الدور: `isEmployee()` كانت
            // تقيّد صاحبَ الدور «employee» وحده، فمندوبٌ بدورٍ عربيّ الاسم يرى
            // سندات الجميع. و`employee_id` هنا مفتاحٌ إلى `users.id` فهو عمود
            // المنشئ، و`payments_process` تحمل `branch_id` فيعمل نطاق الفرع أيضاً.
            $query = $this->scopeToCreator($query, 'employee_id');

            // تطبيق فلاتر البحث
            $this->applyPaymentFilters($query, $request);
            $this->applyQuickFilter($query, $request);

            // ترتيب النتائج
            $query->orderBy('created_at', 'DESC');

            // عدد السجلات في الصفحة يتبع اختيار المستخدم، وكان ثابتاً عند 25
            // فلا يفعل مُنتقي «عرض» شيئاً
            $perPage = (int) $request->input('per_page', 25);
            $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

            $payments = $query->paginate($perPage)->withQueryString();

            if ($returnJson) {
                $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

                $tableHtml = view('purchases::purchases.supplier_payments.partials.table', compact('payments', 'account_setting'))->render();

                $paginationHtml = view('purchases::purchases.supplier_payments.partials.pagination', compact('payments'))->render();

                // دمج HTML الجدول والترقيم معاً
                $combinedHtml = $tableHtml.$paginationHtml;

                return response()->json([
                    'success' => true,
                    'html' => $combinedHtml,
                    'data' => $tableHtml, // للتوافق مع الكود الأمامي
                    'pagination' => $paginationHtml,
                    'total' => $payments->total(),
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'from' => $payments->firstItem(),
                    'to' => $payments->lastItem(),
                ]);
            }

            return $payments;
        } catch (\Exception $e) {
            Log::error('Error in getFilteredPayments: '.$e->getMessage());

            if ($returnJson) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'حدث خطأ أثناء تحميل البيانات: '.$e->getMessage(),
                    ],
                    500,
                );
            }

            // إرجاع نتائج فارغة في حالة الخطأ
            return PaymentsProcess::where('id', 0)->paginate(25);
        }
    }

    private function applyPaymentFilters($query, $request)
    {
        /* الفرع النشط من الهيدر — كما تفعل بقيّة شاشات المشتريات.
           مبدّلُ الفروع يكتب `users.branch_id` و`current_branch_id()` تقرؤه،
           وكانت هذه الشاشة وحدَها لا تقرؤه: يختار المستخدم فرعاً فتبقى
           سنداتُ المنشأة كلِّها في الجدول. و`payments_process` تحمل
           `branch_id` فالتصفيةُ على عمود المستند نفسِه.

           والفرعُ الرئيسيّ مستثنىً: هو مركز المنشأة لا فرعٌ من فروعها،
           فمن يقف عليه يرى السندات كلَّها. */
        $this->applyHeaderBranchScope($query, $request);

        /* ** الفترات المقفلة ** — تُستبعد من الشاشة كلّها لا من نطاقها
           الافتراضيّ وحده: من أقفل سنةً لا يريد سنداتِها أن تعود بضبط
           التاريخ يدوياً، ولا بفلتر «التخصيص» الذي يتنحّى النطاقُ أمامه
           أدناه. والفحصُ على `payment_date` — تاريخِ السند — لا لحظةِ
           إدخاله، وهو التاريخ نفسه الذي يفحصه منعُ الحفظ. */
        $this->excludeClosedRanges($query, 'payments_process.payment_date');

        // فلتر رقم الفاتورة
        if ($request->filled('invoice_number')) {
            $invoiceNumber = trim($request->invoice_number);
            $query->whereHas('purchase_invoice', function ($q) use ($invoiceNumber) {
                $q->where('code', 'like', '%'.$invoiceNumber.'%')->orWhere('reference_number', 'like', '%'.$invoiceNumber.'%');
            });
        }

        // فلتر رقم عملية الدفع
        if ($request->filled('payment_number')) {
            $paymentNumber = trim($request->payment_number);
            $query->where(function ($q) use ($paymentNumber) {
                $q->where('payment_number', 'like', '%'.$paymentNumber.'%')
                    ->orWhere('reference_number', 'like', '%'.$paymentNumber.'%')
                    ->orWhere('id', 'like', '%'.$paymentNumber.'%');
            });
        }

        // فلتر المورد
        if ($request->filled('supplier')) {
            $supplierId = $request->supplier;
            $query->where(function ($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId)->orWhereHas('purchase_invoice', function ($subQ) use ($supplierId) {
                    $subQ->where('supplier_id', $supplierId);
                });
            });
        }

        // فلتر "أضيفت بواسطة"
        if ($request->filled('added_by')) {
            $query->where('employee_id', $request->added_by);
        }

        // فلتر حالة الدفع
        /* «ملغى» = الحالة 5 في العمود الرقمي — كانت القائمة ترسل النصّ `cancelled`
           فيقارنه MySQL بالصفر ولا يطابق سنداً ملغياً أبداً. */
        if ($request->filled('payment_status')) {
            match ($request->payment_status) {
                'cancelled' => $query->where('payment_status', 5),
                'confirmed' => $query->where(fn ($q) => $q->whereNull('payment_status')->orWhere('payment_status', '!=', 5)),
                default => $query->where('payment_status', $request->payment_status),
            };
        }

        // فلتر التخصيص (التواريخ المحددة مسبقاً)
        if ($request->filled('customization')) {
            switch ($request->customization) {
                case '1': // شهرياً
                    $query->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year);
                    break;
                case '0': // أسبوعياً
                    $query->whereBetween('payment_date', [now()->startOfWeek()->format('Y-m-d'), now()->endOfWeek()->format('Y-m-d')]);
                    break;
                case '2': // يومياً
                    $query->whereDate('payment_date', now()->format('Y-m-d'));
                    break;
            }
        }

        /* فلتر التاريخ — والنطاقُ مطبَّقٌ دائماً.
           الحقلان فارغَين كانا يعنيان «بلا حدّ»، فتُقرأ الشاشة على تاريخ
           المنشأة كلّه. صارا يعنيان الفترة المحاسبية الجارية.

           ويتنحّى النطاق أمام «التخصيص» أعلاه: من اختار «يومياً» طلب اليوم
           صراحةً، فلا يُقصّ اختيارُه بحدّي الفترة. */
        if (! $request->filled('customization')) {
            $range = $this->reportDateRange($request, 'from_date', 'to_date');
            $query->whereDate('payment_date', '>=', $range['start'])
                ->whereDate('payment_date', '<=', $range['end']);
        }

        // فلتر الرقم التعريفي
        if ($request->filled('identifier')) {
            $identifier = trim($request->identifier);
            $query->where(function ($q) use ($identifier) {
                $q->where('reference_number', 'like', '%'.$identifier.'%')
                    ->orWhere('payment_number', 'like', '%'.$identifier.'%')
                    ->orWhere('id', 'like', '%'.$identifier.'%');
            });
        }

        // فلتر رقم معرف التحويل
        if ($request->filled('transfer_id')) {
            $transferId = trim($request->transfer_id);
            $query->where(function ($q) use ($transferId) {
                $q->where('reference_number', 'like', '%'.$transferId.'%')->orWhere('transaction_id', 'like', '%'.$transferId.'%');
            });
        }

        // فلتر المبلغ أكبر من
        if ($request->filled('total_greater_than') && is_numeric($request->total_greater_than)) {
            $query->where('amount', '>', floatval($request->total_greater_than));
        }

        // فلتر المبلغ أصغر من
        if ($request->filled('total_less_than') && is_numeric($request->total_less_than)) {
            $query->where('amount', '<', floatval($request->total_less_than));
        }

        // فلتر الحقل المخصص (البحث في الملاحظات)
        if ($request->filled('custom_field')) {
            $customField = trim($request->custom_field);
            $query->where(function ($q) use ($customField) {
                $q->where('notes', 'like', '%'.$customField.'%')->orWhere('payment_data', 'like', '%'.$customField.'%');
            });
        }

        // فلتر مصدر الفاتورة
        if ($request->filled('invoice_origin')) {
            $query->whereHas('purchase_invoice', function ($q) use ($request) {
                $q->where('origin', $request->invoice_origin);
            });
        }
    }

    private function applyQuickFilter($query, $request)
    {
        $filter = $request->get('filter', 'all');

        switch ($filter) {
            case 'completed':
                $query->where('payment_status', 1);
                break;
            case 'pending':
                $query->where('payment_status', 2);
                break;
            case 'failed':
                $query->where('payment_status', 5);
                break;
            case 'draft':
                $query->where('payment_status', 3);
                break;
            case 'review':
                $query->where('payment_status', 4);
                break;
            case 'all':
            default:
                // لا نضيف أي شرط - عرض الكل
                break;
        }
    }

    // دالة البحث السريع
    public function quickSearch(Request $request)
    {
        if (! $request->ajax()) {
            return redirect()->route('PaymentSupplier.indexPurchase');
        }

        try {
            $searchTerm = trim($request->get('term', ''));

            $query = PaymentsProcess::with(['purchase_invoice.supplier', 'employee', 'supplier'])->where('type', 'supplier payments');

            // نطاق البيانات من الصلاحيات لا من اسم الدور: `isEmployee()` كانت
            // تقيّد صاحبَ الدور «employee» وحده، فمندوبٌ بدورٍ عربيّ الاسم يرى
            // سندات الجميع. و`employee_id` هنا مفتاحٌ إلى `users.id` فهو عمود
            // المنشئ، و`payments_process` تحمل `branch_id` فيعمل نطاق الفرع أيضاً.
            $query = $this->scopeToCreator($query, 'employee_id');

            /* والبحثُ السريع بقيدَي الشاشة نفسِها — الفرعُ النشط وما أُقفل.
               لا يمرّ هذا المدخل بـ`applyPaymentFilters`، فبدونهما يصير
               حقلُ البحث باباً خلفياً يُخرج ما تُخفيه الشاشة. */
            $this->applyHeaderBranchScope($query);

            $this->excludeClosedRanges($query, 'payments_process.payment_date');

            if (! empty($searchTerm)) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('payment_number', 'like', '%'.$searchTerm.'%')
                        ->orWhere('reference_number', 'like', '%'.$searchTerm.'%')
                        ->orWhere('amount', 'like', '%'.$searchTerm.'%')
                        ->orWhere('notes', 'like', '%'.$searchTerm.'%')
                        ->orWhere('id', 'like', '%'.$searchTerm.'%')
                        ->orWhereHas('purchase_invoice', function ($subQuery) use ($searchTerm) {
                            $subQuery->where('code', 'like', '%'.$searchTerm.'%')->orWhere('reference_number', 'like', '%'.$searchTerm.'%');
                        })
                        ->orWhereHas('purchase_invoice.supplier', function ($subQuery) use ($searchTerm) {
                            $subQuery->where('trade_name', 'like', '%'.$searchTerm.'%')->orWhere('phone', 'like', '%'.$searchTerm.'%');
                        })
                        ->orWhereHas('employee', function ($subQuery) use ($searchTerm) {
                            $subQuery->where('name', 'like', '%'.$searchTerm.'%');
                        });
                });
            }

            $payments = $query->orderBy('created_at', 'DESC')->paginate(25);
            $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

            return response()->json([
                'success' => true,
                'html' => view('purchases::purchases.supplier_payments.partials.table', compact('payments', 'account_setting'))->render(),
                'data' => view('purchases::purchases.supplier_payments.partials.table', compact('payments', 'account_setting'))->render(),
                'pagination' => view('purchases::purchases.supplier_payments.partials.pagination', compact('payments'))->render(),
                'total' => $payments->total(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in quickSearch: '.$e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء البحث: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    // دالة التصدير
    public function export(Request $request)
    {
        try {
            $query = PaymentsProcess::with(['purchase_invoice.supplier', 'employee', 'supplier'])->where('type', 'supplier payments');

            // نطاق البيانات من الصلاحيات لا من اسم الدور: `isEmployee()` كانت
            // تقيّد صاحبَ الدور «employee» وحده، فمندوبٌ بدورٍ عربيّ الاسم يرى
            // سندات الجميع. و`employee_id` هنا مفتاحٌ إلى `users.id` فهو عمود
            // المنشئ، و`payments_process` تحمل `branch_id` فيعمل نطاق الفرع أيضاً.
            $query = $this->scopeToCreator($query, 'employee_id');

            $this->applyPaymentFilters($query, $request);
            $this->applyQuickFilter($query, $request);

            $payments = $query->orderBy('created_at', 'DESC')->get();

            // يمكنك إضافة منطق التصدير هنا (Excel, PDF, إلخ)

            return response()->json([
                'success' => true,
                'message' => 'تم تصدير البيانات بنجاح',
                'count' => $payments->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in export: '.$e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء التصدير: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    public function create($id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);
        $this->authorizeCreatorScope($invoice->created_by);

        // الفاتورة المرتجعة لا تُسدَّد — والشاشة تُفتَح برابطٍ مباشر كذلك،
        // فالفحص هنا لا في القوالب وحدها
        if ($return = $this->blockingReturnFor($invoice->id)) {
            return redirect()
                ->route('invoicePurchases.show', $invoice->id)
                ->with('error', $this->returnedInvoiceMessage($invoice, $return));
        }

        $amount = $invoice->due_value;
        $invoiceId = $invoice->id;

        // الحصول على الخزائن من الحسابات مثل إنشاء الفاتورة
        $treasury = Account::whereIn('parent_id', [AccountingSettings::id('bank_accounts_parent'), AccountingSettings::id('cash_treasuries_parent')])->get();

        $user = auth()->user();

        $employees = $this->selectableEmployees($user);

        $payments = $this->manualPaymentMethods();

        // تحديد الخزينة بنفس طريقة إنشاء الفاتورة
        $mainTreasuryAccount = null;
        $treasury_id = null;

        if ($user && $user->employee_id) {
            $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

            if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                $mainTreasuryAccount = Account::where('id', $treasuryEmployee->treasury_id)->first();
                $treasury_id = $treasuryEmployee->treasury_id;
            }
        }

        // إذا لم نجد خزينة للموظف، استخدم الخزينة الرئيسية
        if (! $mainTreasuryAccount) {
            $mainTreasuryAccount = AccountingSettings::account('main_treasury');
            $treasury_id = $mainTreasuryAccount?->id;
        }

        if (! $mainTreasuryAccount) {
            return redirect()
                ->route('invoicePurchases.show', $invoice->id)
                ->with('error', __('purchase_cycle.payment.no_treasury'));
        }

        /* الفترة المحاسبية تمنع الحفظ بتاريخ اليوم: يُقال قبل ملء المستند
           كلّه لا بعد الضغط على حفظ — كما في شاشات المبيعات. */
        $periodBlock = $this->accountingPeriodBlock(now());

        $invoice->loadMissing('supplier');

        return view('purchases::purchases.supplier_payments.create', compact('invoice', 'invoiceId', 'payments', 'amount', 'treasury', 'employees', 'mainTreasuryAccount', 'user', 'treasury_id', 'periodBlock'));
    }

    public function show($id)
    {
        /* النوعان معاً لا واحد.
         *
         * `store()` يكتب اليوم `supplier payments`، والصفوف الأقدم في
         * القاعدة نوعُها `purchase` — وهي كلُّ ما فيها حتى الآن. فالقيد
         * على الاسم الجديد وحده يجعل **كل سند صرفٍ قائم** يُرجع ٤٠٤ من
         * هذه الشاشة، ولا يُفتَح إلّا ما أُنشئ بعد تغيير الاسم. */
        $payment = PaymentsProcess::with(['purchase_invoice.supplier', 'supplier', 'employee', 'treasury'])
            ->whereIn('type', ['supplier payments', 'purchase'])
            ->findOrFail($id);
        $this->authorizeCreatorScope($payment->employee_id);

        // كانت تسرد المستخدمين كلَّهم لحقل «الموظّف» في نموذج التعديل
        $employees = $this->creatorOptions(PaymentsProcess::query(), 'employee_id');

        /* القيد المحاسبي للسند — لا عمودَ يربط `payments_process` بـ
           `journal_entries`، فيُعثَر عليه بالفاتورة ونوع المرجع. وكانت
           الشاشة لا تُظهره إطلاقاً رغم أنّ السند يُرحَّل قيداً. */
        $journalEntry = $payment->purchases_id
            ? JournalEntry::where('purchase_invoice_id', $payment->purchases_id)
                ->where('reference_type', ReferenceType::SUPPLIER_PAYMENT)
                ->orderByDesc('id')
                ->first()
            : null;

        /* الخطّ الزمني للسند — مفتاحان لا واحد: الصفوف القديمة بالنوع
           `payments`، والجديدة بالنوع المسجَّل في `LOG_MODULE`. */
        $logs = ModelsLog::whereIn('type', ['payments', self::LOG_MODULE])
            ->where('type_id', $id)
            ->with('user.branch')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($row) => $row->created_at->format('Y-m-d'));

        return view('purchases::purchases.supplier_payments.show', [
            'payment' => $payment,
            'employees' => $employees,
            'journalEntry' => $journalEntry,
            'logs' => $logs,
            // اسم طريقة الدفع لا رقمها — العمود يحمل المعرّف
            'paymentMethods' => PaymentMethod::query()->get(['id', 'name']),
            // مدفوعات الفاتورة كلُّها — لموضع هذا السند منها
            'invoicePayments' => $payment->purchases_id
                ? PaymentsProcess::with(['treasury', 'treasuryAccount'])
                    ->where('purchases_id', $payment->purchases_id)
                    ->whereIn('type', ['supplier payments', 'purchase'])
                    ->orderByDesc('payment_date')
                    ->get()
                : collect(),
            // قوالب سند الصرف المصمَّمة في «قوالب المستندات»
            'documentTemplates' => PrintSettings::templatesFor(self::DOCUMENT_TYPE),
            'defaultTemplate' => PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE),
            'prefersPdf' => PrintSettings::prefersPdf(),
        ]);
    }

    public function edit($id)
    {
        // جلب بيانات الدفعة الحالية مع العلاقات
        $payment = PaymentsProcess::with(['purchase_invoice', 'employee'])->findOrFail($id);
        $this->authorizeCreatorScope($payment->employee_id);

        // جلب الفاتورة المرتبطة
        $invoice = PurchaseInvoice::findOrFail($payment->purchases_id);
        $this->authorizeCreatorScope($invoice->created_by);

        // تحديد البيانات الأساسية
        $invoiceId = $payment->purchases_id;
        $amount = $payment->amount; // المبلغ الحالي للدفعة

        // جلب الخزائن من الحسابات مثل إنشاء الفاتورة
        $treasury = Account::whereIn('parent_id', [AccountingSettings::id('bank_accounts_parent'), AccountingSettings::id('cash_treasuries_parent')])->get();

        // جلب المستخدم الحالي
        $user = auth()->user();

        $employees = $this->selectableEmployees($user);

        // جلب طرق الدفع النشطة
        $payments = $this->manualPaymentMethods();

        // تحديد الخزينة الحالية للدفعة أو الافتراضية
        $mainTreasuryAccount = null;
        $treasury_id = $payment->treasury_id; // استخدام خزينة الدفعة الحالية أولاً

        // إذا لم تكن هناك خزينة محددة في الدفعة، استخدم خزينة الموظف
        if (! $treasury_id) {
            if ($user && $user->employee_id) {
                $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

                if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                    $treasury_id = $treasuryEmployee->treasury_id;
                }
            }
        }

        // جلب بيانات الخزينة المحددة
        if ($treasury_id) {
            $mainTreasuryAccount = Account::where('id', $treasury_id)->first();
        }

        // إذا لم نجد خزينة، استخدم الخزينة الرئيسية
        if (! $mainTreasuryAccount) {
            $mainTreasuryAccount = AccountingSettings::account('main_treasury');
            $treasury_id = $mainTreasuryAccount?->id;
        }

        // التحقق من وجود خزينة متاحة
        if (! $mainTreasuryAccount) {
            return redirect()
                ->route('invoicePurchases.show', $invoice->id)
                ->with('error', __('purchase_cycle.payment.no_treasury'));
        }

        // إضافة بيانات إضافية للعرض
        $installmentId = $payment->installment_id ?? null;

        return view(
            'purchases::purchases.supplier_payments.edit',
            compact(
                'payment', // بيانات الدفعة الحالية
                'invoice', // بيانات الفاتورة
                'invoiceId', // معرف الفاتورة
                'payments', // طرق الدفع المتاحة
                'amount', // المبلغ الحالي
                'treasury', // جميع الخزائن
                'employees', // الموظفين المتاحين
                'mainTreasuryAccount', // الخزينة الرئيسية/المحددة
                'user', // المستخدم الحالي
                'treasury_id', // معرف الخزينة المحددة
                'installmentId', // معرف القسط إن وجد
                'id', // معرف الدفعة للاستخدام في النموذج
            ),
        );
    }

    /**
     * تعديل سند صرف لمورّد.
     *
     * **الربط الضمنيّ لم يكن يعمل** — المسار
     * `updateSupplierPayment/{id}` ومُعامل الدالّة `$payment`: الربط
     * يتمّ بتطابق **اسم** المُعامل مع اسم مقطع المسار، و`id` ليس
     * `payment`. فيصل نموذجٌ فارغٌ غير محفوظ، و`$payment->update()`
     * عليه لا يكتب شيئاً ويعود `false` بلا خطأ — **تعديل السند لا
     * يُحفَظ إطلاقاً** ويظهر مع ذلك «تم التحديث بنجاح».
     */
    /**
     * طرق الدفع اليدوية من «طرق الدفع» في الإعدادات.
     *
     * `type = normal` عمداً: النوع `electronic` بوّاباتُ دفعٍ إلكترونية
     * (Paypal وSquare وTamara) تُحصّل من المتجر لا من موظّفٍ يكتب سنداً
     * بيده.
     *
     * والترتيب ترتيبُ الإعدادات — الافتراضية أوّلاً ثمّ `sort_order` —
     * كي تكون الطريقة الأولى في القائمة هي التي عيّنها المسؤول
     * افتراضية. وكانت الشاشات تسردها بترتيب المعرّف مهما ضُبط.
     */
    protected function manualPaymentMethods()
    {
        try {
            return PaymentMethod::query()
                ->where('type', 'normal')
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.PurchasePayment', 'manualPaymentMethods');

            return collect();
        }
    }

    public function update(ClientPaymentRequest $request, $id)
    {
        $payment = PaymentsProcess::findOrFail($id);
        $this->authorizeCreatorScope($payment->employee_id);

        try {
            DB::beginTransaction();

            // استرجاع البيانات المصادق عليها
            $data = $request->validated();

            /* الفترة المحاسبية المقفلة تردّ السداد.
             *
             * سندُ الصرف يكتب قيداً كالفاتورة تماماً، وكانت شاشة المدفوعات
             * وحدها في المشتريات بلا هذا الفحص إطلاقاً: تُسدَّد فاتورةٌ
             * بتاريخ شهرٍ مُقفَل فيتغيّر رصيدُ الخزينة بعد إصدار ميزانها.
             */
            if ($block = $this->accountingPeriodBlock($data['payment_date'] ?? $payment->payment_date ?? now())) {
                DB::rollBack();

                return back()->withInput()->with('error', $block);
            }

            // التحقق من وجود الفاتورة وجلب تفاصيلها
            $invoice = PurchaseInvoice::findOrFail($payment->purchases_id);
            $this->authorizeCreatorScope($invoice->created_by);

            // ✅ حفظ القيم القديمة قبل التحديث
            $oldAmount = $payment->amount;
            $oldPaymentStatus = $payment->payment_status;

            // حساب إجمالي المدفوعات السابقة (باستثناء الدفعة الحالية والمدفوعات الفاشلة)
            $totalPreviousPayments = PaymentsProcess::where('purchases_id', $invoice->id)
                ->where('type', 'supplier payments')
                ->where('payment_status', '!=', 5) // استثناء المدفوعات الفاشلة
                ->where('id', '!=', $payment->id) // استثناء الدفعة الحالية
                ->sum('amount');

            // حساب المبلغ المتبقي للدفع (مع احتساب المبلغ الجديد)
            $remainingAmount = $invoice->grand_total - $totalPreviousPayments;

            // التحقق من أن مبلغ الدفع الجديد لا يتجاوز المبلغ المتبقي
            if ($data['amount'] > $remainingAmount) {
                return back()
                    ->with('error', 'مبلغ الدفع يتجاوز المبلغ المتبقي للفاتورة. المبلغ المتبقي هو: '.number_format($remainingAmount, 2))
                    ->withInput();
            }

            // ✅ تحديد الخزينة المستهدفة - دائماً من اختيار المدير في الـ form
            $mainTreasuryAccount = null;
            $treasury_id = null;

            // استخدام الخزينة المختارة من الـ form
            if ($data['treasury_id']) {
                $mainTreasuryAccount = Account::find($data['treasury_id']);
                if (! $mainTreasuryAccount) {
                    return back()
                        ->with('error', 'The selected treasury id is invalid.')
                        ->withInput();
                }
                $treasury_id = $data['treasury_id'];
            } else {
                // إذا لم يتم اختيار خزينة، استخدم الخزينة الرئيسية
                $mainTreasuryAccount = AccountingSettings::account('main_treasury');
                $treasury_id = $mainTreasuryAccount->id ?? null;
            }

            // إذا لم يتم العثور على خزينة، توقف العملية وأظهر خطأ
            if (! $mainTreasuryAccount) {
                throw new \Exception('لا توجد خزينة متاحة. يرجى التحقق من إعدادات الخزينة.');
            }

            // ✅ حساب الفرق في المبلغ — للرسائل والسجلّ
            $amountDifference = $data['amount'] - $oldAmount;

            /* ** الفرق يُقاس بالنقد لا بالمبلغ **
             *
             * رصيد المورد الآن يحمل نقدَ هذه الدفعة مخصوماً منه، فيُردّ
             * إليه أوّلاً كي يُقاس المبلغ الجديد على الحالة التي سبقتها —
             * وإلا حُسبت التسوية على رصيدٍ خصمته الدفعة نفسها.
             */
            $supplierAccount = Account::ledger()->where('supplier_id', $invoice->supplier_id)->first();
            $oldCash = $this->paymentCash($payment);
            $balanceBeforeThisPayment = (float) ($supplierAccount->balance ?? 0) + $oldCash;

            $split = $this->splitAgainstSupplierCredit(
                (float) $data['amount'],
                (float) $remainingAmount,
                $balanceBeforeThisPayment
            );

            $cashDifference = round($split['cash'] - $oldCash, 2);

            // التحقق من وجود رصيد كافي في الخزينة إذا زاد النقد المطلوب
            if ($cashDifference > 0 && $mainTreasuryAccount->balance < $cashDifference) {
                throw new \Exception('رصيد الخزينة غير كافي للزيادة المطلوبة. الرصيد الحالي: '
                    .number_format($mainTreasuryAccount->balance, 2)
                    .' والمطلوب إضافيًا نقداً: '.number_format($cashDifference, 2));
            }

            // تعيين حالة الدفع الافتراضية كمسودة
            $payment_status = 3; // مسودة

            // تحديد حالة الدفع بناءً على المبلغ المدفوع والمبلغ المتبقي
            $newTotalPayments = $totalPreviousPayments + $data['amount'];

            if ($newTotalPayments >= $invoice->grand_total) {
                $payment_status = 1; // مكتمل
                $invoice->is_paid = true;
                $invoice->payment_status = 'paid'; // ✅ تحديث حالة الدفع الجديدة
                $invoice->due_value = 0;
            } else {
                $payment_status = 2; // غير مكتمل
                $invoice->is_paid = false;
                $invoice->payment_status = 'partially_paid'; // ✅ تحديث حالة الدفع الجديدة
                $invoice->due_value = $invoice->grand_total - $newTotalPayments;
            }

            // ✅ إضافة البيانات الإضافية للدفعة المحدثة
            $data['payment_status'] = $payment_status;
            $data['credit_settled'] = $split['settled'];
            $data['treasury_id'] = $treasury_id;
            $data['supplier_id'] = $invoice->supplier_id;
            $data['purchases_id'] = $invoice->id;
            $data['invoice_id'] = null; // ترك invoice_id فارغاً للدفعات الشراء
            $data['updated_by'] = Auth::id(); // ✅ إضافة معرف من قام بالتحديث

            /* طريقة الدفع: الشاشة ترسلها في `payment_type` والعمود اسمه
             * `payment_method`، وهو وحده في `$fillable`. فالمفتاح يُرمى
             * قبل أن يصل إلى الاستعلام بلا خطأ ولا تحذير: يختار
             * المستخدم «تحويل بنكي» من «طرق الدفع» ويُحفَظ السند بلا
             * طريقة دفعٍ إطلاقاً، ثمّ تعرض الشاشات شرطةً مكانها. */
            if (array_key_exists('payment_type', $data)) {
                $data['payment_method'] = $data['payment_type'];
                unset($data['payment_type']);
            }

            // معالجة المرفقات
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    // حذف المرفق القديم إذا كان موجوداً
                    if ($payment->attachments && file_exists(public_path('assets/uploads/'.$payment->attachments))) {
                        unlink(public_path('assets/uploads/'.$payment->attachments));
                    }

                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $data['attachments'] = $filename;
                }
            }

            // ✅ تحديث ملاحظة تتضمن معلومات الخزينة والتحديث
            $updateNote = "\nتم التحديث في: ".now()->format('Y-m-d H:i:s').' - الخزينة المستخدمة: '.$mainTreasuryAccount->name;
            $data['notes'] = ($data['notes'] ?? '').$updateNote.$this->settlementNote($split);

            // ✅ تحديث سجل الدفع
            $payment->update($data);

            // تحديث المبلغ المدفوع في الفاتورة
            $invoice->advance_payment = $newTotalPayments;
            $invoice->save();

            /* ✅ إنشاء قيد محاسبي جديد للتحديث (بدون حذف القديم) — بالنقد
               وحده: ما غطّاه رصيد المورد لم يدخل القيد الأوّل فلا يُعدَّل. */
            $this->createUpdatePaymentAccountingEntries($invoice, $oldCash, $split['cash'], $mainTreasuryAccount, $payment->id);

            // ✅ تسجيل إشعار النظام للتحديث
            $supplier = Supplier::find($invoice->supplier_id);
            $user = Auth::user();
            ModelsLog::create([
                'type' => 'payment_supplier_update',
                'type_id' => $payment->id,
                'type_log' => 'log',
                'icon' => 'edit',
                'description' => sprintf('تم تحديث دفعة للمورد **%s** - فاتورة رقم **%s** - المبلغ من **%s** إلى **%s** ر.س - الفرق: **%s** ر.س - حالة الدفع: **%s** - المتبقي: **%s** ر.س', $supplier->trade_name ?? '', $invoice->code ?? '', number_format($oldAmount, 2), number_format($data['amount'], 2), number_format($amountDifference, 2), $this->getPaymentStatusText($payment_status), number_format($invoice->due_value, 2)),
                'created_by' => auth()->id(),
            ]);

            // ✅ إنشاء إشعار للنظام للتحديث
            notifications::create([
                'type' => 'payment_update',
                'title' => $user->name.' قام بتحديث دفعة',
                'description' => 'تم تحديث دفعة للمورد '.($supplier->trade_name ?? '').' - فاتورة رقم '.$invoice->code.' - المبلغ من '.number_format($oldAmount, 2).' إلى '.number_format($data['amount'], 2).' ر.س - الفرق: '.number_format($amountDifference, 2).' ر.س - حالة الدفع: '.$this->getPaymentStatusText($payment_status),
            ]);

            DB::commit();

            // إعداد رسالة النجاح مع تفاصيل التحديث
            $paymentStatusText = $this->getPaymentStatusText($payment_status);
            $changeType = $amountDifference > 0 ? 'زيادة' : ($amountDifference < 0 ? 'تقليل' : 'بدون تغيير في المبلغ');

            $successMessage = sprintf('تم تحديث عملية الدفع بنجاح - %s بمقدار %s ر.س. المبلغ الجديد: %s ر.س، المبلغ المتبقي: %s ر.س - حالة الدفع: %s', $changeType, number_format(abs($amountDifference), 2), number_format($data['amount'], 2), number_format($invoice->due_value, 2), $paymentStatusText);

            return redirect()->route('PaymentSupplier.indexPurchase')->with('success', $successMessage);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('خطأ في تحديث عملية الدفع: '.$e->getMessage());

            return back()
                ->with('error', 'حدث خطأ أثناء تحديث عملية الدفع: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * ✅ دالة إنشاء القيود المحاسبية لتحديث الدفعة (بدون حذف القديم)
     */
    private function createUpdatePaymentAccountingEntries($invoice, $oldAmount, $newAmount, $mainTreasuryAccount, $paymentId)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // حساب الفرق في المبلغ
        $amountDifference = $newAmount - $oldAmount;

        // إذا لم يكن هناك فرق، لا حاجة لإنشاء قيد
        if ($amountDifference == 0) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

            return;
        }

        // حساب المورد
        $supplierAccount = Account::ledger()->where('supplier_id', $invoice->supplier_id)->first();
        if (! $supplierAccount) {
            $supplier = Supplier::find($invoice->supplier_id);
            $supplierAccount = Account::create([
                'name' => 'حساب المورد - '.($supplier->trade_name ?? 'مورد غير معروف'),
                'supplier_id' => $invoice->supplier_id,
                'account_type' => 'supplier',
                'balance' => 0,
                'status' => 1,
            ]);
        }

        // تحديد نوع القيد بناءً على الفرق
        $entryType = $amountDifference > 0 ? 'زيادة' : 'تقليل';
        $absoluteDifference = abs($amountDifference);

        // إنشاء القيد المحاسبي لتحديث الدفعة
        $journalEntry = JournalEntry::create([
            'reference_number' => $invoice->code.'_تحديث_دفعة_'.$paymentId.'_'.time(),
            'purchase_invoice_id' => $invoice->id,
            'date' => now(),
            'description' => $entryType.' دفعة للمورد بمقدار '.number_format($absoluteDifference, 2).' ر.س - فاتورة # '.$invoice->code.' (المبلغ من '.number_format($oldAmount, 2).' إلى '.number_format($newAmount, 2).')',
            'status' => 1,
            'currency' => \App\Support\CurrencySettings::code(),
            'client_id' => $invoice->supplier_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
            'linking_type' => LinkingType::SINGLE,
        ]);

        if ($amountDifference > 0) {
            // زيادة المبلغ - قيد إضافي

            // حساب المورد/المؤسسة (مدين) - زيادة تسديد الدين
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $supplierAccount->id,
                'description' => 'زيادة دفعة للمورد - فاتورة # '.$invoice->code,
                'debit' => $absoluteDifference,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // حساب الخزينة الأساسية (دائن) - خروج نقد إضافي
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $mainTreasuryAccount->id,
                'description' => 'زيادة دفعة للمورد - فاتورة # '.$invoice->code,
                'debit' => 0,
                'credit' => $absoluteDifference,
                'is_debit' => false,
            ]);

            // تحديث رصيد الخزينة (سحب المبلغ الإضافي)
            $mainTreasuryAccount->balance -= $absoluteDifference;
            $mainTreasuryAccount->save();

            // تحديث رصيد المورد (تقليل الدين الإضافي)
            $supplierAccount->balance -= $absoluteDifference;
            $supplierAccount->save();
        } else {
            // تقليل المبلغ - قيد عكسي

            // حساب الخزينة الأساسية (مدين) - إرجاع نقد
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $mainTreasuryAccount->id,
                'description' => 'تقليل دفعة للمورد - فاتورة # '.$invoice->code,
                'debit' => $absoluteDifference,
                'credit' => 0,
                'is_debit' => true,
            ]);

            // حساب المورد/المؤسسة (دائن) - تقليل تسديد الدين
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $supplierAccount->id,
                'description' => 'تقليل دفعة للمورد - فاتورة # '.$invoice->code,
                'debit' => 0,
                'credit' => $absoluteDifference,
                'is_debit' => false,
            ]);

            // تحديث رصيد الخزينة (إضافة المبلغ المُرجع)
            $mainTreasuryAccount->balance += $absoluteDifference;
            $mainTreasuryAccount->save();

            // تحديث رصيد المورد (زيادة الدين)
            $supplierAccount->balance += $absoluteDifference;
            $supplierAccount->save();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * ** التسوية من رصيد المورد ** — «عنده فلوس لنا».
     *
     * حساب المورد التزام، ورصيده `= ما استلمناه بضاعةً − ما دفعناه نقداً`.
     * فالموجب يعني أنّنا **ندين له**، والسالب وحده هو المال الذي يحتفظ به
     * المورد لنا (سُلفة سُدِّدت قبل البضاعة).
     *
     * **والمقياس ليس الرصيد وحده** بل موقعه من متبقّي هذه الفاتورة: الرصيد
     * يحمل الفاتورة نفسها مضافةً إليه سلفاً، فما دام `الرصيد < المتبقّي`
     * فالفرق مالٌ سبق أن خرج من الخزينة ويغطّي المتبقّي بلا صرفٍ جديد:
     *
     *     المغطّى = max(0, المتبقّي − رصيد المورد)
     *
     * ولهذا لا يتغيّر شيء في الدفعة العادية: مورّدٌ بلا سُلفة رصيدُه يساوي
     * متبقّي فواتيره أو يزيد، فيعود المغطّى صفراً ويصير المبلغ كلّه نقداً.
     *
     * **وما غطّاه الرصيد ليس دفعاً**: لا ريال يخرج من الخزينة، ولا قيد له —
     * قيدُ الفاتورة نفسه جعل المورد دائناً بقيمتها فقاصّ رصيدَه المدين. وهو
     * نفس ما تفعله `InvoicesPurchaseController::store()` عند الإنشاء.
     *
     * @param  float  $amount  مبلغ الدفعة كما أدخله المستخدم
     * @param  float  $remaining  متبقّي الفاتورة قبل هذه الدفعة
     * @param  float  $supplierBalance  رصيد حساب المورد قبل هذه الدفعة
     * @return array{settled: float, cash: float}
     */
    private function splitAgainstSupplierCredit(float $amount, float $remaining, float $supplierBalance): array
    {
        $covered = max(0.0, $remaining - $supplierBalance);
        $settled = min($covered, $amount);

        return [
            'settled' => round($settled, 2),
            'cash' => round($amount - $settled, 2),
        ];
    }

    /** النقد الفعليّ في دفعةٍ محفوظة — المبلغ ناقص ما سُوِّي من رصيد المورد. */
    private function paymentCash(PaymentsProcess $payment): float
    {
        return round((float) $payment->amount - (float) ($payment->credit_settled ?? 0), 2);
    }

    /** سطر الملاحظة الذي يُظهر التسوية، فلا تبقى فرقاً صامتاً بين المبلغ والنقد. */
    private function settlementNote(array $split): string
    {
        if ($split['settled'] <= 0) {
            return '';
        }

        return "\nسُوِّي من رصيد المورد: ".number_format($split['settled'], 2)
            .' ر.س — بلا صرفٍ من الخزينة'
            .($split['cash'] > 0 ? ' | نقداً من الخزينة: '.number_format($split['cash'], 2).' ر.س' : '');
    }

    public function store(ClientPaymentRequest $request)
    {
        try {
            DB::beginTransaction();

            // استرجاع البيانات المصادق عليها
            $data = $request->validated();

            /* الفترة المحاسبية المقفلة تردّ السداد.
             *
             * سندُ الصرف يكتب قيداً كالفاتورة تماماً، وكانت شاشة المدفوعات
             * وحدها في المشتريات بلا هذا الفحص إطلاقاً: تُسدَّد فاتورةٌ
             * بتاريخ شهرٍ مُقفَل فيتغيّر رصيدُ الخزينة بعد إصدار ميزانها.
             */
            if ($block = $this->accountingPeriodBlock($data['payment_date'] ?? now())) {
                DB::rollBack();

                return back()->withInput()->with('error', $block);
            }

            // التحقق من وجود الفاتورة وجلب تفاصيلها
            $invoice = PurchaseInvoice::findOrFail($data['purchases_id']);
            $this->authorizeCreatorScope($invoice->created_by);

            // الفاتورة المرتجعة لا تُسدَّد — الفحص يُعاد هنا لأن الحفظ يصل من
            // نافذةٍ فُتحت قبل إنشاء المرتجع، ومن طلبٍ مباشر
            if ($return = $this->blockingReturnFor($invoice->id)) {
                DB::rollBack();

                return redirect()
                    ->route('invoicePurchases.show', $invoice->id)
                    ->with('error', $this->returnedInvoiceMessage($invoice, $return));
            }

            // التحقق من أن مبلغ الدفع لا يتجاوز المبلغ المتبقي
            $totalPreviousPayments = PaymentsProcess::where('purchases_id', $invoice->id)
                ->where('type', 'supplier payments')
                ->where('payment_status', '!=', 5) // استثناء المدفوعات الفاشلة
                ->sum('amount');

            $remainingAmount = $invoice->grand_total - $totalPreviousPayments;

            if ($data['amount'] > $remainingAmount) {
                return back()
                    ->with('error', 'مبلغ الدفع يتجاوز المبلغ المتبقي للفاتورة. المبلغ المتبقي هو: '.number_format($remainingAmount, 2))
                    ->withInput();
            }

            // تحديد الخزينة المستهدفة - دائماً من اختيار المدير في الـ form
            $mainTreasuryAccount = null;
            $treasury_id = null;

            // استخدام الخزينة المختارة من الـ form
            if ($data['treasury_id']) {
                $mainTreasuryAccount = Account::find($data['treasury_id']);
                if (! $mainTreasuryAccount) {
                    return back()
                        ->with('error', 'The selected treasury id is invalid.')
                        ->withInput();
                }
                $treasury_id = $data['treasury_id'];
            } else {
                // إذا لم يتم اختيار خزينة، استخدم الخزينة الرئيسية
                $mainTreasuryAccount = AccountingSettings::account('main_treasury');
                $treasury_id = $mainTreasuryAccount->id ?? null;
            }

            // إذا لم يتم العثور على خزينة، توقف العملية وأظهر خطأ
            if (! $mainTreasuryAccount) {
                throw new \Exception('لا توجد خزينة متاحة. يرجى التحقق من إعدادات الخزينة.');
            }

            /* ** ما تغطّيه سُلفة المورد لا يُطلَب من الخزينة **
             *
             * كان الفحص يقيس المبلغ كلّه على رصيد الخزينة ولا يسأل عن حساب
             * المورد إطلاقاً: فمن دفع لمورّده سلفاً ثمّ أراد تسديد فاتورةٍ
             * من تلك السُلفة يُرَدّ بـ«رصيد الخزينة غير كافي» — وهو لا
             * يطلب من الخزينة شيئاً. انظر `splitAgainstSupplierCredit()`.
             */
            $supplierAccount = Account::ledger()->where('supplier_id', $invoice->supplier_id)->first();
            $split = $this->splitAgainstSupplierCredit(
                (float) $data['amount'],
                (float) $remainingAmount,
                (float) ($supplierAccount->balance ?? 0)
            );

            // الخزينة تُفحص على النقد وحده
            if ($mainTreasuryAccount->balance < $split['cash']) {
                throw new \Exception('رصيد الخزينة غير كافي. الرصيد الحالي: '
                    .number_format($mainTreasuryAccount->balance, 2)
                    .' والمطلوب نقداً: '.number_format($split['cash'], 2));
            }

            // تعيين حالة الدفع الافتراضية كمسودة
            $payment_status = 3; // مسودة

            // تحديد حالة الدفع بناءً على المبلغ المدفوع والمبلغ المتبقي
            $newTotalPayments = $totalPreviousPayments + $data['amount'];

            if ($newTotalPayments >= $invoice->grand_total) {
                $payment_status = 1; // مكتمل
                $invoice->is_paid = true;
                $invoice->payment_status = 'paid'; // ✅ تحديث حالة الدفع الجديدة
                $invoice->due_value = 0;
            } else {
                $payment_status = 2; // غير مكتمل
                $invoice->is_paid = false;
                $invoice->payment_status = 'partially_paid'; // ✅ تحديث حالة الدفع الجديدة
                $invoice->due_value = $invoice->grand_total - $newTotalPayments;
            }

            // إضافة البيانات الإضافية للدفعة
            $data['payment_status'] = $payment_status;
            // ما غطّاه رصيد المورد — يحفظه الإلغاء والتعديل ليعرفا كم تحرّك نقداً
            $data['credit_settled'] = $split['settled'];
            $data['payment_number'] = $this->generatePaymentNumber();
            $data['treasury_id'] = $treasury_id;
            $data['supplier_id'] = $invoice->supplier_id;
            $data['purchases_id'] = $invoice->id;
            $data['invoice_id'] = null; // ترك invoice_id فارغاً للدفعات الشراء
            $data['employee_id'] = Auth::id();
            $data['created_by'] = Auth::id();
            $data['type'] = 'supplier payments';

            /* طريقة الدفع: الشاشة ترسلها في `payment_type` والعمود اسمه
             * `payment_method`، وهو وحده في `$fillable`. فالمفتاح يُرمى
             * قبل أن يصل إلى الاستعلام بلا خطأ ولا تحذير: يختار
             * المستخدم «تحويل بنكي» من «طرق الدفع» ويُحفَظ السند بلا
             * طريقة دفعٍ إطلاقاً، ثمّ تعرض الشاشات شرطةً مكانها. */
            if (array_key_exists('payment_type', $data)) {
                $data['payment_method'] = $data['payment_type'];
                unset($data['payment_type']);
            }

            // معالجة المرفقات
            if ($request->hasFile('attachments')) {
                $file = $request->file('attachments');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/'), $filename);
                    $data['attachments'] = $filename;
                }
            }

            // إضافة ملاحظة تتضمن معلومات الخزينة
            $data['notes'] = ($data['notes'] ?? '')."\nالخزينة المستخدمة: ".$mainTreasuryAccount->name
                .$this->settlementNote($split);

            // إنشاء سجل الدفع
            $payment = PaymentsProcess::create($data);

            // تحديث المبلغ المدفوع في الفاتورة
            $invoice->advance_payment = $newTotalPayments;
            $invoice->save();

            // القيد على النقد وحده — التسوية من الرصيد لا قيد لها
            $this->createPaymentAccountingEntries($invoice, $split['cash'], $mainTreasuryAccount, $payment->id);

            // تسجيل إشعار النظام
            $supplier = Supplier::find($invoice->supplier_id);
            $user = Auth::user();
            ModelsLog::create([
                'type' => 'payment_supplier',
                'type_id' => $payment->id,
                'type_log' => 'log',
                'icon' => 'add',
                'description' => sprintf('تم إضافة دفعة جديدة للمورد **%s** - فاتورة رقم **%s** - المبلغ: **%s** ر.س - المتبقي: **%s** ر.س - حالة الدفع: **%s**', $supplier->trade_name ?? '', $invoice->code ?? '', number_format($data['amount'], 2), number_format($invoice->due_value, 2), $this->getPaymentStatusText($payment_status)),
                'created_by' => auth()->id(),
            ]);

            // إنشاء إشعار للنظام
            notifications::create([
                'type' => 'payment_create',
                'title' => $user->name.' قام بإضافة دفعة جديدة',
                'description' => 'تم إضافة دفعة جديدة للمورد '.($supplier->trade_name ?? '').' - فاتورة رقم '.$invoice->code.' - المبلغ: '.number_format($data['amount'], 2).' ر.س - المتبقي: '.number_format($invoice->due_value, 2).' ر.س - حالة الدفع: '.$this->getPaymentStatusText($payment_status),
            ]);

            DB::commit();

            $paymentStatusText = $this->getPaymentStatusText($payment_status);

            $successMessage = 'تم إضافة عملية الدفع بنجاح - المبلغ: '.number_format($data['amount'], 2).' ر.س، المتبقي: '.number_format($invoice->due_value, 2).' ر.س - حالة الدفع: '.$paymentStatusText;

            // التسوية لا تظهر في تفصيل الدفع، فتُقال صراحةً
            if ($split['settled'] > 0) {
                $successMessage .= ' | سُوِّي من رصيد المورد '.number_format($split['settled'], 2).' ر.س بلا صرفٍ من الخزينة';
            }

            return redirect()->route('PaymentSupplier.indexPurchase')->with('success', $successMessage);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('خطأ في إضافة عملية الدفع: '.$e->getMessage());

            return back()
                ->with('error', 'حدث خطأ أثناء إضافة عملية الدفع: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * ✅ دالة إنشاء القيود المحاسبية للدفعة
     */
    private function createPaymentAccountingEntries($invoice, $amount, $mainTreasuryAccount, $paymentId)
    {
        /* دفعةٌ غطّاها رصيد المورد بالكامل لا يتحرّك فيها شيء: لا ريال يخرج
           من الخزينة، ولا حساب المورد يتغيّر — قيدُ الفاتورة سبق أن قاصّه.
           فالقيد هنا يكون بطرفين صفريّين لا معنى لهما. */
        if (round((float) $amount, 2) <= 0) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // حساب المورد
        $supplierAccount = Account::ledger()->where('supplier_id', $invoice->supplier_id)->first();
        if (! $supplierAccount) {
            $supplier = Supplier::find($invoice->supplier_id);
            $supplierAccount = Account::create([
                'name' => 'حساب المورد - '.($supplier->trade_name ?? 'مورد غير معروف'),
                'supplier_id' => $invoice->supplier_id,
                'account_type' => 'supplier',
                'balance' => 0,
                'status' => 1,
            ]);
        }

        // إنشاء القيد المحاسبي للدفعة
        $journalEntry = JournalEntry::create([
            'reference_number' => $invoice->code.'_دفعة_'.$paymentId.'_'.time(),
            'purchase_invoice_id' => $invoice->id,
            'date' => now(),
            'description' => 'دفعة للمورد بمقدار '.number_format($amount, 2).' ر.س - فاتورة # '.$invoice->code,
            'status' => 1,
            'currency' => \App\Support\CurrencySettings::code(),
            'client_id' => $invoice->supplier_id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
            'linking_type' => LinkingType::SINGLE,
        ]);

        // حساب المورد/المؤسسة (مدين) - تسديد الدين
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $supplierAccount->id,
            'description' => 'دفعة للمورد - فاتورة # '.$invoice->code,
            'debit' => $amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // حساب الخزينة الأساسية (دائن) - خروج نقد
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $mainTreasuryAccount->id,
            'description' => 'دفعة للمورد - فاتورة # '.$invoice->code,
            'debit' => 0,
            'credit' => $amount,
            'is_debit' => false,
        ]);

        // تحديث رصيد الخزينة (سحب المبلغ)
        $mainTreasuryAccount->balance -= $amount;
        $mainTreasuryAccount->save();

        // تحديث رصيد المورد (تقليل الدين)
        $supplierAccount->balance -= $amount;
        $supplierAccount->save();

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * ✅ دالة توليد رقم الدفع
     */
    private function generatePaymentNumber()
    {
        $lastPayment = PaymentsProcess::orderBy('id', 'desc')->first();
        $nextId = $lastPayment ? $lastPayment->id + 1 : 1;

        return 'PAY-'.str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * ✅ دالة مساعدة للحصول على نص حالة الدفع
     */
    private function getPaymentStatusText($status)
    {
        switch ($status) {
            case 1:
                return 'مكتمل';
            case 2:
                return 'غير مكتمل';
            case 3:
                return 'مسودة';
            case 4:
                return 'قيد المراجعة';
            case 5:
                return 'فاشلة/ملغية';
            default:
                return 'غير معروفة';
        }
    }

    /**
     * ✅ دالة مساعدة لإلغاء الدفعة عندما تكون الفاتورة محذوفة أو غير موجودة
     */
    private function cancelPaymentWithoutInvoice($payment, $user)
    {
        try {
            DB::beginTransaction();

            // الحصول على الخزينة المستخدمة
            $mainTreasuryAccount = null;
            if ($payment->treasury_id) {
                $mainTreasuryAccount = Account::find($payment->treasury_id);
            } else {
                if ($user && $user->employee_id) {
                    $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();
                    if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                        $mainTreasuryAccount = Account::find($treasuryEmployee->treasury_id);
                    }
                }
                $mainTreasuryAccount = $mainTreasuryAccount ?? Account::where('name', 'الخزينة الرئيسية')->first();
            }

            if (! $mainTreasuryAccount) {
                throw new \Exception('لا توجد خزينة متاحة لإجراء عملية الإلغاء');
            }

            // استرداد النقد للخزينة — ما سُوِّي من رصيد المورد لم يخرج منها
            $cancelledCash = $this->paymentCash($payment);

            $mainTreasuryAccount->balance += $cancelledCash;
            $mainTreasuryAccount->save();

            // محاولة زيادة رصيد المورد إن وجد
            $supplierAccount = null;
            if ($payment->supplier_id) {
                $supplierAccount = Account::ledger()->where('supplier_id', $payment->supplier_id)->first();
                if ($supplierAccount) {
                    $supplierAccount->balance += $cancelledCash;
                    $supplierAccount->save();
                }
            }

            // حذف المرفقات
            if ($payment->attachments && file_exists(public_path('assets/uploads/'.$payment->attachments))) {
                unlink(public_path('assets/uploads/'.$payment->attachments));
            }

            // تحديث حالة الدفعة
            $payment->update([
                'payment_status' => 5,
                'notes' => ($payment->notes ?? '')."\n[تم الإلغاء (الفاتورة غير موجودة) في ".now()->format('Y-m-d H:i:s').' بواسطة '.$user->name.']',
                'cancelled_by' => $user->id,
            ]);

            // تسجيل إشعار النظام
            ModelsLog::create([
                'type' => 'payment_cancellation',
                'type_id' => $payment->id,
                'type_log' => 'log',
                'icon' => 'cancel',
                'description' => sprintf('تم إلغاء دفعة بمبلغ **%s** ر.س من خزينة **%s** (الفاتورة غير موجودة)', number_format($payment->amount, 2), $mainTreasuryAccount->name ?? ''),
                'created_by' => $user->id,
            ]);

            // إنشاء إشعار
            notifications::create([
                'type' => 'payment_cancellation',
                'title' => $user->name.' ألغى عملية دفع',
                'description' => 'تم إلغاء دفعة بمبلغ '.number_format($payment->amount, 2).' ر.س من خزينة '.$mainTreasuryAccount->name.' (الفاتورة المرتبطة غير موجودة)',
            ]);

            DB::commit();

            return redirect()
                ->route('PaymentSupplier.indexPurchase')
                ->with('success', sprintf('تم إلغاء عملية الدفع بنجاح. تم استرداد مبلغ %s ر.س إلى خزينة %s (ملاحظة: الفاتورة المرتبطة غير موجودة)', number_format($payment->amount, 2), $mainTreasuryAccount->name));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في إلغاء الدفع (بدون فاتورة): '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء إلغاء الدفع: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        PaymentsProcess::destroy($id);

        return redirect()->route('paymentsPurchase.index')->with('success', 'تم حذف عملية الدفع بنجاح');
    }

    public function getInvoiceDetails($invoice_id)
    {
        try {
            $invoice = PurchaseInvoice::findOrFail($invoice_id);
            $this->authorizeCreatorScope($invoice->created_by);

            // حساب المبلغ المدفوع والمتبقي
            $totalPayments = PaymentsProcess::where('purchases_id', $invoice->id)->where('type', 'supplier payments')->sum('amount');

            $remainingAmount = $invoice->grand_total - $totalPayments;

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice' => $invoice,
                    'total_paid' => $totalPayments,
                    'remaining_amount' => $remainingAmount,
                    'client_name' => $invoice->supplier->name ?? 'غير محدد',
                    'invoice_date' => $invoice->invoice_date,
                    'grand_total' => $invoice->grand_total,
                    'payment_status' => $invoice->payment_status,
                ],
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Purchases.PurchasePayment', 'getInvoiceDetails');

            return response()->json(
                [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء جلب تفاصيل الفاتورة: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    // دالة مساعدة لإنشاء القيد المحاسبي للدفعة
    private function createPaymentJournalEntry($invoice, $amount)
    {
        // إنشاء قيد محاسبي للدفعة
        $journalEntry = JournalEntry::create([
            'reference_number' => 'PAY-'.time(),
            'date' => now(),
            'description' => 'دفعة للفاتورة رقم '.$invoice->code,
            'status' => 1,
            'currency' => \App\Support\CurrencySettings::code(),
            'supplier_id' => $invoice->supplier_id,
            'invoice_id' => $invoice->id,
            'created_by_employee' => Auth::id(),
            'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
            'linking_type' => LinkingType::SINGLE,
        ]);

        // إضافة تفاصيل القيد
        // مدين - حساب النقدية
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $invoice->account_id, // حساب الصندوق أو البنك
            'description' => 'دفعة نقدية',
            'debit' => $amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // دائن - حساب المورد
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $invoice->supplier->account_id,
            'description' => 'تسديد دفعة',
            'debit' => 0,
            'credit' => $amount,
            'is_debit' => false,
        ]);
    }

    public function rereceipt($id)
    {
        $receipt = PaymentsProcess::findOrFail($id);
        $this->authorizeCreatorScope($receipt->employee_id);
        $type = request()->query('type', 'a4'); // افتراضي A4 إذا لم يتم تحديد النوع

        return view('purchases::purchases.supplier_payments.receipt.index_repeat', [
            'receipt' => $receipt,
            'receiptType' => $type,
        ]);
    }

    public function pdfReceipt($id)
    {
        $receipt = PaymentsProcess::findOrFail($id);
        $this->authorizeCreatorScope($receipt->employee_id);

        $pdf = Pdf::loadView('purchases::purchases.supplier_payments.receipt.pdf_receipt', compact('receipt'));

        return $pdf->stream('receipt_'.$id.'.pdf');
    }

    public function cancel($id)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $payment = PaymentsProcess::findOrFail($id);
            $this->authorizeCreatorScope($payment->employee_id);

            if ($payment->type !== 'supplier payments') {
                return back()->with('error', 'لا يمكن إلغاء هذا النوع من العمليات');
            }

            /* الإلغاء يكتب قيدَ عكسٍ بتاريخ اليوم، فيُفحَص اليومُ لا تاريخ
               السند: عكسُ سندٍ داخلَ فترةٍ أُقفلت يغيّر أرصدةً صدرت قوائمُها. */
            if ($block = $this->accountingPeriodBlock(now())) {
                DB::rollBack();

                return back()->with('error', $block);
            }

            // البحث عن الفاتورة بطرق متعددة
            $invoice = null;

            // الطريقة الأولى: استخدام invoice_id
            if ($payment->invoice_id) {
                $invoice = PurchaseInvoice::find($payment->invoice_id);
            }

            // الطريقة الثانية: استخدام purchases_id (الحقل الفعلي)
            if (! $invoice && $payment->purchases_id) {
                $invoice = PurchaseInvoice::find($payment->purchases_id);
            }

            // الطريقة الثالثة: البحث عبر supplier_id
            if (! $invoice && $payment->supplier_id) {
                $invoice = PurchaseInvoice::where('supplier_id', $payment->supplier_id)
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            // إذا لم نجد الفاتورة
            if (! $invoice) {
                DB::rollBack();

                return back()->with('error', 'لم يتم العثور على فاتورة الشراء المرتبطة بهذه الدفعة. قد تكون الفاتورة محذوفة.');
            }

            $supplier = Supplier::find($invoice->supplier_id);

            // استرجاع رصيد الخزينة
            $mainTreasuryAccount = null;
            if ($user && $user->employee_id) {
                $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();
                if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                    $mainTreasuryAccount = Account::find($treasuryEmployee->treasury_id);
                }
            }
            $mainTreasuryAccount = $mainTreasuryAccount ?? Account::where('name', 'الخزينة الرئيسية')->first();

            /* يُعاد ما خرج فعلاً لا مبلغ الدفعة كلّه: ما سُوِّي من رصيد
               المورد لم يخرج من الخزينة أصلاً، فإعادتُه تخلق نقداً من عدم
               وتُضاعف دَين المورد. */
            $cancelledCash = $this->paymentCash($payment);

            if ($mainTreasuryAccount) {
                $mainTreasuryAccount->balance += $cancelledCash; // إضافة النقد (استرجاع)
                $mainTreasuryAccount->save();
            }

            // استرجاع رصيد المورد
            $supplierAccount = Account::ledger()->where('supplier_id', $invoice->supplier_id)->first();
            if ($supplierAccount) {
                $supplierAccount->balance += $cancelledCash; // إضافة رصيد المورد (زيادة الدين)
                $supplierAccount->save();
            }

            // تحديث بيانات الفاتورة
            $totalPreviousPayments = PaymentsProcess::where('purchases_id', $invoice->id)
                ->where('type', 'supplier payments')
                ->where('payment_status', '!=', 5)
                ->where('id', '!=', $payment->id)
                ->sum('amount');

            $invoice->advance_payment = $totalPreviousPayments;
            $invoice->due_value = $invoice->grand_total - $totalPreviousPayments;

            if ($totalPreviousPayments == 0) {
                $invoice->is_paid = false;
                $invoice->payment_status = 3;
            } elseif ($totalPreviousPayments >= $invoice->grand_total) {
                $invoice->is_paid = true;
                $invoice->payment_status = 1;
                $invoice->due_value = 0;
            } else {
                $invoice->is_paid = false;
                $invoice->payment_status = 2;
            }
            $invoice->save();

            // البحث عن القيد المحاسبي الأصلي للدفعة بطرق متعددة
            $referenceNumber = $payment->reference_number ?? $invoice->code;

            /*
                **قيدُ هذه الدفعة بعينها — لا آخرُ قيدِ دفعةٍ على الفاتورة.**

                كانت الطريقةُ الأولى «آخرَ قيد دفعةٍ معتمد على الفاتورة»:
                فاتورةٌ عليها دفعتان (٨٧٠٠ ثم ٣١٥٫٥٤) يُلغى أولاهما فيُعكس قيدُ
                الثانية بمبلغها. والرابعة «آخرَ قيد دفعةٍ للمورد» — من أيّ
                فاتورةٍ كانت. والقيدُ يحمل رقمَ الدفعة في مرجعه
                (`{الفاتورة}_دفعة_{id}_…`، و`_تحديث_دفعة_` بعد تعديلها) فيُطلب
                به أوّلاً؛ وللقيود الأقدم بلا هذه الصيغة: قيدٌ على الفاتورة
                نفسها بمبلغ الدفعة نفسه إن كان واحداً، أو آخرُ قيدٍ عليها إن لم
                تكن عليها دفعةٌ غيرُ هذه. وإلا لا يُخمَّن: يُرفض الإلغاء.
            */
            $paymentEntries = fn () => JournalEntry::with('details')
                ->where('purchase_invoice_id', $invoice->id)
                ->where('reference_type', ReferenceType::SUPPLIER_PAYMENT)
                ->where('status', 1)
                ->where('is_reversed', false);

            $originalJournalEntry = $paymentEntries()
                ->where(fn ($q) => $q->where('reference_number', 'like', '%\_دفعة\_'.$payment->id.'\_%'))
                ->orderByDesc('id')
                ->first();

            if (! $originalJournalEntry) {
                $sameAmount = $paymentEntries()->get()
                    ->filter(fn ($entry) => abs((float) $entry->details->sum('debit') - (float) $payment->amount) < 0.005);

                if ($sameAmount->count() === 1) {
                    $originalJournalEntry = $sameAmount->first();
                }
            }

            if (! $originalJournalEntry) {
                $otherPayments = PaymentsProcess::where('purchases_id', $invoice->id)
                    ->where('id', '!=', $payment->id)
                    ->where('payment_status', '!=', 5)
                    ->exists();

                if (! $otherPayments) {
                    $originalJournalEntry = $paymentEntries()->orderByDesc('id')->first();
                }
            }

            if (! $originalJournalEntry && $paymentEntries()->exists()) {
                // على الفاتورة قيودُ دفعٍ ولا يُعرف أيُّها قيدُ هذه الدفعة
                DB::rollBack();

                return back()->with('error', 'تعذّر تحديد القيد المحاسبي لهذه الدفعة بعينها بين دفعات الفاتورة — لم يُلغَ شيء. ألغِها بقيد تسوية يدوي.');
            }

            // إذا لم يتم العثور على قيد، استمر مع إلغاء بدون قيد محاسبي
            if (! $originalJournalEntry) {
                // سنقوم بإلغاء الدفعة فقط دون عكس القيد المحاسبي
                Log::warning('لم يتم العثور على القيد المحاسبي للدفعة رقم: '.$payment->id.' - سيتم الإلغاء بدون عكس قيد محاسبي');
                $originalJournalEntry = null;
            }

            // إنشاء قيد عكسي بدلاً من حذف القيد الأصلي (إذا كان موجوداً)
            if ($originalJournalEntry) {
                $reversalJournalEntry = JournalEntry::create([
                    'reference_number' => 'REV-'.$originalJournalEntry->reference_number,
                    'date' => now(),
                    'description' => 'قيد عكسي لإلغاء دفعة للمورد رقم '.$invoice->code.' - ملغي من القيد رقم #'.$originalJournalEntry->id,
                    'status' => 1,
                    'currency' => \App\Support\CurrencySettings::code(),
                    'client_id' => null, // لا نستخدم client_id للموردين
                    'purchase_invoice_id' => $invoice->id,
                    'reference_type' => ReferenceType::PAYMENT_CANCELLATION,
                    'linking_type' => LinkingType::SINGLE,
                    'created_by_employee' => Auth::id(),
                ]);

                // الخطوة 1: إنشاء قيد عكسي لعملية الدفع
                foreach ($originalJournalEntry->details as $originalDetail) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $reversalJournalEntry->id,
                        'account_id' => $originalDetail->account_id,
                        'description' => 'عكس: '.$originalDetail->description,
                        'debit' => $originalDetail->credit, // عكس المبالغ
                        'credit' => $originalDetail->debit, // عكس المبالغ
                        'is_debit' => ! $originalDetail->is_debit, // عكس نوع القيد
                        'client_account_id' => $originalDetail->client_account_id,
                    ]);
                }

                /*
                    الأصلُ يبقى معتمداً ويُعلَّم «معكوساً» — كعكس الرواتب
                    (`PayrollReversalService`) وقيود الإقفال.

                    كان يُقلب إلى `status = 0`، والتقاريرُ لا تقرأ إلا المعتمد:
                    فيسقط الأصلُ ويبقى عكسُه، وتُعكس الدفعةُ مرّتين — يزيد دَينُ
                    المورد وتنقص الخزينة بمبلغها. والأصلُ وعكسُه معاً يتقاصّان.
                */
                $originalJournalEntry->forceFill([
                    'is_reversed' => true,
                    'description' => $originalJournalEntry->description.' [ملغي - القيد العكسي #'.$reversalJournalEntry->id.']',
                ])->saveQuietly();

                Log::info('تم إنشاء قيد عكسي لإلغاء الدفعة', [
                    'original_journal_id' => $originalJournalEntry->id,
                    'reversal_journal_id' => $reversalJournalEntry->id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                ]);
            } else {
                Log::warning('تم إلغاء دفعة بدون قيد محاسبي - رقم الدفعة: '.$payment->id);
            }

            // حذف الإشعارات القديمة
            notifications::where('type', 'supplier_payment')
                ->where('description', 'like', '%دفعة للمورد%')
                ->delete();

            // إنشاء إشعار إلغاء
            $reversalText = $originalJournalEntry ? 'وتم إنشاء قيد عكسي رقم '.(isset($reversalJournalEntry) ? $reversalJournalEntry->id : 'N/A') : '(بدون قيد محاسبي)';
            notifications::create([
                'type' => 'supplier_payment_cancelled',
                'title' => $user->name.' ألغى عملية دفع',
                'description' => 'تم إلغاء عملية دفع للمورد '.($supplier->trade_name ?? 'غير محدد').' - فاتورة رقم '.$invoice->code.' بقيمة '.number_format($payment->amount, 2).' ر.س '.$reversalText,
            ]);

            // حذف المرفقات
            if ($payment->attachments && file_exists(public_path('assets/uploads/'.$payment->attachments))) {
                unlink(public_path('assets/uploads/'.$payment->attachments));
            }

            // تحديث حالة الدفعة بدلاً من حذفها (للاحتفاظ بالسجل)
            $payment->update([
                'payment_status' => 5, // حالة ملغية
                'notes' => ($payment->notes ?? '').' - تم إلغاء هذه الدفعة في '.now()->format('Y-m-d H:i:s').' بواسطة '.$user->name,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
            ]);

            // تحديث الرصيد المستحق في الفاتورة بعد عكس القيود
            $invoice->refresh();
            $totalPreviousPayments = PaymentsProcess::where('purchases_id', $invoice->id)
                ->where('type', 'supplier payments')
                ->where('payment_status', '!=', 5)
                ->sum('amount');
            $invoice->advance_payment = $totalPreviousPayments;
            $invoice->due_value = $invoice->grand_total - $totalPreviousPayments;
            if ($totalPreviousPayments == 0) {
                $invoice->is_paid = false;
                $invoice->payment_status = 3;
            } elseif ($totalPreviousPayments >= $invoice->grand_total) {
                $invoice->is_paid = true;
                $invoice->payment_status = 1;
                $invoice->due_value = 0;
            } else {
                $invoice->is_paid = false;
                $invoice->payment_status = 2;
            }
            $invoice->save();

            DB::commit();

            $successText = $originalJournalEntry
                ? sprintf('تم إلغاء عملية الدفع بنجاح وإنشاء قيد عكسي رقم %d. تم استرداد مبلغ %s ر.س، المبلغ المستحق للفاتورة الآن: %s ر.س',
                    isset($reversalJournalEntry) ? $reversalJournalEntry->id : 'N/A',
                    number_format($payment->amount, 2),
                    number_format($invoice->due_value, 2)
                )
                : sprintf('تم إلغاء عملية الدفع بنجاح (بدون قيد محاسبي). تم استرداد مبلغ %s ر.س، المبلغ المستحق للفاتورة الآن: %s ر.س',
                    number_format($payment->amount, 2),
                    number_format($invoice->due_value, 2)
                );

            return redirect()
                ->route('PaymentSupplier.indexPurchase')
                ->with('success', $successText);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في إلغاء الدفع: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء إلغاء الدفع: '.$e->getMessage());
        }
    }

    /**
     * عرض صفحة الدفع المجمع للفواتير
     */
    /**
     * الدفع المجمّع — يُفتَح من شاشة المورّد وحدها.
     *
     * الشاشة تسدّد فواتيرَ مورّدٍ بعينه، فبلا مورّدٍ محدَّد تفتح نموذجاً
     * فارغاً يبدأ باختيارٍ لا معنى له. المدخل صار زرَّ «دفع مجمَّع» في شاشة
     * المورّد، وأُزيل من قائمة المدفوعات تبعاً لذلك.
     */
    public function bulkPaymentCreate(Request $request)
    {
        $user = auth()->user();

        if (! $request->filled('supplier_id')) {
            return redirect()
                ->route('SupplierManagement.index')
                ->with('info', __('purchase_cycle.payment.from_supplier_only'));
        }

        // if (!canAny(['purchases_add_payments_all', 'purchases_add_payments_own'])) {
        //     abort(403, 'غير مصرح لك بإضافة مدفوعات');
        // }

        // المورّد وحده — القائمة مقفلة عليه في الشاشة، فلا داعي لتحميل
        // الموردين جميعاً في `select` لا يُبدَّل
        $suppliers = Supplier::where('id', $request->supplier_id)->get();
        $suppliers->each(fn ($supplier) => $this->authorizeCreatorScope($supplier->created_by));

        if ($suppliers->isEmpty()) {
            return redirect()
                ->route('SupplierManagement.index')
                ->with('error', __('purchase_cycle.empty.unknown'));
        }

        // جلب طرق الدفع
        $paymentMethods = PaymentMethod::where('status', 'active')->get();

        // تحديد الخزينة
        $mainTreasuryAccount = null;
        $treasury_id = null;

        if ($user && $user->employee_id) {
            $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();
            if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                $mainTreasuryAccount = Account::where('id', $treasuryEmployee->treasury_id)->first();
                $treasury_id = $treasuryEmployee->treasury_id;
            }
        }

        if (! $mainTreasuryAccount) {
            $mainTreasuryAccount = AccountingSettings::account('main_treasury');
            $treasury_id = $mainTreasuryAccount->id ?? null;
        }

        $account_setting = AccountSetting::where('user_id', $user->id)->first();

        // تحديد المورد المحدد مسبقاً (إذا تم تمريره من صفحة المورد)
        $selected_supplier_id = $request->get('supplier_id');

        // كل الخزائن المتاحة من «الحسابات المحاسبية» — كانت القائمة تحمل
        // الخزينة الرئيسية وحدها، فلا يستطيع المستخدم الدفع من غيرها
        $treasuries = Account::whereIn('parent_id', array_filter([
            AccountingSettings::id('bank_accounts_parent'),
            AccountingSettings::id('cash_treasuries_parent'),
        ]))->orderBy('name')->get();

        return view('purchases::purchases.supplier_payments.bulk_payment', compact(
            'suppliers',
            'paymentMethods',
            'mainTreasuryAccount',
            'treasury_id',
            'treasuries',
            'account_setting',
            'selected_supplier_id'
        ));
    }

    /**
     * جلب الفواتير المستحقة لمورد معين عبر AJAX
     */
    public function getSupplierUnpaidInvoices(Request $request)
    {
        $supplierId = $request->supplier_id;

        if (! $supplierId) {
            return response()->json(['invoices' => [], 'total_due' => 0]);
        }

        $user = auth()->user();

        // جلب الفواتير المستحقة للمورد
        // payment_status يستخدم قيم نصية: 'unpaid', 'partially_paid', 'paid', 'draft'
        $query = PurchaseInvoice::where('supplier_id', $supplierId)
            // **النوع لازم.** الجدول موحّد يحمل أربعة أنواع، وبلا هذا القيد
            // تظهر المرتجعاتُ والإشعاراتُ المدينة وأوامرُ الشراء في قائمة
            // «الفواتير المستحقة» — فيُسدَّد للمورّد ما ليس فاتورةً أصلاً
            ->where('type', 'invoice')
            ->where('due_value', '>', 0)
            ->whereIn('payment_status', ['unpaid', 'partially_paid', 'draft']) // الفواتير غير المدفوعة أو المدفوعة جزئياً
            // الفاتورة التي رُدَّت لا تُسدَّد: أثرها عُكِس بالمرتجع، فلا تُعرَض
            // في المستحقّات — وهو القيد نفسه الذي تطبّقه شاشة فواتير الشراء
            ->whereDoesntHave('relatedDocuments', fn ($q) => $q->where('type', 'Return'))
            ->orderBy('date', 'asc'); // الأقدم أولاً

        /*
         * نطاق البيانات من الصلاحيات — وكان هنا خطأٌ يتجاوز اسمَ الدور:
         *
         *     if ($user->isEmployee()) { $query->where('employee_id', …); }
         *
         * وجدول `purchase_invoices` **لا يحمل عمود `employee_id` إطلاقاً**
         * (منشئُ الفاتورة في `created_by`). فأيّ مستخدمٍ دورُه «employee»
         * كان يُسقط هذه الشاشة بخطأ SQL: «Unknown column employee_id» —
         * أي أن قائمة «الفواتير المستحقة» في السداد المجمَّع لا تعمل له
         * أصلاً. وبقي كامناً لأن لا أحد من المستخدمين الحاليين بهذا الدور.
         */
        $query = $this->scopeToCreator($query);

        $invoices = $query->get();

        $formattedInvoices = $invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'code' => $invoice->code,
                'date' => $invoice->date ? date('Y-m-d', strtotime($invoice->date)) : '-',
                'grand_total' => $invoice->grand_total,
                'advance_payment' => $invoice->advance_payment ?? 0,
                'due_value' => $invoice->due_value,
                'payment_status' => $invoice->payment_status,
            ];
        });

        $totalDue = $invoices->sum('due_value');

        return response()->json([
            'invoices' => $formattedInvoices,
            'total_due' => $totalDue,
        ]);
    }

    /**
     * تنفيذ الدفع المجمع للفواتير
     */
    public function bulkPaymentStore(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:purchase_invoices,id',
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'treasury_id' => 'required|exists:accounts,id',
        ], [
            'supplier_id.required' => 'يرجى اختيار المورد',
            'invoice_ids.required' => 'يرجى اختيار فاتورة واحدة على الأقل',
            'invoice_ids.min' => 'يرجى اختيار فاتورة واحدة على الأقل',
            'payment_amount.required' => 'يرجى إدخال مبلغ الدفع',
            'payment_amount.min' => 'يجب أن يكون المبلغ أكبر من صفر',
            'payment_date.required' => 'يرجى تحديد تاريخ الدفع',
            'treasury_id.required' => 'يرجى اختيار الخزينة',
        ]);

        try {
            DB::beginTransaction();

            $user = auth()->user();
            $supplierId = $request->supplier_id;
            $invoiceIds = $request->invoice_ids;
            $totalPaymentAmount = floatval($request->payment_amount);
            $paymentDate = $request->payment_date;

            // الفترة المحاسبية المقفلة تردّ السداد الجماعي كما تردّ المفرد —
            // القيدُ واحدٌ في الحالتين
            if ($block = $this->accountingPeriodBlock($paymentDate)) {
                DB::rollBack();

                return back()->withInput()->with('error', $block);
            }

            $treasuryId = $request->treasury_id;
            $paymentMethodId = $request->payment_method;
            $referenceNumber = $request->reference_number;
            $notes = $request->notes;

            // جلب المورد
            $supplier = Supplier::findOrFail($supplierId);
            $this->authorizeCreatorScope($supplier->created_by);

            // جلب الخزينة
            $treasuryAccount = Account::findOrFail($treasuryId);

            // جلب حساب المورد
            $supplierAccount = Account::ledger()->where('supplier_id', $supplierId)->first();
            if (! $supplierAccount) {
                throw new \Exception('لم يتم العثور على حساب المورد');
            }

            // جلب الفواتير المحددة مرتبة بالأقدم
            $invoices = PurchaseInvoice::whereIn('id', $invoiceIds)
                ->where('supplier_id', $supplierId)
                ->where('due_value', '>', 0)
                ->orderBy('date', 'asc')
                ->get();

            if ($invoices->isEmpty()) {
                throw new \Exception('لم يتم العثور على فواتير مستحقة');
            }

            $remainingAmount = $totalPaymentAmount;
            $paymentsCreated = [];
            $invoicesUpdated = [];
            $totalSettled = 0.0;

            /* رصيد المورد يُتابَع محلّياً عبر الفواتير: القيد المجمَّع لا
               يحفظ الرصيد بعد كل فاتورة، فقراءته من الحساب في كل دورة
               تُعطي الرقم نفسه وتُحسَب السُلفة مرّةً لكل فاتورة. */
            $runningSupplierBalance = (float) $supplierAccount->balance;

            foreach ($invoices as $invoice) {
                if ($remainingAmount <= 0) {
                    break;
                }

                // حساب المبلغ المستحق للفاتورة
                $invoiceDue = floatval($invoice->due_value);

                // تحديد المبلغ المدفوع لهذه الفاتورة
                $paymentForInvoice = min($remainingAmount, $invoiceDue);

                if ($paymentForInvoice > 0) {
                    // ما تغطّيه سُلفة المورد من هذه الفاتورة لا يخرج من الخزينة
                    $split = $this->splitAgainstSupplierCredit(
                        (float) $paymentForInvoice,
                        $invoiceDue,
                        $runningSupplierBalance
                    );

                    if ($treasuryAccount->balance < $split['cash']) {
                        throw new \Exception('رصيد الخزينة غير كافي لفاتورة '.$invoice->code
                            .'. الرصيد الحالي: '.number_format($treasuryAccount->balance, 2)
                            .' والمطلوب نقداً: '.number_format($split['cash'], 2));
                    }

                    $runningSupplierBalance -= $split['cash'];
                    $totalSettled += $split['settled'];

                    // إنشاء سجل الدفع
                    $payment = PaymentsProcess::create([
                        'type' => 'supplier payments',
                        'purchases_id' => $invoice->id,
                        'supplier_id' => $supplierId,
                        'amount' => $paymentForInvoice,
                        'credit_settled' => $split['settled'],
                        'payment_date' => $paymentDate,
                        'payment_method_id' => $paymentMethodId,
                        'treasury_id' => $treasuryId,
                        'reference_number' => $referenceNumber,
                        'notes' => $notes.' - دفع مجمع'.$this->settlementNote($split),
                        // `employee_id` مفتاح أجنبي إلى `users.id` (انظر
                        // `PaymentsProcess::employee()` وتعليق النظير في
                        // create.blade.php) لا إلى `employees.id`، فـ`$user->employee_id`
                        // كان يكتب معرّف سجلّ موظّفٍ من جدولٍ آخر لأي مستخدمٍ
                        // مرتبطٍ فعلاً بسجلّ موظّف
                        'employee_id' => $user->id,
                        'payment_status' => 1, // مكتمل
                        'branch_id' => $invoice->branch_id ?? auth()->user()->branch_id,
                    ]);

                    // تحديث الفاتورة
                    $newAdvancePayment = floatval($invoice->advance_payment ?? 0) + $paymentForInvoice;
                    $newDueValue = floatval($invoice->grand_total) - $newAdvancePayment;

                    // تحديد حالة الدفع الجديدة (قيم نصية)
                    $newPaymentStatus = $newDueValue <= 0 ? 'paid' : 'partially_paid';

                    $invoice->update([
                        'advance_payment' => $newAdvancePayment,
                        'due_value' => max(0, $newDueValue),
                        'payment_status' => $newPaymentStatus,
                    ]);

                    // القيد على النقد وحده — التسوية من الرصيد لا قيد لها
                    $this->createBulkPaymentAccountingEntry($invoice, $split['cash'], $treasuryAccount, $supplierAccount, $payment->id, $paymentDate);

                    $paymentsCreated[] = $payment;
                    $invoicesUpdated[] = [
                        'code' => $invoice->code,
                        'paid' => $paymentForInvoice,
                        'remaining' => max(0, $newDueValue),
                    ];

                    $remainingAmount -= $paymentForInvoice;
                }
            }

            // تحديث رصيد المورد

            DB::commit();

            // إنشاء رسالة النجاح
            $successMessage = sprintf(
                'تم تنفيذ الدفع المجمع بنجاح - المبلغ: %s ر.س - عدد الفواتير: %d',
                number_format($totalPaymentAmount, 2),
                count($invoicesUpdated)
            );

            if ($totalSettled > 0) {
                $successMessage .= ' | سُوِّي من رصيد المورد '.number_format($totalSettled, 2).' ر.س بلا صرفٍ من الخزينة';
            }

            return redirect()
                ->route('PaymentSupplier.indexPurchase')
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('خطأ في الدفع المجمع للمورد: '.$e->getMessage());

            return back()->with('error', 'حدث خطأ أثناء تنفيذ الدفع: '.$e->getMessage())->withInput();
        }
    }

    /**
     * إنشاء القيد المحاسبي للدفع المجمع
     */
    private function createBulkPaymentAccountingEntry($invoice, $amount, $treasuryAccount, $supplierAccount, $paymentId, $paymentDate)
    {
        // ما غطّاه رصيد المورد لا يتحرّك فيه شيء — فلا قيد بطرفين صفريّين
        if (round((float) $amount, 2) <= 0) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

        // توليد رقم مرجعي للقيد
        $lastEntry = JournalEntry::orderBy('id', 'desc')->first();
        $nextNum = $lastEntry ? $lastEntry->id + 1 : 1;
        $referenceNumber = 'PAY-'.date('Y').'-'.str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        // إنشاء القيد المحاسبي
        $journalEntry = JournalEntry::create([
            'date' => $paymentDate,
            'description' => 'دفع مجمع للمورد - فاتورة رقم: '.$invoice->code,
            'reference_type' => ReferenceType::SUPPLIER_PAYMENT,
            'reference_id' => $paymentId,
            'reference_number' => $referenceNumber,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'employee_id' => auth()->user()->employee_id ?? auth()->user()->id,
            'branch_id' => $invoice->branch_id ?? auth()->user()->branch_id,
            'status' => 1,
            'linking_type' => LinkingType::SINGLE,
        ]);

        // حساب المورد (مدين) - تسديد الدين
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $supplierAccount->id,
            'debit' => $amount,
            'credit' => 0,
            'is_debit' => true,
        ]);

        // حساب الخزينة (دائن) - خروج نقد
        JournalEntryDetail::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $treasuryAccount->id,
            'debit' => 0,
            'credit' => $amount,
            'is_debit' => false,
        ]);

        // تحديث رصيد الخزينة (سحب المبلغ)

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
