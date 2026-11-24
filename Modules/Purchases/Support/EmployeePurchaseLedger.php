<?php

namespace Modules\Purchases\Support;

use App\Support\ClosedPeriodRanges;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * دفتر مشتريات الموظّفين — نفس أرقام `SupplierLedger` مجمَّعةً بمن أنشأ القيد.
 *
 * **لماذا لا يحسب المتحكّم أرقامه بنفسه؟**
 *
 * كان `EmployeePurchasesReportController` يجمع مدين حسابَي «المشتريات»
 * و«ضريبة المدخلات» من تفاصيل القيد، بينما `SupplierLedger` يجمع دائن حساب
 * المورّد. الرقمان لا يتطابقان: الأول يسقط الشحن وأي بندٍ يُرحَّل إلى حسابٍ
 * آخر، والثاني هو الالتزام الفعلي على المنشأة. فكان «إجمالي المشتريات» في
 * شاشة الموظّفين يخالف نظيره في شاشة الموردين للفترة نفسها.
 *
 * وكان السداد أسوأ: يُجمَع بحلقةٍ تنفّذ استعلاماً لكل موظّف، وداخلها
 * استعلامٌ آخر لكل قيد سداد يجلب حسابات الخزائن والبنوك — فعشرون موظّفاً
 * بمئة سند يعني أكثر من ألفَي استعلام في الطلب الواحد.
 *
 * هنا الأساس واحد: حركة حسابات الموردين نفسها، مجمَّعةً بـ
 * `journal_entries.created_by_employee` في استعلامٍ واحدٍ مجمَّع:
 *
 *     مشتريات      = دائن قيود  PI
 *     مرتجعات      = مدين قيود  PR
 *     إشعارات مدينة = مدين قيود  DN
 *     مدفوعات      = صافي قيود  PAY و PV و PCAN و PCHQ و PCHQR
 *     تسويات       = صافي ما عدا ذلك
 *
 * والتصنيفُ نفسُه دالّةٌ واحدة: `SupplierLedger::accumulate()`.
 *
 * فمجموع الموظّفين في فترةٍ = مجموع الموردين فيها، رقماً برقم.
 *
 * @see SupplierLedger مصدر التصنيف والقاعدة المحاسبية
 */
class EmployeePurchaseLedger
{
    public function __construct(
        protected ?int $employeeId = null,
        protected ?int $supplierId = null,
        protected int|array|null $branchId = null,
    ) {}

    /**
     * معرّفاتُ المستخدمين الذين يحقّ لقارئ التقرير رؤيةُ ما أنشأوه.
     *
     * `null` تعني «بلا قيد» لا «لا أحد» — والدفترُ يُستعمل من الشاشات ومن
     * الأوامر المجدولة معاً، فالافتراضُ فيه عدمُ التقييد ويأتيه النطاقُ من
     * المتحكّم عبر {@see visibleTo()}. ولولا ذلك لأفرغ أمرُ كرونٍ بلا
     * مستخدمٍ مسجَّلٍ كلَّ تقريرٍ يبنيه.
     *
     * @var array<int, int>|null
     */
    protected ?array $visibleCreatorIds = null;

    /**
     * قصرُ الدفتر على ما أنشأه هؤلاء — نطاقُ الرؤية آتياً من المتحكّم.
     *
     * @param  array<int, int>|null  $userIds  معرّفاتُ `users` لا `employees`،
     *                                         فالعمود `je.created_by_employee`
     *                                         مُسمّى «موظف» وهو مستخدم.
     */
    public function visibleTo(?array $userIds): static
    {
        $this->visibleCreatorIds = $userIds;

        return $this;
    }

    public static function fromFilters(?int $employeeId, ?int $supplierId, int|array|null $branchId): self
    {
        return new self($employeeId, $supplierId, $branchId);
    }

