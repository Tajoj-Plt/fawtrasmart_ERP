<?php

namespace App\Helpers;

use App\Support\ClientSettings;

/**
 * واجهة القراءة المختصرة لإعدادات موديول العملاء.
 *
 * التعريف والافتراضات في `App\Support\ClientSettings` وحده — هذا الصنف
 * أسماءٌ ذاتُ معنى فوقه، فالمستهلك يكتب `dashboardNeedsVisitDays()` لا
 * `ClientSettings::integer('client_dashboard_settings', 'needs_visit_days')`.
 *
 * @see \App\Support\ClientSettings
 */
class ClientSettingsHelper
{
    public static function getLocationGroupSettings()
    {
        return ClientSettings::group('location_group_settings');
    }


    public static function getRouteTrackingSettings()
    {
        return ClientSettings::group('route_tracking_settings');
    }

    public static function isLocationRequired()
    {
        $settings = self::getLocationGroupSettings();

        return $settings ? $settings->location_required : true;
    }

    public static function isGroupRequired()
    {
        $settings = self::getLocationGroupSettings();

        return $settings ? $settings->group_required : true;
    }


    /** أيُشترَط على الموظف ملاحظة زيارة قبل فتح باقي إجراءات العميل؟ */
    public static function visitNoteRequired(): bool
    {
        return ClientSettings::flag('visit_note_requirement_settings', 'required', true);
    }

    /** أيُشترَط فاتورة/سند/إشعار دائن اليوم لفتح تبديل البضاعة؟ */
    public static function exchangeRequiresTransaction(): bool
    {
        return ClientSettings::flag('exchange_transaction_settings', 'require_transaction', true);
    }

    public static function isRouteTrackingEnabled()
    {
        $settings = self::getRouteTrackingSettings();

        return $settings ? $settings->enable_route_tracking : true;
    }

    public static function getAllSettings()
    {
        return [
            'location_group' => self::getLocationGroupSettings(),
            'route_tracking' => self::getRouteTrackingSettings(),
        ];
    }

    public static function getVisitProximitySettings()
    {
        return ClientSettings::group('visit_proximity_settings');
    }

    public static function getClientDefaultsSettings()
    {
        return ClientSettings::group('client_defaults_settings');
    }

    public static function getUploadLimitsSettings()
    {
        return ClientSettings::group('upload_limits_settings');
    }

    public static function getDebtWriteoffSettings()
    {
        return ClientSettings::group('debt_writeoff_settings');
    }

    public static function getAssignmentNotificationSettings()
    {
        return ClientSettings::group('assignment_notification_settings');
    }

    /**
     * هل هذا القسم/التبويب من «مساحة عمل الزيارة» ظاهر؟ الافتراض `true`
     * لكل الأقسام — إخفاءٌ متعمَّد فقط من إعدادات وحدات العملاء ← الزيارات.
     *
     * @param  string  $key  مثل show_notes_card، show_contact_card — راجع
     *                       ClientSettings::definitions()['visit_workspace_sections_settings']
     */
    /** أيُخفى العميل من قائمة المندوب وخريطته بقيّة اليوم بعد «إنهاء الزيارة»؟ */
    public static function hideFinishedVisitClients(): bool
    {
        return ClientSettings::flag('finished_visit_client_settings', 'hide_after_complete', true);
    }

    /** أيستطيع المندوب إرجاع عميلٍ أنهى زيارته لخطّ سيره في اليوم نفسه؟ */
    public static function allowReturnFinishedClientSameDay(): bool
    {
        return self::hideFinishedVisitClients()
            && ClientSettings::flag('finished_visit_client_settings', 'allow_return_same_day', true);
    }

    public static function visitWorkspaceSectionVisible(string $key): bool
    {
        return ClientSettings::flag('visit_workspace_sections_settings', $key, true);
    }

