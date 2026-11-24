<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountSetting;
use App\Models\Backup;
use App\Models\Client;
use App\Models\Log as ModelsLog;
use App\Models\User;
use App\Services\Cron\CronMonitoringService;
use App\Services\Settings\ApplicationRegistry;
use App\Services\Settings\SettingsNavigation;
use App\Services\Settings\SubscriptionOverview;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * إعدادات الحساب — بيانات المنشأة والتفضيلات الإقليمية وبيانات الدخول.
 *
 * إعدادات المظهر (الألوان والخط) انتقلت إلى `AppearanceController`.
 */
class SittingAccountController extends Controller
{
    /** حقول العميل التي تعكس بيانات المنشأة — مصدرها نموذج الإعدادات */
    protected const CLIENT_FIELDS = [
        'trade_name', 'first_name', 'last_name', 'phone', 'mobile', 'email',
        'street1', 'street2', 'city', 'region', 'postal_code', 'country',
        'tax_number', 'commercial_registration', 'currency', 'code', 'notes',
        'category', 'client_type', 'employee_id', 'opening_balance_date',
    ];

    public function __construct()
    {
        // حماية بصلاحية تعديل الإعدادات العامة
        $this->middleware('permission:settings_edit_general_settings');
    }

    public function index()
    {
        $userId = auth()->id();

        return view('sitting::sittingAccount.index', [
            'client' => Client::where('user_id', $userId)->first(),
            'user' => User::find($userId),
            'account_setting' => AccountSetting::where('user_id', $userId)->first(),
        ]);
    }

    /**
     * لوحة الإعدادات — فهرس كل شاشات القسم.
     *
     * البنود تُقرأ من `SettingsNavigation` نفسه الذي تقرأ منه القائمة
     * الجانبية، فلا تظهر شاشة في أحدهما دون الآخر. وقبله كانت اللوحة
     * تعرض 11 شاشة والقائمة 27، ورابط النسخ الاحتياطي فيها يشير إلى
     * شاشة قديمة، وشاشة «إدارة التطبيقات» غائبة عنهما معاً.
     */
    public function dashboard(SubscriptionOverview $subscriptions, CronMonitoringService $cron)
    {
        $lastBackup = null;

        try {
            $lastBackup = Backup::available()->latest('completed_at')->first();
        } catch (\Throwable) {
            // جدول النسخ غير مُهجَّر في تركيب آخر — البطاقة تعرض «لا توجد»
        }

        $statuses = ApplicationRegistry::statuses();

        return view('sitting::dashboard.index', [
            'groups' => SettingsNavigation::visible(),
            'screenCount' => SettingsNavigation::count(),
            'subscription' => $subscriptions->current(),
            'activeModules' => count(array_filter($statuses, fn ($s) => $s === ApplicationRegistry::ACTIVE)),
            'totalModules' => count($statuses),
            'scheduler' => $cron->schedulerHealth(),
            'backupAgeHours' => $lastBackup?->completed_at
                ? (int) $lastBackup->completed_at->diffInHours(now())
                : null,
        ]);
    }

    /**
     * حفظ إعدادات الحساب وبيانات المنشأة.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'trade_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'street1' => ['nullable', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'commercial_registration' => ['nullable', 'string', 'max:50'],
            'code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // التفضيلات الإقليمية
            'currency' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'language' => ['nullable', 'string', 'max:10'],
            'negative_currency_formats' => ['nullable', 'string', 'max:50'],
            // صيغة التاريخ والوقت المعتمدة — تقرأها شاشات المبيعات والعملاء
            'time_formula' => ['nullable', 'string', 'max:50'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'printing_method' => ['nullable', 'string', 'max:50'],
            // العمود `int` — كسر عشري هنا يُقرَّب صامتاً عند الحفظ
            'global_client_exchange_limit' => ['nullable', 'integer', 'min:0'],
            'backup_frequency' => ['nullable', Rule::in(['', 'daily', 'weekly', 'monthly'])],

            // الإشعارات
            'notification_email' => ['nullable', 'boolean'],
            'notification_sms' => ['nullable', 'boolean'],
            'notification_browser' => ['nullable', 'boolean'],

            // الصور
            'attachments' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'stamp' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ], [
            'trade_name.required' => __('settings.account.errors.trade_name_required'),
            'attachments.image' => __('settings.account.errors.logo_image'),
            'attachments.max' => __('settings.account.errors.logo_max'),
            'stamp.max' => __('settings.account.errors.stamp_max'),
            'signature.max' => __('settings.account.errors.signature_max'),
        ]);

        $userId = auth()->id();

        try {
            DB::transaction(function () use ($request, $validated, $userId) {
                /* صفُّ المنشأة لا صفُّ الحافظ: `firstOrNew` تُعيد الصفَّ القائم
                   لمن لا صفَّ له (انظر [AccountSettingBuilder])، فلا يُنشأ صفٌّ
                   ثانٍ لكل مديرٍ يحفظ — ولا يُنقل الصفُّ إلى اسمه. */
                $setting = AccountSetting::firstOrNew(['user_id' => $userId]);
                $previousCurrency = $setting->currency;

