{{--
    ترويسة الصفحة — بترميز ترويسة المبيعات (`sl-page-head`): أيقونةٌ مربّعة
    بلون الهوية، فالعنوان، فسطرُه الشارح، ففتاتُ المسار تحته، والإجراءاتُ في
    الجهة المقابلة.

    الترميزُ مكتوبٌ هنا ولا يُستدعى `sales::components.page-head` ولا
    `stock::components.page-head`: وحدةٌ معطَّلة تُسقط قوالبها، وترويسةُ
    الإعدادات لا تتوقّف على تشغيل وحدةٍ أخرى.

    الوسائط (كما كانت، فكلُّ نداءٍ قائمٍ يعمل بلا تعديل):
      title   العنوان
      icon    أيقونة feather بلا البادئة، مثال: 'users'
      sub     سطر شارح تحت العنوان (اختياري)
      crumbs  فتات المسار: ['النص' => 'الرابط', 'النص الأخير' => null]
      actions HTML جاهز في جهة الإجراءات (اختياري)

    الإجراءات بطريقتين: `actions` نصّاً، أو `@section('page-actions')` يُعرَّف
    **قبل** استدعاء هذا القالب — للأزرار التي فيها شرطٌ أو صلاحية.
--}}
@php
    // `@include` يمرّر متغيّرات القالب الأب كلَّها لا ما مُرِّر في المصفوفة
    // وحده — ففحصُ النوع يمنع متغيّراً محليّاً في الأب من أن يصير وسيطاً هنا
    $crumbs = (isset($crumbs) && is_array($crumbs)) ? $crumbs : [];
    $icon = (isset($icon) && is_string($icon) && $icon !== '') ? $icon : null;
    $sub = (isset($sub) && is_string($sub) && $sub !== '') ? $sub : null;
    $actions = (isset($actions) && is_string($actions)) ? $actions : null;
@endphp

<div class="sl-page-head">
    <div class="sl-page-head__titles">
        @if ($icon)
            <span class="sl-page-head__icon" aria-hidden="true">
                <i class="feather icon-{{ $icon }}"></i>
            </span>
        @endif

        <div style="min-width:0">
            <h2 class="sl-page-head__title">{{ $title }}</h2>

            @if ($sub)
                <p class="sl-page-head__sub">{{ $sub }}</p>
            @endif

            @if (! empty($crumbs))
                <nav aria-label="{{ __('settings.common.settings') }}">
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
        <div class="sl-page-head__actions">{!! $actions !!}</div>
    @elseif (trim($__env->yieldContent('page-actions')) !== '')
        <div class="sl-page-head__actions">@yield('page-actions')</div>
    @endif
</div>
