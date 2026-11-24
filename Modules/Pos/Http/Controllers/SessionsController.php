<?php

namespace Modules\Pos\Http\Controllers;

use App\Constants\permissions\PosPermissions;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PosSession;
use App\Models\shifts_pos;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
// ═════ هذا الاستيراد لم يكن موجوداً ═════
//
// و`Log::` كان مستعمَلاً في ستّة مواضع في هذا الملف: `suspend()` و
// `resume()` و`print()` و`close()`. وبما أن الملف داخل نطاق
// `Modules\Pos\Http\Controllers`، كان الاسمُ يُحَلّ إلى
// `Modules\Pos\Http\Controllers\Log` — فئةٌ لا وجود لها.
//
// والنتيجةُ `Error` لا `Exception` — و`catch (\Exception $e)` **لا
// يلتقطها**. فمسارُ الخطأ في إقفال الجلسة كان معطوباً: إذا فشل الإقفال
// لأيّ سبب، بدل رسالةٍ مفهومة يحصل المستخدم على 500 بعد أن جرى
// `DB::rollback()` بالفعل.
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosSettings;
use Modules\Pos\Http\Controllers\Concerns\RespectsAccountingPeriods;
use Modules\Pos\Http\Controllers\Concerns\ScopesToPosAccess;
use Modules\Pos\Http\Controllers\Concerns\UsesDocumentCurrency;
use Modules\Pos\Services\Session\CashDrawerService;
use Modules\Pos\Services\Session\PosSessionService;

/**
 * جلساتُ نقطة البيع.
 *
 * ═════════════════════ ما تغيّر في الحراسة ═════════════════════
 *
 *     $isManager = $user->hasRoleName('manager');
 *
 * كان هذا السطرُ يحسم **رؤيةَ بيانات كل الموظفين** في خمسة مواضع، مقارنةً
 * نصّيةً باسم دور. فتسميةُ الدور بالعربية — «مدير» — أو إنشاءُ دورٍ «مشرف
 * فرع» يكسر التحكّم بصمت.
 *
 * والنظامُ يملك لهذا صلاحيةً دقيقة — `points_sale_view_own_sessions`
 * و`points_sale_view_own_sessions` — معرَّفتين في الكتالوج ومربوطتين
 * بالأدوار، **ولا تُقرآن في اشتقاق النطاق إطلاقاً**.
 *
 * وصار النطاقُ في [ScopesToPosAccess]: الصلاحيةُ تحسم، ثم الفرع، ثم
 * التسلسلُ الإداري.
 */
class SessionsController extends Controller
{
    use ResolvesPosSettings;
    use RespectsAccountingPeriods;
    use ScopesToPosAccess;
    use UsesDocumentCurrency;

    public function __construct(
        private PosSessionService $sessions,
        private CashDrawerService $drawer,
    ) {
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
        $this->middleware('permission.role:points_sale_view_own_sessions', ['only' => [
            'index',
            'show',
            'stats',
            'summary',
            'details',
            'getTransactions',
            'refreshStats',
            'print',
        ]]);
        $this->middleware('permission.role:points_sale_open_sessions_own|points_sale_open_sessions_all', ['only' => [
            'create',
            'store',
        ]]);
        $this->middleware('permission.role:points_sale_close_sessions_own|points_sale_close_sessions_all', ['only' => [
            'closeForm',
            'close',
        ]]);
        $this->middleware('permission.role:points_sale_confirm_close_sessions_own|points_sale_confirm_close_sessions_all', ['only' => [
            'confirm',
        ]]);
        $this->middleware('permission.role:pos.sessions.suspend', ['only' => ['suspend', 'resume']]);
    }

    /* ═══════════════════════ القائمة ═══════════════════════ */

