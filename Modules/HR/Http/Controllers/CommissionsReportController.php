<?php

namespace Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CollectionsTarget;
use App\Models\CollectionsTargetAchievement;
use App\Models\CollectionsTargetSettlement;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\SalesTarget;
use App\Models\SalesTargetAchievement;
use App\Models\SalesTargetSettlement;
use App\Models\TargetSetting;
use App\Models\User;
use App\Services\Targets\PendingCommissions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HR\Exports\HrReportTableExport;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * «العمولات المصروفة» — نافذةُ الموارد البشرية على عمولات أهداف المبيعات.
 *
 * **لا نظامَ أهدافٍ هنا.** الأهدافُ وشرائحُها وحسابُ الإنجاز وتسويتُه كلُّها
 * في وحدة المبيعات (`SalesTargetController::settle` ← `TargetSettlementService`)،
 * وهذه الشاشة تقرأ ما كتبته التسويةُ في `sales_target_settlements` ولا تحسب
 * عمولةً ولا تصرفها — فلا ازدواجَ في الحساب ولا في الصرف.
 *
 * **ما يُعدّ مصروفاً**: تسويةٌ بقيد صرف (`direct_journal_entry_id`)، أو
 * `is_settled`، أو `paid_directly` (الصرف الأسبوعيّ من خزينة الموظف). ومع
 * «يتطلب اعتماداً» في إعدادات الأهداف يُكتب القيدُ بانتظار الاعتماد
 * و`is_settled=0` — فكانت تلك العمولاتُ تغيب عن التقرير مع أن سندَها صدر؛
 * وتظهر الآن بحالة «بانتظار اعتماد القيد».
 *
 * **النطاق**: القائمةُ والإحصاءاتُ وقائمةُ الموظفين والتصديرُ والطباعةُ كلُّها
 * في نطاق المستخدم (كانت الإحصاءاتُ تجمع عمولاتِ المنشأة كلِّها لمن يرى فرعه
 * وحده)، والتصديرُ يعيد استعمالَ استعلام الشاشة نفسِه.
 *
 * **الصلاحيات** (مجموعة `commissions.*`): القائمة `commissions.index`،
 * والعرض `commissions.show`، والتصدير `commissions.export`، والطباعة
 * `commissions.print` (`HrVoucherPrintController::commission`). والاحتسابُ
 * والصرفُ والاعتمادُ في وحدة المبيعات (`SalesTargetController::settle`).
 */
