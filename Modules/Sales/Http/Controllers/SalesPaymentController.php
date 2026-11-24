<?php

namespace Modules\Sales\Http\Controllers;

use App\Constants\planFeatures\SalesPlanFeatures;
use App\Enums\LinkingType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ClientPaymentRequest;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Client;
use App\Models\ClientPaymentNotice;
use App\Models\Employee;
use App\Models\Installment;
use App\Models\InstallmentDetail;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\Log as ModelsLog;
use App\Models\notifications;
use App\Models\PaymentMethod;
use App\Models\PaymentsProcess;
use App\Models\Treasury;
use App\Models\TreasuryEmployee;
use App\Models\User;
use App\Services\Clients\ClientVisibilityScope;
use App\Services\PointsService;
use App\Services\Installments\InstallmentCollections;
use App\Services\Installments\InstallmentFinancing;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\DocumentPrintPage;
use App\Support\OperationLog;
use App\Support\PartialPaymentRule;
use App\Support\PrintSettings;
use App\Support\TreasuryAccessGuard;
use App\Traits\AssignedEmployeeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\DocumentAttachments;
use Modules\Sales\Support\PaymentRecorder;
use TCPDF;

/**
 * مدفوعات العملاء — سندات القبض.
 *
 * نظيرُ «مدفوعات الموردين» في المشتريات
 * (`Modules\Purchases\Http\Controllers\PurchasePaymentController`)،
 * وكانت تفترق عنه في كل ما تقرؤه من إعدادات: لا فترةً محاسبية تُفحَص،
 * ولا رقماً من «تسلسل أرقام المستندات»، ولا عملةً من «العملات»
 * (`'currency' => 'SAR'` مكتوبةً في القيد)، ولا سطراً في سجلّ النشاط،
 * ولا قالبَ طباعةٍ من «قوالب المستندات».
 *
 * ما تأتي به الإعدادات الآن:
 *
 *   • *الرقم*        ← «تسلسل أرقام المستندات»، القسم `invoice-payment`.
 *   • *العملة*       ← «العملات»، وتُجمَّد مع السند.
 *   • *الحسابات*     ← «الحسابات المحاسبية»: خزينة الموظّف أوّلاً، ثمّ
 *                      `main_treasury` المضبوطة — لا معرّفاً مكتوباً.
 *   • *الإقفال*      ← «الفترات المحاسبية»: سندٌ بتاريخٍ في فترةٍ أُقفلت
 *                      يُردّ قبل أن يُرحَّل قيده.
 *   • *القوالب*      ← «قوالب المستندات»، النوع `receipt_voucher`.
 *   • *طرق الدفع*    ← «طرق الدفع» وخزائنها.
 *   • *السجلّات*     ← `OperationLog`: النشاط والأحداث والأخطاء.
 *   • *اللغة والمظهر* ← عبر القوالب: `sl-*` مبنيّة على `--app-*`.
 */
class SalesPaymentController extends Controller
{
    use AssignedEmployeeAccess;
    use Concerns\ChecksSubscriptionLimits;
    use Concerns\GeneratesDocumentNumbers;
    use Concerns\RendersSalesDocumentTemplates;
    use Concerns\ResolvesAccountingAccounts;
    use Concerns\RespectsAccountingPeriods;
    use Concerns\ScopesSalesVisibility;
    use Concerns\SendsSalesDocumentEmails;
    use Concerns\UsesDocumentCurrency;

    /** نوع المستند في «قوالب المستندات» — سند قبض من عميل */
    public const DOCUMENT_TYPE = 'receipt_voucher';

    /* مصدرُ بيانات المستند — يخالف نوعَ القالب عمداً: السندُ هنا صفٌّ في
       `payments_process`، بينما `receipt_voucher` يقرأ من `receipts`. */
    public const DATA_TYPE = 'client_payment';

    /** قسم الترقيم المتسلسل الذي تُؤخذ منه أرقام سندات القبض */
    public const NUMBER_SECTION = 'invoice-payment';

    /** قيمة العمود `logs.type` — بها يُجمَع الخطّ الزمني للسند */
    public const LOG_MODULE = 'SalesPayments';

    public function __construct()
    {
        // مُنقولة إلى كتالوج sales.payments.* — نفس نمط InvoicesPurchaseController
        $this->middleware('permission:sales.payments.view', ['only' => ['index', 'show', 'getInvoiceDetails', 'getClientUnpaidInvoices']]);
        $this->middleware('permission:sales.payments.create', ['only' => ['create', 'store']]);
        $this->middleware('permission:sales.payments.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:sales.payments.delete', ['only' => ['destroy']]);
        $this->middleware('permission:sales.payments.cancel', ['only' => ['cancel']]);
        /* الإرسالُ بالبريد على صلاحية الطباعة لا على صلاحيةٍ جديدة:
           كلاهما يُخرج السندَ نفسَه إلى خارج الشاشة، و`permissions:sync`
           لا يربط الصلاحية الجديدة بأيّ دور — فيبقى الزرّ مخفياً عن
           الجميع حتى تُربَط يدوياً. */
        $this->middleware('permission:sales.payments.print', ['only' => ['rereceipt', 'pdfReceipt', 'sendReceiptEmail']]);
        $this->middleware('permission:sales.payments.bulk', ['only' => ['bulkPaymentCreate', 'bulkPaymentStore']]);
    }

