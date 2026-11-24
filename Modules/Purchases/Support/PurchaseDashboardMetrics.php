<?php

namespace Modules\Purchases\Support;

use App\Models\Branch;
use App\Support\ClosedPeriodRanges;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * أرقام لوحة المشتريات — مشتقّةٌ من دفتر أستاذ المورّدين نفسه.
 *
 * **لماذا لا تحسب اللوحة أرقامها بنفسها؟**
 *
 * كانت تفعل، وكانت تخالف التقارير في كل رقم تقريباً:
 *
 * 1. **المدفوعات ناقصة الربع.** كانت تجمع دائن الخزينة في قيود `PAY`
 *    وحدها، وثمانيةُ سدادٍ في النظام مرحّلةٌ سندَ صرف `PV` — ١٠٦٬٨٤٨٫٩٤
 *    من أصل ٤٤٦٬٥٧٨٫٥١ لا تظهر. و`SupplierLedger::PAYMENT_TYPES` يعرف
 *    النوعين منذ كُتب، فالتقرير كان صحيحاً واللوحة وحدها مخطئة.
 *
 * 2. **أساسان مختلفان للمشتريات والمرتجعات.** المشتريات كانت تُجمع
 *    بالضريبة والمرتجعات بلا ضريبة، فنسبة الارتجاع مقسومةٌ على غير مقامها.
 *
 * 3. **الإشعارات المدينة غائبة.** سبعةُ إشعارات تخفض المستحقّ للمورّد ولا
 *    أثر لها في اللوحة، فصافي المشتريات أعلى من حقيقته.
 *
 * 4. **`suppliers.branch_id` لا وجود له.** كل فلترة بفرع كانت ترمي
 *    `Unknown column` — ومستخدم الفرع غير الرئيسي يُفلتر دائماً، فاللوحة
 *    لم تكن تفتح له أصلاً.
 *
 * 5. **محوران للفرع لا محورٌ واحد.** البطاقاتُ تُفلتر بـ«ختمُ القيد أو فرعُ
 *    فاتورته» والجدولُ تحتها يجمع بـ«ختمُ القيد وإلا الفاتورة»، وجداولُ
 *    المستندات (حالة السداد، الاستلام، الأصناف) بفرع الفاتورة وحده. فقيدٌ
 *    ختمُه منطقةٌ وفاتورتُه على الفرع الرئيسي يُعدّ في البطاقتين معاً:
 *    جمعُ الجذور كان ٣٩٢٬٤١٩ والمنشأةُ كلُّها ٣٣٢٬٩٢٥. المحورُ الآن واحد في
 *    كل رقمٍ هنا — `SupplierLedger::branchNodeExpr()` — كما صار في لوحة
 *    المبيعات (عقدةُ العميل هناك، وفرعُ المستند هنا إذ لا عقدةَ للمورّد).
 *
 * **القاعدة هنا:** لا رقم يُحسب في هذا الصنف ابتداءً. كلٌّ منها يأتي من
 * `SupplierLedger` — حساب المورّد التزامٌ يزيد بالدائن وينقص بالمدين —
 * فما تعرضه اللوحة وما يعرضه التقرير شيء واحد بالبناء لا بالمصادفة.
 *
 *     مشتريات = دائن PI،  مرتجعات = مدين PR،  إشعارات = مدين DN
 *     مدفوعات = صافي PAY و PV و PCAN و PCHQ و PCHQR
 *     صافي المشتريات = مشتريات − مرتجعات − إشعارات
 *     الرصيد = رصيد ما قبل الفترة + الصافي − المدفوعات + التسويات
 *
 * وهو شكلُ `SalesDashboardMetrics` نفسُه دالّةً بدالّة، معكوسَ الإشارة:
 * حساب العميل أصلٌ وحساب المورّد التزام.
 */
class PurchaseDashboardMetrics
{
    /** أسماء أنواع الحركة — مفاتيحها من `SupplierLedger::classify()` */
    protected const MOVEMENT_NAMES = [
        'purchase' => 'فاتورة شراء',
        'return' => 'مرتجع شراء',
        'debit_note' => 'إشعار مدين',
        'payment' => 'سداد',
        'other' => 'تسوية',
    ];

    protected SupplierLedger $ledger;

    /** @var array<int, int> معرّفات الفروع المشمولة — فارغةٌ تعني كل الفروع */
    protected array $branchIds;

    /**
     * محورُ الأشخاص: `users.id` الذين يراهم المستخدم — و`null` تعني «بلا
     * قيد» لا «لا أحد»، كبقيّة دفاتر الوحدة.
     *
     * والمحوران لا يغني أحدهما عن الآخر: الفرعُ يقول «أين»، وهذا يقول
     * «مَن». فموظّفٌ مربوطٌ بالفروع كلِّها يمرّ من قيد الفرع بلا أثر —
     * وكانت اللوحةُ تقف عند الفرع وحدَه، فتعرض له مشترياتِ المنشأة كلِّها
     * بينما لا تريه قوائمُها إلّا سجلّاته.
     *
     * @var array<int, int>|null
     */
    protected ?array $userIds = null;

