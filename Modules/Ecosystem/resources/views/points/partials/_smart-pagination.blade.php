{{--
    Smart Pagination Partial - يعرض أرقام الصفحات بشكل ذكي مع نقاط (...)
    
    Parameters:
        $paginator  - required - Laravel Paginator instance
        $label      - required - Arabic label (e.g. "معاملة", "مشارك")
        $style      - optional - 'modern' (default) or 'pts'
        $appends    - optional - array of query params to append (for tabs etc.)
    
    Usage:
        @include('ecosystem::points.partials._smart-pagination', ['paginator' => $transactions, 'label' => 'معاملة'])
        @include('ecosystem::points.partials._smart-pagination', ['paginator' => $participants, 'label' => 'مشارك', 'style' => 'pts'])
--}}

@php
    $style = $style ?? 'modern';
    $appends = $appends ?? [];
    $pager = !empty($appends) ? $paginator->appends($appends) : $paginator;
    
    // حساب نطاق الصفحات الذكي
    $currentPage = $pager->currentPage();
    $lastPage = $pager->lastPage();
    $onEachSide = 2;
    
    $pages = collect();
    
    if ($lastPage <= ($onEachSide * 2 + 5)) {
        // عدد الصفحات قليل - اعرض الكل
        for ($i = 1; $i <= $lastPage; $i++) {
            $pages->push($i);
        }
    } else {
        // الصفحة الأولى دائماً
        $pages->push(1);
        
        // حساب النطاق حول الصفحة الحالية
        $start = max(2, $currentPage - $onEachSide);
        $end = min($lastPage - 1, $currentPage + $onEachSide);
        
        // إضافة ... قبل النطاق
        if ($start > 2) {
            $pages->push('...');
        }
        
        // إضافة صفحات النطاق
        for ($i = $start; $i <= $end; $i++) {
            $pages->push($i);
        }
        
        // إضافة ... بعد النطاق
        if ($end < $lastPage - 1) {
            $pages->push('...');
        }
        
        // الصفحة الأخيرة دائماً
        $pages->push($lastPage);
    }
    
    $urlRange = $pager->getUrlRange(1, $lastPage);
@endphp

@if($pager->hasPages())
    @if($style === 'pts')
    {{-- ===== pts-pagination Style ===== --}}
    <div class="pts-pagination">
        <div class="pts-pagination-info">
            عرض {{ $pager->firstItem() }} إلى {{ $pager->lastItem() }} من {{ $pager->total() }} {{ $label }}
        </div>
        <div class="pts-pagination-links">
            {{-- Previous --}}
            @if ($pager->onFirstPage())
                <span class="page-link disabled"><i class="feather icon-chevron-right"></i></span>
            @else
                <a href="{{ $pager->previousPageUrl() }}" class="page-link"><i class="feather icon-chevron-right"></i></a>
            @endif

            {{-- Page Numbers --}}
            @foreach ($pages as $page)
                @if ($page === '...')
                    <span class="page-link disabled">...</span>
                @elseif ($page == $currentPage)
                    <span class="page-link active">{{ $page }}</span>
                @else
                    <a href="{{ $urlRange[$page] }}" class="page-link">{{ $page }}</a>
                @endif
            @endforeach

            {{-- Next --}}
            @if ($pager->hasMorePages())
                <a href="{{ $pager->nextPageUrl() }}" class="page-link"><i class="feather icon-chevron-left"></i></a>
            @else
                <span class="page-link disabled"><i class="feather icon-chevron-left"></i></span>
            @endif
        </div>
    </div>

    @else
    {{-- ===== pagination-modern Style ===== --}}
    <div class="pagination-modern">
        <div class="pagination-info">
            عرض {{ $pager->firstItem() }} إلى {{ $pager->lastItem() }} من أصل {{ $pager->total() }} {{ $label }}
        </div>
        <nav>
            <ul class="pagination">
                {{-- Previous --}}
                @if ($pager->onFirstPage())
                    <li class="page-item disabled">
                        <span class="page-link"><i class="feather icon-chevron-right"></i></span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $pager->previousPageUrl() }}">
                            <i class="feather icon-chevron-right"></i>
                        </a>
                    </li>
                @endif

                {{-- Page Numbers --}}
                @foreach ($pages as $page)
                    @if ($page === '...')
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    @elseif ($page == $currentPage)
                        <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $urlRange[$page] }}">{{ $page }}</a></li>
                    @endif
                @endforeach

                {{-- Next --}}
                @if ($pager->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $pager->nextPageUrl() }}">
                            <i class="feather icon-chevron-left"></i>
                        </a>
                    </li>
                @else
                    <li class="page-item disabled">
                        <span class="page-link"><i class="feather icon-chevron-left"></i></span>
                    </li>
                @endif
            </ul>
        </nav>
    </div>
    @endif
@endif
