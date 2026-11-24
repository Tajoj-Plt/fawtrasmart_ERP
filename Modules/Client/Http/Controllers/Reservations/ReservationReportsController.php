<?php

namespace Modules\Client\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Client\Http\Controllers\Reservations\Concerns\ScopesReservations;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Models\ReservationService as BookableService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تقاريرُ الحجوزات — صفحةٌ واحدةٌ شاملة، وتعريفٌ واحد لكلّ التقارير.
 *
 * ═══════════ صفحةٌ واحدة لا ثلاثَ عشرةَ شاشة ═══════════
 *
 * كانت كلُّ بطاقةٍ تفتح شاشةً بفلاترها الخاصّة. صارت التقاريرُ كلُّها أقساماً
 * في صفحةٍ واحدة على تصميم «أعمار ديون الفواتير»: فلاترُ واحدةٌ فوقها،
 * ومؤشّراتٌ ورسمان، ثمّ جدولٌ لكلّ تقرير بترقيمٍ وتصديرٍ وطباعةٍ خاصّة به.
 * الطلبُ الواحد يحسب الأقسامَ كلَّها — استعلامٌ مجمَّع لكلّ قسم، لا استعلامٌ
 * لكلّ صفّ — وينطبق عليها نطاقُ الرؤية نفسُه ({@see ScopesReservations}).
 *
 * والروابطُ القديمة `reports/{report}` تُحوَّل إلى الصفحة على مرساة القسم،
 * فلا ينكسر رابطٌ محفوظٌ ولا بطاقةٌ في لوحة الحجوزات.
 *
 * ═══════════ لماذا سجلٌّ لا متحكّمٌ لكلّ تقرير؟ ═══════════
 *
 * التقاريرُ تختلف في ثلاثة أشياء لا أكثر: ما تُجمَّع عليه، وما تُصفّى به
 * مسبقاً، وأيُّ أعمدةٍ تعرض. وكلُّ ما عداه مشترك — الفلاتر والنطاق
 * والتصدير والقالب.
 *
 * ═══════════ وأعمدةُ التقرير تُعرَّف مرّةً للشاشة والتصدير ═══════════
 *
 * `columns()` تقول ما يُعرض، والقالبُ والـCSV يقرآنها كلاهما. فلا يُضاف
 * عمودٌ للشاشة ويُنسى في الملفّ المصدَّر.
 *
 * ═══════════ والمالُ من فاتورة المبيعات وحدَها ═══════════
 *
 * لا قبضَ ولا عربونَ على الحجز؛ رابطُه الماليُّ الوحيد `invoice_id`. فعمودا
 * «المفوتَر» و«قيمة الفواتير» يُقرآن من `invoices` بالاستعلام المجمَّع نفسِه
 * (استعلامٌ فرعيٌّ داخل `SUM` — يعمل في MySQL وSQLite معاً)، والفواتيرُ
 * الملغاة (`voided_at`) لا تُحسب.
 */
class ReservationReportsController extends Controller
{
    use ScopesReservations;

    public function __construct()
    {
        $this->middleware('permission:reservation_reports.view', ['only' => ['index', 'show']]);
        $this->middleware('permission:reservation_reports.export', ['only' => ['export']]);
    }

