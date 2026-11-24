{{--
    زرّ «أنشئ أوّلاً» — يظهر حين تكون قائمةٌ منسدلة **فارغة**.

    ─────────────────── المشكلة ───────────────────

    شاشاتٌ كثيرة تطلب اختياراً من قسمٍ آخر: المستودع يطلب فرعاً، والصنف يطلب
    مجموعة منتجات، والمنتج يطلب مورّداً. فإن لم يُنشَأ ذلك بعد، رأى المستخدم
    قائمةً فارغة بلا ما يقول له **لماذا** فارغة ولا **أين** يملؤها — فيظنّ
    الشاشة معطّلة، أو يبحث في القوائم حتى يجد الشاشة الصحيحة بالصدفة.

    ─────────────────── لماذا عند الفراغ وحده؟ ───────────────────

    زرٌّ دائمٌ بجانب كل قائمة يصير ضجيجاً يتوقّف النظر عنده، ويزاحم الحقل
    نفسه. والحاجة إليه لا تقع إلا حين لا يوجد ما يُختار — فحينها وحدها يظهر.

    ─────────────────── ولماذا ينقل في اللسان نفسه؟ ───────────────────

    لأن الزرّ لا يظهر إلا والقائمةُ فارغة — أي أن النموذج **لا يمكن إتمامه
    أصلاً**. فلا شيء يضيع بالانتقال، وفتحُ لسانٍ جديد يترك المستخدم أمام
    نسختين من الشاشة ويجعله يعود إلى واحدةٍ قديمة لا تعرف ما أُنشئ.

    ─────────────────── الطبقات الثلاث تُحترم هنا ───────────────────

    الزرّ بابٌ إلى شاشةٍ أخرى، فيخضع لما تخضع له:

      • مسارٌ غير مسجَّل        → لا يُعرض شيء (وحدةٌ معطَّلة مثلاً).
      • صلاحية نظامٍ ناقصة     → تلميحٌ بلا رابط: «راجع من يملك الصلاحية».
      • ميزةٌ خارج الباقة       → قفلٌ يقود إلى شاشة الاشتراك.

    وبغير ذلك يقود إلى بابٍ يُغلق في وجهه بـ403 أو 402 — وهو أسوأ من غياب
    الزرّ، لأنه يَعِد بما لا يفي.

    ─────────────────── الاستعمال ───────────────────

        <x-create-first
            :count="count($branches)"
            route="branches.create"
            permission="settings.branches.create"
            :plan="\App\Constants\planFeatures\StockPlanFeatures::STOREHOUSES"
            label="فرع"
            hint="المستودع يتبع فرعاً، ولا فروع مسجَّلة بعد." />

    `plan` اختياريّ: يُمرَّر حين تكون الشاشة الهدف محروسةً بميزة باقة.
    و`permission` اختياريّ كذلك — يُترك حين لا يحرس المسارَ الهدفَ صلاحية.
--}}

@props([
    'count' => 0,
    'route' => null,
    'permission' => null,
    'plan' => null,
    'label' => '',
    'hint' => null,
])

@php
    use App\Support\PlanLock;

    // لا شيء يُعرض ما دام في القائمة ما يُختار
    $show = (int) $count === 0 && $route && \Illuminate\Support\Facades\Route::has($route);

    $lockedByPlan = $show && $plan && PlanLock::locked($plan);
    $lockedByPermission = $show && ! $lockedByPlan && $permission && ! hasPermission($permission);

    $reason = match (true) {
        $lockedByPlan => PlanLock::reason($plan),
        $lockedByPermission => 'لا تملك صلاحية إنشاء '.$label.'. راجع مسؤول الصلاحيات.',
        default => trim(($hint ? $hint.' ' : '').'اضغط لفتح شاشة إنشاء '.$label.'.'),
    };
@endphp

@if ($show)
    <div class="stk-create-first">
        @if ($lockedByPlan)
            <a href="{{ PlanLock::url() }}"
               class="stk-create-first__btn stk-create-first__btn--locked"
               title="{{ $reason }}">
                <i class="feather icon-lock"></i>
                <span>{{ $label }} — خارج الباقة</span>
            </a>
        @elseif ($lockedByPermission)
            <span class="stk-create-first__btn stk-create-first__btn--muted"
                  title="{{ $reason }}" aria-disabled="true">
                <i class="feather icon-slash"></i>
                <span>لا صلاحية لإضافة {{ $label }}</span>
            </span>
        @else
            <a href="{{ route($route) }}"
               class="stk-create-first__btn"
               title="{{ $reason }}">
                <i class="feather icon-plus"></i>
                <span>أضف {{ $label }}</span>
            </a>
        @endif

        @if ($hint && ! $lockedByPlan && ! $lockedByPermission)
            <span class="stk-create-first__hint">{{ $hint }}</span>
        @endif
    </div>
@endif
