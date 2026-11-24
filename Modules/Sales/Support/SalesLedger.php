<?php

namespace Modules\Sales\Support;

use App\Enums\ReferenceType;
use App\Models\Account;
use App\Support\ClosedPeriodRanges;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * دفتر أستاذ العملاء — مصدر أرقام لوحة المبيعات وتقاريرها.
 *
 * **القاعدة الواحدة:** حساب العميل **أصل** — يزيد بالمدين وينقص بالدائن،
 * عكسَ حساب المورّد الذي هو التزام. فالرصيد الموجب يعني «لنا عليه»، وكل
 * رقم مشتقٌّ من قيود مرحّلة (`status = 1`) على حسابه:
 *
 *     مبيعات    = مدين قيود  SI
 *     مرتجعات   = دائن قيود  SR
 *     إشعارات دائنة = دائن قيود  CN
 *     تحصيلات   = صافي قيود  REC و RV و RCHQ و RCHQR — عدا السندات الملغاة
 *                 − ما رُدّ نقداً للعميل (الطرفُ المدين لقيود SR و CN)
 *     مردودات   = ذلك الطرفُ المدين وحده — للعرض بجانب المحصَّل
 *     إلغاءات   = سندُ القبض الملغى وقيدُ إلغائه PCAN معاً
 *     تسويات    = صافي ما عدا ذلك (قيد افتتاحي، قيد يومية…)
 *
 * ورصيد آخر المدة = رصيد أوّلها + المبيعات − المرتجعات − الإشعارات
 * − التحصيلات + التسويات + صافي الإلغاءات.
 *
 * **الطرفُ يُصنَّف لا المستند.** قيدُ المرتجع قد يحمل طرفاً مديناً على
 * العميل — «صرف نقدية مرتجع المبيعات»، أي ردُّ ما دفعه إليه. فجمعُ
 * `دائن − مدين` على نوع المستند كان يطرح المردودَ من قيمة المرتجعات،
 * فتقرأ اللوحةُ ٥٨٬٠٢٣ حيث تقرأ شاشةُ المرتجعات ٦٣٬١٨٩ لنفس المدة.
 * فالمرتجعُ والإشعارُ = طرفُهما الدائن وحده.
 *
 * **والمردودُ يُطرح من المحصَّل لا يُدسّ في التسويات.** كان يُعدّ تسويةً
 * فتبقى فاتورةٌ دُفعت ثم أُرجعت بمالها «محصَّلةً» بكاملها، ويتصدّر
 * مندوبُها قائمةَ التحصيل بمالٍ ردّه بيده (قال المستخدم: «سويت فاتورة
 * مدفوعة وسويت لها مرتجع مع إرجاع المبلغ للعميل، تظهر من ضمن
 * التحصيلات»). فالمحصَّل صافٍ مما رُدّ، والمعادلةُ أعلاه صحيحةٌ كما هي:
 * ما نقص من التحصيل هو ما كان يُضاف تسوية.
 *
 * **لماذا من القيود لا من `invoices`؟** لأن الفاتورة غير المرحّلة ليست
 * إيراداً بعد، ولأن التحصيل قد يُرحَّل سندَ قبض `RV` لا `REC` — وجمعُ
 * `receipts.amount` وحده يُسقط نصفها. وهي العلّة نفسها التي كانت تُسقط
 * ربعَ مدفوعات المشتريات.
 */
class SalesLedger
{
    /** قيود تُحتسب مبيعات */
    public const SALE_TYPES = [ReferenceType::SALES_INVOICE->value];

    /** قيود تُحتسب مرتجعاً */
    public const RETURN_TYPES = [ReferenceType::SALES_RETURN->value];

    /** قيود تُحتسب إشعاراً دائناً */
    public const CREDIT_NOTE_TYPES = [ReferenceType::CREDIT_NOTE->value];

