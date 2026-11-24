<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات وحدة إدارة المهام — المصدر الوحيد لأسمائها.
 *
 * **لماذا كتالوج مستقل؟**
 *
 * لم تكن في الوحدة كلّها صلاحيةٌ واحدة: تسعة متحكّمات — مساحات العمل
 * والمشاريع والمهام والسبرنتات والتعليقات والتقارير والإعدادات — بلا
 * `permission.role` على مسارٍ ولا `hasPermission` في متحكّم. أيّ مستخدمٍ
 * مسجَّلٍ يحذف مشروعاً أو يقفل سبرنتاً أو يقرأ تقارير أداء الفريق كلّه.
 *
 * النمط نقطي `tasks.projects.delete` كما في [StockPermissions]
 * و[SalesPermissions]، لا بنمط الشرطة السفلية القديم.
 *
 * **لا يخلط بين نظامَي صلاحيات**: في `App\Helpers\TaskSettingsHelper`
 * «أدوارٌ» داخل المشروع (مدير/عضو/مراقب) محفوظةٌ في `task_settings`.
 * تلك تقول «ما دور فلانٍ في هذا المشروع»، وهذه تقول «هل يملك المستخدم
 * هذا الفعل في النظام». الثانية تُفحص أولاً ولا تُغني عنها الأولى.
 *
 * **ما يميّز هذه الوحدة**: `TaskController::store()` و`update()` يخدمان
 * ستة أنواع مهامّ بمستوياتٍ مختلفة الخطورة — المكوّن والإبيك يرسمان هيكل
 * المشروع، والمهمة والخطأ عملٌ يومي. فالميدل وير على المسار لا يميّز
 * بينها، ولذلك فُصلت `tasks.items.create_structure` عن
 * `tasks.items.create` وتُفحص داخل المتحكّم وفق `type` الفعلي في الطلب —
 * نفس علاج [\Modules\Stock\Http\Controllers\Stock\StorePermitsManagementController].
 *
 * **التعليقات مِلك كاتبها**: فُصلت `comments.edit_any` و`comments.delete_any`
 * عن `comments.edit_own` و`comments.delete_own` — عضو الفريق يصحّح تعليقه
 * ولا يمحو تعليق غيره، ومن يراجع الفريق يحتاج الاثنين.
 */
class TaskManagerPermissions extends BasePermissionCatalog
{
    /** بادئة أسماء صلاحيات هذه الوحدة. */
    public const PREFIX = 'tasks.';

    public static function module(): array
    {
        return [
            'key' => 'taskmanager',
            'label' => 'إدارة المهام',
            'description' => 'مساحات العمل والمشاريع والمهام والسبرنتات والتعليقات وتقارير الإنجاز وإعدادات الوحدة.',
            'icon' => 'check-square',
            'order' => 65,
        ];
    }

    /**
     * صلاحيات العمليات مجموعةً بالشاشة.
     *
     * @return array<string, array{label: string, description: string, icon?: string, controller?: string, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            // ═══════════════ مساحات العمل ═══════════════
            'workspaces' => [
                'label' => 'مساحات العمل',
                'description' => 'الوعاء الأعلى الذي تنتمي إليه المشاريع — حذف مساحةٍ يسحب معه كل ما تحتها.',
                'icon' => 'layers',
                'controller' => 'TaskManager/WorkspaceController',
                'permissions' => [
                    'tasks.workspaces.view' => 'عرض مساحات العمل',
                    'tasks.workspaces.show' => 'عرض تفاصيل مساحة عمل',
                    'tasks.workspaces.create' => 'إنشاء مساحة عمل',
                    'tasks.workspaces.edit' => 'تعديل مساحة عمل',
                    'tasks.workspaces.delete' => 'حذف مساحة عمل',
                ],
            ],

            // ═══════════════ المشاريع ═══════════════
            'projects' => [
                'label' => 'المشاريع',
                'description' => 'بطاقة المشروع: بياناته ومنهجيته (سكرم أو يدوي) ومدّته.',
                'icon' => 'folder',
                'controller' => 'TaskManager/ProjectController',
                'permissions' => [
                    'tasks.projects.view' => 'عرض المشاريع',
                    'tasks.projects.show' => 'عرض تفاصيل مشروع',
                    'tasks.projects.create' => 'إنشاء مشروع',
                    'tasks.projects.edit' => 'تعديل مشروع',
                    'tasks.projects.delete' => 'حذف مشروع',
                ],
            ],

            /*
                نطاق الرؤية — لا فعلٌ على شاشة.

                الصلاحيات أعلاه وأدناه تقول «ماذا يفعل»، وهذه تقول «على
                أيّ مشاريع»: من لا يملكها يرى ما أنشأه وما هو عضوٌ فيه
                وحده، ودوره داخل المشروع (مدير/عضو/مراقب) يحدّ كتابته.
                تُفحص في [\Modules\TaskManager\Services\ProjectAccess].
            */
            'projects_scope' => [
                'label' => 'نطاق الرؤية',
                'description' => 'من يملكها يرى كل المشاريع ومساحات العمل ويديرها، لا ما هو عضوٌ فيه فقط.',
                'icon' => 'eye',
                'controller' => 'TaskManager/ProjectController',
                'permissions' => [
                    'tasks.projects.view_all' => 'الاطّلاع على كل المشاريع ومساحات العمل وإدارتها',
                ],
            ],