    /**
     * @param  int|array<int, int>|null  $branchId  فرعٌ واحد أو شجرةُ فرعٍ كاملة
     */
    public function __construct(int|array|null $branchId = null)
    {
        $this->branchIds = collect(is_array($branchId) ? $branchId : [$branchId])
            ->reject(fn ($id) => $id === null || $id === '' || $id === 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->ledger = new SupplierLedger(null, null, $this->branchIds);
    }

    /**
     * @param  int|array<int, int>|null  $branchId
     */
    public static function forBranch(int|array|null $branchId): self
    {
        return new self($branchId);
    }

    /**
     * يقصر اللوحةَ على ما أنشأه أولئك المستخدمون — نطاقُ رؤية صاحبها.
     *
     * `null` تعني «بلا قيد»: اللوحةُ تُبنى من الشاشة ومن أوامرَ مجدولةٍ بلا
     * مستخدمٍ مسجَّل، فالافتراضُ فيها عدمُ التقييد ويأتيها النطاقُ من
     * المتحكّم — نفسُ عقد {@see SupplierLedger::visibleTo()}.
     *
     * @param  array<int, int>|null  $userIds  معرّفاتُ `users` لا `employees`
     */
    public function visibleTo(?array $userIds): static
    {
        $this->userIds = $userIds;
        $this->ledger->visibleTo($userIds);

        return $this;
    }

    /**
     * تقييد استعلامٍ يقرأ المستندات مباشرةً على الفروع المشمولة.
     *
     * القيود تُفلتر داخل `SupplierLedger`؛ وهذه للاستعلامات التي تقرأ
     * `purchase_invoices` بنفسها. وفرعُ الفاتورة هو نفسُه محورُ القيد
     * (`branchNodeExpr()` يقدّمه على ختم القيد)، فبطاقةُ حالة السداد
     * وبطاقةُ المشتريات تقرآن الفواتيرَ ذاتها.
     */
    protected function scopeBranch($query, string $column = 'branch_id')
    {
        if ($this->branchIds !== []) {
            $query->whereIn($column, $this->branchIds);
        }

        return $this->scopeCreator($query);
    }

    /**
     * ومحورُ الأشخاص معه — من أنشأ المستند.
     *
     * يُطبَّق هنا لا في كلّ مُستدعٍ: المحوران يلتقيان في كلّ استعلامٍ
     * مباشرٍ على `purchase_invoices`، فموضعٌ واحدٌ يضمن ألّا يسقط أحدهما
     * عن بطاقةٍ دون أخرى. و`?: [0]` مقصودة كما في
     * {@see \Modules\Purchases\Http\Controllers\Concerns\ScopesToEmployeeHierarchy::scopeToCreator()}:
     * مجموعةٌ فارغة تعني «لا أحد»، فيُصرَّح بمعرّفٍ لا يوجد بدل ترك
     * `whereIn` بمصفوفةٍ فارغة.
     */
    protected function scopeCreator($query, string $column = 'created_by')
    {
        if ($this->userIds !== null) {
            $query->whereIn($column, $this->userIds ?: [0]);
        }

        return $query;
    }

    /**
     * مجاميع الفترة على مستوى المنشأة — حاصلُ جمع مجاميع الموردين.
     *
     * @return array<string, float|int>
     */
    public function totals(ReportPeriod $period): array
    {
        $totals = SupplierLedger::emptyTotals();

        foreach ($this->ledger->periodTotals($period) as $supplierTotals) {
            foreach ($totals as $key => $value) {
                if (isset($supplierTotals[$key]) && is_numeric($supplierTotals[$key])) {
                    $totals[$key] += $supplierTotals[$key];
                }
            }
        }

        /*
            «المرتجعات» مستندان لا مستندٌ واحد: مرتجعُ المشتريات `PR`
            والإشعارُ المدين `DN`. كلاهما يُنقص ما للمورّد علينا ويُنقص
            المشتريات — كما يجمع `returns_total` في المبيعات المرتجعَ
            والإشعارَ الدائن. والتفصيلُ باقٍ لحاشية البطاقة ولشريط المطابقة.
        */
        /* عددُ السندات = ما تسرده «تقارير المدفوعات» نفسُه: سنداتُ الصرف القائمة.
           كان يُعدّ كلَّ قيدٍ من أنواع السداد — فسندٌ أُلغي يُحسب ثلاثاً (الأصل
           وقيد العكس) وتسوياتُ المحفظة تُحسب سنداتٍ — فيقول البطاقةُ ١١٣ سنداً
           وشاشةُ المدفوعات أقلّ منها. والمبلغُ يبقى من الدفتر كلّه لأنه ما
           يوافق رصيد المورّد. */
        $totals['payments_count'] = (new SupplierPaymentLedger(null, null, $this->branchIds))
            ->visibleTo($this->userIds)
            ->payments($period)
            ->count();

        $totals['returns_total'] = $totals['returns'] + $totals['debit_notes'];
        $totals['returns_total_count'] = $totals['returns_count'] + $totals['debit_notes_count'];

        $totals['net_purchases'] = $totals['purchases'] - $totals['returns_total'];

        // متوسّط الفاتورة من صافي المشتريات لا إجماليها — بعد طرح المرتجعات
        // والإشعارات المدينة، على عدد فواتير الفترة نفسِه
        $totals['average_invoice'] = $totals['purchases_count'] > 0
            ? $totals['net_purchases'] / $totals['purchases_count']
            : 0.0;

        // نسبة الارتجاع والسداد على أساسٍ واحد: كلاهما شامل الضريبة
        $totals['return_rate'] = $totals['purchases'] > 0
            ? ($totals['returns_total'] / $totals['purchases']) * 100
            : 0.0;

        $totals['payment_rate'] = $totals['net_purchases'] > 0
            ? ($totals['payments'] / $totals['net_purchases']) * 100
            : 0.0;

        return array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $totals);
    }

