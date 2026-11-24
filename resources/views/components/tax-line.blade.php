{{--
    سطرُ ضريبةٍ في تفاصيل السند — للعرض والطباعة وPDF.

    كانت الشاشةُ تطبع `{{ $expense->tax1 }}%` — أي **معرّف** الضريبة على
    أنه نسبتُها، فيُقرأ «1%» لضريبةٍ نسبتُها 15%. والنسبةُ في سجلّ الضريبة
    المرتبط، والمبلغُ المحسوبُ مخزَّنٌ في السند.

    والطريقةُ تُقال مع النسبة: «شاملة» تعني أنّ ما أُدخل يحملها فرُدّ إلى
    وعائه، و«مضافة» تعني أنها فوقه. والقارئُ لا يعرف أيَّهما إلا أن يُقال.

    @props:
      tax     سجلُّ الضريبة (TaxSitting) أو null
      amount  المبلغ المحسوب
      money   هل يُنسَّق المبلغ بعملة النظام؟ (false في الطباعة الحرارية)
      doc     المستند — لتنسيق العملة بعملته
--}}
@props([
    'tax' => null,
    'amount' => 0,
    'money' => true,
    'doc' => null,
])

@php
    $rate = $tax?->tax;
    // النسبةُ تُخزَّن كسراً (0.15) أو مئويةً (15) — كلتاهما تُعرض مئويةً
    $percent = $rate === null ? null : ($rate < 1 ? $rate * 100 : $rate);
@endphp

@if ($tax)
    {{ $tax->name ?? 'ضريبة' }}
    @if ($percent !== null)
        ({{ rtrim(rtrim(number_format($percent, 2), '0'), '.') }}%)
    @endif
    <span class="text-muted">{{ \App\Support\TaxCalculator::typeLabel($tax->type) }}</span>
    —
    @if ($money)
        {{ \App\Support\CurrencySettings::format($amount ?? 0) }}
    @else
        {{ number_format($amount ?? 0, 2) }}
    @endif
@else
    —
@endif
