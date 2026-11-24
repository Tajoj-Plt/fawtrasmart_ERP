<?php

namespace Modules\Purchases\Support;

use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\ClosedPeriodRanges;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * دفتر أستاذ المورّدين — مصدر أرقام تقارير المشتريات كلّها.
 *
 * **لماذا هنا لا في المتحكّم؟**
 *
 * كانت كل شاشة تحسب أرقام المورّد بطريقتها: واحدة تجمع
 * `purchase_invoices.grand_total`، وأخرى تجمع دائن حساب المورّد،
 * وثالثة تقرأ `accounts.balance` جاهزاً. والثلاثة لا تتفق: الأولى تحسب
 * أمر الشراء مشترياتٍ وهو ليس مستنداً مالياً، والثانية كانت تطرح
 * الدائن من المدين فيخرج ما ندين به بإشارة سالبة، والثالثة رصيدٌ
 * تراكمي لا علاقة له بالفترة المطلوبة.
 *
 * **القاعدة الواحدة:** حساب المورّد التزامٌ — يزيد بالدائن وينقص
 * بالمدين. فالرصيد الموجب يعني «له علينا»، وكل رقم في التقرير مشتقّ
 * من قيود مرحّلة (`status = 1`) على حسابه:
 *
 *     مشتريات      = دائن قيود  PI
 *     مرتجعات      = مدين قيود  PR
 *     إشعارات مدينة = مدين قيود  DN
 *     مدفوعات      = صافي قيود  PAY و PV و PCAN و PCHQ و PCHQR
 *     تسويات       = صافي ما عدا ذلك (قيد افتتاحي، قيد يومية…)
 *
 * ورصيد آخر المدة = رصيد أوّلها + المشتريات − المرتجعات − الإشعارات
 * − المدفوعات + التسويات؛ يطابق `accounts.balance` حين تشمل الفترة
 * كل القيود.
 */
class SupplierLedger
{
    /** قيود تُحتسب مشترياتٍ */
    public const PURCHASE_TYPES = [ReferenceType::PURCHASE_INVOICE->value];

    /** قيود تُحتسب مرتجعاً */
    public const RETURN_TYPES = [ReferenceType::PURCHASE_RETURN->value];

    /** قيود تُحتسب إشعاراً مديناً */
    public const DEBIT_NOTE_TYPES = [ReferenceType::DEBIT_NOTE->value];

    /**
     * قيود تُحتسب سداداً للمورّد — بموجبها وعكسِها.
     *
     * السدادُ ما نقص من التزامنا للمورّد نقداً أو بورقةٍ تجارية، وإلغاؤه
     * يُعيده. فالدفعةُ الملغاة (`PCAN` — «قيد عكسي لإلغاء دفعة للمورد»)
     * والشيكُ الصادر (`PCHQ`) ورفضُه (`PCHQR`) تقع هنا لا في «التسويات»:
     * السداد `مدين − دائن`، فالقيدُ العكسيُّ يطرح نفسَه بنفسه.
     *
     * **العلّة التي كانت:** الإلغاءُ يُصنَّف تسويةً، فتبقى الدفعةُ الملغاة
     * محسوبةً سداداً — ثلاثةُ إلغاءاتٍ بـ١٤٬٩٩٩٫٩٩ كانت تُضخّم «المدفوع
     * للموردين». وهي العلّةُ نفسُها التي أُصلحت في تحصيلات المبيعات
     * (`SalesLedger::COLLECTION_TYPES`).
     */
    public const PAYMENT_TYPES = [
        ReferenceType::SUPPLIER_PAYMENT->value,
        ReferenceType::PAYMENT_VOUCHER->value,
        ReferenceType::PAYMENT_CANCELLATION->value,
        ReferenceType::PAYABLE_CHEQUE->value,
        ReferenceType::PAYABLE_CHEQUE_REJECT->value,
    ];

