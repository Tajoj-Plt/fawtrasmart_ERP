<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّقُ طلب تصحيح الحضور — نفس حقول {@see AttendanceDaysRequest} تحت
 * أسماء `requested_*`، لأن الاعتماد لاحقاً يطبّق هذه القيم بنفس منطق
 * شاشة التعديل اليدوي (الحالة الكاملة، لا حقلاً واحداً مبعثراً).
 */
class AttendanceCorrectionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // الصلاحية تحرس المسار نفسه (attendance_corrections.create)
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'attendance_date' => 'required|date',
            'reason' => 'required|string|max:1000',

            'requested_status' => 'required|in:present,absent,late',
            'requested_start_shift' => 'nullable|date_format:H:i',
            'requested_end_shift' => 'nullable|date_format:H:i',
            'requested_check_in_time' => 'nullable|date_format:H:i|required_if:requested_status,present',
            'requested_check_out_time' => 'nullable|date_format:H:i|required_if:requested_status,present',
            'requested_absence_type' => 'nullable|in:1,2|required_if:requested_status,absent',
            'requested_absence_balance' => 'nullable|integer|min:0|required_if:requested_status,absent',
            'requested_notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'يجب اختيار الموظف.',
            'employee_id.exists' => 'الموظف المحدد غير موجود.',
            'attendance_date.required' => 'يجب تحديد تاريخ الحضور المطلوب تصحيحه.',
            'reason.required' => 'يجب توضيح سبب طلب التصحيح.',
            'requested_status.required' => 'يجب اختيار الحالة المطلوبة.',
            'requested_status.in' => 'الحالة المختارة غير صحيحة.',
            'requested_check_in_time.required_if' => 'وقت الحضور المطلوب مطلوب عندما تكون الحالة حاضر.',
            'requested_check_out_time.required_if' => 'وقت الانصراف المطلوب مطلوب عندما تكون الحالة حاضر.',
            'requested_absence_type.required_if' => 'نوع الإجازة مطلوب عندما تكون الحالة إجازة.',
            'requested_absence_balance.required_if' => 'رصيد الإجازة مطلوب عندما تكون الحالة إجازة.',
        ];
    }
}
