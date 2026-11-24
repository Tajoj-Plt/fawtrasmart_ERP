{{--
    ترقيم صفحات «مرتجعات الشراء».

    يُفوَّض إلى `components.custom-pagination` — نفس المكوّن الذي يذيّل جدول
    «فواتير الشراء» و«سجلّ النشاطات»، فيتطابق شكل الشريط ونصّه في الشاشات.

    @param \Illuminate\Pagination\LengthAwarePaginator $returnsData
--}}
@include('components.custom-pagination', ['paginator' => $returnsData])