    /**
     * الرصيد المستحقّ للموردين في نهاية الفترة — دائن موجب، أي «له علينا».
     *
     * يُقرأ من القيود حتى تاريخ النهاية لا من `accounts.balance`، فذاك
     * رصيدٌ تراكمي لا يعرف الفترة ولا الفرع.
     *
     * @return array{outstanding: float, credit: float, suppliers: int}
     */
    public function outstanding(ReportPeriod $period): array
    {
        $balances = $this->ledger->balancesAsOf($period->to);

        $outstanding = 0.0;   // مستحقٌّ للمورّد
        $credit = 0.0;        // مدفوعٌ زيادةً — رصيدٌ لنا عنده
        $suppliers = 0;

        foreach ($balances as $balance) {
            if ($balance > 0.009) {
                $outstanding += $balance;
                $suppliers++;
            } elseif ($balance < -0.009) {
                $credit += abs($balance);
            }
        }

        return [
            'outstanding' => round($outstanding, 2),
            'credit' => round($credit, 2),
            'suppliers' => $suppliers,
        ];
    }

    /**
     * اتجاه المشتريات والمدفوعات عبر الفترة — الوحدة تتبع طولها.
     *
     * @return array{labels: array<int, string>, purchases: array<int, float>, payments: array<int, float>}
     */
    public function trend(ReportPeriod $period): array
    {
        return $this->ledger->trend($period);
    }

    /**
     * الفترة السابقة المكافئة — للمقارنة ونسبة النمو.
     *
     * للمفاتيح التقويمية تُؤخذ الفترةُ التقويمية التي قبلها: «الشهر
     * الحالي» يُقارن بالشهر الماضي كاملاً لا بثلاثين يوماً تسبقه، وهو ما
     * يتوقّعه قارئ اللوحة. وما عداها (فترة مخصّصة) تُزاح بطولها نفسه.
     *
     * الحساب القديم كان يقارن يناير بديسمبر من السنة نفسها — أي بشهرٍ
     * لم يأتِ بعد.
     */
    public function previousPeriod(ReportPeriod $period): ReportPeriod
    {
        $from = $period->from->copy();

        $shifted = match ($period->key) {
            'today', 'yesterday' => [$from->copy()->subDay()->startOfDay(), $from->copy()->subDay()->endOfDay()],
            'week' => [$from->copy()->subWeek()->startOfWeek(), $from->copy()->subWeek()->endOfWeek()],
            'month' => [$from->copy()->subMonthNoOverflow()->startOfMonth(), $from->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$from->copy()->subQuarterNoOverflow()->startOfQuarter(), $from->copy()->subQuarterNoOverflow()->endOfQuarter()],
            'year' => [$from->copy()->subYearNoOverflow()->startOfYear(), $from->copy()->subYearNoOverflow()->endOfYear()],
            default => $this->shiftedByLength($period),
        };

        return new ReportPeriod($period->key.'_previous', $shifted[0], $shifted[1], 'الفترة السابقة');
    }

