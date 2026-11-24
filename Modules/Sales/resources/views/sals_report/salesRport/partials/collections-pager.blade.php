{{--
    شريطُ صفحاتٍ من الخادم لجداول تقرير التحصيلات (التفاصيل والمصفوفة الشهرية).

    روابطُ تحمل فلاتر الشاشة كلَّها مع `page` و`per_page`، بمظهر أزرار `ag-page`
    نفسِه. كان الترقيمُ في المتصفّح يُخفي الصفوفَ بصنفٍ بعد إرسالها كلِّها،
    فصفحةُ سنةٍ كاملة تجاوزت خمسين ميغابايت.

    @param array{per_page:int,page:int,pages:int,total:int,from:int,to:int} $pager
    @param string $unit  اسمُ الوحدة في سطر «عرض … من أصل …» (تحصيل / عميل)
--}}
@php
    $pgUnit = $unit ?? 'صفّ';
@endphp

@if (! empty($pager) && $pager['total'] > 0)
    @php
        $pgUrl = fn (int $p) => request()->fullUrlWithQuery(['page' => $p, 'per_page' => $pager['per_page']]);
        $pgWindow = collect([1, $pager['pages'], $pager['page'] - 1, $pager['page'], $pager['page'] + 1])
            ->filter(fn ($p) => $p >= 1 && $p <= $pager['pages'])
            ->unique()->sort()->values();
        $pgLast = 0;
    @endphp
    <div class="ag-pager no-print">
        <span class="ag-pager__info">عرض {{ number_format($pager['from']) }} – {{ number_format($pager['to']) }} من أصل {{ number_format($pager['total']) }} {{ $pgUnit }}</span>
        @if ($pager['pages'] > 1)
            <nav class="ag-pages" aria-label="صفحات الجدول">
                <a class="ag-page {{ $pager['page'] === 1 ? 'is-disabled' : '' }}" href="{{ $pgUrl(max(1, $pager['page'] - 1)) }}" aria-label="السابق"><i class="fas fa-chevron-right"></i></a>
                @foreach ($pgWindow as $p)
                    @if ($p - $pgLast > 1)
                        <span class="ag-page ag-page--gap">…</span>
                    @endif
                    <a class="ag-page {{ $p === $pager['page'] ? 'is-active' : '' }}" href="{{ $pgUrl($p) }}">{{ $p }}</a>
                    @php $pgLast = $p; @endphp
                @endforeach
                <a class="ag-page {{ $pager['page'] === $pager['pages'] ? 'is-disabled' : '' }}" href="{{ $pgUrl(min($pager['pages'], $pager['page'] + 1)) }}" aria-label="التالي"><i class="fas fa-chevron-left"></i></a>
            </nav>
        @endif
    </div>
@endif
