{{--
    ترقيم صفحات «الإشعارات المدينة».

    يُفوَّض إلى `components.custom-pagination` — نفس المكوّن الذي يذيّل جدول
    «فواتير الشراء» و«سجلّ النشاطات»، فيتطابق شكل الشريط ونصّه في الشاشات.

    @param \Illuminate\Pagination\LengthAwarePaginator $noticesData
--}}
@include('components.custom-pagination', ['paginator' => $noticesData])
