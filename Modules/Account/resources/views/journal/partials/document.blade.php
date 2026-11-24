{{--
    ورقةُ القيد — المتنُ وحده بلا غلافِ مستند.

    ═══════════════ لماذا انقسم الملفّ ═══════════════

    كان `journal/pdf.blade.php` **مستندَ HTML كاملاً**: `<!DOCTYPE html>`
    و`<html>` و`<head>` و`<style>` و`<body>`. وهو صحيحٌ حين يُطبع وحده،
    وكارثةٌ حين يُضمّ في صفحة — وقد كان يُضمّ في `journal/show.blade.php`.

    فتخرج الصفحةُ وفيها **وسمَا `<body>`** ووسما `<html>`. ويُسقط المتصفّح
    الوسومَ المكرَّرة **ويُبقي محتوى `<style>`** — فتسري قواعدُ الورقة على
    الصفحة كلِّها:

        body  { font-family: 'Cairo'; padding: 20px; background: #d3d9e1 }
        table { width: 100%; background: #fff }
        th, td { border: 1px solid #aaa; padding: 10px }

    قواعدُ **بلا صنف** تُصيب جسمَ الصفحة وكلَّ جدولٍ فيها. ولأنّ الورقةَ
    تُحقن بعد أنماط النظام تغلبها عند تساوي الترجيح — فينكسر الشريطُ
    الجانبي والجداولُ في صفحة عرض القيد وحدها.

    ═══════════════ والقاعدة ═══════════════

    المتنُ هنا، والغلافُ في [pdf.blade.php] لمن يطبع. وكلُّ قاعدةٍ مقصورةٌ
    على `.jed` فلا تخرج من الورقة إلى الصفحة.
--}}
@php
    $total_debit = 0;
    $total_credit = 0;

    // مصدرُ السطور: مجموعةٌ مُمرَّرة، أو تفاصيلُ القيد
    $rows = is_iterable($entry) && ! is_object($entry) ? $entry : ($entry->details ?? []);
@endphp

<div class="jed">
    <div class="jed__head">
        <h2 class="jed__title">
            قيد يومية <span class="jed__num">#{{ $entry->id ?? '' }}</span>
        </h2>

        <div class="jed__info">
            <p>التاريخ: {{ $entry->date ?? '' }}</p>
            <p>الوصف: {{ $entry->description ?? '' }}</p>

            {{-- نوعُ القيد نصّاً لا وسماً ملوّناً: الورقةُ تُطبَع غالباً بالأبيض والأسود --}}
            @if (! empty($entry->is_taxable))
                <p><strong>قيد ضريبي</strong></p>
            @endif
        </div>
    </div>

    <table class="jed__table">
        <thead>
            <tr>
                <th colspan="2">الحساب</th>
                <th>مدين</th>
                <th>دائن</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($rows as $item)
                @php
                    $total_debit += $item->debit ?? 0;
                    $total_credit += $item->credit ?? 0;
                @endphp

                <tr>
                    <td>{{ $item->account->code ?? '' }}</td>
                    <td>{{ $item->account->name ?? '' }}</td>
                    <td>{{ isset($item->debit) ? number_format($item->debit, 2) : '' }}</td>
                    <td>{{ isset($item->credit) ? number_format($item->credit, 2) : '' }}</td>
                </tr>
            @endforeach

            <tr class="jed__total">
                <td colspan="2">الإجمالي</td>
                <td>@money($total_debit)</td>
                <td>@money($total_credit)</td>
            </tr>
        </tbody>
    </table>
</div>

{{--
    `@once` تمنع تكرارَ النمط لو ضُمّت الورقةُ مرّتين في صفحةٍ واحدة —
    كصفحةٍ تعرض قيدين.
--}}
@once
<style>
    /* كلُّ قاعدةٍ تحت `.jed` — لا `body` ولا `table` عاريتين */
    .jed {
        direction: rtl;
        font-family: 'Cairo', sans-serif;
        font-size: 14px;
        color: #1B2437;
        text-align: right;
    }

    .jed__head { margin-bottom: 20px; }
    .jed__title { margin: 0; font-size: 20px; }
    .jed__num { font-size: 18px; color: #000; }

    .jed__info { margin-bottom: 10px; }
    .jed__info p { margin: 5px 0; font-size: 14px; color: #333; }

    .jed__table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: #fff;
    }

    .jed__table th {
        background: #e8e8e8;
        border: 1px solid #aaa;
        padding: 10px;
        font-size: 14px;
        text-align: center;
    }

    .jed__table td {
        border: 1px solid #aaa;
        padding: 10px;
        font-size: 14px;
        text-align: right;
    }

    .jed__total td { font-weight: bold; background: #f4f4f4; color: #000; }
</style>
@endonce
