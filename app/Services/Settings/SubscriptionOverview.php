<?php

namespace App\Services\Settings;

use App\Models\FawtraSubscription;
use App\Models\SystemSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ما تعرضه شاشة الاشتراك: المدّة، وبيانات المنشأة، وحجم الاستعمال.
 *
 * لا أرقام مالية هنا عمداً: المبيعات والمقبوضات والمستحقّ لها شاشات
 * المحاسبة، وتكرارها في شاشة اشتراك يعني رقمين يختلفان لسبب لا يفهمه
 * أحد — وشاشة الاشتراك تُجيب عن «متى ينتهي؟» لا عن «كم ربحت؟».
 *
 * عدّ الصفوف يمسح جداول كبيرة (أربعة آلاف فاتورة وثلاثة آلاف عميل)،
 * والشاشة تُفتح للاطّلاع لا للعمل — فتُحفظ نتائجه في الذاكرة المؤقتة
 * لعشر دقائق.
 */
class SubscriptionOverview
{
    private const CACHE_TTL = 600;

    /**
     * الاشتراك الجاري، أو null إن لم يكن هناك اشتراك.
     *
     * المصدر لقطة لوحة فوترة وحدها، ويُبنى منها كائنٌ **غير محفوظ** — لا
     * نسخة ثانية من الحقيقة تتباعد عن الأولى مع كل تجديد.
     */
    public function current(): ?SystemSubscription
    {
        // **لقطة فوترة أو لا شيء.**
        //
        // كان هنا رجوعٌ إلى `system_subscriptions` — سجلٍّ يكتبه المسؤول
        // بيده. وهو أسوأ من الفراغ: يعرض لمن لا اشتراك له باقةً ومدّةً
        // وسعراً كتبها بنفسه فيظنّها اشتراكاً قائماً. والاشتراك لا يُسجَّل
        // ولا يُفعَّل من داخل هذا النظام بحال — كلّه في لوحة فوترة.
        return $this->fromFawtra();
    }

    /**
     * بناء كائن اشتراك من لقطة فوترة — بلا كتابة في قاعدة البيانات.
     *
     * `newInstance` مع `exists = false`: الشاشة تقرؤه كأي صفّ، ولا يظهر في
     * السجلّ التاريخي ولا يُحفَظ بالخطأ.
     */
    protected function fromFawtra(): ?SystemSubscription
    {
        try {
            $record = FawtraSubscription::current();

            if (! $record || ! $record->snapshot) {
                return null;
            }

            $plan = $record->plan();
            $billing = $record->billing();
            $subscription = $record->snapshot['subscription'] ?? [];

            $model = new SystemSubscription([
                'plan_name' => $record->plan_name ?: ($plan['name'] ?? 'باقة فوترة'),
                'plan_code' => (string) ($plan['tier'] ?? $plan['type'] ?? ''),
                'status' => $this->mapStatus($record),
                'started_at' => $subscription['start_date'] ?? null,
                'expires_at' => $record->end_date,
                'price' => (float) ($billing['last_payment']['total_amount'] ?? 0),
                'currency' => $billing['last_payment']['currency'] ?? config('app.currency', 'SAR'),
                'billing_cycle' => $this->mapCycle($subscription['billing_cycle'] ?? null),
                'max_users' => $this->limitOf($record, 'sales_users'),
                'notes' => 'مقروء من لوحة فوترة — يُحدَّث تلقائياً كل '
                    .((int) config('fawtra.sync.interval', 900) / 60).' دقيقة.',
            ]);

            $model->exists = false;

            return $model;
        } catch (\Throwable) {
            // الجداول غير مُهجَّرة أو اللقطة تالفة — لا اشتراك، ولا اختلاق
            return null;
        }
    }

    /** حالة فوترة بمفردات هذه الشاشة. */
    protected function mapStatus(FawtraSubscription $record): string
    {
        return match (true) {
            (bool) $record->is_expired => SystemSubscription::STATUS_EXPIRED,
            $record->is_trial && $record->is_active => SystemSubscription::STATUS_TRIAL,
            (bool) $record->is_active => SystemSubscription::STATUS_ACTIVE,
            $record->status === 'suspended' => SystemSubscription::STATUS_SUSPENDED,
            default => SystemSubscription::STATUS_CANCELLED,
        };
    }

    protected function mapCycle(?string $cycle): string
    {
        return array_key_exists((string) $cycle, SystemSubscription::CYCLES) ? (string) $cycle : 'monthly';
    }

