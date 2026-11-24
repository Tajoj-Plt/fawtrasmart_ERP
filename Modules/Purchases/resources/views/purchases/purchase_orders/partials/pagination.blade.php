{{--
    ترقيم صفحات «أوامر الشراء» — المكوّن المشترك نفسه الذي يذيّل جدول شاشة
    «سجلّ النشاطات».
--}}
@include('components.custom-pagination', ['paginator' => $purchaseOrdersRequests])