    public function index(Request $request)
    {
        /* نطاق التاريخ الافتراضي هو **الفترة المحاسبية الجارية** — نفس ما
           تفعله شاشة الفواتير والمرتجع والإشعارات الدائنة، على `payment_date`
           هنا. كانت شاشة المدفوعات وحدَها تفتح على كل سندٍ سُجّل منذ
           التأسيس، فبطاقةُ «إجمالي المحصَّل» تجمع سنواتٍ أُقفلت. */
        $range = $this->reportDateRange($request, 'from_date', 'to_date');

        // استعلام أساسي لجميع البيانات
        /* الأنواع الثلاثة لا نوعٌ واحد.
         *
         * `store()` يكتب `client payments`، والصفوف الأقدم في القاعدة نوعُها
         * `sale`، ودفعةُ التوقيع على الفاتورة تكتب `client payments` أيضاً.
         * والقيد على اسمٍ واحد يُخفي أكثر المدفوعات: خمسة عشر صفّاً من تسعة
         * عشر لا تظهر في الشاشة إطلاقاً. */
        /* `employee.employee`: اسمُ من سجّل السند يُقرأ من ملفّ الموظف
           (`PaymentsProcess::$recorded_by_name`) — بلا هذا السطر استعلامٌ
           إضافيّ لكلّ صفّ. */
        $query = PaymentsProcess::with(['invoice.client', 'employee.employee', 'client', 'treasuryAccount', 'payment_type'])
            ->whereIn('type', ['client payments', 'sale']);

        /* الفرع النشط من الهيدر.
           شاشةُ المدفوعات كانت وحدَها بلا فرعٍ إطلاقاً — لا في العمود ولا في
           الفلتر — فيرى مَن اختار فرعاً في الهيدر سنداتِ المنشأة كلّها.

           **والفرعُ الرئيسيّ مستثنىً** كما في شاشة الفواتير والمرتجعات
           والإشعارات الدائنة: هو مركزُ المنشأة لا فرعٌ من فروعها، فمن يقف
           عليه يرى المدفوعات كلَّها. وكان التصفيةُ هنا بلا هذا الاستثناء
           وحدَها: الشريطُ يقف على الرئيسيّ (٦) وسنداتُه واحد، فتفتح شاشةُ
           «مدفوعات العملاء» على سندٍ واحدٍ من ٧٩٦٥ — بلا خطأٍ ولا رسالة. */
        $this->applyHeaderBranchScope($query);

        $user = auth()->user();

        /* ── نطاق ما يراه المستخدم ──

           السلَّمُ الخماسيّ لا حالتان: الكلّ، أو فرعُه، أو فريقُه، أو
           سنداتُه هو (`ScopesSalesVisibility`).

           و`payments_process` جدولٌ خاصّ: لا عمود `created_by` فيه إطلاقاً،
           و`employee_id` فيه يحمل **`users.id`** لا `employees.id`
           (`store()` يكتب `$user->id`). وكان هذا السطر يقابله بـ
           `$user->employee_id` — رقمُ الموظف في عمودِ مستخدم، فيصمت أو
           يُطابق سندَ غيره مصادفةً. ومع ذلك فالعمودُ فارغٌ في ٩٢٩٤ سنداً من
           ٩٤٠٥، فيبقى محورُ الفاتورة (من أنشأها ومن يُنسب إليه بيعُها) هو
           ما يُظهر أكثرَ السندات لأصحابها. */
        if ($this->salesScopeApplies()) {
            if (hasPermission('sales.payments.view_assigned_clients')) {
                // عرض المدفوعات للعملاء المعينين فقط
                $query->whereHas('invoice', function ($q) use ($user) {
                    $q->whereHas('client.employees', function ($subQ) use ($user) {
                        $subQ->where('employee_id', $user->employee_id);
                    });
                });
            } else {
                $query->where(function ($q) {
                    $this->scopeSalesDocuments($q, null, 'employee_id', 'user', null);

                    $q->orWhereHas('invoice', function ($subQuery) {
                        $this->scopeSalesDocuments($subQuery);
                    });
                });
            }
        }

        // قاعدةُ منسدلتَي «سجَّلها» و«مسؤول المبيعات» — قبل فلاتر البحث
        $optionsBase = clone $query;

        // البحث الأساسي
        if ($request->filled('invoice_number')) {
            $query->whereHas('invoice', function ($q) use ($request) {
                $q->where('code', 'like', '%'.$request->invoice_number.'%');
            });
        }

        if ($request->filled('payment_number')) {
            $query->where('id', 'like', '%'.$request->payment_number.'%');
        }

        if ($request->filled('customer')) {
            $query->where('client_id', $request->customer);
        }

        // البحث المتقدم
        /* النموذج يُرسل `status_payment` والمتحكّم كان يقرأ `payment_status`
           وحدَه — ففلترُ «الحالة» في الشاشة لا يفعل شيئاً. يُقبل الاسمان. */
        $paymentStatus = $request->input('status_payment', $request->input('payment_status'));

        if ($paymentStatus !== null && $paymentStatus !== '') {
            $query->where('payment_status', $paymentStatus);
        }

        // فلتر التخصيص (customization) - يمكن ربطه بحقل معين حسب المتطلبات
        if ($request->filled('customization')) {
            // يمكن تخصيص هذا الفلتر حسب المتطلبات
            if ($request->customization == '1') { // شهريًا — الشهرُ من هذه السنة لا من كلّ سنة
                $query->whereYear('payment_date', now()->year)->whereMonth('payment_date', now()->month);
            } elseif ($request->customization == '0') { // أسبوعيًا
                $query->whereBetween('payment_date', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($request->customization == '2') { // يوميًا
                $query->whereDate('payment_date', now()->toDateString());
            }
        }

        /* النطاقُ مطبَّقٌ دائماً: الحقلان فارغَين كانا يعنيان «بلا حدّ».

           ويتنحّى أمام «التخصيص» أعلاه — من اختار «يومياً» طلب اليوم صراحةً،
           فلا يُقصّ اختيارُه بحدّي الفترة. */
        if (! $request->filled('customization')) {
            $query->whereDate('payment_date', '>=', $range['start'])
                ->whereDate('payment_date', '<=', $range['end']);
        }

        if ($request->filled('identifier')) {
            $query->where('reference_number', 'like', '%'.$request->identifier.'%');
        }

        if ($request->filled('transfer_id')) {
            $query->where('reference_number', 'like', '%'.$request->transfer_id.'%');
        }

        if ($request->filled('total_greater_than')) {
            $query->where('amount', '>', $request->total_greater_than);
        }

        if ($request->filled('total_less_than')) {
            $query->where('amount', '<', $request->total_less_than);
        }

        /* «بحث» يَعِد في خانته برقم السند والفاتورة واسم العميل، وكان يبحث في
           الملاحظات وحدَها. العميلُ من السند نفسِه أو من فاتورته: ٩٠٨ سندات
           بلا `client_id` واسمُ عميلها على الفاتورة. */
        if ($request->filled('custom_field')) {
            $term = trim((string) $request->custom_field);
            $like = '%'.$term.'%';
            $clientName = function ($q) use ($like) {
                $q->where('trade_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            };

            $query->where(function ($q) use ($term, $like, $clientName) {
                $q->where('notes', 'like', $like)
                    ->orWhere('reference_number', 'like', $like)
                    ->orWhereHas('client', $clientName)
                    ->orWhereHas('invoice', function ($invoice) use ($like, $clientName) {
                        $invoice->where('code', 'like', $like)
                            ->orWhereHas('client', $clientName);
                    });

                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if ($request->filled('invoice_origin')) {
            $query->whereHas('invoice', function ($q) use ($request) {
                $q->where('created_by', $request->invoice_origin);
            });
        }

        if ($request->filled('collected_by')) {
            $query->where('employee_id', $request->collected_by);
        }

        /* مسؤول المبيعات — لا عمودَ له في السند، فهو مسؤولُ الفاتورة
           المسدَّدة (`invoices.employee_id`). */
        if ($request->filled('sales_person_user')) {
            $query->whereHas('invoice', fn ($invoice) => $invoice->where('employee_id', $request->sales_person_user));
        }

        // فلتر الفرع — من عمود السند نفسه
        if ($request->filled('branch')) {
            $query->forBranch($request->branch);
        }

        // فلتر الخزينة
        if ($request->filled('treasury_id')) {
            $query->where('treasury_id', $request->treasury_id);
        }

        // فلتر طريقة الدفع
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        /* فلتر العميل — عميلُ السند نفسِه **أو** عميلُ فاتورته.
           كان من الفاتورة وحدَها، فسندُ دفعةٍ مقدّمة أو رصيدٍ افتتاحيّ بلا
           فاتورة يسقط من نتيجة عميله وهو ظاهرٌ في الجدول باسمه. */
        if ($request->filled('client_id')) {
            $clientId = (int) $request->client_id;

            $query->where(function ($q) use ($clientId) {
                $q->where('client_id', $clientId)
                    ->orWhereHas('invoice', fn ($invoice) => $invoice->where('client_id', $clientId));
            });
        }

        /* الملخّص قبل التقطيع: `paginate` يقصّ النتيجة، فجمعُ الصفحة وحدها
           يعطي «إجمالي المحصَّل» صفحةً لا مدّةً. */
        $summaryQuery = (clone $query);
        $collectedTotal = (float) (clone $summaryQuery)->where('payment_status', '!=', 5)->sum('amount');
        $collectedCount = (clone $summaryQuery)->count();
        $collectedToday = (float) (clone $summaryQuery)
            ->where('payment_status', '!=', 5)
            ->whereDate('payment_date', today())
            ->sum('amount');

        // عدد السجلّات من قائمة مغلقة — كان يُقرأ من الطلب بلا حدّ
        $perPage = (int) $request->input('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        /* الأحدثُ تسجيلاً أوّلاً — بترتيب الإنشاء لا بتاريخ السند.
           كان الترتيب على `payment_date` أوّلاً، وهو تاريخٌ يكتبه المستخدم
           (وقد يؤرّخ سندَه بيوم الفاتورة)، فالسندُ الذي سُجّل قبل دقيقة
           يهبط تحت سنداتٍ أقدمَ منه لأنّ تاريخها المكتوب أحدث. و`id`
           تصاعديٌّ دائماً، فتنازليُّه هو «آخر عمليةٍ أوّلاً» حرفياً. */
        // `appends` وإلّا أسقط التنقّل بين الصفحات كلَّ الفلاتر
        $payments = $query->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->except('page'));

        /* «سجَّلها» من المستند الأصل — سندِ القبض أو الفاتورةِ أو الإشعار
           أو الشيك — لا من عمود السند وحده (انظر `PaymentRecorder`). */
        $recorderNames = PaymentRecorder::names($payments->getCollection());

        // جلب البيانات المطلوبة للـ dropdowns
        /* «سجَّلها» — على نطاق الرؤية كمُنتقي شاشة الفواتير، وبالمديرين
           معاً: كانت `role = employee` وحدَها وبلا نطاق. */
        /* والمصدرُ السنداتُ التي يراها المستخدم في هذا الفرع — انظر
           `creatorFilterOptions()`. `employee_id` هنا يحمل `users.id`. */
        $employees = $this->creatorFilterOptions($optionsBase, 'employee_id');

        $salesPersons = $this->salesPersonFilterOptions(
            Invoice::query()->whereIn('invoices.id', (clone $optionsBase)->toBase()
                ->reorder()
                ->select('payments_process.invoice_id')
                ->whereNotNull('payments_process.invoice_id'))
        );

        /* مُنتقي العميل في الفلتر — على نطاق الرؤية كمُنتقي شاشة السداد
           المجمَّع أدناه (`ClientVisibilityScope`). كانت القائمة كلَّ عملاء
           المنشأة، فتُفشي أسماءَ من لا يراهم المستخدم في «إدارة العملاء»،
           وتَعِد بنتيجةٍ فارغة لمن يضغط اسماً منها. */
        $clients = ClientVisibilityScope::documentSelectClients(auth()->user());

        /* الحساب الأب للخزائن من «الحسابات المحاسبية» لا `parent_id = 13`
           مكتوباً: منشأةٌ شجرتُها غير ذلك تعرض قائمة خزائن فارغة. */
        /* فلترُ «الخزينة» على القائمة نفسِها التي تعرضها شاشةُ التسجيل:
           مَن لا يقبض في خزينةٍ لا يفلتر بها — وإلّا عرض الفلترُ خزائنَ
           لا سنداتٍ له فيها، وأوحى بوجود ما لا يراه. وهي تضمّ الحسابات
           البنكية أيضاً، وكان الفلترُ يقتصر على خزائن النقد. */
        $treasuries = $this->depositableTreasuryAccounts();

        $paymentMethods = PaymentMethod::where('status', 'active')->get();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        // الفروع النشطة لقائمة الفلتر — `status = 0` هو «نشط» في هذا الجدول
        $branches = \App\Models\Branch::where('status', 0)->select('id', 'name')->get();

        $stats = [
            'total' => $collectedTotal,
            'count' => $collectedCount,
            'today' => $collectedToday,
        ];

        // إذا كان الطلب AJAX
        if ($request->ajax()) {
            $html = view('sales::payment.partials.table', compact('payments', 'account_setting', 'recorderNames'))->render();

            return response()->json([
                'success' => true,
                'data' => $html,
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),

                /* بطاقات الملخّص مع كل تحميل — كما تفعل شاشات الفواتير
                   والمرتجع والإشعارات. من دونها تبقى البطاقات على أرقام
                   أوّل تحميلٍ للصفحة بينما يُصفّي المستخدم ويتنقّل، فتقرأ
                   «المحصَّل» لمدّةٍ غير التي في الجدول. */
                'stats_total' => CurrencySettings::format($collectedTotal),
                'stats_count' => $collectedCount,
                'stats_today' => CurrencySettings::format($collectedToday),
            ]);
        }

        $subscriptionLimit = $this->subscriptionLimit(SalesPlanFeatures::PAYMENTS);

        return view('sales::payment.index', compact('payments', 'recorderNames', 'employees', 'salesPersons', 'clients', 'treasuries', 'paymentMethods', 'account_setting', 'stats', 'branches', 'subscriptionLimit', 'range'));
    }

    /**
     * خزائنُ «الخزينة المستخدمة» — على شرط الإيداع لا كلَّ خزائن المنشأة.
     *
     * ── ما كان ──
     *
     * `Account::whereIn('parent_id', [خزائن النقد، الحسابات البنكية])`
     * بلا قيد: كلُّ موظّفٍ يرى خزائن المنشأة كلَّها في شاشة تسجيل الدفعة
     * وتعديلها، ويقبض في خزينة زميله أو فرعٍ آخر.
     *
     * ── والمحوران ──
     *
     *   • **الصلاحية** — شرطُ الإيداع المضبوط على الخزينة نفسها في شاشة
     *     إنشائها (`deposit_permissions`: الجميع أو موظّف أو دور أو فرع).
     *     كان الإعدادُ موجوداً ولا يقرؤه أحد هنا.
     *   • **نطاق الرؤية** — الموظّفُ يودع في خزينته المعيَّنة له في «خزائن
     *     الموظفين» وحدَها، ومن يملك `data_scope_all` أو ليس موظّفاً يرى
     *     الكلّ. والصلاحيةُ وحدها لا تكفي: أكثرُ الخزائن شرطُها «الجميع»،
     *     فتبقى ظاهرةً للجميع وإن لم تكن خزينةَ أحدٍ منهم.
     *
     * ولذلك `ownDepositBlockReason()` لا `depositBlockReason()` — وهي
     * نظيرةُ ما يعمل به السحبُ في المرتجع والإشعار الدائن منذ حين.
     *
     * ── ولماذا الحسابات لا صفوف `treasuries` ──
     *
     * `payments_process.treasury_id` يشير إلى **`accounts`** (ولذلك
     * يتحقّق `ClientPaymentRequest` منه بـ`exists:accounts,id`)، والقائمة
     * تضمّ الحسابات البنكية أيضاً ولا يقابلها صفٌّ في `treasuries`. فمصدرُ
     * القائمة يبقى شجرةَ الحسابات، والتصفيةُ بـ`depositBlockReason()` —
     * وهي تُرجع `null` لحسابٍ بلا صفِّ خزينة، فيمرّ الحسابُ البنكيّ كما
     * كان ولا يُقفَل شيءٌ لم يُضبط له شرط.
     *
     * والحفظُ يسأل الشرط نفسَه ({@see store()})، فلا يُعرَض خيارٌ يُرفض
     * عند الحفظ ولا يُقبل خيارٌ لم يُعرَض. انظر
     * {@see \App\Support\TreasuryAccessGuard}.
     *
     * @return \Illuminate\Support\Collection<int, Account>
     */
    protected function depositableTreasuryAccounts()
    {
        return Account::whereIn('parent_id', array_filter([
            $this->accountingAccountId('cash_treasuries_parent'),
            $this->accountingAccountId('bank_accounts_parent'),
        ]))
            ->orderBy('name')
            ->get()
            ->filter(fn (Account $account) => TreasuryAccessGuard::ownDepositBlockReason($account->id) === null)
            ->values();
    }

    public function create($id, $type = 'invoice')
    {
        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.payments.create)
        $user = auth()->user();

        /* $type يحدّد ما الذي يُسدَّد:
         *
         *   `installment_detail` — قسطٌ بعينه من جدول اتفاقية. `$id` معرّف
         *      الصفّ في `installments_details`، والمبلغ المقترح هو متبقّي
         *      **ذلك القسط** وحده. كانت شاشةُ الجدول ترسل معرّف الفاتورة
         *      و`type=invoice`، فتفتح شاشةُ السداد على متبقّي الفاتورة
         *      كاملاً: من أراد تحصيل قسطٍ بمئة وجد ألفاً في الحقل.
         *
         *   `installment` — الاتفاقية كلّها. `Installment` هو **الاتفاقية**
         *      لا القسط، و`amount` فيها قيمةُ القسط الواحد المحفوظة على
         *      الاتفاقية؛ تُركت كما كانت لمن يناديها.
         *
         *   غير ذلك — الفاتورة.
         */
        $installmentDetail = null;
        $agreement = null;

        if ($type === 'installment_detail') {
            $installmentDetail = InstallmentDetail::with('installment.invoice')->findOrFail($id);
            $agreement = $installmentDetail->installment;
            $invoice = $agreement?->invoice;

            if (! $invoice) {
                return redirect()
                    ->route('installments.agreement_installments')
                    ->with('error', __('sales_installments.messages.not_found'));
            }

            /* مبلغُ القسط **وغرامتُه المحمَّلة**: الغرامة جزءٌ من مطالبة
               هذا القسط لا بندٌ يُحصَّل في سندٍ آخر — انظر
               `App\Services\Installments\LateFeeCharger`. */
            $amount = (float) $installmentDetail->remaining_amount
                + (float) $installmentDetail->late_fee_remaining;

            $invoiceId = $invoice->id;
        } elseif ($type === 'installment_down') {
            // تحصيل الدفعة المقدَّمة: مبلغها هو ما بقي منها بلا سند
            $agreement = Installment::with('invoice')->findOrFail($id);
            $invoice = $agreement->invoice;

            if (! $invoice) {
                return redirect()
                    ->route('installments.index')
                    ->with('error', __('sales_installments.messages.not_found'));
            }

            $amount = (float) $agreement->down_payment_remaining;
            $invoiceId = $invoice->id;
        } elseif ($type === 'installment') {
            $agreement = Installment::with('invoice')->findOrFail($id);
            $amount = $agreement->amount;
            $invoiceId = $agreement->invoice->id;
            $invoice = $agreement->invoice;
        } else {
            $invoice = Invoice::findOrFail($id);
            $amount = $invoice->due_value;
            $invoiceId = $invoice->id;
        }

        // فاتورةٌ لها مرتجع لا تقبل تحصيلاً — يُقال قبل فتح الشاشة
        if ($this->invoiceHasReturn($invoice)) {
            return redirect()
                ->route('invoices.show', $invoice->id)
                ->with('error', __('sales_payment.blocked.has_return', ['code' => $invoice->code]));
        }

        /* الفاتورةُ التي أقبض عليها من فواتير نطاقي — نفسُ ما يعرضه
           جدولُ الفواتير لي (`ScopesSalesVisibility`). */
        if (! $this->canAccessSalesDocument($invoice->created_by, $invoice->employee_id, $invoice->branch_id)) {
            abort(403, 'غير مصرح لك بإضافة مدفوعات لهذه الفاتورة');
        }

        /* الخزائن من شجرة الحسابات لا من جدول `treasuries`.
         *
         * **ما كان** — `Treasury::all()` على جدولٍ **فارغ**: العمود
         * `payments_process.treasury_id` يشير إلى `accounts` (ولذلك
         * يتحقّق `ClientPaymentRequest` منه بـ`exists:accounts,id`)،
         * وكلّ ما يكتبه المتحكّم فيه معرّفُ حساب. فقائمة «الخزينة
         * المستخدمة» تخرج بلا خيارٍ واحد.
         *
         * وهو نفسه ما تفعله شاشةُ «تسجيل دفعة لمورّد»: خزائنُ النقد
         * وحسابات البنوك من «الحسابات المحاسبية». */
        $treasury = $this->depositableTreasuryAccounts();

        $user = User::find(auth()->user()->id);

        /* مُنتقي الموظف على نطاق الرؤية — كان «الكلّ أو أنا وحدي»، فالمشرفُ
           لا يجد مندوبيه فيه. `DataScope::employeeOptions()` هي القائمةُ
           نفسُها التي تقرؤها شاشةُ الفاتورة. */
        $employees = DataScope::employeeOptions();

        $payments = $this->manualPaymentMethods();

        /* الخزينة: خزينة الموظّف أوّلاً، ثمّ المضبوطة في «الحسابات
         * المحاسبية».
         *
         * كان الاحتياط `Account::where('name', 'الخزينة الرئيسية')` —
         * بحثٌ بالاسم العربيّ حرفياً. فمنشأةٌ سمّت خزينتها غير ذلك، أو
         * تعمل بالإنجليزية، لا يجد لها المتحكّم خزينةً ويرمي
         * «لا توجد خزينة متاحة» عند فتح الشاشة. والمفتاح `main_treasury`
         * مضبوطٌ في «الحسابات المحاسبية» لهذا الغرض. */
        $mainTreasuryAccount = null;
        $treasury_id = null;

        if ($user && $user->employee_id) {
            $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

            if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                $mainTreasuryAccount = Account::find($treasuryEmployee->treasury_id);
                $treasury_id = $treasuryEmployee->treasury_id;
            }
        }

        if (! $mainTreasuryAccount) {
            $mainTreasuryAccount = $this->accountingAccount('main_treasury');
            $treasury_id = $mainTreasuryAccount->id ?? null;
        }

        if (! $mainTreasuryAccount) {
            return redirect()
                ->back()
                ->with('error', __('settings.accounting_settings.account_not_mapped', [
                    'account' => $this->accountingAccountLabel('main_treasury'),
                ]));
        }

        // ما سُدِّد سابقاً على الفاتورة — ليُعرَف المتبقّي في الشاشة
        $previouslyPaid = PaymentsProcess::where('invoice_id', $invoiceId)
            ->where('payment_status', '!=', 5)
            ->sum('amount');

        /* المبلغ المقترح وسقفه — يُحسبان هنا لا في القالب.
         *
         * كان القالب يحسب `$remaining` من إجمالي الفاتورة ناقص المسدَّد،
         * ثمّ يستعمله قيمةً مبدئيةً وسقفاً: `value="{{ old('amount',
         * $remaining ?: $amount) }}"`. فأيّ مبلغٍ يرسله المتحكّم — قيمةَ
         * قسطٍ كان أو غيرها — يُهمَل ما دام للفاتورة متبقٍّ. صار الحساب
         * في موضعٍ واحد، والقالب يعرض ما يُعطى. */
        $invoiceRemaining = max(0, round($this->payableCeiling($invoice) - (float) $previouslyPaid, 2));

        /* تحصيلُ قسطٍ أو دفعةٍ مقدَّمة سقفُه مبلغُه هو، لا متبقّي الفاتورة.
         * ويبقى محكوماً بمتبقّي الفاتورة كي لا يُحصَّل أكثر من الدَّين. */
        $payable = in_array($type, ['installment_detail', 'installment_down'], true)
            ? round(min((float) $amount, $invoiceRemaining), 2)
            : $invoiceRemaining;

        return view('sales::payment.create', [
            'invoiceId' => $invoiceId,
            'invoice' => $invoice,
            'payments' => $payments,
            'amount' => $amount,
            'payable' => $payable,
            'invoiceRemaining' => $invoiceRemaining,
            'installmentDetail' => $installmentDetail,
            'installmentId' => $agreement?->id,
            'treasury' => $treasury,
            'employees' => $employees,
            'type' => $type,
            'mainTreasuryAccount' => $mainTreasuryAccount,
            'user' => $user,
            'treasury_id' => $treasury_id,
            'previouslyPaid' => $previouslyPaid,

            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «تسلسل أرقام المستندات» — القسم `invoice-payment`
            'paymentNumber' => $this->previewDocumentNumber(self::NUMBER_SECTION),

            // «الفترات المحاسبية» — سببُ منع الحفظ بتاريخ اليوم إن وُجد
            'periodBlock' => $this->accountingPeriodBlock(now()),

            /* إشعارُ سدادٍ من بوابة العميل فُتحت الشاشةُ لاعتماده — تُعبَّأ
               منه القيمُ ويُختم «معتمداً» عند الحفظ. لا شيء يتغيّر في مسار
               السند: هو مدخلٌ إلى الشاشة لا مسارٌ ثانٍ للتحصيل.
               @see \Modules\Client\Http\Controllers\ClientPaymentNoticeController */
            'paymentNotice' => $this->portalNoticeFor($invoiceId),
        ]);
    }

    /**
     * إشعارُ سداد البوابة المطلوبُ اعتمادُه — إن فُتحت الشاشةُ من شاشة
     * المراجعة (`?notice=`) وكان الإشعارُ لهذه الفاتورة وما زال معلّقاً.
     *
     * القراءةُ بشرط الفاتورة لا بالمعرّف وحده: رابطٌ يحمل إشعارَ فاتورةٍ
     * أخرى كان سيعبّئ مبلغَ دَينٍ لا يخصّ هذه.
     */
    private function portalNoticeFor($invoiceId): ?ClientPaymentNotice
    {
        $noticeId = request('notice');

        if (! $noticeId) {
            return null;
        }

        return ClientPaymentNotice::where('id', $noticeId)
            ->where('invoice_id', $invoiceId)
            ->where('status', ClientPaymentNotice::STATUS_PENDING)
            ->first();
    }

    /**
     * ختمُ إشعار البوابة «معتمداً» بعد نجاح السند — بالسند الذي أنشأه.
     *
     * بعد `commit` عمداً: الإشعارُ أثرٌ للسند لا شرطٌ له، وفشلُ ختمِه لا
     * يجوز أن يُرجِع تحصيلاً صحّ.
     */
    private function sealPortalPaymentNotice(?string $noticeId, PaymentsProcess $payment, Invoice $invoice): void
    {
        if (! $noticeId) {
            return;
        }

        try {
            $notice = ClientPaymentNotice::where('id', $noticeId)
                ->where('invoice_id', $invoice->id)
                ->where('status', ClientPaymentNotice::STATUS_PENDING)
                ->first();

            if (! $notice) {
                return;
            }

            $notice->update([
                'status' => ClientPaymentNotice::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'payment_id' => $payment->id,
            ]);

            notifications::create([
                'user_id' => auth()->id(),
                'receiver_id' => $notice->user_id,
                'client_id' => $notice->client_id,
                'title' => 'تم اعتماد سدادك',
                'description' => 'اعتُمد إشعار سدادك على الفاتورة '.$invoice->code
                    .' بمبلغ '.number_format((float) $notice->amount, 2).' وسُجّل سندُ قبض.',
                'type' => 'client_payment_notice_reviewed',
                'read' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('تعذّر ختم إشعار سداد البوابة بعد حفظ السند', [
                'notice' => $noticeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function store(ClientPaymentRequest $request)
    {
        // ** حدُّ اشتراك فوترة ** — قبل أيّ كتابة، بالقياس الحيّ لا باللقطة
        if ($blocked = $this->subscriptionBlock(SalesPlanFeatures::PAYMENTS)) {
            return redirect()->back()->withInput()->with('error', $blocked);
        }

        try {
            DB::beginTransaction();

            // استرجاع البيانات المصادق عليها
            $data = $request->validated();

            /* ** الإقفال المحاسبي ** — بتاريخ السند لا بتاريخ اليوم.
             *
             * سند القبض يُرحَّل قيداً (مدين الخزينة = دائن العميل)، والقيد لا
             * يجوز في فترةٍ أُقفلت وأُصدرت قوائمها. وكانت الشاشة تقبل أيّ
             * تاريخ: تُسدَّد فاتورةٌ بتاريخ شهرٍ مُقفَل فيتغيّر رصيدُ خزينته
             * بعد إصدار ميزانه.
             */
            if ($block = $this->accountingPeriodBlock($data['payment_date'] ?? now())) {
                DB::rollBack();

                return back()->withInput()->with('error', $block);
            }

            // التحقق من وجود الفاتورة وجلب تفاصيلها
            $invoice = Invoice::findOrFail($data['invoice_id']);

            /* فاتورةٌ لها مرتجع لا تقبل تحصيلاً — الحارس هنا لا في الشاشة
               وحدها: الشاشة تُخفي الزرّ، والطلبُ قد يُرسَل من رابطٍ محفوظ
               أو من لسانٍ فُتح قبل تسجيل المرتجع. */
            if ($this->invoiceHasReturn($invoice)) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('error', __('sales_payment.blocked.has_return', ['code' => $invoice->code]));
            }

            $user = auth()->user();

            /* التحقق من أن المستخدم يمكنه إضافة مدفوعات لعميل هذه الفاتورة —
             * بنفس نمط `InvoicesController::show()`: نطاقٌ كامل، أو صاحب
             * الفاتورة/موظفها، أو عميلٌ مُسنَد إليه.
             *
             * لا `canAddPayment()` القديمة (`AssignedEmployeeAccess`): تتحقّق
             * بـ`hasPermissionTo()` من أسماء صلاحيات نظام job_roles القديم
             * (`sales_add_payments_all`…) عبر استثناءٍ يوقف الحفظ برسالة
             * "There is no permission named..." إن غاب صفّها من هذا
             * التركيب، بدل رفضٍ صريح أو نجاح. الفواتير انتقلت من هذا النمط
             * إلى `data_scope_all` + `sales.invoices.view_assigned_clients`
             * (كتالوج نقطي حديث)، والمدفوعات تتبع الآن النمط نفسه.
             */
            $canPay = false;

            if ($this->canAccessSalesDocument($invoice->created_by, $invoice->employee_id, $invoice->branch_id)) {
                $canPay = true;
            } elseif (
                hasPermission('sales.payments.view_assigned_clients') &&
                $user->employee &&
                $this->isEmployeeAssignedToClient($user->employee->id, $invoice->client_id)
            ) {
                $canPay = true;
            }

            if (! $canPay) {
                DB::rollBack();

                return back()
                    ->with('error', 'ليس لديك صلاحية لإضافة مدفوعات لهذا العميل')
                    ->withInput();
            }

            // حساب إجمالي المدفوعات السابقة
            $totalPreviousPayments = PaymentsProcess::where('invoice_id', $invoice->id)
                ->where('type', 'client payments')
                ->where('payment_status', '!=', 5) // استثناء المدفوعات الفاشلة
                ->sum('amount');

            // حساب المبلغ المتبقي للدفع
            $ceiling = $this->payableCeiling($invoice);
            $remainingAmount = $ceiling - $totalPreviousPayments;

            // التحقق من أن مبلغ الدفع لا يتجاوز المبلغ المتبقي
            if (round($data['amount'], 2) - round($remainingAmount, 2) > 0.01) {
                return back()
                    ->with('error', 'مبلغ الدفع يتجاوز المبلغ المتبقي للفاتورة. المبلغ المتبقي هو: '.number_format($remainingAmount, 2))
                    ->withInput();
            }

            /* «منع السداد الجزئي» — فاتورةٌ من اشتراكٍ دوريّ ضُبط عليه الخيار
               تُسدَّد كاملةً دفعةً واحدة. انظر `PartialPaymentRule`. */
            if (PartialPaymentRule::blocks($invoice, (float) $data['amount'], (float) $remainingAmount)) {
                DB::rollBack();

                return back()
                    ->with('error', PartialPaymentRule::message($invoice, (float) $remainingAmount))
                    ->withInput();
            }

            // تعيين حالة الدفع الافتراضية كمسودة
            $payment_status = 3; // مسودة

            // تحديد حالة الدفع بناءً على المبلغ المدفوع والمبلغ المتبقي
            $newTotalPayments = $totalPreviousPayments + $data['amount'];

            // على السقف لا الإجمالي: فاتورةٌ مقسَّطة لا تكتمل قبل تمويلها
            if ($newTotalPayments >= $ceiling - 0.009) {
                $payment_status = 1; // مكتمل
                $invoice->is_paid = true;
                $invoice->due_value = 0;
            } else {
                $payment_status = 2; // غير مكتمل
                $invoice->is_paid = false;
                $invoice->due_value = $ceiling - $newTotalPayments;
            }

            // إذا تم تحديد حالة دفع معينة في الطلب
            if ($request->has('payment_status')) {
                switch ($request->payment_status) {
                    case 4: // تحت المراجعة
                        $payment_status = 4;
                        $invoice->is_paid = false;
                        break;
                    case 5: // فاشلة
                        $payment_status = 5;
                        $invoice->is_paid = false;
                        // إعادة حساب المبلغ المتبقي بدون احتساب هذه الدفعة
                        $invoice->due_value = $ceiling - $totalPreviousPayments;
                        break;
                }
            }        /* الخزينة: ما اختاره المستخدم أوّلاً، ثمّ خزينة الموظّف، ثمّ
         * المضبوطة في «الحسابات المحاسبية».
         *
         * **ما كان** — الشاشة تعرض قائمة «الخزينة المستخدمة» ويُرسِلها
         * التحقّق في `$data['treasury_id']`، ثمّ يكتب المتحكّم فوقها
         * خزينةَ الموظّف مهما اختار المستخدم: فحصيلةُ فرعٍ تُقيَّد في
         * خزينةٍ أخرى، ولا يظهر للمستخدم أنّ اختياره أُهمل. وهو ما
         * تفعله شاشةُ دفعة المورّد على الصواب: تحترم المختار.
         *
         * والاحتياط كان `Account::where('name', 'الخزينة الرئيسية')` —
         * بحثاً بالاسم العربيّ حرفياً؛ منشأةٌ سمّت خزينتها غير ذلك لا
         * تجد خزينةً فتسقط العملية. */
            $mainTreasuryAccount = null;
            $user = Auth::user();

            if (! empty($data['treasury_id'])) {
                $mainTreasuryAccount = Account::find($data['treasury_id']);

                /* والشرطُ يُفحَص عند الحفظ لا في القائمة وحدها: النموذجُ
                   يُرسَل ولا يُوثَق به، وقد فُتِحت الشاشةُ قبل تغيير الشرط. */
                if ($mainTreasuryAccount
                    && ($denied = TreasuryAccessGuard::ownDepositBlockReason($mainTreasuryAccount->id))) {
                    throw new \Exception($denied);
                }
            }

            if (! $mainTreasuryAccount && $user && $user->employee_id) {
                $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();

                if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                    $mainTreasuryAccount = Account::find($treasuryEmployee->treasury_id);
                }
            }

            $mainTreasuryAccount = $mainTreasuryAccount ?: $this->accountingAccount('main_treasury');

            // إذا لم يتم العثور على خزينة، توقف العملية وأظهر خطأ
            if (! $mainTreasuryAccount) {
                throw new \Exception(__('settings.accounting_settings.account_not_mapped', [
                    'account' => $this->accountingAccountLabel('main_treasury'),
                ]));
            }

            // إضافة البيانات الإضافية للدفعة
            $data['type'] = 'client payments';
            $data['created_by'] = Auth::id();
            $data['payment_status'] = $payment_status;
            $data['employee_id'] = auth()->user()->id;
            $data['treasury_id'] = $mainTreasuryAccount->id; // تخزين معرف الخزينة

            /* فرع السند = فرع فاتورته. السند تابعٌ للمستند الذي يُسدّده، فلو
               حصّل موظّفُ فرعٍ آخر فاتورةً بقيت في فرعها لا انتقلت إليه. */
            $data['branch_id'] = $invoice->branch_id ?? current_branch_id();

            /* طريقة الدفع: الشاشة ترسلها في `payment_type` والعمود اسمه
             * `payment_method`، وهو وحده في `$fillable`. فكان المفتاح
             * يُرمى قبل أن يصل إلى الاستعلام بلا خطأ ولا تحذير: يختار
             * المستخدم «تحويل بنكي» ويُحفَظ السند بلا طريقة دفعٍ إطلاقاً،
             * ثمّ تعرض الشاشات شرطةً مكان الطريقة. */
            $data['payment_method'] = $data['payment_type'] ?? null;
            unset($data['payment_type']);

            /* رقم السند من «تسلسل أرقام المستندات».
             *
             * كانت الشاشة تعرض الرقم المقترح في حقلٍ للقراءة فقط ولا
             * ترسله، ولا يولّده الخادم — فيبقى `reference_number` فارغاً
             * في كل سند، وتعرض القوائم والمطبوعات `#<المعرّف>` بدلاً منه. */
            if (empty($data['reference_number'])) {
                $data['reference_number'] = $this->resolveDocumentNumber(
                    $request->input('reference_number'),
                    self::NUMBER_SECTION,
                );
            }

            /* المرفق — في `assets/uploads/sales/payments` باسمٍ وامتدادٍ يولّدهما
               الخادم. قاعدة `mimes` في الطلب تفحص المحتوى، لكنّ الاسم كان يُحفظ
               كما أرسله المتصفّح: صورةٌ سليمة باسم `x.php` تُنفَّذ. */
            if ($attachmentFiles = DocumentAttachments::files($request)) {
                $data['attachments'] = DocumentAttachments::encode(
                    DocumentAttachments::storeMany($attachmentFiles, DocumentAttachments::SALES_PAYMENT)
                );
            } else {
                unset($data['attachments']);
            }

            // إنشاء سجل الدفع
            $payment = PaymentsProcess::create($data);

            /* سطرُ `Log::create` اليدويّ الذي كان هنا حُذف: `OperationLog`
               أدناه يكتب السجلّ نفسه بنوع الوحدة وأيقونتها وعنوان IP
               والمتصفّح والمسار — فكان الإنشاء الواحد يظهر مرّتين في
               «سجلّ النشاطات»، مرّةً بالنوع `payments` وأخرى بالنوع
               `SalesPayments`، وخطُّ المستند الزمنيّ يقرأ الثاني وحده. */

            // إرسال الإشعار
            $client = Client::find($invoice->client_id);
            notifications::create([
                'type' => 'invoice_payment',
                'title' => $user->name.' أنشأ عملية دفع',
                'description' => 'عملية دفع للفاتورة رقم '.$invoice->id.' للعميل '.$client->trade_name.' بقيمة '.number_format($payment->amount, 2).' ر.س',
            ]);

            // تحديث رصيد الخزينة
            $mainTreasuryAccount->balance += $data['amount'];
            $mainTreasuryAccount->save();

            // تحديث المبلغ المدفوع في الفاتورة
            $invoice->advance_payment = $newTotalPayments;
            $invoice->payment_status = $payment_status;
            $invoice->save();

            // إنشاء قيد محاسبي للدفعة
            $journalEntry = JournalEntry::create([
                'reference_number' => $payment->reference_number ?? $invoice->code,
                'date' => $data['payment_date'] ?? now(),
                'description' => 'دفعة للفاتورة رقم '.$invoice->code,
                'status' => 1,
                /* العملة من «العملات» — وعملةُ الفاتورة أولى، فالسند يسدّدها.
                   كانت `'SAR'` مكتوبةً في الشيفرة، فمنشأةٌ تعمل بغيرها
                   تُرحّل قيودَ تحصيلها بعملةٍ لا تستعملها. */
                'currency' => $this->documentCurrency($invoice),
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'created_by_employee' => Auth::id(),
                'reference_type' => ReferenceType::CUSTOMER_COLLECTION,
                'linking_type' => LinkingType::SINGLE,
            ]);

            // إضافة تفاصيل القيد المحاسبي للدفعة
            // 1. حساب الصندوق/البنك (مدين)
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $mainTreasuryAccount->id,
                'description' => 'استلام دفعة نقدية',
                'debit' => $data['amount'],
                'credit' => 0,
                'is_debit' => true,
            ]);

            // 2. حساب العميل (دائن)
            $clientaccounts = Account::ledger()->where('client_id', $invoice->client_id)->first();
            JournalEntryDetail::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $clientaccounts->id,
                'description' => 'دفعة من العميل',
                'debit' => 0,
                'credit' => $data['amount'],
                'is_debit' => false,
            ]);

            // تحديث رصيد حساب العميل
            if ($clientaccounts) {
                $clientaccounts->balance -= $data['amount'];
                $clientaccounts->save();
            }

            // === معالجة نقاط تحصيل الدفعة (payment_collected) ===
            // فقط للمدفوعات الناجحة (غير الفاشلة وغير تحت المراجعة)
            $pointsEmployeeId = $user->employee_id ?? null;
            $pointsClientId = $invoice->client_id ?? null;

            // لا يمكن إنشاء معاملة نقاط بدون معرف موظف أو معرف عميل
            if (in_array($payment_status, [1, 2, 3]) && ($pointsEmployeeId || $pointsClientId)) {
                try {
                    $pointsContext = [
                        'employee_id' => $pointsEmployeeId,
                        'client_id' => $pointsClientId,
                        'amount' => $data['amount'],
                        'branch_id' => $invoice->branch_id ?? null,
                        'reference_type' => PaymentsProcess::class,
                        'reference_id' => $payment->id,
                        'description' => 'تحصيل دفعة للفاتورة رقم '.$invoice->code.' بمبلغ '.number_format($data['amount'], 2).' ر.س',
                    ];

                    // نقاط تحصيل الدفعة للموظف — فقط إذا يوجد موظف
                    if ($pointsEmployeeId) {
                        app(PointsService::class)->processAction('payment_collected', $pointsContext);
                    }

                    // نقاط استلام الدفعة للعميل — فقط إذا يوجد عميل
                    if ($pointsClientId) {
                        app(PointsService::class)->processAction('payment_received', $pointsContext);
                    }
                } catch (\Throwable $e) {
                    Log::warning('PointsService: فشل معالجة نقاط تحصيل الدفعة', [
                        'payment_id' => $payment->id,
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);

                    /* والنقاطُ التي لم تُحتسب تُكتب في «سجلّ الأخطاء» أيضاً:
                       السدادُ نجح والنقاطُ سقطت، ولا يعرف صاحبُ البرنامج
                       بذلك إن بقي الخبرُ في ملفّ لارافيل وحده. */
                    OperationLog::failure($e, self::LOG_MODULE, 'points', (int) $payment->id);
                }
            }

            DB::commit();

            // أقساطُ الفاتورة وربحُها المحقَّق تتبع السند — وأسطرُ الربح تُضاف
            // إلى قيد السند نفسِه فيكون قيداً مركّباً واحداً
            InstallmentCollections::syncInvoice($invoice->id, $journalEntry ?? null);

            // إعداد رسالة النجاح مع حالة الدفع
            $paymentStatusText = match ($payment_status) {
                1 => 'مكتمل',
                2 => 'غير مكتمل',
                3 => 'مسودة',
                4 => 'تحت المراجعة',
                5 => 'فاشلة',
                default => 'غير معروف',
            };

            $successMessage = sprintf(
                'تم تسجيل عملية الدفع بنجاح. المبلغ المدفوع: %s، المبلغ المتبقي: %s - حالة الدفع: %s',
                number_format($data['amount'], 2),
                number_format($invoice->due_value, 2),
                $paymentStatusText
            );

            /* السجلّات الثلاثة معاً — نقطة تسجيل واحدة.
               كان `Log::error` وحده في الإخفاق، ولا شيء إطلاقاً في النجاح:
               سندُ قبضٍ يُرحَّل قيداً ولا يُكتب له سطرٌ في «سجلّ النشاط». */
            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $payment,
                'تسجيل دفعة على الفاتورة **'.$invoice->code.'**'
                    .' — العميل: '.($invoice->client->trade_name ?? '—')
                    .' — المبلغ: '.CurrencySettings::format($data['amount']),
            );

            // إشعارُ بوابة العميل — إن كان السندُ اعتماداً له
            $this->sealPortalPaymentNotice($request->input('payment_notice_id'), $payment, $invoice);

            return redirect()->route('paymentsClient.index')->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollback();

            // سجلّ الأخطاء وسجلّ الأحداث معاً — كان `Log::error` وحده،
            // فلا يظهر الخطأ في شاشة «سجلّ الأخطاء» التي بُنيت له
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return back()
                ->with('error', 'حدث خطأ أثناء تسجيل عملية الدفع: '.$e->getMessage())
                ->withInput();
        }
    }

    /** طرق الدفع المفعَّلة في شاشة «طرق الدفع» */
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
    /**
     * فاتورةٌ لها مرتجع لا تقبل تحصيلاً.
     *
     * **لماذا** — المرتجع يُلغي البيع كلَّه أو بعضه ويعكس قيده ويردّ
     * البضاعة إلى المخزن. فتحصيلُ دفعةٍ على فاتورةٍ مرتجعة يقبض ثمنَ
     * ما رُدّ: ترتفع ذمّةُ العميل دائنةً بلا مقابل، ويفترق رصيدُ
     * الخزينة عن دفتر المبيعات. والتصحيح بعدها يحتاج سندَ صرفٍ
     * وقيدَ عكسٍ يدويّاً.
     *
     * المرتجع فاتورةٌ نوعُها `returned` ومرجعُها معرّفُ الأصل — وهي
     * العلاقة نفسها التي تعرضها قائمةُ الفواتير في عمود «مرتجعة».
     *
     * يُفحَص في `create()` (فلا تُفتح الشاشة) وفي `store()` (فلا يمرّ
     * طلبٌ يُرسَل مباشرةً)، إذ الشاشةُ وحدها لا تكفي حارساً.
     */
    protected function invoiceHasReturn(?Invoice $invoice): bool
    {
        if (! $invoice) {
            return false;
        }

        return Invoice::where('type', 'returned')
            ->where('reference_number', $invoice->id)
            ->exists();
    }

    /**
     * سقفُ ما يُقبَض على فاتورة: إجماليها وغراماتُ تأخير أقساطها المحمَّلة.
     *
     * الغرامةُ ليست بنداً في الفاتورة ولا تزيد `grand_total` — هي ذمّةٌ
     * مستقلّة رُحِّلت على حساب العميل. لكنّ سندَ القبض يُسدّد الذمّةَ
     * (مدين الخزينة = دائن العميل)، فسقفُه يشملها.
     *
     * وبلا هذا يبقى ما رُحِّل من غرامةٍ غيرَ قابلٍ للتحصيل: فاتورةٌ
     * سُدِّدت بالكامل ترفض أيَّ سندٍ بعدها، فتبقى الغرامةُ في دفتر
     * الأستاذ بلا طريقٍ إلى الخزينة.
     */
    protected function payableCeiling(?Invoice $invoice): float
    {
        /* إجمالي الفاتورة + تمويل التقسيط المرحَّل (ربح ورسوم وضريبة) +
           الغرامات **المحمَّلة**. كان السقفُ الإجماليَّ والغراماتِ غيرَ
           المحصَّلة: فيقف «سداد الكل» عند الفاتورة ويبقى التمويلُ بلا
           طريقٍ إلى الخزينة، وتنقص الغرامةُ المحصَّلة من السقف مرّتين. */
        return InstallmentFinancing::invoiceClaim($invoice);
    }

    protected function manualPaymentMethods()
    {
        try {
            // الشرطان في `PaymentMethod::scopeManual` — تقرؤهما بوابةُ العميل أيضاً
            return PaymentMethod::query()
                ->manual()
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesPayment', 'manualPaymentMethods');

            return collect();
        }
    }

    protected function activePaymentMethods()
    {
        try {
            return PaymentMethod::query()
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'name_en', 'code', 'is_default']);
        } catch (\Throwable $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sales.SalesPayment', 'activePaymentMethods');

            return collect();
        }
    }

    public function show($id)
    {
        $payment = PaymentsProcess::with(['invoice.client', 'invoice.payments_process', 'employee.employee', 'treasuryAccount', 'payment_type'])->findOrFail($id);
        $user = auth()->user();

        // التحقق من صلاحية عرض الدفعة — على نطاق الرؤية (`ScopesSalesVisibility`)
        if ($this->salesScopeApplies()) {
            // إذا كان لديه صلاحية عرض المدفوعات للعملاء المعينين
            if (hasPermission('sales.payments.view_assigned_clients')) {
                // التحقق من أن العميل معين للموظف
                $isAssigned = $payment->invoice->client->employees()
                    ->where('employee_id', $user->employee_id)
                    ->exists();

                if (! $isAssigned) {
                    abort(403, 'ليس لديك صلاحية لعرض هذه الدفعة');
                }
            } else {
                /* وإلّا فسنداتُ نطاقه — و`employee_id` هنا `users.id` لا
                   `employees.id` (انظر `index()`)، وكان يُقابَل بـ
                   `$user->employee_id`. */
                if (! $this->canAccessSalesDocument(null, $payment->employee_id, $payment->branch_id, 'user')) {
                    abort(403, 'ليس لديك صلاحية لعرض هذه الدفعة');
                }
            }
        }

        $employees = Employee::all();
        $account_setting = AccountSetting::where('user_id', auth()->user()->id)->first();

        /* مفتاحان لا واحد: الصفوف القديمة كُتبت بالنوع `payments`،
           والجديدة يكتبها `OperationLog` بالنوع `SalesPayments`.
           والاقتصار على أحدهما يُفرغ الخطّ الزمني.

           وحُذف `whereHas('payments')`: علاقةٌ تربط `type_id` بجدول
           المدفوعات، فتُسقط كل سطرٍ لا يقابله صفٌّ فيه — ومنها أسطر
           الحذف نفسها. */
        $logs = ModelsLog::whereIn('type', ['payments', self::LOG_MODULE])
            ->where('type_id', $id)
            ->with('user.branch')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        /* القيد المحاسبي للسند — يُعثَر عليه بالفاتورة ونوع المرجع.
           لا عمودَ يربط `payments_process` بـ`journal_entries`. */
        $journalEntry = $payment->invoice_id
            ? JournalEntry::where('invoice_id', $payment->invoice_id)
                ->where('reference_type', ReferenceType::CUSTOMER_COLLECTION)
                ->orderByDesc('id')
                ->first()
            : null;

        return view('sales::payment.show', [
            'payment' => $payment,
            'logs' => $logs,
            'employees' => $employees,
            'account_setting' => $account_setting,
            'journalEntry' => $journalEntry,

            // ── ما تأتي به الإعدادات إلى هذه الشاشة ──

            // «قوالب المستندات» — سند القبض وقوالبه
            'documentTemplates' => PrintSettings::templatesFor(self::DOCUMENT_TYPE),
            'defaultTemplate' => PrintSettings::defaultTemplateFor(self::DOCUMENT_TYPE),
            'prefersPdf' => PrintSettings::prefersPdf(),

            // «طرق الدفع» — لعرض اسم الطريقة لا رقمها
            'paymentMethods' => $this->activePaymentMethods(),
        ]);
    }

    /**
     * إرسال سند القبض إلى بريد العميل.
     *
     * لم يكن للدفعة زرُّ إرسالٍ أصلاً — لا بريداً ولا واتساب — بينما
     * لبقيّة مستندات المبيعات زرٌّ (وإن كان يفتح واتساب تحت اسم البريد).
     * والسندُ أحقُّ به: هو إثباتُ السداد الذي يطلبه العميل.
     */
    public function sendReceiptEmail(Request $request, $id)
    {
        $payment = PaymentsProcess::with(['client', 'invoice.client'])->findOrFail($id);

        /* عميلُ الدفعة من `client_id` أولاً: دفعةٌ بلا فاتورة (سدادٌ على
           الحساب) لا عميلَ لها عبر `invoice.client`. */
        $client = $payment->client ?? $payment->invoice?->client;

        $response = $this->mailSalesDocument($request, self::DOCUMENT_TYPE, $payment->id, [
            'number' => (string) ($payment->reference_number ?: $payment->id),
            'client' => $client?->display_name ?? $client?->trade_name ?? '',
            'email' => $client->email ?? null,
            'client_id' => $client->id ?? null,
            'total' => $payment->amount,
            'date' => $payment->payment_date ?? $payment->created_at,
            'link' => route('paymentsClient.show', $payment->id),
        ], $this->builtInReceiptHtml($payment), self::DATA_TYPE);

        if ($this->documentMailSucceeded()) {
            OperationLog::record('emailed', self::LOG_MODULE, (int) $payment->id, 'إرسال سند قبض رقم '.$payment->id.' بالبريد');
        }

        return $response;
    }

    /**
     * قالبُ الوحدة المدمج نصّاً — احتياطُ المرفق حين لا قالبَ مصمَّماً للنوع.
     *
     * `null` عند أي تعثّر: رسالةٌ بلا مرفق خيرٌ من رسالةٍ لا تخرج.
     */
    protected function builtInReceiptHtml(PaymentsProcess $payment): ?string
    {
        try {
            $receipt = $payment->loadMissing(['invoice.client', 'treasuryAccount', 'employee.employee', 'payment_type', 'client']);

            return view('sales::payment.receipt.index_repeat', ['receipt' => $receipt])->render();
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'email_builtin_html', (int) $payment->id);

            return null;
        }
    }

    public function edit($id)
    {
        $payment = PaymentsProcess::with(['invoice', 'employee.employee'])->findOrFail($id);
        $user = auth()->user();

        /* على نطاق الرؤية — و`payments_process.employee_id` يحمل `users.id`
           (انظر `index()`)، ولا عمود `created_by` في الجدول أصلاً. */
        if (! $this->canAccessSalesDocument(null, $payment->employee_id, $payment->branch_id, 'user')) {
            abort(403, 'غير مصرح لك بتعديل هذه الدفعة');
        }

        $employees = Employee::all();

        /* ما كانت الشاشة تأخذه من الإعدادات: لا شيء.
         *
         * كانت طرق الدفع خمسة خياراتٍ **مكتوبةً في القالب** (نقدي…
         * أخرى) بأرقامٍ ثابتة من ١ إلى ٥ لا تقابل «طرق الدفع»
         * المضبوطة، وقائمةُ الخزينة خياراً واحداً `value="1"` لا يقابل
         * حساباً موجوداً — فتعديلُ سندٍ يكتب فيه خزينةً لا وجود لها،
         * أو يمحو طريقةَ دفعه. */
        $payments = $this->manualPaymentMethods();

        $treasury = $this->depositableTreasuryAccounts();

        $invoice = $payment->invoice;

        // ما سُدِّد على الفاتورة من غير هذا السند — منه يُعرَف سقفُ المبلغ
        $paidByOthers = PaymentsProcess::where('invoice_id', $payment->invoice_id)
            ->where('payment_status', '!=', 5)
            ->where('id', '!=', $payment->id)
            ->sum('amount');

        return view('sales::payment.edit', [
            'payment' => $payment,
            'employees' => $employees,
            'id' => $id,
            'invoice' => $invoice,
            'payments' => $payments,
            'treasury' => $treasury,
            'paidByOthers' => $paidByOthers,

            // «الفترات المحاسبية» — سببُ منع الحفظ بتاريخ السند إن وُجد
            'periodBlock' => $this->accountingPeriodBlock($payment->payment_date ?: now()),
        ]);
    }

    /**
     * تعديل سند قبض.
     *
     * **الربط الضمنيّ لم يكن يعمل** — المسار
     * `payments-client/update/{id}` ومُعامل الدالّة `$payment`:
     * Laravel يربط بتطابق **اسم** المُعامل مع اسم مقطع المسار، و`id`
     * ليس `payment`. فكان يصل نموذجٌ **فارغٌ غير محفوظ** من الحاوي،
     * و`$payment->update()` عليه لا يكتب شيئاً ويعود `false` بلا خطأ:
     * **تعديل السند لا يُحفَظ إطلاقاً** — يظهر «تم التحديث بنجاح»
     * ويبقى السند كما كان.
     *
     * وأسوأ منه: فحصُ الصلاحية يقارن `created_by` و`employee_id` على
     * نموذجٍ فارغ (كلاهما `null`)، والقيدُ المحاسبي يُعاد ترحيله
     * بالمبلغ الجديد — فيفترق القيد عن السند الذي لم يتغيّر.
     */
    public function update(ClientPaymentRequest $request, $id)
    {
        $payment = PaymentsProcess::findOrFail($id);

        // التحقق من صلاحية التعديل
        $user = auth()->user();

        // على نطاق الرؤية — انظر `edit()`
        if (! $this->canAccessSalesDocument(null, $payment->employee_id, $payment->branch_id, 'user')) {
            abort(403, 'غير مصرح لك بتعديل هذه الدفعة');
        }

        /*
            الفترة المحاسبية: تاريخُ الدفعة القائم والجديد معاً — كتعديل القيد.
            كان التعديلُ بلا فحصٍ إطلاقاً: دفعةٌ من فترةٍ مقفلة يتغيّر مبلغُها
            وقيدُها ورصيدُ خزينتها وعميلها، والدفترُ مقفل. (نقلُ تاريخها تمنعه
            السِّمةُ في المودل؛ وتغييرُ المبلغ بلا تاريخ لا يمرّ بها.)
        */
        if ($block = $this->accountingPeriodBlock($payment->payment_date ?: now())) {
            return back()->withInput()->with('error', $block);
        }
        if ($request->filled('payment_date') && ($block = $this->accountingPeriodBlock($request->input('payment_date')))) {
            return back()->withInput()->with('error', $block);
        }

        try {
            DB::beginTransaction();

            // استرجاع البيانات المصادق عليها
            $data = $request->validated();

            // التحقق من وجود الفاتورة وجلب تفاصيلها
            $invoice = Invoice::findOrFail($data['invoice_id']);

            // تعديل مبلغ السند تحصيلٌ أيضاً — فيسري عليه حارسُ المرتجع
            if ($this->invoiceHasReturn($invoice)) {
                DB::rollBack();

                return back()
                    ->withInput()
                    ->with('error', __('sales_payment.blocked.has_return', ['code' => $invoice->code]));
            }

            // حساب إجمالي المدفوعات السابقة (باستثناء الدفعة الحالية)
            $totalPreviousPayments = PaymentsProcess::where('invoice_id', $invoice->id)
                ->where('type', 'client payments')
                ->where('payment_status', '!=', 5) // استثناء المدفوعات الفاشلة
                ->where('id', '!=', $payment->id) // استثناء الدفعة الحالية
                ->sum('amount');

            // حساب المبلغ المتبقي للدفع
            $ceiling = $this->payableCeiling($invoice);
            $remainingAmount = $ceiling - $totalPreviousPayments;

            // التحقق من أن مبلغ الدفع لا يتجاوز المبلغ المتبقي
            if ($data['amount'] > $remainingAmount) {
                return back()
                    ->with('error', 'مبلغ الدفع يتجاوز المبلغ المتبقي للفاتورة. المبلغ المتبقي هو: '.number_format($remainingAmount, 2))
                    ->withInput();
            }

            // تعديل الدفعة إلى جزءٍ من المتبقّي يُردّ كإنشائها
            if (PartialPaymentRule::blocks($invoice, (float) $data['amount'], (float) $remainingAmount)) {
                DB::rollBack();

                return back()
                    ->with('error', PartialPaymentRule::message($invoice, (float) $remainingAmount))
                    ->withInput();
            }

            // تعيين حالة الدفع الافتراضية كمسودة
            $payment_status = 3; // مسودة

            // تحديد حالة الدفع بناءً على المبلغ المدفوع والمبلغ المتبقي
            $newTotalPayments = $totalPreviousPayments + $data['amount'];

            // على السقف لا الإجمالي: فاتورةٌ مقسَّطة لا تكتمل قبل تمويلها
            if ($newTotalPayments >= $ceiling - 0.009) {
                $payment_status = 1; // مكتمل
                $invoice->is_paid = true;
                $invoice->due_value = 0;
            } else {
                $payment_status = 2; // غير مكتمل
                $invoice->is_paid = false;
                $invoice->due_value = $ceiling - $newTotalPayments;
            }

            // إذا تم تحديد حالة دفع معينة في الطلب
            if ($request->has('payment_status')) {
                switch ($request->payment_status) {
                    case 4: // تحت المراجعة
                        $payment_status = 4;
                        $invoice->is_paid = false;
                        break;
                    case 5: // فاشلة
                        $payment_status = 5;
                        $invoice->is_paid = false;
                        // إعادة حساب المبلغ المتبقي بدون احتساب هذه الدفعة
                        $invoice->due_value = $ceiling - $totalPreviousPayments;
                        break;
                }
            }

            // إضافة البيانات الإضافية للدفعة
            $data['type'] = 'client payments';
            $data['payment_status'] = $payment_status;

            // نفس تحويل المفتاح الذي في `store()` — وإلا مُحيت طريقة
            // الدفع من السند بمجرّد تعديله
            if (array_key_exists('payment_type', $data)) {
                $data['payment_method'] = $data['payment_type'];
                unset($data['payment_type']);
            }

            // رقم السند لا يتغيّر بالتعديل
            unset($data['reference_number']);

            // المرفق — في `assets/uploads/sales/payments`، انظر `store()`
            if ($attachmentFiles = DocumentAttachments::files($request)) {
                $data['attachments'] = DocumentAttachments::encode(
                    DocumentAttachments::storeMany($attachmentFiles, DocumentAttachments::SALES_PAYMENT)
                );
            } else {
                unset($data['attachments']);
            }

            // تحديث سجل الدفع
            $payment->update($data);

            // تحديث المبلغ المدفوع في الفاتورة
            $invoice->advance_payment = $newTotalPayments;
            $invoice->payment_status = $payment_status;
            $invoice->save();

            /* ** إعادة ترحيل قيد السند **
             *
             * **ما كان** — `$this->getAccountId('cash')` **دالّةٌ غير
             * معرَّفة** في هذا المتحكّم ولا في أيٍّ من سماته. فكلّ تعديلٍ
             * لسندٍ له قيدٌ مرحَّل يسقط بـ«Method … getAccountId does not
             * exist»، ويُلتقط في `catch` فيظهر للمستخدم رسالةَ خطأ
             * ويُلغى التعديل كلُّه بـ`rollback`.
             *
             * والطرفُ المدين هو **خزينة السند** لا حساب «نقدية» مجهول:
             * السند يُقيَّد في الخزينة التي دخل إليها المال، وهي التي
             * اختارها المستخدم في الشاشة. والاحتياط من «الحسابات
             * المحاسبية» لا باسمٍ عربيٍّ مكتوب.
             *
             * والطرف الدائن حسابُ العميل من `accounts.client_id` — نفس
             * ما يفعله `store()`. وكان `$invoice->client->account_id`،
             * وهو عمودٌ لا وجود له في `clients`، فيُكتب `null` في
             * `account_id` ويُفسَد شقُّ القيد الدائن.
             */
            $journalEntry = JournalEntry::where('invoice_id', $invoice->id)
                ->where('reference_type', ReferenceType::CUSTOMER_COLLECTION)
                ->where('reference_number', $payment->reference_number ?: $invoice->code)
                ->first();

            if ($journalEntry) {
                $treasuryAccount = Account::find($payment->treasury_id)
                    ?: $this->accountingAccount('main_treasury');

                $clientAccount = Account::ledger()->where('client_id', $invoice->client_id)->first();

                $journalEntry->update([
                    'date' => $data['payment_date'] ?? now(),
                    'description' => 'تحديث دفعة للفاتورة رقم '.$invoice->code,
                ]);

                // 1. الخزينة (مدين)
                if ($treasuryAccount) {
                    JournalEntryDetail::where('journal_entry_id', $journalEntry->id)
                        ->where('is_debit', true)
                        // أسطرُ ربح التقسيط المضافة إلى القيد المركّب لا تُمَسّ
                        ->whereNotIn('account_id', InstallmentFinancing::financingAccountIds())
                        ->update([
                            'account_id' => $treasuryAccount->id,
                            'description' => 'تحديث استلام دفعة نقدية',
                            'debit' => $data['amount'],
                            'credit' => 0,
                        ]);
                }

                // 2. حساب العميل (دائن)
                if ($clientAccount) {
                    JournalEntryDetail::where('journal_entry_id', $journalEntry->id)
                        ->where('is_debit', false)
                        // أسطرُ ربح التقسيط المضافة إلى القيد المركّب لا تُمَسّ
                        ->whereNotIn('account_id', InstallmentFinancing::financingAccountIds())
                        ->update([
                            'account_id' => $clientAccount->id,
                            'description' => 'تحديث دفعة من العميل',
                            'debit' => 0,
                            'credit' => $data['amount'],
                        ]);
                }
            }

            DB::commit();

            // أقساطُ الفاتورة وربحُها المحقَّق تتبع السند — وأسطرُ الربح تُضاف
            // إلى قيد السند نفسِه فيكون قيداً مركّباً واحداً
            InstallmentCollections::syncInvoice($invoice->id, $journalEntry ?? null);

            // إعداد رسالة النجاح مع حالة الدفع
            $paymentStatusText = match ($payment_status) {
                1 => 'مكتمل',
                2 => 'غير مكتمل',
                3 => 'مسودة',
                4 => 'تحت المراجعة',
                5 => 'فاشلة',
                default => 'غير معروف',
            };

            $successMessage = sprintf('تم تحديث عملية الدفع بنجاح. المبلغ المدفوع: %s، المبلغ المتبقي: %s - حالة الدفع: %s', number_format($data['amount'], 2), number_format($invoice->due_value, 2), $paymentStatusText);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $payment,
                'تعديل دفعة على الفاتورة **'.$invoice->code.'**'
                    .' — المبلغ: '.CurrencySettings::format($data['amount']),
            );

            return redirect()->route('paymentsClient.index')->with('success', $successMessage);
        } catch (\Exception $e) {
            DB::rollback();

            OperationLog::failure($e, self::LOG_MODULE, 'update');

            return back()
                ->with('error', 'حدث خطأ أثناء تحديث عملية الدفع: '.$e->getMessage())
                ->withInput();
        }
    }

