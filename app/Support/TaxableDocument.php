<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * متى يكون المستندُ ضريبياً؟ — قاعدةٌ واحدة لسندات الصرف والقبض والقيود.
 *
 * الوسمُ يُضبط تلقائياً عند الحفظ متى اختِيرت ضريبة، فلا يُترك لأن يتذكّر
 * المستخدمُ خانةً يؤشّرها. ولو تُرك لتذكّره لاختلف السندُ عن أخيه وهما
 * سواء، ولخرج الإقرارُ ناقصاً بمقدار ما نُسي.
 *
 * والقاعدةُ هنا لا في المتحكّمات الثلاثة: ثلاثةُ نسخٍ من شرطٍ واحد تفترق
 * مع أوّل تعديل، وتفترق صامتةً — لا شيء يُنبّه أن سندَ القبض صار يَعُدُّ
 * ما لا يعدُّه سندُ الصرف.
 */
class TaxableDocument
{
    /**
     * هل هذا السند (صرفٌ أو قبض) ضريبيّ؟
     *
     * ضريبيٌّ إن اختِيرت له ضريبةٌ **أو** سُجِّل له مبلغُ ضريبة. والاثنان
     * مقصودان: قد تُختار الضريبةُ ومبلغُها صفرٌ (نسبةٌ صفرية)، وقد يُدخَل
     * المبلغُ يدوياً بلا اختيار من القائمة.
     *
     * @param  array<string, mixed>  $input  حقول الطلب
     */
    public static function fromVoucherInput(array $input): bool
    {
        foreach (['tax1', 'tax2'] as $key) {
            if (static::isChosen($input[$key] ?? null)) {
                return true;
            }
        }

        foreach (['tax1_amount', 'tax2_amount'] as $key) {
            if ((float) ($input[$key] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * من ضغط «إضافة ضرائب» في السند فعليه أن يختار نوعها.
     *
     * الشاشةُ ترسل `has_tax=1` ما دامت حقولُ الضرائب ظاهرة. فإن وصلت ولم
     * تُختر ضريبةٌ رُدّ السند: كان يُحفظ بلا ضريبة وصاحبُه يظنّه ضريبياً.
     * وطلبٌ لا يرسل `has_tax` (تطبيقٌ أو تكاملٌ) لا يمسّه هذا الشرط.
     *
     * @param  array<string, mixed>  $input  حقول الطلب
     *
     * @throws ValidationException
     */
    public static function assertChosenWhenAdded(array $input): void
    {
        if (! filter_var($input['has_tax'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (static::isChosen($input['tax1'] ?? null) || static::isChosen($input['tax2'] ?? null)) {
            return;
        }

        throw ValidationException::withMessages([
            'tax1' => ['اختر نوع الضريبة — أضفت ضرائب للسند ولم تختر أيّاً منها، أو اضغط «إزالة الضرائب».'],
        ]);
    }

    /**
     * هل هذا القيدُ ضريبيّ؟
     *
     * القيدُ لا يحمل حقلَ ضريبةٍ واحداً؛ ضريبتُه في سطوره: كلُّ سطرٍ له
     * `tax_amount` و`tax_type`. فيكون ضريبياً إن رُحِّل عنه سطرُ ضريبةٍ
     * واحدٌ على الأقل — وهو بعينه شرطُ `buildTaxLines()` في المتحكّم:
     * مبلغٌ موجب ونوعٌ `tax` (فـ«معفى» و«صفرية» لا تُرحَّل).
     *
     * @param  array<int, array<string, mixed>>  $details  سطور القيد
     */
    public static function fromJournalDetails(array $details): bool
    {
        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $amount = (float) ($detail['tax_amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $type = $detail['tax_type'] ?? null;

            // `vat` ما ترسله القائمة، و`tax` للسجلّات القديمة — وهو بعينه
            // شرطُ `buildTaxLines()`، فلا يفترق الوسمُ عن السطر المُرحَّل.
            if ($type === null || in_array($type, ['vat', 'tax'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * هل اختِيرت ضريبةٌ فعلاً؟
     *
     * القائمةُ ترسل `''` حين لا اختيار، و`'0'` لخيار «بلا ضريبة» في بعض
     * الشاشات — وكلاهما ليس اختياراً. و`(bool) '0'` في PHP يعطي false،
     * لكنّ `(bool) '00'` يعطي true؛ فالمقارنةُ الرقمية أسلم.
     */
    private static function isChosen($value): bool
    {
        if ($value === null || $value === '' || $value === false) {
            return false;
        }

        return is_numeric($value) ? ((float) $value) > 0 : true;
    }
}