    public function index(Request $request)
    {
        $query = PosSession::with(['user:id,name', 'device:id,device_name,store_id', 'device.store:id,name', 'shift:id,name', 'branch:id,name']);

        // النطاقُ أولاً — قبل أي فلتر يكتبه المستخدم
        $this->scopeToVisibleSessions($query);

        // الفترةُ المحاسبية علاقةً، بالقاعدة نفسِها في المالية والمخزون
        $period = $this->posPeriod($request);
        $this->scopeToPosPeriod($query, $period);

        $this->applyFilters($query, $request);

        $sortField = $request->get('sort', 'started_at');
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $allowedSortFields = ['session_number', 'started_at', 'ended_at', 'total_sales', 'status', 'difference'];

        if (! in_array($sortField, $allowedSortFields, true)) {
            $sortField = 'started_at';
        }

        $query->orderBy($sortField, $sortDirection);

        if ($request->boolean('export')) {
            if (! hasPermission('pos.sessions.export')) {
                abort(403, 'غير مصرح لك بتصدير سجل الجلسات.');
            }

            return $this->exportSessions(clone $query);
        }

        $sessions = $query->paginate(in_array((int) $request->input('per_page'), [10, 25, 50, 100], true)
                ? (int) $request->input('per_page')
                : 15)->withQueryString();

        return view('pos::sessions.index', [
            'sessions' => $sessions,
            'stats' => $this->calculateSessionsStats($request, $period),
            'devices' => $this->accessibleDevices()->get(['id', 'device_name', 'store_id']),
            'shifts' => $this->visibleShifts(),
            'branches' => $this->visibleBranches(),
            'currencies' => $this->activeCurrencies(),
            'selectedCurrency' => $this->filterCurrency($request),
            'period' => $period,
            'canConfirm' => hasPermission('points_sale_confirm_close_sessions_all')
                || hasPermission('points_sale_confirm_close_sessions_own'),
        ]);
    }

