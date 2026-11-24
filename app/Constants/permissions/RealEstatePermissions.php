<?php

namespace App\Constants\permissions;

/**
 * صلاحيات إدارة العقارات (`Modules/RentalManagement` — قسم `realestate`).
 *
 * قسمٌ مستقلّ وظيفياً عن «الإيجارات» القديمة (أوامر حجز الوحدات الفندقية)
 * التي تبقى صلاحياتها الثلاث في `OperationsPermissions` كما هي — فلا يتكرّر
 * اسمٌ بين الكتالوجين ولا تتغيّر أدوارٌ قائمة.
 *
 * المجموعات على مقاس الأدوار الستة في وثيقة التصميم: مدير العقارات، مسؤول
 * التأجير، مسؤول العقود، المحصّل/المحاسب، مسؤول الصيانة، مستخدم القراءة —
 * و`Modules\RentalManagement\Support\RealEstateRoles` يبنيها من هذه الأسماء.
 *
 * نطاق البيانات (الفرع/الفريق/الخاصّ) ليس هنا: هو محور `data_scope_*` العامّ
 * في `DataScopePermissions`، ويسري على قوائم العقارات كما يسري على غيرها.
 */
class RealEstatePermissions extends BasePermissionCatalog
{
    /** بادئة أسماء صلاحيات هذه الوحدة. */
    public const PREFIX = 'realestate.';

    public static function module(): array
    {
        return [
            'key' => 'realestate',
            'label' => 'إدارة العقارات',
            'description' => 'المباني والوحدات والعروض العقارية والعقود والدفعات والصيانة والمتابعات وتقاريرها.',
            'icon' => 'home',
            'order' => 72,
        ];
    }

