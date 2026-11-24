<?php

namespace App\Support;

use App\Models\GeneralClientSetting;
use Illuminate\Support\Collection;

/**
 * سجلّ إعدادات موديول العملاء — المصدر الوحيد لتعريفها وقراءتها.
 *
 * **لماذا سجلّ؟**
 *
 * كانت إعدادات العملاء موزّعةً على شاشتين وثلاثة مصادر: شاشة «عام» تحفظ
 * تفعيل الحقول الإضافية، وشاشة «إعدادات وحدات العملاء» تحفظ عشرة مفاتيح،
 * وجدول `type_client` يحفظ نوع العميل ولا يقرؤه أحد. وكل مفتاحٍ جديد كان
 * يُكتب أربع مرّات: في الهجرة، وفي `index()`، وفي `update()`، وفي القالب —
 * فيسهل أن يُنسى في إحداها، وقد نُسي: `store()` في شاشة «عام» تتجاهل
 * `type` الذي يرسله نموذجها منذ أوّل يوم.
 *
 * هنا تُعرَّف المجموعة مرّةً واحدة — اسمُها وأعمدتُها وخياراتُها
 * وافتراضاتُها — ويُشتقّ منها كلُّ شيء: التحقّق، والحفظ، وفرق القيم في
 * سجلّ النشاط، وقراءةُ المستهلكين.
 *
 * **عمودٌ أم خيار؟**
 *
 *   `columns` ← أعمدةٌ حقيقية في `general_client_settings`، وهي القديمة
 *               وحدها: أربعةَ عشرَ موضعاً في الوحدة يقرؤها بأسمائها.
 *   `options` ← حقيبة `options` (JSON). كلُّ جديدٍ يسكنها، فإضافةُ إعدادٍ
 *               لا تعني هجرةً وعموداً يبقى فارغاً في كل صفٍّ سوى واحد.
 *
 * @see \App\Helpers\ClientSettingsHelper واجهة القراءة المختصرة للمستهلكين
 * @see \Modules\Client\Http\Controllers\ClientModuleSettingsController الشاشة
 */
class ClientSettings
{
    /** @var array<string, GeneralClientSetting> memoized لعمر الطلب */
    private static array $rows = [];