    /**
     * الفروع المطلوبة كقائمة معرّفات — نفس قاعدة `SupplierLedger`.
     *
     * يقبل معرّفاً واحداً أو قائمة، فعقدةٌ في شجرة «تابع لـ» قد تُترجم إلى
     * فروعها كلّها. القائمة الفارغة تعني «كل الفروع» فلا يُطبَّق شرط.
     *
     * @return array<int, int>
     */
    protected function branchIds(): array
    {
        return collect(is_array($this->branchId) ? $this->branchId : [$this->branchId])
            ->reject(fn ($id) => $id === null || $id === '' || $id === 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * رصيدُ ما قبل الفترة لكل موظّف — نظيرُ `SupplierLedger::openingBalances()`
     * حرفياً، مجمَّعاً بمن أنشأ القيد لا بالمورّد.
     *
     * **لماذا موظّفٌ له «رصيدٌ افتتاحي» وهو ليس طرفاً في الدفتر؟** لأن
     * تقرير «حسب الموظف» يريد المعادلة نفسها التي يريدها تقرير «حسب
     * المورد» بالضبط — لا تصل الشاشتان للرقم نفسه إلا حين يُقاس رصيدُ
     * الموظّف بالطريقة نفسها: كلُّ ما أنشأه من قيودٍ قبل الفترة، لا فقط ما
     * وقع فيها. وبلا هذا كانت الشاشتان تتطابقان فقط حين يشمل المدى
     * التاريخَ كلَّه منذ التأسيس — ولأي مدىً أقصر (شهرٌ، سنةٌ) يظهر «غير
     * المسدد» أقلَّ من «رصيد آخر المدة» بمقدار افتتاحي المورّدين الذين
     * تعامل معهم الموظّفُ قبل بداية المدى.
     *
     * @return array<int, float> employee_id ← الرصيد
     */
    public function openingBalances(ReportPeriod $period): array
    {
        $rows = $this->baseQuery()
            ->where('je.date', '<', $period->from->toDateString())
            ->groupBy('je.created_by_employee')
            ->select([
                'je.created_by_employee as employee_id',
                DB::raw('SUM(jed.credit) - SUM(jed.debit) as balance'),
            ])
            ->get();

        return $rows->mapWithKeys(fn ($row) => [(int) $row->employee_id => (float) $row->balance])->all();
    }

    /**
     * مجاميع الفترة لكل موظّف، مصنَّفةً حسب نوع المستند.
     *
     * استعلامٌ واحد يجمّع في قاعدة البيانات؛ لا حلقة ولا استعلامٌ لكل موظّف.
     *
     * @return array<int, array<string, float|int>> employee_id ← الأرقام
     */
    public function periodTotals(ReportPeriod $period): array
    {
        $rows = $this->baseQuery()
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->groupBy('je.created_by_employee', 'je.reference_type')
            ->select([
                'je.created_by_employee as employee_id',
                'je.reference_type',
                DB::raw('SUM(jed.debit) as debit'),
                DB::raw('SUM(jed.credit) as credit'),
                DB::raw('COUNT(DISTINCT je.id) as entries'),
                DB::raw('COUNT(DISTINCT CASE WHEN jed.debit > 0 THEN je.id END) as debit_entries'),
                DB::raw('COUNT(DISTINCT CASE WHEN jed.credit > 0 THEN je.id END) as credit_entries'),
            ])
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $employeeId = (int) $row->employee_id;
            $totals[$employeeId] ??= self::emptyTotals();

            // نفس تصنيف دفتر الموردين حرفياً — الدالّةُ نفسُها لا نسخةٌ منها،
            // فأيُّ انحرافٍ هنا كان يفكّ التطابق بين الشاشتين
            SupplierLedger::accumulate(
                $totals[$employeeId],
                (string) $row->reference_type,
                (float) $row->debit,
                (float) $row->credit,
                (int) $row->entries,
                (int) $row->debit_entries,
                (int) $row->credit_entries,
            );
        }

        // عدد الموردين المتعاملين — مميَّزاً على مستوى الموظّف، لا مجموعَ عدّادات الأنواع
        foreach ($this->supplierCounts($period) as $employeeId => $count) {
            if (isset($totals[$employeeId])) {
                $totals[$employeeId]['suppliers_count'] = $count;
            }
        }

        return $totals;
    }

    /**
     * عدد الموردين المميَّزين لكل موظّف في الفترة.
     *
     * @return array<int, int>
     */
    protected function supplierCounts(ReportPeriod $period): array
    {
        return $this->baseQuery()
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->groupBy('je.created_by_employee')
            ->select([
                'je.created_by_employee as employee_id',
                DB::raw('COUNT(DISTINCT a.supplier_id) as suppliers'),
            ])
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->employee_id => (int) $row->suppliers])
            ->all();
    }

    /**
     * حركة الفترة مفصَّلةً — قيداً قيداً مع مورّده وفرعه، مرتّبةً زمنياً.
     *
     * @return Collection<int, \stdClass> صفوف عليها employee_id
     */
    public function movements(ReportPeriod $period): Collection
    {
        return $this->baseQuery()
            ->leftJoin('suppliers as s', 's.id', '=', 'a.supplier_id')
            ->leftJoin('branches as b', 'b.id', '=', 'je.branch_id')
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->groupBy(
                'je.created_by_employee', 'je.id', 'je.reference_number', 'je.reference_type',
                'je.date', 'je.description', 'je.purchase_invoice_id', 's.trade_name', 'b.name'
            )
            ->select([
                'je.created_by_employee as employee_id',
                'je.id as entry_id',
                'je.reference_number',
                'je.reference_type',
                'je.date',
                'je.description',
                'je.purchase_invoice_id',
                's.trade_name as supplier_name',
                'b.name as branch_name',
                DB::raw('SUM(jed.debit) as debit'),
                DB::raw('SUM(jed.credit) as credit'),
            ])
            ->orderBy('je.date')
            ->orderBy('je.id')
            ->get();
    }

    /**
     * سلسلة زمنية للمشتريات والمدفوعات — لرسم اتجاه الفترة.
     *
     * @return array{labels: array<int, string>, purchases: array<int, float>, payments: array<int, float>}
     */
    public function trend(ReportPeriod $period): array
    {
        $unit = $period->trendUnit();
        $bucket = $unit === 'month'
            ? "DATE_FORMAT(je.date, '%Y-%m')"
            : "DATE_FORMAT(je.date, '%Y-%m-%d')";

        $rows = $this->baseQuery()
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->whereIn('je.reference_type', array_merge(SupplierLedger::PURCHASE_TYPES, SupplierLedger::PAYMENT_TYPES))
            ->groupBy(DB::raw($bucket), 'je.reference_type')
            ->select([
                DB::raw("$bucket as bucket"),
                'je.reference_type',
                DB::raw('SUM(jed.debit) as debit'),
                DB::raw('SUM(jed.credit) as credit'),
            ])
            ->orderBy('bucket')
            ->get();

        $purchases = [];
        $payments = [];

        foreach ($rows as $row) {
            $key = (string) $row->bucket;
            $purchases[$key] ??= 0;
            $payments[$key] ??= 0;

            if (SupplierLedger::classify((string) $row->reference_type) === 'purchase') {
                $purchases[$key] += (float) $row->credit - (float) $row->debit;
            } else {
                $payments[$key] += (float) $row->debit - (float) $row->credit;
            }
        }

        $labels = array_keys($purchases);
        sort($labels);

        return [
            'labels' => array_map(fn ($l) => $unit === 'month' ? $l : substr($l, 5), $labels),
            'purchases' => array_map(fn ($l) => round($purchases[$l], 2), $labels),
            'payments' => array_map(fn ($l) => round($payments[$l], 2), $labels),
        ];
    }

    /** الأعمدة الصفرية — يُبنى عليها كل تجميع */
    public static function emptyTotals(): array
    {
        return [
            'purchases' => 0.0,
            'purchases_count' => 0,
            'returns' => 0.0,
            'returns_count' => 0,
            'debit_notes' => 0.0,
            'debit_notes_count' => 0,
            'payments' => 0.0,
            'payments_count' => 0,
            'adjustments' => 0.0,
            'adjustments_count' => 0,
            'entries_count' => 0,
            'suppliers_count' => 0,
        ];
    }

    /**
     * الاستعلام الأساس: تفاصيل القيود المرحّلة على حسابات الموردين.
     *
     * القيود بلا منشئ (`created_by_employee` فارغ) تُستبعد — لا موظّف
     * تُنسب إليه، وإدراجها يخلق صفّاً بلا اسم يفسد المجموع.
     */
    protected function baseQuery()
    {
        $query = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->whereNotNull('a.supplier_id')
            ->whereNotNull('je.created_by_employee')
            ->where('je.status', 1);

        SupplierLedger::excludeWalletAccounts($query, 'a');

        if ($this->employeeId) {
            $query->where('je.created_by_employee', $this->employeeId);
        }

        if ($this->visibleCreatorIds !== null) {
            $query->whereIn('je.created_by_employee', $this->visibleCreatorIds);
        }

        if ($this->supplierId) {
            $query->where('a.supplier_id', $this->supplierId);
        }

        // المحورُ الواحد نفسُه الذي في `SupplierLedger` — لا نسخةٌ منه
        if (($branchIds = $this->branchIds()) !== []) {
            SupplierLedger::matchBranchNodes($query, $branchIds);
        }

        return $query;
    }
}
