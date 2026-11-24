<?php

namespace Modules\Client\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientModuleSetting;
use App\Models\ClientType;
use App\Models\GeneralClientSetting;
use App\Models\Statuses;
use App\Support\ClientSettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * شاشة إعدادات موديول العملاء الموحّدة.
 *
 * **ما دُمج هنا**
 *
 * كانت إعدادات العملاء شاشتين: «عام» (`clients.general`) تحفظ تفعيل
 * الحقول الإضافية ونوع العميل، و«إعدادات وحدات العملاء» تحفظ تفعيل
 * الأقسام وعشرة مفاتيح سلوك. والفصل بينهما لم يكن على معنى: من يبحث عن
 * «هل الموقع مطلوب؟» لا يعرف في أيّهما يجده. وشاشةُ «عام» كانت — فوق
 * ذلك — تُرسل `type` في نموذجها و`store()` لا تتحقّق منه ولا تحفظه، فنوعُ
 * العميل إعدادٌ يُعرَض ويُختار ولا يُحفظ منذ أوّل يوم.
 *
 * فصارت واحدة: ستّة تبويبات — العملاء، الزيارات، خطوط السير، المتابعة،
 * الموظفون، الفواتير والديون — وحُذفت «عام».
 *
 * **مصدر التعريف واحد**
 *
 * كل مجموعة إعدادات معرَّفة في `App\Support\ClientSettings::definitions()`
 * وحدها؛ وخريطة `FIELDS` هنا تربط اسم الحقل في النموذج بموضعه في تلك
 * المجموعة وبقاعدة تحقّقه. فإضافةُ إعدادٍ سطران لا أربعة مواضع.
 *
 * **السجلّات**: كل حفظٍ يمرّ بـ`OperationLog` — سجلّ النشاط بفرق القيم،
 * وسجلّ الأحداث، وسجلّ الأخطاء عند الفشل.
 *
 * @see \App\Support\ClientSettings تعريف المجموعات وافتراضاتها
 * @see \App\Helpers\ClientSettingsHelper واجهة القراءة للمستهلكين
 */
class ClientModuleSettingsController extends Controller
{
    /** مفتاح الوحدة في السجلّات. */
    private const LOG_MODULE = 'ClientModuleSettings';

    /** الأقسام الفرعية القابلة للتفعيل — أعمدة `client_module_settings`. */
    private const FEATURES = [
        'visits_enabled',
        // «خطوط السير» و«موقع العميل» و«CRM» و«تحويل الفواتير» و«أهداف العملاء»
        // أُزيلت (2026-09-15): كلٌّ منها محروسٌ بصلاحياته، و«موقع العميل» كان يحرس
        // مساراً لا تستدعيه شاشة.
        'notes_enabled',
        'doubtful_debts_enabled',
        'exempt_location_enabled',
        // 'exempt_exchange_enabled' أُزيلت: كانت تُبيح استثناء عميلٍ من شرط
        // «فاتورة/سند/إشعار دائن اليوم» قبل السماح بتبديل بضاعته — إعفاءٌ
        // من ضابطٍ لا صلاحيةَ عمل، وطُلب إلغاؤه كلّياً لا إخفاؤه فقط. راجع
        // ClientController (حساب canExchange/hasTodayTransaction) وزرّ
        // «استبعاد الفواتير» المحذوف من actions-buttons.blade.php.
    ];

