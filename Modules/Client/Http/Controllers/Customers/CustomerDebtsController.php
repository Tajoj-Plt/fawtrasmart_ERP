<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Region_groub;
use App\Models\Statuses;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Client\Services\CustomerLedgerService;

/**
 * مديونيةُ الفواتير وأعمارُها — التقريرُ الوحيد المبنيُّ على المستند لا على
 * الأستاذ، وهو مقصودٌ: سؤالُه «أيُّ **فاتورةٍ** بعينها لم تُسدَّد؟» لا «كم
 * على العميل؟».
 *
 * ══════════ ثلاثةُ أعطابٍ أُصلحت ══════════
 *
 * **١. تاريخُ القطع كان مُهمَلاً في المدفوعات.** كانت الفواتيرُ تُقيَّد
 * بالمدى بينما مدفوعاتُها تُجمع بلا حدّ (`payments->sum('amount')`). فتقريرُ
 * «حتى ٣١/١٢/٢٠٢٥» كان يطرح من فاتورةٍ ديسمبرية دفعةً وقعت في أغسطس ٢٠٢٦،
 * فيقول إنّها سُدِّدت قبل أن يُدفع فيها ريال. صارت المدفوعاتُ تُقيَّد
 * بـ`payment_date <= تاريخِ القطع`.
 *
 * **٢. العمرُ كان يُحسب من `now()`.** فتقريرٌ تاريخيٌّ يُخرج أعماراً بتاريخ
 * اليوم لا بتاريخ التقرير — والشرائحُ كلُّها تنزلق. صار من تاريخ القطع.
 *
 * **٣. لا تاريخَ استحقاق.** كان العمرُ من **تاريخ الفاتورة**، فيُعدُّ
 * متأخّراً من هو في مهلته الائتمانية. صار من تاريخ الاستحقاق =
 * `invoice_date + clients.credit_period` — وهو العمودُ الموجود فعلاً في
 * النظام (لا عمودَ `due_date` في `invoices`).
 *
 * ══════════ لماذا لا يساوي هذا التقريرُ تقريرَ الأرصدة ══════════
 *
 * لأنّهما يقيسان شيئين: هذا يقيس ما بقي على **الفواتير**، وذاك يقيس ما
 * على **الحساب**. والفرقُ بينهما حركاتٌ حقيقيةٌ لا مستندَ فاتورةٍ لها —
 * أرصدةٌ افتتاحية (٤٧١٬٠٥٠ ريالاً هنا)، وقيودُ يوميةٍ، وإشعاراتٌ، ودفعاتٌ
 * على الرصيد السابق لا على فاتورةٍ بعينها.
 *
 * فلا يُخفى الفرقُ ولا يُلفَّق: يُحسب ويُعرض في `reconciliation` بندَ بندٍ،
 * فيقرأ المراجعُ لماذا اختلف الرقمان بدل أن يظنّ أحدَهما خطأً.
 */
class CustomerDebtsController extends Controller
{
    use \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports;
    use \Modules\Client\Http\Controllers\Concerns\RespectsAccountingPeriods;

    public function __construct()
    {
        /* صلاحيةُ هذا التقرير وحدَه.

           كانت التركيبةُ الثلاثية `clients_view_all_reports|clients_view_own_reports|client_reports_view`
           مكرَّرةً حرفياً في التسعة، فمن فُتح له تقريرٌ انفتحت له
           التسعةُ جميعاً. واثنتان منها صلاحيتا **نطاق** لا عملية،
           وقد صار النطاقُ محوراً مستقلاً يقرؤه `App\Support\DataScope`. */
        $this->middleware('permission:clients.reports.debts');
    }

