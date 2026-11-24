<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use App\Models\DailyClosingStockDetail;
use App\Models\User;
use App\Support\CurrencySettings;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Sales\Support\DailyClosingLedger;

/**
 * الإقفال اليومي — مطابقةُ يوم الموظف بما سجّله النظام.
 *
 * **نُقلت الشاشة من وحدة الموارد البشرية إلى المبيعات.** موضعُها الأوّل
 * كان خطأً في التصنيف: لا تقرأ الشاشة من جداول الموارد البشرية شيئاً سوى
 * ربط الموظف بمستودعه وعهدته، وكلّ ما عداه فواتيرُ بيعٍ وسنداتُ قبضٍ
 * ومرتجعاتٌ وقيودُ مبيعات. وقائمةُ المبيعات الجانبية كانت أصلاً تحمل
 * رابطها تحت «التقارير والإقفال» بينما الشيفرة في `Modules\HR`.
 *
 * **ما تغيّر مع النقل**
 *
 *   • بناءُ التفاصيل — مئتا سطرٍ كانت مكرّرةً ثلاثاً في هذا المتحكّم —
 *     انتقل إلى `Modules\Sales\Support\DailyClosingLedger` مرّةً واحدة.
 *   • أرقامُ الحسابات تُقرأ من شاشة «الحسابات المحاسبية» لا من ثوابت
 *     مكتوبة في الشيفرة.
 *   • كلُّ عملية تُكتب في سجلّ النشاط وسجلّ الأحداث، وكلُّ إخفاقٍ في
 *     سجلّ الأخطاء — عبر `OperationLog`. لم يكن يُكتب منها شيء.
 *   • التواريخ تُفحَص بضوابط «الفترات المحاسبية»، والنطاق الافتراضي
 *     للشاشة صار الفترةَ الجارية بدل تاريخ المنشأة كلّه.
 *   • النصوص من `resources/lang/{ar,en}/sales_daily_closing.php`،
 *     والمبالغ برمز العملة المضبوط في «العملات».
 */
class DailyClosingController extends Controller
{
    use RespectsAccountingPeriods;

    /** اسم الوحدة في سجلّات النظام — به يُجمع خطُّ العملية الزمني */
    protected const LOG_MODULE = 'SalesDailyClosing';

    public function __construct(protected DailyClosingLedger $ledger)
    {
        /*
         * الحراسة — نفس نمط `InvoicesPurchaseController`: صلاحيةٌ واحدة
         * مُنقَّطة لكل فعل، وتجميعُ الدوالّ التي تشترك فيها في `only`.
         *
         * كانت الشاشة بلا حراسةٍ إطلاقاً: أي مستخدمٍ مسجَّلٍ دخولُه يفتح
         * `sales/daily-closing` ويقفل ويحذف ويطبع. والرابط في الشريط
         * الجانبي كان يفحص `sales.daily_closing.view` وحدها — فحصٌ لا يمنع
         * شيئاً لأن كتابة العنوان في المتصفّح تتجاوز القائمة.
         *
         * `bulk` منفصلة عن `create`: الإقفال الجماعي وتقريره يمسّان كلّ
         * الموظفين لا الموظفَ وحده. و`print` تشمل الطباعة الفردية وتقرير
         * الفترة، بينما `bulkPrintReport` تتبع `bulk` لأنها تكشف أرقام
         * الموظفين جميعاً.
         *
         * وفحوصُ الملكية داخل الدوالّ تبقى فوق هذه الحراسة: الصلاحية تقول
         * «يُسمح له بالحذف»، و`seesAllClosings()` تقول «يحذف ماذا».
         *
         * ثم صار لكلّ زرٍّ في الشاشة صلاحيتُه (انظر مجموعة `daily_closing`
         * في `SalesPermissions`). و`autoStore` وحدها يصلها زرّان — «إقفال
         * تلقائي» و«تأكيد» نافذة المراجعة — فحارسُها `a|b` في سطرٍ واحد:
         * سطران منفصلان يُطلبان معاً لا أحدُهما.
         */
        $this->middleware('permission:sales.daily_closing.view', ['only' => ['index']]);
        $this->middleware('permission:sales.daily_closing.create', ['only' => ['store', 'getStockProducts']]);
        $this->middleware('permission:sales.daily_closing.review', ['only' => ['getSystemData']]);
        $this->middleware('permission:sales.daily_closing.auto_close|sales.daily_closing.review', ['only' => ['autoStore']]);
        $this->middleware('permission:sales.daily_closing.bulk', ['only' => ['bulkAutoStore']]);
        $this->middleware('permission:sales.daily_closing.bulk_report', ['only' => ['bulkPrintReport']]);
        $this->middleware('permission:sales.daily_closing.delete', ['only' => ['destroy']]);
        $this->middleware('permission:sales.daily_closing.print', ['only' => ['print']]);
        $this->middleware('permission:sales.daily_closing.print_report', ['only' => ['printReport']]);
    }

