<?php

namespace App\Models\Concerns;

use App\Models\Branch;

/**
 * فرعٌ افتراضي عند الإنشاء: الفرع الرئيسي، لا فراغ ولا فرع المستخدم النشط.
 *
 * سجلات المتجر الإلكتروني (طلب، كوبون، طريقة شحن، بانر) تُنشأ أحياناً من
 * سياقٍ بلا فرع مستخدم واضح (عميلٌ زائر في الواجهة، أمر كرون، بذرة) —
 * فحصرها بفرع الموظف الحالي (`current_branch_id()`) يُنسبها لفرعٍ عشوائي
 * تبعاً لمن كان مسجّلاً حين وقع الإنشاء. الفرع الرئيسي مرجعٌ ثابتٌ لا يتغيّر
 * بتغيّر من يُنشئ السجلّ.
 *
 * لا يُطبَّق هذا على `BelongsToBranch` نفسها لأنها سِمةٌ عامة تستعملها
 * موديلاتٌ أخرى خارج المتجر بدلالة مختلفة (`null` = «كل الفروع» بلا تفضيل
 * فرعٍ بعينه) — هذا التخصيص محصورٌ بموديلات المتجر فقط.
 */
trait DefaultsToMainBranch
{
    public static function bootDefaultsToMainBranch(): void
    {
        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $model->branch_id = Branch::mainId();
            }
        });
    }
}
