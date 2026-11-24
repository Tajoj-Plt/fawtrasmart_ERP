{{--
    الترقيم البسيط — لقوائم `simplePaginate` التي لا تعرف عدد صفحاتها.

    الترميز نفسه المعتمد في `stk.blade.php` بلا أرقام: سهمان فقط، لأن
    `Paginator` البسيط لا يحسب `lastPage()` أصلاً.
--}}

@if ($paginator->hasPages())
    <nav aria-label="تصفح الصفحات">
        <ul class="pagination mb-0">

            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginator->onFirstPage())
                    <span class="page-link" aria-hidden="true"><i class="feather icon-chevron-right"></i></span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="السابقة"
                        title="السابقة">
                        <i class="feather icon-chevron-right"></i>
                    </a>
                @endif
            </li>

            <li class="page-item active">
                <span class="page-link" aria-current="page">{{ $paginator->currentPage() }}</span>
            </li>

            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="التالية"
                        title="التالية">
                        <i class="feather icon-chevron-left"></i>
                    </a>
                @else
                    <span class="page-link" aria-hidden="true"><i class="feather icon-chevron-left"></i></span>
                @endif
            </li>

        </ul>
    </nav>
@endif