    /**
     * تعريف كل مجموعة إعدادات: اسمُها المعروض، وتبويبُها، وأعمدتُها،
     * وخياراتُها بافتراضاتها.
     *
     * الترتيب هنا هو ترتيب الظهور داخل التبويب.
     *
     * @return array<string, array{name: string, tab: string, columns?: array<string, mixed>, options?: array<string, mixed>}>
     */
    public static function definitions(): array
    {
        return [
            // ─────────── العميل نفسه ───────────
            'client_defaults_settings' => [
                'name' => 'افتراضيات العميل',
                'tab' => 'clients',
                'columns' => [
                    'default_new_client_status_id' => null,
                ],
                'options' => [
                    'client_type_scope' => 'Both',      // Both|individual|commercial
                    'require_phone' => true,
                    // الجوّال حقلٌ مستقلّ عن الهاتف في نموذج العميل، وكان
                    // `nullable` مثبَّتاً بلا مفتاحٍ يقابله — فمنشأةٌ تعتمد
                    // الجوّال وحده لا تستطيع إلزامه
                    'require_mobile' => false,
                    'unique_phone' => false,
                    // الرقم الضريبي لا «الهوية الوطنية»: `clients` لا عمودَ
                    // فيها بهذا الاسم، و`tax_number` هو حقلُ الهوية الفعليّ
                    // الذي تطلبه الفاتورة الضريبية
                    'require_tax_number' => false,
                    'require_email' => false,
                    // «وضع العميل» الافتراضي: يُختار سلفاً في نموذج الإضافة،
                    // ويُكتب تلقائياً على كل عميلٍ يُحفَظ بوضعٍ فارغ. `null`
                    // تعني بلا وضع. صار للحقل سيلكتٌ في نموذج الإضافة
                    // وإلزاميّته في `require_registration_stage` أدناه. انظر
                    // docs/CRM_Customer_Status_Gap_Analysis_Plan.md القسم 3
                    'default_registration_stage_id' => null,
                ],
            ],

            /** الحقول الإلزامية الأربعة التي كانت مثبَّتةً `required` في
             * `create.blade.php` و/أو غير محقَّقة فعلياً في `ClientRequest`.
             * `client_tree_branch_id` مستثنًى عمداً: حقلٌ بنيويٌّ يُستنتَج
             * منه `branch_id` مباشرةً في `store()`/`update()` — تعطيلُ
             * إلزاميّته يكسر إنشاء العميل، لا يُخفّف قاعدة عمل فحسب. */
            'client_required_fields_settings' => [
                'name' => 'الحقول الإلزامية في نموذج العميل',
                'tab' => 'clients',
                'options' => [
                    'require_trade_name' => true,
                    'require_commercial_registration' => true,
                    'require_category' => true,
                    /* «وضع العميل» (مرحلة التسجيل) صار حقلاً في نموذج
                       الإضافة لا قيمةً تُملأ من الإعدادات وحدها. مطفأً
                       يبقى اختيارياً ويأخذ الوضعَ الافتراضي حين يُترَك
                       فارغاً، ومشغَّلاً يُشترَط اختيارُه صراحةً. */
                    'require_registration_stage' => false,
                ],
            ],

            /* هل يحتاج تغييرُ حالة العميل من موظفٍ لا يملك صلاحية
               `clients_approve_status` موافقةَ مديرٍ أولاً (طلبٌ معلَّق ثم
               اعتماد/رفض)، أم يُغيَّر مباشرة لأيّ موظفٍ يملك صلاحية
               `clients_change_status` العادية؟ `true` تُبقي السلوك الحالي —
               مسار الموافقة كان مفروضاً دوماً بلا مفتاح إيقاف. */
            'client_status_change_settings' => [
                'name' => 'اعتماد تغيير حالة العميل',
                'tab' => 'clients',
                'options' => [
                    'require_manager_approval' => true,
                ],
            ],

            /* بوابة العميل — أيُّ دورٍ يُعدّ «دور العميل».

               كان `ClientAccessMiddleware` يفحص `hasRoleName('client')`،
               واسمٌ مثبَّتٌ في الكود لدورٍ **غير موجودٍ في جدول الأدوار**
               يعني حارساً لا يعمل أبداً. صار الدور يُختار من شاشة «صلاحيات
               العميل» بمعرّفه، فأيُّ دورٍ يصلح ولا اسمَ مقدَّسٌ في الكود.

               `null` = لم يُختَر دورٌ بعد: البوابة لا تحصر أحداً، ولا
               تُخطئ فتحصر موظّفاً. لا تُشغَّل إلّا باختيارٍ صريح.

               ليست في `ClientModuleSettingsController::FIELDS` عمداً —
               تُحفظ من شاشة الصلاحيات وحدها لا من شاشة الوحدات. */
            'client_portal_settings' => [
                'name' => 'بوابة العميل',
                'tab' => 'clients',
                'options' => [
                    'portal_role_id' => null,
                ],
            ],

            'location_group_settings' => [
                'name' => 'إعدادات الموقع والمجموعة',
                'tab' => 'clients',
                'columns' => [
                    'location_required' => true,
                    'group_required' => true,
                ],
            ],

            'upload_limits_settings' => [
                'name' => 'حدود حجم المرفقات',
                'tab' => 'clients',
                'columns' => [
                    'max_upload_size_kb' => 102400,
                ],
                /* ضغطُ الصور قبل رفعها — يقع في متصفّح المندوب لا في الخادم:
                   الشبكة في الميدان ضعيفة، والمهمّ ألّا تُرفع الصورة الأصلية
                   أصلاً. كانت القيم مثبَّتةً في قوائم داخل المودالات، ومن
                   أراد ضغطاً أقوى لكل مندوبيه لا سبيل له.

                   `image_quality_percent` جودة JPEG (١٠-١٠٠)، و
                   `image_max_dimension_px` أطولُ ضلعٍ بعد التصغير. */
                'options' => [
                    'image_quality_percent' => 60,
                    'image_max_dimension_px' => 1280,
                ],
            ],

            'map_settings' => [
                'name' => 'إعدادات إظهار العملاء على الخريطة',
                'tab' => 'clients',
                'columns' => [
                    'map_hide_duration_hours' => 24,
                ],
            ],

            /* «أزرار شاشة عرض العميل» (`client_show_actions_settings`) أُزيلت
               (2026-09-15): كلُّ زرٍّ في `actions-buttons` تحكمه صلاحيةُ مساره، ومفتاحُ
               إخفاءٍ فوقها كان يُخفي الزرّ عن الشركة كلّها لا عن دور. */

            // ─────────── الزيارات ───────────

            /* «صلاحيات الموظف على الزيارات» (`employee_visit_permissions`) أُزيلت
               (2026-09-15): كانت تُقرأ في `VisitController::update()` على مسارٍ لا
               تستدعيه شاشة. */

            'visit_note_requirement_settings' => [
                'name' => 'اشتراط ملاحظة الزيارة لفتح إجراءات العميل',
                'tab' => 'visits',
                'options' => [
                    'required' => true,
                ],
            ],

            'visit_proximity_settings' => [
                'name' => 'نطاق القرب المسموح للزيارات',
                'tab' => 'visits',
                'columns' => [
                    'visit_proximity_radius_km' => 0.3,
                ],
            ],

            /**
             * العميل بعد «إنهاء الزيارة» — يُخفى من قائمة المندوب وخريطته
             * بقيّة اليوم، ويمكنه إرجاعه لخطّ سيره في اليوم نفسه.
             *
             * `hide_after_complete`: الإخفاء نفسه. كان مثبَّتاً بلا مفتاح في
             * `EmployeeClientVisit::finishedTodayClientIdsFor()`؛ صار يمرّ من
             * `hiddenFinishedClientIdsFor()`. الافتراض `true` يحفظ السلوك
             * القائم. والإخفاء ليومه وحده: خطّ سير الغد صفوفٌ جديدة.
             *
             * `allow_return_same_day`: زرّ «إرجاع لخط السير» في قائمة «أُنهيت
             * زياراتهم اليوم» — يفتح للعميل صفّ خطّ سيرٍ ثانياً لليوم نفسه،
             * كما يفعل «سأعود لهذا العميل اليوم» عند الإنهاء لكن بعده.
             */
            'finished_visit_client_settings' => [
                'name' => 'العميل بعد إنهاء الزيارة',
                'tab' => 'visits',
                'options' => [
                    'hide_after_complete' => true,
                    'allow_return_same_day' => true,
                ],
            ],

            /**
             * أقسام «مساحة عمل الزيارة» (`visit_workspace.blade.php`) القابلة
             * للإظهار/الإخفاء — كلّ مفتاحٍ بطاقة أو تبويب مستقل في الشاشة.
             * الافتراض `true` لكل قسم يحافظ على السلوك الحالي (كل الأقسام
             * ظاهرة) حتى يُخفي مدير الإعدادات ما لا يحتاجه عمداً.
             */
            /*
                حُذفت ستة مفاتيح كانت هنا (`show_tasks_card`,
                `show_assets_card`, `show_asset_photos_card`,
                `show_outcome_card`, `show_tasks_tab`, `show_assets_tab`):
                بطاقاتُها وتبويباتُها خرجت من مساحة عمل الزيارة كلّها — العهد
                والأصول والمواعيد لم تعد جزءاً منها، ونتيجة الزيارة صارت
                تُحسَب لا تُختار. مفتاحٌ لا يحكم شيئاً أسوأ من غيابه: يُطفئه
                المدير فلا يتغيّر شيء.
            */
            'visit_workspace_sections_settings' => [
                'name' => 'أقسام مساحة عمل الزيارة',
                'tab' => 'visits',
                'options' => [
                    'show_location_card' => true,
                    'show_quick_actions_card' => true,
                    'show_notes_card' => true,
                    'show_contact_card' => true,
                    'show_details_card' => true,
                    'show_notes_tab' => true,
                    'show_competitors_tab' => true,
                    /* خطوة «رصد المنافسين» في الزيارة — بعد الملاحظة وقبل
                       المستندات، بمودالٍ مستقلّ. مُشغَّلاً (الافتراض): لا تُفتح
                       المستندات ولا تُنهى الزيارة قبل الرصد (أو التصريح بـ«لا
                       يوجد منافس»). مطفأً: تسقط الخطوة كلّها. «سأعود لهذا
                       العميل» لا يشترطها في الحالين. */
                    'show_competitor_capture' => true,
                    /* حقلا ملاحظة الزيارة: «عدد العهدة الموجودة» و«المرفقات».
                       كانا إلزاميَّين مثبَّتَين في `ClientNoteController::addnotes()`
                       بلا أي مفتاح — منشأةٌ لا عهدة لها عند عملائها كانت
                       تُجبَر على كتابة صفرٍ في كل ملاحظة، ومن لا يريد صوراً
                       لا يستطيع إيقافها. المفتاح يقرّر الظهور والإلزام معاً:
                       مطفأً لا يُعرض الحقل ولا يُطلب. */
                    'note_deposit_count' => true,
                    'note_photo' => true,
                    /* أقلّ عددٍ من الصور يجب إرفاقه بالملاحظة. كان `min:1`
                       مثبَّتاً في `ClientNoteController::addnotes()`، ومن أراد
                       صورتين (واجهة المحل والرفّ مثلاً) لا سبيل له. يسري على
                       ملاحظة الزيارة و«ملاحظة الزيارة» المختصرة وشاشة إضافة
                       الملاحظة الرئيسية معاً — كلُّها تمرّ بـ`addnotes()`.
                       يُقرأ فقط متى كان `note_photo` مُشغَّلاً. */
                    'note_min_photos' => 1,
                    'show_docs_tab' => true,
                    'show_attachments_tab' => true,
                    // بطاقة «التوقيع» بنهاية الزيارة — تعطيلُها لا يمنع
                    // إنهاء/إلغاء الزيارة، يُسقط لوحتَي التوقيع فقط
                    'show_signature_section' => true,
                ],
            ],

            /* «تبريرات الزيارات غير المكتملة» (`visit_justification_settings`) أُزيلت
               (2026-09-15): الإعفاء منها صلاحية `staff_visit_justification_exempt`
               يقرؤها `CheckIncompleteVisits` نفسه، فالمفتاحُ العامّ فوقها زائد. */

            // ─────────── خطوط السير ───────────
            'route_tracking_settings' => [
                'name' => 'إعدادات تتبع خط السير',
                'tab' => 'itinerary',
                'columns' => [
                    'enable_route_tracking' => true,
                ],
            ],

            'itinerary_assignment_settings' => [
                'name' => 'تقييد إضافة العملاء لخط السير',
                'tab' => 'itinerary',
                'columns' => [
                    'itinerary_restrict_to_assigned_clients' => false,
                ],
            ],

            /* عن قصدٍ منفصلةٌ عن `itinerary_assignment_settings` أعلاه رغم
               تشابه الاسم: تلك تحكم من يُضاف لخطّ سير موظف (افتراضُها
               إباحةٌ)، وهذه تحكم قوائم العملاء في شاشات الإنشاء الخمس —
               الفواتير والسندات والإشعارات والمرتجعات وتبديل البضاعة —
               وكانت **مقيَّدةً دوماً بخط السير بلا إعداد** لأيّ موظف له
               بيانات خط سير. فافتراضُها هنا `true` ليحافظ على السلوك
               القائم؛ عكسُ افتراض المجموعة الأخرى كان سيُظهر كل العملاء
               فجأةً لكل موظف بمجرّد نشر هذا التغيير. */
            'create_screen_client_scope_settings' => [
                'name' => 'نطاق قوائم العملاء في شاشات الإنشاء',
                'tab' => 'itinerary',
                'options' => [
                    'restrict_to_itinerary' => true,
                ],
            ],

            'itinerary_order_settings' => [
                'name' => 'ترتيب الزيارات في خط السير',
                'tab' => 'itinerary',
                'options' => [
                    'order_strategy' => 'manual',        // manual|nearest|last_visit
                    'max_clients_per_day' => null,
                    'lock_after_start' => false,
                    'allow_reorder_by_employee' => true,
                ],
            ],

            // ─────────── المتابعة ───────────
            'appointment_settings' => [
                'name' => 'إعدادات المواعيد',
                'tab' => 'follow_up',
                'options' => [
                    'default_duration_minutes' => 30,
                    'reminder_hours_before' => 24,
                    'working_hours_start' => '08:00',
                    'working_hours_end' => '17:00',
                    'enforce_working_hours' => false,
                    'allow_overlap' => true,
                    'allow_past_dates' => false,
                    'auto_complete_past' => false,
                ],
            ],

            /* حدودُ لوحة تحكّم العملاء — كانت ١٤ يوماً و٦٠ يوماً مكتوبتين في
               الكود. تقرؤهما `ClientSettingsHelper::dashboardNeedsVisitDays()`
               و`dashboardOverdueDays()`، ويتجاوزهما شريطُ اللوحة لجلسةٍ واحدة. */
            'client_dashboard_settings' => [
                'name' => 'لوحة تحكّم العملاء',
                'tab' => 'follow_up',
                'options' => [
                    'needs_visit_days' => 30,
                    'overdue_days' => 60,
                    // صفوفُ الشرائح — تُنقّى وتُقرأ في `ClientDashboardSegments`
                    'lifecycle_segments' => ClientDashboardSegments::DEFAULT_LIFECYCLE,
                    'aging_segments' => ClientDashboardSegments::DEFAULT_AGING,
                ],
            ],

            // ─────────── الموظفون ───────────
            'assignment_notification_settings' => [
                'name' => 'إشعار الموظفين عند تغيير التعيين',
                'tab' => 'employees',
                'columns' => [
                    'notify_employee_on_assignment_change' => false,
                ],
            ],

            'client_employee_settings' => [
                'name' => 'إضافة موظف للعميل',
                'tab' => 'employees',
                'options' => [
                    'allow_multiple' => true,
                    'max_employees_per_client' => null,
                    /* الأقسام التي يُسحب منها موظّفو العملاء — معرّفات صفوف
                       `departments` من الهيكل التنظيمي. القائمة الفارغة تعني
                       **كل الأقسام**، لا «لا أحد»: منشأةٌ لم تضبط الإعداد بعدُ
                       يجب أن تبقى قوائمُها كما كانت. */
                    'assignable_departments' => [],
                ],
            ],

            // ─────────── الفواتير والديون ───────────
            'invoice_transfer_settings' => [
                'name' => 'استلام وتحويل الفواتير',
                'tab' => 'invoices',
                'options' => [
                    'require_approval' => true,
                    'reason_required' => true,
                    'notify_on_transfer' => true,
                    'allow_transfer_paid' => false,
                    'max_open_per_employee' => null,
                ],
            ],

            'debt_writeoff_settings' => [
                'name' => 'حد اعتماد شطب الديون',
                'tab' => 'invoices',
                'columns' => [
                    'bad_debt_writeoff_approval_threshold' => null,
                ],
            ],

            'exchange_transaction_settings' => [
                'name' => 'شرط المعاملة اليومية لتبديل البضاعة',
                'tab' => 'invoices',
                'options' => [
                    'require_transaction' => true,
                ],
            ],
        ];
    }

