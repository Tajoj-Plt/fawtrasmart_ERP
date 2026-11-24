{{--
    صفُّ بندٍ في نموذج الخطّة.

    يُصيَّر مرّتين: لكلّ بندٍ قائم، وداخل `<template>` بصفٍّ فارغ يُنسخ عند
    «إضافة بند». فالصفُّ المُنشأ في المتصفّح هو نفسُه المُصيَّر في الخادم —
    لا سلسلةَ HTML في السكربت تتخلّف عن القالب كلّما أُضيف حقل.

    $row  مصفوفةُ قيمٍ أو null للصفّ الفارغ
--}}
@php
    $row = $row ?? [];
    $generatedOrder = $row['order'] ?? null;
@endphp

@if ($generatedOrder)
    {{--
        بندٌ وُلِّد أمرُه — يُعرَض ولا يُرسَل: **لا حقلَ فيه البتّة**.

        الحقلُ `disabled` لا يُرسَل مع النموذج بينما `item_id[]` المخفيّ
        يُرسَل؛ فصفٌّ مقفَلٌ بحقولٍ معطَّلة كان سيُزيح `product_id[]` عن
        `item_id[]` بفهرسٍ واحد، فيُنسَب معرّفُ بندٍ إلى بيانات بندٍ آخر —
        كتابةٌ صامتة في الصفّ الخطأ. والحارسُ الخادميّ في `syncItems` قائم.
    --}}
    <tr class="table-light">
        <td>{{ $row['product_name'] ?? '—' }}</td>
        <td>{{ $row['material_name'] ?? '—' }}</td>
        <td>{{ $row['path_name'] ?? '—' }}</td>
        <td class="text-center stk-num">{{ $row['planned_quantity'] ?? '—' }}</td>
        <td><span class="stk-media__sub">{{ ($row['notes'] ?? null) ?: '—' }}</span></td>
        <td class="text-center">
            <span class="stk-state stk-state--on" title="وُلِّد أمرُ هذا البند فلا يُعدَّل">
                <i class="feather icon-lock"></i>
            </span>
        </td>
    </tr>
@else
    <tr>
        <td>
            {{--
                خانةٌ فارغة للبند الجديد تحفظ التوازي بين المصفوفات:
                `product_id[]` و`item_id[]` يُقرآن بالفهرس نفسه في `syncItems`.
            --}}
            <input type="hidden" name="item_id[]" value="{{ $row['id'] ?? '' }}">

            <select name="product_id[]" required class="form-control form-control-sm js-product">
                <option value="">اختر المنتج…</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}"
                        {{ ($row['product_id'] ?? null) == $product->id ? 'selected' : '' }}>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
        </td>

        <td>
            <select name="production_material_id[]" required class="form-control form-control-sm js-material">
                <option value="">اختر قائمة المواد…</option>
                @foreach ($materials as $material)
                    <option value="{{ $material->id }}"
                        {{ ($row['production_material_id'] ?? null) == $material->id ? 'selected' : '' }}>
                        {{ $material->name }}
                    </option>
                @endforeach
            </select>
            <span class="stk-help js-factor-hint"></span>
        </td>

        <td>
            <select name="production_path_id[]" required class="form-control form-control-sm">
                <option value="">اختر المسار…</option>
                @foreach ($paths as $path)
                    <option value="{{ $path->id }}"
                        {{ ($row['production_path_id'] ?? null) == $path->id ? 'selected' : '' }}>
                        {{ $path->name }}
                    </option>
                @endforeach
            </select>
        </td>

        <td>
            {{-- `step="any"`: الكميّاتُ `DECIMAL(18,4)`، و`step="1"` يمنع ٢٫٥ كجم في المتصفّح --}}
            <input type="number" name="planned_quantity[]" required step="any" min="0.0001"
                   class="form-control form-control-sm text-center stk-num js-quantity"
                   value="{{ $row['planned_quantity'] ?? '' }}">
        </td>

        <td>
            <input type="text" name="item_notes[]" maxlength="500" class="form-control form-control-sm"
                   value="{{ $row['notes'] ?? '' }}">
        </td>

        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger js-remove" aria-label="حذف البند">
                <i class="feather icon-trash-2"></i>
            </button>
        </td>
    </tr>
@endif
