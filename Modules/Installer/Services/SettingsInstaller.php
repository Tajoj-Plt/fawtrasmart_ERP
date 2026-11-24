<?php

namespace Modules\Installer\Services;

use App\Models\AccountSetting;
use App\Models\BusinessData;
use App\Models\ColorSitting;
use App\Models\JobRole;
use App\Models\SystemCurrency;
use App\Models\SystemLanguage;
use App\Models\User;
use App\Support\ThemeSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * تجهيز الإعدادات التي لا يعمل النظام بدونها بعد الهجرات والبذور.
 *
 * البذور تزرع *الخيارات* — قائمة العملات، قائمة اللغات، قوالب المستندات.
 * وهذه الطبقة تختار من بينها ما اختاره صاحب النظام: أي عملة افتراضية، أي
 * لغة، ما نسبة الضريبة، ما اسم المنشأة. بلا هذه الخطوة يفتح النظام على
 * قوائم كاملة بلا قيمة مختارة، وهو ما يظهر للمستخدم كأنه معطَّل.
 *
 * كل دالة هنا idempotent: تُستدعى مرتين فتُنتج الحالة نفسها. المعالج قد
 * يُعاد تشغيله بعد تعثّر، فإعادة التشغيل يجب أن تُكمل لا أن تُضاعف.
 */
class SettingsInstaller
{
    /** ما نُفِّذ فعلاً — يُعرض في شاشة الإتمام. */
    protected array $applied = [];

    public function applied(): array
    {
        return $this->applied;
    }

    /**
     * تنفيذ كل خطوات التجهيز.
     *
     * @param  array  $data  مخرجات المعالج المجمّعة من الخطوات السابقة
     */
    public function run(array $data): array
    {
        $admin = $this->createAdministrator($data['admin'] ?? []);

        $this->applyLanguage($data['settings']['language'] ?? 'ar');
        $this->applyCurrency($data['settings']['currency'] ?? 'SAR');
        $this->applyCompanyProfile($admin, $data['company'] ?? [], $data['settings'] ?? []);
        $this->applyAccountSettings($admin, $data['settings'] ?? []);
        $this->applyTaxSettings($data['settings'] ?? []);
        $this->applyAppearance($data['company'] ?? [], $data['settings'] ?? []);
        $this->applyGeneralSettings();
        $this->applyMailSettings($admin, $data['mail'] ?? []);
        $this->flushCaches();

        return $this->applied;
    }

