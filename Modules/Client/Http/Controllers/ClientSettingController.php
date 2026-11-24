<?php

namespace Modules\Client\Http\Controllers;

use App\Helpers\ClientSettingsHelper;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientPermission;
use App\Models\Invoice;
use App\Models\JournalEntryDetail;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Statuses;
use App\Models\User;
use App\Support\ClientPortalPermissions;
use App\Support\ClientSettings;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * إعدادات العميل: الملف الشخصي، والحالات، وصلاحيات بوابة العميل.
 *
 * **شاشة «عام» حُذفت من هنا** — دُمجت في «إعدادات وحدات العملاء».
 * كانت `general()` تعرض الحقول الإضافية ونوع العميل، و`store()` تحفظ
 * الحقول وحدها: النموذج يرسل `type` منذ أوّل يوم و`validate()` لا تذكره
 * ولا سطر يحفظه، فنوعُ العميل إعدادٌ يُختار ولا يُخزَّن. وصار الاثنان في
 * تبويب «العملاء» من الشاشة الموحّدة، ونوعُ العميل يُحفظ فعلاً في
 * `type_client` وفي `client_type_scope` معاً.
 *
 * و`createDefaultSettings()` كانت تزرع الحقول العشرة الافتراضية ولا مسار
 * يستدعيها؛ زرعُها الآن في `GeneralClientSettingSeeder` المسجَّل في
 * «تحديثات النظام».
 *
 * @see \Modules\Client\Http\Controllers\ClientModuleSettingsController الشاشة الموحّدة
 * @see \Database\Seeders\GeneralClientSettingSeeder زرع الحقول الافتراضية
 */
class ClientSettingController extends Controller
{
    /** قيمة العمود `type` في سجلّ النشاط */
    public const LOG_MODULE = 'ClientSettings';

    public function __construct()
    {
        $this->middleware('auth');

        // لوحة الإعدادات وحالاتُ العميل وصلاحياتُ بوابته — ثلاث مجموعات
        // معرَّفة في الكتالوج منذ البداية ولم تكن على المتحكّم واحدةٌ منها
        $this->middleware('permission:clients_edit_client_settings|settings_customer_categories|settings_customer_followup_status|settings_customer_permissions', ['only' => ['setting']]);

        $this->middleware('permission:settings_customer_followup_status|clients_edit_client_settings', ['only' => [
            'status', 'storeStatus', 'updateStatus', 'deleteStatus',
            'registrationStages', 'storeRegistrationStage', 'updateRegistrationStage', 'deleteRegistrationStage',
        ]]);

        $this->middleware('permission:settings_customer_permissions|clients_edit_client_settings', ['only' => [
            'permission', 'permission_store',
        ]]);
    }

    public function setting()
    {
        return view('client::setting.index');
    }

    /**
     * حالات العملاء وحدها (`section = null`) — `statuses` جدولٌ مشترَك،
     * و`Statuses::all()` كانت تجلب حالات أقسامٍ أخرى معها. والقالب كان
     * يُستدعى بمسارٍ خاطئ (`client.setting.status` بدل `client::setting.status`)
     * فالشاشة كانت 500 دائماً.
     */
    public function status()
    {
        $statuses = Statuses::whereNull('section')->ordered()->get();

        return view('client::setting.status', compact('statuses'));
    }

    /**
     * حالة العميل التشغيلية ثنائيةٌ بقرار: نشط أو موقوف، لا ثالث لهما — راجع
     * `2026_09_01_120000_seed_canonical_client_statuses` التي تضمن وجود
     * الصفّين المحميين بـ`kpi_role`. فلا مجال لإنشاء حالة عميل ثالثة من هذه
     * الشاشة مهما كان اسمها؛ أيّ تصنيفٍ إضافي (سبب توقيف، مرحلة تسجيل، علامة
     * توثيقية) يعيش في حقلٍ آخر لا في هذا الجدول.
     */
    public function storeStatus(Request $request)
    {
        if (Statuses::whereNull('section')->count() >= 2) {
            return redirect()->route('clients.status')
                ->with('error', 'حالة العميل تُحصر في «نشط» و«موقوف» فقط — عدّل الحالتين الموجودتين بدل إضافة حالةٍ ثالثة.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('statuses', 'name')->where(fn ($q) => $q->whereNull('section'))],
            'color' => 'nullable|string|max:20',
            'state' => 'nullable|in:open,closed',
            'kpi_role' => 'nullable|in:active,stopped',
        ]);

        try {
            $status = Statuses::create([
                'section' => null,
                'name' => $request->name,
                'color' => $request->color,
                'state' => $request->state === 'closed' ? 0 : 1,
                'kpi_role' => $request->kpi_role,
            ]);
        } catch (\Throwable $e) {
            \App\Support\OperationLog::failure($e, self::LOG_MODULE, 'store_status');

            return redirect()->back()->withInput()->with('error', 'تعذّر حفظ الحالة: '.$e->getMessage());
        }

        \App\Support\OperationLog::record(
            'status_created',
            self::LOG_MODULE,
            $status,
            'إضافة حالة عميل «'.$status->name.'»',
            ['new' => $status->only(['name', 'color', 'state', 'kpi_role'])]
        );

        return redirect()->route('clients.status')->with('success', 'تمت إضافة الحالة بنجاح.');
    }