    /** حدّ ميزة من اللقطة — `-1` بلا حدّ تُترجَم null فتعرضها الشاشة «بلا حدّ». */
    protected function limitOf(FawtraSubscription $record, string $feature): ?int
    {
        foreach ($record->usage() as $item) {
            if (($item['key'] ?? null) === $feature) {
                $allowance = (int) ($item['allowance'] ?? -1);

                return $allowance < 0 ? null : $allowance;
            }
        }

        return null;
    }

    /**
     * بيانات المنشأة — مصدرها شاشة «معلومات الحساب».
     *
     * كانت تُقرأ من `branches` (الفرع الموسوم `is_main`)، وهذا خطأ: ما
     * يكتبه المسؤول في شاشة معلومات الحساب يُحفَظ في صفّ `clients` المرتبط
     * بمستخدمه — الاسم التجاري والسجلّ والرقم الضريبي والعنوان. فكانت
     * الشاشتان تعرضان بيانَين مختلفَين لمنشأة واحدة.
     *
     * الفرع الرئيسي يبقى احتياطاً لكل حقل لم يُملأ بعد، فلا تظهر الصفحة
     * فارغة قبل أن يفتح المسؤول شاشة الحساب أول مرّة.
     *
     * @return array<string, mixed>
     */
    public function establishment(): array
    {
        $userId = auth()->id();

        return Cache::remember('subscription.establishment.'.($userId ?: 'guest'), self::CACHE_TTL, function () use ($userId) {
            $account = null;
            $branch = null;

            try {
                $account = DB::table('clients')->where('user_id', $userId)->first();
            } catch (\Throwable) {
                // لا سجلّ حساب — يتكفّل الاحتياط أدناه
            }

            try {
                $branch = DB::table('branches')->where('is_main', 1)->first()
                    ?? DB::table('branches')->orderBy('id')->first();
            } catch (\Throwable) {
                // ولا فرع — تُعرَض الحقول فارغة
            }

            // العنوان من سجلّ الحساب إن كُتب فيه شيء، وإلا من الفرع
            $address = trim(implode('، ', array_filter([
                $account->street1 ?? null,
                $account->city ?? null,
                $account->region ?? null,
                $account->country ?? null,
            ]))) ?: trim(implode('، ', array_filter([
                $branch->address1 ?? null,
                $branch->city ?? null,
                $branch->region ?? null,
                $branch->country ?? null,
            ]))) ?: null;

            return [
                'name' => $account->trade_name ?? ($branch->name ?? null),
                'code' => $account->code ?? ($branch->code ?? null),
                'phone' => $account->phone ?? ($account->mobile ?? ($branch->phone ?? null)),
                'email' => $account->email ?? null,
                'address' => $address,
                'tax_number' => $account->tax_number ?? null,
                'commercial_registration' => $account->commercial_registration ?? null,
                // العملة من سجلّ الحساب أولاً — هي التي يختارها المسؤول في
                // تبويب «التفضيلات»
                'currency' => $account->currency ?? $this->accountCurrency(),
                'timezone' => $this->accountTimezone(),
                // هل مصدر البيانات سجلّ الحساب فعلاً؟ الشاشة تُنبّه إن لم يكن
                'from_account' => $account !== null,
            ];
        });
    }

    /**
     * حجم ما في النظام، ومقارنته بحدود الباقة.
     *
     * @return array<int, array{label:string,icon:string,value:int,limit:?int,percent:?int}>
     */
    public function usage(?SystemSubscription $subscription = null): array
    {
        // حدود الاشتراك الحقيقية تسبق العدّ المحلّي: ما تمنحه الباقة وما
        // اشتُري من إضافات معروفٌ عند فوترة بالرقم، بينما «عدد المستخدمين
        // في الجدول» تقديرٌ محلّي لا يعرف حدّاً ولا إضافة
        if ($fromDashboard = $this->fawtraUsage()) {
            return $fromDashboard;
        }

        // بلا اشتراك لا أرقام إطلاقاً: «٣ مستخدمون» بلا حدٍّ يقابله رقمٌ
        // بلا معنى في شاشةِ اشتراكٍ غير موجود، وأسوأ منه أنه يوهم بوجوده
        return [];

        $counts = Cache::remember('subscription.usage', self::CACHE_TTL, fn () => [
            'users' => $this->countOf('users'),
            'branches' => $this->countOf('branches'),
            'clients' => $this->countOf('clients'),
            'employees' => $this->countOf('employees'),
            'invoices' => $this->countOf('invoices'),
            'products' => $this->countOf('products'),
        ]);

        $rows = [
            ['key' => 'users', 'label' => __('settings.usage.users'), 'icon' => 'user', 'limit' => $subscription?->max_users],
            ['key' => 'branches', 'label' => __('settings.usage.branches'), 'icon' => 'git-branch', 'limit' => $subscription?->max_branches],
            ['key' => 'clients', 'label' => __('settings.usage.clients'), 'icon' => 'users', 'limit' => $subscription?->max_clients],
            ['key' => 'employees', 'label' => __('settings.usage.employees'), 'icon' => 'user-check', 'limit' => null],
            ['key' => 'invoices', 'label' => __('settings.usage.invoices'), 'icon' => 'file-text', 'limit' => null],
            ['key' => 'products', 'label' => __('settings.usage.products'), 'icon' => 'package', 'limit' => null],
        ];

        return array_map(function (array $row) use ($counts) {
            $value = $counts[$row['key']] ?? 0;
            $limit = $row['limit'];

            return [
                'label' => $row['label'],
                'icon' => $row['icon'],
                'value' => $value,
                'limit' => $limit,
                'percent' => $limit ? min(100, (int) round($value / $limit * 100)) : null,
            ];
        }, $rows);
    }