    // ══════════════════════ العرض ══════════════════════

    /**
     * قائمة الإقفالات — للمدير كلُّها، ولغيره إقفالاتُه وحدها.
     */
    /**
     * قائمة الإقفالات — للمدير كلُّها، ولغيره إقفالاتُه وحدها.
     *
     * لا نطاق تاريخٍ افتراضي: الشاشة تعرض كلّ السجلات حتى يفلتر المستخدم،
     * كما كانت. جُرِّب فرضُ الفترة المحاسبية الجارية افتراضاً فبدت الشاشة
     * فارغةً بلا سبب ظاهر لمن يفتحها، والفلتران قائمان لمن أرادهما.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        // النطاق من الصلاحية لا من اسم الدور — انظر `seesAllClosings()`
        $seesAll = $this->seesAllClosings();

        $query = DailyClosing::with('employee');

        if ($seesAll) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
        } else {
            $query->where('employee_id', $user->id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $closings = $query->orderBy('date', 'desc')->paginate(15)->appends($request->query());

        $employees = $seesAll
            ? $this->closableEmployees()->map(fn ($e) => (object) ['id' => $e->id, 'name' => $e->name])
            : collect();

        return view('sales::daily_closing.index', [
            'closings' => $closings,
            'employees' => $employees,
            'seesAll' => $seesAll,
            'today' => Carbon::today()->format('Y-m-d'),
        ]);
    }

    /**
     * أرقام النظام ليومٍ واحد — لشاشة المراجعة قبل الإقفال (AJAX).
     */
    public function getSystemData(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $employeeId = (int) auth()->id();
        $date = $request->date;

        if ($existing = $this->existingClosing($employeeId, $date)) {
            return response()->json([
                'success' => false,
                'message' => __('sales_daily_closing.messages.already_closed', ['date' => $date]),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->ledger->systemTotals($employeeId, ...$this->dayBounds($date)),
        ]);
    }

    /**
     * منتجات مستودع الموظف وكمياتها — لنموذج الإقفال اليدوي (AJAX).
     */
    public function getStockProducts(Request $request)
    {
        $employeeId = (int) auth()->id();
        $products = $this->ledger->stockProducts($employeeId);
        $date = $request->filled('date') && strtotime((string) $request->date)
            ? Carbon::parse($request->date)->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        return response()->json([
            'products' => $products,
            'total' => collect($products)->sum('system_quantity'),
            // زيارات يوم الإقفال — للعرض لا للمطابقة
            'visits' => $visits = $this->ledger->visits($employeeId, $date),
            // دوامُ اليوم مقابل الزيارات
            'work' => $this->ledger->work($employeeId, $date, $visits),
        ]);
    }

    // ══════════════════════ الإقفال ══════════════════════

    /**
     * إقفالٌ يدوي — لا يُحفظ إلّا إذا طابق كلُّ رقمٍ أدخله الموظف النظامَ.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'reported_sales' => 'required|numeric|min:0',
            'reported_cash_sales' => 'required|numeric|min:0',
            'reported_receipts' => 'required|numeric|min:0',
            'reported_returns' => 'required|numeric|min:0',
            'reported_cash_refunds' => 'nullable|numeric|min:0',
            'reported_expenses' => 'required|numeric|min:0',
            'stock_products' => 'required|array',
            'stock_products.*.product_id' => 'required|integer',
            'stock_products.*.reported_quantity' => 'required|numeric|min:0',
        ]);

        $employeeId = (int) auth()->id();
        $date = $request->date;

        if ($this->existingClosing($employeeId, $date)) {
            return back()->with('error', __('sales_daily_closing.messages.already_closed', ['date' => $date]));
        }

        if ($blocked = $this->accountingPeriodBlock($date)) {
            return back()->with('error', __('sales_daily_closing.messages.period_locked', ['reason' => $blocked]))->withInput();
        }

        $system = $this->ledger->systemTotals($employeeId, ...$this->dayBounds($date));
        $stockProducts = $request->stock_products ?? [];

        $errors = $this->mismatches($request, $system, $stockProducts);

        if (! empty($errors)) {
            OperationLog::record(
                'rejected',
                self::LOG_MODULE,
                null,
                __('sales_daily_closing.log.rejected', ['date' => $date]),
                ['old' => $this->reportedFrom($request), 'new' => $this->comparableSystem($system)]
            );

            return back()
                ->with('error', __('sales_daily_closing.messages.mismatch_intro')."\n".implode("\n", $errors))
                ->withInput();
        }

        try {
            $closing = DB::transaction(function () use ($request, $employeeId, $date, $system, $stockProducts) {
                $cashRefunds = (float) $request->input('reported_cash_refunds', 0);

                $closing = $this->createClosing($employeeId, $date, $system, [
                    'reported_sales' => $request->reported_sales,
                    'reported_cash_sales' => $request->reported_cash_sales,
                    'reported_receipts' => $request->reported_receipts,
                    'reported_cash_refunds' => $cashRefunds,
                    'reported_collections' => $request->reported_cash_sales + $request->reported_receipts - $cashRefunds,
                    'reported_returns' => $request->reported_returns,
                    'reported_expenses' => $request->reported_expenses,
                    'reported_stock_quantity' => collect($stockProducts)->sum('reported_quantity'),
                ], __('sales_daily_closing.messages.manual_note'));

                $this->storeStockDetails($closing, $system['stock_products'], $stockProducts);

                return $closing;
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return back()->with('error', $e->getMessage())->withInput();
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $closing,
            __('sales_daily_closing.log.created', ['date' => $date, 'employee' => auth()->user()->name]),
            ['new' => $this->comparableSystem($system)]
        );

        $this->notifyTelegram($closing);

        return redirect()->route('daily-closing.index')
            ->with('success', __('sales_daily_closing.messages.stored'));
    }

    /**
     * إقفالٌ تلقائي بضغطة زر — يأخذ أرقام النظام كما هي.
     */
    public function autoStore(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $employeeId = (int) auth()->id();
        $date = $request->date;

        if ($this->existingClosing($employeeId, $date)) {
            return response()->json([
                'success' => false,
                'message' => __('sales_daily_closing.messages.already_closed', ['date' => $date]),
            ], 422);
        }

        if ($blocked = $this->accountingPeriodBlock($date)) {
            return response()->json([
                'success' => false,
                'message' => __('sales_daily_closing.messages.period_locked', ['reason' => $blocked]),
            ], 422);
        }

        try {
            $closing = DB::transaction(function () use ($employeeId, $date) {
                $system = $this->ledger->systemTotals($employeeId, ...$this->dayBounds($date));

                $closing = $this->createClosing(
                    $employeeId,
                    $date,
                    $system,
                    $this->reportedMirrors($system),
                    __('sales_daily_closing.messages.auto_note')
                );

                $this->storeStockDetails($closing, $system['stock_products']);

                return $closing;
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'auto_store');

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        OperationLog::record(
            'created',
            self::LOG_MODULE,
            $closing,
            __('sales_daily_closing.log.auto_created', ['date' => $date, 'employee' => auth()->user()->name])
        );

        $this->notifyTelegram($closing);

        return response()->json([
            'success' => true,
            'message' => __('sales_daily_closing.messages.auto_closed'),
        ]);
    }

    /**
     * تقرير Telegram فور إقفال الموظف يومه — بأرقامه المعتمدة هنا مباشرةً،
     * لا بجدولةٍ عمياء تمرّ على كل الموظفين لاحقاً (راجع `app\Services\TelegramNotificationService::sendEmployeeDailyReport()`).
     * فشل الإرسال لا يجوز أن يُسقط الإقفال نفسه — قد أُنجز فعلاً.
     */
    private function notifyTelegram(DailyClosing $closing): void
    {
        try {
            app(\App\Services\TelegramNotificationService::class)->sendEmployeeDailyReport($closing);
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'telegram_notify');
        }
    }

    /**
     * إقفالٌ تلقائي لكل الموظفين ليومٍ واحد — للإدارة.
     *
     * كلُّ موظفٍ في معاملةٍ مستقلّة: إخفاقُ واحدٍ لا يُسقط الباقين، ويُكتب
     * وحده في سجلّ الأخطاء.
     */
    public function bulkAutoStore(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        // الحراسة الأولى على المسار وفي البانية (`sales.daily_closing.bulk`)؛
        // وهذه ثانيةٌ داخل الدالّة فلا يمرّ نداءٌ يصلها من طريقٍ آخر
        if (! auth()->user()->can('sales.daily_closing.bulk')) {
            return response()->json(['success' => false, 'message' => __('sales_daily_closing.denied.bulk')], 403);
        }

        $date = $request->date;

        if ($blocked = $this->accountingPeriodBlock($date)) {
            return response()->json([
                'success' => false,
                'message' => __('sales_daily_closing.messages.period_locked', ['reason' => $blocked]),
            ], 422);
        }

        $results = ['success_count' => 0, 'skipped_count' => 0, 'failed_count' => 0, 'details' => []];

        foreach ($this->closableEmployees() as $employee) {
            if ($this->existingClosing($employee->id, $date)) {
                $results['skipped_count']++;
                $results['details'][] = [
                    'employee' => $employee->name,
                    'status' => 'skipped',
                    'message' => __('sales_daily_closing.messages.already_closed', ['date' => $date]),
                ];

                continue;
            }

            try {
                $system = $this->ledger->systemTotals($employee->id, ...$this->dayBounds($date));

                if (! $this->ledger->hasActivity($system)) {
                    $results['skipped_count']++;
                    $results['details'][] = [
                        'employee' => $employee->name,
                        'status' => 'skipped',
                        'message' => __('sales_daily_closing.messages.no_activity'),
                    ];

                    continue;
                }

                DB::transaction(function () use ($employee, $date, $system) {
                    $closing = $this->createClosing(
                        $employee->id,
                        $date,
                        $system,
                        $this->reportedMirrors($system),
                        __('sales_daily_closing.messages.bulk_note')
                    );

                    $this->storeStockDetails($closing, $system['stock_products']);
                });

                $results['success_count']++;
                $results['details'][] = [
                    'employee' => $employee->name,
                    'status' => 'success',
                    'message' => __('sales_daily_closing.status.success'),
                    'sales' => $system['sales'],
                    'collections' => $system['collections'],
                ];
            } catch (\Throwable $e) {
                OperationLog::failure($e, self::LOG_MODULE, 'bulk_auto_store', $employee->id);

                $results['failed_count']++;
                $results['details'][] = [
                    'employee' => $employee->name,
                    'status' => 'failed',
                    'message' => __('sales_daily_closing.messages.bulk_failed_one'),
                ];
            }
        }

        if ($results['success_count'] > 0) {
            OperationLog::record(
                'bulk_created',
                self::LOG_MODULE,
                null,
                __('sales_daily_closing.log.bulk_created', ['date' => $date, 'count' => $results['success_count']])
            );
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'message' => __('sales_daily_closing.messages.bulk_done', [
                'success' => $results['success_count'],
                'skipped' => $results['skipped_count'],
                'failed' => $results['failed_count'],
                'total' => array_sum([$results['success_count'], $results['skipped_count'], $results['failed_count']]),
            ]),
        ]);
    }

    /**
     * حذف إقفال — للمدير، أو لصاحبه.
     */
    public function destroy($id)
    {
        $closing = DailyClosing::with('employee')->findOrFail($id);
        $user = auth()->user();

        // الصلاحية تقول «يُسمح له بالحذف»، وهذا السطر يقول «يحذف ماذا»:
        // من يرى إقفالات الجميع يحذف أيَّها، وغيرُه إقفالَه هو
        if (! $this->seesAllClosings() && $closing->employee_id !== $user->id) {
            return response()->json(['success' => false, 'message' => __('sales_daily_closing.denied.delete')], 403);
        }

        if ($blocked = $this->accountingPeriodBlock($closing->date)) {
            return response()->json([
                'success' => false,
                'message' => __('sales_daily_closing.messages.period_locked', ['reason' => $blocked]),
            ], 422);
        }

        $snapshot = [
            'date' => $closing->date,
            'employee' => optional($closing->employee)->name,
            'reported_collections' => $closing->reported_collections,
        ];

        try {
            DB::transaction(function () use ($closing) {
                $closing->stockDetails()->delete();
                $closing->delete();
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', $id);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        OperationLog::record(
            'deleted',
            self::LOG_MODULE,
            (int) $id,
            __('sales_daily_closing.log.deleted', [
                'date' => $snapshot['date'],
                'employee' => $snapshot['employee'] ?? '—',
            ]),
            ['old' => $snapshot]
        );

        return response()->json(['success' => true, 'message' => __('sales_daily_closing.messages.deleted')]);
    }

    // ══════════════════════ المطبوعات ══════════════════════

    /**
     * تفاصيل إقفالٍ محفوظ — سطراً سطراً.
     */
    public function print($id)
    {
        $closing = DailyClosing::with('employee')->findOrFail($id);
        $user = auth()->user();

        if (! $this->seesAllClosings() && $closing->employee_id !== $user->id) {
            abort(403, __('sales_daily_closing.denied.report'));
        }

        return view('sales::daily_closing.print', [
            'closing' => $closing,
            'employee' => $closing->employee,
            'date' => $closing->date,
            'stockDetails' => $closing->stockDetails()->get(),
            'data' => $this->ledger->breakdown($closing->employee_id, $closing->date),
        ]);
    }

    /**
     * تقريرُ إقفالاتٍ محفوظة على مدى فترة.
     */
    public function printReport(Request $request)
    {
        $user = auth()->user();
        $seesAll = $this->seesAllClosings();

        $query = DailyClosing::with(['employee', 'stockDetails']);

        if ($seesAll) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
        } else {
            $query->where('employee_id', $user->id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $closings = $query->orderBy('date')->get();

        // تُجلب مرّةً واحدة خارج الحلقة — كانت تُعاد بكلّ إقفال
        $cancelledExpenseRefs = $this->ledger->cancelledExpenseRefs();

        $closingsDetails = $closings->map(fn ($closing) => [
            'closing' => $closing,
            'employee' => $closing->employee,
            'stockDetails' => $closing->stockDetails,
        ] + $this->ledger->breakdown($closing->employee_id, $closing->date, $cancelledExpenseRefs))->all();

        return view('sales::daily_closing.print-report', [
            'closings' => $closings,
            'closingsDetails' => $closingsDetails,
            'seesAll' => $seesAll,
            'filterEmployee' => $seesAll && $request->filled('employee_id') ? User::find($request->employee_id) : null,
            'fromDate' => $request->from_date,
            'toDate' => $request->to_date,
        ]);
    }

    /**
     * تقريرُ يومٍ واحد لكل الموظفين — عرضاً وطباعةً بلا تخزين.
     */
    public function bulkPrintReport(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        if (! auth()->user()->can('sales.daily_closing.bulk_report')) {
            abort(403, __('sales_daily_closing.denied.report'));
        }

        $date = $request->date;
        $cancelledExpenseRefs = $this->ledger->cancelledExpenseRefs();
        $reportData = [];

        foreach ($this->closableEmployees() as $employee) {
            $data = $this->ledger->breakdown($employee->id, $date, $cancelledExpenseRefs);

            // من لا حركة له لا يُطبع له صفحةٌ فارغة
            $hasActivity = ($data['totalSales'] + $data['totalCashSales'] + $data['totalReceipts']
                + $data['totalCashRefunds'] + $data['totalReturns'] + $data['totalExpensesDetail']) > 0
                || $data['visits']['completed_count'] + $data['visits']['missed_count'] > 0;

            if (! $hasActivity) {
                continue;
            }

            $reportData[] = ['employee' => $employee] + $data;
        }

        return view('sales::daily_closing.bulk-print-report', compact('reportData', 'date'));
    }

    // ══════════════════════ الداخل ══════════════════════

    /**
     * هل يرى المستخدم إقفالات الموظفين جميعاً أم إقفالاته وحدها؟
     *
     * كان الجواب `hasRole('manager')` في ستّة مواضع — اسمَ دورٍ واحدٍ
     * حرفياً. فالمالك ومدير المبيعات، وهما فوق المدير في هذا النظام، لا
     * يريان إلّا إقفالاتهما هما، ومنشأةٌ تسمّي دورها «مشرف» بدل «manager»
     * تفقد الشاشة كلَّها. والجواب الآن صلاحية `sales.daily_closing.view_all`
     * تُمنح لمن شاءت شاشةُ الأدوار.
     *
     * وهي صلاحية **نطاق** لا فعل: لا تسمح بشيءٍ جديد، بل توسّع ما يقع عليه
     * ما سُمح به أصلاً — كما `sales.invoices.view_assigned_clients`.
     */
    protected function seesAllClosings(): bool
    {
        return (bool) auth()->user()?->can('sales.daily_closing.view_all');
    }

    /**
     * الموظفون الذين يُقفَل لهم — قائمةُ الفلتر وحلقةُ الإقفال الجماعي.
     *
     * النطاق `role = 'employee'` كما كان بلا تغيير: الإقفال الجماعي يكتب
     * صفَّ إقفالٍ لكلّ من في القائمة، فتوسيعُها إلى «كلّ من يملك صلاحية
     * الإقفال» يُنشئ إقفالاتٍ للمالك والمدير — وهم لا يبيعون في الميدان.
     * وحّدت هنا لأنّ الاستعلام كان مكرَّراً ثلاثاً.
     */
    protected function closableEmployees()
    {
        return User::where('role', 'employee')->orderBy('name')->get();
    }

    /** حدّا اليوم — بداية ونهاية. @return array{0: Carbon, 1: Carbon} */
    protected function dayBounds(string $date): array
    {
        return [Carbon::parse($date)->startOfDay(), Carbon::parse($date)->endOfDay()];
    }

    protected function existingClosing(int $employeeId, string $date): ?DailyClosing
    {
        return DailyClosing::where('employee_id', $employeeId)->where('date', $date)->first();
    }

    /** أرقام النظام تُنسخ حرفياً في خانات «المُبلَّغ» للإقفال التلقائي. */
    protected function reportedMirrors(array $system): array
    {
        return [
            'reported_sales' => $system['sales'],
            'reported_cash_sales' => $system['cash_sales'],
            'reported_receipts' => $system['receipts'],
            'reported_collections' => $system['collections'],
            'reported_returns' => $system['returns'],
            'reported_cash_refunds' => $system['cash_refunds'],
            'reported_expenses' => $system['expenses'],
            'reported_stock_quantity' => $system['stock_total'],
        ];
    }

    protected function createClosing(int $employeeId, string $date, array $system, array $reported, string $note): DailyClosing
    {
        return DailyClosing::create($reported + [
            'employee_id' => $employeeId,
            'date' => $date,
            'system_sales' => $system['sales'],
            'system_cash_sales' => $system['cash_sales'],
            'system_receipts' => $system['receipts'],
            'system_collections' => $system['collections'],
            'system_returns' => $system['returns'],
            'system_cash_refunds' => $system['cash_refunds'],
            'system_expenses' => $system['expenses'],
            'system_stock_quantity' => $system['stock_total'],
            'status' => 'success',
            'notes' => $note,
        ]);
    }

    /**
     * تفاصيل المخزون المحفوظة مع الإقفال.
     *
     * بلا `$reported` تُنسخ كميّاتُ النظام كما هي (الإقفال التلقائي).
     */
    protected function storeStockDetails(DailyClosing $closing, array $systemProducts, ?array $reported = null): void
    {
        $systemMap = collect($systemProducts)->keyBy('product_id');
        $rows = [];

        foreach ($reported ?? $systemProducts as $item) {
            $productId = $item['product_id'];
            $systemItem = $systemMap->get($productId);

            $rows[] = [
                'daily_closing_id' => $closing->id,
                'product_id' => $productId,
                'product_name' => $systemItem['product_name'] ?? ('منتج #'.$productId),
                'reported_quantity' => $reported === null
                    ? ($systemItem['system_quantity'] ?? 0)
                    : $item['reported_quantity'],
                'system_quantity' => $systemItem['system_quantity'] ?? 0,
            ];
        }

        // إدراجٌ واحد بدل استعلامٍ لكلّ منتج
        foreach (array_chunk($rows, 200) as $chunk) {
            DailyClosingStockDetail::insert($chunk);
        }
    }

    /**
     * فروقُ ما أدخله الموظف عمّا سجّله النظام — رسائل جاهزة للعرض.
     *
     * @return array<int, string>
     */
    protected function mismatches(Request $request, array $system, array $stockProducts): array
    {
        $errors = [];

        $comparisons = [
            'reported_sales' => ['sales', __('sales_daily_closing.fields.sales')],
            'reported_cash_sales' => ['cash_sales', __('sales_daily_closing.fields.cash_sales')],
            'reported_receipts' => ['receipts', __('sales_daily_closing.fields.receipts')],
            'reported_returns' => ['returns', __('sales_daily_closing.fields.returns')],
            'reported_cash_refunds' => ['cash_refunds', __('sales_daily_closing.fields.cash_refunds')],
            'reported_expenses' => ['expenses', __('sales_daily_closing.fields.expenses')],
        ];

        foreach ($comparisons as $input => [$systemKey, $label]) {
            $reported = (float) $request->input($input);

            if (abs($reported - $system[$systemKey]) >= 0.01) {
                $errors[] = __('sales_daily_closing.messages.mismatch_field', [
                    'field' => $label,
                    'reported' => CurrencySettings::format($reported),
                    'system' => CurrencySettings::format($system[$systemKey]),
                ]);
            }
        }

        $systemStockMap = collect($system['stock_products'])->keyBy('product_id');

        foreach ($stockProducts as $sp) {
            $systemItem = $systemStockMap->get($sp['product_id']);
            $systemQty = (float) ($systemItem['system_quantity'] ?? 0);
            $reportedQty = (float) $sp['reported_quantity'];

            if (abs($reportedQty - $systemQty) >= 0.01) {
                $errors[] = __('sales_daily_closing.messages.mismatch_stock', [
                    'product' => $systemItem['product_name'] ?? ('#'.$sp['product_id']),
                    'reported' => rtrim(rtrim(number_format($reportedQty, 2, '.', ''), '0'), '.'),
                    'system' => rtrim(rtrim(number_format($systemQty, 2, '.', ''), '0'), '.'),
                ]);
            }
        }

        return $errors;
    }

    /** ما أدخله الموظف — لتسجيله في فرق القيم عند الرفض. */
    protected function reportedFrom(Request $request): array
    {
        return $request->only([
            'reported_sales', 'reported_cash_sales', 'reported_receipts',
            'reported_returns', 'reported_cash_refunds', 'reported_expenses',
        ]);
    }

    /** أرقام النظام المقابلة لها. */
    protected function comparableSystem(array $system): array
    {
        return [
            'reported_sales' => $system['sales'],
            'reported_cash_sales' => $system['cash_sales'],
            'reported_receipts' => $system['receipts'],
            'reported_returns' => $system['returns'],
            'reported_cash_refunds' => $system['cash_refunds'],
            'reported_expenses' => $system['expenses'],
        ];
    }
}
