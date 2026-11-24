<?php

namespace Modules\Client\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * الأساسُ المحاسبيُّ الواحد لتقارير العملاء الستّة.
 *
 * ══════════ لماذا وُجد هذا الملف ══════════
 *
 * كانت التقاريرُ الستّة تحسب رصيدَ العميل بأربع طرقٍ مختلفة:
 *
 *   • كشفُ الحساب  ← مجموعُ حركات المدى، **بلا رصيدٍ افتتاحي**.
 *   • الأرصدةُ والأعمار ← العمودُ `accounts.balance` كما هو، وهو رصيدُ
 *     **اليوم** لا رصيدُ تاريخ القطع، فلا يتأثّر بأيّ فلترِ تاريخ.
 *   • مديونيةُ الفواتير ← `grand_total` ناقصَ مدفوعات الفاتورة.
 *   • `Client::getBalanceAttribute()` ← فواتيرُ ناقصَ مدفوعات.
 *
 * فأربعةُ أرقامٍ لعميلٍ واحد، ولا واحدَ منها يوافق الآخر.
 *
 * ══════════ ما هو الرصيدُ الصحيح ══════════
 *
 * `Account::computeFullBalance()` — وهي الدالّةُ التي تكتب العمود
 * `accounts.balance` أصلاً — تعرّفه هكذا لحسابٍ مدينٍ طرفيّ:
 *
 *     balance = Σ(debit) − Σ(credit)   على `journal_entry_details`
 *
 * وقد فُحص ذلك على المنشأة كاملةً: ٣٨٥٣ حساباً من ٣٨٥٤ يطابق فيها
 * العمودُ هذا المجموعَ بالضبط. فالتعريفُ ليس اجتهاداً هنا، بل هو تعريفُ
 * النظام نفسِه — وكلُّ ما يفعله هذا الملف أنّه **يضيف إليه حدَّ تاريخ**:
 *
 *     balance_at(D) = Σ(debit) − Σ(credit)   حيث `journal_entries.date <= D`
 *
 * ومنه يتفرّع كلُّ شيء:
 *
 *   • الرصيدُ الافتتاحي لمدىً    = balance_at(from − يوم)
 *   • الرصيدُ الختامي            = balance_at(to)
 *   • ختاميٌّ = افتتاحيٌّ + مدينُ المدى − دائنُ المدى   (متطابقٌ حتماً)
 *   • وبتاريخ قطعٍ = اليوم يعود الختاميُّ إلى `accounts.balance` نفسِه.
 *
 * فتطابُقُ التقارير ليس ضبطاً يدوياً يُراجَع، بل نتيجةٌ حسابيةٌ لأنّ
 * الأربعةَ تقرأ من هذه الدالّة الواحدة.
 *
 * ══════════ لماذا `journal_entries.date` لا `created_at` ══════════
 *
 * `date` هو تاريخُ وقوع الحركة محاسبياً، و`created_at` تاريخُ إدخالها في
 * الشاشة. تقريرٌ «حتى ٣١/١٢/٢٠٢٥» يسأل عن الأوّل؛ وفاتورةٌ ديسمبريةٌ
 * أُدخلت في يناير تسقط من التقرير لو قُرئ الثاني.
 *
 * ══════════ الإقفالُ لا يُخفي شيئاً ══════════
 *
 * لا فلترَ حالةٍ ولا استبعادَ فترةٍ مقفلةٍ في هذا الملف. الإقفالُ يمنع
 * الكتابة لا القراءة، ورصيدُ العميل يعبر السنةَ إلى ما بعدها ولا يُصفَّر.
 */
class CustomerLedgerService
{
    /** فواتيرُ ومدينٌ يزيد الدين. */
    public const TYPES_INVOICE = ['SI', 'DN'];

    /**
     * تحصيلاتٌ تُنقص الدين — وعكسُها يُعيده.
     *
     * `PCAN` («قيد عكسي لإلغاء سند قبض») و`RCHQR` (شيكٌ مرتدّ) حدثان
     * تحصيليّان لا تسويتان: الحسابُ `دائن − مدين`، فيطرحان أنفسهما.
     * وتركُهما في «أخرى» كان يُبقي الدفعةَ الملغاة محسوبةً محصَّلة.
     * (نفس قائمة `Modules\Sales\Support\SalesLedger::COLLECTION_TYPES` —
     * الشاشتان تقولان الرقم نفسَه أو لا معنى لواحدةٍ منهما.)
     */
    public const TYPES_RECEIPT = ['REC', 'RV', 'PCAN', 'RCHQ', 'RCHQR'];

