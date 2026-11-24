<?php

namespace Modules\Manufacturing\Services;

use App\Enums\ManufacturOrderStatus;
use App\Models\Account;
use App\Models\IndirectCost;
use App\Models\ManufacturOrders;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * التكاليف غير المباشرة — من أين تُجمع، وكيف تُوزَّع، وكم نصيبُ الوحدة.
 *
 * ── ما كان ───────────────────────────────────────────────────────────
 *
 * الشاشةُ كانت تقبل كلَّ شيءٍ من المستخدم: أيَّ حساب (عملاء وبنوك
 * وصناديق)، وأيَّ قيدٍ في الفترة ولو لم يمسّ الحساب، ومبلغَ القيد مكتوباً
 * باليد ولو زاد على القيد نفسِه، والقيدَ نفسَه في أكثر من توزيع، وأيَّ أمرٍ
 * ولو ملغى أو خارج الفترة، ومبلغَ كلّ أمرٍ يدوياً — و«نوع التوزيع» لا يحسب
 * شيئاً. ثمّ لا يقرأ المحفوظَ أحد.
 *
 * ── القاعدة ─────────────────────────────────────────────────────────
 *
 *   الحسابات   حساباتُ المصروفات وحدها.
 *   القيود     قيودُ الفترة التي فيها حركةٌ على الحساب المختار، ومبلغُ
 *              كلٍّ منها صافي حركته على ذلك الحساب (مدين − دائن) — من
 *              الدفتر لا من الشاشة.
 *   مرّة واحدة  قيدٌ دخل توزيعاً على الحساب نفسِه لا يُعرض في توزيعٍ آخر.
 *   الأوامر    أوامرُ التصنيع التي تتقاطع فترتُها مع فترة التوزيع، غير
 *              الملغاة.
 *   التوزيع    بحسب الكمية أو التكلفة — يحسبه الخادم:
 *                  نصيب الوحدة = إجمالي القيود ÷ أساس التوزيع
 *                  نصيب الأمر  = نصيب الوحدة × أساس الأمر
 *
 * و«أساسُ الأمر» كميّتُه (بحسب الكمية) أو تكلفتُه المباشرة — مواد وتشغيل
 * ومصروفات مباشرة — (بحسب التكلفة).
 */
class IndirectCostAllocator
{
    public const BY_QUANTITY = 1;

    public const BY_COST = 2;

    /**
     * حساباتُ المصروفات — ما يُختار منه «الحساب».
     *
     * بلا استبعاد الآباء: في هذا الدليل حساباتُ مصروفاتٍ لها أبناء وعليها
     * قيودٌ مباشرة (آلاف)، واستبعادُها يُخفي المصروفَ نفسَه الذي يُراد توزيعه.
     */
    public function expenseAccounts(): Collection
    {
        return Account::query()
            ->where('account_type', 'expenses')
            ->orderBy('code')
            ->get(['id', 'name', 'code']);
    }

    public function isExpenseAccount($accountId): bool
    {
        return Account::query()
            ->whereKey($accountId)
            ->where('account_type', 'expenses')
            ->exists();
    }

    /** خريطةُ الشجرة: معرّفُ الحساب ⟵ معرّفُ أبيه — تُقرأ مرّةً في الطلب. */
    private ?array $accountParents = null;

    private function accountParents(): array
    {
        return $this->accountParents ??= Account::query()->pluck('parent_id', 'id')->all();
    }

