<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Http\Controllers\Controller;
use App\Models\PosSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Pos\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Reports\CashVarianceReport;
use Modules\Pos\Reports\CategorySalesReport;
use Modules\Pos\Reports\PaymentMethodReport;
use Modules\Pos\Reports\ProductSalesReport;
use Modules\Pos\Reports\ProfitabilityReport;
use Modules\Pos\Reports\ShiftSalesReport;
use Modules\Pos\Services\Session\CashDrawerService;

/**
 * تقاريرُ نقاط البيع — متحكّمٌ رفيع يفوّض إلى `Reports/*`.
 *
 * ═════════════════════ ما كان ═════════════════════
 *
 * ٢٬٠٩١ سطراً في ملفٍ واحد، **بلا أيّ فحص صلاحية إطلاقاً** — لا
 * `__construct`، ولا `middleware`، ولا `hasPermission` في سطرٍ واحد
 * (تحقّقتُ بالبحث: صفر تطابق). فأيُّ مستخدمٍ مصادقٍ عليه كان يفتح:
 *
 *   /POS/pos_reports/Prof  →  ربحيةُ كل وردية وكل تصنيف وكل منتج بالتكلفة
 *   /POS/pos_reports/Shift →  مبيعاتُ كل الورديات لكل الموظفين
 *
 * والصلاحيةُ `pos_view_reports` كانت في الكتالوج **ولا تُستدعى**.
 *
 * وفيه ستُّ دوالّ تُعيد كتابة الفلاتر والفترة والتجميع ودمجَ المرتجعات،
 * ونقطةُ تشخيصٍ مكشوفة (`quickTest()`) تطبع `print_r` لمخرجات القاعدة
 * مباشرةً، وأربعُ دوالّ مسجَّلةٍ في المسارات **غير موجودةٍ في الملف أصلاً**.
 */
class ReportsController extends Controller
{
    use RespectsAccountingPeriods;
    use ScopesToPosAccess;
    use UsesDocumentCurrency;

    /** سجلُّ التقارير — الإضافةُ هنا مدخلٌ واحد لا دالّةٌ جديدة. */
    private const REPORTS = [
        'category' => CategorySalesReport::class,
        'product' => ProductSalesReport::class,
        'shift' => ShiftSalesReport::class,
        'payment' => PaymentMethodReport::class,
        'profit' => ProfitabilityReport::class,
        'variance' => CashVarianceReport::class,
    ];

    public function __construct(private CashDrawerService $drawer)
    {
        /*
            ═══════════════ بوّابةُ الوحدة ═══════════════

            الدخولُ لمن يملك **أيَّ** صلاحيةِ نقاط بيع، لا لمن يملك صلاحيةً
            بعينها.

            كان الفحصُ بصلاحيةٍ واحدة، وهو يقلب المقصود: كاشيرٌ يملك البيعَ
            وحده يُردّ من باب الشاشة التي هي عمله، ومشرفٌ يملك التقارير
            وحدها كذلك. والحراسةُ الدقيقة موضعُها الدالّة لا الباب.

            والبادئاتُ ثلاثٌ لا واحدة (`pos.` و`pos_` و`points_sale_`):
            إغفالُ إحداها يردّ كلَّ من صلاحياتُه بالنمط القديم.
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

        /*
            ═══════════════ حراسةُ كل دالّة ═══════════════

            مطابقةٌ حرفياً لما يفرضه `Modules/Pos/routes/web.php`. والتكرارُ
            مقصود: الحارسُ على المسار وحده يسقط متى نُودي المتحكّمُ من مسارٍ
            جديد يُنسى تزويدُه، أو من وحدةٍ أخرى تستدعي الدالّة مباشرةً.
        */
        $this->middleware('permission.role:pos_view_reports', ['only' => ['index', 'xReport', 'zReport']]);
        $this->middleware('permission.role:pos_view_reports|pos.reports.profit|pos.reports.variance', ['only' => [
            'show',
        ]]);
        $this->middleware('permission.role:pos.reports.export', ['only' => ['export']]);
    }

