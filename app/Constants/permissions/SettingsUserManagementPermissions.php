<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات شاشات إدارة المستخدمين والأدوار والصلاحيات.
 *
 * قسمٌ صغير وخطِر: من يملك `settings.roles.edit` يستطيع أن يمنح نفسه كل
 * صلاحيةٍ أخرى في النظام. فُصل عن بقية إعدادات النظام في
 * [SystemPermissions] لهذا السبب وحده — كي يُرى وحده حين يُمنح.
 */
class SettingsUserManagementPermissions extends BasePermissionCatalog
{
    public static function module(): array
    {
        return [
            'key' => 'user_management',
            'label' => 'إدارة المستخدمين والأدوار',
            'description' => 'حسابات الدخول والأدوار والصلاحيات — من يملك تعديل الأدوار يملك النظام كله فعلياً.',
            'icon' => 'shield',
            'order' => 95,
        ];
    }

    public static function groups(): array
    {
        return [
            'users' => [
                'label' => 'المستخدمون',
                'description' => 'حسابات الدخول: إنشاؤها وربطها بدورٍ وفرع وحذفها.',
                'icon' => 'users',
                'controller' => 'Sitting/UserManagement/UsersController',
                'permissions' => [
                    'settings.users.view' => 'عرض المستخدمين',
                    'settings.users.create' => 'إضافة مستخدم',
                    'settings.users.edit' => 'تعديل مستخدم',
                    'settings.users.delete' => 'حذف مستخدم',
                ],
            ],

            'roles' => [
                'label' => 'الأدوار',
                'description' => 'حزم الصلاحيات التي تُسنَد إلى المستخدمين — تعديلها يسري فوراً على كل حاملي الدور.',
                'icon' => 'shield',
                'controller' => 'Sitting/UserManagement/RolesController',
                'permissions' => [
                    'settings.roles.view' => 'عرض الأدوار',
                    'settings.roles.create' => 'إضافة دور',
                    'settings.roles.edit' => 'تعديل دور وصلاحياته',
                    'settings.roles.delete' => 'حذف دور',
                ],
            ],

            'permissions' => [
                'label' => 'الصلاحيات',
                'description' => 'الصلاحيات المفردة — تُزرع عادةً من كتالوجات الوحدات، وهذه الشاشة للتدخّل اليدوي.',
                'icon' => 'key',
                'controller' => 'Sitting/UserManagement/PermissionsController',
                'permissions' => [
                    'settings.permissions.view' => 'عرض الصلاحيات',
                    'settings.permissions.create' => 'إضافة صلاحية',
                    'settings.permissions.edit' => 'تعديل صلاحية',
                    'settings.permissions.delete' => 'حذف صلاحية',
                ],
            ],
        ];
    }
}
