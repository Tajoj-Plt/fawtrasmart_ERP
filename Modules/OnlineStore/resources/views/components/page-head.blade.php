{{--
    ترويسة صفحة موحّدة لشاشات المتجر الإلكتروني — نسخة مطابقة لمكوّن
    `stock::components.page-head` (نفس نظام `stk-*` المشترك، انظر
    docs/OnlineStore_Design_Permissions_Migration_Plan.md).

    @props:
      title   العنوان
      icon    أيقونة feather بلا البادئة، مثال: 'shopping-bag'
      sub     سطر شارح تحت العنوان (اختياري)
      crumbs  مسار التنقّل: ['النص' => 'الرابط'، 'النص الأخير' => null]

    @slot actions  أزرار الإجراء يمين الترويسة

    مثال:
      @include('onlinestore::components.page-head', [
          'title' => 'العلامات التجارية',
          'icon' => 'award',
          'sub' => 'العلامات التجارية للمنتجات المعروضة في المتجر',
          'crumbs' => ['الرئيسية' => url('/'), 'العلامات التجارية' => null],
      ])
--}}
@php
    $crumbs = $crumbs ?? [];
    $icon = $icon ?? null;
    $sub = $sub ?? null;
@endphp

<div class="stk-page-head">
    <div class="stk-page-head__titles">
        @if ($icon)
            <span class="stk-page-head__icon" aria-hidden="true">
                <i class="feather icon-{{ $icon }}"></i>
            </span>
        @endif

        <div class="min-width-0">
            <h2 class="stk-page-head__title">{{ $title }}</h2>

            @if ($sub)
                <p class="stk-page-head__sub">{{ $sub }}</p>
            @elseif (! empty($crumbs))
                <nav aria-label="مسار التنقّل">
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

    @if (! empty($actions))
        <div class="stk-page-head__actions">{!! $actions !!}</div>
    @elseif (trim($__env->yieldContent('page-actions')) !== '')
        <div class="stk-page-head__actions">@yield('page-actions')</div>
    @endif
</div>