    /** فهرسُ التقارير — كلٌّ منها يظهر لمن يملك صلاحيته وحده. */
    public function index()
    {
        $available = [];

        foreach (self::REPORTS as $key => $class) {
            $report = new $class(request());

            if (hasPermission($report->permission()) || $this->hasFullPosScope()) {
                $available[$key] = [
                    'key' => $key,
                    'title' => $report->title(),
                    'permission' => $report->permission(),
                ];
            }
        }

        return view('pos::reports.index', [
            'reports' => $available,
            'canViewZ' => hasPermission('pos_view_reports') || $this->hasFullPosScope(),
        ]);
    }

    /**
     * عرضُ تقريرٍ بمفتاحه.
     *
     * `make()` مرّةً واحدة: بناؤه مرّتين يعني تنفيذَ الاستعلامات مرّتين.
     * وأعمدةُ العرض هي أعمدةُ التصدير نفسُها — فلا يختلف ما يُقرأ على الشاشة
     * عمّا يُصدَّر إلى ملف.
     */
    public function show(Request $request, string $report)
    {
        $instance = $this->make($report);

        return view('pos::reports.show', array_merge($instance->run(), [
            'report' => $report,
            'title' => $instance->title(),
            'exportColumns' => $instance->exportColumns(),
            'totalLabels' => $instance->totalLabels(),
        ]));
    }

    /** تصديرُ تقريرٍ بمفتاحه. */
    public function export(Request $request, string $report)
    {
        return $this->make($report)->export();
    }

    private function make(string $report)
    {
        $class = self::REPORTS[$report] ?? null;

        if (! $class) {
            abort(404, 'تقريرٌ غير معروف.');
        }

        return new $class(request());
    }

    /* ═══════════════════════ تقرير Z ═══════════════════════ */

    /**
     * تقريرُ Z — الملخّصُ النهائي لجلسةٍ أو ليوم.
     *
     * **التقريرُ رقم واحد في أيّ نقطة بيع، ولم يكن موجوداً إطلاقاً.**
     *
     * وهو ما يُطبع عند إقفال الوردية ويُسلَّم مع العهدة: افتتاحٌ، ومبيعاتٌ
     * بالتفصيل، وتحصيلٌ بكل طريقة، وحركاتُ درج، ومرتجعات، وضريبة، ورصيدٌ
     * متوقَّعٌ وفعليٌّ وفرقُهما.
     *
     * ولم يكن ممكناً قبل الآن: التحصيلُ بكل طريقة يحتاج جدول المدفوعات (ولم
     * يكن يُكتب)، وحركاتُ الدرج تحتاج جدولها (ولم يكن موجوداً)، والرصيدُ
     * المتوقَّع يحتاج أن يشملهما.
     */
    public function zReport(Request $request, $sessionId)
    {
        if (! hasPermission('pos_view_reports') && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك بعرض تقارير نقاط البيع.');
        }

        $session = PosSession::with(['user:id,name', 'device:id,device_name', 'shift:id,name', 'branch:id,name', 'closedBy:id,name', 'confirmedBy:id,name'])
            ->findOrFail($sessionId);

        $this->authorizeSessionAccess($session);

        return view('pos::reports.z_report', [
            'session' => $session,
            'data' => $this->zReportData($session),
            'symbol' => $this->currencySymbol($session),
            'isFinal' => in_array($session->status, [PosSession::STATUS_CLOSED, PosSession::STATUS_PENDING_REVIEW], true),
        ]);
    }

    /**
     * تقريرُ X — نفسُ تقرير Z لكن لجلسةٍ **ما زالت مفتوحة**.
     *
     * الفرقُ اصطلاحيّ لا تقنيّ: X يُقرأ أثناء الوردية ولا يُقفلها، وZ
     * يُطبع عند الإقفال. ولذلك الدالّةُ واحدة والقالبُ يميّز.
     */
    public function xReport(Request $request)
    {
        if (! hasPermission('pos_view_reports') && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك بعرض تقارير نقاط البيع.');
        }

        $session = PosSession::active()->forUser(auth()->id())->first();

        if (! $session) {
            return back()->with('error', 'لا توجد جلسة نشطة لعرض تقرير X.');
        }

        return view('pos::reports.z_report', [
            'session' => $session->load(['user:id,name', 'device:id,device_name', 'shift:id,name', 'branch:id,name']),
            'data' => $this->zReportData($session),
            'symbol' => $this->currencySymbol($session),
            'isFinal' => false,
        ]);
    }

