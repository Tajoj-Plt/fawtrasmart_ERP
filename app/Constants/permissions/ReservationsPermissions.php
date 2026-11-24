<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات وحدة الحجوزات — المصدر الوحيد لأسمائها.
 *
 * ─────────────────────── ما كان ───────────────────────
 *
 * **لا صلاحيةَ واحدة.** الوحدة كانت مفتوحةً لكل من يسجّل دخوله: مساراتُها
 * محروسةٌ بـ`auth` وحدَه، ولا `permission:` على مسارٍ منها. والقائمةُ
 * الجانبية تعترف بذلك في تعليقها صراحةً — «لا صلاحية معرَّفة لها في
 * القاعدة» — فتعرض الوحدةَ للجميع لأنّ `@can` بصلاحيةٍ غير موجودة يُخفيها
 * عن الجميع.
 *
 * فمن يفتح النظام يُلغي حجزَ عميلٍ ويُصدر فاتورةً ويقبض عربوناً ويردّه.
 *
 * ─────────────────────── مبدأ التقسيم ───────────────────────
 *
 * **ما يمسّ المال يُفصل عمّا يمسّ الموعد، وكلُّ انتقالٍ في سير العمل
 * يُفرد بصلاحيته.**
 *
 *   • `reservations.confirm` و`checkin` و`start` و`complete` أربعُ صلاحياتٍ
 *     لا واحدة: موظّفُ الاستقبال يسجّل الحضور ولا يُكمل الخدمة، ومقدّمُ
 *     الخدمة يبدأ ويُكمل ولا يؤكّد الحجوزات القادمة.
 *
 *   • `reservations.cancel` منفصلةٌ عن `edit`: الإلغاء يقتطع رسماً من عربون
 *     العميل ويكتب قيداً — وهو قرارٌ ماليّ لا تعديلُ موعد.
 *
 *   • `reservations.refund_payment` منفصلةٌ عن `create_payment`: القبضُ
 *     يزيد الخزينة والردُّ يُخرج منها نقداً، ومن يقبض ليس بالضرورة من
 *     يُصرَّح له بالإخراج. وهي القاعدةُ نفسُها في سندات القبض والصرف.
 *
 *   • `reservations.create_invoice` وحدَها: إصدارُ الفاتورة هو لحظةُ
 *     الاعتراف بالإيراد وإقفالِ التزام العربون — فعلٌ محاسبيّ لا تشغيليّ.
 *
 * ─────────────────────── ما لا يُحرَس هنا ───────────────────────
 *
 * **نطاقُ الرؤية** (`view_all` / `view_branch` / `view_assigned`) ليس هنا
 * بل في [DataScopePermissions]: محورٌ مستقلٌّ يسري على الوحدات كلِّها ولا
 * يُكرَّر في كلّ كتالوج — وتكرارُه يعني سلَّمَين للنطاق يفترقان.
 *
 * وصلاحياتُ العميل في بوّابته لا تُشتقّ من هنا: العميل يرى حجوزاتِه بحكم
 * كونه صاحبَها، لا بصلاحيةٍ إدارية.
 */
class ReservationsPermissions extends BasePermissionCatalog
{
    /** بادئة أسماء صلاحيات هذه الوحدة. */
    public const PREFIX = 'reservations.';

    public static function module(): array
    {
        return [
            'key' => 'reservations',
            'label' => 'الحجوزات',
            'description' => 'مواعيد العملاء وخدماتها وأوقات العمل ومدفوعاتها وفواتيرها.',
            'icon' => 'calendar',
            'order' => 25,
        ];
    }

