<?php

namespace App\Services\Attendance;

use App\Enums\VisitStatus;
use App\Models\AttendanceDays;
use App\Models\AttendanceGeofence;
use App\Models\AttendancePunch;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\EmployeeClientVisit;
use App\Models\LeavePermissions;
use App\Models\User;
use App\Services\AttendancePhotoRecorder;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * التسلسلُ الحاكم لتسجيل الحضور — حلقةً حلقة، لا تُتخطّى واحدةٌ منها.
 *
 *   ١) ملفُّ الموظف   — على رأس العمل، وبطاقتُه فعّالة، وقناتُه هي هذه.
 *   ٢) الوردية        — لقطةُ المتوقَّع وحسابُ التأخير والسماحية.
 *   ٣) الموقع         — الإحداثيات أو شبكةُ المقر مقابل نطاق المرفق.
 *   ٤) سجلُّ اليوم     — صفٌّ واحدٌ لليوم، ومنعُ التكرار على القفل.
 *   ٥) الإجازات       — العطلةُ والإجازةُ والتصريح تُفسِّر الفروقات.
 *   ٦) سجلُّ التدقيق   — ختمٌ خامٌّ لكل محاولة، مقبولةً كانت أو مردودة.
 *   ٧) الرواتب        — إعادةُ المعالجة عند الانصراف: دقائقُ وخصميات.
 *
 * ولِمَ طبقةٌ واحدة لا منطقٌ في كلِّ متحكّم؟ لأن مدخلَي التسجيل اثنان
 * (جهازُ الفرع وجوالُ الميداني) وسيصيران ثلاثةً، وأيُّ حلقةٍ تُكتب مرّتين
 * ستُصان مرّةً واحدة. الفرقُ بين المدخلين كلُّه في `StampContext`، لا في
 * القواعد.
 *
 * ولا تدخُّلَ يدويّاً: الوقتُ من ساعة الخادم، والمكانُ من الجهاز، والوسيلةُ
 * من المسار. ولا يملك الموظفُ ولا الموارد البشرية حقلاً يكتبان فيه أياً
 * منها — والتصحيحُ يمرّ بـ`AttendanceCorrectionRequest` وحدها.
 */
class AttendanceStampService
{
    /** أقلُّ مدةٍ بين الحضور والانصراف — ما دونها مسحةٌ مكرّرة لا انصراف. */
    public const MIN_MINUTES_BEFORE_CHECKOUT = 5;

    public function __construct(
        private readonly GeofenceGuard $geofenceGuard,
        private readonly AttendanceProcessorService $processor,
        private readonly AttendancePhotoRecorder $photoRecorder,
    ) {}

    public function stamp(Employee $employee, StampContext $context): StampResult
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();

        /*
            نبضةُ الجهاز أوّلاً — قبل أيّ حلقةِ تحقّق.

            كانت تقع في آخر المسار المقبول وحدَه، بعد أن تكون كلُّ حلقات
            الردّ قد عادت مبكراً. وجهازٌ تُردّ عليه كلُّ مسحةٍ — نطاقٌ
            مضبوطٌ على إحداثياتٍ خاطئة، أو شبكةٌ غيرُ مصرَّحٍ بها، أو
            بطاقاتٌ لم تُفعَّل — **حيٌّ ومعطوب**، وهي الحالُ التي تُفتح
            شاشةُ مراقبة الأجهزة لأجلها بالذات. فكان أشدُّ الأجهزة حاجةً
            إلى الانتباه هو أقلَّها إظهاراً لعلامات الحياة.

            وأيُّ محاولةِ ختمٍ دليلُ حياةٍ بذاتها، قُبلت أو رُدّت.
        */
        $context->kiosk?->touchHeartbeat();

        // ═══ الحلقة ١: ملفُّ الموظف ═══
        $employeeVerdict = $this->verifyEmployeeFile($employee, $context, $today);

        if ($employeeVerdict !== null) {
            return $this->reject($employee, $context, $now, $employeeVerdict, StampResult::STAGE_EMPLOYEE, 403);
        }

        /*
            أيُّ يومٍ يخصّ هذا الختم؟ — سؤالٌ لا يُطرح إلا على الانصراف.
            الحضورُ دائماً لليوم الجاري، أمّا انصرافُ الوردية الليلية فيقع
            بعد منتصف الليل: في تقويمِ الغد، وفي سجلِّ الأمس.
        */
        $targetDate = $context->isCheckIn()
            ? $today
            : $this->resolveCheckoutDate($employee, $today);

        // ═══ الحلقة ٢: الوردية ═══
        $day = $this->processor->describeDay($employee, $targetDate);
        $expectedIn = $day['expected_check_in'];
        $expectedOut = $day['expected_check_out'];

        /*
            نوافذُ الدخول/الخروج من جدول الوردية (`login_*` / `logout_start`)
            كانت تُحفظ في شاشة الورديات ولا تُفحَص. الحضورُ خارجها يُردّ؛
            أما `logout_end_time` فيبقى لقصّ الانصراف المتأخر لا لرفضه.
        */
        $windowVerdict = $this->verifyPunchWindow($context, $now, $day, $targetDate, $this->dayRow($employee, $targetDate));

        if ($windowVerdict !== null) {
            return $this->reject(
                $employee,
                $context,
                $now,
                $windowVerdict,
                StampResult::STAGE_SHIFT,
                403,
            );
        }

        /*
            طريقةُ الانصراف: «خط السير» أو «كلاهما» كانت إعداداً بلا أثر.
            إن وُجدت زيارات مجدولة لليوم ولم تُكمَل → لا انصراف.
        */
        if (! $context->isCheckIn()) {
            // زيارةٌ مفتوحةٌ عند عميل: الانصرافُ يُنهي الدوامَ والزيارةُ ما زالت تعدّ وقتها
            $fieldGuard = app(FieldWorkGuard::class);
            if ($fieldGuard->isFieldEmployee($employee) && ($openVisit = $fieldGuard->openVisit($employee))) {
                return $this->reject(
                    $employee,
                    $context,
                    $now,
                    ['code' => 'visit_open', 'message' => $fieldGuard->openVisitMessage($openVisit, 'سجّل انصرافك')],
                    StampResult::STAGE_SHIFT,
                    409,
                );
            }

            $routeVerdict = $this->verifyCheckoutRoute($employee, $targetDate, $day['config'] ?? []);

            if ($routeVerdict !== null) {
                return $this->reject(
                    $employee,
                    $context,
                    $now,
                    $routeVerdict,
                    StampResult::STAGE_SHIFT,
                    403,
                );
            }
        }

        /*
            نافذةُ الانصراف: ختمٌ بعدها يُقبل ليُغلق اليوم، ويُقصّ إلى نهاية
            الوردية فلا يُكافئ. الوقتُ المُعتمَد يُحسب هنا — في حلقة الوردية —
            لأنه قرارٌ عن الوردية لا عن الموقع ولا عن سجل اليوم.
        */
        $existingDay = $context->isCheckIn() ? null : $this->dayRow($employee, $targetDate);
        $checkedInAt = $existingDay?->check_in_time ? $this->momentOnDate($targetDate, $existingDay->check_in_time) : null;
        $applied = $this->resolveAppliedTime($context, $now, $day, $expectedOut, $checkedInAt);

        $shiftSnapshot = [
            'expected_check_in' => $expectedIn?->format('H:i:s'),
            'expected_check_out' => $expectedOut?->format('H:i:s'),
            'late_minutes' => $context->isCheckIn() ? $this->minutesLate($applied['moment'], $expectedIn) : 0,
            'early_leave_minutes' => $context->isCheckIn() ? 0 : $this->minutesEarly($applied['moment'], $expectedOut),
            'applied_time' => $applied['moment']->format('H:i:s'),
            'adjustment_code' => $applied['code'],
        ];

        // ═══ الحلقة ٣: الموقع الجغرافي ═══
        $fence = $this->geofenceGuard->resolve($employee, $context->kiosk, $context->channel);

