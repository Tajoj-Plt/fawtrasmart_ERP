{{-- طباعة اتفاقية التقسيط في المتصفّح — لم يكن للقسم مطبوعةٌ ولا مسار. --}}

@include('sales::installments.partials.print-layout', ['agreement' => $agreement, 'autoPrint' => true])