    public static function groups(): array
    {
        return [
            'reservations' => [
                'label' => 'الحجوزات',
                'description' => 'مستند الحجز نفسه: إنشاؤه وتعديله وحذفه وعرض تقويمه.',
                'icon' => 'bookmark',
                'controller' => 'Reservations/ReservationsController',
                'permissions' => [
                    'reservations.view' => 'عرض الحجوزات وقائمتها',
                    'reservations.show' => 'عرض تفاصيل حجز',
                    'reservations.create' => 'إضافة حجز',
                    'reservations.edit' => 'تعديل حجز',
                    'reservations.delete' => 'حذف حجز',
                    'reservations.calendar' => 'عرض تقويم الحجوزات',
                    'reservations.dashboard' => 'عرض لوحة الحجوزات',
                ],
            ],

            'workflow' => [
                'label' => 'سير عمل الحجز',
                'description' => 'الانتقال بين حالات الحجز — لكلّ انتقالٍ صلاحيتُه.',
                'icon' => 'git-pull-request',
                'controller' => 'Reservations/ReservationWorkflowController',
                'permissions' => [
                    'reservations.confirm' => 'تأكيد الحجز',
                    'reservations.checkin' => 'تسجيل حضور العميل وعدم حضوره',
                    'reservations.start' => 'بدء تنفيذ الخدمة',
                    'reservations.complete' => 'إكمال الحجز',
                    'reservations.cancel' => 'إلغاء الحجز ورفضه',
                    'reservations.reschedule' => 'إعادة جدولة الحجز',
                ],
            ],

            'finance' => [
                'label' => 'مالية الحجوزات',
                'description' => 'قبض العرابين وردّها وإصدار الفواتير — أفعالٌ تكتب قيوداً محاسبية.',
                'icon' => 'dollar-sign',
                'controller' => 'Reservations/ReservationPaymentController',
                'permissions' => [
                    'reservations.view_payments' => 'عرض سجلّ مدفوعات الحجوزات',
                    'reservations.create_payment' => 'تسجيل دفعة على حجز',
                    'reservations.refund_payment' => 'ردّ دفعة من حجز',
                    'reservations.create_invoice' => 'إصدار فاتورة من حجز',
                ],
            ],

            'services' => [
                'label' => 'خدمات الحجز',
                'description' => 'كتالوج الخدمات القابلة للحجز: مدّتها وأسعارها وموظفوها.',
                'icon' => 'briefcase',
                'controller' => 'Reservations/ReservationServicesController',
                'permissions' => [
                    'reservation_services.view' => 'عرض خدمات الحجز',
                    'reservation_services.create' => 'إضافة خدمة حجز',
                    'reservation_services.edit' => 'تعديل خدمة حجز',
                    'reservation_services.delete' => 'حذف خدمة حجز',
                ],
            ],

            'schedule' => [
                'label' => 'أوقات العمل والعطل',
                'description' => 'دوام الفروع والموظفين والعطل والأيام الاستثنائية.',
                'icon' => 'clock',
                'controller' => 'Reservations/ReservationScheduleController',
                'permissions' => [
                    'reservation_schedule.view' => 'عرض أوقات العمل والعطل',
                    'reservation_schedule.edit' => 'ضبط أوقات العمل',
                    'reservation_schedule.holidays' => 'إدارة العطل والأيام الاستثنائية',
                ],
            ],

            'settings' => [
                'label' => 'إعدادات الحجوزات',
                'description' => 'قواعد الحجز والإلغاء وإعادة الجدولة والتذكير والفوترة.',
                'icon' => 'settings',
                'controller' => 'Reservations/ReservationSettingsController',
                'permissions' => [
                    'reservation_settings.view' => 'عرض إعدادات الحجوزات',
                    'reservation_settings.update' => 'تعديل إعدادات الحجوزات',
                ],
            ],

            'reports' => [
                'label' => 'تقارير الحجوزات',
                'description' => 'تقارير المواعيد والإيرادات وعدم الحضور وأداء الموظفين والخدمات.',
                'icon' => 'bar-chart-2',
                'controller' => 'Reservations/ReservationReportsController',
                'permissions' => [
                    'reservation_reports.view' => 'عرض تقارير الحجوزات',
                    'reservation_reports.export' => 'تصدير تقارير الحجوزات',
                ],
            ],
        ];
    }
}