    /**
     * تعريفُ التقارير: القسم، ومحورُ التجميع، والتصفيةُ المسبقة، والأعمدةُ الزائدة.
     *
     * الترتيبُ هنا هو ترتيبُ الأقسام في الصفحة. العنوانُ والوصفُ من ملفّ
     * الترجمة `reservations.reports.<key>`، والأيقونةُ من Font Awesome 5.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        $raw = [
            'summary' => ['section' => 'status', 'icon' => 'chart-pie', 'group' => 'status', 'unit' => 'status'],
            'daily' => ['section' => 'time', 'icon' => 'calendar-day', 'group' => 'date', 'unit' => 'day'],
            'monthly' => ['section' => 'time', 'icon' => 'chart-bar', 'group' => 'month', 'unit' => 'month', 'performance' => true],
            'by_service' => ['section' => 'services', 'icon' => 'briefcase', 'group' => 'product_id', 'unit' => 'service'],
            'by_employee' => ['section' => 'people', 'icon' => 'users', 'group' => 'employee_id', 'unit' => 'employee'],
            'by_client' => ['section' => 'clients', 'icon' => 'user', 'group' => 'client_id', 'unit' => 'client'],
            'by_branch' => ['section' => 'places', 'icon' => 'sitemap', 'group' => 'branch_id', 'unit' => 'node'],
            'cancelled' => ['section' => 'status', 'icon' => 'times-circle', 'group' => 'date', 'unit' => 'day', 'status' => ['cancelled', 'rejected']],
            'no_show' => ['section' => 'status', 'icon' => 'user-times', 'group' => 'client_id', 'unit' => 'client', 'status' => ['no_show']],
            'rescheduled' => ['section' => 'status', 'icon' => 'redo', 'group' => 'date', 'unit' => 'day', 'rescheduled' => true, 'extra' => ['moves']],
            'revenue' => ['section' => 'money', 'icon' => 'file-invoice-dollar', 'group' => 'date', 'unit' => 'day', 'status' => ['completed']],
            'service_performance' => ['section' => 'services', 'icon' => 'tachometer-alt', 'group' => 'product_id', 'unit' => 'service', 'performance' => true],
            'employee_performance' => ['section' => 'people', 'icon' => 'award', 'group' => 'employee_id', 'unit' => 'employee', 'performance' => true],
            /*
                «المدفوعات» و«حسب طريقة الدفع» و«المتبقّي» أُزيلت: لا قبضَ في
                الحجز — المالُ على فاتورة المبيعات وتقاريرُه تقاريرُها.

                والمواعيدُ لها تقريرُها المستقلّ في تقارير العملاء
                (`ClientReport.customerAppointments`) — قرارُ المستخدم
                2026-09-20: تقريران منفصلان لا صفحةٌ واحدة.
            */
        ];

        foreach ($raw as $key => &$definition) {
            $definition['key'] = $key;
            $definition['title'] = __("reservations.reports.{$key}.title");
            $definition['description'] = __("reservations.reports.{$key}.description");
        }

        return $raw;
    }

    /**
     * جدولُ عرضٍ واحد ومحورُ تجميعٍ يختاره المستخدم — طلبه (2026-09-21):
     * «تجميع حسب الموظّف والإلغاء وغيرها ويكون جدول عرض واحد فقط»، لا
     * ثلاثةَ عشرَ جدولاً متراصاً. الفلاترُ والمؤشّراتُ والرسمان ثابتون
     * مهما كان المحور، فصفوفُ «حسب الحالة» والسلسلةُ الزمنية تُحسبان
     * للرسمين وإن لم تكونا المحورَ المعروض.
     */
    public function index(Request $request)
    {
        // يُقرأ قبل `prepare()`: هي تُثبّت التاريخين في الطلب فيبدو كلُّ طلبٍ «مخصصاً»
        $period = $this->currentPeriod($request);
        [$from, $to] = $this->prepare($request);

        $definitions = self::definitions();
        $report = (string) $request->input('report', 'summary');

        if (! array_key_exists($report, $definitions)) {
            $report = 'summary';
        }

        $definition = $definitions[$report];
        $columns = $this->columns($definition);
        $rows = $this->rowsFor($definition, $request);

        $summaryRows = $report === 'summary' ? $rows : $this->rowsFor($definitions['summary'], $request);

        // الرسمُ الزمني يومٌ أو شهرٌ بطول المدى — صفوفُه من محوره هو
        $timelineKey = $from->diffInDays($to) <= 62 ? 'daily' : 'monthly';
        $timelineRows = $report === $timelineKey ? $rows : $this->rowsFor($definitions[$timelineKey], $request);

        $selectedClient = $request->filled('client_id')
            ? Client::select('id', 'code', 'trade_name', 'first_name', 'last_name')->find($request->integer('client_id'))
            : null;

        return view('client::reservations.reports.index', array_merge($this->filterOptions(), [
            'definitions' => $definitions,
            'report' => $report,
            'definition' => $definition,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $this->totalsOf($rows, $columns),
            'kpis' => $this->kpis($request),
            'timeline' => $this->timeline($timelineRows, $from, $to),
            'statusSlices' => $this->statusSlices($summaryRows),
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'periods' => $this->periods(),
            'selectedClient' => $selectedClient,
        ]));
    }

    /**
     * الروابطُ القديمة `reports/{report}` — تُحوَّل إلى الصفحة والمحورُ
     * المطلوبُ محورُها، بالفلاتر نفسها، فبطاقاتُ اللوحة والروابطُ
     * المحفوظة تعمل كما كانت.
     */
    public function show(Request $request, string $report): RedirectResponse
    {
        $this->definition($report);

        return redirect()->route('Reservations.reports.index', array_merge($request->query(), ['report' => $report]));
    }

    /**
     * التصديرُ CSV — بـBOM كي تقرأ Excel العربيةَ صحيحةً، وبالأعمدة نفسها.
     */
    public function export(Request $request, string $report): StreamedResponse
    {
        $definition = $this->definition($report);
        [$from, $to] = $this->prepare($request);

        $rows = $this->rowsFor($definition, $request);
        $columns = $this->columns($definition);
        $totals = $this->totalsOf($rows, $columns);

        $filename = 'reservations-'.$report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows, $columns, $totals, $definition, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$definition['title'], $from->format('Y-m-d').' — '.$to->format('Y-m-d')]);
            fputcsv($out, array_merge([__('reservations.columns.label')], array_column($columns, 'label')));

            foreach ($rows as $row) {
                fputcsv($out, array_merge(
                    [$row['label']],
                    array_map(fn ($c) => $this->csvValue($row['values'][$c['key']] ?? 0, $c['type']), $columns)
                ));
            }

            fputcsv($out, array_merge(
                [__('reservations.columns.total_row')],
                array_map(fn ($c) => $this->csvValue($totals[$c['key']] ?? '', $c['type']), $columns)
            ));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ════════════════════ الأعمدة ════════════════════

    /**
     * أعمدةُ التقرير — `type` يقرّر التنسيقَ والجمع: `int` و`money` تُجمع،
     * و`percent` تُحسب في سطر الإجمالي من مجاميعها لا بجمع النسب.
     *
     * @return array<int, array{key:string, label:string, type:string}>
     */
    protected function columns(array $definition): array
    {
        $col = fn (string $key, string $type) => ['key' => $key, 'label' => __('reservations.columns.'.$key), 'type' => $type];

        $columns = [$col('count', 'int')];

        if (! empty($definition['performance'])) {
            $columns[] = $col('completed', 'int');
            $columns[] = $col('no_show', 'int');
            $columns[] = $col('cancelled', 'int');
            $columns[] = $col('completion_rate', 'percent');
        }

        foreach ($definition['extra'] ?? [] as $extra) {
            $columns[] = $col($extra, $extra === 'moves' ? 'int' : 'money');
        }

        $columns[] = $col('total', 'money');
        $columns[] = $col('invoiced', 'int');
        $columns[] = $col('invoice_amount', 'money');

        return $columns;
    }

    // ════════════════════ البناء ════════════════════

    /**
     * المدى المحسوبُ يُثبَّت في الطلب فتقرأه `applyFilters()` كما تقرأ بقيّةَ
     * الفلاتر — والصفحةُ والتصديرُ يمرّان من هنا كلاهما.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function prepare(Request $request): array
    {
        [$from, $to] = $this->range($request);

        $request->merge(['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]);

        return [$from, $to];
    }

    /**
     * صفوفُ تقريرٍ — مجمَّعةً في القاعدة على محوره.
     *
     * @return array<int, array{label:string, key:mixed, values:array<string, float|int>}>
     */
    protected function rowsFor(array $definition, Request $request): array
    {
        $query = $this->applyFilters($this->scopedQuery(), $request);

        // التصفيةُ المسبقة الخاصّة بالتقرير — فوق فلاتر المستخدم
        if (! empty($definition['status'])) {
            $query->whereIn('status', $definition['status']);
        }

        if (! empty($definition['rescheduled'])) {
            $query->where('reschedule_count', '>', 0);
        }

        $performance = ! empty($definition['performance']);

        return match ($definition['group']) {
            'date' => $this->grouped($query, 'reservation_date', $performance, 'asc',
                fn ($k) => Carbon::parse($k)->format('Y-m-d')),
            // `SUBSTR` لا `DATE_FORMAT`: مدعومةٌ في MySQL وSQLite معاً
            'month' => $this->grouped($query, 'SUBSTR(reservation_date, 1, 7)', $performance, 'asc',
                fn ($k) => (string) $k),
            'status' => $this->grouped($query, 'status', $performance, null,
                fn ($k) => ReservationStatus::from_value($k)->label()),
            default => $this->groupedByReference($query, $definition['group'], $performance),
        };
    }

    /**
     * قيمةُ فاتورة الحجز — استعلامٌ فرعيٌّ يُستعمل داخل `SUM`، والملغاةُ لا تُحسب.
     */
    protected function invoiceAmountSql(): string
    {
        return '(SELECT i.grand_total FROM invoices i WHERE i.id = reservations.invoice_id AND i.voided_at IS NULL)';
    }

    /**
     * التجميعُ على تعبير — والأعمدةُ الإضافية تُحسب في الاستعلام نفسه.
     *
     * نسبُ الأداء بـ`SUM(CASE WHEN …)` لا `IF`: مدعومةٌ في MySQL وSQLite.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function grouped(Builder $query, string $expression, bool $performance, ?string $order, callable $label): array
    {
        $invoice = $this->invoiceAmountSql();

        $select = "{$expression} as k, COUNT(*) as c, SUM(total) as t, SUM(reschedule_count) as mv"
            .", SUM(CASE WHEN {$invoice} IS NOT NULL THEN 1 ELSE 0 END) as inv_c"
            .", SUM(COALESCE({$invoice}, 0)) as inv_t";

        if ($performance) {
            $select .= ", SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as done"
                .", SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as missed"
                .", SUM(CASE WHEN status IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as voided";
        }

        $query->selectRaw($select)->groupByRaw($expression);

        $order === 'asc'
            ? $query->orderByRaw($expression)
            : $query->orderByDesc('c');

        return $query->get()
            ->map(fn ($row) => $this->row($label($row->k), $row, $performance))
            ->all();
    }

    /**
     * التجميعُ على عمودٍ مرجعيّ — والتسميةُ تُجلَب دفعةً واحدة.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function groupedByReference(Builder $query, string $column, bool $performance): array
    {
        $query->whereNotNull($column);

        $rows = $this->grouped(clone $query, $column, $performance, null, fn ($k) => (string) $k);

        $labels = $this->labelsFor($column, array_column($rows, 'key'));

        return array_map(function ($row) use ($labels) {
            $row['label'] = $labels[$row['key']] ?? ('#'.$row['key']);

            return $row;
        }, $rows);
    }

    /**
     * تسمياتُ المفاتيح — استعلامٌ واحد للعمود كلِّه لا استعلامٌ لكلّ صفّ.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int|string, string>
     */
    protected function labelsFor(string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return match ($column) {
            'employee_id' => Employee::whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($e) => [$e->id => ($e->full_name ?: '#'.$e->id)])->all(),
            'client_id' => Client::whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($c) => [$c->id => $c->display_name ?: '#'.$c->id])->all(),
            'service_id' => BookableService::with('product:id,name')->whereIn('id', $ids)->get()
                ->mapWithKeys(fn ($s) => [$s->id => $s->displayName()])->all(),
            'product_id' => Product::whereIn('id', $ids)->pluck('name', 'id')->all(),
            // الجهةُ بنوعها: «منطقة الرياض — منطقة»، «الروضة — منفذ»
            'branch_id' => Branch::withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'name', 'node_type', 'is_territory'])
                ->mapWithKeys(fn ($b) => [$b->id => $b->name.' — '.$b->displayTypeLabel()])->all(),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    protected function row(string $label, $raw, bool $performance): array
    {
        $count = (int) $raw->c;

        $values = [
            'count' => $count,
            'total' => round((float) $raw->t, 2),
            'moves' => (int) $raw->mv,
            'invoiced' => (int) $raw->inv_c,
            'invoice_amount' => round((float) $raw->inv_t, 2),
        ];

        if ($performance) {
            $values['completed'] = (int) $raw->done;
            $values['no_show'] = (int) $raw->missed;
            $values['cancelled'] = (int) $raw->voided;
            $values['completion_rate'] = $count > 0 ? round(($raw->done / $count) * 100, 1) : 0;
        }

        return ['label' => $label, 'key' => $raw->k, 'values' => $values];
    }

    /**
     * سطرُ الإجمالي — الأعدادُ والمبالغ تُجمع، والنسبةُ تُحسب من مجاميعها.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, float|int>
     */
    protected function totalsOf(array $rows, array $columns): array
    {
        $totals = [];

        foreach ($columns as $column) {
            if ($column['type'] === 'percent') {
                continue;
            }

            $sum = array_sum(array_map(fn ($r) => $r['values'][$column['key']] ?? 0, $rows));
            $totals[$column['key']] = $column['type'] === 'money' ? round($sum, 2) : (int) $sum;
        }

        if (isset($totals['completed'], $totals['count'])) {
            $totals['completion_rate'] = $totals['count'] > 0
                ? round(($totals['completed'] / $totals['count']) * 100, 1)
                : 0;
        }

        return $totals;
    }

    // ════════════════════ المؤشّرات والرسوم ════════════════════

    /**
     * مؤشّراتُ أعلى الصفحة — استعلامٌ واحد على الحجوزات المرئيّة بالفلاتر نفسها.
     *
     * @return array<string, float|int>
     */
    protected function kpis(Request $request): array
    {
        $invoice = $this->invoiceAmountSql();

        $raw = $this->applyFilters($this->scopedQuery(), $request)
            ->selectRaw('COUNT(*) as c, SUM(total) as t, SUM(reschedule_count) as mv'
                .", SUM(CASE WHEN reschedule_count > 0 THEN 1 ELSE 0 END) as moved"
                .", SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as done"
                .", SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed"
                .", SUM(CASE WHEN status IN ('checked_in', 'in_progress') THEN 1 ELSE 0 END) as active"
                .", SUM(CASE WHEN status IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as voided"
                .", SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as missed"
                .", SUM(CASE WHEN {$invoice} IS NOT NULL THEN 1 ELSE 0 END) as inv_c"
                .", SUM(COALESCE({$invoice}, 0)) as inv_t"
                .", SUM(CASE WHEN status = 'completed' THEN COALESCE({$invoice}, 0) ELSE 0 END) as revenue")
            ->first();

        return [
            'count' => (int) ($raw->c ?? 0),
            'total' => round((float) ($raw->t ?? 0), 2),
            'completed' => (int) ($raw->done ?? 0),
            'confirmed' => (int) ($raw->confirmed ?? 0),
            'active' => (int) ($raw->active ?? 0),
            'cancelled' => (int) ($raw->voided ?? 0),
            'no_show' => (int) ($raw->missed ?? 0),
            'lost' => (int) ($raw->voided ?? 0) + (int) ($raw->missed ?? 0),
            'moves' => (int) ($raw->mv ?? 0),
            'moved' => (int) ($raw->moved ?? 0),
            'invoiced' => (int) ($raw->inv_c ?? 0),
            'invoice_amount' => round((float) ($raw->inv_t ?? 0), 2),
            'revenue' => round((float) ($raw->revenue ?? 0), 2),
        ];
    }

    /**
     * سلسلةُ رسم الأعمدة — يومياً حين يقصر المدى، وشهرياً حين يطول؛ والأيامُ
     * الخالية تُملأ بصفرٍ كي لا تُقرأ فجوةُ الرسم انقطاعاً في البيانات.
     *
     * `$rows` صفوفُ محور «يومي» أو «شهري» بحسب طول المدى — يحسبها
     * `index()` للرسم وإن لم يكن أيٌّ منهما المحورَ المعروض.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{mode:string, labels:array<int,string>, values:array<int,int>, keys:array<int,string>}
     */
    protected function timeline(array $rows, Carbon $from, Carbon $to): array
    {
        $daily = $from->diffInDays($to) <= 62;
        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['label']] = (int) $row['values']['count'];
        }

        $labels = $values = $keys = [];
        $cursor = $daily ? $from->copy()->startOfDay() : $from->copy()->startOfMonth();
        $end = $daily ? $to->copy()->startOfDay() : $to->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $daily ? $cursor->format('Y-m-d') : $cursor->format('Y-m');
            $keys[] = $key;
            $labels[] = $daily ? $cursor->format('d/m') : $cursor->format('m/Y');
            $values[] = $counts[$key] ?? 0;
            $daily ? $cursor->addDay() : $cursor->addMonth();
        }

        return ['mode' => $daily ? 'day' : 'month', 'labels' => $labels, 'values' => $values, 'keys' => $keys];
    }

    /**
     * شرائحُ رسم الحلقة — حالةٌ لكلّ شريحة بلونها من التعداد ونسبتها من الكلّ.
     *
     * @param  array<int, array<string, mixed>>  $rows  صفوفُ تقرير «حسب الحالة»
     * @return array<int, array{key:string,label:string,value:int,pct:int,color:string}>
     */
    protected function statusSlices(array $rows): array
    {
        $sum = array_sum(array_map(fn ($r) => (int) $r['values']['count'], $rows));

        return array_values(array_map(function ($row) use ($sum) {
            $status = ReservationStatus::from_value($row['key']);
            $count = (int) $row['values']['count'];

            return [
                'key' => $status->value,
                'label' => $status->label(),
                'value' => $count,
                'pct' => $sum > 0 ? (int) round($count / $sum * 100) : 0,
                'color' => $status->color(),
            ];
        }, $rows));
    }

    // ════════════════════ المدى الزمني ════════════════════

    /**
     * الفتراتُ السريعة — تُحسب في الخادم لا في المتصفّح: الحجوزاتُ تمتدّ إلى
     * المستقبل، فـ«هذا الشهر» يعني الشهرَ التقويميَّ كلَّه لا «آخر ٣٠ يوماً».
     *
     * @return array<string, string>
     */
    protected function periods(): array
    {
        return [
            'today' => __('reservations.reports.page.periods.today'),
            'week' => __('reservations.reports.page.periods.week'),
            'month' => __('reservations.reports.page.periods.month'),
            'quarter' => __('reservations.reports.page.periods.quarter'),
            'year' => __('reservations.reports.page.periods.year'),
            'custom' => __('reservations.reports.page.periods.custom'),
        ];
    }

    /** الفترةُ المضغوطة في الشريط — «هذا الشهر» حين لا مدى في الطلب. */
    protected function currentPeriod(Request $request): string
    {
        if ($request->filled('period')) {
            return (string) $request->input('period');
        }

        return $request->filled('date_from') || $request->filled('date_to') ? 'custom' : 'month';
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function range(Request $request): array
    {
        $preset = match ((string) $request->input('period', '')) {
            'today' => [today(), today()],
            'week' => [now()->startOfWeek(Carbon::SATURDAY), now()->endOfWeek(Carbon::FRIDAY)],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'quarter' => [now()->startOfMonth()->subMonths(2), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => null,
        };

        if ($preset !== null) {
            return $preset;
        }

        return [
            $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : now()->startOfMonth(),
            $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : now()->endOfMonth(),
        ];
    }

    protected function csvValue($value, string $type): string
    {
        return match ($type) {
            'money' => number_format((float) $value, 2, '.', ''),
            'percent' => $value === '' ? '' : $value.'%',
            default => (string) $value,
        };
    }

    /** @return array<string, mixed> */
    protected function definition(string $report): array
    {
        return self::definitions()[$report] ?? abort(404, __('reservations.errors.unknown_report'));
    }
}
