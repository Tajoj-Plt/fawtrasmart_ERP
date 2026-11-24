<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Constants\planFeatures\PosPlanFeatures;
use App\Http\Controllers\Controller;
use App\Models\CashierDevice;
use App\Models\Invoice;
use App\Models\PosSession;
use App\Services\Fawtra\SubscriptionGate;
use App\Support\AccountingPeriodFilter;
use App\Support\AccountingSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Pos\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Pos\Models\PosCashMovement;

/**
 * الشاشةُ الرئيسية لنقاط البيع.
 *
 * ═══════════════ ما كان ═══════════════
 *
 * `/dashboard/pos` كان **مُحوِّلاً** لا شاشة: إغلاقةٌ في `routes/dashboard.php`
 * تجرّب أربعةَ مساراتٍ بالترتيب وتقفز إلى أوّل ما يملك المستخدمُ صلاحيتَه.
 *
 * وأثرُ ذلك أن المنشأة بلا صفحةٍ تُقرأ منها حالُ نقاط البيع: من يفتح الوحدة
 * يجد نفسَه في شاشة البيع أو في قائمة الجلسات بحسب صلاحياته، ولا يرى مبيعاتِ
 * اليوم ولا الجلساتِ المفتوحة ولا ما ينتظر اعتمادَه. والمشرفُ الذي يريد
 * النظرةَ العامة عليه أن يفتح أربعَ شاشاتٍ ويجمع بنفسه.
 *
 * ═══════════════ والبوّابة ═══════════════
 *
 * الدخولُ لمن يملك **أيَّ** صلاحيةِ نقاط بيع — وهو المبدأ نفسُه المطبَّق في
 * بواني الوحدة كلِّها. أمّا ما يُعرَض داخلها فيُحرَس بندًا بندًا: البطاقةُ
 * التي لا يملك صاحبُها صلاحيتَها لا تُحسب ولا تُعرض، فلا يُستعلم عن بياناتٍ
 * لن تُقرأ.
 */
class PosDashboardController extends Controller
{
    use RespectsAccountingPeriods, ScopesToPosAccess, UsesDocumentCurrency;