        /*
            كانت إحداثياتُ مركز المكان تُكتب في سجلّ الختم متى لم يُرسل الجهازُ
            موقعه — فيبدو الموظفُ واقفاً في المركز تماماً «على بُعد 0 م»، وهو
            موقعٌ لم يُقَس. الختمُ بلا موقعٍ يبقى بلا موقع؛ وجهازُ الفرع
            المربوط بالمكان يُقبل بلا إحداثيات (`GeofenceGuard`).
        */
        $location = $this->geofenceGuard->verify($fence, $context, $employee);

        /*
            التسجيلُ من الجوال بلا موقعٍ لا يُقبل ولو لم يُحدَّد للموظف مكان:
            «من أي مكان» معناه أن الموقعَ يُحفظ ليراه المدير، لا أن يُستغنى عنه.
        */
        if ($location['allowed'] && $context->channel === AttendancePunch::CHANNEL_FIELD && ! $context->hasCoordinates()) {
            $location = ['allowed' => false, 'code' => 'location_required', 'message' => 'فعّل الموقع في جوالك — لا يُقبل التسجيل من الجوال بدون موقع.', 'distance' => null];
        }

        if (! $location['allowed']) {
            return $this->reject(
                $employee,
                $context,
                $now,
                ['code' => $location['code'], 'message' => $location['message']],
                StampResult::STAGE_LOCATION,
                403,
                $fence,
                $location['distance'],
                $shiftSnapshot,
            );
        }

        // الصورُ المطلوبة قبل فتح المعاملة — لا يُسجَّل حضورٌ بلا صوره
        $phase = $context->isCheckIn()
            ? AttendancePhotoRecorder::PHASE_CHECKIN
            : AttendancePhotoRecorder::PHASE_CHECKOUT;
        $photoErrors = $this->photoRecorder->validate($phase, $context->photos, $employee);

        if ($photoErrors !== []) {
            return $this->reject(
                $employee,
                $context,
                $now,
                ['code' => 'photos_missing', 'message' => implode(' • ', $photoErrors)],
                StampResult::STAGE_PHOTOS,
                422,
                $fence,
                $location['distance'],
                $shiftSnapshot,
            );
        }