    /**
     * قيود تُحتسب تحصيلاً من العميل — والشيكُ المرتدّ (`RCHQR`) يطرح نفسَه
     * بـ`دائن − مدين`.
     *
     * **سندُ القبض الملغى ليس هنا — لا هو ولا قيدُه العكسي.** كان `PCAN`
     * في هذه القائمة فيطرح نفسَه من الأصل، والمجموعُ صحيح؛ لكنّ قائمةَ
     * «المحصَّل» كانت تعرض الأصلَ ملغىً بين التحصيلات، والقيدَ العكسيَّ
     * سالباً بجانبه، ويُنسب السالبُ لمن ألغى فيظهر مديرٌ لم يحصّل شيئاً
     * بتحصيلٍ سالب (قال المستخدم: «السند الملغي ما يظهر في المحصَّل،
     * يظهر في إلغاء سندات القبض»). فالاثنان معاً يخرجان إلى «الإلغاءات»
     * — انظر {@see self::cancelledEntryIds()} — ولا يُترك الأصلُ وحده تحصيلاً،
     * وإلا عادت نسبةُ التحصيل فوق ١٠٠٪ كما كانت قبل ضمّ `PCAN`.
     */
    public const COLLECTION_TYPES = [
        ReferenceType::CUSTOMER_COLLECTION->value,
        ReferenceType::RECEIPT_VOUCHER->value,
        ReferenceType::RECEIVED_CHEQUE->value,
        ReferenceType::RECEIVED_CHEQUE_REJECT->value,
    ];

    /** القيدُ العكسيُّ لإلغاء سند قبض */
    public const CANCELLATION_TYPES = [ReferenceType::PAYMENT_CANCELLATION->value];

    /** @var array<int, int>|null */
    protected static ?array $cancelledIds = null;

    /**
     * القيودُ الأصلية التي أُلغيت بقيدٍ عكسيٍّ مرحَّل.
     *
     * لا عمودَ يربطهما: `IncomesController` يكتب رقمَ الأصل في وصف العكسي
     * — «(القيد الأصلي #1234)»، وقديماً «ملغي من القيد رقم #1773» — والسندُ
     * نفسُه يُحذف. فالرقمُ آخرُ `#` في الوصف (107 من 107 محلياً، والمبالغ متطابقة).
     *
     * @return array<int, int>
     */
    public static function cancelledEntryIds(): array
    {
        return self::$cancelledIds ??= DB::table('journal_entries')
            ->whereIn('reference_type', self::CANCELLATION_TYPES)
            ->where('status', 1)
            ->where('description', 'like', '%#%')
            ->pluck('description')
            ->map(fn ($text) => preg_match_all('/#(\d+)/', (string) $text, $m) ? (int) end($m[1]) : 0)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** تعبيرُ SQL: القيدُ إلغاءٌ — عكسيٌّ أو أصلٌ أُلغي (0/1) */
    public static function cancellationSql(string $alias = 'je'): string
    {
        $types = implode(',', array_map(fn ($type) => "'".$type."'", self::CANCELLATION_TYPES));
        $ids = self::cancelledEntryIds();

        return $ids === []
            ? "($alias.reference_type IN ($types))"
            : "($alias.reference_type IN ($types) OR $alias.id IN (".implode(',', $ids).'))';
    }

    /**
     * @param  int|array<int, int>|null  $branchId  فرعٌ واحد أو شجرةُ فرعٍ كاملة
     */
    public function __construct(
        protected ?int $clientId = null,
        protected ?int $employeeId = null,
        protected int|array|null $branchId = null,
    ) {}

    public static function fromFilters(?int $clientId, ?int $employeeId, int|array|null $branchId): self
    {
        return new self($clientId, $employeeId, $branchId);
    }

    /**
     * الفروع المطلوبة كقائمة معرّفات — فارغةٌ تعني كل الفروع.
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
     * حسابات العملاء المشمولة — الحساب هو نقطة الربط لا جدول العملاء،
     * فعميلٌ بلا حساب لا حركة له أصلاً.
     *
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        $query = Account::with('client')->whereNotNull('client_id');

        if ($this->clientId) {
            $query->where('client_id', $this->clientId);
        }

        return $query->get()->filter(fn ($account) => $account->client !== null)->values();
    }

    /**
     * مجاميع الفترة لكل عميل.
     *
     * @return array<int, array<string, float|int>>
     */
    public function periodTotals(ReportPeriod $period): array
    {
        $cancellation = self::cancellationSql('je');

        $rows = $this->baseQuery()
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->groupBy('a.client_id', 'je.reference_type', DB::raw($cancellation))
            ->select([
                'a.client_id',
                'je.reference_type',
                // مجمَّعٌ لا خام: التعبيرُ يقرأ `je.id` وليس في التجميع (ONLY_FULL_GROUP_BY)
                DB::raw("MAX($cancellation) as is_cancellation"),
                DB::raw('SUM(jed.debit) as debit'),
                DB::raw('SUM(jed.credit) as credit'),
                DB::raw('COUNT(DISTINCT je.id) as entries'),
                // عدّ المستندات بالطرف الذي يخصّه: فاتورةٌ مدينة، ومرتجعٌ دائن
                DB::raw('COUNT(DISTINCT CASE WHEN jed.debit > 0 THEN je.id END) as debit_entries'),
                DB::raw('COUNT(DISTINCT CASE WHEN jed.credit > 0 THEN je.id END) as credit_entries'),
            ])
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $clientId = (int) $row->client_id;
            $totals[$clientId] ??= self::emptyTotals();

            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $type = (string) $row->reference_type;

            if ((int) $row->is_cancellation === 1) {
                // السندُ الملغى وعكسُه: خارج التحصيل، وصافيهما يُبقي المعادلة
                $totals[$clientId]['cancellations'] += $debit - $credit;

                if (in_array($type, self::CANCELLATION_TYPES, true)) {
                    $totals[$clientId]['cancelled_receipts'] += $debit - $credit;
                    $totals[$clientId]['cancelled_receipts_count'] += (int) $row->entries;
                }
            } elseif (in_array($type, self::SALE_TYPES, true)) {
                $totals[$clientId]['sales'] += $debit - $credit;
                $totals[$clientId]['sales_count'] += (int) $row->debit_entries;
            } elseif (in_array($type, self::RETURN_TYPES, true)) {
                // قيمةُ المرتجع طرفُه الدائن؛ ومدينُه ردُّ ما دفعه العميل — يُطرح من المحصَّل
                $totals[$clientId]['returns'] += $credit;
                $totals[$clientId]['returns_count'] += (int) $row->credit_entries;
                $totals[$clientId]['collections'] -= $debit;
                $totals[$clientId]['refunds'] += $debit;
                $totals[$clientId]['refunds_count'] += (int) $row->debit_entries;
            } elseif (in_array($type, self::CREDIT_NOTE_TYPES, true)) {
                $totals[$clientId]['credit_notes'] += $credit;
                $totals[$clientId]['credit_notes_count'] += (int) $row->credit_entries;
                $totals[$clientId]['collections'] -= $debit;
                $totals[$clientId]['refunds'] += $debit;
                $totals[$clientId]['refunds_count'] += (int) $row->debit_entries;
            } elseif (in_array($type, self::COLLECTION_TYPES, true)) {
                $totals[$clientId]['collections'] += $credit - $debit;
                $totals[$clientId]['collections_count'] += (int) $row->entries;
            } else {
                $totals[$clientId]['adjustments'] += $debit - $credit;
                $totals[$clientId]['adjustments_count'] += (int) $row->entries;
            }

            $totals[$clientId]['entries_count'] += (int) $row->entries;
        }

        return $totals;
    }

