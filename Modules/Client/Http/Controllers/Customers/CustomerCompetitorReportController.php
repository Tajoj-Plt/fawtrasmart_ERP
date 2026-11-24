<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Competitor;
use App\Models\User;
use App\Models\VisitCompetitorObservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * تقريرُ المنافسين — ما رصده المندوبون من منافسين عند العملاء في الزيارات.
 *
 * المصدرُ `visit_competitor_observations` (قسمُ «رصد المنافسين» في ملاحظة
 * الزيارة): العميل، والمنافس، وعددُ أصوله عند العميل، وسعرُه، وصورةٌ، ومن رصد.
 *
 * بعرضين:
 *   • **الملخّص**: منافسٌ لكلّ صفّ — كم رصداً، وعند كم عميلاً، ومجموعُ أصوله،
 *     ومتوسّطُ سعره، وآخرُ رصد، وأكثرُ جهةٍ رُصد فيها.
 *   • **التفاصيل**: كلُّ رصدٍ بتاريخه وعميله وجهته ومندوبه.
 *
 * العملاءُ بفلاتر تقارير العملاء نفسِها ({@see \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports}):
 * فرعُ الشريط العلويّ و«تابع لـ» ونطاقُ البيانات والحالة والوضع والتصنيف.
 */
class CustomerCompetitorReportController extends Controller
{
    use \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports;
    use \Modules\Client\Http\Controllers\Concerns\RespectsAccountingPeriods;

    private const PER_PAGE = 50;

    public function __construct()
    {
        // صلاحيةُ التقرير، أو صلاحيةُ عرض كتالوج المنافسين نفسه
        $this->middleware('permission:clients.reports.competitors|clients_competitors_view');
    }

