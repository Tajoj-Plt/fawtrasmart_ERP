{{--
    شريط «ما استُهلك من حدّ باقتك في فوترة» — نسخةُ المبيعات.

    ══════════════════ لماذا نسخةٌ مستقلّة عن `purchases::` ══════════════════

    لأن نصوص تلك النسخة تُقرأ من `purchase_orders.subscription.*`، وهو ملفُّ
    لغةِ وحدةِ المشتريات. فتضمينُها هنا يعني شاشةَ مبيعاتٍ تعرض نصّاً معرَّفاً
    في وحدةٍ أخرى: أوّلُ تعديلٍ على صياغة المشتريات يغيّر شاشةَ المبيعات بلا
    قصد، وتعطيلُ وحدة المشتريات يُفرغ الشريط. هذه النسخة تقرأ من
    `sales_subscription.*` ولا تعتمد على شيءٍ خارج المبيعات.

    ══════════════════ متى يظهر؟ ══════════════════

    حين تكون الميزة **معروفة** لدى فوترة و**محدودة** بحدّ. وثلاث حالاتٍ تُخفيه:

      • نظامٌ غير مربوط باشتراك — لا لقطة، فلا حدود تُقرأ.
      • ميزةٌ لا يعرفها كتالوج فوترة — `known === false`.
      • ميزةٌ بلا حدّ في الباقة — `unlimited`، فلا شيء يُقاس.

    وفي الثلاث لا يُعرَض شيء: شريطٌ يقول «٠ من ٠» أسوأ من غيابه.

    ══════════════════ الوسائط ══════════════════

      limit  ناتج `SubscriptionGate::limit('<feature>')` (مطلوب)

    ══════════════════ مثال ══════════════════

      @include('sales::components.subscription-bar', ['limit' => $subscriptionLimit])
--}}

@php
    // `@include` يمرّر متغيّرات الأب كلَّها، ففحصُ النوع يمنع متغيّراً محليّاً
    // في الأب اسمه `limit` من أن يصير وسيطاً هنا
    $subLimit = (isset($limit) && is_array($limit)) ? $limit : null;

    $subVisible = $subLimit
        && ($subLimit['known'] ?? false)
        && ! ($subLimit['unlimited'] ?? true);

    if ($subVisible) {
        $subPercent = min(100, max(0, (int) ($subLimit['percent'] ?? 0)));
        // النفاد أحمر، والاقتراب منه (٨٠٪ فأكثر) برتقالي — نفس عتبة
        // `SubscriptionGate::nearingLimits()` حتى لا تتناقض الشاشة مع التنبيهات
        $subTone = ($subLimit['exhausted'] ?? false)
            ? 'danger'
            : ($subPercent >= 80 ? 'warning' : 'info');
    }
@endphp

@if ($subVisible)
    <div class="alert alert-{{ $subTone }} d-flex align-items-center justify-content-between flex-wrap mb-2"
         style="gap:.5rem; padding:.5rem .75rem;">
        <span>
            <i class="feather icon-activity" aria-hidden="true"></i>
            {{ __('sales_subscription.bar.'.(($subLimit['exhausted'] ?? false) ? 'usage_line_exhausted' : 'usage_line'), [
                'used' => \App\Support\LanguageSettings::formatNumber($subLimit['used'] ?? 0, 0),
                'allowance' => \App\Support\LanguageSettings::formatNumber($subLimit['allowance'] ?? 0, 0),
            ]) }}
        </span>

        <div class="progress" style="width: 160px; height: 8px;" role="progressbar"
             aria-valuenow="{{ $subPercent }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ $subLimit['label'] ?? '' }}">
            <div class="progress-bar bg-{{ $subTone }}" style="width: {{ $subPercent }}%"></div>
        </div>
    </div>
@endif
