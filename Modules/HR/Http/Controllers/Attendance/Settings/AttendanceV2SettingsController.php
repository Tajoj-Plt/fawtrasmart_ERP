<?php

namespace Modules\HR\Http\Controllers\Attendance\Settings;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceGeofence;
use App\Models\AttendanceKiosk;
use App\Models\AttendancePhotoType;
use App\Models\AttendanceSetting;
use App\Models\Branch;
use App\Models\CronJob;
use App\Models\Deduction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAttendanceConfig;
use App\Services\Cron\CronJobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\HR\Http\Controllers\Concerns\ScopesToAccessibleEmployees;

/**
 * إعدادات نظام الحضور والانصراف V2.
 *
 * يدير:
 *   - الإعدادات العامة (singleton)
 *   - إعدادات الموظف الفردية (override)
 *   - أنواع صور الحضور
 */
class AttendanceV2SettingsController extends Controller
{
    use ScopesToAccessibleEmployees;

    public function __construct()
    {
        /*
            كان هذا المتحكّم **بلا حارسٍ واحد**: أيّ موظفٍ مسجَّل دخوله
            يستدعي مساراته مباشرةً.
        */
        // الإعداداتُ العامة للحضور — والإعدادُ الفرديّ لموظفٍ مقصورٌ على نطاق المستخدم
        $this->middleware('permission.role:attendance_settings.manage');
    }