    /**
     * خريطة النموذج: اسم الحقل ⇦ [المجموعة، الوجهة، الاسم في الجدول، النوع، قاعدة التحقّق].
     *
     * *الوجهة*: `col` عمودٌ في `general_client_settings`، `opt` مفتاحٌ في
     * حقيبة `options`. والفصل تاريخي لا منهجي: الأعمدة قديمةٌ يقرؤها أربعةَ
     * عشرَ موضعاً بأسمائها، والجديد كلّه في الحقيبة.
     *
     * *النوع* يحكم التحويل قبل الحفظ: `bool` غيابُه إطفاء، و`int?`/`float?`
     * تُبقي `null` على حالها — «بلا حدّ» ليست صفراً.
     *
     * @var array<string, array{0:string,1:string,2:string,3:string,4:mixed}>
     */
    private const FIELDS = [
        // ─────────── العملاء ───────────
        'default_new_client_status_id' => ['client_defaults_settings', 'col', 'default_new_client_status_id', 'int?', null],
        // `null` هنا أيضاً: قاعدتها الحقيقية (`section = 'registration_stage'`
        // لا `IS NULL`) تُبنى وقت التشغيل في validationRules()، فمصفوفة
        // `const` لا تقبل كائن Rule ولا Closure
        'default_registration_stage_id' => ['client_defaults_settings', 'opt', 'default_registration_stage_id', 'int?', null],
        'client_type_scope' => ['client_defaults_settings', 'opt', 'client_type_scope', 'string', 'nullable|in:Both,individual,commercial'],
        'client_require_phone' => ['client_defaults_settings', 'opt', 'require_phone', 'bool', 'nullable|boolean'],
        'client_require_mobile' => ['client_defaults_settings', 'opt', 'require_mobile', 'bool', 'nullable|boolean'],
        'client_unique_phone' => ['client_defaults_settings', 'opt', 'unique_phone', 'bool', 'nullable|boolean'],
        'client_require_tax_number' => ['client_defaults_settings', 'opt', 'require_tax_number', 'bool', 'nullable|boolean'],
        'client_require_email' => ['client_defaults_settings', 'opt', 'require_email', 'bool', 'nullable|boolean'],

        'client_require_trade_name' => ['client_required_fields_settings', 'opt', 'require_trade_name', 'bool', 'nullable|boolean'],
        'client_require_commercial_registration' => ['client_required_fields_settings', 'opt', 'require_commercial_registration', 'bool', 'nullable|boolean'],
        'client_require_category' => ['client_required_fields_settings', 'opt', 'require_category', 'bool', 'nullable|boolean'],
        'client_require_registration_stage' => ['client_required_fields_settings', 'opt', 'require_registration_stage', 'bool', 'nullable|boolean'],

        'client_status_change_requires_approval' => ['client_status_change_settings', 'opt', 'require_manager_approval', 'bool', 'nullable|boolean'],

        'location_required' => ['location_group_settings', 'col', 'location_required', 'bool', 'nullable|boolean'],
        'group_required' => ['location_group_settings', 'col', 'group_required', 'bool', 'nullable|boolean'],

        'max_upload_size_kb' => ['upload_limits_settings', 'col', 'max_upload_size_kb', 'int', 'nullable|integer|min:64|max:512000'],
        'image_quality_percent' => ['upload_limits_settings', 'opt', 'image_quality_percent', 'int', 'nullable|integer|min:10|max:100'],
        'image_max_dimension_px' => ['upload_limits_settings', 'opt', 'image_max_dimension_px', 'int', 'nullable|integer|min:320|max:4096'],

        'map_hide_duration_hours' => ['map_settings', 'col', 'map_hide_duration_hours', 'int', 'nullable|integer|min:1|max:720'],

        // ─────────── الزيارات ───────────

        'visit_note_required' => ['visit_note_requirement_settings', 'opt', 'required', 'bool', 'nullable|boolean'],

        'visit_proximity_radius_km' => ['visit_proximity_settings', 'col', 'visit_proximity_radius_km', 'float', 'nullable|numeric|min:0.01|max:50'],

        'finished_visit_hide_clients' => ['finished_visit_client_settings', 'opt', 'hide_after_complete', 'bool', 'nullable|boolean'],
        'finished_visit_allow_return' => ['finished_visit_client_settings', 'opt', 'allow_return_same_day', 'bool', 'nullable|boolean'],

        'show_location_card' => ['visit_workspace_sections_settings', 'opt', 'show_location_card', 'bool', 'nullable|boolean'],
        'show_quick_actions_card' => ['visit_workspace_sections_settings', 'opt', 'show_quick_actions_card', 'bool', 'nullable|boolean'],
        'show_notes_card' => ['visit_workspace_sections_settings', 'opt', 'show_notes_card', 'bool', 'nullable|boolean'],
        'show_contact_card' => ['visit_workspace_sections_settings', 'opt', 'show_contact_card', 'bool', 'nullable|boolean'],
        'show_details_card' => ['visit_workspace_sections_settings', 'opt', 'show_details_card', 'bool', 'nullable|boolean'],
        'show_notes_tab' => ['visit_workspace_sections_settings', 'opt', 'show_notes_tab', 'bool', 'nullable|boolean'],
        'show_competitors_tab' => ['visit_workspace_sections_settings', 'opt', 'show_competitors_tab', 'bool', 'nullable|boolean'],
        'show_competitor_capture' => ['visit_workspace_sections_settings', 'opt', 'show_competitor_capture', 'bool', 'nullable|boolean'],
        'note_deposit_count' => ['visit_workspace_sections_settings', 'opt', 'note_deposit_count', 'bool', 'nullable|boolean'],
        'note_photo' => ['visit_workspace_sections_settings', 'opt', 'note_photo', 'bool', 'nullable|boolean'],
        'note_min_photos' => ['visit_workspace_sections_settings', 'opt', 'note_min_photos', 'int', 'nullable|integer|min:1|max:10'],
        'show_docs_tab' => ['visit_workspace_sections_settings', 'opt', 'show_docs_tab', 'bool', 'nullable|boolean'],
        'show_attachments_tab' => ['visit_workspace_sections_settings', 'opt', 'show_attachments_tab', 'bool', 'nullable|boolean'],
        'show_signature_section' => ['visit_workspace_sections_settings', 'opt', 'show_signature_section', 'bool', 'nullable|boolean'],

        // ─────────── خطوط السير ───────────
        'enable_route_tracking' => ['route_tracking_settings', 'col', 'enable_route_tracking', 'bool', 'nullable|boolean'],

        'itinerary_restrict_to_assigned_clients' => ['itinerary_assignment_settings', 'col', 'itinerary_restrict_to_assigned_clients', 'bool', 'nullable|boolean'],
        'create_screens_restrict_to_itinerary' => ['create_screen_client_scope_settings', 'opt', 'restrict_to_itinerary', 'bool', 'nullable|boolean'],

        'itinerary_order_strategy' => ['itinerary_order_settings', 'opt', 'order_strategy', 'string', 'nullable|in:manual,nearest,priority,last_visit'],
        'itinerary_max_clients_per_day' => ['itinerary_order_settings', 'opt', 'max_clients_per_day', 'int?', 'nullable|integer|min:1|max:500'],
        'itinerary_lock_after_start' => ['itinerary_order_settings', 'opt', 'lock_after_start', 'bool', 'nullable|boolean'],
        'itinerary_allow_reorder_by_employee' => ['itinerary_order_settings', 'opt', 'allow_reorder_by_employee', 'bool', 'nullable|boolean'],

        // ─────────── المتابعة ───────────

        'dashboard_needs_visit_days' => ['client_dashboard_settings', 'opt', 'needs_visit_days', 'int', 'nullable|integer|min:1|max:365'],
        'dashboard_overdue_days' => ['client_dashboard_settings', 'opt', 'overdue_days', 'int', 'nullable|integer|min:0|max:365'],
        // شرائحُ مخطَّطات اللوحة — صفوفٌ يحرّرها المستخدم؛ قواعدُ عناصرها في validationRules()
        'dashboard_lifecycle_segments' => ['client_dashboard_settings', 'opt', 'lifecycle_segments', 'segments:lifecycle', 'nullable|array|min:1|max:12'],
        'dashboard_aging_segments' => ['client_dashboard_settings', 'opt', 'aging_segments', 'segments:aging', 'nullable|array|min:1|max:12'],

        // ─────────── الموظفون ───────────
        'notify_employee_on_assignment_change' => ['assignment_notification_settings', 'col', 'notify_employee_on_assignment_change', 'bool', 'nullable|boolean'],

        'client_employees_allow_multiple' => ['client_employee_settings', 'opt', 'allow_multiple', 'bool', 'nullable|boolean'],
        'client_employees_max' => ['client_employee_settings', 'opt', 'max_employees_per_client', 'int?', 'nullable|integer|min:1|max:100'],
        // أقسام الهيكل التنظيمي التي يُسحب منها موظّفو العملاء — الفراغ = الكل
        'client_employees_departments' => ['client_employee_settings', 'opt', 'assignable_departments', 'int[]', 'nullable|array'],

        // ─────────── الفواتير والديون ───────────
        'invoice_transfer_require_approval' => ['invoice_transfer_settings', 'opt', 'require_approval', 'bool', 'nullable|boolean'],
        'invoice_transfer_reason_required' => ['invoice_transfer_settings', 'opt', 'reason_required', 'bool', 'nullable|boolean'],
        'invoice_transfer_notify' => ['invoice_transfer_settings', 'opt', 'notify_on_transfer', 'bool', 'nullable|boolean'],
        'invoice_transfer_allow_paid' => ['invoice_transfer_settings', 'opt', 'allow_transfer_paid', 'bool', 'nullable|boolean'],
        'invoice_transfer_max_open' => ['invoice_transfer_settings', 'opt', 'max_open_per_employee', 'int?', 'nullable|integer|min:1|max:1000'],

        'bad_debt_writeoff_approval_threshold' => ['debt_writeoff_settings', 'col', 'bad_debt_writeoff_approval_threshold', 'float?', 'nullable|numeric|min:0'],

        'exchange_require_transaction' => ['exchange_transaction_settings', 'opt', 'require_transaction', 'bool', 'nullable|boolean'],
    ];

