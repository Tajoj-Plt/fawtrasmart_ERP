<?php

namespace Modules\Sales\Http\Controllers\Report;

use App\Constants\SystemAccountIds;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Statuses;
use App\Models\User;
use App\Support\AffiliationTree;
use App\Support\ClosedPeriodRanges;
use App\Support\DataScope;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Trait للدوال المشتركة في تقارير المبيعات
 */
trait SalesReportTrait
{
    /**
     * طرفُ العميل في قيدٍ محمَّلٍ بتفاصيله وحساباتها — `[مدين، دائن]`.
     *
     * ══════════ لماذا طرفُ العميل لا حسابُ المبيعات ══════════
     *
     * لوحةُ المبيعات (`SalesLedger::periodTotals`) تقرأ حسابَ **العميل**:
     * الفاتورةُ = مدينُه (إجماليُّها شاملَ الضريبة)، والمرتجعُ والإشعارُ =
     * دائنُه وحده. وكانت التقاريرُ تقرأ حسابَ المبيعات (44) ومردوداتِه (45)،
     * فتُخرج رقماً آخر لنفس المدى: منذ 2026-09-11 يفصل قيدُ الفاتورة الضريبةَ
     * في حسابها والشحنَ والخصمَ في حسابيهما، بينما الفواتيرُ الأقدم رحّلت
     * الإجماليَّ كلَّه إلى 44 — فـ«قبل الضريبة» لم يكن يوماً أساساً واحداً.
     * والمستخدمُ يطابق كلَّ تقريرٍ باللوحة («بيانات الداش بورد صحيحة جداً»)،
     * فأيُّ أساسٍ ثانٍ يُقرأ خطأً. وهو ما تقرؤه شاشةُ العملاء ودفترُ العميل.
     *
     * محفظةُ العميل تحمل `client_id` نفسَه وهي التزامٌ لا ذمّة — تُستبعد
     * كما في `SalesLedger::excludeWalletAccounts()`.
     *
     * @return array{0: float, 1: float}
     */
    protected function clientLeg($entry): array
    {
        $debit = 0.0;
        $credit = 0.0;

        foreach ($entry->details as $detail) {
            $account = $detail->account;

            if (! $account || $account->client_id === null) {
                continue;
            }

            if (in_array($account->type_accont ?? null, [Account::WALLET_CLIENT, Account::WALLET_SUPPLIER], true)) {
                continue;
            }

            $debit += (float) $detail->debit;
            $credit += (float) $detail->credit;
        }

        return [$debit, $credit];
    }

    /**
     * حساب المبيعات من التفاصيل (صيغة صحيحة: Credit - Debit)
     * نفس طريقة Dashboard::getTotalSales
     */
    protected function calculateDebit($entries)
    {
        return $entries->sum(function ($entry) {
            $salesDetails = $entry->details
                ->where('account_id', SystemAccountIds::SALES);
            $credit = $salesDetails->sum('credit');
            $debit = $salesDetails->sum('debit');

            return $credit - $debit;
        });
    }

    /**
     * حساب الدفعات المرتبطة (من JournalEntryDetail)
     * نفس طريقة Dashboard مع استثناء التحويلات
     */
    protected function calculatePayments($entries)
    {
        // جلب حسابات الخزينة والبنوك
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        return $entries->sum(function ($entry) use ($treasuryAndBankAccounts) {
            if (empty($treasuryAndBankAccounts)) {
                return 0;
            }

            // جلب التفاصيل من حسابات الخزينة والبنوك فقط
            $payments = $entry->details
                ->whereIn('account_id', $treasuryAndBankAccounts)
                ->where('debit', '>', 0);

            // استثناء التحويلات: تحقق من عدم وجود دائن في نفس القيد لحسابات الخزينة/البنوك
            $hasTransfer = $entry->details
                ->whereIn('account_id', $treasuryAndBankAccounts)
                ->where('credit', '>', 0)
                ->isNotEmpty();

            if ($hasTransfer) {
                return 0; // إذا كان تحويل، لا تعد مدفوعة
            }

            return $payments->sum('debit');
        });
    }

    /**
     * حساب المبالغ غير المدفوعة
     */
    protected function calculateUnpaid($entries)
    {
        $total = $this->calculateDebit($entries);
        $paid = $this->calculatePayments($entries);

        return max($total - $paid, 0);
    }

