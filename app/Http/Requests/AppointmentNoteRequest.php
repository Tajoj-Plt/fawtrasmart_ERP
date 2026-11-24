<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * التحقّق من ملاحظة موعد العميل.
 *
 * **قاعدة المرفقات كانت مُعلَّقة** — سطرٌ خارج المصفوفة أصلاً:
 *
 *     // 'attachments' => 'mimes:jpeg,png,jpg,gif,svg', 'max:2048',
 *
 * فلم يكن على المرفق نوعٌ ولا حجم: يُقبل أيُّ ملفٍ بأيّ امتداد وبأيّ حجم
 * ترفعه الاستضافة. الحجمُ من «إعدادات وحدات العملاء»؛ والعددُ ثابتٌ هنا
 * بعد إزالة بطاقة «ملاحظات العملاء» من الإعدادات.
 *
 * @see \App\Helpers\ClientSettingsHelper::maxUploadSizeKb()
 */
class AppointmentNoteRequest extends FormRequest
{
    /** أقصى عدد مرفقات في الملاحظة الواحدة — الافتراضيُّ الذي كان في الإعدادات. */
    public const MAX_ATTACHMENTS = 5;

    public function authorize()
    {
        return true; // تأكد من أن المستخدم مصرح له
    }

    public function rules()
    {
        $maxSizeKb = \App\Helpers\ClientSettingsHelper::maxUploadSizeKb();
        $maxCount = self::MAX_ATTACHMENTS;

        return [
            'user_id' => 'nullable|exists:users,id',
            'client_id' => 'required|exists:clients,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'date' => 'nullable|date',
            'action_type' => 'nullable|string',
            'time' => 'required|date_format:H:i',
            'notes' => 'nullable|string',
            'attachments' => 'nullable|array|max:'.$maxCount,
            'attachments.*' => 'file|mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx,xls,xlsx|max:'.$maxSizeKb,
            'share_with_client' => 'boolean',
            'visit_type' => 'required|in:morning,evening',
        ];
    }

    public function messages()
    {
        return [
            'action_type.required' => 'يرجى تحديد نوع الإجراء.',
            'share_with_client.required' => 'يجب تحديد ما إذا كنت ترغب في مشاركة الملاحظة مع العميل.',
            'visit_type.in' => 'فترة الزيارة غير صالحة.',
            'attachments.max' => 'أقصى عدد مرفقات في الملاحظة الواحدة :max.',
            'attachments.*.max' => 'حجم المرفق يتجاوز الحد المسموح — يُضبط من إعدادات وحدات العملاء.',
            'attachments.*.mimes' => 'نوع المرفق غير مسموح.',
        ];
    }
}
