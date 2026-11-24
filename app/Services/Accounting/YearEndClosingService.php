<?php

namespace App\Services\Accounting;

use App\Constants\planFeatures\AccountPlanFeatures;
use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Services\Fawtra\SubscriptionGate;
use App\Support\AccountingSettings;
use App\Support\CurrencySettings;
use App\Support\OrphanedPeriodDocuments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * الإقفال السنوي — فحصُ الجاهزية، وقيودُ الإقفال، وإعادةُ الفتح.
 *
 * **ما كان**
 *
 * «إقفال السنة» يقلب حالتها وحالة فتراتها إلى «مقفلة» فقط: لا قيدَ إقفال،
 * فتبقى الإيراداتُ والمصروفات تحمل أرصدةَ السنة إلى ما بعدها، ولا يصل صافي
 * الربح إلى حقوق الملكية. ولا فحصَ قبل الإقفال: تُقفل سنةٌ لم تنتهِ، أو فيها
 * قيدٌ غير متوازن أو إذنٌ مخزنيّ ينتظر الاعتماد، بلا كلمةٍ تقول ذلك.
 *
 * **ما صار**
 *
 * - `readiness()` تُعيد أسبابَ المنع (`blockers`) والتنبيهات (`warnings`)
 *   ومعاينةَ القيد — تُعرض في شاشة الفترات قبل الضغط، ويُردّ بها الإقفال.
 * - `close()` تُنشئ قيدَ إقفالٍ لكلّ فرع بتاريخ آخر يومٍ في السنة: تُصفّر كلَّ
 *   حسابٍ تحت جذرَي الإيرادات والمصروفات، والفرقُ إلى «الأرباح والخسائر
 *   المرحّلة» (`retained_earnings` في الحسابات الافتراضية). قيدٌ لكلّ فرع لأن
 *   التقارير تُقرأ بالفرع: قيدٌ واحد على فرع المستخدم يُحمّل فرعَه أرباحَ
 *   المنشأة كلِّها.
 * - `reopen()` تعكس قيودَ الإقفال بقيودٍ عكسية (لا تحذفها) فتعود الأرصدة كما
 *   كانت ويبقى أثرُ الإقفال وإعادةِ الفتح. `report()` تقرير الإقفال.
 *
 * حساباتُ الميزانية لا تحتاج قيدَ افتتاح: التقاريرُ تقرأ رصيدَ أوّل المدة من
 * كلّ ما سبقها، فتنتقل أرصدتُها إلى السنة الجديدة بلا ترحيل.
 */
class YearEndClosingService
{
    /** مفتاحُ حساب «الأرباح والخسائر المرحّلة» في الحسابات الافتراضية */
    public const RETAINED_EARNINGS_KEY = 'retained_earnings';

    public function __construct(protected AccountingPeriodService $periods) {}