    /**
     * الحصول على الدفعات لقيد واحد
     */
    protected function getEntryPayments($entry)
    {
        // جلب حسابات الخزينة والبنوك
        $treasuryAndBankAccounts = Account::whereIn('parent_id', [
            SystemAccountIds::CASH_TREASURIES_PARENT,
            SystemAccountIds::BANK_ACCOUNTS_PARENT,
        ])->pluck('id')->toArray();

        if (empty($treasuryAndBankAccounts)) {
            return 0;
        }

        // جلب التفاصيل من حسابات الخزينة والبنوك فقط
        $payments = $entry->details
            ->whereIn('account_id', $treasuryAndBankAccounts)
            ->where('debit', '>', 0);

        // استثناء التحويلات
        $hasTransfer = $entry->details
            ->whereIn('account_id', $treasuryAndBankAccounts)
            ->where('credit', '>', 0)
            ->isNotEmpty();

        if ($hasTransfer) {
            return 0;
        }

        return $payments->sum('debit');
    }

    /**
     * الحصول على غير المدفوع لقيد واحد
     */
    protected function getEntryUnpaid($entry)
    {
        // احسب المبيعات من حساب المبيعات فقط
        $salesDetails = $entry->details
            ->where('account_id', SystemAccountIds::SALES);
        $salesAmount = $salesDetails->sum('credit') - $salesDetails->sum('debit');

        $paid = $this->getEntryPayments($entry);

        return max($salesAmount - $paid, 0);
    }

    /**
     * حساب إجماليات المبيعات
     */
    protected function calculateSalesTotals($entries)
    {
        return [
            'count' => $entries->count(),
            'amount' => $this->calculateDebit($entries),
            'paid' => $this->calculatePayments($entries),
            'unpaid' => $this->calculateUnpaid($entries),
        ];
    }

    /**
     * تحديد نطاق التاريخ بناءً على نوع التاريخ المحدد
     */
    protected function getDateRange($dateType)
    {
        $today = now();

        switch ($dateType) {
            case 'today':
                return [
                    'from' => $today->copy()->startOfDay(),
                    'to' => $today->copy()->endOfDay(),
                ];

            case 'yesterday':
                $yesterday = $today->copy()->subDay();

                return [
                    'from' => $yesterday->copy()->startOfDay(),
                    'to' => $yesterday->copy()->endOfDay(),
                ];

            case 'this_week':
                return [
                    'from' => $today->copy()->startOfWeek(),
                    'to' => $today->copy()->endOfWeek(),
                ];

            case 'last_week':
                return [
                    'from' => $today->copy()->subWeek()->startOfWeek(),
                    'to' => $today->copy()->subWeek()->endOfWeek(),
                ];

            case 'this_month':
                return [
                    'from' => $today->copy()->startOfMonth(),
                    'to' => $today->copy()->endOfMonth(),
                ];

            case 'last_month':
                return [
                    'from' => $today->copy()->subMonth()->startOfMonth(),
                    'to' => $today->copy()->subMonth()->endOfMonth(),
                ];

            case 'this_year':
                return [
                    'from' => $today->copy()->startOfYear(),
                    'to' => $today->copy()->endOfYear(),
                ];

            case 'last_year':
                return [
                    'from' => $today->copy()->subYear()->startOfYear(),
                    'to' => $today->copy()->subYear()->endOfYear(),
                ];

            default:
                return [
                    'from' => $today->copy()->subMonth(),
                    'to' => $today,
                ];
        }
    }

    /**
     * معالجة الفترة الزمنية بناءً على الطلب (تستخدم في معظم التقارير)
     */
    protected function parseDateRangeRequest(Request $request, $defaultPeriod = '30')
    {
        $period = $request->input('period', $defaultPeriod);
        if ($request->input('date_from') && $request->input('date_to')) {
            $fromDate = Carbon::parse($request->input('date_from'));
            $toDate = Carbon::parse($request->input('date_to'));
        } else {
            $toDate = now();
            switch ($period) {
                case '1': $fromDate = now()->startOfDay();
                    break;
                case '7': $fromDate = now()->subDays(6)->startOfDay();
                    break;
                case '30': $fromDate = now()->subDays(29)->startOfDay();
                    break;
                case '90': $fromDate = now()->subDays(89)->startOfDay();
                    break;
                case '180': $fromDate = now()->subDays(179)->startOfDay();
                    break;
                case '365': $fromDate = now()->subDays(364)->startOfDay();
                    break;
                default: $fromDate = now()->subMonth();
            }
        }

        return [$fromDate, $toDate];
    }

