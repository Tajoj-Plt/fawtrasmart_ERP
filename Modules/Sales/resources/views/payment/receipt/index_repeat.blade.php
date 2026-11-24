{{--
    اختيار قالب سند القبض.

    `?type=thermal` يعطي الإيصال الحراريّ (٨٠ ملم)، وما عداه يعطي سند
    A4. أمّا `?template=<معرّف>` فيصيّره المتحكّم من «قوالب المستندات»
    ولا يصل هذا الملفّ أصلاً.
--}}
@if (request()->query('type') === 'thermal')
    @include('sales::payment.receipt.pdf_receipt', ['receipt' => $receipt])
@else
    @include('sales::payment.receipt.pdf_repeatA4', ['receipt' => $receipt])
@endif
