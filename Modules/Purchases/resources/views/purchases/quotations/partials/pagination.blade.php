{{--
    ترقيم صفحات «طلبات عروض الأسعار» — يُفوَّض إلى المكوّن المشترك نفسه
    الذي يذيّل جدول شاشة «سجلّ النشاطات»، فيتطابق الشريط في الشاشتين.
--}}
@include('components.custom-pagination', ['paginator' => $purchaseQuotation])
