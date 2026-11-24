<?php

namespace Modules\HR\Http\Controllers;

use App\Enums\EmployeeDocumentType;
use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Support\PayrollSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\HR\Http\Controllers\Concerns\BuildsHrReports;
use Modules\HR\Http\Controllers\Concerns\ExportsHrReports;

/**
 * تقاريرُ الموظفين — بتصميم تقارير العملاء (rpt / ag).
 *
 * كلُّ تقريرٍ يبني صفوفه مصفوفاتٍ، فتُرقَّم صفحاتُها بـ`hrPaginate()`
 * (خمسون في الصفحة)، وتخرج كلُّها بـ`?export=rows` لزرَّي التصدير والطباعة
 * بترتيب أعمدة `frHeaders` في القالب نفسه. وفرعا `?export=xlsx|pdf` القديمان
 * باقيان لمن يستدعيهما من رابطٍ محفوظ.
 *
 * أغلبُها تقاريرُ لحظية («كما الحال اليوم») بلا مدى: القائمة، وحسب القسم
 * والمسمى والحالة، والإحصائيات، والهيكل. والمتحقّقون والمغادرون وحدهم
 * بمدى، والعقودُ والوثائقُ بحقل «تنتهي خلال» ومدى انتهاءٍ خاصٍّ بهما.
 *
 * حالةُ الموظف هنا بالحالات الستّ (`EmploymentStatus`) لا بثنائية
 * «نشط/غير نشط»: «على رأس العمل» = نشط + تحت التجربة، كما يقرؤها الصرف.
 */
class EmployeeReportController extends Controller
{
    use BuildsHrReports;
    use ExportsHrReports;

    /** البلدان كما في نموذج الموظف (`employees.country`). */
    private const COUNTRIES = [
        1 => 'السعودية', 2 => 'الإمارات', 3 => 'الكويت', 4 => 'قطر', 5 => 'البحرين', 6 => 'عُمان',
        7 => 'مصر', 8 => 'الأردن', 9 => 'لبنان', 10 => 'سوريا', 11 => 'العراق', 12 => 'فلسطين',
        13 => 'اليمن', 14 => 'الجزائر', 15 => 'المغرب', 16 => 'تونس', 17 => 'ليبيا', 18 => 'السودان',
        19 => 'موريتانيا', 20 => 'جيبوتي', 21 => 'الصومال', 22 => 'جزر القمر',
    ];

    /** حالةُ المواطنة كما في نموذج الموظف (`employees.nationality_status`). */
    private const NATIONALITY = [1 => 'مواطن', 2 => 'مقيم', 3 => 'زائر'];

    public function __construct()
    {
        /*
            تقاريرُ الموظفين كلُّها بصلاحية قسمها (`hr_reports.employees`)،
            والهيكلُ التنظيمي بصلاحية شاشته. و«على أيّ موظفين» يحدّده
            نطاقُ الرؤية في كل استعلام. فهرسُ البلاطات يُصفّى بالصلاحية
            نفسِها، فلا يظهر زرٌّ يَعِد بما يمنعه الخادم.
        */
        $this->middleware('permission.role:hr_reports.employees', ['only' => [
            'index', 'employees', 'byDepartment', 'byJobTitle', 'byStatus', 'turnover',
            'contracts', 'documents', 'statistics',
        ]]);
        /*
            كانت هذه محروسةً بصلاحية شاشة الأقسام
            (`OrganizationalStructure/DepartmentController`) لا شجرة
            التسلسل الإداري. من يُمنح "عرض الأقسام" وحدها كان يرى بالخطأ
            أسماء كل الموظفين ومسمّياتهم وحالاتهم الوظيفية. صلاحية مستقلة
            الآن (`org_chart.index`)، ومنحها للأدوار التي كانت تملك
            صلاحيةِ الأقسام القديمة مضبوطٌ في ترحيل منفصل فلا يفقد أحدٌ
            وصولاً كان يملكه فعلاً.
        */
        $this->middleware('permission.role:org_chart.index', ['only' => ['organization']]);

        /*
            التصديرُ إلى ملفّ (`export=xlsx|pdf`) صلاحيةٌ فوق صلاحية التقرير
            (`hr_reports.export`): من يطّلع على التقرير ليس بالضرورة من
            يُخرجه ملفّاً. أمّا `export=rows` فهو الصفوفُ نفسُها التي يراها
            في الشاشة صفحةً صفحة، ويقرؤها زرُّ «طباعة» أيضاً — فلا يُحجب،
            وزرُّ «تصدير» نفسُه يُخفى في القالب عمّن لا يملك الصلاحية.
        */
        $this->middleware(function ($request, $next) {
            if ($request->filled('export') && $request->input('export') !== 'rows' && ! hasPermission('hr_reports.export')) {
                abort(403, 'ليس لديك صلاحية تصدير تقارير الموارد البشرية.');
            }

            return $next($request);
        });
    }

    /**
     * بلاطات تقارير الموظفين — مصدرٌ واحد للفهرسَين (هذه الشاشة
     * و`hr.reports.index`) فلا تفترق العناوين عن المسارات عن الصلاحيات.
     *
     * التصديرُ والطباعةُ من الشاشة نفسها (`export=rows`). إحصائيات الموظفين
     * لوحةُ مؤشّراتٍ بلا تصدير وفق §11.2.
     *
     * @return array<int, array{route: string, permission: string, icon: string, title: string, hint: string}>
     */
    public static function catalog(): array
    {
        return [
            [
                'route' => 'hr.reports.employees',
                'permission' => 'hr_reports.employees',
                'icon' => 'users',
                'title' => 'قائمة الموظفين',
                'hint' => 'الرقم والاسم والقسم والمسمّى والفرع والمدير وتاريخ التعيين والحالة',
            ],
            [
                'route' => 'hr.reports.employees.by_department',
                'permission' => 'hr_reports.employees',
                'icon' => 'grid',
                'title' => 'الموظفون حسب القسم',
                'hint' => 'عدد الموظفين وتفاصيلهم مجمَّعين حسب القسم',
            ],
            [
                'route' => 'hr.reports.employees.by_job_title',
                'permission' => 'hr_reports.employees',
                'icon' => 'briefcase',
                'title' => 'الموظفون حسب المسمّى',
                'hint' => 'عدد الموظفين وتفاصيلهم مجمَّعين حسب المسمّى الوظيفي',
            ],
            [
                'route' => 'hr.reports.employees.by_status',
                'permission' => 'hr_reports.employees',
                'icon' => 'activity',
                'title' => 'النشطون وغير النشطين',
                'hint' => 'الموظفون حسب الحالة الوظيفية: نشط، تحت التجربة، موقوف، مستقيل، منتهي الخدمة',
            ],
            [
                'route' => 'hr.reports.employees.turnover',
                'permission' => 'hr_reports.employees',
                'icon' => 'repeat',
                'title' => 'المتحقّقون والمغادرون',
                'hint' => 'التعيينات وانتهاء الخدمة خلال فترة، مع السبب والقسم',
            ],
            [
                'route' => 'hr.reports.employees.contracts',
                'permission' => 'hr_reports.employees',
                'icon' => 'file-text',
                'title' => 'العقود',
                'hint' => 'العقود المنتهية والتي تنتهي قريباً',
            ],
            [
                'route' => 'hr.reports.employees.documents',
                'permission' => 'hr_reports.employees',
                'icon' => 'folder',
                'title' => 'وثائق الموظفين المنتهية',
                'hint' => 'الهويات والإقامات والجوازات التي انتهت أو قاربت على الانتهاء',
            ],
            [
                'route' => 'hr.reports.employees.statistics',
                'permission' => 'hr_reports.employees',
                'icon' => 'pie-chart',
                'title' => 'إحصائيات الموظفين',
                'hint' => 'العدد ومتوسط الأعمار ومتوسط الخدمة والتوزيع حسب النوع',
            ],
            [
                'route' => 'hr.reports.employees.organization',
                'permission' => 'org_chart.index',
                'icon' => 'git-merge',
                'title' => 'الهيكل التنظيمي',
                'hint' => 'شجرة المدير المباشر ومن يتبعه',
            ],
        ];
    }

