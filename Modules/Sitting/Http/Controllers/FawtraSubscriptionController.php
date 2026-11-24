<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use App\Models\FawtraAlert;
use App\Models\FawtraSubscription;
use App\Services\ActivityLogService;
use App\Services\ErrorLogService;
use App\Services\EventLogService;
use App\Services\Fawtra\FawtraCredentials;
use App\Services\Fawtra\SubscriptionSync;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * الاشتراك المرتبط بلوحة تحكّم فوترة.
 *
 * **لا يُخلط بشاشة «اشتراك المنشأة»** المجاورة: تلك سجلٌّ يدوي يكتبه المسؤول
 * لتذكّر مدّة اشتراكه ومبلغه، وهذه مرآةٌ لما تقوله لوحة فوترة فعلاً —
 * الباقة وصلاحياتها وحدود الاستخدام والفواتير والإضافات المشتراة.
 *
 * وكل ما يُعرض هنا مقروءٌ من آخر لقطة محفوظة لا من نداء حيّ: الشاشة تُفتح
 * كثيراً والواجهة محدودة المعدّل، والوثيقة توصي بالعمل من اللقطة. زرّ
 * «زامن الآن» هو المخرج حين يريد المستخدم تحديثاً فورياً.
 */
class FawtraSubscriptionController extends Controller
{
    public function __construct(
        protected FawtraCredentials $credentials,
        protected SubscriptionSync $sync,
    ) {
        $this->middleware('permission:settings.subscription.view')->only('index');
        $this->middleware('permission:settings.subscription.manage')
            ->only(['verify', 'link', 'unlink', 'syncNow', 'dismissAlert']);
    }

    public function index()
    {
        $record = FawtraSubscription::current();

        // **تحديثٌ فوريّ عند فتح الشاشة إن حان موعده.** الاعتماد على المهمة
        // الدورية وحدها يجعل المستخدم يرى أرقاماً عمرها ربع ساعة ويظنّ أن
        // الربط لا يعمل. و`isDueForSync()` تمنع نداءً مع كل تحديث للصفحة —
        // فلا يُستنفَد حدّ الطلبات، وتُحترم فترة التراجع بعد الإخفاق
        if ($this->credentials->configured() && (! $record || $record->isDueForSync())) {
            $this->sync->run();
            $record = FawtraSubscription::current();
        }

        $plan = $record?->plan() ?? [];
        $permissions = $record?->planPermissions() ?? [];
        $permissionLabels = $record?->permissionLabels() ?? [];
        $usage = $record?->usage() ?? [];

        $permissionSections = $this->buildPermissionSections($plan, $permissions, $permissionLabels);
        $usageSections = $this->buildUsageSections($usage);

        return view('sitting::fawtra.index', [
            'linked' => $this->credentials->configured(),
            'credentials' => $this->credentials->public(),
            'record' => $record,
            'account' => $record?->account() ?? [],
            'plan' => $plan,
            'permissions' => $permissions,
            'permissionLabels' => $permissionLabels,
            'limits' => $record?->limits() ?? [],
            'usage' => $usage,
            'usageSections' => $usageSections,
            'permissionSections' => $permissionSections,
            'addons' => $record?->addons() ?? [],
            'billing' => $record?->billing() ?? [],
            'alerts' => $this->alerts(),
            'suggestedDomain' => request()->getHost(),

            // آخر محاولات التحقّق معروضةٌ في الشاشة نفسها: التسجيل الذي لا
            // يُرى لا يُستعمل، والسؤال «لماذا رُفض مفتاحي؟» يُجاب هنا لا في
            // شاشة سجلّات أخرى
            'attempts' => $this->recentAttempts(),
            'syncInterval' => (int) config('fawtra.sync.interval', 900) / 60,
        ]);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, bool>  $permissions
     * @param  array<string, string>  $permissionLabels
     * @return array<int, array<string, mixed>>
     */
    protected function buildPermissionSections(array $plan, array $permissions, array $permissionLabels): array
    {
        $sections = $plan['permissions_by_section'] ?? [];

        if (is_array($sections) && $sections !== []) {
            return array_values(array_map(function (array $section): array {
                $items = $section['permissions'] ?? [];
                $enabled = count(array_filter($items, fn ($item) => (bool) ($item['enabled'] ?? false)));

                return [
                    'section' => $section['section'] ?? 'other',
                    'section_label' => $section['section_label'] ?? ($section['section'] ?? 'other'),
                    'icon' => $section['icon'] ?? null,
                    'permissions' => $items,
                    'total' => count($items),
                    'enabled' => $enabled,
                ];
            }, $sections));
        }

        $map = $plan['permission_sections'] ?? [];
        $bucket = [];

        foreach ($permissions as $key => $allowed) {
            $sectionKey = (string) ($map[$key] ?? 'other');
            if (! isset($bucket[$sectionKey])) {
                $bucket[$sectionKey] = [
                    'section' => $sectionKey,
                    'section_label' => $sectionKey,
                    'icon' => null,
                    'permissions' => [],
                ];
            }

            $bucket[$sectionKey]['permissions'][] = [
                'key' => $key,
                'label' => $permissionLabels[$key] ?? $key,
                'enabled' => (bool) $allowed,
            ];
        }

        return array_values(array_map(function (array $section): array {
            $enabled = count(array_filter($section['permissions'], fn ($item) => (bool) ($item['enabled'] ?? false)));
            $section['total'] = count($section['permissions']);
            $section['enabled'] = $enabled;

            return $section;
        }, $bucket));
    }

    /**
     * @param  array<int, array<string, mixed>>  $usage
     * @return array<int, array<string, mixed>>
     */
    protected function buildUsageSections(array $usage): array
    {
        $grouped = [];

        foreach ($usage as $item) {
            $key = (string) ($item['section'] ?? 'other');

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'section' => $key,
                    'section_label' => $item['section_label'] ?? $key,
                    'items' => [],
                ];
            }

            $grouped[$key]['items'][] = $item;
        }

