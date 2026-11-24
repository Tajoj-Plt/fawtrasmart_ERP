<?php

namespace Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\JournalEntryDetail;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryAdvance;
use App\Models\SalaryAdvanceRequest;
use App\Models\SalaryItem;
use App\Services\PayrollCalculator;
use App\Support\PayrollAccounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\Concerns\BuildsHrReports;
use Modules\HR\Http\Controllers\Concerns\ExportsHrReports;

/**
 * تقاريرُ الرواتب — بتصميم تقارير العملاء (rpt / ag).
 *
 * مصدرُ الأرقام كلِّها «سجلُّ صرف الرواتب» (`payroll_runs`): صرفةٌ لكلّ موظف
 * عن كلّ شهر، وبنودُها (`payroll_run_items`). فـ«ملخص الرواتب» و«حسب الشهر»
 * و«حسب القسم» و«لكل موظف» و«المدفوع وغير المدفوع» تقريرٌ واحدٌ بتجميعٍ
 * مختلف ({@see runsReport()})، و«مكونات الراتب» و«البدلات» تقريرٌ واحدٌ على
 * البنود ({@see itemsReport()}).
 *
 * قواعدُ مشتركة:
 *   • المدى على بداية فترة الراتب (`period_from`)، والموظفون من
 *     `hrReportEmployees()` (نطاقُ المستخدم + القسم/المسمى/«تابع لـ»/الموظف/الحالة)
 *     وافتراضُ حالة الموظف «الكل»: رواتبُ من انتهت خدمته جزءٌ من التكلفة.
 *   • الصرفةُ الملغاة لا تدخل في المبالغ ما لم تُطلب صراحةً من «حالة الصرف».
 *   • المرحلةُ من `PayrollRun::stage()` لا من عمود `status` وحده — فـ«المعلّقة»
 *     تنقسم: بانتظار اعتماد المسير / معتمدة قبل يوم الصرف / مستحقة / موقوفة.
 *   • `?export=rows` يُعيد كلَّ الصفوف بترتيب `frHeaders`؛ و`?export=xlsx|pdf`
 *     القديمان باقيان على البيانات نفسها ({@see respondExport()}).
 */
class PayrollReportController extends Controller
{
    use BuildsHrReports;
    use ExportsHrReports;

    /** اسمُ سطر الأساسيّ في بنود الصرفة — {@see PayrollCalculator::writeEarningItems()} */
    private const BASIC_ITEM_NAME = 'الراتب الأساسي';

    public function __construct()
    {
        /*
            تقاريرُ الرواتب كلُّها بصلاحية قسمها (`hr_reports.payroll`) لا
            بصلاحيات شاشات الرواتب: من يقرأ التقرير ليس بالضرورة من يفتح
            المسيرات. و«على أيّ موظفين» يحدّده نطاقُ الرؤية في كل استعلام.
            كشفُ الحساب يحتاج صلاحيةً محاسبيةً فوقها — حارسان متعاقبان = AND،
            لأن `permission.role:a|b` بديلٌ لا جمع.
        */
        $this->middleware('permission.role:hr_reports.payroll', ['only' => [
            'summary', 'byPeriod', 'byDepartment', 'components', 'allowances', 'netSalary',
            'disbursementStatus', 'deductions', 'payslips', 'advances', 'statement', 'reconciliation',
        ]]);
        $this->middleware('permission.role:account.statement.view', ['only' => ['statement']]);

        // التصديرُ صلاحيةٌ فوق صلاحية التقرير (`hr_reports.export`)
        $this->middleware(function ($request, $next) {
            if ($request->filled('export') && ! hasPermission('hr_reports.export')) {
                abort(403, 'ليس لديك صلاحية تصدير تقارير الموارد البشرية.');
            }

            return $next($request);
        });
    }

    /**
     * بلاطات تقارير الرواتب — مصدرٌ واحد لفهرس التقارير.
     *
     * @return array<int, array{route: string, permission: string, extra_permission?: string, icon: string, title: string, hint: string}>
     */
    public static function catalog(): array
    {
        return [
            [
                'route' => 'hr.reports.payroll.summary',
                'permission' => 'hr_reports.payroll',
                'icon' => 'layers',
                'title' => 'ملخص الرواتب',
                'hint' => 'كل راتب في صف: الأساسي والإضافات والخصم والسلف والصافي وحالة الصرف',
            ],
            [
                'route' => 'hr.reports.payroll.by_period',
                'permission' => 'hr_reports.payroll',
                'icon' => 'calendar',
                'title' => 'الرواتب حسب الشهر',
                'hint' => 'مجموع رواتب كل شهر وكم دُفع منه',
            ],
            [
                'route' => 'hr.reports.payroll.by_department',
                'permission' => 'hr_reports.payroll',
                'icon' => 'grid',
                'title' => 'الرواتب حسب القسم',
                'hint' => 'مجموع رواتب كل قسم في الفترة',
            ],
            [
                'route' => 'hr.reports.payroll.components',
                'permission' => 'hr_reports.payroll',
                'icon' => 'list',
                'title' => 'مكونات الراتب',
                'hint' => 'كل بنود الرواتب: الإضافات والخصم وأقساط السلف ومساهمة المنشأة',
            ],
            [
                'route' => 'hr.reports.payroll.allowances',
                'permission' => 'hr_reports.payroll',
                'icon' => 'plus-circle',
                'title' => 'البدلات',
                'hint' => 'البدلات والإضافات وحدها، مجمَّعة بنوع البدل',
            ],
            [
                'route' => 'hr.reports.payroll.net',
                'permission' => 'hr_reports.payroll',
                'icon' => 'dollar-sign',
                'title' => 'صافي الرواتب لكل موظف',
                'hint' => 'مجموع ما استحقه كل موظف في الفترة وصافيه وكم دُفع له',
            ],
            [
                'route' => 'hr.reports.payroll.status',
                'permission' => 'hr_reports.payroll',
                'icon' => 'check-circle',
                'title' => 'المدفوع وغير المدفوع',
                'hint' => 'الرواتب حسب حالتها: بانتظار الاعتماد، مستحقة، موقوفة، مدفوعة، ملغاة',
            ],
            [
                'route' => 'hr.reports.payroll.deductions',
                'permission' => 'hr_reports.payroll',
                'icon' => 'minus-circle',
                'title' => 'الخصميات',
                'hint' => 'سجلات الخصم: المبلغ وما خُصم منه وما بقي',
            ],
            [
                'route' => 'hr.reports.payroll.payslips',
                'permission' => 'hr_reports.payroll',
                'icon' => 'file-text',
                'title' => 'قسائم الرواتب',
                'hint' => 'قسيمة كل موظف في الفترة — للعرض والطباعة',
            ],
            [
                'route' => 'hr.reports.payroll.advances',
                'permission' => 'hr_reports.payroll',
                'icon' => 'credit-card',
                'title' => 'كشف السلف',
                'hint' => 'السلف المصروفة وما سُدِّد منها وما بقي على الموظفين',
            ],
            [
                'route' => 'hr.reports.payroll.statement',
                'permission' => 'hr_reports.payroll',
                'extra_permission' => 'account.statement.view',
                'icon' => 'book-open',
                'title' => 'كشف حساب الموظف',
                'hint' => 'حركة حسابَي مستحقات الراتب والسلف من القيود المحاسبية',
            ],
            [
                'route' => 'hr.reports.payroll.reconciliation',
                'permission' => 'hr_reports.payroll',
                'icon' => 'shuffle',
                'title' => 'مطابقة الرواتب بالمحاسبة',
                'hint' => 'الرواتب المدفوعة مقابل ما رُحِّل على حساب مصروف الرواتب',
            ],
        ];
    }

    /* ================================================================
     |  تقاريرُ الصرفات — تقريرٌ واحدٌ بخمسة تجميعات
     * ================================================================ */

    /** ملخص الرواتب — صرفةٌ لكلّ صف. */
    public function summary(Request $request)
    {
        return $this->runsReport($request, 'summary');
    }

    /** الرواتب حسب الشهر (شهر `period_from`). */
    public function byPeriod(Request $request)
    {
        return $this->runsReport($request, 'month');
    }

    /** الرواتب حسب قسم الموظف. */
    public function byDepartment(Request $request)
    {
        return $this->runsReport($request, 'department');
    }

    /** صافي الرواتب لكل موظف. */
    public function netSalary(Request $request)
    {
        return $this->runsReport($request, 'employee');
    }

    /** المدفوع وغير المدفوع — الصرفات حسب مرحلتها، والملغاة منها. */
    public function disbursementStatus(Request $request)
    {
        return $this->runsReport($request, 'stage');
    }

