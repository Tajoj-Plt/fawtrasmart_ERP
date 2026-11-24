<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تصنيف عميل (`App\Models\CategoriesClient`) — لا `App\Http\Requests\CategoryRequest`
 * المستعملة لتصنيفات المنتجات في وحدة المخزون، تصنيفٌ مختلفٌ تماماً
 * بجدولٍ مختلف. نفس قواعد التحقّق التي كانت مكتوبةً مباشرةً في
 * `CatagroiyClientController::store()`/`update()`، هنا فقط لتُختبر مرّة
 * واحدة بدل نسختين قد تنحرفان عن بعضهما مع الوقت.
 */
class CategoriesClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('id');

        return [
            'name' => 'required|string|max:255|unique:categories_clients,name,'.($categoryId ?? 'NULL'),
            'description' => 'nullable|string',
            'active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم التصنيف مطلوب',
            'name.unique' => 'يوجد تصنيف بنفس هذا الاسم مسبقاً',
        ];
    }
}
