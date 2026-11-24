<?php

namespace Modules\Client\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\CategoriesClient;
use App\Models\Client;
use App\Models\Statuses;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Client\Services\CustomerLedgerService;

class CustomerAccountStatementController extends Controller
{
    use \Modules\Client\Http\Controllers\Concerns\FiltersCustomerReports;
    use \Modules\Client\Http\Controllers\Concerns\RespectsAccountingPeriods;

    /**
     * سقفُ العملاء في الكشف الواحد.
     *
     * كشفُ الحساب مستندٌ لكلّ عميل، لا سطرٌ في جدول. وبلا فلترٍ يطلب
     * المستخدمُ ٣٨٥٢ كشفاً في استجابةٍ واحدة — يُبنى منها JSON بعشرات
     * الميغابايت لا تُعرض ولا تُطبع. فيُقتصر على هذا العدد **ويُقال ذلك
     * صراحةً في الشاشة**: قصٌّ صامتٌ يُقرأ «هؤلاء كلُّ عملائك» وهو كذب.
     */
    private const MAX_CLIENTS = 200;

    public function __construct()
    {
        /* صلاحيةُ هذا التقرير وحدَه.

           كانت التركيبةُ الثلاثية `clients_view_all_reports|clients_view_own_reports|client_reports_view`
           مكرَّرةً حرفياً في التسعة، فمن فُتح له تقريرٌ انفتحت له
           التسعةُ جميعاً. واثنتان منها صلاحيتا **نطاق** لا عملية،
           وقد صار النطاقُ محوراً مستقلاً يقرؤه `App\Support\DataScope`. */
        $this->middleware('permission:clients.reports.statement');
    }

    /**
     * عرض صفحة كشف الحساب
     */
    public function customerAccountStatement(Request $request)
    {
        $employees = User::whereIn('role', ['employee', 'manager'])->get();
        $categories = CategoriesClient::all();
        $statuses = Statuses::assignable()->get(); // حالات العميل القابلة للاختيار فقط — نشط/موقوف، بلا مراحل التسجيل ولا حالات قديمة
        /* قائمتان لسؤالين مختلفين، وكانت واحدةً تخدمهما معاً:

           • «مسؤول المبيعات» يطابق `clients.employee_id` — معرّفُ **موظّف**،
             والقائمةُ من الأقسام المسموحة في إعدادات وحدات العملاء.
           • «تمت الإضافة بواسطة» يطابق `clients.user_id` — معرّفُ **مستخدم**.

           ودمجُهما في متغيّرٍ واحد كان يملأ الحقلين بفضاءِ معرّفاتٍ واحد،
           فأحدُهما يفلتر بمعرّفٍ لا يعني في جدوله ما يعنيه في الآخر. */
        $salesEmployees = $this->salesEmployeeOptions();
        $salesManagers = User::whereIn('role', ['employee', 'manager'])->get();
        $customerTargets = $this->customerTargetOptions();
        $registrationStages = $this->registrationStageOptions();

        /* قائمةُ العملاء لم تعد تُحمَّل كاملةً في الصفحة: ٣٨٥٢ عنصرَ
           `<option>` تُثقل كلَّ فتحةٍ للشاشة قبل أن يُطلب تقرير. تبقى منها
           هنا **المختارُ وحده** كي يظهر اسمُه بعد إعادة التحميل، والبقيةُ
           تأتي من `ClientReport.searchClients` عبر Select2. */
        $selectedClient = $request->filled('client_id')
            ? Client::select('id', 'code', 'trade_name', 'first_name', 'last_name', 'phone')->find($request->input('client_id'))
            : null;

        /* النتيجةُ تُبنى مع الصفحة لا بطلبٍ ثانٍ بعدها — كما في تقريرَي
           أعمار الديون: رابطُ الصفحة يحمل ما يُعرض فيه، ويصلح للحفظ
           والإرسال والرجوع بالمتصفّح.

           و`null` حين لا يطابق الفلترُ عميلاً: الشاشةُ تعرض حينها رسالتَها
           بدل جدولٍ فارغ. */
        $statement = $this->customerAccountStatementAjax($request);
        $statementPayload = json_decode($statement->getContent(), true);

        return view('client::customers.CustomerReport.customer_account_statement', [
            'statementData' => ($statementPayload['success'] ?? false) ? $statementPayload['data'] : null,
            'statementMessage' => ($statementPayload['success'] ?? false) ? null : ($statementPayload['message'] ?? null),
            'employees' => $employees,
            'selectedClient' => $selectedClient,
            'categories' => $categories,
            // شجرةُ «تابع لـ» — المُنتقي التنظيمي الوحيد في الشاشة
            'treeNodes' => $this->affiliationTreeNodes(),
            'statuses' => $statuses,
            'registrationStages' => $registrationStages,
            'customerTargets' => $customerTargets,
            'salesEmployees' => $salesEmployees,
            'salesManagers' => $salesManagers,
            // الفترة المحاسبية — تملأ حقلَي التاريخ القائمَين ولا تزيد فلتراً
            'periodContext' => $this->reportPeriodContext($request, 'date_from', 'date_to'),
        ]);
    }

