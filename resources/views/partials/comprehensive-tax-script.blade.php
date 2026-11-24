{{--
    تكوينُ الضريبة الشاملة للـ JS + تحميل حاسبتها. يُضمَّن بعد حاسبة العائلة
    (invoice-calculator / sales-document-form / purchase-orders-form). لا يُخرِج
    شيئاً حين لا ضريبةَ معلَّمةً «شاملةً». انظر `public/assets/js/comprehensive-tax.js`.
--}}
@php $__ctxTax = \App\Models\TaxSitting::comprehensiveDefault(); @endphp

@if ($__ctxTax)
    @php
        $__ctxMap = \App\Models\TaxSitting::get(['id', 'tax', 'type'])->mapWithKeys(fn ($t) => [
            $t->id => [
                'rate' => (float) $t->tax,
                'type' => $t->type === 'included' ? 'included' : 'excluded',
            ],
        ]);
    @endphp

    <script>
        window.comprehensiveTax = {
            enabled: true,
            fieldName: 'document_tax_id',
            taxes: @json($__ctxMap)
        };
    </script>

    @php $__ctxJsV = @filemtime(public_path('assets/js/comprehensive-tax.js')) ?: time(); @endphp
    <script src="{{ asset('assets/js/comprehensive-tax.js') }}?v={{ $__ctxJsV }}"></script>
@endif