    /**
     * بياناتُ تقرير Z/X — أربعةُ استعلامات مجمَّعة، لا استعلامٌ لكل سطر.
     *
     * @return array<string, mixed>
     */
    private function zReportData(PosSession $session): array
    {
        // ١) التحصيلُ بكل طريقة — من جدول المدفوعات الذي لم يكن يُكتب
        $payments = DB::table('pos_order_payments')
            ->join('payment_methods', 'pos_order_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('pos_order_payments.session_id', $session->id)
            ->whereNull('pos_order_payments.deleted_at')
            ->where('pos_order_payments.status', 'completed')
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.is_online')
            ->select([
                'payment_methods.name',
                'payment_methods.is_online',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CAST(pos_order_payments.amount AS DECIMAL(18,2))) as total'),
                DB::raw('SUM(CAST(pos_order_payments.change_amount AS DECIMAL(18,2))) as change_total'),
            ])
            ->get();

        // ٢) الفواتيرُ والضريبة
        $invoices = DB::table('invoices')
            ->where('session_id', $session->id)
            ->groupBy('type')
            ->select([
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CAST(subtotal AS DECIMAL(18,2))) as subtotal'),
                DB::raw('SUM(CAST(tax_total AS DECIMAL(18,2))) as tax_total'),
                DB::raw('SUM(CAST(discount_amount AS DECIMAL(18,2))) as discount_total'),
                DB::raw('SUM(CAST(grand_total AS DECIMAL(18,2))) as grand_total'),
            ])
            ->get()
            ->keyBy('type');

        // ٣) أكثرُ الأصناف مبيعاً في الجلسة
        $topProducts = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.session_id', $session->id)
            ->where('invoices.type', 'pos')
            ->groupBy('invoice_items.item')
            ->select([
                'invoice_items.item',
                DB::raw('SUM(CAST(invoice_items.quantity AS DECIMAL(18,3))) as quantity'),
                DB::raw('SUM(CAST(invoice_items.total AS DECIMAL(18,2))) as total'),
            ])
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // ٤) حركاتُ الدرج
        $drawer = $this->drawer->summary($session);

        $sales = $invoices['pos'] ?? null;
        $returns = $invoices['returned'] ?? null;

        return [
            'sales' => [
                'count' => (int) ($sales->count ?? 0),
                'subtotal' => (float) ($sales->subtotal ?? 0),
                'tax' => (float) ($sales->tax_total ?? 0),
                'discount' => (float) ($sales->discount_total ?? 0),
                'total' => (float) ($sales->grand_total ?? 0),
            ],
            'returns' => [
                'count' => (int) ($returns->count ?? 0),
                'tax' => (float) ($returns->tax_total ?? 0),
                'total' => (float) ($returns->grand_total ?? 0),
            ],
            'net_sales' => round((float) ($sales->grand_total ?? 0) - (float) ($returns->grand_total ?? 0), 2),
            'payments' => $payments,
            'drawer' => $drawer,
            'top_products' => $topProducts,
            'cash' => [
                'opening' => (float) $session->opening_balance,
                'expected' => $session->computeExpectedCash(),
                'actual' => $session->actual_closing_balance !== null ? (float) $session->actual_closing_balance : null,
                'difference' => $session->difference !== null ? (float) $session->difference : null,
            ],
            'movements_detail' => PosCashMovement::forSession($session->id)
                ->approved()
                ->orderBy('movement_time')
                ->get(['reference_number', 'type', 'amount', 'reason', 'movement_time']),
        ];
    }
}
