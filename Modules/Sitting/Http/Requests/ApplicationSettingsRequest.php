<?php

namespace Modules\Sitting\Http\Requests;

use App\Services\Settings\ApplicationRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * التحقّق من حالات وحدات النظام.
 *
 * كان المتحكّم يكتب ما يصل حرفياً، وعمود `status` من نوع `enum` فيرفض
 * القيمة الغريبة برسالة قاعدة بيانات خام:
 *
 *     SQLSTATE[01000]: Data truncated for column 'status' at row 1
 *
 * أي أن الحماية كانت في المخطَّط لا في الكود، وثمنُها صفحة خطأ 500 في
 * وجه المسؤول بدل رسالة مفهومة.
 */
class ApplicationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة في المتحكّم
    }

    protected function prepareForValidation(): void
    {
        // مربّع اختيار غير مؤشَّر لا يُرسَل أصلاً؛ والقالب يرسل حقلاً
        // مخفياً بـ`inactive` قبله. هذا السطر يضمن الحالة حتى لو عُدِّل
        // القالب لاحقاً فسقط الحقل المخفيّ
        $filled = [];

        foreach (ApplicationRegistry::keys() as $key) {
            $filled[$key] = $this->input($key, ApplicationRegistry::INACTIVE);
        }

        $this->merge($filled);
    }

    public function rules(): array
    {
        $allowed = [ApplicationRegistry::ACTIVE, ApplicationRegistry::INACTIVE];

        return array_fill_keys(
            ApplicationRegistry::keys(),
            ['required', Rule::in($allowed)]
        );
    }

    public function messages(): array
    {
        $messages = [];

        foreach (ApplicationRegistry::keys() as $key) {
            $label = ApplicationRegistry::labelFor($key);
            $messages[$key.'.in'] = __('settings.validation.module_state_in', ['module' => $label]);
            $messages[$key.'.required'] = __('settings.validation.module_state_required', ['module' => $label]);
        }

        return $messages;
    }

    /**
     * الحالات المتحقَّق منها وحدها — لا شيء خارج السجلّ.
     *
     * @return array<string, string>
     */
    public function statuses(): array
    {
        return array_intersect_key($this->validated(), array_flip(ApplicationRegistry::keys()));
    }
}
