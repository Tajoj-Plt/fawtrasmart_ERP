{{--
    ترقيمُ صفحاتٍ بروابط عاديّة — بنفس ترميز `partials/pagination_controls`
    الذي تعرضه «إدارة العملاء» (معلومات «عرض … من …» ثمّ الأول / السابق /
    الحالية / التالي / الأخير بـ`pagination-sm`)، فتتطابق الشاشتان شكلاً.

    الفرق الوحيد: هنا `href` حقيقيّ لكل زرّ بدل `data-page` الذي يلتقطه
    `PaginationManager` في الفهرس — فيعمل في شاشةٍ بلا AJAX، وتلتقطه
    شاشاتُ AJAX بقراءة `href` (دليل موظفي العملاء).

    الاستعمال:  {{ $clients->links('client::partials.link-pagination') }}
--}}
<div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
    <div class="pagination-info text-muted">
        @if ($paginator->total() > 0)
            عرض {{ $paginator->firstItem() }} إلى {{ $paginator->lastItem() }} من {{ $paginator->total() }} نتيجة
        @endif
    </div>

    @if ($paginator->hasPages())
        <nav aria-label="صفحات النتائج">
            <ul class="pagination pagination-sm mb-0">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-double-right"></i></span></li>
                    <li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-right"></i></span></li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->url(1) }}" aria-label="الأول"><i class="fa fa-angle-double-right"></i></a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" aria-label="السابق"><i class="fa fa-angle-right"></i></a>
                    </li>
                @endif

                <li class="page-item active">
                    <span class="page-link">{{ $paginator->currentPage() }}</span>
                </li>

                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" aria-label="التالي"><i class="fa fa-angle-left"></i></a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}" aria-label="الأخير"><i class="fa fa-angle-double-left"></i></a>
                    </li>
                @else
                    <li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-left"></i></span></li>
                    <li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-double-left"></i></span></li>
                @endif
            </ul>
        </nav>
    @endif
</div>
