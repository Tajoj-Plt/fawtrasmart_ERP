<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\OperationLog;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Manufacturing\Http\Controllers\Concerns\ScopesToBranch;
use Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Stock\Http\Controllers\Concerns\UsesDocumentCurrency;

/**
 * تقارير وحدة التصنيع.
 *
 * **ما كان**
 *
 * لم يكن للتصنيع تقريرٌ واحد. الشريط الجانبي واللوحة يشيران إلى
 * `ReportsProduction.index` — وهو مسارٌ في وحدة التقارير يستدعي
 * `RentalsController@production`، وهي دالةٌ لا وجود لها في تلك الفئة.
 * فكلّ نقرةٍ على «تقارير التصنيع» تُنتج 500.
 *
 * ولا تقريرَ غيره: خمسةٌ وسبعون مساراً للتصنيع، ولا واحدَ منها يجمع
 * أوامره ولا تكاليفه ولا حِمل محطّاته.
 *
 * **ما صار**
 *
 * خمسة تقارير على بيانات التصنيع نفسها، لكلٍّ منها مدًى زمني ومرشِّحاته،
 * ومجاميعُ في ذيل كل جدول. وتُشتقّ كلّها من `filters()` و`scope()` — فمدى
 * التاريخ يُقرأ ويُطبَّق في موضعٍ واحد لا في خمسة.
 *
 * والمدى الافتراضي هو الشهر الجاري لا «كل شيء»: تقريرٌ بلا مدًى يقرأ
 * الجدول كلّه ويكبر بلا حدّ.
 */
class ManufacturingReportsController extends Controller
{
    use RespectsAccountingPeriods, ScopesToBranch, UsesDocumentCurrency;

    /** الصلاحية القائمة فعلاً — لا اسمٌ مخترع. */
    protected const PERMISSION = 'stock.reports.manufacturing';