    public function index()
    {
        return view('hr::employee.report.index', [
            'tiles' => self::catalog(),
        ]);
    }

    /* ══════════════════════════ قائمة الموظفين ══════════════════════════ */

    /** قائمة الموظفين — §11.2 الصف الأول. موظفٌ لكلّ صف كما هو اليوم. */
    public function employees(Request $request)
    {
        $employees = $this->employeesBase($request, ['department', 'job_title', 'branch', 'direct_manager'])
            ->orderBy('first_name')
            ->get();

        if (in_array($request->input('export'), ['xlsx', 'pdf'], true)) {
            return $this->exportIfRequested(
                $request,
                'قائمة الموظفين',
                ['الرقم', 'الاسم', 'القسم', 'المسمّى', 'الفرع', 'المدير', 'التعيين', 'النوع', 'الحالة'],
                $this->rosterExportRows($employees),
                'employees'
            );
        }

        $ids = $employees->pluck('id');
        $today = Carbon::today();
        $notice = $this->noticePeriod('contract_end_notice_period');

        // آخرُ عقدٍ لكلّ موظف (بتاريخ البداية ثم الرقم) — ونهايتُه هي «نهاية العقد»
        $lastContract = Contract::whereIn('employee_id', $ids)
            ->orderBy('start_date')->orderBy('id')
            ->get(['id', 'employee_id', 'start_date', 'end_date'])
            ->groupBy('employee_id')
            ->map->last();

        // رقمُ الهوية أو الإقامة من وثائق الموظف — أحدثُها انتهاءً
        $idDocs = EmployeeDocument::whereIn('employee_id', $ids)
            ->whereIn('type', [EmployeeDocumentType::NATIONAL_ID->value, EmployeeDocumentType::RESIDENCY->value])
            ->whereNotNull('number')
            ->orderBy('expiry_date')
            ->get(['employee_id', 'type', 'number', 'expiry_date'])
            ->groupBy('employee_id')
            ->map->last();

        $rows = $employees->map(function (Employee $e) use ($lastContract, $idDocs, $today) {
            $status = $e->employmentStatus();
            $contract = $lastContract->get($e->id);
            $contractDays = $contract?->end_date ? (int) $today->diffInDays($contract->end_date->copy()->startOfDay(), false) : null;

            return [
                'id' => $e->id,
                'code' => $e->employee_code ?: '—',
                'name' => $e->full_name,
                'department' => $e->department->name ?? '—',
                'job_title' => $e->job_title->name ?? '—',
                'branch' => $e->branch->name ?? '—',
                'manager' => $e->direct_manager?->full_name ?? '—',
                'hire_date' => $e->hire_date?->toDateString(),
                'is_new' => $e->hire_date && $e->hire_date->isSameMonth($today),
                'tenure' => $this->tenure($e),
                'nationality' => $this->nationality($e),
                'id_number' => $idDocs->get($e->id)?->number ?? '—',
                'contract_end' => $contract ? ($contract->end_date?->toDateString() ?? 'مفتوح') : '—',
                'contract_days' => $contractDays,
                'type' => $this->employeeTypeLabel($e->employee_type),
                'status' => $status->label(),
                'status_tone' => $this->statusTone($status),
                'working' => $status->isActive(),
            ];
        })->values();

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $rows->map(fn ($r, $i) => [
                $i + 1, $r['code'], $r['name'], $r['department'], $r['job_title'], $r['branch'], $r['manager'],
                $r['hire_date'] ?? '—', $r['tenure'], $r['nationality'], $r['id_number'], $r['contract_end'], $r['status'],
            ])->all()]);
        }

        return view('hr::reports.employees.list', [
            'rows' => $this->hrPaginate($rows, $request),
            'totals' => [
                'all' => $rows->count(),
                'working' => $rows->where('working', true)->count(),
                'new' => $rows->where('is_new', true)->count(),
                'expiring' => $rows->filter(fn ($r) => $r['working'] && $r['contract_days'] !== null && $r['contract_days'] >= 0 && $r['contract_days'] <= $notice)->count(),
                'expired' => $rows->filter(fn ($r) => $r['working'] && $r['contract_days'] !== null && $r['contract_days'] < 0)->count(),
            ],
            'notice' => $notice,
            'hrCharts' => [
                'bar' => $this->countBar('الموظفون حسب القسم', 'sitemap', '#3b82f6', $rows->countBy('department')),
                'donut' => $this->statusDonut($employees->map(fn (Employee $e) => $e->employmentStatus())),
            ],
        ] + $this->sharedViewData());
    }

    /* ═════════════════════ حسب القسم / حسب المسمّى ═════════════════════ */

    /** الموظفون حسب القسم. */
    public function byDepartment(Request $request)
    {
        return $this->groupedReport($request, 'department');
    }

    /** الموظفون حسب المسمّى الوظيفي. */
    public function byJobTitle(Request $request)
    {
        return $this->groupedReport($request, 'job_title');
    }

    /**
     * تقريرٌ مجمَّع: ملخّصٌ (مجموعةٌ لكلّ صف) أو تفاصيل (موظفٌ لكلّ صف).
     * القسمُ والمسمى وجهان لتقريرٍ واحد، فيتشاركان المنطقَ والقالب.
     */
    private function groupedReport(Request $request, string $by)
    {
        $isDept = $by === 'department';
        $fk = $isDept ? 'department_id' : 'job_title_id';
        $none = $isDept ? 'بدون قسم' : 'بدون مسمّى';
        $deleted = $isDept ? 'قسم محذوف' : 'مسمّى محذوف';
        $title = $isDept ? 'الموظفون حسب القسم' : 'الموظفون حسب المسمّى';
        $slug = $isDept ? 'employees_by_department' : 'employees_by_job_title';
        $other = $isDept ? 'job_title' : 'department';

        $employees = $this->employeesBase($request, ['department', 'job_title', 'branch'])
            ->orderBy('first_name')
            ->get();

        $groupName = fn (Employee $e) => $e->{$fk} ? ($e->{$by}->name ?? $deleted) : $none;

        $groups = $employees
            ->groupBy(fn (Employee $e) => (int) ($e->{$fk} ?: 0))
            ->map(function ($rows, $id) use ($groupName, $employees) {
                $statuses = $rows->map(fn (Employee $e) => $e->employmentStatus());

                return [
                    'id' => (int) $id,
                    'name' => $groupName($rows->first()),
                    'count' => $rows->count(),
                    'working' => $statuses->filter(fn ($s) => $s->isActive())->count(),
                    'probation' => $statuses->filter(fn ($s) => $s === EmploymentStatus::PROBATION)->count(),
                    'stopped' => $statuses->filter(fn ($s) => ! $s->isActive() && ! $s->isEnded())->count(),
                    'ended' => $statuses->filter(fn ($s) => $s->isEnded())->count(),
                    'share' => $employees->count() ? round($rows->count() * 100 / $employees->count(), 1) : 0,
                ];
            })
            // «بدون قسم/مسمّى» آخِراً، والباقي أبجدياً
            ->sortBy(fn ($g) => ($g['id'] === 0 ? '1' : '0').$g['name'])
            ->values();

        if (in_array($request->input('export'), ['xlsx', 'pdf'], true)) {
            $counts = $groups->keyBy('id');

            return $this->exportIfRequested(
                $request,
                $title,
                [$isDept ? 'القسم' : 'المسمّى', 'العدد', 'الرقم', 'الاسم', $isDept ? 'المسمّى' : 'القسم', 'الفرع', 'الحالة'],
                $employees->sortBy(fn (Employee $e) => $groupName($e))->map(fn (Employee $e) => [
                    $groupName($e),
                    $counts->get((int) ($e->{$fk} ?: 0))['count'] ?? 0,
                    $e->employee_code ?: '—',
                    $e->full_name,
                    $e->{$other}->name ?? '—',
                    $e->branch->name ?? '—',
                    $e->employmentStatus()->label(),
                ])->values()->all(),
                $slug
            );
        }

        $view = $request->input('view') === 'details' ? 'details' : 'summary';

        $details = $employees
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'group' => $groupName($e),
                'code' => $e->employee_code ?: '—',
                'name' => $e->full_name,
                'other' => $e->{$other}->name ?? '—',
                'branch' => $e->branch->name ?? '—',
                'hire_date' => $e->hire_date?->toDateString() ?? '—',
                'status' => $e->employmentStatus()->label(),
                'status_tone' => $this->statusTone($e->employmentStatus()),
            ])
            ->sortBy(fn ($r) => ($r['group'] === $none ? '1' : '0').$r['group'].'|'.$r['name'])
            ->values();

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $view === 'summary'
                ? $groups->map(fn ($g, $i) => [$i + 1, $g['name'], $g['count'], $g['working'], $g['probation'], $g['stopped'], $g['ended'], $g['share'].'%'])->all()
                : $details->map(fn ($r, $i) => [$i + 1, $r['group'], $r['code'], $r['name'], $r['other'], $r['branch'], $r['hire_date'], $r['status']])->all(),
            ]);
        }

        $largest = $groups->sortByDesc('count')->first();

        return view('hr::reports.employees.'.($isDept ? 'by_department' : 'by_job_title'), [
            'groupBy' => $by,
            'groupKey' => $fk,
            'groupLabel' => $isDept ? 'القسم' : 'المسمّى الوظيفي',
            'otherLabel' => $isDept ? 'المسمّى' : 'القسم',
            'reportTitle' => $title,
            'reportRoute' => $isDept ? 'hr.reports.employees.by_department' : 'hr.reports.employees.by_job_title',
            'reportView' => $view,
            'summary' => $view === 'summary' ? $this->hrPaginate($groups, $request) : null,
            'details' => $view === 'details' ? $this->hrPaginate($details, $request) : null,
            'totals' => [
                'groups' => $groups->count(),
                'employees' => $employees->count(),
                'working' => $groups->sum('working'),
                'ended' => $groups->sum('ended'),
                'stopped' => $groups->sum('stopped'),
                'largest' => $largest,
            ],
            'hrCharts' => [
                'bar' => $this->countBar('الموظفون لكل '.($isDept ? 'قسم' : 'مسمّى'), $isDept ? 'sitemap' : 'briefcase', '#3b82f6', $groups->pluck('count', 'name')),
                'donut' => $this->statusDonut($employees->map(fn (Employee $e) => $e->employmentStatus())),
            ],
        ] + $this->sharedViewData());
    }

    /* ══════════════════════════ حسب الحالة ══════════════════════════ */

    /** النشطون وغير النشطين حسب الحالة الوظيفية. */
    public function byStatus(Request $request)
    {
        $query = $this->employeesBase($request, ['department', 'job_title', 'branch']);

        // «تعيينٌ أو إنهاءٌ في المدى» — حقلٌ اختياري، والتقريرُ لحظيٌّ بدونه
        if ($request->filled('from_date') || $request->filled('to_date')) {
            $from = $request->input('from_date');
            $to = $request->input('to_date');
            $query->where(function (Builder $q) use ($from, $to) {
                $q->where(function (Builder $hire) use ($from, $to) {
                    $hire->whereNotNull('hire_date');
                    if ($from) {
                        $hire->whereDate('hire_date', '>=', $from);
                    }
                    if ($to) {
                        $hire->whereDate('hire_date', '<=', $to);
                    }
                })->orWhere(function (Builder $term) use ($from, $to) {
                    $term->whereNotNull('termination_date');
                    if ($from) {
                        $term->whereDate('termination_date', '>=', $from);
                    }
                    if ($to) {
                        $term->whereDate('termination_date', '<=', $to);
                    }
                });
            });
        }

        if ($request->filled('termination_reason')) {
            $query->where('termination_reason', 'like', '%'.$request->termination_reason.'%');
        }

        $employees = $query->orderBy('first_name')->get();
        $total = $employees->count();

        $groups = collect(EmploymentStatus::cases())
            ->map(function (EmploymentStatus $status) use ($employees, $total) {
                $count = $employees->filter(fn (Employee $e) => $e->employmentStatus() === $status)->count();

                return [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'tone' => $this->statusTone($status),
                    'count' => $count,
                    'share' => $total ? round($count * 100 / $total, 1) : 0,
                    'group' => $status->isActive() ? 'على رأس العمل' : ($status->isEnded() ? 'انتهت خدمته' : 'متوقّف مؤقتاً'),
                ];
            })
            ->filter(fn ($g) => $g['count'] > 0 || ! $request->filled('status'))
            ->values();

        if (in_array($request->input('export'), ['xlsx', 'pdf'], true)) {
            $counts = $groups->keyBy('value');

            return $this->exportIfRequested(
                $request,
                'النشطون وغير النشطين',
                ['الحالة', 'العدد', 'الرقم', 'الاسم', 'القسم', 'المسمّى', 'الفرع'],
                $employees->sortBy(fn (Employee $e) => $e->employmentStatus()->value)->map(fn (Employee $e) => [
                    $e->employmentStatus()->label(),
                    $counts->get($e->employmentStatus()->value)['count'] ?? 0,
                    $e->employee_code ?: '—',
                    $e->full_name,
                    $e->department->name ?? '—',
                    $e->job_title->name ?? '—',
                    $e->branch->name ?? '—',
                ])->values()->all(),
                'employees_by_status'
            );
        }

        $view = $request->input('view') === 'details' ? 'details' : 'summary';

        $details = $employees
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'code' => $e->employee_code ?: '—',
                'name' => $e->full_name,
                'department' => $e->department->name ?? '—',
                'job_title' => $e->job_title->name ?? '—',
                'branch' => $e->branch->name ?? '—',
                'status_value' => $e->employmentStatus()->value,
                'status' => $e->employmentStatus()->label(),
                'status_tone' => $this->statusTone($e->employmentStatus()),
                'hire_date' => $e->hire_date?->toDateString() ?? '—',
                'termination_date' => $e->termination_date?->toDateString() ?? '—',
                'last_working_day' => $e->last_working_day?->toDateString() ?? '—',
                'reason' => $e->termination_reason ?: '—',
            ])
            ->sortBy([['status_value', 'asc'], ['name', 'asc']])
            ->values();

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $view === 'summary'
                ? $groups->map(fn ($g, $i) => [$i + 1, $g['label'], $g['group'], $g['count'], $g['share'].'%'])->all()
                : $details->map(fn ($r, $i) => [
                    $i + 1, $r['code'], $r['name'], $r['department'], $r['job_title'], $r['branch'], $r['status'],
                    $r['hire_date'], $r['termination_date'], $r['last_working_day'], $r['reason'],
                ])->all(),
            ]);
        }

        $statuses = $employees->map(fn (Employee $e) => $e->employmentStatus());

        return view('hr::reports.employees.by_status', [
            'reportView' => $view,
            'summary' => $view === 'summary' ? $this->hrPaginate($groups, $request) : null,
            'details' => $view === 'details' ? $this->hrPaginate($details, $request) : null,
            'totals' => [
                'all' => $total,
                'working' => $statuses->filter(fn ($s) => $s->isActive())->count(),
                'probation' => $statuses->filter(fn ($s) => $s === EmploymentStatus::PROBATION)->count(),
                'stopped' => $statuses->filter(fn ($s) => ! $s->isActive() && ! $s->isEnded())->count(),
                'ended' => $statuses->filter(fn ($s) => $s->isEnded())->count(),
            ],
            'hrCharts' => [
                'bar' => $this->countBar('غير العاملين حسب القسم', 'user-slash', '#ef4444', $employees
                    ->reject(fn (Employee $e) => $e->employmentStatus()->isActive())
                    ->countBy(fn (Employee $e) => $e->department->name ?? 'بدون قسم')),
                'donut' => $this->statusDonut($statuses),
            ],
        ] + $this->sharedViewData());
    }

    /* ═══════════════════════ المتحقّقون والمغادرون ═══════════════════════ */

    /** المتحقّقون والمغادرون (Turnover) — حركةٌ لكلّ صف خلال المدى. */
    public function turnover(Request $request)
    {
        // يفتح على السنة الجارية حتى اليوم — كما كان قبل التصميم الجديد
        if (! $request->hasAny(['from_date', 'to_date', 'accounting_period_id', 'fiscal_year_id'])) {
            $request->merge(['from_date' => Carbon::today()->startOfYear()->toDateString(), 'to_date' => Carbon::today()->toDateString()]);
        }
        $period = $this->hrReportPeriod($request);
        $from = $period['from']->toDateString();
        $to = $period['to']->toDateString();

        $hires = $this->employeesBase($request, ['department', 'job_title', 'branch'])
            ->whereNotNull('hire_date')
            ->whereDate('hire_date', '>=', $from)
            ->whereDate('hire_date', '<=', $to)
            ->get();

        $leaversQuery = $this->employeesBase($request, ['department', 'job_title', 'branch'])
            ->whereNotNull('termination_date')
            ->whereDate('termination_date', '>=', $from)
            ->whereDate('termination_date', '<=', $to);
        if ($request->filled('termination_reason')) {
            $leaversQuery->where('termination_reason', 'like', '%'.$request->termination_reason.'%');
        }
        $leavers = $leaversQuery->get();

        // العددُ في أول المدى: عُيِّن قبله (أو بلا تاريخ تعيين) ولم تنتهِ خدمتُه قبله — بالفلاتر نفسها
        $headcountStart = $this->employeesBase($request)
            ->where(fn (Builder $q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<', $from))
            ->where(fn (Builder $q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>=', $from))
            ->count();

        $rate = $headcountStart > 0 ? round($leavers->count() * 100 / $headcountStart, 1) : null;

        if (in_array($request->input('export'), ['xlsx', 'pdf'], true)) {
            return $this->exportIfRequested(
                $request,
                'الملتحقون والمغادرون',
                ['الحركة', 'الرقم', 'الاسم', 'القسم', 'التاريخ', 'السبب'],
                $hires->map(fn (Employee $e) => [
                    'ملتحق', $e->employee_code ?: '—', $e->full_name, $e->department->name ?? '—', $e->hire_date?->toDateString() ?? '—', '—',
                ])->concat($leavers->map(fn (Employee $e) => [
                    'مغادر', $e->employee_code ?: '—', $e->full_name, $e->department->name ?? '—', $e->termination_date?->toDateString() ?? '—', $e->termination_reason ?: '—',
                ]))->values()->all(),
                'employees_turnover'
            );
        }

        $movement = in_array($request->input('movement'), ['hire', 'leave'], true) ? $request->input('movement') : '';

        $row = fn (Employee $e, string $kind) => [
            'id' => $e->id,
            'kind' => $kind,
            'kind_label' => $kind === 'hire' ? 'التحاق' : 'مغادرة',
            'date' => ($kind === 'hire' ? $e->hire_date : $e->termination_date)?->toDateString() ?? '—',
            'code' => $e->employee_code ?: '—',
            'name' => $e->full_name,
            'department' => $e->department->name ?? '—',
            'job_title' => $e->job_title->name ?? '—',
            'branch' => $e->branch->name ?? '—',
            'status' => $e->employmentStatus()->label(),
            'status_tone' => $this->statusTone($e->employmentStatus()),
            'last_working_day' => $kind === 'leave' ? ($e->last_working_day?->toDateString() ?? '—') : '—',
            'reason' => $kind === 'leave' ? ($e->termination_reason ?: '—') : '—',
        ];

        $rows = collect()
            ->concat($movement !== 'leave' ? $hires->map(fn ($e) => $row($e, 'hire')) : [])
            ->concat($movement !== 'hire' ? $leavers->map(fn ($e) => $row($e, 'leave')) : [])
            ->sortBy([['date', 'desc'], ['name', 'asc']])
            ->values();

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $rows->map(fn ($r, $i) => [
                $i + 1, $r['kind_label'], $r['date'], $r['code'], $r['name'], $r['department'], $r['job_title'],
                $r['branch'], $r['status'], $r['last_working_day'], $r['reason'],
            ])->all()]);
        }

        return view('hr::reports.employees.turnover', [
            'rows' => $this->hrPaginate($rows, $request),
            'totals' => [
                'hires' => $hires->count(),
                'leavers' => $leavers->count(),
                'net' => $hires->count() - $leavers->count(),
                'headcount_start' => $headcountStart,
                'rate' => $rate,
            ],
            'movement' => $movement,
            'hrCharts' => [
                'bar' => $this->countBar('الحركة حسب القسم (التحاق + مغادرة)', 'exchange-alt', '#8b5cf6', $rows->countBy('department'), 'حركة'),
                'donut' => ['title' => 'الالتحاق مقابل المغادرة', 'icon' => 'chart-pie', 'unit' => 'حركة', 'groups' => [
                    ['label' => 'التحاق', 'count' => $rows->where('kind', 'hire')->count(), 'color' => '#10b981'],
                    ['label' => 'مغادرة', 'count' => $rows->where('kind', 'leave')->count(), 'color' => '#ef4444'],
                ]],
            ],
            'fromDate' => $period['from'],
            'toDate' => $period['to'],
            'periodLabel' => $period['label'],
            'periodContext' => $period['context'],
        ] + $this->sharedViewData());
    }

    /* ══════════════════════════════ العقود ══════════════════════════════ */

    /**
     * العقود المنتهية والتي تنتهي قريباً والسارية.
     *
     * «المنتهية» و«تنتهي قريباً» تقرآن **آخرَ عقدٍ** لكلّ موظف: العقدُ الذي
     * جُدِّد (للموظف عقدٌ أحدث منه) أمرُه محسوم، فلا يُنبَّه عليه.
     */
    public function contracts(Request $request)
    {
        $noticeDays = $this->noticePeriod('contract_end_notice_period');
        $within = $request->filled('within_days') ? max(0, (int) $request->within_days) : $noticeDays;
        $status = in_array($request->input('status'), ['expiring', 'expired', 'active', 'all'], true) ? $request->input('status') : 'expiring';
        $today = Carbon::today();

        $employeeIds = $this->employeesBase($request, [], 'employee_state', 'working')->pluck('id');

        $all = Contract::query()
            ->with(['employee.department', 'employee.job_title', 'employee.branch'])
            ->whereIn('employee_id', $employeeIds)
            ->get();

        // آخرُ عقدٍ لكلّ موظف: الأحدثُ بدايةً ثم الأكبرُ رقماً
        $latestIds = $all->groupBy('employee_id')
            ->map(fn ($rows) => $rows->sortBy([['start_date', 'desc'], ['id', 'desc']])->first()->id)
            ->values()->all();

        $rows = $all->map(function (Contract $c) use ($today, $within, $latestIds) {
            $days = $c->end_date ? (int) $today->diffInDays($c->end_date->copy()->startOfDay(), false) : null;
            $state = $days === null ? 'open' : ($days < 0 ? 'expired' : ($days <= $within ? 'expiring' : 'active'));

            return [
                'id' => $c->id,
                'employee_id' => $c->employee_id,
                'code' => $c->code ?: '—',
                'employee' => $c->employee?->full_name ?? '—',
                'department' => $c->employee?->department->name ?? '—',
                'job_title' => $c->employee?->job_title->name ?? '—',
                'type' => $c->type_contract_text,
                'start' => $c->start_date?->toDateString() ?? '—',
                'end' => $c->end_date?->toDateString() ?? 'مفتوح',
                'end_sort' => $c->end_date?->toDateString() ?? '9999-12-31',
                'days' => $days,
                'state' => $state,
                'latest' => in_array($c->id, $latestIds, true),
            ];
        });

        $latest = $rows->where('latest', true);
        $totals = [
            'expired' => $latest->where('state', 'expired')->count(),
            'expiring' => $latest->where('state', 'expiring')->count(),
            'valid' => $latest->whereIn('state', ['active', 'open', 'expiring'])->count(),
            'open' => $latest->where('state', 'open')->count(),
            'without' => $employeeIds->diff($rows->pluck('employee_id')->unique())->count(),
        ];

        $filtered = match ($status) {
            'expired' => $latest->where('state', 'expired'),
            'expiring' => $latest->where('state', 'expiring'),
            'active' => $rows->whereIn('state', ['active', 'open', 'expiring']),
            default => $rows,
        };
        if ($request->filled('expiry_from')) {
            $filtered = $filtered->filter(fn ($r) => $r['end_sort'] >= $request->input('expiry_from'));
        }
        if ($request->filled('expiry_to')) {
            $filtered = $filtered->filter(fn ($r) => $r['end_sort'] <= $request->input('expiry_to'));
        }
        $filtered = $filtered->sortBy([['end_sort', 'asc'], ['employee', 'asc']])->values();

        $stateLabels = ['expired' => 'منتهٍ', 'expiring' => 'ينتهي قريباً', 'active' => 'ساري', 'open' => 'ساري — بلا نهاية'];

        if (in_array($request->input('export'), ['xlsx', 'pdf'], true)) {
            return $this->exportIfRequested(
                $request,
                'عقود الموظفين',
                ['رقم العقد', 'الموظف', 'القسم', 'البداية', 'الانتهاء', 'الأيام المتبقية', 'الحالة'],
                $filtered->map(fn ($r) => [$r['code'], $r['employee'], $r['department'], $r['start'], $r['end'], $r['days'], $stateLabels[$r['state']]])->all(),
                'employee_contracts'
            );
        }

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $filtered->map(fn ($r, $i) => [
                $i + 1, $r['code'], $r['employee'], $r['department'], $r['job_title'], $r['type'], $r['start'], $r['end'],
                $this->daysText($r['days'], 'انتهى'), $stateLabels[$r['state']],
            ])->all()]);
        }

        return view('hr::reports.employees.contracts', [
            'rows' => $this->hrPaginate($filtered, $request),
            'totals' => $totals,
            'status' => $status,
            'within' => $within,
            'noticeDays' => $noticeDays,
            'stateLabels' => $stateLabels,
            'hrCharts' => [
                'bar' => $this->countBar('العقود المعروضة حسب القسم', 'file-contract', '#f59e0b', $filtered->countBy('department'), 'عقد'),
                'donut' => ['title' => 'حالة آخر عقد لكل موظف', 'icon' => 'chart-pie', 'unit' => 'موظف', 'groups' => [
                    ['label' => $stateLabels['expired'], 'count' => $totals['expired'], 'color' => '#ef4444'],
                    ['label' => $stateLabels['expiring'], 'count' => $totals['expiring'], 'color' => '#f59e0b'],
                    ['label' => $stateLabels['active'], 'count' => $latest->where('state', 'active')->count(), 'color' => '#10b981'],
                    ['label' => $stateLabels['open'], 'count' => $totals['open'], 'color' => '#3b82f6'],
                    ['label' => 'بلا عقد', 'count' => $totals['without'], 'color' => '#64748b'],
                ]],
            ],
        ] + $this->sharedViewData());
    }

    /* ══════════════════════════════ الوثائق ══════════════════════════════ */

    /**
     * وثائق الموظفين المنتهية أو القريبة من الانتهاء.
     *
     * لكلّ موظفٍ ونوعِ وثيقة تُقرأ **أحدثُها انتهاءً**: الإقامةُ المجدَّدة
     * تُبقي القديمةَ في الملفّ منتهيةً، ولا يصحّ أن تُعدّ «منتهية».
     */
    public function documents(Request $request)
    {
        $noticeDays = $this->noticePeriod('document_expiry_notice_period');
        $within = $request->filled('within_days') ? max(0, (int) $request->within_days) : $noticeDays;
        $horizon = in_array($request->input('horizon'), ['expired', 'all'], true) ? $request->input('horizon') : 'window';
        $types = EmployeeDocumentType::options();

        $employeeIds = $this->employeesBase($request, [], 'employee_state', 'working')->pluck('id');

        $query = EmployeeDocument::query()
            ->with(['employee.department', 'employee.branch'])
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('expiry_date');
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $docs = $query->orderBy('expiry_date')->get()
            ->groupBy(fn (EmployeeDocument $d) => $d->employee_id.'|'.$d->type?->value)
            ->map->last()   // الأحدثُ انتهاءً لكلّ موظفٍ ونوع
            ->values();

        $rows = $docs->map(function (EmployeeDocument $d) use ($within) {
            $days = $d->daysUntilExpiry();

            return [
                'id' => $d->id,
                'employee_id' => $d->employee_id,
                'employee' => $d->employee?->full_name ?? '—',
                'department' => $d->employee?->department->name ?? '—',
                'title' => $d->displayTitle(),
                'type' => $d->type?->label() ?? '—',
                'number' => $d->number ?: '—',
                'authority' => $d->issuing_authority ?: '—',
                'issue' => $d->issue_date?->toDateString() ?? '—',
                'expiry' => $d->expiry_date->toDateString(),
                'days' => $days,
                'state' => $days < 0 ? 'expired' : ($days <= $within ? 'expiring' : 'valid'),
            ];
        });

        $totals = [
            'expired' => $rows->where('state', 'expired')->count(),
            'expiring' => $rows->where('state', 'expiring')->count(),
            'valid' => $rows->where('state', 'valid')->count(),
            'employees' => $rows->whereIn('state', ['expired', 'expiring'])->pluck('employee_id')->unique()->count(),
        ];

        $filtered = match ($horizon) {
            'expired' => $rows->where('state', 'expired'),
            'all' => $rows,
            default => $rows->whereIn('state', ['expired', 'expiring']),
        };
        $filtered = $filtered->sortBy([['expiry', 'asc'], ['employee', 'asc']])->values();

        if (in_array($request->input('export'), ['xlsx', 'pdf'], true)) {
            return $this->exportIfRequested(
                $request,
                'وثائق الموظفين',
                ['الموظف', 'النوع', 'الرقم', 'الجهة', 'الانتهاء', 'الأيام المتبقية'],
                $filtered->map(fn ($r) => [$r['employee'], $r['title'], $r['number'], $r['authority'], $r['expiry'], $r['days']])->all(),
                'employee_documents'
            );
        }

        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $filtered->map(fn ($r, $i) => [
                $i + 1, $r['employee'], $r['department'], $r['title'], $r['number'], $r['authority'], $r['issue'], $r['expiry'],
                $this->daysText($r['days'], 'انتهت'),
            ])->all()]);
        }

        return view('hr::reports.employees.documents', [
            'rows' => $this->hrPaginate($filtered, $request),
            'totals' => $totals,
            'horizon' => $horizon,
            'within' => $within,
            'noticeDays' => $noticeDays,
            'types' => $types,
            'hrCharts' => [
                'bar' => $this->countBar('الوثائق المعروضة حسب النوع', 'id-card', '#ef4444', $filtered->countBy('type'), 'وثيقة'),
                'donut' => ['title' => 'حالة الوثائق', 'icon' => 'chart-pie', 'unit' => 'وثيقة', 'groups' => [
                    ['label' => 'منتهية', 'count' => $totals['expired'], 'color' => '#ef4444'],
                    ['label' => 'تنتهي خلال '.$within.' يوماً', 'count' => $totals['expiring'], 'color' => '#f59e0b'],
                    ['label' => 'سارية', 'count' => $totals['valid'], 'color' => '#10b981'],
                ]],
            ],
        ] + $this->sharedViewData());
    }

    /* ══════════════════════════════ الإحصائيات ══════════════════════════════ */

    /** إحصائيات الموظفين — لوحةُ مؤشّراتٍ وجداول صغيرة، بلا تصدير وفق §11.2. */
    public function statistics(Request $request)
    {
        // تفتح على من هم على رأس العمل: متوسطُ عمرٍ يخلط المغادرين لا يصف المنشأة اليوم
        if (! $request->has('status')) {
            $request->merge(['status' => 'working']);
        }

        $employees = $this->employeesBase($request, ['department', 'branch'])->get();
        $total = $employees->count();
        $today = Carbon::today();

        // تاريخُ ميلادٍ يجعل العمرَ دون خمس عشرة سنة خطأُ إدخال (كثيراً ما يُترك على تاريخ اليوم) — لا يدخل المتوسط
        $withBirth = $employees->filter(fn (Employee $e) => $e->date_of_birth && $e->date_of_birth->age >= 15);
        $badBirth = $employees->filter(fn (Employee $e) => $e->date_of_birth && $e->date_of_birth->age < 15)->count();
        $withHire = $employees->filter(fn (Employee $e) => $e->hire_date);
        $serviceYears = fn (Employee $e) => $e->hire_date->diffInDays($e->termination_date ?? $today) / 365.25;

        $distribution = function (Collection $groups) use ($total) {
            return $groups->map(fn ($count, $label) => [
                'label' => (string) $label,
                'count' => $count,
                'share' => $total ? round($count * 100 / $total, 1) : 0,
            ])->sortByDesc('count')->values();
        };

        $bands = function (Collection $values, array $edges, string $unit) {
            $out = [];
            foreach ($edges as $i => $edge) {
                $next = $edges[$i + 1] ?? null;
                $label = $next === null ? $edge.'+ '.$unit : ($edge.' – '.$next.' '.$unit);
                $out[$label] = $values->filter(fn ($v) => $v >= $edge && ($next === null || $v < $next))->count();
            }

            return collect($out);
        };

        $byStatus = collect(EmploymentStatus::cases())
            ->map(fn (EmploymentStatus $s) => [
                'label' => $s->label(),
                'tone' => $this->statusTone($s),
                'count' => $c = $employees->filter(fn (Employee $e) => $e->employmentStatus() === $s)->count(),
                'share' => $total ? round($c * 100 / $total, 1) : 0,
            ])
            ->filter(fn ($r) => $r['count'] > 0)
            ->values();

        $citizens = $employees->filter(fn (Employee $e) => (int) $e->nationality_status === 1)->count();
        $byDepartment = $distribution($employees->countBy(fn (Employee $e) => $e->department->name ?? 'بدون قسم'));

        return view('hr::reports.employees.statistics', [
            'total' => $total,
            'working' => $employees->filter(fn (Employee $e) => $e->isActive())->count(),
            'newThisYear' => $withHire->filter(fn (Employee $e) => $e->hire_date->year === $today->year)->count(),
            'averageAge' => $withBirth->isEmpty() ? null : round($withBirth->avg(fn (Employee $e) => $e->date_of_birth->age), 1),
            'withBirth' => $withBirth->count(),
            'badBirth' => $badBirth,
            'averageServiceYears' => $withHire->isEmpty() ? null : round($withHire->avg($serviceYears), 1),
            'withHire' => $withHire->count(),
            'citizenShare' => $total ? round($citizens * 100 / $total, 1) : null,
            'citizens' => $citizens,
            'byStatus' => $byStatus,
            'byType' => $distribution($employees->countBy(fn (Employee $e) => $this->employeeTypeLabel($e->employee_type))),
            'byDepartment' => $byDepartment,
            'byBranch' => $distribution($employees->countBy(fn (Employee $e) => $e->branch->name ?? 'بدون فرع')),
            'byNationality' => $distribution($employees->countBy(fn (Employee $e) => self::NATIONALITY[(int) $e->nationality_status] ?? 'غير محدد')),
            'byGender' => $distribution($employees->countBy(fn (Employee $e) => match ((int) $e->gender) {
                1 => 'ذكر', 2 => 'أنثى', default => 'غير محدد'
            })),
            'byService' => $distribution($bands($withHire->map($serviceYears), [0, 1, 3, 5, 10], 'سنة')->filter()),
            'byAge' => $distribution($bands($withBirth->map(fn (Employee $e) => $e->date_of_birth->age), [15, 25, 35, 45, 55], 'سنة')->filter()),
            'hrCharts' => [
                'bar' => $this->countBar('الموظفون حسب القسم', 'sitemap', '#3b82f6', $byDepartment->pluck('count', 'label')),
                'donut' => $this->statusDonut($employees->map(fn (Employee $e) => $e->employmentStatus())),
            ],
        ] + $this->sharedViewData());
    }

    /* ══════════════════════════ الهيكل التنظيمي ══════════════════════════ */

    /** شجرة المدير المباشر. */
    public function organization(Request $request)
    {
        $employees = $this->employeesBase($request, ['job_title', 'department', 'branch'])
            ->orderBy('first_name')
            ->get();
        $ids = $employees->pluck('id')->all();

        $roots = $employees
            ->filter(fn (Employee $e) => ! $e->direct_manager_id || ! in_array((int) $e->direct_manager_id, $ids, true))
            ->values();

        $childrenOf = $employees->groupBy(fn (Employee $e) => (int) ($e->direct_manager_id ?: 0));

        /*
            بيانات فاسدة (حلقة كاملة في `direct_manager_id` بلا أي جذر)
            تجعل $roots فارغة رغم أن $employees ليست كذلك — حالة حدّية
            نادرة، لكن رسالة "لا موظفون" حينها مضلِّلة: الموظفون موجودون،
            فقط منقطعون عن كل جذر. تُميَّز هنا فيُعرض تنبيهٌ مختلف بدل
            الصمت. (لا يمكن أن يقع هذا من واجهة الإدخال بعد إغلاق الحلقات
            في EmployeeRequest، لكن بيانات قديمة قد تسبق ذلك الإصلاح.)
        */
        $orphanedByCycle = $roots->isEmpty() && $employees->isNotEmpty();

        if ($request->filled('export')) {
            $flat = [];
            $seen = [];
            // نفس حماية `_tree_node.blade.php` من الدورات — لا يقع هذا
            // عملياً حالياً (راجع تعليق `$orphanedByCycle` أعلاه)، لكن
            // ترك مسار التصدير بلا حمايةٍ مماثلة للقالب تناقضٌ دفاعي.
            $walk = function ($nodes, int $depth) use (&$walk, &$flat, $childrenOf, &$seen): void {
                foreach ($nodes as $employee) {
                    if (in_array((int) $employee->id, $seen, true)) {
                        continue;
                    }
                    $seen[] = (int) $employee->id;
                    $kids = $childrenOf->get((int) $employee->id) ?? collect();

                    $flat[] = [
                        'name' => str_repeat('— ', $depth).$employee->full_name,
                        'code' => $employee->employee_code ?: '—',
                        'job_title' => $employee->job_title->name ?? '—',
                        'department' => $employee->department->name ?? '—',
                        'branch' => $employee->branch->name ?? '—',
                        'level' => $depth + 1,
                        'kids' => $kids->count(),
                        'status' => $employee->employmentStatus()->label(),
                    ];
                    $walk($kids, $depth + 1);
                }
            };
            $walk($roots, 0);

            if ($request->input('export') === 'rows') {
                return response()->json(['rows' => collect($flat)->map(fn ($r, $i) => [
                    $i + 1, $r['name'], $r['code'], $r['job_title'], $r['department'], $r['branch'], $r['level'], $r['kids'] ?: '—', $r['status'],
                ])->all()]);
            }

            if ($file = $this->exportIfRequested(
                $request,
                'الهيكل التنظيمي',
                ['الموظف', 'الرقم', 'المسمّى', 'القسم', 'الفرع'],
                array_map(fn ($r) => [$r['name'], $r['code'], $r['job_title'], $r['department'], $r['branch']], $flat),
                'employees_organization'
            )) {
                return $file;
            }
        }

        return view('hr::reports.employees.organization', [
            'employees' => $employees,
            'roots' => $roots,
            'childrenOf' => $childrenOf,
            'orphanedByCycle' => $orphanedByCycle,
            'totals' => [
                'employees' => $employees->count(),
                'roots' => $roots->count(),
                'managers' => $employees->filter(fn ($e) => $childrenOf->has((int) $e->id))->count(),
                'departments' => $employees->pluck('department_id')->filter()->unique()->count(),
            ],
            'hrCharts' => $this->organizationCharts($employees, $roots, $childrenOf),
        ] + $this->sharedViewData());
    }

    /* ══════════════════════════════ أدوات ══════════════════════════════ */

    /**
     * موظفو التقرير: نطاقُ المستخدم + القسم والمسمى و«تابع لـ» والموظف
     * (`hrReportEmployees` بلا ثنائيّتها)، ثم الحالةُ بالحالات الستّ،
     * والنوعُ والمديرُ المباشر ومدى التعيين.
     *
     * @param  array<int, string>  $with
     */
    private function employeesBase(Request $request, array $with = [], string $statusKey = 'status', string $defaultStatus = ''): Builder
    {
        $query = $this->hrReportEmployees($request, false);
        if ($with !== []) {
            $query->with($with);
        }

        $status = (string) $request->input($statusKey, $defaultStatus);
        if ($status === 'working') {
            $query->whereIn('status', EmploymentStatus::activeValues());
        } elseif ($status === 'ended') {
            $query->endedService();
        } elseif ($status === 'stopped') {
            $query->where(fn (Builder $q) => $q->whereNotIn('status', array_merge(
                EmploymentStatus::activeValues(),
                [EmploymentStatus::RESIGNED->value, EmploymentStatus::TERMINATED->value]
            ))->orWhereNull('status'));
        } elseif ($status !== '' && is_numeric($status)) {
            if ((int) $status === EmploymentStatus::INACTIVE->value) {
                // «غير نشط» تشمل `0` القديمة وما لا يُعرف — كما تقرؤها `EmploymentStatus::fromValue()`
                $query->where(fn (Builder $q) => $q->whereNotIn('status', [1, 3, 4, 5, 6])->orWhereNull('status'));
            } else {
                $query->where('status', (int) $status);
            }
        }

        if ($request->filled('employee_type')) {
            $query->where('employee_type', (int) $request->employee_type);
        }
        if ($request->filled('direct_manager_id')) {
            $query->where('direct_manager_id', (int) $request->direct_manager_id);
        }
        if ($request->filled('hire_from')) {
            $query->whereDate('hire_date', '>=', $request->hire_from);
        }
        if ($request->filled('hire_to')) {
            $query->whereDate('hire_date', '<=', $request->hire_to);
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function sharedViewData(): array
    {
        return $this->hrReportOptions() + [
            'statusOptions' => ['' => 'الكل', 'working' => 'على رأس العمل (نشط + تحت التجربة)', 'stopped' => 'متوقّف مؤقتاً (غير نشط + موقوف)', 'ended' => 'انتهت خدمته (مستقيل + منتهي الخدمة)']
                + EmploymentStatus::options(),
            'employeeTypes' => [1 => 'موظف', 2 => 'مستخدم'],
            'canExport' => hasPermission('hr_reports.export'),
            'canShowEmployee' => hasPermission('employees.show'),
        ];
    }

    /** لونُ شارة الحالة بمفردات `rpt-pill`. */
    private function statusTone(EmploymentStatus $status): string
    {
        return match ($status) {
            EmploymentStatus::ACTIVE => 'rpt-pill-success',
            EmploymentStatus::PROBATION => 'rpt-pill-success',
            EmploymentStatus::SUSPENDED => 'rpt-pill-warning',
            EmploymentStatus::INACTIVE => 'rpt-pill-secondary',
            default => 'rpt-pill-danger',
        };
    }

    /**
     * رسمُ أعمدةٍ من عدٍّ بالاسم (`['القسم' => 3, ...]`) — القالبُ يقصّه إلى العشرة الأكبر.
     *
     * @param  Collection<string, int>  $counts
     */
    private function countBar(string $title, string $icon, string $color, Collection $counts, string $unit = 'موظف'): array
    {
        return [
            'title' => $title, 'icon' => $icon, 'color' => $color, 'unit' => $unit,
            'items' => $counts->map(fn ($count, $name) => ['name' => (string) $name, 'value' => (int) $count])->values()->all(),
        ];
    }

    /**
     * حلقةُ التوزيع على الحالات الستّ — «تحت التجربة» بلونٍ غير «نشط».
     *
     * @param  Collection<int, EmploymentStatus>  $statuses
     */
    private function statusDonut(Collection $statuses): array
    {
        $colors = [
            EmploymentStatus::ACTIVE->value => '#10b981', EmploymentStatus::PROBATION->value => '#06b6d4',
            EmploymentStatus::SUSPENDED->value => '#f59e0b', EmploymentStatus::INACTIVE->value => '#64748b',
            EmploymentStatus::RESIGNED->value => '#f97316', EmploymentStatus::TERMINATED->value => '#ef4444',
        ];

        return [
            'title' => 'التوزيع حسب الحالة الوظيفية', 'icon' => 'chart-pie', 'unit' => 'موظف',
            'groups' => collect(EmploymentStatus::cases())->map(fn (EmploymentStatus $s) => [
                'label' => $s->label(),
                'count' => $statuses->filter(fn ($x) => $x === $s)->count(),
                'color' => $colors[$s->value] ?? '#64748b',
            ])->all(),
        ];
    }

    /** رسما الهيكل: المديرون الأكثر تابعين مباشرين، ومواقعُ الموظفين في الشجرة. */
    private function organizationCharts(Collection $employees, Collection $roots, Collection $childrenOf): array
    {
        $rootIds = $roots->pluck('id')->map(fn ($id) => (int) $id)->all();
        $isRoot = fn ($e) => in_array((int) $e->id, $rootIds, true);
        $hasKids = fn ($e) => $childrenOf->has((int) $e->id);

        return [
            'bar' => [
                'title' => 'المديرون الأكثر تابعين مباشرين', 'icon' => 'user-tie', 'color' => '#10b981', 'unit' => 'تابع',
                'items' => $employees->filter($hasKids)
                    ->map(fn ($e) => ['name' => $e->full_name, 'value' => $childrenOf->get((int) $e->id)->count()])
                    ->values()->all(),
            ],
            'donut' => ['title' => 'مواقع الموظفين في الشجرة', 'icon' => 'chart-pie', 'unit' => 'موظف', 'groups' => [
                ['label' => 'في أعلى الشجرة', 'count' => $employees->filter($isRoot)->count(), 'color' => '#8b5cf6'],
                ['label' => 'مديرون لهم تابعون', 'count' => $employees->filter(fn ($e) => $hasKids($e) && ! $isRoot($e))->count(), 'color' => '#10b981'],
                ['label' => 'بلا تابعين', 'count' => $employees->filter(fn ($e) => ! $hasKids($e) && ! $isRoot($e))->count(), 'color' => '#64748b'],
            ]],
        ];
    }

    /** مدّةُ الخدمة «س سنة · ش شهر» — حتى تاريخ الإنهاء لمن انتهت خدمتُه. */
    private function tenure(Employee $e): string
    {
        if (! $e->hire_date) {
            return '—';
        }
        $end = $e->termination_date ?? Carbon::today();
        if ($e->hire_date->gt($end)) {
            return 'لم يبدأ';
        }
        $diff = $e->hire_date->diff($end);
        $parts = array_filter([$diff->y ? $diff->y.' سنة' : null, $diff->m ? $diff->m.' شهر' : null]);

        return $parts ? implode(' · ', $parts) : $diff->d.' يوم';
    }

    private function nationality(Employee $e): string
    {
        $status = self::NATIONALITY[(int) $e->nationality_status] ?? null;
        $country = self::COUNTRIES[(int) $e->country] ?? null;

        return trim(implode(' · ', array_filter([$status, $country]))) ?: '—';
    }

    /** «باقٍ N يوماً» / «انتهى منذ N يوماً» — نصُّ الأيام في التصدير والطباعة. */
    private function daysText(?int $days, string $ended): string
    {
        if ($days === null) {
            return '—';
        }

        return $days < 0 ? $ended.' منذ '.abs($days).' يوماً' : ($days === 0 ? 'اليوم' : $days.' يوماً');
    }

    private function employeeTypeLabel($type): string
    {
        return match ((int) $type) {
            1 => 'موظف',
            2 => 'مستخدم',
            default => 'غير محدد',
        };
    }

    /** @param  Collection<int, Employee>  $employees */
    private function rosterExportRows($employees): array
    {
        return $employees->map(fn (Employee $e) => [
            $e->employee_code ?: '—',
            $e->full_name,
            optional($e->department)->name ?? '—',
            optional($e->job_title)->name ?? '—',
            optional($e->branch)->name ?? '—',
            optional($e->direct_manager)->full_name ?? '—',
            optional($e->hire_date)?->toDateString() ?? '—',
            $this->employeeTypeLabel($e->employee_type),
            $e->employmentStatus()->label(),
        ])->all();
    }

    /**
     * مدّة الإنذار من إعدادات الرواتب — وإن غابت فالافتراض ثلاثون يوماً،
     * وهو افتراض أمرَي التنبيه (`hr:document-expiry-alerts` و
     * `contracts:check-expirations`) نفسِه.
     */
    private function noticePeriod(string $column): int
    {
        try {
            $days = (int) PayrollSettings::value($column);
        } catch (\Throwable) {
            $days = 0;
        }

        return $days > 0 ? $days : 30;
    }
}
