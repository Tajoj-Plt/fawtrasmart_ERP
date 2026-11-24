<?php

namespace Modules\Account\Http\Controllers\Concerns;

use App\Constants\SystemAccountIds;
use App\Enums\ReferenceType;
use App\Models\Account;
use App\Services\Accounting\AccountingPeriodService;
use App\Support\AccountingPeriodFilter;
use App\Support\AccountingSettings;
use App\Support\OrphanedPeriodDocuments;
use App\Support\TreasuryAccessGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أرقام لوحة الحسابات — من القيود، لا من أرصدةٍ محفوظة.
 *
 * **لماذا من القيود**
 *
 * كانت اللوحة تقرأ «الإيرادات» و«المصروفات» من عمود `balance` في جذور
 * الشجرة. وفي ذلك ثلاث علل:
 *
 *   • *الإشارة*: أرصدة المصروفات مخزَّنة بالسالب هنا، فحساب
 *     `الإيرادات − المصروفات` أعطى **ربحاً** قدره 2,641.50 بينما المنشأة
 *     **أنفقت** ذلك المبلغ. لوحةٌ تقلب الخسارة ربحاً أسوأ من لوحةٍ فارغة.
 *   • *الجذور وحدها*: `whereNull('parent_id')` يقرأ الجذر فقط، فإن لم
 *     تُرحَّل الأرصدة إلى أعلى الشجرة قُرئت صفراً — وهو ما حصل للإيرادات.
 *   • *لا مدى زمني*: رصيد الحساب تراكمُ عمره كلّه، فلا يصلح لـ«أداء
 *     الشهر» ولا لمقارنة فترةٍ بفترة.
 *
 * الجواب الصحيح محاسبيّاً يُشتقّ من طرفَي القيد:
 *
 *     الإيراد   = مجموع الدائن − مجموع المدين على حسابات الإيرادات
 *     المصروف   = مجموع المدين − مجموع الدائن على حسابات المصروفات
 *
 * وهو تعريفٌ لا يعتمد على كيفية تخزين الرصيد ولا على ترحيله، ويقبل أي مدًى
 * زمني.
 */
trait BuildsAccountingDashboard
{
    use ScopesToEmployeeHierarchy;

    /**
     * الفترةُ التي تُقرأ عليها اللوحة — تُحلَّل مرّةً وتُستعمل في كلّ بطاقة.
     *
     * وتُحفظ في الخاصّية كي لا يُعاد تحليلُها لكلّ رقمٍ في اللوحة: البطاقاتُ
     * عشرون، والتحليلُ استعلامٌ على جدول الفترات.
     */
    protected ?array $dashboardPeriod = null;

    protected function dashboardPeriod(): array
    {
        return $this->dashboardPeriod ??= AccountingPeriodFilter::resolve(request());
    }

    /**
     * تقييدُ استعلامِ قيودٍ بفترة اللوحة — بالعلاقة لا بالتاريخ.
     *
     * **ما كان**
     *
     * البطاقاتُ تجمع حركةَ الحسابات **منذ أوّل قيدٍ في النظام**، وبطاقةُ
     * «الشهر الجاري» تجمع شهراً تقويمياً لا فترةً محاسبية. فالمنشأةُ التي
     * تعمل بفتراتٍ ربعية تقرأ في لوحتها رقمَين لا يوافق أيٌّ منهما إقفالَها.
     *
     * @param  string  $alias  لقبُ جدول القيود في الاستعلام
     */
    protected function scopeToDashboardPeriod($query, string $alias = 'e')
    {
        return AccountingPeriodFilter::applyRaw(
            $query,
            $this->dashboardPeriod(),
            $alias.'.accounting_period_id'
        );
    }

    /**
     * صافي حركة نوعٍ من الحسابات خلال مدًى زمني.
     *
     * @param  string  $type  `revenues` أو `expenses` أو غيرهما
     * @param  string  $natural  الجانب الطبيعي: `debit` للمصروفات، `credit` للإيرادات
     */
    protected function movementOf(string $type, string $natural, ?Carbon $from = null, ?Carbon $to = null): float
    {
        $sides = $this->movementSides($type, $from, $to);

        return $natural === 'debit'
            ? $sides['debit'] - $sides['credit']
            : $sides['credit'] - $sides['debit'];
    }

