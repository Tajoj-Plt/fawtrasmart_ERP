<?php

namespace Modules\Integration\Http\Requests\Clients;

use App\Helpers\ClientSettingsHelper;
use App\Models\Client;
use App\Models\Contact;
use App\Rules\SaudiPhone;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\Integration\Services\Clients\ClientForm;

/**
 * بيانات «إضافة/تعديل عميل» من التطبيق — قواعد `Modules\Client\Http\Requests\ClientRequest`
 * نفسها (كلّ ما تقرؤه من «إعدادات وحدات العملاء») مضافاً إليها ما يتحقّق
 * منه `store()/update()` في الموقع داخل المتحكّم: عقدة «تابع لـ» ضمن النطاق،
 * الإحداثيات، والموظفون المسؤولون.
 *
 * `code` ليس هنا: رقم العميل يصدره الخادم وحده ولا يُقبل من الطلب.
 */
class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * الأرقام تُطبَّع قبل التحقّق كما في الموقع — انظر {@see SaudiPhone}.
     */
    protected function prepareForValidation(): void
    {
        SaudiPhone::normalizeRequest($this, ['phone', 'mobile'], ['contacts' => ['phone', 'mobile']]);
    }

    public function rules(): array
    {
        $client = $this->editedClient();
        $isUpdate = $client !== null;
        $maxKb = ClientSettingsHelper::maxUploadSizeKb();

        // صيغةُ الرقم السعودي؛ أرقامُ العميل المحفوظة قبلها تُقبل ما لم تتغيّر
        $saudiPhone = new SaudiPhone($client
            ? collect([$client->phone, $client->mobile])
                ->merge(Contact::where('client_id', $client->id)->get(['phone', 'mobile'])
                    ->flatMap(fn ($contact) => [$contact->phone, $contact->mobile]))
                ->filter()->all()
            : null);

        $phone = [ClientSettingsHelper::clientRequirePhone() ? 'required' : 'nullable', 'string', 'max:20', $saudiPhone];
        if (ClientSettingsHelper::clientUniquePhone()) {
            $phone[] = Rule::unique('clients', 'phone')->ignore($client?->id);
        }

        $typeRule = match (ClientSettingsHelper::clientTypeScope()) {
            'individual' => 'nullable|integer|in:1',
            'commercial' => 'nullable|integer|in:2',
            default => 'nullable|integer|in:1,2',
        };

        $location = ClientSettingsHelper::isLocationRequired() ? 'required' : 'nullable';

        $rules = [
            'client_tree_branch_id' => ['required', 'integer', Rule::in(ClientForm::selectableNodeIds($this->user(), $client))],
            'trade_name' => [ClientSettingsHelper::clientRequireTradeName() ? 'required' : 'nullable', 'string', 'max:255'],
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => $phone,
            'mobile' => [ClientSettingsHelper::clientRequireMobile() ? 'required' : 'nullable', 'string', 'max:20', $saudiPhone],
            'email' => [
                ClientSettingsHelper::clientRequireEmail() ? 'required' : 'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($client ? User::where('client_id', $client->id)->value('id') : null),
            ],
            'street1' => 'nullable|string|max:255',
            'street2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:50',
            'tax_number' => [ClientSettingsHelper::clientRequireTaxNumber() ? 'required' : 'nullable', 'string', 'max:50'],
            'commercial_registration' => [ClientSettingsHelper::clientRequireCommercialRegistration() ? 'required' : 'nullable', 'string', 'max:100'],
            'category_id' => [ClientSettingsHelper::clientRequireCategory() ? 'required' : 'nullable', 'integer', 'exists:categories_clients,id'],
            'client_type' => $typeRule,
            'registration_stage_id' => [
                ClientSettingsHelper::clientRequireRegistrationStage() ? 'required' : 'nullable', 'integer',
                Rule::exists('statuses', 'id')->where(fn ($q) => $q->where('section', 'registration_stage')),
            ],
            'printing_method' => 'nullable|integer|in:1,2',
            'notes' => 'nullable|string|max:5000',
            'attachments' => [
                (! $isUpdate && ClientSettingsHelper::isImageFieldEnabled()) ? 'required' : 'nullable',
                'image', 'mimes:jpeg,png,jpg,gif', 'max:'.$maxKb,
            ],
            'latitude' => [$location, 'numeric', 'between:-90,90'],
            'longitude' => [$location, 'numeric', 'between:-180,180'],
            'neighborhood_name_manual' => 'nullable|string|max:255',
            'contacts' => 'nullable|array|max:20',
            'contacts.*.id' => 'nullable|integer',
            'contacts.*.first_name' => 'nullable|string|max:100',
            'contacts.*.last_name' => 'nullable|string|max:100',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => ['nullable', 'string', 'max:20', $saudiPhone],
            'contacts.*.mobile' => ['nullable', 'string', 'max:20', $saudiPhone],
        ] + ClientForm::employeeRules($client);

        // «الحدّ الأقصى للكمية لكل بند» حقلٌ في نموذج التعديل وحده، كما في الموقع.
        // وحدُّ الائتمان والرصيد الافتتاحي ليسا في النموذجين أصلاً — لهما شاشتاهما
        // بصلاحيّتيهما (`clients_manage_credit_limit`، `clients_manage_opening_balance`)
        if ($isUpdate) {
            $rules['item_quantity_limit'] = 'nullable|numeric|min:0';
        }

        if (Schema::hasColumn('clients', 'group_id') && ClientSettingsHelper::isGroupRequired()) {
            $rules['group_id'] = 'required';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'client_tree_branch_id.required' => 'يرجى اختيار الفرع أو المنطقة التي يتبع لها العميل.',
            'client_tree_branch_id.in' => 'الفرع أو المنطقة المختارة خارج صلاحيتك.',
            'email.unique' => 'هذا البريد الإلكتروني مستعمَلٌ في حسابٍ آخر بالنظام (موظّف أو عميل). اختر بريداً غير مستعمَل.',
            'attachments.required' => 'صورة العميل مطلوبة',
            'attachments.image' => 'يجب أن يكون الملف صورة',
            'attachments.mimes' => 'يجب أن تكون الصورة من نوع: jpeg, png, jpg, gif',
            'attachments.max' => 'حجم الصورة يتجاوز الحد المسموح في إعدادات وحدات العملاء',
            'phone.required' => 'رقم الهاتف مطلوب — الحقل إلزاميّ في إعدادات وحدات العملاء',
            'mobile.required' => 'رقم الجوّال مطلوب — الحقل إلزاميّ في إعدادات وحدات العملاء',
            'phone.unique' => 'رقم الهاتف مسجَّلٌ لعميل آخر، وتكرارُه ممنوع في إعدادات وحدات العملاء',
            'tax_number.required' => 'الرقم الضريبي مطلوب — الحقل إلزاميّ في إعدادات وحدات العملاء',
            'email.required' => 'البريد الإلكتروني مطلوب — الحقل إلزاميّ في إعدادات وحدات العملاء',
            'trade_name.required' => 'الاسم التجاري مطلوب — الحقل إلزاميّ في إعدادات وحدات العملاء',
            'commercial_registration.required' => 'السجل التجاري مطلوب — الحقل إلزاميّ في إعدادات وحدات العملاء',
            'category_id.required' => 'تصنيف العميل مطلوب — الحقل إلزاميّ في إعدادات وحدات العملاء',
            'registration_stage_id.required' => 'وضع العميل مطلوب — الحقل إلزاميّ في إعدادات وحدات العملاء',
            'registration_stage_id.exists' => 'وضع العميل المحدَّد غير موجود في قائمة أوضاع العملاء',
            'client_type.in' => 'نوع العميل غير مسموح — نطاق الأنواع محدَّد في إعدادات وحدات العملاء',
            'latitude.required' => 'حدّد موقع العميل على الخريطة — الموقع إلزاميّ في إعدادات وحدات العملاء.',
            'longitude.required' => 'حدّد موقع العميل على الخريطة — الموقع إلزاميّ في إعدادات وحدات العملاء.',
            'group_id.required' => 'المجموعة مطلوبة',
        ];
    }

    /** العميل المعدَّل في مسار التعديل — `null` في الإضافة. */
    public function editedClient(): ?Client
    {
        $client = $this->route('client');

        if ($client === null) {
            return null;
        }

        return $client instanceof Client ? $client : Client::find($client);
    }
}