    /** مرتجعاتٌ وإشعاراتٌ دائنة. */
    public const TYPES_RETURN = ['SR', 'CN'];

    /** أرصدةٌ افتتاحية. */
    public const TYPES_OPENING = ['OPB'];

    /**
     * الحدُّ الذي يُستبدل عنده شرطُ `IN` بمسحِ حسابات العملاء كلِّها.
     * انظر تعليل {@see self::scoped()}.
     */
    private const WIDE_SCOPE = 500;

    /**
     * أرصدةُ الحسابات حتى تاريخٍ شامل — `[account_id => float]`.
     *
     * استعلامٌ واحدٌ للتقرير كلِّه مهما بلغ عددُ العملاء. مرِّر `null`
     * لتاريخ القطع لتحصل على الرصيد الكامل بلا حدّ (= `accounts.balance`).
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, float>
     */
    public function balancesAt(array $accountIds, ?string $cutoff): array
    {
        $wanted = $this->wantedMap($this->normalize($accountIds));

        $out = [];
        foreach ($this->aggregate($accountIds, null, $cutoff) as $row) {
            $id = (int) $row->account_id;

            if ($wanted !== null && ! isset($wanted[$id])) {
                continue;
            }

            $out[$id] = (float) $row->debit - (float) $row->credit;
        }

        return $out;
    }

    /**
     * الوضعُ الكامل لكلّ حساب حتى تاريخ القطع — رصيدٌ ومدينٌ ودائنٌ وآخرُ
     * حركةٍ فيه، باستعلامٍ واحد.
     *
     * «آخرُ حركة» تُقرأ حتى تاريخ القطع لا حتى اليوم: تقريرٌ حتى
     * ٣١/١٢/٢٠٢٥ يجب أن يقول آخرَ حركةٍ وقعت **حينها**، لا حركةً وقعت بعد
     * تاريخ التقرير بشهور.
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, array{balance: float, debit: float, credit: float, last_movement: ?string}>
     */
    public function positionAt(array $accountIds, ?string $cutoff): array
    {
        $wanted = $this->wantedMap($this->normalize($accountIds));
        $out = [];

        /* التجميعُ بنوع المرجع رغم أنّ الرصيدَ لا يحتاجه: منه يُقرأ تاريخُ
           آخر حركةٍ **تُحمِّل** العميل في الاستعلام نفسِه، فلا يلزم استعلامٌ
           ثانٍ لعدّاد الأيام. */
        foreach ($this->aggregate($accountIds, null, $cutoff, true) as $row) {
            $id = (int) $row->account_id;

            if ($wanted !== null && ! isset($wanted[$id])) {
                continue;
            }

            $out[$id] ??= ['balance' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'last_movement' => null, 'last_charge' => null];

            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $type = (string) ($row->reference_type ?? '');

            $out[$id]['debit'] += $debit;
            $out[$id]['credit'] += $credit;
            $out[$id]['balance'] += $debit - $credit;

            if ($row->last_date && (! $out[$id]['last_movement'] || $row->last_date > $out[$id]['last_movement'])) {
                $out[$id]['last_movement'] = $row->last_date;
            }

            $isCharge = ! in_array($type, array_merge(self::TYPES_RECEIPT, self::TYPES_RETURN), true);

            if ($isCharge && $row->last_debit_date
                && (! $out[$id]['last_charge'] || $row->last_debit_date > $out[$id]['last_charge'])) {
                $out[$id]['last_charge'] = $row->last_debit_date;
            }
        }

        return $out;
    }

    /**
     * الرصيدُ الافتتاحي لمدى — أي رصيدُ ما **قبل** يوم البداية.
     *
     * وهذا هو الرقمُ الذي كان غائباً عن كشف الحساب كلِّه: ١٧٤٨ رصيداً
     * افتتاحياً في هذه المنشأة مؤرَّخةٌ في ديسمبر ٢٠٢٥، والنطاقُ الافتراضي
     * هو السنةُ المالية ٢٠٢٦ — فكانت تسقط جميعُها، ويبدأ كلُّ كشفٍ من صفرٍ
     * لا يوافق رصيدَ العميل الحقيقي بحال.
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, float>
     */
    public function openingBalances(array $accountIds, string $from): array
    {
        $before = Carbon::parse($from)->subDay()->format('Y-m-d');

        return $this->balancesAt($accountIds, $before);
    }

