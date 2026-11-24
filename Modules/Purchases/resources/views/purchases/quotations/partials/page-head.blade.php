{{--
    ترويسة صفحة موحّدة للفهرس الجديد
--}}
@php
    $crumbs = (isset($crumbs) && is_array($crumbs)) ? $crumbs : [];
    $icon = (isset($icon) && is_string($icon) && $icon !== '') ? $icon : null;
    $sub = (isset($sub) && is_string($sub) && $sub !== '') ? $sub : null;
    $actions = (isset($actions) && is_string($actions)) ? $actions : null;
@endphp

<div class="sl-page-head">
    <div class="sl-page-head__titles">
        @if ($icon)
            <span class="sl-page-head__icon" aria-hidden="true">
                <i class="fas fa-{{ $icon }}"></i>
            </span>
        @endif

        <div style="min-width:0">
            <h2 class="sl-page-head__title">{{ $title }}</h2>

            @if ($sub)
                <p class="sl-page-head__sub">{{ $sub }}</p>
            @endif

            @if (! empty($crumbs))
                <nav aria-label="{{ __('purchase_orders.breadcrumb_nav') }}">
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