    public function __construct()
    {
        /*
            بوّابةُ الوحدة — أيُّ صلاحيةٍ تفتح، ولا صلاحيةَ بعينها تُشترط.

            وهذه الشاشةُ بالذات أولى الشاشات بهذا المبدأ: هي مقصدُ الدخول،
            فردُّ من يملك صلاحيةً واحدة عنها يعني ردَّه عن الوحدة كلِّها.
        */
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            $allowed = collect(PosPermissions::PREFIXES)
                ->contains(fn ($prefix) => $user?->hasAnyPermissionWithPrefix($prefix));

            if (! $allowed) {
                abort(403, 'لا تملك أي صلاحية على وحدة نقاط البيع.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $period = $this->posPeriod($request);

        return view('pos::dashboard.index', [
            'period' => $period,
            'stats' => $this->stats($period),
            'openSessions' => $this->openSessions(),
            'pendingSessions' => $this->pendingSessions(),
            'pendingMovements' => $this->pendingMovements(),
            'recentSessions' => $this->recentSessions(),
            'topProducts' => $this->topProducts($period),
            'alerts' => $this->alerts(),
            'symbol' => $this->currencySymbol(),
            'plan' => $this->planMap(),
        ]);
    }

    /**
     * أرقامُ اليوم والفترة — استعلامٌ واحدٌ مجموع لا استعلامٌ لكل رقم.
     *
     * والمبيعاتُ تُقرأ من `invoices` لا من `pos_sessions.total_sales`: ذاك
     * عمودٌ يُحدَّث بالتجميع وقد يتأخّر، وهذه هي المستندات نفسها.
     *
     * والعمودُ `grand_total` لا `total`: في `invoices` ثلاثةُ أعمدةِ مبالغ
     * (`subtotal` و`total` و`grand_total`)، و`grand_total` وحده هو المبلغُ
     * الذي يدفعه العميل — وهو ما تكتبه شاشةُ البيع وتقرأه شاشةُ المرتجع.
     *
     * @return array<string, float|int>
     */
    private function stats(array $period): array
    {
        $blank = ['today_sales' => 0.0, 'today_invoices' => 0, 'today_avg' => 0.0,
            'period_sales' => 0.0, 'period_invoices' => 0, 'returns' => 0.0];

        if (! canAny(['pos.sell', 'pos_view_reports', 'points_sale_view_own_sessions'])) {
            return $blank;
        }

        /*
            القاعدةُ على الجلسة لا على الفرع.

            كان `scopeToBranch('pos_branch_id')` وحده — فكاشيرٌ يقرأ «مبيعات
            اليوم» ويراها مبيعاتِ الفرع كلِّه. والرقمُ المجموعُ يُسرّب ما
            يُسرّبه الصفُّ المفرد، وأخفى: لا اسمَ فيه يُنبّه أنّ ما يقرأه
            ليس له.
        */
        $base = fn () => $this->scopeToVisibleSessionsOf(
            Invoice::query()->whereNotNull('session_id')->whereNull('voided_at')
        );

        $today = (clone $base())->whereDate('created_at', today())->where('type', '!=', 'returned');

        $todayRow = $today->selectRaw('COALESCE(SUM(grand_total), 0) AS s, COUNT(*) AS c')->first();

        $periodQuery = (clone $base())->where('type', '!=', 'returned');
        $this->scopeToPosPeriod($periodQuery, $period);
        $periodRow = $periodQuery->selectRaw('COALESCE(SUM(grand_total), 0) AS s, COUNT(*) AS c')->first();

        $returnsQuery = (clone $base())->where('type', 'returned');
        $this->scopeToPosPeriod($returnsQuery, $period);

        $invoices = (int) ($todayRow->c ?? 0);

        return [
            'today_sales' => (float) ($todayRow->s ?? 0),
            'today_invoices' => $invoices,
            'today_avg' => $invoices > 0 ? round((float) $todayRow->s / $invoices, 2) : 0.0,
            'period_sales' => (float) ($periodRow->s ?? 0),
            'period_invoices' => (int) ($periodRow->c ?? 0),
            'returns' => (float) $returnsQuery->sum('grand_total'),
        ];
    }

    /** الجلساتُ المفتوحة الآن — بعُهدتها ومَن يشغّلها. */
    private function openSessions()
    {
        if (! hasPermission('points_sale_view_own_sessions')) {
            return collect();
        }

        $query = PosSession::with(['user:id,name', 'device:id,device_name'])
            ->where('status', PosSession::STATUS_ACTIVE);

        // قاعدةٌ واحدة — وقيدُ الفرع فوقها زائدٌ يحجب جلسةَ مرؤوسٍ في فرعٍ آخر
        $this->scopeToVisibleSessions($query);

        return $query->latest('started_at')->limit(8)->get();
    }

    /**
     * الجلساتُ المنتظِرة تأكيدَ الإقفال — وهي ما يعطّل تسليم العهدة.
     *
     * كان القيدُ `scopeToBranch` وحده: عددُ الفرع كلِّه لكلِّ من يفتح
     * اللوحة. فيقرأ الكاشيرُ «٧ بانتظار التأكيد» وليس له منها شيء.
     */
    private function pendingSessions(): int
    {
        if (! canAny(['points_sale_confirm_close_sessions_own', 'points_sale_confirm_close_sessions_all'])) {
            return 0;
        }

        return (int) $this->scopeToVisibleUsers(
            PosSession::where('status', PosSession::STATUS_PENDING_REVIEW)
        )->count();
    }

    /**
     * حركاتُ الدرج المنتظِرة اعتماداً — لا تدخل الرصيدَ المتوقَّع حتى تُعتمد.
     *
     * والعدُّ بشجرة الإدارة لا بالفرع، وبنفس قاعدة شاشة الدرج — فلا يفترق
     * الرقمُ في اللوحة عن الصفوف التي يفتحها.
     */
    private function pendingMovements(): int
    {
        if (! hasPermission('pos.cash_drawer.approve')) {
            return 0;
        }

        return (int) $this->scopeToVisibleUsers(
            PosCashMovement::where('status', PosCashMovement::STATUS_PENDING), 'created_by'
        )->count();
    }

    /** آخرُ الجلسات المقفلة — بفروقاتها، فالفرقُ هو ما يُراجَع. */
    private function recentSessions()
    {
        if (! hasPermission('points_sale_view_own_sessions')) {
            return collect();
        }

        $query = PosSession::with(['user:id,name', 'device:id,device_name'])
            ->whereIn('status', [PosSession::STATUS_CLOSED, PosSession::STATUS_PENDING_REVIEW]);

        $this->scopeToVisibleSessions($query);

        return $query->latest('ended_at')->limit(6)->get();
    }

    /**
     * أكثرُ الأصناف مبيعاً في الفترة.
     *
     * يُحرَس بصلاحية التقارير: هذا تحليلٌ لا تشغيل، ومن يبيع ليس بالضرورة
     * من يُطّلع على أداء الأصناف.
     */
    private function topProducts(array $period)
    {
        if (! canAny(['pos_view_reports', 'pos_reports_view'])) {
            return collect();
        }

        if (! SubscriptionGate::permits(PosPlanFeatures::REPORTS)) {
            return collect();
        }

        $query = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->whereNotNull('invoices.session_id')
            ->whereNull('invoices.voided_at')
            ->where('invoices.type', '!=', 'returned');

        // نفسُ قاعدة الأرقام أعلاه — وإلّا عرَض للكاشير أصنافَ زملائه
        $this->scopeToVisibleSessionsOf($query, 'invoices.session_id');

        /*
            كان الشرطُ `is_numeric($period['period_id'])` — فسنةٌ كاملة
            (`"year:55"`) تُسقط القيدَ كلَّه بدل أن تُطبّق فتراتِها
            الاثنتَي عشرة. فتُعرض أصنافُ كلِّ السنين تحت عنوان سنةٍ واحدة.
        */
        if ($this->periodConstrains($period)) {
            AccountingPeriodFilter::applyRaw($query, $period, 'invoices.accounting_period_id');
        }

        return $query
            ->selectRaw('products.name AS name, SUM(invoice_items.quantity) AS qty, SUM(invoice_items.total) AS total')
            ->groupBy('products.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();
    }

    /**
     * تنبيهاتُ الإعداد — أشياءُ تعمل صامتةً بالخطأ حتى يُنتبه إليها.
     *
     * @return array<int, array{level:string, text:string, url:?string}>
     */
    private function alerts(): array
    {
        $alerts = [];

        // حسابا فرق الصندوق: بلا ربطٍ لا يُكتب قيدُ الفرق عند الإقفال، ولا
        // شيء يقول ذلك — يتراكم الفرقُ بين الخزينة والدفاتر بلا حسابٍ يحمله
        if (hasPermission('pos_manage_settings')) {
            $missing = collect(['pos_cash_over' => 'فائض درج الكاشير', 'pos_cash_short' => 'عجز درج الكاشير'])
                ->reject(fn ($label, $key) => AccountingSettings::isMapped($key));

            if ($missing->isNotEmpty()) {
                $alerts[] = [
                    'level' => 'danger',
                    'text' => 'لم يُربط بعد: '.$missing->values()->implode('، ')
                        .' — ولا يُكتب قيدُ فرق الصندوق عند إقفال الجلسة.',
                    'url' => \Route::has('AccountingSettings.index') ? route('AccountingSettings.index') : null,
                ];
            }
        }

        // جهازٌ بلا مستودع لا يُباع منه: لا يُعرف من أين تُخصم البضاعة
        if (canAny(['pos.devices.view', 'pos.devices.manage'])) {
            $query = CashierDevice::whereNull('store_id')->where('device_status', 'active');
            $this->scopeToBranch($query);
            $orphans = (int) $query->count();

            if ($orphans > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'text' => $orphans.' جهازاً نشطاً بلا مستودع — لا يمكن البيع منها.',
                    'url' => route('pos.settings.devices.index'),
                ];
            }
        }

        return $alerts;
    }

    /**
     * ما تشمله الباقة — البلاطةُ خارجها تُقفَل ولا تُخفى.
     *
     * @return array<string, bool>
     */
    private function planMap(): array
    {
        return [
            'terminal' => SubscriptionGate::permits(PosPlanFeatures::TERMINAL),
            'sessions' => SubscriptionGate::permits(PosPlanFeatures::SESSIONS),
            'cash_drawer' => SubscriptionGate::permits(PosPlanFeatures::CASH_DRAWER),
            'reports' => SubscriptionGate::permits(PosPlanFeatures::REPORTS),
            'settings' => SubscriptionGate::permits(PosPlanFeatures::SETTINGS),
        ];
    }
}