    /**
     * هل تُقفل هذه السنة الآن؟ ولماذا لا؟
     *
     * @return array{
     *     can_close: bool,
     *     blockers: array<int, array{key: string, message: string}>,
     *     warnings: array<int, array{key: string, message: string}>,
     *     preview: array{revenue: float, expenses: float, net: float, entries: int, lines: int, retained_account: ?string}
     * }
     */
    public function readiness(FiscalYear $year): array
    {
        $blockers = [];
        $warnings = [];
        [$from, $to] = $this->range($year);

        if ($year->status !== FiscalYear::STATUS_OPEN) {
            $blockers[] = $this->reason('already_closed', 'السنة مقفلة بالفعل.');
        }

        // السنةُ تُقفل بعد انتهائها — إقفالُها قبل ذلك يمنع تسجيل ما بقي من أيامها
        if (Carbon::parse($to)->endOfDay()->isFuture()) {
            $blockers[] = $this->reason('not_ended', sprintf(
                'السنة لم تنتهِ بعد — آخر يومٍ فيها %s. أقفل الفترات المنتهية منها إن أردت، وتُقفل السنة بعد انتهائها.',
                $to
            ));
        }

        // الإقفالُ بالترتيب: سنةٌ سابقةٌ مفتوحة تُقفل أوّلاً — وإلّا حُمِّلت أرباحُها على ما بعدها
        $earlierOpen = FiscalYear::whereDate('end_date', '<', $from)->where('status', FiscalYear::STATUS_OPEN)->orderBy('start_date')->value('name');
        if ($earlierOpen) {
            $blockers[] = $this->reason('earlier_year_open', "السنة المالية السابقة «{$earlierOpen}» ما زالت مفتوحة — تُقفل السنواتُ بالترتيب، فأقفلها أوّلاً.");
        }

        $retained = $this->retainedEarningsAccount();
        if (! $retained) {
            $blockers[] = $this->reason('no_retained_earnings', 'لم يُحدَّد حساب «الأرباح والخسائر المرحّلة» في الحسابات الافتراضية — إليه يُرحَّل صافي ربح السنة.');
        } elseif (Account::where('parent_id', $retained->id)->where('id', '!=', $retained->id)->exists()) {
            $blockers[] = $this->reason('retained_earnings_parent', "حساب «{$retained->name}» حسابُ تجميعٍ له أبناء — اختر حساباً طرفياً للأرباح والخسائر المرحّلة.");
        }

        // قيودٌ غير متوازنة: قيدُ الإقفال يُبنى على أرصدةٍ يجب أن تتوازن
        $periodIds = $this->periodIds($year);

        $unbalanced = DB::table('journal_entries as j')
            ->join('journal_entry_details as d', 'd.journal_entry_id', '=', 'j.id')
            ->where('j.status', 1)
            ->whereIn('j.accounting_period_id', $periodIds)
            ->groupBy('j.id', 'j.reference_number')
            ->havingRaw('ABS(COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0)) > 0.01')
            ->select('j.id', 'j.reference_number')
            ->get();
        if ($unbalanced->isNotEmpty()) {
            $blockers[] = $this->reason('unbalanced_entries', sprintf(
                '%d قيد غير متوازن في السنة (المدين لا يساوي الدائن): %s — صحّحها قبل الإقفال.',
                $unbalanced->count(),
                $this->sample($unbalanced->pluck('reference_number'))
            ));
        }

        // قيودٌ لم تُعتمد
        $drafts = DB::table('journal_entries')->where('status', '!=', 1)->whereIn('accounting_period_id', $periodIds)->count();
        if ($drafts > 0) {
            $blockers[] = $this->reason('unposted_entries', "{$drafts} قيد غير معتمد في السنة — اعتمدها أو احذفها قبل الإقفال.");
        }

        // أذونٌ مخزنية تنتظر الاعتماد: لم يُرحَّل قيدُها بعد، فتخرج السنة ناقصةً منها
        if (Schema::hasTable('warehouse_permits')) {
            $pending = DB::table('warehouse_permits')->where('status', 'pending')->whereIn('accounting_period_id', $periodIds);
            $pendingCount = (clone $pending)->count();
            if ($pendingCount > 0) {
                $blockers[] = $this->reason('pending_permits', sprintf(
                    '%d إذن مخزني بانتظار الاعتماد في السنة: %s — اعتمدها أو ألغها قبل الإقفال.',
                    $pendingCount,
                    $this->sample($pending->orderBy('id')->limit(5)->pluck('number'))
                ));
            }
        }

        // جلساتُ نقاط بيعٍ مفتوحة: مبيعاتُها لم تُقفل ولم يُرحَّل فرقُ صندوقها
        if (Schema::hasTable('pos_sessions') && Schema::hasColumn('pos_sessions', 'status')) {
            $dateColumn = Schema::hasColumn('pos_sessions', 'opened_at') ? 'opened_at' : 'created_at';
            $openSessions = DB::table('pos_sessions')->whereIn('status', ['open', 'active'])->whereBetween($dateColumn, [$from, $to.' 23:59:59'])->count();
            if ($openSessions > 0) {
                $blockers[] = $this->reason('open_pos_sessions', "{$openSessions} جلسة نقاط بيع مفتوحة في السنة — أغلقها قبل الإقفال.");
            }
        }

        /*
            ختمٌ لا يطابق التاريخ — سببُ منعٍ لا تنبيه.

            الإقفالُ يُحسب بالختم (فتراتُ السنة)، والقوائمُ تُقرأ به. فمستندٌ
            تاريخُه في السنة وختمُه فارغٌ أو في غيرها يخرج من قيد الإقفال
            ومن قوائم سنته معاً، ومستندٌ مختومٌ بها وتاريخُه خارجها يدخلهما
            بغير حقّ. «ربط العمليات غير المصنَّفة» يُصلح الحالين.
        */
        $mismatched = $this->stampMismatches($from, $to, $periodIds);
        if ($mismatched) {
            $blockers[] = $this->reason('stamp_mismatch', 'مستندات لا يطابق ختمُ فترتها تاريخَها ('.implode('، ', $mismatched).') — اضغط «ربط العمليات غير المصنَّفة» في هذه الشاشة ثم أعد المحاولة.');
        }

        // السنة التالية: بدونها تبقى القوائم على السنة المقفلة ولا تبدأ سنةٌ جديدة
        $next = FiscalYear::whereDate('start_date', '>', $to)->orderBy('start_date')->first();
        if (! $next) {
            $warnings[] = $this->reason('no_next_year', 'لا توجد سنة مالية تالية — أنشئها لتبدأ القوائم والتقارير عليها وتُقبل عمليات ما بعد '.$to.'.');
        }

        $preview = $this->preview($year, $retained);

        // حدُّ الباقة: قيودُ الإقفال قيودٌ تُعدّ عليها
        if ($preview['entries'] > 0) {
            try {
                $quota = app(SubscriptionGate::class)->checkLive(AccountPlanFeatures::LIMIT_JOURNAL_ENTRIES, $preview['entries']);
                if (! $quota['allowed']) {
                    $blockers[] = $this->reason('plan_limit', sprintf(
                        'الإقفال يحتاج %d قيد إقفال وحدُّ القيود في باقتك لا يسمح: %s',
                        $preview['entries'],
                        $quota['reason']
                    ));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($preview['entries'] === 0 && ! $blockers) {
            $warnings[] = $this->reason('nothing_to_close', 'لا أرصدة إيرادات أو مصروفات في السنة — تُقفل بلا قيد إقفال.');
        }

        return [
            'can_close' => $blockers === [],
            'blockers' => $blockers,
            'warnings' => $warnings,
            'preview' => $preview,
        ];
    }

    /**
     * إقفالُ السنة بقيودها — أو رفضُه بأسبابه.
     *
     * @return array<int, JournalEntry> قيودُ الإقفال المُنشأة
     *
     * @throws ValidationException بأسباب المنع
     */
    public function close(FiscalYear $year): array
    {
        $readiness = $this->readiness($year);

        if (! $readiness['can_close']) {
            throw ValidationException::withMessages([
                'fiscal_year' => array_column($readiness['blockers'], 'message'),
            ]);
        }

        $retained = $this->retainedEarningsAccount();

        return DB::transaction(function () use ($year, $retained) {
            /*
                نقرتان على «إقفال السنة» (أو طلبان متزامنان) تمرّان كلتاهما بفحص
                الجاهزية قبل أن تُقفل إحداهما — فتُنشأ قيودُ الإقفال مرّتين.
                قفلُ صفّ السنة ثم إعادةُ فحص حالتها يجعل الثانيةَ تنتظر الأولى
                ثم تجدها مقفلة.
            */
            $locked = FiscalYear::whereKey($year->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== FiscalYear::STATUS_OPEN || $this->closingEntries($locked)->exists()) {
                throw ValidationException::withMessages(['fiscal_year' => ['السنة مقفلة بالفعل.']]);
            }

            // قيودُ الإقفال قبل قفل الفترات: تُختم بفترة آخر يومٍ وهي ما زالت مفتوحة
            $entries = $this->createClosingEntries($locked, $retained);

            $this->periods->closeYear($year);

            return $entries;
        });
    }

    /**
     * إعادةُ فتح السنة — تُعكَس قيودُ إقفالها فتعود الإيرادات والمصروفات بأرصدتها.
     *
     * **ما كان:** تُحذف قيودُ الإقفال حذفاً. فلا يبقى أنّ السنة أُقفلت يوماً،
     * ولا بأيّ أرقام، ولا مَن فتحها — والترقيمُ يأخذ «أعلى رقمٍ موجود + 1»،
     * فيعود الإقفالُ التالي بالأرقام نفسِها (YCL-…-00001) لقيدٍ آخرَ بمبالغَ
     * أخرى: ورقةٌ مطبوعةٌ بالرقم لا تطابق ما في النظام.
     *
     * **ما صار:** لكلّ قيد إقفالٍ قائمٍ قيدٌ عكسيٌّ بتاريخه نفسِه (آخر يوم في
     * السنة) — نفسُ الحسابات بالعكس — رقمُه `REVERSE_` + رقمِ الأصل (اصطلاحُ
     * عكس الرواتب في `PayrollReversalService`)، ويُعلَّم الأصلُ `is_reversed`.
     * فيبقى أثرُ كلِّ إقفالٍ وإعادةِ فتح، ولا يتكرّر رقم، وصافي السنة يعود
     * كما كان قبل الإقفال (الأصلُ وعكسُه يتقاصّان). وكلاهما من نوع «قيد
     * إقفال سنوي»، فيُستثنيان معاً من قائمة الدخل ومن حساب الإقفال التالي.
     *
     * @return int عددُ القيود المعكوسة
     */
    public function reopen(FiscalYear $year): int
    {
        /*
            الفتحُ بعكس الترتيب: سنةٌ لاحقةٌ مقفلة بُنيت أرصدتُها الافتتاحية على
            إقفال هذه — فتُفتح هي أوّلاً.
        */
        $laterClosed = FiscalYear::whereDate('start_date', '>', Carbon::parse($year->end_date)->toDateString())
            ->where('status', '!=', FiscalYear::STATUS_OPEN)->orderByDesc('start_date')->value('name');
        if ($laterClosed) {
            throw ValidationException::withMessages(['fiscal_year' => ["السنة اللاحقة «{$laterClosed}» مقفلة — تُفتح السنواتُ بعكس ترتيب إقفالها، فافتحها أوّلاً."]]);
        }

        return DB::transaction(function () use ($year) {
            $reversed = 0;
            $touched = [];

            /*
                نقرتان على «إعادة فتح السنة» (أو طلبان متزامنان) تجدان كلتاهما
                السنةَ مقفلةً وقيودَ إقفالها قائمة — فتُنشأ القيودُ العكسية
                مرّتين ويُعكس الإقفالُ مرّتين. قفلُ صفّ السنة ثم إعادةُ فحص
                حالتها يجعل الثانيةَ تنتظر الأولى ثم تجدها مفتوحة — كما في `close()`.
            */
            $locked = FiscalYear::whereKey($year->id)->lockForUpdate()->first();
            if (! $locked || $locked->isOpen()) {
                throw ValidationException::withMessages(['fiscal_year' => ['السنة مفتوحة بالفعل.']]);
            }

            // الفتحُ أوّلاً: القيدُ العكسيّ بتاريخ آخر السنة، والكتابةُ في سنةٍ مقفلة ممنوعة
            $this->periods->reopenYear($year);

            foreach ($this->closingEntries($year)->with('details')->get() as $entry) {
                $reverse = new JournalEntry;
                $reverse->date = Carbon::parse($entry->date)->toDateString();
                $reverse->reference_number = self::REVERSAL_PREFIX.$entry->reference_number;
                $reverse->description = "عكس قيد الإقفال {$entry->reference_number} — إعادة فتح السنة المالية «{$year->name}»";
                $reverse->reference_type = ReferenceType::YEAR_CLOSING;
                $reverse->reference_id = $year->id;
                $reverse->batch_reference = $entry->reference_number;
                $reverse->status = 1;
                $reverse->currency = $entry->currency;
                $reverse->created_by_employee = auth()->id();
                $reverse->branch_id = $entry->branch_id;
                $reverse->save();

                if ($entry->branch_id === null && $reverse->branch_id !== null) {
                    // الختمُ التلقائي يملأ الفارغ بفرع المستخدم — العكسُ على فرع أصله
                    $reverse->forceFill(['branch_id' => null])->saveQuietly();
                }

                // السطورُ بالعكس بلا حدث الإنشاء، ثم تُحدَّث أرصدةُ حساباتها وآبائها مرّةً واحدة
                foreach ($entry->details as $detail) {
                    $this->createLineQuietly([
                        'journal_entry_id' => $reverse->id,
                        'account_id' => (int) $detail->account_id,
                        'description' => 'عكس — '.$detail->description,
                        'debit' => (float) $detail->credit,
                        'credit' => (float) $detail->debit,
                    ]);
                    $touched[(int) $detail->account_id] = true;
                }

                $entry->forceFill(['is_reversed' => true])->saveQuietly();
                $reversed++;
            }

            $this->refreshBalances(array_keys($touched));

            return $reversed;
        });
    }

    /** بادئةُ رقم القيد العكسيّ — اصطلاحُ `PayrollReversalService` */
    public const REVERSAL_PREFIX = 'REVERSE_';

    /**
     * قيودُ إقفال هذه السنة **القائمة**: لا المعكوسةُ بإعادة فتحٍ سابقة،
     * ولا قيودُ عكسها.
     */
    public function closingEntries(FiscalYear $year)
    {
        return $this->closingHistory($year)
            ->where('is_reversed', false)
            ->where(fn ($q) => $q->whereNull('reference_number')
                ->orWhere('reference_number', 'not like', str_replace('_', '\\_', self::REVERSAL_PREFIX).'%'));
    }

    /** كلُّ قيود الإقفال وعكسِها لهذه السنة — سجلُّ إقفالاتها وإعادات فتحها */
    public function closingHistory(FiscalYear $year)
    {
        return JournalEntry::where('reference_type', ReferenceType::YEAR_CLOSING->value)
            ->where('reference_id', $year->id);
    }

    /**
     * تقريرُ الإقفال السنوي — ما أُقفل، وبكم، وأين ذهب الصافي، وهل يتطابق.
     *
     * يُقرأ الحسابُ مرّتين باستقلال: الأرصدةُ قبل الإقفال من قيود السنة
     * (`balances()`)، والمُرحَّلُ فعلاً من سطور قيود الإقفال القائمة — فيُعرض
     * الفرقُ إن وُجد بدل أن يُفترض التطابق. وللسنة المفتوحة معاينةٌ لما
     * سيُرحَّل عند إقفالها.
     *
     * @return array<string, mixed>
     */
    public function report(FiscalYear $year): array
    {
        $retained = $this->retainedEarningsAccount();
        $active = $this->closingEntries($year)->orderBy('id')->get();
        $closed = ! $year->isOpen() && $active->isNotEmpty();
        $revenueIds = array_flip($this->descendants(AccountingSettings::id('revenue', SystemAccountIds::REVENUE)));
        $branchNames = DB::table('branches')->pluck('name', 'id');
        $branchName = fn ($id) => $id === null || $id === '' ? 'بلا فرع' : ($branchNames[(int) $id] ?? "فرع #{$id}");

        // ١) الأرصدةُ قبل الإقفال — لكلّ فرعٍ وحساب (مدين − دائن)
        $rows = $this->balances($year);
        $accountIds = $rows->pluck('account_id')->push($retained?->id)->filter()->unique()->all();
        $accounts = Account::whereIn('id', $accountIds)->get(['id', 'code', 'name', 'account_type'])->keyBy('id');

        // ٢) المُرحَّلُ فعلاً — سطورُ قيود الإقفال القائمة لكلّ فرعٍ وحساب
        $posted = $active->isEmpty() ? collect() : DB::table('journal_entry_details as d')
            ->join('journal_entries as j', 'j.id', '=', 'd.journal_entry_id')
            ->whereIn('j.id', $active->pluck('id'))
            ->groupBy('j.branch_id', 'd.account_id')
            ->selectRaw('j.branch_id, d.account_id, ROUND(SUM(d.debit), 2) as debit, ROUND(SUM(d.credit), 2) as credit')
            ->get();
        $postedKey = fn ($branchId, $accountId) => ($branchId ?? '').':'.$accountId;
        $postedMap = $posted->keyBy(fn ($r) => $postedKey($r->branch_id, $r->account_id));

        // ٣) لكلّ حساب: رصيدُه قبل الإقفال، وما يُقفل به (أو أُقفل)
        $accountRows = $rows->groupBy('account_id')->map(function ($group, $accountId) use ($accounts, $revenueIds) {
            $net = round((float) $group->sum('net'), 2);
            $account = $accounts[(int) $accountId] ?? null;

            return [
                'id' => (int) $accountId,
                'code' => $account?->code,
                'name' => $account?->name ?? "حساب #{$accountId}",
                'kind' => isset($revenueIds[(int) $accountId]) ? 'revenue' : 'expense',
                'balance_debit' => $net > 0 ? $net : 0.0,
                'balance_credit' => $net < 0 ? -$net : 0.0,
                // الإقفالُ بعكس الرصيد
                'closing_debit' => $net < 0 ? -$net : 0.0,
                'closing_credit' => $net > 0 ? $net : 0.0,
            ];
        })->sortBy('code', SORT_NATURAL)->values();

        $revenue = round(-(float) $rows->filter(fn ($r) => isset($revenueIds[(int) $r->account_id]))->sum('net'), 2);
        $expenses = round((float) $rows->reject(fn ($r) => isset($revenueIds[(int) $r->account_id]))->sum('net'), 2);
        $net = round($revenue - $expenses, 2);

        // ٤) لكلّ فرع: إيراداته ومصروفاته وصافيه، وقيدُ إقفاله
        $entryByBranch = $active->keyBy(fn ($e) => (string) ($e->branch_id ?? ''));
        $branchRows = $rows->groupBy(fn ($r) => (string) ($r->branch_id ?? ''))->map(function ($group, $branchId) use ($revenueIds, $entryByBranch, $branchName) {
            $rev = round(-(float) $group->filter(fn ($r) => isset($revenueIds[(int) $r->account_id]))->sum('net'), 2);
            $exp = round((float) $group->reject(fn ($r) => isset($revenueIds[(int) $r->account_id]))->sum('net'), 2);
            $entry = $entryByBranch[$branchId] ?? null;

            return [
                'branch' => $branchName($branchId),
                'revenue' => $rev,
                'expenses' => $exp,
                'net' => round($rev - $exp, 2),
                'entry_id' => $entry?->id,
                'entry_number' => $entry?->reference_number,
            ];
        })->sortBy('branch')->values();

        // ٥) فحوصُ المطابقة — للسنة المقفلة
        $checks = [];
        if ($closed) {
            $unbalanced = DB::table('journal_entry_details')->whereIn('journal_entry_id', $active->pluck('id'))
                ->groupBy('journal_entry_id')->havingRaw('ABS(SUM(debit) - SUM(credit)) > 0.005')->pluck('journal_entry_id');
            $checks[] = ['label' => 'كلُّ قيد إقفالٍ متوازن (المدين = الدائن)', 'ok' => $unbalanced->isEmpty(),
                'detail' => $unbalanced->isEmpty() ? $active->count().' قيد' : 'غير متوازن: '.$unbalanced->implode('، ')];

            $mismatch = $rows->filter(function ($r) use ($postedMap, $postedKey) {
                $p = $postedMap[$postedKey($r->branch_id, $r->account_id)] ?? null;
                $closedNet = $p ? (float) $p->debit - (float) $p->credit : 0.0;

                return abs(round((float) $r->net, 2) + $closedNet) > 0.005;
            });
            $checks[] = ['label' => 'كلُّ حساب إيرادٍ أو مصروف أُقفل بعكس رصيده تماماً، في كلّ فرع', 'ok' => $mismatch->isEmpty(),
                'detail' => $mismatch->isEmpty() ? $rows->count().' رصيداً (فرع × حساب)' : $mismatch->count().' رصيداً لم يُقفل بقيمته — الأرصدةُ تغيّرت بعد الإقفال؛ أعد فتح السنة وأقفلها'];

            $retainedPosted = $retained ? round((float) $posted->where('account_id', $retained->id)->sum(fn ($r) => (float) $r->credit - (float) $r->debit), 2) : 0.0;
            $checks[] = ['label' => 'صافي السنة = ما رُحِّل إلى '.($retained?->name ?? 'الأرباح المرحّلة'), 'ok' => abs($retainedPosted - $net) < 0.005,
                'detail' => 'الصافي '.number_format($net, 2).' — المُرحَّل '.number_format($retainedPosted, 2)];

            $stray = $posted->reject(fn ($r) => (int) $r->account_id === (int) $retained?->id)
                ->reject(fn ($r) => $rows->contains(fn ($b) => $postedKey($b->branch_id, $b->account_id) === $postedKey($r->branch_id, $r->account_id)));
            $checks[] = ['label' => 'لا سطرَ إقفالٍ على حسابٍ بلا رصيد', 'ok' => $stray->isEmpty(),
                'detail' => $stray->isEmpty() ? '—' : $stray->count().' سطراً'];
        }

        // ٦) السجلّ — كلُّ إقفالٍ وكلُّ إعادةِ فتح
        $users = DB::table('users')->pluck('name', 'id');
        $history = $this->closingHistory($year)->orderBy('id')->get()->map(function (JournalEntry $e) use ($users, $branchName) {
            $isReversal = str_starts_with((string) $e->reference_number, self::REVERSAL_PREFIX);
            $total = round((float) DB::table('journal_entry_details')->where('journal_entry_id', $e->id)->sum('debit'), 2);

            return [
                'id' => $e->id,
                'number' => $e->reference_number,
                'date' => Carbon::parse($e->date)->toDateString(),
                'created_at' => optional($e->created_at)->format('Y-m-d H:i'),
                'by' => $users[(int) $e->created_by_employee] ?? '—',
                'branch' => $branchName($e->branch_id),
                'total' => $total,
                'state' => $isReversal ? 'reversal' : ($e->is_reversed ? 'reversed' : 'active'),
                'reverses' => $isReversal ? $e->batch_reference : null,
            ];
        });

        return [
            'year' => $year,
            'closed' => $closed,
            'mode' => $closed ? 'posted' : 'preview',
            'closed_at' => $year->closed_at ? Carbon::parse($year->closed_at)->format('Y-m-d H:i') : null,
            'closed_by' => $year->closed_by ? ($users[(int) $year->closed_by] ?? null) : null,
            'retained' => $retained,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net' => $net,
            'accounts' => $accountRows,
            'branches' => $branchRows,
            'entries' => $active,
            'checks' => $checks,
            'history' => $history,
        ];
    }

    /** حسابُ «الأرباح والخسائر المرحّلة» المضبوط — أو null */
    public function retainedEarningsAccount(): ?Account
    {
        $id = AccountingSettings::id(self::RETAINED_EARNINGS_KEY);

        return $id ? Account::find($id) : null;
    }

    // ═══════════════════════ الحساب ═══════════════════════

    /**
     * صافي كلّ حسابِ إيرادٍ أو مصروفٍ في السنة، لكلّ فرع — مدين − دائن.
     *
     * @return Collection<int, object{branch_id: ?int, account_id: int, net: float}>
     */
    protected function balances(FiscalYear $year)
    {
        $accountIds = $this->profitAndLossAccountIds();

        if (! $accountIds) {
            return collect();
        }

        // السنةُ بفتراتها المختومة — العلاقةُ نفسُها التي تقرأ بها القوائمُ والتقارير
        return DB::table('journal_entry_details as d')
            ->join('journal_entries as j', 'j.id', '=', 'd.journal_entry_id')
            ->where('j.status', 1)
            ->whereIn('j.accounting_period_id', $this->periodIds($year))
            // قيدُ إقفالٍ سابقٍ لهذه السنة لا يُعاد إقفالُه
            ->where(fn ($q) => $q->whereNull('j.reference_type')->orWhere('j.reference_type', '!=', ReferenceType::YEAR_CLOSING->value))
            ->whereIn('d.account_id', $accountIds)
            ->groupBy('j.branch_id', 'd.account_id')
            ->selectRaw('j.branch_id, d.account_id, COALESCE(SUM(d.debit), 0) - COALESCE(SUM(d.credit), 0) as net')
            ->get()
            ->filter(fn ($row) => abs((float) $row->net) >= 0.005)
            ->values();
    }

    /**
     * @return array{revenue: float, expenses: float, net: float, entries: int, lines: int, retained_account: ?string}
     */
    protected function preview(FiscalYear $year, ?Account $retained): array
    {
        $balances = $this->balances($year);
        $revenueIds = array_flip($this->descendants(AccountingSettings::id('revenue', SystemAccountIds::REVENUE)));

        $revenue = 0.0;
        $expenses = 0.0;
        foreach ($balances as $row) {
            if (isset($revenueIds[(int) $row->account_id])) {
                $revenue += -(float) $row->net; // الإيرادُ دائن
            } else {
                $expenses += (float) $row->net;
            }
        }

        return [
            'revenue' => round($revenue, 2),
            'expenses' => round($expenses, 2),
            'net' => round($revenue - $expenses, 2),
            'entries' => $balances->pluck('branch_id')->unique()->count(),
            'lines' => $balances->count(),
            'retained_account' => $retained ? trim(($retained->code ? $retained->code.' — ' : '').$retained->name) : null,
        ];
    }

    /**
     * قيدٌ لكلّ فرع: كلُّ حسابٍ بعكس رصيده، والفرقُ إلى الأرباح المرحّلة.
     *
     * @return array<int, JournalEntry>
     */
    protected function createClosingEntries(FiscalYear $year, Account $retained): array
    {
        [, $to] = $this->range($year);
        $created = [];
        $touched = [$retained->id => true];

        foreach ($this->balances($year)->groupBy('branch_id') as $branchId => $rows) {
            $entry = new JournalEntry;
            $entry->date = $to;
            $entry->description = "قيد إقفال السنة المالية «{$year->name}» — ترحيل صافي الإيرادات والمصروفات إلى {$retained->name}";
            $entry->reference_type = ReferenceType::YEAR_CLOSING;
            $entry->reference_id = $year->id;
            $entry->status = 1;
            $entry->currency = CurrencySettings::code();
            $entry->created_by_employee = auth()->id();
            // الفرعُ فرعُ الأرصدة لا فرعُ من ضغط الزرّ (`groupBy` يجعل الفرعَ الفارغ '')
            $entry->branch_id = $branchId === '' ? null : (int) $branchId;
            $entry->save();

            if ($branchId === '' && $entry->branch_id !== null) {
                // الختمُ التلقائي في `creating` يملأ الفارغ بفرع المستخدم: الأرصدةُ بلا فرعٍ تبقى بلا فرع
                $entry->forceFill(['branch_id' => null])->saveQuietly();
            }

            $debit = 0.0;
            $credit = 0.0;
            foreach ($rows as $row) {
                $net = round((float) $row->net, 2);
                if ($net == 0.0) {
                    continue;
                }

                // رصيدٌ مدين يُقفل بدائن، والدائنُ بمدين
                $line = [
                    'journal_entry_id' => $entry->id,
                    'account_id' => (int) $row->account_id,
                    'description' => "إقفال رصيد السنة «{$year->name}»",
                    'debit' => $net < 0 ? -$net : 0,
                    'credit' => $net > 0 ? $net : 0,
                ];
                $this->createLineQuietly($line);
                $touched[$line['account_id']] = true;
                $debit += $line['debit'];
                $credit += $line['credit'];
            }

            // الفرق: دائنٌ للأرباح المرحّلة حين تفوق الإيراداتُ (ربح)، ومدينٌ حين تفوق المصروفاتُ (خسارة)
            $difference = round($debit - $credit, 2);
            if ($difference != 0.0) {
                $this->createLineQuietly([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $retained->id,
                    'description' => $difference > 0 ? "صافي ربح السنة «{$year->name}»" : "صافي خسارة السنة «{$year->name}»",
                    'debit' => $difference < 0 ? -$difference : 0,
                    'credit' => $difference > 0 ? $difference : 0,
                ]);
            }

            $created[] = $entry;
        }

        $this->refreshBalances(array_keys($touched));

        return $created;
    }

    /**
     * سطرُ قيدٍ بلا حدث `created` — رصيدُ حسابه يُحدَّث مرّةً في `refreshBalances()`.
     *
     * الحدثُ يُعيد حسابَ رصيد الحساب من قيوده كلِّها ثم أرصدةَ آبائه حتى
     * الجذر — مع كلّ سطر. وقيدُ الإقفال مئاتُ السطور تحت جذرَين اثنين، فكان
     * الجذران يُعاد حسابهما مئات المرّات (~17 ثانية لسنة 2026).
     */
    protected function createLineQuietly(array $line): void
    {
        JournalEntryDetail::withoutEvents(fn () => JournalEntryDetail::create($line));
    }

    /**
     * أرصدةُ الحسابات المتأثّرة وآبائها — كلٌّ مرّةً واحدة، الأعمقُ أوّلاً
     * فيُحسب الأبُ من أبنائه بعد تحديثهم. هو ما يفعله حدثُ السطر، مجمَّعاً.
     *
     * @param  array<int, int>  $accountIds
     */
    protected function refreshBalances(array $accountIds): void
    {
        $parents = DB::table('accounts')->pluck('parent_id', 'id')->all();
        $depth = [];

        foreach ($accountIds as $accountId) {
            $chain = [];
            $node = (int) $accountId;
            // `array_key_exists` لا `isset`: أبُ الجذر `null`، و`isset` تُسقط الجذرَ نفسه من السلسلة
            while ($node && array_key_exists($node, $parents) && ! isset($chain[$node])) {
                $chain[$node] = true;
                $node = (int) $parents[$node];
            }
            // عمقُ كلّ عقدةٍ في السلسلة = عددُ ما فوقها فيها
            $nodes = array_keys($chain);
            $count = count($nodes);
            foreach ($nodes as $index => $id) {
                $depth[$id] = max($depth[$id] ?? 0, $count - $index);
            }
        }

        arsort($depth);

        foreach (array_keys($depth) as $accountId) {
            Account::updateParentBalances($accountId, false);
        }
    }

    // ═══════════════════════ مساعدات ═══════════════════════

    /** @return array{0: string, 1: string} */
    protected function range(FiscalYear $year): array
    {
        return [
            Carbon::parse($year->start_date)->toDateString(),
            Carbon::parse($year->end_date)->toDateString(),
        ];
    }

    /** كلُّ حسابٍ تحت جذرَي الإيرادات والمصروفات — والجذران معهما */
    protected function profitAndLossAccountIds(): array
    {
        return array_values(array_unique(array_merge(
            $this->descendants(AccountingSettings::id('revenue', SystemAccountIds::REVENUE)),
            $this->descendants(AccountingSettings::id('expenses', SystemAccountIds::EXPENSES)),
        )));
    }

    /** الحسابُ وأحفاده — من خريطةٍ واحدة، ويقف عند المُكرَّر */
    protected function descendants(?int $rootId): array
    {
        if (! $rootId) {
            return [];
        }

        $children = [];
        foreach (DB::table('accounts')->get(['id', 'parent_id']) as $row) {
            if ($row->parent_id && (int) $row->parent_id !== (int) $row->id) {
                $children[(int) $row->parent_id][] = (int) $row->id;
            }
        }

        $seen = [$rootId => true];
        $stack = [$rootId];
        while ($stack) {
            foreach ($children[array_pop($stack)] ?? [] as $child) {
                if (! isset($seen[$child])) {
                    $seen[$child] = true;
                    $stack[] = $child;
                }
            }
        }

        return array_keys($seen);
    }

    /** @return array<int, int> معرّفاتُ فترات السنة */
    protected function periodIds(FiscalYear $year): array
    {
        return DB::table('accounting_periods')->where('fiscal_year_id', $year->id)->pluck('id')->map(fn ($id) => (int) $id)->all() ?: [0];
    }

    /**
     * ما لا يطابق ختمُه تاريخَه في هذه السنة، مفصَّلاً بجداوله:
     * تاريخُه فيها وختمُه فارغٌ أو خارجها، أو ختمُه فيها وتاريخُه خارجها.
     *
     * @param  array<int, int>  $periodIds
     * @return array<int, string> «قيود اليومية: 12» …
     */
    protected function stampMismatches(string $from, string $to, array $periodIds): array
    {
        $found = [];
        foreach (OrphanedPeriodDocuments::TABLES as $table => [$label, $dateColumn]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'accounting_period_id') || ! Schema::hasColumn($table, $dateColumn)) {
                continue;
            }

            $count = DB::table($table)
                ->where(function ($q) use ($dateColumn, $from, $to, $periodIds) {
                    $q->where(fn ($in) => $in->whereBetween(DB::raw("DATE(`{$dateColumn}`)"), [$from, $to])
                        ->where(fn ($stamp) => $stamp->whereNull('accounting_period_id')->orWhereNotIn('accounting_period_id', $periodIds)))
                        ->orWhere(fn ($out) => $out->whereIn('accounting_period_id', $periodIds)
                            ->whereNotBetween(DB::raw("DATE(`{$dateColumn}`)"), [$from, $to]));
                })
                ->count();

            if ($count > 0) {
                $found[] = "{$label}: {$count}";
            }
        }

        return $found;
    }

    /** @return array{key: string, message: string} */
    protected function reason(string $key, string $message): array
    {
        return ['key' => $key, 'message' => $message];
    }

    protected function sample($values): string
    {
        $values = collect($values)->filter()->values();
        $shown = $values->take(5)->implode('، ');

        return $values->count() > 5 ? $shown.' …' : $shown;
    }
}
