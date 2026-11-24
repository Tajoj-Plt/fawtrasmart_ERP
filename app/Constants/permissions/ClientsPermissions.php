<?php

namespace App\Constants\permissions;

/**
 * كتالوج صلاحيات قسم العملاء — سجلّهم ومتابعتهم وخطوط سيرهم.
 *
 * أسماء هذا القسم كلّها من النظام القديم (أعمدة `job_roles`) ولم تُحوَّل بعد
 * إلى النمط النقطي — وذلك عملٌ قائمٌ بذاته يمسّ أكثر من مئة موضع. تُجمع هنا
 * لا لأن أسماءها متشابهة بل لأن ما تحرسه واحد — والتقسيم في هذا النظام
 * بالمعنى لا بالبادئة.
 *
 * **`Approve_Client_Status` صارت `clients_approve_status`**
 * ([الترحيل 2026_09_12_220000]): كانت وحدها بحرفٍ كبير وترتيبٍ معكوس، شاذّةً
 * عن كل اسمٍ في النظام. المعنى واحدٌ والأدوارُ محفوظة — الربطُ بالمعرّف.
 *
 * **`Edit_Client`/`Delete_Client` حُذفتا من هنا** (لا من الجدول ولا من
 * أدوار من يحملهما): كل مَن كان يحملهما (owner/manager/admin) يحمل
 * `clients_edit_data`/`clients_delete` أيضاً، وحارسا `ClientController`
 * على الاسمين معاً (`Edit_Client|clients_edit_data`) — فبقاؤهما في
 * الكتالوج كان تكراراً بلا أثر، لا صلاحيةً منفصلة.
 *
 * **ما فُصل عمداً**: «الإعفاءات» مجموعةٌ وحدها. إعفاء مندوبٍ من التحقق من
 * موقع الزيارة أو من قيد عملية الاستبدال ليس صلاحية عملٍ بل تعطيلُ ضابطٍ
 * رقابي — من يمنحها يجب أن يراها منفصلةً عن «عرض العملاء».
 */
class ClientsPermissions extends BasePermissionCatalog
{
    public static function module(): array
    {
        return [
            'key' => 'clients',
            'label' => 'العملاء',
            'description' => 'سجلّ العملاء وبياناتهم، حدودهم الائتمانية، متابعتهم وزياراتهم وخطوط سيرهم.',
            'icon' => 'users',
            'order' => 30,
        ];
    }

