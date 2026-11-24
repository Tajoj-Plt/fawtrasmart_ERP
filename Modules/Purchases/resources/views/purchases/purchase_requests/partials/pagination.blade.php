{{--
    ترقيم صفحات طلبات الشراء.

    يُفوَّض إلى `components.custom-pagination` — نفس المكوّن الذي يذيّل جدول
    شاشة «سجلّ النشاطات»، فيتطابق شكل الشريط ونصّه في الشاشتين.

    @param \Illuminate\Pagination\LengthAwarePaginator $purchaseOrders
--}}
@include('components.custom-pagination', ['paginator' => $purchaseOrders])