    /**
     * حالات أمر التصنيع — نصّية كما يخزّنها العمود.
     *
     * كانت أرقاماً (0..3) بينما العمود ENUM نصّي، فيُعرض `pending` نيّئاً
     * في التقرير، ومرشِّح «مسودّة» يقارن نصّاً بصفرٍ فيُرجع في MySQL غير
     * الصارم كلَّ السجلات غير الرقمية.
     */
    public static function statuses(): array
    {
        return collect(\App\Enums\ManufacturOrderStatus::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->all();
    }

    /**
     * الأمرُ المُتَمّ (مكتمل أو مغلق) — وحده يحمل تكلفةً فعلية وكميةً منتَجة.
     *
     * ما قبل الإتمام تقديرٌ من أسعارٍ كُتبت في الأمر، وما أُلغي لا يُحسب
     * أصلاً. فكلُّ رقمِ مالٍ أو كميةٍ في هذه التقارير يمرّ بهذا الشرط.
     */
    protected const DONE = "o.status IN ('completed','closed')";

    /** مركز التقارير. */
    public function index(): Renderable
    {
        $this->authorizeReports();

        return view('manufacturing::reports.index', [
            'summary' => $this->summary(),
        ]);
    }

    /**
     * أوامر التصنيع — عددها وكمّياتها وحالاتها.
     *
     * ── ما كان ──────────────────────────────────────────────────────
     *
     *  · الأمرُ الملغي يدخل المجاميع: كميتُه في «المخطَّطة» وتكلفتُه في
     *    «إجمالي التكلفة».
     *  · كمياتُ أصنافٍ مختلفة تُجمع في رقمٍ واحد — مليلتراتُ عطرٍ سائب
     *    مع زجاجاتٍ معبّأة — فيخرج «١٦٬٠٩٨» لا وحدةَ له.
     *  · تكلفةُ المكتمل (فعلية) تُجمع إلى تكلفة ما لم يبدأ (تقديرية).
     *
     * ── ما صار ──────────────────────────────────────────────────────
     *
     *  · الملغي خارج كلِّ مجموع، ولا يُسرَد إلّا لمن رشّح بحالته صراحةً.
     *  · الكمياتُ بالصنف (`byProduct`)؛ ومجموعُ الكمية في الذيل لا يظهر
     *    إلّا حين يكون في النتيجة صنفٌ واحد.
     *  · المالُ بعملته، والفعليُّ منه مفصولٌ عن التقديري.
     */
    public function orders(Request $request): Renderable
    {
        $this->authorizeReports();

        $f = $this->filters($request);
        $cur = $this->currencyExpr('o');
        $done = self::DONE;

        $rows = $this->scope($f, 'listing')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->select(
                'o.id', 'o.code', 'o.name', 'o.from_date', 'o.to_date', 'o.status',
                'o.quantity', 'o.actual_quantity', 'o.last_total_cost',
                'p.name as product_name'
            )
            ->selectRaw("{$cur} as currency_code")
            ->orderByDesc('o.from_date')
            ->orderByDesc('o.id')
            ->paginate($f['per_page'])
            ->withQueryString();

        // المالُ بعملته — لا يُجمع ريالٌ إلى دولار
        $totals = $this->scope($f)
            ->groupBy('currency_code')
            ->selectRaw(
                "{$cur} as currency_code,
                 COUNT(*) n,
                 SUM(CASE WHEN {$done} THEN 1 ELSE 0 END) done_n,
                 SUM(CASE WHEN {$done} THEN 0 ELSE 1 END) open_n,
                 COALESCE(SUM(CASE WHEN {$done} THEN o.last_total_cost END),0) actual_cost,
                 COALESCE(SUM(CASE WHEN {$done} THEN NULL ELSE o.last_total_cost END),0) open_cost"
            )
            ->orderBy('currency_code')
            ->get();

        // الكمياتُ بالصنف — الموضعُ الوحيد الذي يصحّ فيه جمعُها
        $byProduct = $this->scope($f)
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->groupBy('o.product_id', 'p.name')
            ->groupBy('currency_code')
            ->selectRaw(
                "o.product_id, p.name as product_name, {$cur} as currency_code,
                 COUNT(*) n,
                 SUM(CASE WHEN {$done} THEN 1 ELSE 0 END) done_n,
                 COALESCE(SUM(o.quantity),0) planned,
                 COALESCE(SUM(CASE WHEN {$done} THEN o.quantity END),0) done_planned,
                 COALESCE(SUM(CASE WHEN {$done} THEN COALESCE(o.actual_quantity, o.quantity) END),0) produced,
                 COALESCE(SUM(CASE WHEN {$done} THEN o.last_total_cost END),0) actual_cost"
            )
            ->orderBy('p.name')
            ->limit(200)
            ->get();

        // عدُّ الحالات لا يتبع مرشِّح الحالة: هو سياقُ الصفحة، والملغي فيه
        // عددٌ لا مالٌ ولا كمية
        $byStatus = $this->scope($f, 'any')
            ->groupBy('o.status')
            ->selectRaw('o.status, COUNT(*) n')
            ->pluck('n', 'status');

        return $this->render('orders', $f, [
            'rows' => $rows,
            'totals' => $totals,
            'byProduct' => $byProduct,
            'byStatus' => $byStatus,
            // صنفٌ واحد في النتيجة ← يصحّ مجموعُ الكمية في الذيل
            'singleProduct' => $byProduct->pluck('product_id')->unique()->count() === 1,
        ]);
    }

    /**
     * تكاليف الإنتاج — الفعليُّ المسجَّل عند الإتمام، لا ما كُتب في الأمر.
     *
     * ── ما كان ──────────────────────────────────────────────────────
     *
     * التقريرُ يجمع `raw_total` — وهو (الكمية × السعر الذي كتبه المستخدم
     * في الأمر). والمخزونُ يُخرج المادةَ بتكلفتها المخزَّنة، والقيدُ يُرحَّل
     * بها. فأمرُ تعبئةٍ كُتب سعرُ خامِه صفراً يظهر هنا بـ٤٨٤٫٥٠ وقد حُمِّل
     * في الدفتر بـ١٬٦١٠٫٥٠ — وتقريرُ التكاليف لا يوافق لا أمرَه ولا قيدَه.
     * وكان الملغيُّ يدخل المجاميع، والمكتملُ يُجمع إلى ما لم يبدأ.
     *
     * ── ما صار ──────────────────────────────────────────────────────
     *
     *   المُتَمّ (مكتمل/مغلق)
     *     الإجمالي   ← `last_total_cost`: ما حُمِّل فعلاً عند الإتمام، وهو
     *                  المصدرُ الواحد لإجمالي الأمر (صافياً من المرتجع).
     *     المصروفات  ← `expenses_total`، والتشغيل ← `manu_total`.
     *     الخام      ← الإجمالي − المصروفات − التشغيل؛ فيوافق مجموعُ
     *                  الأعمدة الإجماليَّ دائماً. (ويساوي مجموعَ
     *                  `raw_actual_total` ما لم يُرجَع من المواد شيء.)
     *     الوحدة     ← الإجمالي ÷ `actual_quantity`.
     *     المخطَّط    ← بأسعار الأمر (`raw_total`)، والانحرافُ الفرقُ بينهما.
     *
     *   المفتوح (جديد/استُلمت مواده/قيد التنفيذ) ← **تقديري**، ويُوسَم بذلك:
     *     الخام بـ`raw_actual_total` لما صُرف و`raw_total` لما لم يُصرف،
     *     ولا يدخل مجاميعَ الفعلي.
     *
     *   الملغي ← خارج التقرير؛ يُسرَد لمن رشّح بحالته، بلا مجاميع.
     *
     * وبنودُ الأمر تُجمَّع في استعلامٍ فرعي (صفٌّ لكل أمر) قبل الوصل —
     * فلا يتضاعف `last_total_cost` بعدد البنود.
     */
    public function costs(Request $request): Renderable
    {
        $this->authorizeReports();

        $f = $this->filters($request);
        $cur = $this->currencyExpr('o');
        $done = self::DONE;

        $lines = 'COALESCE(i.raw_actual,0) + COALESCE(i.expense_cost,0) + COALESCE(i.labor_cost,0)';
        $planned = 'COALESCE(i.raw_planned,0) + COALESCE(i.expense_cost,0) + COALESCE(i.labor_cost,0)';
        // أمرٌ أُتِمّ قبل أن يُحفظ إجماليُّه (قديم) يُقرأ من بنوده
        $actual = "(CASE WHEN COALESCE(o.last_total_cost,0) > 0 THEN o.last_total_cost ELSE {$lines} END)";

        $rows = $this->scope($f, 'listing')
            ->leftJoinSub($this->itemTotals(), 'i', 'i.manufactur_order_id', '=', 'o.id')
            ->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->select('o.id', 'o.code', 'o.name', 'o.status', 'o.quantity', 'o.actual_quantity', 'p.name as product_name')
            ->selectRaw(
                "{$cur} as currency_code,
                 COALESCE(i.raw_actual,0) raw_lines,
                 COALESCE(i.raw_unissued,0) raw_unissued,
                 COALESCE(i.expense_cost,0) expense_cost,
                 COALESCE(i.labor_cost,0) labor_cost,
                 {$planned} planned_total,
                 CASE WHEN {$done} THEN {$actual} ELSE {$lines} END cost_total"
            )
            ->orderByDesc('o.id')
            ->paginate($f['per_page'])
            ->withQueryString();

        $rows->getCollection()->transform(function ($row) {
            $isDone = in_array($row->status, ['completed', 'closed'], true);
            $total = (float) $row->cost_total;

            $row->basis = $row->status === 'cancelled' ? 'cancelled' : ($isDone ? 'actual' : 'estimated');
            // الخام صافياً: ما حُمِّل على الأمر بعد المصروفات والتشغيل
            $row->raw_cost = $isDone
                ? $total - (float) $row->expense_cost - (float) $row->labor_cost
                : (float) $row->raw_lines;
            $row->variance = $isDone ? $total - (float) $row->planned_total : null;

            // المقسومُ عليه: المنتَجُ فعلاً للمُتَمّ، والمخطَّطُ لما لم يُتَمّ
            $row->base_qty = $isDone && (float) $row->actual_quantity > 0
                ? (float) $row->actual_quantity
                : (float) $row->quantity;
            $row->unit_cost = $row->base_qty > 0 ? $total / $row->base_qty : null;

            return $row;
        });

        $totals = $this->scope($f)
            ->leftJoinSub($this->itemTotals(), 'i', 'i.manufactur_order_id', '=', 'o.id')
            ->groupBy('currency_code')
            ->selectRaw(
                "{$cur} as currency_code,
                 SUM(CASE WHEN {$done} THEN 1 ELSE 0 END) done_n,
                 COALESCE(SUM(CASE WHEN {$done} THEN {$actual} END),0) actual_total,
                 COALESCE(SUM(CASE WHEN {$done} THEN i.expense_cost END),0) expense_cost,
                 COALESCE(SUM(CASE WHEN {$done} THEN i.labor_cost END),0) labor_cost,
                 COALESCE(SUM(CASE WHEN {$done} THEN {$planned} END),0) planned_total,
                 SUM(CASE WHEN {$done} THEN 0 ELSE 1 END) open_n,
                 COALESCE(SUM(CASE WHEN {$done} THEN NULL ELSE {$lines} END),0) open_total"
            )
            ->orderBy('currency_code')
            ->get()
            ->map(function ($t) {
                $t->raw_cost = (float) $t->actual_total - (float) $t->expense_cost - (float) $t->labor_cost;
                $t->variance = (float) $t->actual_total - (float) $t->planned_total;

                return $t;
            });

        return $this->render('costs', $f, ['rows' => $rows, 'totals' => $totals]);
    }

    /**
     * حِمل محطّات العمل — ساعاتها وتكلفتها عبر الأوامر.
     *
     * ── ما كان ──────────────────────────────────────────────────────
     *
     * المحطّةُ تُوصَل ببنود التشغيل ثمّ بالأوامر، والمدى شرطٌ في وصل
     * الأوامر وحده. فالبندُ يبقى في الجمع ولو كان أمرُه خارج المدى أو
     * ملغياً: «ساعات التشغيل» و«تكلفة الحِمل» هما حِملُ المحطّة منذ
     * نشأتها أيّاً كان المدى المكتوب فوق الجدول — وعمودُ «أوامر» وحده
     * يتبع المدى، فيقرأ المرء «٠ أوامر، ٣ ساعات، ٧٥٫٠٠».
     *
     * ── ما صار ──────────────────────────────────────────────────────
     *
     * الحِملُ يُحسب في استعلامٍ فرعي من بنود التشغيل موصولةً بأوامرها:
     * ضمن المدى والفرع، وبلا الملغي. ثمّ تُوصَل به المحطّاتُ `leftJoin` —
     * فالمحطّةُ الخاملة تبقى في الجدول («بلا حِمل» جوابٌ لا غياب).
     *
     * والتكلفةُ `manu_total`: ساعاتُ البند × سعرُ ساعة المحطّة كما حُمِّلت
     * على الأمر. والحِملُ بعملة الأمر، فالمحطّةُ التي عملت لأوامرَ بعملتين
     * تظهر في صفّين.
     */
    public function workstations(Request $request): Renderable
    {
        $this->authorizeReports();

        $f = $this->filters($request);
        $done = self::DONE;

        $load = $this->scope($f)
            ->join('manufactur_orders_items as i', 'i.manufactur_order_id', '=', 'o.id')
            ->whereNotNull('i.workstation_id')
            ->groupBy('i.workstation_id')
            ->groupBy('load_currency')
            ->selectRaw(
                "i.workstation_id, {$this->currencyExpr('o')} as load_currency,
                 COUNT(DISTINCT o.id) orders_count,
                 COALESCE(SUM(i.operating_time),0) operating_time,
                 COALESCE(SUM(i.manu_total),0) load_cost,
                 COALESCE(SUM(CASE WHEN {$done} THEN i.operating_time END),0) done_time,
                 COALESCE(SUM(CASE WHEN {$done} THEN i.manu_total END),0) done_cost"
            );

        $rows = $this->stations($f)
            ->leftJoinSub($load, 'l', 'l.workstation_id', '=', 's.id')
            ->select('s.id', 's.name', 's.code', 's.unit', 's.total_cost', 's.cost_wages')
            ->selectRaw(
                "COALESCE(l.load_currency, {$this->currencyExpr('s')}) as currency_code,
                 COALESCE(l.orders_count,0) orders_count,
                 COALESCE(l.operating_time,0) operating_time,
                 COALESCE(l.load_cost,0) load_cost,
                 COALESCE(l.done_time,0) done_time,
                 COALESCE(l.done_cost,0) done_cost"
            )
            ->orderByDesc('load_cost')
            ->orderBy('s.code')
            ->paginate($f['per_page'])
            ->withQueryString();

        // المجاميعُ على النتيجة كلِّها لا على الصفحة المعروضة
        $totals = $this->stations($f)
            ->joinSub($load, 'l', 'l.workstation_id', '=', 's.id')
            ->groupBy('l.load_currency')
            ->selectRaw(
                'l.load_currency as currency_code,
                 COUNT(DISTINCT s.id) stations,
                 COUNT(DISTINCT s.unit) units,
                 COALESCE(SUM(l.operating_time),0) operating_time,
                 COALESCE(SUM(l.load_cost),0) load_cost,
                 COALESCE(SUM(l.done_cost),0) done_cost'
            )
            ->orderBy('currency_code')
            ->get();

        return $this->render('workstations', $f, [
            'rows' => $rows,
            'totals' => $totals,
            'loadedStations' => (int) $this->stations($f)
                ->joinSub($load, 'l', 'l.workstation_id', '=', 's.id')
                ->distinct()
                ->count('s.id'),
        ]);
    }

    /**
     * الفاقد ونواتج نهاية العمر.
     *
     * تُجمَع من `end_life_*` في بنود الأمر — وهي المخرجات الجانبية: هالكٌ
     * أو ناتجٌ ثانوي. وتُقرأ بصنفها لا ببندها، فالسؤال «كم فقدنا من هذا
     * الصنف» لا «كم بنداً».
     *
     * والهالكُ لا يدخل المخزون إلّا عند إتمام الأمر (إذنُ الهالك يُكتب
     * في `finish`) — فما على أمرٍ مُتَمّ **فعليٌّ** يوافق حركةَ المخزون،
     * وما على أمرٍ مفتوح **متوقَّع** لم يدخل بعد. يُفصلان في عمودين، ولا
     * يدخل المتوقَّعُ مجموعَ القيمة. والملغي خارج التقرير. وشرطُ
     * `end_life_quantity > 0` هو شرطُ الإتمام نفسُه لكتابة سطر الإذن.
     */
    public function waste(Request $request): Renderable
    {
        $this->authorizeReports();

        $f = $this->filters($request);
        $cur = $this->currencyExpr('o');
        $done = self::DONE;

        $base = fn () => $this->scope($f)
            ->join('manufactur_orders_items as i', 'i.manufactur_order_id', '=', 'o.id')
            ->whereNotNull('i.end_life_product_id')
            ->where('i.end_life_quantity', '>', 0);

        $rows = $base()
            ->leftJoin('products as p', 'p.id', '=', 'i.end_life_product_id')
            ->groupBy('p.id', 'p.name', 'i.end_life_waste_type')
            ->groupBy('currency_code')
            ->selectRaw(
                "p.id, p.name as product_name, i.end_life_waste_type, {$cur} as currency_code,
                 COUNT(DISTINCT o.id) orders_count,
                 COALESCE(SUM(CASE WHEN {$done} THEN i.end_life_quantity END),0) qty,
                 COALESCE(SUM(CASE WHEN {$done} THEN i.end_life_total END),0) value,
                 COALESCE(SUM(CASE WHEN {$done} THEN NULL ELSE i.end_life_quantity END),0) open_qty,
                 COALESCE(SUM(CASE WHEN {$done} THEN NULL ELSE i.end_life_total END),0) open_value"
            )
            ->orderByDesc('value')
            ->orderBy('p.name')
            ->paginate($f['per_page'])
            ->withQueryString();

        $totals = $base()
            ->groupBy('currency_code')
            ->selectRaw(
                "{$cur} as currency_code,
                 COALESCE(SUM(CASE WHEN {$done} THEN i.end_life_quantity END),0) qty,
                 COALESCE(SUM(CASE WHEN {$done} THEN i.end_life_total END),0) value,
                 COALESCE(SUM(CASE WHEN {$done} THEN NULL ELSE i.end_life_quantity END),0) open_qty,
                 COALESCE(SUM(CASE WHEN {$done} THEN NULL ELSE i.end_life_total END),0) open_value"
            )
            ->orderBy('currency_code')
            ->get();

        return $this->render('waste', $f, [
            'rows' => $rows,
            'totals' => $totals,
            // مجموعُ الكمية لا يصحّ إلّا لصنفٍ واحد
            'singleProduct' => (int) $base()->distinct()->count('i.end_life_product_id') === 1,
        ]);
    }

    /**
     * قوائم المواد.
     *
     * لا مدًى زمنياً هنا: قائمة المواد تعريفٌ لا حركة. فيُخفى مرشِّح
     * التاريخ في القالب بدل أن يُعرض ولا يفعل شيئاً.
     */
    public function boms(Request $request): Renderable
    {
        $this->authorizeReports();

        $f = $this->filters($request);
        $cur = $this->currencyExpr('m');

        // نطاقُ الفرع على جدول الوصفات — وفلترُ «تابع لـ» يضيق داخله
        $base = fn () => $this->narrowToRequestedBranch(
            $this->scopeRawToActiveBranch(DB::table('production_materials as m'), 'm.branch_id'),
            'm.branch_id',
            $f
        )
            ->when($f['currency'], fn ($q) => $q->whereRaw("{$cur} = ?", [$f['currency']]))
            ->when($f['q'], fn ($q) => $q->where(function ($w) use ($f) {
                $w->where('m.name', 'like', '%'.$f['q'].'%')
                    ->orWhere('m.code', 'like', '%'.$f['q'].'%');
            }));

        $rows = $base()
            ->leftJoin('production_materials_items as mi', 'mi.production_material_id', '=', 'm.id')
            ->leftJoin('products as p', 'p.id', '=', 'm.product_id')
            ->groupBy('m.id', 'm.name', 'm.code', 'm.quantity', 'm.last_total_cost', 'm.status', 'm.currency', 'p.name')
            ->selectRaw(
                "m.id, m.name, m.code, m.quantity, m.last_total_cost, m.status, {$cur} as currency_code,
                 p.name as product_name,
                 COUNT(mi.id) items_count,
                 COALESCE(SUM(mi.raw_total),0) raw_cost"
            )
            ->orderBy('m.code')
            ->paginate($f['per_page'])
            ->withQueryString();

        return $this->render('boms', $f, [
            'rows' => $rows,
            'noDates' => true,
            'activeCount' => (int) $base()->where('m.status', 1)->count(),
        ]);
    }

    // ───────────────────────── المشترك ─────────────────────────

    /**
     * المرشِّحات المقروءة من الطلب.
     *
     * ── المدى الافتراضي ─────────────────────────────────────────────
     *
     * كان `startOfMonth()`/`endOfMonth()` مكتوبَين في الشيفرة — فلا علاقةَ
     * لما يعرضه التقرير بالفترة التي تعمل المنشأة عليها. منشأةٌ سنتُها
     * المالية تبدأ في يوليو، أو فترتُها الجارية ربعٌ لا شهر، تفتح
     * «تقارير التصنيع» فتقرأ شهراً تقويمياً لا يقابل شيئاً في دفترها.
     *
     * صار المصدرُ `reportDateRange()` من
     * [\Modules\Stock\Http\Controllers\Concerns\RespectsAccountingPeriods]
     * — وهو المصدرُ نفسُه الذي تقرأ منه تقاريرُ المخزون والحسابات، بترتيب
     * التفضيل: الفترةُ المحاسبية التي يقع فيها اليوم، ثمّ نطاقٌ مشتقٌّ من
     * `default_period_type`، ثمّ الشهرُ الجاري احتياطاً أخيراً. ووحدتان
     * تُجيبان عن «أيّ فترة؟» بجوابين مختلفين تُخرجان تقريرَ إنتاجٍ لا
     * يوافق دفترَه.
     *
     * وما يكتبه المستخدم يتقدّم دائماً — من كتب تاريخين فقد طلبهما.
     *
     * ── العملة ──────────────────────────────────────────────────────
     *
     * كانت `filterCurrency()` تردّ العملةَ الافتراضية متى غاب `currency`
     * عن الطلب. ومرشِّحُ العملة أُزيل من الشريط — فهو غائبٌ دائماً،
     * والتقاريرُ كلُّها محصورةٌ في الافتراضية صامتةً: أمرٌ بعملةٍ أخرى لا
     * يظهر في أيّ تقرير ولا شيء يقول لماذا. الآن: لا عملةَ في الطلب ←
     * كلُّ العملات، والمالُ يُجمع ويُعرض بعملته.
     *
     * ── الحالة ──────────────────────────────────────────────────────
     *
     * قيمةٌ ليست من حالات الأمر تُهمَل بدل أن تُقارَن بعمود ENUM.
     */
    protected function filters(Request $request): array
    {
        $perPage = (int) $request->input('per_page', 25);

        // `from`/`to` هما اسما الحقلين في شاشات هذه الوحدة
        $range = $this->reportDateRange($request, 'from', 'to');

        $status = (string) $request->input('status');
        $currency = trim((string) $request->input('currency'));

        return [
            'from' => $range['start'],
            'to' => $range['end'],
            // الفترة المحاسبية المطبَّقة — تُعرض في ترويسة التقرير
            'period' => $range['period'] ?? null,
            'is_default_range' => $range['is_default'],
            'status' => array_key_exists($status, self::statuses()) ? $status : null,
            'product_id' => $request->filled('product_id') ? (int) $request->input('product_id') : null,
            'branch_id' => $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
            'currency' => $currency !== '' ? $currency : null,
            'q' => trim((string) $request->input('q')) ?: null,
            'per_page' => ($perPage < 5 || $perPage > 200) ? 25 : $perPage,
        ];
    }

    /**
     * أساس كل استعلام: أوامر التصنيع ضمن المدى والمرشِّحات.
     *
     * ونطاقُ الفرع هنا — في الأساس المشترك — لا في كلٍّ من التقارير
     * الخمسة: تقريرٌ يُنسى حارسُه يعرض إنتاج الفروع كلِّها تحت عنوانٍ
     * يقول غير ذلك، والتقاريرُ أخطرُ من الشاشات في هذا لأنها تُطبع
     * وتُوزَّع ويُبنى عليها قرار.
     *
     * ── الملغي ──────────────────────────────────────────────────────
     *
     * والحسمُ في الملغي هنا أيضاً، للسبب نفسه:
     *
     *   totals   (الافتراضي) ← لكل رقمِ مالٍ أو كمية. الملغي خارجه دائماً،
     *            حتى لو رشّح المستخدم بـ«ملغي» — فتعود المجاميعُ فارغة.
     *   listing  ← لصفوف الجدول. الملغي لا يُسرَد إلّا لمن رشّح بحالته.
     *   any      ← لعدّ الأوامر بحالاتها وحده: بلا شرطِ حالةٍ أصلاً.
     *
     * والمدى على تاريخ بداية الأمر؛ وأمرٌ بلا تاريخ بداية يُقرأ بتاريخ
     * إنشائه بدل أن يسقط من كل تقريرٍ أيّاً كان المدى.
     */
    protected function scope(array $f, string $mode = 'totals')
    {
        $query = $this->scopeRawToActiveBranch(
            DB::table('manufactur_orders as o')
                ->whereRaw('COALESCE(o.from_date, DATE(o.created_at)) BETWEEN ? AND ?', [$f['from'], $f['to']]),
            'o.branch_id'
        );

        // فلترُ الفرع الذي اختاره المستخدم — يضيق داخل نطاقه ولا يوسّع
        $query = $this->narrowToRequestedBranch($query, 'o.branch_id', $f);

        $cancelled = \App\Enums\ManufacturOrderStatus::Cancelled->value;

        if ($mode !== 'any') {
            if ($mode === 'listing' && $f['status'] === $cancelled) {
                $query->where('o.status', $cancelled);
            } else {
                $query->where('o.status', '!=', $cancelled)
                    ->when($f['status'] !== null, fn ($q) => $q->where('o.status', $f['status']));
            }
        }

        return $query
            ->when($f['currency'], fn ($q) => $q->whereRaw($this->currencyExpr('o').' = ?', [$f['currency']]))
            ->when($f['product_id'], fn ($q) => $q->where('o.product_id', $f['product_id']))
            ->when($f['q'], fn ($q) => $q->where(function ($w) use ($f) {
                $w->where('o.name', 'like', '%'.$f['q'].'%')
                    ->orWhere('o.code', 'like', '%'.$f['q'].'%');
            }));
    }

    /**
     * فلترُ «تابع لـ» على استعلامٍ خام — العقدةُ المختارة وما تحتها.
     *
     * كان مطبَّقاً على الأوامر وحدها؛ فتقريرا المحطّات وقوائم المواد
     * يعرضان شريطَ «تابع لـ» ولا يتأثّران به. والقيمةُ تُفحَص قبل
     * تطبيقها: عقدةٌ خارج نطاق المستخدم (مكتوبةٌ في العنوان) تُرجع
     * تقريراً فارغاً لا تقريرَ النطاق كلِّه تحت اسمها.
     */
    protected function narrowToRequestedBranch($query, string $column, array $f)
    {
        if (! $f['branch_id']) {
            return $query;
        }

        if (! $this->isWithinActiveBranch($f['branch_id'])) {
            return $query->whereRaw('1 = 0');
        }

        $ids = $this->branchSubtreeFor($f['branch_id']);

        return $query->where(function ($w) use ($column, $ids) {
            $w->whereIn($column, $ids)->orWhereNull($column);
        });
    }

    /** محطّاتُ العمل في نطاق الفرع وفلتر «تابع لـ» والبحث. */
    protected function stations(array $f)
    {
        return $this->narrowToRequestedBranch(
            $this->scopeRawToActiveBranch(DB::table('work_stations as s'), 's.branch_id'),
            's.branch_id',
            $f
        )->when($f['q'], fn ($q) => $q->where(function ($w) use ($f) {
            $w->where('s.name', 'like', '%'.$f['q'].'%')
                ->orWhere('s.code', 'like', '%'.$f['q'].'%');
        }));
    }

    /**
     * بنودُ الأمر مجمَّعةً — صفٌّ واحد لكل أمر.
     *
     *   raw_planned   الخام بأسعار الأمر (`raw_total`).
     *   raw_actual    الخام بما صُرف فعلاً (`raw_actual_total`)، وما لم
     *                 يُصرف بعدُ فبتقديره — وهي قاعدةُ `rawCostOf()` في
     *                 دورة حياة الأمر، فيوافق التقريرُ ما يُحمَّل عند الإتمام.
     *   raw_unissued  عددُ بنود الخام التي لم تُصرف بعد.
     */
    protected function itemTotals()
    {
        return DB::table('manufactur_orders_items')
            ->groupBy('manufactur_order_id')
            ->selectRaw(
                'manufactur_order_id,
                 COALESCE(SUM(raw_total),0) raw_planned,
                 COALESCE(SUM(COALESCE(raw_actual_total, raw_total)),0) raw_actual,
                 SUM(CASE WHEN raw_product_id IS NOT NULL AND raw_actual_total IS NULL THEN 1 ELSE 0 END) raw_unissued,
                 COALESCE(SUM(expenses_total),0) expense_cost,
                 COALESCE(SUM(manu_total),0) labor_cost'
            );
    }

    /**
     * عملةُ الصفّ في SQL — المخزَّنةُ معه، والافتراضيةُ لما خلا عمودُه.
     *
     * هي قاعدةُ `documentCurrency()` نفسُها. والرمزُ يُنقّى قبل إدراجه في
     * النصّ: يدخل `GROUP BY` و`SELECT` معاً، فلا يصلح له ربطُ المعاملات.
     */
    protected function currencyExpr(string $alias): string
    {
        $code = preg_replace('/[^A-Za-z0-9_]/', '', $this->defaultCurrency());

        return "COALESCE(NULLIF({$alias}.currency, ''), '{$code}')";
    }

    /**
     * أرقام مركز التقارير — بلا مدًى، فهي حالُ الوحدة كلّها.
     *
     * كان فيها «الكمّية المخطَّطة»: مجموعُ كميات الأصناف كلِّها، ومعها
     * الملغي — رقمٌ لا وحدةَ له. و«إجمالي التكلفة» يجمع الملغيَّ والتقديريَّ
     * إلى الفعلي، وبلا عملة. فحُذفت الكمية، وصارت التكلفةُ فعليةَ الأوامر
     * المُتَمّة وحدها، بعملتها.
     */
    protected function summary(): array
    {
        $done = self::DONE;
        $cur = $this->currencyExpr('o');
        $cancelled = \App\Enums\ManufacturOrderStatus::Cancelled->value;

        $orders = fn () => $this->scopeRawToActiveBranch(DB::table('manufactur_orders as o'), 'o.branch_id');

        $o = $orders()->selectRaw(
            "SUM(CASE WHEN o.status <> ? THEN 1 ELSE 0 END) n,
             SUM(CASE WHEN {$done} THEN 1 ELSE 0 END) done_n,
             SUM(CASE WHEN o.status = ? THEN 1 ELSE 0 END) cancelled_n",
            [$cancelled, $cancelled]
        )->first();

        return [
            'orders' => (int) ($o->n ?? 0),
            'done' => (int) ($o->done_n ?? 0),
            'cancelled' => (int) ($o->cancelled_n ?? 0),
            // التكلفةُ الفعلية للأوامر المُتَمّة، بعملتها
            'costs' => $orders()
                ->whereRaw($done)
                ->groupBy('currency_code')
                ->selectRaw("{$cur} as currency_code, COALESCE(SUM(o.last_total_cost),0) cost")
                ->orderBy('currency_code')
                ->pluck('cost', 'currency_code')
                ->map(fn ($v) => (float) $v)
                ->all(),
            'boms' => (int) $this->scopeRawToActiveBranch(DB::table('production_materials'), 'branch_id')->count(),
            'workstations' => (int) $this->scopeRawToActiveBranch(DB::table('work_stations'), 'branch_id')->count(),
            // العملةُ الافتراضية من شاشة «العملات» — لما خلا عمودُه
            'currency' => $this->defaultCurrency(),
        ];
    }

    /** تصييرٌ واحد لكل تقرير — المرشِّحات وقائمة الأصناف في موضعٍ واحد. */
    protected function render(string $view, array $f, array $data): Renderable
    {
        return view('manufacturing::reports.'.$view, array_merge($data, [
            'f' => $f,
            'statuses' => self::statuses(),
            // قائمةُ الأصناف من أوامر الفرع النشط وحدها — صنفٌ لا يُصنَّع
            // هنا خيارٌ يُرجع تقريراً فارغاً دائماً
            'products' => $this->scopeRawToActiveBranch(
                DB::table('manufactur_orders as o')
                    ->join('products as p', 'p.id', '=', 'o.product_id'),
                'o.branch_id'
            )->distinct()->orderBy('p.name')->pluck('p.name', 'p.id'),
            // الفروع والعملات في ترويسة التقرير — الإعدادات العامّة
            'branches' => $this->branchFilterOptions(),
            'currencies' => $this->activeCurrencies(),
            'defaultCurrency' => $this->defaultCurrency(),
        ]));
    }

    /**
     * الصلاحية.
     *
     * تُستعمل `stock.reports.manufacturing` — وهي قائمةٌ في القاعدة فعلاً.
     * وصلاحيات التصنيع كلّها ما زالت ببادئة `stock.` بعد فصل الوحدة؛
     * تغييرها يمسّ سبعاً وثلاثين صلاحيةً ومَن أُسنِدت إليهم، فتُرك لقرارٍ
     * مقصود.
     */
    protected function authorizeReports(): void
    {
        if (! hasPermission(self::PERMISSION)) {
            OperationLog::record(
                action: 'denied',
                module: 'manufacturing_reports',
                subject: null,
                description: 'محاولة عرض تقارير التصنيع بلا صلاحية '.self::PERMISSION,
            );

            abort(403, 'لا تملك صلاحية عرض تقارير التصنيع.');
        }
    }
}