    /**
     * الحسابُ وكلُّ ما تحته في الشجرة.
     *
     * الحسابُ الرئيسيّ («المصروفات») لا قيودَ عليه مباشرةً — قيودُه على
     * فروعه. فمن اختاره أراد مصروفاتِ فروعه كلِّها، ومطابقةُ معرّفه وحده
     * تردّ قائمةً فارغة.
     *
     * @return array<int,int>
     */
    public function accountWithDescendants(int $accountId): array
    {
        $children = [];

        foreach ($this->accountParents() as $id => $parentId) {
            if ($parentId !== null) {
                $children[(int) $parentId][] = (int) $id;
            }
        }

        $ids = [$accountId => true];
        $queue = [$accountId];

        while ($queue) {
            foreach ($children[array_shift($queue)] ?? [] as $childId) {
                if (! isset($ids[$childId])) {
                    $ids[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * آباءُ الحساب صعوداً إلى الجذر.
     *
     * @return array<int,int>
     */
    private function accountAncestors(int $accountId): array
    {
        $parents = $this->accountParents();
        $ancestors = [];
        $current = $parents[$accountId] ?? null;

        while ($current !== null && ! isset($ancestors[(int) $current])) {
            $ancestors[(int) $current] = true;
            $current = $parents[(int) $current] ?? null;
        }

        return array_keys($ancestors);
    }

    /**
     * قيودُ الحساب في الفترة — كلٌّ بصافي حركته على الحساب وفروعه.
     *
     * @param  array<int,int>|null  $branchIds  نطاقُ الجهة، أو null للكلّ
     * @param  int|null  $exceptCostId  التوزيعُ الجاري تعديلُه — قيودُه تبقى ظاهرة
     * @return Collection<int, array{id:int, reference_number:?string, description:?string, date:string, amount:float}>
     */
    public function entries(int $accountId, string $from, string $to, ?array $branchIds = null, ?int $exceptCostId = null): Collection
    {
        $accountIds = $this->accountWithDescendants($accountId);

        // قيدٌ وُزّع على الحساب أو على فرعٍ منه أو على أبٍ له ⟵ لا يُوزَّع ثانيةً
        $used = DB::table('indirect_cost_items as i')
            ->join('indirect_costs as c', 'c.id', '=', 'i.indirect_costs_id')
            ->whereIn('c.account_id', array_merge($accountIds, $this->accountAncestors($accountId)))
            ->whereNotNull('i.restriction_id')
            ->when($exceptCostId, fn ($q) => $q->where('c.id', '!=', $exceptCostId))
            ->select('i.restriction_id');

        $rows = DB::table('journal_entries as e')
            ->join('journal_entry_details as d', 'd.journal_entry_id', '=', 'e.id')
            ->whereIn('d.account_id', $accountIds)
            ->where('e.status', 1)
            // `date` عمودُ تاريخٍ صِرف — المقارنةُ المباشرة تُبقي فهرسَه عاملاً
            ->where('e.date', '>=', $from)
            ->where('e.date', '<=', $to)
            ->whereNotIn('e.id', $used)
            ->when($branchIds !== null, function ($q) use ($branchIds) {
                $q->where(function ($scope) use ($branchIds) {
                    $scope->whereIn('e.branch_id', $branchIds)->orWhereNull('e.branch_id');
                });
            })
            ->groupBy('e.id', 'e.reference_number', 'e.description', 'e.date')
            ->selectRaw('e.id, e.reference_number, e.description, e.date, SUM(COALESCE(d.debit, 0) - COALESCE(d.credit, 0)) AS amount')
            ->havingRaw('SUM(COALESCE(d.debit, 0) - COALESCE(d.credit, 0)) > 0')
            ->orderBy('e.date')
            ->orderBy('e.id')
            ->get();

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'reference_number' => $row->reference_number,
            'description' => $row->description,
            'date' => substr((string) $row->date, 0, 10),
            'amount' => round((float) $row->amount, 2),
        ])->values();
    }

    /**
     * تاريخُ آخر قيدٍ فيه حركةٌ مدينة على الحساب — يُعرض حين تخلو الفترة.
     *
     * الفترةُ المبدئية الشهرُ الجاري، وفي أوّله لا قيودَ بعد: فيُدَلّ
     * المستخدمُ على أين قيودُ حسابه بدل «لا قيود» مجرّدة.
     *
     * @param  array<int,int>|null  $branchIds
     */
    public function lastEntryDate(int $accountId, ?array $branchIds = null): ?string
    {
        $date = DB::table('journal_entries as e')
            ->join('journal_entry_details as d', 'd.journal_entry_id', '=', 'e.id')
            ->whereIn('d.account_id', $this->accountWithDescendants($accountId))
            ->where('e.status', 1)
            ->where('d.debit', '>', 0)
            ->when($branchIds !== null, function ($q) use ($branchIds) {
                $q->where(function ($scope) use ($branchIds) {
                    $scope->whereIn('e.branch_id', $branchIds)->orWhereNull('e.branch_id');
                });
            })
            ->max('e.date');

        return $date ? substr((string) $date, 0, 10) : null;
    }

    /**
     * أوامرُ الفترة غير الملغاة — كلٌّ بكميّته وتكلفته المباشرة.
     *
     * @param  array<int,int>|null  $branchIds
     * @return Collection<int, array{id:int, name:?string, code:?string, product:?string, quantity:float, cost:float, status:string}>
     */
    public function orders(string $from, string $to, ?array $branchIds = null): Collection
    {
        $orders = ManufacturOrders::query()
            ->with('product:id,name')
            ->where('status', '!=', ManufacturOrderStatus::Cancelled->value)
            ->whereDate('from_date', '<=', $to)
            ->whereDate('to_date', '>=', $from)
            ->when($branchIds !== null, function ($q) use ($branchIds) {
                $q->where(function ($scope) use ($branchIds) {
                    $scope->whereIn('branch_id', $branchIds)->orWhereNull('branch_id');
                });
            })
            ->orderBy('from_date')
            ->orderBy('id')
            ->get();

        $costs = $this->directCosts($orders->pluck('id')->all());

        return $orders->map(function (ManufacturOrders $order) use ($costs) {
            $status = $order->status instanceof ManufacturOrderStatus ? $order->status : ManufacturOrderStatus::tryFrom((string) $order->status);

            return [
                'id' => (int) $order->id,
                'name' => $order->name,
                'code' => $order->code,
                'product' => optional($order->product)->name,
                'quantity' => (float) $order->quantity,
                'cost' => (float) ($costs[$order->id] ?? 0),
                'status' => $status ? $status->label() : (string) $order->status,
            ];
        })->values();
    }

    /**
     * التكلفةُ المباشرة لكل أمر — مواد وتشغيل ومصروفاتٌ مباشرة.
     *
     * بنودُ المصروف غير المباشر تُستبعد: دخولُها في الأساس يجعل التوزيعَ
     * يوزَّع على نفسه.
     *
     * @param  array<int,int>  $orderIds
     * @return array<int, float>
     */
    public function directCosts(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return DB::table('manufactur_orders_items')
            ->whereIn('manufactur_order_id', $orderIds)
            ->groupBy('manufactur_order_id')
            ->selectRaw(
                'manufactur_order_id, SUM(COALESCE(raw_total, 0) + COALESCE(manu_total, 0)'
                .' + CASE WHEN indirect_cost_id IS NULL THEN COALESCE(expenses_total, 0) ELSE 0 END) AS cost'
            )
            ->pluck('cost', 'manufactur_order_id')
            ->map(fn ($cost) => round((float) $cost, 2))
            ->all();
    }

    /**
     * التوزيع — نصيبُ الوحدة ونصيبُ كلّ أمر.
     *
     * الكسرُ الباقي من التقريب يُضاف إلى آخر أمرٍ كي يساوي مجموعُ الأنصبة
     * الإجماليَّ بالهللة.
     *
     * @param  iterable<array{id:int, quantity:float, cost:float}>  $orders
     * @return array{base:float, rate:float, shares:array<int, float>}
     */
    public function distribute(float $total, iterable $orders, int $basedOn): array
    {
        $bases = [];

        foreach ($orders as $order) {
            $bases[(int) $order['id']] = max(0.0, (float) ($basedOn === self::BY_COST ? $order['cost'] : $order['quantity']));
        }

        $base = array_sum($bases);

        if ($base <= 0 || $total <= 0) {
            return ['base' => round($base, 4), 'rate' => 0.0, 'shares' => array_fill_keys(array_keys($bases), 0.0)];
        }

        $rate = $total / $base;
        $shares = [];
        $allocated = 0.0;

        foreach ($bases as $orderId => $orderBase) {
            $shares[$orderId] = round($rate * $orderBase, 2);
            $allocated += $shares[$orderId];
        }

        // فرقُ التقريب على آخر أمرٍ له أساس
        $difference = round($total - $allocated, 2);

        if (abs($difference) >= 0.01) {
            foreach (array_reverse(array_keys($bases)) as $orderId) {
                if ($bases[$orderId] > 0) {
                    $shares[$orderId] = round($shares[$orderId] + $difference, 2);
                    break;
                }
            }
        }

        return ['base' => round($base, 4), 'rate' => round($rate, 6), 'shares' => $shares];
    }

    /**
     * نصيبُ الوحدة الساري لتكلفةٍ غير مباشرة.
     *
     *   • حُدّدت أوامرُ عند الإدخال ⟵ النصيبُ المحفوظ في الرأس.
     *   • لم تُحدَّد (اختيارُها اختياريّ) ⟵ الإجماليّ ÷ أساس أوامر الفترة
     *     غير الملغاة القائمة الآن.
     *   • لا أوامر في الفترة أصلاً ⟵ `null`: لا أساسَ يُقسَم عليه، فيتحمّل
     *     الأمرُ الذي يختار التكلفةَ إجماليَّها كاملاً.
     */
    public function effectiveRate(IndirectCost $cost): ?float
    {
        if ((float) $cost->unit_rate > 0) {
            return (float) $cost->unit_rate;
        }

        $byCost = (int) $cost->based_on === self::BY_COST;

        $base = (float) $this
            ->orders(substr((string) $cost->from_date, 0, 10), substr((string) $cost->to_date, 0, 10))
            ->sum($byCost ? 'cost' : 'quantity');

        return $base > 0 ? round((float) $cost->total / $base, 6) : null;
    }

    /**
     * نصيبُ أمرٍ من تكلفةٍ غير مباشرة — يُحسب عند حفظ الأمر.
     *
     *     بحسب الكمية   نصيب الوحدة × كمية الأمر
     *     بحسب التكلفة  نصيب الوحدة × التكلفة المباشرة للأمر
     *
     * ولا يتجاوز النصيبُ إجماليَّ التكلفة نفسِها.
     */
    public function shareFor(IndirectCost $cost, float $orderQuantity, float $orderDirectCost): float
    {
        $rate = $this->effectiveRate($cost);

        if ($rate === null) {
            return round((float) $cost->total, 2);
        }

        $base = (int) $cost->based_on === self::BY_COST ? $orderDirectCost : $orderQuantity;

        return round(min((float) $cost->total, $rate * max(0.0, $base)), 2);
    }

    /** ما حُمِّل فعلاً من هذه التكلفة على أوامر التصنيع (غير الملغاة). */
    public function appliedTotal(int $costId): float
    {
        return round((float) DB::table('manufactur_orders_items as i')
            ->join('manufactur_orders as o', 'o.id', '=', 'i.manufactur_order_id')
            ->where('i.indirect_cost_id', $costId)
            ->where('o.status', '!=', ManufacturOrderStatus::Cancelled->value)
            ->sum('i.expenses_total'), 2);
    }
}