    public static function groups(): array
    {
        return [
            'dashboard' => [
                'label' => 'لوحة التحكم',
                'description' => 'مؤشرات الإشغال والتحصيل وما يحتاج انتباهاً.',
                'icon' => 'bar-chart-2',
                'controller' => 'RealEstate/DashboardController',
                'permissions' => [
                    'realestate.dashboard.view' => 'عرض لوحة تحكم العقارات',
                ],
            ],

            'buildings' => [
                'label' => 'المباني والمجمعات',
                'description' => 'الأصل العقاري: المجمع والمبنى وطوابقه وملّاكه.',
                'icon' => 'home',
                'controller' => 'RealEstate/BuildingsController',
                'permissions' => [
                    'realestate.buildings.view' => 'عرض المباني والمجمعات',
                    'realestate.buildings.show' => 'عرض تفاصيل مبنى',
                    'realestate.buildings.create' => 'إضافة مبنى أو مجمع',
                    'realestate.buildings.edit' => 'تعديل مبنى أو مجمع',
                    'realestate.buildings.delete' => 'حذف مبنى أو مجمع',
                    'realestate.buildings.complexes' => 'إدارة المجمعات (إضافة وتعديل وحذف)',
                ],
            ],

            'owners' => [
                'label' => 'الملّاك',
                'description' => 'ملّاك العقارات واتفاقيات إدارة أملاكهم (نسبة أو قيمة ثابتة) وحساباتهم الجارية وتحويل مستحقاتهم.',
                'icon' => 'user',
                'controller' => 'RealEstate/OwnersController',
                'permissions' => [
                    'realestate.owners.view' => 'عرض الملّاك',
                    'realestate.owners.show' => 'عرض ملفّ مالك وكشف حسابه',
                    'realestate.owners.create' => 'إضافة مالك',
                    'realestate.owners.edit' => 'تعديل مالك واتفاقيته',
                    'realestate.owners.delete' => 'حذف مالك',
                    'realestate.owners.transfer' => 'تحويل مستحقات المالك',
                    'realestate.owners.cancel_transfer' => 'إلغاء تحويل مستحقات المالك (قيد عكسي)',
                ],
            ],

            'units' => [
                'label' => 'الوحدات',
                'description' => 'الوحدات العقارية ومواصفاتها ومرافقها وحالة إشغالها.',
                'icon' => 'grid',
                'controller' => 'RealEstate/UnitsController',
                'permissions' => [
                    'realestate.units.view' => 'عرض الوحدات',
                    'realestate.units.show' => 'عرض تفاصيل وحدة',
                    'realestate.units.create' => 'إضافة وحدة',
                    'realestate.units.edit' => 'تعديل وحدة',
                    'realestate.units.delete' => 'حذف وحدة',
                    'realestate.units.change_status' => 'تغيير حالة إشغال الوحدة يدوياً',
                    'realestate.units.bulk_create' => 'إضافة وحدات بالجملة',
                ],
            ],

            'listings' => [
                'label' => 'العروض العقارية وطلبات العملاء',
                'description' => 'عروض الإيجار والبيع ونشرها واستفساراتها ومعايناتها، وطلبات العملاء الباحثين عن عقار.',
                'icon' => 'tag',
                'controller' => 'RealEstate/ListingsController',
                'permissions' => [
                    'realestate.listings.view' => 'عرض العروض العقارية',
                    'realestate.listings.show' => 'عرض تفاصيل عرض',
                    'realestate.listings.create' => 'إنشاء عرض',
                    'realestate.listings.edit' => 'تعديل عرض',
                    'realestate.listings.delete' => 'حذف عرض',
                    'realestate.listings.publish' => 'نشر العروض وإيقافها وإغلاقها',
                    'realestate.listings.viewings' => 'تسجيل الاستفسارات والمعاينات',
                ],
            ],

            'tenants' => [
                'label' => 'المستأجرون',
                'description' => 'تسجيل المستأجرين (أفراداً وشركات) وبياناتهم الشخصية وهوياتهم ومرفقاتهم.',
                'icon' => 'user-check',
                'controller' => 'RealEstate/TenantsController',
                'permissions' => [
                    'realestate.tenants.view' => 'عرض قائمة المستأجرين',
                    'realestate.tenants.show' => 'عرض ملفّ مستأجر',
                    'realestate.tenants.create' => 'تسجيل مستأجر',
                    'realestate.tenants.edit' => 'تعديل بيانات مستأجر',
                ],
            ],

            'relationships' => [
                'label' => 'اهتمامات العملاء',
                'description' => 'من يهتم بماذا: عميل مهتمّ بوحدة أو مبنى أو عرض (مستأجر محتمل، مشترٍ، مستثمر، وسيط) ومن يتابعه.',
                'icon' => 'users',
                'controller' => 'RealEstate/RelationshipsController',
                'permissions' => [
                    'realestate.relationships.view' => 'عرض اهتمامات العملاء',
                    'realestate.relationships.create' => 'إضافة اهتمام لعميل',
                    'realestate.relationships.edit' => 'تعديل اهتمام عميل',
                    'realestate.relationships.delete' => 'حذف اهتمام عميل',
                ],
            ],

            'contracts' => [
                'label' => 'العقود والتجديدات',
                'description' => 'عقود الإيجار والبيع ومسوداتها وتجديدها ومستنداتها.',
                'icon' => 'file-text',
                'controller' => 'RealEstate/ContractsController',
                'permissions' => [
                    'realestate.contracts.view' => 'عرض العقود',
                    'realestate.contracts.show' => 'عرض تفاصيل عقد',
                    'realestate.contracts.create' => 'إنشاء عقد',
                    'realestate.contracts.edit' => 'تعديل مسودة عقد',
                    'realestate.contracts.delete' => 'حذف مسودة عقد',
                    'realestate.contracts.renew' => 'إنشاء طلب تجديد عقد',
                    'realestate.contracts.print' => 'طباعة العقد',
                ],
            ],

            'contracts_control' => [
                'label' => 'اعتماد العقود وإنهاؤها (عمليات حسّاسة)',
                'description' => 'ما يغيّر التزاماً قائماً: الاعتماد، تعديل المعتمد، الإلغاء، الإنهاء، وتجاوز منع التداخل.',
                'icon' => 'alert-triangle',
                'controller' => 'RealEstate/ContractsController',
                'permissions' => [
                    'realestate.contracts.approve' => 'اعتماد العقود ورفضها',
                    'realestate.contracts.approve_renewal' => 'اعتماد تجديد العقود',
                    'realestate.contracts.amend' => 'تعديل عقد معتمد (مع سجلّ تعديل)',
                    'realestate.contracts.cancel' => 'إلغاء عقد',
                    'realestate.contracts.terminate' => 'إنهاء عقد وتسوية التأمين',
                    'realestate.contracts.override_overlap' => 'تجاوز منع تداخل العقود على الوحدة',
                ],
            ],

            'payments' => [
                'label' => 'الدفعات والاستحقاقات',
                'description' => 'جدول الاستحقاقات وتسجيل الدفعات وتوزيعها وترحيلها.',
                'icon' => 'credit-card',
                'controller' => 'RealEstate/PaymentsController',
                'permissions' => [
                    'realestate.payments.view' => 'عرض الدفعات',
                    'realestate.payments.schedules' => 'عرض جدول الاستحقاقات والمتأخرات',
                    'realestate.payments.show' => 'عرض تفاصيل دفعة وإيصالها',
                    'realestate.payments.create' => 'تسجيل دفعة',
                    'realestate.payments.cancel' => 'إلغاء دفعة مرحّلة (قيد عكسي)',
                    'realestate.payments.regenerate_schedule' => 'إعادة بناء جدول استحقاقات عقد',
                    'realestate.payments.settle_deposit' => 'تسوية التأمين وردّه للمستأجر',
                ],
            ],

            'maintenance' => [
                'label' => 'الصيانة',
                'description' => 'البلاغات وإسنادها ومواعيدها وتكاليفها وإغلاقها.',
                'icon' => 'tool',
                'controller' => 'RealEstate/MaintenanceController',
                'permissions' => [
                    'realestate.maintenance.view' => 'عرض طلبات الصيانة',
                    'realestate.maintenance.show' => 'عرض تفاصيل طلب صيانة',
                    'realestate.maintenance.create' => 'إنشاء طلب صيانة',
                    'realestate.maintenance.edit' => 'تعديل طلب صيانة',
                    'realestate.maintenance.delete' => 'حذف طلب صيانة',
                    'realestate.maintenance.assign' => 'إسناد الطلب وتغيير حالته',
                    'realestate.maintenance.costs' => 'تسجيل تكاليف الصيانة واعتمادها',
                ],
            ],

            'tasks' => [
                'label' => 'المهام والمتابعات',
                'description' => 'مهام الاتصال والمعاينة والتحصيل ولوحة «تحتاج تدخّلاً».',
                'icon' => 'check-square',
                'controller' => 'RealEstate/TasksController',
                'permissions' => [
                    'realestate.tasks.view' => 'عرض المهام والمتابعات',
                    'realestate.tasks.create' => 'إنشاء مهمة',
                    'realestate.tasks.edit' => 'تعديل مهمة وتغيير حالتها',
                    'realestate.tasks.delete' => 'حذف مهمة',
                ],
            ],

            'reports' => [
                'label' => 'التقارير',
                'description' => 'تقارير المحفظة والعقود والمالية والعروض والصيانة والملّاك.',
                'icon' => 'pie-chart',
                'controller' => 'RealEstate/ReportsController',
                'permissions' => [
                    'realestate.reports.view' => 'عرض التقارير التشغيلية',
                    'realestate.reports.financial' => 'عرض التقارير المالية وكشوف الحساب',
                    'realestate.reports.export' => 'تصدير التقارير وحفظ إعداداتها',
                ],
            ],

            'settings' => [
                'label' => 'إعدادات العقارات',
                'description' => 'الربط المحاسبي وسياسات العقود والتحصيل والمرافق.',
                'icon' => 'settings',
                'controller' => 'RealEstate/SettingsController',
                'permissions' => [
                    'realestate.settings.view' => 'عرض إعدادات العقارات',
                    'realestate.settings.edit' => 'تعديل إعدادات العقارات والسياسات',
                    'realestate.logs.view' => 'عرض سجلّ نشاط العقارات',
                ],
            ],
        ];
    }
}
