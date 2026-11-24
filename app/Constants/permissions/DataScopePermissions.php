<?php

namespace App\Constants\permissions;

/**
 * كتالوج نطاقات البيانات — المحور الثاني للصلاحية.
 *
 * صلاحيات العمليات تجيب عن «ماذا يفعل المستخدم»، وهذه تجيب عن «على أي
 * بيانات». فصلُهما هو ما يمنع انفجار الأسماء الذي وقع في النظام القديم
 * (`sales_view_all_invoices` مقابل `sales_view_own_invoices`، وهكذا لكل
 * عملية) — بمحورين تكفي عمليةٌ واحدة ونطاقٌ واحد بدل ضربِ كلٍّ في كلّ.
 *
 * **النطاقات متدرّجة**: من يملك `data_scope_all` يرى كل ما يراه من دونه.
 * تُقرأ في `applyScope()` / `ScopesToAccessibleEmployees` لتقييد كل استعلامٍ
 * بما يحقّ للمستخدم رؤيته.
 *
 * السلّم: الكل ← المنطقة ← الفرع ← القسم ← الفريق ← هو وحده.
 *
 * صلاحيات العمليات (مثل «عرض طلبات الإجازات») تفتح الشاشة؛ وهذه تحدّد
 * *أيّ موظفين* تظهر بياناتهم فيها — لا تُكرَّر لاحقة `_all` / `_own` على
 * كل عملية.
 */
class DataScopePermissions extends BasePermissionCatalog
{
    public static function module(): array
    {
        return [
            'key' => 'data_scope',
            'label' => 'نطاق البيانات',
            'description' => 'يحدّد على أي بياناتٍ تسري صلاحيات المستخدم: بياناته هو، أو فريقه، أو قسمه، أو فرعه، أو منطقته، أو كل شيء.',
            'icon' => 'database',
            'order' => 5,
        ];
    }

    public static function groups(): array
    {
        return [
            'scopes' => [
                'label' => 'نطاقات الرؤية',
                'description' => 'تُمنح واحدةً لا أكثر لكل دور — الأوسعُ يُغني عمّا دونه، ومنحُ اثنتين معاً لا يزيد شيئاً.',
                'icon' => 'database',
                'controller' => 'App/Support/Scopes',
                'permissions' => [
                    'data_scope_own' => 'بياناته هو فقط — المندوب / المحصّل / الموظف',
                    'data_scope_team' => 'بيانات فريقه — المشرف (هو ومن يتبعه إدارياً)',
                    'data_scope_department' => 'بيانات قسمه — مدير القسم',
                    'data_scope_branch' => 'بيانات فرعه — مدير الفرع',
                    'data_scope_region' => 'بيانات منطقته — مدير المنطقة',
                    'data_scope_all' => 'كل البيانات بلا قيد — المدير العام',
                ],
            ],
        ];
    }
}
