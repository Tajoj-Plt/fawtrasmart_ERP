<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CollectionsTarget;
use App\Models\CollectionsTargetSettlement;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\SalesTarget;
use App\Models\SalesTargetSettlement;
use App\Models\TargetSetting;
use App\Models\User;
use App\Support\AffiliationTree;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * «تقرير العمولات» — عمولاتُ أهداف المبيعات والتحصيلات في شاشةٍ واحدة.
 *
 * ══════════ المصروف ══════════
 *
 * من جدولَي التسوية (`sales_target_settlements` و`collections_target_settlements`)
 * — ما كتبته `TargetSettlementService` حين صرفت: تسويةٌ لها قيدُ صرف، أو
 * `is_settled`، أو `paid_directly`. نفسُ تعريف «العمولات المصروفة» في
 * الموارد البشرية (`Modules\HR\…\CommissionsReportController::paidQuery`)،
 * فلا يختلف الرقمان.
 *
 * **الإنجازُ وقتَ الصرف** لا يُخزَّن في جدول التسوية، لكنّ الخدمة تكتبه في
 * وصف قيد الصرف («الإنجاز: 1,414.10 SAR من 2,000.00 SAR (70.7%)»). يُقرأ من
 * هناك ({@see parseAchievement()}) — إعادةُ حسابه اليوم تعطي رقماً آخر متى
 * جاء مرتجعٌ أو تحصيلٌ متأخّر بعد الصرف، فلا تفسّر العمولةُ المصروفة نفسَها.
 *
 * ══════════ المحتسَب عن الفترة ══════════
 *
 * ما كسبه كلُّ موظفٍ عن المدى المختار بقاعدة التسوية نفسِها — دورةً دورة —
 * وما صُرف له منه والباقي ({@see earnedRows()}). كان التقرير يعرض المصروفَ
 * وحده ومستحقَّ «اليوم»، فلا يرى من اختار شهراً ما كسبه المندوبون فيه.
 *
 * ══════════ النطاق ══════════
 *
 * على **الموظف** صاحب العمولة: فرعُه في «تابع لـ» (ملفّه أو حسابه أو فروعه
 * المسندة، {@see employeeIdsInNodes()})، مقصوصاً على نطاق الرؤية في
 * `getEffectiveBranchId()`، ومحورُ الأشخاص على `DataScope::employeeIds()`.
 */
class CommissionsReportController extends Controller
{
    use SalesReportTrait;

    public const METHOD_JOURNAL = 'journal';

    public const METHOD_DIRECT = 'direct';

    public const METHOD_SALARY = 'salary';

    public function __construct()
    {
        $this->middleware('permission:sales.reports.commissions');
    }

    public function index(Request $request)
    {
        $ctx = $this->reportContext($request);
        $rows = $this->paidRows($request, $ctx);
        $earned = $request->input('with_earned', '1') === '1' ? $this->earnedRows($request, $ctx) : collect();

        $groupOptions = $this->groupOptions();
        $groupBy = array_key_exists($request->input('group_by'), $groupOptions) ? $request->input('group_by') : 'employee';
        $isSummary = $request->input('view_type', 'details') === 'summary';

        $totals = $this->totalsOf($rows, $earned);
        $groups = $isSummary ? $this->groupRows($rows, $groupBy, $totals['paid']) : collect();

        if ($request->ajax() || $request->boolean('_ajax')) {
            return response()->json(['success' => true, 'rows' => $rows, 'groups' => $groups, 'earned' => $earned, 'totals' => $totals]);
        }

        return view('sales::sals_report.salesRport.Commissions_Report', $ctx + [
            'rows' => $rows,
            'earned' => $earned,
            'groups' => $groups,
            'groupBy' => $groupBy,
            'groupOptions' => $groupOptions,
            'isSummary' => $isSummary,
            'totals' => $totals,
            'byEmployeeChart' => $this->byEmployeeChart($rows),
            'pageTitle' => __('sales_reports.commissions.title'),
            'formAction' => route('salesReports.commissions'),
        ]);
    }

    // =====================================================================
    //  السياق
    // =====================================================================