    public function index(Request $request)
    {
        $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');
        $from = Carbon::parse($periodContext['start'])->startOfDay();
        $to = Carbon::parse($periodContext['end'])->endOfDay();
        if ($to->isFuture()) {
            $to = Carbon::today()->endOfDay();
        }

        $clients = Client::query();
        $this->applyClientFilters($clients, $request);

        $observations = VisitCompetitorObservation::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('client_id', $clients->select('clients.id'))
            ->when($request->filled('competitor_id'), fn ($q) => $q->where('competitor_id', (int) $request->input('competitor_id')))
            ->when($request->filled('observer_id'), fn ($q) => $q->where('employee_id', (int) $request->input('observer_id')))
            ->with([
                'client:id,code,trade_name,first_name,last_name,status_id,registration_stage_id,tree_node_id,branch_id',
                'client.status_client:id,name,color',
                'client.registrationStage:id,name,color',
                'employee:id,name',
                'competitor:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        // الجهةُ في الشجرة لكلّ عميل — مرّةً لكلّ عميل لا لكلّ رصد
        $nodes = [];
        foreach ($observations->pluck('client')->filter()->unique('id') as $client) {
            $node = $client->resolveTreeNode();
            $nodes[$client->id] = ['name' => $node?->name ?? '—', 'path' => $node?->lineagePathLabel() ?? '—'];
        }

        $rows = $observations->map(fn (VisitCompetitorObservation $o) => [
            'id' => $o->id,
            'date' => $o->created_at?->format('Y-m-d H:i'),
            'competitor_key' => $o->competitor_id ? 'c'.$o->competitor_id : 'n'.mb_strtolower(trim((string) $o->competitor_name)),
            'competitor' => $o->competitor->name ?? ($o->competitor_name ?: '—'),
            'client_id' => $o->client_id,
            'client' => $o->client ? (trim((string) $o->client->trade_name) ?: trim($o->client->first_name.' '.$o->client->last_name)) : ('#'.$o->client_id),
            'code' => $o->client->code ?? '',
            'status' => $o->client?->status_client,
            'stage' => $o->client?->registrationStage,
            'affiliation' => $nodes[$o->client_id]['name'] ?? '—',
            'path' => $nodes[$o->client_id]['path'] ?? '—',
            'assets' => (int) ($o->asset_count ?? 0),
            'price' => $o->price !== null ? (float) $o->price : null,
            'observer' => $o->employee->name ?? '—',
            'notes' => trim((string) $o->notes),
            'photos' => $this->photoUrls($o),
        ]);

        // ── الملخّص: منافسٌ لكلّ صفّ، الأكثرُ رصداً أوّلاً ──
        $summary = $rows->groupBy('competitor_key')->map(function ($group) {
            $prices = $group->pluck('price')->filter(fn ($p) => $p !== null);

            return [
                'competitor' => $group->first()['competitor'],
                'observations' => $group->count(),
                'clients' => $group->pluck('client_id')->unique()->count(),
                'assets' => $group->sum('assets'),
                'avg_price' => $prices->isNotEmpty() ? round($prices->avg(), 2) : null,
                'last' => $group->max('date'),
                'top_affiliation' => $group->countBy('affiliation')->sortDesc()->keys()->first(),
                'affiliations' => $group->pluck('affiliation')->unique()->count(),
            ];
        })->sortByDesc('observations')->values();

        $view = $request->input('view') === 'details' ? 'details' : 'summary';

        // التصديرُ والطباعةُ — صفوفُ العرض الحاليّ كلُّها، بترتيب أعمدة الشاشة
        if ($request->input('export') === 'rows') {
            return response()->json(['rows' => $view === 'summary'
                ? $summary->values()->map(fn ($s, $i) => [
                    $i + 1, $s['competitor'], $s['observations'], $s['clients'], $s['assets'],
                    $s['avg_price'] !== null ? number_format($s['avg_price'], 2) : '—',
                    $s['top_affiliation'] ?? '—', $s['affiliations'], $s['last'] ?? '—',
                ])->all()
                : $rows->values()->map(fn ($r, $i) => [
                    $i + 1, $r['date'], $r['competitor'], trim($r['client'].' - '.$r['code'], ' -'),
                    $r['status']->name ?? 'بدون حالة', $r['stage']->name ?? 'بدون وضع', $r['path'], $r['assets'],
                    $r['price'] !== null ? number_format($r['price'], 2) : '—', $r['observer'], $r['notes'] ?: '—',
                ])->all(),
            ]);
        }

        $page = max(1, (int) $request->input('page', 1));
        $details = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('client::customers.CustomerReport.customer_competitors', [
            'reportView' => $view,
            'summary' => $summary,
            'details' => $details,
            // التصديرُ والطباعةُ من الصفوف كلِّها لا من صفحة التفاصيل
            'allRows' => $rows,
            'totals' => [
                'observations' => $rows->count(),
                'competitors' => $summary->count(),
                'clients' => $rows->pluck('client_id')->unique()->count(),
                'assets' => $rows->sum('assets'),
            ],
            'fromDate' => $from,
            'toDate' => $to,
            'periodContext' => $periodContext,
            'treeNodes' => $this->affiliationTreeNodes(),
            'competitors' => Competitor::orderBy('name')->get(['id', 'name', 'is_active']),
            'observers' => User::whereIn('id', VisitCompetitorObservation::query()->whereNotNull('employee_id')->distinct()->select('employee_id'))
                ->orderBy('name')->get(['id', 'name']),
            'registrationStages' => $this->registrationStageOptions(),
            'categories' => CategoriesClient::all(['id', 'name']),
            'selectedClient' => $request->filled('customer')
                ? Client::select('id', 'code', 'trade_name', 'first_name', 'last_name')->find($request->input('customer'))
                : null,
        ]);
    }

    /**
     * صورُ الرصد — `photo_path` القديمُ الواحد، و`photos_json` للمتعدّدة.
     *
     * @return array<int, string>
     */
    private function photoUrls(VisitCompetitorObservation $o): array
    {
        $paths = [];
        if ($o->photo_path) {
            $paths[] = $o->photo_path;
        }
        $json = is_array($o->photos_json) ? $o->photos_json : json_decode((string) $o->photos_json, true);
        foreach ((array) $json as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_map(
            fn ($p) => str_starts_with($p, 'http') ? $p : asset(str_contains($p, '/') ? $p : 'assets/uploads/visit_competitors/'.$p),
            array_unique($paths)
        ));
    }
}