                if (! $setting->exists) {
                    $setting->user_id = $userId;
                }

                foreach ([
                    'currency', 'timezone', 'language', 'negative_currency_formats',
                    'time_formula', 'business_type', 'printing_method',
                    'global_client_exchange_limit',
                ] as $field) {
                    if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                        $setting->{$field} = $validated[$field];
                    }
                }

                // الحقل قد لا يُرسل أصلاً (نموذج جزئي)، والقيمة الفارغة تعني «معطّل»
                if ($request->has('backup_frequency')) {
                    $frequency = $validated['backup_frequency'] ?? null;
                    $setting->backup_frequency = $frequency ?: null;
                    $setting->backup_auto = filled($frequency);
                }

                $setting->notification_email = $request->boolean('notification_email');
                $setting->notification_sms = $request->boolean('notification_sms');
                $setting->notification_browser = $request->boolean('notification_browser');

                // الشعار والختم والتوقيع: الملف القديم يُحذف حتى لا يتراكم التخزين
                foreach (['attachments', 'stamp', 'signature'] as $field) {
                    if (! $request->hasFile($field)) {
                        continue;
                    }

                    if ($setting->{$field}) {
                        Storage::disk('public')->delete($setting->{$field});
                    }

                    $setting->{$field} = $request->file($field)->store('attachments', 'public');
                }

                $setting->save();

                // مزامنة سجل العميل المقابل للمنشأة.
                // الحقول تُنسخ من المُدخل المُتحقَّق منه فقط — كان `attachments`
                // يُسند هنا كائن ملف مرفوع إلى عمود نصي فيفشل الحفظ كاملاً.
                // وسجلُّ المنشأة في العملاء يتبع صاحبَ صفّ الإعدادات نفسَه
                $client = Client::firstOrNew(['user_id' => $setting->user_id]);
                $client->user_id = $setting->user_id;

                /* «العملة الافتراضية» هنا وفي شاشة العملات إعدادٌ واحد: كان
                   هذا الحقلُ يكتب `account_settings.currency` وحدَه، فتُعرض
                   العملةُ الجديدة في بعض الشاشات ويبقى التنسيقُ والرمزُ على
                   القديمة. يُحدَّث جدولُ العملات معه متى عرف الرمز. */
                if ($setting->currency !== $previousCurrency
                    && \App\Models\SystemCurrency::where('code', $setting->currency)->exists()) {
                    \App\Models\SystemCurrency::query()->update(['is_default' => false]);
                    \App\Models\SystemCurrency::where('code', $setting->currency)->update(['is_default' => true, 'is_active' => true]);
                    app(\Modules\Sitting\Repositories\CurrencyRepository::class)->clearCache();
                }

                foreach (self::CLIENT_FIELDS as $field) {
                    if ($request->has($field)) {
                        $client->{$field} = $validated[$field] ?? $request->input($field);
                    }
                }

                $client->save();

                ModelsLog::create([
                    'type' => 'setting',
                    'type_id' => $setting->id,
                    'type_log' => 'log',
                    'description' => __('settings.account.log_updated'),
                    'created_by' => $userId,
                ]);
            });

            // شاشة الاشتراك تعرض بيانات المنشأة من هنا وتحفظها عشر دقائق —
            // بلا هذا يظلّ المسؤول يرى ما عدّله للتوّ قديماً هناك
            app(SubscriptionOverview::class)->flush();

            return redirect()->route('SittingAccount.index')->with('success', __('settings.account.saved'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.SittingAccount', 'store');

            return redirect()->back()->withInput()
                ->with('error', __('settings.account.save_error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * تغيير البريد الإلكتروني.
     */
    public function Change_email(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore(auth()->id())],
        ], [
            'email.required' => __('settings.account.errors.email_required'),
            'email.email' => __('settings.account.errors.email_invalid'),
            'email.unique' => __('settings.account.errors.email_unique'),
        ]);

        $user = User::findOrFail(auth()->id());
        $user->email = $request->email;
        $user->save();

        ModelsLog::create([
            'type' => 'setting',
            'type_log' => 'log',
            'description' => __('settings.account.log_email_changed'),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('SittingAccount.index')->with('success', __('settings.account.email_changed'));
    }

    /**
     * تغيير كلمة المرور.
     */
    public function change_password(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.confirmed' => __('settings.account.errors.password_confirmed'),
            'password.min' => __('settings.account.errors.password_min'),
            'password.different' => __('settings.account.errors.password_different'),
        ]);

        $user = User::findOrFail(auth()->id());

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => __('settings.account.errors.password_wrong')]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        ModelsLog::create([
            'type' => 'setting',
            'type_log' => 'log',
            'description' => __('settings.account.log_password_changed'),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('SittingAccount.index')->with('success', __('settings.account.password_changed'));
    }
}
