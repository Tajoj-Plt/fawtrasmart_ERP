<?php

namespace Modules\Client\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\CronJob;
use App\Models\Employee;
use App\Models\Statuses;
use App\Services\Clients\ClientVisibilityScope;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Modules\Client\Http\Controllers\Concerns\EnforcesClientModuleSettings;
use Modules\Client\Reservations\Services\AppointmentClientShare;

/**
 * مواعيد العملاء.
 *
 * **ما يأتي من الإعدادات** — «إعدادات الحجوزات» ← المواعيد ← «مواعيد العملاء»
 * (كانت في «إعدادات وحدات العملاء» ← المتابعة حتى 2026-09-13؛ القيمُ نفسُها
 * لم تنتقل من مخزنها — انظر `ReservationSettingsController::clientAppointmentSettings()`):
 *
 *   • *القسم*        ← `appointments_enabled`: مطفأً تُمنع الشاشة والروابط
 *                       المباشرة معاً.
 *   • *التاريخ الماضي* ← `allow_past_dates`: كانت `after_or_equal:today`
 *                       مكتوبةً في القاعدة، فتسجيلُ موعدٍ فات مستحيلٌ حتى
 *                       لمن يوثّق ما جرى بالأمس.
 *   • *ساعات العمل*  ← `working_hours_start/end` مع `enforce_working_hours`:
 *                       مطفأً تبقى إرشاداً، مفعَّلاً تردّ الحفظ.
 *   • *التداخل*      ← `allow_overlap`: مطفأً يُرفض موعدان لنفس الموظف في
 *                       نفس اللحظة.
 *   • *المدّة*       ← `default_duration_minutes`: تُملأ حين لا يذكرها
 *                       النموذج، بدل `null` تُخزَّن ولا تعني شيئاً.
 *
 * **الصلاحيات**: لم يكن على هذا المتحكّم صلاحيةٌ واحدة رغم وجود تسع
 * صلاحيات متابعةٍ معرَّفة في الكتالوج بثلاثة نطاقات. صارت عليها.
 *
 * **السجلّات**: `OperationLog` — كان `ModelsLog::create()` سطراً واحداً في
 * الإنشاء وحده، و`Log::info()`/`Log::error()` في ملفّ لارافيل لا تراهما
 * شاشتا «سجلّ النشاط» و«سجلّ الأخطاء».
 */
