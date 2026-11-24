<?php

namespace Modules\Client\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * إلغاءُ حجز — والسببُ إلزاميّ.
 *
 * **ولمَ يُلزَم السبب؟** لأن الإلغاء نزاعٌ محتمل مع العميل: يُسأل بعده
 * «لماذا أُلغي حجزي؟»، وسجلٌّ يقول «أُلغي» بلا سبب لا يجيب. وهو يُكتب في
 * خطّ حياة الحجز فيبقى مقروءاً بعد سنة.
 */
class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('reservations.cancel');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'charge_fee' => ['nullable', 'boolean'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'treasury_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => __('reservations.attributes.cancel_reason'),
            'charge_fee' => __('reservations.attributes.charge_fee'),
            'refund_amount' => __('reservations.attributes.refund_amount'),
            'treasury_id' => __('reservations.attributes.treasury_id'),
        ];
    }
}