    /**
     * إزاحةٌ بطول الفترة نفسها — للفترات غير التقويمية.
     *
     * `diffInDays` يعيد كسراً (٣٦٤٫٩٩٩… لسنةٍ كاملة) لأن النهاية آخر لحظةٍ
     * من يومها، فيُقتطع إلى عددٍ صحيح قبل أن يُطرح.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function shiftedByLength(ReportPeriod $period): array
    {
        $days = (int) $period->from->copy()->startOfDay()
            ->diffInDays($period->to->copy()->startOfDay()) + 1;

        return [
            $period->from->copy()->subDays($days)->startOfDay(),
            $period->from->copy()->subDay()->endOfDay(),
        ];
    }

    /**
     * أعلى الموردين بصافي المشتريات — مع مستحقّ كلٍّ منهم.
     *
     * الترتيب بالصافي لا بالإجمالي: مورّدٌ رُدَّ إليه نصفُ ما اشتُري منه
     * ليس أكبر ممّن لم يُردّ إليه شيء.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topSuppliers(ReportPeriod $period, int $limit = 8): Collection
    {
        $totals = $this->ledger->periodTotals($period);

        if ($totals === []) {
            return collect();
        }

        $balances = $this->ledger->balancesAsOf($period->to);
        $names = $this->supplierNames();

        return collect($totals)
            ->map(function ($row, $supplierId) use ($names, $balances) {
                $net = $row['purchases'] - $row['returns'] - $row['debit_notes'];

                return [
                    'supplier_id' => (int) $supplierId,
                    'name' => $names[(int) $supplierId] ?? '—',
                    'purchases' => round($row['purchases'], 2),
                    'returns' => round($row['returns'], 2),
                    'debit_notes' => round($row['debit_notes'], 2),
                    // المعروض في العمود: المرتجع والإشعار معاً
                    'returns_total' => round($row['returns'] + $row['debit_notes'], 2),
                    'net_purchases' => round($net, 2),
                    'payments' => round($row['payments'], 2),
                    'invoices' => (int) $row['purchases_count'],
                    'balance' => round($balances[(int) $supplierId] ?? 0, 2),
                ];
            })
            ->filter(fn ($row) => abs($row['net_purchases']) > 0.009)
            ->sortByDesc('net_purchases')
            ->take($limit)
            ->values();
    }

    /**
     * أعلى الموردين مديونيةً في نهاية الفترة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topDebtors(ReportPeriod $period, int $limit = 8): Collection
    {
        $names = $this->supplierNames();

        return collect($this->ledger->balancesAsOf($period->to))
            ->filter(fn ($balance) => $balance > 0.009)
            ->map(fn ($balance, $supplierId) => [
                'supplier_id' => (int) $supplierId,
                'name' => $names[(int) $supplierId] ?? '—',
                'balance' => round($balance, 2),
            ])
            ->sortByDesc('balance')
            ->take($limit)
            ->values();
    }

    /**
     * حركة كل عقدة فرعٍ على حدة — أساسُ التجميع الهرمي.
     *
     * صفٌّ لكل فرعٍ بحركته **المباشرة** وحدها (بلا نسله)، ومفتاح `none`
     * لما لا فرع له. `branchBreakdown()` هي التي تطويه على الشجرة.
     *
     * المحورُ `SupplierLedger::branchNodeExpr()` نفسُه الذي تُفلتر به
     * البطاقات: كان هنا `COALESCE(je.branch_id, pi.branch_id)` — ختمُ القيد
     * أولاً — والبطاقاتُ تقبل أيَّهما، فالجدولُ والبطاقاتُ فوقه على محورين.
     * والتصنيفُ بالطرف لا بالمستند — كما في `SupplierLedger::accumulate()`.
     *
     * @return array<int|string, array<string, float|int>>
     */
    protected function rawByBranch(ReportPeriod $period): array
    {
        $purchase = $this->quoted(SupplierLedger::PURCHASE_TYPES);
        $return = $this->quoted(SupplierLedger::RETURN_TYPES);
        $debitNote = $this->quoted(SupplierLedger::DEBIT_NOTE_TYPES);
        $payment = $this->quoted(SupplierLedger::PAYMENT_TYPES);

        $node = SupplierLedger::branchNodeExpr('pi', 'je');

        $rows = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->leftJoin('purchase_invoices as pi', 'pi.id', '=', 'je.purchase_invoice_id')
            ->whereNotNull('a.supplier_id')
            ->where('je.status', 1)
            ->tap(fn ($q) => SupplierLedger::excludeWalletAccounts($q, 'a'))
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->groupBy(DB::raw($node))
            ->select([
                DB::raw("$node as branch_id"),
                DB::raw("SUM(CASE WHEN je.reference_type IN ($purchase) THEN jed.credit - jed.debit ELSE 0 END) as purchases"),
                // الطرفُ المدين وحده — كما في `SupplierLedger::accumulate()`
                DB::raw("SUM(CASE WHEN je.reference_type IN ($return) THEN jed.debit ELSE 0 END) as returns"),
                DB::raw("SUM(CASE WHEN je.reference_type IN ($debitNote) THEN jed.debit ELSE 0 END) as debit_notes"),
                DB::raw("SUM(CASE WHEN je.reference_type IN ($payment) THEN jed.debit - jed.credit ELSE 0 END) as payments"),
                DB::raw("COUNT(DISTINCT CASE WHEN je.reference_type IN ($purchase) AND jed.credit > 0 THEN je.id END) as invoices"),
            ])
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $key = $row->branch_id === null ? 'none' : (int) $row->branch_id;

            $totals[$key] = [
                'purchases' => (float) $row->purchases,
                'returns' => (float) $row->returns,
                'debit_notes' => (float) $row->debit_notes,
                'payments' => (float) $row->payments,
                'invoices' => (int) $row->invoices,
            ];
        }

