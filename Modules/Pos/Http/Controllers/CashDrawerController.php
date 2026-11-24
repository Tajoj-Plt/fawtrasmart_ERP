<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\PosSession;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Pos\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Services\Session\CashDrawerService;

/**
 * شاشةُ الدرج النقدي — إيداعٌ وسحبٌ وترحيلٌ وعدّ.
 *
 * وحدةٌ كاملةٌ لم تكن موجودة: الجلسةُ تُفتح برصيدٍ افتتاحي وتُقفل برصيدٍ
 * فعلي، وبينهما **لا سبيل لإخراج ريالٍ أو إدخاله**. فكانت هذه الحركاتُ
 * تقع خارج النظام ثم تظهر فرقاً في الإقفال لا تفسير له — والفرقُ الذي
 * وُضع ليقيس الخطأ صار يقيس المشروعَ غيرَ المسجَّل.
 *
 * انظر [CashDrawerService] لتفصيل القيود.
 */
class CashDrawerController extends Controller
{
    use RespectsAccountingPeriods;
    use ScopesToPosAccess;
    use UsesDocumentCurrency;

    public function __construct(private CashDrawerService $drawer)
    {
        /*
            ═══════════════ بوّابةُ الوحدة ═══════════════

            الدخولُ لمن يملك **أيَّ** صلاحيةِ نقاط بيع، لا لمن يملك صلاحيةً
            بعينها.

            كان الفحصُ بصلاحيةٍ واحدة، وهو يقلب المقصود: كاشيرٌ يملك البيعَ
            وحده يُردّ من باب الشاشة التي هي عمله، ومشرفٌ يملك التقارير
            وحدها كذلك. والحراسةُ الدقيقة موضعُها الدالّة لا الباب.

            والبادئاتُ ثلاثٌ لا واحدة (`pos.` و`pos_` و`points_sale_`):
            إغفالُ إحداها يردّ كلَّ من صلاحياتُه بالنمط القديم.
        */
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            $allowed = collect(PosPermissions::PREFIXES)
                ->contains(fn ($prefix) => $user?->hasAnyPermissionWithPrefix($prefix));

            if (! $allowed) {
                abort(403, 'لا تملك أي صلاحية على وحدة نقاط البيع.');
            }

            return $next($request);
        });

        /*
            ═══════════════ حراسةُ كل دالّة ═══════════════

            مطابقةٌ حرفياً لما يفرضه `Modules/Pos/routes/web.php`. والتكرارُ
            مقصود: الحارسُ على المسار وحده يسقط متى نُودي المتحكّمُ من مسارٍ
            جديد يُنسى تزويدُه، أو من وحدةٍ أخرى تستدعي الدالّة مباشرةً.
        */
        $this->middleware('permission.role:pos.cash_drawer.view', ['only' => ['index', 'sessionSummary']]);
        $this->middleware('permission.role:pos.cash_drawer.pay_in|pos.cash_drawer.pay_out|pos.cash_drawer.drop', ['only' => [
            'store',
        ]]);
        $this->middleware('permission.role:pos.cash_drawer.approve', ['only' => ['approve', 'reject']]);
    }

    /** صلاحيةُ كل نوعِ حركة — يخدم إجراءٌ واحدٌ أربعةَ أنواع. */
    private const PERMISSION_BY_TYPE = [
        PosCashMovement::TYPE_PAY_IN => 'pos.cash_drawer.pay_in',
        PosCashMovement::TYPE_PAY_OUT => 'pos.cash_drawer.pay_out',
        PosCashMovement::TYPE_DROP => 'pos.cash_drawer.drop',
        PosCashMovement::TYPE_COUNT => 'pos.cash_drawer.view',
    ];

    public function index(Request $request)
    {
        $query = PosCashMovement::with([
            'session:id,session_number,user_id',
            'session.user:id,name',
            'device:id,device_name',
            'branch:id,name',
            'creator:id,name',
            'approver:id,name',
            'counterpartAccount:id,name,code',
        ]);

        // النطاق: من أنشأ ومن يديرهم — بنفس قاعدة الجلسات حرفياً
        $this->scopeMovements($query);

        $period = $this->posPeriod($request);
        $this->scopeToPosPeriod($query, $period);

        $query
            ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->session_id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('movement_time', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('movement_time', '<=', $request->date_to))
            ->when($this->filterCurrency($request), fn ($q, $c) => $q->where('currency', $c));

        /*
            حجمُ الصفحة من الطلب لا ثابتاً في الشيفرة.

            كانت `paginate(20)` مثبَّتة، ومُنتقي «عرض … سجلاً» في الشاشة
            يُرسل `per_page` فلا يُقرأ — فيبدو المنتقي معطَّلاً.

            ومقيَّدٌ بقائمةٍ مغلقة: `per_page=100000` من شريط العنوان يجلب
            الجدولَ كلَّه في استعلامٍ واحد ويُسقط الصفحة.
        */
        $perPage = (int) $request->input('per_page', 20);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 20;

        $movements = $query->orderByDesc('movement_time')->paginate($perPage)->withQueryString();

        return view('pos::cash_drawer.index', [
            'movements' => $movements,
            'totals' => $this->totals($request, $period),
            'types' => $this->typeOptions(),
            'branches' => $this->branchOptions(),

            /*
                حساباتُ المصروف لسحبِ الدرج، وخزائنُ المستخدم للترحيل
                والإيداع — كلٌّ لنوعه، فقائمةٌ واحدةٌ تخلط مصروفاً بخزينة.
            */
            'expenseAccounts' => $this->expenseAccountOptions(),
            // `accessibleTreasuries()` تُرجع **استعلاماً** لا مجموعة
            'treasuries' => $this->accessibleTreasuries(['id', 'name', 'code'])->get(),
            'currencies' => $this->activeCurrencies(),
            'selectedCurrency' => $this->filterCurrency($request),
            'period' => $period,
            /*
                ═══════════ الجلسةُ التي يُدار درجُها ═══════════

                كانت جلسةَ المستخدم النشطة وحدها. وهو صحيحٌ للكاشير، وخطأٌ
                لمن فوقه:

                  • مشرفٌ يدير خمسةَ كاشيرين لا جلسةَ له — فلا يرى نموذجَ
                    التسجيل إطلاقاً، ولا يُودع فكّةً في درجِ أحدهم.
                  • ومديرٌ ليس موظّفاً أصلاً — كذلك.

                فصارت **قائمةَ اختيار** من الجلسات المفتوحة في نطاق رؤيته،
                وافتراضُها جلستُه هو إن كانت له.
            */
            'openSessions' => $this->selectableSessions(),
            'activeSession' => $this->drawerSession($request),
            'canApprove' => hasPermission('pos.cash_drawer.approve') || $this->hasFullPosScope(),
        ]);
    }

    /**
     * الجلساتُ المفتوحة التي يجوز لهذا المستخدم إدارةُ درجها.
     *
     * نفسُ نطاق الرؤية المطبَّق على الحركات: هو ومن يديرهم إدارياً،
     * و`data_scope_all` أو غيرُ الموظّف بلا قيد.
     *
     * و**المفتوحة** لا النشطة وحدها: الجلسةُ المعلَّقة عهدةٌ لم تُسلَّم،
     * وقد يلزم إيداعُ فكّةٍ فيها أو ترحيلُ نقدها قبل إقفالها.
     */
    private function selectableSessions()
    {
        return $this->scopeToVisibleUsers(
            PosSession::open()->with('user:id,name', 'device:id,device_name')
        )->latest('started_at')->get(['id', 'session_number', 'user_id', 'device_id', 'status']);
    }

    /**
     * أيُّ جلسةٍ تُدار الآن؟
     *
     * المطلوبةُ بـ`?session_id=` إن كانت في نطاقه، وإلّا جلستُه هو، وإلّا
     * أوّلُ ما يبلغ. و`null` حين لا جلسةَ مفتوحةً أصلاً.
     *
     * والتحقّقُ من النطاق هنا لا في الشاشة: مَن يعرف رقمَ جلسةٍ لا يبلغها
     * لا يُدير درجَها بتمريره في العنوان.
     */
    private function drawerSession(Request $request): ?PosSession
    {
        $sessions = $this->selectableSessions();

        if ($request->filled('session_id')) {
            $picked = $sessions->firstWhere('id', (int) $request->input('session_id'));

            if ($picked) {
                return $picked;
            }
        }

        return $sessions->firstWhere('user_id', auth()->id()) ?: $sessions->first();
    }

    /**
     * نطاقُ رؤية حركات الدرج — للجدول وللبطاقات معاً.
     *
     * كان مكتوباً في `index()` وحدها، فخرجت `totals()` منه. وموضعٌ واحدٌ
     * يمنع افتراقَ الرقم عن الصفوف التي يجمعها.
     *
     * ═══════════ ولماذا لا يُستثنى المُعتمِد ═══════════
     *
     * كان `pos.cash_drawer.approve` يرفع القيد — بحجّة أنّ من يعتمد يلزمه
     * أن يرى. والحجّةُ صحيحةٌ والاستنتاجُ خطأ: المُعتمِدُ يبلغ ما يعتمده
     * عبر **شجرة الإدارة** أصلاً، فطلبُ الكاشير يصعد إلى مديره لأنه مديرُه
     * لا لأنه يحمل خانة.
     *
     * والاستثناءُ كان يفتح الفرعَ كلَّه لكلِّ حاملِ الخانة — وقد مُنحت لدور
     * «موظف»، فلم يُقيَّد أحد. وبقاؤه يعني أنّ أيَّ تعميمٍ قادمٍ في شاشة
     * الأدوار يُعيد التسريبَ بنقرة.
     *
     * فالاعتمادُ صلاحيةُ **فعل** لا صلاحيةُ **رؤية** — ويبقى كذلك في
     * `approve()` و`reject()`.
     *
     * @param  Builder  $query
     * @return Builder
     */
    private function scopeMovements($query)
    {
        return $this->scopeToVisibleUsers($query, 'created_by');
    }

    /**
     * مجاميعُ الفلتر — استعلامٌ واحدٌ مجموعٌ بالنوع.
     *
     * ═══════════ ما كان ═══════════
     *
     * الجدولُ مقيَّدٌ بالمُنشئ (`scopeToCreator` أعلاه)، والبطاقاتُ فوقه
     * **ليست**. فكاشيرٌ لا يرى إلّا حركاتِه يقرأ في البطاقات مجموعَ حركات
     * الفرع كلِّه: صفٌّ واحدٌ بمئةٍ تحت بطاقةٍ تقول ثمانيةَ آلاف.
     *
     * وهو تسريبٌ لا خطأَ عرضٍ فقط — المبلغُ نفسُه هو المعلومة.
     *
     * وعدّادُ «المنتظِرة» كان أسوأ: `->when($this->accessibleBranchIds(), …)`
     * و`when()` تُسقط الشرطَ عند **كل** قيمةٍ زائفة — فالمصفوفةُ الفارغة
     * (أي «لا فرعَ يبلغه») تعني «بلا قيد» لا «لا شيء». فأشدُّ المستخدمين
     * تقييداً كان يقرأ العدَّ العامّ. والقاعدةُ معكوسةٌ تماماً.
     *
     * ═══════════ والقاعدة ═══════════
     *
     * البطاقةُ تقرأ ما يقرأه الجدولُ تحتها — نفسَ النطاق ونفسَ الفلاتر.
     */
    private function totals(Request $request, array $period): array
    {
        $query = PosCashMovement::query()->approved();

        $this->scopeMovements($query);
        $this->scopeToPosPeriod($query, $period);

        $query
            ->when($request->filled('session_id'), fn ($q) => $q->where('session_id', $request->session_id))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('movement_time', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('movement_time', '<=', $request->date_to));

        $rows = $query->selectRaw('type, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $payIn = (float) ($rows[PosCashMovement::TYPE_PAY_IN]->total ?? 0);
        $payOut = (float) ($rows[PosCashMovement::TYPE_PAY_OUT]->total ?? 0);
        $drop = (float) ($rows[PosCashMovement::TYPE_DROP]->total ?? 0);

        return [
            'pay_in' => $payIn,
            'pay_out' => $payOut,
            'drop' => $drop,
            'net' => round($payIn - $payOut - $drop, 2),
            'pending' => $this->scopeMovements(PosCashMovement::pending())->count(),
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:pos_sessions,id',
            'type' => 'required|in:pay_in,pay_out,drop,count',
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
            /*
                `exists:accounts,id` وحدها تقبل **أيَّ حسابٍ في الشجرة**: لا
                مُنتقيَ لهذا الحقل في الشاشة، فقيمتُه تأتي من الطلب مباشرةً —
                وطلبٌ مصنوع يُرحّل نقدَ الدرج إلى حسابٍ ليس للمستخدم.

                والقيدُ يقع في `store()` بعد التحقّق (انظر
                `assertTreasuryAccess`)، إذ يحتاج معرفةَ نوع الحركة: الترحيلُ
                والإيداعُ طرفاهما خزينة، والصرفُ النثريُّ طرفُه حسابُ مصروف.
            */
            'counterpart_account_id' => 'nullable|exists:accounts,id',
        ], [
            'amount.required' => 'المبلغ مطلوب',
            'type.in' => 'نوع الحركة غير صحيح',
        ]);

        $permission = self::PERMISSION_BY_TYPE[$validated['type']];

        if (! hasPermission($permission) && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك بهذا النوع من حركات الدرج.');
        }

        /*
            الطرفُ المقابل للترحيل والإيداع خزينة — فيُقاس بنطاق خزائن
            المستخدم. والصرفُ النثريُّ طرفُه حسابُ مصروفٍ لا خزينة، فلا
            يُقاس به.
        */
        if (in_array($validated['type'], [PosCashMovement::TYPE_DROP, PosCashMovement::TYPE_PAY_IN], true)) {
            $this->assertTreasuryAccess(
                isset($validated['counterpart_account_id']) ? (int) $validated['counterpart_account_id'] : null
            );
        }

        /*
            ═══════════ حسابُ المصروف يُشترط هنا لا عند الاعتماد ═══════════

            `postJournal()` ترفض قيدَ سحبٍ بلا حسابٍ مقابل — والترحيلُ
            والإيداعُ يرجعان إلى «الخزينة الرئيسية» تلقائياً، أمّا السحبُ
            فلا افتراضَ له: كلُّ مصروفٍ حسابُه.

            وكان الحقلُ `nullable` هنا ولا مُنتقيَ له في الشاشة. فتُنشأ
            حركةُ سحبٍ بلا حساب، وتبقى `pending`، **ويستحيل اعتمادُها
            أبداً**: كلُّ محاولةٍ تُردّ بالرسالة نفسِها، والحركةُ عالقةٌ في
            الجدول لا تُعتمد ولا تُقيَّد.

            والرفضُ في موضعه: لا تُقبل حركةٌ يُعلم سلفاً أنها لا تُرحَّل.
        */
        if ($validated['type'] === PosCashMovement::TYPE_PAY_OUT && empty($validated['counterpart_account_id'])) {
            return back()->withInput()->with(
                'error',
                'اختر حساب المصروف الذي يُقيَّد عليه السحب — بدونه لا يُمكن اعتماد الحركة ولا ترحيل قيدها.'
            );
        }

        /*
            الجلسةُ في نطاقه — `exists:pos_sessions,id` وحدها تقبل أيَّ رقم.

            وقد صار للشاشة مُنتقي جلسات، فمن يعرف رقمَ جلسةِ زميلٍ لا يبلغها
            كان يُسجّل في درجها بتمريرِ الرقم في الطلب.
        */
        $session = $this->selectableSessions()->firstWhere('id', (int) $validated['session_id']);

        if (! $session) {
            abort(403, 'هذه الجلسة خارج نطاقك، أو ليست مفتوحة.');
        }

        $this->authorizeSessionAccess($session);

        if (! $session->acceptsSales()) {
            return back()->with('error', 'لا يمكن تسجيل حركة درج على جلسة غير نشطة.');
        }

        try {
            $movement = $this->drawer->record($session, $validated);

            $message = $movement->status === PosCashMovement::STATUS_PENDING
                ? 'تم تسجيل الحركة وهي بانتظار اعتماد المشرف.'
                : 'تم تسجيل حركة الدرج بنجاح.';

            return back()->with('success', $message.' رقم الحركة: '.$movement->reference_number);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.CashDrawer', 'store');

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, $id)
    {
        if (! hasPermission('pos.cash_drawer.approve') && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك باعتماد حركات الدرج.');
        }

        $movement = PosCashMovement::findOrFail($id);

        /*
            ═══════════ مخرجُ الحركات العالقة ═══════════

            حركاتُ سحبٍ أُنشئت قبل اشتراط الحساب المقابل لا تُعتمد أبداً:
            `postJournal()` ترفض قيدَ سحبٍ بلا حساب، فتُردّ كلُّ محاولةٍ
            وتبقى الحركةُ في الجدول لا تُعتمد ولا تُقيَّد.

            فيُقبل الحسابُ هنا ليُستكمل ما نقص، بدل أن يُترك الصفُّ عالقاً
            إلى الأبد أو يُحذف بسجلّه.
        */
        if ($request->filled('counterpart_account_id') && ! $movement->counterpart_account_id) {
            $request->validate(['counterpart_account_id' => 'exists:accounts,id']);

            $movement->update(['counterpart_account_id' => (int) $request->input('counterpart_account_id')]);
            $movement->refresh();
        }

        try {
            $this->drawer->approve($movement);

            return back()->with('success', 'تم اعتماد الحركة وترحيل قيدها.');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.CashDrawer', 'approve');

            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        if (! hasPermission('pos.cash_drawer.approve') && ! $this->hasFullPosScope()) {
            abort(403, 'غير مصرح لك برفض حركات الدرج.');
        }

        $movement = PosCashMovement::findOrFail($id);

        try {
            $this->drawer->reject($movement, $request->input('reason'));

            return back()->with('success', 'تم رفض الحركة.');
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.CashDrawer', 'reject');

            return back()->with('error', $e->getMessage());
        }
    }

    /** ملخّصُ درجِ جلسةٍ — للشاشة الحيّة في نقطة البيع. */
    public function sessionSummary($sessionId)
    {
        $session = PosSession::findOrFail($sessionId);

        $this->authorizeSessionAccess($session);

        return response()->json([
            'success' => true,
            'summary' => $this->drawer->summary($session),
            'expected_cash' => $session->computeExpectedCash(),
            'currency' => $this->documentCurrency($session),
            'symbol' => $this->currencySymbol($session),
        ]);
    }

    /**
     * حساباتُ المصروف التي يُقيَّد عليها سحبُ الدرج.
     *
     * الطرفيّةُ وحدها: الحسابُ الأبُ لا يُقيَّد عليه، وعرضُه في القائمة
     * دعوةٌ إلى قيدٍ يُرفض.
     */
    private function expenseAccountOptions()
    {
        return Account::query()
            ->whereDoesntHave('children')
            ->where(function ($q) {
                $q->where('code', 'like', '5%')->orWhere('type', 'expenses');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    private function typeOptions(): array
    {
        return [
            PosCashMovement::TYPE_PAY_IN => PosCashMovement::typeLabel(PosCashMovement::TYPE_PAY_IN),
            PosCashMovement::TYPE_PAY_OUT => PosCashMovement::typeLabel(PosCashMovement::TYPE_PAY_OUT),
            PosCashMovement::TYPE_DROP => PosCashMovement::typeLabel(PosCashMovement::TYPE_DROP),
            PosCashMovement::TYPE_COUNT => PosCashMovement::typeLabel(PosCashMovement::TYPE_COUNT),
        ];
    }

    private function branchOptions()
    {
        $query = Branch::query()->orderBy('name');

        $ids = $this->accessibleBranchIds();

        if ($ids !== null) {
            $query->whereIn('id', $ids ?: [0]);
        }

        return $query->get(['id', 'name']);
    }
}