    /**
     * عرض صفحة تقرير أعمار ديون الفواتير
     */
    public function debtReconstructionInv(Request $request)
    {
        /* قائمتان لسؤالين مختلفين، وكانت واحدةً تخدمهما معاً:

           • «مسؤول المبيعات» يطابق `clients.employee_id` — معرّفُ **موظّف**،
             والقائمةُ من الأقسام المسموحة في إعدادات وحدات العملاء.
           • «تمت الإضافة بواسطة» يطابق `clients.user_id` — معرّفُ **مستخدم**.

           ودمجُهما في متغيّرٍ واحد كان يملأ الحقلين بفضاءِ معرّفاتٍ واحد،
           فأحدُهما يفلتر بمعرّفٍ لا يعني في جدوله ما يعنيه في الآخر. */
        $salesEmployees = $this->salesEmployeeOptions();
        $salesManagers = User::whereIn('role', ['employee', 'manager'])->get();
        $categories = CategoriesClient::all();
        $statuses = Statuses::assignable()->get();
        $customerTargets = $this->customerTargetOptions();
        $registrationStages = $this->registrationStageOptions();
        $neighborhoods = \App\Models\Neighborhood::select('name', 'region_id')
            ->groupBy('name', 'region_id')
            ->orderBy('name')
            ->get();

        $selectedClient = $request->filled('customer')
            ? Client::select('id', 'code', 'trade_name', 'first_name', 'last_name')->find($request->input('customer'))
            : null;

        $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');
        $report = $this->reportRows($request, $periodContext);

        return view('client::customers.CustomerReport.debt_reconstruction_inv', [
            // شجرةُ «تابع لـ» — المُنتقي التنظيمي الوحيد في الشاشة
            'treeNodes' => $this->affiliationTreeNodes(),
            'selectedClient' => $selectedClient,
            'salesEmployees' => $salesEmployees,
            'salesManagers' => $salesManagers,
            'categories' => $categories,
            'statuses' => $statuses,
            'registrationStages' => $registrationStages,
            'customerTargets' => $customerTargets,
            'neighborhoods' => $neighborhoods,
            'reportData' => $report['data'],
            'groupedCustomers' => $report['grouped_customers'],
            'totals' => $report['totals'],
            'reconciliation' => $report['reconciliation'],
            'fromDate' => Carbon::parse($periodContext['start']),
            'toDate' => Carbon::parse($periodContext['end']),
            'chartData' => $report['chart_data'],
            'invoicesCount' => count($report['data']),
            'periodContext' => $periodContext,
        ]);
    }