    public function destroy($id)
    {
        $payment = PaymentsProcess::findOrFail($id);
        $user = auth()->user();

        // على نطاق الرؤية — انظر `edit()`
        if (! $this->canAccessSalesDocument(null, $payment->employee_id, $payment->branch_id, 'user')) {
            abort(403, 'غير مصرح لك بحذف هذه الدفعة');
        }

        $reference = $payment->reference_number ?: ('#'.$payment->id);
        $amount = CurrencySettings::format($payment->amount);
        $paidInvoiceId = $payment->invoice_id;

        /* الحذفُ كان بلا حارس: مفتاحٌ أجنبيّ يمنع الحذف فتخرج صفحةُ خطأ
           لارافيل، ولا يُكتب في «سجلّ الأخطاء» شيء. */
        try {
            PaymentsProcess::destroy($id);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', (int) $id);

            return back()->with('error', 'تعذّر حذف عملية الدفع: '.$e->getMessage());
        }

        InstallmentCollections::syncInvoice($paidInvoiceId);

        OperationLog::record(
            'deleted',
            self::LOG_MODULE,
            (int) $id,
            'حذف دفعة **'.$reference.'** بمبلغ '.$amount,
        );

        return redirect()->route('paymentsClient.index')->with('success', 'تم حذف عملية الدفع بنجاح');
    }

