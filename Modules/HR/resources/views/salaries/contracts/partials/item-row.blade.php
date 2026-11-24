{{--
    صفُّ بندٍ في جدول المستحقات أو المستقطعات.

    @param  string  $kind          addition | deduction — يحدّد أسماء الحقول
    @param  \Illuminate\Support\Collection  $items  بنود الكتالوج من النوع نفسه
    @param  mixed   $selected      معرّف البند المختار (أو null لصفٍّ فارغ)
    @param  string|null  $selectedName  اسمه — لبندٍ ليس في الكتالوج (بند قالب أو نسخة العقد)
    @param  string|null  $formula
    @param  mixed   $amount

    الأسماءُ والأصناف (`item-select` و`calculation-input` و`amount-input`
    و`remove-row`) تقرؤها شيفرةُ الصفحة — لا تُغيَّر.
--}}
@php
    $selected = $selected ?? null;
    $inCatalog = $selected !== null && $selected !== '' && $items->contains('id', (int) $selected);
@endphp
<tr class="{{ $kind }}-row">
    <td class="align-middle text-center text-muted"><i class="feather icon-menu"></i></td>
    <td>
        <select class="form-control item-select" name="{{ $kind }}_type[]" data-type="{{ $kind }}">
            <option value="">اختر البند</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}"
                        data-calculation="{{ $item->calculation_formula }}"
                        data-amount="{{ $item->amount }}"
                        {{ $inCatalog && (int) $selected === (int) $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
            @endforeach
            @if (! $inCatalog && $selected)
                <option value="{{ $selected }}" data-calculation="{{ $formula ?? '' }}" data-amount="{{ $amount ?? '' }}" selected>{{ $selectedName ?? ('#'.$selected) }}</option>
            @endif
        </select>
    </td>
    <td>
        <input type="text" class="form-control calculation-input" dir="ltr"
               name="{{ $kind }}_calculation_formula[]" value="{{ $formula ?? '' }}"
               placeholder="مثال: basic * 0.25">
    </td>
    <td>
        <input type="text" class="form-control amount-input" dir="ltr" inputmode="decimal"
               name="{{ $kind }}_amount[]" value="{{ $amount ?? '' }}" placeholder="0.00">
    </td>
    <td class="align-middle text-center">
        <button type="button" class="btn btn-sm btn-outline-danger remove-row" title="حذف البند" aria-label="حذف البند">
            <i class="feather icon-trash-2"></i>
        </button>
    </td>
</tr>