    /**
     * @param  'summary'|'month'|'department'|'employee'|'stage'  $group
     */
    private function runsReport(Request $request, string $group)
    {
        $meta = $this->runsMeta()[$group];

        // تقريرُ الحالات عن الملغاة أيضاً — فيفتح على «الكل» لا «دون الملغاة»
        if ($group === 'stage' && ! $request->has('status')) {
            $request->merge(['status' => 'all']);
        }

        $period = $this->hrReportPeriod($request);
        $runs = $this->runsQuery($request, $period)->orderByDesc('period_from')->get();
        $rows = $this->stageFiltered(
            $runs->map(fn (PayrollRun $run) => $this->runRow($run)),
            $request
        )->sortBy([['period_from', 'desc'], ['employee', 'asc']])->values();

        $isGrouped = $group !== 'summary';
        $view = $isGrouped && $request->input('view') !== 'details' ? 'summary' : 'details';
        $groups = $isGrouped ? $this->groupRuns($rows, $group) : collect();

        $detailHeaders = ['#', 'الموظف', 'القسم', 'الفترة', 'الأساسي', 'الاستحقاقات', 'الخصميات', 'السلف', 'الصافي', 'الحالة'];
        $groupHeaders = array_values(array_filter([
            '#', $meta['group_label'], $group === 'employee' ? 'القسم' : null, 'عدد الرواتب',
            $group === 'employee' ? null : 'الموظفون',
            'الأساسي', 'الاستحقاقات', 'الخصميات', 'السلف', 'الصافي', 'المدفوع', 'رواتب لم تُدفع بعد',
        ]));
        $headers = $view === 'summary' ? $groupHeaders : $detailHeaders;

        $m = fn ($v) => number_format((float) $v, 2);
        $exportRows = $view === 'summary'
            ? $groups->values()->map(fn ($g, $i) => array_values(array_filter([
                $i + 1, $g['label'], $group === 'employee' ? $g['department'] : null, $g['count'],
                $group === 'employee' ? null : $g['employees'],
                $m($g['basic']), $m($g['additions']), $m($g['deductions']), $m($g['advances']),
                $m($g['net']), $m($g['net_paid']), $g['unpaid_count'],
            ], fn ($v) => $v !== null)))
            : $rows->map(fn ($r, $i) => [
                $i + 1, $r['employee'], $r['department'], $r['period'], $m($r['basic']), $m($r['additions']),
                $m($r['deductions']), $m($r['advances']), $r['net_pending'] ? 'يُحتسب عند الصرف' : $m($r['net']), $r['stage_label'],
            ]);

        if ($response = $this->respondExport($request, $meta['title'], $headers, $exportRows, $meta['slug'])) {
            return $response;
        }

        return view('hr::reports.payroll.runs', [
            'group' => $group,
            'meta' => $meta,
            'reportView' => $view,
            'headers' => $headers,
            'rows' => $view === 'details' ? $this->hrPaginate($rows, $request) : null,
            'groups' => $view === 'summary' ? $this->hrPaginate($groups->values(), $request) : null,
            'totals' => $this->sumRuns($rows),
            'hrCharts' => $this->runsCharts($rows, $group),
        ] + $this->runsPageData($request, $period));
    }

    /**
     * رسما تقارير الصرفات — من صفوف التقرير نفسها (كلِّها لا صفحةٍ منها)،
     * والملغاةُ خارج المبالغ كما في البطاقات.
     *
     * @param  'summary'|'month'|'department'|'employee'|'stage'  $group
     */
    private function runsCharts(Collection $rows, string $group): array
    {
        $live = $rows->where('stage', '!=', PayrollRun::STAGE_CANCELLED);
        $unpaid = $live->where('stage', '!=', PayrollRun::STAGE_PAID);

        $bar = match ($group) {
            'month' => [
                'title' => 'صافي الرواتب لكل شهر', 'icon' => 'calendar', 'color' => '#8b5cf6',
                'items' => $this->chartSums($live, 'month', 'month_label', 'net'),
            ],
            'department' => [
                'title' => 'صافي الرواتب لكل قسم', 'icon' => 'sitemap', 'color' => '#3b82f6',
                'items' => $this->chartSums($live, 'department_id', 'department', 'net'),
            ],
            'stage' => [
                // الصرفةُ المعلّقة صافيها صفرٌ حتى يوم الصرف — فالأساسيُّ مقياسُ ما بقي
                'title' => 'الأكثر رواتب لم تُدفع (الأساسي)', 'icon' => 'hourglass-half', 'color' => '#f59e0b',
                'items' => $this->chartSums($unpaid, 'employee_id', 'employee', 'basic'),
            ],
            default => [
                'title' => 'الأعلى صافي راتب في الفترة', 'icon' => 'chart-bar', 'color' => '#8b5cf6',
                'items' => $this->chartSums($live, 'employee_id', 'employee', 'net'),
            ],
        };

        if ($group === 'department') {
            $donut = [
                'title' => 'الموظفون حسب القسم', 'unit' => 'موظف',
                'groups' => $this->chartGroups($live->groupBy('department_id')->map(fn (Collection $g) => [
                    'label' => $g->first()['department'],
                    'count' => $g->pluck('employee_id')->unique()->count(),
                    'sum' => (float) $g->sum('net'),
                ])),
            ];
        } else {
            $stageColors = [
                PayrollRun::STAGE_PAID => '#10b981', PayrollRun::STAGE_READY => '#ef4444',
                PayrollRun::STAGE_SCHEDULED => '#3b82f6', PayrollRun::STAGE_AWAITING_APPROVAL => '#f59e0b',
                PayrollRun::STAGE_HELD => '#f97316', PayrollRun::STAGE_CANCELLED => '#64748b',
            ];
            $donut = [
                'title' => 'الرواتب حسب حالة الصرف (العدد والصافي)', 'unit' => 'راتب',
                'groups' => collect(PayrollRun::stages())->map(fn ($label, $stage) => [
                    'label' => $label, 'color' => $stageColors[$stage] ?? '#64748b',
                    'count' => $rows->where('stage', $stage)->count(),
                    'sum' => (float) $rows->where('stage', $stage)->sum('net'),
                ])->values()->all(),
            ];
        }

        return [
            'bar' => $bar + ['money' => true, 'unit' => 'ر.س'],
            'donut' => $donut,
        ];
    }

    /**
     * أعمدةُ الرسم: مجموع `$value` لكلّ مفتاح — والجزئيّة تقصّها إلى عشرة.
     *
     * @return array<int, array{name: string, value: float}>
     */
    private function chartSums(Collection $rows, string $key, string $label, string $value): array
    {
        return $rows->groupBy($key)->map(fn (Collection $g) => [
            'name' => (string) $g->first()[$label],
            'value' => (float) $g->sum($value),
        ])->values()->all();
    }

    /**
     * شرائحُ الحلقة بألوانٍ متعاقبة؛ ما زاد على سبع شرائح يُجمع في «أخرى».
     *
     * @param  Collection<int|string, array{label: string, count: float|int, sum?: float}>  $groups
     * @return array<int, array<string, mixed>>
     */
    private function chartGroups(Collection $groups, string $sortBy = 'count'): array
    {
        $palette = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#f97316'];
        $sorted = $groups->filter(fn ($g) => (float) $g['count'] > 0)->sortByDesc($sortBy)->values();

        $top = $sorted->take(7)->values()->map(fn ($g, $i) => $g + ['color' => $palette[$i]]);
        $rest = $sorted->slice(7);
        if ($rest->isNotEmpty()) {
            $top->push([
                'label' => 'أخرى', 'color' => '#64748b',
                'count' => $rest->sum('count'),
                'sum' => $rest->contains(fn ($g) => isset($g['sum'])) ? (float) $rest->sum('sum') : null,
            ]);
        }

        return $top->all();
    }

    /** عنوانُ كلِّ تجميعٍ وشرحُه — ليُعرف من العنوان وحده ما يجيب عنه التقرير. */
    private function runsMeta(): array
    {
        $common = [
            '«الصافي» = الأساسي + الاستحقاقات − الخصميات − أقساط السلف، وهو ما يُدفع للموظف.',
            'الرواتب الملغاة لا تظهر ولا تدخل في المبالغ إلا إذا اخترتها من «حالة الصرف».',
            'المدى على شهر الراتب (بداية فترته)، لا على يوم الدفع.',
            'الراتب الذي لم يُدفع بعد أرقامه مبدئية: الإضافات والخصم والسلف والصافي تُحتسب نهائياً يوم الصرف، فقد يظهر صافيه «يُحتسب عند الصرف».',
        ];

        return [
            'summary' => [
                'route' => 'hr.reports.payroll.summary', 'slug' => 'payroll_summary', 'icon' => 'layers',
                'title' => 'ملخص الرواتب',
                'subtitle' => 'كل راتب في صف: الموظف والشهر والأساسي والإضافات والخصم وقسط السلفة والصافي وحالة الصرف',
                'lead' => 'من «سجل صرف الرواتب»: لكل موظف راتبٌ واحدٌ عن كل شهر.',
                'blocks' => $common,
                'group_label' => null, 'unit' => 'راتب',
            ],
            'month' => [
                'route' => 'hr.reports.payroll.by_period', 'slug' => 'payroll_by_period', 'icon' => 'calendar',
                'title' => 'الرواتب حسب الشهر',
                'subtitle' => 'مجموع رواتب كل شهر: عدد الموظفين والأساسي والإضافات والخصم والسلف والصافي، وكم دُفع منه',
                'lead' => 'يجمع رواتب كل شهر في صف. اضغط الشهر لترى رواتبه موظفاً موظفاً.',
                'blocks' => $common,
                'group_label' => 'الشهر', 'unit' => 'شهر',
            ],
            'department' => [
                'route' => 'hr.reports.payroll.by_department', 'slug' => 'payroll_by_department', 'icon' => 'grid',
                'title' => 'الرواتب حسب القسم',
                'subtitle' => 'مجموع رواتب كل قسم في الفترة: عدد الموظفين والأساسي والإضافات والخصم والسلف والصافي',
                'lead' => 'يجمع الرواتب بقسم الموظف الحالي. اضغط القسم لترى رواتب موظفيه.',
                'blocks' => $common,
                'group_label' => 'القسم', 'unit' => 'قسم',
            ],
            'employee' => [
                'route' => 'hr.reports.payroll.net', 'slug' => 'payroll_net', 'icon' => 'dollar-sign',
                'title' => 'صافي الرواتب لكل موظف',
                'subtitle' => 'لكل موظف: مجموع ما استحقه في الفترة وما خُصم منه وصافيه، وكم دُفع له وكم بقي',
                'lead' => 'يجمع رواتب كل موظف في الفترة في صف. اضغط الاسم لترى رواتبه شهراً شهراً.',
                'blocks' => $common,
                'group_label' => 'الموظف', 'unit' => 'موظف',
            ],
            'stage' => [
                'route' => 'hr.reports.payroll.status', 'slug' => 'payroll_status', 'icon' => 'check-circle',
                'title' => 'المدفوع وغير المدفوع',
                'subtitle' => 'الرواتب حسب حالتها: بانتظار اعتماد المسير، معتمدة قبل يوم الصرف، مستحقة الصرف، موقوفة، مدفوعة، ملغاة',
                'lead' => 'الراتب «غير المدفوع» ثلاثة أحوال: لم يُعتمد مسيره بعد، أو اعتُمد وينتظر يوم الصرف، أو حلّ يومه ولم يُدفع.',
                'blocks' => [
                    '«مدفوعة» = خرج المبلغ وسُجّل قيده. «موقوفة» = أُوقف صرفها بقرار حتى يُرفع الإيقاف.',
                    'هذا التقرير يعرض الملغاة أيضاً في صفها، لكنها لا تدخل في بطاقات المبالغ ولا في سطر الإجمالي.',
                    'اضغط الحالة لترى رواتبها موظفاً موظفاً.',
                ],
                'group_label' => 'حالة الصرف', 'unit' => 'حالة',
            ],
        ];
    }