    public static function groups(): array
    {
        return [
            'directory' => [
                'label' => 'سجل العملاء',
                'description' => 'إضافة العملاء وتعديل بياناتهم وحذفهم، وتعيين الموظفين المسؤولين عنهم. '
                    .'صلاحيات «العرض» الأربع (كل العملاء، عملاؤه فقط، حسب مجموعات الموظف، حسب خط السير اليومي '
                    .'في مجموعة «خطوط السير») نطاقاتٌ بديلة يمكن منحُ أكثر من واحدةٍ منها بأمان معاً — تُفحص بالأولوية '
                    .'(الكل، ثم المجموعات، ثم خط السير، ثم المسنَدون) وأوّل نطاقٍ يجد عملاءَ فعلاً هو الذي يُطبَّق؛ '
                    .'صلاحيةٌ نطاقُها فارغ (مثل مجموعاتٍ لم تُسنَد بعد) لا تحجب الصلاحية التالية.',
                'icon' => 'users',
                'controller' => 'Client/ClientsController',
                'permissions' => [
                    'clients_view_all_clients' => 'عرض جميع العملاء',
                    'clients_view_own_clients' => 'عرض عملائه هو فقط',
                    'clients_view_by_employee_groups' => 'عرض العملاء حسب مجموعات الموظف',
                    'clients_add_client' => 'إضافة عميل جديد',
                    'clients_edit_data' => 'تعديل بيانات العميل',
                    'clients_delete' => 'حذف العميل',
                    'clients_edit_delete_all_clients' => 'تعديل وحذف كل العملاء',
                    'clients_edit_delete_own_clients' => 'تعديل وحذف عملائه هو فقط',
                    'clients_change_status' => 'تغيير حالة العميل',
                    'clients_approve_status' => 'اعتماد تغيير حالة العميل',
                    /* طلب تغيير الوضع (`registration_stage_id`) يُبنى على نفس
                       صلاحية طلب تغيير الحالة عمداً لا صلاحيةٍ جديدة: كلاهما
                       «تصنيف هذا العميل» من زاوية المندوب الذي يطلب، والفرقُ
                       بينهما في القيمة المستهدفة لا في من يملك حقّ اللمس.
                       الاعتمادُ وحده فُصل بصلاحيةٍ جديدة (`clients_approve_stage`)
                       لأن قائمة معتمدي الوضع قد تختلف عملياً عن معتمدي الحالة
                       (وضعٌ تجاري بحت لا يراه من يعتمد إيقاف/تنشيط العميل). */
                    'clients_approve_stage' => 'اعتماد تغيير وضع العميل',
                    'clients_assign_employees' => 'تعيين الموظفين المسؤولين عن العملاء',
                    'clients_export' => 'تصدير بيانات العملاء',
                    'clients_import' => 'استيراد العملاء من ملف',
                    'clients_send_email' => 'إرسال بريد إلكتروني للعميل',
                ],
            ],

            'financial' => [
                'label' => 'الحدود والأرصدة المالية',
                'description' => 'الحد الائتماني وحد الكميات والرصيد الافتتاحي وكشف الحساب — ما يحكم قدرة العميل على الشراء بالآجل.',
                'icon' => 'credit-card',
                'controller' => 'Client/ClientsController + ClientAccountController',
                'permissions' => [
                    'clients_manage_credit_limit' => 'إدارة الحد الائتماني للعميل',
                    'clients_manage_quantity_limit' => 'إدارة حد الكميات للعميل',
                    'clients_manage_opening_balance' => 'إدارة الرصيد الافتتاحي للعميل',
                    'clients_manage_debts' => 'إدارة ديون العملاء',
                    'clients_approve_large_debt_writeoff' => 'اعتماد شطب ديون تتجاوز الحد المسموح',
                    'clients_writeoff_branch_debt' => 'شطب ديون فرع كامل دفعةً واحدة',
                    'clients_view_statement' => 'عرض كشف حساب العميل',
                    'clients_bulk_pay_invoices' => 'سداد فواتير متعددة دفعةً واحدة',
                ],
            ],

            'transactions' => [
                'label' => 'العمليات من ملف العميل',
                'description' => 'إنشاء الفواتير والدفعات والإشعارات مباشرةً من شاشة العميل دون المرور بشاشات المبيعات.',
                'icon' => 'file-plus',
                'controller' => 'Client/ClientsController',
                'permissions' => [
                    'clients_create_invoice' => 'إنشاء فاتورة للعميل',
                    'clients_create_payment' => 'إنشاء دفعة للعميل',
                    'clients_create_credit_notice' => 'إنشاء إشعار دائن للعميل',
                    'clients_product_exchange' => 'استبدال منتجات العميل',
                    'Issue_an_invoice_to_a_customer_who_has_a_debt' => 'إصدار فاتورة لعميلٍ عليه مديونية',
                ],
            ],

            'follow_up' => [
                'label' => 'المتابعة: الملاحظات والمرفقات والمواعيد',
                'description' => 'ثلاثيةُ متابعةِ العميل، ولكلٍّ منها ثلاثةُ نطاقات: جميع العملاء، المُسنَدون إلى الموظف، وعملاؤه هو.',
                'icon' => 'message-square',
                // المواعيد انتقلت إلى وحدة الحجوزات؛ الملاحظاتُ والمرفقات بقيت هنا.
                // والصلاحياتُ الثلاث ما زالت واحدةً للثلاثة، فبقيت في كتالوج العملاء.
                'controller' => 'Client/ClientNoteController + Reservations/AppointmentController',
                'permissions' => [
                    'client_follow_up_view_notes_attachments_appointments_all' => 'عرض ملاحظات ومرفقات ومواعيد جميع العملاء',
                    'client_follow_up_view_notes_attachments_appointments_assigned' => 'عرض ملاحظات ومرفقات ومواعيد العملاء المُسنَدين إليه',
                    'client_follow_up_view_notes_attachments_appointments_own' => 'عرض ملاحظاته ومرفقاته ومواعيده هو',
                    'client_follow_up_add_notes_attachments_appointments_all' => 'إضافة ملاحظات ومرفقات ومواعيد لجميع العملاء',
                    'client_follow_up_add_notes_attachments_appointments_assigned' => 'إضافة ملاحظات ومرفقات ومواعيد للعملاء المُسنَدين إليه',
                    'client_follow_up_add_notes_attachments_appointments_own' => 'إضافة ملاحظات ومرفقات ومواعيد لعملائه هو',
                    'client_follow_up_edit_delete_notes_attachments_appointments_all' => 'تعديل وحذف ملاحظات ومرفقات ومواعيد جميع العملاء',
                    'client_follow_up_edit_delete_notes_attachments_appointments_assigned' => 'تعديل وحذف ملاحظات ومرفقات ومواعيد العملاء المُسنَدين إليه',
                    'client_follow_up_edit_delete_notes_attachments_appointments_own' => 'تعديل وحذف ملاحظاته ومرفقاته ومواعيده هو',
                    'client_follow_up_add_notes_assigned_client' => 'إضافة ملاحظة لعميلٍ مُسنَدٍ إليه',
                    'client_follow_up_add_notes_from_any_location' => 'إضافة ملاحظة من أي موقع جغرافي',
                    'client_follow_up_assign_clients_to_employees' => 'إسناد العملاء إلى الموظفين للمتابعة',
                    'clients_add_employee_note' => 'إضافة ملاحظة موظف على ملف العميل',
                ],
            ],

            'itinerary' => [
                'label' => 'خطوط السير والزيارات',
                'description' => 'خطط زيارة المندوبين اليومية، وتتبّع الزيارات المنفَّذة، وإخفاء عميلٍ من خريطة المندوبين.',
                'icon' => 'map',
                'controller' => 'Client/ItineraryController + VisitsController',
                'permissions' => [
                    'clients_itinerary_view' => 'عرض خطوط السير',
                    'clients_itinerary_add' => 'إضافة خط سير',
                    'clients_itinerary_edit' => 'تعديل خط سير',
                    'clients_itinerary_filter' => 'تصفية خطوط السير',
                    'clients_itinerary_export' => 'تصدير خطوط السير',
                    'clients_view_by_daily_itinerary' => 'عرض العملاء حسب خط السير اليومي',
                    /* شاشة «خط سيري اليومي» (`DailyItineraryController`). الإضافةُ
                       لغيره تعمل على مندوبي نطاقه (`DataScope`) وبعملاء عقد «تابع
                       لـ» التي يملكها، وتظهر عند المندوب «أضافه: الاسم (الدور)». */
                    'clients_daily_itinerary_view' => 'عرض خط سيري اليومي',
                    'clients_daily_itinerary_add_client' => 'إضافة عميل إلى خط سيري اليومي',
                    'clients_daily_itinerary_add_for_others' => 'إضافة عميل إلى خط سير مندوبٍ آخر (حسب «تابع لـ»: فرع، منطقة، مجموعة…)',
                    'clients_visits_track' => 'تتبّع زيارات العملاء',
                    'clients_hide_from_map' => 'إخفاء العميل من الخريطة',
                    /* شاشة متابعةٍ إداريّة فقط — «ملاحظات اليوم»
                       (`ItineraryController::getTodayNotes`) تعرض زيارات كل
                       المناديب وملاحظاتهم دون أزرار إجراء (متابعة واطلاع لا
                       تنفيذ). كانت محروسةً بـ`! isEmployee()` ثابتاً بالكود؛
                       صارت صلاحيةً كأي شاشةٍ أخرى تُمنح وتُسحب من شاشة الأدوار. */
                    'clients_itinerary_today_notes_view' => 'عرض ملاحظات اليوم (متابعة إدارية لكل المناديب)',
                    /* راوتات «مساحة عمل الزيارة» (`VisitWorkspaceController`) كانت
                       محروسةً بـ`clients_visits_track|clients_itinerary_add` فقط —
                       صحيحٌ وظيفياً، لكن بلا اسمٍ خاصٍّ بالشاشة يظهر في قائمة
                       الصلاحيات فيصعب على من يبني دوراً جديداً أن يجدها. أُضيفت
                       كخيارٍ إضافي على الراوتات (OR) لا بديلاً — من يحمل إحدى
                       الصلاحيتين القديمتين يبقى يدخل الشاشة كما كان. */
                    'clients_visit_workspace_access' => 'الدخول إلى مساحة عمل الزيارة (إتمام الزيارة)',
                    /* إنهاء الزيارة وإلغاؤها فعلان يُقفلان الزيارة ويكتبان
                       نتيجتها في التقارير — كانا متاحَين لكلّ من يفتح مساحة
                       العمل. فُصلا بصلاحيتين: من يُسجّل الزيارة ليس بالضرورة
                       من يُقرّر إغلاقها.

                       والاثنان يتحقّقان من الموقع في الخادم فوق ذلك — الصلاحية
                       تقول «من»، والموقع يقول «من أين». */
                    'clients_visit_complete' => 'إنهاء الزيارة والتسجيل والخروج',
                    'clients_visit_cancel' => 'إلغاء الزيارة',
                    /* تجاوزُ فحص الموقع في بدء الزيارة وإنهائها وإلغائها —
                       لمشرفٍ يُغلق زيارةَ مندوبٍ تعطّل، أو لحالاتٍ لا يصل فيها
                       GPS. بدونها يُشترط أن يكون المستخدم ضمن نطاق موقع
                       العميل. تشمل ملاحظة الزيارة كذلك: من يبدأ الزيارة من
                       بعيد ولا يستطيع كتابة ملاحظتها يعلق عند خطوتها الثالثة. */
                    'clients_visit_any_location' => 'بدء الزيارة وإنهاؤها وإلغاؤها من أي موقع (بلا التحقّق من موقع العميل)',
                    /* «دوام المسؤول» يُسجَّل مرّةً على مسؤول العميل نفسه
                       (`client_employee_notes.shift_start/shift_end`) كخطوةٍ
                       إلزاميّة في مساحة عمل الزيارة. تسجيلُه أوّل مرّة متاحٌ
                       لكل من يفتح الشاشة — بلا هذه الصلاحية تُحبَس الزيارة كلّها
                       عند خطوةٍ لا يستطيع أحدٌ تجاوزها. أمّا **تغييرُ** دوامٍ
                       مسجَّلٍ من قبل فهو ما تحرسه هذه الصلاحية: بيانٌ تُبنى عليه
                       جدولةُ الزيارات القادمة، فلا يعيد كتابته كل مندوبٍ يمرّ. */
                    'clients_change_contact_shift' => 'تغيير دوام مسؤول العميل بعد تسجيله',
                    /* المندوب في الميدان يضيف عميلاً جديداً ثم يريد زيارته
                       فوراً. بلا هذه الصلاحية ينتهي «حفظ العميل» عند فهرس
                       العملاء، فيبحث هو عن العميل ثم عن «تسجيل زيارة» —
                       ثلاث شاشاتٍ بين الحفظ وبدء الزيارة.

                       بها يفعل `ClientController::store()` شيئين إضافيين:
                       يضيف العميل إلى خطّ سير **مُنشئه** اليوم، ثم يفتح له
                       مساحة عمل الزيارة مباشرةً.

                       صلاحيةٌ لا مفتاحُ إعدادات عمداً: القرار يختلف بين
                       دورٍ ودور في المنشأة نفسها — مندوبُ الميدان يريدها،
                       ومُدخِلُ البيانات في المكتب الذي يضيف عملاء دفعةً
                       واحدة لا يريد أن يُقحَم في زيارةٍ مع كل حفظ.

                       وهي فعلٌ صريحٌ يقوم به المستخدم لنفسه — النظامُ لا
                       يُضيف عميلاً لخطّ سير أحدٍ من تلقاء نفسه (أُزيلت
                       «الزيارات التلقائية» في 2026-09-14). */
                    'clients_start_visit_on_create' => 'بدء زيارة العميل فور إضافته (يُضاف إلى خطّ سير مُنشئه وتُفتح مساحة الزيارة)',
                    /* كتالوج المنافسين (`competitors`) — يختار منه المندوب
                       عند رصد منافسٍ داخل ملاحظة الزيارة. صلاحياتُ إدارته
                       منفصلةٌ عن الرصد نفسه: المندوب يرصد ولا يضيف أسماءً
                       إلى الكتالوج، وإلّا عاد التشتّت الذي وُجد الجدولُ
                       لينهيه («بيبسي» و«Pepsi» و«بيبسى»). */
                    'clients_competitors_view' => 'عرض قائمة المنافسين',
                    'clients_competitors_add' => 'إضافة منافس',
                    'clients_competitors_edit' => 'تعديل منافس',
                    'clients_competitors_delete' => 'حذف منافس',
                    /* تفويضٌ لا قدرة: تمنع حاملَها من فتح `clients.show` مباشرةً
                       فتُعيده دوماً إلى تسجيل/متابعة الزيارة (من قائمة «الإجراءات»
                       في الفهرس ومن رابط الشاشة مباشرة على السواء) — انظر
                       `ClientController::show()` و`client_cards.blade.php`.
                       لا تمنح وحدها دخول مسار الزيارة؛ تُمنح دوماً مع
                       `clients_visits_track`/`clients_itinerary_add`، وإلا
                       اصطدم حاملُها بصلاحية الراوت نفسه.
                       يُستثنى منها فردٌ بـ`clients_exempt_visit_before_view`
                       في مجموعة «الإعفاءات». */
                    'clients_must_visit_before_view' => 'يجب تسجيل الزيارة قبل عرض العميل',
                ],
            ],

            'visit_justifications' => [
                'label' => 'تبريرات وإعفاءات الزيارات',
                'description' => 'فترات تبرير/إعفاءٍ كاملة (موظف + عميل + نطاق تاريخ) بمعزلٍ عن صلاحية الموظف الحالية، ومراجعة تبريرات الزيارات الفردية التي يقدّمها الموظف نفسه.',
                'icon' => 'alert-circle',
                'controller' => 'Client/VisitJustificationController + IncompleteVisitsController',
                'permissions' => [
                    /* منقولتان من كتالوج HR (`employee_visit_justifications_view`
                       القديمة استُبدلت بالصلاحيات الحبيبية أدناه؛
                       `staff_visit_justification_exempt` بقيت باسمها) —
                       متحكّماهما الحقيقيّان (`IncompleteVisitsController`
                       الذاتي-الخدمة، و`VisitJustificationController` الإداري)
                       في Modules/Client لا HR. راجع
                       database/migrations/2026_09_02_090100_add_visit_justification_period_permissions.php
                       لمنح الصلاحيات الجديدة للأدوار التي كانت تملك القديمة. */
                    'clients_visit_justifications_view_all' => 'عرض كل فترات تبريرات/إعفاءات الزيارات',
                    'clients_visit_justifications_view_own' => 'عرض تبريراته وإعفاءاته هو فقط',
                    'clients_visit_justifications_create' => 'إضافة فترة تبرير/إعفاء زيارات',
                    'clients_visit_justifications_edit' => 'تعديل فترة تبرير/إعفاء زيارات',
                    'clients_visit_justifications_delete' => 'حذف فترة تبرير/إعفاء زيارات',
                    'clients_visit_justifications_approve' => 'اعتماد أو رفض فترة تبرير/إعفاء، وتبريرات الزيارات الفردية',
                    'staff_visit_justification_exempt' => 'الإعفاء من تقديم تبرير الزيارات غير المكتملة',
                ],
            ],

            'exemptions' => [
                'label' => 'الإعفاءات (تعطيل ضوابط رقابية)',
                'description' => 'تُعفي حاملَها من تحقّقٍ يفرضه النظام على غيره — تُمنح فرداً فرداً لا مع الدور كاملاً.',
                'icon' => 'unlock',
                'controller' => 'Client/ClientsController + VisitsController',
                'permissions' => [
                    'clients_exempt_visit_location' => 'الإعفاء من التحقق من موقع الزيارة',
                    /* أزرار شاشة عرض العميل كلُّها مجمَّدةٌ على الموظّف حتى
                       يسجّل ملاحظة زيارةٍ لهذا العميل اليوم (أو ملاحظةً
                       رئيسية)، وزرُّ تبديل البضاعة يشترط فوقها أن يكون اليوم
                       يومَ خطّ سيره. هذه تعفي حاملَها من الاثنين معاً فتفتح
                       له الأزرار بلا زيارة.

                       **لا تُعفي من «معاملة اليوم»**: شرطُ وجود فاتورة أو
                       سندٍ أو إشعارٍ دائنٍ اليوم قبل تبديل البضاعة ضابطٌ
                       رقابيّ أُلغي استثناؤه عمداً بطلبٍ سابق، فلم يُعَد
                       فتحُه من هنا. راجع ClientController::show(). */
                    'clients_exempt_action_locks' => 'الإعفاء من تجميد أزرار شاشة العميل (بلا زيارة)',
                    /* نقيضُ `clients_must_visit_before_view` لا بديلُها: تُمنح
                       لمن يحمل الإلزام (بالدور) ويُراد استثناؤه هو وحده منه —
                       فيفتح `clients.show` مباشرةً بلا زيارةٍ مكتملةٍ اليوم.
                       تُفحص قبل الإلزام في `ClientController::show()` وفي
                       `client_cards.blade.php` معاً، فلا يبقى الإلزام في مكانٍ
                       ويسقط في آخر. مَن لا يحمل الإلزام أصلاً لا تعنيه. */
                    'clients_exempt_visit_before_view' => 'الإعفاء من إلزام تسجيل الزيارة قبل عرض العميل',
                    // بلا أثرٍ بعد اليوم: زرّ استخدامها (`toggleExemptExchange`)
                    // أُلغي، وشرط «معاملة اليوم» لتبديل البضاعة لم يعد يقرأ
                    // `exempt_exchange_transaction` إطلاقاً — أُبقيت الصلاحيةُ
                    // والعمود بلا حذفٍ فقط، لا فعّالةً. راجع actions-buttons.blade.php
                    'clients_exempt_exchange_transaction' => 'الإعفاء من قيد عملية الاستبدال (معطَّلة حالياً — بلا أثر)',
                    // مَن يمنح الإعفاء لا مَن يحمله — كان زرّا الإعفاء في ملفّ
                    // العميل مفتوحَين لكل من يفتح الشاشة، فيُعفي نفسه بضغطة
                    'clients_manage_exemptions' => 'منح الإعفاءات وسحبها من العملاء',
                ],
            ],

            'activity' => [
                'label' => 'سجل النشاط وتقارير العملاء',
                'description' => 'من فعل ماذا على ملف العميل، وتقارير العملاء بنطاقيها: الكل أو عملاؤه هو.',
                'icon' => 'activity',
                'controller' => 'Client/ClientsController + Reports',
                'permissions' => [
                    'clients_view_all_activity_logs' => 'عرض سجلات نشاط كل العملاء',
                    'clients_view_own_activity_log' => 'عرض سجل نشاط عملائه هو',
                    // مَن فتح ملف العميل ومتى — جدول client_profile_views
                    'clients_view_profile_access_log' => 'عرض سجل دخول الموظفين إلى ملف العميل',
                    /* ══════════ كانت هنا صلاحيتا نطاقٍ لا صلاحيتا عملية ══════════

                       `clients_view_all_reports` و`clients_view_own_reports` —
                       «كل العملاء» مقابل «عملائه هو» — هما بعينهما انفجارُ
                       الأسماء الذي يصفه
                       {@see \App\Constants\permissions\DataScopePermissions}:
                       عمليةٌ واحدة مضروبةٌ في نطاقين. وقد صار النطاقُ محوراً
                       مستقلاً تقرؤه التقاريرُ فعلاً عبر `App\Support\DataScope`،
                       فمن أراد قصرَ مندوبٍ على عملائه منحه `data_scope_own`
                       لا صلاحيةَ تقريرٍ ثانية.

                       حُذفتا ([الترحيل 2026_09_12_220000]) بعد نقل مواضعها
                       الخمسة: بوابةُ اللوحة على `clients_dashboard_view`،
                       وشاشةُ الأهداف على `clients.reports.targets`، وبوابةُ
                       التقارير على `client_reports_view|clients.reports.view`. */
                    'client_reports_view' => 'الدخول إلى تقارير العملاء (بوابة القائمة الجانبية)',
                    /* صلاحية لوحة تحكّم العملاء المستقلة — كانت الشاشة تُفتح
                       بتركيبةٍ من ثلاث صلاحياتٍ أخرى («تقارير الكل/عملائه/
                       عرض كل العملاء») بلا اسمٍ خاصٍّ بها يظهر في شاشة الأدوار
                       بوضوح. أُضيفت كخيارٍ إضافي على الراوت لا بديلاً — من
                       يحمل إحدى الثلاث القديمة يبقى يرى اللوحة كما كان. */
                    'clients_dashboard_view' => 'عرض لوحة تحكّم العملاء',
                ],
            ],

            /*
             * ═══════════ تقارير العملاء — صلاحيةٌ لكلّ تقرير ═══════════
             *
             * كانت التسعةُ كلُّها خلف تركيبةٍ واحدة تُكرَّر حرفياً في كلّ
             * متحكّم: `clients_view_all_reports|clients_view_own_reports|client_reports_view`.
             * فمن فُتح له تقريرُ المواعيد انفتح له كشفُ الحساب وأعمارُ
             * الديون ومديونيةُ العملاء معاً — لا سبيلَ لمنح مندوبٍ جدولَ
             * مواعيده دون أرصدة المنشأة.
             *
             * والتقسيمُ هنا هو نفسُه الذي في `SalesPermissions::groups()['reports']`
             * و`PurchasesPermissions`، فتقولُ الوحداتُ الثلاث الشيءَ نفسَه
             * بالصيغة نفسِها: بوابةٌ عامة، ثمّ تقريرٌ تقرير، ثمّ التصدير.
             *
             * وهذه صلاحياتُ **عملية** خالصة: «على أيّ عملاء؟» سؤالٌ يجيب
             * عنه محورُ `data_scope_*` وحدَه بعد اليوم.
             */
            'reports' => [
                'label' => 'تقارير العملاء',
                'description' => 'تقارير العملاء — لكلٍّ صلاحيته كي يُمنح المندوب جدولَ مواعيده دون أرصدة المنشأة. ونطاقُ ما يراه في كلٍّ منها من «نطاق البيانات».',
                'icon' => 'bar-chart-2',
                'controller' => 'Client/Customers/*',
                'permissions' => [
                    'clients.reports.view' => 'الدخول إلى تقارير العملاء (فهرس التقارير)',
                    'clients.reports.balances' => 'تقرير أرصدة العملاء',
                    'clients.reports.debts' => 'تقرير مديونية العملاء',
                    'clients.reports.debt_aging' => 'تقرير أعمار الديون',
                    'clients.reports.statement' => 'كشف حساب العميل',
                    'clients.reports.guide' => 'دليل العملاء والخريطة',
                    'clients.reports.appointments' => 'تقرير مواعيد العملاء',
                    'clients.reports.installments' => 'تقرير أقساط العملاء',
                    'clients.reports.targets' => 'تقرير أهداف العملاء',
                    'clients.reports.wallets' => 'تقرير محافظ العملاء',
                    'clients.reports.competitors' => 'تقرير المنافسين',
                    'clients.reports.assignments' => 'تقرير العملاء المسندين للموظفين',
                    'clients.reports.export' => 'تصدير تقارير العملاء',
                ],
            ],

            'transfers' => [
                'label' => 'تسليم الفواتير واستلامها',
                'description' => 'تحويل فاتورة عميل من موظفٍ إلى آخر، وقبولُها أو رفضُها أو إلغاءُ الطلب — نقلُ عهدةٍ ومسؤوليةِ تحصيل، لا عرضُ بيانات.',
                'icon' => 'repeat',
                'controller' => 'Client/InvoiceTransferController',
                'permissions' => [
                    'clients_invoice_transfer_view' => 'عرض تحويلات الفواتير',
                    'clients_invoice_transfer_send' => 'تحويل فاتورة إلى موظف آخر',
                    'clients_invoice_transfer_respond' => 'قبول أو رفض تحويل وارد',
                    'clients_invoice_transfer_cancel' => 'إلغاء طلب تحويل أرسله',
                ],
            ],

            'crm' => [
                'label' => 'إدارة علاقات العملاء',
                'description' => 'شاشة شجرة العلاقات: العميل ومجموعته وموظفيه وخطّه الزمني — عرضٌ فقط، فتسجيلُ الإجراء نفسه تحرسه صلاحيات «المتابعة» أعلاه، عدا إرسال الإشعار الذي له صلاحيته الخاصة هنا.',
                'icon' => 'users',
                'controller' => 'Client/CRMController',
                'permissions' => [
                    'clients_crm_view' => 'عرض شاشة علاقات العملاء',
                    'clients_crm_send_notification' => 'إرسال إشعار للعميل من شاشة إدارة العلاقات',
                ],
            ],

            'attendance' => [
                'label' => 'حضور العملاء',
                'description' => 'تسجيل حضور العملاء وعرضه — للمنشآت التي تتابع دوام عملائها لا موظفيها فقط.',
                'icon' => 'user-check',
                'controller' => 'Client/CustomerAttendanceController',
                'permissions' => [
                    'customer_attendance_display' => 'عرض حضور العملاء',
                    'customer_attendance_manage' => 'إدارة حضور العملاء',
                ],
            ],

            /* محافظ العملاء — البند ١١ من تقرير الإصلاح. كانت في وحدة
               المبيعات (`sales.customer_wallet.*`) وهي هناك تعدّل رصيد
               ذمم العميل لا محفظته، فانتقلت مع الشاشة إلى هنا. */
            'wallet' => [
                'label' => 'محافظ العملاء',
                'description' => 'رصيد العميل عندنا: إيداعٌ وسحبٌ وسدادُ فواتير منه.',
                'icon' => 'credit-card',
                'controller' => 'Client/ClientWalletController',
                'permissions' => [
                    'clients.wallet.view' => 'عرض محافظ العملاء وكشف حركتها',
                    'clients.wallet.deposit' => 'إيداع في محفظة عميل',
                    'clients.wallet.withdraw' => 'سحب من محفظة عميل',
                    'clients.wallet.pay_invoice' => 'سداد فاتورة من محفظة العميل',
                ],
            ],

            'forgotten_visits' => [
                'label' => 'إدارة الزيارات المنسية',
                'description' => 'التعامل مع الزيارات التي بدأها الموظف ولم يُنهِها — عرضها وإنهاؤها عن بُعد.',
                'icon' => 'clock-alert',
                'controller' => 'Client/ForgottenVisitsController + VisitController',
                'permissions' => [
                    /* الموظف يستطيع عرض زياراته هو المنسية وإنهاؤها من
                       البعيد إذا نسي عند العميل — بلا هذه الصلاحية
                       يُحجَب كلياً عن العمل. صلاحيةٌ لا تحتاج إدارةً
                       عادةً: تُمنح لكل موظف نشط. */
                    'clients_view_forgotten_visits_own' => 'عرض زياراته المنسية',
                    'clients_close_forgotten_visits_own' => 'إنهاء زيارة منسية له',
                    /* المدير/المشرف يرى ويُنهي زيارات مرؤوسيه — لأن موظفاً
                       قد لا يرد على الهاتف أو لا يستطيع إنهاء الزيارة
                       فيضطر المدير للتصرف. تحتاج صلاحيةً إدارية قليلة الاستخدام. */
                    'clients_view_forgotten_visits_all' => 'عرض الزيارات المنسية لجميع الموظفين',
                    'clients_force_close_forgotten_visits' => 'إنهاء أي زيارة منسية (زيارة موظف آخر)',
                    /* إلغاء الزيارة بدل إنهاؤها — إذا لم يحدث شيء فعل،
                       أو كانت الزيارة خطأً وتحتاج إلى إلغاءٍ تام. */
                    'clients_cancel_forgotten_visits' => 'إلغاء زيارة منسية (بسبب)',
                ],
            ],

            /* البوابة التي يدخلها العميل بنفسه. إدارتُها (المفاتيح ودور
               العميل) تحرسها `settings_customer_permissions` في مجموعة
               «الإعدادات»؛ وهنا ما يفعله الموظّف بالبوابة لا ما يضبطه فيها. */
            'portal' => [
                'label' => 'بوابة العميل',
                'description' => 'معاينة البوابة بعين العميل — دخولٌ مؤقّتٌ بحسابه لرؤية ما يراه بالضبط.',
                'icon' => 'monitor',
                'controller' => 'Client/ClientPortalPreviewController',
                'permissions' => [
                    /* انتحالُ حسابٍ لا عرضُ شاشة: حاملُها يدخل بحساب العميل
                       فعلاً (مقصوراً على البوابة، وكلُّ بدءٍ وعودةٍ مسجَّل).
                       تُمنح لمن يدعم العملاء ويحتاج أن يرى شاشتهم، لا مع
                       «عرض العملاء» تلقائياً. لا يُنشئ حاملُها شيئاً باسم
                       العميل: البوابة نفسها عرضٌ لا إنشاء، عدا تعديل الملفّ
                       المقصور على بيانات التواصل. */
                    'clients_portal_preview' => 'معاينة بوابة العميل بحسابه',
                ],
            ],

            'settings' => [
                'label' => 'إعدادات العملاء',
                'description' => 'تصنيفات العملاء وحالات المتابعة وصلاحيات بوابة العميل.',
                'icon' => 'settings',
                'controller' => 'Client/ClientSettingsController',
                'permissions' => [
                    'clients_edit_client_settings' => 'تعديل إعدادات العملاء',
                    'settings_customer_categories' => 'إدارة تصنيفات العملاء',
                    'settings_customer_followup_status' => 'إدارة حالات متابعة العملاء',
                    'settings_customer_permissions' => 'إدارة صلاحيات بوابة العميل',
                    'clients_module_settings_view' => 'عرض إعدادات وحدات العملاء',
                    'clients_module_settings_update' => 'تعديل إعدادات وحدات العملاء',
                ],
            ],
        ];
    }
}