    /**
     * الفلاتر — موضعٌ واحد للقائمة وللإحصاءات.
     *
     * كانت مكتوبةً مرّتين حرفياً (في `index` وفي `calculateSessionsStats`)،
     * فأيُّ فلترٍ يُضاف لأحدهما يجعل البطاقاتِ العلوية لا توافق الجدولَ
     * تحتها.
     *
     * @param  Builder  $query
     */
    private function applyFilters($query, Request $request)
    {
        return $query
            ->when($request->filled('device_id'), fn ($q) => $q->where('device_id', $request->device_id))
            ->when($request->filled('shift_id'), fn ($q) => $q->where('shift_id', $request->shift_id))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('started_at', $request->date))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('started_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('started_at', '<=', $request->date_to))
            ->when($this->filterCurrency($request), fn ($q, $currency) => $q->where('currency', $currency))
            // فروقاتٌ فقط — أوّلُ ما يُبحث عنه في المراجعة
            ->when($request->boolean('with_variance'), fn ($q) => $q->whereRaw('ABS(COALESCE(difference, 0)) > 0.01'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;

                $q->where(function ($inner) use ($search) {
                    $inner->where('session_number', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'LIKE', "%{$search}%"));
                });
            });
    }

    /**
     * إحصاءاتُ البطاقات — بنفس النطاق ونفس الفلاتر.
     *
     * **استعلامٌ واحد بدل تسعة**: كانت الدالّة تُنشئ تسعةَ استعلامات
     * `(clone $baseQuery)->count()` و`->sum()` و`->avg()` — تسعُ رحلاتٍ إلى
     * القاعدة لبطاقاتٍ في أعلى الشاشة.
     */
    private function calculateSessionsStats(Request $request, array $period): array
    {
        $base = PosSession::query();

        $this->scopeToVisibleSessions($base);
        $this->scopeToPosPeriod($base, $period);
        $this->applyFilters($base, $request);

        $row = $base->selectRaw("
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_sessions,
            SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending_sessions,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_sessions,
            SUM(CASE WHEN ABS(COALESCE(difference, 0)) > 0.01 THEN 1 ELSE 0 END) as variance_sessions,
            COALESCE(SUM(total_sales), 0) as total_sales,
            COALESCE(SUM(total_returns), 0) as total_returns,
            COALESCE(SUM(total_cash), 0) as total_cash,
            COALESCE(SUM(total_card), 0) as total_card,
            COALESCE(SUM(difference), 0) as net_variance,
            COALESCE(SUM(total_transactions), 0) as total_transactions,
            COALESCE(AVG(total_sales), 0) as avg_sales
        ")->first();

        return [
            'total_sessions' => (int) ($row->total_sessions ?? 0),
            'active_sessions' => (int) ($row->active_sessions ?? 0),
            'pending_sessions' => (int) ($row->pending_sessions ?? 0),
            'closed_sessions' => (int) ($row->closed_sessions ?? 0),
            'variance_sessions' => (int) ($row->variance_sessions ?? 0),
            'total_sales' => (float) ($row->total_sales ?? 0),
            'total_returns' => (float) ($row->total_returns ?? 0),
            'total_cash' => (float) ($row->total_cash ?? 0),
            'total_card' => (float) ($row->total_card ?? 0),
            'net_variance' => (float) ($row->net_variance ?? 0),
            'total_transactions' => (int) ($row->total_transactions ?? 0),
            'avg_sales' => (float) ($row->avg_sales ?? 0),
        ];
    }

    /**
     * الورديات المعروضة — **المفعَّلة وحدها**.
     *
     * كانت `shifts_pos::query()` بلا قيدٍ على `is_active`: فورديةٌ عطّلها
     * المستخدمُ تبقى في قائمة فتح الجلسة، ويُفتح عليها — والتعطيلُ لا يعني
     * شيئاً. والحارسُ في `assertShiftWindow()` يمنعها الآن، وهذا يمنع
     * الدعوةَ إلى ما سيُمنَع.
     */
    private function visibleShifts()
    {
        /*
            ═══════════ كلُّ الورديات المفعَّلة — لكلِّ مستخدم ═══════════

            كان هنا حصرٌ بالفرع وآخرُ بالموظّف المُسنَد. وهو يكسر ما جاء
            ليحرسه: الكاشيرُ يفتح جلسةً فلا يجد ورديتَه لأنها لم تُسنَد
            إليه بالاسم — والحقلُ اختياريّ فأكثرُ الورديات بلا إسناد — أو
            لأنها سُجّلت على فرعٍ آخر. فيختار ورديةً ليست ورديتَه لينفُذ،
            وتنهار تقاريرُ الورديات ومعها قياسُ الالتزام بالدوام.

            والورديةُ تعريفٌ لا عهدة — انظر `ShiftController::index()`.

            والقيدُ الوحيد الباقي `is_active`: المعطَّلةُ ليست خياراً، وهو
            حالةٌ لا نطاقُ رؤية. والنافذةُ الزمنية تُحسب في
            `shiftsWithWindow()` فيخرج الخيارُ معطَّلاً بسببه.
        */
        return shifts_pos::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id', 'start_time', 'end_time', 'days_of_week', 'grace_minutes', 'is_active']);
    }

    /**
     * الورديات مع حالةِ نافذتها الآن — لنموذج فتح الجلسة.
     *
     * ═══════════ ما كان ═══════════
     *
     * القائمةُ تعرض كلَّ ورديةٍ مفعَّلة، والخادمُ يرفض ما هو خارجَ وقته
     * برسالةٍ بعد الإرسال. فالمستخدم يختار «المسائية» في العاشرة صباحاً،
     * ويملأ الرصيد الافتتاحي، ويضغط حفظ — ثم يُردّ.
     *
     * ═══════════ وما صار ═══════════
     *
     * الخيارُ المرفوض يظهر **معطَّلاً وبسببه**: «المسائية (تعمل 16:00 —
     * 23:00)». فيُعرف قبل الاختيار لا بعده. والخادمُ يبقى الحَكَم —
     * التعطيلُ في الشاشة تسهيلٌ لا حراسة.
     *
     * @return Collection
     */
    private function shiftsWithWindow()
    {
        $service = app(PosSessionService::class);

        return $this->visibleShifts()->map(function ($shift) use ($service) {
            $shift->window_block = $service->shiftWindowBlock($shift);

            return $shift;
        });
    }

    private function visibleBranches()
    {
        $query = Branch::query()->orderBy('name');

        $branchIds = $this->accessibleBranchIds();

        if ($branchIds !== null) {
            $query->whereIn('id', $branchIds ?: [0]);
        }

        return $query->get(['id', 'name']);
    }

    /**
     * تصديرُ الجلسات — بثًّا لا تجميعاً في الذاكرة.
     *
     * كانت `$query->get()` تُحمّل كلَّ الجلسات دفعةً واحدة ثم تُكتب، فتصديرُ
     * سنةٍ كاملة يستهلك ذاكرةً بقدر حجم النتيجة كلِّها.
     */
    private function exportSessions($query)
    {
        $filename = 'pos_sessions_'.now()->format('Y_m_d_His').'.csv';

        return response()->stream(function () use ($query) {
            $file = fopen('php://output', 'w');

            // BOM كي تفتح إكسل الملفَّ بترميز UTF-8
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'رقم الجلسة', 'الفرع', 'الموظف', 'الجهاز', 'الوردية',
                'البداية', 'النهاية', 'المدة (دقيقة)', 'الحالة', 'العملة',
                'الرصيد الافتتاحي', 'إجمالي المبيعات', 'المرتجعات',
                'النقدي', 'البطاقات', 'عدد المعاملات',
                'المتوقَّع', 'الفعلي', 'الفرق',
                'أقفلها', 'أكّدها', 'وقت التأكيد', 'ملاحظات',
            ]);

            $query->with(['user:id,name', 'device:id,device_name', 'shift:id,name', 'branch:id,name', 'closedBy:id,name', 'confirmedBy:id,name'])
                ->chunk(500, function ($rows) use ($file) {
                    foreach ($rows as $session) {
                        $duration = $session->ended_at
                            ? $session->started_at->diffInMinutes($session->ended_at)
                            : $session->started_at->diffInMinutes(now());

                        fputcsv($file, [
                            $session->session_number,
                            $session->branch->name ?? '',
                            $session->user->name ?? '',
                            $session->device->device_name ?? '',
                            $session->shift->name ?? '',
                            $session->started_at?->format('Y-m-d H:i'),
                            $session->ended_at?->format('Y-m-d H:i'),
                            $duration,
                            $this->getStatusText($session->status),
                            $this->documentCurrency($session),
                            number_format((float) $session->opening_balance, 2),
                            number_format((float) $session->total_sales, 2),
                            number_format((float) $session->total_returns, 2),
                            number_format((float) $session->total_cash, 2),
                            number_format((float) $session->total_card, 2),
                            $session->total_transactions,
                            number_format((float) ($session->expected_cash ?? 0), 2),
                            number_format((float) ($session->actual_closing_balance ?? 0), 2),
                            number_format((float) ($session->difference ?? 0), 2),
                            $session->closedBy->name ?? '',
                            $session->confirmedBy->name ?? '',
                            $session->confirmed_at?->format('Y-m-d H:i'),
                            $session->closing_notes ?? '',
                        ]);
                    }
                });

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function getStatusText(?string $status): string
    {
        return match ($status) {
            PosSession::STATUS_ACTIVE => 'نشطة',
            PosSession::STATUS_SUSPENDED => 'معلّقة',
            PosSession::STATUS_PENDING_REVIEW => 'بانتظار التأكيد',
            PosSession::STATUS_CLOSED => 'مغلقة',
            default => (string) $status,
        };
    }

    /* ═══════════════════════ الفتح ═══════════════════════ */

    public function create()
    {
        $user = Auth::user();

        /*
            جلسةٌ مفتوحةٌ تمنع فتحَ ثانية — والمعلَّقةُ مفتوحة.

            كان الفحصُ `active()` وحدها: فمن علّق جلستَه وصل إلى نموذج
            الفتح وأنشأ ثانية، وبقيت الأولى عهدةً معلَّقةً لا تُقفل ولا
            تُحصى. والتحويلُ إلى بطاقتها أوضحُ من رسالة خطأ: منها يُقفلها
            أو يستأنفها.
        */
        $openSession = PosSession::open()->forUser($user->id)->first();

        if ($openSession) {
            return redirect()->route('pos.sessions.show', $openSession->id)
                ->with('warning', sprintf(
                    'لديك جلسة %s (%s) — أقفِلها قبل فتح جلسة جديدة.',
                    $openSession->status === PosSession::STATUS_SUSPENDED ? 'معلَّقة' : 'نشطة',
                    $openSession->session_number
                ));
        }

        if (! hasPermission('points_sale_open_sessions_own') && ! hasPermission('points_sale_open_sessions_all')) {
            abort(403, 'غير مصرح لك بفتح جلسة نقطة بيع.');
        }

        return view('pos::sessions.create', [
            // أجهزتي أنا — لا أجهزةُ مرؤوسيّ: الجلسةُ تُفتح على جهاز صاحبها
            'devices' => $this->ownDevices()->get(['id', 'device_name', 'store_id', 'branch_id']),
            'shifts' => $this->shiftsWithWindow(),
            'currency' => $this->defaultCurrency(),
            'symbol' => $this->currencySymbol(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:cashier_devices,id',
            'shift_id' => 'required|exists:shifts_pos,id',
            'opening_balance' => 'required|numeric|min:0',
        ], [
            'device_id.required' => 'يجب اختيار جهاز',
            'shift_id.required' => 'يجب اختيار وردية',
            'opening_balance.required' => 'الرصيد الافتتاحي مطلوب',
            'opening_balance.min' => 'الرصيد الافتتاحي لا يمكن أن يكون سالباً',
        ]);

        if (! hasPermission('points_sale_open_sessions_own') && ! hasPermission('points_sale_open_sessions_all')) {
            abort(403, 'غير مصرح لك بفتح جلسة نقطة بيع.');
        }

        // الجهازُ يُفحص بالنطاق لا بمقارنة `user_id` وحدها
        $this->assertDeviceAccess((int) $validated['device_id']);

        try {
            $session = $this->sessions->open($validated);

            return redirect()->route('POS.sales_start.index')
                ->with('success', 'تم فتح الجلسة رقم '.$session->session_number);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Sessions', 'store');

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /* ═══════════════════════ العرض ═══════════════════════ */

    public function show($id)
    {
        $session = PosSession::with(['user:id,name', 'device:id,device_name', 'shift:id,name', 'branch:id,name', 'details', 'closedBy:id,name', 'confirmedBy:id,name'])
            ->findOrFail($id);

        $this->authorizeSessionAccess($session);

        $this->sessions->refreshStatistics($session);
        $session->refresh();

        if (request()->ajax() || request()->has('ajax')) {
            return response()->json([
                'success' => true,
                'session' => $session,
                'stats' => [
                    'total_transactions' => $session->total_transactions,
                    'total_sales' => (float) $session->total_sales,
                    'total_cash' => (float) $session->total_cash,
                    'total_card' => (float) $session->total_card,
                    'expected_cash' => $session->computeExpectedCash(),
                ],
            ]);
        }

        return view('pos::sessions.show', [
            'session' => $session,
            'drawer' => $this->drawer->summary($session),
            'symbol' => $this->currencySymbol($session),
        ]);
    }

    public function details($id)
    {
        try {
            $session = PosSession::with(['user:id,name', 'device:id,device_name', 'shift:id,name'])->findOrFail($id);

            $this->authorizeSessionAccess($session);

            return response()->json([
                'success' => true,
                'session' => [
                    'session_number' => $session->session_number,
                    'status' => $session->status,
                    'status_text' => $this->getStatusText($session->status),
                    'user_name' => $session->user->name ?? '',
                    'device_name' => $session->device->device_name ?? 'غير محدد',
                    'shift_name' => $session->shift->name ?? 'غير محدد',
                    'started_at' => $session->started_at?->format('d/m/Y H:i:s'),
                    'ended_at' => $session->ended_at?->format('d/m/Y H:i:s'),
                    'duration' => $session->ended_at
                        ? $session->started_at->diff($session->ended_at)->format('%H:%I:%S')
                        : $session->started_at?->diffForHumans(),
                    'currency' => $this->documentCurrency($session),
                    'opening_balance' => number_format((float) $session->opening_balance, 2),
                    'total_sales' => number_format((float) $session->total_sales, 2),
                    'total_transactions' => $session->total_transactions,
                    'total_cash' => number_format((float) $session->total_cash, 2),
                    'total_card' => number_format((float) $session->total_card, 2),
                    'expected_cash' => number_format($session->computeExpectedCash(), 2),
                    'actual_closing_balance' => number_format((float) ($session->actual_closing_balance ?? 0), 2),
                    'difference' => number_format((float) ($session->difference ?? 0), 2),
                    'closing_notes' => $session->closing_notes,
                    'confirmed_at' => $session->confirmed_at?->format('d/m/Y H:i'),
                ],
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Sessions', 'details');

            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء جلب تفاصيل الجلسة'], 500);
        }
    }

    public function getTransactions($id)
    {
        try {
            $session = PosSession::findOrFail($id);

            $this->authorizeSessionAccess($session);

            $transactions = $session->details()
                ->orderByDesc('transaction_time')
                ->limit(20)
                ->get()
                ->map(fn ($detail) => [
                    'time' => $detail->transaction_time?->format('H:i:s'),
                    'type' => $detail->transaction_type,
                    'reference' => $detail->reference_number,
                    'amount' => number_format((float) $detail->amount, 2),
                    'cash_amount' => number_format((float) $detail->cash_amount, 2),
                    'card_amount' => number_format((float) $detail->card_amount, 2),
                    'payment_method' => $detail->payment_method,
                    'description' => $detail->description,
                ]);

            return response()->json(['success' => true, 'transactions' => $transactions]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Sessions', 'getTransactions');

            return response()->json(['success' => false, 'message' => 'خطأ في جلب المعاملات'], 500);
        }
    }

    public function refreshStats($id)
    {
        try {
            $session = PosSession::findOrFail($id);

            $this->authorizeSessionAccess($session);

            $session = $this->sessions->refreshStatistics($session);

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_transactions' => $session->total_transactions,
                    'total_sales' => number_format((float) $session->total_sales, 2),
                    'total_cash' => number_format((float) $session->total_cash, 2),
                    'total_card' => number_format((float) $session->total_card, 2),
                    'total_returns' => number_format((float) $session->total_returns, 2),
                    'expected_cash' => number_format($session->computeExpectedCash(), 2),
                    'last_transaction' => $session->details()->orderByDesc('transaction_time')->first()?->transaction_time?->diffForHumans(),
                    'duration' => $session->duration,
                ],
            ]);
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Sessions', 'refreshStats');

            return response()->json(['success' => false, 'message' => 'خطأ في تحديث الإحصائيات'], 500);
        }
    }

    /* ═══════════════════════ التعليق والاستئناف ═══════════════════════ */

    public function suspend(Request $request, $id)
    {
        $session = PosSession::findOrFail($id);

        $this->authorizeSessionAccess($session);

        if (! hasPermission('pos.sessions.suspend')) {
            abort(403, 'غير مصرح لك بتعليق الجلسات.');
        }

        if ($session->status !== PosSession::STATUS_ACTIVE) {
            return $this->respond($request, false, 'الجلسة ليست نشطة.');
        }

        $session->update([
            'status' => PosSession::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspend_reason' => $request->input('reason', 'تعليق يدوي'),
        ]);

        Log::info('تم تعليق جلسة نقطة بيع', [
            'session_id' => $session->id,
            'user_id' => Auth::id(),
            'reason' => $request->input('reason'),
        ]);

        return $this->respond($request, true, 'تم تعليق الجلسة بنجاح');
    }

    public function resume(Request $request, $id)
    {
        $session = PosSession::findOrFail($id);

        $this->authorizeSessionAccess($session);

        if (! hasPermission('pos.sessions.suspend')) {
            abort(403, 'غير مصرح لك باستئناف الجلسات.');
        }

        if (! $session->canBeResumed()) {
            return $this->respond($request, false, 'الجلسة ليست معلّقة.');
        }

        // جلسةٌ نشطةٌ أخرى تمنع الاستئناف — وإلّا صار للكاشير جلستان
        if (PosSession::active()->forUser($session->user_id)->exists()) {
            return $this->respond($request, false, 'توجد جلسة نشطة بالفعل. يجب إغلاقها أولاً.');
        }

        $session->update([
            'status' => PosSession::STATUS_ACTIVE,
            'resumed_at' => now(),
            'suspended_at' => null,
            'suspend_reason' => null,
        ]);

        Log::info('تم استئناف جلسة نقطة بيع', ['session_id' => $session->id, 'user_id' => Auth::id()]);

        return $this->respond($request, true, 'تم استئناف الجلسة بنجاح', route('pos.sessions.show', $session->id));
    }

    /* ═══════════════════════ الإقفال ═══════════════════════ */

    /**
     * شاشةُ الإقفال.
     *
     * كانت تُعيد `view('pos::sessions.close')` — **وقالبٌ بهذا الاسم غير
     * موجود في الوحدة**. أي أنّ زرَّ «إغلاق الجلسة» كان يؤدّي إلى صفحة خطأ.
     */
    public function closeForm($id)
    {
        $session = PosSession::with(['user:id,name', 'device:id,device_name', 'shift:id,name'])->findOrFail($id);

        $this->authorizeSessionAccess($session);
        $this->authorizeSessionClose($session);

        if (! $session->canBeClosed()) {
            return redirect()->route('pos.sessions.show', $session->id)
                ->with('error', 'هذه الجلسة ليست نشطة.');
        }

        return view('pos::sessions.close', [
            'session' => $session,
            'summary' => $this->sessions->closingSummary($session),
        ]);
    }

    public function close(Request $request, $id)
    {
        $session = PosSession::findOrFail($id);

        $this->authorizeSessionAccess($session);
        $this->authorizeSessionClose($session);

        $validated = $request->validate([
            'actual_closing_balance' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string|max:1000',
        ], [
            'actual_closing_balance.required' => 'الرصيد الفعلي مطلوب',
            'actual_closing_balance.numeric' => 'الرصيد يجب أن يكون رقماً',
            'actual_closing_balance.min' => 'الرصيد لا يمكن أن يكون أقل من صفر',
        ]);

        try {
            $session = $this->sessions->close($session, $validated);

            $message = $session->needsConfirmation()
                ? 'تم إقفال الجلسة وهي بانتظار تأكيد المشرف.'
                : 'تم إغلاق الجلسة بنجاح.';

            return $this->respond($request, true, $message, route('pos.sessions.summary', $session->id));
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Sessions', 'close');

            Log::error('خطأ في إغلاق جلسة نقطة البيع', [
                'session_id' => $session->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return $this->respond($request, false, $e->getMessage());
        }
    }

    /**
     * تأكيدُ الإقفال — الخطوةُ التي لم تكن.
     *
     * `points_sale_confirm_close_sessions_own/all` صلاحيتان في الكتالوج بلا
     * تنفيذ. والكاشير كان يُقفل عهدته ويعتمدها بنفسه — وهو ما وُضعتا لمنعه.
     */
    public function confirm(Request $request, $id)
    {
        $session = PosSession::findOrFail($id);

        $this->authorizeSessionAccess($session);
        $this->authorizeSessionConfirm($session);

        $validated = $request->validate([
            'confirmation_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->sessions->confirm($session, $validated['confirmation_notes'] ?? null);

            return $this->respond($request, true, 'تم تأكيد إقفال الجلسة.', route('pos.sessions.summary', $session->id));
        } catch (\Throwable $e) {
            OperationLog::failure($e, 'Pos.Sessions', 'confirm');

            return $this->respond($request, false, $e->getMessage());
        }
    }

    /**
     * إحصائياتُ الجلسة — صفحةٌ مستقلّة.
     *
     * ═══════════════ لماذا صفحةٌ لا مودل ═══════════════
     *
     * كانت مودلاً مدفوناً في شاشة العرض، وفيه ثمانيةُ أسطر: أربعةُ مبالغ
     * وأربعُ نسب. وثلاثةُ عيوبٍ لازمته:
     *
     *   • **لا عنوانَ لها**: لا يُرسَل رابطٌ لإحصائيات جلسة، ولا تُفتح من
     *     تقريرٍ أو رسالة، ولا يعود إليها المستخدم بزرّ الرجوع.
     *   • **لا تُطبع**: المودلُ خارج ورقة الطباعة أصلاً.
     *   • **تُحمَّل ولا تُقرأ**: تُبنى مع كل فتحةٍ للشاشة بجدول معاملاتها،
     *     ويراها واحدٌ من كلّ كثير.
     *
     * وصفحةٌ مستقلّة تحلّ الثلاثة، وتتّسع لما لم يكن للمودل أن يسعه:
     * توزيعُ الساعات، وتفصيلُ أنواع الحركة، وأثرُ الدرج.
     *
     * ═══════════════ ولماذا تُحسب هنا ═══════════════
     *
     * التجميعُ في القالب يعني استعراضَ `$session->details` مرّاتٍ لكل
     * سؤال. وهي محمَّلةٌ في الذاكرة على كل حال، فالتجميعُ مرّةً واحدة
     * هنا أوضح وأرخص — والقالبُ يعرض ما حُسب لا يحسبه.
     */
    public function stats($id)
    {
        $session = PosSession::with([
            'user:id,name', 'device:id,device_name', 'shift:id,name',
            'branch:id,name', 'details', 'closedBy:id,name', 'confirmedBy:id,name',
        ])->findOrFail($id);

        $this->authorizeSessionAccess($session);

        // أرقامُ الجلسة تُحدَّث قبل قراءتها: جلسةٌ نشطة تتغيّر تحت العين
        $this->sessions->refreshStatistics($session);
        $session->refresh();

        $details = $session->details;

        $sales = (float) $session->total_sales;
        $count = (int) $session->total_transactions;

        /*
            القسمةُ على صفر: جلسةٌ فُتحت ولم يُبَع فيها شيء حالةٌ عادية لا
            استثناء — وهي أوّلُ ما يُفتح صباحاً.
        */
        $ratio = fn (float $part) => $sales > 0 ? round($part / $sales * 100, 1) : 0.0;

        // توزيعُ الحركة على الساعات — يُظهر ذروةَ الوردية وركودَها
        $byHour = $details
            ->filter(fn ($d) => $d->transaction_time)
            ->groupBy(fn ($d) => $d->transaction_time->format('H'))
            ->map(fn ($rows) => [
                'count' => $rows->count(),
                'amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->sortKeys();

        $byType = $details
            ->groupBy('transaction_type')
            ->map(fn ($rows) => [
                'count' => $rows->count(),
                'amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->sortByDesc('amount');

        $byMethod = $details
            ->filter(fn ($d) => filled($d->payment_method))
            ->groupBy('payment_method')
            ->map(fn ($rows) => [
                'count' => $rows->count(),
                'amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->sortByDesc('amount');

        return view('pos::sessions.stats', [
            'session' => $session,
            'drawer' => $this->drawer->summary($session),
            'symbol' => $this->currencySymbol($session),
            'stats' => [
                'sales' => $sales,
                'cash' => (float) $session->total_cash,
                'card' => (float) $session->total_card,
                'returns' => (float) $session->total_returns,
                'transactions' => $count,
                'average' => $count > 0 ? round($sales / $count, 2) : 0.0,
                'cash_ratio' => $ratio((float) $session->total_cash),
                'card_ratio' => $ratio((float) $session->total_card),
                'return_ratio' => $ratio((float) $session->total_returns),
                'expected_cash' => $session->computeExpectedCash(),
                'sales_count' => $details->where('transaction_type', 'sale')->count(),
                'returns_count' => $details->where('transaction_type', 'return')->count(),
                'first_at' => $details->min('transaction_time'),
                'last_at' => $details->max('transaction_time'),
            ],
            'byHour' => $byHour,
            'byType' => $byType,
            'byMethod' => $byMethod,
        ]);
    }

    public function summary($id)
    {
        $session = PosSession::with(['user:id,name', 'device:id,device_name', 'shift:id,name', 'branch:id,name', 'details', 'closedBy:id,name', 'confirmedBy:id,name'])
            ->findOrFail($id);

        $this->authorizeSessionAccess($session);

        return view('pos::sessions.summary', [
            'session' => $session,
            'drawer' => $this->drawer->summary($session),
            'symbol' => $this->currencySymbol($session),
            'canConfirm' => $session->needsConfirmation()
                && (hasPermission('points_sale_confirm_close_sessions_all')
                    || hasPermission('points_sale_confirm_close_sessions_own')),
        ]);
    }

    public function print($id)
    {
        $session = PosSession::with(['user:id,name', 'device:id,device_name', 'shift:id,name', 'branch:id,name', 'details'])
            ->findOrFail($id);

        $this->authorizeSessionAccess($session);

        return view('pos::sessions.print', [
            'session' => $session,
            'drawer' => $this->drawer->summary($session),
            'symbol' => $this->currencySymbol($session),
        ]);
    }

    /**
     * استجابةٌ موحّدة — JSON للطلبات غير المتزامنة، وإعادةُ توجيه لغيرها.
     *
     * كان كلُّ إجراءٍ يكرّر كتلةَ `if ($request->ajax())` بنسخته الخاصّة.
     */
    private function respond(Request $request, bool $success, string $message, ?string $redirect = null)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(array_filter([
                'success' => $success,
                'message' => $message,
                'redirect' => $redirect,
            ], fn ($v) => $v !== null), $success ? 200 : 422);
        }

        if ($success) {
            return $redirect
                ? redirect()->to($redirect)->with('success', $message)
                : back()->with('success', $message);
        }

        return back()->withInput()->with('error', $message);
    }
}
