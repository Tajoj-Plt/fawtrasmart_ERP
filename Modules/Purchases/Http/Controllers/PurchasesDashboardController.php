<?php

namespace Modules\Purchases\Http\Controllers;

use App\Constants\permissions\PurchasesPermissions;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Purchases\Support\PurchaseDashboardDrillDown;
use Modules\Purchases\Support\PurchaseDashboardMetrics;
use Modules\Purchases\Support\ReportPeriod;

/**
 * لوحة مؤشّرات المشتريات.
 *
 * المتحكّم لا يحسب رقماً: يحدّد الفرع والفترة، ثم يسأل
 * `PurchaseDashboardMetrics` — وهي بدورها لا تحسب شيئاً ابتداءً بل تشتقّ
 * كل رقم من `SupplierLedger`، مصدرِ أرقام تقارير المشتريات كلّها. فما
 * تعرضه اللوحة وما يعرضه التقرير رقمٌ واحد بالبناء.
 *
 * وهو شكلُ `Modules\Sales\Http\Controllers\DashboardController::index()`
 * بنداً ببند — الفرعُ والفترةُ والمقارنةُ وتنبيهُ الإقفال المحاسبي — فما
 * يُصلَح في إحداهما يُقرأ في الأخرى.
 *
 * كان هنا اثنا عشر تابعاً خاصّاً تستعلم القيود بنفسها، فخالفت التقارير
 * في المدفوعات والمرتجعات والإشعارات المدينة — والتفصيل في صدر
 * `PurchaseDashboardMetrics`.
 */
class PurchasesDashboardController extends Controller
{
    /*
     * لم يكن هذا الكنترولر محروساً بأي صلاحية إطلاقاً رغم أن مجموعة
     * `dashboard` في كتالوج `PurchasesPermissions` معرَّفة له صراحةً.
     *
     * ثمّ كان الحارسُ `permission:purchases.dashboard.view` وحدَها، وهي
     * صلاحيةٌ لا تُمنح عادةً مع صلاحيات العمل — فمن مُنح «إضافة فاتورة
     * مشتريات» يُردّ عن اللوحة بـ403. وبوّابةُ الدخول لا تخصّ فعلاً
     * بعينه: أيُّ صلاحيةِ مشترياتٍ واحدة تكفي، وهو مبدأ داشبورد المخزون
     * ({@see \Modules\Stock\Http\Controllers\StockController}) والحسابات.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()?->hasAnyPermissionWithPrefix(PurchasesPermissions::PREFIX)) {
                abort(403, 'لا تملك أي صلاحية على وحدة المشتريات.');
            }

            return $next($request);
        });
    }

    /**
     * «ماذا وراء هذا الرقم؟» — قائمةُ الرقم المنقور في اللوحة (JSON).
     *
     * الفرعُ والفترةُ بما تُحسب به اللوحة نفسُها، فلا تختلف قائمةٌ عن رقمها.
     * وخلايا الشجرة ترسل `node` (وإلا «none» لما لا عقدةَ له) و`node_scope`
     * (`tree` شاملاً ما تحته، أو `direct` وحده) — ولا تخرج عن نطاق المستخدم.
     *
     * نسخةُ `Modules\Sales\Http\Controllers\DashboardController::segment()`.
     */
    public function segment(Request $request)
    {
        $segment = (string) $request->input('segment', '');
        abort_unless(preg_match('/^[a-z_]+(\.[a-z0-9_-]+){0,2}$/', $segment) === 1, 422);

        $branch = $this->resolveBranch($request);
        $period = ReportPeriod::fromRequest($request, 'year');
        [$branchIds, $unbranched, $scopeLabel] = $this->segmentScope($request, $branch);

        $result = (new PurchaseDashboardDrillDown($branchIds, $unbranched, $this->visibleUserIds()))
            ->resolve($segment, $period);
        abort_if($result === null, 404);

        $result['hint'] = implode(' — ', array_filter([$scopeLabel, $period->labelWithDates(), $result['hint'] ?? null]));

        return response()->json($result + ['count' => count($result['rows'])]);
    }