    /** التبويبات بترتيب ظهورها. */
    public const TABS = ['clients', 'visits', 'itinerary', 'follow_up', 'employees', 'invoices'];

    public function __construct()
    {
        $this->middleware('permission:clients_module_settings_view', ['only' => ['index']]);
        $this->middleware('permission:clients_module_settings_update', [
            'only' => ['update', 'storeEmployeeProximityOverride', 'destroyEmployeeProximityOverride'],
        ]);
    }

    public function index()
    {
        // القيم الحالية بأسماء حقول النموذج — القالب يقرأ خريطةً واحدة
        // بدل عشرة متغيّرات، فإضافةُ إعدادٍ لا تعني متغيّراً جديداً يُمرَّر.
        $values = [];

        foreach (self::FIELDS as $input => [$group, $target, $field, $type, $rule]) {
            $values[$input] = $target === 'col'
                ? ClientSettings::column($group, $field)
                : ClientSettings::option($group, $field);
        }

        return view('client::setting.module-settings', [
            'moduleSettings' => ClientModuleSetting::current(),
            'values' => $values,
            'clientFields' => ClientSettings::clientFields(),
            'statuses' => Statuses::assignable()->ordered()->get(['id', 'name']),
            'registrationStages' => Statuses::section('registration_stage')->ordered()->get(['id', 'name']),
            // أقسام الهيكل التنظيمي — مصدرُ اختيار «من أيّ قسمٍ يُعيَّن موظّفو العملاء»
            'departments' => \App\Models\Department::orderBy('name')->get(['id', 'name']),
            'employeesWithProximityOverride' => \App\Models\User::whereNotNull('visit_proximity_radius_km')
                ->orderBy('name')
                ->get(['id', 'name', 'visit_proximity_radius_km']),
            'employeesWithoutProximityOverride' => \App\Models\User::whereNotNull('employee_id')
                ->whereNull('visit_proximity_radius_km')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * إضافة/تحديث نطاق قربٍ مخصَّص لموظفٍ بعينه — أضعف أولويةً من نطاقٍ
     * مخصَّص على العميل نفسه، وأقوى من الإعداد العام.
     *
     * @see \App\Helpers\ClientSettingsHelper::visitProximityRadiusKmFor()
     */
    public function storeEmployeeProximityOverride(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'radius_meters' => 'required|numeric|min:10|max:50000',
        ], [
            'user_id.required' => 'يرجى اختيار الموظف.',
            'radius_meters.required' => 'يرجى إدخال النطاق بالمتر.',
        ]);

        $user = \App\Models\User::findOrFail($validated['user_id']);
        $before = $user->visit_proximity_radius_km;
        $user->visit_proximity_radius_km = round($validated['radius_meters'] / 1000, 3);
        $user->save();

        OperationLog::record(
            'employee_proximity_override_set',
            self::LOG_MODULE,
            $user,
            'ضبط نطاق قرب مخصّص للموظف «'.$user->name.'»: '.$validated['radius_meters'].' متر',
            ['old' => ['visit_proximity_radius_km' => $before], 'new' => ['visit_proximity_radius_km' => $user->visit_proximity_radius_km]]
        );

        return redirect()->route('clients.module_settings', ['tab' => 'visits'])
            ->with('success', 'تم ضبط النطاق المخصَّص للموظف بنجاح.');
    }

    /** إلغاء نطاق القرب المخصَّص لموظف — يعود لاستعمال الإعداد العام (أو نطاق العميل إن وُجد). */
    public function destroyEmployeeProximityOverride(Request $request, $userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        $before = $user->visit_proximity_radius_km;
        $user->visit_proximity_radius_km = null;
        $user->save();

        OperationLog::record(
            'employee_proximity_override_removed',
            self::LOG_MODULE,
            $user,
            'إلغاء نطاق القرب المخصّص للموظف «'.$user->name.'»',
            ['old' => ['visit_proximity_radius_km' => $before], 'new' => ['visit_proximity_radius_km' => null]]
        );

        return redirect()->route('clients.module_settings', ['tab' => 'visits'])
            ->with('success', 'تم إلغاء النطاق المخصَّص.');
    }

    public function update(Request $request)
    {
        $data = $request->validate($this->validationRules(), [
            // الرسالة الافتراضية تذكر مفتاح الحقل ورقم عنصره
            // (`client_employees_departments.0`) — لا معنى له عند القارئ
            'client_employees_departments.*.exists' => 'أحد الأقسام المختارة لم يعد موجوداً في الهيكل التنظيمي.',
            'client_employees_departments.*.integer' => 'اختيار الأقسام غير صالح.',
            '*_segments.*.label.required' => 'اسم كل شريحة في لوحة العملاء مطلوب.',
            '*_segments.*.label.max' => 'اسم الشريحة لا يتجاوز 40 حرفاً.',
            '*_segments.*.color.*' => 'لون الشريحة غير صالح.',
            '*_segments.min' => 'يجب أن تبقى شريحة واحدة على الأقل.',
            '*_segments.max' => 'الحد الأقصى 12 شريحة.',
            '*_segments.*.*.integer' => 'أيام الشرائح وعدد الفواتير أرقام صحيحة.',
            '*_segments.*.*.max' => 'قيمة في جدول الشرائح أكبر من المسموح.',
            '*_segments.*.*.min' => 'قيم جدول الشرائح لا تكون سالبة.',
        ]);

        $moduleChanges = ['old' => [], 'new' => []];
        $settingChanges = ['old' => [], 'new' => []];
        $backfilledCount = 0;

        try {
            DB::transaction(function () use ($request, $data, &$moduleChanges, &$settingChanges, &$backfilledCount) {
                $moduleChanges = $this->saveFeatureToggles($data);
                $settingChanges = $this->saveSettingGroups($data);
                $this->saveClientFields($request);

                // تعبئة مرحلة التسجيل الافتراضية على العملاء الحاليين — بطلبٍ
                // صريح من المستخدم فقط، ولا تمسّ من له مرحلةٌ مضبوطة أصلاً
                if ($request->boolean('apply_default_stage_to_existing') && $request->filled('default_registration_stage_id')) {
                    // وبحالةٍ بعينها إن اختارها (نشط / موقوف) — وإلّا كلُّ من لا وضع له
                    $backfilledCount = Client::whereNull('registration_stage_id')
                        ->when($request->filled('apply_default_stage_status_id'),
                            fn ($q) => $q->where('status_id', $request->integer('apply_default_stage_status_id')))
                        ->update(['registration_stage_id' => $request->input('default_registration_stage_id')]);
                }
            });
        } catch (Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'update');

            return back()->withInput()->with('error', __('client_settings.ui.save_failed'));
        }

        ClientSettings::forget();

        OperationLog::record('updated', self::LOG_MODULE, null, __('client_settings.module_settings.log_updated'), [
            'old' => array_merge($moduleChanges['old'], $settingChanges['old']),
            'new' => array_merge($moduleChanges['new'], $settingChanges['new']),
        ]);

        if ($backfilledCount > 0) {
            OperationLog::record(
                'bulk_registration_stage_updated',
                self::LOG_MODULE,
                null,
                'تعبئة وضع العميل الافتراضي لـ'.$backfilledCount.' عميل لم يكن لهم وضع'
            );
        }

        $successMessage = $backfilledCount > 0
            ? __('client_settings.ui.saved').' وطُبِّق الوضع على '.$backfilledCount.' عميل لم يكن لهم وضع.'
            : __('client_settings.ui.saved');

        return redirect()
            ->route('clients.module_settings', ['tab' => $request->input('active_tab')])
            ->with('success', $successMessage);
    }

