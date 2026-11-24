<?php

namespace Modules\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryDetail;
use App\Support\AccountingPeriodFilter;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Account\Http\Controllers\Concerns\AccountingOperations;

class AccountStatementController extends Controller
{
    // `accountingRange` — الفترة الجارية مدًى افتراضياً حين لا يُطلب غيرها
    use AccountingOperations;

    /**
     * صلاحيات كشف الحساب — الكتالوج الجديد account.statement.*. لم يكن
     * لهذا المتحكّم أي حماية صلاحياتٍ إطلاقاً من قبل.
     */
    public function __construct()
    {
        $this->middleware('permission.role:account.statement.view', ['only' => ['statement', 'showJournalEntryDetails']]);
        $this->middleware('permission.role:account.statement.export', ['only' => ['exportPdf']]);
    }

    /**
     * الكشفُ كلُّه من موضعٍ واحد: افتتاحيٌّ وحركةٌ ونهائيّ.
     *
     * **ما كان**
     *
     * الرصيدُ الجاري يبدأ من **صفر** مهما كان الفلتر، والنهائيُّ آخرُ قيمةٍ
     * فيه. فكشفُ يومَين يقول إنّ رصيد الحساب هو حركةُ اليومَين وحدها،
     * كأنّ ما قبلهما لم يكن. وإن خلا الفلترُ من حركةٍ سقط الرصيدُ إلى
     * `accounts.balance` — رصيدِ **اليوم** لا رصيدِ آخر المدّة، فيقرأ
     * المستعرضُ رقماً لا يخصّ الفترة التي طلبها.
     *
     * وأسوأُ منه أنّ `exportPdf` كان يأخذ `last()` والشاشةُ `first()`،
     * والقائمةُ مرتّبةٌ من الأحدث إلى الأقدم — فالـPDF يطبع رصيدَ **أوّل**
     * عمليةٍ في المدّة على أنّه النهائي.
     *
     * **ما صار**
     *
     *   • الافتتاحيُّ مجموعُ كلّ ما سبق «من تاريخ» — استعلامٌ واحدٌ مُجمَّع.
     *   • الجاري يبدأ منه، فسطورُ الكشف تقرأ أرصدةً حقيقية لا فروقاً.
     *   • النهائيُّ = الافتتاحيُّ + حركةُ المدّة، محسوباً من المجاميع لا
     *     من ترتيب القائمة — فلا يفترق تقريرٌ عن شاشة.
     *
     * والترتيبُ بتاريخ القيد لا بـ`created_at`: قيدٌ يُدخَل اليوم بتاريخ
     * أمس كان يقع في ذيل الكشف، فيسير الرصيدُ الجاري على غير تسلسله.
     *
     * @return array{
     *     opening: float, operations: Collection,
     *     final: float, debit: float, credit: float
     * }
     */
    private function collectStatement(Account $account, array $period): array
    {
        $accountIds = $this->getAccountAndDescendantIds($account);

        // حسابٌ مدينٌ يزيد بالمدين، ودائنٌ يزيد بالدائن
        $sign = ($account->balance_type ?? 'debit') === 'debit' ? 1 : -1;

        /*
            ═══ الرصيد الافتتاحي: كلُّ ما قبل الفترة ═══

            «ما قبل» يُقاس ببداية الفترة المختارة: الفترةُ علاقةٌ في القيد،
            لكنّ ما سبقها لا يُعرف بمعرّفها — إنما بتاريخِ ابتدائها. فالحدُّ
            هنا تاريخيٌّ عن قصد، مصدرُه سجلُّ الفترة نفسِه لا مدخلُ مستخدم.
        */
        $opening = 0.0;
        $fromDate = $period['from'] ?? null;

        if ($fromDate) {
            $before = JournalEntryDetail::whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', function ($q) use ($fromDate) {
                    $q->whereDate('date', '<', Carbon::parse($fromDate));
                })
                ->selectRaw('COALESCE(SUM(debit), 0) AS d, COALESCE(SUM(credit), 0) AS c')
                ->first();

