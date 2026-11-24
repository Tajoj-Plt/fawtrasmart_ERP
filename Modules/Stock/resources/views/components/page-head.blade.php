{{--
    ترويسة صفحة موحّدة لشاشات المخزون.

    كانت كل شاشة تكتب ترويستها بيدها — عنوانٌ عائم ومسارُ تنقّل بترميز
    `content-header row` المكرَّر في مئة قالب، وأزرارُ الإجراء تُحشر داخل
    بطاقة الفلترة لأن لا مكان لها. هنا مكانها.

    @props:
      title   العنوان
      icon    أيقونة feather بلا البادئة، مثال: 'package'
      sub     سطر شارح تحت العنوان (اختياري)
      crumbs  مسار التنقّل: ['النص' => 'الرابط'، 'النص الأخير' => null]

    @slot actions  أزرار الإجراء يمين الترويسة

    مثال:
      @include('stock::components.page-head', [
          'title' => 'الأصناف',
          'icon' => 'tag',
          'sub' => 'تصنيف المنتجات وربطها بالوحدات',
          'crumbs' => ['الرئيسية' => url('/'), 'الأصناف' => null],
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

    {{--
        الإجراءات بطريقتين:

          • `actions` نصّاً — يكفي زراً أو زرَّين بسيطين.
          • `@section('page-actions')` — للإجراءات التي فيها شرط أو صلاحية
            أو قائمة منسدلة، فلا تُكتب في سلسلة نصّية مقروءة.

        القسم يُعرَّف قبل الاستدعاء في القالب، فيكون جاهزاً عند `@yield`.
    --}}
    @if (! empty($actions))
        <div class="stk-page-head__actions">{!! $actions !!}</div>
    @elseif (trim($__env->yieldContent('page-actions')) !== '')
        <div class="stk-page-head__actions">@yield('page-actions')</div>
    @endif
</div>
