{{--
    Smart Pagination Partial - يعرض أرقام الصفحات بشكل ذكي مع نقاط (...)
    
    Parameters:
        $paginator  - required - Laravel Paginator instance
        $label      - required - Arabic label (e.g. "مورد", "طلب", "نتيجة")
        $appends    - optional - array of query params to append (for filters etc.)
    
    Usage:
        @include('onlinestore::partials._smart-pagination', ['paginator' => $suppliers, 'label' => 'مورد'])
        @include('onlinestore::partials._smart-pagination', ['paginator' => $usages, 'label' => 'نتيجة', 'appends' => request()->query()])
--}}

@php
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
    <div class="pagination-modern">
        <div class="pagination-info">
            عرض {{ $pager->firstItem() ?? 0 }} إلى {{ $pager->lastItem() ?? 0 }} من أصل {{ $pager->total() }} {{ $label }}
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