    /**
     * إجمالياتُ حركات المدى لكلّ حساب، مفصَّلةً بحسب نوع المرجع.
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, array{
     *     debit: float, credit: float, count: int,
     *     invoices: float, receipts: float, returns: float, opening: float, other: float,
     *     last_movement: ?string
     * }>
     */
    public function movementTotals(array $accountIds, string $from, string $to): array
    {
        $rows = $this->aggregate($accountIds, $from, $to, true);
        $wanted = $this->wantedMap($this->normalize($accountIds));

        $blank = [
            'debit' => 0.0, 'credit' => 0.0, 'count' => 0,
            'invoices' => 0.0, 'receipts' => 0.0, 'returns' => 0.0,
            'opening' => 0.0, 'other' => 0.0, 'last_movement' => null,
            // طرفا قيود الرصيد الافتتاحي منفصلين — كي يُستبعدا من مدين المدى ودائنه
            'opening_debit' => 0.0, 'opening_credit' => 0.0,
        ];

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->account_id;

            if ($wanted !== null && ! isset($wanted[$id])) {
                continue;
            }

            $out[$id] ??= $blank;

            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $type = (string) ($row->reference_type ?? '');

            $out[$id]['debit'] += $debit;
            $out[$id]['credit'] += $credit;
            $out[$id]['count'] += (int) $row->line_count;

            /* التصنيفُ بنوع المرجع لا بالاتجاه: مرتجعُ مبيعاتٍ (SR) قد
               يحمل مديناً ودائناً معاً، ونسبتُه إلى «مدفوعات» لأنّه دائنٌ
               تجعل عمودَ المدفوعات يقول ما لم يُدفَع. */
            $bucket = match (true) {
                in_array($type, self::TYPES_INVOICE, true) => 'invoices',
                in_array($type, self::TYPES_RECEIPT, true) => 'receipts',
                in_array($type, self::TYPES_RETURN, true) => 'returns',
                in_array($type, self::TYPES_OPENING, true) => 'opening',
                default => 'other',
            };

            /* والمرتجعُ طرفُه الدائن وحده. قيدُ `SR` قد يحمل مديناً على
               العميل — «صرف نقدية مرتجع المبيعات»، أي دفعُ قيمته له —
               فطرحُه من قيمة المرتجعات كان يجعل العمودَ يقول ٥٨٬٠٢٣ حيث
               تقول شاشةُ المرتجعات ٦٣٬١٨٩. ذاك المدينُ صرفٌ لا نقصٌ في
               المرتجع، فيقع في «أخرى» — والمعادلة
               `افتتاحي + مدين − دائن = ختامي` لا تمسّها القسمةُ أصلاً. */
            if ($bucket === 'opening') {
                $out[$id]['opening_debit'] += $debit;
                $out[$id]['opening_credit'] += $credit;
            }

            if ($bucket === 'returns') {
                $out[$id]['returns'] += $credit;
                $out[$id]['other'] += $debit;
            } else {
                $out[$id][$bucket] += $bucket === 'receipts'
                    ? $credit - $debit
                    : $debit - $credit;
            }

            if ($row->last_date && (! $out[$id]['last_movement'] || $row->last_date > $out[$id]['last_movement'])) {
                $out[$id]['last_movement'] = $row->last_date;
            }
        }

