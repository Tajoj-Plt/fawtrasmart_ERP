{{--
    ترقيم صفحات «فواتير الشراء».

    يُفوَّض إلى `components.custom-pagination` — نفس المكوّن الذي يذيّل جدول
    شاشة «سجلّ النشاطات»، فيتطابق شكل الشريط ونصّه في الشاشتين.

    @param \Illuminate\Pagination\LengthAwarePaginator $purchaseData
--}}
@include('components.custom-pagination', ['paginator' => $purchaseData])
