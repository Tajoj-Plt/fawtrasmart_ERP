<?php

namespace Modules\RentalManagement\Http\Controllers\RealEstate;

use App\Models\Branch;
use App\Support\AccountingPeriodFilter;
use App\Support\CurrencySettings;
use App\Support\DataScope;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Modules\RentalManagement\Exports\RealEstateReportExport;
use Modules\RentalManagement\Models\SavedReport;
use Modules\RentalManagement\Services\ReportService;
use Modules\RentalManagement\Support\RealEstateMoney;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

class ReportsController extends RealEstateController
{
    public function __construct(protected ReportService $reports)
    {
        $this->middleware('permission.role:realestate.reports.view|realestate.reports.financial', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:realestate.reports.export', ['only' => ['export', 'save', 'destroySaved']]);
    }

    public function index()
    {
        return view('rentalmanagement::realestate.reports.index', [
            'groups' => collect($this->visibleCatalog())->map(fn ($r, $key) => $r + ['key' => $key])->groupBy('group'),
            'saved' => SavedReport::where('user_id', auth()->id())->latest()->get(),
        ]);
    }

    /** ناتج `AccountingPeriodFilter::resolve()` لآخر طلب — يُمرَّر لقالب اختيار الفترة. */
    private array $period = [];

    public function show(Request $request, string $key)
    {
        $meta = $this->authorizeReport($key);
        $filters = $this->filters($request);

        // تقريرٌ يحتاج عميلاً يُعرض نموذجُه أولاً بدل جدولٍ فارغ بلا تفسير
        $ready = empty($meta['needs']) || filled($filters[$meta['needs']] ?? null);

        return view('rentalmanagement::realestate.reports.show', [
            'key' => $key,
            'meta' => $meta,
            'filters' => $filters,
            'ready' => $ready,
            'report' => $ready ? $this->reports->run($key, $filters, fn ($q, $t = null) => $this->scoped($q, $t)) : null,
            'period' => $this->period,
            'buildings' => $this->buildings(),
            'owners' => $this->owners(),
            // الفروع التي يحقّ له اختيارها وحدها — لا شجرة المنشأة كلّها
            'branches' => DataScope::branchOptions(Branch::query()->select('id', 'name')->orderBy('name'), ['id', 'name']),
        ]);
    }

    public function export(Request $request, string $key)
    {
        $meta = $this->authorizeReport($key);
        $filters = $this->filters($request);
        abort_if(! empty($meta['needs']) && blank($filters[$meta['needs']] ?? null), 422, 'اختر العميل أو المالك أولاً.');

        $report = $this->reports->run($key, $filters, fn ($q, $t = null) => $this->scoped($q, $t));

        OperationLog::record('exported', 're_report_log', null, sprintf('صُدّر تقرير العقارات «%s»', $meta['title']));

        // ترويسة الملف تسمّي العملة: خلايا التصدير أرقامٌ مجرّدة
        $currency = class_exists(RealEstateMoney::class) && method_exists(RealEstateMoney::class, 'code')
            ? RealEstateMoney::code()
            : CurrencySettings::code();

        return Excel::download(new RealEstateReportExport($meta['title'].' — المبالغ بعملة '.$currency, $report), 'realestate_'.$key.'_'.now()->format('Y-m-d_His').'.xlsx');
    }

    public function save(Request $request, string $key)
    {
        $meta = $this->authorizeReport($key);
        $data = $request->validate(['name' => 'required|string|max:100'], [], ['name' => 'اسم التقرير المحفوظ']);

        SavedReport::create([
            'user_id' => auth()->id(),
            'name' => $data['name'],
            'report_key' => $key,
            'filters' => array_filter($this->filters($request), 'filled'),
        ]);

        return back()->with('success', 'حُفظ إعداد التقرير «'.$meta['title'].'»');
    }

    public function destroySaved($id)
    {
        // المحفوظات شخصية: كلّ مستخدمٍ يرى ويحذف ما حفظه هو
        SavedReport::where('user_id', auth()->id())->findOrFail($id)->delete();

        return back()->with('success', 'حُذف التقرير المحفوظ');
    }

    /** التقارير المالية لحامل صلاحيتها فقط — تُخفى من المكتبة وتُردّ بالرابط المباشر. */
    private function visibleCatalog(): array
    {
        return array_filter(ReportService::catalog(), fn ($r) => hasPermission($r['financial'] ? 'realestate.reports.financial' : 'realestate.reports.view'));
    }

    private function authorizeReport(string $key): array
    {
        $meta = ReportService::catalog()[$key] ?? abort(404);
        abort_unless(isset($this->visibleCatalog()[$key]), 403, 'ليست لديك صلاحية هذا التقرير.');

        return $meta;
    }

    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'period_id' => 'nullable|string|max:30',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'building_id' => 'nullable|integer',
            'owner_id' => 'nullable|integer',
            'client_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'usage_type' => 'nullable|'.V::in(V::USAGE_TYPES),
            'occupancy_status' => 'nullable|'.V::in(V::OCCUPANCY),
        ], [], [
            'from_date' => 'من تاريخ', 'to_date' => 'إلى تاريخ', 'building_id' => 'العقار', 'owner_id' => 'المالك',
            'client_id' => 'العميل', 'branch_id' => 'الفرع', 'usage_type' => 'الاستخدام', 'occupancy_status' => 'حالة الإشغال',
        ]);

        // فرعٌ خارج ما يحقّ له اختياره يُسقَط — الصفوف مقيَّدةٌ بالنطاق على أيّ حال
        $selectable = DataScope::selectableBranchIds();

        if (! empty($filters['branch_id']) && $selectable !== null && ! in_array((int) $filters['branch_id'], $selectable, true)) {
            $filters['branch_id'] = null;
        }

        // عميلٌ خارج النطاق لا يُفتح كشفه
        if (! empty($filters['client_id']) && ! $this->scopedClients()->whereKey($filters['client_id'])->exists()) {
            abort(404);
        }

        /*
            الفترة المحاسبية — المصدر نفسه الذي تقرأ منه تقارير الحسابات.
            الأولوية كما هناك: مدًى كتبه المستخدم ← الفترة/السنة المختارة ←
            السنة المالية الجارية ← السنة الميلادية الجارية إن لم يُرسَم تقويم.
        */
        $period = AccountingPeriodFilter::resolve($request);
        $this->period = $period;
        $typed = filled($filters['from_date'] ?? null) || filled($filters['to_date'] ?? null);

        // التاريخان المطابقان لحدَّي الفترة المختارة ليسا مدًى حرّاً — هما الفترة نفسها
        if ($typed && ! empty($period['from']) && ($filters['from_date'] ?? null) === $period['from'] && ($filters['to_date'] ?? null) === $period['to']) {
            $typed = false;
        }

        if (! $typed) {
            if (! empty($period['all']) || ! empty($period['none'])) {
                $filters['from_date'] = '2000-01-01';
                $filters['to_date'] = now()->addYears(5)->endOfYear()->toDateString();
            } elseif (! empty($period['from']) && ! empty($period['to'])) {
                $filters['from_date'] = $period['from'];
                $filters['to_date'] = $period['to'];
            }
        }

        $filters['from_date'] = ($filters['from_date'] ?? null) ?: now()->startOfYear()->toDateString();
        $filters['to_date'] = ($filters['to_date'] ?? null) ?: now()->endOfYear()->toDateString();
        $filters['period_label'] = $typed ? null : ($period['label'] ?? null);

        return $filters;
    }
}
