<?php

namespace Modules\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * قواعدُ جهاز الكاشير.
 *
 * كانت هذه القواعد ورسائلُها — نحوَ ستّين سطراً — **مكتوبةً مرّتين حرفياً**
 * في `store()` و`update()` من `DevicesController`. فأيُّ حقلٍ يُضاف لأحدهما
 * ولا يُضاف للآخر يمرّ في الإنشاء ويُرفض في التعديل.
 */
class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return hasPermission('pos.devices.manage');
    }

    public function rules(): array
    {
        return [
            'device_name' => 'required|string|min:3|max:255',
            'branch_id' => 'required|exists:branches,id',
            'user_id' => 'required|exists:users,id',
            'store_id' => 'required|exists:store_houses,id',
            'device_status' => 'required|in:active,inactive,maintenance,damaged',
            'device_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'description' => 'nullable|string|max:1000',

            // حقولٌ لم تكن موجودة: الجهازُ بلا رقمٍ تسلسلي ولا عنوانٍ شبكي
            // لا يُميَّز عن غيره عند التدقيق
            'serial_number' => 'nullable|string|max:100',
            'ip_address' => 'nullable|ip',
            'max_cash_in_drawer' => 'nullable|numeric|min:0',
            'default_printer_id' => 'nullable|exists:pos_printers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'device_name.required' => 'اسم الجهاز مطلوب',
            'device_name.min' => 'اسم الجهاز يجب أن يكون على الأقل 3 أحرف',
            'device_name.max' => 'اسم الجهاز يجب ألا يتجاوز 255 حرفاً',

            'branch_id.required' => 'الفرع مطلوب',
            'branch_id.exists' => 'الفرع المختار غير موجود',

            'user_id.required' => 'الموظف مطلوب',
            'user_id.exists' => 'الموظف المختار غير موجود',

            'store_id.required' => 'المخزن مطلوب — منه يُخصم البيع من هذا الجهاز',
            'store_id.exists' => 'المخزن المختار غير موجود',

            'device_status.required' => 'حالة الجهاز مطلوبة',
            'device_status.in' => 'حالة الجهاز غير صحيحة',

            'device_image.image' => 'الملف يجب أن يكون صورة',
            'device_image.mimes' => 'صيغة الصورة يجب أن تكون jpeg أو jpg أو png أو webp',
            // كان الحدُّ 20 ميغابايت لصورةِ جهاز — رفعٌ ثقيلٌ بلا داعٍ
            'device_image.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميغابايت',

            'ip_address.ip' => 'عنوان الشبكة غير صحيح',
            'max_cash_in_drawer.min' => 'الحد الأقصى للنقد لا يمكن أن يكون سالباً',

            'description.max' => 'الوصف يجب ألا يتجاوز 1000 حرف',
        ];
    }
}
