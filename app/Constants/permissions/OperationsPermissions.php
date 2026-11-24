<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات الوحدات التشغيلية الصغيرة.
 *
 * وحداتٌ لكلٍّ منها صلاحيةٌ أو ثلاث: الإيجارات، والمتجر الإلكتروني،
 * والطلبات، وإرسال الفواتير إلى هيئة الضرائب. إفرادُ ملفٍّ لكلٍّ منها يعطي
 * تسعة ملفاتٍ بسطرين، وتركُها بلا قسم يعيدها إلى ما كانت عليه: بادئاتٌ
 * لاتينية متناثرة في آخر الشاشة. فجُمعت في قسمٍ واحد بمجموعاتٍ مستقلة —
 * كل وحدةٍ مجموعةٌ باسمها.
 *
 * حين تكبر وحدةٌ منها ويصير لها كتالوجها، تُنقل مجموعتها إليه ولا يتغيّر
 * شيءٌ في الشاشات: التقسيم يُقرأ من جدول `permissions` بعد المزامنة.
 */
class OperationsPermissions extends BasePermissionCatalog
{
    public static function module(): array
    {
        return [
            'key' => 'operations',
            'label' => 'وحدات تشغيلية أخرى',
            'description' => 'الإيجارات، المتجر الإلكتروني، الطلبات، والفوترة الإلكترونية مع هيئة الضرائب.',
            'icon' => 'box',
            'order' => 70,
        ];
    }

    public static function groups(): array
    {
        return [
            'rental' => [
                'label' => 'الإيجارات',
                'description' => 'أوامر حجز الوحدات المؤجَّرة وإعدادات الإيجار.',
                'icon' => 'key',
                'controller' => 'RentalManagement/BookingOrdersController',
                'permissions' => [
                    'rental_unit_view_booking_orders' => 'عرض أوامر الحجز',
                    'rental_unit_manage_booking_orders' => 'إدارة أوامر الحجز',
                    'rental_unit_manage_rental_settings' => 'إدارة إعدادات الإيجارات',
                ],
            ],

            'online_store' => [
                'label' => 'المتجر الإلكتروني',
                'description' => 'محتوى المتجر المعروض للعملاء.',
                'icon' => 'globe',
                'controller' => 'OnlineStore/ContentController',
                'permissions' => [
                    'online_store_content_management' => 'إدارة محتوى المتجر الإلكتروني',
                ],
            ],

            'orders' => [
                'label' => 'الطلبات',
                'description' => 'طلبات العملاء الواردة من المتجر وإعداداتها.',
                'icon' => 'clipboard',
                'controller' => 'OnlineStore/OrdersController',
                'permissions' => [
                    'orders_management' => 'إدارة الطلبات',
                    'orders_setting_management' => 'إدارة إعدادات الطلبات',
                    'employee_orders_management' => 'إدارة طلبات الموظف',
                ],
            ],

            'e_invoicing' => [
                'label' => 'الفوترة الإلكترونية',
                'description' => 'إرسال الفواتير إلى هيئة الضرائب — إجراءٌ لا رجعة فيه بعد قبول الهيئة.',
                'icon' => 'send',
                'controller' => 'Sales/TaxAuthorityController',
                'permissions' => [
                    'sending_invoices_to_the_tax_authority' => 'إرسال الفواتير إلى هيئة الضرائب',
                ],
            ],
        ];
    }
}
