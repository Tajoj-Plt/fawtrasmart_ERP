<?php

namespace Modules\Account\Http\Controllers;

use App\Constants\SystemAccountIds;
use App\Enums\InvoiceType;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ChartOfAccount;
use App\Models\Client;
use App\Models\CostCenter;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Models\JournalEntryInvoice;
use App\Models\PayableCheque;
use App\Models\PaymentsProcess;
use App\Models\PurchaseInvoice;
use App\Models\Receipt;
use App\Models\ReceivedCheque;
use App\Models\Supplier;
use App\Models\TaxSitting;
use App\Models\User;
use App\Support\AccountingPeriodFilter;
use App\Support\AccountingSettings;
use App\Support\OperationLog;
use App\Support\TaxableDocument;
use App\Support\PartialPaymentRule;
use App\Support\TaxCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;
use Modules\Account\Http\Controllers\Concerns\ScopesToEmployeeHierarchy;

class JournalEntryController extends Controller
{
    use AccountingOperations;
    use ScopesToEmployeeHierarchy;

    public function __construct()
    {
        // صلاحيات القيود اليومية — الكتالوج الجديد account.journal.*
        $this->middleware('permission.role:account.journal.view', ['only' => ['index', 'show']]);
        $this->middleware('permission.role:account.journal.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:account.journal.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:account.journal.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:account.journal.modifications', ['only' => ['searchAccounts', 'searchCostCenters', 'record_modifications']]);
    }

    /**
     * أنواعُ المرجع المسموحةُ للقيد اليدوي — كلُّها إلا قيدَ الإقفال السنوي.
     *
     * قيدُ الإقفال يُنشئه الإقفالُ وحده ويعكسه إعادةُ الفتح، وقائمةُ الدخل
     * تستبعده. فقيدٌ يدويٌّ بنوعه يُخفي حركتَه من قائمة الدخل ولا يُعكس
     * بإعادة الفتح — بابٌ للتلاعب بالأرباح من شاشة القيود.
     *
     * @return array<int, string>
     */
    private static function manualReferenceTypes(): array
    {
        return array_values(array_diff(
            array_column(ReferenceType::cases(), 'value'),
            [ReferenceType::YEAR_CLOSING->value]
        ));
    }

    /**
     * قيدُ إقفالٍ سنويّ أو قيدُ عكسه — يُنشئهما الإقفالُ وإعادةُ الفتح وحدهما.
     *
     * بعد إعادة فتح السنة تصير مفتوحةً فلا يمنعهما قفلُ الفترة، وحذفُ أحدهما
     * أو تعديلُه من هنا يُفسد سجلَّ الإقفال: أصلٌ معلَّمٌ «معكوساً» بلا عكس،
     * أو عكسٌ بلا أصل — فيبقى في السنة رصيدٌ لا يبلغ الأرباح المرحّلة.
     */
    private function systemClosingEntryReason(JournalEntry $entry): ?string
    {
        $type = $entry->reference_type instanceof ReferenceType
            ? $entry->reference_type
            : ReferenceType::tryFrom((string) $entry->reference_type);

        return $type === ReferenceType::YEAR_CLOSING
            ? 'قيود الإقفال السنوي وقيود عكسها يُنشئها النظام — لا تُعدَّل ولا تُحذف من هنا. لإلغاء الإقفال أعد فتح السنة من شاشة الفترات المحاسبية، فتُعكَس قيوده تلقائياً.'
            : null;
    }

    /** @throws ValidationException */
    private function guardSystemClosingEntry(JournalEntry $entry): void
    {
        if ($reason = $this->systemClosingEntryReason($entry)) {
            throw ValidationException::withMessages(['reference_type' => [$reason]]);
        }
    }