class CommissionsReportController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        $this->middleware('permission.role:commissions.index', ['only' => ['index']]);
        $this->middleware('permission.role:commissions.show', ['only' => ['show']]);
        $this->middleware('permission.role:commissions.export', ['only' => ['export']]);
    }

    /**
     * نوعُ العمولات المعروضة: أهدافُ المبيعات أو أهدافُ التحصيلات.
     *
     * **كان السجلُّ للمبيعات وحدها.** عمولاتُ أهداف التحصيلات تُصرف بالخدمة
     * نفسِها — سندٌ وقيدٌ وتسوية — وتُكتب في `collections_target_settlements`،
     * ولا شاشةَ تعرضها: تنبيهُ «صُرفت ٦ عمولات لأهداف التحصيلات» يصل، ومن
     * يفتح «العمولات المصروفة» لا يجد منها واحدة.
     */
    private function kind(?Request $request = null): string
    {
        return ($request ?: request())->input('kind') === TargetSetting::TYPE_COLLECTIONS
            ? TargetSetting::TYPE_COLLECTIONS
            : TargetSetting::TYPE_SALES;
    }

    private function isCollections(?Request $request = null): bool
    {
        return $this->kind($request) === TargetSetting::TYPE_COLLECTIONS;
    }

    /** @return class-string<Model> */
    private function settlementModel(?Request $request = null): string
    {
        return $this->isCollections($request) ? CollectionsTargetSettlement::class : SalesTargetSettlement::class;
    }

    /** @return class-string<Model> */
    private function targetModel(?Request $request = null): string
    {
        return $this->isCollections($request) ? CollectionsTarget::class : SalesTarget::class;
    }

    private function targetKey(?Request $request = null): string
    {
        return $this->isCollections($request) ? 'collections_target_id' : 'sales_target_id';
    }

    /** التسوياتُ التي خرج بها مال — بقيدٍ أو بصرفٍ مباشر. */
    private function paidQuery(): Builder
    {
        return $this->settlementModel()::query()
            ->where('commission_amount', '>', 0)
            ->where(function ($q) {
                $q->where('is_settled', true)
                    ->orWhere('paid_directly', true)
                    ->orWhereNotNull('direct_journal_entry_id');
            });
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if (in_array($request->commission_type, ['weekly', 'monthly'], true)) {
            $query->whereHas('target', fn ($q) => $q->where('commission_period', $request->commission_type));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('target_id')) {
            $query->where($this->targetKey($request), $request->target_id);
        }

        if ($request->state === 'posted') {
            $query->where(fn ($q) => $q->where('is_settled', true)->orWhere('paid_directly', true));
        } elseif ($request->state === 'pending_approval') {
            $query->where('is_settled', false)->where('paid_directly', false)->whereNotNull('direct_journal_entry_id');
        }

        // تاريخُ الصرف: `direct_paid_at` يُكتب في كلّ صرف، و`settled_date` في القديم
        if ($request->filled('from_date')) {
            $query->whereRaw('DATE(COALESCE(direct_paid_at, settled_date, created_at)) >= ?', [$request->from_date]);
        }
        if ($request->filled('to_date')) {
            $query->whereRaw('DATE(COALESCE(direct_paid_at, settled_date, created_at)) <= ?', [$request->to_date]);
        }

        return $this->scopeToEmployees($query, 'employee_id');
    }

    public function index(Request $request)
    {
        $query = $this->applyFilters(
            $this->paidQuery()->with([
                'target:id,name,commission_period,target_value',
                'settler:id,name',
                'employee:id,first_name,middle_name,nickname',
            ]),
            $request
        );

        // `?export=rows` — كلُّ صفوف التبويب الحاليّ لزرَّي التصدير والطباعة (`fetch-rows-tools`)
        if ($request->input('export') === 'rows') {
            abort_unless(hasPermission('commissions.export'), 403, 'ليس لديك صلاحية تصدير العمولات.');

            return response()->json(['rows' => $request->input('tab') === 'pending'
                ? $this->pendingRows($request)->values()->map(fn ($row, $i) => [
                    $i + 1, $row['employee_name'], $row['target_name'], $row['from'].' ← '.$row['to'],
                    number_format((float) $row['achieved'], 2), $row['percentage'].'%', number_format((float) $row['commission'], 2),
                    $row['treasury_name'] ?? '—', self::PENDING_LABELS[$row['state']] ?? $row['state'],
                ])->all()
                : $this->paidLines((clone $query)->orderByDesc('id')->get()),
            ]);
        }

        // 25/50/100/200 من قائمة «عدد الصفوف» — و20 تبقى للروابط القديمة
        $perPage = in_array((int) $request->per_page, [20, 25, 50, 100, 200], true) ? (int) $request->per_page : 50;

        $totals = (clone $query)->toBase()->reorder()->selectRaw('COUNT(*) as n, COALESCE(SUM(commission_amount),0) as total')->first();

        // المصروفُ لكلّ موظف بالفلاتر نفسِها — تجميعٌ واحد للرسم لا الصفحةُ وحدها
        $paidByEmployee = (clone $query)->toBase()->reorder()
            ->selectRaw('employee_id, COALESCE(SUM(commission_amount),0) as total')
            ->groupBy('employee_id')->pluck('total', 'employee_id');

        $commissions = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        // حالةُ قيد كلّ عمولة (معتمد / بانتظار الاعتماد) — قراءةٌ واحدة للصفحة
        $journalStatus = JournalEntry::whereIn('id', $commissions->pluck('direct_journal_entry_id')->filter()->all() ?: [0])
            ->pluck('status', 'id');

        $employees = $this->scopeToEmployees(
            Employee::select('id', 'first_name', 'middle_name', 'nickname'),
            'id'
        )->orderBy('first_name')->get();

        $targets = $this->targetModel($request)::orderBy('name')->get(['id', 'name']);

        // الإحصاءاتُ في النطاق نفسه — لا عمولاتُ المنشأة كلِّها
        $scoped = fn () => $this->scopeToEmployees($this->paidQuery(), 'employee_id');
        $byPeriod = fn (string $period) => $scoped()->whereHas('target', fn ($q) => $q->where('commission_period', $period));

        $stats = [
            'total_monthly' => (float) $byPeriod('monthly')->sum('commission_amount'),
            'total_weekly' => (float) $byPeriod('weekly')->sum('commission_amount'),
            'count_monthly' => $byPeriod('monthly')->count(),
            'count_weekly' => $byPeriod('weekly')->count(),
            'pending_approval' => $scoped()->where('is_settled', false)->where('paid_directly', false)->whereNotNull('direct_journal_entry_id')->count(),
            'filtered_total' => (float) ($totals->total ?? 0),
            'filtered_count' => (int) ($totals->n ?? 0),
        ];

        $kind = $this->kind($request);
        $setting = TargetSetting::for($kind);

        // «قيد الانتظار»: من استحقّ عمولةً ولم تُصرف له — في النطاق والفلاتر نفسِها
        $pending = $this->pendingRows($request);
        $due = $pending->where('state', '!=', PendingCommissions::NOT_DUE);
        $running = $pending->where('state', PendingCommissions::NOT_DUE);

        $stats['pending_due_count'] = $due->count();
        $stats['pending_due_total'] = (float) $due->sum('commission');
        $stats['pending_running_count'] = $running->count();
        $stats['pending_running_total'] = (float) $running->sum('commission');

        $tab = $request->input('tab') === 'pending' ? 'pending' : 'paid';

        // الرسمان: الأعمدةُ تتبع التبويب (من صُرف له أكثر / من ينتظر أكثر)، والحلقةُ حالُ العمولات كلِّها
        $empNames = $employees->keyBy('id');
        $hrCharts = [
            'bar' => $tab === 'pending'
                ? [
                    'title' => 'الأعلى عمولةً لم تُصرف', 'icon' => 'hourglass-half', 'color' => '#ef4444', 'money' => true, 'unit' => 'ر.س',
                    'items' => $pending->groupBy(fn ($r) => $r['employee_id'] ?: 'x:'.$r['employee_name'])
                        ->map(fn ($g) => ['name' => $g->first()['employee_name'] ?? '—', 'value' => (float) $g->sum('commission')])->values()->all(),
                ]
                : [
                    'title' => 'الأعلى عمولةً مصروفة', 'icon' => 'hand-holding-usd', 'color' => '#10b981', 'money' => true, 'unit' => 'ر.س',
                    'items' => $paidByEmployee->map(fn ($total, $id) => [
                        'name' => $empNames->get($id)?->full_name ?? 'غير محدد', 'value' => (float) $total,
                    ])->values()->all(),
                ],
            'donut' => [
                'title' => 'العمولات حسب الحالة', 'unit' => 'عمولة',
                'groups' => [
                    ['label' => 'مصروفة (حسب الفلتر)', 'count' => $stats['filtered_count'], 'sum' => $stats['filtered_total'], 'color' => '#10b981'],
                    ['label' => 'مستحقة لم تُصرف', 'count' => $stats['pending_due_count'], 'sum' => $stats['pending_due_total'], 'color' => '#ef4444'],
                    ['label' => 'متراكمة لأهداف جارية', 'count' => $stats['pending_running_count'], 'sum' => $stats['pending_running_total'], 'color' => '#3b82f6'],
                ],
            ],
        ];

        return view('hr::commissions.index', compact(
            'commissions', 'employees', 'targets', 'stats', 'journalStatus', 'setting', 'kind', 'pending', 'tab', 'hrCharts'
        ));
    }

    /** سببُ انتظار العمولة غير المصروفة — بلغة الشاشة */
    public const PENDING_LABELS = [
        PendingCommissions::TREASURY_SHORT => 'رصيد الخزينة لا يكفي',
        PendingCommissions::NO_TREASURY => 'لا خزينة للصرف',
        PendingCommissions::NO_EMPLOYEE => 'الحساب بلا سجلّ موظف',
        PendingCommissions::AWAITING_RUN => 'تنتظر التسوية',
        PendingCommissions::MANUAL => 'تنتظر تسوية يدوية',
        PendingCommissions::NOT_DUE => 'لم تنتهِ مدّة الهدف',
    ];

    /**
     * العمولةُ «بانتظار اعتماد القيد»: لا تسويةَ ولا صرفَ مباشر، وقيدُها غيرُ
     * معتمد (`journal_entries.status != 1`) — القاعدةُ نفسُها في الشاشة والتصدير.
     */
    public static function awaitsJournalApproval($c, $journalStatus): bool
    {
        return ! $c->is_settled && ! $c->paid_directly && $c->direct_journal_entry_id
            && (int) ($journalStatus[$c->direct_journal_entry_id] ?? 0) !== 1;
    }

    /** صفوفُ العمولات المصروفة للتصدير والطباعة — بترتيب أعمدة الشاشة */
    private function paidLines($commissions): array
    {
        $journalStatus = JournalEntry::whereIn('id', $commissions->pluck('direct_journal_entry_id')->filter()->all() ?: [0])->pluck('status', 'id');

        return $commissions->values()->map(fn ($c, $i) => $this->paidLine($c, $i, $journalStatus))->all();
    }

    private function paidLine($c, int $i, $journalStatus): array
    {
        $paidAt = $c->direct_paid_at ?? $c->settled_date ?? $c->created_at;

        return [
            $i + 1,
            $c->employee->full_name ?? 'غير محدد',
            $c->target->name ?? '—',
            match ($c->target->commission_period ?? null) {
                'weekly' => 'أسبوعية',
                default => 'شهرية',
            },
            trim(optional($c->from_date)->format('Y-m-d').' ← '.optional($c->to_date)->format('Y-m-d'), ' ←'),
            $paidAt ? Carbon::parse($paidAt)->format('Y-m-d') : '—',
            number_format((float) $c->commission_amount, 2),
            $c->paid_directly ? 'صرف مباشر' : ($c->direct_journal_entry_id ? 'قيد صرف' : 'تسوية على الراتب'),
            self::awaitsJournalApproval($c, $journalStatus) ? 'بانتظار اعتماد القيد' : ($c->paid_directly ? 'صرف مباشر' : 'مصروفة'),
        ];
    }

    /**
     * العمولاتُ المستحقّة غير المصروفة، مقصورةً على نطاق المستخدم وفلترَي
     * الموظف والهدف.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingRows(Request $request)
    {
        $rows = app(PendingCommissions::class)->for($this->kind($request));

        $allowed = $this->accessibleEmployeeIds();
        if ($allowed !== null) {
            $rows = $rows->filter(fn ($row) => $row['employee_id'] && in_array($row['employee_id'], $allowed));
        }

        if ($request->filled('employee_id')) {
            $rows = $rows->where('employee_id', (int) $request->employee_id);
        }

        if ($request->filled('target_id')) {
            $rows = $rows->where('target_id', (int) $request->target_id);
        }

        // ما حلّ موعدُه أوّلاً، ثم الأكبرُ مبلغاً
        return $rows->sortBy([
            fn ($a, $b) => ($a['state'] === PendingCommissions::NOT_DUE) <=> ($b['state'] === PendingCommissions::NOT_DUE),
            fn ($a, $b) => $b['commission'] <=> $a['commission'],
        ])->values();
    }

    /**
     * «العمولات المصروفة» إلى Excel — الاستعلامُ نفسُه الذي تعرضه القائمة
     * (`paidQuery()` + `applyFilters()` بنطاق المستخدم)، كلُّه لا صفحةٌ واحدة.
     */
    public function export(Request $request)
    {
        $commissions = $this->applyFilters(
            $this->paidQuery()->with([
                'target:id,name,commission_period,target_value',
                'settler:id,name',
                'employee:id,first_name,middle_name,nickname',
            ]),
            $request
        )->orderByDesc('id')->get();

        $rows = $commissions->map(function ($c) {
            $employee = $c->employee;
            $paidAt = $c->direct_paid_at ?? $c->settled_date ?? $c->created_at;
            $posted = $c->is_settled || $c->paid_directly;

            return [
                $c->id,
                $employee ? trim(($employee->full_name ?? '') ?: trim($employee->first_name.' '.$employee->middle_name)) : '—',
                $c->target->name ?? '',
                match ($c->target->commission_period ?? null) {
                    'weekly' => 'أسبوعية',
                    'monthly' => 'شهرية',
                    default => '',
                },
                trim(optional($c->from_date)->format('Y-m-d').' — '.optional($c->to_date)->format('Y-m-d'), ' —'),
                $paidAt ? Carbon::parse($paidAt)->format('Y-m-d') : '',
                round((float) $c->commission_amount, 2),
                $c->paid_directly ? 'صرف مباشر' : ($c->direct_journal_entry_id ? 'قيد صرف' : 'تسوية على الراتب'),
                $c->direct_journal_entry_id ? (string) $c->direct_journal_entry_id : '',
                $posted ? 'مصروفة' : ($c->direct_journal_entry_id ? 'بانتظار اعتماد القيد' : ''),
                $c->settler->name ?? '',
            ];
        })->all();

        return Excel::download(
            new HrReportTableExport(
                $this->isCollections($request) ? 'عمولات أهداف التحصيلات المصروفة' : 'عمولات أهداف المبيعات المصروفة',
                ['#', 'الموظف', 'الهدف', 'نوع العمولة', 'الفترة', 'تاريخ الصرف', 'المبلغ', 'طريقة الصرف', 'رقم القيد', 'الحالة', 'صرفها'],
                $rows
            ),
            'commissions_'.now()->format('Y-m-d').'.xlsx'
        );
    }

    public function show($id)
    {
        $kind = $this->kind();

        $commission = $this->settlementModel()::with([
            'target',
            'target.commissionTiers',
            'settler:id,name',
        ])->findOrFail($id);

        // القالبُ يقرأ `salesTarget` — تُسمّى به علاقةُ الهدف في النوعين
        $commission->setRelation('salesTarget', $commission->target);

        // حمايةُ السجلّ المفرد: `show/{id}` تُطلب بمعرّفٍ مباشر
        $this->authorizeEmployeeScope($commission->employee_id);

        $employee = Employee::with('department')->find($commission->employee_id);

        // القيدُ وسندُ الصرف اللذان كتبتهما التسوية — السندُ مرجعُ القيد
        $journal = $commission->direct_journal_entry_id
            ? JournalEntry::with('details.account')->find($commission->direct_journal_entry_id)
            : null;
        $expense = $journal && $journal->reference_id
            ? Expense::where('id', $journal->reference_id)->where('code', $journal->reference_number)->first()
            : null;

        // الهدفُ المسنَد للموظف: `sales_target_achievements.employee_id` يحمل users.id
        $userId = User::where('employee_id', $commission->employee_id)->value('id');
        $assignment = $userId
            ? ($this->isCollections() ? CollectionsTargetAchievement::class : SalesTargetAchievement::class)::where($this->targetKey(), $commission->{$this->targetKey()})->where('employee_id', $userId)->first()
            : null;

        // الإنجازُ مستخرجاً من العمولة وشريحتها: العمولة = المحقق × نسبة الشريحة
        $tiers = $commission->target?->commissionTiers?->sortBy('achievement_percentage') ?? collect();

        return view('hr::commissions.show', compact('commission', 'employee', 'journal', 'expense', 'assignment', 'tiers', 'kind'));
    }
}