    /**
     * صفّ مجموعة — يُنشأ بافتراضاتها إن لم يوجد، ومحفوظٌ لعمر الطلب.
     *
     * الافتراضات تُكتب في الصفّ عند إنشائه أوّل مرّة فقط: تغييرُ افتراضٍ
     * في هذا الملف لا يعيد كتابة ما ضبطه المستخدم.
     */
    public static function group(string $key): GeneralClientSetting
    {
        if (isset(self::$rows[$key])) {
            return self::$rows[$key];
        }

        $definition = self::definitions()[$key] ?? null;

        $defaults = $definition['columns'] ?? [];

        if (! empty($definition['options'])) {
            $defaults['options'] = $definition['options'];
        }

        return self::$rows[$key] = GeneralClientSetting::getOrCreateByKey(
            $key,
            $definition['name'] ?? $key,
            $defaults
        );
    }

    /**
     * قيمة عمودٍ في مجموعة — الافتراض من التعريف عند الغياب.
     */
    public static function column(string $group, string $field): mixed
    {
        $value = self::group($group)->getAttribute($field);

        if ($value !== null) {
            return $value;
        }

        return self::definitions()[$group]['columns'][$field] ?? null;
    }

    /**
     * قيمة خيارٍ في حقيبة مجموعة — الافتراض من التعريف عند الغياب.
     *
     * الافتراض يُقرأ من التعريف لا يُمرَّر في النداء: مفتاحٌ أُضيف إلى
     * التعريف بعد أن حُفظ الصفّ لا يوجد في حقيبته، ولو قرأه كلُّ مستهلكٍ
     * بافتراضه هو لاختلفت الافتراضات بين موضعين.
     */
    public static function option(string $group, string $key, mixed $default = null): mixed
    {
        $value = self::group($group)->option($key, null);

        if ($value !== null) {
            return $value;
        }

        if (array_key_exists($key, self::definitions()[$group]['options'] ?? [])) {
            return self::definitions()[$group]['options'][$key];
        }

        return $default;
    }