    /**
     * الحسابُ وكلُّ أحفاده — من خريطةٍ واحدة، ويقف عند المُكرَّر.
     *
     * @return array<int, int>
     */
    private function accountWithDescendants(int $accountId): array
    {
        $children = [];
        foreach (DB::table('accounts')->get(['id', 'parent_id']) as $row) {
            if ($row->parent_id && (int) $row->parent_id !== (int) $row->id) {
                $children[(int) $row->parent_id][] = (int) $row->id;
            }
        }

        $ids = [$accountId => true];
        $stack = [$accountId];
        while ($stack) {
            foreach ($children[array_pop($stack)] ?? [] as $child) {
                if (! isset($ids[$child])) {
                    $ids[$child] = true;
                    $stack[] = $child;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * حساباتُ الضريبة المضبوطة في «الحسابات المحاسبية» — كلُّ مفتاحٍ فيه `vat`
     * (المخرجات والمدخلات، وضريبةُ الشحن والتقسيط).
     *
     * @return array<int, int>
     */
    private function taxAccountIds(): array
    {
        return collect(AccountingSettings::map())
            ->filter(fn ($accountId, $key) => $accountId && str_contains((string) $key, 'vat'))
            ->map(fn ($accountId) => (int) $accountId)
            ->unique()
            ->values()
            ->all();
    }

    public function index(Request $request)
    {
        $query = JournalEntry::with([
            'details.account',
            'details.costCenter',
            'client',
            'createdByEmployee',
            'branch',
        ]);

        /*
            سياقُ الفرع من الشريط العلوي.

            **ما كان**: فلترٌ يدويّ باستثناءٍ للفرع الرئيسي — من وقف عليه
            رأى قيودَ الفروع كلّها، فلا معنى لاختياره إيّاه. ومن وقف على
            غيره قُيّد بـ`where('branch_id', …)` صارمة، وعمودُ الفرع كان
            **فارغاً في كل صفٍّ في الجدول** (لا كاتبَ له أصلاً) — فتخرج
            الشاشة فارغةً تماماً.

            **ما صار**: `forBranch()` نفسها التي تستعملها المبيعات — تُصفّي
            بالفرع النشط وتُبقي معه الصفوف التي سبقت عمود الفرع
            (`orWhereNull`)، و«كل الفروع» (`branch_id` فارغ) بلا تصفية.
            والكتابةُ صارت تختم الفرع في القيد (`StampsOwnerContext`)، فما
            يُنشأ من اليوم يُصفّى بحقّ.
        */
        $query->forBranch($this->headerBranchNode());

        // نطاق البيانات: قيوده هو، أو قيود من يديرهم إن كان يديرهم، أو الكل بلا قيد
        $this->scopeToCreator($query, 'created_by_employee');

        /*
            البحث حسب الحساب — **وأبنائه**.

            القائمةُ تعرض الشجرةَ كلَّها، وأوّلُها حساباتُ التجميع («1 - الأصول»،
            «4 - الإيرادات» …). والقيودُ تُرحَّل على الطرفية، فكان اختيارُ حسابِ
            تجميعٍ يطابق حسابَه وحده فتخرج الشاشةُ فارغةً دائماً.
        */
        $accountFilterIds = $request->filled('account_id')
            ? $this->accountWithDescendants((int) $request->account_id)
            : null;

        if ($accountFilterIds !== null) {
            $query->whereHas('details', function ($q) use ($accountFilterIds) {
                $q->whereIn('account_id', $accountFilterIds);
            });
        }

        // البحث حسب الوصف
        if ($request->filled('description')) {
            $query->where('description', 'like', '%'.$request->description.'%');
        }

        /*
            البحث برقم القيد — ما تعرضه القائمةُ رقماً: `reference_number`
            (SI-…، EXP-…، YCL-…) أو المعرّفُ حين لا مرجع، ومعه مرجعُ الدفعة
            لقيود الربط المتعدّد. جزءٌ من الرقم يكفي («00084»)، والرقمُ
            المجرّد يطابق المعرّفَ أيضاً.
        */
        $entryNumber = trim((string) $request->input('entry_number', ''));
        if ($entryNumber !== '') {
            $like = '%'.addcslashes($entryNumber, '%_\\').'%';
            $query->where(function ($q) use ($like, $entryNumber) {
                $q->where('journal_entries.reference_number', 'like', $like)
                    ->orWhere('journal_entries.batch_reference', 'like', $like);

                if (ctype_digit($entryNumber)) {
                    $q->orWhere('journal_entries.id', (int) $entryNumber);
                }
            });
        }

        /*
            «التجميع الزمني» (`date_type`) عرضٌ لا فلتر — يُطبَّق بعد الترقيم أدناه.
            كان هنا `where('date_type', …)` على عمودٍ لا وجود له في الجدول،
            فاختيارُ «يومياً» أو غيرِه يُسقط الشاشة بخطأ SQL.
        */
        $grouping = in_array($request->input('date_type'), ['daily', 'weekly', 'monthly'], true)
            ? $request->input('date_type')
            : null;

        // البحث حسب الإجمالي الأدنى
        if ($request->filled('total_from')) {
            $query->whereHas('details', function ($q) use ($request) {
                $q->havingRaw('SUM(debit) >= ?', [$request->total_from]);
            });
        }

        // البحث حسب الإجمالي الأعلى
        if ($request->filled('total_to')) {
            $query->whereHas('details', function ($q) use ($request) {
                $q->havingRaw('SUM(debit) <= ?', [$request->total_to]);
            });
        }

        /*
            **الفلترة بالعلاقة لا بالتاريخ.**

            `accounting_period_id` مختومٌ في القيد لحظةَ إنشائه، فالسؤالُ عن
            فترةٍ صار مقارنةَ عمودٍ بمعرّف — لا مدًى يُحسب في كلّ شاشة ثمّ
            يُقارَن بتاريخين.

            وكانت الشاشة تفتح على القيود كلِّها منذ أوّل قيدٍ في النظام:
            محاسبٌ يقفل الربع الأول ويعمل في الثاني يرى خليطاً من السنين،
            ويقرأ في الترويسة مجاميعَ لا تخصّ فترتَه.
        */
        $period = AccountingPeriodFilter::resolve($request);

        /*
            من بحث برقمٍ يريد ذلك القيدَ أيّاً كانت سنتُه — فلا يُحصر في السنة
            الجارية، وقيدٌ من سنةٍ سابقة (مقفلةٍ أو لا) يُوجد. والقائمةُ تُرسل
            السنةَ الجارية دائماً (خيارُها الافتراضي)، فهي كالغياب هنا؛ وفترةٌ
            أو سنةٌ غيرُها اختيرت عمداً فتُحترم.
        */
        $defaultYear = AccountingPeriodFilter::currentYear();
        $periodIsDefault = ! $request->filled('period_id')
            || ($defaultYear && $request->input('period_id') === AccountingPeriodFilter::YEAR_PREFIX.$defaultYear->id);

        if ($entryNumber !== '' && $periodIsDefault) {
            $period = AccountingPeriodFilter::resolve(new Request(['period_id' => AccountingPeriodFilter::ALL]));
            // القائمةُ تقرأ الطلب — فتُظهر «كل الفترات» لا السنةَ التي لم تُطبَّق
            $request->merge(['period_id' => AccountingPeriodFilter::ALL]);
        }

        $query = AccountingPeriodFilter::apply($query, $period);

        // البحث حسب أضيفت بواسطة
        if ($request->filled('created_by')) {
            $query->where('created_by_employee', $request->created_by);
        }

        // البحث حسب مركز التكلفة (الآن في جدول التفاصيل)
        if ($request->filled('cost_center')) {
            $query->whereHas('details', function ($q) use ($request) {
                $q->where('cost_center_id', $request->cost_center);
            });
        }

        // البحث حسب حالة القيد
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        /*
            فلترُ «قيدٌ ضريبي». `filled` لا `input` وحدها: القيمةُ '0'
            تعني «غير الضريبية» وهي مطلوبةٌ صراحةً، ولو فُحص صدقُ القيمة
            لسقط هذا الخيار.
        */
        /*
            «ضريبي» = قيدٌ فيه ضريبةٌ فعلاً، لا العلامةُ وحدها.

            `is_taxable` لا يضبطه إلا القيدُ اليدوي؛ قيودُ الفواتير والسندات
            تُرحِّل سطرَ ضريبتها ولا تضع العلامة — فكان «ضريبية فقط» فارغاً
            دائماً و«غير ضريبية» يضمّ قيودَ الفواتير الضريبية. والتعريفُ الآن:
            العلامة، أو سطرٌ معلَّمٌ سطرَ ضريبة، أو سطرٌ على حسابٍ ضريبيٍّ من
            «الحسابات المحاسبية».
        */
        if ($request->filled('taxable')) {
            $taxAccounts = $this->taxAccountIds();
            $hasTax = function ($q) use ($taxAccounts) {
                $q->where('journal_entries.is_taxable', 1)
                    ->orWhereExists(function ($line) use ($taxAccounts) {
                        $line->select(DB::raw(1))
                            ->from('journal_entry_details as tax_line')
                            ->whereColumn('tax_line.journal_entry_id', 'journal_entries.id')
                            ->where(fn ($w) => $w->where('tax_line.is_tax_line', 1)->orWhereIn('tax_line.account_id', $taxAccounts ?: [0]));
                    });
            };

            (string) $request->input('taxable') === '1'
                ? $query->where($hasTax)
                : $query->whereNot($hasTax);
        }

        /*
            البحث حسب المصدر — نوعُ القيد (`reference_type`).

            كانت القائمةُ أسماءً من نظامٍ آخر (invoice, pay_run, asset_sell …)
            والشرطُ على عمود `source` غيرِ الموجود: كلُّ اختيارٍ يُسقط الشاشة
            بخطأ SQL. صارت القائمةُ أنواعَ القيود الفعلية (`ReferenceType`).
        */
        if ($request->filled('source')) {
            $request->input('source') === 'none'
                ? $query->whereNull('reference_type')
                : $query->where('reference_type', $request->input('source'));
        }

        // البحث حسب حالة الاتزان (متزن أو غير متزن)
        if ($request->filled('balance_status')) {
            if ($request->balance_status == 'unbalanced') {
                // القيود غير المتوازنة (المدين لا يساوي الدائن)
                $query->whereRaw('ROUND(COALESCE((SELECT SUM(debit) FROM journal_entry_details WHERE journal_entry_id = journal_entries.id), 0), 2) != ROUND(COALESCE((SELECT SUM(credit) FROM journal_entry_details WHERE journal_entry_id = journal_entries.id), 0), 2)');
            } elseif ($request->balance_status == 'balanced') {
                // القيود المتوازنة
                $query->whereRaw('ROUND(COALESCE((SELECT SUM(debit) FROM journal_entry_details WHERE journal_entry_id = journal_entries.id), 0), 2) = ROUND(COALESCE((SELECT SUM(credit) FROM journal_entry_details WHERE journal_entry_id = journal_entries.id), 0), 2)');
            }
        }

        // حساب الإحصائيات بناءً على القيود المفلترة
        $statsQuery = clone $query;
        $totalDebit = 0;
        $totalCredit = 0;

        if ($request->filled('account_id')) {
            // إذا تم اختيار حساب معين، نحسب إجمالي حركاته فقط (مدين، دائن) في القيود المفلترة
            $stats = JournalEntryDetail::whereIn('journal_entry_id', $statsQuery->select('journal_entries.id'))
                ->whereIn('account_id', $accountFilterIds)
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            $totalDebit = $stats->total_debit ?? 0;
            $totalCredit = $stats->total_credit ?? 0;
        } else {
            // إذا لم يتم تحديد حساب، نحسب إجمالي المدين والدائن لجميع القيود المفلترة
            $stats = JournalEntryDetail::whereIn('journal_entry_id', $statsQuery->select('journal_entries.id'))
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            $totalDebit = $stats->total_debit ?? 0;
            $totalCredit = $stats->total_credit ?? 0;
        }

        $balance = abs($totalDebit - $totalCredit);
        $balanceType = $totalDebit >= $totalCredit ? 'مدين' : 'دائن';

        // عدد السجلّات من مُنتقي الشاشة — كان مثبَّتاً على 50 والمنتقي بلا أثر
        $perPage = (int) $request->input('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        // نسخةٌ قبل الترتيب — لمجاميع المجموعات الزمنية
        $groupBase = clone $query;

        // مع التجميع يُرتَّب بتاريخ القيد كي تتجاور قيودُ اليوم/الأسبوع/الشهر
        $entries = ($grouping
            ? $query->orderBy('date', 'desc')->orderBy('id', 'desc')
            : $query->orderBy('created_at', 'desc'))
            ->paginate($perPage)->appends($request->query());

        // نطاق الرؤية نفسه المطبَّق على edit/update/destroy — لا صلاحيات
        // النظام القديم (`g_a_d_r_add_edit_delete_own/all_journal_entries`)
        $canEditAny = auth()->user()->hasRolePermission('account.journal.edit');
        $entries->getCollection()->transform(function ($entry) use ($canEditAny, $grouping) {
            $entry->can_edit = $canEditAny && $this->canAccessCreator($entry->created_by_employee);
            $entry->group_key = $grouping ? $this->journalGroupKey($entry->date, $grouping) : null;

            return $entry;
        });

        $groups = $grouping ? $this->journalGroups($groupBase, $entries, $grouping) : [];

        /* كان هنا `$entryDetails = JournalEntryDetail::with('account')->get()` —
           كلُّ سطور القيود في النظام (٦٩ ألفاً) مع حساباتها في كل فتحٍ للشاشة،
           والقالبُ لا يقرؤها في أيّ موضع. */
        // Generated by Copilot - عرض الموظفين فقط (المستخدمين المرتبطين بموظفين)
        $users = User::whereNotNull('employee_id')->with('employee')->get();
        $costCenters = CostCenter::all();
        $accounts = Account::all();

        return view('account::journal.index', compact('entries', 'users', 'costCenters', 'accounts', 'totalDebit', 'totalCredit', 'balance', 'balanceType', 'period', 'grouping', 'groups'));
    }

    /** مفتاحُ المجموعة الزمنية لتاريخ قيد — الأسبوعُ يبدأ السبت ({@see \App\Helpers\WeekHelper}). */
    private function journalGroupKey($date, string $grouping): string
    {
        $date = \Carbon\Carbon::parse($date);

        return match ($grouping) {
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->copy()->startOfWeek(\Carbon\Carbon::SATURDAY)->format('Y-m-d'),
            default => $date->format('Y-m'),
        };
    }

    /**
     * عنوانُ كلِّ مجموعةٍ ظاهرة في الصفحة وعددُ قيودها ومجموعُها — على
     * **الفلتر كاملاً** لا على الصفحة وحدها، فيومٌ يمتدّ على صفحتين يحمل
     * الرقمَ نفسه في كلتيهما.
     *
     * @return array<string, array{label: string, count: int, total: float}>
     */
    private function journalGroups($groupBase, $entries, string $grouping): array
    {
        $keys = $entries->getCollection()->pluck('group_key')->filter()->unique()->values()->all();
        if (! $keys) {
            return [];
        }

        $keyExpr = match ($grouping) {
            'daily' => 'DATE(journal_entries.date)',
            // DAYOFWEEK: الأحد=1 … السبت=7 ⇒ ‎%7 يجعل السبت 0 فيُطرح منه حتى السبت
            'weekly' => 'DATE_SUB(DATE(journal_entries.date), INTERVAL (DAYOFWEEK(journal_entries.date) % 7) DAY)',
            default => "DATE_FORMAT(journal_entries.date, '%Y-%m')",
        };
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $counts = (clone $groupBase)->toBase()
            ->reorder()
            ->selectRaw("{$keyExpr} as grp, COUNT(*) as cnt")
            ->whereRaw("{$keyExpr} IN ({$placeholders})", $keys)
            ->groupByRaw($keyExpr)
            ->pluck('cnt', 'grp');

        // المجموعُ = مدينُ القيود، كعمود «المبلغ» في الجدول
        $totals = DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
            ->whereIn('journal_entry_details.journal_entry_id', (clone $groupBase)->toBase()->reorder()->select('journal_entries.id'))
            ->whereRaw("{$keyExpr} IN ({$placeholders})", $keys)
            ->groupByRaw($keyExpr)
            ->selectRaw("{$keyExpr} as grp, SUM(journal_entry_details.debit) as total")
            ->pluck('total', 'grp');

        $groups = [];
        foreach ($keys as $key) {
            $groups[$key] = [
                'label' => $this->journalGroupLabel($key, $grouping),
                'count' => (int) ($counts[$key] ?? 0),
                'total' => (float) ($totals[$key] ?? 0),
            ];
        }

        return $groups;
    }

    private function journalGroupLabel(string $key, string $grouping): string
    {
        if ($grouping === 'monthly') {
            return \Carbon\Carbon::createFromFormat('Y-m-d', $key.'-01')->locale('ar')->translatedFormat('F Y');
        }

        $date = \Carbon\Carbon::createFromFormat('Y-m-d', $key)->locale('ar');

        return $grouping === 'daily'
            ? $date->translatedFormat('l').' '.$key
            : 'الأسبوع من '.$key.' إلى '.$date->copy()->addDays(6)->format('Y-m-d');
    }

    public function create()
    {
        // قيدُ الإقفال السنوي لا يُختار يدوياً — انظر `manualReferenceTypes()`
        $referenceTypes = array_intersect_key(ReferenceType::getAllWithDetails(), array_flip(self::manualReferenceTypes()));
        $costCenters = CostCenter::all();

        /*
            نسب الضريبة كانت مكتوبةً في القالب: 0% و15% و5% ثلاثة خيارات
            ثابتة، بينما شاشة «إعدادات الضرائب» تعرّف نسب المنشأة. فمن
            يضيف ضريبةً بنسبة 10% لا يجدها في القيد اليدوي.
        */
        $taxs = TaxSitting::all();

        return view('account::journal.create', compact('referenceTypes', 'costCenters', 'taxs'));
    }

    // New endpoint for searching accounts
    public function searchAccounts(Request $request)
    {
        $query = $request->get('q');
        $parentId = $request->get('parent_id', null);

        $accounts = Account::searchable();

        if ($query) {
            $accounts->where(function ($q) use ($query) {
                $q->where('name', 'like', '%'.$query.'%')
                    ->orWhere('code', 'like', '%'.$query.'%');
            });
        }

        if ($parentId !== null) {
            $accounts->where('parent_id', $parentId);
        }

        // استبعاد الحسابات التي لديها أبناء (الحسابات الرئيسية)
        // فقط الحسابات الفرعية (الورقية) يمكن استخدامها في القيود
        $accounts->whereDoesntHave('children');

        // Limit results to prevent overload
        $results = $accounts->limit(50)->get();

        // Add level information for indentation
        $results->each(function ($account) {
            $account->level = $this->getAccountLevel($account);
        });

        return response()->json($results);
    }

    // Helper function to calculate account level
    private function getAccountLevel($account, $level = 0)
    {
        if (! $account->parent_id) {
            return $level;
        }

        $parent = Account::find($account->parent_id);
        if ($parent) {
            return $this->getAccountLevel($parent, $level + 1);
        }

        return $level;
    }

    // New endpoint for searching cost centers
    public function searchCostCenters(Request $request)
    {
        $query = $request->get('q');

        $costCenters = CostCenter::searchable();

        if ($query) {
            $costCenters->where('name', 'like', '%'.$query.'%')
                ->orWhere('code', 'like', '%'.$query.'%');
        }

        // Limit results to prevent overload
        $results = $costCenters->limit(50)->get();

        return response()->json($results);
    }

    /**
     * Get next reference number for a given reference type
     * جلب الرقم المرجعي التالي لنوع مرجع معين
     */
    public function getNextReferenceNumber(Request $request)
    {
        $referenceType = $request->get('reference_type');

        if (! $referenceType) {
            return response()->json([
                'success' => false,
                'message' => 'نوع المرجع مطلوب',
            ], 400);
        }

        // التحقق من صحة نوع المرجع
        $validType = ReferenceType::fromValue($referenceType);
        if (! $validType) {
            return response()->json([
                'success' => false,
                'message' => 'نوع المرجع غير صحيح',
            ], 400);
        }

        /*
            «قيد يومية» هو المستند الذي تُنشئه هذه الشاشة، وله قسمٌ في شاشة
            «ترقيم المستندات» (`entry`) ببادئته وعدد خاناته وتصفيره الدوري.
            فيُعايَن رقمه من هناك لا من مولّدٍ مكتوبٍ في النموذج.

            بقيّة الأنواع أرقامُ مستنداتٍ أخرى — فاتورةٌ أو سندٌ وُلّد قيدُه
            تلقائياً — فتبقى على مولّدها المرتبط بنوعها.
        */
        $nextNumber = $referenceType === ReferenceType::JOURNAL_VOUCHER->value
            ? peek_document_number('entry')
            : JournalEntry::getNextReferenceNumber($referenceType);

        return response()->json([
            'success' => true,
            'reference_number' => $nextNumber,
            'reference_type_name' => $validType->getName(),
        ]);
    }

    /**
     * رقم القيد التالي من قسم «قيد يومية» في شاشة الترقيم — مع استهلاكه.
     *
     * يتخطّى ما كان محجوزاً فعلاً في `journal_entries`: عدّادٌ أُعيد ضبطه
     * أو قيودٌ استُوردت بأرقامٍ سابقة تجعل الرقم التالي مكرَّراً، فيُردّ
     * الحفظ بخطأ مفتاحٍ مكرّر. والزيادة تحفظ البادئة وطول الأرقام كما
     * ضُبطا في الإعدادات، فلا تُستبدل بصيغةٍ مكتوبة في الشيفرة.
     */
    private function consumeEntryNumber(): string
    {
        $number = next_document_number('entry');
        $safety = 0;

        while ($safety < 10000 && JournalEntry::where('reference_number', $number)->exists()) {
            if (preg_match('/^(.*?)(\d+)$/', $number, $parts)) {
                $number = $parts[1].str_pad((string) ((int) $parts[2] + 1), strlen($parts[2]), '0', STR_PAD_LEFT);
            } else {
                $number .= '-1';
            }

            $safety++;
        }

        return $number;
    }

    private function buildAccountTree($accounts, $parentId = null, $level = 0)
    {
        $tree = [];

        foreach ($accounts as $account) {
            if ($account->parent_id == $parentId) {
                $account->level = $level;
                $tree[] = $account;
                // إضافة الحسابات الفرعية عبر البحث في الحسابات
                $tree = array_merge($tree, $this->buildAccountTree($accounts, $account->id, $level + 1));
            }
        }

        return $tree;
    }

    /**
     * تطبيعُ سطور القيد على إعدادات الضريبة — الطريقة المصرية.
     *
     * **ما كان**
     *
     * `tax_amount` يصل من المتصفّح ويُحفظ كما هو، والشاشةُ تحسبه ضرباً
     * في النسبة وحدها. فطريقةُ الضريبة — «شاملة» أو «مضافة» — لا يقرؤها
     * شيء: من ضبط ضريبةً شاملةً أُضيفت له فوق مبلغٍ يحملها أصلاً،
     * فتضخّم القيدُ بمقدارها مرّتين.
     *
     * **ما صار**
     *
     * النسبةُ وطريقتُها تُقرآن من سجلّ الضريبة المُختار (`tax_id`) لا من
     * الطلب. فإن كانت «مضافة» بقي السطرُ على مبلغه وأُضيف سطرُ الضريبة
     * فوقه؛ وإن كانت «شاملة» رُدَّ السطرُ إلى **أساسه قبل الضريبة**
     * واستُخرجت منه — فيبقى مجموعُ الجانبَين كما أدخله المحاسب، ويظهر
     * وعاءُ الضريبة وضريبتُه كلٌّ في سطره.
     *
     * @return array السطورُ بعد تصحيح `debit`/`credit`/`tax_amount`
     */
    protected function normalizeTaxDetails(array $details): array
    {
        foreach ($details as $index => $detail) {
            $type = $detail['tax_type'] ?? null;
            $taxId = $detail['tax_id'] ?? null;

            // «معفاة» و«صفرية» لا وعاءَ لهما، و«بلا ضريبة» كذلك
            $taxable = ! empty($taxId) && in_array($type, ['vat', 'tax'], true);

            if (! $taxable) {
                $details[$index]['tax_amount'] = 0;

                continue;
            }

            $debit = (float) ($detail['debit'] ?? 0);
            $credit = (float) ($detail['credit'] ?? 0);
            $amount = $debit > 0 ? $debit : $credit;

            $lines = TaxCalculator::forDocument($amount, $taxId);

            $details[$index]['tax_amount'] = $lines['tax'];

            // «شاملة» تعني أن ما أُدخل يحمل الضريبة، فيُردّ إلى أساسه
            if ($lines['inclusive']) {
                if ($debit > 0) {
                    $details[$index]['debit'] = $lines['base'];
                } else {
                    $details[$index]['credit'] = $lines['base'];
                }
            }
        }

        return $details;
    }

    /**
     * سطور الضريبة في القيد.
     *
     * **ما كان**
     *
     * شاشة القيد تعرض لكل سطرٍ عمودَي «نوع الضريبة» و«النسبة» وتحسب
     * مبلغها وتجمعه في «إجمالي الضريبة» أسفل الجدول. ثم **لا يصل شيءٌ من
     * ذلك إلى القاعدة**: التحقّق لا يذكر `tax_amount`، والحفظ يقرأ
     * `debit` و`credit` و`cost_center_id` وحدها.
     *
     * فالضريبة تُحسب أمام عين المستخدم ثم تُهمَل. ولا يقف الضرر عند رقمٍ
     * ضائع: القيد يُحفظ بمبلغه قبل الضريبة، فلا يُطابق الفاتورة التي عنه،
     * ولا يبلغ حساب الضريبة شيءٌ فيخرج الإقرار ناقصاً.
     *
     * **ما صار**
     *
     * الضريبة سطرٌ محاسبيّ مستقلّ كما ينبغي — لا رقمٌ يُضاف إلى سطر
     * الأصل. يتبع جانبَ سطره: ضريبةُ سطرٍ مدين مدينةٌ مثله (ضريبة مشتريات
     * مدفوعة)، وضريبةُ سطرٍ دائن دائنةٌ (ضريبة مبيعات محصَّلة). وحسابها
     * يُؤخذ من «الحسابات المحاسبية» لا من ثابتٍ مكتوب.
     *
     * @return array سطور الضريبة جاهزةً للإدراج
     */
    protected function buildTaxLines(array $details, int $journalEntryId): array
    {
        $lines = [];

        foreach ($details as $detail) {
            $amount = (float) ($detail['tax_amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $type = $detail['tax_type'] ?? null;

            /*
                «معفاة» و«صفرية» لا تُرحَّل: لا مبلغ لهما وإن كُتب رقم.

                والمقبولُ `vat` — وهو ما ترسله القائمة — و`tax` للسجلّات
                القديمة. وكان الشرطُ يقبل `tax` وحدَها، فسطرُ الضريبة الذي
                يختاره المستخدمُ من الشاشة **لا يُرحَّل إطلاقاً**: يُحسب
                أمام عينه ثم يسقط عند الحفظ.
            */
            if ($type !== null && ! in_array($type, ['vat', 'tax'], true)) {
                continue;
            }

            $isDebitLine = (float) ($detail['debit'] ?? 0) > 0;

            /*
                جانب الضريبة يتبع جانب سطرها:
                  • سطرٌ مدين  → مصروفٌ أو أصل  → ضريبة **مشتريات** مدينة
                  • سطرٌ دائن  → إيرادٌ أو التزام → ضريبة **مبيعات** دائنة
            */
            $accountId = $isDebitLine
                ? AccountingSettings::id('vat_paid', SystemAccountIds::VAT_PAID)
                : AccountingSettings::id('vat_payable', SystemAccountIds::VAT_PAYABLE);

            if (! $accountId || ! Account::whereKey($accountId)->exists()) {
                throw ValidationException::withMessages([
                    'details' => 'تعذّر ترحيل الضريبة: اضبط حسابَي ضريبة القيمة المضافة '
                        .'(المحصَّلة والمدفوعة) في «الحسابات المحاسبية».',
                ]);
            }

            $lines[] = [
                'journal_entry_id' => $journalEntryId,
                'account_id' => $accountId,
                'description' => 'ضريبة القيمة المضافة'
                    .(! empty($detail['description']) ? ' - '.$detail['description'] : ''),
                'debit' => $isDebitLine ? $amount : 0,
                'credit' => $isDebitLine ? 0 : $amount,
                'cost_center_id' => $detail['cost_center_id'] ?? null,
                'tax_id' => $detail['tax_id'] ?? null,
                'tax_type' => $detail['tax_type'] ?? null,
                /*
                    وسمُ السطر المولَّد: شاشةُ التعديل تُخفيه وتعيد توليده
                    من اختيار السطر الأصل. وبغير الوسم يظهر سطراً عادياً
                    فيُحفَظ مرّةً أخرى إلى جانب المولَّد الجديد — والقيد
                    يتضخّم بمقدار الضريبة في كلّ تعديل.
                */
                'is_tax_line' => true,
            ];
        }

        return $lines;
    }

    /**
     * مجموع ضريبة السطور — يدخل في فحص توازن القيد.
     *
     * بغيره يُقارَن المدين بالدائن قبل الضريبة، فيُقبل قيدٌ يخرج غير
     * متوازن بعد إضافتها.
     */
    protected function taxTotals(array $details): array
    {
        $debit = 0.0;
        $credit = 0.0;

        foreach ($details as $detail) {
            $amount = (float) ($detail['tax_amount'] ?? 0);
            $type = $detail['tax_type'] ?? null;

            if ($amount <= 0 || ($type !== null && ! in_array($type, ['vat', 'tax'], true))) {
                continue;
            }

            if ((float) ($detail['debit'] ?? 0) > 0) {
                $debit += $amount;
            } else {
                $credit += $amount;
            }
        }

        return ['debit' => $debit, 'credit' => $credit];
    }

    public function store(Request $request)
    {
        // فترة مقفلة لا يُكتب فيها قيد جديد
        $this->guardPeriod($request->input('journal_entry.date'), 'journal_entry.date');

        $request->validate([
            'journal_entry.date' => 'required|date',
            'journal_entry.description' => 'required|string|max:500',
            'journal_entry.reference_number' => 'nullable|string|max:50',
            'journal_entry.reference_type' => 'required|string|in:'.implode(',', self::manualReferenceTypes()),
            'journal_entry.currency' => 'nullable|string|max:10',
            'journal_entry.attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'details' => 'required|array|min:1',
            'details.*.account_id' => 'required|exists:accounts,id',
            'details.*.description' => 'nullable|string|max:255',
            'details.*.debit' => 'required_without:details.*.credit|nullable|numeric|min:0',
            'details.*.credit' => 'required_without:details.*.debit|nullable|numeric|min:0',
            'details.*.cost_center_id' => 'nullable|exists:cost_centers,id',
            /*
                حقول الضريبة: النموذج يجمعها، وكان التحقّق يُسقطها فتصل فارغة.

                و`vat` هي القيمةُ التي ترسلها القائمة لـ«خاضعة للضريبة»،
                ولم تكن مقبولةً هنا — فكلُّ سطرٍ ضريبيٍّ يُردّ بخطأ تحقّقٍ
                لا يذكر سببه. و`tax` تبقى للسجلّات القديمة.
            */
            'details.*.tax_type' => 'nullable|in:vat,tax,exempt,zero',
            // النسبةُ لم تعد تُرسَل: يُرسَل معرّفُ الضريبة وتُقرأ نسبتُها
            // وطريقتُها من الإعدادات
            'details.*.tax_id' => 'nullable|integer|exists:tax_sittings,id',
            'details.*.tax_amount' => 'nullable|numeric|min:0',
        ], [
            'journal_entry.date.required' => 'تاريخ القيد مطلوب',
            'journal_entry.description.required' => 'وصف القيد مطلوب',
            'journal_entry.reference_type.required' => 'نوع المرجع مطلوب',
            'journal_entry.reference_type.in' => 'نوع المرجع المحدد غير صحيح',
            'details.required' => 'يجب إضافة تفاصيل القيد',
            'details.*.account_id.required' => 'حساب القيد مطلوب',
            'details.*.account_id.exists' => 'الحساب المحدد غير موجود',
            'details.*.cost_center_id.exists' => 'مركز التكلفة المحدد غير موجود',
        ]);

        try {
            DB::beginTransaction();

            // التحقق من توازن القيد
            /*
                التوازن يُفحص **شاملاً الضريبة**.

                كان يُقارَن المدين بالدائن قبلها، فيُقبل قيدٌ متوازنٌ في
                الشاشة ثم يخرج مختلّاً بعد ترحيل سطورها — وهو ما يقع كلّما
                وقعت الضريبة على جانبٍ واحد.
            */
            // السطورُ تُطبَّع على إعدادات الضريبة قبل أيّ حسابٍ أو حفظ
            $details = $this->normalizeTaxDetails($request->details);

            $taxTotals = $this->taxTotals($details);

            $totalDebit = collect($details)->sum('debit') + $taxTotals['debit'];
            $totalCredit = collect($details)->sum('credit') + $taxTotals['credit'];

            if (abs($totalDebit - $totalCredit) > 0.001) {
                // ردٌّ من داخل المعاملة: تُغلق أوّلاً، وإلا بقيت مفتوحةً لما بعد الطلب
                DB::rollBack();

                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'مجموع المدين يجب أن يساوي مجموع الدائن',
                    ]);
                }

                return back()
                    ->withErrors(['message' => 'مجموع المدين يجب أن يساوي مجموع الدائن'])
                    ->withInput();
            }

            // التحقق من أن جميع الحسابات المستخدمة ليست حسابات رئيسية (لديها أبناء)
            $accountIds = collect($details)->pluck('account_id')->filter()->unique();
            $parentAccounts = Account::whereIn('id', $accountIds)
                ->whereHas('children')
                ->get();

            if ($parentAccounts->count() > 0) {
                DB::rollBack();

                $parentAccountNames = $parentAccounts->pluck('name')->implode('، ');
                $errorMessage = 'لا يمكن استخدام الحسابات الرئيسية في القيود. الحسابات التالية لديها حسابات فرعية: '.$parentAccountNames;

                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                    ]);
                }

                return back()
                    ->withErrors(['message' => $errorMessage])
                    ->withInput();
            }

            // إنشاء القيد
            $journalEntry = new JournalEntry;
            $journalEntry->date = $request->input('journal_entry.date');
            $journalEntry->description = $request->input('journal_entry.description');
            $journalEntry->reference_type = $request->input('journal_entry.reference_type');

            /*
                رقم المرجع.

                **ما كان**

                النموذج يعرض رقماً مُعايَناً (`peek`) عند اختيار نوع المرجع،
                ثمّ يُرسله مع الحفظ. وهذا الفرع كان يقبله كما هو ما دام
                غيرَ فارغ — فلا يُستهلَك الرقم من عدّاد «ترقيم المستندات»
                إطلاقاً، ويبقى العدّاد واقفاً: القيد التالي يُعايِن الرقم
                نفسه، ومحرِّران يفتحان الشاشة معاً يحفظان برقمٍ واحد.

                وإن تُرك الحقل فارغاً، تولّى `JournalEntry::boot()` توليده
                بصيغة `TYPE-YYYY-0001` **مكتوبةً في الموديل** — فتُهدر
                البادئة وعدد الخانات والتصفير الدوري المضبوطة في الإعدادات.

                **ما صار**

                القيد اليدوي يستهلك رقمه من القسم `entry` عند الحفظ دائماً،
                فالمعاينة عرضٌ لا حجز. وبقيّة الأنواع أرقامُ مستنداتها
                الأصلية (فاتورة، سند) فتُقبل كما تُرسَل.
            */
            $referenceType = $journalEntry->reference_type instanceof ReferenceType
                ? $journalEntry->reference_type->value
                : $journalEntry->reference_type;

            $isManualEntry = $referenceType === ReferenceType::JOURNAL_VOUCHER->value;

            $referenceNumber = $request->input('journal_entry.reference_number');

            if ($isManualEntry) {
                $journalEntry->reference_number = $this->consumeEntryNumber();
            } elseif (! empty($referenceNumber) && $referenceNumber !== 'جاري التوليد...') {
                $journalEntry->reference_number = $referenceNumber;
            }
            // وإلا فرقمُ مستندٍ آخر يولّده `boot()` حسب نوع المرجع

            $journalEntry->status = 1; // معتمد مباشرة
            $journalEntry->currency = $request->input('journal_entry.currency', 'SAR');
            $journalEntry->created_by_employee = auth()->id();
            $journalEntry->approved_by_employee = auth()->id();

            // معالجة المرفق
            if ($request->hasFile('journal_entry.attachment')) {
                $file = $request->file('journal_entry.attachment');
                if ($file->isValid()) {
                    $filename = time().'_'.$file->getClientOriginalName();
                    $file->move(public_path('assets/uploads/journal'), $filename);
                    $journalEntry->attachment = $filename;
                }
            }

            $journalEntry->save();

            \Log::info('Created journal entry #'.$journalEntry->id);

            // إضافة التفاصيل مع مركز التكلفة لكل سطر
            foreach ($details as $detail) {
                if (! empty($detail['debit']) || ! empty($detail['credit'])) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $detail['account_id'],
                        'description' => $detail['description'] ?? null,
                        'debit' => $detail['debit'] ?? 0,
                        'credit' => $detail['credit'] ?? 0,
                        'cost_center_id' => $detail['cost_center_id'] ?? null,
                        // اختيارُ الضريبة يُحفَظ على السطر ليعود كما تُرك
                        'tax_id' => $detail['tax_id'] ?? null,
                        'tax_type' => $detail['tax_type'] ?? null,
                    ]);
                }
            }

