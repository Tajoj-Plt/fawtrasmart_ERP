<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmployeeClientVisit;
use App\Models\notifications;
use App\Models\User;
use App\Models\Visit;
use App\Support\OperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * زيارات العملاء: عرضُها وتعديلُها وتقاريرها.
 *
 * التسجيلُ الآليّ بالـGPS (متتبّعٌ صامت في القوالب الثلاثة يسجّل الوصول
 * والانصراف، و«وضع تسجيل الحضور/الانصراف» الذي كان يحكمه) أُزيل كلّه في
 * 2026-09-14 — الزيارة تُبدأ وتُنهى يدوياً من مساحة عمل الزيارة.
 *
 * **ما يأتي من الإعدادات** — «إعدادات وحدات العملاء» ← تبويب الزيارات:
 *
 *   • *القسم*        ← `visits_enabled`: مطفأً تُمنع الشاشة والروابط معاً.
 *   • *نطاق القرب*   ← `visit_proximity_radius_km` (كان مربوطاً).
 *   • *حالة الزيارة* ← لا يعدّلها الموظف من `update()` — ثابتٌ لا إعداد (2026-09-15).
 *
 * **الحسابات**: `SALES` و`SALES_RETURNS` و`CASH_TREASURIES_PARENT` و
 * `BANK_ACCOUNTS_PARENT` كانت ثوابت في تقارير الأداء — صارت من «الحسابات
 * المحاسبية» (`ResolvesAccountingAccounts`). وهذه قراءاتُ تقارير لا قيود،
 * فتحتمل الغياب ولا تستعمل `require`: مفتاحٌ غير مربوط يعني رقماً صفرياً
 * في التقرير لا شاشةً لا تُفتح.
 *
 * **الصلاحيات**: لم تكن على المتحكّم صلاحيةٌ واحدة رغم أنّ فيه ما يمسح
 * بيانات الزيارات كلَّها (`clearVisitsData`) وما يسجّل مغادرةً يدوية.
 *
 * **السجلّات**: `OperationLog` بدل `Log::error()` — الأخيرة تكتب في ملفّ
 * لارافيل فلا تراها شاشة «سجلّ الأخطاء».
 */
class VisitController extends Controller
{
    use Concerns\EnforcesClientModuleSettings;
    use Concerns\ResolvesAccountingAccounts;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientVisits';

