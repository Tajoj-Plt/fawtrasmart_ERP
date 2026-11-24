<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات وحدة المخزون والتصنيع — المصدر الوحيد لأسمائها.
 *
 * **لماذا كتالوج مستقل؟**
 *
 * حماية الوحدة اليوم أسماءٌ من النظام القديم بنمط الشرطة السفلية موزَّعة
 * على بضعة متحكّمات، بينما تسعةٌ وثلاثون متحكّماً في الوحدة — الجرد
 * والأذون والمستودعات والتصنيع والتقارير — أغلبها بلا حراسة.
 *
 * النمط هنا نقطي `stock.permits.approve` كما في [SalesPermissions]
 * و[AccountPermissions]، ولا يتصادم مع الأسماء القديمة فيتعايش النظامان
 * أثناء الانتقال.
 *
 * **محورا الصلاحية منفصلان**: العملية هنا، والنطاق في
 * [DataScopePermissions] — فلا يُضرَب كل فعل في كل نطاق.
 *
 * **ما يميّز هذه الوحدة**: الفعل الذي يغيّر رصيداً ليس كالفعل الذي يعرضه.
 * إذنٌ مخزني لا يمسّ المخزون حتى يُعتمَد، ولذلك فُصلت `permits.approve`
 * و`permits.reject` و`permits.cancel_approval` عن `permits.create` —
 * فمن يحرّر الإذن ليس بالضرورة من يعتمده، وهذا أصل الضبط الداخلي.
 * وكذلك فُصل `adjustments.approve` عن إنشاء الجرد، و`orders.finish` عن
 * إنشاء أمر التصنيع.
 *
 * **العمليات الخطرة معزولة**: توليد الأرصدة الافتتاحية بالجملة لكل
 * المستودعات، والتسوية اليدوية لرصيد صنف — لكلٍّ صلاحيتها هي.
 */
class StockPermissions extends BasePermissionCatalog
{
    /** بادئة أسماء صلاحيات هذه الوحدة. */
    public const PREFIX = 'stock.';

    public static function module(): array
    {
        return [
            'key' => 'stock',
            'label' => 'المخزون والتصنيع',
            'description' => 'المنتجات والمستودعات والأذون المخزنية والجرد وأوامر التصنيع وتقارير الأرصدة.',
            'icon' => 'package',
            'order' => 40,
        ];
    }

