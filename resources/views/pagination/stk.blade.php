{{--
    قالب الترقيم المعتمد للنظام كلّه.

    **ما كان**

    المشروع لا يسجّل قالب ترقيم ولا يحمل `resources/views/vendor/pagination/`،
    فكل نداءٍ لـ`$paginator->links()` — وهي **سبعةٌ وأربعون** موضعاً موزّعة
    على الحسابات والمخزون والمبيعات والموارد البشرية ونقطة البيع والمتجر —
    يُصيّر قالب لارافيل الافتراضي، وهو مكتوب **بترميز Tailwind**:
    `<span class="relative inline-flex items-center px-4 py-2 …">` وأصنافُ
    utility لا وجود لها في هذا المشروع أصلاً. فيخرج شريطٌ عارٍ بلا حدودٍ
    ولا تباعد، لا يشبه شيئاً في بقية الشاشات ولا يتبع سمة النظام.

    **ما صار**

    ترميز `ul.pagination` بعناصر `page-item`/`page-link` — وهو ما يصمّمه
    `stock-theme` وتعرضه شاشة أذون المخزن. يُسجَّل قالباً افتراضياً في
    `AppServiceProvider`، فيسري على السبعة والأربعين موضعاً بلا تعديل
    سطرٍ فيها.

    الأسهم تتبع اتّجاه الصفحة: في RTL يشير `chevron-right` إلى السابق
    و`chevron-left` إلى التالي — وهو عكس ما كانت تفعله الشرائط اليدوية.
--}}

@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();

        $atStart = $paginator->onFirstPage();
        $atEnd = ! $paginator->hasMorePages();
    @endphp

    <nav aria-label="تصفح الصفحات">
        <ul class="pagination mb-0">

            {{-- الأولى والسابقة --}}
            @foreach ([['chevrons-right', 1, 'الصفحة الأولى', $atStart], ['chevron-right', max(1, $current - 1), 'السابقة', $atStart]] as [$icon, $page, $label, $off])
                <li class="page-item {{ $off ? 'disabled' : '' }}">
                    @if ($off)
                        <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                    @else
                        <a class="page-link" href="{{ $paginator->url($page) }}" rel="prev"
                            aria-label="{{ $label }}" title="{{ $label }}">
                            <i class="feather icon-{{ $icon }}"></i>
                        </a>
                    @endif
                </li>
            @endforeach

            {{--
                الأرقام: `elements` يبنيها لارافيل نفسه، فتُحترم نافذة
                `onEachSide` وتُوضع «…» حيث يقرّرها.
            --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="page-item {{ $page == $current ? 'active' : '' }}">
                            @if ($page == $current)
                                <span class="page-link" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            {{-- التالية والأخيرة --}}
            @foreach ([['chevron-left', min($last, $current + 1), 'التالية', $atEnd], ['chevrons-left', $last, 'الصفحة الأخيرة', $atEnd]] as [$icon, $page, $label, $off])
                <li class="page-item {{ $off ? 'disabled' : '' }}">
                    @if ($off)
                        <span class="page-link" aria-hidden="true"><i class="feather icon-{{ $icon }}"></i></span>
                    @else
                        <a class="page-link" href="{{ $paginator->url($page) }}" rel="next"
                            aria-label="{{ $label }}" title="{{ $label }}">
                            <i class="feather icon-{{ $icon }}"></i>
                        </a>
                    @endif
                </li>
            @endforeach

        </ul>
    </nav>
@endif