    /**
     * العقدةُ الفعّالة — «تابع لـ» المطلوبة، أو فرعُ المستخدم.
     *
     * ══════════ لماذا لم تعد تقرأ `cascade_node` بنفسها ══════════
     *
     * كانت تفكّ البادئة `b_` بيدها، وهي أثرٌ من حين ظُنّ أنّ الفروعَ
     * والمجموعات جدولان — وهما جدولٌ واحد. صارت القراءةُ في
     * {@see AffiliationTree::requestedNodeId()}، وهو نفسُه الذي تقرأ به
     * تقاريرُ العملاء، فيقبل `tree_node` الجديد ويبقى يفهم الروابطَ
     * المحفوظة بالصيغة القديمة.
     *
     * و`branch_id` يُفصَل فيه هنا لا هناك: يُهمَل عند مستخدمٍ خارج الفرع
     * الرئيسي، وإلّا وسّع نطاقَه برابطٍ مصنوعٍ بيد.
     *
     * ══════════ والقصُّ على نطاق الرؤية ══════════
     *
     * كانت تُعيد العقدةَ المطلوبة كما جاءت من الطلب بلا أيّ تحقّق: `if
     * ($node) return $node;` قبل كلّ شيء. فيكفي أن يُبدَّل `tree_node` في
     * شريط العنوان ليقرأ موظّفُ فرعٍ أرقامَ منطقةٍ أخرى كاملةً — وشرطُ
     * `$isMainBranch` أسفلَها لا يُبلَغ أصلاً في تلك الحالة.
     *
     * وهو بعينه ما سدّته المشترياتُ في `PurchaseReportScope::reportNodeId()`
     * حين نُقلت إلى الشجرة، وبقيت المبيعاتُ مفتوحةً. القصُّ الآن في
     * {@see DataScope::clampNode()} — مصدرٌ واحدٌ للوحدات الثلاث بدل ثلاث
     * نسخٍ تفترق.
     */
    public function getEffectiveBranchId(Request $request, $isMainBranch, $userBranchId)
    {
        $node = $this->requestedReportNodeId($request, $isMainBranch, $userBranchId);

        /* الفرعُ الرئيسيّ يعني المنشأةَ كلَّها — في الشجرة كما في الشريط
           (`HeaderBranchScope`). كان «حسب البند» وحده يعامله هكذا، وبقيّةُ
           التقارير تقصر النتيجةَ على شجرته هو (ابنٌ واحد)، فيختلف تقريران
           على الاختيار نفسِه. */
        if ($node && optional(Branch::findCached($node))->is_main) {
            $node = null;
        }

        return DataScope::clampNode($node);
    }

    /**
     * عقدةُ الشريط العلويّ التي تُقصر عليها شجرةُ «تابع لـ» — أو `null`.
     *
     * ══════════ لماذا ══════════
     *
     * كان التقريرُ يُقيَّد بفرع الشريط، والمُنتقي يعرض الشجرةَ كلَّها من
     * جذورها (١٦٧ عقدة): من وقف على «منطقة الرياض» يرى في «تابع لـ» مناطقَ
     * أخرى، يختارها فيخرج تقريرٌ مقصورٌ على ما ليس له. صار المُنتقي يبدأ
     * من عقدة الشريط ولا يعرض إلّا ما تحتها، والخادمُ يرفض ما خرج عنها.
     *
     * والفرعُ الرئيسيّ لا يقصر شيئاً — نفسُ قاعدة {@see \App\Support\HeaderBranchScope}.
     */
    protected function headerTreeRootId($isMainBranch = null, $userBranchId = null): ?int
    {
        $userBranchId ??= auth()->user()->branch_id ?? null;

        if (! $userBranchId) {
            return null;
        }

        $isMainBranch ??= (bool) optional(Branch::findCached($userBranchId))->is_main;

        return $isMainBranch ? null : (int) $userBranchId;
    }