    /**
     * جانبا الحركة منفصلين — المدين والدائن.
     *
     * الصافي وحده لا يكفي: صافي مصروفٍ **سالب** يعني أن الدائن غلب المدين
     * (كردٍّ أو تسويةِ جردٍ تُخفّض التكلفة)، وهو رقمٌ صحيح لكنّه يُقرأ
     * عطلاً ما لم يُعرض الجانبان إلى جانبه.
     */
    /**
     * قيدُ منشئ القيود ضمن نطاق المستخدم — على استعلامٍ خام يحمل لقب
     * جدول القيود.
     *
     * **لماذا؟** كانت مؤشرات اللوحة المشتقّة من القيود (الإيرادات
     * والمصروفات والصافي، الاتجاه الشهري، أعلى الحسابات حركةً) تُجمع على
     * قيود المنشأة كلِّها لكل من يفتح اللوحة — بينما عدّادات القيود
     * والسندات الأخيرة والخزائن في الشاشة نفسها منطاقة. فيقرأ المندوب
     * بطاقةَ «صافي الدخل» عن منشأةٍ لا يرى قيودَها. صار النطاق واحداً:
     * قيودُه هو ومن يديرهم (`accessibleUserIds`)، وغيرُ المقيَّد بلا قيد.
     */
    private array|null|false $dashboardCreatorIdsCache = false;

    /**
     * نطاق منشئي المستندات **للوحة تحديداً** — `null` بلا قيد.
     *
     * غيرُ الموظف (لا `employee_id`) يدير النظام ولا يعمل فيه، فلا يُحجب
     * ولو كان دورُه بلا `data_scope_all` — نفس مبدأ بقية الوحدات
     * (`StorehouseAccess`، ونطاق المخزون). ومن سواه على سلَّم
     * `accessibleUserIds` المعتاد: هو ومن يديرهم، و`data_scope_all` بلا قيد.
     */
    protected function dashboardCreatorIds(): ?array
    {
        if ($this->dashboardCreatorIdsCache !== false) {
            return $this->dashboardCreatorIdsCache;
        }

        if (! auth()->user()?->employee_id) {
            return $this->dashboardCreatorIdsCache = null;
        }

        return $this->dashboardCreatorIdsCache = $this->accessibleUserIds();
    }

    protected function scopeEntriesToCreator($q, string $column = 'e.created_by_employee')
    {
        $ids = $this->dashboardCreatorIds();

        if ($ids !== null) {
            $q->whereIn($column, $ids ?: [0]);
        }

        return $q;
    }

    protected function movementSides(string $type, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $q = DB::table('journal_entry_details as d')
            ->join('accounts as a', 'a.id', '=', 'd.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->where('a.account_type', $type);

        // بنطاق المستخدم — كسائر أرقام اللوحة المستندية
        $this->scopeEntriesToCreator($q);

        /*
            بلا مدىً صريح: الفترةُ بعلاقتها — وهو وضعُ اللوحة الأصل.
            وبمدىً صريح: مقارنةٌ زمنية طلبها المستدعي (كاتّجاهِ الأشهر).
        */
        if (! $from && ! $to) {
            $this->scopeToDashboardPeriod($q);
        }

        if ($from) {
            $q->whereDate('e.date', '>=', $from->toDateString());
        }

        if ($to) {
            $q->whereDate('e.date', '<=', $to->toDateString());
        }

        $row = $q->selectRaw('COALESCE(SUM(d.debit),0) dr, COALESCE(SUM(d.credit),0) cr')->first();

        return [
            'debit' => (float) ($row->dr ?? 0),
            'credit' => (float) ($row->cr ?? 0),
        ];
    }

    /**
     * الاتجاه الشهري — استعلامٌ واحد لكل الأشهر.
     *
     * كانت النسخة السابقة تدور على الأشهر، وتُحمّل في كل دورةٍ **كل** قيود
     * الشهر مع تفاصيلها وحساباتها، ثم تجمع في PHP. أي 12 استعلاماً ثقيلاً
     * زائد N+1 على التفاصيل — وكلّه ليصل إلى رقمين في الشهر.
     *
     * @return array{months: array, receipts: array, expenses: array, net: array}
     */
    protected function monthlyTrend(int $months = 12): array
    {
        $start = now()->copy()->subMonths($months - 1)->startOfMonth();

        $rows = $this->scopeEntriesToCreator(DB::table('journal_entry_details as d')
            ->join('accounts as a', 'a.id', '=', 'd.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id'))
            ->whereIn('a.account_type', [Account::REVENUES, Account::EXPENSES])
            // قيدُ الإقفال السنوي يُصفّر الإيرادات والمصروفات — اتجاهُ الشهر يُقرأ قبله
            ->where(fn ($q) => $q->whereNull('e.reference_type')->orWhere('e.reference_type', '!=', ReferenceType::YEAR_CLOSING->value))
            ->whereDate('e.date', '>=', $start->toDateString())
            ->groupBy(DB::raw("DATE_FORMAT(e.date, '%Y-%m')"), 'a.account_type')
            ->selectRaw("DATE_FORMAT(e.date, '%Y-%m') ym, a.account_type, SUM(d.debit) dr, SUM(d.credit) cr")
            ->get();

        $byKey = [];

        foreach ($rows as $r) {
            $byKey[$r->ym][$r->account_type] = [(float) $r->dr, (float) $r->cr];
        }

        $labels = [];
        $receipts = [];
        $expenses = [];
        $net = [];

        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');

            $labels[] = $m->translatedFormat('M Y');

            [$rdr, $rcr] = $byKey[$key][Account::REVENUES] ?? [0.0, 0.0];
            [$edr, $ecr] = $byKey[$key][Account::EXPENSES] ?? [0.0, 0.0];

            $rev = $rcr - $rdr;
            $exp = $edr - $ecr;

            $receipts[] = round($rev, 2);
            $expenses[] = round($exp, 2);
            $net[] = round($rev - $exp, 2);
        }

        return ['months' => $labels, 'receipts' => $receipts, 'expenses' => $expenses, 'net' => $net];
    }

