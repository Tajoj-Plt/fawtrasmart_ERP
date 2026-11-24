<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تقريرُ مواعيد العملاء — صفحةٌ مستقلّة على تصميم «أعمار الديون».
 *
 * قرارُ المستخدم (2026-09-20): تقريران منفصلان — الحجوزاتُ في
 * `Reservations.reports.index` والمواعيدُ هنا — وكلاهما يُفتح من لوحة
 * تقارير العملاء. الشاشةُ القديمة (`customer_apptilmition`، جدولُ صفوفٍ
 * واحد) استُبدلت بأقسامٍ مجمَّعة على نمط تقارير الحجوزات نفسِه: فلاترُ
 * واحدة فوق الصفحة، مؤشّرات، رسمان، ثمّ جدولٌ لكلّ تقرير بترقيمٍ وتصديرٍ
 * وطباعةٍ خاصّة به.
 *
 * الحالةُ في `appointments.status` **عددٌ** (١..٤) لا نصّ، وتسمياتُها من
 * النموذج نفسِه (`status_text`) فلا تفترق عن شاشة المواعيد. ولا مالَ في
 * المواعيد فلا أعمدةَ مبالغ.
 */
class CustomerAppointmentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:clients.reports.appointments');
    }

    /**
     * تعريفُ الأقسام: محورُ التجميع والأيقونة والوحدة — كتقارير الحجوزات.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $raw = [
            'by_employee' => ['icon' => 'user-clock', 'group' => 'employee_id', 'unit' => 'موظّف', 'performance' => true],
            'summary' => ['icon' => 'chart-pie', 'group' => 'status', 'unit' => 'حالة'],
            'daily' => ['icon' => 'calendar-day', 'group' => 'date', 'unit' => 'يوم', 'performance' => true],
            'by_client' => ['icon' => 'user-tag', 'group' => 'client_id', 'unit' => 'عميل', 'performance' => true],
        ];

        // `chip` نصُّ زرّ «تجميع حسب» فوق الجدول الواحد، والعنوانُ للترويسة والتصدير
        $texts = [
            'by_employee' => ['الموظّف', 'المواعيد حسب الموظّف', 'عبء المواعيد على كلّ موظّف — الموعد المشترك يُحسب لكلّ موظفيه.'],
            'summary' => ['الحالة', 'المواعيد حسب الحالة', 'مواعيد العملاء موزّعةً على حالاتها: مكتملة، مجدولة، صُرف النظر عنها، مجدولة مجدداً.'],
            'daily' => ['اليوم', 'المواعيد اليومية', 'عدد المواعيد وحالاتها لكلّ يوم في المدى.'],
            'by_client' => ['العميل', 'المواعيد حسب العميل', 'مواعيد كلّ عميل ونسبة إنجازها.'],
        ];

        foreach ($raw as $key => &$definition) {
            $definition['key'] = $key;
            [$definition['chip'], $definition['title'], $definition['description']] = $texts[$key];
        }

        return $raw;
    }

    /** تسمياتُ الحالات وألوانُها — القاموسُ الواحد للشاشة والرسم والفلتر. */
    public static function statuses(): array
    {
        return [
            Appointment::STATUS_PENDING => ['label' => (new Appointment(['status' => Appointment::STATUS_PENDING]))->status_text, 'color' => '#3b82f6'],
            Appointment::STATUS_COMPLETED => ['label' => (new Appointment(['status' => Appointment::STATUS_COMPLETED]))->status_text, 'color' => '#10b981'],
            Appointment::STATUS_IGNORED => ['label' => (new Appointment(['status' => Appointment::STATUS_IGNORED]))->status_text, 'color' => '#ef4444'],
            Appointment::STATUS_RESCHEDULED => ['label' => (new Appointment(['status' => Appointment::STATUS_RESCHEDULED]))->status_text, 'color' => '#f97316'],
        ];
    }

    /**
     * جدولُ عرضٍ واحد ومحورُ تجميعٍ يختاره المستخدم (طلبه 2026-09-20:
     * «تجميع حسب الموظف والإلغاء وغيرها ويكون جدول عرض واحد فقط») —
     * لا أربعةُ جداول متراصة. الرسمان ثابتان مهما كان المحور، فصفوفُ
     * «يومي» و«حسب الحالة» تُحسب لهما وإن لم تكن المحورَ المعروض.
     */
    public function customerAppointments(Request $request)
    {
        $period = $this->currentPeriod($request);
        [$from, $to] = $this->prepare($request);

        $definitions = self::definitions();
        $group = (string) $request->input('group', 'by_employee');

        if (! array_key_exists($group, $definitions)) {
            $group = 'by_employee';
        }

        $definition = $definitions[$group];
        $columns = $this->columns($definition);
        $rows = $this->rowsFor($definition, $request);

        $summaryRows = $group === 'summary' ? $rows : $this->rowsFor($definitions['summary'], $request);
        $dailyRows = $group === 'daily' ? $rows : $this->rowsFor($definitions['daily'], $request);

        return view('client::customers.CustomerReport.appointments', [
            'definitions' => $definitions,
            'group' => $group,
            'definition' => $definition,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $this->totalsOf($rows, $columns),
            'kpis' => $this->kpis($request),
            'timeline' => $this->timeline($dailyRows, $from, $to),
            'statusSlices' => $this->statusSlices($summaryRows),
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'periods' => $this->periods(),
            'statuses' => self::statuses(),
            'employees' => Employee::orderBy('first_name')->get(),
            'selectedClient' => $request->filled('client_id')
                ? Client::select('id', 'code', 'trade_name', 'first_name', 'last_name')->find($request->integer('client_id'))
                : null,
        ]);
    }

    /** التصديرُ CSV — بـBOM كي تقرأ Excel العربيةَ صحيحةً، وبأعمدة الشاشة نفسها. */
    public function export(Request $request, string $report): StreamedResponse
    {
        $definition = self::definitions()[$report] ?? abort(404);
        [$from, $to] = $this->prepare($request);

        $rows = $this->rowsFor($definition, $request);
        $columns = $this->columns($definition);
        $totals = $this->totalsOf($rows, $columns);

        $filename = 'appointments-'.$report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $columns, $totals, $definition, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$definition['title'], $from->format('Y-m-d').' — '.$to->format('Y-m-d')]);
            fputcsv($out, array_merge(['البيان'], array_column($columns, 'label')));

            foreach ($rows as $row) {
                fputcsv($out, array_merge(
                    [$row['label']],
                    array_map(fn ($c) => $this->csvValue($row['values'][$c['key']] ?? 0, $c['type']), $columns)
                ));
            }

            fputcsv($out, array_merge(
                ['الإجمالي'],
                array_map(fn ($c) => $this->csvValue($totals[$c['key']] ?? '', $c['type']), $columns)
            ));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ════════════════════ الأعمدة ════════════════════

    /** @return array<int, array{key:string, label:string, type:string}> */
    protected function columns(array $definition): array
    {
        $columns = [['key' => 'count', 'label' => 'العدد', 'type' => 'int']];

        if (! empty($definition['performance'])) {
            $statuses = self::statuses();
            $columns[] = ['key' => 'completed', 'label' => $statuses[Appointment::STATUS_COMPLETED]['label'], 'type' => 'int'];
            $columns[] = ['key' => 'pending', 'label' => $statuses[Appointment::STATUS_PENDING]['label'], 'type' => 'int'];
            $columns[] = ['key' => 'ignored', 'label' => $statuses[Appointment::STATUS_IGNORED]['label'], 'type' => 'int'];
            $columns[] = ['key' => 'rescheduled', 'label' => $statuses[Appointment::STATUS_RESCHEDULED]['label'], 'type' => 'int'];
            $columns[] = ['key' => 'completion_rate', 'label' => 'نسبة الإنجاز', 'type' => 'percent'];
        }

        return $columns;
    }

    // ════════════════════ البناء ════════════════════

    /**
     * مواعيدُ المدى بالفلاتر: التاريخُ والموظّفُ والعميلُ والحالة.
     * «الموظّف» عبر جدول الربط (`forEmployee`) لا `employee_id` وحدَه.
     */
    protected function appointmentsQuery(Request $request): Builder
    {
        $query = Appointment::query()
            ->whereDate('appointments.appointment_date', '>=', $request->input('date_from'))
            ->whereDate('appointments.appointment_date', '<=', $request->input('date_to'));

        if ($request->filled('employee_id')) {
            $query->forEmployee((int) $request->input('employee_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('appointments.client_id', $request->integer('client_id'));
        }

        if ($request->filled('appointment_status')) {
            $query->where('appointments.status', (int) $request->input('appointment_status'));
        }

        return $query;
    }

    /**
     * صفوفُ قسمٍ — مجمَّعةً في القاعدة على محوره.
     *
     * «حسب الموظّف» يضمّ جدولَ الربط: الموعدُ ذو الموظفَين يُحسب لكليهما،
     * وصفوفُ مزامنة الحجوزات (بلا ربط) تُقرأ من `employee_id` بـ`COALESCE`.
     *
     * @return array<int, array{label:string, key:mixed, values:array<string, float|int>}>
     */
    protected function rowsFor(array $definition, Request $request): array
    {
        $query = $this->appointmentsQuery($request);
        $performance = ! empty($definition['performance']);

        $expression = match ($definition['group']) {
            'status' => 'appointments.status',
            'date' => 'DATE(appointments.appointment_date)',
            'employee_id' => 'COALESCE(ae.employee_id, appointments.employee_id)',
            default => 'appointments.client_id',
        };

        if ($definition['group'] === 'employee_id') {
            $query->leftJoin('appointment_employees as ae', 'ae.appointment_id', '=', 'appointments.id')
                ->whereRaw('COALESCE(ae.employee_id, appointments.employee_id) IS NOT NULL');
        }

        if ($definition['group'] === 'client_id') {
            $query->whereNotNull('appointments.client_id');
        }

        $select = "{$expression} as k, COUNT(*) as c";

        if ($performance) {
            $select .= ', SUM(CASE WHEN appointments.status = '.Appointment::STATUS_COMPLETED.' THEN 1 ELSE 0 END) as done'
                .', SUM(CASE WHEN appointments.status = '.Appointment::STATUS_PENDING.' THEN 1 ELSE 0 END) as pend'
                .', SUM(CASE WHEN appointments.status = '.Appointment::STATUS_IGNORED.' THEN 1 ELSE 0 END) as ign'
                .', SUM(CASE WHEN appointments.status = '.Appointment::STATUS_RESCHEDULED.' THEN 1 ELSE 0 END) as res';
        }

        $rows = $query->selectRaw($select)
            ->groupByRaw($expression)
            ->when($definition['group'] === 'date',
                fn ($q) => $q->orderByRaw($expression),
                fn ($q) => $q->orderByDesc('c'))
            ->get();

        $labels = match ($definition['group']) {
            'employee_id' => Employee::whereIn('id', $rows->pluck('k')->all() ?: [0])->get()
                ->mapWithKeys(fn ($e) => [$e->id => ($e->full_name ?: '#'.$e->id)])->all(),
            'client_id' => Client::whereIn('id', $rows->pluck('k')->all() ?: [0])->get()
                ->mapWithKeys(fn ($c) => [$c->id => $c->display_name ?: '#'.$c->id])->all(),
            default => [],
        };

        $statuses = self::statuses();

        return $rows->map(function ($raw) use ($definition, $labels, $performance, $statuses) {
            $label = match ($definition['group']) {
                'status' => $statuses[(int) $raw->k]['label'] ?? ('#'.$raw->k),
                'date' => (string) $raw->k,
                default => $labels[$raw->k] ?? ('#'.$raw->k),
            };

            $values = ['count' => (int) $raw->c];

            if ($performance) {
                $values['completed'] = (int) $raw->done;
                $values['pending'] = (int) $raw->pend;
                $values['ignored'] = (int) $raw->ign;
                $values['rescheduled'] = (int) $raw->res;
                $values['completion_rate'] = $raw->c > 0 ? round(($raw->done / $raw->c) * 100, 1) : 0;
            }

            return ['label' => $label, 'key' => $raw->k, 'values' => $values];
        })->all();
    }

    /** @return array<string, float|int> */
    protected function totalsOf(array $rows, array $columns): array
    {
        $totals = [];

        foreach ($columns as $column) {
            if ($column['type'] === 'percent') {
                continue;
            }

            $totals[$column['key']] = (int) array_sum(array_map(fn ($r) => $r['values'][$column['key']] ?? 0, $rows));
        }

        if (isset($totals['completed'], $totals['count'])) {
            $totals['completion_rate'] = $totals['count'] > 0
                ? round(($totals['completed'] / $totals['count']) * 100, 1)
                : 0;
        }

        return $totals;
    }

    // ════════════════════ المؤشّرات والرسوم ════════════════════

    /** @return array<string, int> */
    protected function kpis(Request $request): array
    {
        $raw = $this->appointmentsQuery($request)
            ->selectRaw('COUNT(*) as c'
                .', SUM(CASE WHEN status = '.Appointment::STATUS_COMPLETED.' THEN 1 ELSE 0 END) as done'
                .', SUM(CASE WHEN status = '.Appointment::STATUS_PENDING.' THEN 1 ELSE 0 END) as pend'
                .', SUM(CASE WHEN status = '.Appointment::STATUS_IGNORED.' THEN 1 ELSE 0 END) as ign'
                .', SUM(CASE WHEN status = '.Appointment::STATUS_RESCHEDULED.' THEN 1 ELSE 0 END) as res'
                .', SUM(CASE WHEN share_with_client = 1 THEN 1 ELSE 0 END) as shared')
            ->first();

        $count = (int) ($raw->c ?? 0);
        $done = (int) ($raw->done ?? 0);

        return [
            'count' => $count,
            'completed' => $done,
            'pending' => (int) ($raw->pend ?? 0),
            'ignored' => (int) ($raw->ign ?? 0),
            'rescheduled' => (int) ($raw->res ?? 0),
            'shared' => (int) ($raw->shared ?? 0),
            'completion_rate' => $count > 0 ? round($done / $count * 100, 1) : 0,
        ];
    }

    /**
     * سلسلةُ رسم الأعمدة — من صفوف القسم اليومي المحسوبة سلفاً، والأيامُ
     * الخالية تُملأ بصفر. يومياً حين يقصر المدى وشهرياً حين يطول.
     *
     * @param  array<int, array<string, mixed>>  $dailyRows
     */
    protected function timeline(array $dailyRows, Carbon $from, Carbon $to): array
    {
        $daily = $from->diffInDays($to) <= 62;
        $counts = [];

        foreach ($dailyRows as $row) {
            $key = $daily ? (string) $row['label'] : substr((string) $row['label'], 0, 7);
            $counts[$key] = ($counts[$key] ?? 0) + (int) $row['values']['count'];
        }

        $labels = $values = [];
        $cursor = $daily ? $from->copy()->startOfDay() : $from->copy()->startOfMonth();
        $end = $daily ? $to->copy()->startOfDay() : $to->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $daily ? $cursor->format('Y-m-d') : $cursor->format('Y-m');
            $labels[] = $daily ? $cursor->format('d/m') : $cursor->format('m/Y');
            $values[] = $counts[$key] ?? 0;
            $daily ? $cursor->addDay() : $cursor->addMonth();
        }

        return ['mode' => $daily ? 'day' : 'month', 'labels' => $labels, 'values' => $values];
    }

    /**
     * @param  array<int, array<string, mixed>>  $summaryRows
     * @return array<int, array{key:int,label:string,value:int,pct:int,color:string}>
     */
    protected function statusSlices(array $summaryRows): array
    {
        $statuses = self::statuses();
        $sum = array_sum(array_map(fn ($r) => (int) $r['values']['count'], $summaryRows));

        return array_values(array_map(function ($row) use ($sum, $statuses) {
            $key = (int) $row['key'];
            $count = (int) $row['values']['count'];

            return [
                'key' => $key,
                'label' => $statuses[$key]['label'] ?? ('#'.$key),
                'value' => $count,
                'pct' => $sum > 0 ? (int) round($count / $sum * 100) : 0,
                'color' => $statuses[$key]['color'] ?? '#64748b',
            ];
        }, $summaryRows));
    }

    // ════════════════════ المدى الزمني ════════════════════

    /**
     * الفتراتُ تقويميّةٌ تُحسب في الخادم — الموعدُ يقع في المستقبل أيضاً،
     * لذا «الشهر القادم» بين الأزرار (كما في الشاشة القديمة).
     *
     * @return array<string, string>
     */
    protected function periods(): array
    {
        return [
            'today' => 'اليوم',
            'week' => 'هذا الأسبوع',
            'month' => 'هذا الشهر',
            'next_month' => 'الشهر القادم',
            'year' => 'هذه السنة',
            'custom' => 'مخصص',
        ];
    }

    protected function currentPeriod(Request $request): string
    {
        $period = (string) $request->input('period', '');

        if (array_key_exists($period, $this->periods())) {
            return $period;
        }

        return $request->filled('date_from') || $request->filled('date_to') ? 'custom' : 'month';
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function prepare(Request $request): array
    {
        $today = today();

        $preset = match ($this->currentPeriod($request)) {
            'today' => [$today->copy(), $today->copy()],
            'week' => [$today->copy()->startOfWeek(Carbon::SATURDAY), $today->copy()->endOfWeek(Carbon::FRIDAY)],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'next_month' => [$today->copy()->addMonthNoOverflow()->startOfMonth(), $today->copy()->addMonthNoOverflow()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [
                $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : $today->copy()->startOfMonth(),
                $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : $today->copy()->endOfMonth(),
            ],
        };

        $request->merge(['date_from' => $preset[0]->toDateString(), 'date_to' => $preset[1]->toDateString()]);

        return $preset;
    }

    protected function csvValue($value, string $type): string
    {
        return $type === 'percent' && $value !== '' ? $value.'%' : (string) $value;
    }
}