    /**
     * قواعد التحقّق مشتقّةً من الخريطة — لا تُكتب مرّتين.
     *
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        $rules = [
            'active_tab' => ['nullable', Rule::in(self::TABS)],
            'settings' => 'array',
            'settings.*' => 'integer|exists:general_client_settings,id',
            /* عناصرُ الحقول المصفوفية: قاعدةُ العنصر لا تُشتقّ من الخريطة —
               مفتاحُها `.*` ليس إعداداً يُقرأ ولا يُحفظ، فلا موضع له فيها.

               و`nullable` ليست تساهلاً: الحقل المخفيّ الذي يسبق القائمة في
               النموذج يُرسل عنصراً فارغاً دائماً — وهو ما يتيح إفراغ
               الاختيار أصلاً — ويحوّله `ConvertEmptyStringsToNull` إلى
               `null` قبل التحقّق، فترفضه `integer` وحدها بالرسالة
               «client_employees_departments.0 يجب أن يكون عدداً صحيحاً».
               `castValue('int[]')` تُسقط الفارغَ بعد ذلك. */
            'client_employees_departments.*' => 'nullable|integer|exists:departments,id',
        ];

        // عناصرُ صفوف الشرائح — الاسمُ واللونُ إلزاميّان، والأيامُ الفارغة «بلا حدّ»
        foreach (['dashboard_lifecycle_segments', 'dashboard_aging_segments'] as $segments) {
            $rules[$segments.'.*.label'] = 'required|string|max:40';
            $rules[$segments.'.*.color'] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
            $rules[$segments.'.*.from'] = 'nullable|integer|min:0|max:3650';
            $rules[$segments.'.*.to'] = 'nullable|integer|min:0|max:3650';
            $rules[$segments.'.*.min_invoices'] = 'nullable|integer|min:0|max:1000';
            $rules[$segments.'.*.min_gap'] = 'nullable|integer|min:0|max:3650';
            $rules[$segments.'.*.value_rule'] = 'nullable|in:any,above_median,min_amount';
            $rules[$segments.'.*.min_amount'] = 'nullable|numeric|min:0';
        }

        foreach (self::FEATURES as $feature) {
            $rules[$feature] = 'nullable|boolean';
        }

        foreach (self::FIELDS as $input => [$group, $target, $field, $type, $rule]) {
            // حالةُ العميل الافتراضية ومرحلة التسجيل الافتراضية تحتاجان
            // وجوداً في `statuses` بشرطٍ مختلفٍ على القسم لكلٍّ منهما —
            // `Rule` لا تُكتب نصّاً في مصفوفة `const`
            $rules[$input] = $rule ?? match ($input) {
                'default_registration_stage_id' => ['nullable', 'integer', Rule::exists('statuses', 'id')->where(fn ($q) => $q->where('section', 'registration_stage'))],
                default => ['nullable', Rule::exists('statuses', 'id')->where(fn ($q) => $q->whereNull('section'))],
            };
        }

        // تعبئةُ الوضع للعملاء بلا وضع — مقصورةً على حالةٍ معتمدة (نشط / موقوف)
        $rules['apply_default_stage_status_id'] = ['nullable', 'integer', Rule::exists('statuses', 'id')->whereNull('section')->whereNotNull('kpi_role')];

        return $rules;
    }

    /**
     * تفعيل الأقسام الفرعية.
     *
     * **الغائب لا يُمسّ.** المربّع غير المؤشَّر يصل مع ذلك — لكلّ سويتش
     * حقلٌ مخفيّ بقيمة `0` يسبقه في `components.switch` — فغيابُ الاسم
     * بالكلّية يعني أن هذا الحفظ لم يحمل الحقل أصلاً لا أنّ المستخدم أطفأه.
     * والفرق ليس نظرياً: نداءٌ لا يحمل الأقسام (اختبارٌ، أو تكاملٌ، أو
     * نموذجٌ اختُصر لاحقاً) كان يطفئ الوحدة كلَّها بلا أن يطلب أحد ذلك.
     *
     * @return array{old: array, new: array}
     */
    private function saveFeatureToggles(array $data): array
    {
        $submitted = array_values(array_filter(
            self::FEATURES,
            fn (string $feature) => array_key_exists($feature, $data)
        ));

        if (! $submitted) {
            return ['old' => [], 'new' => []];
        }

        $settings = ClientModuleSetting::current();

        foreach ($submitted as $feature) {
            $settings->{$feature} = (bool) $data[$feature];
        }

        $settings->updated_by = Auth::id();

        $changes = OperationLog::diff($settings, $submitted);
        $settings->save();

        return ['old' => $changes['old'] ?? [], 'new' => $changes['new'] ?? []];
    }

    /**
     * مجموعات الإعدادات كلّها — تُجمَّع بالمجموعة ثم تُحفظ صفّاً واحداً لكلٍّ.
     *
     * **الغائب لا يُمسّ**، لنفس سبب `saveFeatureToggles()`: الحقل الرقمي
     * الفارغ يصل سلسلةً فارغة فيُخزَّن `null` قصداً («بلا حدّ»)، أمّا الحقل
     * الذي لم يُرسَل أصلاً فيُترك على قيمته. ولولا هذا الفرق لكان كلُّ حفظٍ
     * جزئيّ يمسح ما لم يُذكَر فيه.
     *
     * @return array{old: array, new: array}
     */
    private function saveSettingGroups(array $data): array
    {
        $columnsByGroup = [];
        $optionsByGroup = [];

        foreach (self::FIELDS as $input => [$group, $target, $field, $type, $rule]) {
            if (! array_key_exists($input, $data)) {
                continue;
            }

            $value = $this->castValue($data[$input], $type);

            if ($target === 'col') {
                $columnsByGroup[$group][$field] = $value;
            } else {
                $optionsByGroup[$group][$field] = $value;
            }
        }

        $old = [];
        $new = [];

        foreach (array_keys(ClientSettings::definitions()) as $group) {
            $columns = $columnsByGroup[$group] ?? [];
            $options = $optionsByGroup[$group] ?? [];

            if (! $columns && ! $options) {
                continue;
            }

            $row = ClientSettings::group($group);

            $before = $this->snapshot($row, array_keys($columns), array_keys($options));

            if ($columns) {
                $row->fill($columns);
            }

            if ($options) {
                $row->mergeOptions($options);
            }

            $row->save();

            $after = $this->snapshot($row, array_keys($columns), array_keys($options));

            // الحقول التي لم تتغيّر لا تدخل السجلّ — وإلّا امتلأ بما لم يمسّه أحد
            foreach ($after as $field => $value) {
                if (($before[$field] ?? null) !== $value) {
                    $old[$group.'.'.$field] = $before[$field] ?? null;
                    $new[$group.'.'.$field] = $value;
                }
            }
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * تفعيل حقول العميل الإضافية — ما كانت تحفظه شاشة «عام».
     *
     * الغياب الكامل للمفتاح يعني أن التبويب لم يُعرض في هذا الحفظ، فلا
     * تُطفَأ الحقول كلّها: التبويبات تُحفظ بنموذجٍ واحد لكنّ حقول التبويب
     * غير المفتوح تصل، أمّا نداءٌ آخر (اختبار أو تكامل) قد لا يرسلها أصلاً.
     */
    private function saveClientFields(Request $request): void
    {
        // `type_client` صفٌّ واحد كانت شاشة العميل تقرؤه — يُبقى متوافقاً مع
        // `client_type_scope` بدل أن يبقى مصدراً ثانياً يتناقض معه. وهو خارج
        // حارس `client_fields_submitted` لأنّه إعدادٌ في مجموعةٍ أخرى
        $scope = $request->input('client_type_scope');

        if ($scope) {
            $type = ClientType::query()->first();

            $type ? $type->update(['type' => $scope]) : ClientType::create(['type' => $scope]);
        }

        if (! $request->has('client_fields_submitted')) {
            return;
        }

        $active = array_map('intval', $request->input('settings', []));

        foreach (ClientSettings::clientFields() as $field) {
            $shouldBeActive = in_array((int) $field->id, $active, true);

            if ((bool) $field->is_active !== $shouldBeActive) {
                $field->update(['is_active' => $shouldBeActive]);
            }
        }
    }

    /**
     * لقطةُ حقولٍ من صفّ — للمقارنة قبل الحفظ وبعده.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $options
     * @return array<string, mixed>
     */
    private function snapshot(GeneralClientSetting $row, array $columns, array $options): array
    {
        $snapshot = [];

        foreach ($columns as $column) {
            $value = $row->getAttribute($column);
            $snapshot[$column] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        }

        foreach ($options as $option) {
            $snapshot[$option] = $row->option($option);
        }

        return $snapshot;
    }

    /**
     * تحويل قيمة النموذج إلى نوعها المخزَّن.
     *
     * `int?`/`float?` تُبقيان `null` على حالها: الفراغ في «أقصى عدد عملاء»
     * يعني «بلا حدّ»، وتحويله إلى صفرٍ يقلبه إلى منعٍ كامل.
     */
    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => $value === null || $value === '' ? null : (int) $value,
            'int?' => $value === null || $value === '' ? null : (int) $value,
            // قائمةُ معرّفات — تُحفظ مصفوفةً نظيفة بلا فراغات ولا تكرار،
            // والفراغُ يبقى `[]` لا `null`: «كل الأقسام» قيمةٌ لا غياب
            'int[]' => array_values(array_unique(array_filter(
                array_map('intval', is_array($value) ? $value : []),
            ))),
            // صفوفُ الشرائح تُحفظ منقّاةً بالدالّة نفسها التي تقرؤها بها اللوحة
            'segments:lifecycle' => \App\Support\ClientDashboardSegments::normalizeLifecycle($value),
            'segments:aging' => \App\Support\ClientDashboardSegments::normalizeAging($value),
            'float', 'float?' => $value === null || $value === '' ? null : (float) $value,
            default => $value === '' ? null : $value,
        };
    }
}
