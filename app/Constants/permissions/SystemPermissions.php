<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات قسم النظام — الإعدادات العامة والبنية التحتية.
 *
 * ما يجمع هذا القسم أن صلاحياته لا تخصّ وحدةً تشغيلية بعينها: النسخ
 * الاحتياطي والمهام المجدولة والبريد والرسائل والتكاملات ومفاتيح الـAPI
 * واللغات والإشعارات. أخطرُ صلاحيات النظام هنا لا في وحدات العمل —
 * استعادةُ نسخةٍ احتياطية تكتب فوق قاعدة البيانات كلها، وتشغيلُ مهمةٍ
 * مجدولة يدوياً ينفّذ أمراً على الخادم.
 *
 * **ما ليس هنا**: إدارة المستخدمين والأدوار في [SettingsUserManagementPermissions]،
 * والفترات والإعدادات المحاسبية في [AccountPermissions] — الاسم يبدأ بـ
 * `settings.` لكنّ ما تحكمه محاسبةٌ لا إعداداتُ نظام.
 */
class SystemPermissions extends BasePermissionCatalog
{
    public static function module(): array
    {
        return [
            'key' => 'system',
            'label' => 'النظام والإعدادات',
            'description' => 'الإعدادات العامة، اللغات، الفروع، الإشعارات، النسخ الاحتياطي، المهام المجدولة، البريد والرسائل والتكاملات.',
            'icon' => 'settings',
            'order' => 90,
        ];
    }

