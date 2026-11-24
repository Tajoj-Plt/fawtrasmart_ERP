{{--
    خانةُ الضريبة الشاملة على المستند — قائمةٌ واحدةٌ أعلى المستند تُطبَّق على
    كلّ الأصناف بدل اختيار ضريبةٍ لكلّ سطر. تظهر فقط حين تُعلَّم ضريبةٌ «شاملةً»
    في «إعدادات الضريبة» ({@see \App\Models\TaxSitting::comprehensiveDefault()}).

    اختياريةٌ على المستند: «بدون ضريبة» تمسحها عن الكلّ. القيمةُ الافتراضية هي
    المعلَّمة، أو المحفوظةُ على المستند عند التعديل (يمرّرها البليد في
    `$documentTaxId`). مشتركٌ بين مستندات البيع والشراء.
--}}
@php $__ctxTax = \App\Models\TaxSitting::comprehensiveDefault(); @endphp

@if ($__ctxTax)
    @php
        $__ctxTaxes = \App\Models\TaxSitting::orderBy('name')->get();
        $__ctxSelected = old('document_tax_id', $documentTaxId ?? $__ctxTax->id);
    @endphp

    <div class="form-group" style="max-width:380px">
        <label for="document_tax_id" class="form-label" style="font-weight:600">
            <i class="feather icon-percent"></i> {{ __('settings.tax.document_field_label') }}
        </label>
        <select name="document_tax_id" id="document_tax_id" class="form-control">
            <option value="">{{ __('settings.tax.document_field_none') }}</option>
            @foreach ($__ctxTaxes as $__t)
                <option value="{{ $__t->id }}" @selected((string) $__ctxSelected === (string) $__t->id)>
                    {{ $__t->name }} ({{ rtrim(rtrim(number_format($__t->tax, 2), '0'), '.') }}%)
                </option>
            @endforeach
        </select>
        <small class="text-muted">{{ __('settings.tax.document_field_hint') }}</small>
    </div>
@endif