    /** عدد الساعات التي تصير بعدها الزيارةُ المفتوحة «منسية». */
    public const FORGOTTEN_AFTER_HOURS = 4;

    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:clients_visits_track|clients_itinerary_view', ['only' => [
            'index', 'show', 'myVisits', 'getTodayVisits', 'hasVisitedToday',
        ]]);

        $this->middleware('permission:clients_itinerary_edit', ['only' => ['update', 'destroy']]);

        /* `manualDeparture` و`clearVisitsData` و`storeEmployeeLocation`
           مسجَّلةٌ في `routes/web.php` ولا وجود لها في هذا الصنف — ثلاثة
           مسارات تعطي 500 عند فتحها. لم تُدرَج هنا لأنّ
           `only[]` باسمٍ لا يقابله إجراءٌ يوهم بحراسةٍ لا محروسَ لها.
           كتابتُها أو حذفُ مساراتها قرارٌ خارج نطاق هذا الإصلاح.

           `showItineraryPlanner`/`getItineraryData`/`saveItinerary`/
           `sortClientsByDistance` حُذفت: خطُّ سيرٍ ثانٍ مكرَّرٌ على جدول
           `visits` بلا مسارٍ يصلها ولا شاشةَ تعرضها — النسخة الحيّة في
           `ItineraryController` على `EmployeeClientVisit`. */
    }

    // عرض جميع الزيارات
    public function index()
    {
        $visits = Visit::with(['employee', 'client'])
            ->orderBy('visit_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $visits,
            'count' => $visits->count(),
        ]);
    }

    // عرض تفاصيل زيارة
    public function show($id)
    {
        $visit = Visit::with(['employee', 'client'])->find($id);

        if (! $visit) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'الزيارة غير موجودة',
                ],
                404,
            );
        }

        return response()->json([
            'success' => true,
            'data' => $visit,
        ]);
    }

    // Check if employee has visited a specific client today
    public function hasVisitedToday($clientId)
    {
        $today = now()->toDateString();

        $visit = Visit::where('employee_id', Auth::id())
            ->where('client_id', $clientId)
            ->whereDate('visit_date', $today)
            ->whereNotNull('arrival_time')
            ->first();

        return response()->json([
            'has_visited' => $visit ? true : false,
            'visit' => $visit,
        ]);
    }

    /**
     * إشعارٌ داخليّ **واحد** لكلّ حدث: وصولُ المندوب إلى عميله (بدء الزيارة)،
     * وانصرافُه (إنهاؤها) — `public` عمداً: يستدعيها
     * `VisitWorkspaceController::start()`/`complete()`، المسلكُ الوحيد لهما.
     *
     * كان يُكتب صفٌّ للمندوب وصفٌّ لكلّ حامل دور Spatie «manager» — وهو على
     * السيرفر عامّةُ الموظفين — فيتكرّر الحدثُ الواحد عشرات المرّات. صار صفّاً
     * واحداً مُرسِلُه المندوب (`user_id`) بلا مستقبِل: يراه المندوبُ في
     * إشعاراته، ويراه من يملك «عرض إشعارات النظام» / «عرض جميع الإشعارات»
     * بقاعدة قوائم الإشعارات نفسها. لا Telegram هنا — ذاك حصراً لتقرير
     * الإقفال اليومي (`TelegramNotificationService`).
     */
    public function sendVisitNotifications($visit, $type)
    {
        $employeeName = $visit->employee->name ?? 'غير معروف';
        $clientName = $visit->client->trade_name ?? 'غير معروف';
        $arrival = $type === 'arrival';
        $time = Carbon::parse($arrival ? $visit->arrival_time : $visit->departure_time)->format('H:i');

        notifications::create([
            'user_id' => $visit->employee_id,
            'type' => 'visit',
            'title' => $arrival ? 'وصول موظف إلى عميل' : 'انصراف موظف من عميل',
            'message' => $arrival
                ? "الموظف $employeeName وصل إلى العميل $clientName — $time"
                : "الموظف $employeeName انصرف من العميل $clientName — $time",
            'read' => false,
            'data' => [
                'visit_id' => $visit->id,
                'employee_id' => $visit->employee_id,
                'client_id' => $visit->client_id,
                'type' => $type,
            ],
        ]);
    }

    // إرسال إشعار للموظف
    private function sendEmployeeNotification($employeeId, $message, $title)
    {
        notifications::create([
            'user_id' => $employeeId,
            'type' => 'visit_notification',
            'title' => $title,
            'message' => $message,
            'read' => false,
            'data' => [
                'type' => 'visit_update',
            ],
        ]);
    }

    // تحديث زيارة
    public function update(Request $request, $id)
    {
        $visit = Visit::find($id);

        if (! $visit) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'الزيارة غير موجودة',
                ],
                404,
            );
        }

        $request->validate([
            'status' => 'sometimes|in:present,absent',
            'arrival_time' => 'sometimes|date',
            'departure_time' => 'sometimes|date|after:arrival_time',
            'notes' => 'sometimes|string',
        ]);

        $isOwner = $visit->employee_id == Auth::id();
        $isAdmin = Auth::user()->hasRole('admin');

        if (! $isOwner && ! $isAdmin) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل هذه الزيارة',
                ],
                403,
            );
        }

        // موظفٌ غير أدمن لا يعدّل حالة زيارته من هنا — تُكتب في مساحة عمل الزيارة.
        // كان مفتاحان في «إعدادات وحدات العملاء» (المنعُ افتراضاً للحالة، والإباحةُ
        // للملاحظات) على مسارٍ لا تستدعيه شاشة؛ أُزيلا (2026-09-15) وبقي سلوكُهما.
        if (! $isAdmin && $request->has('status')) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'غير مصرح لك بتعديل حالة الزيارة',
                ],
                403,
            );
        }

        // `$request->all()` كانت تمرّر كلَّ ما وصل: `employee_id` أو
        // `is_approved` مدسوسان في الطلب يمرّان إلى `fillable` بلا فحص،
        // وقد رُفض تعديلُ الحالة قبل أسطر بصلاحيةٍ يتخطّاها هذا السطر
        $payload = $request->only(['status', 'arrival_time', 'departure_time', 'notes']);

        $before = $visit->only(array_keys($payload));

        $visit->update($payload);

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            $visit,
            'تحديث زيارة العميل '.($visit->client->trade_name ?? '#'.$visit->client_id),
            ['old' => $before, 'new' => $payload]
        );

        if ($request->has('departure_time')) {
            $this->sendVisitNotifications($visit, 'departure');
            $this->sendEmployeeNotification($visit->employee_id, 'تم تحديث وقت انصرافك من العميل '.$visit->client->trade_name, 'تحديث انصراف');
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الزيارة بنجاح',
            'data' => $visit,
        ]);
    }

    // حذف زيارة
    public function destroy($id)
    {
        $visit = Visit::find($id);

        if (! $visit) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'الزيارة غير موجودة',
                ],
                404,
            );
        }

        if ($visit->employee_id != Auth::id() && ! Auth::user()->hasRole('admin')) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'غير مصرح لك بحذف هذه الزيارة',
                ],
                403,
            );
        }

        // السجلّ قبل الحذف: بعده لا يبقى ما يُوصَف
        OperationLog::record(
            'deleted',
            self::LOG_MODULE,
            $visit,
            'حذف زيارة العميل '.($visit->client->trade_name ?? '#'.$visit->client_id)
                .' بتاريخ '.$visit->visit_date,
            ['old' => [
                'client_id' => $visit->client_id,
                'employee_id' => $visit->employee_id,
                'visit_date' => (string) $visit->visit_date,
                'status' => $visit->status,
            ]]
        );

        $visit->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الزيارة بنجاح',
        ]);
    }

    // زيارات الموظف الحالي
    public function myVisits()
    {
        $visits = Visit::with('client')->where('employee_id', Auth::id())->orderBy('visit_date', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $visits,
            'count' => $visits->count(),
        ]);
    }

    // زيارات اليوم
    public function getTodayVisits()
    {
        $today = now()->toDateString();

        $visits = Visit::with(['employee', 'client'])
            ->whereDate('visit_date', $today)
            ->orderBy('visit_date', 'desc')
            ->get()
            ->map(function ($visit) {
                return [
                    'id' => $visit->id,
                    'client_name' => $visit->client->trade_name ?? 'غير معروف',
                    'employee_name' => $visit->employee->name ?? 'غير معروف',
                    'arrival_time' => $visit->arrival_time ? $visit->arrival_time->format('H:i') : '--:--',
                    'departure_time' => $visit->departure_time ? $visit->departure_time->format('H:i') : '--:--',
                    'status' => $visit->status,
                    'created_at' => $visit->created_at->toDateTimeString(),
                ];
            });

        return response()->json([
            'success' => true,
            'visits' => $visits,
            'count' => $visits->count(),
        ]);
    }

    /** اللحظةُ التي تُعدّ الزيارةُ التي بدأت قبلها منسية. */
    private function forgottenThreshold(): Carbon
    {
        return now()->subHours(self::FORGOTTEN_AFTER_HOURS);
    }

    private function today(): string
    {
        return now()->toDateString();
    }

    /**
     * الزيارات المنسية — التي بدأها الموظف ولم يُنهِها.
     *
     * يعرضها الموظف نفسُه (زياراتُه) والمدير (زيارات كل الموظفين).
     * الزيارة المنسية هي التي لا تزال في حالة STARTED منذ أكثر من X ساعة
     * (أو اليوم السابق).
     */
    public function listForgottenVisits(Request $request)
    {
        $userId = Auth::id();
        $user = Auth::user();

        // تصفية: هل يريد الموظفُ زياراتَه هو أم كلَّ الموظفين؟
        $scope = $request->query('scope', 'own');
        $viewAllPermission = hasPermission('clients_view_forgotten_visits_all');
        $viewOwnPermission = hasPermission('clients_view_forgotten_visits_own');

        if (! $viewOwnPermission && ! $viewAllPermission) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بعرض الزيارات المنسية',
            ], 403);
        }

        // إذا طلب الكلَّ لكن لا صلاحية، أرجع زياراته هو فقط
        if ($scope === 'all' && ! $viewAllPermission) {
            $scope = 'own';
        }

        // نفسُ تعريف «المنسية» في شاشة `itineraryDashboard` حرفاً بحرف —
        // كان هذا المسار يطابق كلَّ صفٍّ قديمٍ في الجدول لأنّه لا يستثني
        // المُنهاةَ (`departure_time`) ولا الصفوفَ التلقائية.
        $query = Visit::openWorkspaceVisit()
            ->where(fn ($q) => $q->where(fn ($started) => $started->whereNotNull('started_at')
                ->where('started_at', '<', $this->forgottenThreshold()))
                ->orWhere(fn ($never) => $never->whereNull('started_at')
                    ->whereDate('visit_date', '<', $this->today())))
            ->with(['employee', 'client']);

        // نطاق البيانات: الموظف نفسه أم الكل حسب الصلاحية
        if ($scope === 'own') {
            $query->where('employee_id', $userId);
        }

        $forgottenVisits = $query->orderBy('started_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'forgotten_visits' => $forgottenVisits,
            'count' => $forgottenVisits->count(),
            'scope' => $scope,
        ]);
    }

    /**
     * إنهاء زيارة منسية عن بُعد.
     *
     * الموظف ينهي زيارته هو، والمدير ينهي زيارة أيّ موظف.
     */
    public function closeForgottenVisit(Request $request, $visitId)
    {
        $userId = Auth::id();
        $visit = Visit::find($visitId);

        if (! $visit) {
            return response()->json([
                'success' => false,
                'message' => 'الزيارة غير موجودة',
            ], 404);
        }

        // التحقق من الصلاحية: هل هي زيارتي أنا أم زيارة موظف آخر؟
        $isOwnVisit = $visit->employee_id === $userId;

        if ($isOwnVisit) {
            // زيارتي أنا — أحتاج `clients_close_forgotten_visits_own`
            if (! hasPermission('clients_close_forgotten_visits_own')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنهاء زياراتك المنسية',
                ], 403);
            }
        } else {
            // زيارة موظف آخر — أحتاج `clients_force_close_forgotten_visits`
            if (! hasPermission('clients_force_close_forgotten_visits')) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإنهاء زيارات الموظفين الآخرين',
                ], 403);
            }
        }

        $request->validate([
            'outcome' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $before = $visit->only(['stage', 'status', 'departure_time', 'outcome', 'outcome_notes']);

        // إنهاء الزيارة: تسجيل الخروج الآن
        // `status` لا يُكتب هنا: `enum('present','absent')` — حضورُ الموظف،
        // لا حالةُ الزيارة. كانت `'status' => 'completed'` ترفعها MySQL
        // تحت `STRICT_TRANS_TABLES` (خطأ 1265) فيفشل زرّ «إنهاء» بـ500.
        $visit->update([
            'stage' => Visit::STAGE_COMPLETED,
            'departure_time' => now(),
            'outcome' => $request->input('outcome', 'successful'),
            'outcome_notes' => $request->input('notes', 'تم إنهاء الزيارة عن بُعد — الموظف لم يعد متاحاً'),
        ]);

        /* صفُّ خطّ السير يُكمَل هنا كما يُكمله «إنهاء الزيارة» من مساحة العمل
           (`VisitWorkspaceController::complete()`) — بدونها تُغلَق الزيارة
           ويبقى صفُّ اليوم «لم تتم»، فيظهر العميلُ غيرَ مزارٍ في خطّ السير
           وفي تقاريره رغم أنّ زيارته مكتملة. تُنادى **بعد** التحديث أعلاه:
           `markCompletedFor()` تنسحب متى وجدت زيارةَ مساحة عملٍ مفتوحة.
           ونقاطُ اكتمال خطّ السير لا تُقيَّم هنا: هذا إغلاقٌ متأخّرٌ أو
           إغلاقُ مديرٍ، لا يومٌ أنهاه المندوب في وقته. */
        /* المدّة لا تُكتب إلا إن كانت معقولة: زيارةٌ تُركت مفتوحةً خمسة
           أيّامٍ ليست زيارةً دامت خمسة أيّام — كتابةُ الفرق الخام تُفسد
           متوسّطَ مدّة الزيارة في التقارير. فوق المهلة نمرّر `null`،
           و`markCompletedFor()` تترك العمود كما هو. */
        $elapsedMinutes = (int) round($visit->fresh()->elapsed_seconds / 60);

        EmployeeClientVisit::markCompletedFor(
            (int) $visit->employee_id,
            (int) $visit->client_id,
            $visit->visit_date,
            $elapsedMinutes <= self::FORGOTTEN_AFTER_HOURS * 60 ? $elapsedMinutes : null
        );

        // تسجيل العملية
        OperationLog::record(
            'closed_forgotten',
            self::LOG_MODULE,
            $visit,
            'إنهاء زيارة منسية: '
                .($visit->client->trade_name ?? '#'.$visit->client_id)
                .' (الموظف: '.$visit->employee->name.')',
            [
                'action' => $isOwnVisit ? 'closed_by_self' : 'closed_by_manager',
                'closer_id' => $userId,
                'closed_by' => Auth::user()->name,
                'old' => $before,
            ]
        );

        // إخطار الموظف الأصلي (إذا أغلقها مدير)
        if (! $isOwnVisit) {
            notifications::create([
                'user_id' => $visit->employee_id,
                'type' => 'forgotten_visit_closed',
                'title' => 'تم إنهاء زيارة منسية',
                'message' => 'المدير '.Auth::user()->name.' أنهى الزيارة المنسية عند العميل '
                    .($visit->client->trade_name ?? 'غير معروف').' (بتاريخ '.$visit->visit_date->format('Y-m-d').')',
                'read' => false,
                'data' => [
                    'visit_id' => $visit->id,
                    'client_id' => $visit->client_id,
                    'closed_by' => $userId,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنهاء الزيارة بنجاح',
            'visit' => $visit,
        ]);
    }

    /**
     * إلغاء زيارة منسية بدلاً من إنهاؤها.
     *
     * عندما تكون الزيارة خطأً تماماً أو لا يجب الاستمرار فيها.
     */
    public function cancelForgottenVisit(Request $request, $visitId)
    {
        $userId = Auth::id();
        $visit = Visit::find($visitId);

        if (! $visit) {
            return response()->json([
                'success' => false,
                'message' => 'الزيارة غير موجودة',
            ], 404);
        }

        // التحقق من الصلاحية
        if (! hasPermission('clients_cancel_forgotten_visits')) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإلغاء الزيارات',
            ], 403);
        }

        // الموظف لا يلغي زيارات غيره
        if ($visit->employee_id !== $userId && ! Auth::user()->hasRole(['admin', 'manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك إلغاء زيارة موظف آخر',
            ], 403);
        }

        $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        $before = $visit->only(['stage', 'status', 'cancel_reason']);

        // راجع `closeForgottenVisit()`: `status` عمودُ حضورٍ لا حالةُ زيارة.
        $visit->update([
            'stage' => Visit::STAGE_CANCELLED,
            'cancel_reason' => $request->input('cancel_reason'),
        ]);

        OperationLog::record(
            'cancelled_forgotten',
            self::LOG_MODULE,
            $visit,
            'إلغاء زيارة منسية: '.($visit->client->trade_name ?? '#'.$visit->client_id),
            [
                'reason' => $request->input('cancel_reason'),
                'cancelled_by' => Auth::user()->name,
                'old' => $before,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الزيارة بنجاح',
            'visit' => $visit,
        ]);
    }

    /**
     * لوحة تحكم الزيارات — الجارية والمنسية والمعلقة.
     *
     * تجميعٌ شامل للزيارات الجارية والمنسية لمعاينة سريعة وتحكم فوري.
     * الموظف يرى زياراته هو، والمدير يرى كلّ الموظفين (حسب الصلاحية).
     */
    public function itineraryDashboard(Request $request)
    {
        $userId = Auth::id();
        $user = Auth::user();

        // التحقق من الصلاحيات
        $canViewAll = hasPermission('clients_view_forgotten_visits_all');
        $canViewOwn = hasPermission('clients_view_forgotten_visits_own');
        $canViewItinerary = hasPermission('clients_view_itinerary_dashboard');

        if (! $canViewOwn && ! $canViewAll && ! $canViewItinerary) {
            return redirect()->route('clients.dashboard')->with('error', 'غير مصرح لك بعرض هذه الصفحة');
        }

        // تحديد النطاق: مَن يرى الجميع يبدأ على «الكل» — الشاشةُ شاشةُ
        // متابعةٍ لزيارات المندوبين، وافتراضُ «زياراتي» كان يفتحها فارغةً
        // للمدير الذي لا زياراتِ له.
        $canSeeEveryone = $canViewAll || $canViewItinerary;
        $scope = $request->query('scope', $canSeeEveryone ? 'all' : 'own');
        if ($scope === 'all' && ! $canSeeEveryone) {
            $scope = 'own';
        }

        $base = fn () => Visit::openWorkspaceVisit()
            ->with(['employee', 'client'])
            ->when($scope === 'own', fn ($q) => $q->where('employee_id', $userId));

        /* القِدَم يُقاس بالساعة لا بالتقويم: زيارةٌ بدأت 23:50 كانت تنقلب
           «منسية» عند منتصف الليل والمندوب ما زال عند العميل، لأنّ
           `visit_date` صار «قبل اليوم». فالتاريخ لا يحكم إلا الصفَّ الذي لم
           يبدأ أصلاً (مساحةُ عملٍ فُتحت ونُسيت)، والمبتدِئُ تحكمه مهلته. */
        $stale = fn ($q) => $q->where(fn ($started) => $started->whereNotNull('started_at')
            ->where('started_at', '<', $this->forgottenThreshold()))
            ->orWhere(fn ($never) => $never->whereNull('started_at')
                ->whereDate('visit_date', '<', $this->today()));

        // «الجارية» تعني فعلاً بدأت ولم تُنسَ بعد — لا مجرّد مساحة عملٍ
        // فُتحت ولم يُضغَط فيها «بدء الزيارة» بعد. كانت هذه الحالة (started_at
        // فارغ، لكن تاريخ اليوم) تُحسَب «جارية» فتظهر للمستخدم زيارةٌ تقول
        // «بدأت: -- (لم تبدأ بعد)» وسط قائمةٍ يُفترض أن تكون كلّها زياراتٍ
        // حيّة — أُبعدت هنا؛ تبقى ضمن نطاق $stale الطبيعي حتى تُفتح فعلاً أو
        // يمضي عليها يومها فتُحتسب منسيّة.
        $fresh = fn ($q) => $q->whereNotNull('started_at')
            ->where('started_at', '>=', $this->forgottenThreshold());

        // ========== الزيارات المنسية (Forgotten) ==========
        $forgottenVisits = $base()->where($stale)->orderBy('started_at', 'asc')->get();

        // ========== الزيارات الجارية (In Progress) ==========
        $ongoingVisits = $base()->where($fresh)->orderBy('started_at', 'desc')->get();

        // إعادة الترتيب: المنسية أولاً ثم الجارية
        $allVisits = $forgottenVisits->concat($ongoingVisits)->unique('id');

        /* المدّة المنقضية تُحسب هنا لا في القالب: صبُّ `started_at` يُعاد
           تفسيره بحسب المنطقة الزمنية وقتَ كلِّ وصول، و`View::composer('*')`
           قد يبدّلها أثناء التصيير نفسه — فالقالبُ يُخرج رقماً غيرَ رقم
           المتحكّم. راجع `Visit::getElapsedSecondsAttribute()`. */
        $elapsedSeconds = $allVisits->mapWithKeys(fn ($visit) => [$visit->id => $visit->elapsed_seconds]);

        // إحصائيات
        $stats = [
            'total' => $allVisits->count(),
            'forgotten' => $forgottenVisits->count(),
            'ongoing' => $ongoingVisits->count(),
            'by_stage' => $allVisits->groupBy('stage')->map->count(),
        ];

        return view('client::visits-dashboard', [
            'visits' => $allVisits,
            'ongoingVisits' => $ongoingVisits,
            'forgottenVisits' => $forgottenVisits,
            'elapsedSeconds' => $elapsedSeconds,
            'stats' => $stats,
            'scope' => $scope,
            'canViewAll' => $canSeeEveryone,
            /* المهلة تُعرض في الشاشة بالرقم لا بعبارةٍ مبهمة، وتُقرأ من
               الثابت نفسِه الذي يحكم التصنيف — فلا يفترق المكتوبُ عن
               المطبَّق إن تغيّر. */
            'forgottenAfterHours' => self::FORGOTTEN_AFTER_HOURS,
            /* الأزرار تُحسب لكلّ بطاقةٍ على حدة لا مرّةً للشاشة كلّها: حقُّ
               الإنهاء يختلف بين زيارتي («_own») وزيارةِ غيري («force»)،
               فزرٌّ واحدٌ لكل البطاقات كان يَعِد مَن يملك «_own» وحدها
               بإنهاء زيارات الآخرين ثمّ يردّه المتحكّم بـ403. القِيَم هنا
               هي عينُها شروطُ `closeForgottenVisit()` و`cancelForgottenVisit()`. */
            'currentUserId' => $userId,
            'canCloseOwn' => hasPermission('clients_close_forgotten_visits_own'),
            'canForceClose' => hasPermission('clients_force_close_forgotten_visits'),
            'canCancelVisit' => hasPermission('clients_cancel_forgotten_visits'),
            'canCancelOthers' => $user->hasRole(['admin', 'manager']),
        ]);
    }
}