    /**
     * بياناتُ كشف الحساب.
     *
     * ══════════ لماذا كان الكشفُ يخرج فارغاً أو مخالفاً للرصيد ══════════
     *
     * سببانِ اثنان، وكلاهما مُصلَحٌ هنا:
     *
     * **١. لا رصيدَ افتتاحياً.** كان يجمع حركاتِ المدى وحدَها بادئاً من
     * صفر. والنطاقُ الافتراضي هو السنةُ المالية الجارية (٢٠٢٦)، بينما
     * ١٧٤٨ رصيداً افتتاحياً في هذه المنشأة مؤرَّخةٌ في ديسمبر ٢٠٢٥ — فتسقط
     * كلُّها، ويُغلق الكشفُ على رقمٍ لا يوافق رصيدَ العميل بحال. صار يبدأ
     * من `balance_at(from − يوم)` كما يبدأ أيُّ كشفٍ محاسبي.
     *
     * **٢. الخزينةُ مكانَ العميل.** كان يقرأ `Expense`/`Revenue` بشرط
     * `treasury_id = رقمُ حساب العميل` — و`treasury_id` خزينةٌ لا عميل،
     * فما كان يعود منهما إلّا الصدفة. وكلُّ ما يمسّ حساب العميل يمرّ في
     * `journal_entry_details` أصلاً — الفواتيرُ والتحصيلاتُ والمرتجعاتُ
     * والإشعاراتُ والقيودُ والأرصدةُ الافتتاحية — فقراءتُه منه وحدَه أشملُ
     * وأدقُّ من ثلاثة مصادر يتداخل بعضُها.
     *
     * والحصيلة: `افتتاحي + مدين − دائن = ختامي`، والختاميُّ بتاريخ قطعٍ =
     * اليوم يساوي `accounts.balance` — وهو نفسُه رقمُ تقريري الأرصدة
     * والأعمار، لأنّ الثلاثةَ تقرأ من `CustomerLedgerService`.
     */
    public function customerAccountStatementAjax(Request $request)
    {
        try {
            $clientsQuery = Client::with([
                'account:id,client_id,balance',
                'branch:id,name',
                'categoriesClient:id,name',
                'status_client:id,name,color',
                'registrationStage:id,name,color',
                'customerTarget:id,name,color',
            ]);

            $this->applyClientFilters($clientsQuery, $request);

            $matchedCount = (clone $clientsQuery)->count();

            if ($matchedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد عملاء تطابق الفلاتر المحددة',
                ], 404);
            }

            // نسخةٌ لا الأصل: `limit` على الأصل كان يقصر مؤشّراتِ «كلّ المطابقين» أدناه على 200 أيضاً
            $clients = (clone $clientsQuery)->orderBy('trade_name')->limit(self::MAX_CLIENTS)->get();

            /* ══════════ نطاقُ التقرير ══════════
               تاريخا الشاشة، وإلّا حدودُ الفترة المحاسبية المختارة، وإلّا
               السنةُ المالية الجارية. */
            $period = $this->reportPeriodContext($request, 'date_from', 'date_to');
            $from = $period['start'];
            $to = $period['end'];

