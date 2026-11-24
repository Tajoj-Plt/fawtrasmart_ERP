<?php

namespace Modules\Branches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchSettingBranch;
use App\Models\Location;
use App\Models\Log as ModelsLog;
use App\Models\Statuses;
use App\Services\RegionGroubsToBranchesMigrationService;
use App\Support\AffiliationTree;
use App\Support\BranchScope;
use App\Support\HeaderBranchScope;
use App\Support\OperationLog;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Branches\Services\BranchSettingsCatalog;
use Modules\Branches\Services\BranchSettingsService;

class BranchesController extends Controller
{
    public function __construct()
    {
        /*
            حراسةٌ على المتحكّم بجانب حراسة المسار — لا بدلاً منها.

            **لماذا الاثنتان؟** لأن المسار يحرس العنوان، والمتحكّم يحرس
            الدالّة. ودالّةٌ تُستدعى من مسارٍ ثانٍ يُضاف بعد شهرين — أو من
            متحكّمٍ آخر، أو من أمرٍ في الطرفية — تمرّ بلا حارسٍ إن كانت
            الحراسة على المسار وحده. وقد وقع فعلاً في هذا النظام: مسار
            `hr.department.dashboard` في `routes/dashboard.php` كان يفتح
            شاشة الموارد البشرية كاملةً من خارج وحدتها.

            **و`switchBranch` مستثنى عمداً**: كل مستخدمٍ يبدّل فرعه من
            الترويسة، والدالّة نفسها تتحقّق أن الفرع ضمن فروعه المسموح بها.
            وحراستُه بصلاحيةٍ إدارية تُعطّل مبدّل الفروع لكل من لا يدير
            الفروع — وهم الأكثرية.
        */
        $this->middleware('permission.role:branches.dashboard', ['only' => ['dashboard']]);
        $this->middleware('permission.role:branches.view', ['only' => ['index']]);
        $this->middleware('permission.role:branches.export', ['only' => ['export']]);
        $this->middleware('permission.role:branches.show', ['only' => ['show']]);
        $this->middleware('permission.role:branches.create', ['only' => ['create', 'store']]);
        $this->middleware('permission.role:branches.edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission.role:branches.toggle_status', ['only' => ['updateStatus']]);
        $this->middleware('permission.role:branches.delete', ['only' => ['destroy']]);
        $this->middleware('permission.role:branches.hierarchy', ['only' => ['bulkAssignParent']]);
        $this->middleware('permission.role:branches.migrate', ['only' => ['migrateRegionGroubsForm', 'migrateRegionGroubsSubmit']]);
        $this->middleware('permission.role:branches.settings.view', ['only' => ['settings', 'getSettings']]);
        $this->middleware('permission.role:branches.settings.edit', ['only' => ['settings_store']]);
        $this->middleware('permission.role:branches.settings.copy', ['only' => ['settingsCopy']]);
        $this->middleware('permission.role:branches.settings.reset', ['only' => ['settingsReset']]);
    }

