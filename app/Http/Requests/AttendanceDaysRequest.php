<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceDaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // نفسُ اليوم لا يحمل إلا صفاً واحداً لكل موظف — يحرسه أيضاً قيدٌ
        // فريد في القاعدة؛ هذا يمنع الخطأ الخام ويعطي رسالةً مفهومة.
        $uniqueAttendanceDate = Rule::unique('attendance_days', 'attendance_date')
            ->where(fn ($query) => $query->where('employee_id', $this->input('employee_id')));

        if ($this->route('id')) {
            $uniqueAttendanceDate = $uniqueAttendanceDate->ignore($this->route('id'));
        }

        return [
            'employee_id' => 'required|exists:employees,id',
            'attendance_date' => ['required', 'date', $uniqueAttendanceDate],
            'status' => 'required|in:present,absent,late',

            'absence_type' => 'nullable|in:1,2|required_if:status,absent',
            'absence_balance' => 'nullable|integer|min:0|required_if:status,absent',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages()
    {
        return [
            'employee_id.required' => 'يجب اختيار الموظف.',
            'employee_id.exists' => 'الموظف المحدد غير موجود.',
            'attendance_date.required' => 'يجب تحديد تاريخ الحضور.',
            'attendance_date.date' => 'التاريخ غير صالح.',
            'attendance_date.unique' => 'يوجد سجل حضور لهذا الموظف في هذا التاريخ مسبقاً.',
            'status.required' => 'يجب اختيار الحالة.',
            'status.in' => 'الحالة المختارة غير صحيحة.',

            'check_in_time.required_if' => 'تسجيل الدخول مطلوب عندما تكون الحالة حاضر.',
            'check_in_time.date_format' => 'تسجيل الدخول يجب أن يكون بصيغة الوقت الصحيحة.',
            'check_out_time.required_if' => 'تسجيل الخروج مطلوب عندما تكون الحالة حاضر.',
            'check_out_time.date_format' => 'تسجيل الخروج يجب أن يكون بصيغة الوقت الصحيحة.',
            'absence_type.required_if' => 'نوع الإجازة مطلوب عندما تكون الحالة إجازة.',
            'absence_balance.required_if' => 'رصيد الإجازة مطلوب عندما تكون الحالة إجازة.',
        ];
    }
}
