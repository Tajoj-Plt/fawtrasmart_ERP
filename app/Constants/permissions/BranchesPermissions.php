<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات وحدة الفروع والمناطق — المصدر الوحيد لأسمائها.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 * الوحدة كلّها كانت خلف صلاحيةٍ واحدة: `permission:branches`. فمن مُنح
 * «الفروع» ملك كل شيء فيها — إنشاءَ الفروع وحذفَها، ونسخَ الإعدادات على
 * فروعٍ أخرى، ودمجَ المجموعات الذي ينقل آلاف العملاء دفعةً واحدة، والتراجعَ
 * عن الدمج، وترحيلَ البنية القديمة كلَّها.
 *
 * وهذه أفعالٌ لا يجوز أن تُمنح جملةً: من يُضيف فرعاً جديداً ليس بالضرورة من
 * يُصرَّح له بحذف فرعٍ قائم بما فيه، ومن يُحرّر بيانات فرعٍ ليس من يُعيد
 * ترتيب الشجرة كلّها.
 *
 * ─────────────────────── مبدأ التقسيم ───────────────────────
 *
 * **ما يُغيّر البنية يُفصل عمّا يقرؤها، وما لا رجعة فيه يُفرد بصلاحيته.**
 *
 *   • `branches.view` يقرأ، و`branches.create` يُضيف، و`branches.delete`
 *     يحذف فرعاً بكل ما تحته — ثلاثةٌ لا واحدة.
 *   • `branches.hierarchy` لإسناد الآباء بالجملة: فعلٌ يُعيد رسم الشجرة
 *     كلّها في نداءٍ واحد، فلا يُلحق بـ`edit`.
 *   • `groups.merge` و`groups.revert` و`groups.transfer_clients` أفعالٌ
 *     تنقل عملاءً بين كياناتٍ محاسبية — أخطر ما في الوحدة، ولكلٍّ صلاحيته.
 *   • `branches.settings_copy` و`settings_reset` يمسّان فروعاً كثيرة دفعةً،
 *     ففُصلا عن `settings.edit` الذي يمسّ فرعاً واحداً.
 *   • `branches.migrate` ترحيلٌ بنيويّ يُنفَّذ مرّةً في عمر النظام — صلاحيةٌ
 *     تُمنح ليومٍ واحد ثم تُنزع.
 *
 * ─────────────────────── ما لا يُحرَس هنا ───────────────────────
 *
 * **تبديل الفرع النشط** (`branch.switch`) خارج هذا الكتالوج عمداً: كل
 * مستخدمٍ يبدّل فرعه من الترويسة، والمتحكّم يتحقّق أن الفرع ضمن فروعه
 * المسموح بها. وحراستُه بصلاحيةٍ إدارية تُعطّل مبدّل الفروع لكل من لا يدير
 * الفروع — وهم الأكثرية.
 *
 * ونطاقُ البيانات (أي فروعٍ يرى المستخدم) ليس هنا بل في
 * [DataScopePermissions] — محورٌ مستقلّ لا يُضرب في هذا.
 */
class BranchesPermissions extends BasePermissionCatalog
{
    /** بادئة أسماء صلاحيات هذه الوحدة. */
    public const PREFIX = 'branches.';

    public static function module(): array
    {
        return [
            'key' => 'branches',
            'label' => 'الفروع والمناطق',
            'description' => 'الفروع ومجموعات المناطق والشجرة الهرمية وإعدادات كل فرع.',
            'icon' => 'map-pin',
            'order' => 20,
        ];
    }

    public static function groups(): array
    {
        return [
            'branches' => [
                'label' => 'الفروع',
                'description' => 'منافذ العمل: إنشاؤها وتحريرها وتفعيلها وحذفها.',
                'icon' => 'git-branch',
                'controller' => 'Branches/BranchesController',
                'permissions' => [
                    'branches.view' => 'عرض الفروع وقائمتها',
                    'branches.show' => 'عرض تفاصيل فرع',
                    'branches.create' => 'إضافة فرع',
                    'branches.edit' => 'تعديل بيانات فرع',
                    'branches.toggle_status' => 'تفعيل الفرع وتعطيله',
                    'branches.delete' => 'حذف فرع',
                ],
            ],

            'hierarchy' => [
                'label' => 'الشجرة الهرمية',
                'description' => 'إعادة رسم بنية الفروع: إسناد الآباء بالجملة والترحيل البنيوي.',
                'icon' => 'share-2',
                'controller' => 'Branches/BranchesController@bulkAssignParent',
                'permissions' => [
                    'branches.hierarchy' => 'إسناد الآباء بالجملة وإعادة ترتيب الشجرة',
                    'branches.migrate' => 'ترحيل البنية القديمة إلى شجرة الفروع',
                ],
            ],

            'settings' => [
                'label' => 'إعدادات الفروع',
                'description' => 'إعدادات كل فرع على حدة، ونسخُها أو إرجاعها للافتراضي على فروعٍ كثيرة.',
                'icon' => 'sliders',
                'controller' => 'Branches/BranchesController@settings',
                'permissions' => [
                    'branches.settings.view' => 'عرض إعدادات الفرع',
                    'branches.settings.edit' => 'تعديل إعدادات فرع',
                    'branches.settings.copy' => 'نسخ الإعدادات إلى فروع أخرى',
                    'branches.settings.reset' => 'إرجاع الإعدادات إلى الافتراضي',
                ],
            ],

            'groups' => [
                'label' => 'مجموعات المناطق',
                'description' => 'العقد التنظيمية فوق الفروع: دمجها ونقل عملائها والتراجع عن الدمج.',
                'icon' => 'layers',
                'controller' => 'Branches/TerritoryGroupsController',
                'permissions' => [
                    'branches.groups.view' => 'عرض مجموعات المناطق وسجلّ الدمج',
                    'branches.groups.merge' => 'دمج المجموعات (مفردةً وبالجملة)',
                    'branches.groups.revert' => 'التراجع عن عملية دمج',
                    'branches.groups.transfer_clients' => 'نقل العملاء بين المجموعات',
                    'branches.groups.delete' => 'حذف مجموعة منطقة',
                ],
            ],

            'reports' => [
                'label' => 'تقارير الفروع',
                'description' => 'لوحة المؤشرات وتصدير بيانات الفروع.',
                'icon' => 'bar-chart-2',
                'controller' => 'Branches/BranchesController@dashboard',
                'permissions' => [
                    'branches.dashboard' => 'لوحة مؤشرات الفروع',
                    'branches.export' => 'تصدير بيانات الفروع',
                ],
            ],
        ];
    }
}