        return $totals;
    }

    /**
     * مقارنة مستوىً واحد من الشجرة — أبناءُ العقدة المختارة، كلٌّ مطويّاً
     * على نسله.
     *
     * **لماذا مستوىً واحداً؟** لأن سرد كل النسل مسطَّحاً يضع الأب وأبناءه
     * في صفوفٍ متجاورة، فيُقرأ المبلغ مرّتين ولا يصحّ جمع العمود.
     *
     *     لا عقدة مختارة  ← الجذور، كلٌّ بنسله + «غير محدّد»
     *     عقدة مختارة     ← أبناؤها المباشرون، كلٌّ بنسله
     *                       + صفٌّ لحركتها المباشرة إن وُجدت
     *
     * @param  int|null  $parentId  العقدة المختارة — أو `null` للجذور
     * @return Collection<int, array<string, mixed>>
     */
    public function branchBreakdown(ReportPeriod $period, ?int $parentId = null): Collection
    {
        $raw = $this->rawByBranch($period);
        $children = Branch::where('parent_id', $parentId)->orderBy('name')->get(['id', 'name']);

        $rows = collect();

        foreach ($children as $child) {
            $subtree = Branch::find($child->id)?->descendantBranchIds()->push($child->id)
                ->map(fn ($id) => (int) $id)->unique()->values()->all() ?? [$child->id];

            $rows->push($this->foldSubtree($raw, $subtree, $child->name, (int) $child->id, false));
        }

        if ($parentId !== null) {
            $own = $this->foldSubtree($raw, [$parentId], 'الحركة المباشرة', $parentId, true);

            if ($own['purchases'] != 0.0 || $own['payments'] != 0.0) {
                $rows->push($own);
            }
        } elseif (isset($raw['none'])) {
            $rows->push($this->foldSubtree($raw, ['none'], 'غير محدّد', null, false));
        }

        return $rows
            ->filter(fn ($row) => $row['purchases'] != 0.0 || $row['payments'] != 0.0)
            ->sortByDesc('net_purchases')
            ->values();
    }

    /**
     * الشجرة كاملةً — كلُّ أبٍ بمجموعه شاملاً نسلَه، وأبناؤه تحته.
     *
     * نظيرةُ `SalesDashboardMetrics::branchTree()` حرفاً بحرف: الصفوف بترتيب
     * الشجرة ولكلٍّ عمقُه، ورقمُ الأب شاملٌ ما تحته فلا يُجمع العمود، وحركةُ
     * العقدة نفسِها (`own_*`) منفصلة. والمحورُ `rawByBranch()` نفسُه —
     * فرعُ الفاتورة وإلا ختمُ القيد — فمجموعُ الجذور يساوي البطاقات.
     *
     * @param  int|null  $rootId  العقدة المختارة — أو `null` لكل الجذور
     * @return Collection<int, array<string, mixed>>
     */
    public function branchTree(ReportPeriod $period, ?int $rootId = null): Collection
    {
        $raw = $this->rawByBranch($period);

        $nodes = Branch::withoutGlobalScopes()
            ->get(['id', 'name', 'parent_id', 'node_type', 'is_territory'])
            ->keyBy('id');
        $children = $nodes->groupBy(fn ($node) => (int) ($node->parent_id ?? 0));

        $figures = fn ($row) => [
            'purchases' => (float) ($row['purchases'] ?? 0),
            'returns' => (float) ($row['returns'] ?? 0),
            'debit_notes' => (float) ($row['debit_notes'] ?? 0),
            'payments' => (float) ($row['payments'] ?? 0),
            'invoices' => (int) ($row['invoices'] ?? 0),
        ];
        $active = fn (array $f) => abs($f['purchases']) > 0.009 || abs($f['payments']) > 0.009;
        $net = fn (array $f) => $f['purchases'] - $f['returns'] - $f['debit_notes'];

        // مجموعُ كل عقدةٍ شاملاً نسلَها — محفوظاً، ومحروساً من حلقةٍ في `parent_id`
        $subtotals = [];
        $sum = function (int $id, array $seen = []) use (&$sum, &$subtotals, $raw, $children, $figures) {
            if (isset($subtotals[$id])) {
                return $subtotals[$id];
            }

            $total = $figures($raw[$id] ?? null);

            if (isset($seen[$id])) {
                return $total;
            }
            $seen[$id] = true;

            foreach ($children->get($id, collect()) as $child) {
                foreach ($sum((int) $child->id, $seen) as $key => $value) {
                    $total[$key] += $value;
                }
            }

            return $subtotals[$id] = $total;
        };

        $rows = collect();
        $emit = function (int $id, int $depth, ?int $parentId, array $seen = []) use (&$emit, &$rows, $nodes, $children, $sum, $raw, $figures, $active, $net) {
            if (isset($seen[$id]) || ! isset($nodes[$id])) {
                return;
            }
            $seen[$id] = true;

            $total = $sum($id);
            if (! $active($total)) {
                return;
            }

            $kids = $children->get($id, collect())
                ->filter(fn ($child) => $active($sum((int) $child->id)))
                ->sortByDesc(fn ($child) => $net($sum((int) $child->id)));

            $own = $figures($raw[$id] ?? null);
            $node = $nodes[$id];

            $rows->push([
                'branch_id' => $id,
                'parent_id' => $parentId,
                'depth' => $depth,
                'name' => $node->name,
                // نوعُ العرض لا المخزَّن: المناطق والمنافذ القديمة مخزَّنةٌ «مجموعة»
                'type' => Branch::nodeTypeMeta(Branch::displayNodeType($node, $children->get($id, collect())))['label'] ?? '',
                'has_children' => $kids->isNotEmpty(),
                'invoices' => $total['invoices'],
                'purchases' => round($total['purchases'], 2),
                'returns_total' => round($total['returns'] + $total['debit_notes'], 2),
                'net_purchases' => round($net($total), 2),
                'payments' => round($total['payments'], 2),
                'own_net' => round($net($own), 2),
                'own_payments' => round($own['payments'], 2),
            ]);

            foreach ($kids as $kid) {
                $emit((int) $kid->id, $depth + 1, $id, $seen);
            }
        };

        $roots = $rootId !== null
            ? collect([$rootId])
            : $nodes->filter(fn ($node) => $node->parent_id === null || ! isset($nodes[$node->parent_id]))
                ->sortByDesc(fn ($node) => $net($sum((int) $node->id)))
                ->keys();

        foreach ($roots as $root) {
            $emit((int) $root, 0, null);
        }

        // ما لا فرعَ له — جذرٌ مستقلّ، يظهر فقط في عرض المنشأة كلّها
        if ($rootId === null && isset($raw['none']) && $active($figures($raw['none']))) {
            $none = $figures($raw['none']);
            $rows->push([
                'branch_id' => null, 'parent_id' => null, 'depth' => 0,
                'name' => 'غير محدّد', 'type' => '', 'has_children' => false,
                'invoices' => $none['invoices'],
                'purchases' => round($none['purchases'], 2),
                'returns_total' => round($none['returns'] + $none['debit_notes'], 2),
                'net_purchases' => round($net($none), 2),
                'payments' => round($none['payments'], 2),
                'own_net' => 0.0, 'own_payments' => 0.0,
            ]);
        }

        return $rows->values();
    }

    /**
     * طيُّ حركة شجرةٍ فرعية في صفٍّ واحد.
     *
     * @param  array<int|string, array<string, float|int>>  $raw
     * @param  array<int, int|string>  $ids
     * @return array<string, mixed>
     */
    protected function foldSubtree(array $raw, array $ids, string $name, ?int $branchId, bool $isSelf): array
    {
        $purchases = $returns = $debitNotes = $payments = 0.0;
        $invoices = 0;

        foreach ($ids as $id) {
            if (! isset($raw[$id])) {
                continue;
            }

            $purchases += $raw[$id]['purchases'];
            $returns += $raw[$id]['returns'];
            $debitNotes += $raw[$id]['debit_notes'];
            $payments += $raw[$id]['payments'];
            $invoices += $raw[$id]['invoices'];
        }

        return [
            'branch_id' => $branchId,
            'name' => $name,
            'is_self' => $isSelf,
            'nodes' => count($ids),
            'purchases' => round($purchases, 2),
            'returns' => round($returns, 2),
            'debit_notes' => round($debitNotes, 2),
            'returns_total' => round($returns + $debitNotes, 2),
            'net_purchases' => round($purchases - $returns - $debitNotes, 2),
            'payments' => round($payments, 2),
            'invoices' => $invoices,
        ];
    }

    /**
     * أداء الموظّفين — من دفتر مشتريات الموظّفين نفسه الذي يغذّي تقريرهم.
     *
     * نظيرُ `SalesDashboardMetrics::salesByEmployee()`: منشئُ القيد هو
     * الموظّف المسؤول عن الحركة. ويُقرأ من `EmployeePurchaseLedger` لا من
     * استعلامٍ هنا، فمجموعُ الموظّفين في اللوحة = مجموعُهم في التقرير.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function purchasesByEmployee(ReportPeriod $period, int $limit = 8): Collection
    {
        $totals = EmployeePurchaseLedger::fromFilters(null, null, $this->branchIds)
            ->visibleTo($this->userIds)
            ->periodTotals($period);

        if ($totals === []) {
            return collect();
        }

        $names = DB::table('users')->whereIn('id', array_keys($totals))->pluck('name', 'id');

        return collect($totals)
            ->map(fn ($row, $userId) => [
                'employee_id' => (int) $userId,
                'name' => $names[(int) $userId] ?? 'غير محدّد',
                'purchases' => round($row['purchases'], 2),
                'payments' => round($row['payments'], 2),
                'invoices' => (int) $row['purchases_count'],
            ])
            ->filter(fn ($row) => $row['purchases'] != 0.0 || $row['payments'] != 0.0)
            ->sortByDesc('purchases')
            ->take($limit)
            ->values();
    }

    /**
     * توزيع فواتير الفترة على حالات السداد — بمبالغها لا بعددها وحده.
     *
     * تُقرأ من المستندات لا من القيود: حالة السداد خاصّية فاتورة، والقيد
     * لا يحملها. ويقتصر على الفاتورة وحدها — أمر الشراء لا يُدفع.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function paymentStatusMix(ReportPeriod $period): Collection
    {
        $labels = [
            'paid' => 'مسدَّدة',
            'partially_paid' => 'مسدَّدة جزئياً',
            'unpaid' => 'غير مسدَّدة',
            // فاتورةٌ رُدّ منها لا تُعدّ ديناً مفتوحاً ولا مدفوعة: لها بابٌ مستقلّ
            // بدل أن تُحسب «غير مسدَّدة» وهي مردودة، أو تختفي من التوزيع
            'returned' => 'لها مرتجع',
        ];

        $rows = DB::table('purchase_invoices')
            ->where('type', \App\Enums\PurchaseDocumentType::INVOICE->value)
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => $this->scopeBranch($q))
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'purchase_invoices.date'))
            ->groupBy('status_key')
            ->select([
                DB::raw("CASE WHEN EXISTS (SELECT 1 FROM purchase_invoices r WHERE r.reference_id = purchase_invoices.id AND r.type = 'Return') THEN 'returned' ELSE payment_status END as status_key"),
                DB::raw('COUNT(*) as documents'),
                DB::raw('SUM(grand_total) as amount'),
            ])
            ->get()
            ->keyBy('status_key');

        return collect($labels)
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'documents' => (int) ($rows[$key]->documents ?? 0),
                'amount' => round((float) ($rows[$key]->amount ?? 0), 2),
            ])
            ->values();
    }

    /**
     * حالة الاستلام لفواتير الفترة — كم وصل فعلاً ممّا اشتُري.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function receivingMix(ReportPeriod $period): Collection
    {
        $labels = [
            'received' => 'مستلمة',
            'partially_received' => 'مستلمة جزئياً',
            'not_received' => 'غير مستلمة',
        ];

        $rows = DB::table('purchase_invoices')
            ->where('type', \App\Enums\PurchaseDocumentType::INVOICE->value)
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => $this->scopeBranch($q))
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'purchase_invoices.date'))
            ->groupBy('receiving_status')
            ->select([
                'receiving_status',
                DB::raw('COUNT(*) as documents'),
                DB::raw('SUM(grand_total) as amount'),
            ])
            ->get()
            ->keyBy('receiving_status');

        return collect($labels)
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'documents' => (int) ($rows[$key]->documents ?? 0),
                'amount' => round((float) ($rows[$key]->amount ?? 0), 2),
            ])
            ->values();
    }

    /**
     * أعلى الأصناف شراءً — من دفتر أستاذ المنتجات نفسه الذي يغذّي تقريرها.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function topProducts(ReportPeriod $period, int $limit = 8): Collection
    {
        return ProductPurchaseLedger::fromFilters(['branch_id' => $this->branchIds ?: null])
            ->visibleTo($this->userIds)
            ->productTotals($period)
            ->sortByDesc('net_amount')
            ->take($limit)
            ->values();
    }

    /**
     * سنداتُ الصرف المقابلة للتسويات — تُحسب في «المدفوع» وتُصفّي التسوية.
     *
     * مصروفٌ قُيّد على المورّد يقابله سندُ صرفٍ (`PV`) بلا فاتورة يُسدَّد به:
     * التسويةُ تزيد رصيدَه والسندُ ينقصه فيبقى الأثرُ صفراً. لكنّ الأول
     * يُصنَّف «تسويات» والثاني «مدفوعاً»، فتظهر التسويةُ وحدها كأنّها دَينٌ
     * مفتوح. فيُعرض الطرفان معاً — للموردين الذين لهم تسوياتٌ فقط.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function adjustmentCounterPayments(ReportPeriod $period): Collection
    {
        $names = $this->supplierNames();
        $movements = $this->ledger->movements($period);

        $suppliers = $movements
            ->filter(fn ($row) => SupplierLedger::classify($row->reference_type) === 'other')
            ->pluck('supplier_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        return $movements
            ->filter(fn ($row) => in_array((int) $row->supplier_id, $suppliers, true)
                && SupplierLedger::classify($row->reference_type) === 'payment'
                && empty($row->purchase_invoice_id))
            ->sortBy(fn ($row) => [$row->date, $row->entry_id])
            ->map(fn ($row) => [
                'entry_id' => (int) $row->entry_id,
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $names[(int) $row->supplier_id] ?? '—',
                'code' => $row->reference_number ?: '#'.$row->entry_id,
                'type' => SupplierLedger::typeName($row->reference_type),
                'date' => (string) $row->date,
                'description' => (string) $row->description,
                'employee_name' => $row->employee_name ?: '—',
                // كما يُحسب في «المدفوع»: مدين − دائن
                'amount' => round((float) $row->debit - (float) $row->credit, 2),
            ])
            ->values();
    }

    /**
     * قيود التسوية على حسابات الموردين — ما ليس فاتورةً ولا مرتجعاً ولا إشعاراً
     * ولا سداداً (مصروفٌ قُيّد على المورّد، سندُ قبضٍ منه…).
     *
     * هي الفرقُ بين «صافي المشتريات − المدفوع» والمستحقّ المعروض، فتُعرض
     * قيداً قيداً ليُعرف من أين جاء الرقم. المبلغ بإشارته: موجبٌ له علينا
     * وسالبٌ عليه، ومجموعُها هو بطاقةُ «تسويات».
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function adjustmentEntries(ReportPeriod $period): Collection
    {
        $names = $this->supplierNames();

        return $this->ledger->movements($period)
            ->filter(fn ($row) => SupplierLedger::classify($row->reference_type) === 'other')
            ->sortBy(fn ($row) => [$row->date, $row->entry_id])
            ->map(fn ($row) => [
                'entry_id' => (int) $row->entry_id,
                'supplier_id' => (int) $row->supplier_id,
                'supplier_name' => $names[(int) $row->supplier_id] ?? '—',
                'code' => $row->reference_number ?: '#'.$row->entry_id,
                'type' => SupplierLedger::typeName($row->reference_type),
                'date' => (string) $row->date,
                'description' => (string) $row->description,
                'employee_name' => $row->employee_name ?: '—',
                'amount' => round((float) $row->credit - (float) $row->debit, 2),
            ])
            ->values();
    }

    /**
     * آخر حركات الموردين — فواتير ومرتجعات وإشعارات وسداد الفترة.
     *
     * من الدفتر لا من `purchase_invoices` — كما تفعل `recentMovements()`
     * في المبيعات. كانت تقرأ المستندات فتعرض فاتورةً لم تُرحَّل بعد وتُغفل
     * كلَّ سداد، فلا يطابق الجدولُ البطاقاتِ فوقه.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentMovements(ReportPeriod $period, int $limit = 8): Collection
    {
        $names = $this->supplierNames();

        $rows = $this->ledger->movements($period)
            ->sortByDesc(fn ($row) => [$row->date, $row->entry_id])
            ->take($limit)
            ->map(function ($row) use ($names) {
                $kind = SupplierLedger::classify($row->reference_type);
                $amount = $kind === 'purchase' || $kind === 'other'
                    ? (float) $row->credit - (float) $row->debit
                    : (float) $row->debit - (float) $row->credit;

                return [
                    'entry_id' => (int) $row->entry_id,
                    // يقرؤهما `attachDocumentUrls()` ثم يُنزعان
                    'reference_type' => (string) $row->reference_type,
                    'purchase_invoice_id' => $row->purchase_invoice_id ? (int) $row->purchase_invoice_id : null,
                    'supplier_id' => (int) $row->supplier_id,
                    'code' => $row->reference_number ?: '#'.$row->entry_id,
                    'kind' => $kind,
                    'kind_label' => self::MOVEMENT_NAMES[$kind] ?? self::MOVEMENT_NAMES['other'],
                    'date' => (string) $row->date,
                    'amount' => round($amount, 2),
                    'supplier_name' => $names[(int) $row->supplier_id] ?? '—',
                    'branch_name' => $row->branch_name ?: 'غير محدّد',
                    'employee_name' => $row->employee_name ?: '—',
                ];
            })
            ->values();

        // رابطُ كل مستندٍ إلى شاشته — بقاعدة قوائم اللوحة نفسِها
        return PurchaseDashboardDrillDown::attachDocumentUrls($rows)
            ->map(function ($row) {
                unset($row['reference_type'], $row['purchase_invoice_id']);

                return $row;
            })
            ->values();
    }

    /**
     * أوامر الشراء المعلّقة — طلباتٌ لم تتحوّل إلى فواتير بعد.
     *
     * ليست مستنداً مالياً فلا تدخل أيّ مجموع، لكنها التزامٌ قادم يعني
     * شيئاً لمن يقرأ اللوحة.
     *
     * @return array{count: int, amount: float}
     */
    public function openOrders(ReportPeriod $period): array
    {
        $row = DB::table('purchase_invoices')
            ->where('type', \App\Enums\PurchaseDocumentType::ORDER->value)
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => $this->scopeBranch($q))
            ->selectRaw('COUNT(*) as documents, COALESCE(SUM(grand_total), 0) as amount')
            ->first();

        return [
            'count' => (int) ($row->documents ?? 0),
            'amount' => round((float) ($row->amount ?? 0), 2),
        ];
    }

    /**
     * أسماء الموردين — الاسم التجاري وإلا الاسم الشخصي.
     *
     * @return Collection<int, string>
     */
    protected function supplierNames(): Collection
    {
        return $this->ledger->accounts()
            ->mapWithKeys(fn ($account) => [
                (int) $account->supplier_id => $account->supplier->trade_name
                    ?: trim(($account->supplier->first_name ?? '').' '.($account->supplier->last_name ?? ''))
                    ?: '—',
            ]);
    }

    /** أنواعُ قيدٍ في صيغة قائمة SQL — الثوابت مغلقة فلا مدخل لمستخدم هنا */
    protected function quoted(array $types): string
    {
        return implode(',', array_map(fn ($type) => "'".$type."'", $types));
    }
}