    /**
     * حدود الاستخدام كما تقولها لوحة فوترة — لا عدٌّ محلّي ولا رقمٌ ثابت.
     *
     * كل صفٍّ يحمل ما استُهلك وما تسمح به الباقة **زائدَ الإضافات
     * المشتراة**، ودوريّته (يومي/شهري/سقف متزامن)، وكم إضافةً رفعت حدّه.
     * و`-1` عند فوترة تعني «بلا حدّ» فتُترجَم `null` هنا لتعرضها الشاشة
     * «غير محدود» لا رقماً سالباً.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function fawtraUsage(): ?array
    {
        try {
            $record = FawtraSubscription::current();

            if (! $record || $record->usage() === []) {
                return null;
            }

            return array_map(function (array $item) {
                $allowance = (int) ($item['allowance'] ?? -1);
                $used = (int) ($item['used'] ?? 0);
                $unlimited = ($item['unlimited'] ?? false) || $allowance < 0;

                return [
                    'key' => $item['key'] ?? '',
                    'label' => $item['label'] ?? ($item['key'] ?? ''),
                    'icon' => 'award',
                    'value' => $used,
                    'limit' => $unlimited ? null : $allowance,
                    'percent' => $unlimited || $allowance <= 0
                        ? null
                        : min(100, (int) round($used / $allowance * 100)),
                    'period' => $item['period'] ?? null,
                    'addon_quantity' => (int) ($item['addon_quantity'] ?? 0),
                    'unlimited' => $unlimited,
                    'exhausted' => (bool) ($item['exhausted'] ?? false),
                ];
            }, $record->usage());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * ما تفتحه الباقة من شاشات — من الاشتراك لا من قائمةٍ مكتوبة في الشيفرة.
     *
     * @return array<string, bool>
     */
    public function planPermissions(): array
    {
        try {
            return FawtraSubscription::current()?->planPermissions() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** الإضافات المشتراة فوق الباقة. */
    public function addons(): array
    {
        try {
            return FawtraSubscription::current()?->addons() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * وجهة من لا اشتراك له: صفحة الأسعار في لوحة فوترة.
     *
     * من اللقطة إن وُجدت — ومن لا اشتراك له لا لقطة عنده، فيُبنى من عنوان
     * اللوحة والمسار المضبوط. ولا تسجيل ولا تفعيل داخل هذا النظام بحال.
     */
    public function pricingUrl(): string
    {
        try {
            if ($url = FawtraSubscription::current()?->links()['pricing'] ?? null) {
                return $url;
            }
        } catch (\Throwable) {
            // لا لقطة — يُبنى العنوان أدناه
        }

        return rtrim((string) config('fawtra.base_url'), '/')
            .'/'.ltrim((string) config('fawtra.pricing_path', '/prices'), '/');
    }

    public function flush(): void
    {
        Cache::forget('subscription.usage');
        // مفتاح بيانات المنشأة يخصّ كل مستخدم على حدة
        Cache::forget('subscription.establishment.'.(auth()->id() ?: 'guest'));
    }

    // ==================== الداخل ====================

    /** كل استعلام محاط: جدول ناقص في تركيب آخر يجب ألّا يُسقط الشاشة. */
    protected function countOf(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function accountCurrency(): ?string
    {
        try {
            return DB::table('account_settings')->value('currency');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function accountTimezone(): ?string
    {
        try {
            return DB::table('account_settings')->value('timezone');
        } catch (\Throwable) {
            return null;
        }
    }
}
