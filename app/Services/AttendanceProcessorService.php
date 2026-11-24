<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceDays;
use App\Models\AttendanceSetting;
use App\Models\CustomShift;
use App\Models\Deduction;
use App\Models\DeductionHistory;
use App\Models\Employee;
use App\Models\EmployeeAttendanceConfig;
use App\Models\Holiday;
use App\Models\LeavePermissions;
use App\Models\LeaveRequest;
use App\Models\SalarySlip;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Services\Attendance\AttendanceRuleOverrides;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * نظام معالجة الحضور والانصراف الأوتوماتيكي.
 *
 * المسؤوليات:
 *   1) فحص حضور كل موظف نشط للتاريخ المُمرَّر
 *   2) احتساب التأخير والغياب حسب الإعدادات
 *   3) مراعاة العطلات الرسمية، العطلات الأسبوعية، والإجازات المعتمدة
 *   4) إنشاء خصمية تلقائية على Deduction model عند التأخير/الغياب
 *   5) تحديث attendance_days بالحالة والمعلومات
 *
 * تُستخدم من:
 *   - ProcessAttendanceDaily artisan command (cron)
 *   - يدوياً من Controllers
 */
class AttendanceProcessorService
{
    /**
     * عالج يوم حضور كامل لكل الموظفين.
     *
     * @return array{processed:int, present:int, absent:int, late:int, leave:int, holiday:int, deductions:int, errors:array}
     */
    public function processDay(?Carbon $date = null): array
    {
        $date = $date ?? now();
        $dateStr = $date->toDateString();
        $stats = [
            'processed' => 0, 'present' => 0, 'absent' => 0, 'late' => 0,
            'half_day' => 0, 'leave' => 0, 'holiday' => 0, 'deductions' => 0,
            'errors' => [],
        ];

        $employees = Employee::whereIn('status', EmploymentStatus::activeValues())
            ->with(['attendanceConfig', 'job_title'])
            ->get();

        foreach ($employees as $emp) {
            try {
                $result = $this->processEmployeeDay($emp, $date);
                $stats['processed']++;
                if (isset($stats[$result['status']])) {
                    $stats[$result['status']]++;
                }
                if ($result['deduction_created']) {
                    $stats['deductions']++;
                }
            } catch (\Throwable $e) {
                $stats['errors'][] = "Employee #{$emp->id}: ".$e->getMessage();
                Log::error('AttendanceProcessor failed for employee', [
                    'employee_id' => $emp->id, 'date' => $dateStr, 'err' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * عالج يوم حضور موظف واحد.
     *
     * تُستدعى مع كل ختمٍ وكل ليلةٍ وعند اعتماد إجازة أو تصحيح — فيجب أن
     * يكون ناتجُها واحداً مهما تكرّرت: حالةُ اليوم وخصمياتُه **تُزامَن**
     * مع ما يستحقّه اليوم الآن (`syncDayDeductions`)، لا تُضاف فوق ما سبق.
     *
     * @return array{status:string, deduction_created:bool, message:string}
     */
    public function processEmployeeDay(Employee $employee, Carbon $date): array
    {
        return DB::transaction(function () use ($employee, $date) {
            $dateStr = $date->toDateString();

            $day = AttendanceDays::where('employee_id', $employee->id)
                ->whereDate('attendance_date', $dateStr)
                ->first();

            /*
                تصحيحٌ معتمد يثبّت اليوم أمام الكرون. الإجازة المعتمدة تتقدّم
                عليه لأن قرار الإجازة نظاميّ أعلى من تصحيح حضور.
            */
            if ($day && $day->manual_override) {
                $leave = $this->getApprovedLeave($employee, $date);
                if ($leave) {
                    return $this->markAsLeave($employee, $date, $leave);
                }

                return [
                    'status' => (string) $day->status,
                    'deduction_created' => false,
                    'message' => 'مثبَّت بتصحيح يدوي — تُرِك كما هو',
                ];
            }

            // 1) عطلة رسمية؟ 2) عطلة أسبوعية؟ — ومن عمل فيها يُحسب له إضافياً
            if ($this->isHoliday($date, $employee)) {
                return $this->markAsHoliday($employee, $date);
            }

            if ($this->isWeeklyOff($date, $employee)) {
                return $this->markAsWeeklyOff($employee, $date);
            }

            // 3) في إجازة معتمدة؟
            $leave = $this->getApprovedLeave($employee, $date);
            if ($leave) {
                return $this->markAsLeave($employee, $date, $leave);
            }

            $config = $this->resolveConfig($employee, $date);

            // الدوامُ المرن: عددُ ساعاتٍ لا وقتُ بداية — يُقاس بصافي ما عمله
            if (($config['work_mode'] ?? 'shift') === 'flexible') {
                return $this->processFlexibleDay($employee, $date, $day, $config);
            }

            $expectedIn = $this->getExpectedCheckIn($employee, $date, $config);
            $expectedOut = $this->getExpectedCheckOut($employee, $date, $config, $expectedIn);

            /*
                لم يحن وقتُ الحكم بعد.

                كانت المعالجةُ تكتب «غائب» لكل من لم يسجّل لحظةَ تشغيلها —
                والمجدوِلُ يعمل خلال اليوم، فيُسجَّل غائباً الساعة الخامسة
                عصراً من دوامُه يبدأ مساءً، وتُنشأ عليه خصمية غياب لا
                يرفعها حضورُه بعد ذلك.

                والغيابُ لا يُقطع به قبل «بداية الدوام + مهلة الغياب»: بعدها
                لا يكون الحضورُ إلا غياباً على كل حال (التأخّرُ فوقها غياب).
            */
            $noCheckIn = ! $day || ! $day->check_in_time;
            if ($noCheckIn && $expectedIn && now()->lt($expectedIn->copy()->addMinutes((int) $config['absent_after_minutes']))) {
                return [
                    'status' => 'pending',
                    'deduction_created' => false,
                    'message' => 'لم يسجّل بعد — لم يحن وقت احتساب الغياب',
                ];
            }

            if (! $day) {
                $day = AttendanceDays::create([
                    'employee_id' => $employee->id,
                    'attendance_date' => $dateStr,
                    'status' => AttendanceDays::STATUS_ABSENT,
                    'auto_generated' => true,
                    'check_in_method' => 'manual',
                    'check_out_method' => 'manual',
                ]);
            }

            /*
                أذونُ الخروج والتأخير بعذر — ما تُفسَّر به فروقاتُ اليوم.
                الدقائقُ تبقى مكتوبةً كما وقعت — الإذنُ يرفع العقوبةَ لا
                يُزوّر الواقعة.
            */
            $excusedLate = $this->hasPermission($employee, $date, LeavePermissions::LATE_ARRIVAL);
            $excusedEarlyLeave = $this->hasPermission($employee, $date, LeavePermissions::EARLY_LEAVE);

            $day->expected_check_in = $expectedIn?->format('H:i:s');
            $day->expected_check_out = $expectedOut?->format('H:i:s');
            $day->is_holiday = false;
            $day->is_weekly_off = false;
            // إجازةٌ أُلغي اعتمادُها لا تبقى علامتُها على اليوم
            $day->is_on_leave = false;
            $day->leave_request_id = null;
            $day->early_leave_minutes = 0;
            $day->overtime_minutes = 0;

            // 4) لم يُسجّل دخول → غياب
            if (! $day->check_in_time) {
                $day->status = AttendanceDays::STATUS_ABSENT;
                $day->late_minutes = 0;
                $day->processed_at = now();
                $day->save();

                $created = $this->syncDayDeductions($day, $config, ['absent' => true]);

                return [
                    'status' => 'absent',
                    'deduction_created' => $created,
                    'message' => 'غائب — لم يسجل حضور',
                ];
            }

            // 5) التأخير
            $checkInTime = Carbon::parse($day->attendance_date->toDateString().' '.$day->check_in_time);
            $lateMinutes = 0;
            if ($expectedIn && $checkInTime->gt($expectedIn)) {
                $lateMinutes = (int) abs($checkInTime->diffInMinutes($expectedIn));
            }

            $graceMinutes = (int) $config['late_grace_minutes'];
            $absentAfterMinutes = (int) $config['absent_after_minutes'];

            $day->late_minutes = $lateMinutes;

            if ($excusedLate || $lateMinutes <= $graceMinutes) {
                $day->status = AttendanceDays::STATUS_PRESENT;
            } elseif ($lateMinutes >= $absentAfterMinutes) {
                $day->status = AttendanceDays::STATUS_ABSENT;
            } else {
                $day->status = AttendanceDays::STATUS_LATE;
            }

            // 6) الانصراف: مبكّر / إضافي / نصف يوم
            if ($day->check_out_time && $expectedOut) {
                $checkOutTime = Carbon::parse($day->attendance_date->toDateString().' '.$day->check_out_time);

                /*
                    الانصرافُ لليوم التالي للوردية الليلية وحدها.

                    كان يُنقل إلى الغد كلَّما وقع قبل الحضور — فحضورٌ
                    الساعة 20:11 وانصرافٌ قُصّ إلى 16:00 صار انصرافاً في
                    الغد، وحُسب له 1440 دقيقة إضافياً. والورديةُ الليلية
                    تُعرف بانتهائها في يومٍ بعد يوم بدايتها.
                */
                $overnightShift = $expectedIn && $expectedOut->toDateString() !== $expectedIn->toDateString();
                if ($checkOutTime->lt($checkInTime) && $overnightShift) {
                    $checkOutTime->addDay();
                }

                if ($checkOutTime->gte($checkInTime)) {
                    $expectedShiftMinutes = $expectedIn
                        ? max(1, (int) abs($expectedOut->diffInMinutes($expectedIn)))
                        : max(1, (int) round(((float) ($config['work_hours_per_day'] ?? 8)) * 60));
                    $threshold = (int) ($config['early_leave_threshold_minutes'] ?? 0);

                    if ($checkOutTime->lt($expectedOut)) {
                        $earlyMinutes = (int) abs($checkOutTime->diffInMinutes($expectedOut));
                        $day->early_leave_minutes = (! $excusedEarlyLeave && $earlyMinutes > $threshold) ? $earlyMinutes : 0;
                    } else {
                        /*
                            الإضافيُّ ما زاد على تعويض التأخير، وفوق حدٍّ أدنى.

                            من تأخّر نصف ساعة وبقي بعد الدوام نصف ساعة عمل
                            ساعاتِه بالضبط — لا تأخيرَ يُعاقَب ولا إضافيَّ
                            يُدفع معاً. والحدُّ الأدنى هو سماحُ الانصراف نفسه:
                            دقيقتان بعد الدوام لا تُعدّ ساعاتٍ إضافية.
                        */
                        $after = (int) abs($checkOutTime->diffInMinutes($expectedOut));
                        $overtime = max(0, $after - $lateMinutes);
                        $day->overtime_minutes = $overtime > $threshold ? $overtime : 0;
                    }

                    /*
                        الاستراحةُ فوق المسموح نقصٌ في الدوام: تُضاف إلى دقائق
                        الانصراف المبكر (فتُخصم مثلها) وتُنقص الإضافيّ. والإذنُ
                        بالانصراف المبكر لا يُغطّيها — هي وقتٌ خارج العمل وسط اليوم.
                    */
                    $excessBreak = max(0, (int) $day->break_minutes - (int) ($config['break_allowed_minutes'] ?? 60));
                    if ($excessBreak > 0) {
                        $day->early_leave_minutes = (int) $day->early_leave_minutes + $excessBreak;
                        $day->overtime_minutes = max(0, (int) $day->overtime_minutes - $excessBreak);
                    }

                    if ($day->status !== AttendanceDays::STATUS_ABSENT) {
                        // صافي العمل: من الحضور إلى الانصراف ناقصاً الاستراحات
                        $workedMinutes = max(0, (int) abs($checkOutTime->diffInMinutes($checkInTime)) - (int) $day->break_minutes);
                        /*
                            الحكمُ على اليوم بعد الانصراف — من إعدادَين ظاهرَين في «الدوام والحضور»:
                              • صافي عملٍ أقلّ من «يُحتسب غياباً إذا عمل أقل من (دقيقة)» ⇐ غياب
                              • أقلّ من «نصف يوم إذا عمل أقل من (%)» من الدوام المطلوب ⇐ نصف يوم
                              • غيرُ ذلك حاضر، والنقصُ انصرافٌ مبكر يُخصم بدقائقه
                            والدوامُ المطلوب صافياً: طولُ الوردية ناقصاً الاستراحةَ المسموحة —
                            وردية 09:00–17:00 باستراحة ساعة = 7 ساعات؛ من عمل 7 ساعات حاضرٌ كامل.
                            والإذنُ بالانصراف المبكر يرفع خصمَ الانصراف لا يجعل الدقائقَ يومَ عمل.
                        */
                        $requiredNet = max(1, $expectedShiftMinutes - (int) ($config['break_allowed_minutes'] ?? 0));
                        $halfShift = (int) floor($requiredNet * ((int) ($config['half_day_below_percent'] ?? 50)) / 100);
                        $absentBelow = (int) ($config['absent_below_minutes'] ?? 120);

                        if ($workedMinutes < $absentBelow) {
                            $day->status = AttendanceDays::STATUS_ABSENT;
                            $day->early_leave_minutes = 0;
                            $day->overtime_minutes = 0;
                        } elseif ($workedMinutes < $halfShift || $day->early_leave_minutes >= $halfShift) {
                            $day->status = AttendanceDays::STATUS_HALF_DAY;
                        }
                    }
                }
            }

            $day->processed_at = now();
            $day->save();

            // 7) خصمياتُ اليوم كما يستحقّها الآن — تُلغى ما لم يعد مستحقاً
            $wanted = [];
            if ($day->status === AttendanceDays::STATUS_ABSENT) {
                $wanted['absent'] = true;
            } elseif ($day->status === AttendanceDays::STATUS_HALF_DAY) {
                $wanted['half_day'] = true;
            } elseif ($day->status === AttendanceDays::STATUS_LATE) {
                $wanted['late'] = true;
            }
            if ($day->early_leave_minutes > 0
                && in_array($day->status, [AttendanceDays::STATUS_PRESENT, AttendanceDays::STATUS_LATE], true)) {
                $wanted['early_leave'] = true;
            }

            $created = $this->syncDayDeductions($day, $config, $wanted);

            return [
                'status' => $day->status,
                'deduction_created' => $created,
                'message' => 'تأخير '.$lateMinutes.' دقيقة',
            ];
        });
    }

    /**
     * يومُ موظفٍ بدوامٍ مرن: عددُ ساعاتٍ في اليوم يبدؤها متى شاء.
     *
     * لا تأخيرَ لأنه لا موعدَ بداية. الحكمُ بصافي العمل (من الحضور إلى
     * الانصراف ناقصاً الاستراحات) مقابل الساعات المطلوبة: النقصُ فوق السماح
     * انصرافٌ مبكر يُخصم، والزيادةُ فوقه إضافي، وما دون مهلة الغياب غيابٌ،
     * وما دون نصف المطلوب نصفُ يوم.
     *
     * @param  array<string, mixed>  $config
     * @return array{status:string, deduction_created:bool, message:string}
     */
    private function processFlexibleDay(Employee $employee, Carbon $date, ?AttendanceDays $day, array $config): array
    {
        $required = max(1, (int) round(((float) ($config['work_hours_per_day'] ?? 8)) * 60));
        $threshold = (int) ($config['early_leave_threshold_minutes'] ?? 0);
        $absentAfter = (int) ($config['absent_after_minutes'] ?? 120);

        // لم يسجّل: لا يُحكم بالغياب قبل انقضاء اليوم — قد يبدأ دوامه مساءً
        if (! $day || ! $day->check_in_time) {
            if ($date->copy()->endOfDay()->isFuture()) {
                return ['status' => 'pending', 'deduction_created' => false, 'message' => 'دوام مرن — لم يسجّل بعد'];
            }

            $day ??= AttendanceDays::create([
                'employee_id' => $employee->id,
                'attendance_date' => $date->toDateString(),
                'status' => AttendanceDays::STATUS_ABSENT,
                'auto_generated' => true,
                'check_in_method' => 'manual',
                'check_out_method' => 'manual',
            ]);
        }

        $day->expected_check_in = null;
        $day->expected_check_out = null;
        $day->is_holiday = false;
        $day->is_weekly_off = false;
        $day->is_on_leave = false;
        $day->leave_request_id = null;
        $day->late_minutes = 0;
        $day->early_leave_minutes = 0;
        $day->overtime_minutes = 0;

        if (! $day->check_in_time) {
            $day->status = AttendanceDays::STATUS_ABSENT;
        } else {
            $day->status = AttendanceDays::STATUS_PRESENT;

            if ($day->check_out_time) {
                $in = Carbon::parse($date->toDateString().' '.$day->check_in_time);
                $out = Carbon::parse($date->toDateString().' '.$day->check_out_time);
                if ($out->lt($in)) {
                    $out->addDay(); // دوامٌ مرنٌ بدأ مساءً وانتهى بعد منتصف الليل
                }

                $net = max(0, (int) $in->diffInMinutes($out) - (int) $day->break_minutes);
                // نفسُ إعدادَي الحكم على اليوم في الوردية (انظر processEmployeeDay)
                $half = (int) floor($required * ((int) ($config['half_day_below_percent'] ?? 50)) / 100);
                $excusedEarly = $this->hasPermission($employee, $date, LeavePermissions::EARLY_LEAVE);

                if ($net < (int) ($config['absent_below_minutes'] ?? 120)) {
                    $day->status = AttendanceDays::STATUS_ABSENT;
                } elseif ($net < $half) {
                    $day->status = AttendanceDays::STATUS_HALF_DAY;
                } else {
                    $short = $required - $net;
                    $day->early_leave_minutes = (! $excusedEarly && $short > $threshold) ? $short : 0;
                    $extra = $net - $required;
                    $day->overtime_minutes = $extra > $threshold ? $extra : 0;
                }
            }
        }

        $day->processed_at = now();
        $day->save();

        $wanted = match ($day->status) {
            AttendanceDays::STATUS_ABSENT => ['absent' => true],
            AttendanceDays::STATUS_HALF_DAY => ['half_day' => true],
            default => $day->early_leave_minutes > 0 ? ['early_leave' => true] : [],
        };

        return [
            'status' => $day->status,
            'deduction_created' => $this->syncDayDeductions($day, $config, $wanted),
            'message' => 'دوام مرن',
        ];
    }

    /**
     * عالج نطاقاً من الموظفين والتواريخ (شاشة «حساب أيام الحضور»).
     *
     * لا يُنشئ دفاترَ غيابٍ فارغة — يشغّل المعالج الحقيقي على كل يوم ≤ اليوم.
     *
     * @param  array<int, int>  $employeeIds
     * @return array{processed:int, present:int, absent:int, late:int, leave:int, holiday:int, deductions:int, skipped_future:int, errors:array}
     */
    public function processEmployeesDateRange(array $employeeIds, Carbon $from, Carbon $to): array
    {
        $stats = [
            'processed' => 0, 'present' => 0, 'absent' => 0, 'late' => 0,
            'half_day' => 0, 'leave' => 0, 'holiday' => 0, 'deductions' => 0,
            'skipped_future' => 0,
            'errors' => [],
        ];

        $today = now()->startOfDay();
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        if ($to->lt($from)) {
            return $stats;
        }

        $employees = Employee::whereIn('id', $employeeIds)
            ->whereIn('status', EmploymentStatus::activeValues())
            ->with(['attendanceConfig', 'job_title'])
            ->get()
            ->keyBy('id');

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            if ($date->gt($today)) {
                $stats['skipped_future'] += count($employeeIds);

                continue;
            }

            foreach ($employeeIds as $employeeId) {
                $employee = $employees->get($employeeId);

                if (! $employee) {
                    continue;
                }

                try {
                    $result = $this->processEmployeeDay($employee, $date->copy());
                    $stats['processed']++;
                    if (isset($stats[$result['status']])) {
                        $stats[$result['status']]++;
                    }
                    if ($result['deduction_created']) {
                        $stats['deductions']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors'][] = "Employee #{$employeeId} @ {$date->toDateString()}: ".$e->getMessage();
                    Log::error('AttendanceProcessor range failed', [
                        'employee_id' => $employeeId,
                        'date' => $date->toDateString(),
                        'err' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $stats;
    }

    /**
     * حالةُ الحضور الفورية عند مسح الباركود — بنفس عتبات ومنطق
     * `processEmployeeDay` بالضبط (الوردية الفعلية ليوم الأسبوع، ودقائق
     * السماح، وعتبة اعتبار التأخير غياباً) — لا عتبةٍ ثابتة منفصلة.
     *
     * هذه ليست الحالة النهائية: `processEmployeeDay` يعيد حسابها فور
     * الانصراف (TODO 9) أو ليلاً عبر الكرون، فتستقرّ عليها. الغرض هنا
     * أن تكون الرسالة الفورية للموظف صحيحةً منذ اللحظة الأولى، لا أن
     * تُقرأ "متأخر" بعد الساعة 08:00 دائماً بصرف النظر عن ورديته.
     *
     * @return array{status: string, late_minutes: int}
     */
    public function determineCheckInStatus(Employee $employee, Carbon $date, Carbon $checkInTime): array
    {
        $config = $this->resolveConfig($employee, $date);

        if (($config['work_mode'] ?? 'shift') === 'flexible') {
            return ['status' => AttendanceDays::STATUS_PRESENT, 'late_minutes' => 0];
        }

        $expectedIn = $this->getExpectedCheckIn($employee, $date, $config);

        $lateMinutes = 0;
        if ($expectedIn && $checkInTime->gt($expectedIn)) {
            $lateMinutes = (int) abs($checkInTime->diffInMinutes($expectedIn));
        }

        $graceMinutes = (int) $config['late_grace_minutes'];
        $absentAfterMinutes = (int) $config['absent_after_minutes'];

        if ($lateMinutes <= $graceMinutes) {
            $status = AttendanceDays::STATUS_PRESENT;
        } elseif ($lateMinutes >= $absentAfterMinutes) {
            $status = AttendanceDays::STATUS_ABSENT;
        } else {
            $status = AttendanceDays::STATUS_LATE;
        }

        return ['status' => $status, 'late_minutes' => $lateMinutes];
    }

    /**
     * وصفُ اليوم كما تراه حلقاتُ التسلسل قبل الختم: الوردية المتوقَّعة،
     * والعطلةُ والإجازةُ إن وُجدتا.
     *
     * كلُّ ما تحته مكتوبٌ هنا منذ `processEmployeeDay` — لكنه كان محبوساً
     * خلف `private`، فكان كلُّ مسارِ تسجيلٍ يريد أن يعرف «أهذا يومُ عطلة؟»
     * مضطراً إلى إعادة كتابة المنطق نفسِه، فيتفرّع الجوابُ بتفرّع النسخ.
     * لا حسابَ جديداً هنا: كشفٌ فقط.
     *
     * @return array{
     *     config: array<string, mixed>,
     *     expected_check_in: ?Carbon,
     *     expected_check_out: ?Carbon,
     *     is_holiday: bool,
     *     holiday_name: ?string,
     *     is_weekly_off: bool,
     *     leave: ?LeaveRequest,
     *     has_shift_day: bool,
     *     shift_day: ?ShiftDay
     * }
     */
    public function describeDay(Employee $employee, Carbon $date): array
    {
        $config = $this->resolveConfig($employee, $date);
        $expectedIn = $this->getExpectedCheckIn($employee, $date, $config);
        $expectedOut = $expectedIn ? $this->getExpectedCheckOut($employee, $date, $config, $expectedIn) : null;
        $isHoliday = $this->isHoliday($date, $employee);
        /*
            نافذةُ الدخول/الخروج من صفّ الوردية فقط حين أوقاتُ اليوم
            مأخوذةٌ من تلك الوردية. إن غلب إعدادُ الموظف الفردي (مثل دوام
            مسائي 16:35) على ورديةٍ دائمة صباحية، لا تُفرَض نافذةُ الصباح
            (حتى 12:00) فتردّ المسح المسائي.
        */
        $shiftDayForWindows = $this->resolveShiftDayForPunchWindows($employee, $date);

        /*
            الدوامُ المرن بلا موعد: لا بدايةَ يُقاس عليها التأخير، ولا نهايةَ
            يُقصّ إليها الانصراف، ولا نوافذَ ورديةٍ تُفرض — كلُّ من يقرأ
            اليومَ (الختم، الشاشات) يرى ذلك من هنا لا بشرطٍ في كل موضع.
        */
        if (($config['work_mode'] ?? 'shift') === 'flexible') {
            $expectedIn = null;
            $expectedOut = null;
            $shiftDayForWindows = null;
        }

        return [
            'config' => $config,
            'expected_check_in' => $expectedIn,
            'expected_check_out' => $expectedOut,
            'is_holiday' => $isHoliday,
            'holiday_name' => $isHoliday ? $this->getHolidayName($date, $employee) : null,
            'is_weekly_off' => $this->isWeeklyOff($date, $employee),
            'leave' => $this->getApprovedLeave($employee, $date),
            'has_shift_day' => $this->resolveShiftDay($employee, $date) !== null,
            'shift_day' => $shiftDayForWindows,
        ];
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * إعداداتُ الحضور لهذا الموظف — وليومٍ بعينه إن مُرِّر.
     *
     * `$date` جديدٌ هنا: مهلةُ التأخير تُعرَّف في **شاشة الورديات** لكل
     * يومٍ من أيام الوردية (`shift_days.grace_period`)، ولا تُعرف إلا
     * بمعرفة أيِّ يومِ أسبوعٍ نحن فيه. وبدونه تُقرأ مهلةُ المنشأة العامّة
     * لكل وردية.
     */
    private function resolveConfig(Employee $employee, ?Carbon $date = null): array
    {
        $global = AttendanceSetting::current();
        $custom = $employee->attendanceConfig; // EmployeeAttendanceConfig

        /*
            مهلةُ التأخير — إعدادُ الموظف أولاً إن وُجد.

            ثم: إعدادُ المنشأة والوردية معاً بـ`min` لا بأسبقيّة أحدهما.
            كانت الورديةُ تعلو على المنشأة، و`grace_period` الافتراضي عند
            إنشاء الوردية = 15، فيُلغى صامتًا ما ضبطه المسؤول في «إعدادات
            الحضور» (مثل السماح = 0 لخصم كل تأخير). تأخيرُ 10 دقائق يبقى
            «حاضراً» بلا خصم رغم تفعيل الخصمية في الإعدادات.

            فإعدادُ المنشأة سقفٌ عام، والورديةُ قد تشدِّد فقط (قيمةٌ أصغر)،
            ولا تُرخِّص فوق سقف المنشأة.

            وصفرُ المنشأة مهلةٌ صريحة — لا تُسقَط بـ`?:` أو `array_filter`
            بلا شرط.
        */
        $shiftGrace = $date !== null
            ? $this->resolveShiftDay($employee, $date)?->grace_period
            : null;

        $companyGrace = $global->late_grace_minutes;
        $resolvedGrace = $custom?->late_grace_minutes;
        if ($resolvedGrace === null) {
            $candidates = [];
            if ($companyGrace !== null && $companyGrace !== '') {
                $candidates[] = (int) $companyGrace;
            }
            if ($shiftGrace !== null && $shiftGrace !== '') {
                $candidates[] = (int) $shiftGrace;
            }
            $resolvedGrace = $candidates === [] ? 15 : min($candidates);
        }

        $config = [
            'late_grace_minutes' => (int) $resolvedGrace,
            'absent_after_minutes' => $custom?->absent_after_minutes ?? $global->absent_after_minutes ?? 120,
            'auto_deduction_enabled' => $custom?->auto_deduction_enabled ?? $global->auto_deduction_enabled ?? false,
            'late_deduction_amount' => $custom?->late_deduction_amount ?? $global->late_deduction_amount ?? 0,
            'absence_deduction_amount' => $custom?->absence_deduction_amount ?? $global->absence_deduction_amount ?? 0,
            'early_leave_deduction_amount' => $custom?->early_leave_deduction_amount
                ?? $global->early_leave_deduction_amount
                ?? $global->late_deduction_amount
                ?? 0,
            'work_hours_per_day' => $custom?->work_hours_per_day ?? $global->work_hours_per_day ?? 8,
            'checkout_method' => $custom?->checkout_method ?? $global->checkout_method ?? 'hours',
            'deduction_calculation_type' => $global->deduction_calculation_type ?? 'fixed',
            // اعتمادُ خصميات الحضور: تلقائي (تُقيَّد فوراً) أو يدوي (معلّقة حتى يعتمدها المدير)
            'deduction_approval' => $global->deduction_approval ?: 'auto',
            /*
                العطلةُ الأسبوعية: إسنادُ الموظف أَولى، ثم إعدادُ المنشأة،
                ثم الجمعة. والوسطُ هو الجديد — كان الثابتُ يلي الإسناد
                مباشرةً، فمنشأةٌ عطلتُها الجمعة والسبت يُحتسب سبتُها يومَ
                غيابٍ لكل موظفٍ بلا إعدادٍ خاص، بلا شاشةٍ تُصلحه.

                و`??` لا `?:`: مصفوفةٌ فارغةٌ قرارٌ صريح — «لا عطلة أسبوعية»
                لمنشأةٍ تعمل سبعة أيام — و`?:` كانت ستقلبها إلى الجمعة.
            */
            'weekly_off_days' => $custom?->weekly_off_days
                ?? $global->default_weekly_off_days
                ?? ['fri'],

            // بدايةُ الدوام لمن لا وردية له — مُدخَلةٌ لا مكتوبةٌ في الشيفرة
            'default_shift_start' => $global->default_shift_start ?: '08:00:00',
            'photos_required' => $custom?->photos_required ?? $global->photos_required ?? false,
            // سماحُ الانصراف المبكر: ما دونه لا يُعدّ انصرافاً مبكراً أصلاً
            'early_leave_threshold_minutes' => $global->early_leave_threshold_minutes ?? 0,
            // الحكمُ على اليوم بعد الانصراف: غيابٌ تحت هذه الدقائق، ونصفُ يومٍ تحت هذه النسبة
            'absent_below_minutes' => (int) ($global->absent_below_minutes ?? 120),
            'half_day_below_percent' => (int) ($global->half_day_below_percent ?? 50),
            // كم دقيقة قبل بداية الوردية يُفتح تسجيلُ الحضور
            'checkin_early_minutes' => (int) ($global->checkin_early_minutes ?? 60),
            /*
                وبعد كم دقيقةٍ من بدايتها يُقفَل — `null` = لا يُقفل.

                منفصلٌ عن `absent_after_minutes` عمداً: تلك تحكم على اليوم
                (متأخّر أم غائب)، وهذه تمنع الختمَ أصلاً. وكانت واحدةً
                تفعلهما، فيُمنع المتأخّرُ من تسجيل حضوره ولا يبقى له إلّا
                طلبُ تصحيح. انظر
                `2026_10_03_120000_add_checkin_close_after_minutes_to_attendance_settings`.
            */
            'checkin_close_after_minutes' => $global->checkin_close_after_minutes ?? null,
            // حسب الوردية أو دوامٌ مرن بعدد ساعات
            'work_mode' => $custom?->work_mode ?: 'shift',
            // الاستراحةُ المسموحة في اليوم — الزائدُ نقصٌ في الدوام
            'break_allowed_minutes' => (int) ($custom?->break_allowed_minutes ?? $global->break_allowed_minutes ?? 60),
            /*
                نافذةُ الانصراف العامّة: تُستعمل حين لا صفَّ ورديةٍ يحمل
                `logout_end_time` — وهي الحالُ في كل منشأةٍ بلا جدول ورديات.
            */
            'checkout_window_minutes' => $custom?->checkout_window_minutes
                ?? $global->checkout_window_minutes
                ?? null,
            'allow_second_shift' => (bool) ($global->allow_second_shift ?? false),
            'uses_route_tracking' => (bool) ($custom?->uses_route_tracking ?? false),
        ];

        /*
            قواعدُ الحضور (شاشة الصيغ): أزواجُ `مفتاح=قيمة` كانت تُحفظ ولا
            تُقرأ. تُدمَج هنا فوق الأساس — فهي سياسةٌ صريحة على ورديةٍ أو عامّة.
        */
        $shiftId = $date !== null
            ? $this->resolveShiftId($employee, $date)
            : ($employee->shift_id ? (int) $employee->shift_id : null);

        foreach (app(AttendanceRuleOverrides::class)->forEmployee($employee, $shiftId) as $key => $value) {
            $config[$key] = $value;
        }

        return $config;
    }

    private function getExpectedCheckIn(Employee $employee, Carbon $date, array $config): ?Carbon
    {
        /*
            ترتيبُ المصادر — الأخصّ أولاً:

            ١) ورديةٌ مخصَّصة بمدّة (`custom_shifts`) لهذا اليوم — إسنادٌ مؤقَّت
               يعلو على كل شيء.

            ٢) وقتٌ ثابت في إعداد الموظف الفردي — شاشة «إعدادات الموظفين».
               كان يُلغى بصمت إن بقي الموظف على وردية دائمة صباحية، فيُقاس
               على 08:00 وتُرفض نافذةُ الصباح مسحاً مسائياً مضبوطاً على 16:35.

            ٣) الوردية الدائمة (`employees.shift_id`).

            ٤) بدايةُ دوام المنشأة.
        */
        $customDay = $this->resolveCustomShiftDay($employee, $date);
        if ($customDay?->start_time) {
            return Carbon::parse($date->toDateString().' '.$customDay->start_time);
        }

        if ($employee->attendanceConfig?->expected_check_in) {
            return Carbon::parse($date->toDateString().' '.$employee->attendanceConfig->expected_check_in);
        }

        $time = $this->resolvePermanentShiftDay($employee, $date)?->start_time
            ?: $config['default_shift_start'];

        return Carbon::parse($date->toDateString().' '.$time);
    }

    private function getExpectedCheckOut(Employee $employee, Carbon $date, array $config, Carbon $expectedIn): ?Carbon
    {
        $customDay = $this->resolveCustomShiftDay($employee, $date);
        $time = $customDay?->end_time
            ?? $employee->attendanceConfig?->expected_check_out
            ?? $this->resolvePermanentShiftDay($employee, $date)?->end_time;

        if (! $time) {
            // احسب من ساعات العمل + بداية الوردية؛ عبورُ منتصف الليل هنا
            // تلقائيٌّ بطبيعة addHours() على لحظة زمنية.
            return $expectedIn->copy()->addHours((float) $config['work_hours_per_day']);
        }

        $expectedOut = Carbon::parse($date->toDateString().' '.$time);

        // وردية تعبر منتصف الليل (كـ 22:00 → 06:00): وقتُ الانصراف من
        // إعدادات الوردية يقع زمنياً قبل أو عند وقت الحضور المتوقَّع على
        // نفس اليوم، فهو فعلياً في اليوم التالي.
        if ($expectedOut->lte($expectedIn)) {
            $expectedOut->addDay();
        }

        return $expectedOut;
    }

    /**
     * صفُّ الوردية لنوافذ المسح فقط — لا يُرجَع ورديةٌ دائمة حين يغلب
     * إعدادُ الموظف الفردي على أوقاتها.
     */
    private function resolveShiftDayForPunchWindows(Employee $employee, Carbon $date): ?ShiftDay
    {
        $customDay = $this->resolveCustomShiftDay($employee, $date);
        if ($customDay) {
            return $customDay;
        }

        if ($employee->attendanceConfig?->expected_check_in) {
            return null;
        }

        return $this->resolvePermanentShiftDay($employee, $date);
    }

    /**
     * صفُّ وردية الموظف ليوم الأسبوع الذي يقع فيه `$date` تحديداً — لا
     * أوّل صفٍّ عشوائي. يفضّل الإسناد المخصَّص ثم الدائم (لمهلة التأخير وغيرها).
     */
    private function resolveShiftDay(Employee $employee, Carbon $date): ?ShiftDay
    {
        return $this->resolveCustomShiftDay($employee, $date)
            ?? $this->resolvePermanentShiftDay($employee, $date);
    }

    private function resolveCustomShiftDay(Employee $employee, Carbon $date): ?ShiftDay
    {
        $custom = CustomShift::query()
            ->whereDate('from_date', '<=', $date->toDateString())
            ->whereDate('to_date', '>=', $date->toDateString())
            ->whereHas('employees', fn ($q) => $q->where('employees.id', $employee->id))
            ->orderByDesc('is_main')
            ->orderByDesc('id')
            ->first();

        if (! $custom?->shift_id) {
            return null;
        }

        return $this->shiftDayForShiftId((int) $custom->shift_id, $date);
    }

    private function resolvePermanentShiftDay(Employee $employee, Carbon $date): ?ShiftDay
    {
        if (! $employee->shift_id) {
            return null;
        }

        return $this->shiftDayForShiftId((int) $employee->shift_id, $date);
    }

    private function shiftDayForShiftId(int $shiftId, Carbon $date): ?ShiftDay
    {
        $shift = Shift::with('days')->find($shiftId);
        $dayName = strtolower($date->format('l'));
        $shiftDay = $shift?->days?->firstWhere('day', $dayName);

        return $shiftDay && $shiftDay->working_day ? $shiftDay : null;
    }

    /**
     * أيُّ ورديةٍ تخصّ هذا الموظفَ في **هذا اليوم بعينه**؟
     *
     * الوردياتُ المخصَّصة (`custom_shifts`) أَولى من ورديته الدائمة: هي
     * إسنادٌ مؤقَّتٌ بمدّة، وهذا هو معناها.
     *
     * **وكانت لا تُقرأ إطلاقاً.** لا ذِكرَ لـ`CustomShift` في `app/Services`
     * كلِّه: الحسابُ يقرأ `employees.shift_id` وحدَه — الوردِيّةَ الدائمة.
     * فشاشةٌ كاملةٌ تُسنِد الورديّاتِ المتناوبة بمدّةٍ وتُدار وتُحذف ولا
     * أثرَ لها في حسابٍ واحد: من أُسنِد إليه الوردِيّةُ الليلية أسبوعاً
     * يُقاس على ورديّته النهارية، فيُقرأ متأخراً أربعَ عشرة ساعةً كلَّ
     * ليلة أو غائباً.
     *
     * والعضويةُ من الجدول الوسيط دائماً: الوضعان — «موظفون» و«قواعد» —
     * كلاهما يُنهي إلى `employees()->sync(...)` في المتحكّم، فلا حاجةَ
     * لإعادة تطبيق القواعد هنا (ولا يصحّ: القواعدُ تُقرأ لحظةَ الحفظ،
     * ومن نُقل قسمُه بعدها لا يُنتزع من جدولٍ أُسنِد إليه).
     *
     * وعند التداخل: «رئيسي» يعلو على «فرعي»، ثم الأحدثُ إسناداً.
     */
    private function resolveShiftId(Employee $employee, Carbon $date): ?int
    {
        $custom = CustomShift::query()
            ->whereDate('from_date', '<=', $date->toDateString())
            ->whereDate('to_date', '>=', $date->toDateString())
            ->whereHas('employees', fn ($q) => $q->where('employees.id', $employee->id))
            ->orderByDesc('is_main')
            ->orderByDesc('id')
            ->first();

        if ($custom?->shift_id) {
            return (int) $custom->shift_id;
        }

        return $employee->shift_id ? (int) $employee->shift_id : null;
    }

    private function isHoliday(Carbon $date, Employee $employee): bool
    {
        return Holiday::isHoliday($date, $employee);
    }

    /**
     * احصل على العطلة المنطبقة (لأخذ اسمها في الـ notes).
     */
    private function getHolidayName(Carbon $date, ?Employee $employee = null): ?string
    {
        return Holiday::findForDate($date, $employee)?->named;
    }

    /**
     * أهذا اليومُ عطلةٌ أسبوعيةٌ لهذا الموظف؟
     *
     * الورديةُ أولاً: أيامُ عملها تُحدَّد في شاشة الورديات
     * (`shift_days.working_day`)، وكانت تُتجاهَل — فتُقرأ العطلةُ من
     * الإعداد العام وحده. موظفٌ ورديتُه تعمل السبت يُكتب سبتُه عطلة،
     * ومن عطلةُ ورديته الأحد يُحسب غائباً فيه ويُخصم منه.
     *
     * ثم إعدادُ الموظف الفردي، ثم إعدادُ المنشأة — لمن لا وردية له.
     */
    private function isWeeklyOff(Carbon $date, Employee $employee): bool
    {
        $shiftId = $this->resolveShiftId($employee, $date);

        if ($shiftId) {
            $shift = Shift::with('days')->find($shiftId);
            $shiftDay = $shift?->days?->firstWhere('day', strtolower($date->format('l')));

            if ($shift && $shift->days->isNotEmpty()) {
                // يومٌ غيرُ مذكورٍ في الوردية، أو مذكورٌ بلا عمل = عطلة
                return ! $shiftDay || ! $shiftDay->working_day;
            }
        }

        $config = $this->resolveConfig($employee);
        $offDays = (array) $config['weekly_off_days'];
        $dayCode = substr(strtolower($date->format('D')), 0, 3); // sat, sun, mon, ...

        return in_array($dayCode, array_map('strtolower', $offDays), true);
    }

    /**
     * أللموظف إذنٌ من هذا النوع يشمل هذا اليوم؟
     *
     * `leave_type` يفرّق النوعين: إذنُ انصرافٍ مبكر لا يُبرّر تأخيرَ
     * الحضور، والعكس — وخلطُهما يرفع عقوبةً لم يُؤذَن فيها.
     */
    private function hasPermission(Employee $employee, Carbon $date, int $leaveType): bool
    {
        return LeavePermissions::where('employee_id', $employee->id)
            ->where('leave_type', $leaveType)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->exists();
    }

    private function getApprovedLeave(Employee $employee, Carbon $date): ?LeaveRequest
    {
        return LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();
    }

    private function markAsHoliday(Employee $employee, Carbon $date): array
    {
        $name = $this->getHolidayName($date, $employee) ?? 'عطلة رسمية';

        return $this->markDayOff($employee, $date, ['is_holiday' => true, 'is_weekly_off' => false], $name);
    }

    private function markAsWeeklyOff(Employee $employee, Carbon $date): array
    {
        return $this->markDayOff($employee, $date, ['is_holiday' => false, 'is_weekly_off' => true], 'عطلة أسبوعية');
    }

    /**
     * يومُ عطلة — رسميةً أو أسبوعية.
     *
     * ومن عمل فيه يُحسب له عملُه كلُّه إضافياً: كان اليومُ يُكتب «عطلة»
     * ويُطوى وقتُ دخوله وخروجه كأن لم يكن — موظفٌ عمل يومَ العيد ثماني
     * ساعات لا يظهر له في أيّ مكانٍ أنه عمل. وما عليه من خصمياتٍ في هذا
     * اليوم تُلغى: العطلةُ لا غيابَ فيها ولا تأخير.
     */
    private function markDayOff(Employee $employee, Carbon $date, array $flags, string $note): array
    {
        $day = AttendanceDays::firstOrNew([
            'employee_id' => $employee->id,
            'attendance_date' => $date->toDateString(),
        ]);

        $worked = 0;
        if ($day->check_in_time && $day->check_out_time) {
            $in = Carbon::parse($date->toDateString().' '.$day->check_in_time);
            $out = Carbon::parse($date->toDateString().' '.$day->check_out_time);
            if ($out->lt($in)) {
                $out->addDay();
            }
            $worked = (int) abs($out->diffInMinutes($in));
        }

        $day->fill($flags + [
            'status' => AttendanceDays::STATUS_HOLIDAY,
            'is_on_leave' => false,
            'leave_request_id' => null,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'overtime_minutes' => $worked,
            'auto_generated' => $day->exists ? $day->auto_generated : true,
            'processed_at' => now(),
            'notes' => $worked > 0 ? $note.' — عمل '.intdiv($worked, 60).':'.str_pad((string) ($worked % 60), 2, '0', STR_PAD_LEFT).' ساعة إضافية' : $note,
        ]);
        $day->save();

        $this->syncDayDeductions($day, $this->resolveConfig($employee, $date), []);

        return ['status' => 'holiday', 'deduction_created' => false, 'message' => $day->notes];
    }

    private function markAsLeave(Employee $employee, Carbon $date, LeaveRequest $leave): array
    {
        $day = AttendanceDays::updateOrCreate(
            ['employee_id' => $employee->id, 'attendance_date' => $date->toDateString()],
            [
                'status' => AttendanceDays::STATUS_LEAVE,
                'is_on_leave' => true,
                'leave_request_id' => $leave->id,
                'auto_generated' => true,
                'manual_override' => false,
                'processed_at' => now(),
                'notes' => 'إجازة معتمدة #'.$leave->id,
            ]
        );

        /*
            الإجازةُ المعتمدة تُسقط ما على اليوم من خصمياتٍ آلية: كان
            الغيابُ يُحسب ويُخصم، ثم تُعتمد الإجازةُ عن اليوم نفسِه فيبقى
            الخصمُ كما هو.
        */
        $this->syncDayDeductions($day, $this->resolveConfig($employee, $date), []);

        return ['status' => 'leave', 'deduction_created' => false, 'message' => 'في إجازة'];
    }

    /**
     * الأجرُ الذي يُقاس عليه خصمُ اليوم — من القسيمة أوّلاً لا من العقد.
     *
     * **ما كان**
     *
     *     $contract = $employee?->contracts()->orderByDesc('start_date')->first();
     *     $basicSalary = (float) ($contract?->basic_salary ?? 0);
     *
     * والخصمُ يُقتطع من راتبٍ تدفعه `salary_slips.basic_amount`، لا من
     * `contracts.basic_salary`. والعمودان مستقلّان ويجوز اختلافُهما: تُعدَّل
     * القسيمةُ بزيادةٍ ولا يُعدَّل العقد، فيُخصَم يومُ الغياب على الأجر
     * القديم بينما يُصرف الراتبُ على الجديد — ولا يطابق أحدُهما الآخر في
     * أيِّ تقرير.
     *
     * **ما صار**
     *
     * القسيمةُ النشطة للموظف هي المرجع؛ فإن لم تكن له قسيمة — وهو وارد قبل
     * إنشائها — فالعقدُ احتياطاً، فلا يسقط الخصمُ إلى صفر لغياب قسيمة.
     *
     * والقسيمةُ تُختار بالأحدث سريانَ ابتداءٍ بين النشطة والمعتمَدة: هما
     * الحالتان اللتان يُصرف عنهما، كما يقرؤهما `ProcessPayrollRuns`.
     */
    private function dailyDeductionWageBase(AttendanceDays $day): float
    {
        $employee = $day->employee;

        if (! $employee) {
            return 0.0;
        }

        $slipWage = (float) SalarySlip::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [SalarySlip::STATUS_ACTIVE, SalarySlip::STATUS_APPROVED])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->value('basic_amount');

        if ($slipWage > 0) {
            return $slipWage;
        }

        $contract = $employee->contracts()->orderByDesc('start_date')->first();

        return (float) ($contract?->basic_salary ?? 0);
    }

    /**
     * تاريخُ نهاية إجازةٍ بعد N يوماً عملياً (تُتخطّى العطل الأسبوعية والرسمية).
     */
    public function endDateForWorkingDays(Employee $employee, Carbon $start, int $workingDays): Carbon
    {
        $workingDays = max(1, $workingDays);
        $cursor = $start->copy()->startOfDay();
        $counted = 0;
        $guard = 0;

        while ($counted < $workingDays && $guard < 3660) {
            if (! $this->isNonWorkingDay($employee, $cursor)) {
                $counted++;
                if ($counted === $workingDays) {
                    return $cursor->copy();
                }
            }
            $cursor->addDay();
            $guard++;
        }

        return $cursor;
    }

    /** هل اليوم عطلة أسبوعية أو رسمية لهذا الموظف؟ */
    public function isNonWorkingDay(Employee $employee, Carbon $date): bool
    {
        return $this->isHoliday($date, $employee) || $this->isWeeklyOff($date, $employee);
    }

    /**
     * علامةُ الخصميات التي يُنشئها هذا المعالج — بها تُعرف عند المزامنة.
     *
     * عامّةٌ لأنّ اعتمادَ طلب تصحيح الحضور يحتاجها ليُلغي خصميّاتِ اليوم
     * الآليّةَ كلَّها — لا المربوطةَ بـ`attendance_days.deduction_id` وحدَها
     * ({@see \Modules\HR\Http\Controllers\Attendance\AttendanceCorrectionApprovalController::staleDeductionsFor()}).
     */
    public const AUTO_DEDUCTION_NOTE = 'تم إنشاؤها تلقائياً من نظام الحضور';

    /**
     * يُزامن خصمياتِ اليوم الآلية مع ما يستحقّه اليوم الآن.
     *
     * **ما كان** (`createDeductionIfEnabled`): يُضيف ولا يُلغي.
     *   · أولُ خصميةٍ تُربط باليوم تبقى: من خُصم عليه «تأخير» صباحاً ثم
     *     صار يومُه «نصف يوم» بقي عليه خصمُ التأخير وحده.
     *   · خصميةُ الانصراف المبكر لا تُربط باليوم، فتُنشأ من جديدٍ مع كل
     *     معالجة — كلِّ ختمٍ وكلِّ ليلة — حتى تتكرّر على اليوم الواحد.
     *   · تصحيحٌ أو إجازةٌ أو عطلةٌ تُضاف بعد الخصم لا ترفعه.
     *
     * **ما صار:** لكل يومٍ خصميةٌ واحدةٌ على الأكثر من كل نوع. ما لم يعد
     * مستحقاً — أو تغيّر مبلغُه — يُلغى (والإلغاءُ يعكس قيده عبر
     * `Deduction::updated`)، وما استُحقّ ولم يوجد يُنشأ. وما اقتُطع من
     * راتبٍ مصروف لا يُمَسّ: تسويتُه قرارٌ في الرواتب لا هنا.
     *
     * @param  array<string, bool>  $wanted  أنواعٌ من absent|half_day|late|early_leave
     * @return bool هل أُنشئت خصميةٌ جديدة
     */
    private function syncDayDeductions(AttendanceDays $day, array $config, array $wanted): bool
    {
        $typeMap = [
            'absent' => Deduction::TYPE_ABSENCE,
            'half_day' => Deduction::TYPE_HALF_DAY,
            'late' => Deduction::TYPE_LATE,
            'early_leave' => Deduction::TYPE_EARLY_LEAVE,
        ];

        $target = [];
        if ($config['auto_deduction_enabled']) {
            foreach ($wanted as $type => $on) {
                if (! $on) {
                    continue;
                }
                $amount = round($this->deductionAmount($day, $config, $type), 2);
                if ($amount > 0) {
                    $target[$typeMap[$type]] = ['type' => $type, 'amount' => $amount];
                }
            }
        }

        $existing = Deduction::query()
            ->where('employee_id', $day->employee_id)
            ->whereDate('date', $day->attendance_date->toDateString())
            ->where('notes', self::AUTO_DEDUCTION_NOTE)
            ->whereIn('deduction_type', array_values($typeMap))
            ->whereIn('status', [Deduction::STATUS_PENDING, Deduction::STATUS_APPROVED])
            ->get();

        $actor = Auth::id();
        $created = false;

        foreach ($existing as $deduction) {
            $keep = isset($target[$deduction->deduction_type])
                && abs((float) $deduction->amount - $target[$deduction->deduction_type]['amount']) < 0.01
                && (float) $deduction->settled_amount <= 0;

            if ($keep) {
                unset($target[$deduction->deduction_type]);

                continue;
            }

            if ((float) $deduction->settled_amount > 0) {
                continue; // اقتُطع من راتبٍ مصروف — لا يُلغى من هنا
            }

            $deduction->update([
                'status' => Deduction::STATUS_CANCELLED,
                'cancelled_by_user' => $actor,
                'cancelled_at' => now(),
                'cancellation_reason' => 'أُعيد احتساب يوم '.$day->attendance_date->toDateString().' — لم تعد مستحقة بمبلغها',
            ]);

            DeductionHistory::create([
                'deduction_id' => $deduction->id,
                'action' => 'cancelled',
                'user_id' => $actor,
                'amount' => $deduction->amount,
                'description' => 'إلغاء تلقائي عند إعادة احتساب الحضور',
                'notes' => 'تاريخ: '.$day->attendance_date->toDateString(),
            ]);
        }

        /*
            خصميةٌ رفضها المدير لليوم نفسِه ونوعِه قرارٌ نهائي — كانت إعادةُ
            الاحتساب (الختم التالي، الكرون الليلي) تُنشئها من جديد بعد رفضها.
        */
        if ($target !== []) {
            $rejected = Deduction::query()
                ->where('employee_id', $day->employee_id)
                ->whereDate('date', $day->attendance_date->toDateString())
                ->where('notes', self::AUTO_DEDUCTION_NOTE)
                ->where('status', Deduction::STATUS_REJECTED)
                ->pluck('deduction_type')
                ->all();
            $target = array_diff_key($target, array_flip($rejected));
        }

        $manual = ($config['deduction_approval'] ?? 'auto') === 'manual';

        foreach ($target as $deductionType => $item) {
            $deduction = Deduction::create([
                'employee_id' => $day->employee_id,
                'deduction_type' => $deductionType,
                'amount' => $item['amount'],
                'settled_amount' => 0,
                // يدويّ: معلّقةٌ في شاشة الخصميات ولا قيدَ حتى تُعتمد
                'status' => $manual ? Deduction::STATUS_PENDING : Deduction::STATUS_APPROVED,
                'date' => $day->attendance_date->toDateString(),
                'reason' => $this->deductionReason($day, $item['type']),
                'notes' => self::AUTO_DEDUCTION_NOTE,
                'created_by_user' => $actor,
                'approved_by_user' => $manual ? null : $actor,
                'approved_at' => $manual ? null : now(),
            ]);

            DeductionHistory::create([
                'deduction_id' => $deduction->id,
                'action' => 'created',
                'user_id' => $actor,
                'amount' => $item['amount'],
                'description' => $manual
                    ? 'إنشاء من نظام الحضور — بانتظار اعتماد المدير'
                    : 'إنشاء واعتماد تلقائي من نظام الحضور',
                'notes' => 'تاريخ: '.$day->attendance_date->toDateString(),
            ]);

            $created = true;
        }

        // الخصميةُ الأساسية (غير الانصراف المبكر) تُربط باليوم للعرض
        $primary = Deduction::query()
            ->where('employee_id', $day->employee_id)
            ->whereDate('date', $day->attendance_date->toDateString())
            ->where('notes', self::AUTO_DEDUCTION_NOTE)
            ->whereIn('deduction_type', [Deduction::TYPE_ABSENCE, Deduction::TYPE_HALF_DAY, Deduction::TYPE_LATE])
            ->whereNotIn('status', [Deduction::STATUS_CANCELLED, Deduction::STATUS_REJECTED])
            ->latest('id')
            ->value('id');

        if ((int) $day->deduction_id !== (int) $primary) {
            $day->deduction_id = $primary;
            $day->save();
        }

        return $created;
    }

    /**
     * مبلغُ خصميةِ نوعٍ في يومٍ بعينه.
     *
     * · «مبلغ ثابت»: المبلغُ المكتوب في الإعداد لكل حالة.
     * · «لكل دقيقة»: مبلغُ الدقيقة × الدقائق المحسوبة.
     * · «يومية من الراتب»: الغيابُ يوميةٌ كاملة، ونصفُ اليوم نصفُها،
     *   والتأخيرُ والانصرافُ المبكر **بالدقيقة من اليومية**. كانت هذه
     *   الطريقةُ تخصم يوميةً كاملةً على أيّ تأخيرٍ بعد السماح — دقيقةٌ
     *   واحدة = يومُ عمل — ولا تعمل أصلاً إن لم يُكتب مبلغٌ لا تستعمله.
     *
     * والدقائقُ المحسوبة هي ما **بعد** السماح: من تأخّر عشرين دقيقةً
     * والسماحُ خمسَ عشرة يُخصم منه خمس.
     */
    private function deductionAmount(AttendanceDays $day, array $config, string $type): float
    {
        $lateChargeable = max(0, (int) $day->late_minutes - (int) $config['late_grace_minutes']);
        $earlyChargeable = max(0, (int) $day->early_leave_minutes - (int) ($config['early_leave_threshold_minutes'] ?? 0));
        $minutes = match ($type) {
            'late' => $lateChargeable,
            'early_leave' => $earlyChargeable,
            default => 0,
        };

        if ($config['deduction_calculation_type'] === 'daily_salary') {
            $daily = $this->dailyDeductionWageBase($day) / 30;
            $shiftMinutes = max(1, (int) round(((float) ($config['work_hours_per_day'] ?? 8)) * 60));

            return match ($type) {
                'absent' => $daily,
                'half_day' => $daily / 2,
                default => $daily / $shiftMinutes * $minutes,
            };
        }

        $absence = (float) $config['absence_deduction_amount'];
        $late = (float) $config['late_deduction_amount'];
        $early = (float) ($config['early_leave_deduction_amount'] ?? $late);

        if ($config['deduction_calculation_type'] === 'per_minute') {
            return match ($type) {
                'absent' => $absence,
                'half_day' => $absence / 2,
                'late' => $late * $minutes,
                'early_leave' => $early * $minutes,
            };
        }

        return match ($type) {
            'absent' => $absence,
            'half_day' => $absence / 2,
            'late' => $minutes > 0 ? $late : 0,
            'early_leave' => $minutes > 0 ? $early : 0,
        };
    }

    private function deductionReason(AttendanceDays $day, string $type): string
    {
        $date = $day->attendance_date->toDateString();

        return match ($type) {
            'absent' => $day->check_in_time
                ? 'تأخر كثيراً (يُحتسب غياباً) — حضور '.$day->check_in_time.' يوم '.$date
                : 'غياب يوم '.$date,
            'half_day' => 'نصف يوم — '.$date.($day->early_leave_minutes ? " (انصراف مبكر {$day->early_leave_minutes} د)" : ''),
            'early_leave' => "انصراف مبكر {$day->early_leave_minutes} دقيقة - ".$date,
            default => "تأخير {$day->late_minutes} دقيقة - ".$date,
        };
    }
}
