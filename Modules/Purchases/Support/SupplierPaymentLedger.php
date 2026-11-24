<?php

namespace Modules\Purchases\Support;

use App\Enums\ReferenceType;
use App\Support\ClosedPeriodRanges;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * مدفوعات الموردين — سندات الصرف كما تراها القيود.
 *
 * **لماذا لا من `payments_process`؟**
 *
 * شاشة تقرير المدفوعات كانت تستعلم `accounts` عن أعمدة لا وجود لها في
 * الجدول أصلاً — `payment_date` و`amount` و`payment_method` و`direction`
 * — فكل فتحةٍ للتقرير تنتهي بخطأ SQL. وجدول `payments_process` يمتلئ من
 * مسارٍ واحد من عدة مسارات تُنشئ سنداتِ صرف، فما مرّ من غيره لا يظهر فيه.
 *
 * القيد هو الأثر الوحيد الذي تتركه كل مسارات الصرف بلا استثناء: مدينٌ
 * على حساب المورّد ودائنٌ على الخزينة أو البنك. فمن هنا تُقرأ المدفوعات،
 * ومن الطرف الدائن يُعرف مصدر الصرف.
 */
class SupplierPaymentLedger
{
    /** أنواع القيود التي تمثّل صرفاً لمورّد */
    public const TYPES = [
        ReferenceType::SUPPLIER_PAYMENT->value,
        ReferenceType::PAYMENT_VOUCHER->value,
    ];

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
     * الفروع المطلوبة كقائمة معرّفات — نفس قاعدة `SupplierLedger`.
     *
     * عقدةٌ في شجرة «تابع لـ» قد تُترجم إلى فروعها كلّها، والقائمة الفارغة
     * تعني «كل الفروع» فلا يُطبَّق شرط.
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
     * قصرُ الاستعلام على الفروع المطلوبة.
     *
     * الفرع يُقرأ من القيد ومن فاتورته معاً — كما في `SupplierLedger`:
     * سندُ صرفٍ قد يُنشأ بلا `branch_id` بينما الفاتورة التي يسدّدها
     * تحمله، فالاعتماد على القيد وحده كان يُفرغ التقرير كلّما فُلتر بفرع.
     */
    protected function scopeBranch($query): void
    {
        if (($branchIds = $this->branchIds()) === []) {
            return;
        }

        $query->where(function ($q) use ($branchIds) {
            $q->whereIn('je.branch_id', $branchIds)
                ->orWhereIn('je.purchase_invoice_id', function ($sub) use ($branchIds) {
                    $sub->select('id')->from('purchase_invoices')->whereIn('branch_id', $branchIds);
                });
        });
    }