    /**
     * نطاقُ رؤية صاحب اللوحة على محور الأشخاص — `null` تعني «بلا قيد».
     *
     * **ولماذا لا يكفي الفرع؟** `resolveBranch()` يقرأ `users.branch_id`
     * وحدَه، فمن فرعُه رئيسيّ — أو موظّفٌ مربوطٌ بالفروع كلِّها — يمرّ من
     * قيد الفرع بلا أثر فترى لوحتُه المنشأةَ كلَّها، بينما لا تريه قوائمُ
     * المشتريات إلّا ما يسعه نطاقُه (`ScopesToEmployeeHierarchy`). فكان
     * الرقمُ في اللوحة يخالف ما تحته من قوائم.
     *
     * ونفسُ المصدر الذي تقرأ منه القوائم (`DataScope::documentUserIds()`)
     * يُقرأ هنا، فلا يفترق المحوران بعد أوّل تعديل.
     *
     * @return array<int, int>|null
     */
    protected function visibleUserIds(): ?array
    {
        return \App\Support\DataScope::documentUserIds();
    }

    /**
     * نطاقُ القائمة: نطاقُ اللوحة، أو عقدةٌ من شجرتها نُقرت خليّتُها.
     *
     * @return array{0: array<int, int>, 1: bool, 2: string|null}
     */
    protected function segmentScope(Request $request, array $branch): array
    {
        $node = (string) $request->input('node', '');

        if ($node === '') {
            return [$branch['ids'], false, null];
        }

        if ($node === 'none') {
            // «غير محدّد» جذرٌ لا يظهر إلا في عرض المنشأة كلّها
            abort_unless($branch['selected'] === null, 403);

            return [[], true, 'غير محدّد'];
        }

        abort_unless(ctype_digit($node), 422);
        $model = Branch::find((int) $node);
        abort_unless($model, 404);

        // لا تخرج القائمةُ عن العقدة المعروضة — ومستخدمُ الفرع محبوسٌ فيها دائماً.
        // بلا عقدةٍ مختارة تعرض اللوحةُ المنشأةَ كلَّها، فكلُّ عقدةٍ فيها مسموحة.
        if ($branch['selected'] !== null) {
            abort_unless((int) $model->id === $branch['selected'] || $model->isUnderBranch($branch['selected']), 403);
        }

        $direct = $request->input('node_scope') === 'direct';

        return [
            $this->subtreeIds((int) $model->id, ! $direct),
            false,
            $model->name.($direct ? ' — الحركة المباشرة' : ''),
        ];
    }

    public function index(Request $request)
    {
        $branch = $this->resolveBranch($request);
        $period = ReportPeriod::fromRequest($request, 'year');

        $metrics = PurchaseDashboardMetrics::forBranch($branch['ids'])->visibleTo($this->visibleUserIds());

        $totals = $metrics->totals($period);
        $previous = $metrics->totals($metrics->previousPeriod($period));
        $outstanding = $metrics->outstanding($period);

        return view('purchases::dashboard.index', [
            'period' => $period,
            'periodTabs' => ReportPeriod::TABS,

            'branchOptions' => $branch['options'],
            'branchLineage' => $branch['lineage'],
            'selectedBranchId' => $branch['selected'],
            'isMainBranch' => $branch['isMain'],
            'includeChildren' => $branch['includeChildren'],
            'descendantCount' => $branch['descendants'],
            'hasChildBranches' => $branch['hasChildren'],

            'totals' => $totals,
            'previousTotals' => $previous,
            'deltas' => $this->deltas($totals, $previous),
            'outstanding' => $outstanding,
            'openOrders' => $metrics->openOrders($period),

            'trend' => $metrics->trend($period),
            'topSuppliers' => $metrics->topSuppliers($period),
            'topDebtors' => $metrics->topDebtors($period),
            'branchBreakdown' => $metrics->branchBreakdown($period, $branch['selected']),
            // الآباءُ والأبناءُ معاً — كلُّ أبٍ بمجموعه شاملاً ما تحته
            'branchTree' => $metrics->branchTree($period, $branch['selected']),
            'purchasesByEmployee' => $metrics->purchasesByEmployee($period),
            'paymentStatusMix' => $metrics->paymentStatusMix($period),
            'receivingMix' => $metrics->receivingMix($period),
            'topProducts' => $metrics->topProducts($period),
            'recentMovements' => $metrics->recentMovements($period),

            // حالة الإقفال المحاسبي لطرفَي الفترة — كما في لوحة المبيعات
            'periodNotice' => $this->accountingPeriodNotice($period),
        ]);
    }

