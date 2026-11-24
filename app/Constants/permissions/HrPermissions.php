<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات قسم الموارد البشرية (أُعيد بناؤه 2026-09-24).
 *
 * **القاعدة الواحدة:** لكل شاشةٍ مجموعة، ولكل فعلٍ صلاحية باسم
 * `<الشاشة>.<الفعل>` (`index` قائمة، `show` تفاصيل، `create` إضافة، `edit`
 * تعديل، `destroy` حذف، ثم أفعال الشاشة الخاصة كالاعتماد والصرف).
 *
 * **لا صلاحياتِ «هو»:** لا `view_own` ولا «عرض … الخاص به». على أيّ موظفين تسري
 * الصلاحية يحدّده نطاق الرؤية (`data_scope_own` / `_team` / `_department` /
 * `_branch` / `_region` / `_all`) — فالموظف الذي يرى قسيمته وحدها يحمل
 * `payslips.index` + `data_scope_own`، والمدير يحمل الصلاحية نفسها بنطاقٍ أوسع.
 *
 * **الأقسام** (`section`) ترتّب المجموعات في شاشة الأدوار: الموظفون، الهيكل
 * التنظيمي، الحضور والانصراف، الإجازات والأذونات، الرواتب والمستحقات، تتبّع
 * الوقت، التقارير. `sections()` تعطي عناوينها بترتيبها.
 *
 * الأسماء القديمة (`staff_*`, `employees_*`, `hr_*_manage`, `salaries_*`,
 * `*_view_own`…) متقاعدة: الترحيلان `2026_09_24_200000` و`2026_09_24_220000`
 * منحا الجديدة لكل دورٍ حمل نظيرتها ثم أزالا صفوف القديمة.
 */
class HrPermissions extends BasePermissionCatalog
{
    public static function module(): array
    {
        return [
            'key' => 'hr',
            'label' => 'الموارد البشرية',
            'description' => 'الموظفون والهيكل التنظيمي، الحضور والإجازات، الرواتب والعقود والسلف — وما يراه كلُّ مستخدم يحدّده نطاق الرؤية.',
            'icon' => 'briefcase',
            'order' => 60,
        ];
    }

    /** عناوين الأقسام بترتيب ظهورها في شاشة الأدوار. */
    public static function sections(): array
    {
        return [
            'employees' => 'الموظفون',
            'org' => 'الهيكل التنظيمي',
            'attendance' => 'الحضور والانصراف',
            'leaves' => 'الإجازات والأذونات',
            'payroll' => 'الرواتب والمستحقات',
            'time' => 'تتبّع الوقت',
            'reports' => 'التقارير واللوحات',
        ];
    }