        // ═══ الحلقات ٤–٦: سجلُّ اليوم، والإجازات، وسجلُّ التدقيق ═══
        $outcome = DB::transaction(function () use (
            $employee, $context, $now, $targetDate, $day, $fence, $location, $shiftSnapshot, $phase, $applied
        ) {
            /*
                القفل قبل القراءة: مسحتان متزامنتان لنفس البطاقة (نقرةٌ
                مزدوجة على الجهاز، أو ضغطتان على الجوال) كانتا تقرآن معاً
                «لا حضورَ بعد» فتكتبان معاً. القفلُ يُسلسلهما، فالثانيةُ
                تقرأ ما كتبته الأولى وتُردّ تكراراً.
            */
            $record = AttendanceDays::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $targetDate->toDateString())
                ->lockForUpdate()
                ->first();

            $duplicate = $this->verifyDayRecord($record, $context, $day, $now);

            if ($duplicate !== null) {
                return ['rejected' => $duplicate];
            }

            if (! $record) {
                $record = new AttendanceDays([
                    'employee_id' => $employee->id,
                    'attendance_date' => $targetDate->toDateString(),
                ]);
            }

            $this->applyStampToDay($record, $employee, $context, $applied, $targetDate, $day, $fence, $location, $shiftSnapshot);
            $record->save();

            $punch = $this->recordPunch(
                $employee,
                $context,
                $now,
                AttendancePunch::RESULT_ACCEPTED,
                null,
                null,
                null,
                $fence,
                $location['distance'],
                $shiftSnapshot,
                $record,
                $targetDate,
            );

            // ربطُ الصفّ بالختم الذي أنشأه — الأثرُ يُقرأ في الاتجاهين
            $record->forceFill([
                $context->isCheckIn() ? 'check_in_punch_id' : 'check_out_punch_id' => $punch->id,
            ])->save();

            $this->photoRecorder->store($record, $phase, $context->photos, [
                'latitude' => $context->latitude,
                'longitude' => $context->longitude,
                'ip_address' => $context->ipAddress,
            ]);

            return ['day' => $record, 'punch' => $punch];
        });

        if (isset($outcome['rejected'])) {
            return $this->reject(
                $employee,
                $context,
                $now,
                $outcome['rejected'],
                StampResult::STAGE_DAY,
                409,
                $fence,
                $location['distance'],
                $shiftSnapshot,
            );
        }

        /** @var AttendanceDays $record */
        $record = $outcome['day'];
        /** @var AttendancePunch $punch */
        $punch = $outcome['punch'];

        // ═══ الحلقة ٧: الربطُ بالرواتب ═══
        /*
            كان الخصمُ يُنشأ عند الانصراف وحدَه (`processEmployeeDay`).
            فمن حضر متأخّراً ولم ينصرف بعد — أو نسي الانصراف — لا خصميةَ
            في الرواتب حتى الكرون الليلي. والخصمُ يُحسب من وقت الحضور،
            فلا معنى لتأخير إنشائه إلى الانصراف.
        */
        try {
            $this->processor->processEmployeeDay($employee, $record->attendance_date);
            $record->refresh();
        } catch (\Throwable $e) {
            Log::error('فشل معالجة يوم الحضور فور الختم: '.$e->getMessage(), [
                'employee_id' => $employee->id,
                'attendance_date' => $record->attendance_date->toDateString(),
                'punch_type' => $context->punchType,
            ]);
        }

        return StampResult::accepted(
            message: $context->isCheckIn() ? 'تم تسجيل الحضور بنجاح' : 'تم تسجيل الانصراف بنجاح',
            punch: $punch,
            day: $record,
            warnings: array_merge(
                $location['warnings'],
                $this->dayNotices($day, $employee, $targetDate, $context),
                $this->cappingNotice($applied, $now),
                $this->shortDayNotice($record, $context),
            ),
            data: $this->responseData($employee, $record, $context, $fence, $location['distance']),
        );
    }

    // ════════════════════════════════════════════════════════════
    // الحلقة ١ — ملفُّ الموظف
    // ════════════════════════════════════════════════════════════

    /** @return array{code: string, message: string}|null */
    private function verifyEmployeeFile(Employee $employee, StampContext $context, Carbon $today): ?array
    {
        if (! $employee->isActive()) {
            return [
                'code' => 'employee_inactive',
                'message' => 'لا يمكن تسجيل الحضور: الموظف غير نشط حالياً ('.$employee->employmentStatus()->label().')',
            ];
        }

        if ($employee->hire_date && $today->lt($employee->hire_date->copy()->startOfDay())) {
            return [
                'code' => 'before_hire_date',
                'message' => 'تاريخ تعيين هذا الموظف لم يحن بعد',
            ];
        }

        $lastWorkingDay = $employee->last_working_day ?? $employee->termination_date;

        if ($lastWorkingDay && $today->gt($lastWorkingDay->copy()->startOfDay())) {
            return [
                'code' => 'after_last_working_day',
                'message' => 'انتهت خدمة هذا الموظف بتاريخ '.$lastWorkingDay->format('Y-m-d'),
            ];
        }

        /*
            `=== false` لا `! …`: العمودُ يُنشَأ بقيمةٍ افتراضية `true`، لكنّ
            صفّاً سابقاً للهجرة (أو استعلاماً لا يختار العمود) يقرأ `null` —
            و`!null` يصدق، فتُوقَف بطاقةُ كلِّ موظفٍ في المنشأة. الإيقافُ
            قرارٌ صريحٌ يُكتب صفراً، لا غيابُ قيمة.
        */
        /*
            **الوسيلةُ تُثبَت ولا تُدَّعى.**

            كان `method` يصل من العميل حرفاً يُقبل كما هو: شاشةُ الجهاز
            تُرسل `barcode` إن كانت الكاميرا مفتوحة و`manual` إن لم تكن —
            ولا يُرسَل باركودٌ مع التسجيل إطلاقاً. فأيُّ طلبٍ يقول
            «method=barcode» مع `employee_id` كان يُسجَّل باركوداً موثَّقاً
            وهو لم يُمسَح شيء. والوسيلةُ تدخل سجلَّ التدقيق وتقارير الموارد
            البشرية بوصفها دليلاً — فإن لم تكن مُثبَتة فهي تزويرٌ مُوثَّق.

            فهنا تُقابَل الدعوى بالبيّنة: باركودٌ مُرسَلٌ يطابق باركودَ هذا
            الموظف بعينه. وإلّا رُدَّ الختمُ ولم يُخفَّض صامتاً إلى «يدوي» —
            التخفيضُ الصامت يُمرِّر تسجيلاً لم يأذن به أحد.
        */
        if (in_array($context->method, [AttendancePunch::METHOD_BARCODE, AttendancePunch::METHOD_QR], true)) {
            $supplied = trim((string) $context->rawBarcode);

            if ($supplied === '') {
                return [
                    'code' => 'barcode_not_supplied',
                    'message' => 'تسجيلٌ بالباركود بلا باركود — امسح بطاقة الموظف.',
                ];
            }

            // مقارنةٌ ثابتةُ الزمن: الباركودُ معرِّفُ هوية
            if (! is_string($employee->barcode) || ! hash_equals($employee->barcode, $supplied)) {
                return [
                    'code' => 'barcode_mismatch',
                    'message' => 'الباركود الممسوح لا يطابق هذا الموظف.',
                ];
            }
        }

        if ($context->method === AttendancePunch::METHOD_BARCODE && $employee->barcode_enabled === false) {
            return [
                'code' => 'barcode_disabled',
                'message' => 'بطاقةُ هذا الموظف موقوفة — راجع الموارد البشرية لإصدار بطاقةٍ بديلة.',
            ];
        }

        $allowedChannel = $employee->attendance_channel ?: 'branch';

        if ($allowedChannel !== 'both'
            && $context->channel !== AttendancePunch::CHANNEL_MANUAL
            && $this->channelKey($context->channel) !== $allowedChannel) {
            return [
                'code' => 'channel_not_allowed',
                'message' => $allowedChannel === 'branch'
                    ? 'هذا الموظف مُسجَّلٌ على جهاز الفرع — لا يُقبل تسجيلُه من الجوال.'
                    : 'هذا الموظف مُسجَّلٌ ميدانياً — لا يُقبل تسجيلُه من جهاز الفرع.',
            ];
        }

        /*
            أجهزةُ الموظف المسموحة (إعدادات الحضور ← الموظفون). من رُبط بأجهزةٍ
            لا يُقبل مسحُه إلا عليها؛ ومن لا جهازَ له يُقبل على أيِّ جهاز.
        */
        if ($context->channel === AttendancePunch::CHANNEL_KIOSK && $context->kiosk) {
            $allowed = $employee->attendanceKiosks()->get(['attendance_kiosks.id', 'attendance_kiosks.name']);

            if ($allowed->isNotEmpty() && ! $allowed->contains('id', $context->kiosk->id)) {
                return [
                    'code' => 'kiosk_not_allowed',
                    'message' => 'هذا الجهاز غير مسموح لهذا الموظف — يسجّل على: '.$allowed->pluck('name')->implode('، '),
                ];
            }
        }

        return null;
    }

    private function channelKey(string $channel): string
    {
        return $channel === AttendancePunch::CHANNEL_KIOSK ? 'branch' : 'field';
    }

    // ════════════════════════════════════════════════════════════
    // الحلقة ٤ — سجلُّ اليوم الموحَّد
    // ════════════════════════════════════════════════════════════

    /**
     * @param  array<string, mixed>  $day  وصفُ اليوم من `describeDay`
     * @return array{code: string, message: string}|null
     */
    private function verifyDayRecord(?AttendanceDays $record, StampContext $context, array $day = [], ?Carbon $now = null): ?array
    {
        /*
            يومٌ ثُبِّت بطلب تصحيحٍ معتمد لا يُعدَّل بختم.

            كان أيُّ ختمٍ لاحق «يلغي قفل التصحيح» ويكتب فوقه — فقرارُ المدير
            يُمحى بمسحة بطاقة. التعديلُ على يومٍ مصحَّح يكون بطلب تصحيحٍ آخر.
        */
        if ($record && $record->manual_override) {
            return [
                'code' => 'day_corrected',
                'message' => 'حضور هذا اليوم ثُبِّت بطلب تصحيح معتمد — لا يُعدَّل بالتسجيل. إن كان فيه خطأ فقدّم طلب تصحيح جديداً.',
            ];
        }

        /*
            إجازةٌ معتمدةٌ تشمل هذا اليوم → لا حضورَ يُحتسَب فيه.

            كان الإجازةُ تُنتج تنبيهاً نصّياً وحدَه («للموظف إجازةٌ معتمدة
            تشمل هذا اليوم — راجع الموارد البشرية») بينما يُقبل الختمُ
            ويُكتب اليومُ يومَ عملٍ عادياً. ثم يأتي الكرونُ ليلاً فيُعيد
            الحالةَ إلى «إجازة» — فيُمحى أثرُ الحضور صامتاً، وتبقى ساعاتٌ
            حُسبت في هذه الأثناء لمن سأل عن اليوم قبل الكرون.

            والردُّ هنا يُكتب في سجل الأختام مردوداً، فتُقرأ المحاولةُ في
            «سجلات الحضور» — وهي الموضع الذي تُقرأ فيه المحاولاتُ المردودة.

            وردُّ **الانصراف** يُستثنى: من سُجِّل حضورُه قبل اعتماد الإجازة
            يجب أن يُغلق يومَه، وإلا بقي معلَّقاً بلا انصرافٍ إلى الأبد.
        */
        if ($context->isCheckIn() && ! empty($day['leave'])) {
            $leave = $day['leave'];

            return [
                'code' => 'on_approved_leave',
                'message' => 'الموظف في إجازةٍ معتمدة'
                    .($leave->start_date && $leave->end_date
                        ? ' من '.Carbon::parse($leave->start_date)->toDateString()
                          .' إلى '.Carbon::parse($leave->end_date)->toDateString()
                        : '')
                    .' — لا يُحتسب هذا اليوم يومَ عمل. راجع الموارد البشرية.',
            ];
        }

        /*
            العطلةُ الأسبوعية من إعدادات الحضور (مثل الجمعة) كانت تُنبَّه
            نصّاً وتُقبل المسحة — فيُكتب يومُ عملٍ في يوم إجازةٍ مضبوط.
            الحضورُ يُردّ؛ والانصرافُ يبقى لإغلاق يومٍ بدأ قبل منتصف الليل
            أو قبل ضبط العطلة.
        */
        if ($context->isCheckIn() && ! empty($day['is_weekly_off'])) {
            $offDays = $day['config']['weekly_off_days'] ?? [];
            $labels = [
                'sat' => 'السبت', 'sun' => 'الأحد', 'mon' => 'الاثنين',
                'tue' => 'الثلاثاء', 'wed' => 'الأربعاء', 'thu' => 'الخميس', 'fri' => 'الجمعة',
            ];
            $named = collect($offDays)->map(fn ($d) => $labels[strtolower((string) $d)] ?? $d)->filter()->implode('، ');

            return [
                'code' => 'weekly_off',
                'message' => 'اليوم عطلتك الأسبوعية'
                    .($named !== '' ? ' (أيام العطلة: '.$named.')' : '')
                    .' — لا يُسجَّل فيه حضور. إن كان هذا خطأً فراجع الموارد البشرية.',
            ];
        }

        if ($context->isCheckIn() && ! empty($day['is_holiday'])) {
            $name = $day['holiday_name'] ?? null;

            return [
                'code' => 'public_holiday',
                'message' => 'اليوم عطلةٌ رسمية'
                    .($name ? ' («'.$name.'»)' : '')
                    .' — لا يُقبل تسجيل الحضور.',
            ];
        }

        if ($context->isCheckIn()) {
            if ($record && $record->check_in_time) {
                /*
                    الورديةُ الثانية: بعد اكتمال اليوم (حضور+انصراف) يُسمَح
                    ببدء دورةٍ جديدة إن فُعِّل الإعداد الأساسي. تُأرشَف الفترة
                    الأولى في الملاحظات ويُفرَّغ الانصراف لاستقبال الحضور الجديد.
                */
                if ($record->check_out_time && ! empty($day['config']['allow_second_shift'])) {
                    $firstPeriod = trim((string) ($record->check_in_time.'–'.$record->check_out_time));
                    $note = 'الوردية الأولى: '.$firstPeriod;
                    $existing = trim((string) ($record->notes ?? ''));
                    if (! str_contains($existing, $note)) {
                        $record->notes = $existing === '' ? $note : $existing."\n".$note;
                    }
                    $record->check_out_time = null;
                    $record->check_out_punch_id = null;

                    return null;
                }

                return [
                    'code' => 'already_checked_in',
                    'message' => 'تم تسجيل الحضور مسبقاً لهذا اليوم في الساعة '.$record->check_in_time,
                ];
            }

            return null;
        }

        if (! $record || ! $record->check_in_time) {
            return [
                'code' => 'no_check_in',
                'message' => 'لم يُسجَّل حضورٌ لهذا اليوم — لا انصرافَ بلا حضور.',
            ];
        }

        /*
            مسحتان متتاليتان للبطاقة نفسِها (نقرةٌ مزدوجة، أو موظفٌ يعيد المسح
            ظنّاً أن الأولى لم تُقرأ) كانت الثانيةُ تُسجَّل انصرافاً بعد ثوانٍ
            من الحضور — فيُغلق يومُه وهو لم يبدأ. لا انصرافَ قبل دقائق من الحضور.
        */
        if (! $record->check_out_time && $now) {
            $inAt = Carbon::parse($record->attendance_date->toDateString().' '.$record->check_in_time);
            if ($now->gte($inAt) && $inAt->diffInMinutes($now) < self::MIN_MINUTES_BEFORE_CHECKOUT) {
                return [
                    'code' => 'too_soon_after_check_in',
                    'message' => 'سُجِّل حضورك الساعة '.$record->check_in_time.' — لا يمكن تسجيل الانصراف قبل مرور '.self::MIN_MINUTES_BEFORE_CHECKOUT.' دقائق. إن كانت هذه مسحةً مكرّرة فلا حاجة لشيء.',
                ];
            }
        }

        if ($record->check_out_time) {
            return [
                'code' => 'already_checked_out',
                'message' => 'تم تسجيل الانصراف مسبقاً في الساعة '.$record->check_out_time,
            ];
        }

        return null;
    }

    private function applyStampToDay(
        AttendanceDays $record,
        Employee $employee,
        StampContext $context,
        array $applied,
        Carbon $date,
        array $day,
        ?AttendanceGeofence $fence,
        array $location,
        array $shiftSnapshot,
    ): void {
        $prefix = $context->isCheckIn() ? 'check_in' : 'check_out';
        $now = $applied['moment'];

        // الوقتُ المُعتمَد لا وقتُ الختم: هما واحدٌ إلا حين يقع الانصراف
        // خارج نافذة الوردية، فيُقصّ. والختمُ الخام يحتفظ بالوقتين معاً.
        $record->{$prefix.'_time'} = $now->format('H:i:s');
        // عمودُ اليوم تعدادٌ (manual|barcode|qr|geo): مسحُ «رمز المكتب» بالجوال يُحفظ فيه «qr»،
        // والختمُ في سجلّ الأختام يبقى بنوعه الدقيق (`office_qr`)
        $record->{$prefix.'_method'} = $context->method === AttendancePunch::METHOD_OFFICE
            ? AttendancePunch::METHOD_QR
            : $context->method;
        $record->{$prefix.'_channel'} = $context->channel;
        $record->{$prefix.'_kiosk_id'} = $context->kiosk?->id;
        $record->{$prefix.'_geofence_id'} = $fence?->id;
        $record->{$prefix.'_distance_meters'} = $location['distance'];
        $record->{($context->isCheckIn() ? 'checkin' : 'checkout').'_latitude'} = $context->latitude;
        $record->{($context->isCheckIn() ? 'checkin' : 'checkout').'_longitude'} = $context->longitude;

        $record->expected_check_in = $shiftSnapshot['expected_check_in'];
        $record->expected_check_out = $shiftSnapshot['expected_check_out'];
        $record->ip_address = $context->ipAddress;
        $record->user_agent = $context->userAgent;

        if (! $context->isCheckIn()) {
            $record->check_out_capped = $applied['code'] !== null;

            // انصرافٌ أثناء الاستراحة يُغلقها عند لحظة الانصراف
            if ($record->break_started_at) {
                $record->break_minutes = (int) $record->break_minutes
                    + (int) max(0, $record->break_started_at->diffInMinutes($now));
                $record->break_started_at = null;
            }
        }

        if ($context->isCheckIn()) {
            $record->scanned_via_barcode = $context->method === AttendancePunch::METHOD_BARCODE;

            // نفسُ عتبات `processEmployeeDay` — لا حسابَ موازياً
            $decision = $this->processor->determineCheckInStatus($employee, $date, $now);

            $record->status = $decision['status'];
            $record->late_minutes = $decision['late_minutes'];
        }

        // ═══ الحلقة ٥: الإجازات والعطلات — تُفسِّر الفرق ولا تمنعه ═══
        $record->is_holiday = (bool) $day['is_holiday'];
        $record->is_weekly_off = (bool) $day['is_weekly_off'];
        $record->is_on_leave = $day['leave'] !== null;
        $record->leave_request_id = $day['leave']?->id;
    }

    // ════════════════════════════════════════════════════════════
    // الحلقة ٥ — ما يُفسِّر فروقاتِ اليوم
    // ════════════════════════════════════════════════════════════

    /** @return array<int, string> */
    private function dayNotices(array $day, Employee $employee, Carbon $date, StampContext $context): array
    {
        $notices = [];

        // الحضورُ في عطلةٍ أسبوعية أو رسمية يُردّ (انظر `verifyDayRecord`)؛
        // يبقى التنبيهُ للانصراف فقط إن وُجد يومٌ بدأ قبل العطلة.
        if ($day['is_holiday'] && ! $context->isCheckIn()) {
            $notices[] = 'اليوم عطلةٌ رسمية'.($day['holiday_name'] ? ' («'.$day['holiday_name'].'»)' : '')
                .' — راجع الموارد البشرية.';
        }

        if ($day['is_weekly_off'] && ! $context->isCheckIn()) {
            $notices[] = 'اليوم عطلةٌ أسبوعية حسب إعدادات الحضور.';
        }

        // الحضورُ في يوم إجازةٍ معتمدة يُردّ لا يُنبَّه عليه (انظر `verifyDayRecord`)؛
        // ويبقى التنبيهُ للانصراف، وهو المسموحُ به لإغلاق يومٍ بدأ قبل الاعتماد.
        if ($day['leave'] && ! $context->isCheckIn()) {
            $notices[] = 'للموظف إجازةٌ معتمدة تشمل هذا اليوم — راجع الموارد البشرية.';
        }

        $permission = $this->activePermission($employee, $date, $context);

        if ($permission) {
            $notices[] = 'يوجد تصريحٌ معتمد ('.($permission->leave_type_text ?: 'تصريح').') يُفسِّر فارقَ الوقت.';
        }

        return $notices;
    }

    /**
     * التصريحُ المعتمد الذي يشمل هذا اليوم — الوصولُ المتأخر للحضور،
     * والانصرافُ المبكر للانصراف. تصريحُ انصرافٍ مبكر لا يُبرّر تأخيرَ
     * الحضور، فلا يُخلَط النوعان.
     */
    private function activePermission(Employee $employee, Carbon $date, StampContext $context): ?LeavePermissions
    {
        return LeavePermissions::where('employee_id', $employee->id)
            ->where('leave_type', $context->isCheckIn()
                ? LeavePermissions::LATE_ARRIVAL
                : LeavePermissions::EARLY_LEAVE)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();
    }

    // ════════════════════════════════════════════════════════════
    // الحلقة ٦ — سجلُّ التدقيق
    // ════════════════════════════════════════════════════════════

    private function recordPunch(
        ?Employee $employee,
        StampContext $context,
        Carbon $now,
        string $result,
        ?string $stage,
        ?string $code,
        ?string $reason,
        ?AttendanceGeofence $fence,
        ?int $distance,
        array $shiftSnapshot,
        ?AttendanceDays $record,
        ?Carbon $attendanceDate = null,
    ): AttendancePunch {
        return AttendancePunch::create([
            'employee_id' => $employee?->id,
            'attendance_day_id' => $record?->id,
            'punch_type' => $context->punchType,
            'punched_at' => $now,
            // يومُ السجل لا يومُ التقويم: انصرافُ الوردية الليلية يقع
            // بعد منتصف الليل وينتمي إلى سجلّ اليوم السابق.
            'attendance_date' => ($attendanceDate ?? $now)->toDateString(),
            'channel' => $context->channel,
            'method' => $context->method,
            'kiosk_id' => $context->kiosk?->id,
            'geofence_id' => $fence?->id,
            'latitude' => $context->latitude,
            'longitude' => $context->longitude,
            'accuracy_meters' => $context->accuracyMeters,
            'distance_meters' => $distance,
            'ip_address' => $context->ipAddress,
            'wifi_ssid' => $context->wifiSsid,
            'user_agent' => $context->userAgent,
            'raw_barcode' => $context->rawBarcode,
            'applied_time' => $shiftSnapshot['applied_time'] ?? $now->format('H:i:s'),
            'adjustment_code' => $shiftSnapshot['adjustment_code'] ?? null,
            'entry_note' => $context->entryNote,
            'expected_check_in' => $shiftSnapshot['expected_check_in'] ?? null,
            'expected_check_out' => $shiftSnapshot['expected_check_out'] ?? null,
            'late_minutes' => $shiftSnapshot['late_minutes'] ?? 0,
            'early_leave_minutes' => $shiftSnapshot['early_leave_minutes'] ?? 0,
            'result' => $result,
            'stage' => $stage,
            'rejection_code' => $code,
            'rejection_reason' => $reason,
            'created_by' => $context->userId,
        ]);
    }

    /**
     * يُسجّل المحاولةَ المردودة ثم يُرجِع سببَ الردّ.
     *
     * الردُّ يُكتب أيضاً: شكوى «النظام لم يسجّل لي» لا يُجاب عنها إلا بسجلٍّ
     * يقول متى حاول الموظف، ومن أين، ولماذا رُدَّ.
     */
    private function reject(
        ?Employee $employee,
        StampContext $context,
        Carbon $now,
        array $verdict,
        string $stage,
        int $httpStatus,
        ?AttendanceGeofence $fence = null,
        ?int $distance = null,
        array $shiftSnapshot = [],
    ): StampResult {
        $punch = null;

        try {
            $punch = $this->recordPunch(
                $employee,
                $context,
                $now,
                AttendancePunch::RESULT_REJECTED,
                $stage,
                $verdict['code'],
                mb_substr($verdict['message'], 0, 255),
                $fence,
                $distance,
                $shiftSnapshot,
                null,
            );
        } catch (\Throwable $e) {
            // تعذُّرُ كتابة سجل التدقيق لا يُحوّل ردَّاً مفهوماً إلى خطأ ٥٠٠
            Log::error('تعذّر تسجيل ختم مردود: '.$e->getMessage());
        }

        return StampResult::rejected($stage, $verdict['code'], $verdict['message'], $punch, $httpStatus);
    }

    /**
     * ختمٌ لباركودٍ لا يطابق أيَّ موظف — واقعةٌ تُسجَّل ولا تُهمَل.
     */
    /**
     * مسحةٌ بعد اكتمال اليوم (حضور وانصراف) — تُكتب مردودةً في سجلّ الأختام.
     *
     * كانت الشاشةُ تردّها «اليوم مكتمل» ولا يبقى لها أثر، مع أن سجلّ الحضور
     * يَعِد بأن «كلّ محاولة تسجيل تُكتب هنا». وتكرارُها من بطاقةٍ واحدة إشارة.
     */
    public function rejectDayComplete(Employee $employee, StampContext $context): StampResult
    {
        return $this->reject(
            $employee,
            $context,
            Carbon::now(),
            ['code' => 'day_complete', 'message' => 'سُجِّل الحضور والانصراف لهذا اليوم — مسحةٌ زائدة.'],
            StampResult::STAGE_DAY,
            409,
        );
    }

    /**
     * بدءُ استراحةٍ أو إنهاؤها.
     *
     * الاستراحةُ وسطَ الدوام وحدَه: بعد الحضور وقبل الانصراف، ولا تبدأ
     * والموظفُ عند عميل. وكلُّ بدءٍ وإنهاءٍ يُكتب في سجل التسجيلات
     * (`rest_in` / `rest_out`) مقبولاً أو مردوداً، ومجموعُ الدقائق على اليوم
     * (`break_minutes`) — والزائدُ عن المسموح نقصٌ في الدوام عند الاحتساب.
     */
    public function toggleBreak(Employee $employee, StampContext $context): StampResult
    {
        $now = Carbon::now();
        $starting = $context->punchType === AttendancePunch::TYPE_BREAK_START;
        $date = $this->resolveCheckoutDate($employee, $now->copy()->startOfDay());

        $fail = fn (string $code, string $message) => $this->reject(
            $employee, $context, $now, ['code' => $code, 'message' => $message], StampResult::STAGE_DAY, 409,
        );

        $outcome = DB::transaction(function () use ($employee, $context, $now, $starting, $date) {
            $record = AttendanceDays::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $record || ! $record->check_in_time) {
                return ['no_check_in', 'سجّل حضورك أولاً — الاستراحة تكون أثناء الدوام.'];
            }
            if ($record->check_out_time) {
                return ['already_checked_out', 'سجّلت انصرافك — انتهى دوام اليوم.'];
            }
            if ($record->manual_override) {
                return ['day_corrected', 'حضور هذا اليوم ثُبِّت بطلب تصحيح معتمد.'];
            }

            if ($starting) {
                if ($record->break_started_at) {
                    return ['break_open', 'أنت في استراحة منذ '.$record->break_started_at->format('H:i').'.'];
                }

                $guard = app(FieldWorkGuard::class);
                if ($openVisit = $guard->openVisit($employee)) {
                    return ['visit_open', $guard->openVisitMessage($openVisit, 'ابدأ الاستراحة')];
                }

                $record->break_started_at = $now;
            } else {
                if (! $record->break_started_at) {
                    return ['no_break', 'لست في استراحة الآن.'];
                }

                $record->break_minutes = (int) $record->break_minutes
                    + (int) max(0, $record->break_started_at->diffInMinutes($now));
                $record->break_started_at = null;
            }

            $record->save();

            $punch = $this->recordPunch(
                $employee, $context, $now, AttendancePunch::RESULT_ACCEPTED, null, null, null,
                null, null, [], $record, $date,
            );

            return ['ok', $record, $punch];
        });

        if ($outcome[0] !== 'ok') {
            return $fail($outcome[0], $outcome[1]);
        }

        [, $record, $punch] = $outcome;
        $allowed = (int) ($this->processor->describeDay($employee, $date)['config']['break_allowed_minutes'] ?? 60);
        $used = (int) $record->break_minutes;

        return StampResult::accepted(
            message: $starting ? 'بدأت الاستراحة' : 'انتهت الاستراحة — عدت إلى الدوام',
            punch: $punch,
            day: $record,
            warnings: (! $starting && $used > $allowed)
                ? ['مجموع استراحاتك اليوم '.$used.' دقيقة والمسموح '.$allowed.' — الزائد يُحسب نقصاً في الدوام.']
                : [],
            data: [
                'on_break' => $starting,
                'break_started_at' => $record->break_started_at?->format('H:i'),
                'break_minutes' => $used,
                'break_allowed_minutes' => $allowed,
            ],
        );
    }

    public function rejectUnknownBarcode(StampContext $context): StampResult
    {
        return $this->reject(
            null,
            $context,
            Carbon::now(),
            ['code' => 'unknown_barcode', 'message' => 'الباركود غير صحيح أو الموظف غير موجود'],
            StampResult::STAGE_EMPLOYEE,
            404,
        );
    }

    // ════════════════════════════════════════════════════════════
    // مساعدات
    // ════════════════════════════════════════════════════════════

    /**
     * الختمُ التالي المستحقّ لهذا الموظف — حضورٌ أم انصرافٌ أم لا شيء.
     *
     * هذا ما يجعل المسحَ مسحاً لا إدخالاً يدوياً: الجهازُ لا يسأل المشغّلَ
     * «أهو حضورٌ أم انصراف؟» — السؤالُ جوابُه في سجل اليوم، ومن يسأله
     * يفتح باب الخطأ. مسحةٌ واحدة تُنهي العملية.
     *
     * ويُرجِع `null` حين يكتمل اليوم: لا ختمَ ثالثاً، والشاشةُ تقول
     * «اليوم مكتمل» بدل أن تُحاول وتُردّ.
     */
    public function nextPunchTypeFor(Employee $employee): ?string
    {
        $today = Carbon::now()->startOfDay();
        $record = $this->dayRow($employee, $this->resolveCheckoutDate($employee, $today));

        if (! $record || ! $record->check_in_time) {
            return AttendancePunch::TYPE_IN;
        }

        if (! $record->check_out_time) {
            return AttendancePunch::TYPE_OUT;
        }

        // اكتمالُ الفترة الأولى + السماح بالوردية الثانية → حضورٌ ثانٍ
        if (AttendanceSetting::current()->allow_second_shift) {
            $hasSecondPeriod = is_string($record->notes)
                && str_contains($record->notes, 'الوردية الأولى:');

            if (! $hasSecondPeriod) {
                return AttendancePunch::TYPE_IN;
            }
        }

        return null;
    }

    /**
     * يومُ السجل الذي يخصّه ختمُ الانصراف.
     *
     * الوردياتُ الليلية (٢٢:٠٠ → ٠٦:٠٠) تنتهي في تقويمِ الغد. وكان الختمُ
     * يُطلَب لليوم الجاري دائماً، فيجد صفَّ الغد بلا حضورٍ ويردّ الانصرافَ
     * بـ«لا انصرافَ بلا حضور» — ويبقى صفُّ الأمس مفتوحاً إلى الأبد. كلُّ
     * موظفٍ ليليٍّ كان مضطراً إلى طلب تصحيحٍ عن كل نوبةٍ يعملها.
     *
     * والإسنادُ محدودٌ بثلاثة شروط مجتمعة، وإلّا أُغلقت أيامٌ منسيّةٌ
     * قديمة بأختامٍ لا تخصّها:
     *
     *   ١) اليومُ الجاري لم يبدأ بعد — لا حضورَ مسجَّلاً فيه. فمن بدأ
     *      نوبةً جديدة فنوبتُه القديمة تُغلق بطلب تصحيح لا بختمٍ يلتبس
     *      بنوبتين.
     *   ٢) صفُّ الأمس مفتوح: فيه حضورٌ وليس فيه انصراف.
     *   ٣) ورديةُ الأمس **تعبر منتصف الليل فعلاً** — أي أن نهايتها
     *      المتوقَّعة تقع في تقويم اليوم. ورديةٌ نهارية نُسي انصرافُها
     *      تبقى لدورة التصحيح، فالختمُ صباحَ الغد فيها أقربُ إلى حضورٍ
     *      جديد منه إلى انصرافٍ متأخر.
     *
     * وأمّا كم تأخّر الختمُ عن نهاية الوردية فليس شرطَ إسناد: نافذةُ
     * الانصراف تتكفّل به — تقبل الختم وتقصّه إلى نهاية الوردية.
     *
     * وهي عامّةٌ لأن الشاشات تحتاجها قبل الختم لا بعده: شاشةٌ تشتقّ زرَّها
     * من صفّ اليوم الجاري وحده تعرض «تسجيل حضور» لموظفٍ ليليٍّ نوبتُه
     * مفتوحة، فيضغطه ويفتح نوبةً ثانية بدل أن يُغلق الأولى.
     */
    public function resolveCheckoutDate(Employee $employee, Carbon $today): Carbon
    {
        $todayRow = $this->dayRow($employee, $today);

        if ($todayRow && $todayRow->check_in_time) {
            return $today;
        }

        $yesterday = $today->copy()->subDay();
        $yesterdayRow = $this->dayRow($employee, $yesterday);

        if (! $yesterdayRow || ! $yesterdayRow->check_in_time || $yesterdayRow->check_out_time) {
            return $today;
        }

        $expectedOut = $this->processor->describeDay($employee, $yesterday)['expected_check_out'];

        if (! $expectedOut || $expectedOut->toDateString() !== $today->toDateString()) {
            return $today;
        }

        return $yesterday;
    }

    public function dayRow(Employee $employee, Carbon $date): ?AttendanceDays
    {
        return AttendanceDays::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();
    }

    /**
     * الوقتُ الذي يُكتب في سجل اليوم — وهو وقتُ الختم إلا أن يقع الانصراف
     * خارج نافذة الوردية.
     *
     * `shift_days.logout_end_time` عمودٌ قائمٌ في هذا المستودع منذ البداية:
     * تحفظه شاشةُ إدارة الورديات ولا يقرؤه منطقٌ واحد. فكانت النافذةُ
     * تُضبط ولا تحرس شيئاً — ويُحتسب الإضافي إلى أيّ ساعةٍ مرَّ فيها
     * الموظفُ على الجهاز.
     *
     * والقصُّ إلى **نهاية الوردية** لا إلى طرف النافذة: الختمُ الواقع خارج
     * النافذة لا يُثبت شيئاً عن ساعة المغادرة الحقيقية، فلا يُبنى عليه
     * استحقاق. ومن عمل إضافياً فعلاً يُثبته بطلب تصحيحٍ معتمد — أو تُوسَّع
     * نافذةُ ورديته.
     *
     * ولا تُقصّ ساعاتُ عملٍ إلى ما دون المستحق: القصُّ لا يقع إلا حين يكون
     * وقتُ الختم **بعد** نهاية الوردية، فالنتيجةُ إسقاطُ إضافيٍّ غير مُثبَت
     * لا حسمُ وقتٍ عُمِل.
     *
     * @param  array<string, mixed>  $day  مخرجاتُ `describeDay`
     * @return array{moment: Carbon, code: ?string, punched_at: Carbon}
     */
    private function resolveAppliedTime(
        StampContext $context,
        Carbon $now,
        array $day,
        ?Carbon $expectedOut,
        ?Carbon $checkedInAt = null,
    ): array {
        $unchanged = ['moment' => $now, 'code' => null, 'punched_at' => $now];

        if ($context->isCheckIn() || ! $expectedOut) {
            return $unchanged;
        }

        $windowEnd = $this->checkoutWindowEnd($day, $expectedOut);

        // بلا نافذةٍ مضبوطة لا قصّ — النافذةُ هي الإذن، وغيابُها ليس منعاً.
        if (! $windowEnd || $now->lte($windowEnd)) {
            return $unchanged;
        }

        // ختمٌ بعد النافذة، لكنه قبل نهاية الوردية (وردياتٌ تعبر منتصف
        // الليل بنوافذَ مضبوطةٍ خطأً): لا يُقصّ إلى ما دون ما عُمِل.
        if ($now->lte($expectedOut)) {
            return $unchanged;
        }

        /*
            ولا يُقصّ إلى ما قبل الحضور: من حضر بعد نهاية الوردية (20:11
            ونهايتُها 16:00) كان انصرافُه يُقصّ إلى 16:00 — قبل حضوره —
            فيقرؤه المعالجُ انصرافاً في الغد ويحسب له 1440 دقيقة إضافياً.
        */
        if ($checkedInAt && $expectedOut->lte($checkedInAt)) {
            return $unchanged;
        }

        return [
            'moment' => $expectedOut->copy(),
            'code' => 'capped_to_shift_end',
            'punched_at' => $now,
        ];
    }

    /**
     * نهايةُ نافذة الانصراف كلحظةٍ على خطّ الزمن.
     *
     * مصدران، والأسبقيّةُ للأخصّ:
     *
     *   ١) `shift_days.logout_end_time` — وقتٌ مجرَّدٌ على صفّ الوردية،
     *      يُثبَّت على يوم نهاية الوردية؛ ووقوعُه قبلها يعني أنه في اليوم
     *      التالي، كما يفعل `getExpectedCheckOut` بنهاية الوردية نفسِها
     *      في ورديةٍ تعبر منتصف الليل.
     *
     *   ٢) `attendance_settings.checkout_window_minutes` — دقائقُ بعد
     *      موعد الانصراف المتوقَّع. وبها وحدها تعمل الطبقةُ في منشأةٍ بلا
     *      جدول ورديات: هناك يُشتقّ موعدُ الانصراف من `work_hours_per_day`
     *      ولا يوجد صفُّ ورديةٍ تُقرأ منه نافذة، فكانت الطبقةُ نائمةً
     *      تماماً بلا أن يُنبّه أحد.
     */
    private function checkoutWindowEnd(array $day, Carbon $expectedOut): ?Carbon
    {
        $windowTime = $day['shift_day']?->logout_end_time;

        if ($windowTime) {
            $windowEnd = Carbon::parse($expectedOut->toDateString().' '.$windowTime);

            if ($windowEnd->lt($expectedOut)) {
                $windowEnd->addDay();
            }

            return $windowEnd;
        }

        $minutes = $day['config']['checkout_window_minutes'] ?? null;

        // صفرٌ نافذةٌ صالحة (لا انصرافَ بعد الوردية إطلاقاً)، والفراغُ لا قيد
        if ($minutes === null || $minutes === '') {
            return null;
        }

        return $expectedOut->copy()->addMinutes((int) $minutes);
    }

    private function minutesLate(Carbon $now, ?Carbon $expectedIn): int
    {
        if (! $expectedIn || $now->lte($expectedIn)) {
            return 0;
        }

        // Carbon 3 لا يُرجع فرقاً مطلقاً — `abs()` لازمة لا تجميلية
        return (int) abs($now->diffInMinutes($expectedIn));
    }

    private function minutesEarly(Carbon $now, ?Carbon $expectedOut): int
    {
        if (! $expectedOut || $now->gte($expectedOut)) {
            return 0;
        }

        return (int) abs($expectedOut->diffInMinutes($now));
    }

    private function responseData(
        Employee $employee,
        AttendanceDays $record,
        StampContext $context,
        ?AttendanceGeofence $fence,
        ?int $distance,
    ): array {
        $data = [
            'employee_name' => $employee->full_name,
            'date' => $record->attendance_date->format('Y-m-d'),
            'check_in_time' => $record->check_in_time,
            'check_out_time' => $record->check_out_time,
            'expected_check_in' => $record->expected_check_in,
            'expected_check_out' => $record->expected_check_out,
            'status' => $this->statusLabel($record->status),
            'status_code' => $record->status,
            'late_minutes' => (int) $record->late_minutes,
            'channel' => $context->channel === AttendancePunch::CHANNEL_KIOSK ? 'جهاز الفرع' : 'الجوال الميداني',
            'location' => $fence?->name,
            'latitude' => $context->latitude,
            'longitude' => $context->longitude,
            'distance_meters' => $distance,
        ];

        if (! $context->isCheckIn()) {
            $data['check_out_capped'] = (bool) $record->check_out_capped;
            $minutes = (int) $record->working_hours;
            $data['working_hours'] = sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
            $data['early_leave_minutes'] = (int) $record->early_leave_minutes;
            $data['overtime_minutes'] = (int) $record->overtime_minutes;
        }

        return $data;
    }

    /**
     * القصُّ يُقال للموظف في وجهه.
     *
     * ختمٌ يُقبل ثم يُكتب بوقتٍ آخر دون إخبارٍ هو أسوأُ من الردّ: الموظف
     * ينصرف مطمئناً، ويكتشف الفارقَ في قسيمة آخر الشهر حين لا يبقى ما
     * يُثبت به ساعاته.
     *
     * @param  array{moment: Carbon, code: ?string, punched_at: Carbon}  $applied
     * @return array<int, string>
     */
    private function cappingNotice(array $applied, Carbon $now): array
    {
        if ($applied['code'] !== 'capped_to_shift_end') {
            return [];
        }

        return [sprintf(
            'وقعَ انصرافُك (%s) خارج نافذة الوردية، فسُجِّل على نهاية الوردية (%s) '
            .'ولم يُحتسب أجرٌ إضافي. إن كنت عملتَ فعلاً بعد الدوام فقدِّم طلب تصحيح حضور.',
            $now->format('H:i'),
            $applied['moment']->format('H:i'),
        )];
    }

    /**
     * انصرافٌ بعد دقائق من الحضور يُقال للموظف: اليوم لا يُحتسب دواماً كاملاً.
     *
     * @return array<int, string>
     */
    private function shortDayNotice(AttendanceDays $record, StampContext $context): array
    {
        if ($context->isCheckIn()
            || ! in_array($record->status, [AttendanceDays::STATUS_ABSENT, AttendanceDays::STATUS_HALF_DAY], true)) {
            return [];
        }

        $minutes = (int) $record->working_hours;
        $worked = sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);

        return [$record->status === AttendanceDays::STATUS_ABSENT
            ? "مدة دوامك اليوم {$worked} فقط — أقل من الحد الأدنى، فيُحتسب اليوم غياباً."
            : "مدة دوامك اليوم {$worked} — أقل من نصف الوردية، فيُحتسب اليوم نصف يوم."];
    }

    private function statusLabel(?string $status): string
    {
        return [
            AttendanceDays::STATUS_PRESENT => 'في الوقت',
            AttendanceDays::STATUS_LATE => 'متأخر',
            AttendanceDays::STATUS_ABSENT => 'يُحتسب غياباً',
            AttendanceDays::STATUS_HALF_DAY => 'نصف يوم',
            AttendanceDays::STATUS_LEAVE => 'إجازة',
            AttendanceDays::STATUS_HOLIDAY => 'عطلة',
        ][$status] ?? (string) $status;
    }

    /**
     * فحصُ نوافذ الدخول والخروج المضبوطة على صفّ يوم الوردية.
     *
     * @param  array<string, mixed>  $day
     * @return array{code: string, message: string}|null
     */
    /**
     * متى يُقبل تسجيلُ الحضور لهذا اليوم.
     *
     * كان الحضورُ يُقبل في أيّ ساعة ما لم تُضبط «نافذة الدخول» على صفّ
     * الوردية — ونادراً ما تُضبط. فموظفٌ ورديتُه ٠٨:٠٠ يسجّل حضوراً العاشرة
     * ليلاً بعد أن احتُسب يومُه غياباً، فيُقلب اليومُ بلا طلب تصحيح.
     *
     * النافذةُ تبدأ من (البداية − «يُفتح الحضور قبل»)، أو من `login_start_time`
     * على صفّ الوردية إن ضُبط.
     *
     * وتنتهي بـ`login_end_time` إن ضُبط، وإلّا بـ«يُقفل تسجيل الحضور بعد»
     * إن ضُبط، وإلّا **لا تنتهي**. والافتراضُ أنّها لا تنتهي: كان الإقفالُ
     * مربوطاً بـ«مهلة الغياب» بلا مفتاح، فيُمنع المتأخّرُ من تسجيل حضوره
     * ولا يبقى له إلّا طلبُ تصحيحٍ عن تأخيرٍ عاديّ — واليومُ يُحكَم عليه
     * بتلك المهلة سواءٌ سجّل أم لم يسجّل، فالمنعُ يُفقد السجلَّ ولا يُغيّر
     * الحكم.
     *
     * @param  array<string, mixed>  $day
     * @return array{start: ?Carbon, end: ?Carbon}
     */
    public function checkInWindow(array $day, Carbon $targetDate): array
    {
        $shiftDay = $day['shift_day'] ?? null;
        $expectedIn = $day['expected_check_in'] ?? null;
        $config = $day['config'] ?? [];

        // الدوامُ المرن يبدأ متى شاء صاحبُه في يومه — مرّةً واحدة
        if (($config['work_mode'] ?? 'shift') === 'flexible') {
            return ['start' => $targetDate->copy()->startOfDay(), 'end' => $targetDate->copy()->endOfDay()];
        }

        $start = $this->momentOnDate($targetDate, $shiftDay?->login_start_time ?? null)
            ?? $expectedIn?->copy()->subMinutes((int) ($config['checkin_early_minutes'] ?? 60));

        /*
            نهايةُ النافذة — بثلاث مراتب:

              ١) `login_end_time` على صفّ الوردية: قرارٌ صريحٌ على جدولٍ صريح.
              ٢) «يُقفل تسجيل الحضور بعد» من إعدادات الدوام، إن ضُبط.
              ٣) وإلّا **لا نهاية**: المتأخّرُ يسجّل حضورَه، ويُحكَم على يومه
                 بـ`absent_after_minutes` (متأخّر أم غياب) — سجلٌّ بحقيقته.

            كانت المرتبةُ الثانية هي `absent_after_minutes` نفسَها بلا مفتاحٍ
            يُطفئها، فيُقفَل التسجيلُ 10:00 لكلّ من لا وردية له (08:00 + 120).
            والحكمُ على اليوم لم يتغيّر — `determineCheckInStatus()` تُسند
            «غياباً» للمتأخّر كما كانت.
        */
        $closeAfter = $config['checkin_close_after_minutes'] ?? null;

        $end = $this->momentOnDate($targetDate, $shiftDay?->login_end_time ?? null)
            ?? ($closeAfter !== null && $closeAfter !== ''
                ? $expectedIn?->copy()->addMinutes((int) $closeAfter)
                : null);

        return ['start' => $start, 'end' => $end];
    }

    private function verifyPunchWindow(StampContext $context, Carbon $now, array $day, Carbon $targetDate, ?AttendanceDays $record = null): ?array
    {
        $shiftDay = $day['shift_day'] ?? null;

        if ($context->isCheckIn()) {
            // يومُ عطلةٍ أو إجازة يُردّ بسببه الصحيح في `verifyDayRecord` لا بالنافذة
            if (! empty($day['is_holiday']) || ! empty($day['is_weekly_off']) || ! empty($day['leave'])) {
                return null;
            }

            // يومٌ فيه حضورٌ أو مصحَّح: ردُّه الصحيح («سُجّل مسبقاً»، «مصحَّح»، أو
            // الوردية الثانية) في `verifyDayRecord` — لا «انتهى الوقت»
            if ($record && ($record->check_in_time || $record->manual_override)) {
                return null;
            }

            ['start' => $start, 'end' => $end] = $this->checkInWindow($day, $targetDate);
            $shiftStart = ($day['expected_check_in'] ?? null)?->format('H:i');

            if ($start && $now->lt($start)) {
                return [
                    'code' => 'outside_login_window',
                    'message' => 'لم يبدأ وقت تسجيل الحضور بعد — يبدأ الساعة '.$start->format('H:i')
                        .($shiftStart ? ' (ورديتك تبدأ '.$shiftStart.')' : '').'.',
                ];
            }

            if ($end && $now->gt($end)) {
                return [
                    'code' => 'outside_login_window',
                    'message' => 'انتهى وقت تسجيل الحضور لهذا اليوم (كان حتى '.$end->format('H:i').') — يُحتسب اليوم غياباً. إن كان لديك عذر فقدّم طلب تصحيح حضور.',
                ];
            }

            return null;
        }

        if (! $shiftDay) {
            return null;
        }

        $logoutStart = null;
        $expectedOut = $day['expected_check_out'] ?? null;
        $baseDate = $expectedOut instanceof Carbon ? $expectedOut : $targetDate;
        $logoutStart = $this->momentOnDate($baseDate, $shiftDay->logout_start_time ?? null);

        if ($logoutStart && $now->lt($logoutStart)) {
            return [
                'code' => 'outside_logout_window',
                'message' => 'لا يُقبل الانصراف قبل الساعة '.$logoutStart->format('H:i').' — إن احتجت الخروج مبكراً فاطلب إذن انصراف مبكر.',
            ];
        }

        return null;
    }

    private function momentOnDate(Carbon $date, mixed $time): ?Carbon
    {
        if ($time === null || $time === '') {
            return null;
        }

        return Carbon::parse($date->toDateString().' '.$time);
    }

    /**
     * إنصرافٌ بشرط اكتمال خط السير — حين تكون طريقة الانصراف route/both.
     *
     * @param  array<string, mixed>  $config
     * @return array{code: string, message: string}|null
     */
    private function verifyCheckoutRoute(Employee $employee, Carbon $date, array $config): ?array
    {
        $method = $config['checkout_method'] ?? AttendanceSetting::CHECKOUT_HOURS;

        if (! in_array($method, [AttendanceSetting::CHECKOUT_ROUTE, AttendanceSetting::CHECKOUT_BOTH], true)) {
            return null;
        }

        // بلا تتبّع مسار على إعداد الموظف، وبدون زيارات لليوم → لا قيد
        $userId = User::where('employee_id', $employee->id)->value('id');
        $ids = array_values(array_filter([(int) $employee->id, $userId ? (int) $userId : null]));

        $visits = EmployeeClientVisit::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('visit_date', $date->toDateString())
            ->get();

        if ($visits->isEmpty()) {
            // route إلزامي مع uses_route_tracking وبلا زيارات = لا يُمنع ساعات العمل
            if ($method === AttendanceSetting::CHECKOUT_ROUTE && ! empty($config['uses_route_tracking'])) {
                return [
                    'code' => 'route_missing',
                    'message' => 'لا يوجد خط سير لهذا اليوم — لا يُقبل الانصراف بطريقة «خط السير».',
                ];
            }

            return null;
        }

        $incomplete = $visits->filter(function (EmployeeClientVisit $visit) {
            $status = $visit->visit_status;

            if ($status === VisitStatus::COMPLETED || $status === VisitStatus::COMPLETED->value) {
                return false;
            }

            // التوافق مع العمود القديم status=active بمعنى مكتمل
            return $visit->status !== 'active';
        });

        if ($incomplete->isNotEmpty()) {
            return [
                'code' => 'route_incomplete',
                'message' => sprintf(
                    'لم يُكمَل خط السير بعد (%d من %d زيارة معلّقة) — أكمل المهام ثم سجّل الانصراف.',
                    $incomplete->count(),
                    $visits->count()
                ),
            ];
        }

        return null;
    }
}