    /**
     * إرجاع بيانات تقرير أعمار ديون الفواتير عبر AJAX
     */
    public function invoiceDebtAgingAjax(Request $request)
    {
        try {
            $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');
            $report = $this->reportRows($request, $periodContext);

            return response()->json([
                'success' => true,
                'data' => $report['data'],
                'grouped_customers' => $report['grouped_customers'],
                'totals' => $report['totals'],
                'reconciliation' => $report['reconciliation'],
                'chart_data' => $report['chart_data'],
                'count' => count($report['data']),
                // النطاقُ المطبَّق فعلاً لا ما كتبه المستخدم
                'from_date' => $periodContext['start'],
                'to_date' => $periodContext['end'],
            ]);
        } catch (\Throwable $e) {
            Log::error('خطأ في تقرير أعمار ديون الفواتير: '.$e->getMessage().' - '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحميل البيانات',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * صفوفُ التقرير.
     *
     * ══════════ معنى المدى هنا ══════════
     *
     * `to_date` هو **تاريخُ القطع**: لا فاتورةَ بعده ولا دفعةَ بعده.
     *
     * و`from_date` **يُقيّد متى اختار المستخدمُ مدىً**: زرُّ فترةٍ سريعة، أو
     * تاريخان، أو سنةٌ مالية، أو فترةٌ محاسبية — فلا تظهر إلّا فواتيرُ ذلك
     * المدى. كان لا يُقيّد إلّا بخانة «فواتير الفترة فقط»، فزرُّ «شهر» يعرض
     * ١٧٢٤ فاتورةً منها ١٤٨٨ أقدمُ من الشهر، وقال المستخدم: «اذا فلترت حسب
     * الفترة يعطيني الذي لهم أكثر من الفترة».
     *
     * والتحميلُ الأوّل بلا مدىً مختار (`is_default`) يعرض المديونيةَ كلَّها
     * حتى تاريخ القطع. ومن أراد الديونَ الأقدمَ مع مدىً مختار يضع
     * `include_older`. و`restrict_to_period` القديمُ يُقيّد دائماً (روابطُ محفوظة).
     *
     * @param  array<string, mixed>  $period
     * @return array{data: array, grouped_customers: array, totals: array, reconciliation: array, chart_data: array}
     */
    private function reportRows(Request $request, array $period): array
    {
        // حدُّ البيانات لا يتجاوز اليوم — كاللوحة ({@see CustomerLedgerService::dataCutoff()})
        $cutoff = CustomerLedgerService::dataCutoff($period['end']);
        /* `$cutoff` يقصّ الفواتيرَ والمدفوعات؛ و`$cut` يقيس العمرَ وحدَه،
           فلا يتجاوز اليومَ ولو انتهى المدى في المستقبل — والمدى الافتراضي
           ينتهي بنهاية السنة المالية. {@see CustomerLedgerService::agingDate()} */
        $cut = CustomerLedgerService::agingDate($cutoff);
        $restrict = $request->boolean('restrict_to_period')
            || (! ($period['is_default'] ?? false) && ! $request->boolean('include_older'));

        /* أعمدةٌ بعينها لا الصفُّ كلُّه: `invoices` جدولٌ عريض (٥١ عموداً
           فيه نصوصٌ طويلة كـ`qrcode` و`payment_method`)، وتحميلُ ٥١٠٥ صفوفٍ
           كاملةً كان يستنفد ذاكرةَ الطلب قبل أن يُبنى صفٌّ واحد. */
        $query = Invoice::query()
            ->select([
                'id', 'code', 'client_id', 'branch_id', 'employee_id', 'type',
                'invoice_date', 'created_at', 'grand_total', 'total',
                'payment_status', 'is_paid', 'voided_at',
            ])
            ->with([
                'client:id,code,trade_name,first_name,last_name,branch_id,category_id,status_id,customer_target_id,credit_period,employee_id',
                'client.branch:id,name',
                'client.status_client:id,name,color',
                'client.registrationStage:id,name,color',
                'client.customerTarget:id,name,color',
                'branch:id,name',
                'employee.branch:id,name',
            ])
            ->where('type', '!=', 'returned')   // المرتجعاتُ مستنداتٌ عكسية لا ديون
            ->whereNull('voided_at')            // والملغاةُ لا دينَ عليها أصلاً
            ->whereNotNull('client_id')
            ->where('invoice_date', '<=', $cutoff);

        if ($restrict) {
            $query->where('invoice_date', '>=', $period['start']);
        }

        // فلاترُ العميل كلُّها — التعريفُ نفسُه المستعمَل في التقارير الشقيقة
        $query->whereHas('client', fn ($q) => $this->applyClientFilters($q, $request));

        /* فرعُ الفاتورة نفسِها إن طُلب صراحةً: `invoices.branch_id` مختومٌ
           لحظةَ البيع، وقد يخالف الجهةَ التي يتبع لها العميلُ اليوم. الفلترُ
           العامّ يمرّ على العميل، وهذا يضيف الفاتورةَ الصادرةَ من تلك الجهة
           ولو نُقل عميلُها بعدها. */
        if ($request->boolean('branch_by_invoice')) {
            $node = $this->requestedTreeNodeId($request);

            if ($node) {
                $query->whereIn('branch_id', $this->collectBranchAndDescendantIds($node));
            }
        }

        $invoices = $query->orderBy('invoice_date')->get();

        /* ══════════ المتبقّي من الأستاذ — كاللوحة ══════════

           كان المتبقّي على الفاتورة = إجماليُّها − مرتجعُها − دفعاتُها المربوطةُ
           بها (`payments_process`). والتحصيلُ «على الحساب» لا يُربط بفاتورة،
           فتبقى الفاتورةُ مستحقّةً ورقياً ورصيدُ العميل في الأستاذ صفرٌ أو دائن:
           ٣٠٦٬٨١٤ هنا مقابل ٢٧٧٬٠٣١ في لوحتَي العملاء والمبيعات وتقرير الأرصدة.

           صار المتبقّي **البنودَ المفتوحةَ في الأستاذ** (FIFO بعد مدّة الائتمان)
           — نفسَ ما تُعمّر به اللوحةُ وتقريرُ أعمار الديون — موزَّعةً على
           فواتيرها بـ`journal_entries.invoice_id`. وما بقي مفتوحاً بلا فاتورة
           (رصيدٌ افتتاحي، قيدُ تسوية، غرامة…) صفٌّ باسمه، فمجموعُ الجدول =
           مديونيةُ الأستاذ. */
        $clientScope = Client::query()->select('clients.id', 'clients.credit_period');
        $this->applyClientFilters($clientScope, $request);
        $periodsByClient = $clientScope->pluck('credit_period', 'id')->all();

        $accountToClient = [];
        foreach (array_chunk(array_keys($periodsByClient), 1000) as $chunk) {
            foreach (\App\Models\Account::ledger()->whereIn('client_id', $chunk)->get(['id', 'client_id']) as $account) {
                $accountToClient[(int) $account->id] = (int) $account->client_id;
            }
        }
        $periodsByAccount = array_map(fn ($clientId) => $periodsByClient[$clientId] ?? null, $accountToClient);

        $ledger = app(CustomerLedgerService::class);
        $openByInvoice = [];
        $looseItems = [];
        foreach ($ledger->openItems(array_keys($accountToClient), $cutoff, $periodsByAccount) as $accountId => $position) {
            if ((float) $position['balance'] <= 0.005) {
                continue; // رصيدٌ غير مدين لا دينَ فيه — كاللوحة
            }
            foreach ($position['items'] as $item) {
                $item['client_id'] = $accountToClient[$accountId] ?? null;
                if ($item['invoice_id']) {
                    $openByInvoice[$item['invoice_id']]['amount'] = ($openByInvoice[$item['invoice_id']]['amount'] ?? 0.0) + (float) $item['amount'];
                    $openByInvoice[$item['invoice_id']]['days'] = max($openByInvoice[$item['invoice_id']]['days'] ?? 0, (int) $item['days']);
                    $openByInvoice[$item['invoice_id']]['items'][] = $item;
                } else {
                    $looseItems[] = $item;
                }
            }
        }

        if ($invoices->isEmpty() && $looseItems === [] && $openByInvoice === []) {
            return $this->emptyReport($request, $period);
        }

        /* المرتجعاتُ تُطرح من فاتورتها الأصلية.

           كانت ورقةُ المرتجع تُستبعد صفّاً (`type != returned`) ولا يُطرح
           مبلغُها من الأصل، فالفاتورةُ المرتجعةُ بالكامل تبقى في التقرير
           «غير مسدَّدة» بكامل قيمتها. */
        [$returnedByInvoice, $refundedByInvoice] = $this->returnsByOriginal($invoices->pluck('id')->all(), $cutoff);

        $rows = [];
        $consumed = [];
        $paymentStatusFilter = $request->input('payment_status');
        $invoiceTypeFilter = $request->input('invoice_type');

        foreach ($invoices as $invoice) {
            $total = (float) ($invoice->grand_total ?? $invoice->total ?? 0);
            $returned = round((float) ($returnedByInvoice[$invoice->id] ?? 0), 2);

            // المتبقّي من الأستاذ — بنودُ الفاتورة المفتوحة (انظر أعلاه)
            $open = $openByInvoice[$invoice->id] ?? null;
            $consumed[$invoice->id] = true;
            $remaining = round((float) ($open['amount'] ?? 0.0), 2);

            // المرتجعةُ بالكامل لا دينَ عليها ولا هي «مسدَّدة» — تسقط من التقرير
            if ($returned > 0.005 && $returned >= $total - 0.005 && $remaining <= 0.005) {
                continue;
            }

            // المسدَّدُ = ما أطفأ الفاتورةَ في الأستاذ: إجماليُّها − مرتجعُها − المتبقّي
            $paid = max(0.0, round($total - $returned - $remaining, 2));

            // حالةُ السداد محسوبةً بتاريخ القطع، لا العمودُ المحفوظ (وهو
            // حالةُ اليوم فيكذب على كلّ تقريرٍ تاريخي)
            $paymentState = match (true) {
                $remaining <= 0.005 => 'paid',
                $paid > 0.005 => 'partial',
                default => 'unpaid',
            };

            if ($paymentStatusFilter && $paymentStatusFilter !== $paymentState) {
                continue;
            }

            if ($invoiceTypeFilter && $invoiceTypeFilter !== ($invoice->type ?? '')) {
                continue;
            }

            // «المسدَّدة» تُعرض فقط متى طُلبت صراحةً — التقريرُ تقريرُ ديون
            if ($remaining <= 0.005 && $paymentStatusFilter !== 'paid') {
                continue;
            }

            $client = $invoice->client;
            $invoiceDate = Carbon::parse($invoice->invoice_date ?? $invoice->created_at)->startOfDay();
            $creditDays = $ledger->sanitizeCreditPeriod($client?->credit_period);
            $dueDate = $invoiceDate->copy()->addDays($creditDays);

            // التأخّرُ من تاريخ **الاستحقاق** حتى تاريخ **القطع** — ومن بند الأستاذ حين يوجد
            $daysLate = $open ? (int) $open['days'] : (int) max(0, $dueDate->diffInDays($cut, false));

            $buckets = CustomerLedgerService::emptyBuckets();
            $buckets[CustomerLedgerService::bucketFor($daysLate)] = $remaining;

            $rows[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->code ?? $invoice->id,
                'invoice_type' => $invoice->type,
                'client_id' => $client?->id,
                'client_code' => $client?->code ?? 'غير محدد',
                'client_name' => $client?->display_name ?? 'غير محدد',
                /* «تابع لـ» يُقرأ من عقدة العميل الفعلية، ويرجع إلى فرع
                   الفاتورة المختوم لمن لا عقدةَ له. */
                'branch' => $client?->resolveTreeNode()?->name
                    ?? $invoice->branch->name
                    ?? $invoice->employee?->branch?->name
                    ?? 'غير محدد',
                'affiliation_type' => $client?->resolveTreeNode()?->displayTypeLabel() ?? '',
                'status_name' => $client?->status_client?->name ?? 'غير محدد',
                'status_color' => $client?->status_client?->color ?? '#6c757d',
                // «الوضع» = مرحلة التسجيل، حقلٌ مستقلٌّ عن الحالة
                // بلا وضعٍ تبقى القيمةُ فارغة: «الوضع» صار تحت «الحالة» في
                // العمود نفسِه، وشرطةٌ تحت كلّ حالةٍ ضجيجٌ لا معلومة
                'stage_name' => $client?->registrationStage?->name ?? null,
                'stage_color' => $client?->registrationStage?->color ?? '#6c757d',
                'target_name' => $client?->customerTarget?->name ?? 'بلا هدف',
                'target_color' => $client?->customerTarget?->color ?? '#6c757d',
                'invoice_date' => $invoiceDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'credit_period' => $creditDays,
                'days_late' => $daysLate,
                'total_amount' => round($total, 2),
                'returned_amount' => $returned,
                'paid_amount' => round($paid, 2),
                'total_due' => $remaining,
                'payment_state' => $paymentState,
                'payment_state_label' => ['paid' => 'مسدَّدة', 'partial' => 'مسدَّدة جزئياً', 'unpaid' => 'غير مسدَّدة'][$paymentState],
                'today' => $buckets['today'],
                'days1to30' => $buckets['days1to30'],
                'days31to60' => $buckets['days31to60'],
                'days61to90' => $buckets['days61to90'],
                'days91to120' => $buckets['days91to120'],
                'daysOver120' => $buckets['daysOver120'],
            ];
        }

        $rows = array_merge($rows, $this->looseLedgerRows($request, $looseItems, $openByInvoice, $consumed, $restrict, $period));

        // فلترُ كروت الأعمار — القيمُ المرسَلةُ من الشاشة هي هي كما كانت
        if ($request->filled('period_filter') && $request->input('period_filter') !== 'custom') {
            $column = [
                '1' => 'today',
                '7' => 'days1to30',
                '30' => 'days31to60',
                '90' => 'days61to90',
                '180' => 'days91to120',
                '365' => 'daysOver120',
            ][(string) $request->input('period_filter')] ?? null;

            if ($column) {
                $rows = array_values(array_filter($rows, fn ($row) => abs($row[$column]) > 0.005));
            }
        }

        /* «عمر الدين»: الفواتيرُ التي تجاوز عمرُ استحقاقها الحدَّ — `days_late`
           من تاريخ الاستحقاق (بعد مدّة الائتمان) إلى تاريخ القطع. */
        $debtAge = \App\Support\DebtAge::fromRequest($request);
        if ($debtAge !== null) {
            $rows = array_values(array_filter($rows, fn ($row) => \App\Support\DebtAge::exceeds($row['days_late'], $debtAge)));
        }

        usort($rows, fn ($a, $b) => $b['days_late'] <=> $a['days_late']);

        /* فواتيرُ مفتوحةٌ لعميلٍ **حسابُه غير مدين** (دائنٌ أو صفر) حتى تاريخ
           القطع: دفع على الحساب لا على الفاتورة، فالفاتورةُ «مستحقّة» ورقياً
           والعميلُ لا يدين فعلياً — بل قد يكون له رصيدٌ دائن. تُعلَّم ولا
           تُحذف، وتُجمع وحدَها كي لا تُقرأ ديناً حقيقياً. */
        $clientIds = array_values(array_unique(array_filter(array_column($rows, 'client_id'))));
        $ledgerBalanceByClient = [];
        if ($clientIds !== []) {
            $accountToClient = \App\Models\Account::ledger()->whereIn('client_id', $clientIds)->pluck('client_id', 'id')->all();
            foreach ($ledger->balancesAt(array_map('intval', array_keys($accountToClient)), $cutoff) as $accountId => $balance) {
                $clientId = (int) ($accountToClient[$accountId] ?? 0);
                $ledgerBalanceByClient[$clientId] = ($ledgerBalanceByClient[$clientId] ?? 0.0) + (float) $balance;
            }
        }
        foreach ($rows as $i => $row) {
            $accountBalance = round((float) ($ledgerBalanceByClient[(int) $row['client_id']] ?? 0.0), 2);
            $rows[$i]['account_balance'] = $accountBalance;
            $rows[$i]['account_not_debit'] = $accountBalance <= 0.005;
        }

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['client_name'].' #'.$row['client_id']][] = $row;
        }

        $sum = fn (string $key) => round(array_sum(array_column($rows, $key)), 2);

        $totals = [
            'invoices_count' => count($rows),
            'clients_count' => count($grouped),
            'total_amount' => $sum('total_amount'),
            'returned_amount' => $sum('returned_amount'),
            'paid_amount' => $sum('paid_amount'),
            'total_due' => $sum('total_due'),
            'today' => $sum('today'),
            'days1to30' => $sum('days1to30'),
            'days31to60' => $sum('days31to60'),
            'days61to90' => $sum('days61to90'),
            'days91to120' => $sum('days91to120'),
            'daysOver120' => $sum('daysOver120'),
        ];

        $totals['debt_age'] = $debtAge;

        $notDebitRows = array_filter($rows, fn ($row) => $row['account_not_debit']);
        $totals['not_debit_invoices'] = count($notDebitRows);
        $totals['not_debit_due'] = round(array_sum(array_column($notDebitRows, 'total_due')), 2);
        $totals['not_debit_clients'] = count(array_unique(array_column($notDebitRows, 'client_id')));
        $totals['not_debit_credit'] = round(abs(array_sum(array_filter(
            array_map(fn ($id) => $ledgerBalanceByClient[(int) $id] ?? 0.0, array_unique(array_column($notDebitRows, 'client_id'))),
            fn ($b) => $b < 0
        ))), 2);

        return [
            'data' => $rows,
            'grouped_customers' => $grouped,
            'totals' => $totals,
            'reconciliation' => $this->reconcile($request, $rows, $cutoff, $restrict),
            'chart_data' => [
                'aging_labels' => ['غير مستحق', '1-30 يوم', '31-60 يوم', '61-90 يوم', '91-120 يوم', '+120 يوم'],
                'aging_values' => [
                    $totals['today'], $totals['days1to30'], $totals['days31to60'],
                    $totals['days61to90'], $totals['days91to120'], $totals['daysOver120'],
                ],
            ],
        ];
    }

    /**
     * تسويةُ مديونية الفواتير مع رصيد الأستاذ.
     *
     * التقريرانِ يقيسان شيئين مختلفين، ومن حقّ المراجع أن يرى الفرقَ
     * مفسَّراً لا أن يكتشفه. فتُحسب مديونيةُ نفس العملاء من الأستاذ حتى
     * نفس تاريخ القطع، ويُعرض الفرقُ صريحاً.
     *
     * ولا تُحسب حين يقصر المستخدمُ الجدولَ على فواتير الفترة: المقارنةُ
     * حينئذٍ بين مدىً ومدىً آخر، ولا معنى لها.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function reconcile(Request $request, array $rows, string $cutoff, bool $restrict): array
    {
        if ($restrict) {
            return ['available' => false, 'reason' => 'الجدولُ مقصورٌ على فواتير الفترة — لا تُقارَن بالأستاذ.'];
        }

        /* جدولٌ مفلترٌ بحالة السداد أو شريحة العمر أو نوع الفاتورة جزءٌ من
           الدين لا كلُّه، ومقارنتُه برصيدٍ كامل تخترع «فرقاً» (٢٧٬٤٢٧ لشريحة
           +١٢٠ وحدَها) لا يقابله شيء. */
        if ($request->filled('payment_status') || $request->filled('invoice_type') || $request->filled(\App\Support\DebtAge::PARAM)
            || ($request->filled('period_filter') && $request->input('period_filter') !== 'custom')) {
            return ['available' => false, 'reason' => 'الجدولُ مفلترٌ بحالة السداد أو شريحة العمر أو نوع الفاتورة — لا يُقارَن برصيد الأستاذ الكامل.'];
        }

        try {
            $clientIds = array_values(array_unique(array_filter(array_column($rows, 'client_id'))));

            if ($clientIds === []) {
                return ['available' => false, 'reason' => 'لا فواتيرَ مستحقّة في هذا النطاق.'];
            }

            $accountIds = \App\Models\Account::ledger()
                ->whereIn('client_id', $clientIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $balances = app(CustomerLedgerService::class)->balancesAt($accountIds, $cutoff);
            $ledgerTotal = round(array_sum(array_filter($balances, fn ($b) => $b > 0)), 2);
            $invoiceTotal = round(array_sum(array_column($rows, 'total_due')), 2);

            return [
                'available' => true,
                'invoice_debt' => $invoiceTotal,
                'ledger_debt' => $ledgerTotal,
                'difference' => round($invoiceTotal - $ledgerTotal, 2),
                'note' => 'الفرقُ حركاتٌ على حساب العميل لا تُربط بفاتورةٍ في هذا الجدول، وتعمل في الاتجاهين: إشعاراتٌ دائنةٌ غيرُ مربوطةٍ بفاتورة وقيودُ يوميةٍ دائنة تُخفض الأستاذ، وأرصدةٌ افتتاحيةٌ تزيده ودفعاتُ الرصيد السابق تُخفضه. تقريرُ أرصدة العملاء وكشفُ الحساب يعرضان الرقمَ المحاسبي.',
            ];
        } catch (\Throwable $e) {
            Log::warning('تعذّرت تسويةُ مديونية الفواتير مع الأستاذ', ['error' => $e->getMessage()]);

            return ['available' => false, 'reason' => 'تعذّر حسابُ التسوية.'];
        }
    }

    /**
     * مبالغُ المرتجعات ومبالغُ ما رُدّ نقداً عنها، مفهرسةً بمعرّف الفاتورة الأصلية.
     *
     * الربطُ بالأصل: `reference_number` تكتبه المبيعاتُ ونقطةُ البيع والمتجرُ
     * جميعاً (معرّفَ الأصل نصّاً)، و`reference_id` أُضيف في 2026-09-01
     * للمبيعات وحدها — فيُقرأ هذا ويُرجع إلى ذاك في المرتجعات الأقدم.
     *
     * والصرفُ النقديُّ للعميل (`payments_process.type = treasury_return`)
     * مسجَّلٌ على ورقة المرتجع لا على الأصل، فيُنسب إلى الأصل عبرها.
     *
     * @param  array<int, int>  $invoiceIds
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    private function returnsByOriginal(array $invoiceIds, string $cutoff): array
    {
        $wanted = array_flip($invoiceIds);

        /* كلُّ المرتجعات حتى تاريخ القطع ثم الفرزُ هنا — لا `whereIn` بآلاف
           المعرّفات على عمودٍ نصّي، والمرتجعاتُ قليلةٌ أمام الفواتير. */
        $returns = DB::table('invoices')
            ->where('type', 'returned')
            ->whereNull('voided_at')
            ->where('invoice_date', '<=', $cutoff)
            ->get(['id', 'reference_id', 'reference_number', 'grand_total', 'total']);

        $originalOf = [];
        $returned = [];

        foreach ($returns as $return) {
            $original = (int) ($return->reference_id ?: $return->reference_number);

            if (! isset($wanted[$original])) {
                continue;
            }

            $originalOf[$return->id] = $original;
            $returned[$original] = ($returned[$original] ?? 0) + (float) ($return->grand_total ?? $return->total ?? 0);
        }

        $refunded = [];

        if ($originalOf !== []) {
            $refunds = DB::table('payments_process')
                ->whereIn('invoice_id', array_keys($originalOf))
                ->where('type', 'treasury_return')
                ->whereDate('payment_date', '<=', $cutoff)
                ->groupBy('invoice_id')
                ->pluck(DB::raw('SUM(amount)'), 'invoice_id');

            foreach ($refunds as $returnId => $amount) {
                $original = $originalOf[$returnId];
                $refunded[$original] = ($refunded[$original] ?? 0) + (float) $amount;
            }
        }

        return [$returned, $refunded];
    }

