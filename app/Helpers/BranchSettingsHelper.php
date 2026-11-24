<?php

use Modules\Branches\Services\BranchSettingsService;

/*
 * دوال عامة لقراءة إعدادات الفروع من أي مكان في النظام.
 *
 * الإعدادات كانت تُحفَظ ولا تُقرأ: لا سطر واحد في الشيفرة يسأل «هل مشاركة
 * العملاء مفعّلة لهذا الفرع؟». هذه الدوال هي نقطة القراءة الموحّدة، وتراعي
 * التوريث من الأب والقيمة الافتراضية.
 *
 * الاستعمال:
 *   branch_setting_enabled('enforce_credit_limit')          // الفرع النشط
 *   branch_setting('max_discount_percent', 0, $branch->id)  // فرع محدّد
 */

if (! function_exists('current_branch_id')) {
    /**
     * الفرع النشط للمستخدم الحالي.
     *
     * التبديل بين الفروع في هذا النظام يكتب `users.branch_id` مباشرة
     * (انظر `BranchesController::switchBranch`) ولا يستعمل الجلسة، والمدير
     * الذي اختار «كل الفروع» تكون قيمته null — فتعود الإعدادات إلى الافتراضي.
     */
    function current_branch_id(): ?int
    {
        $userBranch = auth()->user()->branch_id ?? null;

        return $userBranch ? (int) $userBranch : null;
    }
}

if (! function_exists('branch_setting')) {
    /**
     * قيمة إعداد فرع بعد التوريث والافتراضي.
     *
     * @param  mixed  $default  ما يُرجَع إذا لم يكن المفتاح معرّفاً أو كانت قيمته فارغة
     * @return mixed
     */
    function branch_setting(string $key, $default = null, ?int $branchId = null)
    {
        return BranchSettingsService::value($branchId ?? current_branch_id(), $key, $default);
    }
}

if (! function_exists('branch_setting_enabled')) {
    /** هل الإعداد البولياني مفعّل للفرع؟ */
    function branch_setting_enabled(string $key, ?int $branchId = null): bool
    {
        return BranchSettingsService::enabled($branchId ?? current_branch_id(), $key);
    }
}

if (! function_exists('branch_setting_source')) {
    /**
     * من أين جاءت قيمة الإعداد: own | inherited | default.
     * مفيد في الشاشات لبيان أن الفرع يتبع أباه.
     */
    function branch_setting_source(string $key, ?int $branchId = null): ?string
    {
        return BranchSettingsService::resolve($branchId ?? current_branch_id(), $key)['source'] ?? null;
    }
}