    /**
     * لوحة الموديول: الهيكلُ التنظيميّ كاملاً بأرقامه، لا مداخلَ أدواته وحدها.
     *
     * كانت اللوحة تعرض أربعةَ أرقامٍ عامّة ومداخلَ الأدوات. وما يحتاجه من
     * يدير المنشأة ليس عددَ العقد بل **أين عملاؤه**: أيُّ منطقةٍ تحمل نصفَهم،
     * وأيُّ مجموعةٍ تحتها فارغة، وأيُّ فرعٍ يبدو صفراً لأن كلَّ عملائه
     * معلَّقون على منافذه.
     *
     * فصارت تعرض الشجرةَ كلَّها مرتَّبةً — الفرعُ الرئيسيّ أوّلاً ثمّ المناطقُ
     * فالفروعُ فالمجموعاتُ فالمنافذ — وبجانب كل عقدةٍ أرقامُها: ما ارتبط بها
     * مباشرةً وما تحتها، ونشِطُهم وموقوفُهم والجديدُ منهم، وأحياؤها وموظفوها.
     *
     * ── الأداء ──
     * كلُّ ما سبق من **ستّة استعلاماتٍ مُجمَّعة** لا استعلامٍ لكل عقدة: الشجرةُ
     * مرّةً، ثمّ `GROUP BY` واحدٌ للعملاء (بأعمدةِ النشِط والموقوف والجديد
     * معاً في نفس المرور)، وواحدٌ للأحياء، وواحدٌ للموظفين، وعدُّ العملاء
     * الكلّي، وآخِرُ السجلّ. والتدحرّجُ صعوداً يمشي كلَّ صفٍّ سلسلةَ أسلافه
     * مرّةً واحدة — عددُ العقد × العمق، بلا استعلامٍ إضافيّ.
     */
    public function dashboard(Request $request)
    {
        // الترتيب بالاسم من قاعدة البيانات لا من PHP: ترتيبُ العربية يتبع
        // ترتيبَ الجدول (collation)، و`sort` في PHP يقارن بايتاتٍ خاماً.
        $all = Branch::query()->orderBy('name')->get();
        $byId = $all->keyBy('id');

        // أعدادُ كل عقدةٍ مباشرةً، ثمّ هي وما تحتها. الوجهان لازمان معاً:
        // فرعٌ كلُّ عملائه على مجموعاته يظهر مباشرةً بصفر، والإجماليُّ وحده
        // يقول إنه ليس فارغاً.
        $direct = $this->dashboardDirectCounts($all);

        $totals = [];
        foreach ($direct as $key => $map) {
            $totals[$key] = $this->rollUpByTree($map, $all);
        }

        // ── ترتيب الإخوة ──
        // الرئيسيُّ أوّلاً، ثمّ منطقةٌ ففرعٌ فمجموعةٌ فمنفذ. الترتيبُ على
        // الإخوة وحدهم؛ التداخلُ يبقى للشجرة نفسها فيُقرأ «منطقة ← ما تحتها»
        // كما رُسم لا كما رتّبه المعرّف. وترتيبُ PHP ثابت (stable) منذ 8.0،
        // فترتيبُ الاسم القادمُ من الاستعلام يبقى محفوظاً داخل كل رتبة.
        $typeRank = ['region' => 1, 'branch' => 2, 'group' => 3, 'outlet' => 4];
        $displayTypes = $this->displayTypesByNode($all);
        $ordered = $all->sortBy(fn (Branch $b) => $b->is_main ? 0 : ($typeRank[$displayTypes[(int) $b->id] ?? 'group'] ?? 5))->values();

        // اليتيم — أبوه المسجَّل غير موجود — يُعرض جذراً بدل أن يختفي من
        // الشاشة تماماً كما يقع في الشجرة.
        $childrenByParent = $ordered->groupBy(function (Branch $b) use ($byId) {
            $parentId = (int) ($b->parent_id ?? 0);

            return $parentId && $byId->has($parentId) ? $parentId : 0;
        });

        $roots = $childrenByParent->get(0, collect());

        // عملاءُ الجدول كلُّهم مقابلَ من استقرّ منهم على عقدةٍ قائمة: الفارقُ
        // عملاءُ بلا موضعٍ في الهيكل — لا يظهرون في صفٍّ من الشجرة، ولا في
        // أيّ تقريرٍ يُفلتَر بالجهة.
        $clientRowsTotal = Schema::hasTable('clients') ? (int) DB::table('clients')->count() : 0;
        $clientsPlaced = 0;
        foreach ($direct['clients'] as $nodeId => $count) {
            if ($byId->has($nodeId)) {
                $clientsPlaced += $count;
            }
        }

        /*
         * ══════════ الجهة المختارة ══════════
         *
         * نفسُ مُنتقي «تابع لـ» الذي تعمل به تقاريرُ العملاء: `tree_node`
         * معرّفاً مجرّداً، و`exact_node` لمن أراد الجهةَ وحدَها دون ما تحتها.
         * وقراءةُ الطلب من [\App\Support\AffiliationTree] لا بيدٍ هنا، فتبقى
         * الروابطُ المحفوظة بصيغها القديمة (`b_12`، `region_group`) مفهومةً
         * في اللوحة كما هي مفهومةٌ في التقارير.
         *
         * وأثرُ الاختيار أن اللوحةَ كلَّها تنزل إلى تلك الجهة: الأرقامُ
         * والرسمان والبطاقاتُ والجدول. فمن أراد «ابناً تحت ابنٍ تحت ابن»
         * نزل إليه مستوىً بعد مستوى ورأى إحصاءَه وحدَه.
         */
        $selectedId = AffiliationTree::requestedNodeId($request, ['tree_node']);
        $selected = $selectedId ? $byId->get($selectedId) : null;

        /*
         * ── فرعُ الشريط العلويّ ──
         *
         * الفرعُ المختار في الشريط هو **سقفُ** اللوحة: تبدأ منه، ولا يُصعد
         * فوقه بالمُنتقي ولا برابطٍ محفوظ. فمن اختار «منطقة الرياض» في
         * الشريط رأى اللوحةَ كلَّها عليها، وينزل داخلها كما يشاء.
         *
         * والفرعُ الرئيسيّ يعني المنشأةَ كلَّها — القاعدةُ في
         * [HeaderBranchScope] لا هنا، فتوافق اللوحةُ بقيّةَ شاشات النظام.
         */
        $headerNode = $this->headerScopeNode($byId);
        $headerIds = $headerNode ? $this->subtreeIds((int) $headerNode->id, $childrenByParent) : null;

        if ($headerNode && (! $selected || ! isset($headerIds[(int) $selected->id]))) {
            $selected = $headerNode;
        }

        $selectedId = $selected ? (int) $selected->id : null;
        $exactNode = $selected !== null && AffiliationTree::exactRequested($request);

        // جذرُ العرض: الجهةُ المختارة، وإلّا الشجرةُ كاملةً
        $scopeRoots = $selected ? collect([$selected]) : $roots;

        // الشجرةُ تصل القالبَ مسطَّحةً: صفٌّ لكل عقدةٍ بعمقه وأرقامه، فيبقى
        // القالبُ حلقةً واحدة بلا استدعاءِ قالبٍ جزئيّ لكل صفّ.
        $scopeRows = $this->dashboardHierarchy(
            $scopeRoots,
            // «هذه الجهة وحدَها»: تُقطع الأبناءُ عن البناء فلا ينزل التسطيح
            $exactNode ? collect() : $childrenByParent,
            $direct,
            $totals,
            $clientsPlaced,
            $displayTypes
        );

        /*
         * ── المستوى المعروض ──
         *
         * ما تحت الجهة **مباشرةً** لا كلُّ ما تحتها: من ضغط على عنصرٍ يريد
         * أبناءه، لا أبناءَ أبنائه مبعثرين معهم في رسمٍ واحد.
         *
         * وتُبنى هنا قبل الإحصاء لأن الرسمَين وعدَّ الأنواع يُحسبان منها،
         * فيتطابق ما يُرسم مع ما في الجدول حرفاً بحرف. وكانت تُبنى بعده
         * فيُحسب الرسمُ من الشجرة كلِّها والجدولُ من مستوىً واحد.
         */
        $treeBase = $selected ? 1 : 0;
        $levelRows = $scopeRows->where('depth', $treeBase)->values();

        // الشجرةُ المعروضة في الجدول: كلُّ ما تحت الجهة بأعماقه، لا مستواها
        // الأوّل وحدَه. والجهةُ المختارةُ نفسُها خارجها — لها بطاقتُها فوق.
        $treeRows = $scopeRows->where('depth', '>=', $treeBase)->values();

        // ── أرقامُ النطاق المعروض ──
        // بلا اختيارٍ هي أرقامُ المنشأة كلِّها؛ ومع اختيارٍ أرقامُ الجهة
        // وما تحتها (أو وحدَها). والعُقدُ تُعدّ من الصفوف المبنيّة نفسِها،
        // فلا يفترق ما في البطاقات عمّا في الجدول.
        $scopeCount = function (string $key) use ($selected, $exactNode, $direct, $totals, $clientsPlaced) {
            if (! $selected) {
                return $key === 'clients' ? $clientsPlaced : array_sum($direct[$key]);
            }

            return $exactNode ? $direct[$key][$selected->id] ?? 0 : $totals[$key][$selected->id] ?? 0;
        };

        // الأنواعُ تُعدّ على المستوى المعروض وحدَه — الدائرةُ كانت تعدّ
        // الشجرةَ كلَّها **والجهةَ نفسَها معها**، فتقول «٤٣ عنصراً» والجدولُ
        // تحتها يعرض خمسة.
        $byType = $levelRows->countBy('type');
        $maxDepth = $scopeRows->isEmpty() ? 0 : ((int) $scopeRows->max('depth') + 1);

        $stats = [
            // عددان لا واحد: ما تحت الجهة كلِّه، وما تحتها مباشرةً.
            // خلطُهما هو ما جعل الدائرةَ تخالف الجدول.
            'total' => $scopeRows->count(),
            'level' => $levelRows->count(),
            'roots' => $scopeRoots->count(),
            'depth' => $maxDepth,
            'inactive' => $scopeRows->where('active', false)->count(),
            'branches' => $byType->get('branch', 0),
            'regions' => $byType->get('region', 0),
            'groups' => $byType->get('group', 0),
            'outlets' => $byType->get('outlet', 0),
            'clients' => $scopeCount('clients'),
            'clients_active' => $scopeCount('clients_active'),
            'clients_stopped' => $scopeCount('clients_stopped'),
            'clients_new' => $scopeCount('clients_new'),
            'neighborhoods' => $scopeCount('neighborhoods'),
            'employees' => $scopeCount('employees'),
            // شأنُ المنشأة كلِّها لا شأنَ فرعٍ بعينه: هم خارجُ كلِّ فرعٍ أصلاً،
            // فلا يُنبَّه إليهم مَن حصر الشريطَ على فرعه
            'unplaced_clients' => $headerNode ? 0 : max(0, $clientRowsTotal - $clientsPlaced),
            // عقدةٌ بلا عميلٍ فيها ولا تحتها — إمّا جديدةٌ أو مهجورة
            // «بلا عملاء» يوافق ما يفلتره الجدول، فهو على المستوى نفسِه
            'empty_nodes' => $levelRows->where('clients', 0)->count(),
            // مقامُ النِّسب: عملاءُ المنشأة كلُّهم، لا عملاءُ النطاق. فنصيبُ
            // الجهة يبقى نصيباً من الكلّ مهما نزلنا، وإلّا صار كلُّ نطاقٍ
            // ١٠٠٪ من نفسه فلا يقول شيئاً.
            'clients_all' => $clientsPlaced,
        ];

        // ── سلامة الهيكل ──
        // اليتامى شأنُ الشجرة كلِّها لا شأنَ نطاقٍ بعينه: هم خارجُ كلِّ
        // فرعٍ أصلاً، فلا يُعرضون إلّا في العرض الكامل.
        $orphans = $selected
            ? collect()
            : $all->filter(fn (Branch $b) => $b->parent_id && ! $byId->has($b->parent_id))->values();

        $scopeIds = array_flip($scopeRows->pluck('id')->all());
        $noCode = $all->filter(fn (Branch $b) => trim((string) $b->code) === '' && isset($scopeIds[(int) $b->id]))->values();

        // ── الأكبر بين المعروضين ──
        // من صفوف المستوى نفسِها: الرسمُ كان يسحب من الشجرة كلِّها، فيظهر
        // فيه حفيدٌ إلى جانب ابنٍ ولا يُعرف من أين جاء.
        //
        // والعددُ يبقى شاملاً ما تحت كلِّ صفّ: فرعٌ كلُّ عملائه على مجموعاته
        // كان سيظهر بصفرٍ وهو أكبرُ إخوته.
        $topNodes = $levelRows
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'type' => $row['type'],
                'clients' => $row['clients'],
                'direct' => $row['clients_direct'],
            ])
            ->filter(fn (array $row) => $row['clients'] > 0)
            ->sortByDesc('clients')
            ->take(10)
            ->values();

        // بطاقةُ الجهة المختارة نفسِها — مسارُها من الجذر وأرقامُها كاملةً
        $selectedCard = $selected
            ? $this->dashboardSelectedCard($selected, $byId, $childrenByParent, $direct, $totals, $clientsPlaced, $exactNode, $displayTypes)
            : null;

        // عقدُ المُنتقي: نفسُ مصدر تقارير العملاء، فما يُرى هنا يُرى هناك
        $treeNodes = AffiliationTree::nodes();

        $headerScope = null;
        if ($headerNode) {
            // المُنتقي يعرض شجرةَ فرع الشريط وحدَها — لا خيارَ فيه يُردّ عنده
            $treeNodes = array_values(array_filter($treeNodes, fn (array $n) => isset($headerIds[(int) $n['id']])));

            // والمسارُ يبدأ من فرع الشريط: ما فوقه روابطُ لا تقود إلى شيء
            if ($selectedCard) {
                $selectedCard['path'] = array_values(array_filter(
                    $selectedCard['path'],
                    fn (array $step) => isset($headerIds[(int) $step['id']])
                ));
            }

            $headerScope = [
                'id' => (int) $headerNode->id,
                'name' => (string) $headerNode->name,
                // أهو الجهةُ المعروضة نفسُها، أم نزل المستخدمُ تحتها؟
                'is_current' => $selectedId === (int) $headerNode->id,
            ];
        }

        $recentLogs = ModelsLog::where('type', 'branch')
            ->orderByDesc('created_at')
            ->take(8)
            ->get();

        return view('branches::dashboard', compact(
            'stats',
            'levelRows',
            'treeRows',
            'treeBase',
            'selectedCard',
            'selectedId',
            'headerScope',
            'exactNode',
            'treeNodes',
            'topNodes',
            'orphans',
            'noCode',
            'recentLogs',
        ));
    }

    /**
     * بطاقةُ الجهة المختارة: مسارُها من الجذر، ونوعُها، وأرقامُها كاملةً.
     *
     * المسارُ ليس زينةً: «حائل» وحدَها لا تقول تحت أيّ منطقةٍ هي، والمنشأةُ
     * قد يكون فيها اسمٌ مكرّرٌ تحت جهتين. والأرقامُ بوجهَيها — الجهةُ وحدَها
     * وهي وما تحتها — لأن الفرق بينهما هو الجواب عن «أين عملائي فعلاً».
     *
     * @param  Collection<int, Branch>  $byId
     * @param  Collection<int, Collection<int, Branch>>  $childrenByParent
     * @param  array<string, array<int, int>>  $direct
     * @param  array<string, array<int, int>>  $totals
     * @return array<string, mixed>
     */
    private function dashboardSelectedCard(
        Branch $node,
        Collection $byId,
        Collection $childrenByParent,
        array $direct,
        array $totals,
        int $clientsPlaced,
        bool $exactNode,
        array $displayTypes = []
    ): array {
        $typeOf = fn (Branch $b) => $displayTypes[(int) $b->id] ?? $b->nodeTypeKey();

        // المسارُ من الجذر إلى الجهة — بعدّةٍ حارسة، فشجرةٌ تالفةٌ بحلقةٍ
        // كانت ستدور هنا بلا نهاية
        $path = [];
        $cursor = $node;
        $guard = 0;
        while ($cursor && $guard++ < 50) {
            array_unshift($path, ['id' => (int) $cursor->id, 'name' => (string) $cursor->name, 'type' => $typeOf($cursor)]);
            $cursor = $cursor->parent_id ? $byId->get($cursor->parent_id) : null;
        }

        $children = $childrenByParent->get((int) $node->id, collect());
        $clients = $exactNode ? ($direct['clients'][$node->id] ?? 0) : ($totals['clients'][$node->id] ?? 0);

        return [
            'id' => (int) $node->id,
            'name' => (string) $node->name,
            'code' => trim((string) $node->code),
            'type' => $typeOf($node),
            'is_main' => (bool) $node->is_main,
            'active' => (int) $node->status !== 1,
            'exact' => $exactNode,
            'path' => $path,
            'depth' => max(0, count($path) - 1),
            'children' => $children->count(),
            // ما تحتها موزَّعاً بأنواعه: «٦ مجموعات ومنفذان» أوضحُ من «٨ عناصر»
            'children_by_type' => $children->countBy(fn (Branch $child) => $typeOf($child))->all(),
            'descendants' => max(0, ($totals['nodes'][$node->id] ?? 1) - 1),
            'clients' => $clients,
            'clients_direct' => $direct['clients'][$node->id] ?? 0,
            'clients_below' => max(0, ($totals['clients'][$node->id] ?? 0) - ($direct['clients'][$node->id] ?? 0)),
            'clients_active' => $exactNode ? ($direct['clients_active'][$node->id] ?? 0) : ($totals['clients_active'][$node->id] ?? 0),
            'clients_stopped' => $exactNode ? ($direct['clients_stopped'][$node->id] ?? 0) : ($totals['clients_stopped'][$node->id] ?? 0),
            'clients_new' => $exactNode ? ($direct['clients_new'][$node->id] ?? 0) : ($totals['clients_new'][$node->id] ?? 0),
            'neighborhoods' => $exactNode ? ($direct['neighborhoods'][$node->id] ?? 0) : ($totals['neighborhoods'][$node->id] ?? 0),
            'employees' => $exactNode ? ($direct['employees'][$node->id] ?? 0) : ($totals['employees'][$node->id] ?? 0),
            'share' => $clientsPlaced > 0 ? (int) round($clients / $clientsPlaced * 100) : 0,
        ];
    }

    /**
     * الشجرةُ مسطَّحةً: صفٌّ لكل عقدةٍ بعمقه وأرقامه، بترتيب القراءة نفسِه.
     *
     * ── ولماذا لا قالبٌ جزئيّ يستدعي نفسَه لكل عقدة، كما في شجرة الفهرس؟ ──
     *
     * لأن كلَّ استدعاءِ قالبٍ في هذا النظام يُشغِّل `View::composer('*')`
     * المسجَّلة في `AppServiceProvider` — وفيها استعلامُ زياراتِ اليوم بلا
     * تخزين. فشجرةٌ من مئةٍ وسبعٍ وستّين عقدةً تعني مئةً وسبعاً وستّين
     * استعلاماً إضافياً على `visits`، ومثلَها فحصَ صلاحيةٍ لكل صفّ. قيسَ
     * الفارقُ فعلاً على نفس الشجرة: صفحةُ الفهرس تُصدر ١٥٢٩ استعلاماً في
     * الطلب الواحد، وهذه اللوحةُ بعد التسطيح ٧٥ — منها ٢٢ لها والباقي
     * للترويسة والقائمة الجانبية المشتركتَين.
     *
     * فالتسطيحُ هنا يجعل القالبَ حلقةً واحدة داخل قالبٍ واحد: لا استدعاءَ
     * قالبٍ لكل صفّ، ولا فحصَ صلاحيةٍ لكل صفّ.
     *
     * @param  Collection<int, Branch>  $roots
     * @param  Collection<int, Collection<int, Branch>>  $childrenByParent
     * @param  array<string, array<int, int>>  $direct
     * @param  array<string, array<int, int>>  $totals
     * @return Collection<int, array<string, mixed>>
     */
    private function dashboardHierarchy(
        Collection $roots,
        Collection $childrenByParent,
        array $direct,
        array $totals,
        int $clientsBase,
        array $displayTypes = []
    ): Collection {
        $rows = collect();

        // مكدَّسٌ بدل استدعاءٍ ذاتيّ: شجرةٌ عميقةٌ أو تالفةٌ بحلقةٍ كانت تُنهي
        // مكدَّسَ PHP نفسَه، وهنا تُوقفها العدّةُ الحارسة بلا انهيار.
        $stack = [];
        foreach ($roots->reverse() as $root) {
            $stack[] = [$root, 0, null, null];
        }

        $guard = 0;
        while ($stack !== [] && $guard++ < 20000) {
            [$node, $depth, $parentName, $parentId] = array_pop($stack);

            $children = $childrenByParent->get((int) $node->id, collect());

            $clientsDirect = $direct['clients'][$node->id] ?? 0;
            $clientsTotal = $totals['clients'][$node->id] ?? $clientsDirect;

            $rows->push([
                'id' => (int) $node->id,
                'name' => (string) $node->name,
                'code' => trim((string) $node->code),
                // نوعُ العرض لا المخزَّن: المناطقُ والمنافذُ القديمة مخزَّنةٌ
                // «مجموعة» — انظر `Branch::displayNodeType()`
                'type' => $displayTypes[(int) $node->id] ?? $node->nodeTypeKey(),
                'depth' => $depth,
                'parent_id' => $parentId,
                'parent_name' => $parentName,
                'is_main' => (bool) $node->is_main,
                // status: 0/NULL = نشط، 1 = معطّل (كما في CheckBranchStatus)
                'active' => (int) $node->status !== 1,
                'has_children' => $children->isNotEmpty(),
                'children' => $children->count(),
                'subtree' => max(0, ($totals['nodes'][$node->id] ?? 1) - 1),
                'clients' => $clientsTotal,
                'clients_direct' => $clientsDirect,
                'clients_below' => max(0, $clientsTotal - $clientsDirect),
                'clients_active' => $totals['clients_active'][$node->id] ?? 0,
                'clients_stopped' => $totals['clients_stopped'][$node->id] ?? 0,
                'clients_new' => $totals['clients_new'][$node->id] ?? 0,
                'neighborhoods' => $totals['neighborhoods'][$node->id] ?? 0,
                'employees' => $totals['employees'][$node->id] ?? 0,
                'share' => $clientsBase > 0 ? (int) round($clientsTotal / $clientsBase * 100) : 0,
            ]);

            // معكوسةً ليخرج الابنُ الأوّل أوّلاً من المكدَّس، فيبقى ترتيبُ
            // الإخوة كما رُتِّب ويبقى النزولُ عميقاً قبل الانتقال إلى الأخ التالي
            foreach ($children->reverse() as $child) {
                $stack[] = [$child, $depth + 1, (string) $node->name, (int) $node->id];
            }
        }

        return $rows;
    }

    /**
     * عقدةُ الشريط العلويّ التي تُحصر عليها شاشاتُ الهيكل — أو `null` حين
     * لا حصر (لا فرعَ مختاراً، أو المختارُ هو الرئيسيّ لمن له المنشأةُ كلُّها).
     *
     * @param  Collection<int, Branch>  $byId
     */
    private function headerScopeNode(Collection $byId): ?Branch
    {
        $nodeId = HeaderBranchScope::nodeId();

        return $nodeId ? $byId->get($nodeId) : null;
    }

    /**
     * معرّفاتُ العقدة وكلِّ ما تحتها — مفاتيحَ لا قيماً، فالفحصُ `isset`.
     *
     * بمكدَّسٍ وعدّةٍ حارسة: شجرةٌ تالفةٌ بحلقةٍ تتوقّف ولا تدور.
     *
     * @param  Collection<int, Collection<int, Branch>>  $childrenByParent
     * @return array<int, true>
     */
    private function subtreeIds(int $rootId, Collection $childrenByParent): array
    {
        $ids = [];
        $stack = [$rootId];
        $guard = 0;

        while ($stack !== [] && $guard++ < 20000) {
            $id = (int) array_pop($stack);
            if (isset($ids[$id])) {
                continue;
            }

            $ids[$id] = true;
            foreach ($childrenByParent->get($id, collect()) as $child) {
                $stack[] = (int) $child->id;
            }
        }

        return $ids;
    }

    /**
     * نوعُ **العرض** لكل عقدة — منطقة / فرع / مجموعة / منفذ.
     *
     * المخزَّنُ في `node_type` يكذب في المنشآت القديمة: مناطقُها ومنافذُها
     * مخزَّنةٌ «مجموعة»، فكانت الشجرةُ واللوحةُ تعرضان «منطقة الرياض —
     * مجموعة». `Branch::displayNodeType()` يصحّح ذلك للعرض وحدَه، ويحتاج
     * أبناءَ العقدة ليعرف الجذرَ الذي تحته فروع — فتُجمَع الأبناءُ هنا مرّةً
     * بدل قراءتها لكل صفّ.
     *
     * للعرض والترتيب والتصفية فقط: ما يقرّر المحاسبةَ يبقى على `nodeTypeKey()`.
     *
     * @param  Collection<int, Branch>  $all
     * @return array<int, string>
     */
    private function displayTypesByNode(Collection $all): array
    {
        $children = $all->groupBy(fn (Branch $b) => (int) ($b->parent_id ?? 0));

        return $all->mapWithKeys(fn (Branch $b) => [
            (int) $b->id => Branch::displayNodeType($b, $children->get((int) $b->id, collect())),
        ])->all();
    }

    /**
     * أعدادُ كل عقدةٍ مباشرةً — استعلامٌ مُجمَّعٌ واحدٌ لكل مصدر، لا استعلامٌ
     * لكل عقدة.
     *
     * ── لماذا لا يُعدّ العملاء بـ`clients.branch_id`؟ ──
     * `branch_id` صار **الفرعَ المحاسبيّ المشتقّ** لا موضعَ العميل: عميلُ
     * المنطقة أو المنفذ يحمل معرّفَ الفرع الذي فوقهما، فالعدُّ به يُظهرهما
     * فارغَين ويكدّس عملاءهما على الفرع. الموضعُ الحقيقيّ `tree_node_id`،
     * و`COALESCE` تغطّي صفوفاً سبقت العمود.
     *
     * والنشِطُ والموقوفُ والجديدُ تُحسب في **نفس المرور** بأعمدةٍ شرطية بدل
     * ثلاثة استعلاماتٍ إضافية على نفس الجدول.
     *
     * @param  Collection<int, Branch>  $all
     * @return array<string, array<int, int>>
     */
    private function dashboardDirectCounts(Collection $all): array
    {
        $out = [
            // «واحدٌ لكل عقدة» — يصير بعد التدحرّج حجمَ الشجرة تحت كل عقدة
            'nodes' => $all->mapWithKeys(fn (Branch $b) => [(int) $b->id => 1])->all(),
            'clients' => [],
            'clients_active' => [],
            'clients_stopped' => [],
            'clients_new' => [],
            'neighborhoods' => [],
            'employees' => [],
        ];

        $nodeExpr = $this->clientNodeExpression();

        if ($nodeExpr !== null) {
            // «نشط» و«موقوف» ليستا 1/2 مثبَّتتين بالكود: تُقرآن من الحالة
            // المضبوطة بهذا الدور في شاشة «حالات العملاء» — نفس مصدر لوحة
            // العملاء، وإلا اختلف رقمُ اللوحتين على نفس البيانات.
            $activeIds = Statuses::activeClientStatusIds();
            $stoppedIds = Statuses::stoppedClientStatusIds();
            $since = now()->subDays(30)->startOfDay();

            /*
             * الجهةُ تُحسب في استعلامٍ داخليّ ثمّ يُجمَّع الخارجيُّ عليها.
             *
             * ولماذا لا `GROUP BY` على التعبير مباشرةً كما كان؟ لأن التعبير
             * صار يحمل استعلاماً فرعياً مرتبطاً، و`ONLY_FULL_GROUP_BY` —
             * وهو الوضعُ الافتراضيّ في MySQL 5.7 فما فوق — يرفض تجميعاً
             * على تعبيرٍ كهذا برسالة «isn't in GROUP BY». والجدولُ المشتقّ
             * يكتب التعبيرَ مرّةً واحدة، فلا يُعاد حسابُه في الشرط والتجميع.
             */
            $placed = DB::table('clients')->selectRaw($nodeExpr.' as node_id, status_id, created_at');

            $rows = DB::query()
                ->fromSub($placed, 'placed')
                ->selectRaw('node_id')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status_id IN ('.$this->inPlaceholders($activeIds).') THEN 1 ELSE 0 END) as active', $activeIds)
                ->selectRaw('SUM(CASE WHEN status_id IN ('.$this->inPlaceholders($stoppedIds).') THEN 1 ELSE 0 END) as stopped', $stoppedIds)
                ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as fresh', [$since])
                ->whereNotNull('node_id')
                ->groupBy('node_id')
                ->get();

            foreach ($rows as $row) {
                $id = (int) $row->node_id;
                $out['clients'][$id] = (int) $row->total;
                $out['clients_active'][$id] = (int) $row->active;
                $out['clients_stopped'][$id] = (int) $row->stopped;
                $out['clients_new'][$id] = (int) $row->fresh;
            }
        }

        if (Schema::hasTable('neighborhoods')) {
            /*
             * `COUNT(DISTINCT name)` لا `COUNT(*)`.
             *
             * جدولُ `neighborhoods` صفٌّ **لكل عميل** لا لكل حيّ: فيه
             * `client_id`، فالعميلان في الحيّ الواحد صفّان باسمٍ واحد.
             * فالعدُّ بـ`COUNT(*)` كان يعطي عددَ العملاء الذين سُجّل لهم حيّ
             * لا عددَ الأحياء — ٢٧٤٠ صفّاً مقابل ٤٣٦ حيّاً فعلياً هنا،
             * ومنطقةٌ واحدة كانت تُظهر «٤٠٧ أحياء» وأحياؤها ٩٧.
             */
            $out['neighborhoods'] = DB::table('neighborhoods')
                ->select('region_id', DB::raw('COUNT(DISTINCT name) as c'))
                ->whereNotNull('region_id')
                ->groupBy('region_id')
                ->pluck('c', 'region_id')
                ->mapWithKeys(fn ($c, $id) => [(int) $id => (int) $c])
                ->all();
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'branch_id')) {
            $out['employees'] = DB::table('employees')
                ->select('branch_id', DB::raw('COUNT(*) as c'))
                ->whereNotNull('branch_id')
                ->groupBy('branch_id')
                ->pluck('c', 'branch_id')
                ->mapWithKeys(fn ($c, $id) => [(int) $id => (int) $c])
                ->all();
        }

        return $out;
    }

    /**
     * تعبيرُ SQL الذي يعطي **جهةَ العميل** في الهيكل — تعريفٌ واحدٌ تقرؤه
     * لوحةُ الفروع وشجرتُها معاً.

     * ── لماذا لا `clients.branch_id` وحدَه ──
     * `branch_id` صار الفرعَ **المحاسبيَّ المشتقَّ** لا موضعَ العميل: عميلُ
     * المنطقة أو المنفذ يحمل معرّفَ الفرع الذي فوقهما، فالعدُّ به يُظهرهما
     * فارغَين ويكدّس عملاءهما على الفرع. الموضعُ الحقيقيّ `tree_node_id`،
     * ثمّ `branch_id`، ثمّ منطقةُ الحيّ — وهي بعينها أولويّةُ
     * [\App\Support\AffiliationTree::constrainClients] التي تفلتر بها
     * تقاريرُ العملاء، فيتطابق ما تعدّه اللوحةُ وما يعرضه التقرير.
     *
     * وكانت النسخةُ منه في موضعين، فأيُّ تعديلٍ في أحدهما يترك الآخر على
     * تعريفٍ آخر لنفس السؤال.
     *
     * @return string|null null حين لا يكون في القاعدة جدولُ عملاءَ صالح
     */
    private function clientNodeExpression(): ?string
    {
        if (! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'branch_id')) {
            return null;
        }

        $expr = Schema::hasColumn('clients', 'tree_node_id')
            ? 'COALESCE(tree_node_id, branch_id)'
            : 'branch_id';

        /*
         * الصفُّ القديم — بلا `tree_node_id` وبلا `branch_id` — يُنسب إلى
         * منطقة حيِّه. وبدون هذا الطريق الثالث يختلف رقمُ اللوحة عن رقم
         * التقرير على نفس الجهة، وهو ما وقع فعلاً: عميلٌ في «أم الحمام
         * الشرقي» كان يُعدّ هنا «خارج الهيكل» ويُعدّ هناك ضمن منطقته.
         *
         * و`COALESCE` تتوقّف عند أوّل قيمةٍ غير فارغة، فالاستعلامُ الفرعيُّ
         * لا يُنفَّذ إلّا لتلك الصفوف وحدها (صفّان من ٣٨٥٢ هنا).
         */
        if (Schema::hasTable('neighborhoods') && Schema::hasColumn('neighborhoods', 'client_id')) {
            $expr = 'COALESCE('.$expr.', (select n.region_id from neighborhoods n'
                .' where n.client_id = clients.id and n.region_id is not null limit 1))';
        }

        return $expr;
    }

    /**
     * علاماتُ استفهامٍ بعدد القيم — لتبقى قائمةُ `IN` مربوطةً لا مدموجةً في
     * نصّ الاستعلام. والقائمةُ الفارغة تُعطي `NULL` فلا يطابقها شيء، وهو
     * الصواب: «لا حالةَ مضبوطة» تعني «لا أحد».
     *
     * @param  array<int, mixed>  $values
     */
    private function inPlaceholders(array $values): string
    {
        return $values === [] ? 'NULL' : implode(',', array_fill(0, count($values), '?'));
    }

    // عرض جميع الفروع كشجرة قابلة للطي مع المجموعات
    public function index()
    {
        $allBranches = Branch::orderBy('name')->get();

        // خريطة الأبناء حسب parent_id لبناء الشجرة بدون استعلامات إضافية
        $childrenByParent = $allBranches->groupBy(fn ($b) => $b->parent_id ?? 0);
        $rootBranches = $childrenByParent->get(0, collect());
        $bulkParentOptions = $this->branchParentOptions(null);

        // أعداد الارتباطات لكل عقدة — استعلامان مجمّعان لا استعلام لكل صف.
        // بدونها لا يفرّق المستخدم بين فرع يخدم 900 عميل وفرع فارغ، فيحذف
        // أو ينقل على غير هدى.
        //
        // من `dashboardDirectCounts` نفسِها لا من عدٍّ ثانٍ: هي تعدّ النشِطَ
        // والموقوفَ في المرور نفسه، فيقرأ الفهرسُ واللوحةُ رقماً واحداً.
        $direct = $this->dashboardDirectCounts($allBranches);
        $counts = [
            'clients' => $direct['clients'],
            'neighborhoods' => $direct['neighborhoods'],
            'clients_total' => $this->rollUpByTree($direct['clients'], $allBranches),
            'neighborhoods_total' => $this->rollUpByTree($direct['neighborhoods'], $allBranches),
            // النشِطُ والموقوفُ شاملَين ما تحت العقدة — كما في اللوحة
            'clients_active' => $this->rollUpByTree($direct['clients_active'], $allBranches),
            'clients_stopped' => $this->rollUpByTree($direct['clients_stopped'], $allBranches),
        ];

        // نوعُ العرض لكل عقدة — يقرؤه صفُّ الشجرة وفلترُ «النوع» وبطاقاتُ الإحصاء
        $displayTypes = $this->displayTypesByNode($allBranches);

        /*
         * ── فرعُ الشريط العلويّ ──
         * من اختار فرعاً في الشريط رأى شجرتَه وحدَها: هو جذرُها، وبطاقاتُ
         * الإحصاء تعدّ ما تحته لا المنشأةَ كلَّها. والرئيسيّ يعني الكلّ —
         * انظر [HeaderBranchScope].
         */
        $headerNode = $this->headerScopeNode($allBranches->keyBy('id'));
        $scoped = $allBranches;
        $headerScope = null;

        if ($headerNode) {
            $headerIds = $this->subtreeIds((int) $headerNode->id, $childrenByParent);
            $scoped = $allBranches->filter(fn (Branch $b) => isset($headerIds[(int) $b->id]))->values();
            $rootBranches = collect([$headerNode]);
            $headerScope = ['id' => (int) $headerNode->id, 'name' => (string) $headerNode->name, 'is_current' => true];
        }

        // بطاقات الإحصاء أعلى الشاشة — بنفس أسلوب صفحات سجل النشاطات.
        // تُحسب من المجموعة المحمّلة أصلاً، بلا استعلام إضافي.
        $byType = $scoped->countBy(fn (Branch $b) => $displayTypes[(int) $b->id] ?? $b->nodeTypeKey());

        $stats = [
            'total' => $scoped->count(),
            'roots' => $rootBranches->count(),
            'branches' => ($byType['branch'] ?? 0) + ($byType['outlet'] ?? 0),
            'territories' => ($byType['region'] ?? 0) + ($byType['group'] ?? 0),
            // status: 0/NULL = نشط، 1 = معطّل (كما في CheckBranchStatus)
            'inactive' => $scoped->filter(fn (Branch $b) => (int) $b->status === 1)->count(),
            // تحت فرع الشريط: هو وكلُّ ما تحته — وهو الرقمُ في صفّه من الشجرة
            'clients' => $headerNode
                ? ($counts['clients_total'][$headerNode->id] ?? 0)
                : array_sum($counts['clients']),
            'neighborhoods' => $headerNode
                ? ($counts['neighborhoods_total'][$headerNode->id] ?? 0)
                : array_sum($counts['neighborhoods']),
        ];

        return view('branches::index', compact(
            'rootBranches',
            'childrenByParent',
            'bulkParentOptions',
            'counts',
            'displayTypes',
            'headerScope',
            'stats',
        ));
    }

    /**
     * أعداد العملاء والأحياء لكل عقدة، مباشرةً ومع ما تحتها.
     *
     * ── لماذا لا يُعدّ العملاء بـ`clients.branch_id` وحده ──
     *
     * `branch_id` صار **الفرع المحاسبيّ** المشتقّ لا موضعَ العميل: عميلٌ تابعٌ
     * لمنطقةٍ أو منفذٍ يحمل معرّفَ الفرع الذي فوقهما. فالعدُّ به يُظهر المنطقةَ
     * والمنفذَ فارغَين ويكدّس عملاءهما على الفرع — يحسبهما كأنّهما فرعٌ واحد.
     * الموضعُ الحقيقيّ في `clients.tree_node_id`، و`COALESCE` تغطّي صفوفاً
     * سبقت العمود.
     *
     * وتُعاد الأعداد بوجهَين: `clients` ما ارتبط بالعقدة نفسها، و`clients_total`
     * هي وما تحتها — ففرعٌ كلُّ عملائه على منافذه كان يبدو فارغاً بلا الثاني.
     *
     * @param  Collection<int, Branch>|null  $allBranches  شجرةٌ محمّلة مسبقاً، وإلّا تُقرأ هنا
     * @return array{clients: array<int,int>, neighborhoods: array<int,int>, clients_total: array<int,int>, neighborhoods_total: array<int,int>}
     */
    private function linkCountsByBranch(?Collection $allBranches = null): array
    {
        $clients = [];
        $nodeExpr = $this->clientNodeExpression();

        if ($nodeExpr !== null) {
            // الجدولُ المشتقُّ لا `GROUP BY` على التعبير: انظر شرحَه في
            // `dashboardDirectCounts` — `ONLY_FULL_GROUP_BY` يرفض التجميعَ
            // على تعبيرٍ يحمل استعلاماً فرعياً مرتبطاً.
            $clients = DB::query()
                ->fromSub(DB::table('clients')->selectRaw($nodeExpr.' as node_id'), 'placed')
                ->selectRaw('node_id, COUNT(*) as c')
                ->whereNotNull('node_id')
                ->groupBy('node_id')
                ->pluck('c', 'node_id')
                ->map(fn ($c) => (int) $c)
                ->all();
        }

        // نفسُ قاعدة العدّ التي في `dashboardDirectCounts` — انظر شرحَها
        // هناك: الجدولُ صفٌّ لكل عميل، فـ`COUNT(*)` يعدّ العملاء لا الأحياء.
        $neighborhoods = Schema::hasTable('neighborhoods')
            ? DB::table('neighborhoods')->select('region_id', DB::raw('COUNT(DISTINCT name) as c'))
                ->whereNotNull('region_id')->groupBy('region_id')
                ->pluck('c', 'region_id')->map(fn ($c) => (int) $c)->all()
            : [];

        $nodes = $allBranches ?? Branch::query()->get(['id', 'parent_id']);

        return [
            'clients' => $clients,
            'neighborhoods' => $neighborhoods,
            'clients_total' => $this->rollUpByTree($clients, $nodes),
            'neighborhoods_total' => $this->rollUpByTree($neighborhoods, $nodes),
        ];
    }

    /**
     * عددُ العملاء التابعين لعقدةٍ أو أكثر — بموضعهم في الهيكل.
     *
     * موضعٌ واحد لقاعدةٍ تتكرّر: `tree_node_id` أوّلاً، و`branch_id` احتياطاً
     * لصفوفٍ سبقت العمود. ولو قُرئ `branch_id` للجميع لعُدّ عميلُ المنفذ ضمن
     * الفرع الذي فوقه فبدا المنفذُ فارغاً.
     *
     * @param  array<int, int|string>  $nodeIds
     */
    private function clientsOnNodes(array $nodeIds): int
    {
        if ($nodeIds === [] || ! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'branch_id')) {
            return 0;
        }

        $query = DB::table('clients');

        if (Schema::hasColumn('clients', 'tree_node_id')) {
            $query->where(function ($w) use ($nodeIds) {
                $w->whereIn('tree_node_id', $nodeIds)
                    ->orWhere(function ($legacy) use ($nodeIds) {
                        $legacy->whereNull('tree_node_id')->whereIn('branch_id', $nodeIds);
                    });
            });
        } else {
            $query->whereIn('branch_id', $nodeIds);
        }

        return (int) $query->count();
    }

    /**
     * تجميعُ عددٍ لكل عقدة صعوداً: كلُّ عقدةٍ تجمع نفسَها وكلَّ ما تحتها.
     *
     * يمشي كلُّ صفٍّ سلسلةَ أسلافه مرّةً واحدة، فالتكلفةُ عددُ العقد × العمق —
     * لا استعلامَ لكل عقدة ولا حسابَ أحفادٍ متكرّر.
     *
     * @param  array<int,int>  $direct
     * @param  Collection<int, Branch>  $nodes
     * @return array<int,int>
     */
    private function rollUpByTree(array $direct, Collection $nodes): array
    {
        $parentOf = $nodes->pluck('parent_id', 'id')->all();
        $total = [];

        foreach ($nodes as $node) {
            $count = $direct[$node->id] ?? 0;
            if ($count === 0) {
                $total[$node->id] = $total[$node->id] ?? 0;

                continue;
            }

            $cursor = (int) $node->id;
            $guard = 0;
            // حدٌّ أقصى للأمان: شجرةٌ تالفةٌ بحلقةٍ كانت ستدور هنا بلا نهاية
            while ($cursor && $guard++ < 50) {
                $total[$cursor] = ($total[$cursor] ?? 0) + $count;
                $cursor = (int) ($parentOf[$cursor] ?? 0);
            }
        }

        return $total;
    }

    /**
     * تصدير الشجرة كاملة إلى CSV — بعمودَي المسار والعمق حتى يبقى الهيكل
     * مقروءاً خارج الشاشة، لا قائمة أسماء مسطّحة.
     */
    public function export()
    {
        Branch::preloadCache();

        $all = Branch::query()->get();
        $byId = $all->keyBy('id');
        $childrenByParent = $all->sortBy('name')->groupBy(fn (Branch $b) => $b->parent_id ?? 0);
        $counts = $this->linkCountsByBranch($all);

        $typeLabels = [
            'branch' => __('branches.node_types.branch_short'),
            'region' => __('branches.node_types.region'),
            'group' => __('branches.node_types.group_short'),
            'outlet' => __('branches.node_types.outlet_short'),
        ];

        $rows = [];
        $walk = function ($parentKey, int $depth) use (&$walk, $childrenByParent, $byId, $counts, $typeLabels, &$rows) {
            foreach ($childrenByParent->get($parentKey, collect()) as $node) {
                $type = $node->node_type ?: ($node->is_territory ? 'group' : 'branch');

                $path = [];
                $cursor = $node;
                $guard = 0;
                while ($cursor && $guard++ < 50) {
                    array_unshift($path, $cursor->name);
                    $cursor = $cursor->parent_id ? $byId->get($cursor->parent_id) : null;
                }

                $rows[] = [
                    $node->code ?: '',
                    str_repeat('— ', $depth).$node->name,
                    $typeLabels[$type] ?? $type,
                    $depth + 1,
                    implode(' ← ', $path),
                    $node->parent_id ? (optional($byId->get($node->parent_id))->name ?? '') : '',
                    ((int) $node->status === 1) ? __('branches.common.inactive') : __('branches.common.active'),
                    $node->is_main ? __('branches.export.yes') : '',
                    $counts['clients'][$node->id] ?? 0,
                    $counts['neighborhoods'][$node->id] ?? 0,
                    $node->phone ?: '',
                    $node->city ?: '',
                ];

                $walk($node->id, $depth + 1);
            }
        };
        $walk(0, 0);

        $headers = array_map(fn ($k) => __('branches.export.'.$k), [
            'code', 'name', 'type', 'level', 'full_path', 'parent',
            'status', 'is_main', 'clients', 'hoods', 'phone', 'city',
        ]);

        $filename = 'branches-tree-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            // BOM: بدونه يفتح Excel العربية كرموز مشوّهة
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * تعيين نفس الأب لعدة فروع دفعة واحدة (نقل تحت فرع أب واحد).
     */
    public function bulkAssignParent(Request $request)
    {
        $request->validate([
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
            'parent_id' => 'nullable|integer|exists:branches,id',
        ], [
            'branch_ids.required' => __('branches.messages.bulk_pick_one'),
            'branch_ids.min' => __('branches.messages.bulk_pick_one'),
        ]);

        $ids = collect($request->input('branch_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values();

        $newParentId = $request->filled('parent_id') ? (int) $request->parent_id : null;
        $idSet = $ids->all();

        if ($newParentId !== null && in_array($newParentId, $idSet, true)) {
            return redirect()->route('branches.index')
                ->with('error', __('branches.messages.bulk_parent_in_selection'));
        }

        $errors = [];
        foreach ($ids as $bid) {
            $branch = Branch::withoutGlobalScopes()->find($bid);
            if (! $branch) {
                $errors[] = __('branches.messages.bulk_branch_missing', ['id' => $bid]);

                continue;
            }
            if ($newParentId !== null && $newParentId === $bid) {
                $errors[] = __('branches.messages.bulk_self_parent', ['name' => $branch->name]);

                continue;
            }
            if ($newParentId !== null && $branch->descendantBranchIds()->contains($newParentId)) {
                $errors[] = __('branches.messages.bulk_cycle', ['name' => $branch->name]);

                continue;
            }
        }

        if ($errors !== []) {
            return redirect()->route('branches.index')->with('error', implode(' ', $errors));
        }

        $movedNames = [];

        try {
            DB::transaction(function () use ($ids, $newParentId, &$movedNames) {
                foreach ($ids as $bid) {
                    $branch = Branch::withoutGlobalScopes()->findOrFail($bid);
                    if ((string) $branch->parent_id === (string) $newParentId) {
                        continue;
                    }
                    $branch->update(['parent_id' => $newParentId]);
                    $branch->refresh();
                    $branch->regenerateSubtreeCodes();
                    // بدون الإفراغ تقرأ الدورة التالية شجرة ما قبل هذا النقل.
                    Branch::flushTreeCaches();
                    $movedNames[] = $branch->name;
                }
            });
        } catch (ValidationException $e) {
            return redirect()->route('branches.index')
                ->with('error', collect($e->errors())->flatten()->first() ?? __('branches.messages.bulk_validation_failed'));
        } catch (\Throwable $e) {
            Log::error('bulkAssignParent: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()->route('branches.index')
                ->with('error', __('branches.messages.bulk_failed', ['error' => config('app.debug') ? $e->getMessage() : __('branches.messages.try_again')]));
        }

        if ($movedNames === []) {
            return redirect()->route('branches.index')
                ->with('success', __('branches.messages.bulk_nothing_changed'));
        }

        $parentLabel = $newParentId
            ? (optional(Branch::find($newParentId))->name ?? ('#'.$newParentId))
            : __('branches.messages.root_level');

        ModelsLog::create([
            'type' => 'branch',
            'type_id' => $newParentId ?? ($ids->first() ?? 0),
            'type_log' => 'log',
            'description' => __('branches.log.bulk_moved', ['count' => count($movedNames), 'parent' => $parentLabel])
                .' — '.implode('، ', array_slice($movedNames, 0, 20))
                .(count($movedNames) > 20 ? ' …' : ''),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('branches.index')
            ->with('success', __('branches.messages.bulk_done', ['count' => count($movedNames), 'parent' => $parentLabel]));
    }

    // عرض نموذج إضافة فرع جديد

    /**
     * تبديلُ الفرع النشط — الحارسُ واحدٌ مع الشاشة.
     *
     * **ما كان**: فحصان مختلفان في دالّةٍ واحدة. الموظفُ يُفحَص فحصاً
     * صحيحاً (`employee_branch` + فرعُه)، ثم **يمرّ غيرُ الموظف بلا فحصٍ
     * إطلاقاً** إلى `$user->branch_id = $branchId; $user->save();` — أي أنّ
     * أيّ حسابٍ غير موظف يبدّل إلى أي فرعٍ بتعديل الرقم في الرابط. وهذا
     * صار أخطرَ بعد أن ربطنا شاشاتِ المخزون والحسابات ونقاط البيع بالفرع
     * النشط: التبديلُ لم يعد تغييرَ عنوانٍ في الشريط بل فتحَ بياناتِ فرع.
     *
     * و«جميع الفروع» كانت محروسةً بـ`hasRoleName('manager')` — مقارنةً
     * نصّيةً باسم دور، تكسرها تسميةٌ عربية أو دورٌ جديد بصمت.
     *
     * **ما صار**: `BranchScope` هو الحَكَم — نفسه الذي تبني به الشاشةُ
     * القائمة، فلا يعرض الشريطُ خياراً يرفضه الخادم ولا العكس.
     */
    public function switchBranch($branchId)
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->back()->with('error', __('branches.messages.login_first'));
        }

        $branchId = (int) $branchId;

        try {
            // «جميع الفروع»: `branch_id = null` — لمالك `data_scope_all` وحده
            if ($branchId === 0) {
                if (! BranchScope::canSwitchToAll()) {
                    return redirect()->back()->with('error', __('branches.messages.not_allowed_all'));
                }

                $user->branch_id = null;
                $user->save();
                BranchScope::forget();

                Log::info('Switched to all branches', ['user_id' => $user->id]);

                return redirect()->back()->with('success', __('branches.messages.switched_all'));
            }

            $branch = Branch::find($branchId);

            if (! $branch) {
                return redirect()->back()->with('error', __('branches.messages.branch_not_found'));
            }

            // الفرعُ المعطَّل يوقع المستخدم في صفحة «الفرع غير نشط» بلا مخرج
            if ((int) $branch->status === 1) {
                return redirect()->back()->with('error', __('branches.messages.branch_inactive', ['name' => $branch->name]));
            }

            if (! BranchScope::canSwitchTo($branchId)) {
                return redirect()->back()->with('error', __('branches.messages.branch_not_allowed'));
            }

            $user->branch_id = $branchId;
            $user->save();

            // القائمةُ تُبنى في الشريط بعد هذا الطلب — والنطاقُ تغيّر للتوّ
            BranchScope::forget();

            Log::info('Branch switched', [
                'user_id' => $user->id,
                'new_branch_id' => $branchId,
                'branch_name' => $branch->name,
            ]);

            return redirect()->back()->with('success', __('branches.messages.branch_switched'));
        } catch (\Exception $e) {
            Log::error('Branch switch failed: '.$e->getMessage());

            return redirect()->back()->with('error', __('branches.messages.switch_failed', ['error' => $e->getMessage()]));
        }
    }

    // تخزين بيانات الفرع
    public function store(Request $request)
    {
        $parentIdForCheck = $request->filled('parent_id') ? (int) $request->parent_id : null;

        $request->validate([
            // تفرّد على مستوى الإخوة لا على مستوى الجدول: «الشمال» تحت الرياض وتحت
            // جدة اسمان مشروعان، لكن اسمان متطابقان تحت الأب نفسه لا يُميَّزان في الشجرة.
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('branches', 'name')->where(
                    fn ($q) => $parentIdForCheck === null
                        ? $q->whereNull('parent_id')
                        : $q->where('parent_id', $parentIdForCheck)
                ),
            ],
            /*
             * الإلزاميُّ واحدٌ لكل الأنواع: الاسمُ والنوع. وكانت أربعةُ حقولٍ
             * (الهاتف والعنوان والمدينة والبلد) إلزاميّةً للفرع والمنفذ دون
             * المنطقة والمجموعة، فيرى المستخدمُ شاشتين بقاعدتين لعملٍ واحد.
             * وما بقي من الحقول اختياريٌّ للجميع — وكلُّها أعمدةٌ تقبل NULL.
             */
            'node_type' => 'required|in:branch,region,group,outlet',
            'is_main' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            /*
                الهاتف والعنوان إلزامٌ للموضع الحقيقيّ وحده.

                أعمدة `phone` و`mobile` و`address1` و`city` و`country` كلّها
                `NOT NULL` بلا قيمةٍ افتراضية، و`ConvertEmptyStringsToNull`
                يحوّل الحقل الفارغ إلى `null` — فكان الحفظ بلا هاتف يسقط بخطأ
                SQL خام لا يسمّي الحقل ولا يفهمه المستخدم، والشاشة تقول له
                فوق ذلك إنّ البطاقة كلّها «اختيارية».

                والإلزام مقصورٌ على الفرع والمنفذ: هما موضعان لهما عنوانٌ
                وهاتف، أمّا المنطقةُ والمجموعةُ فتقسيمٌ تنظيميّ لا مكان له —
                وإلزامُها يدفع المستخدم لاختراع رقم. وما يبقى فارغاً منها
                يُحفظ نصّاً فارغاً لا `null`، فيرضى العمود.
            */
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'work_hours' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'credit_limit' => 'nullable|numeric|min:0',
            'parent_id' => 'nullable|exists:branches,id',
        ], [
            'name.unique' => __('branches.messages.duplicate_name_under_parent'),
        ]);

        $parentId = $parentIdForCheck;
        $nodeType = $request->node_type;
        $isTerritory = $nodeType !== 'branch'; // فرع = لا، الباقي (منطقة/مجموعة/منفذ) = نعم

        try {
            $newCode = Branch::generateTreeCode($parentId);
        } catch (ValidationException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Branches.Branches', 'store');

            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        DB::beginTransaction();

        try {
            // إذا كان الفرع رئيسي، نلغي أي فرع رئيسي آخر
            if ($request->is_main) {
                Branch::where('is_main', true)->update(['is_main' => false]);
            }

            // إنشاء الفرع (كود هرمي: 1 ← 11،12 ← 111…)
            $branch = Branch::create([
                'name' => $request->name,
                'is_main' => $request->is_main ?? false,
                'code' => $newCode,
                // أعمدةٌ `NOT NULL` بلا افتراضي: الفارغُ نصٌّ فارغ لا `null`
                'phone' => (string) $request->phone,
                'mobile' => (string) $request->mobile,
                'address1' => (string) $request->address1,
                'address2' => $request->address2,
                'city' => (string) $request->city,
                'region' => $request->region,
                'country' => (string) $request->country,
                'work_hours' => $request->work_hours,
                'description' => $request->description,
                'credit_limit' => $request->credit_limit ?? 0,
                'parent_id' => $parentId,
                'node_type' => $nodeType,
                'is_territory' => $isTerritory,
                /*
                    الحالة صارت من الشاشة بعد أن كانت مكتوبةً صفراً دائماً،
                    فلا سبيل لإنشاء عنصرٍ معطّل إلّا بفتح شاشة التعديل بعده.
                    و`0` نشِط و`1` معطّل — كما تقرأها الشجرة
                    (`(int) $node->status !== 1`).
                */
                'status' => auth()->user()?->can('branches.toggle_status')
                    ? ($request->boolean('is_active', true) ? 0 : 1)
                    : 0,
            ]);

            // إنشاء الموقع إذا تم تحديده
            if ($request->filled('latitude') && $request->filled('longitude')) {
                Location::create([
                    'branch_id' => $branch->id,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ]);
            }

            /*
             * لا تُكتب أي صفوف إعدادات للفرع الجديد عمداً.
             *
             * الكود القديم كان يكتب `share_cost_center = 0` صراحةً، فيقطع الفرع
             * الجديد عن وراثة أبيه في هذا الإعداد تحديداً من أول لحظة. الآن
             * الفرع بلا ضبط خاص = يرث كل شيء من أبيه (أو الافتراضي إن كان جذراً)،
             * ولا يُكتب له صف إلا حين يضبط المستخدم إعداداً بنفسه.
             */

            // تسجيل الإجراء في السجلات
            ModelsLog::create([
                'type' => 'branch',
                'type_id' => $branch->id,
                'type_log' => 'log',
                'description' => __('branches.log.created', [
                    'name' => $branch->name,
                    'kind' => $branch->is_main ? __('branches.log.kind_main') : __('branches.log.kind_sub'),
                ]),
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('branches.index')->with('success', __('branches.messages.created', [
                'type' => $branch->nodeTypeLabel(),
                'name' => $branch->name,
            ]));

        } catch (ValidationException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Branches.Branches', 'store');

            DB::rollBack();

            // إعادة البيانات مع أخطاء التحقق
            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (QueryException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Branches.Branches', 'store');

            DB::rollBack();

            // تحليل خطأ قاعدة البيانات وعرض رسالة واضحة
            $errorMessage = $this->getDatabaseErrorMessage($e);

            return redirect()->back()
                ->with('error', $errorMessage)
                ->withInput();

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Branches.Branches', 'store');

            DB::rollBack();

            // رسالة خطأ عامة
            return redirect()->back()
                ->with('error', __('branches.messages.unexpected_error', ['error' => $e->getMessage()]))
                ->withInput();
        }
    }

    /**
     * تحليل رسالة خطأ قاعدة البيانات وعرض رسالة واضحة للمستخدم
     */
    private function getDatabaseErrorMessage(QueryException $e)
    {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();

        // تحليل رسالة الخطأ بناءً على كود الخطأ أو المحتوى
        switch ($errorCode) {
            case 23000: // تكامل البيانات (مفتاح مكرر، إلخ)
                if (str_contains($errorMessage, 'Duplicate entry')) {
                    if (str_contains($errorMessage, 'branches_name_unique')) {
                        return __('branches.messages.name_exists');
                    } elseif (str_contains($errorMessage, 'branches_code_unique')) {
                        return __('branches.messages.code_exists');
                    } else {
                        return __('branches.messages.record_exists');
                    }
                } elseif (str_contains($errorMessage, 'Cannot add or update a child row')) {
                    return __('branches.messages.related_data_problem');
                }
                break;

            case 22003: // تجاوز النطاق الرقمي
                return __('branches.messages.value_out_of_range');

            case 22001: // تجاوز طول الحقل
                return __('branches.messages.value_too_long');

            case 23502: // قيمة غير مسموح بها
                return __('branches.messages.missing_required');
        }

        // إذا لم نتمكن من تحديد الخطأ المحدد، نعرض رسالة عامة
        if (config('app.debug')) {
            // في وضع التطوير، نعرض رسالة الخطأ الكاملة
            return __('branches.messages.db_error_prefix', ['error' => $e->getMessage()]);
        } else {
            // في وضع الإنتاج، نعرض رسالة عامة
            return __('branches.messages.db_error');
        }
    }

    public function create(Request $request)
    {
        $parentOptions = $this->branchParentOptions(null);
        $codePreviewIfRoot = Branch::peekNextTreeCode(null) ?? '—';
        $codePreviewByParentId = [];
        foreach (Branch::orderBy('name')->get() as $b) {
            $codePreviewByParentId[$b->id] = Branch::peekNextTreeCode($b->id) ?? '—';
        }

        // «إضافة ابن هنا» من الشجرة يمرّر الأب في الرابط، فيفتح النموذج
        // جاهزاً بدل أن يبحث المستخدم عن الأب في قائمة من 86 عنصراً.
        $presetParent = $request->filled('parent_id')
            ? Branch::find((int) $request->parent_id)
            : null;

        return view('branches::create', compact(
            'parentOptions',
            'codePreviewIfRoot',
            'codePreviewByParentId',
            'presetParent',
        ));
    }

    // عرض تفاصيل الفرع
    public function show($id)
    {
        $branch = Branch::with(['geoLocation', 'parentBranch'])->findOrFail($id);

        $children = Branch::where('parent_id', $branch->id)->orderBy('name')->get();
        $ancestors = $branch->ancestors()->reverse()->values();
        $descendantCount = $branch->descendantBranchIds()->count();

        /* عملاءُ هذه العقدة بموضعهم لا بفرعهم المحاسبيّ — انظر
           `linkCountsByBranch()`. و«تحته» يجمع العقدةَ وكلَّ أحفادها، فمنطقةٌ
           كلُّ عملائها على منافذها لا تبدو فارغة. */
        $subtreeIds = $branch->descendantBranchIds()->push($branch->id)->unique()->all();

        $stats = [
            'clients' => $this->clientsOnNodes([$branch->id]),
            'clients_subtree' => $this->clientsOnNodes($subtreeIds),
            'employees' => DB::table('employees')->where('branch_id', $branch->id)->count(),
            'neighborhoods' => DB::table('neighborhoods')->where('region_id', $branch->id)->count(),
            'neighborhoods_subtree' => Schema::hasTable('neighborhoods')
                ? (int) DB::table('neighborhoods')->whereIn('region_id', $subtreeIds)->count()
                : 0,
        ];

        // الإعدادات المضبوطة فعلاً — الخاصة بالفرع أو الموروثة من أبٍ أعلى.
        // ما بقي على الافتراضي لا يُعرض هنا حتى لا تغرق البطاقة في ٥٠ بنداً
        // لا يميّز فيها المستخدم ما ضُبط عمداً عمّا لم يُلمس أصلاً.
        $settings = collect(BranchSettingsService::definitions())
            ->map(fn ($definition) => BranchSettingsService::resolve($branch->id, $definition->key))
            ->filter(fn ($resolved) => $resolved && $resolved['source'] !== 'default')
            ->map(fn ($resolved) => [
                'name' => $resolved['definition']->name,
                'display' => $resolved['definition']->display($resolved['value']),
                'source' => $resolved['source'],
                'source_branch' => $resolved['source_branch']?->name,
            ])
            ->values();

        return view('branches::show', compact(
            'branch',
            'children',
            'ancestors',
            'descendantCount',
            'stats',
            'settings',
        ));
    }

    // عرض نموذج تعديل الفرع
    public function edit($id)
    {
        // استرجاع الفرع بواسطة الـ id
        $branch = Branch::findOrFail($id);
        $parentOptions = $this->branchParentOptions((int) $id);

        return view('branches::edit', compact('branch', 'parentOptions'));
    }

    // تحديث بيانات الفرع
    public function update(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);
        $oldParentId = $branch->parent_id;
        $newParentId = $request->filled('parent_id') ? (int) $request->parent_id : null;
        $parentChanged = (string) $oldParentId !== (string) $newParentId;

        $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('branches', 'name')->ignore($id)->where(
                    fn ($q) => $newParentId === null
                        ? $q->whereNull('parent_id')
                        : $q->where('parent_id', $newParentId)
                ),
            ],
            /*
             * الإلزاميُّ واحدٌ لكل الأنواع: الاسمُ والنوع. وكانت أربعةُ حقولٍ
             * (الهاتف والعنوان والمدينة والبلد) إلزاميّةً للفرع والمنفذ دون
             * المنطقة والمجموعة، فيرى المستخدمُ شاشتين بقاعدتين لعملٍ واحد.
             * وما بقي من الحقول اختياريٌّ للجميع — وكلُّها أعمدةٌ تقبل NULL.
             */
            'node_type' => 'required|in:branch,region,group,outlet',
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('branches', 'code')->ignore($id),
            ],
            'is_main' => 'nullable|boolean',
            // كما في `store()`: أعمدةٌ `NOT NULL`، وإلزامُها على الموضع الحقيقيّ
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'work_hours' => 'nullable|string',
            'description' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
            'parent_id' => [
                'nullable',
                'exists:branches,id',
                Rule::notIn([(int) $id]),
                function (string $attribute, mixed $value, \Closure $fail) use ($id) {
                    if (! $value) {
                        return;
                    }
                    $current = Branch::find($id);
                    if ($current && $current->descendantBranchIds()->contains((int) $value)) {
                        $fail(__('branches.messages.parent_in_subtree'));
                    }
                },
            ],
        ], [
            'name.unique' => __('branches.messages.duplicate_name_under_parent'),
        ]);

        $oldName = $branch->name;
        $wasMain = $branch->is_main;

        // معالجة حالة الفرع الرئيسي
        if ($request->is_main) {
            Branch::where('is_main', true)->where('id', '!=', $id)->update(['is_main' => false]);
        } elseif ($wasMain && ! $request->is_main) {
            $otherMainBranches = Branch::where('is_main', true)->where('id', '!=', $id)->count();
            if ($otherMainBranches == 0) {
                return back()->with('error', __('branches.messages.need_one_main'));
            }
        }

        $nodeType = $request->node_type;
        $isTerritory = $nodeType !== 'branch'; // فرع = لا، الباقي (منطقة/مجموعة/منفذ) = نعم

        $payload = [
            'name' => $request->name,
            'is_main' => $request->is_main ?? false,
            // أعمدةٌ `NOT NULL` بلا افتراضي: الفارغُ نصٌّ فارغ لا `null`
            'phone' => (string) $request->phone,
            'mobile' => (string) $request->mobile,
            'address1' => (string) $request->address1,
            'address2' => $request->address2,
            'city' => (string) $request->city,
            'region' => $request->region,
            'country' => (string) $request->country,
            'work_hours' => $request->work_hours,
            'description' => $request->description,
            'credit_limit' => $request->credit_limit ?? 0,
            'parent_id' => $newParentId,
            'node_type' => $nodeType,
            'is_territory' => $isTerritory,
        ];
        if (! $parentChanged) {
            $payload['code'] = $request->code;
        }

        try {
            DB::transaction(function () use ($branch, $payload, $parentChanged) {
                $branch->update($payload);
                if ($parentChanged) {
                    $branch->refresh();
                    $branch->regenerateSubtreeCodes();
                    Branch::flushTreeCaches();
                }
            });
        } catch (ValidationException $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Branches.Branches', 'update');

            return back()->withErrors($e->errors())->withInput();
        }

        $branch->refresh();

        // تحديث أو إنشاء الموقع
        if ($request->filled('latitude') && $request->filled('longitude')) {
            $locationData = [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'branch_id' => $branch->id,
            ];

            // كان الشرط يقرأ عمود branches.location النصّي (فارغ دائماً) بدل السجل،
            // فيُنشئ صف موقع جديد عند كل حفظ ولا يُحدّث القديم ولا يُحذف أبداً.
            if ($branch->geoLocation) {
                $branch->geoLocation->update($locationData);
            } else {
                Location::create($locationData);
            }
            $branch->unsetRelation('geoLocation');
        } else {
            Location::where('branch_id', $branch->id)->delete();
        }

        // تسجيل الإجراء في السجلات
        $logDescription = __('branches.log.updated', ['old' => $oldName, 'new' => $branch->name, 'extra' => '']);

        if ($wasMain != $branch->is_main) {
            $logDescription .= $branch->is_main ? __('branches.log.set_main') : __('branches.log.unset_main');
        }

        ModelsLog::create([
            'type' => 'branch',
            'type_id' => $branch->id,
            'type_log' => 'log',
            'description' => $logDescription,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('branches.index')->with('success', __('branches.messages.updated', [
            'type' => $branch->nodeTypeLabel(),
            'name' => $branch->name,
        ]));
    }

    // حذف الفرع
    public function destroy($id)
    {
        $branch = Branch::findOrFail($id);
        if ($branch->childBranches()->exists()) {
            return redirect()->route('branches.index')
                ->with('error', __('branches.messages.has_children'));
        }

        // الفرع الرئيسي هو مرجع النظام؛ حذفه يترك التطبيق بلا فرع افتراضي.
        if ($branch->is_main) {
            return redirect()->route('branches.index')
                ->with('error', __('branches.messages.is_main_cannot_delete'));
        }

        // كان الحذف يتم بلا فحص للارتباطات، فتبقى سجلات تشير إلى فرع غير موجود.
        $blockers = [];

        /* العملاءُ يُفحصون بموضعهم (`tree_node_id`) لا بفرعهم المحاسبيّ.
           فبالفحص القديم كانت **منطقةٌ لها خمسون عميلاً تُحذَف بلا اعتراض**:
           `branch_id` عندهم يشير إلى الفرع الذي فوقها لا إليها، والمفتاحُ
           الأجنبيّ `set null` كان يمسح تبعيّتهم بصمت. */
        $clientsHere = $this->clientsOnNodes([$branch->id]);
        if ($clientsHere > 0) {
            $blockers[] = $clientsHere.' '.__('branches.messages.clients_word');
        }

        foreach ([
            'employees' => ['branch_id', __('branches.messages.employees_word')],
            'neighborhoods' => ['region_id', __('branches.messages.hoods_word')],
        ] as $table => [$column, $label]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $count = (int) DB::table($table)->where($column, $branch->id)->count();
            if ($count > 0) {
                $blockers[] = "{$count} {$label}";
            }
        }

        if ($blockers !== []) {
            return redirect()->route('branches.index')->with(
                'error',
                __('branches.messages.has_links', ['name' => $branch->name, 'links' => implode(' + ', $blockers)])
            );
        }

        $name = $branch->name;
        // التسميةُ تُقرأ قبل الحذف: الصفُّ لن يبقى لتُقرأ منه بعده
        $deletedName = $branch->name;
        $deletedTypeLabel = $branch->nodeTypeLabel();

        try {
            DB::transaction(function () use ($branch) {
                // بيانات تابعة للفرع لا معنى لها بعده — كانت تُترك يتيمة في الجداول.
                BranchSettingBranch::where('branch_id', $branch->id)->delete();
                Location::where('branch_id', $branch->id)->delete();
                $branch->delete();
            });
        } catch (\Throwable $e) {
            Log::error('branches.destroy: '.$e->getMessage());

            return redirect()->route('branches.index')
                ->with('error', __('branches.messages.delete_failed', ['error' => config('app.debug') ? $e->getMessage() : __('branches.messages.try_again')]));
        }

        ModelsLog::create([
            'type' => 'branch',
            'type_id' => $id,
            'type_log' => 'log',
            'description' => __('branches.log.deleted', ['name' => $name]),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('branches.index')->with('success', __('branches.messages.deleted', [
            'type' => $deletedTypeLabel,
            'name' => $deletedName,
        ]));
    }

    /**
     * قائمة الفروع لقوائم الاختيار: كل عنصر خيارٌ واحد يحمل مساره كاملاً.
     *
     *   فرع الرياض
     *   فرع الرياض ← شرق الرياض
     *   فرع الرياض ← شرق الرياض ← اشبيليا
     *   فرع جدة
     *
     * الإزاحة وحدها لا تكفي: القائمة المنسدلة تُغلَق على السطر المختار فيرى
     * المستخدم «اشبيليا» بلا سياق ولا يعرف تحت أي فرع اختارها — والأسماء
     * المكرَّرة بين الفروع تجعل الاختيار تخميناً. المسار الكامل في السطر
     * نفسه يحسم ذلك سواء كانت القائمة مفتوحة أو مغلقة.
     *
     * الترتيب يبقى ترتيب الشجرة (أب ثم أبناؤه) فتظل عناصر الفرع متجاورة.
     *
     * يستثني الفرع الحالي وجميع أحفاده عند التعديل لتجنب حلقة في الشجرة.
     *
     * @return Collection<int, array{id: int, label: string, depth: int, name: string}>
     */
    private function branchParentOptions(?int $excludeBranchId): Collection
    {
        $forbidden = collect();
        if ($excludeBranchId) {
            $ex = Branch::find($excludeBranchId);
            $forbidden = $ex ? $ex->descendantBranchIds()->push($excludeBranchId) : collect([$excludeBranchId]);
        }

        $all = Branch::query()->orderBy('name')->get();
        $childrenByParent = $all->groupBy(fn (Branch $b) => $b->parent_id ?? 0);

        $options = collect();
        $visited = [];

        // المسار يُبنى أثناء المشي: لا استعلام ولا صعود للأسلاف لكل عنصر.
        $walk = function ($parentKey, int $depth, array $trail) use (&$walk, $childrenByParent, $forbidden, &$options, &$visited) {
            foreach ($childrenByParent->get($parentKey, collect()) as $node) {
                // تخطّي المستبعَد يتخطّى شجرته كلها: كل أحفاده ممنوعون أيضاً.
                if ($forbidden->contains($node->id)) {
                    continue;
                }

                $path = array_merge($trail, [$node->name ?: __('branches.messages.unnamed_node')]);

                $visited[$node->id] = true;
                $options->push($this->branchOptionRow($node, $depth, $path));
                $walk($node->id, $depth + 1, $path);
            }
        };

        $walk(0, 0, []);

        // عنصر أبوه مفقود من الجدول لا يصله المشي من الجذر، وحذفه من القائمة
        // يخفيه عن المستخدم بلا سبب — يُلحق في آخرها كجذر.
        foreach ($all as $node) {
            if (isset($visited[$node->id]) || $forbidden->contains($node->id)) {
                continue;
            }
            $options->push($this->branchOptionRow($node, 0, [$node->name ?: __('branches.messages.unnamed_node')]));
        }

        return $options->values();
    }

    /**
     * سطر واحد في قائمة الفروع: المسار من الجذر حتى العنصر.
     *
     * الفاصل ` ← ` هو نفسه المستعمل في `Branch::lineagePathLabel()` وفي بقية
     * شاشات الموديول، فيقرأ المستخدم المسار بنفس الشكل أينما ظهر.
     *
     * الكود لا يُضاف للتسمية: أكواد المجموعات المولّدة تلقائياً من نوع
     * `TR-35-0bf4079f` تُطيل السطر وتحجب المسار الذي هو المقصود هنا.
     *
     * @param  array<int, string>  $path
     * @return array{id: int, label: string, depth: int, name: string}
     */
    private function branchOptionRow(Branch $node, int $depth, array $path): array
    {
        return [
            'id' => $node->id,
            'depth' => $depth,
            'name' => $node->name ?: __('branches.messages.unnamed_node'),
            'label' => implode(' ← ', $path),
        ];
    }

    /**
     * شاشة إعدادات الفروع: كل إعدادات فرع مختار، مجمّعة حسب التصنيف.
     *
     * الشاشة القديمة كانت تعرض قائمة مسطّحة من مفاتيح بوليانية بلا شرح ولا
     * تصنيف ولا أي بيان بمن يرث ماذا. الآن الشاشة تعرض القيمة المحسومة لكل
     * إعداد ومصدرها (خاص/موروث/افتراضي) عبر `BranchSettingsService`.
     */
    public function settings(Request $request)
    {
        $branchs = Branch::orderBy('name')->get();

        $selectedBranchId = $request->filled('branch_id')
            ? (int) $request->input('branch_id')
            : (int) (auth()->user()->branch_id ?: ($branchs->first()->id ?? 0));

        $branch = $selectedBranchId ? $branchs->firstWhere('id', $selectedBranchId) : null;
        $selectedBranchId = $branch?->id;

        $activeGroup = $request->input('group');
        $groupsMeta = BranchSettingsCatalog::groups();
        $grouped = $branch ? BranchSettingsService::grouped($branch->id) : [];

        // تصنيفات موجودة في البيانات ولا تعريف لها في الكتالوج (مفاتيح قديمة
        // أو أضافها تركيب سابق) — تُعرض تحت «أخرى» بدل أن تختفي من الشاشة.
        foreach (array_keys($grouped) as $groupKey) {
            if (! isset($groupsMeta[$groupKey])) {
                $groupsMeta[$groupKey] = [
                    'label' => __('branches.messages.other_settings'),
                    'icon' => 'more-horizontal',
                    'hint' => __('branches.messages.other_settings_hint'),
                ];
            }
        }

        // ترتيب التصنيفات كما في الكتالوج، مع إسقاط الفارغ منها.
        $groupsMeta = array_intersect_key($groupsMeta, $grouped);

        if (! $activeGroup || ! isset($grouped[$activeGroup])) {
            $activeGroup = array_key_first($groupsMeta);
        }

        return view('branches::settings', [
            'branchs' => $branchs,
            // قوائم هذه الشاشة بترتيب الشجرة نفسه: الابن تحت أبيه بمسافة
            // بادئة — الإعدادات تُورَّث عبر الشجرة، فقائمةٌ أبجدية تُخفي
            // العلاقة التي يقوم عليها التوريث كله.
            'branchOptions' => $this->branchParentOptions(null),
            'branch' => $branch,
            'selectedBranchId' => $selectedBranchId,
            'groupsMeta' => $groupsMeta,
            'grouped' => $grouped,
            'activeGroup' => $activeGroup,
            'parentBranch' => $branch?->parentBranch,
            'ownCount' => $branch ? BranchSettingsService::ownCount($branch->id) : 0,
            'childCount' => $branch ? max(0, $branch->descendantBranchIds()->count()) : 0,
        ]);
    }

    /**
     * حفظ إعدادات الفرع المختار.
     *
     * الحفظ يقتصر على المفاتيح التي عرضها التبويب المفتوح (`__present__`)،
     * فلا يمحو تبويبٌ إعداداتِ تبويب آخر لم يكن معروضاً أصلاً.
     */
    public function settings_store(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $branch = Branch::findOrFail($request->branch_id);
        $present = array_values(array_filter((array) $request->input('__present__', [])));

        if ($present === []) {
            return redirect()
                ->route('branches.settings', ['branch_id' => $branch->id])
                ->with('error', __('branches.messages.settings_none_sent'));
        }

        $this->validateSettingInput($request, $present);

        $result = BranchSettingsService::saveForBranch(
            $branch,
            $request->all(),
            $present,
            auth()->id(),
        );

        // «طبّق على الفروع التابعة»: يُلغى الضبط الخاص لدى كل تابع لهذه
        // المفاتيح، فيصير هذا الفرع أقرب مضبوط في السلسلة ويتبعه الجميع —
        // ويظلّون تابعين له في أي تعديل لاحق، لا نسخة مجمّدة من قيمة اليوم.
        $forced = ['branches' => 0, 'rows' => 0];
        if ($request->boolean('apply_to_descendants')) {
            $forced = BranchSettingsService::forceOnDescendants($branch, $present);
        }

        $changedCount = count($result['changed']);
        $clearedCount = count($result['cleared']);

        if ($changedCount || $clearedCount || $forced['branches']) {
            $details = [];
            if ($changedCount) {
                $details[] = __('branches.log.settings_edited', ['count' => $changedCount, 'names' => implode('، ', array_slice($result['changed'], 0, 5)).($changedCount > 5 ? '، …' : '')]);
            }
            if ($clearedCount) {
                $details[] = __('branches.log.settings_inherited', ['count' => $clearedCount]);
            }
            if ($forced['branches']) {
                $details[] = __('branches.log.settings_forced', ['count' => $forced['branches']]);
            }

            ModelsLog::create([
                'type' => 'branch',
                'type_id' => $branch->id,
                'type_log' => 'log',
                'description' => __('branches.log.settings_changed', ['name' => $branch->name, 'details' => implode('، ', $details)]),
                'created_by' => auth()->id(),
            ]);
        }

        $message = $changedCount || $clearedCount
            ? __('branches.messages.settings_saved', ['name' => $branch->name])
            : __('branches.messages.settings_unchanged');

        if ($request->boolean('apply_to_descendants')) {
            $message .= $forced['branches']
                ? ' '.__('branches.messages.settings_cascaded', ['count' => $forced['branches']])
                : ' '.__('branches.messages.settings_cascaded_none');
        }

        return redirect()
            ->route('branches.settings', ['branch_id' => $branch->id, 'group' => $request->input('group')])
            ->with('success', $message);
    }

    /**
     * نسخ إعدادات فرع إلى فروع أخرى (أو إلى كل أبنائه في الشجرة).
     */
    public function settingsCopy(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'mode' => 'required|in:selected,descendants',
            'target_ids' => 'nullable|array',
            'target_ids.*' => 'integer|exists:branches,id',
            'groups' => 'nullable|array',
            'groups.*' => 'string',
        ]);

        $source = Branch::findOrFail($data['branch_id']);

        $targetIds = $data['mode'] === 'descendants'
            ? $source->descendantBranchIds()->map(fn ($id) => (int) $id)->all()
            : array_map('intval', $data['target_ids'] ?? []);

        if ($targetIds === []) {
            return redirect()
                ->route('branches.settings', ['branch_id' => $source->id])
                ->with('error', $data['mode'] === 'descendants'
                    ? __('branches.messages.copy_no_descendants', ['name' => $source->name])
                    : __('branches.messages.copy_no_targets'));
        }

        $copied = BranchSettingsService::copyTo(
            $source,
            $targetIds,
            $data['groups'] ?? null,
            auth()->id(),
        );

        if ($copied === 0) {
            return redirect()
                ->route('branches.settings', ['branch_id' => $source->id])
                ->with('error', __('branches.messages.copy_nothing_own', ['name' => $source->name]));
        }

        ModelsLog::create([
            'type' => 'branch',
            'type_id' => $source->id,
            'type_log' => 'log',
            'description' => __('branches.log.settings_copied', ['name' => $source->name, 'count' => count($targetIds)]),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('branches.settings', ['branch_id' => $source->id])
            ->with('success', __('branches.messages.copied', ['branches' => count($targetIds), 'values' => $copied]));
    }

    /**
     * إعادة إعدادات فرع إلى الافتراضي — حذف ضبطه الخاص ليعود إلى وراثة أبيه.
     */
    public function settingsReset(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'groups' => 'nullable|array',
            'groups.*' => 'string',
        ]);

        $branch = Branch::findOrFail($data['branch_id']);
        $deleted = BranchSettingsService::reset($branch, $data['groups'] ?? null);

        if ($deleted === 0) {
            return redirect()
                ->route('branches.settings', ['branch_id' => $branch->id])
                ->with('error', __('branches.messages.reset_nothing'));
        }

        ModelsLog::create([
            'type' => 'branch',
            'type_id' => $branch->id,
            'type_log' => 'log',
            'description' => __('branches.log.settings_reset', ['name' => $branch->name, 'count' => $deleted]),
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('branches.settings', ['branch_id' => $branch->id])
            ->with('success', __('branches.messages.reset_done', ['count' => $deleted]));
    }

    /**
     * قراءة إعدادات فرع كـ JSON — تُستعمل من الشاشات الأخرى وواجهات الأجهزة.
     */
    public function getSettings(Request $request)
    {
        $branchId = $request->input('branch_id') ?: (auth()->user()->branch_id ?? null);

        if (! $branchId) {
            return response()->json(['settings' => [], 'values' => []]);
        }

        $settings = [];
        $values = [];

        foreach (BranchSettingsService::definitions() as $definition) {
            $resolved = BranchSettingsService::resolve((int) $branchId, $definition->key);

            $settings[] = [
                'id' => $definition->id,
                'key' => $definition->key,
                'name' => $definition->name,
                'group' => $definition->group,
                'type' => $definition->type,
                'value' => $resolved['value'],
                'source' => $resolved['source'],
                // `status` مُبقى للتوافق مع أي مستهلك قديم لهذه الواجهة.
                'status' => $definition->isBoolean() ? (int) $resolved['value'] : 1,
            ];

            $values[$definition->key] = $resolved['value'];
        }

        return response()->json([
            'branch_id' => (int) $branchId,
            'settings' => $settings,
            'values' => $values,
        ]);
    }

    /**
     * تحقّق من قيم الإعدادات المرسلة، كلٌّ حسب نوعه وحدوده في الكتالوج.
     *
     * @param  array<int, string>  $present
     */
    protected function validateSettingInput(Request $request, array $present): void
    {
        $rules = [];
        $names = [];

        foreach ($present as $key) {
            $definition = BranchSettingsService::definitions()->get($key);

            if (! $definition || $definition->isBoolean()) {
                continue; // البوليانية: الغياب = مغلق، لا شيء يُتحقَّق منه
            }

            if ($request->boolean('__inherit__'.$key)) {
                continue; // سيُحذف الضبط الخاص، فلا معنى للتحقّق من قيمته
            }

            $options = $definition->options ?? [];
            $names[$key] = $definition->name;

            $rules[$key] = match ($definition->type) {
                'number' => array_filter([
                    'nullable',
                    'numeric',
                    isset($options['min']) ? 'min:'.$options['min'] : null,
                    isset($options['max']) ? 'max:'.$options['max'] : null,
                ]),
                'date' => ['nullable', 'date'],
                'time' => ['nullable', 'date_format:H:i'],
                'select' => ['nullable', Rule::in(array_keys($definition->choices()))],
                'textarea' => ['nullable', 'string', 'max:'.($options['maxlength'] ?? 1000)],
                default => array_filter([
                    'nullable',
                    ($options['input'] ?? null) === 'email' ? 'email' : 'string',
                    'max:'.($options['maxlength'] ?? 191),
                ]),
            };
        }

        if ($rules !== []) {
            $request->validate($rules, [], $names);
        }
    }

    public function updateStatus($id)
    {
        $userBranchId = auth()->user()->branch_id; // جلب معرف الفرع الخاص بالمستخدم

        $branch = Branch::findOrFail($id);

        // منع المستخدم من إيقاف الفرع الذي ينتمي إليه
        if ($userBranchId == $branch->id) {
            return redirect()->route('branches.index')->with('error', __('branches.messages.cannot_disable_own'));
        }

        // تعطيل الفرع الرئيسي يحجب كل من فرعه الافتراضي هذا الفرع خلف صفحة
        // "الفرع غير نشط" — نمنعه بدل اكتشافه بعد فوات الأوان.
        if ($branch->status == 0 && $branch->is_main) {
            return redirect()->route('branches.index')
                ->with('error', __('branches.messages.cannot_disable_main'));
        }

        // تبديل الحالة فقط إذا كان الفرع مختلفًا عن فرع المستخدم
        $branch->update(['status' => $branch->status == 0 ? 1 : 0]);
        // تحديد النص بناءً على حالة الفرع
        $statusText = $branch->status == 1
            ? __('branches.log.status_disabled', ['name' => $branch->name])
            : __('branches.log.status_enabled', ['name' => $branch->name]);

        ModelsLog::create([
            'type' => 'branch',
            'type_id' => $branch->id, // ID النشاط المرتبط
            'type_log' => 'log', // نوع النشاط
            'description' => $statusText.' **'.$branch->name.'**',
            'created_by' => auth()->id(), // ID المستخدم الحالي
        ]);

        return redirect()->route('branches.index')->with('success', __('branches.messages.status_updated'));
    }

    /**
     * ترحيل جدول region_groubs (إن وُجد) إلى branches وحذف الجدول القديم.
     */
    public function migrateRegionGroubsForm()
    {
        $hasTable = Schema::hasTable('region_groubs');
        $rowCount = $hasTable ? (int) DB::table('region_groubs')->count() : 0;

        $rowsWithParent = 0;
        $rowsWithoutParent = 0;
        $clientsLinked = 0;
        $neighborhoodsLinked = 0;

        if ($hasTable && $rowCount > 0) {
            $rowsWithParent = (int) DB::table('region_groubs as rg')
                ->join('branches as b', 'rg.branch_id', '=', 'b.id')
                ->count();
            $rowsWithoutParent = $rowCount - $rowsWithParent;

            $regionIds = DB::table('region_groubs')->pluck('id')->all();
            if (! empty($regionIds)) {
                $neighborhoodsLinked = (int) DB::table('neighborhoods')
                    ->whereIn('region_id', $regionIds)
                    ->count();

                $clientsViaNeighborhood = DB::table('neighborhoods')
                    ->whereIn('region_id', $regionIds)
                    ->whereNotNull('client_id')
                    ->pluck('client_id');
                $clientsViaGroup = collect();
                if (Schema::hasColumn('clients', 'group_id')) {
                    $clientsViaGroup = DB::table('clients')->whereIn('group_id', $regionIds)->pluck('id');
                }
                $clientsLinked = $clientsViaNeighborhood->merge($clientsViaGroup)->unique()->filter()->count();
            }
        }

        return view('branches::migrate_region_groubs', compact(
            'hasTable',
            'rowCount',
            'rowsWithParent',
            'rowsWithoutParent',
            'clientsLinked',
            'neighborhoodsLinked',
        ));
    }

    public function migrateRegionGroubsSubmit(Request $request, RegionGroubsToBranchesMigrationService $service)
    {
        $validator = Validator::make($request->all(), [
            'dry_run' => 'nullable|boolean',
            'keep_table' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->route('branches.migrate_region_groubs')->withErrors($validator);
        }

        $result = $service->migrate(
            (bool) $request->boolean('dry_run'),
            ! $request->boolean('keep_table'),
        );

        $details = __('branches.migrate.summary', [
            'rows' => $result['region_groubs_count'],
            'new' => $result['inserted'],
            'reused' => $result['reused_existing'],
            'skipped' => $result['skipped_no_parent'],
            'dropped' => $result['dropped_table'] ? __('branches.export.yes') : '-',
        ]);

        if ($request->boolean('dry_run') && ($result['dry_run_clients_linked'] ?? 0) > 0) {
            $details .= __('branches.migrate.summary_dry_clients', ['count' => $result['dry_run_clients_linked']]);
        }

        if (! $request->boolean('dry_run') && $result['region_groubs_count'] > 0) {
            $details .= __('branches.migrate.summary_fk', [
                'hoods' => $result['fk_updates']['neighborhoods'],
                'clients' => $result['fk_updates']['clients_group'],
                'employees' => $result['fk_updates']['employee_group'],
            ]);
            if (($result['clients_synced'] ?? 0) > 0) {
                $details .= __('branches.migrate.summary_synced', ['count' => $result['clients_synced']]);
            }
        }

        return redirect()->route('branches.migrate_region_groubs')
            ->with('success', $result['message'].' — '.$details);
    }
}
