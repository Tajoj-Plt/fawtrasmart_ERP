<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Neighborhood;
use App\Models\PaymentsProcess;
use App\Models\Region_groub;
use App\Models\Statuses;
use App\Models\Treasury;
use App\Models\User;
use App\Support\AccountingSettings;
use App\Support\ClosedPeriodRanges;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Client\Services\CustomerLedgerService;
use Modules\Sales\Support\SalesLedger;

class CustomerReportController extends Controller
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
        $this->middleware('permission:clients.reports.view');
    }

    public function index()
    {
        return view('client::customers.index');
    }

    /**
     * جلب بيانات الفروع والمجموعات
     */
    public function getBranchesRegionData()
    {
        try {
            // جلب جميع الفروع مع معلومات المجموعات التابعة لها
            $branches = DataScope::branchOptions(Branch::with('regionGroups'), ['id', 'name'])
                ->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'region_ids' => $branch->regionGroups->pluck('id')->toArray(),
                    ];
                });

            // جلب جميع المجموعات مع معلومات الفرع التابعة له
            $regions = Region_groub::with('branch')
                ->select('id', 'name', 'territory_office_id')
                ->get()
                ->map(function ($region) {
                    return [
                        'id' => $region->id,
                        'name' => $region->name,
                        'branch_id' => $region->territory_office_id,
                    ];
                });

            return response()->json([
                'success' => true,
                'branches' => $branches,
                'regions' => $regions,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching branches and regions: '.$e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'خطأ في جلب البيانات',
                ],
                500,
            );
        }
    }

    /**
     * جلب الأحياء حسب المجموعة
     */
    public function getNeighborhoods(Request $request)
    {
        $regionGroupId = $request->get('region_group_id');

        if (! $regionGroupId) {
            return response()->json([]);
        }

        $neighborhoods = Neighborhood::where('region_id', $regionGroupId)->select('id', 'name')->get();

        return response()->json($neighborhoods);
    }

    /**
     * جلب المجموعات حسب الفرع
     */
    public function getRegionGroups(Request $request)
    {
        $branchId = $request->get('branch_id');

        if (! $branchId) {
            return response()->json([]);
        }

        $regionGroups = Region_groub::where('territory_office_id', $branchId)->select('id', 'name')->get();

        return response()->json($regionGroups);
    }

    /**
     * جلب المدن حسب المنطقة أو الفرع
     */
    public function getCities(Request $request)
    {
        $query = Client::with([
            'locations',
            'branch',
            'categoriesClient',
            'neighborhood.region', // العلاقة مع الأحياء والمجموعات
        ]);
        // فلترة حسب الفرع إذا تم تحديده
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // فلترة حسب المجموعة إذا تم تحديدها
        if ($request->filled('region_group_id')) {
            $query->whereHas('neighborhood', function ($q) use ($request) {
                $q->where('region_id', $request->region_group_id);
            });
        }

        // قائمةُ المدن تتبع نطاقَ الرؤية كبقيّة الشاشة — وإلّا أفشت
        // للمندوب خريطةَ انتشار المنشأة كلَّها من منسدلةٍ واحدة.
        $this->applyClientVisibilityScope($query);

        $cities = $query->pluck('city')->values();

        return response()->json($cities);
    }

    /**
     * قائمةُ العملاء لمُنتقي Select2 — نقطةُ النداء الوحيدة لكلّ التقارير.
     *
     * ══════════ لماذا AJAX ══════════
     *
     * كانت شاشاتُ التقارير تطبع `<option>` لكلّ عميل: ٣٨٥٢ عنصراً في كلّ
     * صفحة، تُحمَّل كلَّها قبل أن يُطلب تقريرٌ واحد. صارت تُحمَّل عشرون في
     * الطلب، والبقيةُ بالتمرير.
     *
     * ══════════ ولماذا يرث الفلاتر ══════════
     *
     * حين يختار المستخدمُ فرعاً ثمّ يفتح قائمةَ العملاء، فسؤالُه «عملاءُ
     * هذا الفرع» لا «كلُّ العملاء». فتُمرَّر الفلاترُ الحيّةُ من الشاشة
     * (`branch`, `cascade_node`, `status`, `customer_target_id`,
     * `category_client`) وتُطبَّق هنا بنفس تعريف التقارير — فلا تظهر في
     * القائمة أسماءٌ يستحيل أن يُرجع التقريرُ لها صفّاً.
     *
     * والبحثُ يمرّ على الاسم التجاري والشخصي والكود والجوال والهاتف
     * والبريد: من يبحث برقم جوالٍ كان لا يجد شيئاً لأنّ `mobile` عمودٌ
     * قائمٌ بذاته غيرُ `phone`، ولم يكن يُفحَص.
     */
    public function searchClients(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        $page = max(1, (int) $request->get('page', 1));
        $limit = 20;

        $query = Client::query();

        // الفلاترُ الحيّةُ من الشاشة — القائمةُ تتبع الفرعَ والحالةَ والهدف
        $this->applyClientFilters($query, $request->duplicate(
            array_diff_key($request->query(), array_flip(['customer', 'client_id', 'q', 'page']))
        ));

        if ($term !== '') {
            $like = '%'.$term.'%';

            $query->where(function ($q) use ($like) {
                $q->where('trade_name', 'LIKE', $like)
                    ->orWhere('first_name', 'LIKE', $like)
                    ->orWhere('last_name', 'LIKE', $like)
                    ->orWhere('code', 'LIKE', $like)
                    ->orWhere('mobile', 'LIKE', $like)
                    ->orWhere('phone', 'LIKE', $like)
                    ->orWhere('email', 'LIKE', $like);
            });
        }

        $total = (clone $query)->count();

        $clients = $query
            ->orderBy('trade_name')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get(['id', 'trade_name', 'first_name', 'last_name', 'code', 'mobile', 'phone', 'email']);

        return response()->json([
            'results' => $clients->map(fn ($client) => [
                'id' => $client->id,
                'text' => trim($client->code.' - '.$client->display_name),
                'title' => $client->mobile ?: ($client->phone ?: $client->email),
            ])->values(),
            'pagination' => ['more' => $page * $limit < $total],
        ]);
    }

    public function getEmployeeGroups(Request $request)
    {
        // هذه الدالة قد تحتاج إلى تنفيذ إذا كانت مطلوبة
        return response()->json([]);
    }

    public function getGroupClients(Request $request)
    {
        // هذه الدالة قد تحتاج إلى تنفيذ إذا كانت مطلوبة
        return response()->json([]);
    }

    /**
     * عرض صفحة مدفوعات العملاء.
     *
     * ⚠️ القالب كان يشير إلى متغيّراتٍ (`$branches`, `$clients`, ...) لم
     * تكن هذه الدالّة تمرّرها إطلاقاً — كل فتحٍ لهذه الصفحة كان يسقط بخطأ
     * "Undefined variable" فوراً. أُضيفت هنا نفس بيانات الفلاتر التي تجلبها
     * شاشات التقارير الشقيقة (حساب/أرصدة/أقساط) — قراءة قوائم فقط، لا
     * منطق أعمال. جلب بيانات المدفوعات الفعلية نفسها (`customerPaymentsAjax`)
     * ما زال غير مُنفَّذ — انظر ملاحظتها أدناه.
     */
    public function customerPayments(Request $request)
    {
        /* النتيجةُ تُبنى هنا لا بطلبٍ ثانٍ بعد التحميل: الشاشةُ تُقدَّم
           كاملةً — فلاترُها وجدولُها ومؤشّراتُها — كما في تقريرَي أعمار
           الديون. فرابطُها يحمل ما يُعرض فيه، ويصلح للحفظ والإرسال والرجوع
           بالمتصفّح، ولا يومض الجدولُ فارغاً ثمّ يمتلئ. */
        $report = $this->paymentsReportData($request);

        // التصديرُ والطباعةُ يطلبان الصفوفَ كلَّها — الصفحةُ لا تحمل إلّا صفحتَها
        if ($request->input('export') === 'rows') {
            return response()->json($this->paymentsExportRows($report['grouped_data']));
        }

        /* ══════════ ترقيمٌ على الخادم بالعميل (2026-09-28) ══════════

           كانت الصفحةُ تطبع كلَّ الدفعات (٨٧٥٣ صفّاً لسنة ٢٠٢٦، ٩٫٩ ميغابايت)
           ثمّ يُخفي المتصفّحُ ما عدا الصفحةَ الأولى — وفي كلّ صفٍّ `@include`
           مرّتين، وكلُّ `@include` يُشغّل مُركِّبَ العرض العامّ باستعلام: ١٠٥٧٣
           استعلاماً و١٢٨ ثانيةً رسماً. الآن تُرسم صفحةُ عملاءٍ واحدة، والمؤشّراتُ
           والرسمان والمجموعُ الكلّي من البيانات كلِّها كما كانت. */
        $perPage = in_array((int) $request->input('per_page'), self::PAYMENTS_PAGE_SIZES, true)
            ? (int) $request->input('per_page')
            : self::PAYMENTS_PAGE_SIZES[0];
        $groups = array_values($report['grouped_data']);
        $page = max(1, min((int) $request->input('page', 1), (int) ceil(max(count($groups), 1) / $perPage)));
        $offset = ($page - 1) * $perPage;
        $pagedGroups = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($groups, $offset, $perPage),
            count($groups),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('client::customers.CustomerReport.customer_payments', [
            'pagedGroups' => $pagedGroups,
            'perPage' => $perPage,
            'pageSizes' => self::PAYMENTS_PAGE_SIZES,
            // ترقيمُ الصفوف يتّصل عبر الصفحات: دفعاتُ العملاء قبل هذه الصفحة
            'rowOffset' => array_sum(array_map(fn ($g) => count($g['payments']), array_slice($groups, 0, $offset))),
            'groupedData' => $report['grouped_data'],
            'totals' => $report['totals'],
            'chartData' => $report['chart_data'],
            'fromDate' => $report['from_date'],
            'toDate' => $report['to_date'],

            /* نفسُ فلاتر بقيّة تقارير العملاء ومن مصادرها نفسِها:
               «تابع لـ» شجرةً واحدة، والعميلُ بالبحث لا بقائمةٍ من ٣٨٥٢
               عنصراً، ومسؤولُ المبيعات من أقسام إعدادات وحدات العملاء. */
            'treeNodes' => $this->affiliationTreeNodes(),
            'salesEmployees' => $this->salesEmployeeOptions(),
            'statuses' => Statuses::assignable()->get(['id', 'name', 'color']),
            'registrationStages' => $this->registrationStageOptions(),
            'customerTargets' => $this->customerTargetOptions(),
            'selectedClient' => $request->filled('client_id')
                ? Client::find($request->input('client_id'))
                : null,

            /* منشئُ القيد (`journal_entries.created_by_employee` = users.id).
               كانت القائمةُ على دورَي employee/manager فتُسقط المندوبين
               (دورُهم «مندوب مبيعات») وهم منشئو أكثر السندات. */
            'employees' => User::where(fn ($q) => $q->whereIn('role', ['employee', 'manager'])->orWhereNotNull('employee_id'))
                ->tap(fn ($q) => DataScope::constrainCreator($q, 'id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'clientCategories' => CategoriesClient::all(),
            /* حساباتُ الخزائن والبنوك — حسابٌ لكلّ سطر. كانت `Treasury::all()`:
               جدولُ الخزائن يكرّر الحسابَ الواحد (٣١ صفّاً لعشرين اسماً، و«خزينة
               الادريسي» خمسَ مرّات)، فظهر الاسمُ مكرَّراً في القائمة. */
            'treasuries' => Account::whereIn('parent_id', array_values(array_filter([
                AccountingSettings::id('cash_treasuries_parent'),
                AccountingSettings::id('bank_accounts_parent'),
            ])) ?: [0])->orderBy('name')->get(['id', 'name', 'parent_id']),
            'selectedTreasuryAccount' => $request->filled('treasury_account')
                ? (int) $request->input('treasury_account')
                : ($request->filled('treasury_id') ? (int) Treasury::whereKey($request->input('treasury_id'))->value('account_id') : null),
            'bankParentId' => AccountingSettings::id('bank_accounts_parent'),
            // الفترة المحاسبية — تملأ حقلَي التاريخ القائمَين ولا تزيد فلتراً
            'periodContext' => $this->reportPeriodContext($request, 'date_from', 'date_to'),
        ]);
    }

    /**
     * بيانات تقرير مدفوعات العملاء.
     *
     * **كانت تُرجع استجابةً فارغةً ثابتة** بلا أيّ استعلام منذ أُنشئت
     * الشاشة — فالجدول والرسمُ البياني فارغان أبداً مهما كانت البيانات.
     * والمصدرُ الذي كان يحتاج قراراً هو `payments_process`: صفوفُ العملاء
     * فيها ٩٢٩١، تحمل `client_id` و`payment_date` و`amount`، وهي نفسُها
     * التي تقرؤها شاشةُ مدفوعات المبيعات.
     *
     * **النوعان معاً**: `client payments` هي الدفعاتُ على الفواتير،
     * و`previous_balance_payment` سدادُ رصيدٍ سابق — وكلاهما نقدٌ ورد من
     * العميل، فإسقاطُ الثاني يُنقص التقريرَ سداداً حقيقياً. وما عداهما
     * (سنداتُ الموردين وإرجاعُ الخزينة) خارجٌ عن «مدفوعات العملاء».
     *
     * **والنطاقُ من الفترة المحاسبية** لا من حقلَين فارغَين يعنيان تاريخَ
     * المنشأة كلَّه — وهو النطاقُ نفسُه الذي يُعلنه شريطُ الحالة فوق
     * التقرير، فلا يقول الشريطُ مدىً ويعرض الجدولُ غيرَه.
     *
     * وشكلُ الاستجابة هو ما يقرؤه القالبُ حرفياً (`updateReportDisplay`،
     * `updateTableBody`، `updateChart`) — لم يُغيَّر منه شيء.
     */
    /** أحجامُ صفحة «مدفوعات العملاء» — بالعميل لا بالدفعة، فلا تنقسم مجموعةٌ بين صفحتين */
    private const PAYMENTS_PAGE_SIZES = [25, 50, 100, 200];

    /**
     * صفوفُ التصدير والطباعة — كلُّ الدفعات، بأعمدة الجدول التسعة نفسها.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array{rows: array<int, array<int, mixed>>, total: float}
     */
    private function paymentsExportRows(array $groups): array
    {
        $rows = [];
        $n = 0;
        $total = 0.0;

        foreach ($groups as $group) {
            $client = trim(($group['client']['trade_name'] ?? '').' - '.($group['client']['code'] ?? ''), ' -');

            foreach ($group['payments'] as $payment) {
                $rows[] = [
                    ++$n,
                    $client,
                    $group['client']['status']['name'] ?? '—',
                    $group['client']['stage']['name'] ?? '—',
                    $group['client']['target']['name'] ?? '—',
                    $payment['payment_date'],
                    (string) $payment['id'],
                    $payment['type_label'] ?? 'غير محدد',
                    $payment['treasury']['name'] ?? 'غير محدد',
                    round((float) $payment['amount'], 2),
                    data_get($payment, 'invoice.employee.branch.name', 'غير محدد'),
                    data_get($payment, 'invoice.employee.name', 'غير محدد'),
                ];
                $total += (float) $payment['amount'];
            }
        }

        return ['rows' => $rows, 'total' => round($total, 2)];
    }

    public function customerPaymentsAjax(Request $request)
    {
        /* المسارُ القديم يبقى عاملاً بنفس شكل استجابته لمن يناديه، والحسابُ
           في `paymentsReportData()` — تعريفٌ واحدٌ للشاشة وله. */
        return response()->json(['success' => true] + $this->paymentsReportData($request));
    }

    /**
     * بيانات تقرير مدفوعات العملاء — مجمَّعةً بالعميل، مع مؤشّراتها ورسمها.
     *
     * @return array{grouped_data: array, totals: array, chart_data: array, from_date: string, to_date: string}
     */
    private function paymentsReportData(Request $request): array
    {
        $period = $this->reportPeriodContext($request, 'date_from', 'date_to');
        $from = Carbon::parse($period['start'])->toDateString();
        $to = Carbon::parse($period['end'])->toDateString();

        /* ══════════ المصدرُ: القيودُ لا `payments_process` ══════════

           كانت الشاشةُ تقرأ `payments_process` وحدَه، وأكثرُ التحصيل لا يمرّ
           به: سنداتُ القبض (`REC` باسم RCV-…) تُرحَّل قيداً ولا تكتب هناك
           صفّاً — ٧٨٢٠ قيداً بـ٨٢١٬٢٨٢٫٨٨ في ٢٠٢٦. فكان التقريرُ يقول
           ٧٦٨٬٩٢٢٫٦٠ حيث دفترُ العملاء ٩٤١٬١٢٦٫١٥. ويُسقط كذلك ٨١٤ دفعةً
           (١٣١٬٩٧١٫٧٧) لا `client_id` على صفّها، ويعدّ الدفعاتِ الفاشلة.

           التحصيلُ هنا تعريفُ دفتر العملاء نفسُه
           (`CustomerLedgerService::TYPES_RECEIPT`، ومثلُه `SalesLedger`):
           دائنُ حساب العميل ناقصَ مدينه في قيود القبض والشيكات وإلغاءِ سند
           القبض — فالإلغاءُ يطرح نفسَه، والعميلُ هو صاحبُ الحساب المقيَّد لا
           عمودٌ قد يكون فارغاً. */
        $clients = Client::query();

        /* فلاترُ العميل كلُّها — تعريفٌ واحدٌ مع بقيّة تقارير العملاء، وفيها
           «تابع لـ» على عقدة العميل وفرعُ الشريط العلويّ ونطاقُ الرؤية. */
        $this->applyClientFilters($clients, $request);

        /* ══════════ الأساسُ: تقريرُ التحصيلات نفسُه (2026-09-28) ══════════

           طلب المستخدم أن تجلب «مدفوعات العملاء» ما يجلبه «تقرير التحصيلات»
           (`CollectionsReportController::getCollectionsDetail()`)، فالطرفُ هو
           **طرفُ العميل** بالقاعدة نفسِها حرفاً:
             • سندُ قبضٍ/تحصيلٍ/شيك (`SalesLedger::COLLECTION_TYPES`): دائنُ العميل — موجب؛
             • ردُّ نقدٍ مع مرتجعٍ أو إشعارٍ دائن (`SR`/`CN`): مدينُ العميل — سالب؛
             • إلغاءُ سند قبض (`PCAN`): مدينُ العميل — سالب، والسندُ الأصليّ يبقى صفّاً.
           فمجموعُ الجدول = «صافي التحصيلات» هناك = «التحصيلات» في لوحة المبيعات.
           كانت الشاشةُ تقرأ قيودَ القبض وحدَها فيغيب عنها ردُّ النقد للعميل. */
        $collectionTypes = SalesLedger::COLLECTION_TYPES;
        $refundTypes = array_merge(SalesLedger::RETURN_TYPES, SalesLedger::CREDIT_NOTE_TYPES);
        $cancellationTypes = SalesLedger::CANCELLATION_TYPES;

        $query = DB::table('journal_entry_details as d')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'd.account_id')
            ->where('e.status', 1)
            ->whereNotNull('a.client_id')
            ->whereBetween('e.date', [$from, $to])
            ->where(function ($q) use ($collectionTypes, $refundTypes, $cancellationTypes) {
                $q->where(fn ($w) => $w->whereIn('e.reference_type', $collectionTypes)->where('d.credit', '>', 0))
                    ->orWhere(fn ($w) => $w->whereIn('e.reference_type', $refundTypes)->where('d.debit', '>', 0))
                    ->orWhere(fn ($w) => $w->whereIn('e.reference_type', $cancellationTypes)->where('d.debit', '>', 0));
            })
            ->whereIn('a.client_id', $clients->select('clients.id'));

        SalesLedger::excludeWalletAccounts($query, 'a');
        ClosedPeriodRanges::exclude($query, 'e.date');

        // ── فلاترُ السند نفسِه ──
        // منشئُ القيد (`created_by_employee` = users.id)، وهو ما تعرضه القائمة
        if ($request->filled('employee_id')) {
            $query->where('e.created_by_employee', $request->input('employee_id'));
        }

        /* الخزينةُ **حسابٌ** تحت الخزائن أو البنوك — والقيدُ يحمله في طرفه
           المقابل. `treasury_id` القديم (`treasuries.id`) يُترجَم إلى حسابه
           لروابطَ محفوظة؛ جدولُ الخزائن يكرّر الحسابَ الواحد صفوفاً عدّة. */
        $treasuryAccountId = $request->filled('treasury_account')
            ? (int) $request->input('treasury_account')
            : ($request->filled('treasury_id') ? (int) Treasury::whereKey($request->input('treasury_id'))->value('account_id') : null);

        if ($treasuryAccountId !== null) {
            $query->whereExists(function ($q) use ($treasuryAccountId) {
                $q->select(DB::raw(1))
                    ->from('journal_entry_details as t')
                    ->whereColumn('t.journal_entry_id', 'e.id')
                    ->where('t.account_id', $treasuryAccountId ?: 0);
            });
        }

        $refundList = "'".implode("','", array_merge($refundTypes, $cancellationTypes))."'";
        $payments = $query
            ->groupBy('e.id', 'e.date', 'e.reference_number', 'e.reference_type', 'e.created_by_employee', 'a.client_id')
            ->orderBy('e.date')
            ->orderBy('e.id')
            ->get([
                'e.id',
                'e.date',
                'e.reference_number',
                'e.reference_type',
                'e.created_by_employee',
                'a.client_id',
                // الردُّ والإلغاءُ سالبان: −(مدين − دائن)، والتحصيلُ دائن − مدين
                DB::raw("CASE WHEN e.reference_type IN ($refundList) THEN -(SUM(d.debit) - SUM(d.credit)) ELSE SUM(d.credit) - SUM(d.debit) END as amount"),
            ]);

        // ── ما يُعرض بجانب كلّ سند: الخزينة، ومنشئُه، والعميلُ وجهتُه ──
        $cashParents = array_values(array_filter([
            AccountingSettings::id('cash_treasuries_parent'),
            AccountingSettings::id('bank_accounts_parent'),
        ]));

        $treasuryByEntry = [];
        foreach ($payments->pluck('id')->unique()->chunk(1000) as $chunk) {
            $treasuryByEntry += DB::table('journal_entry_details as t')
                ->join('accounts as ta', 'ta.id', '=', 't.account_id')
                ->whereIn('t.journal_entry_id', $chunk->values()->all())
                ->whereIn('ta.parent_id', $cashParents ?: [0])
                ->pluck('ta.name', 't.journal_entry_id')
                ->all();
        }

        $creators = User::whereIn('id', $payments->pluck('created_by_employee')->filter()->unique())->pluck('name', 'id');
        $clientModels = Client::whereIn('id', $payments->pluck('client_id')->unique())
            ->get(['id', 'trade_name', 'first_name', 'last_name', 'code', 'tree_node_id', 'branch_id', 'status_id', 'registration_stage_id', 'customer_target_id'])
            ->keyBy('id');

        /* الحالةُ والوضعُ من جدول `statuses` نفسِه (الوضعُ قسمُ `registration_stage`)،
           والهدفُ من `customer_targets` — اسمٌ ولونٌ لكلٍّ، باستعلامين للتقرير كلِّه. */
        $statusRows = DB::table('statuses')
            ->whereIn('id', $clientModels->pluck('status_id')->merge($clientModels->pluck('registration_stage_id'))->filter()->unique())
            ->get(['id', 'name', 'color'])
            ->keyBy('id');
        $targetRows = DB::table('customer_targets')
            ->whereIn('id', $clientModels->pluck('customer_target_id')->filter()->unique())
            ->get(['id', 'name', 'color'])
            ->keyBy('id');
        $badge = fn ($row, string $empty) => ['name' => $row->name ?? $empty, 'color' => $row->color ?? '#6c757d'];
        $nodeNames = Branch::withoutGlobalScopes()
            ->whereIn('id', $clientModels->map(fn ($c) => $c->tree_node_id ?: $c->branch_id)->filter()->unique())
            ->pluck('name', 'id');

        $typeLabels = [
            'REC' => 'تحصيل',
            'RV' => 'سند قبض',
            'PCAN' => 'إلغاء سند قبض',
            'RCHQ' => 'شيك مستلم',
            'RCHQR' => 'شيك مرتدّ',
            'SR' => 'ردّ نقدي مع مرتجع',
            'CN' => 'ردّ نقدي مع إشعار دائن',
        ];

        // التجميعُ بالعميل — القالب يرسم عنواناً لكلّ عميل ثمّ دفعاتِه
        $grouped = [];
        $chart = [];
        $totalTransactions = 0;

        foreach ($payments as $payment) {
            $client = $clientModels->get((int) $payment->client_id);

            if (! $client) {
                continue;
            }

            $key = (int) $client->id;
            $amount = (float) $payment->amount;
            $isReversal = $amount < 0;

            $grouped[$key] ??= [
                'client' => [
                    'id' => $key,
                    'trade_name' => $client->trade_name ?: ($client->display_name ?: '—'),
                    'code' => $client->code ?: '',
                    'status' => $badge($statusRows[$client->status_id] ?? null, 'بدون حالة'),
                    'stage' => $badge($statusRows[$client->registration_stage_id] ?? null, 'بدون وضع'),
                    'target' => $badge($targetRows[$client->customer_target_id] ?? null, 'بلا هدف'),
                ],
                'total_payments' => 0.0,
                'payments_count' => 0,
                'payments' => [],
            ];

            $grouped[$key]['total_payments'] += $amount;

            // الإلغاءُ يُطرح من المبلغ ولا يُعدّ معاملةَ تحصيل
            if (! $isReversal) {
                $grouped[$key]['payments_count']++;
                $totalTransactions++;
            }

            $grouped[$key]['payments'][] = [
                'id' => $payment->reference_number ?: $payment->id,
                'payment_date' => Carbon::parse($payment->date)->toDateString(),
                'payment_method' => null,
                'type_label' => $typeLabels[$payment->reference_type] ?? $payment->reference_type,
                'amount' => $amount,
                'treasury' => isset($treasuryByEntry[$payment->id]) ? ['name' => $treasuryByEntry[$payment->id]] : null,
                // «الفرع» عقدةُ العميل، و«الموظف» منشئُ القيد — المفاتيحُ كما يقرؤها القالب
                'invoice' => [
                    'employee' => [
                        'name' => $creators[$payment->created_by_employee] ?? 'غير محدد',
                        'branch' => ['name' => $nodeNames[$client->tree_node_id ?: $client->branch_id] ?? 'غير محدد'],
                    ],
                ],
            ];

            // الرسمُ البياني بالشهر — مدىً بطول سنةٍ لا يُقرأ يوماً بيوم
            $bucket = Carbon::parse($payment->date)->format('Y-m');
            $chart[$bucket] = ($chart[$bucket] ?? 0) + $amount;
        }

        ksort($chart);

        $totalPayments = array_sum(array_column($grouped, 'total_payments'));

        return [
            'data' => [],
            'grouped_data' => $grouped,
            'totals' => [
                'total_payments' => round($totalPayments, 2),
                'total_clients' => count($grouped),
                'total_transactions' => $totalTransactions,
                'average_payment' => $totalTransactions ? round($totalPayments / $totalTransactions, 2) : 0,
            ],
            'chart_data' => [
                'labels' => array_keys($chart),
                'payments' => array_map(fn ($v) => round($v, 2), array_values($chart)),
            ],
            'from_date' => (string) $period['start'],
            'to_date' => (string) $period['end'],
        ];
    }

    public function getCustomerStatement($id)
    {
        // هذه الدالة قد تحتاج إلى تنفيذ إذا كانت مطلوبة
        return response()->json(['success' => false]);
    }
}
