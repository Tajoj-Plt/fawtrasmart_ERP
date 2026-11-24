<?php

namespace Modules\HR\Http\Controllers\Concerns;

use App\Http\Requests\AttendanceCorrectionRequestRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceDays;
use App\Models\Employee;
use App\Models\Log as ModelsLog;
use App\Services\AttendanceProcessorService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * منطقُ تقديم طلب تصحيح حضور — مشتركٌ بين شاشة الطلب المخصَّصة
 * (`AttendanceCorrectionRequestController::store`) وشاشة تعديل يوم
 * الحضور القديمة (`AttendanceDaysController::update`)، بعد أن صارت
 * الأخيرة تُقدِّم طلباً لا تكتب مباشرةً.
 *
 * لقطةُ `current_*` تُبنى من آخر ما في `attendance_days` لنفس
 * (employee_id, attendance_date) **وقت التقديم** — لا من أيّ صفٍّ بعينه
 * قد يكون المستخدم بدأ منه، لأن نموذج التعديل يسمح بتغيير الموظف
 * والتاريخ أنفسِهما.
 */
trait SubmitsAttendanceCorrectionRequests
{
    protected function submitCorrectionRequest(AttendanceCorrectionRequestRequest $request, string $logDescription = 'تم تقديم طلب تصحيح حضور'): AttendanceCorrectionRequest
    {
        $this->authorizeEmployeeScope($request->employee_id);

        $duplicate = AttendanceCorrectionRequest::where('employee_id', $request->employee_id)
            ->whereDate('attendance_date', $request->attendance_date)
            ->pending()
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'attendance_date' => 'يوجد طلب تصحيح معلَّق بالفعل لهذا الموظف في هذا التاريخ — انتظر اعتماده أو رفضه أولاً.',
            ]);
        }

        $current = AttendanceDays::where('employee_id', $request->employee_id)
            ->whereDate('attendance_date', $request->attendance_date)
            ->first();

        $this->ensureDayNeedsCorrection($request, $current);

        $correction = AttendanceCorrectionRequest::create([
            'employee_id' => $request->employee_id,
            'attendance_date' => $request->attendance_date,

            'requested_status' => $request->requested_status,
            'requested_start_shift' => $request->requested_start_shift,
            'requested_end_shift' => $request->requested_end_shift,
            'requested_check_in_time' => $request->requested_check_in_time,
            'requested_check_out_time' => $request->requested_check_out_time,
            'requested_absence_type' => $request->requested_absence_type,
            'requested_absence_balance' => $request->requested_absence_balance,
            'requested_notes' => $request->requested_notes,

            'current_status' => $current?->status,
            'current_start_shift' => $current?->start_shift,
            'current_end_shift' => $current?->end_shift,
            'current_check_in_time' => $current?->check_in_time,
            'current_check_out_time' => $current?->check_out_time,
            'current_absence_type' => $current?->absence_type,
            'current_absence_balance' => $current?->absence_balance,

            'reason' => $request->reason,
            'status' => AttendanceCorrectionRequest::STATUS_PENDING,
            'requested_by' => auth()->id(),
        ]);

        ModelsLog::create([
            'type' => 'attendance_correction_request_log',
            'type_id' => $correction->id,
            'type_log' => 'log',
            'description' => $logDescription,
            'created_by' => auth()->id(),
        ]);

        return $correction;
    }

    /**
     * التصحيحُ لِيومٍ انتهى وفيه ما يُصحَّح — لا غير.
     *
     * كان يُقبل طلبٌ لأي تاريخ: للغد، ولليوم والموظفُ ما زال في دوامه،
     * وليومٍ داوم فيه كاملاً في وقته، وليوم عطلة أو إجازة. فيعتمده المدير
     * فيتثبّت اليومُ بقرارٍ يدوي ويقفل حسابَه أمام الختم والكرون.
     */
    protected function ensureDayNeedsCorrection(AttendanceCorrectionRequestRequest $request, ?AttendanceDays $current): void
    {
        $date = Carbon::parse($request->attendance_date)->startOfDay();
        $fail = fn (string $message) => throw ValidationException::withMessages(['attendance_date' => $message]);

        if ($date->isFuture()) {
            $fail('لا يُطلب تصحيحٌ ليومٍ لم يأتِ بعد.');
        }

        $employee = Employee::find($request->employee_id);
        $day = app(AttendanceProcessorService::class)->describeDay($employee, $date);

        if ($day['is_holiday'] || $day['is_weekly_off']) {
            $fail('هذا اليوم عطلة للموظف — لا دوام فيه يُسجَّل أو يُصحَّح.');
        }

        if ($day['leave']) {
            $fail('للموظف إجازة معتمدة في هذا اليوم — عدّل الإجازة نفسها بدل طلب تصحيح.');
        }

        // اليومُ الجاري: لا تصحيحَ قبل انتهاء الوردية — يسجّل الموظفُ حضوره وانصرافه كالمعتاد
        if ($date->isToday()) {
            $shiftEnd = $day['expected_check_out'];

            if ($current?->check_in_time && ! $current->check_out_time) {
                $fail('الموظف حاضر الآن ولم ينصرف بعد — يسجّل انصرافه كالمعتاد. يُطلب التصحيح بعد انتهاء اليوم إن بقي خطأ.');
            }

            if ($shiftEnd && now()->lt($shiftEnd)) {
                $fail('دوام اليوم لم ينتهِ بعد (ينتهي '.$shiftEnd->format('H:i').') — سجّل الحضور والانصراف كالمعتاد، ويُطلب التصحيح بعد انتهاء الدوام.');
            }
        }

        // يومٌ داوم فيه كاملاً وفي وقته: لا شيء يُصحَّح
        $complete = $current
            && $current->check_in_time && $current->check_out_time
            && $current->status === AttendanceDays::STATUS_PRESENT
            && (int) $current->late_minutes === 0
            && (int) $current->early_leave_minutes === 0;

        if ($complete) {
            $fail('الموظف داوم هذا اليوم كاملاً في وقته — لا يوجد ما يُصحَّح.');
        }

        if ($request->requested_status === AttendanceDays::STATUS_PRESENT
            && $request->requested_check_in_time && $request->requested_check_out_time
            && $request->requested_check_out_time <= $request->requested_check_in_time) {
            $expectedIn = $day['expected_check_in'];
            $expectedOut = $day['expected_check_out'];
            $overnight = $expectedIn && $expectedOut && $expectedOut->toDateString() !== $expectedIn->toDateString();

            if (! $overnight) {
                throw ValidationException::withMessages([
                    'requested_check_out_time' => 'وقت الانصراف يجب أن يكون بعد وقت الحضور.',
                ]);
            }
        }
    }
}