    /** @return Collection<int|string, array<string, mixed>> */
    private function groupRuns(Collection $rows, string $group): Collection
    {
        if ($group === 'stage') {
            // كلُّ المراحل ولو فارغة — ليظهر أنّ «الموقوفة» صفر لا أنها منسيّة
            return collect(PayrollRun::stages())->map(fn ($label, $stage) => $this->sumRuns(
                $rows->where('stage', $stage), false
            ) + [
                'key' => $stage, 'label' => $label, 'tone' => $this->stageTone($stage),
                'link' => ['status' => $stage],
            ])->values();
        }

        $keyBy = match ($group) {
            'month' => 'month',
            'department' => 'department_id',
            default => 'employee_id',
        };

        $groups = $rows->groupBy($keyBy)->map(function (Collection $items, $key) use ($group) {
            $first = $items->first();
            $base = $this->sumRuns($items, false) + ['key' => $key, 'department' => $first['department']];

            return $base + match ($group) {
                'month' => [
                    'label' => $first['month_label'],
                    'link' => [
                        'from_date' => Carbon::parse($key.'-01')->toDateString(),
                        'to_date' => Carbon::parse($key.'-01')->endOfMonth()->toDateString(),
                        'accounting_period_id' => null, 'fiscal_year_id' => null,
                    ],
                ],
                'department' => [
                    'label' => $first['department'],
                    'link' => $key ? ['department_id' => $key] : null,
                ],
                default => [
                    'label' => $first['employee'],
                    'link' => ['employee_id' => $key],
                ],
            };
        });

        return match ($group) {
            'month' => $groups->sortKeysDesc()->values(),
            default => $groups->sortBy('label')->values(),
        };
    }