    public function index()
    {
        $setting = AttendanceSetting::current();
        $photoTypes = AttendancePhotoType::orderBy('sort_order')->orderBy('name')->get();

        // nickname جزءٌ من الاسم الكامل (Employee::getFullNameAttribute)، وكان
        // إسقاطه من select يجعل الأسماء تظهر ناقصةً في القائمة والجدول.
        // النشطون والمعيَّنون تحت التجربة — كان التجريبيّ يسقط من القائمة
        $employees = $this->scopeToEmployees(Employee::whereIn('status', EmploymentStatus::activeValues()), 'id')
            ->orderBy('first_name')
            ->select('id', 'first_name', 'middle_name', 'nickname', 'employee_code', 'attendance_channel', 'attendance_geofence_id', 'department_id', 'branch_id')
            ->get();

        $perEmployeeConfigs = $this->scopeToEmployees(EmployeeAttendanceConfig::with('employee:id,first_name,middle_name,nickname,attendance_channel,attendance_geofence_id'))
            ->orderBy('employee_id')
            ->paginate(20)
            ->withQueryString();

        $geofences = AttendanceGeofence::active()->orderBy('name')->get(['id', 'name']);

        /*
            لاختيار الموظفين دفعةً — أقسامٌ أو فروعٌ كاملة بدل موظفٍ موظف.

            كانت القائمتان تُبنيان من أقسام/فروع موظفي القائمة فقط، فلم يظهر إلا
            قسمٌ واحد (قلّةٌ من الموظفين لهم قسم)، وكان الفرعُ هو الرئيسي وحده
            فلا يُضاف من يعمل في فرعٍ إضافي (`employee_branch`). الآن: كلُّ
            الأقسام، والفروعُ الجذرية وحدها (أكبرُ أبٍ في الشجرة)، والعددُ بجانب
            كلٍّ، وما لا موظفين فيه يظهر معطّلاً.

            والفرعُ الجذري يشمل كلَّ من تحته: موظفٌ مسجَّلٌ على فرعٍ فرعيّ أو عقدة
            «تابع لـ» يُنسب إلى جذرها — فاختيارُ الجذر يجلب الشجرةَ كلَّها.
        */
        $extraBranches = DB::table('employee_branch')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get(['employee_id', 'branch_id'])
            ->groupBy('employee_id');

        $parentOf = Branch::pluck('parent_id', 'id');
        $rootOf = function ($id) use ($parentOf) {
            for ($guard = 0; $id && $parentOf->get($id) && $guard < 50; $guard++) {
                $id = $parentOf->get($id);
            }

            return $parentOf->has($id) ? $id : null;
        };

        $employees->each(function ($emp) use ($extraBranches, $rootOf) {
            $emp->setAttribute('branch_ids', collect([$emp->branch_id])
                ->merge($extraBranches->get($emp->id, collect())->pluck('branch_id'))
                ->filter()->map($rootOf)->filter()->unique()->values()->all());
        });

        $departments = Department::orderBy('name')->get(['id', 'name'])
            ->each(fn ($d) => $d->setAttribute('emp_count', $employees->where('department_id', $d->id)->count()));
        $branches = Branch::whereNull('parent_id')->orderBy('name')->get(['id', 'name'])
            ->each(fn ($b) => $b->setAttribute('emp_count', $employees->filter(fn ($e) => in_array($b->id, $e->branch_ids))->count()));

        // خصمياتُ الحضور المعلّقة بانتظار الاعتماد — رابطٌ مباشر من بطاقة الخصميات
        $pendingDeductions = Deduction::where('status', Deduction::STATUS_PENDING)
            ->whereIn('deduction_type', [Deduction::TYPE_ABSENCE, Deduction::TYPE_LATE, Deduction::TYPE_EARLY_LEAVE, Deduction::TYPE_HALF_DAY])
            ->count();

        // المراجعةُ الليلية للحضور (غيابُ من لم يسجّل، وإقفالُ أيام الأمس) — مهمةٌ مجدولة
        $nightlyJob = CronJob::where('handler', 'attendance:process')->first();

        // أجهزةُ الفروع — لتبويب «الأماكن والأجهزة» وبطاقة الإحصاء
        $kiosks = AttendanceKiosk::with('branch:id,name')->withCount('employees')->orderBy('name')
            ->get(['id', 'name', 'branch_id', 'is_active', 'last_seen_at']);

        // أجهزةُ كلِّ موظفٍ في جدول الإعدادات الخاصة
        $employeeKiosks = DB::table('attendance_kiosk_employee')
            ->join('attendance_kiosks', 'attendance_kiosks.id', '=', 'attendance_kiosk_employee.kiosk_id')
            ->whereIn('attendance_kiosk_employee.employee_id', $perEmployeeConfigs->pluck('employee_id'))
            ->get(['attendance_kiosk_employee.employee_id', 'attendance_kiosks.name'])
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        return view('hr::attendance.settings.v2_index', compact(
            'setting', 'photoTypes', 'employees', 'perEmployeeConfigs', 'geofences', 'departments', 'branches', 'nightlyJob', 'pendingDeductions', 'kiosks', 'employeeKiosks'
        ));
    }

