<?php

namespace Modules\Purchases\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\Wallets\WalletReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Purchases\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;
use Modules\Purchases\Support\HeaderPickers;

/**
 * تقريرُ محافظ الموردين — الدفعاتُ المقدَّمة للموردين: أرصدتُها وسجلُّ حركاتها.
 *
 * مرآةُ «تقرير محافظ العملاء» (`CustomerWalletReportController`) بابَ باب:
 * المحتوى المشترك نفسُه (`reports.wallets.content`) والمحرّكُ نفسُه
 * ({@see WalletReportService}) — والطرفُ وحدَه مبدَّل.
 *
 * ══════════ أيُّ الموردين ══════════
 *
 * نطاقُ شاشة المحافظ نفسُها (`SupplierWalletController::index()`): محورُ
 * الأشخاص (`scopeSuppliers`)، ثم الجهةُ — «تابع لـ» في الطلب أو فرعُ الشريط
 * العلويّ وما تحته ({@see PurchaseReportScope::reportBranchIds()})، والمورّدُ
 * بلا فرعٍ يبقى ظاهراً كما في قوائم الموردين كلِّها.
 */
class SupplierWalletReportController extends Controller
{
    use PurchaseReportScope, RespectsAccountingPeriods {
        PurchaseReportScope::excludeClosedRanges insteadof RespectsAccountingPeriods;
    }
    use ScopesToEmployeeHierarchy;

    public function __construct()
    {
        /* صلاحيةُ التقرير، أو صلاحيةُ عرض المحافظ نفسها — كنظيره في العملاء */
        $this->middleware('permission:purchases.reports.supplier_wallets|purchases.supplier_wallet.view');
    }

    public function index(Request $request)
    {
        $service = WalletReportService::for('supplier');
        $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');
        $from = Carbon::parse($periodContext['start'])->toDateString();
        $to = min(Carbon::parse($periodContext['end'])->toDateString(), Carbon::today()->toDateString());

        $query = $this->scopeSuppliers(Supplier::query()->whereIn('suppliers.id', $service->partiesWithWallet() ?: [0]));

        if (! $this->scopeIsPeopleAxis() && ($branchIds = $this->reportBranchIds($request)) !== null) {
            $query->where(fn ($q) => $q->whereIn('suppliers.branch_id', $branchIds)->orWhereNull('suppliers.branch_id'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('suppliers.id', (int) $request->input('supplier_id'));
        }
        // حالةُ المورّد كما تقرؤها شاشةُ الموردين: `1` نشط وما عداه موقوف
        if (in_array($request->input('status'), ['active', 'inactive'], true)) {
            $request->input('status') === 'active'
                ? $query->where('suppliers.status', 1)
                : $query->where(fn ($q) => $q->where('suppliers.status', '!=', 1)->orWhereNull('suppliers.status'));
        }

        $suppliers = $query->with('branch:id,name')->get()->keyBy('id');
        $canWallet = hasPermission('purchases.supplier_wallet.view');

        $parties = $suppliers->map(fn (Supplier $supplier) => [
            'name' => trim((string) ($supplier->trade_name ?: trim($supplier->first_name.' '.$supplier->last_name))) ?: ('#'.$supplier->id),
            'code' => (string) ($supplier->number_suply ?? ''),
            'affiliation' => $supplier->branch->name ?? '—',
            'affiliation_type' => '',
            'status' => (int) $supplier->status === 1 ? 'نشط' : 'موقوف',
            'status_color' => (int) $supplier->status === 1 ? '#28c76f' : '#82868b',
            'url' => route('SupplierManagement.show', $supplier->id),
            'wallet_url' => $canWallet ? route('purchases.supplier-wallet.show', $supplier->id) : null,
        ])->all();

        $report = $service->screen(array_keys($parties), $parties, $from, $to, $request->only(WalletReportService::SCREEN_INPUTS));

        return view('purchases::purchases.report.purchaseReport.supplier_wallets', $report + $this->reportTreeContext($request) + [
            'periodContext' => $periodContext,
            'fromDate' => Carbon::parse($from),
            'toDate' => Carbon::parse($to),
            // فلترُ المورِّد يتبع فرعَ الشريط العلويّ — كبقيّة تقارير الموردين
            'supplierOptions' => HeaderPickers::suppliers(Supplier::orderBy('trade_name'))
                ->whereIn('suppliers.id', $service->partiesWithWallet() ?: [0])
                ->get(['id', 'trade_name', 'first_name', 'last_name', 'number_suply']),
        ]);
    }
}