    /**
     * مجاميعُ صفوف الصرفات. `$skipCancelled` للبطاقات وسطر الإجمالي:
     * الملغاةُ لا مالَ فيها — وتُعدّ وحدها في `cancelled`.
     *
     * @return array<string, float|int>
     */
    private function sumRuns(Collection $rows, bool $skipCancelled = true): array
    {
        $live = $skipCancelled ? $rows->where('stage', '!=', PayrollRun::STAGE_CANCELLED) : $rows;

        return [
            'count' => $live->count(),
            'employees' => $live->pluck('employee_id')->unique()->count(),
            'basic' => (float) $live->sum('basic'),
            'additions' => (float) $live->sum('additions'),
            'deductions' => (float) $live->sum('deductions'),
            'advances' => (float) $live->sum('advances'),
            'net' => (float) $live->sum('net'),
            'net_paid' => (float) $live->where('stage', PayrollRun::STAGE_PAID)->sum('net'),
            'net_unpaid' => (float) $live->whereNotIn('stage', [PayrollRun::STAGE_PAID, PayrollRun::STAGE_CANCELLED])->sum('net'),
            'basic_unpaid' => (float) $live->whereNotIn('stage', [PayrollRun::STAGE_PAID, PayrollRun::STAGE_CANCELLED])->sum('basic'),
            'unpaid_count' => $live->whereNotIn('stage', [PayrollRun::STAGE_PAID, PayrollRun::STAGE_CANCELLED])->count(),
            'cancelled' => $rows->where('stage', PayrollRun::STAGE_CANCELLED)->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function runRow(PayrollRun $run): array
    {
        $stage = $run->stage();
        $employee = $run->employee;

        return [
            'id' => $run->id,
            'employee_id' => (int) $run->employee_id,
            'employee' => $employee?->full_name ?? '#'.$run->employee_id,
            'department_id' => (int) ($employee?->department_id ?? 0),
            'department' => $employee?->department?->name ?? 'بدون قسم',
            'period' => $run->period_label,
            'period_from' => $run->period_from?->toDateString(),
            'month' => $run->period_from?->format('Y-m') ?? '—',
            'month_label' => $run->period_from ? $run->period_from->copy()->locale('ar')->translatedFormat('F Y') : '—',
            'basic' => (float) $run->basic_amount,
            'additions' => (float) $run->total_additions,
            'deductions' => (float) $run->total_deductions,
            'advances' => (float) $run->total_advances_deducted,
            'net' => (float) $run->net_salary,
            // الصرفةُ المعلّقة لا يُحتسب صافيها إلا يوم الصرف (`PayrollDisbursementService`)
            'net_pending' => ! in_array($stage, [PayrollRun::STAGE_PAID, PayrollRun::STAGE_CANCELLED], true) && (float) $run->net_salary <= 0,
            'stage' => $stage,
            'stage_label' => PayrollRun::stages()[$stage] ?? $run->status_label,
            'tone' => $this->stageTone($stage),
        ];
    }

    private function stageTone(string $stage): string
    {
        return match ($stage) {
            PayrollRun::STAGE_PAID => 'rpt-pill-success',
            PayrollRun::STAGE_HELD => 'rpt-pill-danger',
            PayrollRun::STAGE_READY, PayrollRun::STAGE_AWAITING_APPROVAL => 'rpt-pill-warning',
            default => 'rpt-pill-secondary',
        };
    }

    /* ================================================================
     |  البنود — مكونات الراتب والبدلات
     * ================================================================ */

    /** مكونات الراتب — كلُّ بنود الصرفات. */
    public function components(Request $request)
    {
        return $this->itemsReport($request, false);
    }

    /**
     * البدلات — بنودُ الاستحقاق وحدها.
     *
     * التجميع باسم نوع الكتالوج إن رُبط البند عبر `salary_items.component_type_id`،
     * وإلا باسم البند كما صُرف. علم التأمينات على النوع لا يغيّر المبلغ هنا.
     */
    public function allowances(Request $request)
    {
        return $this->itemsReport($request, true);
    }

    private function itemsReport(Request $request, bool $allowancesOnly)
    {
        $period = $this->hrReportPeriod($request);
        $typeLabels = $this->itemTypeLabels();

        $query = PayrollRunItem::query()
            ->with(['run.employee.department:id,name', 'run.slip.payroll'])
            ->whereHas('run', function (Builder $run) use ($request, $period) {
                $run->whereIn('employee_id', $this->employeeIdsQuery($request))
                    ->whereBetween('period_from', [$period['from']->toDateString(), $period['to']->toDateString()]);
                $this->applyRunStatus($run, $request);
            });

        /*
            سطرُ «الراتب الأساسي» يُكتب بندَ استحقاقٍ بلا مصدر
            (`PayrollCalculator::writeEarningItems`) — فكان يُعدّ «بدلاً» ويبتلع
            البدلات كلَّها. هنا نوعٌ مستقلّ (`basic`)، والبدلاتُ دونه.
        */
        $isBasic = fn (Builder $q) => $q->where('item_type', PayrollRunItem::TYPE_ADDITION)
            ->whereNull('source_table')->where('name', self::BASIC_ITEM_NAME);

        $type = $allowancesOnly ? PayrollRunItem::TYPE_ADDITION : (string) $request->input('item_type', '');
        if ($type === 'basic') {
            $query->where($isBasic);
        } elseif ($type !== '') {
            $query->where('item_type', $type);
            if ($type === PayrollRunItem::TYPE_ADDITION) {
                $query->whereNot($isBasic);
            }
        }
        if ($request->filled('item_name')) {
            $query->where('name', 'like', '%'.$request->input('item_name').'%');
        }

        $items = $query->get();
        $catalogNames = $allowancesOnly ? $this->catalogNamesForItems($items) : [];
        $typeOrder = array_flip(array_keys($typeLabels));

        $rows = $items->filter(fn (PayrollRunItem $item) => $item->run !== null)->map(function (PayrollRunItem $item) use ($catalogNames, $typeLabels, $typeOrder) {
            $run = $item->run;
            $stage = $run->stage();
            $type = $item->item_type === PayrollRunItem::TYPE_ADDITION && $item->source_table === null && $item->name === self::BASIC_ITEM_NAME
                ? 'basic' : $item->item_type;

            return [
                'id' => $item->id,
                'run_id' => $run->id,
                'employee_id' => (int) $run->employee_id,
                'employee' => $run->employee?->full_name ?? '#'.$run->employee_id,
                'department' => $run->employee?->department?->name ?? 'بدون قسم',
                'period' => $run->period_label,
                'period_from' => $run->period_from?->toDateString(),
                'type' => $type,
                'type_order' => $typeOrder[$type] ?? 99,
                'type_label' => $typeLabels[$type] ?? $item->type_label,
                'name' => $catalogNames[$item->id] ?? ($item->name ?: 'بدون اسم'),
                'raw_name' => $item->name,
                'amount' => (float) $item->amount,
                'stage' => $stage,
                'stage_label' => PayrollRun::stages()[$stage] ?? $run->status_label,
                'tone' => $this->stageTone($stage),
            ];
        });
        $rows = $this->stageFiltered($rows, $request)
            ->sortBy([['period_from', 'desc'], ['employee', 'asc'], ['type_order', 'asc'], ['name', 'asc']])
            ->values();

        // أقساطُ السلف اسمُها «تسوية سلفة #رقم» — سطرٌ لكلّ سلفة لا يُقرأ؛ فتُجمع في سطرٍ واحد
        $oneLine = fn ($r) => $r['type'] === PayrollRunItem::TYPE_ADVANCE;
        $groups = $rows
            ->groupBy(fn ($r) => $allowancesOnly ? $r['name'] : $r['type'].'|'.($oneLine($r) ? '' : $r['name']))
            ->map(fn (Collection $g) => [
                'type' => $g->first()['type'],
                'type_order' => $g->first()['type_order'],
                'type_label' => $g->first()['type_label'],
                'name' => $oneLine($g->first()) ? 'أقساط سداد السلف (كل السلف)' : $g->first()['name'],
                'raw_name' => $oneLine($g->first()) ? null : $g->first()['raw_name'],
                'employees' => $g->pluck('employee_id')->unique()->count(),
                'count' => $g->count(),
                'amount' => (float) $g->sum('amount'),
            ])
            ->sortBy($allowancesOnly ? [['amount', 'desc']] : [['type_order', 'asc'], ['name', 'asc']])
            ->values();

        $view = $request->input('view') === 'details' ? 'details' : 'summary';
        $total = (float) $rows->sum('amount');
        $m = fn ($v) => number_format((float) $v, 2);

        $headers = $view === 'summary'
            ? ($allowancesOnly
                ? ['#', 'البدل', 'الموظفون', 'عدد المرات', 'المبلغ', 'النسبة من الإجمالي']
                : ['#', 'النوع', 'البند', 'الموظفون', 'عدد المرات', 'المبلغ'])
            : ['#', 'الموظف', 'القسم', 'الفترة', 'النوع', 'البند', 'المبلغ', 'حالة الصرف'];

        $exportRows = $view === 'summary'
            ? $groups->map(fn ($g, $i) => $allowancesOnly
                ? [$i + 1, $g['name'], $g['employees'], $g['count'], $m($g['amount']), $total > 0 ? round($g['amount'] / $total * 100, 1).'%' : '—']
                : [$i + 1, $g['type_label'], $g['name'], $g['employees'], $g['count'], $m($g['amount'])])
            : $rows->map(fn ($r, $i) => [
                $i + 1, $r['employee'], $r['department'], $r['period'], $r['type_label'], $r['name'], $m($r['amount']), $r['stage_label'],
            ]);

        $title = $allowancesOnly ? 'البدلات' : 'مكونات الراتب';
        if ($response = $this->respondExport($request, $title, $headers, $exportRows, $allowancesOnly ? 'payroll_allowances' : 'payroll_components')) {
            return $response;
        }

        $byType = $rows->groupBy('type');

        return view('hr::reports.payroll.items', [
            'allowancesOnly' => $allowancesOnly,
            'reportView' => $view,
            'headers' => $headers,
            'rows' => $view === 'details' ? $this->hrPaginate($rows, $request) : null,
            'groups' => $view === 'summary' ? $this->hrPaginate($groups, $request) : null,
            'typeLabels' => $typeLabels,
            'totals' => [
                'amount' => $total,
                'lines' => $rows->count(),
                'employees' => $rows->pluck('employee_id')->unique()->count(),
                'kinds' => $groups->count(),
                'top' => $groups->sortByDesc('amount')->first(),
                'by_type' => $byType->map(fn ($g) => ['amount' => (float) $g->sum('amount'), 'count' => $g->count()])->all(),
            ],
            'hrCharts' => $allowancesOnly
                ? [
                    'bar' => [
                        'title' => 'أعلى البدلات مبلغاً', 'icon' => 'plus-circle', 'color' => '#10b981', 'money' => true, 'unit' => 'ر.س',
                        'items' => $groups->map(fn ($g) => ['name' => $g['name'], 'value' => $g['amount']])->all(),
                    ],
                    'donut' => [
                        'title' => 'البدلات حسب القسم (المبلغ)', 'unit' => 'ر.س', 'money' => true,
                        'groups' => $this->chartGroups($rows->groupBy('department')->map(fn (Collection $g, $dept) => [
                            'label' => (string) $dept, 'count' => (float) $g->sum('amount'),
                        ])),
                    ],
                ]
                : [
                    'bar' => [
                        'title' => 'أكبر البنود مبلغاً', 'icon' => 'list', 'color' => '#3b82f6', 'money' => true, 'unit' => 'ر.س',
                        'items' => $groups->map(fn ($g) => ['name' => $g['name'], 'value' => $g['amount']])->all(),
                    ],
                    'donut' => [
                        'title' => 'البنود حسب النوع (المبلغ)', 'unit' => 'ر.س', 'money' => true,
                        'groups' => collect($typeLabels)->map(fn ($label, $type) => [
                            'label' => $label,
                            'color' => match ($type) {
                                'basic' => '#3b82f6', PayrollRunItem::TYPE_ADDITION => '#10b981', PayrollRunItem::TYPE_RETRO => '#06b6d4',
                                PayrollRunItem::TYPE_DEDUCTION => '#ef4444', PayrollRunItem::TYPE_ADVANCE => '#f59e0b', default => '#64748b',
                            },
                            'count' => (float) ($byType->get($type)?->sum('amount') ?? 0),
                        ])->values()->all(),
                    ],
                ],
        ] + $this->runsPageData($request, $period));
    }

    /** @return array<string, string> بترتيب العرض */
    private function itemTypeLabels(): array
    {
        return [
            'basic' => 'الراتب الأساسي',
            PayrollRunItem::TYPE_ADDITION => 'بدل / إضافة',
            PayrollRunItem::TYPE_RETRO => 'فروقات بأثر رجعي',
            PayrollRunItem::TYPE_DEDUCTION => 'خصم',
            PayrollRunItem::TYPE_ADVANCE => 'قسط سلفة',
            PayrollRunItem::TYPE_EMPLOYER => 'مساهمة المنشأة (لا تُخصم من الراتب)',
        ];
    }

    /* ================================================================
     |  الخصميات
     * ================================================================ */

    /**
     * الخصميات — سجلُّ الخصم (`deductions`) لا بندُ الصرف.
     *
     * المتبقّي من `Deduction::remaining_amount`. والملغاةُ والمرفوضة خارج
     * الصفوف والمبالغ إلا إذا طُلبت. و«المتبقي للخصم» للمعتمدة والمسوّاة
     * جزئياً وحدها؛ المعلّقةُ لم تُعتمد بعد فتُعرض وحدها.
     */
    public function deductions(Request $request)
    {
        $period = $this->hrReportPeriod($request);
        $void = [Deduction::STATUS_CANCELLED, Deduction::STATUS_REJECTED];
        $owing = [Deduction::STATUS_APPROVED, Deduction::STATUS_PARTIALLY_SETTLED];

        $query = Deduction::query()
            ->with(['employee.department:id,name'])
            ->whereIn('employee_id', $this->employeeIdsQuery($request))
            ->whereBetween('date', [$period['from']->toDateString(), $period['to']->toDateString()]);

        if ($request->filled('deduction_type')) {
            $query->where('deduction_type', $request->input('deduction_type'));
        }

        $status = (string) $request->input('status', '');
        if ($status === '') {
            $query->whereNotIn('status', $void);
        } elseif ($status === 'void') {
            $query->whereIn('status', $void);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $tones = [
            Deduction::STATUS_PENDING => 'rpt-pill-warning',
            Deduction::STATUS_APPROVED => 'rpt-pill-danger',
            Deduction::STATUS_PARTIALLY_SETTLED => 'rpt-pill-warning',
            Deduction::STATUS_SETTLED => 'rpt-pill-success',
        ];

        $rows = $query->orderByDesc('date')->orderByDesc('id')->get()->map(function (Deduction $d) use ($void, $owing, $tones) {
            $isVoid = in_array($d->status, $void, true);

            return [
                'id' => $d->id,
                'date' => $d->date?->toDateString() ?? '—',
                'employee_id' => (int) $d->employee_id,
                'employee' => $d->employee?->full_name ?? '#'.$d->employee_id,
                'department' => $d->employee?->department?->name ?? 'بدون قسم',
                'type_label' => $d->type_label,
                'status' => $d->status,
                'status_label' => $d->status_label,
                'tone' => $tones[$d->status] ?? 'rpt-pill-secondary',
                'void' => $isVoid,
                'amount' => $isVoid ? 0.0 : (float) $d->amount,
                'shown_amount' => (float) $d->amount,
                'settled' => $isVoid ? 0.0 : (float) $d->settled_amount,
                'remaining' => in_array($d->status, $owing, true) ? (float) $d->remaining_amount : 0.0,
                'pending' => $d->status === Deduction::STATUS_PENDING ? (float) $d->remaining_amount : 0.0,
            ];
        });

        $view = $request->input('view') === 'summary' ? 'summary' : 'details';
        $groups = $rows->groupBy('employee_id')->map(fn (Collection $g) => [
            'employee_id' => $g->first()['employee_id'],
            'employee' => $g->first()['employee'],
            'department' => $g->first()['department'],
            'count' => $g->count(),
            'amount' => (float) $g->sum('amount'),
            'settled' => (float) $g->sum('settled'),
            'remaining' => (float) $g->sum('remaining'),
            'pending' => (float) $g->sum('pending'),
        ])->sortBy('employee')->values();

        $m = fn ($v) => number_format((float) $v, 2);
        $headers = $view === 'summary'
            ? ['#', 'الموظف', 'القسم', 'عدد الخصميات', 'المبلغ', 'خُصم فعلاً', 'باقٍ للخصم', 'بانتظار الاعتماد']
            : ['#', 'التاريخ', 'الموظف', 'القسم', 'النوع', 'المبلغ', 'خُصم فعلاً', 'باقٍ للخصم', 'الحالة'];
        $exportRows = $view === 'summary'
            ? $groups->map(fn ($g, $i) => [$i + 1, $g['employee'], $g['department'], $g['count'], $m($g['amount']), $m($g['settled']), $m($g['remaining']), $m($g['pending'])])
            : $rows->values()->map(fn ($r, $i) => [
                $i + 1, $r['date'], $r['employee'], $r['department'], $r['type_label'], $m($r['shown_amount']),
                $m($r['settled']), $m($r['remaining']), $r['status_label'],
            ]);

        if ($response = $this->respondExport($request, 'الخصميات', $headers, $exportRows, 'payroll_deductions')) {
            return $response;
        }

        $voidCount = $status === '' ? Deduction::query()
            ->whereIn('employee_id', $this->employeeIdsQuery($request))
            ->whereBetween('date', [$period['from']->toDateString(), $period['to']->toDateString()])
            ->when($request->filled('deduction_type'), fn ($q) => $q->where('deduction_type', $request->input('deduction_type')))
            ->whereIn('status', $void)
            ->count() : null;

        return view('hr::reports.payroll.deductions', [
            'reportView' => $view,
            'headers' => $headers,
            'rows' => $view === 'details' ? $this->hrPaginate($rows->values(), $request) : null,
            'groups' => $view === 'summary' ? $this->hrPaginate($groups, $request) : null,
            'totals' => [
                'count' => $rows->where('void', false)->count(),
                'amount' => (float) $rows->sum('amount'),
                'settled' => (float) $rows->sum('settled'),
                'remaining' => (float) $rows->sum('remaining'),
                'pending' => (float) $rows->sum('pending'),
                'pending_count' => $rows->where('status', Deduction::STATUS_PENDING)->count(),
                'void_shown' => $rows->where('void', true)->count(),
                'void_hidden' => $voidCount,
            ],
            'hrCharts' => [
                'bar' => [
                    'title' => 'الأكثر خصماً', 'icon' => 'minus-circle', 'color' => '#ef4444', 'money' => true, 'unit' => 'ر.س',
                    'items' => $this->chartSums($rows, 'employee_id', 'employee', 'amount'),
                ],
                'donut' => [
                    'title' => 'الخصميات حسب النوع (العدد والمبلغ)', 'unit' => 'خصم',
                    'groups' => $this->chartGroups($rows->where('void', false)->groupBy('type_label')->map(fn (Collection $g, $label) => [
                        'label' => (string) $label, 'count' => $g->count(), 'sum' => (float) $g->sum('amount'),
                    ])),
                ],
            ],
            'deductionTypes' => Deduction::types(),
            'statusOptions' => ['' => 'السارية (دون الملغاة والمرفوضة)'] + Deduction::statuses()
                + ['void' => 'الملغاة والمرفوضة معاً', 'all' => 'الكل'],
        ] + $this->runsPageData($request, $period));
    }

    /* ================================================================
     |  قسائم الرواتب
     * ================================================================ */

    /** قسائم الرواتب للعرض والطباعة الجماعية — بلا تقسيم صفحات لتُطبع كلُّها. */
    public function payslips(Request $request)
    {
        $period = $this->hrReportPeriod($request, true);

        $runs = $this->runsQuery($request, $period)
            ->with(['items', 'employee.branch:id,name', 'contract'])
            ->orderBy('period_from')
            ->get();

        $wanted = $this->stageWanted($request);
        if ($wanted) {
            $runs = $runs->filter(fn (PayrollRun $run) => $run->stage() === $wanted)->values();
        }
        $runs = $runs->sortBy([
            fn ($a, $b) => strcmp((string) $a->employee?->full_name, (string) $b->employee?->full_name),
            fn ($a, $b) => strcmp((string) $a->period_from, (string) $b->period_from),
        ])->values();

        $rows = $runs->map(fn (PayrollRun $run) => $this->runRow($run));
        $headers = ['#', 'الموظف', 'القسم', 'الفترة', 'الأساسي', 'الاستحقاقات', 'الخصميات', 'السلف', 'الصافي', 'الحالة'];
        $m = fn ($v) => number_format((float) $v, 2);
        $exportRows = $rows->map(fn ($r, $i) => [
            $i + 1, $r['employee'], $r['department'], $r['period'], $m($r['basic']), $m($r['additions']),
            $m($r['deductions']), $m($r['advances']), $r['net_pending'] ? 'يُحتسب عند الصرف' : $m($r['net']), $r['stage_label'],
        ]);

        if ($response = $this->respondExport($request, 'قسائم الرواتب', $headers, $exportRows, 'payroll_payslips')) {
            return $response;
        }

        return view('hr::reports.payroll.payslips', [
            'runs' => $runs,
            'headers' => $headers,
            'totals' => $this->sumRuns($rows),
        ] + $this->runsPageData($request, $period));
    }

    /* ================================================================
     |  كشف السلف
     * ================================================================ */

    /**
     * كشف السلف — النظامان القائمَان: `salary_advance_requests` (الطلبات —
     * مسارُ الصرف الحالي) و`salary_advances` (السلف بأقساط — النظام القديم).
     *
     * ما كان خطأً وصُحّح:
     *   • الطلباتُ المرفوضة والملغاة كانت تُعدّ «متبقية» على الموظف. الآن
     *     المرحلةُ من `SalaryAdvanceRequest::stage()`: المرفوضة/الملغاة خارج
     *     المبالغ (تُعرض وحدها إن طُلبت)، والمعلّقة والمعتمدة غيرُ المصروفة
     *     «بانتظار الاعتماد أو الصرف» — لم يخرج مالها فليست ديناً على أحد.
     *   • «المسدَّد» كان `settled_amount` وحده فيُهمل أقساط الاقتطاع من الراتب؛
     *     الآن `repaidAmount()` و`remainingAmount()`.
     *   • السلفُ القديمة كانت تجمع كلَّ الأقساط مدفوعةً أو لا، ولا تستثني
     *     الملغاة؛ الآن المسدَّد = الأقساط `paid` وحدها، والملغاةُ خارج المبالغ.
     */
    public function advances(Request $request)
    {
        $period = $this->hrReportPeriod($request);
        [$from, $to] = [$period['from']->toDateString(), $period['to']->toDateString()];

        $legacy = SalaryAdvance::query()
            ->with(['employee.department:id,name', 'payments'])
            ->whereIn('employee_id', $this->employeeIdsQuery($request))
            ->whereBetween('submission_date', [$from, $to])
            ->get();

        $requests = SalaryAdvanceRequest::query()
            ->with(['employee.department:id,name'])
            ->whereIn('employee_id', $this->employeeIdsQuery($request))
            ->whereBetween('requested_date', [$from, $to])
            ->get();

        $stateTones = ['waiting' => 'rpt-pill-warning', 'outstanding' => 'rpt-pill-danger', 'settled' => 'rpt-pill-success', 'void' => 'rpt-pill-secondary'];
        $rows = collect();

        foreach ($requests as $advance) {
            $stage = $advance->stage();
            $state = match ($stage) {
                'pending', 'approved' => 'waiting',
                'disbursed', 'repaying' => 'outstanding',
                'settled' => 'settled',
                default => 'void',
            };
            $out = in_array($state, ['outstanding', 'settled'], true);

            $rows->push($this->advanceRow($advance->employee, (int) $advance->employee_id, [
                'id' => $advance->id,
                'kind' => 'request',
                'source' => 'طلب سلفة',
                'date' => $advance->requested_date?->toDateString(),
                'amount' => (float) $advance->amount,
                'disbursed' => $out ? (float) $advance->amount : 0.0,
                'paid' => $out ? $advance->repaidAmount() : 0.0,
                'remaining' => $state === 'outstanding' ? $advance->remainingAmount() : 0.0,
                'waiting' => $state === 'waiting' ? (float) $advance->amount : 0.0,
                'state' => $state,
                'state_label' => $advance->stageLabel(),
                'tone' => $stateTones[$state],
            ]));
        }

        foreach ($legacy as $advance) {
            $isVoid = $advance->status === 'cancelled';
            $paid = $isVoid ? 0.0 : round((float) $advance->payments->where('status', 'paid')->sum('amount'), 2);
            $remaining = $isVoid ? 0.0 : round(max(0, (float) $advance->amount - $paid), 2);
            $state = $isVoid ? 'void' : (($advance->status === 'completed' || $remaining <= 0.001) ? 'settled' : 'outstanding');

            $rows->push($this->advanceRow($advance->employee, (int) $advance->employee_id, [
                'id' => $advance->id,
                'kind' => 'legacy',
                'source' => 'سلفة بأقساط (النظام السابق)',
                'date' => $advance->submission_date?->toDateString(),
                'amount' => (float) $advance->amount,
                'disbursed' => $isVoid ? 0.0 : (float) $advance->amount,
                'paid' => $paid,
                'remaining' => $remaining,
                'waiting' => 0.0,
                'state' => $state,
                'state_label' => match ($state) {
                    'void' => 'ملغاة',
                    'settled' => 'مسدَّدة بالكامل',
                    default => $paid > 0 ? 'قيد السداد' : 'مصروفة — في دورة السداد',
                },
                'tone' => $stateTones[$state],
            ]));
        }

        // «pending» القديمة = بانتظار الاعتماد أو الصرف
        $wanted = (string) $request->input('advance_state', '');
        $wanted = $wanted === 'pending' ? 'waiting' : $wanted;
        $voidHidden = $wanted === '' ? $rows->where('state', 'void')->count() : null;
        if ($wanted === '') {
            $rows = $rows->where('state', '!=', 'void');
        } elseif ($wanted !== 'all') {
            $rows = $rows->where('state', $wanted);
        }
        $rows = $rows->sortBy([['date', 'desc'], ['employee', 'asc']])->values();

        $view = $request->input('view') === 'summary' ? 'summary' : 'details';
        $groups = $rows->groupBy('employee_id')->map(fn (Collection $g) => [
            'employee_id' => $g->first()['employee_id'],
            'employee' => $g->first()['employee'],
            'department' => $g->first()['department'],
            'count' => $g->count(),
            'disbursed' => (float) $g->sum('disbursed'),
            'paid' => (float) $g->sum('paid'),
            'remaining' => (float) $g->sum('remaining'),
            'waiting' => (float) $g->sum('waiting'),
        ])->sortBy('employee')->values();

        $m = fn ($v) => number_format((float) $v, 2);
        $headers = $view === 'summary'
            ? ['#', 'الموظف', 'القسم', 'عدد السلف', 'المصروف', 'المسدَّد', 'المتبقي', 'بانتظار الاعتماد أو الصرف']
            : ['#', 'التاريخ', 'الموظف', 'القسم', 'النوع', 'المبلغ', 'المسدَّد', 'المتبقي', 'الحالة'];
        $exportRows = $view === 'summary'
            ? $groups->map(fn ($g, $i) => [$i + 1, $g['employee'], $g['department'], $g['count'], $m($g['disbursed']), $m($g['paid']), $m($g['remaining']), $m($g['waiting'])])
            : $rows->map(fn ($r, $i) => [$i + 1, $r['date'] ?? '—', $r['employee'], $r['department'], $r['source'], $m($r['amount']), $m($r['paid']), $m($r['remaining']), $r['state_label']]);

        if ($response = $this->respondExport($request, 'كشف السلف', $headers, $exportRows, 'payroll_advances')) {
            return $response;
        }

        return view('hr::reports.payroll.advances', [
            'reportView' => $view,
            'headers' => $headers,
            'rows' => $view === 'details' ? $this->hrPaginate($rows, $request) : null,
            'groups' => $view === 'summary' ? $this->hrPaginate($groups, $request) : null,
            'totals' => [
                'count' => $rows->count(),
                'disbursed' => (float) $rows->sum('disbursed'),
                'disbursed_count' => $rows->whereIn('state', ['outstanding', 'settled'])->count(),
                'paid' => (float) $rows->sum('paid'),
                'remaining' => (float) $rows->sum('remaining'),
                'outstanding_count' => $rows->where('state', 'outstanding')->count(),
                'waiting' => (float) $rows->sum('waiting'),
                'waiting_count' => $rows->where('state', 'waiting')->count(),
                'void_hidden' => $voidHidden,
            ],
            'stateOptions' => [
                '' => 'كل السلف (دون المرفوضة والملغاة)',
                'waiting' => 'بانتظار الاعتماد أو الصرف',
                'outstanding' => 'مصروفة ولم تُسدَّد بالكامل',
                'settled' => 'مسدَّدة بالكامل',
                'void' => 'المرفوضة والملغاة',
                'all' => 'الكل',
            ],
            'currentState' => $wanted,
            'hrCharts' => [
                'bar' => [
                    'title' => 'الأعلى سلفاً متبقية', 'icon' => 'credit-card', 'color' => '#ef4444', 'money' => true, 'unit' => 'ر.س',
                    'items' => $this->chartSums($rows, 'employee_id', 'employee', 'remaining'),
                ],
                'donut' => [
                    'title' => 'السلف حسب الحالة (العدد والمبلغ)', 'unit' => 'سلفة',
                    'groups' => collect([
                        'waiting' => ['بانتظار الاعتماد أو الصرف', '#f59e0b'],
                        'outstanding' => ['مصروفة ولم تُسدَّد بالكامل', '#ef4444'],
                        'settled' => ['مسدَّدة بالكامل', '#10b981'],
                        'void' => ['المرفوضة والملغاة', '#64748b'],
                    ])->map(fn ($meta, $state) => [
                        'label' => $meta[0], 'color' => $meta[1],
                        'count' => $rows->where('state', $state)->count(),
                        'sum' => (float) $rows->where('state', $state)->sum('amount'),
                    ])->values()->all(),
                ],
            ],
        ] + $this->runsPageData($request, $period));
    }

    /** @return array<string, mixed> */
    private function advanceRow($employee, int $employeeId, array $data): array
    {
        return [
            'employee_id' => $employeeId,
            'employee' => $employee?->full_name ?? '#'.$employeeId,
            'department' => $employee?->department?->name ?? 'بدون قسم',
        ] + $data;
    }

    /* ================================================================
     |  كشف حساب الموظف
     * ================================================================ */

    /**
     * كشف حساب الموظف — حركة حسابَي المستحقات والسلف (§11.3).
     *
     * المصدر `journal_entry_details` على الحسابين اللذين ينشئهما
     * `syncEmployeeLiabilityAccounts`. الخصمية لا قيداً مستقلاً لها:
     * تُنقص مبلغ الاستحقاق قبل الترحيل، فتظهر في بطاقات الصرف لا في السطر.
     *
     * بلا موظفٍ محدَّد: صفٌّ لكلّ موظف (افتتاحي/مدين/دائن/ختامي للحسابين).
     * وبموظفٍ محدَّد: حركتُه سطراً سطراً برصيدٍ جارٍ.
     */
    public function statement(Request $request)
    {
        $period = $this->hrReportPeriod($request);
        [$from, $to] = [$period['from']->toDateString(), $period['to']->toDateString()];

        if ($request->filled('employee_id')) {
            $this->authorizeEmployeeScope($request->input('employee_id'));
        }

        $employees = $this->hrReportEmployees($request, false)
            ->with(['department:id,name'])
            ->orderBy('first_name')
            ->get();

        $accountMap = [];
        foreach ($employees as $employee) {
            if ($employee->salary_payable_account_id) {
                $accountMap[(int) $employee->salary_payable_account_id] = ['employee_id' => (int) $employee->id, 'kind' => 'payable'];
            }
            if ($employee->employee_advance_account_id) {
                $accountMap[(int) $employee->employee_advance_account_id] = ['employee_id' => (int) $employee->id, 'kind' => 'advance'];
            }
        }

        $accountIds = array_keys($accountMap);
        $openingByAccount = collect();
        $lines = collect();

        if ($accountIds !== []) {
            $openingByAccount = JournalEntryDetail::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
                ->whereIn('journal_entry_details.account_id', $accountIds)
                ->whereDate('journal_entries.date', '<', $from)
                ->groupBy('journal_entry_details.account_id')
                ->selectRaw('journal_entry_details.account_id, COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
                ->get()
                ->keyBy('account_id');

            $lines = JournalEntryDetail::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
                ->whereIn('journal_entry_details.account_id', $accountIds)
                ->whereDate('journal_entries.date', '>=', $from)
                ->whereDate('journal_entries.date', '<=', $to)
                ->orderBy('journal_entries.date')
                ->orderBy('journal_entry_details.id')
                ->get([
                    'journal_entry_details.id',
                    'journal_entry_details.account_id',
                    'journal_entry_details.description',
                    'journal_entry_details.debit',
                    'journal_entry_details.credit',
                    'journal_entries.date as entry_date',
                    'journal_entries.reference_number',
                    'journal_entries.description as entry_description',
                ]);
        }

        $movementByAccount = $lines->groupBy('account_id')->map(fn ($rows) => [
            'debit' => (float) $rows->sum('debit'),
            'credit' => (float) $rows->sum('credit'),
        ]);

        $summaries = $employees->map(fn (Employee $employee) => [
            'employee_id' => (int) $employee->id,
            'employee' => $employee->full_name,
            'department' => $employee->department?->name ?? 'بدون قسم',
            'payable' => $this->accountStatementSide($employee->salary_payable_account_id, 'payable', $openingByAccount, $movementByAccount),
            'advance' => $this->accountStatementSide($employee->employee_advance_account_id, 'advance', $openingByAccount, $movementByAccount),
        ])->values();

        $selected = $request->filled('employee_id') ? $employees->first() : null;
        $ledger = collect();
        $opening = ['payable' => 0.0, 'advance' => 0.0];

        if ($selected) {
            $running = $opening = [
                'payable' => $this->accountOpening($selected->salary_payable_account_id, 'payable', $openingByAccount),
                'advance' => $this->accountOpening($selected->employee_advance_account_id, 'advance', $openingByAccount),
            ];

            foreach ($lines as $line) {
                $meta = $accountMap[(int) $line->account_id] ?? null;
                if (! $meta) {
                    continue;
                }

                $kind = $meta['kind'];
                $debit = (float) $line->debit;
                $credit = (float) $line->credit;
                $running[$kind] += $this->accountDelta($kind, $debit, $credit);

                $ledger->push([
                    'date' => Carbon::parse($line->entry_date)->toDateString(),
                    'reference' => $line->reference_number ?: '—',
                    'description' => $line->description ?: ($line->entry_description ?: '—'),
                    'kind' => $kind,
                    'kind_label' => $kind === 'payable' ? 'مستحقات الراتب' : 'سلف الموظف',
                    'nature' => $this->statementNature($kind, $debit, $credit),
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $running[$kind],
                ]);
            }
        }

        $m = fn ($v) => number_format((float) $v, 2);
        $view = $selected ? 'details' : 'summary';
        $headers = $view === 'details'
            ? ['#', 'التاريخ', 'المرجع', 'الحساب', 'نوع الحركة', 'البيان', 'مدين', 'دائن', 'رصيد الحساب']
            : ['#', 'الموظف', 'القسم', 'مستحقات: أول المدة', 'مستحقات: مدين', 'مستحقات: دائن', 'مستحقات: آخر المدة', 'سلف: أول المدة', 'سلف: مدين', 'سلف: دائن', 'سلف: آخر المدة'];
        $exportRows = $view === 'details'
            ? $ledger->map(fn ($l, $i) => [$i + 1, $l['date'], $l['reference'], $l['kind_label'], $l['nature'], $l['description'], $m($l['debit']), $m($l['credit']), $m($l['balance'])])
            : $summaries->map(fn ($r, $i) => [
                $i + 1, $r['employee'], $r['department'],
                $m($r['payable']['opening']), $m($r['payable']['debit']), $m($r['payable']['credit']), $m($r['payable']['closing']),
                $m($r['advance']['opening']), $m($r['advance']['debit']), $m($r['advance']['credit']), $m($r['advance']['closing']),
            ]);

        $title = $selected ? 'كشف حساب '.$selected->full_name : 'كشف حساب الموظف';
        if ($response = $this->respondExport($request, $title, $headers, $exportRows, $selected ? 'employee_statement' : 'employee_statements')) {
            return $response;
        }

        $sideTotals = fn (string $side) => [
            'opening' => (float) $summaries->sum(fn ($r) => $r[$side]['opening']),
            'debit' => (float) $summaries->sum(fn ($r) => $r[$side]['debit']),
            'credit' => (float) $summaries->sum(fn ($r) => $r[$side]['credit']),
            'closing' => (float) $summaries->sum(fn ($r) => $r[$side]['closing']),
        ];

        return view('hr::reports.payroll.statement', [
            'reportView' => $view,
            'headers' => $headers,
            'selected' => $selected,
            'opening' => $opening,
            'summaries' => $view === 'summary' ? $this->hrPaginate($summaries, $request) : null,
            'ledger' => $view === 'details' ? $this->hrPaginate($ledger, $request) : null,
            'sideTotals' => ['payable' => $sideTotals('payable'), 'advance' => $sideTotals('advance')],
            'unlinked' => $summaries->filter(fn ($r) => ! $r['payable']['linked'] && ! $r['advance']['linked'])->count(),
            'operational' => $this->statementOperationalTotals($request, $period),
            'hrCharts' => [
                'bar' => [
                    'title' => 'الأعلى مستحقاتٍ آخر المدة', 'icon' => 'book-open', 'color' => '#8b5cf6', 'money' => true, 'unit' => 'ر.س',
                    'items' => $summaries->map(fn ($r) => ['name' => $r['employee'], 'value' => $r['payable']['closing']])->all(),
                ],
                'donut' => [
                    'title' => 'الموظفون حسب رصيدهم آخر المدة', 'unit' => 'موظف',
                    'groups' => [
                        [
                            'label' => 'له مستحقات راتب', 'color' => '#8b5cf6',
                            'count' => $summaries->filter(fn ($r) => $r['payable']['closing'] > 0.005)->count(),
                            'sum' => (float) $summaries->sum(fn ($r) => max(0, $r['payable']['closing'])),
                        ],
                        [
                            'label' => 'عليه سلف (بلا مستحقات)', 'color' => '#f59e0b',
                            'count' => $summaries->filter(fn ($r) => $r['payable']['closing'] <= 0.005 && $r['advance']['closing'] > 0.005)->count(),
                            'sum' => (float) $summaries->filter(fn ($r) => $r['payable']['closing'] <= 0.005)->sum(fn ($r) => max(0, $r['advance']['closing'])),
                        ],
                        [
                            'label' => 'بلا رصيد', 'color' => '#10b981',
                            'count' => $summaries->filter(fn ($r) => ($r['payable']['linked'] || $r['advance']['linked'])
                                && $r['payable']['closing'] <= 0.005 && $r['advance']['closing'] <= 0.005)->count(),
                        ],
                        [
                            'label' => 'بلا حسابات مربوطة', 'color' => '#64748b',
                            'count' => $summaries->filter(fn ($r) => ! $r['payable']['linked'] && ! $r['advance']['linked'])->count(),
                        ],
                    ],
                ],
            ],
        ] + $this->runsPageData($request, $period));
    }

    /* ================================================================
     |  مطابقة الرواتب بالمحاسبة
     * ================================================================ */

    /**
     * مطابقة الرواتب بالمحاسبة — الرواتبُ المدفوعة مقابل حساب مصروف الرواتب.
     *
     * مبلغ المصروف في الصرف = الأساسي + الاستحقاقات − الخصميات، وهو ما
     * يُرحَّل مديناً على حساب المصروف في قيد الاستحقاق (SAL-ACC).
     */
    public function reconciliation(Request $request)
    {
        $period = $this->hrReportPeriod($request);
        [$from, $to] = [$period['from']->toDateString(), $period['to']->toDateString()];

        $runs = PayrollRun::query()
            ->with(['employee.department:id,name'])
            ->whereIn('employee_id', $this->employeeIdsQuery($request))
            ->whereBetween('period_from', [$from, $to])
            ->where('status', PayrollRun::STATUS_DISBURSED)
            ->orderBy('period_from')
            ->get();

        $expenseAccountId = $this->salaryExpenseAccountId();
        $expenseAccount = $expenseAccountId ? Account::query()->find($expenseAccountId) : null;

        $journalIds = $runs
            ->flatMap(fn (PayrollRun $run) => [$run->accrual_journal_entry_id, $run->journal_entry_id])
            ->filter()->unique()->values()->all();

        $detailsByJournal = $journalIds === []
            ? collect()
            : JournalEntryDetail::query()->whereIn('journal_entry_id', $journalIds)->get()->groupBy('journal_entry_id');

        $stateLabels = ['matched' => 'مطابق', 'variance' => 'فيه فرق', 'unposted' => 'بلا قيد'];
        $stateTones = ['matched' => 'rpt-pill-success', 'variance' => 'rpt-pill-danger', 'unposted' => 'rpt-pill-warning'];

        $rows = $runs->map(function (PayrollRun $run) use ($detailsByJournal, $expenseAccountId, $stateLabels, $stateTones) {
            $payrollExpense = $this->payrollExpenseAmount($run);
            $accrualDetails = $detailsByJournal->get($run->accrual_journal_entry_id) ?? collect();
            $paymentDetails = $detailsByJournal->get($run->journal_entry_id) ?? collect();

            $postedExpense = (float) $accrualDetails->where('account_id', $expenseAccountId)->sum('debit');
            $variance = round($payrollExpense - $postedExpense, 2);
            $hasAccrual = (bool) $run->accrual_journal_entry_id && $accrualDetails->isNotEmpty();
            $hasPayment = (bool) $run->journal_entry_id && $paymentDetails->isNotEmpty();

            $state = 'unposted';
            if ($hasAccrual && abs($variance) < 0.01) {
                $state = 'matched';
            } elseif ($hasAccrual || $hasPayment) {
                $state = 'variance';
            }

            return [
                'id' => $run->id,
                'employee_id' => (int) $run->employee_id,
                'employee' => $run->employee?->full_name ?? '#'.$run->employee_id,
                'department' => $run->employee?->department?->name ?? 'بدون قسم',
                'period' => $run->period_label,
                'payroll_expense' => $payrollExpense,
                'payroll_net' => (float) $run->net_salary,
                'payroll_advances' => (float) $run->total_advances_deducted,
                'posted_expense' => $postedExpense,
                'variance' => $variance,
                'has_accrual' => $hasAccrual,
                'has_payment' => $hasPayment,
                'state' => $state,
                'state_label' => $stateLabels[$state],
                'tone' => $stateTones[$state],
            ];
        });

        $accrualIds = $runs->pluck('accrual_journal_entry_id')->filter()->all();

        $orphansQuery = JournalEntryDetail::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
            ->where('journal_entry_details.account_id', $expenseAccountId ?: 0)
            ->where('journal_entry_details.debit', '>', 0)
            ->whereDate('journal_entries.date', '>=', $from)
            ->whereDate('journal_entries.date', '<=', $to);

        if ($accrualIds !== []) {
            $orphansQuery->whereNotIn('journal_entries.id', $accrualIds);
        }

        // بلا فلتر موظفين: نطاقُ المستخدم وحده (كما كان)؛ ومع فلترٍ: قيودُ موظفي الفلتر وحدهم
        if (collect(['employee_id', 'department_id', 'job_title_id', 'branch_id'])->contains(fn ($key) => $request->filled($key))) {
            $orphansQuery->whereIn('journal_entries.employee_id', $this->employeeIdsQuery($request));
        } else {
            $this->scopeAnyQueryToEmployees($orphansQuery, 'journal_entries.employee_id');
        }

        $orphans = $orphansQuery->orderBy('journal_entries.date')->get([
            'journal_entries.id as journal_entry_id',
            'journal_entries.date as entry_date',
            'journal_entries.reference_number',
            'journal_entries.description',
            'journal_entries.employee_id',
            'journal_entry_details.debit',
        ])->map(fn ($o) => [
            'date' => Carbon::parse($o->entry_date)->toDateString(),
            'reference' => $o->reference_number ?: '—',
            'description' => $o->description ?: '—',
            'debit' => (float) $o->debit,
        ]);

        $m = fn ($v) => number_format((float) $v, 2);
        $headers = ['#', 'الموظف', 'الفترة', 'مصروف الراتب', 'الصافي', 'السلف', 'المرحَّل على حساب المصروف', 'الفرق', 'قيد الاستحقاق', 'قيد الدفع', 'النتيجة'];
        $exportRows = $rows->map(fn ($r) => [
            $r['employee'], $r['period'], $m($r['payroll_expense']), $m($r['payroll_net']), $m($r['payroll_advances']),
            $m($r['posted_expense']), $m($r['variance']), $r['has_accrual'] ? 'نعم' : 'لا', $r['has_payment'] ? 'نعم' : 'لا', $r['state_label'],
        ])->concat($orphans->map(fn ($o) => [
            'قيد بلا راتب: '.$o['reference'], $o['date'], '', '', '', $m($o['debit']), '', '', '', 'قيد بلا راتب',
        ]))->values()->map(fn ($r, $i) => array_merge([$i + 1], $r));

        if ($response = $this->respondExport($request, 'مطابقة الرواتب بالمحاسبة', $headers, $exportRows, 'payroll_reconciliation')) {
            return $response;
        }

        return view('hr::reports.payroll.reconciliation', [
            'headers' => $headers,
            'rows' => $this->hrPaginate($rows->values(), $request),
            'orphans' => $orphans,
            'expenseAccount' => $expenseAccount,
            'totals' => [
                'count' => $rows->count(),
                'payroll_expense' => (float) $rows->sum('payroll_expense'),
                'payroll_net' => (float) $rows->sum('payroll_net'),
                'payroll_advances' => (float) $rows->sum('payroll_advances'),
                'posted_expense' => (float) $rows->sum('posted_expense'),
                'variance' => (float) $rows->sum('variance'),
                'matched' => $rows->where('state', 'matched')->count(),
                'variance_count' => $rows->where('state', 'variance')->count(),
                'unposted' => $rows->where('state', 'unposted')->count(),
                'orphans' => (float) $orphans->sum('debit'),
            ],
            'hrCharts' => [
                'bar' => [
                    'title' => 'الأكبر فرقاً بين الرواتب والمحاسبة', 'icon' => 'balance-scale', 'color' => '#ef4444', 'money' => true, 'unit' => 'ر.س',
                    'items' => $rows->groupBy('employee_id')->map(fn (Collection $g) => [
                        'name' => $g->first()['employee'], 'value' => (float) $g->sum(fn ($r) => abs($r['variance'])),
                    ])->values()->all(),
                ],
                'donut' => [
                    'title' => 'نتيجة المطابقة (العدد ومصروف الراتب)', 'unit' => 'سجل',
                    'groups' => collect(['matched' => '#10b981', 'variance' => '#ef4444', 'unposted' => '#f59e0b'])
                        ->map(fn ($color, $state) => [
                            'label' => $stateLabels[$state], 'color' => $color,
                            'count' => $rows->where('state', $state)->count(),
                            'sum' => (float) $rows->where('state', $state)->sum('payroll_expense'),
                        ])->values()
                        ->push(['label' => 'قيد بلا راتب', 'color' => '#64748b', 'count' => $orphans->count(), 'sum' => (float) $orphans->sum('debit')])
                        ->all(),
                ],
            ],
        ] + $this->runsPageData($request, $period));
    }

    /* ================================================================
     |  أدواتٌ مشتركة
     * ================================================================ */

    /**
     * `?export=rows` ← JSON لكلّ الصفوف بترتيب الأعمدة؛ و`?export=xlsx|pdf`
     * ← الملفّ القديم من الصفوف نفسها بلا عمود «#». وإلا null فتُرسم الصفحة.
     *
     * @param  array<int, string>  $headers
     */
    private function respondExport(Request $request, string $title, array $headers, Collection $rows, string $slug)
    {
        $format = (string) $request->input('export', '');

        if ($format === '') {
            return null;
        }

        if ($format === 'rows') {
            return response()->json(['rows' => $rows->values()->all()]);
        }

        return $this->exportIfRequested(
            $request,
            $title,
            array_slice($headers, 1),
            fn () => $rows->map(fn ($r) => array_slice(array_values($r), 1))->all(),
            $slug
        );
    }

    /** معرّفاتُ موظفي التقرير استعلاماً فرعياً — نسخةٌ جديدة لكلّ استعمال. */
    private function employeeIdsQuery(Request $request): Builder
    {
        return $this->hrReportEmployees($request, false)->select('employees.id');
    }

    /** الصرفاتُ في المدى لموظفي التقرير، بفلتر «حالة الصرف». */
    private function runsQuery(Request $request, array $period): Builder
    {
        $query = PayrollRun::query()
            ->with(['employee.department:id,name', 'slip.payroll'])
            ->whereIn('employee_id', $this->employeeIdsQuery($request))
            ->whereBetween('period_from', [$period['from']->toDateString(), $period['to']->toDateString()]);

        $this->applyRunStatus($query, $request);

        return $query;
    }

    /**
     * «حالة الصرف»: فارغةٌ = كلُّ غير الملغاة؛ `all` = الكل؛ قيمةٌ من
     * `PayrollRun::statuses()` (الروابط القديمة) على العمود؛ ومرحلةٌ من
     * `stages()` على عمودها الأساسيّ هنا، ثم تُصفّى بـ`stage()` في
     * {@see stageFiltered()} — المرحلةُ تُحسب ولا تُخزَّن.
     */
    private function applyRunStatus(Builder $query, Request $request): void
    {
        $status = (string) $request->input('status', '');

        if ($status === 'all') {
            return;
        }
        if ($status === '' || ! (isset(PayrollRun::statuses()[$status]) || isset(PayrollRun::stages()[$status]))) {
            $query->where('status', '!=', PayrollRun::STATUS_CANCELLED);
        } elseif (isset(PayrollRun::statuses()[$status])) {
            $query->where('status', $status);
        } elseif ($status === PayrollRun::STAGE_PAID) {
            $query->where('status', PayrollRun::STATUS_DISBURSED);
        } else {
            $query->where('status', PayrollRun::STATUS_PENDING);
        }
    }

    /** مرحلةٌ من مراحل «المعلّقة» تحتاج `stage()` لتُصفّى، أو null. */
    private function stageWanted(Request $request): ?string
    {
        $status = (string) $request->input('status', '');

        return in_array($status, [
            PayrollRun::STAGE_AWAITING_APPROVAL, PayrollRun::STAGE_SCHEDULED,
            PayrollRun::STAGE_READY, PayrollRun::STAGE_HELD,
        ], true) ? $status : null;
    }

    private function stageFiltered(Collection $rows, Request $request): Collection
    {
        $wanted = $this->stageWanted($request);

        return $wanted ? $rows->where('stage', $wanted)->values() : $rows->values();
    }

    /** ما تحتاجه كلُّ صفحة: المدى وخيارات الفلتر وخيارات «حالة الصرف». */
    private function runsPageData(Request $request, array $period): array
    {
        return [
            'fromDate' => $period['from'],
            'toDate' => $period['to'],
            'periodLabel' => $period['label'],
            'periodContext' => $period['context'],
            'runStatusOptions' => ['' => 'كل الرواتب (دون الملغاة)', PayrollRun::STATUS_PENDING => 'غير المدفوعة كلها']
                + PayrollRun::stages() + ['all' => 'الكل مع الملغاة'],
        ] + $this->hrReportOptions();
    }

    /**
     * @param  Collection<int, PayrollRunItem>  $items
     * @return array<int, string>
     */
    private function catalogNamesForItems(Collection $items): array
    {
        if (
            ! Schema::hasTable('salary_items')
            || ! Schema::hasTable('salary_component_types')
            || ! Schema::hasColumn('payroll_run_items', 'source_table')
        ) {
            return [];
        }

        $sourceIds = $items
            ->filter(fn (PayrollRunItem $item) => $item->source_table === 'salary_items' && $item->source_id)
            ->pluck('source_id')
            ->unique()
            ->filter()
            ->values();

        if ($sourceIds->isEmpty()) {
            return [];
        }

        $namesBySalaryItemId = SalaryItem::query()
            ->whereIn('id', $sourceIds)
            ->with('componentType')
            ->get()
            ->mapWithKeys(fn (SalaryItem $item) => [
                $item->id => $item->componentType?->name,
            ])
            ->filter()
            ->all();

        $names = [];
        foreach ($items as $item) {
            if ($item->source_table !== 'salary_items' || ! isset($namesBySalaryItemId[$item->source_id])) {
                continue;
            }
            $names[$item->id] = $namesBySalaryItemId[$item->source_id];
        }

        return $names;
    }

    /**
     * مصروف الراتب المرحَّل: الإجمالي بعد الخصم وقبل السلفة.
     */
    private function payrollExpenseAmount(PayrollRun $run): float
    {
        return round(
            (float) $run->basic_amount + (float) $run->total_additions - (float) $run->total_deductions,
            2
        );
    }

    /**
     * حسابُ مصروف الرواتب كما يُرحِّل إليه `PayrollAccountant` — من «إعدادات
     * الحسابات المحاسبية» (`employees_salaries_expense`) وحدها. كان وراءه ثابتٌ احتياطيّ (`EMPLOYEES_SALARIES_EXPENSE`)
     * فتُطابق المطابقةُ حساباً لا يُرحِّل إليه الصرفُ أصلاً. `null` = غير مربوط،
     * فيظهر كلُّ صرفٍ «غير مرحَّل» بدل مطابقةٍ مضلِّلة.
     */
    private function salaryExpenseAccountId(): ?int
    {
        return PayrollAccounts::id(PayrollAccounts::SALARY_EXPENSE);
    }

    /**
     * @param  Collection<int|string, object>  $openingByAccount
     * @param  Collection<int|string, array{debit: float, credit: float}>  $movementByAccount
     * @return array{opening: float, debit: float, credit: float, closing: float, linked: bool}
     */
    private function accountStatementSide(?int $accountId, string $kind, $openingByAccount, $movementByAccount): array
    {
        $linked = (bool) $accountId;
        $opening = $this->accountOpening($accountId, $kind, $openingByAccount);
        $movement = $movementByAccount->get($accountId) ?? ['debit' => 0.0, 'credit' => 0.0];
        $debit = (float) $movement['debit'];
        $credit = (float) $movement['credit'];

        return [
            'opening' => $opening,
            'debit' => $debit,
            'credit' => $credit,
            'closing' => $opening + $this->accountDelta($kind, $debit, $credit),
            'linked' => $linked,
        ];
    }

    /** @param  Collection<int|string, object>  $openingByAccount */
    private function accountOpening(?int $accountId, string $kind, $openingByAccount): float
    {
        if (! $accountId) {
            return 0.0;
        }

        $row = $openingByAccount->get($accountId);
        $debit = (float) ($row->d ?? 0);
        $credit = (float) ($row->c ?? 0);

        return $this->accountDelta($kind, $debit, $credit);
    }

    /** الالتزام يزيد بالدائن، والأصل (السلفة) يزيد بالمدين. */
    private function accountDelta(string $kind, float $debit, float $credit): float
    {
        return $kind === 'payable' ? $credit - $debit : $debit - $credit;
    }

    private function statementNature(string $kind, float $debit, float $credit): string
    {
        if ($kind === 'payable') {
            return $credit >= $debit ? 'استحقاق راتب' : 'دفع راتب';
        }

        return $debit >= $credit ? 'صرف سلفة' : 'سداد سلفة';
    }

    /** @return array{earnings: float, deductions: float, advances: float, net: float, count: int} */
    private function statementOperationalTotals(Request $request, array $period): array
    {
        $runs = PayrollRun::query()
            ->whereIn('employee_id', $this->employeeIdsQuery($request))
            ->whereBetween('period_from', [$period['from']->toDateString(), $period['to']->toDateString()])
            ->where('status', PayrollRun::STATUS_DISBURSED)
            ->get();

        return [
            'earnings' => (float) $runs->sum(fn (PayrollRun $run) => (float) $run->basic_amount + (float) $run->total_additions),
            'deductions' => (float) $runs->sum('total_deductions'),
            'advances' => (float) $runs->sum('total_advances_deducted'),
            'net' => (float) $runs->sum('net_salary'),
            'count' => $runs->count(),
        ];
    }
}
