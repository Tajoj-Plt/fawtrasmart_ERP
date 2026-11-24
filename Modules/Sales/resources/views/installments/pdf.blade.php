{{-- معاينة الاتفاقية للحفظ PDF — نفس المصدر بلا فتح نافذة الطباعة. --}}

@include('sales::installments.partials.print-layout', ['agreement' => $agreement, 'autoPrint' => false])
