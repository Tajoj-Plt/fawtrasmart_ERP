{{--
    ترويسة صفحة موحّدة لشاشات إعدادات المبيعات — على قياس ترويسة «الأذون
    المخزنية» (`stock::components.page-head`).

    كانت كل شاشة إعدادات تكتب ترويستها بيدها: `content-header row` ثم
    عنوانٌ عائم ثم `ol.breadcrumb` — أربعون سطراً مكرّرة في كل قالب، تتباعد
    مع أول تعديل. وأزرار الإجراء لا مكان لها فتُحشر داخل بطاقة الفلترة.

    الوسائط:
      title   العنوان (نصّ مترجَم)
      icon    أيقونة feather بلا البادئة، مثال: 'truck'
      sub     سطر شارح تحت العنوان (اختياري)
      crumbs  فتات المسار: ['النص' => 'الرابط', 'النص الأخير' => null]
      actions HTML جاهز في جهة الإجراءات (اختياري)

    الإجراءات بطريقتين:
      • `actions` نصّاً — يكفي زرّاً أو زرَّين بسيطين.
      • `@section('page-actions')` — للإجراءات التي فيها شرط أو صلاحية أو
        قائمة منسدلة. يجب تعريف القسم **قبل** استدعاء هذا القالب، فـ
        `yieldContent` تقرأ ما سُجّل حتى تلك اللحظة.

    مثال:
      @include('sales::components.page-head', [
          'title' => __('client_settings.shipping.title'),
          'icon'  => 'truck',
          'sub'   => __('client_settings.shipping.subtitle'),
          'crumbs' => [
              __('client_settings.ui.home') => route('dashboard'),
              __('client_settings.hub.title') => route('clients.setting'),
              __('client_settings.shipping.title') => null,
          ],
      ])
--}}
@php
    // `@include` يمرّر متغيّرات القالب الأب كلَّها، لا ما مُرِّر في المصفوفة
    // وحده — ففحصُ النوع يمنع متغيّراً محليّاً في الأب من أن يصير وسيطاً هنا
    $crumbs = (isset($crumbs) && is_array($crumbs)) ? $crumbs : [];
    $icon = (isset($icon) && is_string($icon) && $icon !== '') ? $icon : null;
    $sub = (isset($sub) && is_string($sub) && $sub !== '') ? $sub : null;
    $actions = (isset($actions) && is_string($actions)) ? $actions : null;
@endphp

<div class="cl-page-head">
    <div class="cl-page-head__titles">
        @if ($icon)
            <span class="cl-page-head__icon" aria-hidden="true">
                <i class="feather icon-{{ $icon }}"></i>
            </span>
        @endif

        <div style="min-width:0">
            <h2 class="cl-page-head__title">{{ $title }}</h2>

            @if ($sub)
                <p class="cl-page-head__sub">{{ $sub }}</p>
            @endif

            @if (! empty($crumbs))
                <nav aria-label="{{ __('client_settings.ui.breadcrumb') }}">
                    <ol class="breadcrumb">
                        @foreach ($crumbs as $label => $url)
                            @if ($url)
                                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                            @else
                                <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif
        </div>
    </div>

    @if ($actions !== null && trim($actions) !== '')
        <div class="cl-page-head__actions">{!! $actions !!}</div>
    @elseif (trim($__env->yieldContent('page-actions')) !== '')
        <div class="cl-page-head__actions">@yield('page-actions')</div>
    @endif
</div>
