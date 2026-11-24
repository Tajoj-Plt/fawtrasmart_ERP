<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FawtraSubscription;
use App\Models\SystemSubscription;
use App\Services\Fawtra\FawtraCredentials;
use App\Services\Settings\SubscriptionOverview;

/**
 * اشتراك المنشأة في النظام.
 *
 * لا يُخلط بشاشة اشتراكات العملاء: تلك تدير ما تبيعه المنشأة لعملائها،
 * وهذه تعرض ما تشتريه المنشأة من النظام — متى ينتهي، وبكم، وما حجم ما
 * بداخل النظام مقابل حدود الباقة.
 */
class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionOverview $overview)
    {
        $this->middleware('permission:settings.subscription.view')->only('index');
        // لا `store` ولا `renew`: لا كتابة اشتراك في هذا النظام أصلاً
        $this->middleware('permission:settings.subscription.manage')->only(['sync']);
    }

    public function index()
    {
        $subscription = $this->overview->current();
        $fawtra = FawtraSubscription::current();
        $plan = $fawtra?->plan() ?? [];
        $usage = $this->overview->usage($subscription);
        $usageSections = collect($usage)
            ->groupBy(fn (array $item) => $item['section'] ?? 'other')
            ->map(function ($items, $section) {
                $first = $items->first();

                return [
                    'section' => $section,
                    'section_label' => $first['section_label'] ?? $section,
                    'items' => $items->values()->all(),
                ];
            })
            ->values()
            ->all();

        $permissionSections = $plan['permissions_by_section'] ?? [];

        return view('sitting::subscription.index', [
            'subscription' => $subscription,
            // السجلّ التاريخي والفواتير المحلّية أُفرغا: لا يُكتب في
            // `system_subscriptions` شيء بعد اليوم، وعرضُ جدولٍ يملؤه
            // المستخدم بيده في شاشةِ اشتراكٍ مصدرُه اللوحة تضليل
            'history' => collect(),
            'establishment' => $this->overview->establishment(),
            // المشترك كما تعرفه لوحة فوترة: مَن سجّل الاشتراك وبأي بريد وجوال
            // — من اللقطة لا من جداولنا، فهو الحساب الذي تُرسَل إليه فواتير
            // الاشتراك وتنبيهاته
            'subscriber' => $fawtra?->account() ?? [],
            'usage' => $usage,
            'usageSections' => $usageSections,
            'invoices' => collect(),
            'statuses' => SystemSubscription::statuses(),
            'cycles' => SystemSubscription::cycles(),

            // صلاحيات الباقة وإضافاتها وأسماؤها — كلّها من لقطة الاشتراك،
            // فترقيةُ الباقة عند فوترة تظهر هنا بلا تعديل شيفرة
            'planPermissions' => $this->overview->planPermissions(),
            'planAddons' => $this->overview->addons(),
            'permissionLabels' => $fawtra?->permissionLabels() ?? [],
            'permissionSections' => is_array($permissionSections) ? $permissionSections : [],

            // الفواتير والروابط من اللوحة: التجديد والترقية والشراء لا تقع
            // في هذا النظام، والفاتورة مستند صادر عنهم
            'dashboardInvoices' => $fawtra?->invoices() ?? [],
            'dashboardLinks' => $fawtra?->links() ?? [],

            // وجهة من لا اشتراك له — صفحة الأسعار في اللوحة
            'pricingUrl' => $this->overview->pricingUrl(),

            // مربوطٌ = لا تجديد محلّي بحال: تُخفى الكرت ويُوجَّه الزرّ للّوحة
            // حتى قبل أن تصل روابطها في اللقطة
            'fawtraLinked' => app(FawtraCredentials::class)->configured()
                || $fawtra?->snapshot !== null,
        ]);
    }
}
