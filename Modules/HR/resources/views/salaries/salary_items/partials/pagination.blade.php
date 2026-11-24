@if ($items->hasPages())
<style>
    .pagination-link,
    .pagination-link:focus,
    .pagination-link:active {
        outline: none !important;
        box-shadow: none !important;
    }
    
    a[href="#"] {
        cursor: pointer;
    }
    
    .table-responsive, 
    .invoice-container,
    .content-wrapper,
    body {
        -ms-overflow-style: none;
        scrollbar-width: none;
        overflow-y: auto;
    }
    
    .table-responsive::-webkit-scrollbar, 
    .invoice-container::-webkit-scrollbar,
    .content-wrapper::-webkit-scrollbar,
    body::-webkit-scrollbar {
        display: none;
        width: 0;
    }
</style>

<div class="d-flex justify-content-between align-items-center mt-3 w-100">
    {{-- معلومات الترقيم --}}
    <div class="pagination-info text-muted">
        عرض {{ $items->firstItem() ?? 0 }} إلى {{ $items->lastItem() ?? 0 }} من {{ $items->total() }} نتيجة
    </div>

    {{-- أزرار التنقل --}}
    <nav aria-label="صفحات النتائج">
        <ul class="pagination pagination-sm mb-0">
            {{-- الصفحة الأولى --}}
            @if ($items->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link"><i class="fa fa-angle-double-right"></i></span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link pagination-link" href="#" 
                       onclick="changePage(1)"
                       aria-label="الأول">
                        <i class="fa fa-angle-double-right"></i>
                    </a>
                </li>
            @endif

            {{-- الصفحة السابقة --}}
            @if ($items->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link"><i class="feather icon-chevron-right"></i></span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link pagination-link" href="#"
                       onclick="changePage({{ $items->currentPage() - 1 }})"
                       aria-label="السابق">
                        <i class="feather icon-chevron-right"></i>
                    </a>
                </li>
            @endif

            {{-- رقم الصفحة الحالية --}}
            <li class="page-item active">
                <span class="page-link">{{ $items->currentPage() }}</span>
            </li>

            {{-- الصفحة التالية --}}
            @if ($items->hasMorePages())
                <li class="page-item">
                    <a class="page-link pagination-link" href="#"
                       onclick="changePage({{ $items->currentPage() + 1 }})"
                       aria-label="التالي">
                        <i class="feather icon-chevron-left"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link"><i class="feather icon-chevron-left"></i></span>
                </li>
            @endif

            {{-- الصفحة الأخيرة --}}
            @if ($items->hasMorePages())
                <li class="page-item">
                    <a class="page-link pagination-link" href="#"
                       onclick="changePage({{ $items->lastPage() }})"
                       aria-label="الأخير">
                        <i class="fa fa-angle-double-left"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link"><i class="fa fa-angle-double-left"></i></span>
                </li>
            @endif
        </ul>
    </nav>
</div>
@else
    {{-- رسالة عندما لا يوجد تعدد صفحات --}}
    <div class="d-flex justify-content-between align-items-center mt-3 w-100">
        <div class="pagination-info text-muted">
            عرض {{ $items->count() }} من {{ $items->total() }} نتيجة
        </div>
        <div class="text-muted small">
            صفحة واحدة
        </div>
    </div>
@endif