    /** خيارٌ منطقي — القيم العائدة من JSON قد تكون `"0"`/`"1"`. */
    public static function flag(string $group, string $key, bool $default = false): bool
    {
        return (bool) self::option($group, $key, $default);
    }

    /** خيارٌ عددي، و`null` تبقى `null` — فرقٌ بين «صفر» و«بلا حدّ». */
    public static function integer(string $group, string $key, ?int $default = null): ?int
    {
        $value = self::option($group, $key, $default);

        return $value === null || $value === '' ? null : (int) $value;
    }

    /** خيارٌ عشري، و`null` تبقى `null`. */
    public static function decimal(string $group, string $key, ?float $default = null): ?float
    {
        $value = self::option($group, $key, $default);

        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * حقول العميل الإضافية — الصفوف التي لا مجموعةَ لها في التعريف أعلاه.
     *
     * هذه صفوفٌ من طرازٍ آخر تماماً: كلُّ صفٍّ حقلٌ في نموذج العميل
     * (`image`، `birth_date`، `credit_limit`…) و`is_active` وحده يعنيه.
     * تُميَّز بالاستثناء لا بعمودٍ يفرّقها، فلا هجرةَ لتصنيفٍ يُشتقّ.
     *
     * @return Collection<int, GeneralClientSetting>
     */
    public static function clientFields(): Collection
    {
        return GeneralClientSetting::query()
            ->whereNotIn('key', array_keys(self::definitions()))
            ->orderBy('id')
            ->get();
    }

    /** أمُفعَّلٌ حقلُ العميل الإضافي؟ — الغياب تفعيلٌ، كما كان قبل الشاشة. */
    public static function fieldEnabled(string $key, bool $default = true): bool
    {
        $row = GeneralClientSetting::where('key', $key)->first();

        return $row ? (bool) $row->is_active : $default;
    }

    /** إسقاط الحفظ لعمر الطلب — يُستدعى بعد الحفظ في الشاشة. */
    public static function forget(): void
    {
        self::$rows = [];
    }
}