    public static function groups(): array
    {
        return [
            /* ══════════════════ الموظفون ══════════════════ */
            'employees' => [
                'label' => 'ملفات الموظفين',
                'section' => 'employees',
                'description' => 'قائمة الموظفين وملفاتهم: الإضافة والتعديل والحذف، وإنهاء الخدمة والأرشفة كلٌّ بصلاحيته.',
                'icon' => 'user',
                'controller' => 'HR/EmployeeController',
                'permissions' => [
                    'employees.index' => 'عرض قائمة الموظفين',
                    'employees.show' => 'عرض ملف الموظف',
                    'employees.create' => 'إضافة موظف',
                    'employees.edit' => 'تعديل بيانات الموظف',
                    'employees.destroy' => 'حذف موظف (بلا أثر)',
                    'employees.terminate' => 'إنهاء خدمة موظف',
                    'employees.archive' => 'أرشفة الموظفين واستردادهم',
                    'employees.sensitive' => 'عرض البيانات الحسّاسة (البنك والآيبان)',
                    'employees.export' => 'تصدير الموظفين',
                ],
            ],

            'employee_documents' => [
                'label' => 'وثائق الموظفين',
                'section' => 'employees',
                'description' => 'الوثائق الرسمية (الإقامة، الجواز، الشهادات…) — القراءة مفصولة عن الكتابة.',
                'icon' => 'paperclip',
                'controller' => 'HR/EmployeeDocumentController',
                'permissions' => [
                    'employee_documents.index' => 'عرض وثائق الموظفين',
                    'employee_documents.create' => 'إضافة وثيقة',
                    'employee_documents.edit' => 'تعديل وثيقة',
                    'employee_documents.destroy' => 'حذف وثيقة',
                ],
            ],

            'employee_accounts' => [
                'label' => 'حسابات دخول الموظفين',
                'section' => 'employees',
                'description' => 'حساب الموظف في النظام: تفعيل الدخول، كلمة المرور، الأدوار، والدخول بحسابه — أفعالٌ ترفع الامتياز.',
                'icon' => 'key',
                'controller' => 'HR/EmployeeController (login_to, updatePassword, toggleSystemAccess, roles)',
                'permissions' => [
                    'employee_accounts.access' => 'تفعيل / إيقاف دخول الموظف للنظام',
                    'employee_accounts.password' => 'تغيير كلمة مرور الموظف وإرسال بيانات الدخول',
                    'employee_accounts.roles' => 'إدارة أدوار الموظفين',
                    'employee_accounts.login_as' => 'الدخول بحساب الموظف',
                ],
            ],

            /*
                `employee_stock.*` أُزيلت (2026-10-04): لا مسارَ ولا متحكّمَ ولا
                ذكرَ لها في الشيفرة كلِّها — `HR/EmployeeController (stock permits)`
                المذكورُ في وصفها لا وجودَ له. والأذوناتُ المخزنية للموظفين
                تحرسها صلاحياتُ وحدة المخزون. كانت تُمنَح لثلاثة أدوار ولا
                تفتح شيئاً.
            */

            /* شاشةٌ واحدةٌ لإعدادات ملفّ الموظف وقواعد الهيكل التنظيمي —
               نظيرُ `attendance_settings.manage` و`payroll_settings.manage`:
               من يضبط القواعدَ ليس بالضرورة من يسجّل الموظفين. */
            'employee_settings' => [
                'label' => 'إعدادات الموظفين والهيكل التنظيمي',
                'section' => 'employees',
                'description' => 'الحقول الإلزامية في ملفّ الموظف، وقواعد التحقّق، وإلزاميّة حقول الأقسام والمسمّيات والمستويات والأنواع وفرادة أسمائها.',
                'icon' => 'sliders',
                'controller' => 'HR/EmployeeSettingsController',
                'permissions' => [
                    'employee_settings.manage' => 'إدارة إعدادات الموظفين والهيكل التنظيمي',
                ],
            ],

            /* ══════════════════ الهيكل التنظيمي ══════════════════ */
            'departments' => [
                'label' => 'الأقسام',
                'section' => 'org',
                'description' => 'أقسام المنشأة ومديروها.',
                'icon' => 'grid',
                'controller' => 'HR/OrganizationalStructure/DepartmentController',
                'permissions' => [
                    'departments.index' => 'عرض الأقسام',
                    'departments.show' => 'عرض تفاصيل القسم',
                    'departments.create' => 'إضافة قسم',
                    'departments.edit' => 'تعديل قسم',
                    'departments.destroy' => 'حذف قسم',
                ],
            ],

            'job_titles' => [
                'label' => 'المسمّيات الوظيفية',
                'section' => 'org',
                'description' => 'المسمّيات الوظيفية التي تُسنَد إلى الموظفين والعقود.',
                'icon' => 'tag',
                'controller' => 'HR/OrganizationalStructure/JobTitleController',
                'permissions' => [
                    'job_titles.index' => 'عرض المسمّيات الوظيفية',
                    'job_titles.show' => 'عرض تفاصيل المسمّى',
                    'job_titles.create' => 'إضافة مسمّى وظيفي',
                    'job_titles.edit' => 'تعديل مسمّى وظيفي',
                    'job_titles.destroy' => 'حذف مسمّى وظيفي',
                ],
            ],

            'job_levels' => [
                'label' => 'المستويات الوظيفية',
                'section' => 'org',
                'description' => 'الدرجات / المستويات الوظيفية.',
                'icon' => 'bar-chart',
                'controller' => 'HR/OrganizationalStructure/ManagingFunctionalLevelsController',
                'permissions' => [
                    'job_levels.index' => 'عرض المستويات الوظيفية',
                    'job_levels.show' => 'عرض تفاصيل المستوى',
                    'job_levels.create' => 'إضافة مستوى وظيفي',
                    'job_levels.edit' => 'تعديل مستوى وظيفي',
                    'job_levels.destroy' => 'حذف مستوى وظيفي',
                ],
            ],

            'job_types' => [
                'label' => 'أنواع الوظائف',
                'section' => 'org',
                'description' => 'أنواع الوظائف (دوام كامل، جزئي، تعاقد…).',
                'icon' => 'layers',
                'controller' => 'HR/OrganizationalStructure/ManagingJobTypesController',
                'permissions' => [
                    'job_types.index' => 'عرض أنواع الوظائف',
                    'job_types.show' => 'عرض تفاصيل النوع',
                    'job_types.create' => 'إضافة نوع وظيفة',
                    'job_types.edit' => 'تعديل نوع وظيفة',
                    'job_types.destroy' => 'حذف نوع وظيفة',
                ],
            ],

            'org_chart' => [
                'label' => 'الشجرة التنظيمية',
                'section' => 'org',
                'description' => 'عرض التسلسل الإداري للموظفين.',
                'icon' => 'share-2',
                'controller' => 'HR/HRReportsController (org chart)',
                'permissions' => [
                    'org_chart.index' => 'عرض الشجرة التنظيمية',
                ],
            ],

            /* ══════════════════ الحضور والانصراف ══════════════════
               مرتّبةٌ كما يجري العمل: التسجيلُ وأدواته، ثم متابعةُ اليوم وسجلّاته
               وتصحيحاته، ثم الدوامُ والعطل، ثم الأماكنُ والأجهزة، ثم القواعدُ
               والإعدادات. ولكلّ شاشةٍ مجموعتُها — لا مجموعةٌ تحرس شاشتين. */
            'attendance_registration' => [
                'label' => 'تسجيل الحضور',
                'section' => 'attendance',
                'description' => 'شاشتا التسجيل: الجوال، وجهاز الفرع. على أيّ موظفين تسري يحدّده نطاق الرؤية.',
                'icon' => 'smartphone',
                'controller' => 'HR/Attendance/FieldAttendanceController + BarcodeAttendanceController (الشاشة)',
                'permissions' => [
                    'attendance_registration.mobile' => 'التسجيل من الجوال (شاشة «سجّل حضوري» ورمز المكتب)',
                    'attendance_registration.kiosk' => 'تشغيل شاشة جهاز الفرع ومسح البطاقات',
                ],
            ],

            'attendance_cards' => [
                'label' => 'بطاقات الحضور (الباركود)',
                'section' => 'attendance',
                'description' => 'بطاقات الموظفين التي تُمسح على جهاز الفرع، وطريقة تسجيل كل موظف.',
                'icon' => 'credit-card',
                'controller' => 'HR/Attendance/BarcodeAttendanceController (البطاقات)',
                'permissions' => [
                    'attendance_cards.index' => 'عرض بطاقات الموظفين',
                    'attendance_cards.manage' => 'إصدار البطاقات وإيقافها وحذفها وتحديد طريقة تسجيل الموظف',
                    'attendance_cards.print' => 'طباعة البطاقات',
                ],
            ],

            'attendance_days' => [
                'label' => 'الحضور اليومي وأيام الحضور',
                'section' => 'attendance',
                'description' => 'حضور اليوم لكل موظف، وأيام الحضور المحسوبة: التأخير والغياب والإضافي.',
                'icon' => 'calendar',
                'controller' => 'HR/Attendance/UnifiedAttendanceLogController + AttendanceDaysController',
                'permissions' => [
                    'attendance_days.index' => 'عرض الحضور اليومي وأيام الحضور',
                    'attendance_days.show' => 'عرض تفاصيل اليوم',
                    'attendance_days.edit' => 'إضافة يوم حضور أو حذفه',
                    'attendance_days.calculate' => 'إعادة حساب أيام الحضور',
                ],
            ],

            'attendance_records' => [
                'label' => 'سجلات الحضور',
                'section' => 'attendance',
                'description' => 'كل محاولة تسجيل — المقبولة والمرفوضة وسببها — من الجهاز أو الجوال أو جهاز البصمة.',
                'icon' => 'user-check',
                'controller' => 'HR/Attendance/AttendancePunchesController',
                'permissions' => [
                    'attendance_records.index' => 'عرض سجلات الحضور',
                    'attendance_records.create' => 'تسجيل حضور موظف يدوياً من شاشة جهاز الفرع (بلا بطاقة)',
                    'attendance_records.pull' => 'سحب السجلات من جهاز البصمة',
                ],
            ],

            'attendance_corrections' => [
                'label' => 'طلبات تصحيح الحضور',
                'section' => 'attendance',
                'description' => 'طلب تصحيح حضورٍ ناقص أو خاطئ، واعتماده أو رفضه.',
                'icon' => 'edit-3',
                'controller' => 'HR/Attendance/CorrectionRequests + CorrectionApprovals',
                'permissions' => [
                    'attendance_corrections.index' => 'عرض طلبات التصحيح',
                    'attendance_corrections.create' => 'تقديم طلب تصحيح وإلغاؤه',
                    'attendance_corrections.approve' => 'اعتماد / رفض طلبات التصحيح',
                ],
            ],

            'attendance_team' => [
                'label' => 'لوحة متابعة الفريق',
                'section' => 'attendance',
                'description' => 'لوحة حيّة لحضور الفريق — لمن يديرهم بحسب نطاقه.',
                'icon' => 'users',
                'controller' => 'HR/Attendance/TeamLiveStatusController',
                'permissions' => [
                    'attendance_team.index' => 'عرض لوحة متابعة الفريق',
                ],
            ],

            'attendance_books' => [
                'label' => 'دفاتر الحضور',
                'section' => 'attendance',
                'description' => 'دفاتر الحضور الدورية للموظفين.',
                'icon' => 'book',
                'controller' => 'HR/Attendance/AttendanceSheetsController',
                'permissions' => [
                    'attendance_books.index' => 'عرض دفاتر الحضور',
                    'attendance_books.show' => 'عرض تفاصيل الدفتر',
                    'attendance_books.create' => 'إنشاء دفتر حضور',
                    'attendance_books.edit' => 'تعديل دفتر حضور',
                    'attendance_books.destroy' => 'حذف دفتر حضور',
                ],
            ],

            'shifts' => [
                'label' => 'الورديات',
                'section' => 'attendance',
                'description' => 'الورديات وجداول الدوام والورديات المخصّصة.',
                'icon' => 'clock',
                'controller' => 'HR/ShiftManagementController + CustomShiftsController',
                'permissions' => [
                    'shifts.index' => 'عرض الورديات',
                    'shifts.show' => 'عرض تفاصيل الوردية',
                    'shifts.create' => 'إضافة وردية',
                    'shifts.edit' => 'تعديل وردية وتعيين موظفيها',
                    'shifts.destroy' => 'حذف وردية',
                ],
            ],

            'holidays' => [
                'label' => 'العطل الرسمية',
                'section' => 'attendance',
                'description' => 'قوائم العطل الرسمية وربطها بالأقسام والمسميات والموظفين.',
                'icon' => 'sun',
                'controller' => 'HR/Attendance/Settings/HolidayController',
                'permissions' => [
                    'holidays.index' => 'عرض العطل الرسمية',
                    'holidays.show' => 'عرض تفاصيل قائمة العطل',
                    'holidays.create' => 'إضافة قائمة عطل',
                    'holidays.edit' => 'تعديل قائمة عطل وربطها',
                    'holidays.destroy' => 'حذف قائمة عطل',
                ],
            ],

            'attendance_places' => [
                'label' => 'أماكن التسجيل وأجهزة الفروع',
                'section' => 'attendance',
                'description' => 'أماكن الفروع على الخريطة، وأجهزة التسجيل فيها: رمز الربط، والموظفون المربوطون، ورمز المكتب.',
                'icon' => 'map-pin',
                'controller' => 'HR/Attendance/Settings/GeofencesController',
                'permissions' => [
                    'attendance_places.index' => 'عرض الأماكن والأجهزة',
                    'attendance_places.show' => 'عرض تفاصيل المكان أو الجهاز وموظفيه',
                    'attendance_places.create' => 'إضافة مكان أو جهاز',
                    'attendance_places.edit' => 'تعديل مكان أو جهاز، وربط الموظفين، ورمز الربط ورمز المكتب',
                    'attendance_places.destroy' => 'حذف مكان أو جهاز',
                ],
            ],

            'attendance_devices' => [
                'label' => 'أجهزة البصمة',
                'section' => 'attendance',
                'description' => 'أجهزة البصمة (ZKTeco) واتصالها، ولوحة مراقبة الأجهزة.',
                'icon' => 'cpu',
                'controller' => 'HR/Attendance/Settings/MachinesController + DeviceMonitorController',
                'permissions' => [
                    'attendance_devices.index' => 'عرض أجهزة البصمة ومراقبة الأجهزة',
                    'attendance_devices.show' => 'عرض تفاصيل جهاز البصمة',
                    'attendance_devices.create' => 'إضافة جهاز بصمة',
                    'attendance_devices.edit' => 'تعديل جهاز بصمة واختبار الاتصال',
                    'attendance_devices.destroy' => 'حذف جهاز بصمة',
                ],
            ],

            'attendance_rules' => [
                'label' => 'قواعد الحضور ومحدّداته',
                'section' => 'attendance',
                'description' => 'قواعد احتساب التأخير والغياب والخصم، ومحدّدات الحضور وموظفوها.',
                'icon' => 'sliders',
                'controller' => 'HR/Attendance/Settings/AttendanceRuleController + AttendanceDeterminantsController',
                'permissions' => [
                    'attendance_rules.index' => 'عرض القواعد والمحدّدات',
                    'attendance_rules.show' => 'عرض تفاصيل القاعدة أو المحدّد',
                    'attendance_rules.create' => 'إضافة قاعدة أو محدّد',
                    'attendance_rules.edit' => 'تعديل قاعدة أو محدّد وتعيين موظفيه',
                    'attendance_rules.destroy' => 'حذف قاعدة أو محدّد',
                ],
            ],

            'attendance_settings' => [
                'label' => 'إعدادات الحضور',
                'section' => 'attendance',
                'description' => 'الدوام والتأخير والحكم على اليوم والخصميات والصور، والإعداد الخاص لكل موظف.',
                'icon' => 'settings',
                'controller' => 'HR/Attendance/Settings/AttendanceV2SettingsController + BasicController',
                'permissions' => [
                    'attendance_settings.manage' => 'إدارة إعدادات الحضور وإعدادات الموظفين الخاصة',
                ],
            ],

            /* ══════════════════ الإجازات والأذونات ══════════════════ */
            'leave_requests' => [
                'label' => 'طلبات الإجازات',
                'section' => 'leaves',
                'description' => 'تقديم طلبات الإجازة والبتّ فيها.',
                'icon' => 'briefcase',
                'controller' => 'HR/Attendance/LeaveRequestsController',
                'permissions' => [
                    'leave_requests.index' => 'عرض طلبات الإجازات',
                    'leave_requests.show' => 'عرض تفاصيل الطلب',
                    'leave_requests.create' => 'تقديم طلب إجازة',
                    'leave_requests.edit' => 'تعديل طلب إجازة',
                    'leave_requests.destroy' => 'حذف طلب إجازة',
                    'leave_requests.approve' => 'الموافقة على طلبات الإجازات ورفضها',
                ],
            ],

            'leave_permissions' => [
                'label' => 'أذونات المغادرة',
                'section' => 'leaves',
                'description' => 'أذونات الخروج المؤقت خلال الدوام.',
                'icon' => 'log-out',
                'controller' => 'HR/Attendance/LeavePermissionsController',
                'permissions' => [
                    'leave_permissions.index' => 'عرض أذونات المغادرة',
                    'leave_permissions.show' => 'عرض تفاصيل الإذن',
                    'leave_permissions.create' => 'إضافة إذن مغادرة',
                    'leave_permissions.edit' => 'تعديل إذن مغادرة',
                    'leave_permissions.destroy' => 'حذف إذن مغادرة',
                ],
            ],

            'leave_types' => [
                'label' => 'أنواع الإجازات',
                'section' => 'leaves',
                'description' => 'أنواع الإجازات وقواعدها (مدفوعة، سنوية، مرضية…).',
                'icon' => 'list',
                'controller' => 'HR/Attendance/Settings/LeaveTypesController',
                'permissions' => [
                    'leave_types.index' => 'عرض أنواع الإجازات',
                    'leave_types.show' => 'عرض تفاصيل النوع',
                    'leave_types.create' => 'إضافة نوع إجازة',
                    'leave_types.edit' => 'تعديل نوع إجازة',
                    'leave_types.destroy' => 'حذف نوع إجازة',
                ],
            ],

            'leave_policies' => [
                'label' => 'سياسات الإجازات',
                'section' => 'leaves',
                'description' => 'سياسات الإجازات وتعيينها للموظفين.',
                'icon' => 'file-text',
                'controller' => 'HR/Attendance/Settings/LeavePoliciesController',
                'permissions' => [
                    'leave_policies.index' => 'عرض سياسات الإجازات',
                    'leave_policies.show' => 'عرض تفاصيل السياسة',
                    'leave_policies.create' => 'إضافة سياسة',
                    'leave_policies.edit' => 'تعديل سياسة وتعيين موظفيها',
                    'leave_policies.destroy' => 'حذف سياسة',
                ],
            ],

            'leave_balances' => [
                'label' => 'أرصدة الإجازات',
                'section' => 'leaves',
                'description' => 'أرصدة إجازات الموظفين وتعديلها وإعادة احتسابها.',
                'icon' => 'pie-chart',
                'controller' => 'HR/Attendance/Settings/EmployeeLeaveBalancesController',
                'permissions' => [
                    'leave_balances.index' => 'عرض أرصدة الإجازات',
                    'leave_balances.show' => 'عرض تفاصيل الرصيد',
                    'leave_balances.create' => 'إضافة رصيد',
                    'leave_balances.edit' => 'تعديل رصيد وإعادة احتسابه',
                    'leave_balances.destroy' => 'حذف رصيد',
                ],
            ],

            /* ══════════════════ الرواتب والمستحقات ══════════════════ */
            'payroll' => [
                'label' => 'مسيرات الرواتب',
                'section' => 'payroll',
                'description' => 'مسير الرواتب: تجميع الموظفين بالقواعد، واعتماده — وهو الاعتماد الوحيد قبل الصرف.',
                'icon' => 'dollar-sign',
                'controller' => 'HR/Salaries/PayrollProcessController',
                'permissions' => [
                    'payroll.index' => 'عرض قائمة المسيرات',
                    'payroll.show' => 'عرض تفاصيل المسير',
                    'payroll.create' => 'إنشاء مسير',
                    'payroll.edit' => 'تعديل المسير',
                    'payroll.destroy' => 'حذف المسير',
                    'payroll.approve' => 'اعتماد المسير',
                    'payroll.export' => 'تصدير المسير إلى Excel',
                    'payroll.print' => 'طباعة المسير',
                ],
            ],

            'payslips' => [
                'label' => 'قسائم الرواتب',
                'section' => 'payroll',
                'description' => 'قسائم الرواتب وصرفها — الصرفُ مسارٌ واحد لـ«صرف الراتب» و«صرف القسيمة».',
                'icon' => 'file-text',
                'controller' => 'HR/Salaries/SalarySlipController',
                'permissions' => [
                    'payslips.index' => 'عرض القسائم',
                    'payslips.show' => 'عرض تفاصيل القسيمة',
                    'payslips.create' => 'إنشاء قسيمة',
                    'payslips.edit' => 'تعديل القسيمة',
                    'payslips.destroy' => 'حذف القسيمة',
                    'payslips.approve' => 'اعتماد القسيمة وإيقافها',
                    'payslips.disburse' => 'صرف القسيمة / الراتب',
                    'payslips.print' => 'طباعة القسيمة',
                ],
            ],

            'disbursements' => [
                'label' => 'سجلات الصرف',
                'section' => 'payroll',
                'description' => 'صرفات الرواتب الشهرية: الإيقاف والتسوية الرجعية والعكس — كلٌّ صلاحيةٌ مستقلة.',
                'icon' => 'credit-card',
                'controller' => 'HR/Salaries/PayrollRunController',
                'permissions' => [
                    'disbursements.index' => 'عرض سجلات الصرف',
                    'disbursements.show' => 'عرض تفاصيل الصرف',
                    'disbursements.stop' => 'إيقاف الصرف ورفع الإيقاف',
                    'disbursements.settle' => 'تسوية الصرف (تسوية رجعية)',
                    'disbursements.reverse' => 'عكس صرفٍ تمّ (قيد عكسي)',
                    'disbursements.cancel' => 'إلغاء صرفة لم تُصرف',
                ],
            ],

            'contracts' => [
                'label' => 'العقود',
                'section' => 'payroll',
                'description' => 'عقود الموظفين: إنشاؤها واعتمادها وطباعتها وتنبيهات انتهائها.',
                'icon' => 'file-text',
                'controller' => 'HR/Salaries/ContractsController',
                'permissions' => [
                    'contracts.index' => 'عرض العقود',
                    'contracts.show' => 'عرض تفاصيل العقد',
                    'contracts.create' => 'إنشاء عقد',
                    'contracts.edit' => 'تعديل العقد',
                    'contracts.destroy' => 'حذف العقد',
                    'contracts.approve' => 'اعتماد العقد',
                    'contracts.print' => 'طباعة العقد',
                    'contracts.notifications' => 'إدارة واستلام إشعارات انتهاء العقود',
                ],
            ],

            'loans' => [
                'label' => 'السلف والطلبات',
                'section' => 'payroll',
                'description' => 'طلبات السلف والبتّ فيها وصرفها وتسويتها وإلغاؤها — آثارها النقدية مفصولة عن الموافقة.',
                'icon' => 'arrow-up-circle',
                'controller' => 'HR/Salaries/AdvanceRequestController + AdvanceApprovalController + AncestorController',
                'permissions' => [
                    'loans.index' => 'عرض السلف',
                    'loans.show' => 'عرض تفاصيل السلفة',
                    'loans.create' => 'طلب / إنشاء سلفة',
                    'loans.edit' => 'تعديل السلفة',
                    'loans.destroy' => 'حذف السلفة',
                    'loans.approve' => 'الموافقة على السلفة',
                    'loans.disburse' => 'صرف السلفة',
                    'loans.cancel' => 'إلغاء السلفة',
                    'loans.settle' => 'تسوية السلفة',
                ],
            ],

            'deductions' => [
                'label' => 'الخصومات والجزاءات',
                'section' => 'payroll',
                'description' => 'خصومات الموظفين (تأخير، غياب، جزاء…): الاعتماد يُنشئ القيد المحاسبي.',
                'icon' => 'minus-circle',
                'controller' => 'HR/Salaries/DeductionController',
                'permissions' => [
                    'deductions.index' => 'عرض الخصومات',
                    'deductions.show' => 'عرض تفاصيل الخصم',
                    'deductions.create' => 'إضافة خصم',
                    'deductions.edit' => 'تعديل الخصم',
                    'deductions.destroy' => 'حذف الخصم',
                    'deductions.approve' => 'اعتماد الخصم (أو رفضه) وإنشاء القيد',
                    'deductions.settle' => 'تسوية الخصم يدوياً (تحصيل نقدي أو إعفاء)',
                ],
            ],

            'commissions' => [
                'label' => 'العمولات المصروفة',
                'section' => 'payroll',
                'description' => 'عمولات المبيعات المحتسبة من أهداف المبيعات — لا نظام أهداف داخل الموارد البشرية.',
                'icon' => 'percent',
                'controller' => 'HR/CommissionsReportController + Sales/SalesTargetController@settle',
                'permissions' => [
                    'commissions.index' => 'عرض العمولات',
                    'commissions.show' => 'عرض تفاصيل العمولة',
                    'commissions.create' => 'احتساب / إنشاء عمولة',
                    // `edit` و`destroy` أُزيلتا (2026-10-04): الشاشةُ تقريرٌ
                    // يُعرَض ويُصدَّر ويُطبَع — لا فعلَ تعديلٍ ولا حذفٍ فيها
                    'commissions.approve' => 'اعتماد العمولة',
                    'commissions.disburse' => 'صرف العمولة',
                    'commissions.export' => 'تصدير العمولات إلى Excel',
                    'commissions.print' => 'طباعة سند العمولة',
                ],
            ],

            'insurance' => [
                'label' => 'التأمينات الاجتماعية',
                'section' => 'payroll',
                'description' => 'حصص التأمينات المحجوزة وسدادها للمؤسسة — السداد أثرٌ نقديّ منفصل.',
                'icon' => 'shield',
                'controller' => 'HR/Salaries/SocialInsuranceSettingsController',
                'permissions' => [
                    'insurance.index' => 'عرض التأمينات',
                    'insurance.show' => 'عرض تفاصيل التأمينات (كشف الاشتراكات)',
                    'insurance.pay' => 'سداد التأمينات',
                    'insurance.settings' => 'إدارة إعدادات التأمينات ونسبها',
                ],
            ],

            'eos' => [
                'label' => 'مكافأة نهاية الخدمة',
                'section' => 'payroll',
                'description' => 'احتساب المكافأة واعتمادها وتسويتها وصرفها.',
                'icon' => 'log-out',
                'controller' => 'HR/Salaries/EndOfServiceBenefitController',
                'permissions' => [
                    'eos.index' => 'عرض مكافآت نهاية الخدمة',
                    'eos.show' => 'عرض تفاصيل المكافأة',
                    'eos.create' => 'احتساب / إعادة احتساب المكافأة',
                    'eos.approve' => 'اعتماد المكافأة (قيد الاستحقاق)',
                    'eos.settle' => 'تسوية المكافأة على حساب الموظف',
                    'eos.pay' => 'صرف المكافأة من خزينة',
                    'eos.settings' => 'إعدادات نهاية الخدمة',
                ],
            ],

            'salary_items' => [
                'label' => 'بنود الرواتب',
                'section' => 'payroll',
                'description' => 'بنود المستحقات والاستقطاعات الموحّدة — المصدر الوحيد (الكتالوج القديم متقاعد).',
                'icon' => 'list',
                'controller' => 'HR/Salaries/SalaryItemsController',
                'permissions' => [
                    'salary_items.index' => 'عرض بنود الرواتب',
                    'salary_items.show' => 'عرض تفاصيل البند',
                    'salary_items.create' => 'إضافة بند',
                    'salary_items.edit' => 'تعديل البند وتفعيله/تعطيله',
                    'salary_items.destroy' => 'حذف البند',
                ],
            ],

            // مجموعة `salary_templates` أُزيلت (2026-09-24): «قوالب الرواتب» أُلغيت —
            // ترحيل `2026_09_24_230000_retire_salary_templates_permissions` يحذف صفوفها.

            'overtime' => [
                'label' => 'العمل الإضافي',
                'section' => 'payroll',
                'description' => 'طلبات العمل الإضافي والبتّ فيها وإعدادات احتسابه.',
                'icon' => 'clock',
                'controller' => 'HR/Salaries/OvertimeRequestController + OvertimeApprovalController + OvertimeSettingsController',
                'permissions' => [
                    'overtime.index' => 'عرض طلبات العمل الإضافي',
                    'overtime.show' => 'عرض تفاصيل الطلب',
                    'overtime.create' => 'تقديم طلب عمل إضافي',
                    'overtime.approve' => 'اعتماد / رفض طلبات العمل الإضافي',
                    'overtime.settings' => 'إعدادات الأجر الإضافي',
                ],
            ],

            'bank_files' => [
                'label' => 'ملفات البنك',
                'section' => 'payroll',
                'description' => 'ملفات تحويل الرواتب البنكية (WPS).',
                'icon' => 'download',
                'controller' => 'HR/Salaries/PayrollBankFileController',
                'permissions' => [
                    'bank_files.index' => 'عرض ملفات البنك',
                    'bank_files.show' => 'عرض تفاصيل الملف',
                    'bank_files.create' => 'توليد ملف بنكي',
                    'bank_files.download' => 'تنزيل الملف البنكي',
                ],
            ],

            'payroll_settings' => [
                'label' => 'إعدادات الرواتب',
                'section' => 'payroll',
                'description' => 'الإعدادات المركزية للرواتب: الحسابات المربوطة، حسابات الخصومات والمكافآت، الإعدادات العامة.',
                'icon' => 'settings',
                'controller' => 'HR/Salaries/SalarySittingController + PayrollAccountsController',
                'permissions' => [
                    'payroll_settings.manage' => 'إدارة إعدادات الرواتب وحساباتها',
                ],
            ],

            /* ══════════════════ تتبّع الوقت ══════════════════ */
            'time_entries' => [
                'label' => 'ساعات العمل',
                'section' => 'time',
                'description' => 'تسجيل ساعات العمل على المشاريع والأنشطة — وعلى أيّ موظفين يحدّده نطاق الرؤية.',
                'icon' => 'watch',
                'controller' => 'TaskManager/TimeTrackingController',
                'permissions' => [
                    'time_entries.index' => 'عرض ساعات العمل',
                    'time_entries.create' => 'تسجيل ساعات عمل',
                    'time_entries.edit' => 'تعديل ساعات العمل',
                    // `destroy` أُزيلت (2026-10-04): `Reports/TimeTrackingController`
                    // أفعالُه عرضٌ وتقاريرُ فقط، ولا مسارَ حذفٍ لساعةِ عمل

                ],
            ],

            'time_projects' => [
                'label' => 'مشاريع تتبّع الوقت',
                'section' => 'time',
                'description' => 'المشاريع التي تُحتسب عليها الساعات.',
                'icon' => 'folder',
                'controller' => 'TaskManager/TimeTrackingController',
                'permissions' => [
                    'time_projects.create' => 'إضافة مشروع',
                    'time_projects.edit' => 'تعديل مشروع',
                    'time_projects.destroy' => 'حذف مشروع',
                ],
            ],

            'time_activities' => [
                'label' => 'أنشطة تتبّع الوقت',
                'section' => 'time',
                'description' => 'الأنشطة التي تُصنَّف بها الساعات.',
                'icon' => 'activity',
                'controller' => 'TaskManager/TimeTrackingController',
                'permissions' => [
                    'time_activities.create' => 'إضافة نشاط',
                    'time_activities.edit' => 'تعديل نشاط',
                    'time_activities.destroy' => 'حذف نشاط',
                ],
            ],

            /* ══════════════════ التقارير واللوحات ══════════════════ */
            'hr_dashboard' => [
                'label' => 'لوحة الموارد البشرية',
                'section' => 'reports',
                'description' => 'لوحة مؤشرات الموارد البشرية — أرقامها مقيّدة بنطاق الرؤية.',
                'icon' => 'monitor',
                'controller' => 'HR/EmployeeController@dashboardhr + DashboardController',
                'permissions' => [
                    'hr_dashboard.index' => 'عرض لوحة الموارد البشرية',
                ],
            ],

            'hr_reports' => [
                'label' => 'تقارير الموارد البشرية',
                'section' => 'reports',
                'description' => 'تقارير الموظفين والحضور والإجازات والرواتب — كلٌّ بصلاحيته، وتصديرها معها.',
                'icon' => 'bar-chart-2',
                'controller' => 'HR/HRReportsController + EmployeeReportController + PayrollReportController',
                'permissions' => [
                    'hr_reports.employees' => 'تقارير الموظفين',
                    'hr_reports.attendance' => 'تقارير الحضور والإجازات',
                    'hr_reports.payroll' => 'تقارير الرواتب والمستحقات',
                    'hr_reports.export' => 'تصدير تقارير الموارد البشرية',
                ],
            ],

            'performance' => [
                'label' => 'تقييم أداء الموظفين',
                'section' => 'reports',
                'description' => 'شاشة أداء الموظفين (المبيعات، الحضور، الأهداف).',
                'icon' => 'trending-up',
                'controller' => 'HR/EmployeePerformanceController',
                'permissions' => [
                    'performance.index' => 'عرض أداء الموظفين',
                    'performance.export' => 'تصدير أداء الموظفين',
                ],
            ],
        ];
    }
}
