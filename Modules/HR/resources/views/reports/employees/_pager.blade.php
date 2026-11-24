{{-- ترقيمُ صفحات الجدول — @param LengthAwarePaginator $pager --}}
@if ($pager && $pager->hasPages())
    <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">عرض {{ $pager->firstItem() }} إلى {{ $pager->lastItem() }} من {{ number_format($pager->total()) }}</small>
        {{ $pager->links('pagination::bootstrap-5') }}
    </div>
@endif