    /**
     * صلاحيات العمليات مجموعةً بالشاشة.
     *
     * @return array<string, array{label: string, controller: string, permissions: array<string, string>}>
     */
    public static function groups(): array
    {
        return [
            // ═══════════════ المنتجات ═══════════════
            'products' => [
                'label' => 'المنتجات والأصناف',
                'description' => 'بطاقة الصنف كاملةً: بياناته وتكلفته وحركاته واستيراده وتصديره.',
                'icon' => 'package',
                'controller' => 'Stock/ProductsController',
                'permissions' => [
                    'stock.products.view' => 'عرض المنتجات',
                    'stock.products.show' => 'عرض تفاصيل منتج',
                    'stock.products.edit' => 'تعديل منتج',
                    'stock.products.delete' => 'حذف منتج',
                    'stock.products.bulk_delete' => 'حذف منتجات بالجملة',
                    'stock.products.toggle_status' => 'تفعيل المنتج وتعطيله',
                    'stock.products.services' => 'إدارة الخدمات',
                    'stock.products.compiled' => 'إدارة المنتجات المجمَّعة',
                    'stock.products.movements' => 'عرض حركات المنتج',
                    'stock.products.timeline' => 'عرض الخطّ الزمني وسجلّ النشاط',
                    'stock.products.cost' => 'عرض تكلفة المنتج وهامش ربحه',
                    'stock.products.import' => 'استيراد المنتجات',
                    'stock.products.export' => 'تصدير المنتجات',
                ],
            ],

            'products_stock' => [
                'label' => 'تسوية رصيد الصنف (عملية خطرة)',
                'description' => 'تعديل رصيد صنفٍ بلا مستندٍ يبرّره — يلتفّ على الأذون والجرد معاً.',
                'icon' => 'alert-triangle',
                'controller' => 'Stock/ProductsController',
                'permissions' => [
                    'stock.products.manual_adjust' => 'تعديل رصيد صنف يدوياً',
                ],
            ],

            'barcodes' => [
                'label' => 'الباركود',
                'description' => 'توليد الباركود وطباعته وضبط قوالبه.',
                'icon' => 'maximize',
                'controller' => 'Stock/ProductsController + BarcodeSettingsController',
                'permissions' => [
                    'stock.barcodes.print' => 'طباعة الباركود',
                    'stock.barcodes.download' => 'تنزيل صورة الباركود',
                    'stock.barcodes.settings' => 'إدارة إعدادات الباركود',
                ],
            ],

            // ═══════════════ التصنيفات والبيانات المرجعية ═══════════════
            'categories' => [
                'label' => 'التصنيفات',
                'description' => 'تصنيف المنتجات وربط كل تصنيفٍ بوحداته.',
                'icon' => 'folder',
                'controller' => 'Stock/CategoryController + CategoryUnitController',
                'permissions' => [
                    'stock.categories.view' => 'عرض التصنيفات',
                    'stock.categories.create' => 'إنشاء تصنيف',
                    'stock.categories.edit' => 'تعديل تصنيف',
                    'stock.categories.delete' => 'حذف تصنيف',
                    'stock.categories.toggle_status' => 'تفعيل التصنيف وتعطيله',
                ],
            ],

            'brands' => [
                'label' => 'العلامات التجارية',
                'description' => 'العلامات التجارية للمنتجات.',
                'icon' => 'award',
                'controller' => 'Stock/BrandController + BrandApiController',
                'permissions' => [
                    'stock.brands.view' => 'عرض العلامات التجارية',
                    'stock.brands.create' => 'إنشاء علامة تجارية',
                    'stock.brands.edit' => 'تعديل علامة تجارية',
                    'stock.brands.delete' => 'حذف علامة تجارية',
                    'stock.brands.toggle_status' => 'تفعيل العلامة وتمييزها',
                ],
            ],

            'units' => [
                'label' => 'الوحدات',
                'description' => 'وحدات القياس ومعاملات التحويل بينها.',
                'icon' => 'box',
                'controller' => 'Stock/UnitController',
                'permissions' => [
                    'stock.units.view' => 'عرض الوحدات',
                    'stock.units.create' => 'إنشاء وحدة',
                    'stock.units.edit' => 'تعديل وحدة',
                    'stock.units.delete' => 'حذف وحدة',
                    'stock.units.toggle_status' => 'تفعيل الوحدة وتعطيلها',
                ],
            ],

            'product_groups' => [
                'label' => 'مجموعات المنتجات',
                'description' => 'مجموعات المنتجات المستعملة في التسعير والتقارير.',
                'icon' => 'grid',
                'controller' => 'Stock/ProductGroupController',
                'permissions' => [
                    'stock.product_groups.view' => 'عرض مجموعات المنتجات',
                    'stock.product_groups.create' => 'إنشاء مجموعة',
                    'stock.product_groups.edit' => 'تعديل مجموعة',
                    'stock.product_groups.delete' => 'حذف مجموعة',
                    'stock.product_groups.toggle_status' => 'تفعيل المجموعة وتعطيلها',
                ],
            ],

            // ═══════════════ قوائم الأسعار ═══════════════
            'price_lists' => [
                'label' => 'قوائم الأسعار',
                'description' => 'قوائم الأسعار وربطها بالعملاء والفروع.',
                'icon' => 'tag',
                'controller' => 'Stock/PriceListController',
                'permissions' => [
                    'stock.price_lists.view' => 'عرض قوائم الأسعار',
                    'stock.price_lists.show' => 'عرض تفاصيل قائمة أسعار',
                    'stock.price_lists.create' => 'إنشاء قائمة أسعار',
                    'stock.price_lists.edit' => 'تعديل قائمة أسعار',
                    'stock.price_lists.delete' => 'حذف قائمة أسعار',
                    'stock.price_lists.manage_products' => 'إضافة الأصناف إلى القائمة وحذفها',
                    'stock.price_lists.import' => 'استيراد قائمة أسعار',
                    'stock.price_lists.export' => 'تصدير قائمة أسعار',
                ],
            ],

            // ═══════════════ المستودعات ═══════════════
            'warehouses' => [
                'label' => 'المستودعات',
                'description' => 'المستودعات وبياناتها والموظفون المسؤولون عنها.',
                'icon' => 'home',
                'controller' => 'Stock/StorehouseController',
                'permissions' => [
                    'stock.warehouses.view' => 'عرض المستودعات',
                    'stock.warehouses.show' => 'عرض تفاصيل مستودع',
                    'stock.warehouses.create' => 'إنشاء مستودع',
                    'stock.warehouses.edit' => 'تعديل مستودع',
                    'stock.warehouses.delete' => 'حذف مستودع',
                    'stock.warehouses.toggle_status' => 'تفعيل المستودع وتعطيله',
                ],
            ],

            'warehouses_opening' => [
                'label' => 'الأرصدة الافتتاحية للمستودعات (عملية خطرة)',
                'description' => 'توليد أرصدة افتتاحية بالجملة لكل المستودعات — يُنفَّذ مرةً عند التأسيس.',
                'icon' => 'alert-triangle',
                'controller' => 'Stock/StorehouseController',
                'permissions' => [
                    'stock.warehouses.opening_balance' => 'توليد الأرصدة الافتتاحية بالجملة',
                ],
            ],

            // ═══════════════ الأذون المخزنية ═══════════════
            'permits' => [
                'label' => 'الأذون المخزنية',
                'description' => 'أذون الإضافة والصرف والسحب والتحويل — لا تمسّ الرصيد قبل اعتمادها.',
                'icon' => 'file-text',
                'controller' => 'Stock/StorePermitsManagementController',
                'permissions' => [
                    'stock.permits.view' => 'عرض الأذون المخزنية',
                    'stock.permits.show' => 'عرض تفاصيل إذن',
                    'stock.permits.create_addition' => 'إنشاء إذن إضافة',
                    'stock.permits.create_disbursement' => 'إنشاء إذن صرف',
                    'stock.permits.create_transfer' => 'إنشاء إذن تحويل',
                    'stock.permits.create_opening' => 'إنشاء إذن رصيد افتتاحي',
                    'stock.permits.edit' => 'تعديل إذن مخزني',
                    'stock.permits.delete' => 'حذف إذن مخزني',
                    'stock.permits.print' => 'طباعة الإذن',
                    'stock.permits.import' => 'استيراد الأذون',
                    'stock.permits.export' => 'تصدير الأذون',
                ],
            ],

            // اعتماد الإذن هو ما يحرّك المخزون ويكتب القيد — يُفصل عن تحريره
            'permits_approval' => [
                'label' => 'اعتماد الأذون المخزنية',
                'description' => 'اعتماد الأذون ورفضها وإلغاء اعتمادها — مفصولةٌ عن تحريرها كي لا يعتمد المحرّر إذنه.',
                'icon' => 'check-circle',
                'controller' => 'Stock/StorePermitsManagementController',
                'permissions' => [
                    'stock.permits.approve' => 'اعتماد الإذن المخزني',
                    'stock.permits.reject' => 'رفض الإذن المخزني',
                    'stock.permits.cancel_approval' => 'إلغاء اعتماد إذن معتمَد',
                ],
            ],

            // ═══════════════ الجرد ═══════════════
            'adjustments' => [
                'label' => 'الجرد وتسوياته',
                'description' => 'أوراق الجرد وفروقه وتسوياته.',
                'icon' => 'clipboard',
                'controller' => 'Stock/InventoryAdjustmentController + InventoryManagementController',
                'permissions' => [
                    'stock.adjustments.view' => 'عرض عمليات الجرد',
                    'stock.adjustments.show' => 'عرض تفاصيل جرد',
                    'stock.adjustments.create' => 'إنشاء عملية جرد',
                    'stock.adjustments.edit' => 'تعديل عملية جرد',
                    'stock.adjustments.delete' => 'حذف عملية جرد',
                    'stock.adjustments.count' => 'إدخال كميات الجرد',
                    'stock.adjustments.export' => 'تصدير الجرد',
                ],
            ],

            'adjustments_approval' => [
                'label' => 'اعتماد فروق الجرد',
                'description' => 'اعتماد فروق الجرد — هنا يُكتب الفرق على الرصيد فعلاً.',
                'icon' => 'check-circle',
                'controller' => 'Stock/InventoryManagementController',
                'permissions' => [
                    'stock.adjustments.approve' => 'اعتماد فروق الجرد وترحيلها',
                    'stock.adjustments.cancel' => 'إلغاء تسوية جرد معتمَدة',
                ],
            ],

            // ═══════════════ حركة مخزون الموظفين ═══════════════
            'employee_stock' => [
                'label' => 'حركة مخزون الموظفين',
                'description' => 'مخزون العهدة لدى الموظفين وحركته.',
                'icon' => 'user',
                'controller' => 'EmployeeStockMovementController',
                'permissions' => [
                    'stock.employee_stock.view' => 'عرض حركة مخزون الموظفين',
                ],
            ],

            // ═══════════════ التصنيع ═══════════════
            'manufacturing_orders' => [
                'label' => 'أوامر التصنيع',
                'description' => 'أوامر التصنيع من فتحها إلى تنفيذها.',
                'icon' => 'tool',
                'controller' => 'Manufacturing/ManufacturingOrdersController + OrdersController',
                'permissions' => [
                    'stock.manufacturing_orders.view' => 'عرض أوامر التصنيع',
                    'stock.manufacturing_orders.show' => 'عرض تفاصيل أمر تصنيع',
                    'stock.manufacturing_orders.create' => 'إنشاء أمر تصنيع',
                    'stock.manufacturing_orders.edit' => 'تعديل أمر تصنيع',
                    'stock.manufacturing_orders.delete' => 'حذف أمر تصنيع',
                    'stock.manufacturing_orders.notes' => 'إدارة ملاحظات أمر التصنيع',
                    'stock.manufacturing_orders.cost_breakdown' => 'عرض تفصيل تكلفة الأمر',
                    'stock.manufacturing_orders.receive_materials' => 'استلام مواد للأمر',
                    'stock.manufacturing_orders.return_materials' => 'إرجاع مواد من الأمر',
                ],
            ],

            'manufacturing_completion' => [
                'label' => 'إنهاء أوامر التصنيع وإقفالها',
                'description' => 'إنهاء أمر التصنيع وإقفاله — بعده يدخل المنتج المخزون.',
                'icon' => 'check-square',
                'controller' => 'Manufacturing/ManufacturingOrdersController',
                'permissions' => [
                    'stock.manufacturing_orders.finish' => 'إنهاء أمر التصنيع',
                    'stock.manufacturing_orders.undo_finish' => 'التراجع عن إنهاء أمر',
                    'stock.manufacturing_orders.close' => 'إقفال أمر التصنيع',
                    'stock.manufacturing_orders.reopen' => 'إعادة فتح أمر مقفَل',
                    'stock.manufacturing_orders.cancel' => 'إلغاء أمر التصنيع',
                ],
            ],

            'bom' => [
                'label' => 'قوائم مواد الإنتاج',
                'description' => 'قوائم مواد الإنتاج ومكوّنات كل منتج.',
                'icon' => 'list',
                'controller' => 'Manufacturing/BOMController',
                'permissions' => [
                    'stock.bom.view' => 'عرض قوائم المواد',
                    'stock.bom.show' => 'عرض تفاصيل قائمة مواد',
                    'stock.bom.create' => 'إنشاء قائمة مواد',
                    'stock.bom.edit' => 'تعديل قائمة مواد',
                    'stock.bom.delete' => 'حذف قائمة مواد',
                    'stock.bom.cost' => 'عرض التكلفة المعياريّة',
                ],
            ],

            'indirect_costs' => [
                'label' => 'التكاليف غير المباشرة',
                'description' => 'التكاليف غير المباشرة وتوزيعها على أوامر التصنيع.',
                'icon' => 'percent',
                'controller' => 'Manufacturing/IndirectCostsController',
                'permissions' => [
                    'stock.indirect_costs.view' => 'عرض التكاليف غير المباشرة',
                    'stock.indirect_costs.show' => 'عرض تفاصيل تكلفة',
                    'stock.indirect_costs.create' => 'إضافة تكلفة غير مباشرة',
                    'stock.indirect_costs.edit' => 'تعديل تكلفة غير مباشرة',
                    'stock.indirect_costs.delete' => 'حذف تكلفة غير مباشرة',
                    'stock.indirect_costs.export' => 'تصدير التكاليف',
                ],
            ],

            'workstations' => [
                'label' => 'محطات العمل',
                'description' => 'محطات العمل وطاقتها الإنتاجية.',
                'icon' => 'cpu',
                'controller' => 'Manufacturing/WorkstationsController',
                'permissions' => [
                    'stock.workstations.view' => 'عرض محطات العمل',
                    'stock.workstations.show' => 'عرض تفاصيل محطة',
                    'stock.workstations.create' => 'إنشاء محطة عمل',
                    'stock.workstations.edit' => 'تعديل محطة عمل',
                    'stock.workstations.delete' => 'حذف محطة عمل',
                ],
            ],

            /*
                خطط الإنتاج — الطبقةُ فوق أوامر التصنيع.

                `approve` و`generate_orders` مفصولتان عن `create` و`edit`
                على القاعدة نفسِها التي فُصلت بها `permits.approve` عن
                `permits.create`: من يكتب الخطّة ليس بالضرورة من يقرّ
                تحويلها إلى أوامرَ تحجز المواد وتستهلك عدّادَ الباقة.
                والتوليدُ هو الفعلُ الوحيد هنا الذي يُنشئ مستنداتٍ خارجها.
            */
            'production_plans' => [
                'label' => 'خطط الإنتاج',
                'description' => 'خطط الإنتاج وبنودها، وتوليدُ أوامر التصنيع منها.',
                'icon' => 'calendar',
                'controller' => 'Manufacturing/ProductionPlansController',
                'permissions' => [
                    'stock.production_plans.view' => 'عرض خطط الإنتاج',
                    'stock.production_plans.show' => 'عرض تفاصيل خطة إنتاج',
                    'stock.production_plans.create' => 'إنشاء خطة إنتاج',
                    'stock.production_plans.edit' => 'تعديل خطة إنتاج',
                    'stock.production_plans.delete' => 'حذف خطة إنتاج',
                    'stock.production_plans.approve' => 'اعتماد خطة إنتاج وإلغاؤها',
                    'stock.production_plans.generate_orders' => 'توليد أوامر التصنيع من الخطة',
                ],
            ],

            'production_paths' => [
                'label' => 'مسارات الإنتاج',
                'description' => 'مسارات الإنتاج وترتيب محطاتها.',
                'icon' => 'git-branch',
                'controller' => 'Manufacturing/SettingsController',
                'permissions' => [
                    'stock.production_paths.view' => 'عرض مسارات الإنتاج',
                    'stock.production_paths.create' => 'إنشاء مسار إنتاج',
                    'stock.production_paths.edit' => 'تعديل مسار إنتاج',
                    'stock.production_paths.delete' => 'حذف مسار إنتاج',
                ],
            ],

            // ═══════════════ الإعدادات ═══════════════
            'settings' => [
                'label' => 'إعدادات المخزون والتصنيع',
                'description' => 'الإعدادات العامة للمخزون والمنتجات والتصنيع.',
                'icon' => 'settings',
                'controller' => 'Stock/InventorySettingsController + ProductsSettingsController + Manufacturing/SettingsController',
                'permissions' => [
                    'stock.settings.view' => 'عرض إعدادات المخزون',
                    'stock.settings.general' => 'إدارة الإعدادات العامة للمخزون',
                    'stock.settings.products' => 'إدارة إعدادات المنتجات والضرائب الافتراضية',
                    'stock.settings.employee_warehouse' => 'إدارة المستودع الافتراضي للموظف',
                    'stock.settings.manufacturing' => 'إدارة إعدادات التصنيع',
                ],
            ],

            // ═══════════════ التقارير ═══════════════
            'reports' => [
                'label' => 'تقارير المخزون',
                'description' => 'تقارير الأرصدة والحركة والتقييم — ولكل تقريرٍ صلاحيته.',
                'icon' => 'bar-chart-2',
                'controller' => 'Reports/*',
                'permissions' => [
                    'stock.reports.view' => 'الدخول إلى تقارير المخزون',
                    'stock.reports.balance' => 'رصيد المخزون',
                    'stock.reports.warehouse_balance' => 'رصيد المستودعات',
                    'stock.reports.summary_movement' => 'ملخّص حركة المخزون',
                    'stock.reports.detailed_movement' => 'الحركة التفصيلية للمخزون',
                    'stock.reports.movement_by_product' => 'حركة المخزون حسب الصنف',
                    'stock.reports.inventory_sheet' => 'ورقة الجرد',
                    'stock.reports.value' => 'تقييم المخزون',
                    'stock.reports.damaged' => 'تقرير الهالك والتالف',
                    'stock.reports.trial_balance' => 'ميزان مراجعة المخزون',
                    'stock.reports.warehouse_statement' => 'كشف حساب المستودع',
                    'stock.reports.employee_statement' => 'كشف حساب مخزون الموظف',
                    'stock.reports.manufacturing' => 'تقارير التصنيع',
                    'stock.reports.export' => 'تصدير تقارير المخزون',
                    'stock.reports.print' => 'طباعة تقارير المخزون',
                ],
            ],

            // النظام القديم بكل مجموعاته حُذف 2026-08-24 — آخرها `legacy_permits`
            // (كانت تحرس Modules\OnlineStore\Http\Controllers\StorePermitsController
            // عبر `inv_manage_inventory_permission_view/add/edit`؛ حُوِّل ذلك
            // المتحكّم الآن إلى `permits`/`permits_approval` أعلاه، نفس ما
            // تستعمله Stock/StorePermitsManagementController).
        ];
    }
}
