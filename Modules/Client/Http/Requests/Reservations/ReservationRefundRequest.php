<?php

namespace Modules\Client\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ردُّ دفعةٍ للعميل — صلاحيةٌ مستقلّة عن القبض.
 *
 * القبضُ يُدخل نقداً والردُّ يُخرجه، ومن يُصرَّح له بالأوّل ليس بالضرورة
 * من يُصرَّح له بالثاني — وهي قاعدةُ سندَي القبض والصرف نفسُها.
 *
 * و`payment_id` يربط الردَّ بدفعته الأصلية؛ حدُّه (ما بقي منها بعد ما
 * رُدّ) تفحصه الخدمةُ خلف قفل الحجز لا هنا.
 */
class ReservationRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('reservations.refund_payment');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_id' => ['nullable', 'integer', 'exists:reservation_payments,id'],
            'treasury_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => __('reservations.attributes.amount'),
            'payment_id' => __('reservations.attributes.payment_id'),
            'treasury_id' => __('reservations.attributes.treasury_id'),
            'date' => __('reservations.attributes.date'),
            'reason' => __('reservations.attributes.reason'),
        ];
    }
}