    /**
     * معاينة سند القبض وطباعته.
     *
     * **ما أُصلح**
     *
     *   • *قوالب «قوالب المستندات» لا تُصيَّر*: شاشة عرض السند تعرض
     *     قوالب النوع `receipt_voucher` وتربط كلَّ واحدٍ منها بـ
     *     `?template=<معرّف>` — وهذه الدالّة تتجاهل المُعامل تماماً
     *     وتعرض القالب المدمج دائماً. فالقالب الذي يصمّمه المستخدم
     *     ويعيّنه افتراضياً لا يظهر أبداً.
     *   • *`$receiptType` لا يقرؤه أحد*: كان يُمرَّر إلى القالب والقالب
     *     يقرأ `request()->query('type')` مباشرةً.
     *   • *العلاقات لا تُحمَّل*: `findOrFail()` وحدها، ثمّ يقرأ القالب
     *     `invoice->client` و`treasury` و`payment_type` و`employee` —
     *     أربعة استعلاماتٍ متتابعة لكلّ سند.
     */
    public function rereceipt($id)
    {
        $receipt = PaymentsProcess::with([
            'invoice.client',
            'treasuryAccount',
            'employee.employee',
            'payment_type',
            'client',
        ])->findOrFail($id);

        /* قالبٌ مصمَّمٌ في «قوالب المستندات» — إلّا أن يُطلب المدمج صراحةً
           (`?builtin=1`)، وبه تطبع مساحةُ الزيارة. */
        if (! $this->wantsBuiltinPrint(request()->query('template'))
            && ($html = $this->renderSalesTemplate(self::DOCUMENT_TYPE, $id, request()->query('template'), self::DATA_TYPE))) {
            if (! request()->boolean('embed')) {
                OperationLog::record(
                    'printed',
                    self::LOG_MODULE,
                    $receipt,
                    'طباعة سند القبض رقم '.($receipt->reference_number ?: $receipt->id),
                );
            }

            return response(DocumentPrintPage::wrap($html));
        }

        if (! request()->boolean('embed')) {
            OperationLog::record(
                'printed',
                self::LOG_MODULE,
                $receipt,
                'طباعة سند القبض رقم '.($receipt->reference_number ?: $receipt->id),
            );
        }

        return response()->view('sales::payment.receipt.index_repeat', compact('receipt'));
    }

