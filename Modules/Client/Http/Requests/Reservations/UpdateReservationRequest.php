<?php

namespace Modules\Client\Http\Requests\Reservations;

/**
 * تعديلُ حجز — القواعدُ نفسُها إلّا أن التاريخ والوقت يصيران اختياريَّين.
 *
 * الوراثةُ متعمّدة: نموذجان متطابقان بقاعدتَين متوازيتين يفترقان بعد أوّل
 * حقلٍ يُضاف إلى أحدهما.
 *
 * وما يتغيّر هنا: الصلاحيةُ (`edit` لا `create`)، وسقوطُ `required` لأنّ
 * التعديلَ الجزئي يُبقي ما لم يُرسَل على حاله، وسقوطُ الحالة — تغييرُها
 * انتقالٌ له زرُّه وصلاحيتُه في شاشة الحجز.
 */
class UpdateReservationRequest extends StoreReservationRequest
{
    public function authorize(): bool
    {
        return hasPermission('reservations.edit');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules['reservation_date'] = ['sometimes', 'date'];
        $rules['start_time'] = ['sometimes', 'date_format:H:i,H:i:s'];

        // العميلُ لا يُفرَض في التعديل: الحجزُ محفوظٌ به أصلاً
        $rules['client_id'] = ['sometimes', 'integer', 'exists:clients,id'];

        unset($rules['status']);

        return $rules;
    }

    /**
     * عميلُ الحجز المفتوح يبقى مقبولاً ولو ضاق نطاقُ المستخدم بعد حفظه —
     * وإلّا رُدّ تعديلُ ملاحظةٍ لأنّ النموذج أعاد `client_id` كما هو.
     */
    protected function keptClientId(): ?int
    {
        $id = $this->route('reservation');

        return $id ? (int) \Modules\Client\Reservations\Models\Reservation::whereKey($id)->value('client_id') : null;
    }
}