        return array_values($grouped);
    }

    /**
     * فحص المفتاح حيّاً بلا حفظ — يُستدعى من زرّ «تحقّق».
     *
     * كان الزرّ الوحيد يحفظ ويربط معاً، فإن رُفض المفتاح عاد المستخدم إلى
     * الصفحة نفسها. وهذه الشاشة لم تكن تعرض رسائل الجلسة أصلاً، فبدا الزرّ
     * كأنه لا يفعل شيئاً. الآن: جوابٌ فوريّ تحت النموذج، ورسائل تُعرض.
     */
    public function verify(Request $request)
    {
        $data = $request->validate(
            FawtraCredentials::rules(),
            FawtraCredentials::ruleMessages(),
            FawtraCredentials::attributes()
        );

        $result = $this->sync->verify($data);

        $this->record(
            $result['ok'] ? 'fawtra.verify.ok' : 'fawtra.verify.failed',
            ($result['ok'] ? 'نجح التحقّق من مفتاح فوترة' : 'أخفق التحقّق من مفتاح فوترة')
                .' — '.($result['message'] ?? ''),
            $data['client_id'],
            ! $result['ok']
        );

        return response()->json($result);
    }

    /**
     * آخر ما جرى من محاولات الربط والتحقّق — من سجلّ الأحداث نفسه.
     *
     * @return Collection<int, object>
     */
    protected function recentAttempts()
    {
        try {
            return EventLog::query()
                ->where('action', 'like', 'fawtra.%')
                ->latest('id')
                ->limit(6)
                ->get(['action', 'description', 'created_at']);
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * تسجيل المحاولة في سجلّات النظام الثلاثة.
     *
     * **لماذا تُسجَّل محاولةٌ لم تُغيّر شيئاً؟** لأن أشيع سؤالٍ بعد أسبوع هو
     * «لماذا توقّف الربط؟» — والجواب في محاولةٍ رُفضت ولم يبق لها أثر. ولأن
     * تكرار الرفض من عنوانٍ واحد إشارةُ عبثٍ بالمفاتيح تستحقّ أن تُرى.
     *
     * والسرّ لا يُكتب في أي سجلّ: يُذكر معرّف العميل وحده.
     */
    protected function record(string $action, string $message, ?string $clientId, bool $isError): void
    {
        try {
            app(EventLogService::class)->log($action, $message);

            app(ActivityLogService::class)->log(
                $action,
                '['.$action.'] '.$message.($clientId ? ' — العميل: '.$clientId : ''),
                null,
                null,
                'setting'
            );

            if ($isError) {
                app(ErrorLogService::class)->log($message, request()->fullUrl(), auth()->id());
            }
        } catch (\Throwable) {
            // السجلّ خدمةٌ مساعدة: تعذّرُ الكتابة فيه لا يُفشل ما نجح
        }
    }

    /** ربط النظام — أو تغيير مفتاح قائم. */
    public function link(Request $request)
    {
        $data = $request->validate(
            FawtraCredentials::rules(),
            FawtraCredentials::ruleMessages(),
            FawtraCredentials::attributes()
        );

        // الفحص قبل الحفظ: مفتاحٌ مرفوض يُحفظ فيبدو النظام مربوطاً وهو ليس
        // كذلك، ثم تتعثّر كل مزامنة دون أن يعرف أحد السبب
        $check = $this->sync->verify($data);

        if (! $check['ok']) {
            $this->record('fawtra.link.rejected', 'رُفض مفتاح فوترة عند الربط — '.$check['message'], $data['client_id'], true);

            return back()->withInput()->with('error', $check['message']);
        }

        $this->credentials->store($data);

        // الكتابة في `.env` قد تُرفض بصلاحيات الملف — وحينها يبدو الربط
        // ناجحاً ثم لا يعمل شيء. تُفحص النتيجة بدل الظنّ
        if (! $this->credentials->configured()) {
            return back()->withInput()->with(
                'error',
                'تعذّر حفظ بيانات الربط في ملف .env — تأكّد أن الملف قابل للكتابة على الخادم.'
            );
        }

        $result = $this->sync->run(force: true);

        $this->record(
            $result['ok'] ? 'fawtra.link.ok' : 'fawtra.link.sync_failed',
            $result['ok']
                ? 'رُبط النظام بلوحة فوترة وجُلبت بيانات الاشتراك'
                : 'رُبط النظام لكن تعثّرت أول مزامنة — '.$result['message'],
            $data['client_id'],
            ! $result['ok']
        );

        return redirect()->route('Fawtra.index')->with(
            $result['ok'] ? 'success' : 'warning',
            $check['message'].($result['ok'] ? ' وجُلبت بيانات الاشتراك.' : ' لكن تعثّرت المزامنة: '.$result['message'])
        );
    }

    /**
     * قطع الربط.
     *
     * تُمحى المفاتيح وتبقى اللقطة: حذفُها يمحو تاريخ ما كان مشتركاً فيه بلا
     * فائدة، وإعادةُ الربط تستبدلها بأحدث منها فوراً.
     */
    public function unlink()
    {
        $this->credentials->forget();

        return redirect()->route('Fawtra.index')
            ->with('success', 'قُطع الربط بلوحة فوترة. البيانات المحفوظة باقية للاطّلاع.');
    }

    /** مزامنة فورية — يتجاهل رمز المزامنة وفترة التراجع. */
    public function syncNow()
    {
        $result = $this->sync->run(force: true);

        // لا نموذج مُدخَل هنا خلافاً لـ`link()`: هذه إعادة مزامنة لربطٍ قائم
        // فعلاً، فمعرّف العميل يُقرأ من الإعداد المحفوظ لا من طلبٍ لا يحمله
        $this->record(
            $result['ok'] ? 'fawtra.sync.ok' : 'fawtra.sync.failed',
            $result['ok']
                ? 'مزامنة يدوية ناجحة مع لوحة فوترة'
                : 'تعثّرت المزامنة اليدوية — '.$result['message'],
            config('fawtra.client_id'),
            ! $result['ok']
        );

        return redirect()->route('Fawtra.index')->with(
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }

    /** إخفاء تنبيه من الشاشة — لا يحذفه ولا يُلغي سببه. */
    public function dismissAlert(FawtraAlert $alert)
    {
        $alert->update(['dismissed_at' => now()]);

        return back()->with('success', 'أُخفي التنبيه.');
    }

    /**
     * التنبيهات المعروضة — غير المُخفاة، الأخطر أولاً.
     */
    protected function alerts()
    {
        try {
            return FawtraAlert::open()
                ->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')")
                ->orderByDesc('remote_created_at')
                ->limit(20)
                ->get();
        } catch (\Throwable) {
            // الجدول غير مُهجَّر بعد
            return collect();
        }
    }
}