    /**
     * فحص معادلة الميزانية: الأصول = الخصوم + حقوق الملكية + (إيراد − مصروف).
     *
     * أهمّ رقمٍ في لوحةٍ محاسبية، ولم يكن معروضاً. الفرق الذي يظهر هنا
     * يدلّ على قيدٍ ناقص أو غير متوازن — وهو ما لا يُكتشف بالنظر إلى
     * الأرصدة مفردةً.
     */
    protected function balanceEquation(): array
    {
        $side = fn (string $type, string $natural) => $this->movementOf($type, $natural);

        $assets = $side(Account::ASSETS, 'debit');
        $liabilities = $side(Account::LIABILITIES, 'credit');
        $equity = $side(Account::EQUITY, 'credit');
        $revenues = $side(Account::REVENUES, 'credit');
        $expenses = $side(Account::EXPENSES, 'debit');

        $left = $assets;
        $right = $liabilities + $equity + $revenues - $expenses;
        $diff = round($left - $right, 2);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'revenues' => $revenues,
            'expenses' => $expenses,
            'left' => $left,
            'right' => $right,
            'difference' => $diff,
            'balanced' => abs($diff) < 0.01,
        ];
    }

    /**
     * القيود التي لا يساوي مدينُها دائنَها.
     *
     * قيدٌ غير متوازن يفسد كل تقريرٍ بعده بصمت، ولا تُظهره شاشةُ القيود —
     * إذ تعرض المستندات لا مجاميعها. هذه أوّل ما ينبغي أن تراه في اللوحة.
     */
    protected function unbalancedEntries(int $limit = 5): array
    {
        $rows = DB::table('journal_entry_details as d')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->groupBy('d.journal_entry_id', 'e.reference_number', 'e.date', 'e.description')
            ->havingRaw('ABS(SUM(d.debit) - SUM(d.credit)) > 0.01')
            ->selectRaw('d.journal_entry_id id, e.reference_number, e.date, e.description,
                         SUM(d.debit) dr, SUM(d.credit) cr')
            ->orderByRaw('ABS(SUM(d.debit) - SUM(d.credit)) DESC')
            ->get();

        return [
            'count' => $rows->count(),
            'rows' => $rows->take($limit)->map(fn ($r) => (object) [
                'id' => $r->id,
                'reference_number' => $r->reference_number,
                'date' => $r->date,
                'description' => $r->description,
                'debit' => (float) $r->dr,
                'credit' => (float) $r->cr,
                'difference' => round((float) $r->dr - (float) $r->cr, 2),
            ])->values(),
        ];
    }

    /**
     * الذمم المدينة والدائنة — من شجرة الحسابات كما ضُبطت في الإعدادات.
     */
    protected function receivablesPayables(): array
    {
        $sumTree = function (?int $rootId) {
            if (! $rootId) {
                return null;
            }

            $ids = $this->descendantAccountIds($rootId);

            return (float) Account::whereIn('id', $ids)->sum('balance');
        };

        $clients = AccountingSettings::id('clients_account', SystemAccountIds::CLIENTS_ACCOUNT);

        // المفتاح في الإعدادات `suppliers` لا `suppliers_account` — والاسم
        // الخاطئ كان يُرجع `null` فتظهر الذمم الدائنة «غير مضبوطة» أبداً
        $suppliers = AccountingSettings::id('suppliers', null);

        return [
            'receivable' => $sumTree($clients),
            'payable' => $sumTree($suppliers),
            'receivable_account' => $clients ? optional(Account::find($clients))->name : null,
            'payable_account' => $suppliers ? optional(Account::find($suppliers))->name : null,
        ];
    }

    /** معرّفات الحساب وكل ذرّيّته — بجولات لا بعوديّةٍ لكل عقدة. */
    protected function descendantAccountIds(int $rootId): array
    {
        $ids = [$rootId];
        $frontier = [$rootId];

        // حدٌّ للعمق: شجرةٌ فيها حلقة لا تُوقف الصفحة
        for ($depth = 0; $depth < 12 && $frontier; $depth++) {
            $frontier = Account::whereIn('parent_id', $frontier)
                ->whereNotIn('id', $ids)
                ->pluck('id')->all();

            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /**
     * أرصدة الخزائن والبنوك.
     *
     * النطاق الآن وفق `ScopesToEmployeeHierarchy`: موظفٌ عاديّ يرى خزائنه
     * المعيَّنة فقط، ومن يدير غيره يرى خزائنه وخزائن كل من يديرهم أيضاً،
     * ومن ليس موظفاً (أو يملك `data_scope_all`) يرى الكل بلا قيد. كان
     * الشرط السابق يفحص عمود `role` النصي القديم لا الربط الفعلي بموظف.
     */
    protected function treasuryAccounts()
    {
        $cashParent = AccountingSettings::id('cash_treasuries_parent', SystemAccountIds::CASH_TREASURIES_PARENT);
        $bankParent = AccountingSettings::id('bank_accounts_parent', SystemAccountIds::BANK_ACCOUNTS_PARENT);

        $q = Account::whereIn('parent_id', array_filter([$cashParent, $bankParent]));

        $treasuryIds = $this->accessibleTreasuryIds();
        if ($treasuryIds !== null) {
            $q->whereIn('id', $treasuryIds ?: [0]);
        }

        return $q->get()->each(function ($a) use ($cashParent) {
            $a->is_cash = (int) $a->parent_id === (int) $cashParent;
        });
    }

    /**
     * خزائنُ **سند القبض**: ما يجوز لهذا المستخدم الإيداع فيه.
     *
     * `treasuryAccounts()` وحدَها تجيب عن سؤال الرؤية (خزائنه ومن يديرهم)،
     * ولا تسأل شرطَ الإيداع المضبوط على الخزينة نفسها. فكانت الشاشة تعرض
     * خزائن الزملاء كلَّها، ويختار المستخدمُ واحدةً ويملأ السند كلَّه ثم
     * يُردّ عند الحفظ برسالة «لا يُسمح لك بالإيداع…». والقائمةُ والحفظُ
     * يسألان الشرط نفسه الآن — {@see TreasuryAccessGuard}.
     *
     * @param  int|null  $keepAccountId  خزينةُ السند عند التعديل: تبقى في القائمة
     *                                   وإن مُنعت، وإلّا اختفت قيمتُه المحفوظة
     */
    protected function depositableTreasuryAccounts($keepAccountId = null)
    {
        return $this->guardedTreasuryAccounts('deposit', $keepAccountId);
    }

    /** نظيرتُها لسند الصرف — ما يجوز لهذا المستخدم السحب منه. */
    protected function withdrawableTreasuryAccounts($keepAccountId = null)
    {
        return $this->guardedTreasuryAccounts('withdraw', $keepAccountId);
    }

    /**
     * @param  string  $action  `deposit` أو `withdraw`
     */
    private function guardedTreasuryAccounts(string $action, $keepAccountId = null)
    {
        return $this->treasuryAccounts()
            ->filter(function ($account) use ($action, $keepAccountId) {
                if ($keepAccountId && (int) $account->id === (int) $keepAccountId) {
                    return true;
                }

                return TreasuryAccessGuard::blockReason($account->id, $action) === null;
            })
            ->values();
    }

    /**
     * حالة إقفال الفترات — تُقرأ من الإعدادات نفسها لا من فرضٍ.
     */
    protected function periodStatus(): array
    {
        try {
            $settings = app(AccountingPeriodService::class)->settings();

            return [
                'enforced' => (bool) $settings->enforce_lock,
                'lock_before' => $settings->lock_before_date?->format('Y-m-d'),
                'block_future' => (bool) $settings->block_future_dates,
                'block_undefined' => (bool) $settings->block_undefined_dates,
                /*
                    **ما خرج عن التقويم يُعَدّ ويُعرَض.**

                    الشاشاتُ تُقرأ على فترة، فمستندٌ بلا فترة لا يظهر في أيٍّ
                    منها: لا في القوائم ولا في التقارير ولا في الإقرار — وهو
                    قائمٌ في الدفتر ورصيدُ الحساب يحمله. فبقاؤه مخفيّاً أسوأُ
                    من رفضه.
                */
                'orphans' => OrphanedPeriodDocuments::summary(),
                'current' => AccountingPeriodFilter::current()?->name,
            ];
        } catch (\Throwable $e) {
            return [
                'enforced' => false, 'lock_before' => null, 'block_future' => false,
                'block_undefined' => false, 'orphans' => ['total' => 0, 'tables' => []],
                'current' => null,
            ];
        }
    }

    /**
     * أحدث المستندات المالية — من جداولها، بمبالغها الحقيقية.
     *
     * نطاق الرؤية وفق `ScopesToEmployeeHierarchy` نفسه المطبَّق على شاشتي
     * المصروفات والإيرادات: موظفٌ عاديّ يرى سنداته هو فقط هنا أيضاً.
     */
    protected function recentVouchers(string $model, int $limit = 5)
    {
        // نطاق اللوحة: غيرُ الموظف بلا قيد — انظر dashboardCreatorIds()
        $query = $model::query();
        $ids = $this->dashboardCreatorIds();

        if ($ids !== null) {
            $query->whereIn('created_by', $ids ?: [0]);
        }

        return $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($v) => (object) [
                'id' => $v->id,
                'code' => $v->code ?: '—',
                'amount' => (float) ($v->total_amount ?: $v->amount ?: 0),
                'date' => $v->date,
                'description' => $v->description,
            ]);
    }

    /**
     * أكبر الحسابات حركةً في نوعٍ ما — من القيود لا من الرصيد.
     */
    protected function topAccountsByMovement(string $type, string $natural, int $limit = 5)
    {
        return $this->scopeEntriesToCreator(DB::table('journal_entry_details as d')
            ->join('accounts as a', 'a.id', '=', 'd.account_id')
            // الانضمام للقيود لقيد منشئها — كان الجمع على التفاصيل مباشرةً
            // بلا نطاقٍ إطلاقاً
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id'))
            ->where('a.account_type', $type)
            ->groupBy('a.id', 'a.name', 'a.code')
            ->selectRaw('a.id, a.name, a.code, SUM(d.debit) dr, SUM(d.credit) cr')
            ->get()
            ->map(fn ($r) => (object) [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'total' => $natural === 'debit'
                    ? (float) $r->dr - (float) $r->cr
                    : (float) $r->cr - (float) $r->dr,
            ])
            ->filter(fn ($r) => abs($r->total) > 0.001)
            ->sortByDesc(fn ($r) => abs($r->total))
            ->take($limit)
            ->values();
    }

    /**
     * الوضع الضريبي: المحصَّل ناقص المدفوع.
     *
     * رقمٌ تسأل عنه كل منشأةٍ آخرَ كل فترة — كم عليها للهيئة أو لها —
     * ولم يكن في اللوحة. يُشتقّ من حسابَي الضريبة كما ضُبطا في الإعدادات.
     */
    protected function vatPosition(): array
    {
        $collectedId = AccountingSettings::id('vat_payable', SystemAccountIds::VAT_PAYABLE);
        $paidId = AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID);

        $movement = function (?int $accountId, string $natural) {
            if (! $accountId) {
                return null;
            }

            $row = DB::table('journal_entry_details')
                ->where('account_id', $accountId)
                ->selectRaw('COALESCE(SUM(debit),0) dr, COALESCE(SUM(credit),0) cr')
                ->first();

            $dr = (float) ($row->dr ?? 0);
            $cr = (float) ($row->cr ?? 0);

            return $natural === 'credit' ? $cr - $dr : $dr - $cr;
        };

        $collected = $movement($collectedId, 'credit');   // ضريبة المبيعات: دائنة
        $paid = $movement($paidId, 'debit');              // ضريبة المشتريات: مدينة

        return [
            'collected' => $collected,
            'paid' => $paid,
            'net' => ($collected === null || $paid === null) ? null : round($collected - $paid, 2),
            'collected_account' => $collectedId ? optional(Account::find($collectedId))->name : null,
            'paid_account' => $paidId ? optional(Account::find($paidId))->name : null,
        ];
    }

    /**
     * حركة الخزائن خلال مدّة: ما دخل وما خرج.
     *
     * الرصيد وحده لا يكفي — لا يقول أدخل مليونٌ وخرج مليونٌ أم لم تتحرّك
     * الخزينة أصلاً. الداخل والخارج يفصلان بينهما.
     */
    protected function treasuryFlow(?Carbon $from = null, ?Carbon $to = null): array
    {
        $ids = $this->treasuryAccounts()->pluck('id');

        if ($ids->isEmpty()) {
            return ['in' => 0.0, 'out' => 0.0, 'net' => 0.0];
        }

        $q = DB::table('journal_entry_details as d')
            ->join('journal_entries as e', 'e.id', '=', 'd.journal_entry_id')
            ->whereIn('d.account_id', $ids);

        if (! $from && ! $to) {
            $this->scopeToDashboardPeriod($q);
        }

        if ($from) {
            $q->whereDate('e.date', '>=', $from->toDateString());
        }

        if ($to) {
            $q->whereDate('e.date', '<=', $to->toDateString());
        }

        $row = $q->selectRaw('COALESCE(SUM(d.debit),0) dr, COALESCE(SUM(d.credit),0) cr')->first();

        $in = (float) ($row->dr ?? 0);
        $out = (float) ($row->cr ?? 0);

        return ['in' => $in, 'out' => $out, 'net' => round($in - $out, 2)];
    }

    /**
     * الأصول الثابتة: عددُها وقيمة شرائها.
     */
    protected function fixedAssets(): array
    {
        try {
            $rows = DB::table('asset_depreciations')
                ->selectRaw('COUNT(*) n, COALESCE(SUM(purchase_value),0) value')
                ->first();

            $sold = Schema::hasColumn('asset_depreciations', 'status')
                ? (int) DB::table('asset_depreciations')->where('status', 2)->count()
                : 0;

            return [
                'count' => (int) ($rows->n ?? 0),
                'value' => (float) ($rows->value ?? 0),
                'sold' => $sold,
            ];
        } catch (\Throwable $e) {
            return ['count' => 0, 'value' => 0.0, 'sold' => 0];
        }
    }

    /**
     * توزيع الحركة على مراكز التكلفة.
     */
    protected function costCenterLoad(int $limit = 5)
    {
        try {
            return DB::table('journal_entry_details as d')
                ->join('cost_centers as c', 'c.id', '=', 'd.cost_center_id')
                ->whereNotNull('d.cost_center_id')
                ->groupBy('c.id', 'c.name')
                ->selectRaw('c.id, c.name, SUM(d.debit) dr, SUM(d.credit) cr')
                ->get()
                ->map(fn ($r) => (object) [
                    'id' => $r->id,
                    'name' => $r->name,
                    'total' => (float) $r->dr - (float) $r->cr,
                ])
                ->filter(fn ($r) => abs($r->total) > 0.001)
                ->sortByDesc(fn ($r) => abs($r->total))
                ->take($limit)
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * أكبر الحسابات رصيداً — نافذةٌ سريعة على أين يسكن المال.
     */
    protected function largestAccounts(int $limit = 6)
    {
        return Account::whereNotNull('account_type')
            ->whereDoesntHave('children')
            ->where('balance', '!=', 0)
            ->orderByRaw('ABS(balance) DESC')
            ->limit($limit)
            ->get(['id', 'name', 'code', 'balance', 'account_type']);
    }

    /** توزيع الحسابات على أنواعها المحاسبية الحقيقية. */
    protected function accountTypeDistribution()
    {
        $labels = [
            Account::ASSETS => 'أصول',
            Account::LIABILITIES => 'خصوم',
            Account::EQUITY => 'حقوق ملكية',
            Account::REVENUES => 'إيرادات',
            Account::EXPENSES => 'مصروفات',
        ];

        $counts = Account::selectRaw('account_type, COUNT(*) c')
            ->groupBy('account_type')
            ->pluck('c', 'account_type');

        $out = [];

        foreach ($labels as $key => $label) {
            $out[] = (object) [
                'key' => $key,
                'name' => $label,
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }

        return collect($out);
    }
}