            'project_members' => [
                'label' => 'أعضاء المشروع',
                'description' => 'دعوة المستخدمين إلى المشروع — من يدخل المشروع يرى كل مهامّه. والدعوة لمدير المشروع ممّن يملكون هذه الصلاحية.',
                'icon' => 'user-plus',
                'controller' => 'TaskManager/ProjectController',
                'permissions' => [
                    'tasks.projects.invite' => 'دعوة عضو إلى مشروع',
                ],
            ],

            // ═══════════════ لوحات العمل ═══════════════
            'board' => [
                'label' => 'لوحة المهام ولوحة المعلومات',
                'description' => 'لوحة كانبان وقائمة الانتظار ولوحة مؤشّرات المشروع.',
                'icon' => 'trello',
                'controller' => 'TaskManager/TaskManagerController',
                'permissions' => [
                    'tasks.board.view' => 'عرض لوحة المهام',
                    'tasks.board.dashboard' => 'عرض لوحة معلومات المهام',
                    'tasks.board.backlog' => 'عرض قائمة الانتظار',
                ],
            ],

            // ═══════════════ المهام ═══════════════
            'items' => [
                'label' => 'المهام',
                'description' => 'إنشاء المهام وتعديلها وحذفها وتغيير حالتها وترتيبها.',
                'icon' => 'check-square',
                'controller' => 'TaskManager/TaskController',
                'permissions' => [
                    'tasks.items.view' => 'عرض المهام',
                    'tasks.items.show' => 'عرض تفاصيل مهمة',
                    'tasks.items.create' => 'إنشاء مهمة أو خطأ برمجي',
                    'tasks.items.edit' => 'تعديل مهمة',
                    'tasks.items.delete' => 'حذف مهمة',
                    'tasks.items.update_status' => 'تغيير حالة مهمة',
                    'tasks.items.reorder' => 'إعادة ترتيب المهام',
                ],
            ],

            'items_structure' => [
                'label' => 'هيكل المشروع (مكوّنات وإبيكات وقصص)',
                'description' => 'المكوّن والإبيك وقصّة المستخدم ترسم هيكل المشروع لا عملَه اليومي — ومن يغيّرها يعيد ترتيب خطّة الفريق كلّها.',
                'icon' => 'git-branch',
                'controller' => 'TaskManager/TaskController',
                'permissions' => [
                    'tasks.items.create_structure' => 'إنشاء مكوّن أو إبيك أو قصّة مستخدم',
                ],
            ],

            // ═══════════════ التعليقات ═══════════════
            'comments' => [
                'label' => 'التعليقات',
                'description' => 'نقاش المهمة — والتفريق بين تعليق المستخدم نفسه وتعليق غيره.',
                'icon' => 'message-square',
                'controller' => 'TaskManager/CommentController',
                'permissions' => [
                    'tasks.comments.view' => 'عرض التعليقات',
                    'tasks.comments.create' => 'إضافة تعليق',
                    'tasks.comments.edit_own' => 'تعديل تعليقه هو',
                    'tasks.comments.edit_any' => 'تعديل تعليق أي مستخدم',
                    'tasks.comments.delete_own' => 'حذف تعليقه هو',
                    'tasks.comments.delete_any' => 'حذف تعليق أي مستخدم',
                ],
            ],