    /**
     * جودةُ ضغط الصور قبل الرفع، كنسبةٍ بين 0 و1 (ما يقبله `canvas.toBlob`).
     *
     * الضغط في المتصفّح لا في الخادم: الشبكة في الميدان ضعيفة، والمكسب أن
     * تُرفع الصورة مضغوطةً أصلاً لا أن تُضغط بعد وصولها.
     */
    public static function uploadImageQuality(): float
    {
        $percent = (int) ClientSettings::integer('upload_limits_settings', 'image_quality_percent', 60);

        // خارج المدى يُنتج `toBlob` صورةً سوداء أو يتجاهل المعامل بصمت
        return max(10, min(100, $percent)) / 100;
    }

    /** أطولُ ضلعٍ للصورة بعد التصغير، بالبكسل. */
    public static function uploadImageMaxDimension(): int
    {
        return max(320, (int) ClientSettings::integer('upload_limits_settings', 'image_max_dimension_px', 1280));
    }

    /**
     * أقلّ عدد صورٍ يجب إرفاقه بالملاحظة — ملاحظة الزيارة والمختصرة
     * والرئيسية على السواء (كلُّها تمرّ بـ`ClientNoteController::addnotes()`).
     *
     * `max(1, …)` حارسٌ لا تجميل: صفرٌ أو قيمةٌ سالبة تسرّبت إلى الإعداد
     * تُنتج `min:0` — قاعدةً لا تعني شيئاً بينما الحقل معروضٌ إلزاميّاً.
     * إطفاءُ الصور كلّها له مفتاحُه (`note_photo`) لا عددٌ صفريّ.
     */
    public static function noteMinPhotos(): int
    {
        return max(1, (int) ClientSettings::integer('visit_workspace_sections_settings', 'note_min_photos', 1));
    }


    /** نطاق القرب المسموح بين الموظف والعميل عند تسجيل زيارة/ملاحظة (كم). */
    public static function visitProximityRadiusKm(): float
    {
        $settings = self::getVisitProximitySettings();

        return $settings && $settings->visit_proximity_radius_km !== null
            ? (float) $settings->visit_proximity_radius_km
            : 0.3;
    }

    /**
     * نطاق القرب الفعلي لهذا الموظف تحديداً — نطاقُه المخصَّص
     * (`users.visit_proximity_radius_km` — الزيارات تُسنَد لمستخدمٍ لا
     * لسجلّ موظفٍ منفصل) إن وُجد، وإلا الإعداد العام. تجاوزُ موظفٍ بعينه
     * لا يغيّر الافتراض العام لبقيّة الموظفين.
     *
     * يُستعمَل هذا بدل `visitProximityRadiusKm()` وحدها في كل موضعٍ يملك
     * موظفاً بعينه (تسجيل زيارة، إضافة ملاحظة، بدء/إتمام زيارة من مساحة
     * العمل) — الدالّة الأخرى تبقى للقراءة العامة (شاشة الإعدادات نفسها،
     * وموضعٍ لا يُعرَف فيه الموظف بعد).
     */
    public static function visitProximityRadiusKmFor(?\App\Models\User $employeeUser): float
    {
        if ($employeeUser && $employeeUser->visit_proximity_radius_km !== null) {
            return (float) $employeeUser->visit_proximity_radius_km;
        }

        return self::visitProximityRadiusKm();
    }