    /**
     * التحقّق النهائي: هل استقرّت الإعدادات الأساسية فعلاً في قاعدة البيانات؟
     *
     * **لماذا فحصٌ منفصل عن التطبيق؟** لأن كل دالة `apply*` أعلاه تبتلع
     * استثناءها وتُسجّل ملاحظة، كي لا يُفشل إعدادٌ ثانوي التثبيتَ كلّه. لكن
     * ذلك يعني أن التثبيت قد ينتهي «بنجاح» وقاعدةُ البيانات بلا عملة افتراضية
     * ولا لغة — فيفتح المستخدم النظام على شاشاتٍ تبدو معطّلة ولا يعرف السبب.
     *
     * هذه الدالة تقرأ ما استقرّ فعلاً — لا ما حاولت الدوال كتابته — فتفرّق
     * بين «كُتب» و«وصل».
     *
     * @return array<int, array{key:string, label:string, ok:bool, value:?string, critical:bool}>
     */
    public function verify(): array
    {
        $checks = [];

        $checks[] = $this->verifyOne('admin', 'حساب مدير النظام', true, function () {
            $user = User::whereHas('roles')->first();

            return $user?->email;
        });

        $checks[] = $this->verifyOne('language', 'اللغة الافتراضية', true, function () {
            if (! Schema::hasTable('system_languages')) {
                return null;
            }

            $lang = SystemLanguage::where('is_default', true)->first();

            return $lang ? "{$lang->name} ({$lang->code})" : null;
        });

        // العملة الافتراضية في *جدول العملات* — لا في إعدادات الحساب وحدها.
        // شاشة العملات وكل تنسيق مبلغ في النظام يقرأ من هنا
        $checks[] = $this->verifyOne('currency', 'العملة الافتراضية في جدول العملات', true, function () {
            if (! Schema::hasTable('system_currencies')) {
                return null;
            }

            $currency = SystemCurrency::where('is_default', true)->first();

            return $currency ? "{$currency->name} ({$currency->code})" : null;
        });

        $checks[] = $this->verifyOne('account_currency', 'عملة إعدادات الحساب', true, function () {
            if (! Schema::hasTable('account_settings')) {
                return null;
            }

            return AccountSetting::query()->value('currency');
        });

        $checks[] = $this->verifyOne('timezone', 'المنطقة الزمنية', true, function () {
            if (! Schema::hasTable('account_settings')) {
                return null;
            }

            return AccountSetting::query()->value('timezone');
        });

        $checks[] = $this->verifyOne('tax', 'نسبة الضريبة', true, function () {
            if (! Schema::hasTable('tax_sittings')) {
                return null;
            }

            $row = DB::table('tax_sittings')->first();

            return $row ? ((float) $row->tax * 100).'% ('.$row->type.')' : null;
        });

        $checks[] = $this->verifyOne('theme', 'ثيم النظام', true, function () {
            if (! Schema::hasTable('color_sittings')) {
                return null;
            }

            $theme = ColorSitting::first();

            return $theme ? ($theme->primary_color.' — '.($theme->layout_mode ?: 'light')) : null;
        });

        $checks[] = $this->verifyOne('company', 'اسم المنشأة', true, function () {
            if (! Schema::hasTable('business_data')) {
                return null;
            }

            return DB::table('business_data')->value('business_name');
        });

        $checks[] = $this->verifyOne('roles', 'أدوار النظام والصلاحيات', true, function () {
            if (! Schema::hasTable('roles')) {
                return null;
            }

            $roles = DB::table('roles')->count();
            $permissions = DB::table('permissions')->count();

            return $roles > 0 && $permissions > 0 ? "{$roles} دور، {$permissions} صلاحية" : null;
        });

        $checks[] = $this->verifyOne('accounts', 'شجرة الحسابات', true, function () {
            if (! Schema::hasTable('accounts')) {
                return null;
            }

            $count = DB::table('accounts')->count();

            return $count > 0 ? "{$count} حساب" : null;
        });

        // غير حرجة: النظام يعمل بدونها وتُضبط لاحقاً من الإعدادات
        $checks[] = $this->verifyOne('mail', 'البريد الصادر', false, function () {
            if (! Schema::hasTable('smtp_settings')) {
                return null;
            }

            return DB::table('smtp_settings')->where('is_active', true)->value('smtp_host');
        });

        $checks[] = $this->verifyOne('sequences', 'ترقيم المستندات', false, function () {
            if (! Schema::hasTable('serial_settings')) {
                return null;
            }

            $count = DB::table('serial_settings')->count();

            return $count > 0 ? "{$count} قسم" : null;
        });

        return $checks;
    }