    /**
     * الفلاتر المطبَّقة — تُبنى مرة وتُمرَّر لكل استعلام.
     *
     * @param  int|null  $supplierId  مورّد بعينه
     * @param  int|null  $employeeId  منشئ القيد
     * @param  int|null  $branchId  فرع القيد
     */
    public function __construct(
        protected ?int $supplierId = null,
        protected ?int $employeeId = null,
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

    /** قصرُ الدفتر على ما لا عقدةَ فرعٍ له — صفُّ «غير محدّد» */
    protected bool $unbranchedOnly = false;

    /**
     * ما لا عقدةَ له وحده — نقيضُ تحديد الفروع، لا تضييقٌ عليها.
     *
     * تستعمله قائمةُ «غير محدّد» في شجرة اللوحة: ذاك الصفُّ يُجمع في
     * `rawByBranch()` بمفتاح `none`، ولا تصفُه قائمةُ معرّفاتٍ أصلاً.
     */
    public function onlyUnbranched(bool $on = true): static
    {
        $this->unbranchedOnly = $on;

        return $this;
    }

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

    public static function fromFilters(?int $supplierId, ?int $employeeId, int|array|null $branchId): self
    {
        return new self($supplierId, $employeeId, $branchId);
    }

    /**
     * الفروع المطلوبة كقائمة معرّفات.
     *
     * يقبل معرّفاً واحداً أو قائمة، فشجرة الفروع تعني أن اختيار أبٍ قد
     * يشمل أبناءه. القائمة الفارغة تعني «كل الفروع» فلا يُطبَّق شرط.
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
     * حسابات المورّدين المشمولة — الحساب هو نقطة الربط لا جدول الموردين،
     * فمورّد بلا حساب لا حركة له أصلاً.
     *
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        $query = Account::with('supplier')->whereNotNull('supplier_id');

        /* حسابُ المحفظة يحمل `supplier_id` أيضاً، فكان المورّدُ صاحبُ
           المحفظة يعود حسابين — وتقاريرُ الموردين تجمع أرقامَ `periodTotals`
           (المفهرسةَ بالمورّد) على كلّ حساب، فتُحسب مشترياتُه ومدفوعاتُه
           مرّتين: ٢٤٬١٠٤٫٨٠ في شهرٍ حقيقتُه ١٥٬١٦٢٫٠٠. الاستعلامُ الأساس يستبعد
           المحافظَ أصلاً، فيُستبعد هنا بالقاعدة نفسِها. */
        self::excludeWalletAccounts($query, 'accounts');

        if ($this->supplierId) {
            $query->where('supplier_id', $this->supplierId);
        }

        return $query->get()->filter(fn ($account) => $account->supplier !== null)->values();
    }

    /**
     * مجاميع الفترة لكل مورّد، مصنَّفةً حسب نوع المستند.
     *
     * @return array<int, array<string, float|int>> supplier_id ← الأرقام
     */
    public function periodTotals(ReportPeriod $period): array
    {
        $rows = $this->baseQuery()
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->groupBy('a.supplier_id', 'je.reference_type')
            ->select([
                'a.supplier_id',
                'je.reference_type',
                DB::raw('SUM(jed.debit) as debit'),
                DB::raw('SUM(jed.credit) as credit'),
                DB::raw('COUNT(DISTINCT je.id) as entries'),
                // عدّ المستندات بالطرف الذي يخصّها: فاتورةٌ دائنة، ومرتجعٌ مدين
                DB::raw('COUNT(DISTINCT CASE WHEN jed.debit > 0 THEN je.id END) as debit_entries'),
                DB::raw('COUNT(DISTINCT CASE WHEN jed.credit > 0 THEN je.id END) as credit_entries'),
            ])
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $supplierId = (int) $row->supplier_id;
            $totals[$supplierId] ??= self::emptyTotals();

            self::accumulate(
                $totals[$supplierId],
                (string) $row->reference_type,
                (float) $row->debit,
                (float) $row->credit,
                (int) $row->entries,
                (int) $row->debit_entries,
                (int) $row->credit_entries,
            );
        }

