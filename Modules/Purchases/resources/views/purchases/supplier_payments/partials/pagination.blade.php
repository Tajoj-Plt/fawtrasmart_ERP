{{--
    ترقيم صفحات «مدفوعات الموردين».

    يُفوَّض إلى `components.custom-pagination` — نفس المكوّن الذي يذيّل جدول
    «فواتير الشراء» و«سجلّ النشاطات»، فيتطابق شكل الشريط ونصّه في الشاشات.

    الروابط صارت روابطَ صفحاتٍ عادية يلتقطها مستمع `.pagination a` في الشاشة،
    بدل نداء `window.loadDataWithPage()` مكتوبٍ في كل زرّ — وهو نداءٌ يسقط
    صامتاً متى لم تكن الدالّة معرَّفة.

    @param \Illuminate\Pagination\LengthAwarePaginator $payments
--}}
@include('components.custom-pagination', ['paginator' => $payments])