    public function updateStatus(Request $request, $id)
    {
        $status = Statuses::whereNull('section')->find($id);

        if (! $status) {
            return redirect()->route('clients.status')->with('error', 'الحالة غير موجودة.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('statuses', 'name')->where(fn ($q) => $q->whereNull('section'))->ignore($status->id)],
            'color' => 'nullable|string|max:20',
            'state' => 'nullable|in:open,closed',
            'kpi_role' => 'nullable|in:active,stopped',
        ]);

        $before = $status->only(['name', 'color', 'state', 'kpi_role']);

        try {
            $status->update([
                'name' => $request->name,
                'color' => $request->color,
                'state' => $request->state === 'closed' ? 0 : 1,
                'kpi_role' => $request->kpi_role,
            ]);
        } catch (\Throwable $e) {
            \App\Support\OperationLog::failure($e, self::LOG_MODULE, 'update_status', (int) $id);

            return redirect()->back()->withInput()->with('error', 'تعذّر تحديث الحالة: '.$e->getMessage());
        }

        \App\Support\OperationLog::record(
            'status_updated',
            self::LOG_MODULE,
            $status,
            'تعديل حالة العميل «'.$status->name.'»',
            ['old' => $before, 'new' => $status->only(['name', 'color', 'state', 'kpi_role'])]
        );

        return redirect()->route('clients.status')->with('success', 'تم تحديث الحالة بنجاح.');
    }

    public function deleteStatus($id)
    {
        $status = Statuses::whereNull('section')->find($id);

        if (! $status) {
            return redirect()->route('clients.status')->with('error', 'الحالة غير موجودة.');
        }

        // الحذف يمرّ من isInUse() لا من زرٍّ في الشاشة وحده — حذف حالةٍ
        // مربوطة بعميلٍ يترك clients.status_id يشير إلى صفٍّ محذوف
        if ($status->is_deletable === false || $status->isInUse()) {
            return redirect()->route('clients.status')->with('error', 'لا يمكن حذف هذه الحالة — مستعملة حالياً في بيانات العملاء.');
        }

        try {
            // السجلّ قبل الحذف: بعده لا يبقى ما يُوصَف
            \App\Support\OperationLog::record(
                'status_deleted',
                self::LOG_MODULE,
                $status,
                'حذف حالة العميل «'.$status->name.'»',
                ['old' => $status->only(['name', 'color', 'state', 'kpi_role'])]
            );

            $status->delete();
        } catch (\Throwable $e) {
            \App\Support\OperationLog::failure($e, self::LOG_MODULE, 'delete_status', (int) $id);

            return redirect()->route('clients.status')->with('error', 'تعذّر حذف الحالة: '.$e->getMessage());
        }

        return redirect()->route('clients.status')->with('success', 'تم حذف الحالة بنجاح.');
    }

    /**
     * مراحل التسجيل (`section = 'registration_stage'`) — قائمةٌ مفتوحة
     * بخلاف حالات العميل: تفصيلٌ إضافي على العميل لا يمسّ حالته
     * التشغيلية، فلا سقفَ على عددها ولا حصرَ على اسمَين، والأدمن يضيف
     * ويعدّل ويحذف بحرّية. انظر docs/CRM_Customer_Status_Gap_Analysis_Plan.md
     * القسم 3.
     */
    public function registrationStages()
    {
        $stages = Statuses::section('registration_stage')->ordered()->get();

        return view('client::setting.registration_stages', compact('stages'));
    }