        return $totals;
    }

    /**
     * يُضيف حركةَ نوع قيدٍ واحد إلى مجاميع مورّد — **التصنيفُ الوحيد**.
     *
     * يستعمله هذا الدفتر و`EmployeePurchaseLedger` معاً، فمجموعُ الموظّفين
     * يساوي مجموعَ الموردين بالبناء لا بنسختين متطابقتين يُنتظر ألّا تفترقا.
     *
     * **الطرفُ يُصنَّف لا المستند** — القاعدةُ نفسُها في
     * `SalesLedger::periodTotals()`. قيدُ المرتجع أو الإشعار قد يحمل طرفاً
     * دائناً على المورّد: قيمةٌ ردّها المورّد نقداً. فجمعُ `مدين − دائن` على
     * نوع المستند كان يطرح المستردَّ من قيمة المرتجعات، فتقرأ اللوحةُ أقلَّ
     * ممّا تقرأ شاشةُ المرتجعات لنفس المدة. فالمرتجعُ والإشعارُ = طرفُهما
     * المدين وحده، والدائنُ تسويةٌ تُبقي المعادلة صحيحةً بالبناء:
     *
     *     ختامي = افتتاحي + مشتريات − مرتجعات − إشعارات − مدفوعات + تسويات
     *
     * @param  array<string, float|int>  $totals
     */
    public static function accumulate(
        array &$totals,
        string $type,
        float $debit,
        float $credit,
        int $entries,
        int $debitEntries,
        int $creditEntries,
    ): void {
        switch (self::classify($type)) {
            case 'purchase':
                $totals['purchases'] += $credit - $debit;
                $totals['purchases_count'] += $creditEntries;
                break;

            case 'return':
                // قيمةُ المرتجع طرفُه المدين؛ ودائنُه ما ردّه المورّد نقداً
                $totals['returns'] += $debit;
                $totals['returns_count'] += $debitEntries;
                $totals['adjustments'] += $credit;
                break;

            case 'debit_note':
                $totals['debit_notes'] += $debit;
                $totals['debit_notes_count'] += $debitEntries;
                $totals['adjustments'] += $credit;
                break;

            case 'payment':
                $totals['payments'] += $debit - $credit;
                $totals['payments_count'] += $entries;
                break;

            default:
                $totals['adjustments'] += $credit - $debit;
                $totals['adjustments_count'] += $entries;
        }

        $totals['entries_count'] += $entries;
    }

    /**
     * رصيد ما قبل الفترة لكل مورّد — دائن موجب.
     *
     * @return array<int, float>
     */
    /*
        الأرصدة لا تُستبعَد منها الفتراتُ المقفلة — بخلاف الحركة.
        الرصيدُ حصيلةُ التاريخ كلِّه: من أقفل ٢٠٢٥ لم يُسقط ما للمورّد
        منها، وحذفُ حركتها من الرصيد يخترع رقماً لا يقابله شيء في دفتر
        الأستاذ. فالإخفاءُ يقع على ما جرى **في** الفترة، لا على ما بقي
        **بعدها** — وهي القاعدةُ نفسُها في `balancesAsOf()` و`creditsAsOf()`.
     */
    public function openingBalances(ReportPeriod $period): array
    {
        $rows = $this->baseQuery()
            ->where('je.date', '<', $period->from->toDateString())
            ->groupBy('a.supplier_id')
            ->select([
                'a.supplier_id',
                DB::raw('SUM(jed.credit) - SUM(jed.debit) as balance'),
            ])
            ->get();

        return $rows->mapWithKeys(fn ($row) => [(int) $row->supplier_id => (float) $row->balance])->all();
    }

    /**
     * حركة الفترة مفصَّلة — قيداً قيداً، مرتّبةً زمنياً.
     *
     * @return Collection<int, \stdClass> صفوف عليها supplier_id
     */
    public function movements(ReportPeriod $period): Collection
    {
        return $this->baseQuery()
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by_employee')
            ->leftJoin('branches as b', 'b.id', '=', 'je.branch_id')
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->groupBy('a.supplier_id', 'je.id', 'je.reference_number', 'je.reference_type', 'je.date', 'je.description', 'je.purchase_invoice_id', 'je.created_by_employee', 'u.name', 'b.name')
            ->select([
                'a.supplier_id',
                'je.id as entry_id',
                'je.reference_number',
                'je.reference_type',
                'je.date',
                'je.description',
                'je.purchase_invoice_id',
                // منشئُ القيد — قائمةُ «أداء الموظفين» تُبنى منه
                'je.created_by_employee',
                'u.name as employee_name',
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
            ->whereIn('je.reference_type', array_merge(self::PURCHASE_TYPES, self::PAYMENT_TYPES))
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

            if (in_array((string) $row->reference_type, self::PURCHASE_TYPES, true)) {
                $purchases[$key] += (float) $row->credit - (float) $row->debit;
            } else {
                $payments[$key] += (float) $row->debit - (float) $row->credit;
            }
        }

        $labels = array_keys($purchases);
        sort($labels);

        return [
            // المفتاحُ كاملاً (Y-m أو Y-m-d) — نقرُ نقطةٍ في الرسم يفتح حركتها
            'buckets' => $labels,
            'labels' => array_map(
                fn ($label) => $unit === 'month' ? $label : substr($label, 5),
                $labels
            ),
            'purchases' => array_map(fn ($label) => round($purchases[$label], 2), $labels),
            'payments' => array_map(fn ($label) => round($payments[$label], 2), $labels),
        ];
    }

    /**
     * طرفا الحساب خاماً لكل مورّد في مدىً — لتقرير «أرصدة الموردين».
     *
     * التقريرُ مرآةُ «أرصدة العملاء»، وذاك يعرض المعادلةَ المحاسبية بطرفَيها
     * لا بتصنيف المستندات: (قيودُ الرصيد الافتتاحي + ما قبل المدى) + دائنُ
     * المدى − مدينُه = الختامي. فتُعاد الأرقامُ مجموعةً على المورّد ونوعِ
     * المرجع، ويُفصل القيدُ الافتتاحي (`OPB`) عن بقيّة الحركة كي لا تُقرأ
     * فاتورةٌ قديمةٌ «رصيداً افتتاحياً».
     *
     * لا يغيّر شيئاً في قاعدة الدفتر: نفسُ `baseQuery()` ونطاقِه، والاستبعادُ
     * المحاسبي (`$excludeClosed`) على حركة المدى وحدَها كما في
     * {@see periodTotals()}، لا على ما قبله كما في {@see openingBalances()}.
     *
     * @return array<int, array{
     *     debit: float, credit: float, opening_debit: float, opening_credit: float,
     *     last_movement: ?string, last_charge: ?string, figures: array<string, float|int>
     * }>
     */
    public function sideTotals(?string $from, ?string $to, bool $excludeClosed = false): array
    {
        $query = $this->baseQuery();

        if ($from) {
            $query->where('je.date', '>=', $from);
        }

        if ($to) {
            $query->where('je.date', '<=', $to);
        }

        if ($excludeClosed) {
            ClosedPeriodRanges::exclude($query, 'je.date');
        }

        $rows = $query
            ->groupBy('a.supplier_id', 'je.reference_type')
            ->select([
                'a.supplier_id',
                'je.reference_type',
                DB::raw('SUM(jed.debit) as debit'),
                DB::raw('SUM(jed.credit) as credit'),
                DB::raw('COUNT(DISTINCT je.id) as entries'),
                DB::raw('COUNT(DISTINCT CASE WHEN jed.debit > 0 THEN je.id END) as debit_entries'),
                DB::raw('COUNT(DISTINCT CASE WHEN jed.credit > 0 THEN je.id END) as credit_entries'),
                DB::raw('MAX(je.date) as last_date'),
                // آخر ما حمّل الحساب (دائنٌ على المورّد) — عدّادُ «الأيام»
                DB::raw('MAX(CASE WHEN jed.credit > 0 THEN je.date END) as last_credit_date'),
            ])
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $supplierId = (int) $row->supplier_id;
            $out[$supplierId] ??= [
                'debit' => 0.0, 'credit' => 0.0,
                'opening_debit' => 0.0, 'opening_credit' => 0.0,
                'last_movement' => null, 'last_charge' => null,
                'figures' => self::emptyTotals(),
            ];

            $type = (string) $row->reference_type;
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;

            if ($type === ReferenceType::OPENING_BALANCE->value) {
                $out[$supplierId]['opening_debit'] += $debit;
                $out[$supplierId]['opening_credit'] += $credit;
            } else {
                $out[$supplierId]['debit'] += $debit;
                $out[$supplierId]['credit'] += $credit;
            }

            self::accumulate(
                $out[$supplierId]['figures'],
                $type,
                $debit,
                $credit,
                (int) $row->entries,
                (int) $row->debit_entries,
                (int) $row->credit_entries,
            );

            $last = $row->last_date ? substr((string) $row->last_date, 0, 10) : null;
            if ($last && (! $out[$supplierId]['last_movement'] || $last > $out[$supplierId]['last_movement'])) {
                $out[$supplierId]['last_movement'] = $last;
            }

            $charge = $row->last_credit_date ? substr((string) $row->last_credit_date, 0, 10) : null;
            if ($charge && self::classify($type) === 'purchase'
                && (! $out[$supplierId]['last_charge'] || $charge > $out[$supplierId]['last_charge'])) {
                $out[$supplierId]['last_charge'] = $charge;
            }
        }

        return $out;
    }

    /**
     * تاريخُ آخر حركةٍ لكل مورّد حتى تاريخ — عمودُ «آخر حركة» في القوائم.
     *
     * @return array<int, string> `[supplierId => 'Y-m-d']`
     */
    public function lastMovementDates(Carbon $asOf): array
    {
        return $this->baseQuery()
            ->where('je.date', '<=', $asOf->toDateString())
            ->groupBy('a.supplier_id')
            ->selectRaw('a.supplier_id, MAX(je.date) as last_date')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->supplier_id => substr((string) $row->last_date, 0, 10)])
            ->all();
    }

    /**
     * رصيد كل مورّد حتى تاريخ — دائن موجب، أي «له علينا».
     *
     * @return array<int, float> supplier_id ← الرصيد
     */
    public function balancesAsOf(Carbon $asOf): array
    {
        return $this->baseQuery()
            ->where('je.date', '<=', $asOf->toDateString())
            ->groupBy('a.supplier_id')
            ->selectRaw('a.supplier_id, COALESCE(SUM(jed.credit),0) - COALESCE(SUM(jed.debit),0) as balance')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->supplier_id => (float) $row->balance])
            ->all();
    }

    /**
     * حركات الدائن حتى تاريخ، من الأحدث إلى الأقدم — مادّةُ أعمار الديون.
     *
     * تُجلب لكل الموردين دفعةً واحدة بدل استعلامٍ لكل مورّد، ومرتّبةً في
     * قاعدة البيانات فلا تُرتَّب في الذاكرة.
     *
     * @return Collection<int, \stdClass> صفوف: supplier_id, date, credit
     */
    public function creditsAsOf(Carbon $asOf): Collection
    {
        return $this->baseQuery()
            ->where('je.date', '<=', $asOf->toDateString())
            ->where('jed.credit', '>', 0)
            ->groupBy('a.supplier_id', 'je.id', 'je.date')
            ->selectRaw('a.supplier_id, je.date, SUM(jed.credit) as credit')
            ->orderByDesc('je.date')
            ->orderByDesc('je.id')
            ->get();
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
        ];
    }

    /** الاسم العربي لنوع المستند */
    public static function typeName(?string $type): string
    {
        $case = $type ? ReferenceType::fromValue($type) : null;

        return $case?->getName() ?? 'قيد يومية';
    }

    /** تصنيف الحركة: purchase | return | debit_note | payment | other */
    public static function classify(?string $type): string
    {
        return match (true) {
            in_array((string) $type, self::PURCHASE_TYPES, true) => 'purchase',
            in_array((string) $type, self::RETURN_TYPES, true) => 'return',
            in_array((string) $type, self::DEBIT_NOTE_TYPES, true) => 'debit_note',
            in_array((string) $type, self::PAYMENT_TYPES, true) => 'payment',
            default => 'other',
        };
    }

    /**
     * الاستعلام الأساس: تفاصيل القيود المرحّلة على حسابات الموردين.
     *
     * الفرع محورٌ واحد — `branchNodeExpr()` — لا «ختمُ القيد أو فرعُ
     * فاتورته» كما كان. انظر `matchBranchNodes()` لسبب ذلك.
     */
    protected function baseQuery()
    {
        $query = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->whereNotNull('a.supplier_id')
            ->where('je.status', 1);

        self::excludeWalletAccounts($query, 'a');

        if ($this->supplierId) {
            $query->where('a.supplier_id', $this->supplierId);
        }

        if ($this->employeeId) {
            $query->where('je.created_by_employee', $this->employeeId);
        }

        if ($this->visibleCreatorIds !== null) {
            $query->whereIn('je.created_by_employee', $this->visibleCreatorIds);
        }

        if ($this->unbranchedOnly) {
            self::matchNoBranchNode($query);
        } elseif (($branchIds = $this->branchIds()) !== []) {
            self::matchBranchNodes($query, $branchIds);
        }

        return $query;
    }

    /**
     * تعبيرُ SQL لفرع الحركة — فرعُ فاتورتها، وإلا ختمُ القيد.
     *
     * **لماذا الفاتورةُ أولاً؟** لأن فرعها يُختار عمداً عند الإنشاء (مُنتقي
     * الفرع أو `Scopes::resolveBranchId()`)، بينما `je.branch_id` يُملأ
     * آلياً من `users.branch_id` لمن رحّل القيد (`JournalEntry::boot()`).
     * فالأولُ قرارٌ والثاني أثرٌ جانبي — و٢٦ قيداً في هذه المنشأة ختمُها
     * منطقةٌ وفاتورتُها على الفرع الرئيسي.
     *
     * والقيدُ بلا فاتورة (سدادٌ غير مربوط، قيدُ يومية) يبقى على ختمه.
     *
     * نظيرُ `SalesLedger::clientNodeExpr()`: هناك العقدةُ عقدةُ العميل،
     * وهنا لا عقدةَ للمورّد فالعقدةُ عقدةُ المستند.
     */
    public static function branchNodeExpr(string $invoiceAlias = 'pi', string $entryAlias = 'je'): string
    {
        return "COALESCE($invoiceAlias.branch_id, $entryAlias.branch_id)";
    }

    /**
     * تقييدُ استعلامٍ على قيود الموردين بعُقدٍ من الشجرة — بمحورٍ واحد.
     *
     * **العلّة التي كانت:** الشرط `je.branch_id IN (…) OR فاتورتُه IN (…)`
     * يُدخل القيدَ في كل عقدةٍ يطابقها أيٌّ من الاثنين. فقيدٌ ختمُه «منطقة
     * الرياض» وفاتورتُه «الفرع الرئيسي» يُعدّ في البطاقتين معاً: جمعُ
     * الجذور كان ٣٩٢٬٤١٩ والمنشأةُ كلُّها ٣٣٢٬٩٢٥ — ٥٩٬٤٩٤ محسوبةً مرّتين.
     * وهي علّةُ `SalesLedger::baseQuery()` نفسُها قبل إصلاحها.
     *
     * يضمّ `purchase_invoices` باسمٍ مستعارٍ خاصٍّ (`lpi`) كي لا يصطدم
     * بضمٍّ آخر في الاستعلام نفسه، والضمُّ واحدٌ لواحد فلا يُضاعف صفّاً.
     *
     * @param  array<int, int>  $ids
     */
    public static function matchBranchNodes($query, array $ids, string $entryAlias = 'je'): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $query->leftJoin('purchase_invoices as lpi', 'lpi.id', '=', "$entryAlias.purchase_invoice_id")
            ->whereIn(DB::raw(self::branchNodeExpr('lpi', $entryAlias)), $ids);
    }

    /**
     * ما لا عقدةَ له — صفُّ «غير محدّد» في شجرة اللوحة.
     *
     * قيدٌ بلا فاتورةٍ وبلا ختمِ فرعٍ عليه؛ يظهر جذراً مستقلّاً في الشجرة،
     * فقائمتُه تحتاج نقيضَ {@see matchBranchNodes()} لا قائمةَ معرّفات.
     */
    public static function matchNoBranchNode($query, string $entryAlias = 'je'): void
    {
        $query->leftJoin('purchase_invoices as lpi', 'lpi.id', '=', "$entryAlias.purchase_invoice_id")
            ->whereRaw(self::branchNodeExpr('lpi', $entryAlias).' IS NULL');
    }

    /**
     * استبعادُ محفظة المورّد من دفتر الموردين.
     *
     * `ClientWalletService` يفتح حساباً ثانياً يحمل `supplier_id` نفسَه —
     * «محفظة» — وشرطُ `supplier_id IS NOT NULL` وحده يبتلعه فيخلط رصيدَه
     * بذمّة صاحبه. نسخةُ `SalesLedger::excludeWalletAccounts()`؛ تُعاد هنا
     * لأن الوحدات لا تستورد بعضها (وحدةٌ معطَّلة تُسقط أصنافها).
     */
    public static function excludeWalletAccounts($query, string $alias = 'a'): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('accounts', 'type_accont')) {
            return;
        }

        $query->where(function ($w) use ($alias) {
            $w->whereNull("$alias.type_accont")
                ->orWhereNotIn("$alias.type_accont", [Account::WALLET_CLIENT, Account::WALLET_SUPPLIER]);
        });
    }

    /** القيد كنموذج — للتفاصيل التي تحتاج علاقاته */
    public static function entry(int $id): ?JournalEntry
    {
        return JournalEntry::with(['details.account', 'createdByEmployee', 'branch'])->find($id);
    }
}