            // ═══════════════ السبرنتات ═══════════════
            'sprints' => [
                'label' => 'السبرنتات',
                'description' => 'دورات العمل: إنشاؤها وضمّ المهام إليها.',
                'icon' => 'repeat',
                'controller' => 'TaskManager/SprintController',
                'permissions' => [
                    'tasks.sprints.view' => 'عرض السبرنتات',
                    'tasks.sprints.show' => 'عرض تفاصيل سبرنت',
                    'tasks.sprints.create' => 'إنشاء سبرنت',
                    'tasks.sprints.edit' => 'تعديل سبرنت',
                    'tasks.sprints.delete' => 'حذف سبرنت',
                    'tasks.sprints.manage_tasks' => 'ضمّ المهام إلى سبرنت وإخراجها منه',
                ],
            ],

            'sprints_lifecycle' => [
                'label' => 'دورة حياة السبرنت (عمليات خطرة)',
                'description' => 'بدء السبرنت وإقفاله وإعادة فتحه — الإقفال يُثبّت قياس الأداء (والمهامّ غير المنجزة تبقى فيه)، وإعادة الفتح تنقضه.',
                'icon' => 'alert-triangle',
                'controller' => 'TaskManager/SprintController',
                'permissions' => [
                    'tasks.sprints.change_status' => 'تغيير حالة السبرنت',
                    'tasks.sprints.close' => 'إقفال سبرنت',
                    'tasks.sprints.reopen' => 'إعادة فتح سبرنت مقفَل',
                ],
            ],

            // ═══════════════ التقارير ═══════════════
            'reports' => [
                'label' => 'التقارير',
                'description' => 'تقارير المهام والمشاريع ومساحات العمل وأداء الفرق.',
                'icon' => 'bar-chart-2',
                'controller' => 'TaskManager/ReportsController',
                'permissions' => [
                    'tasks.reports.view' => 'عرض تقارير المهام',
                    'tasks.reports.performance' => 'عرض تقارير أداء الفرق',
                    'tasks.reports.export' => 'تصدير تقارير المهام',
                ],
            ],

            // ═══════════════ الإعدادات ═══════════════
            'settings' => [
                'label' => 'إعدادات المهام',
                'description' => 'إعدادات المشروع الواحد (الفريق والسبرنت والتنبيهات).',
                'icon' => 'sliders',
                'controller' => 'TaskManager/TaskSettingsController',
                'permissions' => [
                    'tasks.settings.view' => 'عرض إعدادات مشروع',
                    'tasks.settings.edit' => 'تعديل إعدادات مشروع',
                ],
            ],

            'settings_global' => [
                'label' => 'الإعدادات العامّة للمهام',
                'description' => 'الحالات والأنواع والأولويات الافتراضية لكل المشاريع — تُدار من لوحة إعدادات النظام.',
                'icon' => 'settings',
                'controller' => 'TaskManager/Settings/GlobalSettingsController',
                'permissions' => [
                    'tasks.settings.global.view' => 'عرض الإعدادات العامّة للمهام',
                    'tasks.settings.global.edit' => 'تعديل الإعدادات العامّة للمهام',
                ],
            ],
        ];
    }

    /**
     * صلاحية إنشاء كل نوع مهمّة — تُفحص داخل `TaskController::store()`
     * و`update()` وفق `type` الفعلي في الطلب، إذ يخدم إجراءٌ واحدٌ ستة
     * أنواع ولا تميّز بينها الميدل وير على مستوى المسار.
     *
     * @return array<string, string>
     */
    public static function createPermissionByType(): array
    {
        return [
            'component' => 'tasks.items.create_structure',
            'epic' => 'tasks.items.create_structure',
            'user_story' => 'tasks.items.create_structure',
            'task' => 'tasks.items.create',
            'sub_task' => 'tasks.items.create',
            'bug' => 'tasks.items.create',
        ];
    }
}