    /**
     * تقريرٌ فارغٌ بالبنية نفسِها — كي لا يسقط القالبُ على مفتاحٍ ناقص.
     *
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    /**
     * بنودُ الأستاذ المفتوحةُ التي لا فاتورةَ لها في الجدول — صفٌّ لكلّ بند.
     *
     * رصيدٌ افتتاحي (`OPB`)، أو قيدُ تسويةٍ أو غرامةٍ مدين، أو فاتورةٌ خرجت
     * من الجدول بفلترٍ (مدى الفترة، نوع الفاتورة). بدونها ينقص الجدولُ عن
     * المديونية. ولا تُعرض مع فلترٍ يخصّ الفواتير وحدَها (حالة السداد، نوع
     * الفاتورة، فرع الفاتورة) — فهي ليست فواتير.
     *
     * @param  array<int, array<string, mixed>>  $looseItems
     * @param  array<int, array<string, mixed>>  $openByInvoice
     * @param  array<int, bool>  $consumed
     * @return array<int, array<string, mixed>>
     */
    private function looseLedgerRows(Request $request, array $looseItems, array $openByInvoice, array $consumed, bool $restrict, array $period): array
    {
        if ($request->filled('payment_status') || $request->filled('invoice_type') || $request->boolean('branch_by_invoice')) {
            return [];
        }

        // فاتورةٌ لها بندٌ مفتوح ولم تُعرض (أقدمُ من المدى، أو مستندُ مرتجعٍ ردّ نقداً)
        foreach ($openByInvoice as $invoiceId => $open) {
            if (! isset($consumed[$invoiceId])) {
                array_push($looseItems, ...$open['items']);
            }
        }

        if ($restrict) {
            $looseItems = array_values(array_filter($looseItems, fn ($item) => ($item['date'] ?? '') >= $period['start']));
        }

        if ($looseItems === []) {
            return [];
        }

        $clients = Client::with([
            'branch:id,name',
            'status_client:id,name,color',
            'registrationStage:id,name,color',
            'customerTarget:id,name,color',
        ])->whereIn('id', array_unique(array_column($looseItems, 'client_id')))->get()->keyBy('id');

        $labels = [
            'OPB' => 'رصيد افتتاحي',
            'JV' => 'قيد يومية',
            'ACC' => 'قيد تسوية',
            'EXP' => 'سند صرف',
            'PCAN' => 'إلغاء سند قبض',
            'SR' => 'مرتجع (ردّ نقدي)',
        ];

        $rows = [];
        foreach ($looseItems as $item) {
            $client = $clients->get($item['client_id']);
            $amount = round((float) $item['amount'], 2);
            $days = (int) $item['days'];
            $buckets = CustomerLedgerService::emptyBuckets();
            $buckets[CustomerLedgerService::bucketFor($days)] = $amount;
            $type = (string) ($item['type'] ?? '');

            $rows[] = [
                'invoice_id' => $item['invoice_id'] ?? null,
                'invoice_number' => $item['reference'] ?: '—',
                'invoice_type' => 'ledger',
                'ledger_label' => $labels[$type] ?? ($type !== '' ? 'قيد '.$type : 'قيد مدين'),
                'client_id' => $client?->id,
                'client_code' => $client?->code ?? 'غير محدد',
                'client_name' => $client?->display_name ?? 'غير محدد',
                'branch' => $client?->resolveTreeNode()?->name ?? $client?->branch?->name ?? 'غير محدد',
                'affiliation_type' => $client?->resolveTreeNode()?->displayTypeLabel() ?? '',
                'status_name' => $client?->status_client?->name ?? 'غير محدد',
                'status_color' => $client?->status_client?->color ?? '#6c757d',
                'stage_name' => $client?->registrationStage?->name ?? null,
                'stage_color' => $client?->registrationStage?->color ?? '#6c757d',
                'target_name' => $client?->customerTarget?->name ?? 'بلا هدف',
                'target_color' => $client?->customerTarget?->color ?? '#6c757d',
                'invoice_date' => $item['date'],
                'due_date' => $item['due'],
                'credit_period' => 0,
                'days_late' => $days,
                'total_amount' => $amount,
                'returned_amount' => 0.0,
                'paid_amount' => 0.0,
                'total_due' => $amount,
                'payment_state' => 'unpaid',
                'payment_state_label' => $labels[$type] ?? 'قيد مدين',
                'today' => $buckets['today'],
                'days1to30' => $buckets['days1to30'],
                'days31to60' => $buckets['days31to60'],
                'days61to90' => $buckets['days61to90'],
                'days91to120' => $buckets['days91to120'],
                'daysOver120' => $buckets['daysOver120'],
            ];
        }

        return $rows;
    }