    /**
     * قصرُ عقد الشجرة على عقدة الشريط وما تحتها.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function trimNodesToHeader(array $nodes): array
    {
        $root = $this->headerTreeRootId();

        if (! $root) {
            return $nodes;
        }

        $allowed = AffiliationTree::descendantIds($root);

        return array_values(array_filter(
            $nodes,
            fn ($node) => in_array((int) ($node['id'] ?? 0), $allowed, true)
        ));
    }

    /**
     * العقدةُ **المطلوبة** قبل قصّها على نطاق الرؤية.
     *
     * فُصلت عن {@see getEffectiveBranchId()} لأنّ القصّ يجب أن يقع على
     * المخرج النهائي مرّةً واحدة، ولا يصحّ أن يتسلّل فرعٌ من أحد الفروع
     * الثلاثة أدناه بلا مرورٍ به.
     */
    private function requestedReportNodeId(Request $request, $isMainBranch, $userBranchId): ?int
    {
        $node = AffiliationTree::requestedNodeId(
            $request,
            ['tree_node', 'cascade_node', 'region_group', 'region_group_id']
        );

        if ($node) {
            /* عقدةٌ خارج شجرة الشريط تُردّ إلى عقدة الشريط نفسِها — المُنتقي
               لا يعرضها، فلا تصل إلّا برابطٍ محفوظٍ من فرعٍ آخر أو بيد. */
            $header = $this->headerTreeRootId($isMainBranch, $userBranchId);

            if ($header && ! in_array($node, AffiliationTree::descendantIds($header), true)) {
                return $header;
            }

            return $node;
        }

        if ($isMainBranch) {
            $requestedBranchId = $request->input('branch_id');

            return ($requestedBranchId && $requestedBranchId !== '0' && $requestedBranchId !== 'all' && $requestedBranchId !== '')
                ? (int) $requestedBranchId
                : null;
        }

        return $userBranchId ? (int) $userBranchId : null;
    }

    /**
     * الفروعُ المشمولة فعلياً — العقدةُ وما تحتها، مقصوصةً على نطاق الرؤية.
     *
     * `null` تعني «كلّ الفروع»، وهي لا تُعاد إلّا لمن نطاقُه يسمح بها.
     *
     * @return array<int, int>|null
     */
    protected function scopedBranchIds(Request $request, $isMainBranch, $userBranchId): ?array
    {
        $node = $this->getEffectiveBranchId($request, $isMainBranch, $userBranchId);

        $requested = $node
            ? ($request->boolean('exact_node') ? [$node] : (AffiliationTree::descendantIds($node) ?: [$node]))
            : null;

        return DataScope::intersectBranchIds($requested);
    }

    /** عقدُ «تابع لـ» لمُنتقي التقارير — نفسُ مصدر تقارير العملاء. */
    protected function affiliationTreeNodes(): array
    {
        return DataScope::filterNodes($this->trimNodesToHeader(AffiliationTree::nodes()));
    }

    /**
     * توسيع الفرع ليشمل المجموعات التي رُحِّلت
     */
    public function expandBranchToDescendants($branchId): array
    {
        return AffiliationTree::descendantIds($branchId);
    }

