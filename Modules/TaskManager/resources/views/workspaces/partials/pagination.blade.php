{{-- ملف: resources/views/taskmanager/workspaces/partials/pagination.blade.php --}}
@if ($workspaces->hasPages())

<style>
    /* منع التمرير التلقائي عند تغيير الصفحات */
    .pagination-link,
    .pagination-link:focus,
    .pagination-link:active {
        outline: none !important;
        box-shadow: none !important;
    }
    
    /* التأكد من أن الروابط لا تسبب scroll */
    a[href="#"] {
        cursor: pointer;
    }
    
    /* إخفاء شريط التمرير مع الحفاظ على وظيفة التمرير */
    .table-responsive, 
    .workspace-container,
    .content-wrapper,
    body {
        -ms-overflow-style: none;  /* لـ Internet Explorer و Edge */
        scrollbar-width: none;     /* لـ Firefox */
        overflow-y: auto;
    }
    
    /* لمتصفحات WebKit (كروم، سفاري، إلخ) */
    .table-responsive::-webkit-scrollbar, 
    .workspace-container::-webkit-scrollbar,
    .content-wrapper::-webkit-scrollbar,
    body::-webkit-scrollbar {
        display: none;
        width: 0;
    }
</style>
    <div class="d-flex justify-content-between align-items-center mt-3 w-100">
        {{-- معلومات الترقيم --}}
        <div class="pagination-info text-muted">
            عرض {{ $workspaces->firstItem() ?? 0 }} إلى {{ $workspaces->lastItem() ?? 0 }} من {{ $workspaces->total() }} نتيجة
        </div>

        {{-- أزرار التنقل --}}
        <nav aria-label="صفحات النتائج">
            <ul class="pagination pagination-sm mb-0">
                {{-- الصفحة الأولى --}}
                @if ($workspaces->onFirstPage())
                    <li class="page-item disabled">
                        <span class="page-link"><i class="fa fa-angle-double-right"></i></span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link pagination-link" href="#" 
                           data-page="1" 
                           aria-label="الأول">
                            <i class="fa fa-angle-double-right"></i>
                        </a>
                    </li>
                @endif

                {{-- الصفحة السابقة --}}
                @if ($workspaces->onFirstPage())
                    <li class="page-item disabled">
                        <span class="page-link"><i class="fa fa-angle-right"></i></span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link pagination-link" href="#"
                           data-page="{{ $workspaces->currentPage() - 1 }}"
                           aria-label="السابق">
                            <i class="fa fa-angle-right"></i>
                        </a>
                    </li>
                @endif

                {{-- رقم الصفحة الحالية --}}
                <li class="page-item active">
                    <span class="page-link">{{ $workspaces->currentPage() }}</span>
                </li>

                {{-- الصفحة التالية --}}
                @if ($workspaces->hasMorePages())
                    <li class="page-item">
                        <a class="page-link pagination-link" href="#"
                           data-page="{{ $workspaces->currentPage() + 1 }}"
                           aria-label="التالي">
                            <i class="fa fa-angle-left"></i>
                        </a>
                    </li>
                @else
                    <li class="page-item disabled">
                        <span class="page-link"><i class="fa fa-angle-left"></i></span>
                    </li>
                @endif

                {{-- الصفحة الأخيرة --}}
                @if ($workspaces->hasMorePages())
                    <li class="page-item">
                        <a class="page-link pagination-link" href="#"
                           data-page="{{ $workspaces->lastPage() }}"
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
            عرض {{ $workspaces->count() }} من {{ $workspaces->total() }} نتيجة
        </div>
        <div class="text-muted small">
            صفحة واحدة
        </div>
    </div>
@endif