            $opening = $sign * ((float) $before->d - (float) $before->c);
        }

        // ═══ حركةُ المدّة ═══
        $query = JournalEntryDetail::query()
            ->whereIn('journal_entry_details.account_id', $accountIds)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_details.journal_entry_id')
            ->select('journal_entry_details.*')
            ->with('journalEntry:id,date,description,reference_number,status');

        /*
            حركةُ الفترة بالعلاقة: `journal_entries.accounting_period_id`
            مختومٌ لحظةَ إنشاء القيد، فلا يُعاد استنتاجُه من التاريخ.
        */
        AccountingPeriodFilter::applyRaw($query, $period, 'journal_entries.accounting_period_id');

        $entries = $query
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entry_details.id')
            ->get();

        $running = $opening;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $rows = [];

        foreach ($entries as $entry) {
            $debit = (float) ($entry->debit ?? 0);
            $credit = (float) ($entry->credit ?? 0);

            $totalDebit += $debit;
            $totalCredit += $credit;
            $running += $sign * ($debit - $credit);

            $rows[] = [
                'id' => $entry->id,
                'date' => $entry->journalEntry->date,
                'reference_number' => $entry->journalEntry->reference_number,
                'description' => $entry->description ?? $entry->journalEntry->description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
                'journal_entry_id' => $entry->journal_entry_id,
            ];
        }

        return [
            'opening' => round($opening, 2),
            // من الأحدث إلى الأقدم للعرض
            'operations' => collect(array_reverse($rows)),
            'final' => round($opening + $sign * ($totalDebit - $totalCredit), 2),
            'debit' => round($totalDebit, 2),
            'credit' => round($totalCredit, 2),
        ];
    }

    /**
     * عرض كشف حساب لحساب معين
     * Display account statement for a specific account
     */
    public function statement(Request $request, $accountId)
    {
        try {
            // جلب الحساب
            $account = Account::findOrFail($accountId);

            // جلب تاريخ البداية والنهاية من الطلب
            /*
                **الكشفُ يُقرأ على فترةٍ بعلاقتها لا بمدىً على التاريخ.**

                القيدُ مختومٌ بفترته لحظةَ إنشائه، فالسؤالُ عن كشفِ فترةٍ صار
                مقارنةَ عمودٍ بمعرّف. وكشفٌ يبدأ من أوّل قيدٍ في تاريخ الحساب
                ليس كشفَ فترةٍ بل سِجِلَّ عمر.
            */
            $period = AccountingPeriodFilter::resolve($request);

            $statement = $this->collectStatement($account, $period);
            $operations = $statement['operations'];

            // دعم عرض كل العمليات عند الطباعة بدون Pagination
            $showAll = (bool) $request->query('all');
            $perPage = $showAll ? max($operations->count(), 1) : 20;
            $currentPage = $showAll ? 1 : (int) $request->query('page', 1);
            $operationsPaginator = new LengthAwarePaginator(
                $operations->forPage($currentPage, $perPage)->values(),
                $operations->count(),
                $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            /*
                الرصيدُ النهائي رصيدُ **آخر المدّة المطلوبة**: الافتتاحيُّ
                زائدَ حركتِها. ولا يُستبدَل برصيد الحساب اليومَ حين تخلو
                المدّةُ من حركة — فذاك رقمٌ لا يخصّ ما طُلب.
            */
            $finalBalance = $statement['final'];
            $openingBalance = $statement['opening'];
            $periodDebit = $statement['debit'];
            $periodCredit = $statement['credit'];

            // معلومات الشركة
            $companyName = config('app.name', 'الشركة');

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'account' => $account,
                    'operations' => $operationsPaginator->items(),
                    'openingBalance' => $openingBalance,
                    'periodDebit' => $periodDebit,
                    'periodCredit' => $periodCredit,
                    'finalBalance' => $finalBalance,
                    'pagination' => [
                        'current_page' => $operationsPaginator->currentPage(),
                        'total' => $operationsPaginator->total(),
                        'per_page' => $operationsPaginator->perPage(),
                        'last_page' => $operationsPaginator->lastPage(),
                    ],
                ]);
            }

            return view('account::accounts_chart.account_statement', compact(
                'account',
                'operationsPaginator',
                'openingBalance',
                'periodDebit',
                'periodCredit',
                'finalBalance',
                'companyName',
                'period'
            ));

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.AccountStatement', 'statement');

            return redirect()->back()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * عرض تفاصيل قيد محاسبي
     * Show journal entry details
     */
    public function showJournalEntryDetails($journalEntryId)
    {
        try {
            $journalEntry = JournalEntry::with('details.account')
                ->findOrFail($journalEntryId);

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $journalEntry->id,
                        'date' => $journalEntry->date,
                        'reference_number' => $journalEntry->reference_number,
                        'description' => $journalEntry->description,
                        'status' => $journalEntry->status,
                        'details' => $journalEntry->details->map(function ($detail) {
                            return [
                                'account_id' => $detail->account_id,
                                'account_name' => $detail->account->name,
                                'account_code' => $detail->account->code,
                                'description' => $detail->description,
                                'debit' => $detail->debit,
                                'credit' => $detail->credit,
                            ];
                        }),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'طلب غير صحيح',
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.AccountStatement', 'showJournalEntryDetails');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تصدير كشف الحساب إلى PDF
     * Export account statement to PDF
     */
    public function exportPdf(Request $request, $accountId)
    {
        try {
            $account = Account::findOrFail($accountId);
            $period = AccountingPeriodFilter::resolve($request);

            /*
                المصدرُ نفسُه الذي تقرأ منه الشاشة. وكان للـPDF استعلامُه
                وحسابُه، ويأخذ `last()` من قائمةٍ مرتّبةٍ من الأحدث إلى
                الأقدم — أي رصيدَ أوّل عمليةٍ على أنّه النهائي.
            */
            $statement = $this->collectStatement($account, $period);

            $operations = $statement['operations'];
            $finalBalance = $statement['final'];
            $openingBalance = $statement['opening'];
            $periodDebit = $statement['debit'];
            $periodCredit = $statement['credit'];

            // توليد HTML للطباعة
            $html = view('account::accounts_chart.account_statement_pdf', compact(
                'account',
                'operations',
                'openingBalance',
                'periodDebit',
                'periodCredit',
                'finalBalance',
                'period'
            ))->render();

            // إذا تم طلب HTML فقط
            if ($request->query('format') === 'html') {
                return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
            }

            return response()->json([
                'success' => true,
                'html' => $html,
            ]);

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Account.AccountStatement', 'exportPdf');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إرجاع الحساب الحالي مع جميع الحسابات الفرعية التابعة له.
     */
    private function getAccountAndDescendantIds(Account $account): array
    {
        $allIds = [$account->id];
        $parentIds = [$account->id];

        while (! empty($parentIds)) {
            $childrenIds = Account::whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->toArray();

            if (empty($childrenIds)) {
                break;
            }

            $allIds = array_merge($allIds, $childrenIds);
            $parentIds = $childrenIds;
        }

        return array_values(array_unique($allIds));
    }
}