            // سطور الضريبة — كانت تُحسب في الشاشة ولا تصل القاعدة
            $taxLines = $this->buildTaxLines($details, $journalEntry->id);

            foreach ($taxLines as $taxLine) {
                JournalEntryDetail::create($taxLine);
            }

            /*
                القيدُ ضريبيٌّ إن رُحِّل عنه سطرُ ضريبةٍ واحدٌ على الأقل —
                وهو شرطُ `buildTaxLines` نفسُه، فلا يفترق الوسمُ عن الواقع.
            */
            $journalEntry->is_taxable = TaxableDocument::fromJournalDetails($details);
            $journalEntry->save();

            OperationLog::record(
                'created',
                'finance_log',
                $journalEntry->id, // ID النشاط المرتبط
                'تم اضافة قيد جديد  **'.'**'
            );

            // تخصيص المدفوعات تلقائيًا إذا كان القيد مرتبط بحساب عميل أو مورد
            $this->allocatePaymentsForJournalEntry($journalEntry);

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء القيد المحاسبي بنجاح',
                    'redirect' => route('journal.index'),
                ]);
            }

            return redirect()->route('journal.index')->with('success', 'تم إنشاء القيد المحاسبي بنجاح');
        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.JournalEntry', 'store');

            DB::rollback();

            \Log::error('Error creating journal entry: '.$e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حفظ القيد: '.$e->getMessage(),
                ]);
            }

            return back()
                ->withErrors(['message' => 'حدث خطأ أثناء حفظ القيد: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function show($id)
    {
        $entry = JournalEntry::with([
            'details.account',
            'details.costCenter',
            'linkedInvoices',
            'invoice.client',
            'purchaseInvoice.supplier',
            'client',
            'employee',
            'branch',
            'createdByEmployee',
            'approvedByEmployee',
        ])->findOrFail($id);

        $entryDetails = JournalEntryDetail::with('account')->get();

        // جمع كل العناصر المرتبطة
        $relatedItems = $this->getRelatedItems($entry);

        // نطاق الرؤية نفسه المطبَّق على edit/update/destroy — لا صلاحيات
        // النظام القديم (`g_a_d_r_add_edit_delete_own/all_journal_entries`)
        $canEdit = auth()->user()->hasRolePermission('account.journal.edit')
            && $this->canAccessCreator($entry->created_by_employee);

        return view('account::journal.show', compact('entry', 'entryDetails', 'relatedItems', 'canEdit'));
    }

    /**
     * Get all related items for a journal entry based on reference_type
     */
    private function getRelatedItems(JournalEntry $entry): array
    {
        $relatedItems = [];

        // فاتورة المبيعات
        if ($entry->invoice_id && $entry->invoice) {
            $relatedItems[] = [
                'type' => 'sales_invoice',
                'type_name' => 'فاتورة مبيعات',
                'icon' => 'fa-file-invoice',
                'color' => 'success',
                'id' => $entry->invoice->id,
                'number' => $entry->invoice->invoice_number ?? $entry->invoice->id,
                'date' => $entry->invoice->created_at?->format('Y-m-d'),
                'amount' => $entry->invoice->total ?? 0,
                'client' => $entry->invoice->client->name ?? null,
                'url' => $this->safeRoute('invoices.show', $entry->invoice->id),
            ];
        }

        // فاتورة المشتريات
        if ($entry->purchase_invoice_id && $entry->purchaseInvoice) {
            $relatedItems[] = [
                'type' => 'purchase_invoice',
                'type_name' => 'فاتورة مشتريات',
                'icon' => 'fa-file-invoice-dollar',
                'color' => 'warning',
                'id' => $entry->purchaseInvoice->id,
                'number' => $entry->purchaseInvoice->invoice_number ?? $entry->purchaseInvoice->id,
                'date' => $entry->purchaseInvoice->created_at?->format('Y-m-d'),
                'amount' => $entry->purchaseInvoice->total ?? 0,
                'supplier' => $entry->purchaseInvoice->supplier->name ?? null,
                'url' => $this->safeRoute('invoicePurchases.show', $entry->purchaseInvoice->id),
            ];
        }

        // العميل
        if ($entry->client_id && $entry->client) {
            $relatedItems[] = [
                'type' => 'client',
                'type_name' => 'عميل',
                'icon' => 'fa-user',
                'color' => 'primary',
                'id' => $entry->client->id,
                'name' => $entry->client->name,
                'phone' => $entry->client->phone ?? null,
                'url' => $this->safeRoute('showClients', $entry->client->id),
            ];
        }

        // المورد - من فاتورة المشتريات
        if ($entry->purchaseInvoice && $entry->purchaseInvoice->supplier) {
            $supplier = $entry->purchaseInvoice->supplier;
            $relatedItems[] = [
                'type' => 'supplier',
                'type_name' => 'مورد',
                'icon' => 'fa-truck',
                'color' => 'info',
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone ?? null,
                'url' => $this->safeRoute('suppliers.show', $supplier->id),
            ];
        }

        // الموظف
        if ($entry->employee_id && $entry->employee) {
            $relatedItems[] = [
                'type' => 'employee',
                'type_name' => 'موظف',
                'icon' => 'fa-id-badge',
                'color' => 'secondary',
                'id' => $entry->employee->id,
                'name' => $entry->employee->name,
                'department' => $entry->employee->department ?? null,
                'url' => $this->safeRoute('employees.show', $entry->employee->id),
            ];
        }

        // عمليات الدفع المرتبطة - البحث عن طريق reference_number
        $payments = PaymentsProcess::where('reference_number', $entry->id)->get();
        if ($payments->count() > 0) {
            foreach ($payments as $payment) {
                $relatedItems[] = [
                    'type' => 'payment',
                    'type_name' => $payment->type == 'receipt' ? 'سند قبض' : 'سند صرف',
                    'icon' => $payment->type == 'receipt' ? 'fa-hand-holding-usd' : 'fa-money-bill-wave',
                    'color' => $payment->type == 'receipt' ? 'success' : 'danger',
                    'id' => $payment->id,
                    'number' => $payment->reference_number ?? $payment->id,
                    'date' => $payment->payment_date ? date('Y-m-d', strtotime($payment->payment_date)) : ($payment->created_at?->format('Y-m-d')),
                    'amount' => $payment->amount ?? 0,
                    'status' => $payment->payment_status ?? null,
                    'url' => $this->safeRoute('paymentsClient.show', $payment->id),
                ];
            }
        }

        // الفواتير المرتبطة من خلال جدول الربط
        if ($entry->linkedInvoices && $entry->linkedInvoices->count() > 0) {
            foreach ($entry->linkedInvoices as $linkedInvoice) {
                // تجنب التكرار إذا كانت الفاتورة الرئيسية مضافة
                if ($entry->invoice_id == $linkedInvoice->id) {
                    continue;
                }
                $relatedItems[] = [
                    'type' => 'linked_invoice',
                    'type_name' => 'فاتورة مرتبطة',
                    'icon' => 'fa-link',
                    'color' => 'info',
                    'id' => $linkedInvoice->id,
                    'number' => $linkedInvoice->invoice_number ?? $linkedInvoice->id,
                    'date' => $linkedInvoice->created_at?->format('Y-m-d'),
                    'allocation_amount' => $linkedInvoice->pivot->allocation_amount ?? 0,
                    'allocation_percentage' => $linkedInvoice->pivot->allocation_percentage ?? 0,
                    'url' => $this->safeRoute('invoices.show', $linkedInvoice->id),
                ];
            }
        }

        // مراكز التكلفة (الآن من جدول التفاصيل)
        $costCentersInEntry = $entry->details->pluck('costCenter')->filter()->unique('id');
        foreach ($costCentersInEntry as $costCenter) {
            if ($costCenter) {
                $relatedItems[] = [
                    'type' => 'cost_center',
                    'type_name' => 'مركز تكلفة',
                    'icon' => 'fa-sitemap',
                    'color' => 'dark',
                    'id' => $costCenter->id,
                    'name' => $costCenter->name,
                    'code' => $costCenter->code ?? null,
                ];
            }
        }

        // الفرع
        if ($entry->branch_id && $entry->branch) {
            $relatedItems[] = [
                'type' => 'branch',
                'type_name' => 'الفرع',
                'icon' => 'fa-building',
                'color' => 'primary',
                'id' => $entry->branch->id,
                'name' => $entry->branch->name,
            ];
        }

        // ═══════════════════════════════════════════════════════════════
        // سند القبض - البحث من reference_type
        // ═══════════════════════════════════════════════════════════════
        if ($entry->reference_type == ReferenceType::CUSTOMER_COLLECTION->value && $entry->reference_number) {
            $receipt = Receipt::where('code', $entry->reference_number)->first();
            if ($receipt) {
                $relatedItems[] = [
                    'type' => 'receipt',
                    'type_name' => 'سند قبض',
                    'icon' => 'fa-hand-holding-usd',
                    'color' => 'success',
                    'id' => $receipt->id,
                    'code' => $receipt->code,
                    'date' => $receipt->date,
                    'amount' => $receipt->amount ?? 0,
                    'client' => $receipt->client->name ?? null,
                    'description' => $receipt->description ?? null,
                    'url' => $this->safeRoute('receipts.show', $receipt->id),
                ];
            }
        }

        // ═══════════════════════════════════════════════════════════════
        // سند الصرف - البحث من reference_type
        // ═══════════════════════════════════════════════════════════════
        if ($entry->reference_type == ReferenceType::EXPENSES->value && $entry->reference_number) {
            $expense = Expense::where('code', $entry->reference_number)->first();
            if ($expense) {
                $relatedItems[] = [
                    'type' => 'expense',
                    'type_name' => 'سند صرف',
                    'icon' => 'fa-money-bill-wave',
                    'color' => 'danger',
                    'id' => $expense->id,
                    'code' => $expense->code,
                    'date' => $expense->date,
                    'amount' => $expense->amount ?? 0,
                    'supplier' => $expense->supplier->name ?? null,
                    'description' => $expense->description ?? null,
                    'url' => $this->safeRoute('expenses.show', $expense->id),
                ];
            }
        }

        // ═══════════════════════════════════════════════════════════════
        // الشيكات المستلمة المرتبطة بهذا القيد
        // ═══════════════════════════════════════════════════════════════
        $receivedCheques = ReceivedCheque::where('journal_entry_id', $entry->id)
            ->orWhere('collection_journal_entry_id', $entry->id)
            ->get();

        foreach ($receivedCheques as $cheque) {
            $chequeRelationType = $cheque->journal_entry_id == $entry->id ? 'شيك مستلم (إنشاء)' : 'شيك مستلم (تحصيل/تظهير)';
            $relatedItems[] = [
                'type' => 'received_cheque',
                'type_name' => $chequeRelationType,
                'icon' => 'fa-money-check',
                'color' => 'info',
                'id' => $cheque->id,
                'cheque_number' => $cheque->cheque_number,
                'amount' => $cheque->amount ?? 0,
                'due_date' => $cheque->due_date?->format('Y-m-d'),
                'status' => $cheque->status?->value ?? $cheque->status,
                'drawer_bank' => $cheque->drawer_bank ?? null,
                'url' => $this->safeRoute('receivedCheques.show', $cheque->id),
            ];
        }

        // ═══════════════════════════════════════════════════════════════
        // الشيكات الصادرة المرتبطة بهذا القيد
        // ═══════════════════════════════════════════════════════════════
        $payableCheques = PayableCheque::where('journal_entry_id', $entry->id)
            ->orWhere('cash_journal_entry_id', $entry->id)
            ->get();

        foreach ($payableCheques as $cheque) {
            $chequeRelationType = $cheque->journal_entry_id == $entry->id ? 'شيك صادر (إصدار)' : 'شيك صادر (صرف)';
            $relatedItems[] = [
                'type' => 'payable_cheque',
                'type_name' => $chequeRelationType,
                'icon' => 'fa-money-check-alt',
                'color' => 'warning',
                'id' => $cheque->id,
                'cheque_number' => $cheque->cheque_number,
                'amount' => $cheque->amount ?? 0,
                'due_date' => $cheque->due_date?->format('Y-m-d'),
                'status' => $cheque->status?->value ?? $cheque->status,
                'payee_name' => $cheque->payee_name ?? null,
                'url' => $this->safeRoute('payableCheques.show', $cheque->id),
            ];
        }

        // ═══════════════════════════════════════════════════════════════
        // الفواتير المسددة من جدول journal_entry_invoices
        // ═══════════════════════════════════════════════════════════════
        $paidInvoices = JournalEntryInvoice::where('entry_id', $entry->id)->get();

        foreach ($paidInvoices as $paidInvoice) {
            // تحقق من نوع الفاتورة
            if ($paidInvoice->invoice_type == 'sale' || $paidInvoice->invoice_type == 'sales') {
                $invoice = $paidInvoice->invoice;
                if ($invoice) {
                    // تجنب التكرار إذا كانت الفاتورة مضافة مسبقاً
                    $alreadyExists = collect($relatedItems)->contains(function ($item) use ($invoice) {
                        return ($item['type'] == 'sales_invoice' || $item['type'] == 'linked_invoice') && $item['id'] == $invoice->id;
                    });

                    if (! $alreadyExists) {
                        $relatedItems[] = [
                            'type' => 'paid_invoice',
                            'type_name' => 'فاتورة مسددة',
                            'icon' => 'fa-file-invoice-dollar',
                            'color' => 'success',
                            'id' => $invoice->id,
                            'number' => $invoice->invoice_number ?? $invoice->id,
                            'date' => $invoice->created_at?->format('Y-m-d'),
                            'total_amount' => $invoice->total ?? 0,
                            'paid_amount' => $paidInvoice->allocation_amount ?? 0,
                            'client' => $invoice->client->name ?? null,
                            'url' => $this->safeRoute('invoices.show', $invoice->id),
                        ];
                    }
                }
            } elseif ($paidInvoice->invoice_type == 'purchase') {
                $purchaseInvoice = $paidInvoice->purchaseInvoice;
                if ($purchaseInvoice) {
                    // تجنب التكرار إذا كانت الفاتورة مضافة مسبقاً
                    $alreadyExists = collect($relatedItems)->contains(function ($item) use ($purchaseInvoice) {
                        return $item['type'] == 'purchase_invoice' && $item['id'] == $purchaseInvoice->id;
                    });

                    if (! $alreadyExists) {
                        $relatedItems[] = [
                            'type' => 'paid_purchase_invoice',
                            'type_name' => 'فاتورة مشتريات مسددة',
                            'icon' => 'fa-file-invoice',
                            'color' => 'warning',
                            'id' => $purchaseInvoice->id,
                            'number' => $purchaseInvoice->invoice_number ?? $purchaseInvoice->id,
                            'date' => $purchaseInvoice->created_at?->format('Y-m-d'),
                            'total_amount' => $purchaseInvoice->total ?? 0,
                            'paid_amount' => $paidInvoice->allocation_amount ?? 0,
                            'supplier' => $purchaseInvoice->supplier->name ?? null,
                            'url' => $this->safeRoute('invoicePurchases.show', $purchaseInvoice->id),
                        ];
                    }
                }
            }
        }

        return $relatedItems;
    }

    /**
     * Generate route safely, returns null if route doesn't exist
     *
     * @param  mixed  $parameters
     */
    private function safeRoute(string $routeName, $parameters = []): ?string
    {
        try {
            return route($routeName, $parameters);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.JournalEntry', 'safeRoute');

            return null;
        }
    }

    public function edit($id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($blocked = $this->systemClosingEntryReason($entry)) {
            return redirect()->route('journal.index')->with('error', $blocked);
        }

        // فحص صلاحية التعديل
        $user = auth()->user();
        $this->authorizeCreatorScope($entry->created_by_employee);

        $journal = JournalEntry::with('details')->findOrFail($id);
        $accounts = Account::all();
        $clients = Client::all();
        $employees = Employee::all();
        $costCenters = CostCenter::all();
        // قيدُ الإقفال السنوي لا يُختار يدوياً — انظر `manualReferenceTypes()`
        $referenceTypes = array_intersect_key(ReferenceType::getAllWithDetails(), array_flip(self::manualReferenceTypes()));

        // نسب الضريبة من «إعدادات الضرائب» لا من ثلاثة خيارات مكتوبة في القالب
        $taxs = TaxSitting::all();

        return view('account::journal.edit', compact('entry', 'journal', 'accounts', 'clients', 'employees', 'costCenters', 'referenceTypes', 'taxs'));
    }

    public function update(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);

        $this->guardSystemClosingEntry($entry);

        // الحركة تُنقَل من تاريخٍ إلى آخر: يجب أن يكون الطرفان مفتوحَين
        $this->guardPeriod($entry->date, 'journal_entry.date');
        $this->guardPeriod($request->input('journal_entry.date'), 'journal_entry.date');

        // فحص صلاحية التعديل
        $user = auth()->user();
        $this->authorizeCreatorScope($entry->created_by_employee);

        if ($entry->invoice_id || $entry->purchase_invoice_id) {
            DB::rollback();

            return back()->with('error', 'لا يمكن تعديل القيد المرتبط بفاتورة. القيد مرتبط بفاتورة '.
                ($entry->invoice_id ? 'مبيعات رقم #'.$entry->invoice_id : 'شراء رقم #'.$entry->purchase_invoice_id));
        }
        $request->validate([
            'journal_entry.date' => 'required|date',
            'journal_entry.description' => 'required|string|max:500',
            'journal_entry.reference_number' => 'nullable|string|max:50',
            'journal_entry.reference_type' => 'required|string|in:'.implode(',', self::manualReferenceTypes()),
            'journal_entry.currency' => 'nullable|string|max:10',
            'journal_entry.attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'details' => 'required|array|min:1',
            'details.*.account_id' => 'required|exists:accounts,id',
            'details.*.description' => 'nullable|string|max:255',
            'details.*.debit' => 'required_without:details.*.credit|numeric|min:0',
            'details.*.credit' => 'required_without:details.*.debit|numeric|min:0',
            'details.*.cost_center_id' => 'nullable|exists:cost_centers,id',
        ], [
            'journal_entry.date.required' => 'تاريخ القيد مطلوب',
            'journal_entry.description.required' => 'وصف القيد مطلوب',
            'journal_entry.reference_type.required' => 'نوع المرجع مطلوب',
            'journal_entry.reference_type.in' => 'نوع المرجع المحدد غير صحيح',
            'details.required' => 'يجب إضافة تفاصيل القيد',
            'details.*.account_id.required' => 'حساب القيد مطلوب',
            'details.*.account_id.exists' => 'الحساب المحدد غير موجود',
            'details.*.cost_center_id.exists' => 'مركز التكلفة المحدد غير موجود',
        ]);

        try {
            DB::beginTransaction();

            // التحقق من توازن القيد
            /*
                التوازن يُفحص **شاملاً الضريبة**.

                كان يُقارَن المدين بالدائن قبلها، فيُقبل قيدٌ متوازنٌ في
                الشاشة ثم يخرج مختلّاً بعد ترحيل سطورها — وهو ما يقع كلّما
                وقعت الضريبة على جانبٍ واحد.
            */
            // السطورُ تُطبَّع على إعدادات الضريبة قبل أيّ حسابٍ أو حفظ
            $details = $this->normalizeTaxDetails($request->details);

            $taxTotals = $this->taxTotals($details);

            $totalDebit = collect($details)->sum('debit') + $taxTotals['debit'];
            $totalCredit = collect($details)->sum('credit') + $taxTotals['credit'];

            if (abs($totalDebit - $totalCredit) > 0.001) {
                // ردٌّ من داخل المعاملة: تُغلق أوّلاً، وإلا بقيت مفتوحةً لما بعد الطلب
                DB::rollBack();

                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'مجموع المدين يجب أن يساوي مجموع الدائن',
                    ]);
                }

                return back()
                    ->withErrors(['message' => 'مجموع المدين يجب أن يساوي مجموع الدائن'])
                    ->withInput();
            }

            // التحقق من أن جميع الحسابات المستخدمة ليست حسابات رئيسية (لديها أبناء)
            $accountIds = collect($details)->pluck('account_id')->filter()->unique();
            $parentAccounts = Account::whereIn('id', $accountIds)
                ->whereHas('children')
                ->get();

            if ($parentAccounts->count() > 0) {
                DB::rollBack();

                $parentAccountNames = $parentAccounts->pluck('name')->implode('، ');
                $errorMessage = 'لا يمكن استخدام الحسابات الرئيسية في القيود. الحسابات التالية لديها حسابات فرعية: '.$parentAccountNames;

                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                    ]);
                }

                return back()
                    ->withErrors(['message' => $errorMessage])
                    ->withInput();
            }

            // حذف البيانات المرتبطة بالقيد السابق
            $this->deleteRelatedData($entry);

            // تحديث القيد
            $entry->date = $request->input('journal_entry.date');
            $entry->description = $request->input('journal_entry.description');
            /*
                التعديل لا يُعيد توليد الرقم: القيد مُرقَّم من قبلُ ورقمه
                مرجعٌ في مستنداتٍ أخرى. وكان الحقل الفارغ يمحو الرقم
                القائم — فيلتقطه `boot()` بصيغته المكتوبة في الموديل.
            */
            $entry->reference_number = $request->input('journal_entry.reference_number')
                ?: $entry->reference_number;
            $entry->reference_type = $request->input('journal_entry.reference_type');
            $entry->currency = $request->input('journal_entry.currency', 'SAR');

            // معالجة المرفق
            $attachmentPath = $entry->attachment;
            if ($request->hasFile('journal_entry.attachment')) {
                // حذف المرفق القديم
                if ($attachmentPath) {
                    Storage::disk('public')->delete($attachmentPath);
                }
                $attachmentPath = $request->file('journal_entry.attachment')->store('journal_entries', 'public');
            }

            $entry->attachment = $attachmentPath;
            $entry->save();

            // إضافة التفاصيل الجديدة مع مركز التكلفة لكل سطر
            $taxLines = $this->buildTaxLines($details, $entry->id);

            // الوسمُ يُعاد حسابُه عند التعديل: قيدٌ نُزعت ضريبتُه يعود عادياً
            $entry->is_taxable = TaxableDocument::fromJournalDetails($details);
            $entry->save();

            foreach ($details as $detail) {
                if (! empty($detail['debit']) || ! empty($detail['credit'])) {
                    JournalEntryDetail::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $detail['account_id'],
                        'description' => $detail['description'] ?? null,
                        'debit' => $detail['debit'] ?? 0,
                        'credit' => $detail['credit'] ?? 0,
                        'cost_center_id' => $detail['cost_center_id'] ?? null,
                        // اختيارُ الضريبة يُحفَظ على السطر ليعود كما تُرك
                        'tax_id' => $detail['tax_id'] ?? null,
                        'tax_type' => $detail['tax_type'] ?? null,
                    ]);
                }
            }

            // سطور الضريبة — تُبنى قبل الحلقة لترمي قبل أي كتابة إن لم تُضبط حساباتها
            foreach ($taxLines as $taxLine) {
                JournalEntryDetail::create($taxLine);
            }

            // تخصيص المدفوعات تلقائيًا إذا كان القيد مرتبط بحساب عميل أو مورد
            $this->allocatePaymentsForJournalEntry($entry);

            /*
                تعديل قيدٍ محاسبي عملية جسيمة: تُحذف بنوده وتُعاد كتابتها،
                وتتغيّر أرصدة حساباته. وكانت تمرّ بلا أثرٍ في «سجلّ النشاط»
                — يُعدَّل القيد ولا يُعرف من عدّله ولا ما الذي تغيّر، بينما
                الإنشاء والحذف يُسجَّلان. وهي أخطر الثلاث.
            */
            OperationLog::record('updated', 'Account.JournalEntry', $entry,
                'عدّل القيد المحاسبي رقم '.$entry->reference_number
                .' بتاريخ '.$entry->date);

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث القيد المحاسبي بنجاح',
                    'redirect' => route('journal.show', $entry->id),
                ]);
            }

            return redirect()->route('journal.show', $entry->id)->with('success', 'تم تحديث القيد المحاسبي بنجاح');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.JournalEntry', 'update');

            DB::rollback();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تحديث القيد: '.$e->getMessage(),
                ]);
            }

            return back()
                ->withErrors(['message' => 'حدث خطأ أثناء تحديث القيد: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * حذف البيانات المرتبطة بالقيد
     *
     * @return void
     */
    private function deleteRelatedData(JournalEntry $entry)
    {
        // 1. الحصول على جميع الفواتير المرتبطة بهذا القيد قبل حذف أي شيء
        $linkedInvoices = JournalEntryInvoice::where('entry_id', $entry->id)->get();

        // 2. حذف الروابط من جدول journal_entry_invoices أولاً
        JournalEntryInvoice::where('entry_id', $entry->id)->delete();

        // 3. حذف المدفوعات المرتبطة بهذا القيد
        PaymentsProcess::where('reference_number', $entry->id)->delete();

        // 4. تحديث حالة الفواتير بعد حذف الدفعات والروابط
        foreach ($linkedInvoices as $link) {
            if ($link->invoice_type === InvoiceType::SALE->value) {
                // بالنسبة لفواتير البيع
                $invoice = Invoice::find($link->invoice_id);
                if ($invoice) {
                    $invoice->updatePaymentStatus(); // إعادة حساب حالة الدفع
                }
            } else {
                // بالنسبة لفواتير الشراء
                $invoice = PurchaseInvoice::find($link->invoice_id);
                if ($invoice) {
                    $invoice->updatePaymentStatus(); // إعادة حساب حالة الدفع
                }
            }
        }

        // 5. حذف تفاصيل القيد
        $entry->details()->delete();
    }

    /**
     * تخصيص المدفوعات تلقائيًا للفواتير غير المدفوعة عند اعتماد قيد اليومية
     *
     * @return void
     */
    private function allocatePaymentsForJournalEntry(JournalEntry $entry)
    {
        // الحصول على تفاصيل القيد
        $details = $entry->details;

        \Log::info('Starting payment allocation for journal entry #'.$entry->id);
        \Log::info('Number of entry details: '.count($details));

        // التحقق من أن القيد مرتبط بحساب عميل أو مورد
        foreach ($details as $detail) {
            $account = $detail->account;

            if (! $account) {
                continue;
            }

            // إذا كان الحساب مرتبطًا بعميل
            if ($account && $account->client_id) {
                // حساب الرصيد المتاح للدفع (الائتمان - المدين)
                $availableBalance = $detail->credit - $detail->debit;

                \Log::info('Processing client account #'.$account->id.' with available balance: '.$availableBalance);

                if ($availableBalance > 0) {
                    // الحصول على الفواتير غير المدفوعة للعميل
                    $unpaidInvoices = $this->getUnpaidClientInvoices($account->client_id);

                    \Log::info('Found '.count($unpaidInvoices).' unpaid client invoices');

                    // تخصيص المدفوعات تسلسليًا
                    $this->allocatePaymentsToInvoices($entry, $account, $unpaidInvoices, $availableBalance, 'client');
                }
            }

            // إذا كان الحساب مرتبطًا بمورد
            if ($account && $account->supplier_id) {
                // حساب الرصيد المتاح للدفع (المدين - الائتمان)
                $availableBalance = $detail->debit - $detail->credit;

                \Log::info('Processing supplier account #'.$account->id.' with available balance: '.$availableBalance);

                if ($availableBalance > 0) {
                    // الحصول على فواتير الشراء غير المدفوعة للمورد
                    $unpaidInvoices = $this->getUnpaidSupplierInvoices($account->supplier_id);

                    \Log::info('Found '.count($unpaidInvoices).' unpaid supplier invoices');

                    // تخصيص المدفوعات تسلسليًا
                    $this->allocatePaymentsToInvoices($entry, $account, $unpaidInvoices, $availableBalance, 'supplier');
                }
            }
        }

        \Log::info('Finished payment allocation for journal entry #'.$entry->id);
    }

    /**
     * الحصول على الفواتير غير المدفوعة لعميل معين
     *
     * @param  int  $clientId
     * @return Collection
     */
    private function getUnpaidClientInvoices($clientId)
    {
        return Invoice::where('client_id', $clientId)
            ->where('is_paid', false)
            ->where('payment_status', '!=', 1) // ليس مدفوع بالكامل
            ->orderBy('invoice_date', 'asc')
            ->get();
    }

    /**
     * الحصول على فواتير الشراء غير المدفوعة لمورد معين
     *
     * @param  int  $supplierId
     * @return Collection
     */
    private function getUnpaidSupplierInvoices($supplierId)
    {
        return PurchaseInvoice::where('supplier_id', $supplierId)
            ->where('is_paid', false)
            ->where('payment_status', '!=', 'paid') // نص وليس رقم
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * تخصيص المدفوعات للفواتير تسلسليًا مع تسديد الرصيد السابق أولاً
     * Generated by Copilot
     *
     * يتم التسديد بالترتيب التالي:
     * 1. حساب الرصيد السابق (رصيد الحساب - إجمالي الفواتير غير المسددة)
     * 2. تسديد الرصيد السابق أولاً
     * 3. ثم تسديد الفواتير حسب تاريخها (الأقدم أولاً)
     *
     * @param  JournalEntry  $entry  القيد المحاسبي
     * @param  Account  $account  الحساب
     * @param  Collection  $invoices  الفواتير غير المسددة
     * @param  float  $available_balance  الرصيد المتاح للدفع
     * @param  string  $type  نوع الحساب (client أو supplier)
     * @return void
     */
    private function allocatePaymentsToInvoices(JournalEntry $entry, Account $account, $invoices, $available_balance, $type)
    {
        $remaining_balance = $available_balance;
        $paid_invoices = [];
        $total_allocated = 0;
        $invoice_links = [];

        \Log::info('========== بدء توزيع المدفوعات ==========');
        \Log::info('قيد اليومية #'.$entry->id.' - المبلغ المتاح: '.$available_balance);
        \Log::info('نوع الحساب: '.$type);

        // الخطوة الأولى: حساب الرصيد السابق وتسديده قبل الفواتير
        if ($type === 'client' && $account->client_id) {
            // حساب الرصيد السابق للعميل
            $balance_info = $this->calculateClientPreviousBalance($account->client_id, $available_balance);

            \Log::info('تحليل رصيد العميل:', [
                'رصيد_الحساب_الأصلي' => $balance_info['account_balance'],
                'إجمالي_المتبقي_من_الفواتير' => $balance_info['total_invoices_due'],
                'الرصيد_السابق_الأصلي' => $balance_info['previous_balance'],
                'الرصيد_السابق_المتبقي' => $balance_info['remaining_previous_balance'],
            ]);

            // تسديد الرصيد السابق أولاً (إن وجد)
            if ($balance_info['remaining_previous_balance'] > 0) {
                $previous_balance_pay = min($remaining_balance, $balance_info['remaining_previous_balance']);

                if ($previous_balance_pay > 0) {
                    \Log::info('تسديد الرصيد السابق للعميل', [
                        'المبلغ_المدفوع' => $previous_balance_pay,
                        'من_أصل' => $balance_info['remaining_previous_balance'],
                    ]);

                    // تسجيل دفعة للرصيد السابق
                    PaymentsProcess::create([
                        'client_id' => $account->client_id,
                        'invoice_id' => null, // لا يوجد فاتورة مرتبطة
                        'amount' => $previous_balance_pay,
                        'type' => 'previous_balance_payment',
                        'payment_date' => $entry->date,
                        'payment_status' => 1,
                        'payment_method' => 1,
                        'reference_number' => $entry->id,
                        'notes' => 'تسديد رصيد سابق من قيد اليومية #'.$entry->id,
                        'employee_id' => auth()->id(),
                    ]);

                    $remaining_balance -= $previous_balance_pay;
                    $total_allocated += $previous_balance_pay;

                    \Log::info('تم تسديد الرصيد السابق للعميل. المتبقي للفواتير: '.$remaining_balance);
                }
            }
        } elseif ($type === 'supplier' && $account->supplier_id) {
            // حساب الرصيد السابق للمورد
            $balance_info = $this->calculateSupplierPreviousBalance($account->supplier_id, $available_balance);

            \Log::info('تحليل رصيد المورد:', [
                'رصيد_الحساب_الأصلي' => $balance_info['account_balance'],
                'إجمالي_المتبقي_من_الفواتير' => $balance_info['total_invoices_due'],
                'الرصيد_السابق_الأصلي' => $balance_info['previous_balance'],
                'الرصيد_السابق_المتبقي' => $balance_info['remaining_previous_balance'],
            ]);

            // تسديد الرصيد السابق للمورد أولاً (إن وجد)
            if ($balance_info['remaining_previous_balance'] > 0) {
                $previous_balance_pay = min($remaining_balance, $balance_info['remaining_previous_balance']);

                if ($previous_balance_pay > 0) {
                    \Log::info('تسديد الرصيد السابق للمورد', [
                        'المبلغ_المدفوع' => $previous_balance_pay,
                        'من_أصل' => $balance_info['remaining_previous_balance'],
                    ]);

                    // تسجيل دفعة للرصيد السابق للمورد
                    PaymentsProcess::create([
                        'supplier_id' => $account->supplier_id,
                        'purchases_id' => null, // لا يوجد فاتورة مرتبطة
                        'amount' => $previous_balance_pay,
                        'type' => 'supplier_previous_balance_payment',
                        'payment_date' => $entry->date,
                        'payment_status' => 1,
                        'payment_method' => 1,
                        'reference_number' => $entry->id,
                        'notes' => 'تسديد رصيد سابق للمورد من قيد اليومية #'.$entry->id,
                        'employee_id' => auth()->id(),
                    ]);

                    $remaining_balance -= $previous_balance_pay;
                    $total_allocated += $previous_balance_pay;

                    \Log::info('تم تسديد الرصيد السابق للمورد. المتبقي للفواتير: '.$remaining_balance);
                }
            }
        }

        // إذا لم يتبقى مبلغ، لا داعي للاستمرار
        if ($remaining_balance <= 0) {
            \Log::info('تم تسديد الرصيد السابق بالكامل. لا يوجد مبلغ متبقي للفواتير.');
            \Log::info('انتهى توزيع المدفوعات.');

            return;
        }

        // الخطوة الثانية: تسديد الفواتير (الأقدم أولاً)
        \Log::info('عدد الفواتير غير المسددة: '.$invoices->count());

        foreach ($invoices as $invoice) {
            if ($remaining_balance <= 0) {
                break;
            }

            // حساب المبلغ المتبقي للفاتورة
            $invoice_remaining = $invoice->calculateRemainingAmount();

            if ($invoice_remaining <= 0) {
                continue;
            }

            // تحديد المبلغ الذي سيتم دفعه لهذه الفاتورة
            $payment_amount = min($remaining_balance, $invoice_remaining);

            // فاتورةٌ تمنع السداد الجزئي لا يُوزَّع عليها جزء — انظر `PartialPaymentRule`
            if (PartialPaymentRule::blocks($invoice, $payment_amount, $invoice_remaining)) {
                continue;
            }

            // طباعة معلومات الفاتورة التي تتم معالجتها
            \Log::info('معالجة الفاتورة #'.($type === 'client' ? ($invoice->invoice_number ?? $invoice->id) : $invoice->code), [
                'المبلغ_المتبقي_للفاتورة' => $invoice_remaining,
                'المبلغ_المدفوع' => $payment_amount,
            ]);

            // إنشاء سجل الدفع
            $payment = new PaymentsProcess;
            $payment->payment_date = $entry->date;
            $payment->amount = $payment_amount;
            $payment->payment_status = 1; // مدفوع
            $payment->payment_method = 1; // نقدًا (يمكن تعديله حسب الحاجة)
            $payment->notes = 'دفع تلقائي من قيد اليومية #'.$entry->id;
            $payment->employee_id = auth()->id();
            $payment->reference_number = $entry->id; // ربط الدفع بالقيد عبر reference_number

            if ($type === 'client') {
                $payment->client_id = $account->client_id;
                $payment->invoice_id = $invoice->id;
                $payment->type = 'client payments';
            } else {
                $payment->supplier_id = $account->supplier_id;
                $payment->purchases_id = $invoice->id;
                $payment->type = 'supplier payments';
            }

            $payment->save();

            // تحديث حالة الفاتورة
            if ($type === 'client') {
                $invoice->updatePaymentStatus();
            } else {
                $this->updateSupplierInvoicePaymentStatus($invoice);

                // طباعة حالة الفاتورة بعد التحديث
                \Log::info('فاتورة الشراء #'.$invoice->code.' تم تحديثها. الحالة الجديدة: '.$invoice->payment_status);
            }

            // جمع معلومات الفواتير المدفوعة
            $paid_invoices[] = $type === 'client' ? ($invoice->invoice_number ?? $invoice->id) : $invoice->code;
            $total_allocated += $payment_amount;

            // إعداد روابط الفواتير للقيد
            $invoice_links[] = [
                'entry_id' => $entry->id,
                'invoice_id' => $invoice->id,
                'invoice_type' => $type === 'client' ? InvoiceType::SALE->value : InvoiceType::PURCHASE->value,
                'allocation_amount' => $payment_amount,
                'created_at' => time(),
            ];

            // تقليل الرصيد المتبقي
            $remaining_balance -= $payment_amount;
        }

        // طباعة معلومات الملخص
        \Log::info('ملخص التوزيع للقيد #'.$entry->id, [
            'إجمالي_المخصص' => $total_allocated,
            'الرصيد_المتبقي' => $remaining_balance,
            'عدد_الفواتير_المسددة' => count($paid_invoices),
        ]);

        // ربط القيد بالفواتير المدفوعة باستخدام جدول journal_entry_invoices
        if (! empty($invoice_links)) {
            foreach ($invoice_links as $link) {
                JournalEntryInvoice::create($link);
            }

            // تحديث القيد ليكون من نوع متعدد الربط
            $entry->linking_type = 'multiple';
            $entry->batch_reference = implode(', ', $paid_invoices);
            $entry->save();

            \Log::info('تم ربط القيد #'.$entry->id.' بـ '.count($invoice_links).' فاتورة');
        }

        \Log::info('========== انتهى توزيع المدفوعات ==========');
    }

    /**
     * حساب المبلغ المتبقي لفاتورة الشراء
     *
     * @return float
     */
    private function calculateSupplierInvoiceRemainingAmount(PurchaseInvoice $invoice)
    {
        $totalPaid = $invoice->payments->sum('amount');

        return $invoice->grand_total - $totalPaid;
    }

    /**
     * تحديث حالة دفع فاتورة الشراء
     *
     * @return void
     */
    private function updateSupplierInvoicePaymentStatus(PurchaseInvoice $invoice)
    {
        // طباعة معلومات التصحيح قبل التحديث
        \Log::info('Updating payment status for purchase invoice #'.$invoice->code);
        \Log::info('Current payment status: '.$invoice->payment_status);
        \Log::info('Is paid: '.($invoice->is_paid ? 'true' : 'false'));

        $invoice->updatePaymentStatus();

        // طباعة معلومات التصحيح بعد التحديث
        \Log::info('Updated payment status for purchase invoice #'.$invoice->code);
        \Log::info('New payment status: '.$invoice->payment_status);
        \Log::info('Is paid: '.($invoice->is_paid ? 'true' : 'false'));
    }

    /**
     * حساب الرصيد السابق للعميل
     * Generated by Copilot
     *
     * الرصيد السابق = رصيد الحساب (balance) - إجمالي الفواتير غير المسددة أو المسددة جزئياً
     * هذا يمثل الديون القديمة التي ليس لها فواتير في النظام
     *
     * @param  int  $client_id  معرف العميل
     * @param  float  $received_amount  المبلغ المستلم (الذي تم خصمه من الحساب)
     * @return array معلومات الرصيد السابق
     */
    private function calculateClientPreviousBalance($client_id, $received_amount = 0)
    {
        // جلب حساب العميل
        $client_account = Account::ledger()->where('client_id', $client_id)->first();

        if (! $client_account) {
            return [
                'account_balance' => 0,
                'total_invoices_due' => 0,
                'previous_balance' => 0,
                'remaining_previous_balance' => 0,
            ];
        }

        // رصيد الحساب الأصلي (قبل خصم المبلغ المستلم)
        // نضيف المبلغ المستلم لأن الرصيد تم تحديثه قبل استدعاء هذه الدالة
        $account_balance = abs($client_account->balance) + $received_amount;

        // حساب إجمالي المتبقي من الفواتير غير المسددة أو المسددة جزئياً
        $unpaid_invoices = $this->getUnpaidClientInvoices($client_id);
        $total_invoices_due = 0;

        foreach ($unpaid_invoices as $invoice) {
            // المتبقّي بعد النقد **و«مرتجع»** — ومنه ما أرجعه إشعارٌ دائن
            $invoice_due = $invoice->calculateRemainingAmount();
            if ($invoice_due > 0) {
                $total_invoices_due += $invoice_due;
            }
        }

        // الرصيد السابق = رصيد الحساب الأصلي - إجمالي المتبقي من الفواتير
        $previous_balance = max(0, $account_balance - $total_invoices_due);

        // حساب المدفوعات السابقة من الرصيد السابق
        $previous_balance_paid = PaymentsProcess::where('client_id', $client_id)
            ->whereIn('type', ['previous_balance_payment', 'opening_balance_payment'])
            ->sum('amount');

        // الرصيد السابق المتبقي (غير المسدد)
        $remaining_previous_balance = max(0, $previous_balance - $previous_balance_paid);

        return [
            'account_balance' => $account_balance,
            'total_invoices_due' => $total_invoices_due,
            'previous_balance' => $previous_balance,
            'remaining_previous_balance' => $remaining_previous_balance,
        ];
    }

    /**
     * حساب الرصيد السابق للمورد
     * Generated by Copilot
     *
     * الرصيد السابق = رصيد الحساب (balance) - إجمالي فواتير الشراء غير المسددة أو المسددة جزئياً
     * هذا يمثل الديون القديمة التي ليس لها فواتير في النظام
     *
     * @param  int  $supplier_id  معرف المورد
     * @param  float  $paid_amount  المبلغ المدفوع (الذي تم خصمه من الحساب)
     * @return array معلومات الرصيد السابق
     */
    private function calculateSupplierPreviousBalance($supplier_id, $paid_amount = 0)
    {
        // جلب حساب المورد
        $supplier_account = Account::ledger()->where('supplier_id', $supplier_id)->first();

        if (! $supplier_account) {
            return [
                'account_balance' => 0,
                'total_invoices_due' => 0,
                'previous_balance' => 0,
                'remaining_previous_balance' => 0,
            ];
        }

        // رصيد الحساب الأصلي (قبل خصم المبلغ المدفوع)
        // نضيف المبلغ المدفوع لأن الرصيد تم تحديثه قبل استدعاء هذه الدالة
        $account_balance = abs($supplier_account->balance) + $paid_amount;

        // حساب إجمالي المتبقي من فواتير الشراء غير المسددة أو المسددة جزئياً
        $unpaid_invoices = $this->getUnpaidSupplierInvoices($supplier_id);
        $total_invoices_due = 0;

        foreach ($unpaid_invoices as $invoice) {
            $invoice_paid = $invoice->payments->sum('amount');
            $invoice_due = $invoice->grand_total - $invoice_paid;
            if ($invoice_due > 0) {
                $total_invoices_due += $invoice_due;
            }
        }

        // الرصيد السابق = رصيد الحساب الأصلي - إجمالي المتبقي من الفواتير
        $previous_balance = max(0, $account_balance - $total_invoices_due);

        // حساب المدفوعات السابقة من الرصيد السابق للمورد
        $previous_balance_paid = PaymentsProcess::where('supplier_id', $supplier_id)
            ->whereIn('type', ['supplier_previous_balance_payment', 'supplier_opening_balance_payment'])
            ->sum('amount');

        // الرصيد السابق المتبقي (غير المسدد)
        $remaining_previous_balance = max(0, $previous_balance - $previous_balance_paid);

        return [
            'account_balance' => $account_balance,
            'total_invoices_due' => $total_invoices_due,
            'previous_balance' => $previous_balance,
            'remaining_previous_balance' => $remaining_previous_balance,
        ];
    }

    public function destroy($id)
    {
        $entry = JournalEntry::findOrFail($id);

        $this->guardSystemClosingEntry($entry);

        // يُلتقطان قبل الحذف: بعده لا يبقى ما يُسمّى به المحذوف في السجلّ
        $entryId = $entry->id;
        $entryNumber = $entry->reference_number;

        /*
            الحرس خارج `try` عمداً: `catch (\Exception)` أدناه يبتلع كل شيء
            ويعرضه «حدث خطأ أثناء حذف القيد»، و`ValidationException` تنحدر من
            `Exception` — فلو نُودي داخله لضاع سببُ المنع وظهر خطأ عام.

            وحذف قيدٍ في فترة مقفلة يغيّر أرصدةً أُقفلت عمداً.
        */
        $this->guardPeriod($entry->date);

        try {
            DB::beginTransaction();

            // فحص صلاحية الحذف
            $user = auth()->user();
            $this->authorizeCreatorScope($entry->created_by_employee);

            // التحقق من أن القيد غير مرتبط بفاتورة مبيعات أو شراء
            if ($entry->invoice_id || $entry->purchase_invoice_id) {
                DB::rollback();

                return back()->with('error', 'لا يمكن حذف القيد المرتبط بفاتورة. القيد مرتبط بفاتورة '.
                    ($entry->invoice_id ? 'مبيعات رقم #'.$entry->invoice_id : 'شراء رقم #'.$entry->purchase_invoice_id));
            }

            // التحقق من وجود روابط في جدول journal_entry_invoices
            $hasLinkedInvoices = JournalEntryInvoice::where('entry_id', $entry->id)->exists();
            if ($hasLinkedInvoices) {
                DB::rollback();

                return back()->with('error', 'لا يمكن حذف القيد المرتبط بفواتير. يرجى إلغاء ربط الفواتير أولاً.');
            }

            // 1. الحصول على جميع الفواتير المرتبطة بهذا القيد قبل حذف أي شيء (للقيود غير المرتبطة مباشرة)
            $linkedInvoices = JournalEntryInvoice::where('entry_id', $entry->id)->get();

            // 2. حذف الروابط من جدول journal_entry_invoices أولاً
            JournalEntryInvoice::where('entry_id', $entry->id)->delete();

            // 3. حذف المدفوعات المرتبطة بهذا القيد
            /*
                دفعاتُ «الرصيد السابق» التي سجّلها هذا القيد (`allocatePaymentsToInvoices`)
                وحدها. كان الشرطُ `reference_number = معرّف القيد` فقط، والحقلُ نفسُه
                يحمل معرّفَ سند القبض في توزيعه وأرقاماً يكتبها المستخدم في دفعات
                المبيعات — فحذفُ القيد #5000 يمسح توزيعَ سند القبض #5000 فتعود
                فواتيرُه غيرَ مسدّدة.
            */
            PaymentsProcess::where('reference_number', (string) $entry->id)
                ->whereNull('receipt_id')
                ->whereIn('type', ['previous_balance_payment', 'supplier_previous_balance_payment'])
                ->whereIn('notes', [
                    'تسديد رصيد سابق من قيد اليومية #'.$entry->id,
                    'تسديد رصيد سابق للمورد من قيد اليومية #'.$entry->id,
                ])
                ->delete();

            // 4. تحديث حالة الفواتير بعد حذف الدفعات والروابط
            foreach ($linkedInvoices as $link) {
                if ($link->invoice_type === InvoiceType::SALE->value) {
                    // بالنسبة لفواتير البيع
                    $invoice = Invoice::find($link->invoice_id);
                    if ($invoice) {
                        $invoice->updatePaymentStatus(); // إعادة حساب حالة الدفع
                    }
                } else {
                    // بالنسبة لفواتير الشراء
                    $invoice = PurchaseInvoice::find($link->invoice_id);
                    if ($invoice) {
                        $invoice->updatePaymentStatus(); // إعادة حساب حالة الدفع
                    }
                }
            }

            // 5. Get all account IDs that will be affected by deleting this journal entry
            $accountIds = $entry->details->pluck('account_id')->unique()->toArray();

            // 6. Delete the journal entry details
            $entry->details()->delete();

            // 7. Delete the journal entry itself
            $entry->delete();

            // 8. Update balances for all affected accounts
            foreach ($accountIds as $accountId) {
                Account::updateParentBalances($accountId, true);
            }

            OperationLog::record('deleted', 'Account.JournalEntry', $entryId,
                'حذف القيد المحاسبي رقم '.$entryNumber
                .' وأُرجعت الفواتير المرتبطة إلى حالتها السابقة');

            DB::commit();

            return redirect()->route('journal.index')->with('success', 'تم حذف القيد المحاسبي بنجاح وإرجاع الفواتير إلى حالتها السابقة');
        } catch (\Exception $e) {
            // يبلغ جدول «سجلّ الأخطاء»، لا ملفّ اللوق وحده
            OperationLog::failure($e, 'Account.JournalEntry', 'destroy');

            DB::rollback();
            \Log::error('Error deleting journal entry: '.$e->getMessage());
            \Log::error($e->getTraceAsString());

            return back()->with('error', 'حدث خطأ أثناء حذف القيد: '.$e->getMessage());
        }
    }

    public function record_modifications(Request $request)
    {
        // نجلب تفاصيل القيود مع القيود المعلقة
        $query = JournalEntryDetail::with(['account', 'journalEntry.client', 'journalEntry.costCenter', 'journalEntry.createdByEmployee'])
            ->whereHas('journalEntry')
            ->orWhereNull('journal_entry_id')
            ->latest();

        // تطبيق الفلاتر
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('description')) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', '%'.$request->description.'%')->orWhereHas('journalEntry', function ($q) use ($request) {
                    $q->where('description', 'like', '%'.$request->description.'%');
                });
            });
        }

        if ($request->filled('from_date')) {
            $query->whereHas('journalEntry', function ($q) use ($request) {
                $q->whereDate('date', '>=', $request->from_date);
            });
        }

        if ($request->filled('to_date')) {
            $query->whereHas('journalEntry', function ($q) use ($request) {
                $q->whereDate('date', '<=', $request->to_date);
            });
        }

        if ($request->filled('employee_id')) {
            $query->whereHas('journalEntry', function ($q) use ($request) {
                $q->where('created_by_employee', $request->employee_id);
            });
        }

        $entries = $query->paginate(20);
        $accounts = ChartOfAccount::all();
        $employees = Employee::all();

        return view('account::journal.record_modifications', compact('entries', 'accounts', 'employees'));
    }
}
