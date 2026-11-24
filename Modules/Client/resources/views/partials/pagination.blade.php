@if(isset($items) && $items->hasPages())
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="pagination-info text-muted">
            عرض {{ $items->firstItem() ?? 0 }} إلى {{ $items->lastItem() ?? 0 }} من {{ $items->total() }} {{ $itemName ?? 'عنصر' }}
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                {{-- Previous Button --}}
                <li class="page-item {{ $items->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link pagination-link" 
                       href="#" 
                       data-page="{{ $items->currentPage() - 1 }}"
                       {{ $items->onFirstPage() ? 'aria-disabled="true"' : '' }}>
                        Previous
                    </a>
                </li>

                {{-- Numbered Pages --}}
                @php
                    $start = max($items->currentPage() - 2, 1);
                    $end = min($start + 4, $items->lastPage());
                    if ($end - $start < 4) {
                        $start = max($end - 4, 1);
                    }
                @endphp

                @for ($i = $start; $i <= $end; $i++)
                    <li class="page-item {{ $items->currentPage() == $i ? 'active' : '' }}">
                        <a class="page-link pagination-link" 
                           href="#" 
                           data-page="{{ $i }}">
                            {{ $i }}
                        </a>
                    </li>
                @endfor

                {{-- Next Button --}}
                <li class="page-item {{ !$items->hasMorePages() ? 'disabled' : '' }}">
                    <a class="page-link pagination-link" 
                       href="#" 
                       data-page="{{ $items->currentPage() + 1 }}"
                       {{ !$items->hasMorePages() ? 'aria-disabled="true"' : '' }}>
                        Next
                    </a>
                </li>
            </ul>
        </nav>
    </div>
@else
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="pagination-info text-muted">
            عرض {{ $items->count() }} من {{ $items->total() }} {{ $itemName ?? 'عنصر' }}
        </div>
    </div>
@endif