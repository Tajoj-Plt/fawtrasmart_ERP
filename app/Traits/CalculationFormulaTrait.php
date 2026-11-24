<?php

namespace App\Traits;

trait CalculationFormulaTrait
{
    /**
     * تقييم صيغة حسابية بسيطة باستخدام المتغيرات المقدمة.
     *
     * تستبدل هذه الدالة أسماء المتغيرات في الصيغة بقيمها الرقمية
     * ثم تقوم بتقييم التعبير. يُسمح فقط بالعمليات الحسابية الأساسية
     * (+, -, *, /, الأقواس والنقاط العشرية).
     * إذا احتوت الصيغة على أحرف غير مسموح بها، ستعيد 0.
     *
     * @param string|null $formula الصيغة الحسابية (مثل: "basic_amount * 0.1")
     * @param array $variables خريطة من المتغيرات المتاحة وقيمها الرقمية
     * @return float نتيجة تقييم التعبير، أو 0 في حالة الخطأ
     */
    protected function evaluateCalculationFormula(?string $formula, array $variables): float
    {
        if (empty($formula)) {
            return 0;
        }

        // استبدال أسماء المتغيرات بقيمها الرقمية
        foreach ($variables as $key => $value) {
            // إحاطة اسم المتغير بحدود الكلمات لتجنب الاستبدال العرضي
            $pattern = '/\b' . preg_quote($key, '/') . '\b/';
            $formula = preg_replace($pattern, (string)$value, $formula);
        }

        // السماح فقط بالأرقام والعمليات الحسابية والأقواس والنقاط العشرية والمسافات
        if (!preg_match('/^[0-9+\-*\/().\s]+$/', $formula)) {
            return 0;
        }

        try {
            // استخدام eval لحساب التعبير. قمع الأخطاء للتعامل مع القسمة على صفر أو أخطاء النحو.
            $result = @eval('return ' . $formula . ';');
            if ($result === false || $result === null) {
                return 0;
            }
            return floatval($result);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * تنظيف الأرقام من الفواصل وتحويلها إلى قيم عشرية
     *
     * @param mixed $number الرقم المراد تنظيفه
     * @return float القيمة العشرية المنظفة
     */
    protected function cleanNumber($number): float
    {
        if (empty($number)) {
            return 0;
        }
        return floatval(str_replace(',', '', $number));
    }

    /**
     * الحصول على المتغيرات المتاحة للصيغ الحسابية
     *
     * @param float $basicAmount الراتب الأساسي
     * @param array $additionalVars متغيرات إضافية
     * @return array مصفوفة المتغيرات المتاحة
     */
    protected function getFormulaVariables(float $basicAmount, array $additionalVars = []): array
    {
        $variables = [
            'basic_amount'  => $basicAmount,
            'basic'         => $basicAmount,
            'basic_salary'  => $basicAmount,
        ];

        return array_merge($variables, $additionalVars);
    }

    /**
     * الحصول على قائمة المتغيرات المتاحة للاستخدام في الصيغ (للعرض في الواجهة)
     *
     * @return array قائمة المتغيرات مع الوصف
     */
    public function getAvailableFormulaVariables(): array
    {
        return [
            [
                'name' => 'basic_amount',
                'description' => 'الراتب الأساسي',
                'example' => 'basic_amount * 0.1'
            ],
            [
                'name' => 'basic',
                'description' => 'الراتب الأساسي (اختصار)',
                'example' => 'basic + 500'
            ],
            [
                'name' => 'basic_salary',
                'description' => 'الراتب الأساسي (بديل)',
                'example' => 'basic_salary * 0.15'
            ],
        ];
    }
}