    private function emptyReport(Request $request, array $period): array
    {
        $buckets = CustomerLedgerService::emptyBuckets();

        return [
            'data' => [],
            'grouped_customers' => [],
            'totals' => array_merge($buckets, [
                'invoices_count' => 0,
                'clients_count' => 0,
                'total_amount' => 0,
                'returned_amount' => 0,
                'paid_amount' => 0,
                'total_due' => 0,
            ]),
            'reconciliation' => ['available' => false, 'reason' => 'لا فواتيرَ في هذا النطاق.'],
            'chart_data' => [
                'aging_labels' => ['غير مستحق', '1-30 يوم', '31-60 يوم', '61-90 يوم', '91-120 يوم', '+120 يوم'],
                'aging_values' => [0, 0, 0, 0, 0, 0],
            ],
        ];
    }

    /**
     * بحثُ العملاء لقوائم Select2 — انظر
     * {@see CustomerReportController::searchClients()} وهي الأصلُ الموحَّد.
     */
    public function searchClients(Request $request)
    {
        return app(CustomerReportController::class)->searchClients($request);
    }

    /**
     * جلب بيانات الفروع والمجموعات كشجرة هرمية
     */
    public function getBranchesRegionData()
    {
        try {
            return response()->json([
                'success' => true,
                'branches' => \App\Support\DataScope::branchOptions(Branch::orderBy('name'), ['id', 'name', 'is_main']),
                'regions' => Region_groub::select('id', 'name', 'territory_office_id')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($region) => [
                        'id' => $region->id,
                        'name' => $region->name,
                        'branch_id' => (int) $region->territory_office_id,
                    ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('خطأ في جلب بيانات الفروع والمجموعات: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'خطأ في تحميل البيانات'], 500);
        }
    }
}