    /**
     * رصيد ما قبل الفترة لكل عميل — مدين موجب.
     *
     * @return array<int, float>
     */
    /*
        الأرصدة لا تُستبعَد منها الفتراتُ المقفلة — بخلاف الحركة.
        الرصيدُ حصيلةُ التاريخ كلِّه: من أقفل ٢٠٢٥ لم يُسقط ما على العميل
        منها، وحذفُ حركتها من الرصيد يخترع رقماً لا يقابله شيء في دفتر
        الأستاذ. فالإخفاءُ يقع على ما جرى **في** الفترة، لا على ما بقي
        **بعدها**.
     */
    public function openingBalances(ReportPeriod $period): array
    {
        return $this->baseQuery()
            ->where('je.date', '<', $period->from->toDateString())
            ->groupBy('a.client_id')
            ->select(['a.client_id', DB::raw('SUM(jed.debit) - SUM(jed.credit) as balance')])
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->client_id => (float) $row->balance])
            ->all();
    }

    /**
     * رصيد كل عميل حتى تاريخ — مدين موجب، أي «لنا عليه».
     *
     * @return array<int, float>
     */
    public function balancesAsOf(Carbon $asOf): array
    {
        return $this->baseQuery()
            ->where('je.date', '<=', $asOf->toDateString())
            ->groupBy('a.client_id')
            ->select(['a.client_id', DB::raw('SUM(jed.debit) - SUM(jed.credit) as balance')])
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->client_id => (float) $row->balance])
            ->all();
    }

    /**
     * حركة الفترة مفصَّلة — قيداً قيداً، مرتّبةً زمنياً.
     *
     * @return Collection<int, \stdClass>
     */
    public function movements(ReportPeriod $period): Collection
    {
        return $this->baseQuery()
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by_employee')
            ->leftJoin('branches as b', 'b.id', '=', 'je.branch_id')
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->groupBy('a.client_id', 'je.id', 'je.reference_number', 'je.reference_type', 'je.date', 'je.description', 'je.invoice_id', 'je.created_by_employee', 'u.name', 'b.name')
            ->select([
                'a.client_id',
                DB::raw(self::cancellationSql('je').' as is_cancellation'),
                'je.id as entry_id',
                'je.reference_number',
                'je.reference_type',
                'je.date',
                'je.description',
                'je.invoice_id',
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
     * سلسلة زمنية للمبيعات والتحصيلات — لرسم اتجاه الفترة.
     *
     * @return array{labels: array<int, string>, sales: array<int, float>, collections: array<int, float>}
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
            // المرتجعُ والإشعارُ هنا لطرفهما المدين وحده: المردودُ نقداً يُطرح من التحصيل
            ->whereIn('je.reference_type', array_merge(self::SALE_TYPES, self::COLLECTION_TYPES, self::RETURN_TYPES, self::CREDIT_NOTE_TYPES))
            ->whereRaw('NOT '.self::cancellationSql('je'))
            ->groupBy(DB::raw($bucket), 'je.reference_type')
            ->select([
                DB::raw("$bucket as bucket"),
                'je.reference_type',
                DB::raw('SUM(jed.debit) as debit'),
                DB::raw('SUM(jed.credit) as credit'),
            ])
            ->orderBy('bucket')
            ->get();

        $sales = [];
        $collections = [];

        foreach ($rows as $row) {
            $key = (string) $row->bucket;
            $sales[$key] ??= 0;
            $collections[$key] ??= 0;

            $type = (string) $row->reference_type;

            if (in_array($type, self::SALE_TYPES, true)) {
                $sales[$key] += (float) $row->debit - (float) $row->credit;
            } elseif (in_array($type, self::COLLECTION_TYPES, true)) {
                $collections[$key] += (float) $row->credit - (float) $row->debit;
            } else {
                $collections[$key] -= (float) $row->debit;
            }
        }

        $labels = array_keys($sales);
        sort($labels);

        return [
            // المفتاحُ كاملاً (Y-m أو Y-m-d) — نقرُ نقطةٍ في الرسم يفتح حركتها
            'buckets' => $labels,
            'labels' => array_map(fn ($label) => $unit === 'month' ? $label : substr($label, 5), $labels),
            'sales' => array_map(fn ($label) => round($sales[$label], 2), $labels),
            'collections' => array_map(fn ($label) => round($collections[$label], 2), $labels),
        ];
    }

    /** @return array<string, float|int> */
    public static function emptyTotals(): array
    {
        return [
            'sales' => 0.0,
            'sales_count' => 0,
            'returns' => 0.0,
            'returns_count' => 0,
            'credit_notes' => 0.0,
            'credit_notes_count' => 0,
            'collections' => 0.0,
            'collections_count' => 0,
            // ما رُدّ نقداً للعميل مع مرتجعٍ أو إشعار — مطروحٌ من `collections` أعلاه
            'refunds' => 0.0,
            'refunds_count' => 0,
            'adjustments' => 0.0,
            'adjustments_count' => 0,
            // مبلغُ سندات القبض الملغاة في الفترة (بتاريخ الإلغاء) وعددُها
            'cancelled_receipts' => 0.0,
            'cancelled_receipts_count' => 0,
            // صافي الأصل الملغى وعكسِه — صفرٌ إن وقعا في الفترة نفسِها
            'cancellations' => 0.0,
            'entries_count' => 0,
        ];
    }

    /**
     * تصنيف نوع القيد إلى مجموعته — للعرض والتلوين.
     *
     * @param  bool  $isCancellation  من `cancellationSql()`: عكسيٌّ أو أصلٌ أُلغي
     */
    public static function classify(?string $type, bool $isCancellation = false): string
    {
        return match (true) {
            in_array((string) $type, self::CANCELLATION_TYPES, true) => 'cancellation',
            $isCancellation => 'cancelled_collection',
            in_array((string) $type, self::SALE_TYPES, true) => 'sale',
            in_array((string) $type, self::RETURN_TYPES, true) => 'return',
            in_array((string) $type, self::CREDIT_NOTE_TYPES, true) => 'credit_note',
            in_array((string) $type, self::COLLECTION_TYPES, true) => 'collection',
            default => 'adjustment',
        };
    }

    /**
     * الاستعلام الأساس: تفاصيل القيود المرحّلة على حسابات ذمم العملاء.
     *
     * **الفرع من العميل وحده — لا من ختم القيد.** كان الشرط
     * `je.branch_id IN (…) OR عميلُه في العقدة`، فيدخل في حساب العقدة
     * كلُّ عميلٍ لغيرها مرّت له حركةٌ مختومةٌ بها. والختمُ فرعٌ محاسبيٌّ
     * حقيقيٌّ دائماً بينما العميل يُنسب غالباً إلى مجموعةٍ تحته، فـ٩٧٪
     * من حركات هذه المنشأة (١٧٤٩٥ من ١٧٩٩٠) ختمُها مخالفٌ لعقدة عميلها.
     * والأثر أنّ الشرط يجمع **شذراتٍ** من دفاترِ عملاءَ أجانب — بعضَ
     * حركتهم لا كلَّها — فيخرج رصيدٌ لا يقابله شيء: «الفرع الرئيسي» له
     * ثمانيةُ عملاء بذمّةٍ ٧٦٦، وكانت اللوحة تقول ٤٩٢٬٢١٣ عن ١٤٧٩ عميلاً.
     *
     * فالمحورُ واحد: العميلُ لمن؟ — وهو محورُ شاشة العملاء نفسِه
     * (`CatagroiyClientController::applyDashboardClientMatchesBranchNodes`)
     * ومحورُ تجميع `SalesDashboardMetrics::rawByBranch()` أصلاً، فاتّفقت
     * بطاقاتُ اللوحة مع جدول الفروع تحتها ومع شاشة العملاء.
     *
     * (كان التعليق هنا يقول إنّ `invoices` بلا عمود فرع، وهذا لم يعد
     * صحيحاً منذ أُضيف `invoices.branch_id`؛ لكنّ هذا الدفتر يقرأ القيود
     * لا الفواتير، فمصدرُه القيدُ لا المستند.)
     */
    protected function baseQuery()
    {
        $query = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->whereNotNull('a.client_id')
            ->where('je.status', 1);

        self::excludeWalletAccounts($query, 'a');

        if ($this->clientId) {
            $query->where('a.client_id', $this->clientId);
        }

        if ($this->employeeId) {
            $query->where('je.created_by_employee', $this->employeeId);
        }

        if (($branchIds = $this->branchIds()) !== []) {
            $query->whereIn('a.client_id', function ($sub) use ($branchIds) {
                $sub->select('id')->from('clients');
                self::matchClientNodes($sub, 'clients', $branchIds);
            });
        }

        return $query;
    }

    /**
     * استبعادُ محفظة العميل من دفتر الذمم.
     *
     * `ClientWalletService` يفتح للعميل حساباً ثانياً يحمل `client_id`
     * نفسَه — «محفظة»، وهو **التزامٌ دائن** لا ذمّةٌ مدينة. وشرطُ
     * `client_id IS NOT NULL` وحده يبتلعه، فتُطرح المحفظةُ من ذمّة صاحبها
     * ويظهر إيداعُها في «التسويات».
     *
     * وهو نفسُ ما يفعله `Account::scopeLedger()` — يُعاد هنا لأن الدفتر
     * يعمل على Query Builder خامٍ باسمٍ مستعار، لا على Eloquent.
     */
    public static function excludeWalletAccounts($query, string $alias = 'a'): void
    {
        if (! Schema::hasColumn('accounts', 'type_accont')) {
            return;
        }

        $query->where(function ($w) use ($alias) {
            $w->whereNull("$alias.type_accont")
                ->orWhereNotIn("$alias.type_accont", [Account::WALLET_CLIENT, Account::WALLET_SUPPLIER]);
        });
    }

    /**
     * تقييدُ استعلامٍ خام بعملاء عقدةٍ من الشجرة — بتبعيّتهم الصريحة
     * (`tree_node_id`) أولاً، والفرعِ المحاسبي المشتقّ لمن سبقوا العمود
     * ثانياً. نفس قاعدة `Client::scopeInTreeNode()` على Query Builder خامٍ
     * لا Eloquent، فتُستعمل هنا وفي `SalesDashboardMetrics`.
     *
     * **لماذا لا `clients.branch_id` وحده كما كان؟** لأنه صار الفرعَ
     * المحاسبيَّ المشتقَّ (أقرب سلفٍ نوعُه «فرع») لا موقعَ العميل التنظيميّ.
     * فعميلٌ منسوبٌ لمجموعةٍ تحت فرع «المدينة» فرعُه المحاسبي «المدينة»
     * نفسُه، وفلترةُ لوحة المبيعات بتلك المجموعة كانت لا تُطابق شيئاً أبداً
     * — العمود الذي تُقارَن به قيمتُه دائماً معرّفُ الفرع لا المجموعة.
     *
     * **والمسارُ الثالث: المنطقة.** العميلُ يُنسب إلى عقدةٍ بثلاثة طرق لا
     * طريقين: `tree_node_id`، وإلا `branch_id`، وإلا حيُّه في
     * `neighborhoods.region_id` — صفٌّ لكلّ عميل لا لكلّ حيّ. وإغفالُ
     * الثالث كان يُسقط من لوحة المبيعات عملاءَ تراهم شاشةُ العملاء في
     * العقدة نفسِها، فيختلف الرقمان على شاشتين لسببٍ لا يظهر في أيّهما.
     * والقاعدةُ هنا حرفُ قاعدةِ تلك الشاشة
     * (`applyDashboardClientMatchesBranchNodes`) — تُقرأ معها أو لا تُقرأ.
     *
     * @param  array<int, int>  $ids
     */
    public static function matchClientNodes($query, string $table, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $hasTreeNode = Schema::hasColumn('clients', 'tree_node_id');
        $hasGroupId = Schema::hasColumn('clients', 'group_id');

        // أيُّ انتماءٍ عدا الشجرة: الفرع المحاسبي، أو المجموعة، أو منطقةُ الحيّ
        $legacyPaths = function ($any) use ($ids, $table, $hasGroupId) {
            $any->whereIn("$table.branch_id", $ids)
                ->orWhereExists(function ($sub) use ($ids, $table) {
                    $sub->select(DB::raw(1))
                        ->from('neighborhoods')
                        ->whereColumn('neighborhoods.client_id', "$table.id")
                        ->whereIn('neighborhoods.region_id', $ids);
                });

            if ($hasGroupId) {
                $any->orWhereIn("$table.group_id", $ids);
            }
        };

        $query->where(function ($w) use ($ids, $table, $hasTreeNode, $legacyPaths) {
            if ($hasTreeNode) {
                $w->whereIn("$table.tree_node_id", $ids)
                    ->orWhere(function ($legacy) use ($table, $legacyPaths) {
                        $legacy->whereNull("$table.tree_node_id")
                            ->where($legacyPaths);
                    });

                return;
            }

            $w->where($legacyPaths);
        });
    }

    /**
     * معرّفاتُ عملاء العُقد (أو كل العملاء حين لا عقدة) — بقاعدة {@see self::matchClientNodes()}.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public static function clientIdsInNodes(array $ids): array
    {
        return DB::table('clients')
            ->when(Schema::hasColumn('clients', 'deleted_at'), fn ($q) => $q->whereNull('clients.deleted_at'))
            ->when($ids !== [], fn ($q) => $q->where(fn ($w) => self::matchClientNodes($w, 'clients', $ids)))
            ->pluck('clients.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * تعبيرُ SQL لعقدة العميل — تبعيّتُه الصريحة، وإلا فرعُه المحاسبي.
     * أساسُ التجميع الهرمي في `SalesDashboardMetrics::rawByBranch()`: لا
     * `je.branch_id` (فرعٌ محاسبيٌّ حقيقيٌّ دائماً) لأنه يطمس أيَّ عقدةٍ
     * تنظيميةٍ (منطقة/مجموعة/منفذ) تحته مهما بلغ عمقُها.
     */
    public static function clientNodeExpr(string $clientAlias = 'c'): string
    {
        return Schema::hasColumn('clients', 'tree_node_id')
            ? "COALESCE($clientAlias.tree_node_id, $clientAlias.branch_id)"
            : "$clientAlias.branch_id";
    }
}