class AppointmentController extends Controller
{
    use EnforcesClientModuleSettings;

    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientAppointments';

    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:client_follow_up_view_notes_attachments_appointments_all|client_follow_up_view_notes_attachments_appointments_assigned|client_follow_up_view_notes_attachments_appointments_own', ['only' => [
            'index', 'show', 'export', 'calendar',
        ]]);

        $this->middleware('permission:client_follow_up_add_notes_attachments_appointments_all|client_follow_up_add_notes_attachments_appointments_assigned|client_follow_up_add_notes_attachments_appointments_own', ['only' => [
            'create', 'store',
        ]]);

        $this->middleware('permission:client_follow_up_edit_delete_notes_attachments_appointments_all|client_follow_up_edit_delete_notes_attachments_appointments_assigned|client_follow_up_edit_delete_notes_attachments_appointments_own', ['only' => [
            'edit', 'update', 'updateStatus', 'destroy',
        ]]);
    }

    /**
     * موعدُ الحجز لا يُمسّ من هنا.
     *
     * الموعدُ الذي كتبه `ReservationAppointmentSync` (`reservation_id` غير
     * فارغ) صورةٌ عن حجزه: تاريخُه ووقتُه وموظفُه وحالتُه تُشتقّ من الحجز
     * عند كلّ انتقال. تعديلُه أو حذفُه أو تغييرُ حالته مباشرةً يفصله عن
     * حجزه حتى أوّل مزامنة تعيده — أو تُنشئ موعداً ثانياً للحجز نفسه إن
     * حُذف. فالتعديلُ من شاشة الحجز، وهذه الدالة تردّ بالرسالة التي تقول ذلك.
     */
    private function reservationLockBlock(Appointment $appointment): ?string
    {
        return $appointment->reservation_id
            ? __('reservations.errors.appointment_reservation_locked')
            : null;
    }

    /** ردُّ القفل — JSON لطلبات AJAX، وإلّا عودةٌ برسالة */
    private function reservationLockResponse(Appointment $appointment, string $block)
    {
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => false, 'message' => $block], 422);
        }

        $fallback = \Illuminate\Support\Facades\Route::has('Reservations.show')
            ? route('Reservations.show', $appointment->reservation_id)
            : route('appointments.index');

        return redirect(url()->previous() ?: $fallback)->with('error', $block);
    }

    /**
     * أنواعُ الإجراء المستعملة فعلاً — لفلتر «نوع الموعد».
     *
     * كان الفلتر قائمةً ثابتة بقيمٍ إنجليزية (`visit`, `meeting`, …) بينما
     * العمود `action_type` يحمل ما اختاره المستخدم بالعربية («زيارة»، «حجز»)
     * — فلا يطابق الفلترُ صفّاً واحداً أبداً. القيمُ الآن من الجدول نفسه.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function actionTypeOptions()
    {
        return Appointment::query()
            ->whereNotNull('action_type')
            ->where('action_type', '!=', '')
            ->distinct()
            ->orderBy('action_type')
            ->pluck('action_type');
    }

    /**
     * الوقتُ على صيغة `H:i` — من `H:i` أو `H:i:s`.
     *
     * عمودُ `time` من نوع TIME فيعود `12:00:00`، ونموذجُ التعديل يعيده كما
     * هو؛ والقاعدةُ كانت `date_format:H:i` وحدها، فكلُّ حفظٍ لا يُعاد فيه
     * كتابةُ الوقت يُرفض بـ«صيغة الوقت غير صحيحة».
     */
    private function normalizeTime(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return substr($time, 0, 5);
    }

    /**
     * حراسة السجلّ نفسه بعد جلبه — لا يكفي أن يملك المستخدم إحدى صلاحيات
     * `_all|_assigned|_own` الثلاث ليصل شاشة الموعد (ذاك عمل الميدلوير)؛
     * يجب أيضاً أن يطابق الموعدُ *تحديداً* أيّ صلاحيةٍ منها يملكها فعلاً:
     * `_all` بلا قيد، `_assigned` إن كان `employee_id` الموعد موظفَه هو،
     * `_own` إن كان هو من أنشأه (`created_by`). قبل هذه الدالة كان أيّ
     * مستخدمٍ يحمل `_own` فقط يعدّل/يحذف موعد أيّ موظفٍ آخر بتغيير الرقم
     * في الرابط — كل الدوال كانت تستخدم `findOrFail($id)` وحدها.
     */
    private function authorizeAppointmentScope(Appointment $appointment, string $prefix): void
    {
        $user = auth()->user();

        if (hasPermission("{$prefix}_all")) {
            return;
        }

        // المُسنَد = أيٌّ من موظفي الموعد، لا أوّلُهم وحده
        if (hasPermission("{$prefix}_assigned") && $user->employee_id && in_array((int) $user->employee_id, $appointment->assignedEmployeeIds(), true)) {
            return;
        }

        if (hasPermission("{$prefix}_own") && (int) $appointment->created_by === (int) $user->id) {
            return;
        }

        abort(403, 'لا تملك صلاحية الوصول إلى هذا الموعد.');
    }

    /** نفس التسلسل، لكن على استعلام قائمة (index/export) لا سجلٍّ واحد. */
    private function scopeAppointmentsQuery($query, string $prefix): void
    {
        $user = auth()->user();

        if (hasPermission("{$prefix}_all")) {
            return;
        }

        $query->where(function ($q) use ($user, $prefix) {
            $applied = false;

            if (hasPermission("{$prefix}_assigned") && $user->employee_id) {
                $q->orWhere(fn ($own) => $own->forEmployee((int) $user->employee_id));
                $applied = true;
            }

            if (hasPermission("{$prefix}_own")) {
                $q->orWhere('created_by', $user->id);
                $applied = true;
            }

            if (! $applied) {
                $q->whereRaw('0 = 1');
            }
        });
    }

    /**
     * موظفو الموعد من النموذج — `employee_ids[]`، إلزاميّاً.
     *
     * كان «تعيين موظف» مربّعاً اختيارياً يرسل `created_by` من قائمة
     * `User::where('role','employee')` — وهي فارغةٌ دائماً (لا مستخدمَ بهذا
     * الدور) — ثمّ لا يقرؤه `store()` أصلاً؛ فيُنسب الموعدُ لمن أنشأه أو لا
     * لأحد. موعدٌ بلا موظّفٍ لا يحضره أحد.
     *
     * @return array<int, int>
     */
    private function employeeIdsFrom(Request $request): array
    {
        return collect((array) $request->input('employee_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * عملاءُ المُنتقيات — بنطاق الرؤية نفسِه الذي تستعمله الحجوزات
     * والمبيعات (`ClientVisibilityScope`): صلاحياتُ الرؤية أولاً، وفرعُ
     * الترويسة حين تكون الرؤية شاملة. كانت `Client::all()` فلا يُصفّي
     * اختيارُ فرعٍ من الهيدر شيئاً هنا.
     */
    private function visibleClientsQuery(?int $keep = null)
    {
        return ClientVisibilityScope::applyDocumentSelectScope(Client::query(), null, null, $keep);
    }

    private function visibleClients(?int $keep = null)
    {
        return $this->visibleClientsQuery($keep)->orderByDesc('id')->get();
    }

    /** قاعدةُ تحقّق: العميلُ المُرسَل داخل نطاق رؤية المستخدم. */
    private function clientInScopeRule(?int $keep = null): \Closure
    {
        return function ($attribute, $value, $fail) use ($keep) {
            if ($value && ! $this->visibleClientsQuery($keep)->whereKey((int) $value)->exists()) {
                $fail('العميل المختار خارج نطاقك — اختر عميلاً من القائمة.');
            }
        };
    }

    /** قائمةُ الموظفين للنموذج والفلتر — العاملون، بالاسم الكامل */
    private function employeeOptions(array $include = [])
    {
        return Employee::query()
            ->where(fn ($q) => $q->active()->when($include, fn ($q2) => $q2->orWhereIn('id', $include)))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'middle_name', 'nickname']);
    }

    /** قواعدُ الحقول المشتركة بين الإنشاء والتعديل: الموظفون والتكرار والمشاركة */
    private function sharedRules(string $dateField = 'date'): array
    {
        return [
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'share_with_client' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'recurrence_type' => 'nullable|required_if:is_recurring,1|in:'.implode(',', array_keys(Appointment::RECURRENCE_STEPS)),
            'recurrence_date' => 'nullable|required_if:is_recurring,1|date|after:'.$dateField,
        ];
    }

    private function sharedMessages(): array
    {
        return [
            'employee_ids.required' => 'يجب تحديد موظف واحد على الأقل للموعد',
            'employee_ids.min' => 'يجب تحديد موظف واحد على الأقل للموعد',
            'employee_ids.*.exists' => 'أحد الموظفين المحددين غير موجود',
            'recurrence_type.required_if' => 'اختر نوع التكرار',
            'recurrence_date.required_if' => 'حدّد تاريخ نهاية التكرار — بدونه لا تتوقّف السلسلة',
            'recurrence_date.after' => 'تاريخ نهاية التكرار يجب أن يكون بعد تاريخ الموعد',
        ];
    }

    /** أوّلُ تداخلٍ عند أيٍّ من موظفي الموعد — أو `null` */
    private function overlapForEmployees(array $employeeIds, ?string $date, ?string $time, ?int $duration, ?int $ignoreId = null): ?string
    {
        foreach ($employeeIds as $employeeId) {
            if ($block = $this->appointmentOverlapBlock($date, $time, $employeeId, (int) auth()->id(), $duration, $ignoreId)) {
                $name = Employee::find($employeeId)?->full_name;

                return $name ? $block.' ('.$name.')' : $block;
            }
        }

        return null;
    }

    /** حالةُ مهمّة التوليد — ليعرف من يحفظ موعداً متكرّراً هل سيتولّد فعلاً */
    private function recurringJobActive(): bool
    {
        try {
            return (bool) CronJob::where('handler', 'appointments:generate-recurring')->first()?->isActive();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function index(Request $request)
    {
        if ($block = $this->sectionBlock('appointments')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        $query = Appointment::with(['client.status_client', 'createdBy', 'employees', 'employee']);
        $this->scopeAppointmentsQuery($query, 'client_follow_up_view_notes_attachments_appointments');

        // البحث حسب العميل
        if ($request->filled('client')) {
            $query->where('client_id', $request->client);
        }

        // البحث حسب حالة الموعد
        if ($request->filled('appointment_status')) {
            $statusMap = [
                'pending' => 1,
                'completed' => 2,
                'cancelled' => 3,
                'rescheduled' => 4,
            ];

            if (isset($statusMap[$request->appointment_status])) {
                $query->where('status', $statusMap[$request->appointment_status]);
            }
        }

        // البحث حسب نوع الموعد
        if ($request->filled('appointment_type')) {
            $query->where('action_type', $request->appointment_type);
        }

        // البحث حسب الموظف — موظفو الموعد، لا من أنشأه
        if ($request->filled('employee')) {
            $query->forEmployee((int) $request->employee);
        }

        // البحث حسب التاريخ من
        if ($request->filled('date_from')) {
            $query->whereDate('appointment_date', '>=', $request->date_from);
        }

        // البحث حسب التاريخ إلى
        if ($request->filled('date_to')) {
            $query->whereDate('appointment_date', '<=', $request->date_to);
        }

        /* فلترُ «الأولوية» أُزيل: كان يستعلم عموداً `priority` لا وجودَ له في
           `appointments`، فاختيارُ أيّ أولوية يُسقط القائمة بخطأ SQL. */

        $appointments = $query->latest('appointment_date')->paginate(10);

        // ✅ إذا كان الطلب AJAX، نرجع فقط الجدول
        if ($request->ajax()) {
            return view('client::reservations.appointments.partials.appointments_table', [
                'appointments' => $appointments,
            ])->render();
        }

        // ✅ إذا كان طلب عادي، نرجع الصفحة كاملة
        $employees = $this->employeeOptions();
        $clients = $this->visibleClients();
        $statuses = Statuses::all();
        $actionTypes = $this->actionTypeOptions();

        /* بيانات التقويم — بنطاق الرؤية نفسِه الذي يحكم الجدول أعلاه.
           كانت تُجلب بلا نطاق: من يملك `_own` وحدها يرى في التقويم مواعيدَ
           الموظفين كلِّهم بأسماء عملائهم وجوّالاتهم. */
        $calendarScoped = Appointment::with(['client', 'createdBy', 'employees', 'employee']);
        $this->scopeAppointmentsQuery($calendarScoped, 'client_follow_up_view_notes_attachments_appointments');

        $calendarAppointmentsQuery = $calendarScoped
            ->whereBetween('appointment_date', [
                now()->subMonths(6)->startOfMonth()->format('Y-m-d'),
                now()->addMonths(6)->endOfMonth()->format('Y-m-d'),
            ])
            ->orderBy('appointment_date')
            ->orderBy('time')
            ->get();

        // تجميع المواعيد حسب التاريخ بتنسيق YYYY-MM-DD
        $calendarBookings = [];

        foreach ($calendarAppointmentsQuery as $appointment) {
            // تنسيق التاريخ بصيغة YYYY-MM-DD
            $dateKey = date('Y-m-d', strtotime($appointment->appointment_date));

            // إنشاء مصفوفة للتاريخ إذا لم تكن موجودة
            if (! isset($calendarBookings[$dateKey])) {
                $calendarBookings[$dateKey] = [];
            }

            // إضافة بيانات الموعد
            $calendarBookings[$dateKey][] = [
                'id' => $appointment->id,
                'date' => $dateKey,
                'time' => $appointment->time ?? 'غير محدد',
                'client' => $appointment->client ? $appointment->client->trade_name : 'عميل غير محدد',
                'phone' => $appointment->client ? $appointment->client->phone : 'غير متوفر',
                'employee' => $appointment->employeeNames() ?? 'غير محدد',
                'notes' => $appointment->notes ?? '',
                'status' => $this->getStatusClass($appointment->status),
                'status_text' => $this->getStatusText($appointment->status),
                'status_code' => $appointment->status,
            ];
        }

        // جلب بيانات FullCalendar
        $fullCalendarEvents = $this->getCalendarData();

        return view('client::reservations.appointments.index', compact(
            'appointments',
            'statuses',
            'employees',
            'clients',
            'actionTypes',
            'calendarBookings',
            'fullCalendarEvents'
        ));
    }

    /**
     * Get status class for calendar view
     */
    protected function getStatusClass($status)
    {
        $statusMap = [
            1 => 'pending',      // قيد الانتظار
            2 => 'completed',    // مكتمل
            3 => 'cancelled',    // ملغي
            4 => 'confirmed',     // معاد جدولته
        ];

        return $statusMap[$status] ?? 'pending';
    }

    protected function getStatusText($status)
    {
        $statusMap = [
            1 => 'قيد الانتظار',
            2 => 'مكتمل',
            3 => 'ملغي',
            4 => 'معاد جدلته',
        ];

        return $statusMap[$status] ?? 'غير معروف';
    }

    // ✅ دالة تحديث الحالة
    public function updateStatus($id, $status)
    {
        /* الحالةُ من الرابط نفسه — فكانت تُكتب أيَّ قيمة: `…/status/99`
           يحفظ حالةً لا يعرفها أيُّ قالب، ويختفي الموعدُ من كل فلتر. */
        if (! in_array((int) $status, [1, 2, 3, 4], true)) {
            $message = __('reservations.errors.appointment_bad_status');

            return request()->ajax()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : redirect()->back()->with('error', $message);
        }

        $status = (int) $status;
        $appointment = Appointment::findOrFail($id);
        $this->authorizeAppointmentScope($appointment, 'client_follow_up_edit_delete_notes_attachments_appointments');

        // حالةُ موعد الحجز تُشتقّ من حالة الحجز — تُغيَّر من شاشته
        if ($block = $this->reservationLockBlock($appointment)) {
            return $this->reservationLockResponse($appointment, $block);
        }

        try {
            $oldStatus = $appointment->status;
            $appointment->status = $status;
            $appointment->save();

            $statusNames = [
                1 => 'قيد الانتظار',
                2 => 'مكتمل',
                3 => 'ملغي',
                4 => 'معاد جدولته',
            ];

            OperationLog::record(
                'status_changed',
                self::LOG_MODULE,
                $appointment,
                'تغيير حالة موعد العميل '.($appointment->client->trade_name ?? '#'.$appointment->client_id)
                    .' إلى «'.($statusNames[$status] ?? $status).'»',
                ['old' => ['status' => $oldStatus], 'new' => ['status' => $status]]
            );

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث الحالة إلى: '.$statusNames[$status],
                ]);
            }

            return redirect()->back()->with('success', 'تم تحديث الحالة بنجاح');

        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, self::LOG_MODULE, 'update_status', (int) $id);

            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('reservations.messages.appointment_error'),
                ], 500);
            }

            return redirect()->back()->with('error', 'حدث خطأ أثناء التحديث');
        }
    }

    /**
     * Export appointments to Excel
     */
    public function export(Request $request)
    {
        // Get all appointments with filters applied
        $query = Appointment::with(['client', 'createdBy', 'employees', 'employee']);
        $this->scopeAppointmentsQuery($query, 'client_follow_up_view_notes_attachments_appointments');

        // Apply the same filters as in index method
        if ($request->filled('client')) {
            $query->where('client_id', $request->client);
        }

        if ($request->filled('appointment_status')) {
            $statusMap = [
                'pending' => 1,
                'completed' => 2,
                'cancelled' => 3,
                'rescheduled' => 4,
            ];

            if (isset($statusMap[$request->appointment_status])) {
                $query->where('status', $statusMap[$request->appointment_status]);
            }
        }

        if ($request->filled('appointment_type')) {
            $query->where('action_type', $request->appointment_type);
        }

        if ($request->filled('employee')) {
            $query->forEmployee((int) $request->employee);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('appointment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('appointment_date', '<=', $request->date_to);
        }

        $appointments = $query->get();

        // Create CSV content
        $csvData = [];
        $csvData[] = ['اسم العميل', 'رقم الهاتف', 'التاريخ', 'الوقت', 'الحالة', 'الموظف', 'الملاحظات'];

        foreach ($appointments as $appointment) {
            $statusText = '';
            switch ($appointment->status) {
                case 1: $statusText = 'قيد الانتظار';
                    break;
                case 2: $statusText = 'مكتمل';
                    break;
                case 3: $statusText = 'ملغي';
                    break;
                case 4: $statusText = 'معاد جدلته';
                    break;
                default: $statusText = 'غير معروف';
            }

            $csvData[] = [
                $appointment->client->trade_name ?? 'غير محدد',
                $appointment->client->phone ?? 'غير محدد',
                $appointment->appointment_date,
                $appointment->time,
                $statusText,
                $appointment->employeeNames() ?? 'غير محدد',
                $appointment->notes ?? '',
            ];
        }

        // Generate CSV file
        $filename = 'appointments_'.date('Y-m-d_H-i-s').'.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($csvData) {
            $file = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Get appointments for calendar view
     */
    protected function getCalendarData()
    {
        // بنطاق الرؤية — انظر التعليق في `index()`؛ ومغذّي `calendar()` يمرّ من هنا
        $scoped = Appointment::with(['client', 'createdBy', 'employees', 'employee']);
        $this->scopeAppointmentsQuery($scoped, 'client_follow_up_view_notes_attachments_appointments');

        $appointments = $scoped
            ->whereBetween('appointment_date', [
                now()->subMonths(1)->startOfMonth()->format('Y-m-d'),
                now()->addMonths(6)->endOfMonth()->format('Y-m-d'),
            ])
            ->orderBy('appointment_date')
            ->orderBy('time')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'title' => ($appointment->client->trade_name ?? 'عميل').' - '.$appointment->time,
                    'start' => $appointment->appointment_date,
                    'extendedProps' => [
                        'client_name' => $appointment->client->trade_name ?? 'عميل',
                        'client_phone' => $appointment->client->phone ?? 'غير متوفر',
                        'time' => $appointment->time,
                        'status_code' => $appointment->status,
                        'status_text' => $this->getStatusText($appointment->status),
                        'notes' => $appointment->notes ?? 'لا توجد ملاحظات',
                        'employee' => $appointment->employeeNames() ?? 'غير محدد',
                    ],
                    'className' => 'status-'.$appointment->status,
                    'backgroundColor' => $this->getStatusColorCode($appointment->status),
                    'borderColor' => $this->getStatusColorCode($appointment->status),
                ];
            })->toArray();

        return $appointments;

    }

    /**
     * نموذجُ الموعد.
     *
     * `?client_id=` — من «ترتيب موعد» في شاشة العميل: العميلُ محدَّدٌ مقفل،
     * والإلغاءُ والحفظُ يعودان إلى شاشته. كان الرابطُ بلا معرّف فيختار
     * المستخدمُ العميلَ الذي كان واقفاً عنده من قائمة كلِّ العملاء.
     */
    public function create(Request $request)
    {
        if ($block = $this->sectionBlock('appointments')) {
            return redirect()->route('dashboard')->with('error', $block);
        }

        // العميلُ المقفول من رابط شاشة العميل يمرّ على النطاق أيضاً —
        // `Client::find` كان يفتح أيَّ عميل بمجرّد معرفة معرّفه
        $lockedClient = $request->filled('client_id')
            ? $this->visibleClientsQuery()->whereKey((int) $request->client_id)->first()
            : null;
        $clients = $lockedClient ? collect([$lockedClient]) : $this->visibleClients();

        // الافتراضيّ: الموظفُ الحالي إن كان موظفاً — ويبقى تغييرُه ممكناً
        $selectedEmployees = old('employee_ids', array_filter([auth()->user()->employee_id]));
        $employees = $this->employeeOptions();

        // ما كان النموذج يملأه بيده: المدّة وساعات العمل من الإعدادات
        $defaultDuration = $this->appointmentDefaultDuration();
        $workingHours = [
            'start' => \App\Helpers\ClientSettingsHelper::appointmentWorkingHoursStart(),
            'end' => \App\Helpers\ClientSettingsHelper::appointmentWorkingHoursEnd(),
            'enforced' => \App\Helpers\ClientSettingsHelper::appointmentsEnforceWorkingHours(),
        ];

        $allowPastDates = \App\Helpers\ClientSettingsHelper::appointmentsAllowPastDates();
        $recurringJobActive = $this->recurringJobActive();

        // `?date=` — من نقرة يومٍ في التقويم: يُملأ التاريخُ إن كان صالحاً وحده
        $presetDate = null;

        if ($request->filled('date')) {
            try {
                $presetDate = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', (string) $request->date)->toDateString();
            } catch (\Throwable $e) {
                $presetDate = null;
            }
        }

        return view('client::reservations.appointments.create', compact(
            'clients', 'lockedClient', 'employees', 'selectedEmployees', 'defaultDuration',
            'workingHours', 'allowPastDates', 'recurringJobActive', 'presetDate'
        ));
    }

    /**
     * تخزين موعد جديد.
     */
    public function store(Request $request)
    {
        if ($block = $this->sectionBlock('appointments')) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $block], 403);
            }

            return redirect()->back()->withInput()->with('error', $block);
        }

        // `after_or_equal:today` كانت مكتوبةً في القاعدة — صارت إعداداً
        $dateRules = ['required', 'date'];

        if (! \App\Helpers\ClientSettingsHelper::appointmentsAllowPastDates()) {
            $dateRules[] = 'after_or_equal:today';
        }

        $validator = Validator::make(
            $request->all(),
            [
                // النطاقُ فوق `exists`: العميلُ المُرسَل يجب أن يكون ضمن
                // ما يراه المستخدم (فرع الترويسة/صلاحيات الرؤية) — القائمةُ
                // المعروضة منطاقة، وبدون هذا يُحفظ أيُّ معرّفٍ يُرسَل يدوياً
                'client_id' => ['required', 'exists:clients,id', $this->clientInScopeRule()],
                'date' => $dateRules,
                'time' => 'required|date_format:H:i,H:i:s',
                'duration' => 'nullable|integer|min:1|max:1440',
                'notes' => 'nullable|string|max:500',
                'action_type' => 'required|string|max:50',
            ] + $this->sharedRules(),
            [
                'client_id.required' => 'يجب اختيار العميل',
                'client_id.exists' => 'العميل غير موجود',
                'date.required' => 'يجب إدخال التاريخ',
                'date.date' => 'التاريخ غير صحيح',
                'date.after_or_equal' => 'يجب أن يكون التاريخ اليوم أو في المستقبل',
                'time.required' => 'يجب إدخال الوقت',
                'time.date_format' => 'صيغة الوقت غير صحيحة',
                'notes.max' => 'الملاحظات يجب أن تكون 500 حرف كحد أقصى',
                'action_type.required' => 'يجب اختيار نوع الإجراء',
            ] + $this->sharedMessages(),
        );

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $employeeIds = $this->employeeIdsFrom($request);
        $duration = $request->filled('duration') ? (int) $request->duration : null;
        $request->merge(['time' => $this->normalizeTime($request->time)]);

        // ساعات العمل والتاريخ الماضي — ضابطان من الإعدادات
        $block = $this->appointmentBlock($request->date, $request->time)
            ?? $this->overlapForEmployees($employeeIds, $request->date, $request->time, $duration);

        if ($block) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $block], 422);
            }

            return redirect()->back()->withInput()->with('error', $block);
        }

        try {
            $appointment = DB::transaction(function () use ($request, $employeeIds, $duration) {
                $appointment = new Appointment;
                $appointment->client_id = $request->client_id;
                $appointment->employee_id = $employeeIds[0];
                $appointment->appointment_date = $request->date;
                $appointment->time = $request->time;
                // الفراغ يعني «خذ الافتراضي»، لا موعداً بلا مدّة
                $appointment->duration = $duration ?? $this->appointmentDefaultDuration();
                $appointment->notes = $request->notes;
                $appointment->created_by = auth()->user()->id;
                $appointment->action_type = $request->action_type;
                $appointment->share_with_client = $request->boolean('share_with_client');

                /* كان `recurrence_type` يُرسَل دائماً (قائمةٌ بقيمةٍ مختارة سلفاً)
                   فيُحفظ **كلُّ** موعدٍ متكرّراً وإن لم يُعلَّم المربّع. */
                $appointment->is_recurring = $request->boolean('is_recurring');
                $appointment->recurrence_type = $appointment->is_recurring ? (int) $request->recurrence_type : 1;
                $appointment->recurrence_date = $appointment->is_recurring ? $request->recurrence_date : null;

                $appointment->save();
                $appointment->employees()->sync($employeeIds);

                return $appointment;
            });

            OperationLog::record(
                'created',
                self::LOG_MODULE,
                $appointment,
                'إضافة موعد للعميل '.($appointment->client->trade_name ?? '#'.$appointment->client_id)
                    .' بتاريخ '.$appointment->appointment_date->toDateString().' الساعة '.$appointment->time,
                ['new' => [
                    'client_id' => $appointment->client_id,
                    'appointment_date' => $appointment->appointment_date->toDateString(),
                    'time' => $appointment->time,
                    'duration' => $appointment->duration,
                    'employee_ids' => $employeeIds,
                    'share_with_client' => $appointment->share_with_client,
                    'is_recurring' => $appointment->is_recurring,
                ]]
            );
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => __('reservations.messages.appointment_error')], 500);
            }

            return redirect()->back()->withInput()->with('error', __('reservations.messages.appointment_error'));
        }

        // بعد الحفظ: الموعدُ قائمٌ مهما جرى للبريد والإشعارات
        $share = app(AppointmentClientShare::class);
        $share->notifyEmployees($appointment);
        $shareResult = $share->shareWithClient($appointment);
        $message = 'تم إضافة الموعد بنجاح';

        if ($appointment->share_with_client) {
            $summary = AppointmentClientShare::summary($shareResult, filled($appointment->client?->email));
            $message .= $summary !== '' ? ' — '.$summary : '';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'appointment' => [
                    'id' => $appointment->id,
                    'client_id' => $appointment->client_id,
                    'employee_id' => $appointment->employee_id,
                    'employee_ids' => $employeeIds,
                    'appointment_date' => $appointment->appointment_date,
                    'time' => $appointment->time,
                    'duration' => $appointment->duration,
                    'notes' => $appointment->notes,
                    'action_type' => $appointment->action_type,
                    'status' => $appointment->status,
                    'status_text' => $this->getStatusText($appointment->status),
                ],
            ]);
        }

        // التوجيه إلى صفحة عرض العميل بدلاً من قائمة المواعيد
        return redirect()->route('clients.show', $appointment->client_id)->with('success', $message);
    }

    /**
     * سببُ رفض موعدٍ يتداخل مع موعدٍ آخر لنفس الموظف — أو `null`.
     *
     * التداخل يُحسب بالمدّة لا باللحظة: موعدُ التاسعة مدّته ساعة يتعارض مع
     * موعد التاسعة والنصف وإن اختلف وقت البدء.
     */
    protected function appointmentOverlapBlock(?string $date, ?string $time, ?int $employeeId, ?int $createdBy, ?int $duration = null, ?int $ignoreId = null): ?string
    {
        if (\App\Helpers\ClientSettingsHelper::appointmentsAllowOverlap() || ! $date || ! $time || (! $employeeId && ! $createdBy)) {
            return null;
        }

        $default = $this->appointmentDefaultDuration();
        $start = \Illuminate\Support\Carbon::parse(\Illuminate\Support\Carbon::parse($date)->toDateString().' '.$time);
        $end = $start->copy()->addMinutes($duration ?: $default);

        /* «لنفس الموظف» = الموظفُ المُسنَد إليه الموعد (`employee_id`)، وإلّا من أنشأه.
           كان يُقارن بمن أنشأه وحده، وبالمدّةِ الافتراضية للموعد الجديد ولو كُتبت
           له مدّةٌ أطول — فيمرّ تداخلٌ حقيقي. والملغى لا يحجز وقتاً. */
        $conflict = Appointment::query()
            ->when(
                $employeeId,
                fn ($q) => $q->forEmployee($employeeId),
                fn ($q) => $q->where('created_by', $createdBy)
            )
            ->whereDate('appointment_date', $start->toDateString())
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', Appointment::STATUS_IGNORED))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get()
            ->first(function (Appointment $other) use ($start, $end, $default) {
                if (! $other->time) {
                    return false;
                }

                $otherStart = \Illuminate\Support\Carbon::parse(
                    \Illuminate\Support\Carbon::parse($other->appointment_date)->toDateString().' '.$other->time
                );
                $otherEnd = $otherStart->copy()->addMinutes((int) ($other->duration ?: $default));

                return $start < $otherEnd && $otherStart < $end;
            });

        return $conflict ? __('client_settings.enforcement.appointment_overlap') : null;
    }

    /**
     * عرض تفاصيل موعد.
     */
    public function show($id)
    {
        $appointment = Appointment::with(['client', 'employee', 'employees', 'recurrenceParent'])->findOrFail($id);
        $this->authorizeAppointmentScope($appointment, 'client_follow_up_view_notes_attachments_appointments');
        $client = Client::findOrFail($appointment->client_id);

        // رقمُ الحجز الذي ولّد الموعد — لبطاقة «من الحجز» في الشاشة
        $reservationCode = null;

        if ($appointment->reservation_id) {
            try {
                $reservationCode = \Modules\Client\Reservations\Models\Reservation::withTrashed()
                    ->find($appointment->reservation_id)?->code;
            } catch (\Throwable $e) {
                $reservationCode = null;
            }
        }

        return view('client::reservations.appointments.show', compact('appointment', 'client', 'reservationCode'));
    }

    public function edit($id)
    {
        $appointment = Appointment::with('employees')->findOrFail($id);
        $this->authorizeAppointmentScope($appointment, 'client_follow_up_edit_delete_notes_attachments_appointments');

        if ($block = $this->reservationLockBlock($appointment)) {
            return $this->reservationLockResponse($appointment, $block);
        }

        // عميلُ الموعد نفسُه يبقى في القائمة وإن خرج عن النطاق (`$keep`)
        $clients = $this->visibleClients($appointment->client_id);

        $selectedEmployees = old('employee_ids', $appointment->assignedEmployeeIds());
        // موظفٌ انتهت خدمتُه يبقى ظاهراً في موعده القديم
        $employees = $this->employeeOptions($appointment->assignedEmployeeIds());

        $defaultDuration = $this->appointmentDefaultDuration();
        $workingHours = [
            'start' => \App\Helpers\ClientSettingsHelper::appointmentWorkingHoursStart(),
            'end' => \App\Helpers\ClientSettingsHelper::appointmentWorkingHoursEnd(),
            'enforced' => \App\Helpers\ClientSettingsHelper::appointmentsEnforceWorkingHours(),
        ];
        $allowPastDates = \App\Helpers\ClientSettingsHelper::appointmentsAllowPastDates();
        $recurringJobActive = $this->recurringJobActive();

        return view('client::reservations.appointments.edit', compact(
            'appointment', 'clients', 'employees', 'selectedEmployees', 'defaultDuration',
            'workingHours', 'allowPastDates', 'recurringJobActive'
        ));
    }

    /**
     * تحديث الموعد.
     *
     * الموظفون إلزاميّون كما في الإنشاء، ومن أُضيف منهم يُشعَر. والمشاركةُ
     * تُبلغ العميل حين تُفعَّل الآن، أو حين يتغيّر وقتُ موعدٍ مُشارَكٍ أصلاً —
     * لا عند كلّ حفظٍ لملاحظة.
     */
    public function update(Request $request, $id)
    {
        $current = Appointment::find($id);
        $dateRules = ['nullable', 'date'];

        /* التاريخُ الماضي يُمنع حين **يتغيّر** التاريخ فقط: موعدُ الأمس يجب أن
           تُحدَّث حالتُه أو ملاحظتُه دون أن يُرفض لأنّ يومه مضى. */
        if (! \App\Helpers\ClientSettingsHelper::appointmentsAllowPastDates()
            && (! $current || ! $request->filled('date')
                || \Illuminate\Support\Carbon::parse($request->date)->toDateString() !== \Illuminate\Support\Carbon::parse($current->appointment_date)->toDateString())) {
            $dateRules[] = 'after_or_equal:today';
        }

        // نهايةُ التكرار بعد تاريخ الموعد — المُرسَل، وإلّا المحفوظ
        $referenceDate = $request->filled('date')
            ? (string) $request->date
            : ($current ? \Illuminate\Support\Carbon::parse($current->appointment_date)->toDateString() : 'today');

        $validator = Validator::make($request->all(), [
            // عميلُ الموعد الحاليّ مسموحٌ به وإن خرج عن النطاق ($keep)
            'client_id' => ['nullable', 'exists:clients,id', $this->clientInScopeRule($current?->client_id)],
            'date' => $dateRules,
            'time' => 'nullable|date_format:H:i,H:i:s',
            'duration' => 'nullable|integer|min:1|max:1440',
            'action_type' => 'nullable|string|max:50',
            'status' => 'required|in:'.implode(',', [Appointment::STATUS_PENDING, Appointment::STATUS_COMPLETED, Appointment::STATUS_IGNORED, Appointment::STATUS_RESCHEDULED]),
            'notes' => 'nullable|string|max:500',
        ] + $this->sharedRules($referenceDate), $this->sharedMessages());

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $appointment = Appointment::findOrFail($id);
        $this->authorizeAppointmentScope($appointment, 'client_follow_up_edit_delete_notes_attachments_appointments');

        if ($block = $this->reservationLockBlock($appointment)) {
            return $this->reservationLockResponse($appointment, $block);
        }

        $newDate = $request->filled('date') ? $request->date : $appointment->appointment_date;
        $newTime = $request->filled('time') ? $this->normalizeTime($request->time) : $appointment->time;
        $employeeIds = $this->employeeIdsFrom($request);
        $previousEmployeeIds = $appointment->assignedEmployeeIds();
        $addedEmployeeIds = array_values(array_diff($employeeIds, $previousEmployeeIds));

        // ساعات العمل والتاريخ الماضي — نفس ضابطَي الإنشاء، فقط إذا تغيّر الموعد فعلاً
        $newDuration = $request->filled('duration') ? (int) $request->duration : ((int) $appointment->duration > 0 ? (int) $appointment->duration : null);
        $dateChanged = \Illuminate\Support\Carbon::parse($newDate)->toDateString() !== \Illuminate\Support\Carbon::parse($appointment->appointment_date)->toDateString();
        $changedMoment = $dateChanged
            || substr((string) $newTime, 0, 5) !== substr((string) $appointment->time, 0, 5)
            || $newDuration !== ((int) $appointment->duration > 0 ? (int) $appointment->duration : null);

        if ($changedMoment) {
            /* ضابطُ التاريخ الماضي على التاريخ **المتغيّر** وحده — كما في قاعدة
               التحقّق أعلاه؛ كان يُطبَّق على تاريخ الموعد القديم حين يتغيّر وقتُه
               أو مدّتُه فقط، فموعدُ الأمس لا تُصحَّح مدّتُه. ساعاتُ العمل تُفحص
               بالوقت وحده، فيوم اليوم يكفيها حين لم يتغيّر التاريخ. */
            if ($block = $this->appointmentBlock($dateChanged ? $newDate : now()->toDateString(), $newTime)) {
                return redirect()->back()->withInput()->with('error', $block);
            }
        }

        // التداخل: عند كلّ الموظفين إن تغيّر الوقت، وعند المضافين وحدَهم إن لم يتغيّر
        $checkIds = $changedMoment ? $employeeIds : $addedEmployeeIds;

        if ($block = $this->overlapForEmployees($checkIds, \Illuminate\Support\Carbon::parse($newDate)->toDateString(), $newTime, $newDuration, $appointment->id)) {
            return redirect()->back()->withInput()->with('error', $block);
        }

        $before = $appointment->only(['client_id', 'appointment_date', 'time', 'duration', 'action_type', 'status', 'notes', 'share_with_client', 'is_recurring'])
            + ['employee_ids' => $previousEmployeeIds];
        $wasShared = (bool) $appointment->share_with_client;

        try {
            DB::transaction(function () use ($request, $appointment, $newDate, $newTime, $employeeIds) {
                if ($request->filled('client_id')) {
                    $appointment->client_id = $request->client_id;
                }
                $appointment->appointment_date = $newDate;
                $appointment->time = $newTime;
                if ($request->filled('duration')) {
                    $appointment->duration = (int) $request->duration;
                }
                if ($request->filled('action_type')) {
                    $appointment->action_type = $request->action_type;
                }
                $appointment->status = $request->status;
                if ($request->filled('notes')) {
                    $appointment->notes = $request->notes;
                }
                $appointment->employee_id = $employeeIds[0];
                $appointment->share_with_client = $request->boolean('share_with_client');

                // الموعدُ المولَّد من سلسلةٍ لا يصير أصلَ سلسلةٍ جديدة
                if (! $appointment->recurrence_parent_id) {
                    $appointment->is_recurring = $request->boolean('is_recurring');
                    $appointment->recurrence_type = $appointment->is_recurring ? (int) $request->recurrence_type : 1;
                    $appointment->recurrence_date = $appointment->is_recurring ? $request->recurrence_date : null;
                }

                $appointment->save();
                $appointment->employees()->sync($employeeIds);
            });

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $appointment,
                'تحديث موعد العميل '.($appointment->client->trade_name ?? '#'.$appointment->client_id),
                [
                    'old' => $before,
                    'new' => $appointment->only(['client_id', 'appointment_date', 'time', 'duration', 'action_type', 'status', 'notes', 'share_with_client', 'is_recurring'])
                        + ['employee_ids' => $employeeIds],
                ]
            );
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update', $appointment->id);

            return redirect()->back()->withInput()
                ->with('error', __('reservations.messages.appointment_error'));
        }

        $share = app(AppointmentClientShare::class);
        $message = 'تم تحديث الموعد بنجاح';

        if ($addedEmployeeIds !== []) {
            $share->notifyEmployees($appointment->fresh(), $addedEmployeeIds);
        }

        if ($appointment->share_with_client && (! $wasShared || $changedMoment)) {
            $appointment->unsetRelation('employees');
            $summary = AppointmentClientShare::summary(
                $share->shareWithClient($appointment, $wasShared ? 'updated' : 'created'),
                filled($appointment->client?->email)
            );
            $message .= $summary !== '' ? ' — '.$summary : '';
        }

        return redirect()
            ->route('appointments.index')
            ->with('success', $message);
    }

    /**
     * حذفُ موعد.
     *
     * يُنادى من قائمة المواعيد (AJAX، `DELETE`) ومن شاشة الموعد (نموذج) —
     * فالردُّ JSON للأولى، وعودةٌ إلى القائمة للثانية لأنّ «السابق» هو
     * شاشةُ الموعد المحذوف نفسِه.
     */
    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);
        $this->authorizeAppointmentScope($appointment, 'client_follow_up_edit_delete_notes_attachments_appointments');

        if ($block = $this->reservationLockBlock($appointment)) {
            return $this->reservationLockResponse($appointment, $block);
        }

        $wantsJson = request()->ajax() || request()->wantsJson();

        try {
            // السجلّ قبل الحذف: بعده لا يبقى ما يُوصَف
            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $appointment,
                'حذف موعد العميل '.($appointment->client->trade_name ?? '#'.$appointment->client_id)
                    .' بتاريخ '.$appointment->appointment_date,
                ['old' => [
                    'client_id' => $appointment->client_id,
                    'appointment_date' => $appointment->appointment_date,
                    'time' => $appointment->time,
                    'status' => $appointment->status,
                ]]
            );

            $appointment->employees()->detach();
            $appointment->delete();
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'destroy', (int) $id);

            if ($wantsJson) {
                return response()->json(['success' => false, 'message' => __('reservations.messages.appointment_error')], 500);
            }

            return redirect()->back()->with('error', __('reservations.messages.appointment_error'));
        }

        $message = 'تم حذف الموعد وجميع البيانات المرتبطة به بنجاح';

        if ($wantsJson) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('appointments.index')->with('success', $message);
    }

    /*
        أُزيلت دوالٌ بلا مسار — لا يصل إليها طلب، ولا يناديها قالب:
        `ignore()`، `complete()`، `markStatus()` (كانت تكتب حالةً نصّية في عمود
        رقمي)، `addNote()`، `destroyAppointment()` (نسخةٌ ثانية من `destroy()`
        بلا سجلّ)، `formatCalendarBookings()`، `getStatusColor()`.
    */

    /**
     * Get appointments for calendar view
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function calendar()
    {
        $appointments = $this->getCalendarData();

        return response()->json($appointments);
    }

    /**
     * Get color code for status
     */
    protected function getStatusColorCode($status)
    {
        $colorMap = [
            1 => '#ffc107',    // Yellow - Pending
            2 => '#28a745',    // Green - Completed
            3 => '#dc3545',    // Red - Cancelled
            4 => '#17a2b8',    // Cyan - Rescheduled
        ];

        return $colorMap[$status] ?? '#6c757d'; // Gray - Default
    }
}