    /** هل نجحت كل الفحوص الحرجة؟ */
    public function verificationPassed(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['critical'] && ! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * فحصٌ واحد — الاستدعاء محاطٌ لأن جدولاً ناقصاً يجب أن يُقرأ «غير مضبوط»
     * لا أن يُسقط شاشة النتيجة.
     */
    protected function verifyOne(string $key, string $label, bool $critical, callable $resolver): array
    {
        try {
            $value = $resolver();
        } catch (Throwable $e) {
            $value = null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'ok' => filled($value),
            'value' => $value ? (string) $value : null,
            'critical' => $critical,
        ];
    }

    /** الدور المُسنَد إلى حساب التثبيت. */
    public const ADMIN_ROLE = 'manager';

    /**
     * مدير النظام — يُنشأ من نموذج المعالج لا من `UserSeeder`.
     *
     * **الدور `manager`، والصلاحيات الناقصة تُمنح للحساب نفسه.**
     *
     * سيدرات الصلاحيات المتخصّصة تفرّق بين الدورين بالاسم حرفياً: `owner`
     * يأخذ `SHARED + OWNER_ONLY` و`manager` يأخذ `SHARED` وحدها. فالإسناد
     * المجرَّد لـ`manager` كان يُخرج **الحساب الوحيد في النظام** ناقصَ سبع
     * صلاحيات — مفاتيح API وتصدير النسخ الاحتياطي وإدارة الاشتراك وقفل
     * الفترات المحاسبية — ولا أحد سواه يمنحها له.
     *
     * فتُمنح الفجوة لهذا المستخدم بعينه عبر `model_has_permissions`، لا
     * لدور `manager` كلّه: كل مديرٍ يُنشأ لاحقاً يبقى على حدوده المقصودة.
     *
     * والفجوة تُحسب من الدورين وقت التنفيذ — `owner` ناقص `manager` — لا من
     * ثوابت `OWNER_ONLY` المتفرّقة: بعضها يصل `manager` أصلاً من المنح
     * الأساسي في `RolesSeeder` (فهو في `JobRole::$job_roles`)، فنسخُها هنا
     * يمنح ما هو ممنوح ويُغفل ما يُضاف غداً.
     *
     * وإن غاب الدور — بأن تعثّرت البذور — يُنشأ هنا مع صلاحياته الأساسية،
     * فالتثبيت لا ينتهي بحسابٍ لا يستطيع صاحبه دخول شيء.
     */
    public function createAdministrator(array $admin): ?User
    {
        if (empty($admin['email']) || empty($admin['password'])) {
            $this->note('admin', false, 'لم تُمرَّر بيانات المدير — تُخُطِّي');

            return null;
        }

        try {
            $role = Role::where('name', self::ADMIN_ROLE)->where('guard_name', 'web')->first();

            if (! $role) {
                // شبكة أمان: البذور لم تُنشئ الدور، فيُبنى هنا بقائمة
                // الصلاحيات الأساسية نفسها التي يستعملها RolesSeeder
                $role = Role::create(['name' => self::ADMIN_ROLE, 'guard_name' => 'web']);
                $role->givePermissionTo(array_values(array_unique(JobRole::$job_roles)));
            }

            $user = User::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'] ?? 'مدير النظام',
                    'password' => Hash::make($admin['password']),
                    'phone' => $admin['phone'] ?? null,
                    // العمود النصّي مرآةُ العرض، و`role_id` هو المرجع الموثوق
                    // الذي تقرؤه شاشات إدارة المستخدمين (`primaryRole`) —
                    // وكان يخرج من التثبيت فارغاً، فيُعرض المدير «بلا دور»
                    'role' => self::ADMIN_ROLE,
                    'role_id' => $role->id,
                ]
            );

            $user->syncRoles([$role]);

            $granted = $this->grantOwnerGap($user, $role);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->note('admin', true, sprintf(
                'أُنشئ حساب المدير %s بدور %s (%d صلاحية%s)',
                $user->email,
                self::ADMIN_ROLE,
                $role->permissions()->count(),
                $granted > 0 ? sprintf('، و%d صلاحية مالكٍ مُسنَدة إليه مباشرةً', $granted) : ''
            ));

            return $user;
        } catch (Throwable $e) {
            $this->note('admin', false, 'تعذّر إنشاء حساب المدير: '.$e->getMessage());

            return null;
        }
    }

    /**
     * سدّ الفرق بين `owner` ودور حساب التثبيت — للمستخدم وحده.
     *
     * تُقرأ الصلاحيتان من قاعدة البيانات لا من ثوابت السيدرات: الفجوة هنا
     * هي ما يملكه `owner` فعلاً بعد أن انتهت البذور كلها، فلا تعتمد على
     * تتبّع `OWNER_ONLY` في أربعة ملفّاتٍ متفرّقة.
     *
     * @return int عدد الصلاحيات المُسنَدة
     */
    protected function grantOwnerGap(User $user, Role $role): int
    {
        $owner = Role::where('name', 'owner')->where('guard_name', 'web')->first();

        if (! $owner || $owner->is($role)) {
            return 0;
        }

        $missing = $owner->permissions->pluck('name')
            ->diff($role->permissions->pluck('name'))
            ->values();

        if ($missing->isEmpty()) {
            return 0;
        }

        $user->givePermissionTo($missing->all());

        return $missing->count();
    }

    /**
     * اللغة الافتراضية.
     *
     * تُفعَّل المختارة وتُجعل الافتراضية. ولا تُعطَّل الأخرى: تعطيلها يُخرجها
     * من `supportedLocales` كلّها (انظر AppServiceProvider) فتختفي من مبدّل
     * اللغة، وقد يريد صاحب النظام لغةً ثانية.
     */
    public function applyLanguage(string $code): void
    {
        try {
            if (! Schema::hasTable('system_languages')) {
                $this->note('language', false, 'جدول اللغات غير موجود — تُخُطِّي');

                return;
            }

            $language = SystemLanguage::where('code', $code)->first();

            // اللغة المختارة غير مزروعة — تُنشأ بدل تخطّيها، تماماً كما تفعل
            // `applyCurrency`. تخطّيها يعني أن يُنهي المستخدم التثبيت باختيارٍ
            // لم يُطبَّق ولا يعلم
            if (! $language) {
                $definition = collect(\Database\Seeders\LanguageSeeder::languages())
                    ->firstWhere('code', $code);

                $language = SystemLanguage::create(array_merge(
                    $definition ?? ['code' => $code, 'name' => $code, 'direction' => 'ltr'],
                    ['is_active' => true, 'is_default' => false]
                ));

                $this->note('language', true, "أُنشئت اللغة «{$code}» — لم تكن ضمن اللغات المزروعة");
            }

            SystemLanguage::query()->update(['is_default' => false]);

            // `refresh()` بعد التحديث الجماعي ضرورةٌ لا تجميل.
            //
            // النموذج حُمِّل قبل سطرٍ واحد وقد يكون `is_default` فيه `true`
            // أصلاً (اللغة العربية هي ما يزرعه `LanguageSeeder` افتراضياً).
            // فالتحديث الجماعي يُصفّرها في القاعدة والنموذج لا يعلم، ثم
            // `= true` لا يُعدّ تغييراً عند Eloquent فلا يكتبه `save()` —
            // وتخرج القاعدة **بلا لغة افتراضية إطلاقاً** بينما تقول الرسالة
            // إن الضبط تمّ. والعطل يظهر فقط حين يختار المستخدم القيمة
            // المزروعة أصلاً، أي في الحالة الأشيع.
            $language->refresh();

            // التفعيل قبل جعلها افتراضية — `LanguageService::setDefault` يرفض
            // جعل لغةٍ معطّلة افتراضيةً، ونظامٌ لغتُه الافتراضية معطّلة يسقط من
            // `supportedLocales` كلّه
            $language->is_active = true;
            $language->is_default = true;
            $language->save();

            // مفاتيح `LanguageRepository::clearCache()` الثلاثة زائد مفتاح
            // `AppServiceProvider`. النموذج يفرّغها عند الحفظ، والتفريغ هنا
            // احتياطٌ لو غُيّر ذلك السلوك
            foreach (SystemLanguage::CACHE_KEYS as $key) {
                Cache::forget($key);
            }

            // لغة التطبيق في الطلب الحالي — بها تُعرض بقية شاشات المعالج
            app()->setLocale($code);

            $this->note('language', true, "اللغة الافتراضية: {$language->name} ({$code})");
        } catch (Throwable $e) {
            $this->note('language', false, 'تعذّر ضبط اللغة: '.$e->getMessage());
        }
    }

    /** العملة الافتراضية — واحدة فقط، فتُنزع الصفة عن غيرها أولاً. */
    public function applyCurrency(string $code): void
    {
        try {
            if (! Schema::hasTable('system_currencies')) {
                $this->note('currency', false, 'جدول العملات غير موجود — تُخُطِّي');

                return;
            }

            $currency = SystemCurrency::where('code', $code)->first();

            // العملة المختارة غير مزروعة — تُنشأ بدل تخطّيها.
            //
            // قائمة الاختيار في المعالج تُعرض قبل تشغيل البذور، فقد تحوي رمزاً
            // لا يزرعه `SystemCurrenciesSeeder`. وتخطّيه يعني أن يُنهي المستخدم
            // التثبيت باختيارٍ لم يُطبَّق ولا يعلم، فيجد شاشة العملات على ريالٍ
            // سعودي لم يطلبه.
            if (! $currency) {
                $currency = SystemCurrency::create([
                    'code' => $code,
                    'name' => $code,
                    'symbol' => $code,
                    'decimal_places' => 2,
                    'exchange_rate' => 1,
                    'is_active' => true,
                    'is_default' => false,
                ]);

                $this->note('currency', true, "أُنشئت العملة «{$code}» — لم تكن ضمن العملات المزروعة");
            }

            // الافتراضية واحدة لا أكثر: تُنزع الصفة عن الجميع أولاً. النزع عن
            // `is_default = true` وحدها يترك صفوفاً بقيمة null تُقرأ افتراضيةً
            // في بعض الاستعلامات
            SystemCurrency::query()->update(['is_default' => false]);

            // `refresh()` لازم — العلّة نفسها الموصوفة في `applyLanguage`:
            // `SystemCurrenciesSeeder` يزرع الريال السعودي افتراضياً، فلو
            // اختاره المستخدم لكان النموذج محمَّلاً بـ `is_default = true`،
            // ويُصفّره التحديثُ الجماعي في القاعدة، ثم لا يرى Eloquent تغييراً
            // فلا يكتب شيئاً — وتخرج القاعدة بلا عملة افتراضية
            $currency->refresh();

            $currency->is_default = true;
            $currency->is_active = true;
            $currency->exchange_rate = 1;
            $currency->save();

            // مفاتيح `CurrencyRepository` — تُفرَّغ هنا لأن شاشة العملات تقرأ
            // منها، وبقاؤها يُظهر عملةً افتراضية غير التي اختيرت
            Cache::forget('active_system_currencies');
            Cache::forget('default_system_currency');

            $this->note('currency', true, "العملة الافتراضية في جدول العملات: {$currency->name} ({$code})");
        } catch (Throwable $e) {
            $this->note('currency', false, 'تعذّر ضبط العملة: '.$e->getMessage());
        }
    }

    /** بيانات المنشأة — تظهر على الفواتير وقوالب المستندات. */
    public function applyCompanyProfile(?User $admin, array $company, array $settings): void
    {
        try {
            if (! Schema::hasTable('business_data')) {
                $this->note('company', false, 'جدول بيانات المنشأة غير موجود — تُخُطِّي');

                return;
            }

            $payload = array_filter([
                'business_name' => $company['name'] ?? null,
                'business_email' => $company['email'] ?? null,
                'phone' => $company['phone'] ?? null,
                'mobile' => $company['phone'] ?? null,
                'city' => $company['city'] ?? null,
                'country' => $company['country'] ?? null,
                'tax_number' => $company['tax_number'] ?? null,
                'commercial_registration' => $company['commercial_registration'] ?? null,
                'website' => $company['website'] ?? null,
                'business_type' => $settings['business_type'] ?? 'products',
            ], fn ($v) => $v !== null && $v !== '');

            // الأعمدة تختلف بين تركيبٍ وآخر (هجرة enhance اختيارية الأعمدة)،
            // فيُصفّى ما لا وجود له بدل أن يرمي الاستعلام
            $payload = $this->onlyExistingColumns('business_data', $payload);

            BusinessData::updateOrCreate(
                ['user_id' => $admin?->id],
                $payload + ['user_id' => $admin?->id]
            );

            $this->note('company', true, 'حُفظت بيانات المنشأة: '.($company['name'] ?? 'بلا اسم'));
        } catch (Throwable $e) {
            $this->note('company', false, 'تعذّر حفظ بيانات المنشأة: '.$e->getMessage());
        }
    }

    /**
     * إعدادات الحساب — العملة والمنطقة الزمنية واللغة وصيغ التاريخ.
     * يقرؤها SittingInfoController وشاشات كثيرة، وغيابها يترك الواجهة
     * على قيمٍ فارغة.
     */
    public function applyAccountSettings(?User $admin, array $settings): void
    {
        try {
            if (! Schema::hasTable('account_settings')) {
                $this->note('account_settings', false, 'جدول إعدادات الحساب غير موجود — تُخُطِّي');

                return;
            }

            $payload = $this->onlyExistingColumns('account_settings', [
                'currency' => $settings['currency'] ?? 'SAR',
                'timezone' => $settings['timezone'] ?? 'Asia/Riyadh',
                'language' => $settings['language'] ?? 'ar',
                'business_type' => $settings['business_type'] ?? 'products',
                'date_format' => $settings['date_format'] ?? 'Y-m-d',
                'time_format' => $settings['time_format'] ?? 'H:i',
                'negative_currency_formats' => '-1',
                'printing_method' => 'a4',
            ]);

            AccountSetting::updateOrCreate(
                ['user_id' => $admin?->id],
                $payload + ['user_id' => $admin?->id]
            );

            $this->note('account_settings', true, 'إعدادات الحساب: عملة '.($settings['currency'] ?? 'SAR').'، توقيت '.($settings['timezone'] ?? 'Asia/Riyadh'));
        } catch (Throwable $e) {
            $this->note('account_settings', false, 'تعذّر حفظ إعدادات الحساب: '.$e->getMessage());
        }
    }

    /**
     * ضريبة القيمة المضافة.
     *
     * تُخزَّن كنسبة عشرية (0.15) لا مئوية (15) — هكذا يقرؤها النظام، ووضع 15
     * مكانها يضرب كل فاتورة في خمسة عشر.
     */
    public function applyTaxSettings(array $settings): void
    {
        try {
            if (! Schema::hasTable('tax_sittings')) {
                $this->note('tax', false, 'جدول الضرائب غير موجود — تُخُطِّي');

                return;
            }

            $percent = (float) ($settings['tax_percent'] ?? 15);
            $rate = $percent > 1 ? $percent / 100 : $percent;
            $type = ($settings['tax_type'] ?? 'excluded') === 'included' ? 'included' : 'excluded';

            $existing = DB::table('tax_sittings')->first();

            $row = [
                'name' => $settings['tax_name'] ?? 'ضريبة القيمة المضافة',
                'tax' => $rate,
                'type' => $type,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('tax_sittings')->where('id', $existing->id)->update($row);
            } else {
                DB::table('tax_sittings')->insert($row + ['created_at' => now()]);
            }

            $this->note('tax', true, 'الضريبة: '.($rate * 100).'% ('.($type === 'included' ? 'شاملة' : 'غير شاملة').')');
        } catch (Throwable $e) {
            $this->note('tax', false, 'تعذّر حفظ إعدادات الضريبة: '.$e->getMessage());
        }
    }

    /** المظهر — اسم التطبيق الظاهر في الشريط وشاشة الدخول. */
    /**
     * المظهر — الثيم المختار واسم التطبيق.
     *
     * الثيم يُطبَّق من `ColorSitting::PRESETS` نفسها التي تعرضها شاشة المظهر،
     * لا من قيمٍ مكتوبة هنا: مصدرٌ واحد يعني أن ما يختاره المستخدم في التثبيت
     * هو ما يجده لاحقاً محدَّداً في تلك الشاشة، لا لوحةً مشابهة بلا اسم.
     *
     * وصفُّ `color_sittings` يُنشأ دائماً وإن لم يُختر ثيم — النظام يقرأ منه
     * على كل عرض، وغيابه يعني الاعتماد على الافتراضي الضمني بلا سجلّ يُعدَّل.
     */
    public function applyAppearance(array $company, array $settings = []): void
    {
        try {
            if (! Schema::hasTable('color_sittings')) {
                $this->note('appearance', false, 'جدول المظهر غير موجود — تُخُطِّي');

                return;
            }

            $theme = ColorSitting::first() ?: new ColorSitting(ColorSitting::DEFAULTS);

            $presetKey = $settings['theme'] ?? 'default';
            $preset = ColorSitting::PRESETS[$presetKey] ?? ColorSitting::PRESETS['default'];

            // `label` و`group` وصفٌ للعرض لا أعمدةٌ في الجدول
            foreach (array_diff_key($preset, array_flip(['label', 'group'])) as $column => $value) {
                $theme->{$column} = $value;
            }

            if (! empty($company['name'])) {
                $theme->app_title = $company['name'];
            }

            $theme->save();

            ThemeSettings::forget();

            $label = ColorSitting::PRESETS[$presetKey]['label'] ?? $presetKey;

            $this->note('appearance', true, "الثيم: {$label} ({$preset['primary_color']}) — واسم التطبيق: ".($company['name'] ?? '—'));
        } catch (Throwable $e) {
            $this->note('appearance', false, 'تعذّر ضبط المظهر: '.$e->getMessage());
        }
    }

    /** صفّ الإعدادات العامة للمخزون — الشاشات تفترض وجوده وتنهار على null. */
    public function applyGeneralSettings(): void
    {
        try {
            if (! Schema::hasTable('general_settings')) {
                return;
            }

            if (! DB::table('general_settings')->exists()) {
                DB::table('general_settings')->insert([
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->note('general_settings', true, 'أُنشئ صفّ الإعدادات العامة');
        } catch (Throwable $e) {
            $this->note('general_settings', false, 'تعذّر إنشاء الإعدادات العامة: '.$e->getMessage());
        }
    }

    /**
     * إعدادات البريد — اختيارية.
     *
     * تُكتب على `smtp_settings` لا على `.env`: النظام يقرأ منها عبر
     * MailerConfigurator، وكتابةُ `.env` وحدها تُهمَل.
     */
    public function applyMailSettings(?User $admin, array $mail): void
    {
        if (empty($mail['host']) || ! $admin) {
            return;
        }

        try {
            if (! Schema::hasTable('smtp_settings')) {
                $this->note('mail', false, 'جدول البريد غير موجود — تُخُطِّي');

                return;
            }

            $row = [
                'user_id' => $admin->id,
                'use_smtp' => true,
                'is_active' => true,
                'sender_name' => $mail['sender_name'] ?? null,
                'email' => $mail['from'] ?? null,
                'smtp_username' => $mail['username'] ?? null,
                'password' => $mail['password'] ?? null,
                'smtp_host' => $mail['host'],
                'smtp_port' => (int) ($mail['port'] ?? 587),
                'smtp_encryption' => $mail['encryption'] ?? 'tls',
                'updated_at' => now(),
            ];

            $existing = DB::table('smtp_settings')->where('user_id', $admin->id)->first();

            if ($existing) {
                DB::table('smtp_settings')->where('id', $existing->id)->update($row);
            } else {
                DB::table('smtp_settings')->insert($row + ['created_at' => now()]);
            }

            $this->note('mail', true, 'حُفظت إعدادات البريد ('.$mail['host'].')');
        } catch (Throwable $e) {
            $this->note('mail', false, 'تعذّر حفظ إعدادات البريد: '.$e->getMessage());
        }
    }

    /**
     * تفريغ الكاش الذي يحمل حالةً ما قبل التثبيت.
     *
     * لولاه لبقيت `supportedLocales` محسوبةً على جدولٍ فارغ ساعةً كاملة،
     * وحالاتُ التطبيقات على قيمها القديمة — فيبدو التثبيت كأنه لم يُطبَّق.
     */
    public function flushCaches(): void
    {
        try {
            foreach (SystemLanguage::CACHE_KEYS as $key) {
                Cache::forget($key);
            }

            Cache::forget('application_settings.map');
            Cache::forget('system_theme_settings');

            foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
                try {
                    \Illuminate\Support\Facades\Artisan::call($command);
                } catch (Throwable $e) {
                    // مسح كاشٍ متعثّر لا يُفشل التثبيت
                }
            }

            $this->note('cache', true, 'فُرِّغ الكاش وأُعيد بناء الإعدادات');
        } catch (Throwable $e) {
            $this->note('cache', false, 'تعذّر تفريغ الكاش: '.$e->getMessage());
        }
    }

    /**
     * تصفية مصفوفة على أعمدة الجدول الموجودة فعلاً.
     * الجداول هنا مرّت بهجرات «enhance» شرطية، فما يوجد في تركيبٍ قد يغيب في آخر.
     */
    protected function onlyExistingColumns(string $table, array $payload): array
    {
        return array_filter(
            $payload,
            fn ($column) => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function note(string $key, bool $ok, string $message): void
    {
        $this->applied[] = ['key' => $key, 'ok' => $ok, 'message' => $message];
    }
}