    /**
     * سند القبض ملفَّ PDF.
     *
     * **ما كان** — `Pdf::loadView()` على Dompdf، وDompdf لا يصل الحروف
     * العربية ولا يعكس اتجاهها: يخرج السند كلُّه «؟؟؟؟؟ ؟؟؟؟؟» — وهو
     * ما يراه المستخدم في مستنده. والقالب نفسه يطلب خطّ `Arial`، وهو
     * في Dompdf خطُّ `Helvetica` الأساسيّ بلا محرفٍ عربيّ واحد.
     *
     * صار على TCPDF بخطّ `aealarabiya` و`setRTL(true)` — وهي نفسها
     * الطريقة التي تُبنى بها فاتورة المبيعات PDF في هذا المشروع
     * (`InvoicesController::generatePdf`)، وهي تصل الحروف وتعكس
     * الاتجاه.
     */
    public function pdfReceipt($id)
    {
        $receipt = PaymentsProcess::with([
            'invoice.client',
            'treasuryAccount',
            'employee.employee',
            'payment_type',
            'client',
        ])->findOrFail($id);

        $number = $receipt->reference_number ?: str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT);

        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Fawtra');
        $pdf->SetTitle('سند قبض '.$number);
        $pdf->SetMargins(15, 15, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->setRTL(true);
        $pdf->SetFont('aealarabiya', '', 13);

        /* `?embed=1` يمنع الطباعة التلقائية في القالب — و`writeHTML()`
           لا ينفّذ السكربتات أصلاً، لكنّ المُعامل يبقى كي لا يختلف
           المخرَجان. */
        $html = view('sales::payment.receipt.pdf_repeatA4', [
            'receipt' => $receipt,
        ])->render();

        $pdf->writeHTML($html, true, false, true, false, '');

        OperationLog::record(
            'exported_pdf',
            self::LOG_MODULE,
            $receipt,
            'تنزيل سند القبض رقم '.$number.' ملفَّ PDF',
        );

        return $pdf->Output('receipt-'.$number.'.pdf', 'I');
    }