    protected function reportContext(Request $request): array
    {
        [$fromDate, $toDate] = $this->parseDateRangeRequest($request);

        $user = User::find(auth()->id());
        $userBranchId = $user->branch_id;
        $isMainBranch = (bool) optional(Branch::find($userBranchId))->is_main;

        $effectiveBranchId = $this->getEffectiveBranchId($request, $isMainBranch, $userBranchId);
        $branchIds = $effectiveBranchId
            ? ($request->boolean('exact_node') ? [$effectiveBranchId] : (AffiliationTree::descendantIds($effectiveBranchId) ?: [$effectiveBranchId]))
            : null;

        $employees = $this->scopeEmployees(Employee::query(), $branchIds, 'id', 'branch_id')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'nickname']);

        $targets = collect()
            ->concat(SalesTarget::orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['key' => 's:'.$t->id, 'name' => $t->name, 'kind' => TargetSetting::TYPE_SALES]))
            ->concat(CollectionsTarget::orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['key' => 'c:'.$t->id, 'name' => $t->name, 'kind' => TargetSetting::TYPE_COLLECTIONS]));

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'effectiveBranchId' => $effectiveBranchId,
            'branchIds' => $branchIds,
            'treeNodes' => $this->affiliationTreeNodes(),
            'cascadeBranches' => [],
            'employeesList' => $employees,
            'targetsList' => $targets,
            'kind' => in_array($request->input('kind'), [TargetSetting::TYPE_SALES, TargetSetting::TYPE_COLLECTIONS], true) ? $request->input('kind') : '',
            'dateBasis' => $request->input('date_basis') === 'period' ? 'period' : 'paid',
        ];
    }

    /**
     * قصرُ استعلامٍ على موظّفي النطاق والعقدة.
     *
     * محورُ الأشخاص وحدَه أو المكانُ وحدَه — لا الاثنان معاً (انظر
     * `DataScope`): المكانُ مقصوصٌ أصلاً في `getEffectiveBranchId()`.
     */
    protected function scopeEmployees(Builder $query, ?array $branchIds, string $idColumn, ?string $branchColumn = null): Builder
    {
        if (DataScope::isPeopleAxis()) {
            $query->whereIn($idColumn, DataScope::employeeIds() ?: [0]);
        }

        if ($branchIds) {
            $query->whereIn($idColumn, $this->employeeIdsInNodes($branchIds));
        }

        return $query;
    }

    /**
     * موظّفو العقدة — بفرع ملفّه، أو فرعِ حساب دخوله، أو أيِّ فرعٍ أُسند إليه.
     *
     * `employees.branch_id` وحده كان يُسقط من لا فرعَ في ملفّه (صاحبُ عمولة
     * التحصيلات ٢٣٠٠ ملفُّه بلا فرع وحسابُه على الفرع الرئيسي)، ومن أُسند إلى
     * فروعٍ عدّة في `employee_branch` لا يُرى إلّا في أوّلها.
     *
     * @param  array<int, int>  $branchIds
     * @return array<int, int>
     */
    protected function employeeIdsInNodes(array $branchIds): array
    {
        return Employee::whereIn('branch_id', $branchIds)->pluck('id')
            ->merge(User::whereIn('branch_id', $branchIds)->whereNotNull('employee_id')->pluck('employee_id'))
            ->merge(\Illuminate\Support\Facades\DB::table('employee_branch')->whereIn('branch_id', $branchIds)->pluck('employee_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all() ?: [0];
    }

    protected function groupOptions(): array
    {
        return [
            'employee' => __('sales_reports.commissions.group.employee'),
            'target' => __('sales_reports.commissions.group.target'),
            'kind' => __('sales_reports.commissions.group.kind'),
            'method' => __('sales_reports.commissions.group.method'),
            'month' => __('sales_reports.commissions.group.month'),
        ];
    }

    // =====================================================================
    //  المصروف
    // =====================================================================

    protected function paidRows(Request $request, array $ctx): Collection
    {
        $rows = collect();

        foreach ($this->kindsFor($ctx['kind']) as $kind) {
            $isCollections = $kind === TargetSetting::TYPE_COLLECTIONS;
            $model = $isCollections ? CollectionsTargetSettlement::class : SalesTargetSettlement::class;
            $targetKey = $isCollections ? 'collections_target_id' : 'sales_target_id';

            $query = $model::query()
                ->with(['target:id,name,commission_period,target_value', 'settler:id,name', 'employee:id,first_name,middle_name,nickname,branch_id', 'employee.branch:id,name'])
                ->where('commission_amount', '>', 0)
                ->where(fn ($q) => $q->where('is_settled', true)->orWhere('paid_directly', true)->orWhereNotNull('direct_journal_entry_id'));

            $this->scopeEmployees($query, $ctx['branchIds'], 'employee_id');

            $from = $ctx['fromDate']->format('Y-m-d');
            $to = $ctx['toDate']->format('Y-m-d');
            if ($ctx['dateBasis'] === 'period') {
                // فترةُ العمولة تتقاطع مع المدى
                $query->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from);
            } else {
                // تاريخُ الصرف: `direct_paid_at` في كلّ صرف، و`settled_date` في القديم
                $query->whereRaw('DATE(COALESCE(direct_paid_at, settled_date, created_at)) BETWEEN ? AND ?', [$from, $to]);
            }

            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->input('employee_id'));
            }
            if ($request->filled('target')) {
                [$prefix, $id] = array_pad(explode(':', (string) $request->input('target'), 2), 2, null);
                if ($prefix !== ($isCollections ? 'c' : 's')) {
                    continue;
                }
                $query->where($targetKey, (int) $id);
            }
            if (in_array($request->input('commission_period'), ['weekly', 'monthly'], true)) {
                $query->whereHas('target', fn ($q) => $q->where('commission_period', $request->input('commission_period')));
            }
            if ($request->input('state') === 'posted') {
                $query->where(fn ($q) => $q->where('is_settled', true)->orWhere('paid_directly', true));
            } elseif ($request->input('state') === 'pending_approval') {
                $query->where('is_settled', false)->where('paid_directly', false)->whereNotNull('direct_journal_entry_id');
            }
            match ($request->input('method')) {
                self::METHOD_DIRECT => $query->where('paid_directly', true),
                self::METHOD_JOURNAL => $query->where('paid_directly', false)->whereNotNull('direct_journal_entry_id'),
                self::METHOD_SALARY => $query->where('paid_directly', false)->whereNull('direct_journal_entry_id'),
                default => null,
            };

            foreach ($query->get() as $s) {
                $rows->push(['_model' => $s, 'kind' => $kind]);
            }
        }

        // قيودُ الصرف دفعةً واحدة: حالتُها، وسندُها، والخزينةُ المصروفُ منها، والإنجازُ في وصفها
        $journalIds = $rows->pluck('_model.direct_journal_entry_id')->filter()->unique()->values()->all();
        $journals = $journalIds
            ? JournalEntry::with(['details:id,journal_entry_id,account_id,debit,credit', 'details.account:id,name,code'])
                ->whereIn('id', $journalIds)
                ->get(['id', 'status', 'reference_number', 'reference_id', 'reference_type', 'description', 'date'])
                ->keyBy('id')
            : collect();

        return $rows->map(function (array $wrap) use ($journals) {
            /** @var SalesTargetSettlement|CollectionsTargetSettlement $s */
            $s = $wrap['_model'];
            $kind = $wrap['kind'];
            $journal = $s->direct_journal_entry_id ? $journals->get($s->direct_journal_entry_id) : null;
            $achievement = $this->parseAchievement($journal->description ?? null);
            $paidAt = $s->direct_paid_at ?? $s->settled_date ?? $s->created_at;
            $posted = $s->is_settled || $s->paid_directly;
            $method = $s->paid_directly ? self::METHOD_DIRECT : ($s->direct_journal_entry_id ? self::METHOD_JOURNAL : self::METHOD_SALARY);
            $treasury = $journal ? $journal->details->first(fn ($d) => (float) $d->credit > 0)?->account : null;
            $amount = (float) $s->commission_amount;
            $employee = $s->employee;
            $auto = str_contains((string) ($journal->description ?? ''), 'تسوية تلقائية') || str_contains((string) $s->notes, 'تلقائية');

            return [
                'id' => (int) $s->id,
                'row_key' => $kind.'-'.$s->id,
                'kind' => $kind,
                'kind_label' => __('sales_reports.commissions.kind.'.$kind),
                'employee_id' => $s->employee_id ? (int) $s->employee_id : null,
                'employee_name' => $employee ? trim($employee->full_name) : '—',
                'branch_name' => $employee->branch->name ?? '—',
                'target_id' => (int) ($kind === TargetSetting::TYPE_COLLECTIONS ? $s->collections_target_id : $s->sales_target_id),
                'target_name' => $s->target->name ?? '—',
                'commission_period' => $s->target->commission_period ?? null,
                'from' => optional($s->from_date)->format('Y-m-d'),
                'to' => optional($s->to_date)->format('Y-m-d'),
                'paid_at' => $paidAt ? Carbon::parse($paidAt)->format('Y-m-d') : null,
                'month' => $paidAt ? Carbon::parse($paidAt)->format('Y-m') : '—',
                'achieved' => $achievement['achieved'],
                'target_amount' => $achievement['target'] ?? (float) ($s->target->target_value ?? 0) ?: null,
                'percentage' => $achievement['percentage'],
                'rate' => $achievement['achieved'] ? round($amount / $achievement['achieved'] * 100, 2) : null,
                'commission' => $amount,
                'method' => $method,
                'method_label' => __('sales_reports.commissions.method.'.$method),
                'journal_id' => $s->direct_journal_entry_id ? (int) $s->direct_journal_entry_id : null,
                'voucher_code' => $journal && $journal->getRawOriginal('reference_type') === 'EXP' ? $journal->reference_number : null,
                'expense_id' => $journal && $journal->getRawOriginal('reference_type') === 'EXP' ? $journal->reference_id : null,
                'treasury_name' => $treasury->name ?? null,
                'state' => $posted ? 'posted' : 'pending_approval',
                'state_label' => __('sales_reports.commissions.state.'.($posted ? 'posted' : 'pending_approval')),
                'mode_label' => __('sales_reports.commissions.mode.'.($auto ? 'auto' : 'manual')),
                // التسويةُ المجدولة تكتب في `settled_by` أوّلَ مديرٍ عامّ (العمود NOT NULL) لا من صرف — فالتلقائيّةُ «النظام»
                'settled_by' => $auto ? __('sales_reports.commissions.system') : ($s->settler->name ?? '—'),
                'notes' => $s->notes,
            ];
        })
            ->sortByDesc(fn ($r) => ($r['paid_at'] ?? '').'#'.str_pad((string) $r['id'], 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * الإنجازُ كما كتبته الخدمة في وصف قيد الصرف.
     *
     * الصيغة: «الإنجاز: 1,414.10 SAR من 2,000.00 SAR (70.7%)» — ورمزُ العملة
     * قد يسبق الرقم أو يليه بحسب إعدادات العملة، فتُلتقط الأرقامُ وحدها.
     *
     * @return array{achieved: ?float, target: ?float, percentage: ?float}
     */
    protected function parseAchievement(?string $description): array
    {
        $none = ['achieved' => null, 'target' => null, 'percentage' => null];

        if (! $description || ! preg_match('/الإنجاز:\s*([^|]+)/u', $description, $m)) {
            return $none;
        }

        if (! preg_match_all('/-?\d[\d,]*(?:\.\d+)?/', $m[1], $nums) || count($nums[0]) < 2) {
            return $none;
        }

        $n = array_map(fn ($v) => (float) str_replace(',', '', $v), $nums[0]);

        return [
            'achieved' => $n[0],
            'target' => $n[1],
            'percentage' => $n[2] ?? ($n[1] > 0 ? round($n[0] / $n[1] * 100, 1) : null),
        ];
    }


    // =====================================================================
    //  المحتسَب عن الفترة
    // =====================================================================

    /**
     * ما استحقّه كلُّ موظفٍ عن المدى المختار، وما صُرف له منه، والباقي.
     *
     * كان التقرير يعرض المصروفَ وحده، و«المستحقّ» محسوباً لليوم لا للمدى —
     * فمن اختار «سبتمبر» لا يرى ما كسبه المندوبون في سبتمبر إلّا إن صُرف.
     *
     * يُقسَّم المدى على **دورات التسوية** كما تحسبها التسويةُ نفسُها: شهرياً أو
     * أسبوعياً (من يوم التسوية) أو ربعياً أو سنوياً بحسب «إعدادات الأهداف»، أو
     * مدّةُ الهدف كلُّها إن كان يُصرف عند نهايتها. ولكلّ دورةٍ إنجازُها ×
     * نسبةُ شريحتها من هدف الموظف — ثمّ تُجمع. الدورةُ التي يقطعها المدى
     * تُحسب على الجزء الواقع فيه، والدورةُ التي لم تنتهِ بعد «جارية».
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function earnedRows(Request $request, array $ctx): Collection
    {
        $from = $ctx['fromDate']->format('Y-m-d');
        $to = $ctx['toDate']->format('Y-m-d');
        $today = Carbon::today()->toDateString();

        $allowed = null;
        if (DataScope::isPeopleAxis()) {
            $allowed = DataScope::employeeIds() ?: [0];
        }
        $inNode = $ctx['branchIds'] ? $this->employeeIdsInNodes($ctx['branchIds']) : null;
        $calc = app(\App\Services\TargetCalculationService::class);
        // إنجازُ الموظف في دورةٍ واحد مهما تعدّدت أهدافُه — يُحسب مرّةً واحدة
        $memo = [];

        $rows = collect();

        foreach ($this->kindsFor($ctx['kind']) as $kind) {
            $isCollections = $kind === TargetSetting::TYPE_COLLECTIONS;
            $setting = TargetSetting::for($kind);
            $method = $setting->calculation_method ?: ($isCollections ? 'total_collected' : 'net_sales');
            $class = $isCollections ? CollectionsTarget::class : SalesTarget::class;
            $settlementClass = $isCollections ? CollectionsTargetSettlement::class : SalesTargetSettlement::class;
            $targetKey = $isCollections ? 'collections_target_id' : 'sales_target_id';

            $targets = $class::with(['achievements.employee', 'commissionTiers'])
                ->where('status', 'active')
                ->when($request->filled('target'), function ($q) use ($request, $isCollections) {
                    [$prefix, $id] = array_pad(explode(':', (string) $request->input('target'), 2), 2, null);
                    $prefix === ($isCollections ? 'c' : 's') ? $q->whereKey((int) $id) : $q->whereRaw('1 = 0');
                })
                ->when(in_array($request->input('commission_period'), ['weekly', 'monthly'], true),
                    fn ($q) => $q->where('commission_period', $request->input('commission_period')))
                ->get();

            foreach ($targets as $target) {
                $cycles = $this->cyclesFor($target, $setting, $from, $to, $today);

                if ($cycles === []) {
                    continue;
                }

                foreach ($target->achievements as $achievement) {
                    $user = $achievement->employee;
                    $employeeId = $user && $user->employee_id ? (int) $user->employee_id : null;

                    if (! $user
                        || ($allowed !== null && (! $employeeId || ! in_array($employeeId, $allowed)))
                        || ($inNode !== null && (! $employeeId || ! in_array($employeeId, $inNode, true)))
                        || ($request->filled('employee_id') && $employeeId !== (int) $request->input('employee_id'))) {
                        continue;
                    }

                    $targetAmount = (float) $achievement->target_amount;
                    $achieved = 0.0;
                    $earned = 0.0;
                    $running = 0.0;

                    foreach ($cycles as [$cFrom, $cTo, $ended]) {
                        $key = $kind.'|'.$user->id.'|'.$cFrom.'|'.$cTo.'|'.$method;
                        $cAchieved = $memo[$key] ??= (float) ($isCollections
                            ? $calc->getEmployeeCollections($user->id, $cFrom, $cTo, $method)
                            : $calc->getEmployeeNetSales($user->id, $cFrom, $cTo, $method));
                        $cCommission = round((float) $target->calculateCommission($cAchieved, $targetAmount), 2);

                        $achieved += $cAchieved;
                        $earned += $cCommission;
                        if (! $ended) {
                            $running += $cCommission;
                        }
                    }

                    // ما صُرف له من هذا الهدف عن فتراتٍ تتقاطع مع المدى
                    $paid = $employeeId
                        ? (float) $settlementClass::where($targetKey, $target->id)
                            ->where('employee_id', $employeeId)
                            ->where('commission_amount', '>', 0)
                            ->where(fn ($q) => $q->where('is_settled', true)->orWhere('paid_directly', true)->orWhereNotNull('direct_journal_entry_id'))
                            ->whereDate('from_date', '<=', $to)
                            ->whereDate('to_date', '>=', $from)
                            ->sum('commission_amount')
                        : 0.0;

                    if ($earned <= 0.005 && $paid <= 0.005) {
                        continue;
                    }

                    $remaining = max(0.0, round($earned - $paid, 2));
                    $targetTotal = $targetAmount * count($cycles);

                    $rows->push([
                        'kind' => $kind,
                        'kind_label' => __('sales_reports.commissions.kind.'.$kind),
                        'target_id' => (int) $target->id,
                        'target_name' => (string) $target->name,
                        'employee_id' => $employeeId,
                        'employee_name' => (string) $user->name,
                        'from' => $cycles[0][0],
                        'to' => end($cycles)[1],
                        'cycles' => count($cycles),
                        'achieved' => $achieved,
                        'target_amount' => $targetTotal,
                        'percentage' => $targetTotal > 0 ? round($achieved / $targetTotal * 100, 1) : null,
                        'commission' => round($earned, 2),
                        'paid' => $paid,
                        'remaining' => $remaining,
                        // الباقي في دوراتٍ لم تنتهِ «جارٍ» — لم يحن صرفُه بعد
                        'remaining_running' => min($remaining, round($running, 2)),
                        'state' => match (true) {
                            $remaining <= 0.005 => 'settled',
                            $paid > 0.005 => 'partial',
                            $running >= $earned - 0.005 => 'running',
                            default => 'unpaid',
                        },
                    ]);
                }
            }
        }

        return $rows->map(fn ($r) => $r + [
            'state_label' => __('sales_reports.commissions.earned_state.'.$r['state']),
            'state_tone' => ['settled' => 'success', 'partial' => 'info', 'running' => 'secondary', 'unpaid' => 'warning'][$r['state']],
        ])
            ->sortByDesc('remaining')
            ->values();
    }

    /**
     * دوراتُ التسوية الواقعة في المدى — `[من، إلى، انتهت؟]`.
     *
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    protected function cyclesFor($target, TargetSetting $setting, string $from, string $to, string $today): array
    {
        $clamped = $target->clampToPeriod($from, $to);

        if ($clamped === null) {
            return [];
        }

        [$from, $to] = $clamped;

        /* الهدفُ بلا مدّة لا يُحتسب قبل إنشائه: هدفٌ أُنشئ في ٢٢ سبتمبر كان
           يُخرج عمولاتٍ عن يناير إلى أغسطس. دورةُ الإنشاء نفسُها تُحتسب كاملة
           — كما تُسوّيها التسويةُ اليدوية من أوّل الشهر. */
        $createdOn = $target->created_at ? $target->created_at->toDateString() : null;

        // هدفٌ يُصرف عند نهاية مدّته: المدّةُ دورةٌ واحدة
        if ($target->settlesAtPeriodEnd()) {
            return [[$from, $to, $target->end_date->toDateString() < $today]];
        }

        $unit = $setting->settlement_frequency ?: ($target->commission_period ?: 'monthly');
        // الأسبوعيّ يبدأ من يوم التسوية (1 = الأحد … 7 = السبت) كما في `TargetSettlementSchedule`
        $weekStart = (max(1, (int) ($setting->settlement_day ?: 1)) - 1) % 7;

        $cycles = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        for ($guard = 0; $cursor->lte($end) && $guard < 400; $guard++) {
            [$cStart, $cEnd] = match ($unit) {
                'weekly' => [$cursor->copy()->startOfWeek($weekStart), $cursor->copy()->startOfWeek($weekStart)->addDays(6)],
                'quarterly' => [$cursor->copy()->startOfQuarter(), $cursor->copy()->endOfQuarter()],
                'yearly' => [$cursor->copy()->startOfYear(), $cursor->copy()->endOfYear()],
                default => [$cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth()],
            };

            if ($createdOn === null || $target->hasPeriod() || $cEnd->toDateString() >= $createdOn) {
                $cycles[] = [
                    max($cStart->toDateString(), $from),
                    min($cEnd->toDateString(), $to),
                    $cEnd->toDateString() < $today,
                ];
            }

            $cursor = $cEnd->copy()->addDay()->startOfDay();
        }

        return $cycles;
    }

    // =====================================================================
    //  المجاميع والملخّص
    // =====================================================================

    /** @return list<string> */
    protected function kindsFor(string $kind): array
    {
        return $kind !== '' ? [$kind] : [TargetSetting::TYPE_SALES, TargetSetting::TYPE_COLLECTIONS];
    }

    protected function totalsOf(Collection $rows, Collection $earned): array
    {

        return [
            'count' => $rows->count(),
            'paid' => (float) $rows->sum('commission'),
            'posted' => (float) $rows->where('state', 'posted')->sum('commission'),
            'pending_approval' => (float) $rows->where('state', 'pending_approval')->sum('commission'),
            'pending_approval_count' => $rows->where('state', 'pending_approval')->count(),
            'sales' => (float) $rows->where('kind', TargetSetting::TYPE_SALES)->sum('commission'),
            'sales_count' => $rows->where('kind', TargetSetting::TYPE_SALES)->count(),
            'collections' => (float) $rows->where('kind', TargetSetting::TYPE_COLLECTIONS)->sum('commission'),
            'collections_count' => $rows->where('kind', TargetSetting::TYPE_COLLECTIONS)->count(),
            'employees' => $rows->pluck('employee_id')->filter()->unique()->count(),
            'achieved' => (float) $rows->sum('achieved'),
            // المحتسَب عن المدى: ما كُسب، وما صُرف منه، والباقي — مستحقّاً (دورتُه انتهت) أو جارياً
            'earned' => (float) $earned->sum('commission'),
            'earned_paid' => (float) $earned->sum('paid'),
            'due' => (float) $earned->sum(fn ($r) => $r['remaining'] - $r['remaining_running']),
            'due_count' => $earned->filter(fn ($r) => $r['remaining'] - $r['remaining_running'] > 0.005)->count(),
            'running' => (float) $earned->sum('remaining_running'),
            'running_count' => $earned->filter(fn ($r) => $r['remaining_running'] > 0.005)->count(),
        ];
    }

    protected function groupRows(Collection $rows, string $groupBy, float $grand): Collection
    {
        $keyOf = match ($groupBy) {
            'employee' => fn ($r) => (string) ($r['employee_id'] ?? 'none'),
            'target' => fn ($r) => $r['kind'].':'.$r['target_id'],
            'kind' => fn ($r) => $r['kind'],
            'method' => fn ($r) => $r['method'],
            'month' => fn ($r) => $r['month'],
        };

        return $rows->groupBy($keyOf)
            ->map(function (Collection $g) use ($groupBy, $grand) {
                $first = $g->first();
                $amount = (float) $g->sum('commission');
                $achieved = (float) $g->sum('achieved');

                return [
                    'label' => match ($groupBy) {
                        'employee' => $first['employee_name'],
                        'target' => $first['target_name'],
                        'kind' => $first['kind_label'],
                        'method' => $first['method_label'],
                        'month' => $first['month'],
                    },
                    'sub' => match ($groupBy) {
                        'employee' => $first['branch_name'],
                        'target' => $first['kind_label'],
                        default => null,
                    },
                    'count' => $g->count(),
                    'employees' => $g->pluck('employee_id')->filter()->unique()->count(),
                    'targets' => $g->map(fn ($r) => $r['kind'].':'.$r['target_id'])->unique()->count(),
                    'achieved' => $achieved,
                    'commission' => $amount,
                    'sales' => (float) $g->where('kind', TargetSetting::TYPE_SALES)->sum('commission'),
                    'collections' => (float) $g->where('kind', TargetSetting::TYPE_COLLECTIONS)->sum('commission'),
                    'pending_approval' => (float) $g->where('state', 'pending_approval')->sum('commission'),
                    'rate' => $achieved > 0.005 ? round($amount / $achieved * 100, 2) : null,
                    'share' => $grand > 0.005 ? round($amount / $grand * 100, 1) : 0.0,
                    'last_paid' => $g->max('paid_at'),
                ];
            })
            ->sortBy(fn ($g) => $groupBy === 'month' ? $g['label'] : -$g['commission'])
            ->values();
    }

    /** أعلى الموظفين عمولةً — للرسم الشريطيّ */
    protected function byEmployeeChart(Collection $rows): array
    {
        $top = $rows->groupBy(fn ($r) => (string) ($r['employee_id'] ?? 'none'))
            ->map(fn (Collection $g) => [
                'label' => $g->first()['employee_name'],
                'sales' => round((float) $g->where('kind', TargetSetting::TYPE_SALES)->sum('commission'), 2),
                'collections' => round((float) $g->where('kind', TargetSetting::TYPE_COLLECTIONS)->sum('commission'), 2),
            ])
            ->sortByDesc(fn ($e) => $e['sales'] + $e['collections'])
            ->take(12)
            ->values();

        return [
            'labels' => $top->pluck('label')->all(),
            'sales' => $top->pluck('sales')->all(),
            'collections' => $top->pluck('collections')->all(),
        ];
    }
}
