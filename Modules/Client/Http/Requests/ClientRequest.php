<?php

namespace Modules\Client\Http\Requests;

use App\Helpers\ClientSettingsHelper;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use App\Rules\SaudiPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * الأرقام تُطبَّع قبل التحقّق: «+966 50 123 4567» و«٠٥٠١٢٣٤٥٦٧» يُحفظان
     * 0501234567 — انظر {@see SaudiPhone}.
     */
    protected function prepareForValidation(): void
    {
        SaudiPhone::normalizeRequest($this, ['phone', 'mobile'], ['contacts' => ['phone', 'mobile']]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // التحقق مما إذا كان هذا تعديل أو إنشاء جديد
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        // قاعدة المرفقات: إلزامية في الإنشاء وحده، وفقط إذا كان حقل الصورة
        // مفعّلاً من «الحقول الإضافية» في إعدادات العملاء — كان الحقل هنا
        // إلزامياً دوماً حتى مع تعطيله من تلك الشاشة
        $maxUploadKb = ClientSettingsHelper::maxUploadSizeKb();
        $attachmentsRule = (! $isUpdate && ClientSettingsHelper::isImageFieldEnabled())
            ? 'required|image|mimes:jpeg,png,jpg,gif|max:'.$maxUploadKb
            : 'nullable|image|mimes:jpeg,png,jpg,gif|max:'.$maxUploadKb;

        /* ── قواعد التسجيل من «إعدادات وحدات العملاء» ← تبويب العملاء ──

           كانت الأربعة `nullable` مثبَّتةً في الملفّ، وفي الشاشة أربعةُ
           مفاتيح تقابلها تُضبَط ولا تُقرأ: «الهاتف مطلوب»، و«منع تكرار
           الهاتف»، و«الهوية مطلوبة»، و«البريد مطلوب». */
        // صيغةُ الرقم السعودي: صفرٌ ثم تسعُ خانات. أرقامُ السجلّ المحفوظة
        // قبل القاعدة تُقبل ما لم تتغيّر، فلا يُحبس عميلٌ قديمٌ عن التعديل
        $saudiPhone = new SaudiPhone($this->storedNumbers());

        $phoneRules = [ClientSettingsHelper::clientRequirePhone() ? 'required' : 'nullable', 'string', 'max:20', $saudiPhone];

        if (ClientSettingsHelper::clientUniquePhone()) {
            // العميلُ نفسُه يُستثنى، وإلّا منعه رقمُه هو من الحفظ
            $phoneRules[] = Rule::unique('clients', 'phone')->ignore($this->route('id'));
        }

        // الجوّال مفتاحُه مستقلّ عن الهاتف: منشأةٌ تُلزم أحدَهما أو كليهما أو
        // لا شيء — وكان `nullable` مثبَّتاً هنا بلا إعدادٍ يقابله
        $mobileRules = [ClientSettingsHelper::clientRequireMobile() ? 'required' : 'nullable', 'string', 'max:20', $saudiPhone];

        $taxNumberRule = ClientSettingsHelper::clientRequireTaxNumber() ? 'required' : 'nullable';

        $emailRules = [
            ClientSettingsHelper::clientRequireEmail() ? 'required' : 'nullable',
            'email',
            'max:255',
            /* البريد فريدٌ في `users` — والقاعدة كانت غائبة.

               `store()` و`update()` ينشئان للعميل حسابَ دخولٍ في `users`
               ببريده، والعمود فريدٌ في قاعدة البيانات ولا قاعدةَ تحقّقٍ
               تقابله. فبريدٌ يخصّ موظفاً أو مديراً يمرّ من الشاشة ثمّ ينفجر
               عند `insert`: في التعديل برسالة SQLSTATE[23000] خام، وفي
               الإنشاء بعد أن حُفظ العميلُ فعلاً — فيبقى في الجدول بلا حسابٍ
               محاسبيّ لأنّ كتلة إنشاء الحساب تأتي بعد إنشاء المستخدم. */
            Rule::unique('users', 'email')->ignore($this->clientUserId()),
        ];

        // نطاقُ نوع العميل: «كلاهما» يقبل الاثنين، وغيرُه يقصر القبول على
        // قيمةٍ واحدة — فما يُخفى من القائمة لا يمرّ من الطلب المصنوع بيد
        $typeScope = ClientSettingsHelper::clientTypeScope();
        $clientTypeRule = match ($typeScope) {
            'individual' => 'nullable|integer|in:1',
            'commercial' => 'nullable|integer|in:2',
            default => 'nullable|integer|in:1,2',
        };

        // «الحقول الإلزامية في نموذج العميل» ← إعدادات وحدات العملاء ←
        // نفس نمط $phoneRules/$taxNumberRule/$emailRules أعلاه
        $tradeNameRules = [ClientSettingsHelper::clientRequireTradeName() ? 'required' : 'nullable', 'string', 'max:255'];
        $commercialRegistrationRules = [ClientSettingsHelper::clientRequireCommercialRegistration() ? 'required' : 'nullable', 'string', 'max:100'];
        $categoryIdRules = [ClientSettingsHelper::clientRequireCategory() ? 'required' : 'nullable', 'exists:categories_clients,id'];

        // «وضع العميل» — نفس المفتاح الذي ترسم به الشاشةُ النجمةَ، فلا
        // تفترق قاعدةُ التحقّق عمّا يراه المستخدم. مطفأً يبقى اختيارياً
        // ويُملأ من الوضع الافتراضي في `store()` حين يُترَك فارغاً
        $registrationStageRules = [
            ClientSettingsHelper::clientRequireRegistrationStage() ? 'required' : 'nullable',
            'integer',
            Rule::exists('statuses', 'id')->where(fn ($q) => $q->where('section', 'registration_stage')),
        ];

        return [
            'trade_name' => $tradeNameRules,
            'code' => ['nullable', Rule::unique('clients', 'code')->ignore($this->id)],
            // تعديل القاعدة هنا
            'currency' => 'nullable|string|max:50',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => $phoneRules,
            'mobile' => $mobileRules,
            'street1' => 'nullable|string|max:255',
            'street2' => 'nullable|string|max:255',
            // العنوان الوطني: أربع خانات
            'building_number' => 'nullable|digits:4',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'tax_number' => $taxNumberRule.'|string|max:50',
            'commercial_registration' => $commercialRegistrationRules,
            'credit_limit' => 'nullable|numeric',
            'credit_period' => 'nullable|integer',
            'printing_method' => 'nullable|integer|in:1,2',
            'opening_balance' => 'nullable|numeric',
            'opening_balance_date' => 'nullable|date',
            'email' => $emailRules,
            'category_id' => $categoryIdRules,
            'client_type' => $clientTypeRule,
            'registration_stage_id' => $registrationStageRules,
            'notes' => 'nullable|string',
            'attachments' => $attachmentsRule,
            'item_quantity_limit' => 'nullable|numeric|min:0',
            // 'employee_id' => 'nullable',
            'contacts' => 'nullable|array',
            'contacts.*.first_name' => 'nullable|string|max:100',
            'contacts.*.last_name' => 'nullable|string|max:100',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => ['nullable', 'string', 'max:20', $saudiPhone],
            'contacts.*.mobile' => ['nullable', 'string', 'max:20', $saudiPhone],
        ];
    }

    /**
     * أرقامُ العميل وجهاتِ اتصاله كما هي محفوظةٌ الآن — فارغةٌ عند الإنشاء.
     *
     * @return array<int, string>
     */
    private function storedNumbers(): array
    {
        $clientId = $this->route('id') ?? $this->route('client');
        $clientId = is_object($clientId) ? $clientId->getKey() : $clientId;

        if (! $clientId) {
            return [];
        }

        return collect(Client::whereKey($clientId)->first(['phone', 'mobile'])?->only(['phone', 'mobile']))
            ->merge(Contact::where('client_id', $clientId)->get(['phone', 'mobile'])
                ->flatMap(fn ($contact) => [$contact->phone, $contact->mobile]))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * حسابُ الدخول المرتبط بهذا العميل — يُستثنى من فحص التفرّد.
     *
     * في الإنشاء لا وجودَ له فيرجع `null`، فيُمنَع أيُّ بريدٍ مستعمَل. وفي
     * التعديل يُستثنى حسابُ العميل نفسِه وحده، كي لا يمنعَه بريدُه هو من
     * الحفظ.
     */
    private function clientUserId(): ?int
    {
        $clientId = $this->route('id');

        return $clientId ? User::where('client_id', $clientId)->value('id') : null;
    }

    public function messages()
    {
        return [
            'required' => 'هذا الحقل مطلوب',
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
        ];
    }
}
