<?php

namespace Modules\Client\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * إعادةُ جدولة — الموعدُ الجديد، والموظّفُ الجديد إن تغيّر.
 *
 * ولا يُفحَص هنا حدُّ المحاولات ولا مهلةُ إعادة الجدولة ولا أهليّةُ الموظّف:
 * كلُّها قواعدُ عملٍ في الخدمات، يمرّ عليها الطلبُ القادم من الـAPI كما
 * يمرّ القادمُ من الشاشة.
 */
class RescheduleReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('reservations.reschedule');
    }

    public function rules(): array
    {
        return [
            'reservation_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reservation_date' => __('reservations.attributes.new_date'),
            'start_time' => __('reservations.attributes.new_start'),
            'end_time' => __('reservations.attributes.new_end'),
            'employee_id' => __('reservations.attributes.employee_id'),
            'note' => __('reservations.attributes.reason'),
        ];
    }
}
