<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CustomerTarget;
use Illuminate\Http\Request;
use Modules\Client\Http\Controllers\Concerns\EnforcesClientModuleSettings;

/**
 * تقرير توزيع العملاء على أهداف التقييم — يقرأ بيانات «أهداف العملاء»
 * (`CustomerTarget`) ولا يكتبها، لذا صلاحية العرض وحدها تكفيه. لكنّه كان
 * يعمل ويعرض التوزيع حتى لو أطفأ المدير قسم «أهداف العملاء» في «إعدادات
 * وحدات العملاء» — لا حارس اشتراكٍ (`client.feature`) ولا حارس قسمٍ
 * (`sectionBlock`) مثل بقيّة شاشات الهدف. صار عليه الاثنان.
 */
class CustomerTargetReportController extends Controller
{
    use EnforcesClientModuleSettings;
    use \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports;

    public function __construct()
    {
        /* صلاحيةُ هذا التقرير وحدَه.

           كانت التركيبةُ الثلاثية `clients_view_all_reports|clients_view_own_reports|client_reports_view`
           مكرَّرةً حرفياً في التسعة، فمن فُتح له تقريرٌ انفتحت له
           التسعةُ جميعاً. واثنتان منها صلاحيتا **نطاق** لا عملية،
           وقد صار النطاقُ محوراً مستقلاً يقرؤه `App\Support\DataScope`. */
        $this->middleware('permission:clients.reports.targets');
    }

    public function index(Request $request)
    {
        if ($block = $this->sectionBlock('customer_targets')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        /* الفرعُ النشط في الهيدر يحكم الصفحةَ كلَّها: الجدولَ والإحصائياتِ
           وتوزيعَ الأهداف. كانت الصفحةُ لا تقرؤه أصلاً، وكان التوزيعُ يعدّ
           عملاءَ المنشأة كلَّها بلا نطاقٍ ولا فرع. */
        $headerNode = $this->headerTreeNodeId();

        $pageScope = function ($q) use ($headerNode) {
            $this->applyClientVisibilityScope($q);

            if ($headerNode) {
                $q->inTreeNode($headerNode);
            }
        };

        // جلب الأهداف مع عدد العملاء لكل هدف — ضمن نطاق الصفحة
        $targets = CustomerTarget::withCount(['clients' => $pageScope])
            ->orderBy('priority', 'desc')
            ->get();

        // بناء الـ query الأساسي
        // الحالةُ والوضعُ عمودان في الجدول وفلتران في الشاشة (طلبُ المستخدم 2026-09-28)
        $query = Client::with(['customerTarget', 'branch', 'employee', 'status_client:id,name,color', 'registrationStage:id,name,color'])
            ->select('clients.*');

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // تطبيق الفلاتر
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

        /* تعريفٌ واحدٌ مع بقيّة تقارير العملاء (`applyClientFilters`):
           «تابع لـ» والهدفُ والحالةُ والوضعُ والتصنيفُ ومسؤولُ المبيعات.

           وكان «الفرع» هنا يطابق `clients.branch_id` — الفرعَ المحاسبيَّ
           المشتقَّ آلياً لا تبعيّةَ العميل، فعميلُ المنطقة يُنسب إلى الفرع
           الذي فوقها ويختفي عمّن يفلتر بمنطقته. */
        // «تابع لـ» يغلب الهيدر، وبدونه يحكم الهيدر — داخل applyClientFilters نفسِها
        $this->applyClientFilters($query, $request);

        // فلتر البحث النصي
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('clients.trade_name', 'like', "%{$search}%")
                    ->orWhere('clients.first_name', 'like', "%{$search}%")
                    ->orWhere('clients.last_name', 'like', "%{$search}%")
                    ->orWhere('clients.code', 'like', "%{$search}%")
                    ->orWhere('clients.phone', 'like', "%{$search}%");
            });
        }

        /* الطباعةُ والتصديرُ يحتاجان **كلَّ** الصفوف المطابقة لا صفحةَ
           الخمسين المعروضة: تطلبها الشاشةُ بـ`export=1` فتعود صفوفُ الجدول
           وحدَها — بالفلاتر والنطاق والفرع النشط نفسِها. */
        if ($request->boolean('export')) {
            return view('client::customers.customer_targets.ajax_table', [
                'clients' => $query->orderBy('clients.trade_name')->get(),
                'exportMode' => true,
            ]);
        }

        $clients = $query->orderBy('clients.trade_name')->paginate(50);

        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // الإحصائيات العامة (مستقلة عن الفلاتر)
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        /* «مستقلّة عن الفلاتر» لا تعني «مستقلّة عن النطاق»: الفلترُ
           اختيارُ المستخدم، والنطاقُ حدُّه. وعدّاً مطلقاً كهذا يفشي
           حجمَ قاعدة العملاء كاملةً لمن لا يرى منها إلّا عملاءَه. */
        $scoped = fn () => tap(Client::query(), $pageScope);

        $stats = [
            'total_clients' => $scoped()->count(),
            'evaluated_clients' => $scoped()->whereNotNull('customer_target_id')->count(),
            'unevaluated_clients' => $scoped()->whereNull('customer_target_id')->count(),
        ];

        return view('client::customers.customer_targets.report', [
            'clients' => $clients,
            'targets' => $targets,
            'stats' => $stats,
            'treeNodes' => $this->affiliationTreeNodes(),
            'registrationStages' => $this->registrationStageOptions(),
            // للشارة «الحالة: …» في الفلاتر المطبّقة — القائمةُ نفسُها من `status-filter-options`
            'statuses' => \App\Models\Statuses::clientFilterOptions(),
            'headerNode' => $headerNode ? \App\Models\Branch::find($headerNode) : null,
        ]);
    }
}
