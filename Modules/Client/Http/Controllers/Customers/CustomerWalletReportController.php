<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Statuses;
use App\Services\Wallets\WalletReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Client\Services\CustomerLedgerService;

/**
 * تقريرُ محافظ العملاء — أرصدتُها وسجلُّ حركاتها.
 *
 * شاشةٌ واحدة بعرضين:
 *
 *   • **الأرصدة**: عميلٌ لكل صفّ — رصيدُ محفظته أوّلَ المدى، وإيداعاتُه
 *     وسحوباتُه وما سُدِّد منها من فواتير وما رُدّ إليها، ثم رصيدُها آخرَ المدى.
 *   • **الحركات**: كلُّ حركةٍ في المدى بنوعها وفاتورتها وخزينتها ومُدخِلها.
 *
 * العملاءُ يُفلترون بفلاتر تقارير العملاء نفسها ({@see \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports}):
 * فرعُ الشريط العلويّ و«تابع لـ» ونطاقُ البيانات والحالة والتصنيف — فالعميلُ
 * الذي يُخفيه نطاقُ المستخدم في «أرصدة العملاء» يُخفيه هنا أيضاً.
 * والأرقامُ كلُّها من {@see WalletReportService}، المحرّكِ نفسه لتقرير محافظ الموردين.
 */
class CustomerWalletReportController extends Controller
{
    use \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports;
    use \Modules\Client\Http\Controllers\Concerns\RespectsAccountingPeriods;

    public function __construct()
    {
        /* صلاحيةُ التقرير، أو صلاحيةُ عرض المحافظ نفسها: من يرى رصيدَ كلِّ
           محفظةٍ في شاشتها لا يُحجب عنه مجموعُها. */
        $this->middleware('permission:clients.reports.wallets|clients.wallet.view');
    }

    public function index(Request $request)
    {
        $service = WalletReportService::for('client');
        $periodContext = $this->reportPeriodContext($request, 'from_date', 'to_date');
        $from = Carbon::parse($periodContext['start'])->toDateString();
        // لا بعد اليوم — كبقيّة تقارير العملاء ({@see CustomerLedgerService::dataCutoff()})
        $to = CustomerLedgerService::dataCutoff($periodContext['end']);

        // أصحابُ المحافظ وحدَهم، ثم فلاترُ تقارير العملاء ونطاقُها
        $query = Client::query()->whereIn('clients.id', $service->partiesWithWallet() ?: [0]);
        $this->applyClientFilters($query, $request);
        $clients = $query->with(['status_client:id,name,color'])->get()->keyBy('id');

        $parties = $clients->map(function (Client $client) {
            $node = $client->resolveTreeNode();

            return [
                'name' => preg_replace('/\s+/u', ' ', trim((string) $client->display_name)) ?: ('#'.$client->id),
                'code' => (string) ($client->code ?? ''),
                'affiliation' => $node?->name ?? '—',
                'affiliation_type' => $node?->displayTypeLabel() ?? '',
                'status' => $client->status_client->name ?? '—',
                'status_color' => $client->status_client->color ?? '#6c757d',
                'url' => route('clients.show', $client->id),
                'wallet_url' => hasPermission('clients.wallet.view') ? route('clients.wallet.show', $client->id) : null,
            ];
        })->all();

        $report = $service->screen(array_keys($parties), $parties, $from, $to, $request->only(WalletReportService::SCREEN_INPUTS));

        return view('client::customers.CustomerReport.customer_wallets', $report + [
            'periodContext' => $periodContext,
            'fromDate' => Carbon::parse($from),
            'toDate' => Carbon::parse($to),
            'treeNodes' => $this->affiliationTreeNodes(),
            'selectedClient' => $request->filled('customer')
                ? Client::select('id', 'code', 'trade_name', 'first_name', 'last_name')->find($request->input('customer'))
                : null,
            'statuses' => Statuses::assignable()->get(),
            'categories' => CategoriesClient::all(),
        ]);
    }
}
