<?php

namespace Modules\Sitting\Http\Requests;

use App\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التحقّق من مفتاح ربط جديد.
 *
 * الصلاحيات ومدّة الصلاحية كانتا غائبتين عن الشاشة كلّها، فكان المتحكّم
 * ينادي `ApiKey::generate($id, $name)` بلا ثالث ولا رابع — والنتيجة مفتاح
 * بـ`abilities = []` لا يفتح شيئاً، لأن `ApiKey::can()` يرفض ما ليس مذكوراً
 * صراحةً. وأربعة من مسارات `routes/api.php` الخمسة تشترط صلاحية بعينها،
 * فكان المفتاح المولَّد من الواجهة عاجزاً عن أربعة أخماس الواجهة البرمجية.
 */
class ApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(array_keys(ApiKey::ABILITIES))],
            // مهلة اختيارية، لكنها لا تقبل تاريخاً ماضياً — مفتاح يولد
            // منتهياً لا معنى له
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('settings.api.name'),
            'abilities' => __('settings.api.abilities'),
            'expires_at' => __('settings.api.expiry_date'),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('settings.api.errors.name_required'),
            'abilities.required' => __('settings.api.errors.abilities_required'),
            'abilities.min' => __('settings.api.errors.abilities_required'),
            'abilities.*.in' => __('settings.api.errors.ability_unknown'),
            'expires_at.after' => __('settings.api.errors.expiry_after'),
        ];
    }
}