    /**
     * الحالة الافتراضية للعميل الجديد.
     *
     * **لا رقمَ مكتوباً في الشيفرة.** كان المتحكّم يكتب `?? 3` احتياطاً،
     * والثلاثةُ معرّفُ صفٍّ في `statuses` قد يُحذف أو يعني حالةً أخرى في
     * منشأةٍ أخرى. فالاحتياط هنا **أوّلُ حالةٍ في ترتيب الشاشة** — قيمةٌ
     * مشتقّة من بيانات المنشأة نفسها لا ثابتٌ يُخمَّن.
     *
     * و`null` تعني «لا حالاتِ عملاءَ معرَّفة أصلاً»، فيُترك العمود فارغاً.
     */
    public static function defaultNewClientStatusId(): ?int
    {
        $configured = self::getClientDefaultsSettings()?->default_new_client_status_id;

        if ($configured) {
            return (int) $configured;
        }

        try {
            return \App\Models\Statuses::assignable()->ordered()->value('id');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * مرحلة التسجيل التي يُنشأ بها كل عميل جديد تلقائياً — من الإعدادات
     * وحدها، لا يختارها منشئ العميل يدوياً من نموذج الإضافة. `null` تعني
     * بلا مرحلة، وهي الافتراض ما لم يُضبَط مفتاحٌ صراحةً: لا نفترض
     * «تحت المراجعة» ولا «متابعة» بلا قرارٍ من الأدمن.
     *
     * @see docs/CRM_Customer_Status_Gap_Analysis_Plan.md القسم 3
     */
    public static function defaultRegistrationStageId(): ?int
    {
        // حقيبة `options` لا عمود — نفس `client_type_scope` وأخواتها، لا
        // خاصيّةً مباشرة على النموذج كحالة العميل الافتراضية (عمودٌ حقيقي)
        $configured = ClientSettings::option('client_defaults_settings', 'default_registration_stage_id');

        return $configured ? (int) $configured : null;
    }

    /**
     * الحد الأقصى الموحّد لحجم المرفقات (كيلوبايت). الافتراضي 100 ميجا —
     * أكبر قيمة كانت مستخدمة قبل التوحيد، حتى لا يرفض التوحيدُ رفعاتٍ
     * كانت مقبولة سابقاً في بعض الشاشات.
     */
    public static function maxUploadSizeKb(): int
    {
        $settings = self::getUploadLimitsSettings();

        return $settings && $settings->max_upload_size_kb !== null
            ? (int) $settings->max_upload_size_kb
            : 102400;
    }

    /** null يعني عدم اشتراط موافقة إضافية على أي مبلغ — السلوك الحالي الافتراضي. */
    public static function badDebtWriteoffApprovalThreshold(): ?float
    {
        $settings = self::getDebtWriteoffSettings();

        return $settings && $settings->bad_debt_writeoff_approval_threshold !== null
            ? (float) $settings->bad_debt_writeoff_approval_threshold
            : null;
    }

    public static function shouldNotifyEmployeeOnAssignmentChange(): bool
    {
        $settings = self::getAssignmentNotificationSettings();

        return $settings ? (bool) $settings->notify_employee_on_assignment_change : false;
    }

    /** هل حقل صورة العميل مفعّل من «الحقول الإضافية» في إعدادات العملاء؟ */
    public static function isImageFieldEnabled(): bool
    {
        return ClientSettings::fieldEnabled('image');
    }

    public static function getMapSettings()
    {
        return ClientSettings::group('map_settings');
    }

    public static function getItineraryAssignmentSettings()
    {
        return ClientSettings::group('itinerary_assignment_settings');
    }

    /** مدّة إخفاء العميل من الخريطة (ساعات) — كانت 24 مثبَّتة بالكود. */
    public static function mapHideDurationHours(): int
    {
        $settings = self::getMapSettings();

        return $settings && $settings->map_hide_duration_hours !== null
            ? (int) $settings->map_hide_duration_hours
            : 24;
    }

    /** تقييد إضافة عميل لخط سير موظف على العملاء المُسنَدين إليه فقط. */
    public static function shouldRestrictItineraryToAssignedClients(): bool
    {
        $settings = self::getItineraryAssignmentSettings();

        return $settings ? (bool) $settings->itinerary_restrict_to_assigned_clients : false;
    }

    /**
     * أتقتصر قوائم العملاء في شاشات الإنشاء (فواتير، سندات، إشعارات
     * دائنة، مرتجعات، تبديل بضاعة) على عملاء خط سير الموظف اليوم؟
     *
     * الافتراض `true`: هذا هو السلوك الذي كانت خمس شاشاتٍ تفرضه بلا
     * إعداد لكل موظفٍ له بيانات خط سير — تعطيلُه صريحٌ لا افتراضي.
     */
    public static function createScreensRestrictToItinerary(): bool
    {
        return ClientSettings::flag('create_screen_client_scope_settings', 'restrict_to_itinerary', true);
    }

    // ══════════════════ المواعيد ══════════════════

    /** مدّة الموعد الافتراضية بالدقائق — تُملأ في نموذج الموعد الجديد. */
    public static function appointmentDefaultDurationMinutes(): int
    {
        return (int) ClientSettings::integer('appointment_settings', 'default_duration_minutes', 30);
    }

    /** كم ساعةً قبل الموعد يُرسَل التذكير — `null` يعني لا تذكير. */
    public static function appointmentReminderHoursBefore(): ?int
    {
        // صفرٌ = لا تذكير: الفراغُ لا يُخزَّن فراغاً — `option()` يقرأ الغيابَ افتراضَ الـ24 ساعة
        $hours = ClientSettings::integer('appointment_settings', 'reminder_hours_before', 24);

        return $hours !== null && $hours > 0 ? $hours : null;
    }

    /** بداية ساعات العمل المسموح حجزُ المواعيد فيها (HH:MM). */
    public static function appointmentWorkingHoursStart(): string
    {
        return (string) ClientSettings::option('appointment_settings', 'working_hours_start', '08:00');
    }

    /** نهاية ساعات العمل المسموح حجزُ المواعيد فيها (HH:MM). */
    public static function appointmentWorkingHoursEnd(): string
    {
        return (string) ClientSettings::option('appointment_settings', 'working_hours_end', '17:00');
    }

    /** أتُرفَض المواعيد خارج ساعات العمل؟ — مطفأً تبقى ساعاتُ العمل إرشاداً. */
    public static function appointmentsEnforceWorkingHours(): bool
    {
        return ClientSettings::flag('appointment_settings', 'enforce_working_hours');
    }

    /** أيُسمح بموعدين متداخلين لنفس الموظف؟ */
    public static function appointmentsAllowOverlap(): bool
    {
        return ClientSettings::flag('appointment_settings', 'allow_overlap', true);
    }

    /** أيُسمح بحجز موعد بتاريخٍ مضى؟ */
    public static function appointmentsAllowPastDates(): bool
    {
        return ClientSettings::flag('appointment_settings', 'allow_past_dates');
    }

    /** أتُقفَل المواعيد الفائتة تلقائياً بمهمّة مجدولة؟ */
    public static function appointmentsAutoCompletePast(): bool
    {
        return ClientSettings::flag('appointment_settings', 'auto_complete_past');
    }

    // ══════════════════ لوحة تحكّم العملاء ══════════════════

    /** العميلُ «بحاجة زيارة» إن مضى على آخر زيارةٍ له هذا العددُ من الأيام. */
    public static function dashboardNeedsVisitDays(): int
    {
        return max(1, (int) ClientSettings::integer('client_dashboard_settings', 'needs_visit_days', 30));
    }

    /** العميلُ «متأخّر» إن تجاوز استحقاقُ جزءٍ من دينه هذا العددَ من الأيام. */
    public static function dashboardOverdueDays(): int
    {
        return max(0, (int) ClientSettings::integer('client_dashboard_settings', 'overdue_days', 60));
    }

    // ══════════════════ ترتيب خط السير ══════════════════

    /** أسلوب ترتيب عملاء خط السير: manual|nearest|last_visit. */
    public static function itineraryOrderStrategy(): string
    {
        return (string) ClientSettings::option('itinerary_order_settings', 'order_strategy', 'manual');
    }

    /** أقصى عدد عملاء في خط سير اليوم الواحد — `null` بلا حدّ. */
    public static function itineraryMaxClientsPerDay(): ?int
    {
        return ClientSettings::integer('itinerary_order_settings', 'max_clients_per_day');
    }

    /** أيُقفَل خطّ السير عن التعديل بعد بدء أوّل زيارة فيه؟ */
    public static function itineraryLockAfterStart(): bool
    {
        return ClientSettings::flag('itinerary_order_settings', 'lock_after_start');
    }

    /** أيُعيد الموظفُ ترتيبَ خطّ سيره بنفسه؟ */
    public static function itineraryAllowReorderByEmployee(): bool
    {
        return ClientSettings::flag('itinerary_order_settings', 'allow_reorder_by_employee', true);
    }

    // ══════════════════ موظفو العميل ══════════════════

    /** أيُسمح بأكثر من موظف مسؤول عن العميل الواحد؟ */
    public static function clientEmployeesAllowMultiple(): bool
    {
        return ClientSettings::flag('client_employee_settings', 'allow_multiple', true);
    }

    /** أقصى عدد موظفين للعميل الواحد — `null` بلا حدّ. */
    public static function clientEmployeesMax(): ?int
    {
        return ClientSettings::integer('client_employee_settings', 'max_employees_per_client');
    }

    /**
     * أقسام الهيكل التنظيمي التي يُسحب منها موظّفو العملاء.
     *
     * مصفوفةٌ فارغة = **كل الأقسام**. الفرق مقصود: منشأةٌ لم تفتح الشاشة
     * بعدُ يجب أن تبقى قوائمُ موظّفيها كما كانت، لا أن تفرغ فجأةً.
     *
     * @return array<int>
     */
    public static function clientEmployeeDepartmentIds(): array
    {
        $value = ClientSettings::option('client_employee_settings', 'assignable_departments', []);

        if (! is_array($value)) {
            // حقيبةُ الخيارات قد تعود من JSON بقيمةٍ مفردة أو نصّ
            $value = $value === null || $value === '' ? [] : [$value];
        }

        return array_values(array_filter(array_map('intval', $value)));
    }

    /**
     * قصرُ استعلام موظّفين على الأقسام المسموحة — نقطةُ التطبيق الوحيدة،
     * فلا يتكرّر شرطُ `whereIn` في كل شاشةٍ تعرض قائمة الموظفين.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  استعلامٌ على `employees`
     */
    public static function scopeEmployeesToClientDepartments($query)
    {
        $departmentIds = self::clientEmployeeDepartmentIds();

        return $departmentIds ? $query->whereIn('department_id', $departmentIds) : $query;
    }

    // ══════════════════ تحويل واستلام الفواتير ══════════════════

    /** أيحتاج التحويلُ قبولَ الموظف المستلِم؟ — مطفأً يُنفَّذ التحويل فوراً. */
    public static function invoiceTransferRequiresApproval(): bool
    {
        return ClientSettings::flag('invoice_transfer_settings', 'require_approval', true);
    }

    /** أيُشترَط سببٌ مكتوب عند التحويل والرفض؟ */
    public static function invoiceTransferReasonRequired(): bool
    {
        return ClientSettings::flag('invoice_transfer_settings', 'reason_required', true);
    }

    /** أيُشعَر الطرفان عند التحويل؟ */
    public static function invoiceTransferNotify(): bool
    {
        return ClientSettings::flag('invoice_transfer_settings', 'notify_on_transfer', true);
    }

    /** أيُسمح بتحويل فاتورةٍ سُدّدت كلياً؟ */
    public static function invoiceTransferAllowPaid(): bool
    {
        return ClientSettings::flag('invoice_transfer_settings', 'allow_transfer_paid');
    }

    /** أقصى عدد تحويلات معلّقة للموظف الواحد — `null` بلا حدّ. */
    public static function invoiceTransferMaxOpenPerEmployee(): ?int
    {
        return ClientSettings::integer('invoice_transfer_settings', 'max_open_per_employee');
    }

    // ══════════════════ افتراضات العميل ══════════════════

    /** نطاق نوع العميل المسموح: Both|individual|commercial. */
    public static function clientTypeScope(): string
    {
        return (string) ClientSettings::option('client_defaults_settings', 'client_type_scope', 'Both');
    }

    /** أيُشترَط رقم الهاتف عند إضافة عميل؟ */
    public static function clientRequirePhone(): bool
    {
        return ClientSettings::flag('client_defaults_settings', 'require_phone', true);
    }

    /** أيُشترَط رقم الجوّال عند إضافة عميل؟ */
    public static function clientRequireMobile(): bool
    {
        return ClientSettings::flag('client_defaults_settings', 'require_mobile');
    }

    /** أيُمنَع تكرار رقم الهاتف بين العملاء؟ */
    public static function clientUniquePhone(): bool
    {
        return ClientSettings::flag('client_defaults_settings', 'unique_phone');
    }

    /**
     * أيُشترَط الرقم الضريبي للعميل؟
     *
     * `tax_number` لا `national_id`: جدول `clients` لا يحمل عموداً بالاسم
     * الثاني، فقاعدةُ `required` عليه كانت سترفض كلَّ حفظٍ بحقلٍ لا يُرسَل.
     */
    public static function clientRequireTaxNumber(): bool
    {
        return ClientSettings::flag('client_defaults_settings', 'require_tax_number');
    }

    /**
     * هدف التحصيل الشهري — عمودٌ معرَّفٌ في `general_client_settings` منذ
     * البداية ولا يقرؤه سطرٌ واحد؛ كانت شاشةُ العملاء تكتب `648` نصّاً.
     */
    public static function monthlyCollectionTarget(): float
    {
        $value = self::getClientDefaultsSettings()?->monthly_collection_target;

        return $value !== null ? (float) $value : 0.0;
    }

    /** أيُشترَط البريد الإلكتروني؟ */
    public static function clientRequireEmail(): bool
    {
        return ClientSettings::flag('client_defaults_settings', 'require_email');
    }

    /** أيُشترَط الاسم التجاري عند إضافة عميل؟ */
    public static function clientRequireTradeName(): bool
    {
        return ClientSettings::flag('client_required_fields_settings', 'require_trade_name', true);
    }

    /** أيُشترَط السجل التجاري عند إضافة عميل؟ */
    public static function clientRequireCommercialRegistration(): bool
    {
        return ClientSettings::flag('client_required_fields_settings', 'require_commercial_registration', true);
    }

    /** أيُشترَط تصنيف العميل عند إضافته؟ */
    public static function clientRequireCategory(): bool
    {
        return ClientSettings::flag('client_required_fields_settings', 'require_category', true);
    }

    /**
     * أيُشترَط اختيار «وضع العميل» (مرحلة التسجيل) عند إضافته؟
     *
     * مطفأً — وهو الافتراضي — يبقى الحقل اختيارياً في نموذج الإضافة،
     * ويأخذ العميلُ الوضعَ الافتراضي المضبوط في
     * `defaultRegistrationStageId()` حين يُترَك فارغاً.
     */
    public static function clientRequireRegistrationStage(): bool
    {
        return ClientSettings::flag('client_required_fields_settings', 'require_registration_stage');
    }

    /**
     * أيحتاج تغييرُ حالة العميل من موظفٍ لا يملك صلاحية `clients_approve_status`
     * موافقةَ مديرٍ أولاً؟ مطفأً يُغيَّر مباشرة لأيّ موظفٍ يملك صلاحية
     * `clients_change_status` العادية، بصرف النظر عن صلاحية الاعتماد —
     * راجع ClientController::updateStatusClient().
     */
    public static function clientStatusChangeRequiresApproval(): bool
    {
        return ClientSettings::flag('client_status_change_settings', 'require_manager_approval', true);
    }

    /** أمُفعَّلٌ حقلُ العميل الإضافي (`image`، `birth_date`، …)؟ */
    public static function isFieldEnabled(string $key, bool $default = true): bool
    {
        return ClientSettings::fieldEnabled($key, $default);
    }


    // ══════════════════ بوابة العميل ══════════════════

    /**
     * مُعرّف الدور الذي يُعدّ «دور العميل» — يُختار من شاشة «صلاحيات
     * العميل»، ولا اسمَ دورٍ مثبَّتٌ في الكود.
     *
     * `null` = لم يُختَر بعد؛ فالبوابة لا تحصر أحداً، ولا تحصر موظّفاً بالخطأ.
     */
    public static function clientPortalRoleId(): ?int
    {
        return ClientSettings::integer('client_portal_settings', 'portal_role_id');
    }

    /**
     * أهذا المستخدم حسابُ عميلٍ يُحصَر في البوابة؟
     *
     * شرطان معاً: دورُه هو الدور المختار، **و** حسابُه مربوطٌ بسجلّ عميل
     * (`client_id`). الدور وحده لا يكفي — لولا `client_id` لما عرفت شاشاتُ
     * البوابة أيَّ عميلٍ تعرض، فتقع على `null` وتُظهر بيانات فارغة أو تنهار.
     */
    public static function isClientPortalUser(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        $roleId = self::clientPortalRoleId();

        return $roleId !== null
            && (int) $user->role_id === $roleId
            && $user->client_id !== null;
    }
}