    public function updateGeneral(Request $request)
    {
        /*
            location_check_enabled و ip_check_enabled ليسا هنا: جدولُ
            attendance_settings يحمل مفتاحَي تشغيلٍ فقط، بلا إحداثياتٍ ولا نطاقٍ
            ولا قائمةِ عناوين — فلا يملك ما يتحقّق به. الهندسةُ كلُّها في
            «محددات الحضور»، وهي تُطبَّق الآن فعلاً على تسجيل الحضور عبر
            AttendanceEntryGuard، فالشاشةُ تحيل إليها بدل مفتاحين بلا أثر.

            أما early_leave_threshold_minutes و photos_count_checkin/checkout
            فصارت مقروءةً فعلاً: الأولى في AttendanceProcessorService،
            والأخريان في AttendancePhotoRecorder.
        */
        $validated = $request->validate([
            'late_grace_minutes' => 'required|integer|min:0|max:480',
            'absent_after_minutes' => 'required|integer|min:0|max:1440',
            'auto_deduction_enabled' => 'nullable|boolean',
            'late_deduction_amount' => 'nullable|numeric|min:0|max:1000000',
            'absence_deduction_amount' => 'nullable|numeric|min:0|max:1000000',
            'early_leave_deduction_amount' => 'nullable|numeric|min:0|max:1000000',
            'deduction_calculation_type' => 'required|in:fixed,daily_salary,per_minute',
            'deduction_approval' => 'required|in:auto,manual',
            'work_hours_per_day' => 'required|numeric|min:1|max:24',
            'checkout_method' => 'required|in:hours,route,both',
            'photos_required' => 'nullable|boolean',
            'photos_count_checkin' => 'nullable|integer|min:0|max:10',
            'photos_count_checkout' => 'nullable|integer|min:0|max:10',
            'early_leave_threshold_minutes' => 'nullable|integer|min:0|max:480',
            // نافذةُ الانصراف العامّة — الفراغُ يعني لا قيد
            'checkout_window_minutes' => 'nullable|integer|min:0|max:1440',
            'checkin_early_minutes' => 'required|integer|min:0|max:720',
            /*
                «يُقفل تسجيل الحضور بعد» — الفراغُ يعني **لا يُقفل**، وهو
                الافتراض. ضابطٌ مستقلٌّ عن `absent_after_minutes`: تلك تحكم
                على اليوم، وهذه تمنع الختمَ أصلاً. وكانت واحدةً تفعلهما.
            */
            'checkin_close_after_minutes' => 'nullable|integer|min:0|max:1440',
            // قيودُ «بدء زيارة العميل» — ثلاثُ خانات، كلٌّ بحقلٍ مخفيٍّ يسبقها
            'visit_requires_check_in' => 'nullable|boolean',
            'visit_blocked_after_check_out' => 'nullable|boolean',
            'visit_blocked_during_break' => 'nullable|boolean',
            'absent_below_minutes' => 'required|integer|min:0|max:1440',
            'half_day_below_percent' => 'required|integer|min:0|max:100',
            'break_allowed_minutes' => 'required|integer|min:0|max:480',
            // الورديةُ الافتراضية لمن لا وردية له
            'default_shift_start' => 'nullable|date_format:H:i',
            'default_weekly_off_days' => 'nullable|array',
            'default_weekly_off_days.*' => 'in:sat,sun,mon,tue,wed,thu,fri',
        ], [], [
            'late_grace_minutes' => 'مدة السماح للتأخير',
            'absent_after_minutes' => 'اعتبار غياب بعد',
            'checkin_close_after_minutes' => 'إقفال تسجيل الحضور بعد',
            'work_hours_per_day' => 'عدد ساعات العمل اليومي',
        ]);

        // حدُّ الغياب يجب أن يقع بعد حدِّ التأخير، وإلا صار الموظف غائباً قبل
        // أن يُعَدَّ متأخراً وانقلبت القاعدتان على بعضهما.
        if ($validated['absent_after_minutes'] < $validated['late_grace_minutes']) {
            return back()->withInput()->withErrors([
                'absent_after_minutes' => 'مدة اعتبار الغياب يجب أن تساوي مدة السماح للتأخير أو تزيد عليها.',
            ]);
        }

        /*
            والإقفالُ — إن ضُبط — لا يسبق مهلةَ السماح: نافذةٌ تُقفل قبل أن
            يُعدَّ الموظفُ متأخّراً تمنع ختماً كان سيُحتسب حضوراً بلا خصم.
        */
        $closeAfter = $validated['checkin_close_after_minutes'] ?? null;
        if ($closeAfter !== null && $closeAfter < $validated['late_grace_minutes']) {
            return back()->withInput()->withErrors([
                'checkin_close_after_minutes' => 'إقفال التسجيل يجب أن يساوي مدة السماح للتأخير أو يزيد عليها — وإلّا أُقفل الباب قبل أن يُعدّ الموظف متأخراً.',
            ]);
        }

        /*
            «المراجعة الليلية» مفتاحٌ على المهمة المجدولة نفسها (cron_jobs)، لا
            عمودٌ في الإعدادات — فما يُرى هنا هو ما يُرى في «المهام المجدولة».
        */
        if ($request->has('nightly_job_present')
            && ($job = CronJob::where('handler', 'attendance:process')->first())) {
            $cron = app(CronJobService::class);
            $wantOn = $request->boolean('nightly_job_enabled');
            if ($wantOn && ! $job->isActive()) {
                $cron->enable($job, auth()->id());
            } elseif (! $wantOn && $job->isActive()) {
                $cron->disable($job, auth()->id());
            }
        }

        $setting = AttendanceSetting::current();
        $setting->update(array_merge($validated, [
            'auto_deduction_enabled' => $request->boolean('auto_deduction_enabled'),
            'photos_required' => $request->boolean('photos_required'),
            /*
                مجموعةُ خانات: لا يصل منها شيءٌ حين لا يُختار أيُّ يوم،
                فيغيب المفتاحُ عن `$validated` ولا يُحدَّث — ويبقى الاختيارُ
                القديم رغم إلغائه. والفراغُ هنا قرارٌ صريح: لا عطلة أسبوعية.
            */
            'default_weekly_off_days' => $request->input('default_weekly_off_days', []),
            // الفراغُ يعني ٠٨:٠٠ كما يقول النموذج — يُحفظ صريحاً فلا تبقى لوحةُ الحضور تراه «لم يُضبط»
            'default_shift_start' => $request->filled('default_shift_start') ? $request->input('default_shift_start') : '08:00',
            /*
                الفراغُ يُحفظ `null` صريحاً لا صفراً: الصفرُ يعني «أقفل عند
                بداية الوردية بالضبط»، و`null` يعني «لا تُقفل». وبلا هذا
                السطر يبقى آخرُ رقمٍ محفوظٍ عند تفريغ الحقل.
            */
            'checkin_close_after_minutes' => $request->filled('checkin_close_after_minutes')
                ? (int) $request->input('checkin_close_after_minutes')
                : null,
            // الخانةُ المطفأة تصل `0` من الحقل المخفيّ الذي يسبقها
            'visit_requires_check_in' => $request->boolean('visit_requires_check_in'),
            'visit_blocked_after_check_out' => $request->boolean('visit_blocked_after_check_out'),
            'visit_blocked_during_break' => $request->boolean('visit_blocked_during_break'),
        ]));

        return back()->with('success', 'تم حفظ إعدادات الحضور العامة');
    }

