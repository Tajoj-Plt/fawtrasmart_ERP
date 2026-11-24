{{--
    ترويسة الصفحة — بترميز ترويسة المبيعات (`sl-page-head`) حرفاً بحرف:
    أيقونةٌ مربّعة بلون الهوية، فالعنوان، فسطرُه الشارح، ففتاتُ المسار تحته،
    والإجراءاتُ في الجهة المقابلة.

    الترميزُ مكتوبٌ هنا ولا يُستدعى `sales::components.page-head`: وحدةٌ
    معطَّلة تُسقط قوالبها، فترويسةُ الفروع لا تتوقّف على تشغيل المبيعات.

    وواجهةُ النداء لم تتغيّر (title/subtitle/icon/crumbs + شريحة actions)،
    فكلُّ `<x-branches::page-head>` في الشاشات يعمل كما كان.
--}}
@props([
    'title' => '',
    'subtitle' => null,
    'icon' => 'git-branch',
    'crumbs' => [],   // ['نص' => 'رابط', 'نص أخير' => null]
    'actions' => null,
])

@php
    // فتاتُ التنقّل يبدأ دائماً من الرئيسية ثم لوحة الفروع، فما يمرّره
    // القالبُ هو الذيلُ لا المسار كاملاً.
    $headCrumbs = array_merge([
        __('branches.common.home') => url('/dashboard'),
        __('branches.common.branches') => route('branches.dashboard'),
    ], $crumbs);

    $headActions = $actions ? trim((string) $actions) : '';
@endphp

<div class="sl-page-head">
    <div class="sl-page-head__titles">
        @if ($icon)
            <span class="sl-page-head__icon" aria-hidden="true">
                <i class="feather icon-{{ $icon }}"></i>
            </span>
        @endif

        <div style="min-width:0">
            {{-- `js-page-title`: شاشتا الإضافة والتعديل تبدّلان العنوان مع نوع العنصر --}}
            <h2 class="sl-page-head__title js-page-title">{{ $title }}</h2>

            @if ($subtitle)
                <p class="sl-page-head__sub">{{ $subtitle }}</p>
            @endif

            <nav aria-label="{{ __('branches.common.path') }}">
                <ol class="breadcrumb">
                    @foreach ($headCrumbs as $label => $url)
                        @if ($url)
                            <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                        @else
                            <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        </div>
    </div>

    @if ($headActions !== '')
        <div class="sl-page-head__actions">{!! $headActions !!}</div>
    @endif
</div>