    /**
     * القوائمُ المنسدلة، مقصورةً على العقدة المختارة وما تحتها.
     *
     * `$withClientList = false` لشاشةٍ انتقلت إلى مُنتقي العملاء بالبحث
     * (`reports.partials.client-select`): تحميلُ ٣٨٥٢ عميلاً بعلاقاتهم في
     * كلّ فتحةٍ لصفحةٍ لا تعرضهم إهدارٌ صامت — وطباعتُهم `<option>`
     * كانت تزن مليون حرفٍ في الصفحة الواحدة.
     */
    protected function getFilteredDropdowns($branchIds, bool $withClientList = true)
    {
        /* مَن يُنشئ القيود: المستخدمون المربوطون بموظّف، ومعهم دورا
           employee/manager القديمان. كانت القائمةُ على الدورين وحدَهما،
           والمندوبون دورُهم «مندوب مبيعات» — وهم منشئو ٥١٠٦ من ٥١١٢ قيدَ
           مبيعات، فلم يكن فلترُ الموظف يعرض إلّا المدراءَ الأربعة. */
        $employeesQuery = User::where(function ($q) {
            $q->whereIn('role', ['employee', 'manager'])->orWhereNotNull('employee_id');
        });
        $clientsQuery = Client::query()->with('branch')->where('status', 'active');
        $regionGroupsQuery = Branch::where('is_territory', 1)->with('parentBranch:id,name');

        /* القوائمُ تتبع نطاقَ الرؤية كما تتبعه الأرقام.

           تقييدُ الاستعلامات وحدَها وتركُ المنسدلات كاملةً حراسةٌ نصفية:
           من رأى اسمَ زميله في «الموظف» وضغطه خرج بشاشةٍ فارغةٍ بلا
           تفسير، وعَلِم من القائمة وحدَها أسماءَ من لا يحقّ له الاطّلاع
           على بياناتهم. */
        DataScope::constrainCreator($employeesQuery, 'id');
        DataScope::constrainOwner($clientsQuery, 'employee_id');

        if ($branchIds) {
            $employeesQuery->whereIn('branch_id', $branchIds);

            /* التبعيّةُ أوّلاً (`clients.tree_node_id`)، والأعمدةُ القديمة
               لمن لا تبعيّةَ له وحدَه — عبر `AffiliationTree` نفسِه الذي
               يطابق به فهرسُ العملاء وتقاريرُهم.

               كانت المطابقةُ على `branch_id` وهو الفرعُ **المحاسبيّ**
               المشتقُّ آلياً: عميلُ منطقةٍ يُنسب إلى الفرع الذي فوقها،
               فيسقط من قائمة عملاء منطقته. اختبارُ منطقة الرياض هنا:
               ٦٤٩ عميلاً بالقديم و٦٥٧ بالتبعيّة الحقيقية. */
            AffiliationTree::constrainClients($clientsQuery, $branchIds);

            $regionGroupsQuery->where(function ($q) use ($branchIds) {
                $q->whereIn('id', $branchIds)
                    ->orWhereIn('parent_id', $branchIds);
            });
        }

        $employees = $employeesQuery->orderBy('name')->get();
        $clients = $withClientList ? $clientsQuery->orderBy('trade_name')->get() : collect();
        $regionGroups = $regionGroupsQuery->orderBy('parent_id')->orderBy('name')->get();

        $branchesQuery = Branch::where('is_territory', 0)->orWhereNull('is_territory');
        if ($branchIds) {
            $branchesQuery->whereIn('id', $branchIds);
        }
        $branches = DataScope::branchOptions($branchesQuery);

        /* `cascadeBranches` للشاشات التي ما زالت على المُنتقي القديم،
           و`treeNodes` للمُنتقي المشترك (`reports.partials.affiliation-tree`)
           — والمصدرُ واحد، فلا تتفرّق الشاشتان في ما تعرضانه. */
        $cascadeBranches = Branch::select('id', 'name', 'parent_id', 'is_territory', 'node_type')
            ->orderBy('name')->get()
            ->map(fn (Branch $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'parent_id' => $b->parent_id,
                'is_territory' => $b->is_territory,
                'node_type' => $b->nodeTypeKey(),
                'type_label' => $b->displayTypeLabel(),
            ])
            ->pipe(fn ($all) => collect($this->trimNodesToHeader($all->all())));

        $treeNodes = DataScope::filterNodes($this->trimNodesToHeader(AffiliationTree::nodes()));

        // حالة العميل («نشط/موقوف») ووضعه («مرحلة التسجيل») — نفس مصدر
        // انديكس العملاء وتقارير العملاء (FiltersCustomerReports)
        $statuses = Statuses::assignable()->ordered()->get(['id', 'name', 'color']);
        $registrationStages = Statuses::section('registration_stage')->ordered()->get(['id', 'name', 'color']);

        return compact('employees', 'clients', 'regionGroups', 'branches', 'cascadeBranches', 'treeNodes', 'statuses', 'registrationStages');
    }