    /**
     * الفرع المعروض وموضعه من الشجرة — تنقّلٌ مستوىً بمستوى.
     *
     * **لماذا لا قائمةٌ مسطّحة بكل العقد؟** لأن سرد الأب وأبنائه في قائمةٍ
     * واحدة يخلط مستوياتٍ لا تُقارَن، ويجعل جمع العمود يقرأ المبلغ مرّتين.
     * فالتنقّل هنا نزولٌ في الشجرة:
     *
     *     لا اختيار      ← الجذور (أكبر الآباء)
     *     عقدة مختارة   ← أبناؤها المباشرون، ومسارٌ للعودة صعوداً
     *
     * ويُقبل أي عمق: اختيار ابنٍ يعرض أبناءه، واختيار حفيدٍ يعرض أبناءه —
     * وهكذا حتى الورقة.
     *
     * @return array{ids: array<int, int>, selected: int|null, isMain: bool, options: \Illuminate\Support\Collection, lineage: \Illuminate\Support\Collection, includeChildren: bool, descendants: int, hasChildren: bool}
     */
    protected function resolveBranch(Request $request): array
    {
        $includeChildren = $request->boolean('include_children', true);
        $userBranchId = auth()->user()?->branch_id;
        $isMain = (bool) Branch::where('id', $userBranchId)->value('is_main');

        $requested = $request->input('branch_id');
        $selectedId = in_array($requested, [null, '', '0', 'all'], true) ? null : (int) $requested;
        $selected = $selectedId ? Branch::find($selectedId) : null;

        // مستخدم فرعٍ غير رئيسي محبوسٌ في شجرته: يتنقّل داخلها ولا يخرج
        if (! $isMain) {
            $home = $userBranchId ? Branch::find($userBranchId) : null;

            if (! $home) {
                return $this->branchState(null, null, collect(), $includeChildren, false);
            }

            // اختيارٌ خارج شجرته يُرَدّ إلى فرعه
            if (! $selected || ! $selected->isUnderBranch((int) $home->id)) {
                $selected = $home;
            }

            return $this->branchState($selected, $home, $this->childrenOf($selected->id), $includeChildren, false);
        }

        return $this->branchState($selected, null, $this->childrenOf($selected?->id), $includeChildren, true);
    }

    /**
     * أبناء عقدةٍ مباشرةً — أو الجذور حين لا عقدة.
     *
     * **كانت تستبعد عُقد المناطق** بحجّة أنها «تقسيمٌ جغرافي لا فرعٌ
     * محاسبي». والحجّة صحيحةٌ نظراً وخاطئةٌ عملاً: المستنداتُ تُوسَم
     * بالعقدة التي أصدرتها أياً كان نوعُها، فأربعةُ أخماسِ فواتيرِ الشراء
     * في منشأةٍ تعمل بالمناطق تقع على عُقدٍ كانت الشجرةُ تُخفيها — فتعرض
     * اللوحةُ خُمسَ المنشأة وتبدو أرقامُها خاطئة.
     *
     * ولا يُصلحها الضمُّ التنازلي: المناطق جذورٌ بلا أبٍ، فلا فرعَ يرثها.
     *
     * التقسيمُ الجغرافي يُميَّز بالعرض — بأيقونةٍ أو وسمٍ في القائمة — لا
     * بالحجب من الحساب. وهي القاعدةُ نفسُها التي تعمل بها لوحةُ المبيعات.
     *
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    protected function childrenOf(?int $parentId)
    {
        return Branch::where('parent_id', $parentId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'node_type', 'is_territory']);
    }

    /**
     * حالة الفرع كما تقرأها الشاشة.
     *
     * @param  Branch|null  $home  جذرُ صلاحية المستخدم — يقف عنده المسار
     * @return array<string, mixed>
     */
    protected function branchState(?Branch $selected, ?Branch $home, $options, bool $includeChildren, bool $isMain): array
    {
        $ids = $selected
            ? $this->subtreeIds((int) $selected->id, $includeChildren)
            : [];

        // المسار من الجذر (أو من فرع المستخدم) إلى العقدة المختارة
        $lineage = collect();

        if ($selected) {
            $lineage = $selected->lineageFromRoot()
                ->when($home, fn ($path) => $path->filter(fn ($node) => $node->isUnderBranch((int) $home->id))->values());
        }

        return [
            'ids' => $ids,
            'selected' => $selected?->id ? (int) $selected->id : null,
            'isMain' => $isMain,
            'options' => $options,
            'lineage' => $lineage,
            'includeChildren' => $includeChildren,
            'descendants' => $selected ? max(0, count($ids) - 1) : 0,
            'hasChildren' => $options->isNotEmpty(),
        ];
    }