    public function storePhotoType(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:attendance_photo_types,code',
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'phase' => 'required|in:checkin,checkout,both',
            'is_required' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        AttendancePhotoType::create(array_merge($validated, [
            'is_active' => true,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]));

        return back()->with('success', 'تم إضافة نوع الصورة');
    }

    public function togglePhotoType($id)
    {
        $type = AttendancePhotoType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);

        return back()->with('success', 'تم تحديث حالة نوع الصورة');
    }

    public function deletePhotoType($id)
    {
        $type = AttendancePhotoType::findOrFail($id);
        if ($type->photos()->exists()) {
            return back()->with('error', 'لا يمكن حذف نوع صورة مرتبط بصور موجودة');
        }
        $type->delete();

        return back()->with('success', 'تم حذف نوع الصورة');
    }

    /**
     * إعدادات موظفٍ بعينه — تُستدعى من الشاشة لملء نموذج التعديل.
     *
     * بدونها كان النموذج المعنون «إضافة/تعديل» يعرف الإضافة وحدها: اختيار
     * موظفٍ له إعدادٌ محفوظ ثم الحفظ كان يمسح قيمه لأن الحقول الفارغة تصل
     * null عبر ConvertEmptyStringsToNull، والجدول لا يقدّم إلا زرَّ حذف.
     */
    public function employeeConfig($employeeId)
    {
        $this->authorizeEmployeeScope($employeeId);

        $config = EmployeeAttendanceConfig::where('employee_id', $employeeId)->first();

        // طريقةُ التسجيل ومكانُه على ملفّ الموظف نفسه — تُعرض ولو لم يكن له إعدادٌ خاص
        $employee = Employee::find($employeeId, ['id', 'attendance_channel', 'attendance_geofence_id']);
        $registration = [
            'attendance_channel' => $employee?->attendance_channel ?: 'branch',
            'attendance_geofence_id' => $employee?->attendance_geofence_id,
            'kiosk_ids' => $employee ? $employee->attendanceKiosks()->pluck('attendance_kiosks.id')->map(fn ($id) => (string) $id)->all() : [],
        ];

        if (! $config) {
            return response()->json(['exists' => false, 'employee' => $registration]);
        }

        return response()->json([
            'exists' => true,
            'employee' => $registration,
            'config' => [
                'work_mode' => $config->work_mode ?: 'shift',
                'break_allowed_minutes' => $config->break_allowed_minutes,
                'expected_check_in' => $config->expected_check_in ? substr($config->expected_check_in, 0, 5) : null,
                'expected_check_out' => $config->expected_check_out ? substr($config->expected_check_out, 0, 5) : null,
                'late_grace_minutes' => $config->late_grace_minutes,
                'absent_after_minutes' => $config->absent_after_minutes,
                'work_hours_per_day' => $config->work_hours_per_day,
                'checkout_method' => $config->checkout_method,
                'weekly_off_days' => $config->weekly_off_days ?? [],
                'notes' => $config->notes,
            ],
        ]);
    }

    public function storeEmployeeConfig(Request $request)
    {
        // موظفٌ واحد أو مجموعة — نفس الإعداد يُطبَّق على كلٍّ منهم
        if ($request->filled('employee_id') && ! $request->filled('employee_ids')) {
            $request->merge(['employee_ids' => [$request->input('employee_id')]]);
        }

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'expected_check_in' => 'nullable|date_format:H:i',
            'expected_check_out' => 'nullable|date_format:H:i',
            'late_grace_minutes' => 'nullable|integer|min:0|max:480',
            'absent_after_minutes' => 'nullable|integer|min:0|max:1440',
            'work_hours_per_day' => 'nullable|numeric|min:1|max:24',
            'checkout_method' => 'nullable|in:hours,route,both',
            'weekly_off_days' => 'nullable|array',
            'weekly_off_days.*' => 'string|in:sat,sun,mon,tue,wed,thu,fri',
            'auto_deduction_enabled' => 'nullable|boolean',
            'late_deduction_amount' => 'nullable|numeric|min:0|max:1000000',
            'absence_deduction_amount' => 'nullable|numeric|min:0|max:1000000',
            'early_leave_deduction_amount' => 'nullable|numeric|min:0|max:1000000',
            'photos_required' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
            // الموظف الميداني: طريقةُ التسجيل ومكانُه، ونوعُ الدوام، والاستراحة
            'attendance_channel' => 'nullable|in:branch,field,both',
            'attendance_geofence_id' => 'nullable|integer|exists:attendance_geofences,id',
            'kiosk_ids' => 'nullable|array',
            'kiosk_ids.*' => 'integer|exists:attendance_kiosks,id',
            'work_mode' => 'nullable|in:shift,flexible',
            'break_allowed_minutes' => 'nullable|integer|min:0|max:480',
        ], [
            'employee_ids.required' => 'اختر موظفاً واحداً على الأقل.',
        ]);

        $employeeIds = array_values(array_unique(array_map('intval', $validated['employee_ids'])));
        foreach ($employeeIds as $employeeId) {
            $this->authorizeEmployeeScope($employeeId);
        }
        unset($validated['employee_ids']);

        /*
            الدوامُ المرن بلا ساعاتٍ خاصة يأخذ «عدد ساعات العمل اليومي» من
            الإعدادات العامة (`resolveConfig`) — كان يُرفض الحفظ ويُطلب إدخالُها
            هنا مع أنها مضبوطةٌ هناك.
        */

        // طريقةُ التسجيل ومكانُه عمودان على الموظف لا على الإعداد
        if ($request->filled('attendance_channel')) {
            Employee::whereKey($employeeIds)->update([
                'attendance_channel' => $validated['attendance_channel'],
                'attendance_geofence_id' => $validated['attendance_channel'] === 'branch'
                    ? null : ($validated['attendance_geofence_id'] ?? null),
            ]);

            /*
                أجهزةُ الفرع المسموحة: تُستبدل بما اختير (فارغ = أيُّ جهاز).
                والميدانيُّ لا يمسح على جهاز أصلاً فتُفكّ أجهزتُه.
            */
            $kioskIds = $validated['attendance_channel'] === 'field'
                ? [] : array_values(array_unique(array_map('intval', $validated['kiosk_ids'] ?? [])));
            Employee::whereKey($employeeIds)->get(['id'])
                ->each(fn (Employee $emp) => $emp->attendanceKiosks()->sync($kioskIds));
        }
        unset($validated['attendance_channel'], $validated['attendance_geofence_id'], $validated['kiosk_ids']);

        // نفس قاعدة الشاشة العامة: الغياب بعد التأخير لا قبله — لكن هنا الحقلان
        // اختياريّان، فلا نتحقّق إلا حين يُملأ كلاهما.
        if (! is_null($validated['late_grace_minutes'] ?? null)
            && ! is_null($validated['absent_after_minutes'] ?? null)
            && $validated['absent_after_minutes'] < $validated['late_grace_minutes']) {
            return back()->withInput()->withErrors([
                'absent_after_minutes' => 'مدة اعتبار الغياب يجب أن تساوي مدة السماح للتأخير أو تزيد عليها.',
            ]);
        }

        if (! empty($validated['expected_check_in']) && ! empty($validated['expected_check_out'])
            && $validated['expected_check_out'] <= $validated['expected_check_in']) {
            return back()->withInput()->withErrors([
                'expected_check_out' => 'وقت الانصراف المتوقع يجب أن يكون بعد وقت الحضور المتوقع.',
            ]);
        }

        /*
            weekly_off_days: إلغاء تحديد كل الأيام يحذف المفتاح من الطلب أصلاً،
            فكان الإعداد القديم يبقى ولا سبيل لتفريغ القائمة. نثبّتها صراحةً:
            مصفوفةٌ فارغة تعني «لا عطلة أسبوعية لهذا الموظف»، وحذف السجلّ كلّه
            هو ما يعني «ارجع إلى الإعداد العام».
        */
        $values = array_merge($validated, [
            'weekly_off_days' => $validated['weekly_off_days'] ?? [],
            'auto_deduction_enabled' => $request->has('auto_deduction_enabled')
                ? $request->boolean('auto_deduction_enabled') : null,
            'photos_required' => $request->has('photos_required')
                ? $request->boolean('photos_required') : null,
        ]);

        DB::transaction(function () use ($employeeIds, $values) {
            foreach ($employeeIds as $employeeId) {
                EmployeeAttendanceConfig::updateOrCreate(['employee_id' => $employeeId], $values);
            }
        });

        return back()->with('success', count($employeeIds) === 1
            ? 'تم حفظ إعدادات الموظف'
            : 'تم حفظ الإعداد لـ '.count($employeeIds).' موظف');
    }

    public function deleteEmployeeConfig($id)
    {
        // findOrFail بدل where()->delete() حتى لا تُعلن الشاشة نجاحاً على لا شيء.
        $config = EmployeeAttendanceConfig::findOrFail($id);
        $this->authorizeEmployeeScope($config->employee_id);
        $config->delete();

        return back()->with('success', 'تم حذف الإعداد الفردي');
    }
}
