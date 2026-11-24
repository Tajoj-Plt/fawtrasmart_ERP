<?php

namespace Modules\Client\Http\Requests\Reservations;

use App\Models\Client;
use App\Services\Clients\ClientVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Client\Reservations\Enums\ReservationStatus;
use Modules\Client\Reservations\Models\ReservationSetting;

/**
 * التحقّق من نموذج إنشاء الحجز.
 *
 * ═══════════ ما يُتحقَّق هنا وما لا يُتحقَّق ═══════════
 *
 * هنا **شكلُ المُدخَل**: أموجودٌ؟ أرقمٌ هو؟ أيشير إلى صفٍّ قائم؟
 *
 * أمّا **قواعدُ العمل** — أالموعدُ متاح؟ أالموظّفُ مشغول؟ أاليومُ عطلة؟ —
 * فليست هنا بل في `ReservationAvailabilityService`، لأنّها تُطبَّق على
 * الحجز القادم من الـAPI ونقطة البيع كذلك، ولا يمرّ أيٌّ منها بهذا الطلب.
 *
 * ═══════════ والإلزامُ يُقرأ من الإعدادات ═══════════
 *
 * `require_client` و`require_mobile` و`require_email` مفاتيحُ في شاشة
 * الإعدادات — فتُقرأ منها لا تُكتب `required` في الشيفرة.
 */
class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('reservations.create');
    }

    public function rules(): array
    {
        $settings = ReservationSetting::current();

        return [
            'client_id' => [
                $settings->require_client ? 'required' : 'nullable',
                'integer', 'exists:clients,id',
            ],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            // الخدمةُ منتجٌ خدمي؛ ونوعُه يُفحص في `ServiceProductCatalog::resolve`
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            // للتوافق مع النداءات القديمة وحدها
            'service_id' => ['nullable', 'integer', 'exists:reservation_services,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],

            'reservation_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:1440'],

            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['amount', 'percentage'])],
            'tax_id' => ['nullable', 'integer', 'exists:tax_sittings,id'],

            /*
                لا قبضَ في الحجز — فلا `paid_amount` ولا خزينةَ ولا طريقةَ دفع.
                المالُ على فاتورة المبيعات («تحويل إلى فاتورة»).

                والحالةُ عند الإنشاء: مسودةٌ أو انتظار، والتأكيدُ لمن يملك
                `reservations.confirm` — وحينها يمرّ التأكيدُ بالانتقال الحقيقيّ
                في الخدمة. ما بعدها («حضر»، «مكتمل») لا يُكتب من نموذج الإنشاء.
            */
            'status' => ['nullable', Rule::in(array_map(
                fn (ReservationStatus $s) => $s->value,
                ReservationStatus::storableAtCreate(hasPermission('reservations.confirm'))
            ))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', Rule::in(['back_office', 'online', 'pos', 'api'])],
        ];
    }

    public function attributes(): array
    {
        return collect([
            'client_id', 'employee_id', 'service_id', 'branch_id', 'reservation_date',
            'start_time', 'end_time', 'duration', 'unit_price', 'discount_value',
            'tax_id', 'notes',
        ])->mapWithKeys(fn ($key) => [$key => __('reservations.attributes.'.$key)])->all();
    }

    /**
     * ما لا تقوله القواعدُ وحدها:
     *
     *   • العميلُ المختار يجب أن يكون **في نطاق رؤية** المستخدم — القائمةُ
     *     مقصوصةٌ به، والقائمةُ ليست حارساً: طلبٌ معدَّل يحمل معرّفَ عميلٍ لا
     *     يراه صاحبُه يُردّ هنا.
     *   • إلزامُ الجوّال والبريد يُفحَص على **سجلّ العميل** لا على حقلٍ في
     *     النموذج: النموذجُ يختار عميلاً قائماً، والبيانُ الناقصُ في سجلّه.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $settings = ReservationSetting::current();
            $clientId = $this->input('client_id');

            if (! $clientId) {
                return;
            }

            $visible = ClientVisibilityScope::applyDocumentSelectScope(
                Client::query(), null, null, $this->keptClientId()
            );

            if (! $visible->whereKey($clientId)->exists()) {
                $validator->errors()->add('client_id', __('reservations.more.client_out_of_scope'));

                return;
            }

            if (! $settings->require_mobile && ! $settings->require_email) {
                return;
            }

            $client = Client::find($clientId);

            if (! $client) {
                return;
            }

            if ($settings->require_mobile && blank($client->phone) && blank($client->mobile ?? null)) {
                $validator->errors()->add('client_id', __('reservations.errors.client_mobile_required'));
            }

            if ($settings->require_email && blank($client->email)) {
                $validator->errors()->add('client_id', __('reservations.errors.client_email_required'));
            }
        });
    }

    /**
     * عميلٌ يبقى مقبولاً ولو خرج من النطاق — لا شيء عند الإنشاء؛ والتعديلُ
     * يُبقي عميلَ الحجز المحفوظ (انظر `UpdateReservationRequest`).
     */
    protected function keptClientId(): ?int
    {
        return null;
    }
}
