<?php

namespace Modules\Client\Http\Controllers\Reservations;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\ClientModuleSetting;
use App\Support\ClientSettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Models\ReservationSetting;
use Modules\Client\Reservations\Services\ReservationAvailabilityService;
use Throwable;

/**
 * شاشةُ إعدادات وحدة الحجوزات.
 *
 * ═══════════ كلُّ مفتاحٍ هنا يحرس شيئاً ═══════════
 *
 * مفتاحٌ يُعرَض ولا يقرؤه أحد **أسوأ من غيابه**: يَعِد المستخدمَ بضبطٍ لا
 * يقع، فيظنّ أنه منع تكرارَ الحجز وهو مسموح. وقائمةُ من يقرأ كلَّ مفتاح
 * في `docs/reservations/DEVELOPER_GUIDE.md` — تُحدَّث مع كلّ إضافة.
 *
 * ═══════════ والترقيمُ والحساباتُ ليسا هنا ═══════════
 *
 * ترقيمُ الحجز في شاشة «الترقيم المتسلسل» (القسم `reservation`)،
 * والحساباتُ في شاشة «الحسابات المحاسبية» (المفاتيح `reservation_*`).
 * وتكرارُهما هنا يعني مصدرَين للحقيقة يفترقان. فالشاشةُ تعرض **حالتَهما**
 * وتُحيل إليهما بروابط، ولا تحفظهما.
 */
class ReservationSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:reservation_settings.view', ['only' => ['index']]);
        $this->middleware('permission:reservation_settings.update', ['only' => ['update']]);
    }

    public function index()
    {
        $settings = ReservationSetting::current();

        return view('client::reservations.settings.index', [
            'settings' => $settings,
            'statuses' => ReservationStatus::cases(),

            // تبويب «الموظفون»: من يُحجز له — قسمٌ أو مسمّى وظيفي
            'departments' => DB::table('departments')->orderBy('name')->get(['id', 'name']),
            'jobTitles' => DB::table('jop_titles')->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderBy('name')->get(['id', 'name']),
            'eligibleCount' => app(ReservationAvailabilityService::class)->eligibleEmployees(null)->count(),

            // رقمُ الحجز التالي — للاطّلاع؛ الترقيمُ من «الترقيم المتسلسل» في إعدادات النظام
            'nextNumber' => peek_document_number('reservation'),

            // مواعيدُ العملاء — تُعدَّل هنا وتُحفظ في مخزنها في وحدة العملاء
            'clientAppointments' => $this->clientAppointmentSettings(),

            // مهمّةُ «تذكير الحجوزات القادمة» — المهلُ تُحفظ هنا والإرسالُ منها،
            // فتُعرض حالتُها بجانب الحقل كما تُعرض مهمّةُ المواعيد
            'reminderCron' => $this->cronStatus('reservations:reminders'),
        ]);
    }

    /**
     * حالةُ مهمّةٍ مجدولة بمعالجها — للعرض بجانب الإعداد الذي تقرؤه.
     *
     * @return array{active: bool, last_run_at: ?string, url: ?string}
     */
    protected function cronStatus(string $handler): array
    {
        $cron = DB::table('cron_jobs')->where('handler', $handler)->first(['status', 'last_run_at']);

        return [
            'active' => $cron !== null && $cron->status === 'active',
            'last_run_at' => $cron?->last_run_at,
            'url' => Route::has('CronJobs.index') ? route('CronJobs.index') : null,
        ];
    }

    public function update(Request $request)
    {
        $validated = $request->validate(
            $this->rules() + $this->appointmentRules(),
            ['appointment_working_hours_end.after' => 'نهاية ساعات عمل المواعيد يجب أن تكون بعد بدايتها.'],
            $this->attributes() + $this->appointmentAttributes()
        );

        // مواعيدُ العملاء لا تسكن `reservation_settings` — تُحفظ في مخزنها أدناه
        $validated = Arr::except($validated, array_keys($this->appointmentRules()));

        $settings = ReservationSetting::current();

        try {
            // الخانات غير المؤشَّرة لا تُرسَل أصلاً — فتُصفَّر صراحةً وإلا
            // استحال إطفاءُ مفتاحٍ مفعَّل
            foreach ($this->booleanKeys() as $key) {
                $validated[$key] = $request->boolean($key);
            }

            // القائمتان تُحفظان فارغتين إن لم تُرسَلا — فإلغاءُ آخر قسمٍ مختار يقع فعلاً
            $validated['employee_department_ids'] = array_values(array_map('intval', array_filter((array) $request->input('employee_department_ids', []))));
            $validated['employee_job_title_ids'] = array_values(array_map('intval', array_filter((array) $request->input('employee_job_title_ids', []))));

            $settings->fill($validated + ['updated_by' => auth()->id()]);
            // `diff()` يقارن نصّاً — فالقائمتان (مصفوفتان) تُقارَنان هنا بـJSON
            $arrayKeys = ['employee_department_ids', 'employee_job_title_ids'];
            $changes = OperationLog::diff($settings, array_values(array_filter(
                array_keys($this->rules()),
                fn ($key) => ! str_contains($key, '*') && ! in_array($key, $arrayKeys, true)
            )));
            foreach ($arrayKeys as $key) {
                if (json_encode(array_values((array) $settings->getOriginal($key))) !== json_encode(array_values((array) $settings->{$key}))) {
                    $changes['old'][$key] = $settings->getOriginal($key);
                    $changes['new'][$key] = $settings->{$key};
                }
            }

            $appointmentChanges = DB::transaction(function () use ($settings, $request) {
                $settings->save();

                return $this->saveClientAppointmentSettings($request);
            });

            $changes['old'] = array_merge($changes['old'] ?? [], $appointmentChanges['old']);
            $changes['new'] = array_merge($changes['new'] ?? [], $appointmentChanges['new']);

            ReservationSetting::forget();
            ClientSettings::forget();
        } catch (Throwable $e) {
            OperationLog::failure($e, 'ReservationSettings', 'update');

            return back()->withInput()->with('error', 'تعذّر حفظ الإعدادات. راجع سجلّ الأخطاء.');
        }

        OperationLog::record('updated', 'ReservationSettings', $settings, 'تعديل إعدادات الحجوزات', $changes);

        return back()->with('success', 'تم حفظ الإعدادات.');
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            /*
                ما في الشاشة وحدَه. أُزيل (2026-09-10): التفعيل (لا يقرؤه أحد)،
                والفرعُ الافتراضي (الحجزُ يتبع فرع موظّفه)، و«اليوم غير المضبوط»
                (الدوامُ من الورديات)، والعربونُ ورسمُ الإلغاء والخزينةُ والفوترةُ
                التلقائية (لا مالَ في الحجز). أعمدتُها باقيةٌ بقيمها ولا يقرؤها شيء.
            */

            // عام
            'default_status' => ['required', Rule::in(array_column(ReservationStatus::cases(), 'value'))],
            'allow_repeated_booking' => ['nullable', 'boolean'],
            'allow_overbooking' => ['nullable', 'boolean'],
            'max_daily_reservations' => ['nullable', 'integer', 'min:0'],

            // الموظفون — القائمةُ مطلوبةٌ حين يُختار القيدُ الذي تخصّه
            'employee_filter' => ['required', Rule::in(['all', 'department', 'job_title'])],
            'employee_department_ids' => ['nullable', 'array', 'required_if:employee_filter,department'],
            'employee_department_ids.*' => ['integer', 'exists:departments,id'],
            'employee_job_title_ids' => ['nullable', 'array', 'required_if:employee_filter,job_title'],
            'employee_job_title_ids.*' => ['integer', 'exists:jop_titles,id'],

            // المواعيد
            'default_duration' => ['required', 'integer', 'min:1', 'max:1440'],
            'min_duration' => ['required', 'integer', 'min:1', 'max:1440'],
            'max_duration' => ['required', 'integer', 'min:1', 'max:1440', 'gte:min_duration'],
            'slot_interval' => ['required', 'integer', 'min:5', 'max:240'],
            'min_advance_minutes' => ['nullable', 'integer', 'min:0'],
            'max_advance_days' => ['nullable', 'integer', 'min:0', 'max:3650'],

            // الإلغاء
            'allow_cancellation' => ['nullable', 'boolean'],
            'cancellation_deadline_hours' => ['nullable', 'integer', 'min:0'],
            'allow_cancel_after_confirm' => ['nullable', 'boolean'],

            // إعادة الجدولة
            'allow_rescheduling' => ['nullable', 'boolean'],
            'reschedule_deadline_hours' => ['nullable', 'integer', 'min:0'],
            'max_reschedule_attempts' => ['nullable', 'integer', 'min:0', 'max:50'],

            // العميل
            'require_client' => ['nullable', 'boolean'],
            'require_mobile' => ['nullable', 'boolean'],
            'require_email' => ['nullable', 'boolean'],

            // الإشعارات
            'notify_created' => ['nullable', 'boolean'],
            'notify_confirmed' => ['nullable', 'boolean'],
            'notify_cancelled' => ['nullable', 'boolean'],
            'notify_rescheduled' => ['nullable', 'boolean'],
            'notify_reminder' => ['nullable', 'boolean'],
            'notify_reminder_employee' => ['nullable', 'boolean'],
            'notify_completed' => ['nullable', 'boolean'],
            'notify_no_show' => ['nullable', 'boolean'],
            'reminder_offsets' => ['nullable', 'string', 'max:120', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],

            // متقدّم
            'calendar_default_view' => ['required', Rule::in(['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listWeek'])],
        ];
    }

    /**
     * المفاتيحُ المنطقية — تُصفَّر صراحةً عند الحفظ.
     *
     * خانةُ الاختيار غير المؤشَّرة **لا تُرسَل في الطلب أصلاً**، فلا
     * يجدها `validated()` ولا تُحدَّث — ويستحيل إطفاءُ مفتاحٍ مفعَّل.
     * وهو عطلٌ صامت: المستخدم يُطفئ المفتاحَ ويحفظ ويجده مضاءً.
     *
     * @return array<int, string>
     */
    protected function booleanKeys(): array
    {
        return array_keys(array_filter(
            $this->rules(),
            fn ($rules) => in_array('boolean', $rules, true)
        ));
    }

    /**
     * أسماءُ الحقول في رسائل التحقّق — **لكلّ حقلٍ في القواعد**.
     *
     * كانت عشرةً فقط، فظهر الباقي باسمه البرمجي مفكّكاً في الرسالة
     * («treat unset days as closed»). والأسماءُ من ملفّ الترجمة.
     *
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return collect(array_keys($this->rules()))
            ->reject(fn (string $key) => str_contains($key, '*'))
            ->mapWithKeys(fn (string $key) => [$key => __('reservations.settings_fields.'.$key)])
            ->all();
    }

    // ═══════════ مواعيدُ العملاء ═══════════

    /**
     * إعداداتُ «مواعيد العملاء» — مكانُ تعديلها هذه الشاشة منذ 2026-09-13.
     *
     * كانت بطاقةً في «إعدادات وحدات العملاء ← المتابعة» ومفتاحاً في «تفعيل
     * الأقسام» هناك، ثم انتقلت شاشاتُ المواعيد إلى وحدة الحجوزات فبقيت
     * إعداداتُها في وحدةٍ لم تعد تسكنها. **نُقل مكانُ التعديل وحده**: القيمُ
     * باقيةٌ في `general_client_settings` (المجموعة `appointment_settings`) وفي
     * `client_module_settings.appointments_enabled`، فكلُّ قارئٍ لها —
     * `ClientSettingsHelper::appointment*()` و`EnforcesClientModuleSettings`
     * وأمر `clients:appointment-reminders` والأشرطة الجانبية — لم يتغيّر، ولم
     * تضع قيمةٌ ضبطها المستخدم.
     *
     * @return array<string, mixed>
     */
    protected function clientAppointmentSettings(): array
    {
        $cron = $this->cronStatus('clients:appointment-reminders');

        return [
            'enabled' => (bool) ClientModuleSetting::current()->appointments_enabled,
            'duration' => ClientSettingsHelper::appointmentDefaultDurationMinutes(),
            'reminder_hours' => ClientSettingsHelper::appointmentReminderHoursBefore(),
            'working_start' => substr(ClientSettingsHelper::appointmentWorkingHoursStart(), 0, 5),
            'working_end' => substr(ClientSettingsHelper::appointmentWorkingHoursEnd(), 0, 5),
            'enforce_working_hours' => ClientSettingsHelper::appointmentsEnforceWorkingHours(),
            'allow_overlap' => ClientSettingsHelper::appointmentsAllowOverlap(),
            'allow_past_dates' => ClientSettingsHelper::appointmentsAllowPastDates(),
            'auto_complete_past' => ClientSettingsHelper::appointmentsAutoCompletePast(),
            'cron_active' => $cron['active'],
            'cron_url' => $cron['url'],
        ];
    }

    /** @return array<string, array<int, string>> */
    protected function appointmentRules(): array
    {
        return [
            'appointments_enabled' => ['nullable', 'boolean'],
            'appointment_default_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'appointment_reminder_hours_before' => ['nullable', 'integer', 'min:1', 'max:336'],
            'appointment_working_hours_start' => ['required', 'date_format:H:i'],
            'appointment_working_hours_end' => ['required', 'date_format:H:i', 'after:appointment_working_hours_start'],
            'appointment_enforce_working_hours' => ['nullable', 'boolean'],
            'appointment_allow_overlap' => ['nullable', 'boolean'],
            'appointment_allow_past_dates' => ['nullable', 'boolean'],
            'appointment_auto_complete_past' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function appointmentAttributes(): array
    {
        return [
            'appointments_enabled' => 'تفعيل مواعيد العملاء',
            'appointment_default_duration_minutes' => 'مدّة الموعد الافتراضية',
            'appointment_reminder_hours_before' => 'التذكير قبل الموعد',
            'appointment_working_hours_start' => 'بداية ساعات عمل المواعيد',
            'appointment_working_hours_end' => 'نهاية ساعات عمل المواعيد',
            'appointment_enforce_working_hours' => 'رفض المواعيد خارج ساعات العمل',
            'appointment_allow_overlap' => 'السماح بموعدين متداخلين',
            'appointment_allow_past_dates' => 'السماح بموعد بتاريخ مضى',
            'appointment_auto_complete_past' => 'إقفال المواعيد الفائتة تلقائياً',
        ];
    }

    /**
     * يحفظ مواعيدَ العملاء في مخزنها، ويعيد الفرقَ لسجلّ النشاط.
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    protected function saveClientAppointmentSettings(Request $request): array
    {
        $group = ClientSettings::group('appointment_settings');

        $new = [
            'default_duration_minutes' => (int) $request->input('appointment_default_duration_minutes'),
            'reminder_hours_before' => $request->filled('appointment_reminder_hours_before')
                ? (int) $request->input('appointment_reminder_hours_before')
                : 0, // صفر = بلا تذكير؛ `null` كان يُقرأ افتراضَ الـ24 ساعة
            'working_hours_start' => (string) $request->input('appointment_working_hours_start'),
            'working_hours_end' => (string) $request->input('appointment_working_hours_end'),
            'enforce_working_hours' => $request->boolean('appointment_enforce_working_hours'),
            'allow_overlap' => $request->boolean('appointment_allow_overlap'),
            'allow_past_dates' => $request->boolean('appointment_allow_past_dates'),
            'auto_complete_past' => $request->boolean('appointment_auto_complete_past'),
        ];

        $old = [];
        foreach (array_keys($new) as $key) {
            $old[$key] = $group->option($key);
        }

        $group->mergeOptions($new);
        $group->save();

        $module = ClientModuleSetting::current();
        $old['appointments_enabled'] = (bool) $module->appointments_enabled;
        $new['appointments_enabled'] = $request->boolean('appointments_enabled');
        $module->appointments_enabled = $new['appointments_enabled'];
        $module->updated_by = auth()->id();
        $module->save();

        $changes = ['old' => [], 'new' => []];
        foreach ($new as $key => $value) {
            if (json_encode($old[$key]) !== json_encode($value)) {
                $changes['old']['client_appointments.'.$key] = $old[$key];
                $changes['new']['client_appointments.'.$key] = $value;
            }
        }

        return $changes;
    }
}