            $accountIds = $clients->pluck('account.id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

            /* ثلاثةُ استعلاماتٍ للتقرير كلِّه مهما بلغ عددُ العملاء —
               لا ثلاثةٌ لكلّ عميل كما كان (١١٥٥٦ استعلاماً بلا فلتر). */
            $ledger = app(CustomerLedgerService::class);
            $openingBalances = $ledger->openingBalances($accountIds, $from);
            $linesByAccount = $ledger->movementLines($accountIds, $from, $to);

            $groupedClients = [];
            $totalOpening = 0.0;
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            $totalClosing = 0.0;
            $totalClientsCount = 0;
            $closingDebt = 0.0;
            $closingDebtClients = 0;
            $closingCredit = 0.0;
            $closingCreditClients = 0;

            foreach ($clients as $client) {
                $account = $client->account;

                if (! $account) {
                    continue;
                }

                $accountId = (int) $account->id;
                $opening = (float) ($openingBalances[$accountId] ?? 0);
                $lines = $linesByAccount->get($accountId, collect());

                $runningBalance = $opening;
                $clientDebit = 0.0;
                $clientCredit = 0.0;
                $operations = [];

                foreach ($lines as $line) {
                    $debit = (float) $line->debit;
                    $credit = (float) $line->credit;

                    $runningBalance += $debit - $credit;
                    $clientDebit += $debit;
                    $clientCredit += $credit;

                    $referenceType = (string) ($line->reference_type ?? '');

                    $operations[] = [
                        'date' => $line->date,
                        'type' => $this->operationLabel($referenceType),
                        'reference_type' => $referenceType,
                        'description' => $line->entry_description ?: ($line->line_description ?? ''),
                        'reference' => $line->reference_number ?: ($line->invoice_code ?? ''),
                        'debit' => round($debit, 2),
                        'credit' => round($credit, 2),
                        // يُبقيان لتوافق العرض القديم: المبلغُ واتجاهُه
                        'amount' => round($debit > 0 ? $debit : $credit, 2),
                        'is_deposit' => $debit > 0,
                        'balance' => round($runningBalance, 2),
                    ];
                }

                $closing = $runningBalance;

                /* «إخفاءُ الأرصدة الصفرية» يقيس الرصيدَ **الختامي** لا
                   مجموعَ الحركات: عميلٌ اشترى ودفع كلَّ ما اشترى رصيدُه صفرٌ
                   وحركتُه ليست صفراً، وهو المقصودُ بالإخفاء. */
                if ($request->boolean('hide_zero_balance') && abs($closing) < 0.005 && abs($opening) < 0.005) {
                    continue;
                }

                if ($request->boolean('hide_empty') && $operations === [] && abs($opening) < 0.005) {
                    continue;
                }

                $groupedClients[$client->display_name.' #'.$client->id] = [
                    'client' => [
                        'id' => $client->id,
                        'code' => $client->code,
                        'trade_name' => $client->display_name,
                        'phone' => $client->phone,
                        'email' => $client->email,
                        'address' => $client->address,
                        'branch' => $client->branch->name ?? '—',
                        'status' => $client->status_client->name ?? '—',
                        'status_color' => $client->status_client->color ?? 'secondary',
                        // «الوضع» = مرحلة التسجيل، حقلٌ مستقلٌّ عن الحالة
                        'stage' => $client->registrationStage->name ?? '—',
                        'stage_color' => $client->registrationStage->color ?? '#6c757d',
                        'affiliation' => $client->resolveTreeNode()?->name ?? '—',
                        'affiliation_type' => $client->resolveTreeNode()?->displayTypeLabel() ?? '',
                        'target' => $client->customerTarget->name ?? 'بلا هدف',
                        'target_color' => $client->customerTarget->color ?? '#6c757d',
                        'category' => $client->categoriesClient->name ?? '—',
                        'opening_balance' => number_format($opening, 2),
                    ],
                    'operations' => $operations,
                    'totals' => [
                        'opening_balance' => number_format($opening, 2),
                        'debit' => number_format($clientDebit, 2),
                        'credit' => number_format($clientCredit, 2),
                        // الاسمانِ القديمان يبقيان — العرضُ يقرأهما
                        'deposits' => number_format($clientDebit, 2),
                        'withdraws' => number_format($clientCredit, 2),
                        'final_balance' => number_format($closing, 2),
                        'balance_type' => $closing >= 0 ? 'مدين' : 'دائن',
                    ],
                ];

                $totalOpening += $opening;
                $totalDebit += $clientDebit;
                $totalCredit += $clientCredit;
                $totalClosing += $closing;
                $totalClientsCount++;

                /* الختاميُّ مفصَّلاً: مديونيةُ المدينين والأرصدةُ الدائنة كلٌّ
                   وحدَه — الصافي وحدَه يخلط ما لنا بما علينا. */
                if ($closing > 0.005) {
                    $closingDebt += $closing;
                    $closingDebtClients++;
                } elseif ($closing < -0.005) {
                    $closingCredit += abs($closing);
                    $closingCreditClients++;
                }
            }

            $truncated = $matchedCount > self::MAX_CLIENTS;
            $shownClients = $totalClientsCount;

            /* ══════════ المؤشّراتُ لكلّ العملاء المطابقين لا للمعروضين ══════════

               الكشوفُ التفصيلية مقصورةٌ على أوّل MAX_CLIENTS عميلاً، وكانت
               المؤشّراتُ تُجمع من تلك الكشوف وحدَها — فبلا فلترٍ يقول الكشفُ
               «المديونية 17,988.46 · 38 مديناً» والمديونيةُ الحقيقيةُ 277,261.23
               على 1,284 مديناً (أوّلُ 200 عميلٍ أبجدياً فقط). فتُحسب هنا من
               الأستاذ مباشرةً لكلّ المطابقين، بنفس قاعدتَي الإخفاء أعلاه. */
            $allAccountIds = [];
            foreach (array_chunk((clone $clientsQuery)->setEagerLoads([])->pluck('clients.id')->all(), 1000) as $chunk) {
                array_push($allAccountIds, ...\App\Models\Account::ledger()->whereIn('client_id', $chunk)->pluck('id')->map(fn ($id) => (int) $id)->all());
            }

            $allOpening = $ledger->openingBalances($allAccountIds, $from);
            $allMoves = $ledger->movementTotals($allAccountIds, $from, $to);

            $totalOpening = $totalDebit = $totalCredit = $totalClosing = 0.0;
            $closingDebt = $closingCredit = 0.0;
            $totalClientsCount = $closingDebtClients = $closingCreditClients = 0;

            foreach (array_unique($allAccountIds) as $accountId) {
                $opening = (float) ($allOpening[$accountId] ?? 0);
                $moves = $allMoves[$accountId] ?? ['debit' => 0.0, 'credit' => 0.0, 'count' => 0];
                $closing = $opening + (float) $moves['debit'] - (float) $moves['credit'];

                if ($request->boolean('hide_zero_balance') && abs($closing) < 0.005 && abs($opening) < 0.005) {
                    continue;
                }

                if ($request->boolean('hide_empty') && (int) $moves['count'] === 0 && abs($opening) < 0.005) {
                    continue;
                }

                $totalOpening += $opening;
                $totalDebit += (float) $moves['debit'];
                $totalCredit += (float) $moves['credit'];
                $totalClosing += $closing;
                $totalClientsCount++;

                if ($closing > 0.005) {
                    $closingDebt += $closing;
                    $closingDebtClients++;
                } elseif ($closing < -0.005) {
                    $closingCredit += abs($closing);
                    $closingCreditClients++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'grouped_clients' => $groupedClients,
                    'totals' => [
                        'total_clients' => $totalClientsCount,
                        'matched_clients' => $matchedCount,
                        // عددُ الكشوف التفصيلية المعروضة تحت المؤشّرات
                        'shown_clients' => $shownClients,
                        'opening_balance' => number_format($totalOpening, 2),
                        'total_debit' => number_format($totalDebit, 2),
                        'total_credit' => number_format($totalCredit, 2),
                        // الاسمانِ القديمان يبقيان — العرضُ يقرأهما
                        'total_deposits' => number_format($totalDebit, 2),
                        'total_withdraws' => number_format($totalCredit, 2),
                        'final_balance' => number_format($totalClosing, 2),
                        'closing_debt' => number_format($closingDebt, 2),
                        'closing_debt_clients' => $closingDebtClients,
                        'closing_credit' => number_format($closingCredit, 2),
                        'closing_credit_clients' => $closingCreditClients,
                    ],
                    'period' => [
                        'from' => $from,
                        'to' => $to,
                        'label' => $period['label'] ?? '',
                        'status' => $period['status'] ?? 'open',
                    ],
                    'truncated' => $truncated,
                    'notice' => $truncated
                        ? 'المؤشّراتُ أعلاه لكلّ العملاء المطابقين ('.number_format($matchedCount).' عميلاً). والكشوفُ التفصيلية أدناه لأوّل '.self::MAX_CLIENTS.' منهم فقط — ضيّق الفلاتر (عميل أو فرع أو حالة) لعرض كشف عميلٍ بعينه.'
                        : null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('خطأ في تقرير كشف الحساب: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب البيانات: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * الاسمُ العربي لنوع المرجع.
     *
     * الأنواعُ الواقعةُ فعلاً على حسابات العملاء في هذه المنشأة هي:
     * `SI` و`REC` و`OPB` و`JV` و`CN` و`SR` و`PCAN` و`EXP` و`ACC` —
     * والبقيةُ مبقاةٌ لأنّ الجدولَ نفسَه مشتركٌ مع بقيّة الوحدات.
     */
    private function operationLabel(string $referenceType): string
    {
        $typeMapping = [
            'OPB' => 'رصيد افتتاحي',
            'JV' => 'قيد يومية عام',
            'PV' => 'سند صرف',
            'RV' => 'سند قبض',
            'PI' => 'فاتورة مشتريات',
            'PR' => 'مردودات مشتريات',
            'SI' => 'فاتورة مبيعات',
            'SR' => 'مردودات مبيعات',
            'DN' => 'إشعار مدين',
            'CN' => 'إشعار دائن',
            'AW' => 'مخزن معتمد',
            'EXP' => 'مصروفات',
            'ADV' => 'سلفة/عهدة',
            'STK' => 'تسوية مخزون',
            'TRF' => 'تحويل داخلي',
            'PAY' => 'مدفوعات موردين',
            'REC' => 'تحصيلات عملاء',
            'SAL' => 'مسير رواتب',
            'DEP' => 'إهلاك أصول',
            'ACC' => 'تسوية قيود محاسبية',
            'PCAN' => 'إلغاء دفعة',
        ];

        return $typeMapping[$referenceType] ?? ($referenceType ?: 'عملية عامة');
    }
}