    /**
     * الفرع وحده، أو هو وكل أبنائه مهما عمقوا.
     *
     * @return array<int, int>
     */
    protected function subtreeIds(int $branchId, bool $includeChildren): array
    {
        if (! $includeChildren) {
            return [$branchId];
        }

        $branch = Branch::find($branchId);

        if (! $branch) {
            return [$branchId];
        }

        return $branch->descendantBranchIds()
            ->push($branchId)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * تغيّر كل مؤشّر عن الفترة السابقة المكافئة — نسبةً واتجاهاً.
     *
     * القيمة `null` حين لا سابقةَ لها: النمو من صفرٍ ليس «١٠٠٪» ولا صفراً،
     * فالأصدق ألّا يُعرض رقمٌ أصلاً.
     *
     * @return array<string, array{percent: float|null, direction: string}>
     */
    protected function deltas(array $totals, array $previous): array
    {
        $keys = ['purchases', 'net_purchases', 'returns', 'returns_total', 'payments', 'purchases_count'];
        $deltas = [];

        foreach ($keys as $key) {
            $now = (float) ($totals[$key] ?? 0);
            $then = (float) ($previous[$key] ?? 0);

            if (abs($then) < 0.009) {
                $deltas[$key] = ['percent' => null, 'direction' => $now > 0 ? 'up' : 'flat'];

                continue;
            }

            $percent = (($now - $then) / abs($then)) * 100;

            $deltas[$key] = [
                'percent' => round($percent, 1),
                'direction' => $percent > 0.05 ? 'up' : ($percent < -0.05 ? 'down' : 'flat'),
            ];
        }

        return $deltas;
    }

    /**
     * حالة الإقفال المحاسبي للفترة المعروضة — أو `null` إن لا شيء يُقال.
     *
     * نسخةُ `DashboardController::accountingPeriodNotice()` في المبيعات:
     * اللوحة تعرض ولا تكتب، فالإقفال لا يمنعها؛ لكن قارئ الأرقام يعنيه أن
     * يعرف أن مدةً مقفلةٌ قد لا تقبل تصحيحاً.
     */
    protected function accountingPeriodNotice(ReportPeriod $period): ?array
    {
        try {
            $service = app(AccountingPeriodService::class);

            if (! DB::table('fiscal_years')->exists()) {
                return ['level' => 'muted', 'message' => 'لم تُضبط سنواتٌ ماليةٌ ولا فتراتٌ محاسبية بعد، فلا قيدَ على التواريخ المعروضة.'];
            }

            $reason = $service->blockReason($period->to) ?: $service->blockReason($period->from);

            return $reason
                ? ['level' => 'warning', 'message' => 'تنبيه: الفترة المحاسبية لهذا التاريخ مقفلة — '.$reason]
                : null;
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Purchases.Dashboard', 'accountingPeriodNotice');

            // إعدادات الفترات غير مهيّأة — اللوحة تعرض ولا تعتمد عليها
            return null;
        }
    }
}