    public static function groups(): array
    {
        return [
            'general' => [
                'label' => 'الإعدادات العامة',
                'description' => 'بيانات المنشأة والضرائب والإحصائيات — ما يسري على كل الشاشات.',
                'icon' => 'sliders',
                'controller' => 'Sitting/SettingsController',
                'permissions' => [
                    'settings_general_view' => 'عرض الإعدادات العامة',
                    'settings_edit_general_settings' => 'تعديل الإعدادات العامة',
                    'settings_edit_tax_settings' => 'تعديل إعدادات الضرائب',
                    'settings_statistics_configuration' => 'إعداد الإحصائيات ولوحات المؤشرات',
                    'settings_view_own_reports' => 'عرض تقاريره الخاصة',
                ],
            ],

            'languages' => [
                'label' => 'اللغات والترجمة',
                'description' => 'لغات الواجهة وتفعيلها — تفعيل لغةٍ هنا يغيّر كل مبدّلات اللغة في النظام.',
                'icon' => 'globe',
                'controller' => 'Sitting/LanguagesController',
                'permissions' => [
                    'settings_languages_view' => 'عرض اللغات',
                    'settings_languages_create' => 'إضافة لغة',
                    'settings_languages_edit' => 'تعديل لغة وترجماتها',
                    'settings_languages_delete' => 'حذف لغة',
                    'settings_languages_activate' => 'تفعيل اللغات وتعطيلها',
                    'settings_languages_set_default' => 'تعيين اللغة الافتراضية',
                ],
            ],

            'branches' => [
                'label' => 'الفروع',
                'description' => 'فروع المنشأة — وحدة تقسيم البيانات التي يبنى عليها نطاق `data_scope_branch`.',
                'icon' => 'map-pin',
                'controller' => 'Branches/BranchesController',
                'permissions' => [
                    'branches' => 'عرض الفروع وإدارتها',
                ],
            ],

            'groups' => [
                'label' => 'مجموعات النظام',
                'description' => 'المجموعات التي تُصنَّف بها الكيانات ويُقسَّم بها النطاق الجغرافي والتنظيمي.',
                'icon' => 'grid',
                'controller' => 'Sitting/GroupsController',
                'permissions' => [
                    'settings_groups_management' => 'الدخول إلى إدارة المجموعات',
                    'settings_groups_management_add' => 'إضافة مجموعة',
                    'settings_groups_management_edit' => 'تعديل مجموعة نظام',
                    'settings_groups_management_delete' => 'حذف مجموعة نظام',
                ],
            ],

            'notifications' => [
                'label' => 'الإشعارات',
                'description' => 'إشعارات النظام وإشعارات العملاء: قراءتها وإرسالها والرد عليها وحذفها.',
                'icon' => 'bell',
                'controller' => 'Sitting/NotificationsController',
                'permissions' => [
                    'notifications_view_all_notifications' => 'عرض جميع الإشعارات',
                    'notifications_view_system_notifications' => 'عرض إشعارات النظام',
                    'notifications_view_client_notifications' => 'عرض إشعارات العملاء',
                    'notifications_send_notifications' => 'إرسال إشعارات',
                    'notifications_reply_to_notifications' => 'الرد على الإشعارات',
                    'notifications_mark_as_read' => 'تعليم الإشعارات مقروءة',
                    'notifications_delete_notifications' => 'حذف الإشعارات',
                ],
            ],

            'templates' => [
                'label' => 'قوالب المستندات',
                'description' => 'قوالب طباعة الفواتير والسندات والتقارير.',
                'icon' => 'layout',
                'controller' => 'Sitting/DocumentTemplatesController',
                'permissions' => [
                    'templates' => 'إدارة قوالب المستندات',
                ],
            ],

            'workflow' => [
                'label' => 'دورة العمل والمهام',
                'description' => 'دورة العمل بين الموظفين ولوحة المهام.',
                'icon' => 'repeat',
                'controller' => 'TaskManager/TasksController',
                'permissions' => [
                    'work_cycle' => 'الدخول إلى دورة العمل',
                    'tasks_view_tasks' => 'عرض المهام',
                ],
            ],

            'backup' => [
                'label' => 'النسخ الاحتياطي (عمليات خطرة)',
                'description' => 'إنشاء النسخ وتنزيلها وحذفها — و«الاستعادة» تكتب فوق قاعدة البيانات كاملةً بلا رجعة.',
                'icon' => 'hard-drive',
                'controller' => 'Sitting/BackupController',
                'permissions' => [
                    'settings.backup.view' => 'عرض النسخ الاحتياطية',
                    'settings.backup.create' => 'إنشاء نسخة احتياطية',
                    'settings.backup.download' => 'تنزيل نسخة احتياطية',
                    'settings.backup.export' => 'تصدير نسخة احتياطية',
                    'settings.backup.restore' => 'استعادة نسخة احتياطية (تكتب فوق البيانات)',
                    'settings.backup.delete' => 'حذف نسخة احتياطية',
                    'settings.backup.settings' => 'إدارة إعدادات النسخ الاحتياطي',
                ],
            ],

            'system_update' => [
                'label' => 'تحديث النظام',
                'description' => 'تشغيل ترحيلات قاعدة البيانات وزراعة بيانات التحديث بعد رفع نسخة جديدة إلى الخادم — بديل سطر الأوامر لمن لا يملكه.',
                'icon' => 'download-cloud',
                'controller' => 'Sitting/SystemUpdateController',
                'permissions' => [
                    'settings.system_update.view' => 'عرض شاشة تحديث النظام',
                    // «تحديث» في كل تسمية عمداً: بحث شاشة الصلاحيات `like`
                    // على التسمية والاسم الخام، فتسميةٌ لا تحمل كلمة القسم
                    // تسقط من نتيجة من يبحث عنه باسمه
                    'settings.system_update.migrate' => 'تشغيل ترحيلات قاعدة البيانات (تحديث النظام)',
                    'settings.system_update.seed' => 'زراعة بيانات التحديث (السيدرات)',
                ],
            ],

            'maintenance_mode' => [
                'label' => 'وضع الصيانة',
                'description' => 'إقفال النظام على كل المستخدمين إلا حسابات محدَّدة — قبل استرجاع نسخة أو ترحيل كبير أو تصحيح بيانات يدوي.',
                'icon' => 'lock',
                'controller' => 'Sitting/MaintenanceModeController',
                'permissions' => [
                    'settings.maintenance_mode.view' => 'عرض شاشة وضع الصيانة',
                    // «الصيانة» في التسميتين عمداً: بحث شاشة الصلاحيات `like`
                    // على التسمية، فتسميةٌ لا تحمل كلمة القسم تسقط من نتيجة
                    // من يبحث عنه باسمه
                    'settings.maintenance_mode.manage' => 'تفعيل وضع الصيانة وإيقافه',
                ],
            ],

            'cache' => [
                'label' => 'مسح الذاكرة المؤقتة',
                'description' => 'مسح الكاش حين يبقى النظام يعمل بقيمة قديمة بعد تعديل إعداد أو رفع تحديث — لا يمسّ البيانات، والنظام يعيد بناء ما مُسح وحدَه.',
                'icon' => 'refresh-cw',
                'controller' => 'Sitting/CacheController',
                'permissions' => [
                    'settings.cache.view' => 'عرض شاشة مسح الذاكرة المؤقتة',
                    'settings.cache.clear' => 'مسح الذاكرة المؤقتة',
                ],
            ],

            'cron' => [
                'label' => 'المهام المجدولة',
                'description' => 'جدولة الأوامر الدورية وتشغيلها يدوياً وقراءة سجلات تنفيذها.',
                'icon' => 'clock',
                'controller' => 'Sitting/CronJobsController',
                'permissions' => [
                    'settings.cron.view' => 'عرض المهام المجدولة',
                    'settings.cron.create' => 'إضافة مهمة مجدولة',
                    'settings.cron.update' => 'تعديل مهمة مجدولة',
                    'settings.cron.delete' => 'حذف مهمة مجدولة',
                    'settings.cron.run' => 'تشغيل مهمة مجدولة يدوياً',
                    'settings.cron.logs' => 'عرض سجلات تنفيذ المهام',
                ],
            ],

            'subscription' => [
                'label' => 'الاشتراك',
                'description' => 'باقة النظام وحدودها وتاريخ تجديدها.',
                'icon' => 'award',
                'controller' => 'Sitting/SubscriptionController',
                'permissions' => [
                    'settings.subscription.view' => 'عرض الاشتراك',
                    'settings.subscription.manage' => 'إدارة الاشتراك',
                ],
            ],

            'mail' => [
                'label' => 'البريد الصادر (SMTP)',
                'description' => 'إعدادات خادم البريد — يقرؤها النظام من قاعدة البيانات لا من ملف البيئة.',
                'icon' => 'mail',
                'controller' => 'Sitting/SmtpSettingsController',
                'permissions' => [
                    'smtp.view' => 'عرض إعدادات البريد الصادر',
                    'smtp.manage' => 'إدارة إعدادات البريد الصادر',
                ],
            ],

            'sms' => [
                'label' => 'الرسائل النصية',
                'description' => 'مزوّدو الرسائل النصية ومفاتيحهم واختبار الإرسال.',
                'icon' => 'message-square',
                'controller' => 'Sitting/SmsSettingsController',
                'permissions' => [
                    'sms.view' => 'عرض إعدادات الرسائل النصية',
                    'sms.create' => 'إضافة مزوّد رسائل نصية',
                    'sms.edit' => 'تعديل مزوّد رسائل نصية',
                    'sms.delete' => 'حذف مزوّد رسائل نصية',
                    'sms.test' => 'اختبار إرسال رسالة نصية',
                ],
            ],

            'integrations' => [
                'label' => 'التكاملات الخارجية',
                'description' => 'ربط النظام بخدمات خارجية، وسجلّ ما جرى في كل عملية ربط.',
                'icon' => 'link',
                'controller' => 'Sitting/IntegrationsController',
                'permissions' => [
                    'integrations.view' => 'عرض التكاملات',
                    'integrations.create' => 'إضافة تكامل',
                    'integrations.edit' => 'تعديل تكامل',
                    'integrations.delete' => 'حذف تكامل',
                    'integrations.test' => 'اختبار التكامل',
                    'integration_logs.view' => 'عرض سجلات التكامل',
                ],
            ],

            'api_keys' => [
                'label' => 'مفاتيح الـAPI (عمليات خطرة)',
                'description' => 'مفاتيح الوصول البرمجي — المفتاح يتجاوز شاشة الدخول، وإبطاله يقطع كل تكاملٍ يستعمله فوراً.',
                'icon' => 'code',
                'controller' => 'Sitting/ApiKeysController',
                'permissions' => [
                    'api_keys.view' => 'عرض مفاتيح الـAPI',
                    'api_keys.create' => 'إنشاء مفتاح API',
                    'api_keys.edit' => 'تعديل مفتاح API',
                    'api_keys.revoke' => 'إبطال مفتاح API',
                    'api_keys.delete' => 'حذف مفتاح API',
                ],
            ],
        ];
    }
}