    /**
     * بحثُ العملاء لمُنتقي Select2 في شاشات تقارير المبيعات.
     *
     * ══════════ لماذا مسارٌ في المبيعات لا مسارُ تقارير العملاء ══════════
     *
     * `ClientReport.searchClients` محروسٌ بصلاحيات تقارير العملاء، فمستخدمٌ
     * له تقاريرُ المبيعات وحدَها كان سيرى قائمةً فارغةً بلا سببٍ ظاهر —
     * 403 صامتٌ داخل القائمة المنسدلة. فلكلّ وحدةٍ مصدرُها المحروسُ
     * بصلاحيتها هي، والمنطقُ واحد.
     *
     * والقائمةُ تتبع نطاقَ الشاشة نفسَه: العقدةُ المختارة وما تحتها، وحالةُ
     * العميل ووضعُه — وإلّا أمكن اختيارُ عميلٍ يستحيل أن يُرجع التقريرُ له
     * صفّاً، فتخرج الشاشةُ فارغةً بلا تفسير.
     */
    protected function searchReportClients(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        $page = max(1, (int) $request->get('page', 1));
        $limit = 20;

        $query = Client::query()->where('status', 'active');

        $user = auth()->user();
        $userBranch = $user->branch_id ? Branch::find($user->branch_id) : null;
        $isMainBranch = $userBranch && $userBranch->is_main == 1;

        $node = $this->getEffectiveBranchId($request, $isMainBranch, $user->branch_id);
        $selectedNode = $node ? Branch::find($node) : null;

        // الفرعُ الرئيسي لا يقيّد شيئاً — نفسُ قاعدة الشاشة تماماً
        if ($node && ! ($selectedNode && $selectedNode->is_main == 1)) {
            $query->inTreeNode($node, ! $request->boolean('exact_node'));
        }

        /* ونطاقُ الرؤية فوق ذلك: مندوبٌ نطاقُه «بياناته هو» لا يُكمّل له
           المُنتقي عملاءَ غيرِه ولو كانوا في فرعه. */
        DataScope::constrainOwner($query, 'employee_id');

        if ($request->filled('status')) {
            $request->input('status') === 'no_status'
                ? $query->whereNull('status_id')
                : $query->where('status_id', $request->input('status'));
        }

        if ($request->filled('registration_stage')) {
            $request->input('registration_stage') === 'no_stage'
                ? $query->whereNull('registration_stage_id')
                : $query->where('registration_stage_id', $request->input('registration_stage'));
        }

        if ($term !== '') {
            $like = '%'.$term.'%';

            $query->where(function ($q) use ($like) {
                $q->where('trade_name', 'LIKE', $like)
                    ->orWhere('first_name', 'LIKE', $like)
                    ->orWhere('last_name', 'LIKE', $like)
                    ->orWhere('code', 'LIKE', $like)
                    ->orWhere('mobile', 'LIKE', $like)
                    ->orWhere('phone', 'LIKE', $like);
            });
        }

        $total = (clone $query)->count();

        $clients = $query
            ->orderBy('trade_name')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get(['id', 'trade_name', 'first_name', 'last_name', 'code', 'mobile', 'phone']);

        return response()->json([
            'results' => $clients->map(fn (Client $client) => [
                'id' => $client->id,
                'text' => trim($client->code.' - '.$client->display_name),
                'title' => $client->mobile ?: $client->phone,
            ])->values(),
            'pagination' => ['more' => $page * $limit < $total],
        ]);
    }

    /**
     * تقييدُ استعلامِ قيودٍ بنطاق رؤية المستخدم — محورُ الأشخاص.
     *
     * ══════════ لماذا على `created_by_employee` ══════════
     *
     * العمودُ مُسمّى «موظف» وهو في الحقيقة `users.id`
     * ({@see \App\Models\JournalEntry::createdByEmployee()} تُرجع `User`)،
     * فالقيدُ بـ{@see DataScope::userIds()} لا بمعرّفات الموظفين — والخلطُ
     * بينهما يُفرغ الشاشةَ لأنّ الجدولين لا يتقاطعان في المعرّفات.
     *
     * ومحورُ الأشخاص وحدَه: قيدُ الفرع مطبَّقٌ أصلاً في كلّ متحكّمٍ عبر
     * `getEffectiveBranchId()`، وقد صار مقصوصاً على النطاق في مصدره —
     * فإضافتُه هنا ثانيةً تطرح قيودَ فرعٍ لا عميلَ لها من تقرير مديرِه.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    protected function scopeJournalToVisibleUsers($query, string $column = 'created_by_employee')
    {
        return DataScope::isPeopleAxis()
            ? $query->whereIn($column, DataScope::userIds() ?? [0])
            : $query;
    }

    /**
     * استبعادُ ما أُقفل محاسبياً من استعلام — نفس تعريف
     * `App\Support\ClosedPeriodRanges` الذي تعتمده شاشة فواتير المبيعات،
     * محلّياً هنا كي لا تُستورَد بقيّةُ دوال
     * `Concerns\RespectsAccountingPeriods` (حراسة الكتابة والصلاحيات) في
     * متحكّمات تقارير قراءةٍ فقط.
     */
    protected function excludeClosedRanges($query, string $column): void
    {
        ClosedPeriodRanges::exclude($query, $column);
    }
}