    public function cancel($id)
    {
        // منع إلغاء عملية الدفع
        return back()->with('error', 'لا يمكنك إلغاء عملية الدفع');
    }

    private function cancelOld($id)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $payment = PaymentsProcess::findOrFail($id);

            $invoice = Invoice::findOrFail($payment->invoice_id);
            $client = Client::find($invoice->client_id);

            // استرجاع رصيد الخزينة
            $mainTreasuryAccount = null;
            if ($user && $user->employee_id) {
                $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();
                if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                    $mainTreasuryAccount = Account::find($treasuryEmployee->treasury_id);
                }
            }
            $mainTreasuryAccount = $mainTreasuryAccount ?? Account::where('name', 'الخزينة الرئيسية')->first();

            if ($mainTreasuryAccount) {
                $mainTreasuryAccount->balance -= $payment->amount;
                $mainTreasuryAccount->save();
            }

            // استرجاع رصيد العميل
            $clientAccount = Account::ledger()->where('client_id', $invoice->client_id)->first();
            if ($clientAccount) {
                $clientAccount->balance += $payment->amount;
                $clientAccount->save();
            }

            // تحديث بيانات الفاتورة
            $totalPreviousPayments = PaymentsProcess::where('invoice_id', $invoice->id)
                ->where('type', 'client payments')
                ->where('payment_status', '!=', 5)
                ->where('id', '!=', $payment->id)
                ->sum('amount');

            $invoice->advance_payment = $totalPreviousPayments;
            $invoice->due_value = $this->payableCeiling($invoice) - $totalPreviousPayments;

            if ($totalPreviousPayments == 0) {
                $invoice->is_paid = false;
                $invoice->payment_status = 3;
            } elseif ($totalPreviousPayments >= $this->payableCeiling($invoice)) {
                $invoice->is_paid = true;
                $invoice->payment_status = 1;
                $invoice->due_value = 0;
            } else {
                $invoice->is_paid = false;
                $invoice->payment_status = 2;
            }
            $invoice->save();

            // البحث عن القيد المحاسبي الأصلي للدفعة (وليس قيد الفاتورة)
            $referenceNumber = $payment->reference_number ?? $invoice->code;

            // طريقة 1: البحث بـ reference_type للقيود الجديدة
            $originalJournalEntry = JournalEntry::with('details')
                ->where('invoice_id', $invoice->id)
                ->where('status', 1)
                ->where('reference_type', ReferenceType::CUSTOMER_COLLECTION)
                ->where(function ($query) use ($referenceNumber, $invoice) {
                    $query->where('reference_number', $referenceNumber)
                        ->orWhere('description', 'like', '%دفعة للفاتورة رقم '.$invoice->code.'%');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            // طريقة 2: البحث بالوصف واستبعاد قيد الفاتورة الأصلي للقيود القديمة
            if (! $originalJournalEntry) {
                $originalJournalEntry = JournalEntry::with('details')
                    ->where('invoice_id', $invoice->id)
                    ->where('status', 1)
                    ->where(function ($query) use ($referenceNumber, $invoice) {
                        $query->where('reference_number', $referenceNumber)
                            ->orWhere('description', 'like', '%دفعة للفاتورة رقم '.$invoice->code.'%')
                            ->orWhere('description', 'like', '%تحديث دفعة للفاتورة رقم '.$invoice->code.'%');
                    })
                    // استبعاد قيود الفاتورة والمرتجعات
                    ->where('description', 'not like', '%فاتورة مبيعات%')
                    ->where('description', 'not like', '%مرتجع%')
                    ->whereNull('reference_type') // القيود القديمة لا تحتوي على reference_type
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            if (! $originalJournalEntry) {
                // تسجيل تفاصيل للمساعدة في التشخيص
                Log::warning('لم يتم العثور على القيد المحاسبي للدفعة', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'invoice_code' => $invoice->code,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date,
                    'reference_number' => $referenceNumber,
                ]);

                DB::rollBack();

                return back()->with('error', 'لم يتم العثور على القيد المحاسبي المرتبط بهذه الدفعة. يرجى التأكد من وجود القيد أو مراجعة المحاسب.');
            }

            // إنشاء قيد عكسي بدلاً من حذف القيد الأصلي
            $reversalJournalEntry = JournalEntry::create([
                'reference_number' => 'REV-'.$originalJournalEntry->reference_number,
                'date' => now(),
                'description' => 'قيد عكسي لإلغاء دفعة للفاتورة رقم '.$invoice->code.' - ملغي من القيد رقم #'.$originalJournalEntry->id,
                'status' => 1,
                'currency' => 'SAR',
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
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

            // تحديث القيد الأصلي للدفعة ليظهر أنه ملغي
            $originalJournalEntry->update([
                'status' => 0, // حالة ملغي
                'description' => $originalJournalEntry->description.' [ملغي - القيد العكسي #'.$reversalJournalEntry->id.']',
            ]);

            Log::info('تم إنشاء قيد عكسي لإلغاء الدفعة', [
                'original_journal_id' => $originalJournalEntry->id,
                'reversal_journal_id' => $reversalJournalEntry->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ]);

            // لا يتم عكس قيد الفاتورة، فقط عكس قيد الدفع وتحديث الرصيد المستحق

            // ذف الإشعارات القديمة
            notifications::where('type', 'invoice_payment')
                ->where('description', 'like', '%عملية دفع للفاتورة رقم '.$invoice->id.'%')
                ->delete();

            // إنشاء إشعار إلغاء
            notifications::create([
                'type' => 'invoice_payment_cancelled',
                'title' => $user->name.' ألغى عملية دفع',
                'description' => 'تم إلغاء عملية دفع للفاتورة رقم '.$invoice->id.' للعميل '.($client->trade_name ?? 'غير محدد').' بقيمة '.number_format($payment->amount, 2).' ر.س وتم إنشاء قيد عكسي رقم '.$reversalJournalEntry->id,
            ]);

            // حذف المرفقات — ما كان داخل مجلد المرفوعات وحده (القيمة من القاعدة لا تُتبع خارجه)
            DocumentAttachments::delete($payment->attachments);

            // تحديث حالة الدفعة بدلاً من حذفها (للاحتفاظ بالسجل)
            $payment->update([
                'payment_status' => 5, // حالة ملغية
                'notes' => ($payment->notes ?? '').' - تم إلغاء هذه الدفعة في '.now()->format('Y-m-d H:i:s').' بواسطة '.$user->name,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
            ]);

            // تحديث الرصيد المستحق في الفاتورة بعد عكس القيود
            $invoice->refresh();
            $totalPreviousPayments = PaymentsProcess::where('invoice_id', $invoice->id)
                ->where('type', 'client payments')
                ->where('payment_status', '!=', 5)
                ->sum('amount');
            $invoice->advance_payment = $totalPreviousPayments;
            $invoice->due_value = $this->payableCeiling($invoice) - $totalPreviousPayments;
            if ($totalPreviousPayments == 0) {
                $invoice->is_paid = false;
                $invoice->payment_status = 3;
            } elseif ($totalPreviousPayments >= $this->payableCeiling($invoice)) {
                $invoice->is_paid = true;
                $invoice->payment_status = 1;
                $invoice->due_value = 0;
            } else {
                $invoice->is_paid = false;
                $invoice->payment_status = 2;
            }
            $invoice->save();

            DB::commit();

            // أقساطُ الفاتورة وربحُها المحقَّق تتبع السند — لا حين تُفتح شاشةُ التقسيط
            InstallmentCollections::syncInvoice($invoice->id);

            OperationLog::record(
                'cancelled',
                self::LOG_MODULE,
                $payment,
                'إلغاء دفعة **'.($payment->reference_number ?: '#'.$payment->id).'**'
                    .' بقيد عكسيّ رقم '.$reversalJournalEntry->id
                    .' — المبلغ: '.CurrencySettings::format($payment->amount),
            );

            return redirect()
                ->route('paymentsClient.index')
                ->with('success', sprintf(
                    'تم إلغاء عملية الدفع بنجاح وإنشاء قيد عكسي رقم %d. تم استرداد مبلغ %s ر.س، المبلغ المستحق للفاتورة الآن: %s ر.س',
                    $reversalJournalEntry->id,
                    number_format($payment->amount, 2),
                    number_format($invoice->due_value, 2)
                ));

        } catch (\Exception $e) {
            DB::rollBack();

            OperationLog::failure($e, self::LOG_MODULE, 'cancel', (int) $id);

            return back()->with('error', 'حدث خطأ أثناء إلغاء الدفع: '.$e->getMessage());
        }
    }

    /**
     * عرض صفحة الدفع المجمع للفواتير
     * يسمح بتسديد مبلغ واحد لعدة فواتير
     */
    public function bulkPaymentCreate(Request $request)
    {
        $user = auth()->user();

        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.payments.bulk)

        /* قائمةُ العميل بمحورَي النطاق معاً — الصلاحيةُ **وفرعُ الشريط
           العلويّ**. كانت بالصلاحية وحدها، فمن بدّل الشريط إلى فرعٍ بعينه
           يجد في المُنتقي عملاء المنشأة كلِّها. والفرعُ الرئيسيّ يعني
           المنشأة كلَّها فلا يُرشِّح — انظر `HeaderBranchScope`. */
        $clients = ClientVisibilityScope::applyDocumentSelectScope(
            Client::orderBy('trade_name'),
            $user,
        )->get();

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
            /* من «الحسابات المحاسبية» لا بالبحث عن الاسم «الخزينة
               الرئيسية» حرفياً: منشأةٌ سمّت خزينتها غير ذلك تفتح الشاشة
               بلا خزينة. وهو ما تفعله `create()` على الصواب. */
            $mainTreasuryAccount = $this->accountingAccount('main_treasury');
            $treasury_id = $mainTreasuryAccount->id ?? null;
        }

        $account_setting = AccountSetting::where('user_id', $user->id)->first();

        // تحديد العميل المحدد مسبقاً (إذا تم تمريره من صفحة العميل)
        $selected_client_id = $request->get('client_id');

        return view('sales::payment.bulk_payment', compact(
            'clients',
            'paymentMethods',
            'mainTreasuryAccount',
            'treasury_id',
            'account_setting',
            'selected_client_id'
        ));
    }

    /**
     * جلب الفواتير المستحقة لعميل معين عبر AJAX
     */
    public function getClientUnpaidInvoices(Request $request)
    {
        $clientId = $request->client_id;

        if (! $clientId) {
            return response()->json(['invoices' => [], 'total_due' => 0]);
        }

        $user = auth()->user();

        // جلب الفواتير غير المدفوعة بالكامل
        $query = Invoice::where('client_id', $clientId)
            ->whereIn('payment_status', [2, 3]) // غير مكتمل أو مسودة
            ->where('due_value', '>', 0)
            // فاتورة المرتجع نفسها ليست مستحقّة سداد — نفس القيد المطبَّق
            // بجهة الموردين (PurchasePaymentController::getSupplierUnpaidInvoices)
            ->where('type', '!=', 'returned')
            // ولا الفاتورة التي رُدَّت (أُنشئ ضدّها مرتجع): أثرها عُكِس فعلاً
            ->whereDoesntHave('relatedDocuments', fn ($q) => $q->where('type', 'returned'))
            // ولا التي صدر عليها إشعار دائن سارٍ (0 = ملغي، يُستثنى من الاستبعاد)
            ->whereDoesntHave('creditNotifications', fn ($q) => $q->where('status', '!=', 0));

        // فواتيرُ نطاقي وحدَها تُعرض للسداد (`ScopesSalesVisibility`)
        $this->scopeSalesDocuments($query);

        $invoices = $query->orderBy('created_at', 'asc')->get();

        $invoicesData = $invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'code' => $invoice->code,
                'invoice_date' => $invoice->invoice_date,
                'grand_total' => round($invoice->grand_total, 2),
                'advance_payment' => round($invoice->advance_payment ?? 0, 2),
                'due_value' => round($invoice->due_value, 2),
                'payment_status' => $invoice->payment_status,
                'payment_status_text' => $this->getPaymentStatusText($invoice->payment_status),
            ];
        });

        $totalDue = $invoices->sum('due_value');

        return response()->json([
            'invoices' => $invoicesData,
            'total_due' => round($totalDue, 2),
        ]);
    }

    /**
     * الحصول على نص حالة الدفع
     */
    private function getPaymentStatusText($status)
    {
        return match ($status) {
            1 => 'مكتمل',
            2 => 'غير مكتمل',
            3 => 'مسودة',
            4 => 'تحت المراجعة',
            5 => 'ملغي',
            default => 'غير معروف',
        };
    }

    /**
     * تنفيذ الدفع المجمع للفواتير
     */
    public function bulkPaymentStore(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|exists:payment_methods,id',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ], [
            'client_id.required' => 'يرجى اختيار العميل',
            'payment_amount.required' => 'يرجى إدخال مبلغ الدفع',
            'payment_amount.min' => 'مبلغ الدفع يجب أن يكون أكبر من صفر',
            'invoice_ids.required' => 'يرجى اختيار فاتورة واحدة على الأقل',
            'invoice_ids.min' => 'يرجى اختيار فاتورة واحدة على الأقل',
        ]);

        $user = auth()->user();

        // ✅ صلاحية الإضافة محروسة أصلاً في المُنشئ (permission:sales.payments.bulk)

        try {
            DB::beginTransaction();

            $clientId = $request->client_id;
            $totalPaymentAmount = floatval($request->payment_amount);
            $invoiceIds = $request->invoice_ids;
            $remainingAmount = $totalPaymentAmount;

            // جلب الفواتير المحددة مرتبة حسب التاريخ (الأقدم أولاً)
            $invoices = Invoice::whereIn('id', $invoiceIds)
                ->where('client_id', $clientId)
                ->where('due_value', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($invoices->isEmpty()) {
                return back()->with('error', 'لا توجد فواتير مستحقة للدفع');
            }

            // التحقق من أن إجمالي المستحق لا يقل عن المبلغ المدفوع
            $totalDue = $invoices->sum('due_value');

            // تحديد الخزينة
            $mainTreasuryAccount = null;
            if ($user && $user->employee_id) {
                $treasuryEmployee = TreasuryEmployee::where('employee_id', $user->employee_id)->first();
                if ($treasuryEmployee && $treasuryEmployee->treasury_id) {
                    $mainTreasuryAccount = Account::where('id', $treasuryEmployee->treasury_id)->first();
                }
            }
            if (! $mainTreasuryAccount) {
                $mainTreasuryAccount = Account::where('name', 'الخزينة الرئيسية')->first();
            }
            if (! $mainTreasuryAccount) {
                throw new \Exception('لا توجد خزينة متاحة. يرجى التحقق من إعدادات الخزينة.');
            }

            // جلب حساب العميل
            $clientAccount = Account::ledger()->where('client_id', $clientId)->first();
            if (! $clientAccount) {
                throw new \Exception('حساب العميل غير موجود');
            }

            $client = Client::find($clientId);
            $paymentsCreated = [];
            $invoicesUpdated = [];
            $receiptJournals = [];

            // توزيع المبلغ على الفواتير
            foreach ($invoices as $invoice) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $dueValue = floatval($invoice->due_value);
                $paymentForThisInvoice = min($remainingAmount, $dueValue);

                if ($paymentForThisInvoice <= 0) {
                    continue;
                }

                /* فاتورةٌ تمنع التجزئة لا يُسجَّل عليها جزء: تُتخطّى إلى التالية،
                   ويُذكر ما لم يُستعمل من المبلغ في رسالة النتيجة. */
                if (PartialPaymentRule::blocks($invoice, $paymentForThisInvoice, $dueValue)) {
                    continue;
                }

                // حساب إجمالي المدفوعات السابقة للفاتورة
                $totalPreviousPayments = PaymentsProcess::where('invoice_id', $invoice->id)
                    ->where('type', 'client payments')
                    ->where('payment_status', '!=', 5)
                    ->sum('amount');

                $newTotalPayments = $totalPreviousPayments + $paymentForThisInvoice;

                // تحديد حالة الدفع للفاتورة
                if ($newTotalPayments >= $this->payableCeiling($invoice)) {
                    $invoicePaymentStatus = 1; // مكتمل
                    $invoice->is_paid = true;
                    $invoice->due_value = 0;
                } else {
                    $invoicePaymentStatus = 2; // غير مكتمل
                    $invoice->is_paid = false;
                    $invoice->due_value = $this->payableCeiling($invoice) - $newTotalPayments;
                }

                // إنشاء سجل الدفع
                $payment = PaymentsProcess::create([
                    'invoice_id' => $invoice->id,
                    'branch_id' => $invoice->branch_id ?? current_branch_id(),
                    'amount' => $paymentForThisInvoice,
                    'payment_date' => $request->payment_date,
                    'payment_method' => $request->payment_method,
                    'reference_number' => $request->reference_number,
                    'notes' => ($request->notes ?? '').' [دفع مجمع]',
                    'type' => 'client payments',
                    'payment_status' => $invoicePaymentStatus,
                    'created_by' => $user->id,
                    'employee_id' => $user->id,
                    'treasury_id' => $mainTreasuryAccount->id,
                ]);

                $paymentsCreated[] = $payment;

                // إنشاء قيد محاسبي للدفعة
                $journalEntry = JournalEntry::create([
                    'reference_number' => $request->reference_number ?? 'BULK-'.$invoice->code,
                    'date' => $request->payment_date,
                    'description' => 'دفعة مجمعة للفاتورة رقم '.$invoice->code.' - العميل: '.($client->trade_name ?? ''),
                    'status' => 1,
                    'currency' => 'SAR',
                    'client_id' => $clientId,
                    'invoice_id' => $invoice->id,
                    'created_by_employee' => $user->id,
                    'reference_type' => ReferenceType::CUSTOMER_COLLECTION,
                    'linking_type' => LinkingType::SINGLE,
                ]);

                // حساب الخزينة (مدين)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $mainTreasuryAccount->id,
                    'description' => 'استلام دفعة مجمعة - فاتورة '.$invoice->code,
                    'debit' => $paymentForThisInvoice,
                    'credit' => 0,
                    'is_debit' => true,
                ]);

                // حساب العميل (دائن)
                JournalEntryDetail::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $clientAccount->id,
                    'description' => 'دفعة مجمعة من العميل - فاتورة '.$invoice->code,
                    'debit' => 0,
                    'credit' => $paymentForThisInvoice,
                    'is_debit' => false,
                ]);

                // تحديث الفاتورة
                $invoice->advance_payment = $newTotalPayments;
                $invoice->payment_status = $invoicePaymentStatus;
                $invoice->save();

                $receiptJournals[$invoice->id] = $journalEntry;

                $invoicesUpdated[] = [
                    'code' => $invoice->code,
                    'paid' => $paymentForThisInvoice,
                    'status' => $invoicePaymentStatus == 1 ? 'مكتمل' : 'غير مكتمل',
                ];

                /* السجلّات الثلاثة معاً كما في `store()`.
                   كان هنا سطرُ `Log::create` وحده: نشاطٌ بلا عنوان IP ولا
                   متصفّح ولا مسار، ولا سطرَ في «سجلّ الأحداث» إطلاقاً —
                   فالدفعُ المجمَّع، وهو يمسّ فواتيرَ عدّة وقيوداً عدّة،
                   كان أقلَّ العمليات أثراً في السجلّات. */
                OperationLog::record(
                    'created',
                    self::LOG_MODULE,
                    $payment,
                    'دفعة مجمَّعة على الفاتورة **'.$invoice->code.'**'
                        .' — العميل: '.($client->trade_name ?? '—')
                        .' — المبلغ: '.CurrencySettings::format($paymentForThisInvoice),
                );

                $remainingAmount -= $paymentForThisInvoice;
            }

            // تحديث رصيد الخزينة
            $mainTreasuryAccount->balance += $totalPaymentAmount;
            $mainTreasuryAccount->save();

            // تحديث رصيد حساب العميل
            $clientAccount->balance -= $totalPaymentAmount;
            $clientAccount->save();

            // إنشاء إشعار
            notifications::create([
                'type' => 'bulk_payment',
                'title' => $user->name.' أنشأ دفعة مجمعة',
                'description' => 'دفعة مجمعة للعميل '.($client->trade_name ?? '').' بقيمة '.number_format($totalPaymentAmount, 2).' ر.س لعدد '.count($invoicesUpdated).' فواتير',
            ]);

            DB::commit();

            // أقساطُ الفواتير وربحُها المحقَّق تتبع السندات
            foreach ($invoices as $paidInvoice) {
                InstallmentCollections::syncInvoice($paidInvoice->id, $receiptJournals[$paidInvoice->id] ?? null);
            }

            // إعداد رسالة النجاح
            $invoicesSummary = collect($invoicesUpdated)->map(function ($inv) {
                return $inv['code'].' ('.number_format($inv['paid'], 2).' - '.$inv['status'].')';
            })->implode('، ');

            $remainingMessage = $remainingAmount > 0
                ? ' - المبلغ المتبقي غير المستخدم: '.number_format($remainingAmount, 2).' ر.س'
                : '';

            return redirect()
                ->route('paymentsClient.index')
                ->with('success', sprintf(
                    'تم تسجيل الدفعة المجمعة بنجاح! المبلغ الكلي: %s ر.س - الفواتير: %s%s',
                    number_format($totalPaymentAmount, 2),
                    $invoicesSummary,
                    $remainingMessage
                ));

        } catch (\Exception $e) {
            DB::rollBack();

            /* سجلّ الأخطاء وسجلّ الأحداث معاً — كان `Log::error` وحده،
               وهو ملفّ لارافيل لا الشاشةُ التي بُنيت للأخطاء. */
            OperationLog::failure($e, self::LOG_MODULE, 'bulk_store');

            return back()
                ->with('error', 'حدث خطأ أثناء تسجيل الدفعة المجمعة: '.$e->getMessage())
                ->withInput();
        }
    }
}