    public function storeRegistrationStage(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('statuses', 'name')->where(fn ($q) => $q->where('section', 'registration_stage'))],
            'color' => 'nullable|string|max:20',
        ]);

        try {
            $stage = Statuses::create([
                'section' => 'registration_stage',
                'name' => $request->name,
                'color' => $request->color,
                'is_deletable' => true,
            ]);
        } catch (\Throwable $e) {
            \App\Support\OperationLog::failure($e, self::LOG_MODULE, 'store_registration_stage');

            return redirect()->back()->withInput()->with('error', 'تعذّر حفظ وضع العميل: '.$e->getMessage());
        }

        \App\Support\OperationLog::record(
            'status_created',
            self::LOG_MODULE,
            $stage,
            'إضافة وضع «'.$stage->name.'»',
            ['new' => $stage->only(['name', 'color'])]
        );

        return redirect()->route('clients.registrationStages')->with('success', 'تمت إضافة الوضع بنجاح.');
    }

    public function updateRegistrationStage(Request $request, $id)
    {
        $stage = Statuses::section('registration_stage')->find($id);

        if (! $stage) {
            return redirect()->route('clients.registrationStages')->with('error', 'الوضع غير موجود.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('statuses', 'name')->where(fn ($q) => $q->where('section', 'registration_stage'))->ignore($stage->id)],
            'color' => 'nullable|string|max:20',
        ]);

        $before = $stage->only(['name', 'color']);

        try {
            $stage->update([
                'name' => $request->name,
                'color' => $request->color,
            ]);
        } catch (\Throwable $e) {
            \App\Support\OperationLog::failure($e, self::LOG_MODULE, 'update_registration_stage', (int) $id);

            return redirect()->back()->withInput()->with('error', 'تعذّر تحديث الوضع: '.$e->getMessage());
        }

        \App\Support\OperationLog::record(
            'status_updated',
            self::LOG_MODULE,
            $stage,
            'تعديل وضع «'.$stage->name.'»',
            ['old' => $before, 'new' => $stage->only(['name', 'color'])]
        );

        return redirect()->route('clients.registrationStages')->with('success', 'تم تحديث الوضع بنجاح.');
    }

    public function deleteRegistrationStage($id)
    {
        $stage = Statuses::section('registration_stage')->find($id);

        if (! $stage) {
            return redirect()->route('clients.registrationStages')->with('error', 'الوضع غير موجود.');
        }

        // الحذف يمرّ من isInUse() لا من زرٍّ في الشاشة وحده — حذف وضعٍ
        // مربوطٍ بعميلٍ يترك clients.registration_stage_id يشير إلى صفٍّ محذوف
        if ($stage->is_deletable === false || $stage->isInUse()) {
            return redirect()->route('clients.registrationStages')->with('error', 'لا يمكن حذف هذا الوضع — مستعمل حالياً في بيانات العملاء.');
        }

        try {
            \App\Support\OperationLog::record(
                'status_deleted',
                self::LOG_MODULE,
                $stage,
                'حذف وضع «'.$stage->name.'»',
                ['old' => $stage->only(['name', 'color'])]
            );

            $stage->delete();
        } catch (\Throwable $e) {
            \App\Support\OperationLog::failure($e, self::LOG_MODULE, 'delete_registration_stage', (int) $id);

            return redirect()->route('clients.registrationStages')->with('error', 'تعذّر حذف الوضع: '.$e->getMessage());
        }

        return redirect()->route('clients.registrationStages')->with('success', 'تم حذف الوضع بنجاح.');
    }

    /**
     * شاشة «صلاحيات بوابة العميل».
     *
     * كانت تُصفّي المفاتيح بـ`where('category', 'basic')` على جدولٍ **لا
     * عمود `category` فيه**، فتخرج الأقسام الثلاثة فارغةً دائماً: تُفتح
     * الشاشة بلا مفتاحٍ واحد، وأيُّ حفظٍ يُطفئ الثمانية جميعاً لأن
     * `settings[]` لا يصل فيه شيء. التقسيم صار في
     * `ClientPortalPermissions::groups()` — كودٌ لا عمود.
     *
     * ويُختار هنا أيضاً **دور العميل**: أيُّ دورٍ في النظام تُعدّ حساباتُه
     * حسابات عملاء تُحصَر في البوابة. لا اسمَ دورٍ مثبَّتٌ في الكود.
     */
    public function permission()
    {
        $groups = ClientPortalPermissions::groups();
        $activeKeys = ClientPortalPermissions::activeKeys();

        // مفتاحٌ في الكتالوج ولا صفَّ له في الجدول لا يمكن تفعيله — تُميّزه
        // الشاشة بدل أن تقدّم مفتاحاً لا يُحفَظ
        $storedKeys = ClientPermission::pluck('key')->all();

        $roles = Role::orderBy('name')->get(['id', 'name']);
        $portalRoleId = ClientSettingsHelper::clientPortalRoleId();

        // كم حساباً سيتأثّر فعلاً بالدور المختار — رقمٌ يمنع اختيار دور
        // «مدير» بالخطأ ثم اكتشافَ الحصر من شكاوى الموظفين
        $affectedUsers = $portalRoleId
            ? User::where('role_id', $portalRoleId)->whereNotNull('client_id')->count()
            : 0;

        $roleUsers = $portalRoleId
            ? User::where('role_id', $portalRoleId)->count()
            : 0;

        return view('client::setting.permission', compact(
            'groups', 'activeKeys', 'storedKeys', 'roles', 'portalRoleId', 'affectedUsers', 'roleUsers'
        ));
    }

    /**
     * حفظ المفاتيح ودور البوابة معاً في معاملةٍ واحدة.
     *
     * الحفظ **بالمفتاح النصّي** (`key`) لا بـ`id`: المُعرّفات تختلف بين
     * قاعدةٍ وأخرى (زرعٌ بترتيبٍ مختلف، صفٌّ حُذف)، فنموذجٌ يرسل `id`
     * يُفعّل في الإنتاج مفتاحاً غير الذي ضُغط في الشاشة.
     */
    public function permission_store(Request $request)
    {
        $known = array_keys(ClientPortalPermissions::all());

        /* `perm[<key>]` خريطةً لا `settings[]` قائمةً: مكوّن المفتاح
           (`client::components.switch`) يُرسل حقلاً مخفيّاً بـ`0` قبل
           المربّع، وهو ما يجعل الإطفاء يصل إلى الخادم أصلاً. وقائمةٌ
           باسمٍ واحدٍ مكرَّر كانت ستستقبل `0` قيمةً فتسقط في `Rule::in`. */
        $validated = $request->validate([
            'perm' => 'nullable|array',
            'perm.*' => 'nullable|boolean',
            'portal_role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ], [], [
            'perm' => 'صلاحيات البوابة',
            'portal_role_id' => 'دور العميل',
        ]);

        // مفتاحٌ لا يعرفه الكتالوج يُهمَل بصمت — نموذجٌ قديمٌ في تبويبٍ
        // مفتوحٍ منذ نشرةٍ سابقة يجب ألّا يُسقط الحفظ كلَّه
        $chosen = array_values(array_intersect(
            array_keys(array_filter($validated['perm'] ?? [])),
            $known
        ));

        $portalRoleId = $validated['portal_role_id'] ?? null;

        $before = [
            'active_keys' => ClientPortalPermissions::activeKeys(),
            'portal_role_id' => ClientSettingsHelper::clientPortalRoleId(),
        ];

        try {
            DB::transaction(function () use ($chosen, $known, $portalRoleId) {
                /* الكتالوجُ هو المرجع لا الجدول: كان الحفظ يمرّ على الصفوف
                   الموجودة وحدَها، فمفتاحٌ أُضيف إلى الكتالوج ولا صفَّ له
                   يبقى غيرَ قابلٍ للتفعيل أبداً — يُعرض في الشاشة ويُضغط
                   ولا يُحفَظ. الصفُّ يُنشأ عند أوّل حفظٍ مطفأً أو مفعَّلاً
                   بحسب ما اختير. */
                foreach ($known as $key) {
                    ClientPermission::updateOrCreate(
                        ['key' => $key],
                        [
                            'name' => ClientPortalPermissions::all()[$key]['label'] ?? $key,
                            'is_active' => in_array($key, $chosen, true),
                        ],
                    );
                }

                // مفتاحٌ في الجدول خرج من الكتالوج يُطفأ ولا يُحذف — لا يحرس
                // شيئاً بعد خروجه، وحذفُه يفقد أثرَ من فعّله
                ClientPermission::whereNotIn('key', $known)->update(['is_active' => false]);

                $row = ClientSettings::group('client_portal_settings');
                $options = $row->options ?? [];
                $options['portal_role_id'] = $portalRoleId;
                $row->options = $options;
                $row->save();
            });
        } catch (\Throwable $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'permission_store');

            return redirect()->back()->withInput()
                ->with('error', 'حدث خطأ أثناء حفظ صلاحيات البوابة. يرجى المحاولة مرة أخرى.');
        }

        // الكاشان معاً: مفاتيحُ البوابة في `ClientPortalPermissions`،
        // ومجموعةُ الإعداد في `ClientSettings` — وإلّا بقي الحارس يقرأ
        // القديم إلى ساعة، فيبدو الحفظ وكأنه لم يقع
        ClientPortalPermissions::forget();
        ClientSettings::forget();

        OperationLog::record(
            'updated',
            self::LOG_MODULE,
            null,
            'تعديل صلاحيات بوابة العميل',
            [
                'before' => $before,
                'after' => [
                    'active_keys' => $chosen,
                    'portal_role_id' => $portalRoleId,
                ],
            ]
        );

        return redirect()->route('clients.permission')->with('success', 'تم حفظ التغييرات بنجاح.');
    }

    // ══════════════════ بوابة العميل ══════════════════

    /**
     * حارسُ شاشات البوابة داخل المتحكّم — طبقةٌ ثانية تحت
     * `ClientAccessMiddleware`.
     *
     * لماذا طبقتان؟ الوسيط يحصر **العميل** فيما هو مسموحٌ له؛ وهذا يمنع
     * **غير العميل** من شاشةٍ لا تعنيه. فمديرٌ يفتح `clients.personal`
     * حسابُه بلا `client_id`، فتصير كل استعلامات الشاشة
     * `where('client_id', null)` — صفحةٌ فارغةٌ بلا تفسير، و`findOrFail(null)`
     * في `profile()` صفحةُ خطأ ٤٠٤ صريحة.
     *
     * @return array{0: ?\Illuminate\Http\RedirectResponse, 1: ?int} [التحويل إن وجب، مُعرّف العميل]
     */
    private function guardPortal(string $permissionKey): array
    {
        $clientId = auth()->user()?->client_id;

        if (! $clientId) {
            return [
                redirect()->route('dashboard')
                    ->with('error', 'هذه الشاشة لحسابات العملاء وحدها — حسابك غير مرتبط بسجلّ عميل.'),
                null,
            ];
        }

        if (! ClientPortalPermissions::enabled($permissionKey)) {
            return [
                redirect()->route('login')
                    ->with('error', 'هذه الصفحة غير متاحة في البوابة حالياً. راجع إدارة النظام.'),
                null,
            ];
        }

        return [null, (int) $clientId];
    }

    public function personal()
    {
        [$deny, $clientId] = $this->guardPortal('view_client_profile');

        if ($deny) {
            return $deny;
        }

        $client = Client::find($clientId);

        /* أرقام الملخّص بالتجميع لا بجلب الصفوف: `->get()` ثم `count()` على
           عميلٍ له مئات الفواتير يحمّل الذاكرة كلَّها لتُقرأ منها ثلاثة أرقام.
           والجدول أدناه يجلب آخر خمسٍ فقط. */
        $invoicesCount = Invoice::where('client_id', $clientId)->where('type', '!=', 'returned')->count();
        $invoicesTotal = (float) Invoice::where('client_id', $clientId)->where('type', '!=', 'returned')->sum('grand_total');
        $invoicesDue = (float) Invoice::where('client_id', $clientId)->sum('due_value');

        $recentInvoices = Invoice::where('client_id', $clientId)
            ->where('type', '!=', 'returned')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        /* المرتجعاتُ فواتيرُ نوعُها `returned` لا جدولٌ مستقل — ولذلك تُستثنى
           من عدّ الفواتير أعلاه، وإلّا عُدّ المرتجعُ بيعاً مرّةً أخرى. */
        $returnsQuery = Invoice::where('client_id', $clientId)->where('type', 'returned');
        $returnsTotal = (float) (clone $returnsQuery)->sum('grand_total');
        $recentReturns = (clone $returnsQuery)->orderByDesc('created_at')->limit(5)->get();

        /* ملخّصُ الحساب من قيود حساب العميل المحاسبي لا من جدول الفواتير:
           الرصيدُ هو ما تحرّك فعلاً — فاتورةً وسداداً وإشعاراً دائناً ورصيداً
           افتتاحياً. وحسابُ «المدفوع» من الفواتير وحدها يُسقط كلَّ سدادٍ لم
           يُخصَّص لفاتورة. انظر `statement()` — المصدر نفسه. */
        $account = Account::ledger()->where('client_id', $clientId)->first();
        $ledgerTotals = ['debit' => 0.0, 'credit' => 0.0];
        $recentMovements = collect();
        $monthlySeries = [];

        if ($account) {
            $ledgerTotals = [
                'debit' => (float) JournalEntryDetail::where('account_id', $account->id)->sum('debit'),
                'credit' => (float) JournalEntryDetail::where('account_id', $account->id)->sum('credit'),
            ];

            $recentMovements = JournalEntryDetail::where('account_id', $account->id)
                ->with('journalEntry')
                ->orderByDesc('id')
                ->limit(6)
                ->get();

            /* منحنى ستّة أشهر: صافي حركة كل شهر (مدين − دائن) بالتجميع في
               قاعدة البيانات لا بجلب القيود كلِّها وطيّها في PHP. */
            $since = now()->copy()->startOfMonth()->subMonths(5);

            $rows = JournalEntryDetail::query()
                ->where('account_id', $account->id)
                ->where('created_at', '>=', $since)
                /* الفرقُ يُحسب في الاستعلام باسمٍ واحد: `pluck(DB::raw('d - c'))`
                   يبحث عن خاصّيةٍ اسمُها «d - c» في صفّ النتيجة فلا يجدها،
                   فتعود القيمُ فارغةً والمنحنى مسطّحاً بلا خطأ يُرى. */
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(debit) - SUM(credit) as net")
                ->groupBy('ym')
                ->pluck('net', 'ym');

            for ($i = 5; $i >= 0; $i--) {
                $month = now()->copy()->startOfMonth()->subMonths($i);
                $monthlySeries[] = [
                    'label' => $month->locale('ar')->translatedFormat('F'),
                    'value' => round((float) ($rows[$month->format('Y-m')] ?? 0), 2),
                ];
            }
        }

        /* الحجوزاتُ القادمة — تُعرض في اللوحة ولو كان مفتاحُ الحجز مطفأً:
           الموظّفُ قد يحجز للعميل من الداخل، والعميلُ يستحقّ أن يرى موعده. */
        $upcomingReservations = collect();

        if (class_exists(\Modules\Client\Reservations\Models\Reservation::class)) {
            $upcomingReservations = \Modules\Client\Reservations\Models\Reservation::query()
                ->where('client_id', $clientId)
                ->whereDate('reservation_date', '>=', today())
                ->whereNotIn('status', ['cancelled', 'rejected', 'no_show'])
                ->with('product')
                ->orderBy('reservation_date')
                ->orderBy('start_time')
                ->limit(4)
                ->get();
        }

        /* إشعاراتُ العميل — ما وُجّه إلى حسابه أو كُتب باسم عميله.
           بطاقةُ «إشعاراتك» في اللوحة تقرؤها، وهي نفسُها التي يعدّها جرسُ
           الترويسة، فلا رقمٌ يخالف قائمةً. */
        $portalNotices = \App\Models\notifications::query()
            ->where(fn ($q) => $q->where('receiver_id', auth()->id())->orWhere('client_id', $clientId))
            ->orderByDesc('id')
            ->limit(4)
            ->get(['id', 'title', 'description', 'type', 'created_at']);

        return view('client::portal.dashboard', [
            'portalClient' => $client,
            'portalNotices' => $portalNotices,
            'invoicesCount' => $invoicesCount,
            'invoicesTotal' => $invoicesTotal,
            'invoicesDue' => $invoicesDue,
            'recentInvoices' => $recentInvoices,
            'returnsTotal' => $returnsTotal,
            'recentReturns' => $recentReturns,
            'ledgerBalance' => $ledgerTotals['debit'] - $ledgerTotals['credit'],
            'ledgerPaid' => $ledgerTotals['credit'],
            'recentMovements' => $recentMovements,
            'monthlySeries' => $monthlySeries,
            'upcomingReservations' => $upcomingReservations,
            'allowedRoutes' => ClientPortalPermissions::allowedRoutes(),
        ]);
    }

    /**
     * كشف حساب العميل في البوابة.
     *
     * من قيود حسابه المحاسبي لا من جدول الفواتير: الفواتير تُظهر ما صدر له
     * وحده، والحساب يُظهر كلَّ ما تحرّك — الفاتورة والسداد والإشعار الدائن
     * والرصيد الافتتاحي. وكشفٌ لا يُظهر سدادَ العميل كشفٌ يُخاصم.
     */
    public function statement()
    {
        [$deny, $clientId] = $this->guardPortal('view_account_statement');

        if ($deny) {
            return $deny;
        }

        $client = Client::find($clientId);
        $account = Account::ledger()->where('client_id', $clientId)->first();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $balance = 0.0;

        if ($account) {
            $details = JournalEntryDetail::where('account_id', $account->id)
                ->with('journalEntry')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($details as $detail) {
                $debit = (float) $detail->debit;
                $credit = (float) $detail->credit;

                $totalDebit += $debit;
                $totalCredit += $credit;
                $balance += $debit - $credit;

                $entry = $detail->journalEntry;

                $rows[] = [
                    'date' => optional($entry?->date ?? $detail->created_at)->format('Y-m-d') ?? '—',
                    'description' => $detail->description ?: ($entry->description ?? 'حركة على الحساب'),
                    'reference' => (string) ($entry->reference_number ?? ''),
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $balance,
                ];
            }
        }

        return view('client::portal.statement', [
            'portalClient' => $client,
            'rows' => $rows,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'closingBalance' => $balance,
        ]);
    }

    public function invoice_client(Request $request)
    {
        [$deny, $clientId] = $this->guardPortal('view_and_pay_invoices');

        if ($deny) {
            return $deny;
        }

        $invoices = Invoice::with(['client', 'createdByUser', 'updatedByUser'])
            ->where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->get();

        /* القالب القديم (`setting.invoice_client`) كان شاشةَ إدارةٍ منسوخة:
           قوائمُ تصفيةٍ بكل العملاء والموظّفين والمستخدمين، ونموذجُ إنشاء
           فاتورة. استُبدل بشاشةِ عرضٍ للعميل وحده. */
        return view('client::portal.invoices', [
            'portalClient' => Client::find($clientId),
            'invoices' => $invoices,

            /* إشعاراتُ السداد التي أرسلها العميل — تُعرض معها ليعرف مصير
               إشعاره، وليُمنع إرسالُ إشعارٍ ثانٍ على فاتورةٍ إشعارُها معلّق.
               والمفتاحُ يُمرَّر لأن الشاشة تُفتح بمفتاح «مشاهدة فواتيره»
               وحدَه، والسدادُ مفتاحٌ آخر قد يكون مطفأً. */
            'canPay' => ClientPortalPermissions::enabled('submit_payment_notice'),

            /* طرقُ الدفع من «الإعدادات ← طرق الدفع» لا من قائمةٍ في الشيفرة
               (طلب المستخدم 2026-09-19). وتحمل معها تعليماتِ المنشأة وحسابَها
               البنكي، فيرى العميلُ إلى أين يحوّل قبل أن يرسل إشعاره. */
            'paymentMethods' => \App\Models\PaymentMethod::query()
                ->manual()
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'instructions', 'bank_name', 'bank_account_name',
                    'bank_account_number', 'bank_iban', 'min_amount', 'max_amount', 'is_default']),
            'paymentNotices' => \App\Models\ClientPaymentNotice::where('client_id', $clientId)
                ->with('invoice')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    private function generateInvoiceNumber()
    {
        $lastInvoice = Invoice::latest()->first();
        $nextId = $lastInvoice ? $lastInvoice->id + 1 : 1;

        return str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function appointments_client()
    {
        [$deny, $clientId] = $this->guardPortal('view_appointments');

        if ($deny) {
            return $deny;
        }

        /* ما عُلِّم «مشاركة مع العميل» وحده — موعدٌ داخليٌّ للموظفين عن العميل
           (متابعةُ تحصيلٍ مثلاً) ليس للعميل أن يقرأه. */
        $appointments = Appointment::where('client_id', $clientId)
            ->where('share_with_client', true)
            ->with('employees', 'employee')
            ->orderByDesc('appointment_date')
            ->orderByDesc('time')
            ->paginate(15);

        // فتحُ «مواعيدي» هو الاطّلاعُ على إشعارات المواعيد المشارَكة
        \App\Models\notifications::where('receiver_id', auth()->id())
            ->where('type', 'client_appointment_shared')
            ->where('read', false)
            ->update(['read' => true]);

        return view('client::portal.appointments', [
            'portalClient' => Client::find($clientId),
            'appointments' => $appointments,
        ]);
    }

    public function questions_client(Request $request)
    {
        [$deny, $clientId] = $this->guardPortal('view_and_approve_quotes');

        if ($deny) {
            return $deny;
        }

        $quotes = Quote::where('client_id', $clientId)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('client::portal.quotes', [
            'portalClient' => Client::find($clientId),
            'quotes' => $quotes,
        ]);
    }

    public function profile()
    {
        [$deny, $clientId] = $this->guardPortal('edit_client_profile');

        if ($deny) {
            return $deny;
        }

        $client = Client::findOrFail($clientId);

        return view('client::portal.profile', [
            'portalClient' => $client,
            'client' => $client,
        ]);
    }

    /**
     * الحقول التي يعدّلها العميل في ملفّه بنفسه — بيانات تواصلٍ وهوية لا غير.
     *
     * كان الحفظ `$request->except('_token')` مباشرةً إلى `update()`، و
     * `Client::$fillable` تقبل `credit_limit` و`credit_period` و`employee_id`
     * و`status_id` و`branch_id` و`password`. ونموذجُ «تعديل ملفّي» نفسه
     * يعرض الحدّ الائتماني والمدّة الائتمانية والرصيد الافتتاحي في حقولٍ
     * قابلةٍ للكتابة — أي أن العميل كان يرفع حدَّه الائتماني بضغطة حفظ،
     * ويُسند نفسه إلى موظّفٍ آخر، ويغيّر فرعه. قائمةٌ بيضاء لا سوداء:
     * حقلٌ جديدٌ في النموذج غداً يُرفض حتى يُضاف هنا عمداً.
     *
     * @var array<int, string>
     */
    private const CLIENT_SELF_EDITABLE = [
        'trade_name',
        'first_name',
        'last_name',
        'phone',
        'mobile',
        'street1',
        'street2',
        'city',
        'region',
        'postal_code',
        'country',
        'email',
        'tax_number',
        'commercial_registration',
        'notes',
        'attachments',
    ];

    public function Client_store(Request $request)
    {
        [$deny, $clientId] = $this->guardPortal('edit_client_profile');

        if ($deny) {
            return $deny;
        }

        $data_request = $request->only(self::CLIENT_SELF_EDITABLE);

        $client = Client::findOrFail($clientId);

        // حفظ نسخة من البيانات القديمة لتحديد التعديلات

        // معالجة الصورة إذا تم تحميلها
        if ($request->hasFile('attachments')) {
            $file = $request->file('attachments');
            if ($file->isValid()) {
                // حذف الملف القديم إذا وجد
                if ($client->attachments) {
                    $oldFile = public_path('uploads/clients/').$client->attachments;
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $filename = time().'_'.$file->getClientOriginalName();
                $file->move(public_path('uploads/clients'), $filename);
                $data_request['attachments'] = $filename;
            }
        }

        // تحديث بيانات العميل
        $client->update($data_request);

        // تحديد الحقول التي تم تعديلها

        // معالجة جهات الاتصال
        if ($request->has('contacts') && is_array($request->contacts)) {
            $existingContactIds = $client->contacts->pluck('id')->toArray();

            foreach ($request->contacts as $contactData) {
                if (isset($contactData['id']) && in_array($contactData['id'], $existingContactIds)) {
                    // تحديث جهة الاتصال الموجودة
                    $contact = $client->contacts()->find($contactData['id']);
                    $contact->update($contactData);
                } else {
                    // إضافة جهة اتصال جديدة
                    $newContact = $client->contacts()->create($contactData);
                }
            }

            // حذف جهات الاتصال غير المدرجة في الطلب
            $newContactIds = array_column($request->contacts, 'id');
            $contactsToDelete = array_diff($existingContactIds, $newContactIds);
            $client->contacts()->whereIn('id', $contactsToDelete)->delete();
        }

        return redirect()->route('clients.personal')->with('success', '✨ تم تحديث العميل بنجاح!');
    }

    /**
     * تحديث الإعدادات
     */
    public function update(Request $request, $id)
    {
        // التحقق من البيانات المرسلة
        $request->validate([
            'setting_name' => 'required|string|max:255',
            'setting_value' => 'required|string',
        ]);

        // تحديث الإعدادات في قاعدة البيانات (مثال)
        $setting = ClientSettingController::findOrFail($id); // افترض أن لديك نموذج ClientSetting
        $setting->name = $request->setting_name;
        $setting->value = $request->setting_value;
        $setting->save();

        return redirect()->route('clients.settings')->with('success', 'تم تحديث الإعدادات بنجاح.');
    }

    /**
     * أُلغيت `client_target_create()`/`client_target_store()`/`client_target()` كلياً:
     * كانت تدير رقم هدفٍ عامٍّ واحدٍ مشترك (`Target` id=2) تُقارَن به مدفوعات
     * كل عميل لتدريجه G/K/B/C/D — بلا صلاحيةٍ ولا اشتراكٍ يحرسها، وتأكَّد
     * أنها غير مُستخدَمةٍ فعلياً قبل الحذف. هذا نظامٌ مختلفٌ كلياً عن
     * «الأهداف» (`CustomerTarget`, `CustomerTargetController`) — فئاتٌ لكل
     * عميلٍ على حدة، محميّةٌ بصلاحيةٍ واشتراكٍ من الأصل — فلم تكن استبدالاً
     * لها بل ميزةً منفصلة. راجع `client.target.statistics_sitting` أدناه،
     * الباقية لأنها لا تزال المدخل إلى «إدارة تبرير الزيارات».
     */
    public function statistics_sitting(Request $request)
    {
        return view('client::setting.customer_statistics.index');
    }

    /**
     * حذف الإعدادات
     */
    public function destroy($id)
    {
        // حذف الإعدادات من قاعدة البيانات (مثال)
        $setting = ClientSettingController::findOrFail($id); // افترض أن لديك نموذج ClientSetting
        $setting->delete();

        return redirect()->route('clients.settings')->with('success', 'تم حذف الإعدادات بنجاح.');
    }
}