    /**
     * قيودُ الصرف التي أُلغيت — لا تُعدّ مدفوعاتٍ ولا تدخل الإحصاء.
     *
     * الإلغاءُ يكتب قيداً عكسياً (`PCAN`) ولا يمحو الأصل، فيبقى سندُ الصرف
     * الملغى في الدفتر مدينَ المورّد كأنّه قائم: كانت التقارير تعدّه دفعةً
     * وهي لوحةُ المشتريات تطرحه بقيد العكس — فتزيد المدفوعاتُ هنا عمّا هناك
     * بقدر ما أُلغي. رقمُ القيد الأصلي مكتوبٌ في وصف قيد العكس
     * (`ملغي من القيد رقم #ID`) فمنه يُعرف.
     *
     * @return array<int, int>
     */
    protected function cancelledEntryIds(): array
    {
        return DB::table('journal_entries')
            ->where('reference_type', ReferenceType::PAYMENT_CANCELLATION->value)
            ->where('status', 1)
            ->where('description', 'like', '%دفعة للمورد%')
            ->pluck('description')
            ->map(fn ($text) => preg_match('/#(\d+)/u', (string) $text, $m) ? (int) $m[1] : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * سندات الصرف في الفترة — سنداً سنداً، ومعها مصدر الصرف.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function payments(ReportPeriod $period): Collection
    {
        $rows = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->join('suppliers as s', 's.id', '=', 'a.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'je.created_by_employee')
            ->leftJoin('branches as b', 'b.id', '=', 'je.branch_id')
            ->whereNotNull('a.supplier_id')
            /* محفظةُ المورّد حسابٌ ثانٍ يحمل `supplier_id` نفسَه — انظر
               `SupplierLedger::excludeWalletAccounts()`. تسديدٌ من المحفظة
               يُقيَّد مدين حسابه الحقيقي ودائن محفظته معاً، وكلاهما «مورّدٌ»
               بهذا الشرط: فمجموع القيد على حسابات الموردين صفرٌ دائماً مهما
               بلغ، ويسقطه `HAVING <> 0` أدناه — سندٌ حقيقيٌّ يختفي من
               التقرير كأنه لم يقع. استبعادُ المحفظة يُبقي الطرف الحقيقي
               وحده فيظهر أثره. */
            ->tap(fn ($q) => SupplierLedger::excludeWalletAccounts($q, 'a'))
            ->where('je.status', 1)
            ->whereIn('je.reference_type', self::TYPES)
            ->whereNotIn('je.id', $this->cancelledEntryIds())
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            // ما أُقفل محاسبياً لا يُعرض ولا يدخل الإحصاء — نفس تعريف بقيّة الدفاتر
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->when($this->supplierId, fn ($q) => $q->where('a.supplier_id', $this->supplierId))
            ->when($this->employeeId, fn ($q) => $q->where('je.created_by_employee', $this->employeeId))
            ->when($this->visibleCreatorIds !== null, fn ($q) => $q->whereIn('je.created_by_employee', $this->visibleCreatorIds ?? []))
            ->tap(fn ($q) => $this->scopeBranch($q))
            ->groupBy('je.id', 'je.reference_number', 'je.reference_type', 'je.date', 'je.description', 'a.supplier_id', 's.trade_name', 's.number_suply', 'je.created_by_employee', 'u.name', 'b.name')
            ->select([
                'je.id as entry_id',
                'je.reference_number',
                'je.reference_type',
                'je.date',
                'je.description',
                'a.supplier_id',
                's.trade_name as supplier_name',
                's.number_suply as supplier_code',
                'je.created_by_employee as employee_id',
                'u.name as employee_name',
                'b.name as branch_name',
                DB::raw('SUM(jed.debit) - SUM(jed.credit) as amount'),
            ])
            ->havingRaw('SUM(jed.debit) - SUM(jed.credit) <> 0')
            ->orderByDesc('je.date')
            ->orderByDesc('je.id')
            ->get();

        $sources = $this->paymentSources($rows->pluck('entry_id')->all());

        return $rows->map(fn ($row) => [
            'entry_id' => (int) $row->entry_id,
            'reference_number' => $row->reference_number ?: '—',
            'reference_type' => (string) $row->reference_type,
            'type_name' => SupplierLedger::typeName($row->reference_type),
            'date' => \Carbon\Carbon::parse($row->date)->format('d/m/Y'),
            'raw_date' => \Carbon\Carbon::parse($row->date)->format('Y-m-d'),
            'supplier_id' => (int) $row->supplier_id,
            'supplier_name' => $row->supplier_name,
            'supplier_code' => $row->supplier_code,
            'employee_id' => $row->employee_id ? (int) $row->employee_id : null,
            'employee_name' => $row->employee_name ?: 'غير محدد',
            'branch_name' => $row->branch_name ?: '—',
            'description' => $row->description,
            'amount' => round((float) $row->amount, 2),
            'source' => $sources[(int) $row->entry_id] ?? '—',
        ]);
    }

    /**
     * مصدر الصرف لكل سند — اسم الحساب الدائن (خزينة أو بنك).
     *
     * @param  array<int, int>  $entryIds
     * @return array<int, string>
     */
    protected function paymentSources(array $entryIds): array
    {
        if (empty($entryIds)) {
            return [];
        }

        return DB::table('journal_entry_details as jed')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->whereIn('jed.journal_entry_id', $entryIds)
            ->whereNull('a.supplier_id')
            ->where('jed.credit', '>', 0)
            ->groupBy('jed.journal_entry_id')
            ->select([
                'jed.journal_entry_id',
                DB::raw('MIN(a.name) as source_name'),
            ])
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->journal_entry_id => $row->source_name])
            ->all();
    }

    /**
     * مدفوعات الفترة مجمَّعةً زمنياً — يوماً بيوم أو شهراً بشهر.
     *
     * التجميع في قاعدة البيانات لا في الذاكرة: فترةٌ بعشرة آلاف سند تعيد
     * ثلاثين صفّاً لا عشرة آلاف. والأيام الخالية تُدرَج صفراً حين تُطلب،
     * فالرسم البياني لا ينقطع والقارئ يرى يوماً لم يُصرف فيه شيء.
     *
     * @param  string  $unit  `day` أو `month`
     * @param  bool  $fillGaps  إدراج الفترات الخالية بصفر
     * @return Collection<int, array<string, mixed>>
     */
    public function buckets(ReportPeriod $period, string $unit = 'day', bool $fillGaps = true): Collection
    {
        $format = $unit === 'month' ? '%Y-%m' : '%Y-%m-%d';

        /* السندُ يُعدّ إن كان له أثرٌ صافٍ على حسابات الموردين — كما تسرده
           `payments()` (`HAVING net <> 0`). كان العدُّ على كلّ قيدٍ مسّ حساباً،
           فسندٌ سُدِّد من محفظة المورّد (مدين ذمّته/دائن محفظته) يُعدّ سنداً
           بمبلغ صفر: الشريطُ يقول ستّةَ سنداتٍ والجدولُ اثنين. فيُجمَّع أوّلاً
           قيداً قيداً ثمّ تُجمع الفتراتُ ممّا له أثر. */
        $perEntry = DB::table('journal_entry_details as jed')
            ->join('journal_entries as je', 'je.id', '=', 'jed.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jed.account_id')
            ->whereNotNull('a.supplier_id')
            // محفظةُ المورّد تحمل `supplier_id` نفسَه — انظر التعليق في `payments()`
            ->tap(fn ($q) => SupplierLedger::excludeWalletAccounts($q, 'a'))
            ->where('je.status', 1)
            ->whereIn('je.reference_type', self::TYPES)
            ->whereNotIn('je.id', $this->cancelledEntryIds())
            ->whereBetween('je.date', [$period->from->toDateString(), $period->to->toDateString()])
            // ما أُقفل محاسبياً لا يُعرض ولا يدخل الإحصاء — نفس تعريف بقيّة الدفاتر
            ->tap(fn ($q) => ClosedPeriodRanges::exclude($q, 'je.date'))
            ->when($this->supplierId, fn ($q) => $q->where('a.supplier_id', $this->supplierId))
            ->when($this->employeeId, fn ($q) => $q->where('je.created_by_employee', $this->employeeId))
            ->when($this->visibleCreatorIds !== null, fn ($q) => $q->whereIn('je.created_by_employee', $this->visibleCreatorIds ?? []))
            ->tap(fn ($q) => $this->scopeBranch($q))
            ->groupBy('je.id', 'je.date', 'je.created_by_employee', 'a.supplier_id')
            ->havingRaw('SUM(jed.debit) - SUM(jed.credit) <> 0')
            ->select([
                'je.id as entry_id',
                'je.date as entry_date',
                'je.created_by_employee as employee_id',
                'a.supplier_id',
                DB::raw('SUM(jed.debit) - SUM(jed.credit) as net'),
            ]);

        $rows = DB::query()->fromSub($perEntry, 't')
            ->groupBy(DB::raw("DATE_FORMAT(t.entry_date, '$format')"))
            ->select([
                DB::raw("DATE_FORMAT(t.entry_date, '$format') as bucket"),
                DB::raw('SUM(t.net) as amount'),
                DB::raw('COUNT(DISTINCT t.entry_id) as vouchers'),
                DB::raw('COUNT(DISTINCT t.supplier_id) as suppliers'),
                DB::raw('COUNT(DISTINCT t.employee_id) as employees'),
                DB::raw('MIN(t.entry_date) as first_date'),
            ])
            ->orderBy('bucket')
            ->get()
            ->keyBy('bucket');

        $buckets = collect();

        foreach ($this->bucketKeys($period, $unit, $fillGaps, $rows->keys()->all()) as $key) {
            $row = $rows->get($key);
            $amount = $row ? round((float) $row->amount, 2) : 0.0;
            $vouchers = $row ? (int) $row->vouchers : 0;

            $buckets->push([
                'bucket' => $key,
                'label' => self::bucketLabel($key, $unit),
                'amount' => $amount,
                'vouchers' => $vouchers,
                'suppliers' => $row ? (int) $row->suppliers : 0,
                'employees' => $row ? (int) $row->employees : 0,
                'average' => $vouchers > 0 ? round($amount / $vouchers, 2) : 0.0,
                'is_empty' => $vouchers === 0,
            ]);
        }

        return $buckets;
    }

    /**
     * مفاتيح الفترات المعروضة — كاملةً بالخالية، أو ما فيه حركةٌ فقط.
     *
     * @param  array<int, string>  $present  المفاتيح التي فيها حركة
     * @return array<int, string>
     */
    protected function bucketKeys(ReportPeriod $period, string $unit, bool $fillGaps, array $present): array
    {
        if (! $fillGaps) {
            sort($present);

            return $present;
        }

        $keys = [];
        $cursor = $unit === 'month'
            ? $period->from->copy()->startOfMonth()
            : $period->from->copy()->startOfDay();
        $end = $period->to->copy();

        // حدٌّ أعلى يمنع فترةً مفتوحة من توليد آلاف الصفوف الفارغة
        $limit = $unit === 'month' ? 120 : 400;

        while ($cursor->lte($end) && count($keys) < $limit) {
            $keys[] = $cursor->format($unit === 'month' ? 'Y-m' : 'Y-m-d');
            $unit === 'month' ? $cursor->addMonth() : $cursor->addDay();
        }

        // ما تجاوز الحدّ يبقى ظاهراً إن كانت فيه حركة، فلا يضيع رقم
        foreach ($present as $key) {
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        sort($keys);

        return $keys;
    }

    /** عنوان الفترة كما يُقرأ: اسم الشهر أو اليوم بيومه من الأسبوع */
    public static function bucketLabel(string $key, string $unit): string
    {
        $months = [
            '01' => 'يناير', '02' => 'فبراير', '03' => 'مارس', '04' => 'أبريل',
            '05' => 'مايو', '06' => 'يونيو', '07' => 'يوليو', '08' => 'أغسطس',
            '09' => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر',
        ];

        if ($unit === 'month') {
            [$year, $month] = explode('-', $key);

            return ($months[$month] ?? $month).' '.$year;
        }

        $days = ['Sat' => 'السبت', 'Sun' => 'الأحد', 'Mon' => 'الإثنين', 'Tue' => 'الثلاثاء',
            'Wed' => 'الأربعاء', 'Thu' => 'الخميس', 'Fri' => 'الجمعة'];

        $date = \Carbon\Carbon::parse($key);

        return ($days[$date->format('D')] ?? '').' '.$date->format('d/m/Y');
    }

    /**
     * تجميع السندات على مفتاح — مورّداً كان أو موظفاً.
     *
     * @param  Collection<int, array<string, mixed>>  $payments
     * @return Collection<int, array<string, mixed>>
     */
    public static function groupBy(Collection $payments, string $idKey, string $nameKey): Collection
    {
        return $payments
            ->groupBy(fn ($payment) => $payment[$idKey] ?? 0)
            ->map(function (Collection $group) use ($idKey, $nameKey) {
                $first = $group->first();
                $amounts = $group->pluck('amount');

                return [
                    'id' => $first[$idKey],
                    'name' => $first[$nameKey],
                    'code' => $first['supplier_code'] ?? null,
                    'count' => $group->count(),
                    'total' => round($amounts->sum(), 2),
                    'average' => round($amounts->avg() ?: 0, 2),
                    'largest' => round($amounts->max() ?: 0, 2),
                    'first_date' => $group->min('raw_date'),
                    'last_date' => $group->max('raw_date'),
                    'suppliers_count' => $group->pluck('supplier_id')->unique()->count(),
                    'employees_count' => $group->pluck('employee_id')->unique()->count(),
                    'items' => $group->sortBy('raw_date')->values()->all(),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * سلسلة زمنية للمدفوعات — لرسم اتجاه الفترة.
     *
     * @param  Collection<int, array<string, mixed>>  $payments
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    public static function trend(Collection $payments, ReportPeriod $period): array
    {
        $monthly = $period->trendUnit() === 'month';

        $buckets = $payments
            ->groupBy(fn ($payment) => $monthly ? substr($payment['raw_date'], 0, 7) : $payment['raw_date'])
            ->map(fn ($group) => round(collect($group)->sum('amount'), 2))
            ->sortKeys();

        return [
            'labels' => $buckets->keys()->map(fn ($k) => $monthly ? $k : substr($k, 5))->all(),
            'values' => $buckets->values()->all(),
        ];
    }
}
