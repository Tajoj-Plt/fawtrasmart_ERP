{{--
    صفّ صنفٍ واحد في نموذج التبديل.

    يُستعمل في موضعين: صفوفُ العملية المحفوظة تُطبع منه في الخادم، وقالبُ
    الصفّ الجديد في المتصفّح يُقرأ منه بعد استبدال `__I__` بالفهرس — فلا
    تتباعد نسختان من الترميز، ولا تبقى نصوصٌ عربية محشورةً في سلاسل
    JavaScript لا تمرّ بملفّ لغة.

    الوسائط:
      index     فهرس الصفّ — رقمٌ، أو `__I__` حين يُقرأ قالباً
      products  الأصناف المتاحة
      reasons   أسباب التبديل [id => label]
      item      البند المحفوظ (أو null للصفّ الفارغ)
--}}
@php
    $selectedProduct = $item->product_id ?? null;
    $selectedReason = $item->reason_id ?? null;
    $quantity = $item->quantity ?? 1;
    $reasonNotes = $item->reason_notes ?? null;
@endphp

<div class="sl-item" data-row>
    <div class="form-group mb-0">
        <label>{{ __('sales_exchanges.items.product') }} <span class="sl-req"></span></label>
        <select name="items[{{ $index }}][product_id]" class="form-control" required>
            <option value="">{{ __('sales_exchanges.items.product') }}</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected((string) $selectedProduct === (string) $product->id)>
                    {{ $product->name }}@if ($product->code) ({{ $product->code }})@endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group mb-0">
        <label>{{ __('sales_exchanges.items.quantity') }} <span class="sl-req"></span></label>
        <input type="number" min="1" step="1" required
               name="items[{{ $index }}][quantity]" class="form-control"
               value="{{ $quantity }}">
    </div>

    <div class="form-group mb-0">
        <label>{{ __('sales_exchanges.items.reason') }} <span class="sl-req"></span></label>
        <select name="items[{{ $index }}][reason]" class="form-control" required>
            <option value="">{{ __('sales_exchanges.items.reason') }}</option>
            @foreach ($reasons as $key => $label)
                <option value="{{ $key }}" @selected((string) $selectedReason === (string) $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <button type="button" class="btn btn-outline-danger sl-item__remove"
            title="{{ __('sales_exchanges.items.remove') }}"
            aria-label="{{ __('sales_exchanges.items.remove') }}">
        <i class="feather icon-trash-2"></i>
    </button>

    <div class="sl-item__notes">
        <input type="text" name="items[{{ $index }}][reason_notes]" class="form-control"
               value="{{ $reasonNotes }}"
               placeholder="{{ __('sales_exchanges.fields.notes') }}">
    </div>
</div>