        return $out;
    }

    /**
     * تاريخُ آخر حركةٍ **تُحمِّل** العميلَ حتى تاريخ القطع.
     *
     * عدّادُ «الأيام» في تقارير العملاء يقيس قِدَمَ الدين، فلا يُصفَّر
     * بتحصيلٍ ولا بإشعارٍ دائن — وإلّا صار عميلٌ عليه دينُ سنةٍ دفع منه
     * ريالاً أمس «متأخّراً يوماً واحداً».
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, string>
     */
    public function lastChargeDates(array $accountIds, ?string $cutoff): array
    {
        $ids = $this->normalize($accountIds);

        if ($ids === []) {
            return [];
        }

        $wanted = $this->wantedMap($ids);

        $rows = $this->scoped($ids, null, $cutoff)
            ->where('d.debit', '>', 0)
            ->whereNotIn('e.reference_type', array_merge(self::TYPES_RECEIPT, self::TYPES_RETURN))
            ->groupBy('d.account_id')
            ->get(['d.account_id', DB::raw('MAX(e.date) as last_date')]);

        $out = [];

        foreach ($rows as $row) {
            $id = (int) $row->account_id;

            if ($wanted !== null && ! isset($wanted[$id])) {
                continue;
            }

            $out[$id] = $row->last_date;
        }

        return $out;
    }

    /**
     * سطورُ الحركة نفسُها — لكشف الحساب.
     *
     * تُرتَّب بالتاريخ ثمّ بالمعرّف: يومٌ فيه فاتورةٌ وتحصيلٌ يجب أن يقرأ
     * بنفس الترتيب في كلّ فتحةٍ للتقرير، وإلّا اختلف الرصيدُ الجاري
     * المعروض بين طبعتَين لنفس الكشف.
     *
     * @param  array<int, int>  $accountIds
     * @return \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection>
     */
    public function movementLines(array $accountIds, string $from, string $to)
    {
        $ids = $this->normalize($accountIds);

        if ($ids === []) {
            return collect();
        }

        $wanted = $this->wantedMap($ids);

        $rows = $this->scoped($ids, $from, $to)
            ->leftJoin('invoices as i', 'i.id', '=', 'e.invoice_id')
            ->orderBy('e.date')
            ->orderBy('d.id')
            ->get([
                'd.id',
                'd.account_id',
                'd.debit',
                'd.credit',
                'd.description as line_description',
                'e.date',
                'e.reference_type',
                'e.reference_number',
                'e.description as entry_description',
                'i.code as invoice_code',
            ]);

        if ($wanted !== null) {
            $rows = $rows->filter(fn ($row) => isset($wanted[(int) $row->account_id]))->values();
        }

        return $rows->groupBy('account_id');
    }

    /**
     * البنودُ المفتوحة بأسلوب FIFO — أساسُ أعمار ديون العملاء.
     *
     * ══════════ لماذا FIFO ══════════
     *
     * كان عمرُ الدين يُحسب من **آخر قيدٍ مدينٍ في الحساب** ثمّ يُلقى
     * **الرصيدُ كلُّه** في الشريحة الموافقة. فعميلٌ عليه دينُ سنةٍ اشترى
     * أمسِ بمئة ريال يقفز رصيدُه كاملاً إلى شريحة «١-٣٠»، ويختفي دينُه
     * القديم من التقرير الذي وُضع أصلاً لإظهاره.
     *
     * الصحيحُ أن تُطرح التحصيلاتُ من أقدم المستحقّات أوّلاً — وهي القاعدةُ
     * المحاسبية المعتادة — ثمّ يُوزَّع ما بقي على شرائحه بعمر **استحقاقه**
     * هو، فيبقى القديمُ قديماً.
     *
     * ولأنّ مجموعَ ما بقي = مجموعُ المدين − مجموعُ الدائن، فمجموعُ
     * الشرائح يساوي رصيدَ العميل حتماً — وهو التطابقُ المطلوب بين تقرير
     * الأعمار وتقرير الأرصدة.
     *
     * @param  array<int, int>  $accountIds
     * @param  array<int, int|null>  $creditPeriods  `[account_id => أيامُ الائتمان]`
     * @return array<int, array{items: array<int, array<string, mixed>>, balance: float, debit: float, credit: float}>
     */
    public function openItems(array $accountIds, string $cutoff, array $creditPeriods = []): array
    {
        $ids = $this->normalize($accountIds);

        if ($ids === []) {
            return [];
        }

        $wanted = $this->wantedMap($ids);

        $lines = $this->scoped($ids, null, $cutoff)
            ->where(function ($q) {
                $q->where('d.debit', '>', 0)->orWhere('d.credit', '>', 0);
            })
            ->orderBy('e.date')
            ->orderBy('d.id')
            ->get(['d.account_id', 'd.debit', 'd.credit', 'e.date', 'e.reference_type', 'e.reference_number', 'e.invoice_id']);

        if ($wanted !== null) {
            $lines = $lines->filter(fn ($line) => isset($wanted[(int) $line->account_id]))->values();
        }

        /* حدُّ **البيانات** هو `$cutoff`؛ وأمّا **العمر** فيُقاس من اليوم أو
           من طرف المدى، أيُّهما أسبق — انظر {@see self::agingDate()}. */
        $cut = self::agingDate($cutoff);
        $out = [];

        foreach ($lines->groupBy('account_id') as $accountId => $accountLines) {
            $accountId = (int) $accountId;
            $days = $this->sanitizeCreditPeriod($creditPeriods[$accountId] ?? null);

            $open = [];
            $credit = 0.0;
            $debit = 0.0;

            /* ══════════ الدائنُ الزائد يُرحَّل ولا يُهدر ══════════

               تحصيلٌ يسبق فاتورتَه — دفعةٌ مقدَّمة، أو تحصيلٌ أُدخل بتاريخٍ
               أقدم — لا يجد بنداً مفتوحاً يُطفئه. وإسقاطُه هنا كان يجعل
               مجموعَ البنود المفتوحة أكبرَ من رصيد العميل، فيخالف مجموعُ
               شرائح الأعمار إجماليَّ الأرصدة (٥٬٦٦٠ ريالاً فرقاً على
               مستوى المنشأة). فيُحتفظ به رصيداً دائناً مؤجَّلاً يُطفئ أوّلَ
               مستحقٍّ يأتي بعده. */
            $unapplied = 0.0;

            foreach ($accountLines as $line) {
                $lineDebit = (float) $line->debit;
                $lineCredit = (float) $line->credit;

                $debit += $lineDebit;
                $credit += $lineCredit;

                /* السطرُ صافي الاتجاه: قيدٌ يحمل الاثنين معاً على نفس
                   الحساب يُحسب بفرقه مرّةً واحدة، لا مرّتين متعاكستين. */
                $net = $lineDebit - $lineCredit;

                if ($net > 0) {
                    $applied = min($unapplied, $net);
                    $unapplied -= $applied;
                    $net -= $applied;

                    if ($net <= 0.005) {
                        continue;
                    }

                    $due = Carbon::parse($line->date)->startOfDay()->addDays($days);
                    $open[] = [
                        'date' => Carbon::parse($line->date)->format('Y-m-d'),
                        'due' => $due->format('Y-m-d'),
                        'amount' => $net,
                        'type' => (string) ($line->reference_type ?? ''),
                        'reference' => $line->reference_number,
                        // المستندُ صاحبُ البند — به يُوزَّع الدينُ على الفواتير في أعمار ديون الفواتير
                        'invoice_id' => $line->invoice_id ? (int) $line->invoice_id : null,
                        'days' => (int) max(0, $due->diffInDays($cut, false)),
                    ];

                    continue;
                }

                // دائنٌ صافٍ — يُطفئ أقدمَ المستحقّات أوّلاً
                $remaining = -$net;
                foreach ($open as $i => $item) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $applied = min($remaining, $open[$i]['amount']);
                    $open[$i]['amount'] -= $applied;
                    $remaining -= $applied;
                }

                $unapplied += $remaining;
                $open = array_values(array_filter($open, fn ($item) => $item['amount'] > 0.005));
            }

            $out[$accountId] = [
                'items' => array_values($open),
                'balance' => $debit - $credit,
                'debit' => $debit,
                'credit' => $credit,
                // دائنٌ لم يجد ديناً يُطفئه — يساوي الرصيدَ سالباً حين يكون سالباً
                'unapplied_credit' => round($unapplied, 2),
            ];
        }

        return $out;
    }

    /**
     * ما لم يُسدَّد بعدُ من قيود الرصيد الافتتاحي — من البنود المفتوحة نفسِها.
     *
     * لا حسابٌ مستقلّ: هو مجموعُ البنود المفتوحة التي نوعُ قيدها `OPB` بعد
     * أن أطفأ الدائنُ أقدمَ المستحقّات أوّلاً (FIFO) — فالمبلغُ هو هو ما
     * يعمّره تقريرُ أعمار الديون تحت هذا القيد، ولا يزيد على رصيد العميل.
     * قيدٌ افتتاحيٌّ دائن لا «متبقّي» له: هو رصيدٌ لصالح العميل لا دين.
     *
     * @param  array{items?: array<int, array{type: string, amount: float}>}|null  $openRow  صفُّ {@see self::openItems()}
     */
    public static function openingRemaining(?array $openRow): float
    {
        $sum = 0.0;

        foreach ($openRow['items'] ?? [] as $item) {
            if (in_array($item['type'] ?? '', self::TYPES_OPENING, true)) {
                $sum += (float) $item['amount'];
            }
        }

        return round($sum, 2);
    }

    /**
     * شرائحُ الأعمار فارغةً.
     *
     * الحدودُ هي هي المستعملةُ في الشاشتين قبل هذا التعديل — اليوم،
     * ١-٣٠، ٣١-٦٠، ٦١-٩٠، ٩١-١٢٠، أكثرُ من ١٢٠ — ولم تُبدَّل.
     *
     * @return array<string, float>
     */
    public static function emptyBuckets(): array
    {
        return [
            'today' => 0.0,
            'days1to30' => 0.0,
            'days31to60' => 0.0,
            'days61to90' => 0.0,
            'days91to120' => 0.0,
            'daysOver120' => 0.0,
        ];
    }

    /**
     * التاريخُ الذي يُقاس منه عمرُ الدين — لحظةُ القراءة.
     *
     * ══════════ لماذا لا يكفي طرفُ المدى ══════════
     *
     * المدى الافتراضي في تقارير العملاء هو **السنةُ المالية الجارية**
     * كاملةً (`RespectsAccountingPeriods::defaultReportRange()`)، فطرفُه
     * الأعلى ٣١/١٢ — تاريخٌ في **المستقبل** طوالَ السنة. وقياسُ أعمار
     * الديون إليه يضيف إلى كلّ بندٍ الفرقَ بين اليوم ونهايةِ السنة: في
     * ٨/٩/٢٠٢٦ كان ذلك ١١٤ يوماً تُضاف إلى كلّ دين، فيُقرأ دينُ ٦٤ يوماً
     * «١٧٨ يوماً» ويقفز من شريحتَي «١-٣٠» و«٦١-٩٠» إلى «+١٢٠» وحدَها.
     * والعطبُ يظهر مرّتين: الأيامُ غلطٌ في كلّ صف، وتتبدّل بتبدّل الفترة
     * المختارة بلا سببٍ يخصّ الدينَ نفسَه.
     *
     * وعمرُ الدين سؤالٌ عن الحاضر: لم يمضِ من الزمن إلّا ما مضى. فطرفُ
     * المدى يبقى حدَّ **البيانات** — لا حركةَ بعده — بينما يُقاس **العمر**
     * من أقربِ التاريخين: طرفِ المدى أو اليوم. فتقريرٌ تاريخيّ ينتهي في
     * الماضي يُعمَّر إلى طرفه كما يجب، وتقريرٌ ينتهي في المستقبل يُعمَّر
     * إلى اليوم ولا يستلف أياماً لم تأتِ.
     */
    public static function agingDate(?string $cutoff): Carbon
    {
        $today = Carbon::today()->startOfDay();

        if (! $cutoff) {
            return $today;
        }

        $cut = Carbon::parse($cutoff)->startOfDay();

        return $cut->greaterThan($today) ? $today : $cut;
    }

    /**
     * حدُّ **البيانات** لتقارير المديونية — طرفُ المدى، ولا يتجاوز اليوم.
     *
     * لوحتا العملاء والمبيعات تقرآن المديونيةَ «الآن»، والمدى الافتراضيُّ
     * في التقارير ينتهي بنهاية السنة المالية؛ فقيدٌ مؤرَّخٌ بعد اليوم (فاتورةٌ
     * أُدخلت مسبقاً، قسطٌ مجدوَل) كان يدخل رصيدَ التقرير ويغيب عن اللوحة.
     * ومدىً ينتهي في الماضي يبقى كما هو — تقريرٌ تاريخيّ.
     */
    public static function dataCutoff(?string $end): string
    {
        $today = Carbon::today()->toDateString();

        return ($end && $end < $today) ? Carbon::parse($end)->toDateString() : $today;
    }

    /** اسمُ الشريحة الموافقة لعدد أيام التأخّر. */
    public static function bucketFor(int $daysLate): string
    {
        return match (true) {
            $daysLate <= 0 => 'today',
            $daysLate <= 30 => 'days1to30',
            $daysLate <= 60 => 'days31to60',
            $daysLate <= 90 => 'days61to90',
            $daysLate <= 120 => 'days91to120',
            default => 'daysOver120',
        };
    }

    /**
     * أيامُ الائتمان مُنقّاةً.
     *
     * العمودُ حرٌّ في قاعدة البيانات، وفيه فعلاً قيمةٌ واحدةٌ فاسدة
     * (`1010961551`) كفيلةٌ بأن تدفع تاريخَ الاستحقاق مليونَي سنةٍ إلى
     * الأمام فيصير الدينُ «غيرَ مستحقٍّ بعد» إلى الأبد.
     */
    public function sanitizeCreditPeriod($value): int
    {
        $days = (int) ($value ?? 0);

        return ($days < 0 || $days > 3650) ? 0 : $days;
    }

    /**
     * الاستعلامُ المجمَّع المشترك.
     *
     * @param  array<int, int>  $accountIds
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function aggregate(array $accountIds, ?string $from, ?string $to, bool $groupByType = false)
    {
        $ids = array_values(array_unique(array_filter($accountIds)));

        if ($ids === []) {
            return collect();
        }

        $select = [
            'd.account_id',
            DB::raw('SUM(d.debit) as debit'),
            DB::raw('SUM(d.credit) as credit'),
            DB::raw('COUNT(*) as line_count'),
            DB::raw('MAX(e.date) as last_date'),
            // آخرُ تاريخٍ فيه سطرٌ مدينٌ فعلاً — لا آخرُ تاريخٍ في المجموعة
            DB::raw('MAX(CASE WHEN d.debit > 0 THEN e.date END) as last_debit_date'),
        ];

        $group = ['d.account_id'];

        if ($groupByType) {
            $select[] = 'e.reference_type';
            $group[] = 'e.reference_type';
        }

        return $this->scoped($ids, $from, $to)
            ->groupBy($group)
            ->get($select);
    }

    /**
     * استعلامُ الأستاذ محدوداً بمجموعة حساباتٍ ومدىً — بالخطّة الأسرع.
     *
     * ══════════ لماذا لا `whereIn` دائماً ══════════
     *
     * تقريرٌ بلا فلتر يشمل ٣٨٥٤ حساباً، و`IN` بهذا الطول يكلّف ثانيتين
     * ونصفاً في الاستعلام الواحد — وأربعةٌ منها تجعل الشاشةَ عشرَ ثوانٍ.
     * فحين تتّسع المجموعةُ يُستبدل الشرطُ بربطٍ على `accounts` يقول
     * «حساباتُ العملاء» (٢٧٦١ حساباً له حركة)، ثمّ تُنقّى النتيجةُ في
     * الذاكرة — والحصيلةُ هي هي في نصف ثانية.
     *
     * والحدُّ ٥٠٠: دونه يبقى `IN` أسرعَ لأنّه يستعمل الفهرس على
     * `journal_entry_details.account_id`، وفوقه يغلب المسحُ الكامل.
     *
     * @param  array<int, int>  $ids
     */
    private function scoped(array $ids, ?string $from, ?string $to): \Illuminate\Database\Query\Builder
    {
        /* القيودُ المرحّلة وحدَها — كدفتر المبيعات (`SalesLedger`). كلُّ
           القيود هنا مرحّلةٌ اليوم فلا يتغيّر رقم، لكنّ قيداً معلَّقاً غداً
           كان سيدخل رصيدَ العميل في تقارير العملاء ويغيب عن لوحة المبيعات. */
        $query = DB::table('journal_entry_details as d')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->where('e.status', 1);

        if (count($ids) <= self::WIDE_SCOPE) {
            $query->whereIn('d.account_id', $ids);
        }
        // وإلّا فبلا شرطِ حساب: التنقيةُ تقع في الذاكرة عبر wantedMap()،
        // وحتى ربطُ `accounts` لتضييق النطاق يكلّف أكثرَ ممّا يوفّر.

        if ($from !== null) {
            $query->where('e.date', '>=', $from);
        }

        if ($to !== null) {
            $query->where('e.date', '<=', $to);
        }

        return $query;
    }

    /**
     * أيلزم تنقيةُ النتائج في الذاكرة؟ — نعم متى وسّعنا النطاق أعلاه.
     *
     * @param  array<int, int>  $ids
     * @return ?array<int, bool> خريطةُ المسموح، أو `null` إن كان الاستعلام دقيقاً أصلاً
     */
    private function wantedMap(array $ids): ?array
    {
        return count($ids) > self::WIDE_SCOPE ? array_flip($ids) : null;
    }

    /**
     * معرّفاتُ الحسابات صحيحةً وفريدةً وبلا أصفار.
     *
     * @param  array<int, mixed>  $accountIds
     * @return array<int, int>
     */
    private function normalize(array $accountIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $accountIds))));
    }
}
