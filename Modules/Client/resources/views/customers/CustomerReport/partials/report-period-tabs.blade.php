{{--
    أزرارُ الفترات السريعة فوق شبكة الفلترة — بمقاس أزرار لوحة العملاء.

    تحمل الصنفَ `period-btn` و`data-period` كما كانت، فمعالجُ الشاشة
    (`changePeriod()`) يعمل بلا تعديل. الزرُّ المضغوط يُقرأ من `period`
    في الطلب.

    أسماءُ المعاملات مسبوقةٌ بـ`tab` كي لا يلتقط الـinclude متغيّراً بالاسم
    نفسِه من الشاشة — انظر رأس partials.report-pager.

    @param array|null  $tabPeriods  [قيمة => عنوان]، الافتراضُ يوم..سنة + مخصص
    @param string|null $tabCurrent  القيمةُ المضغوطة إن لم تكن `request('period')`
--}}
@php
    $ptPeriods = is_array($tabPeriods ?? null)
        ? $tabPeriods
        : ['1' => 'يوم', '7' => 'أسبوع', '30' => 'شهر', '90' => '3 أشهر', '180' => '6 أشهر', '365' => 'سنة', 'custom' => 'مخصص'];
    $ptCurrent = (string) ($tabCurrent ?? request('period', ''));
@endphp

<div class="ag-tabs" role="group" aria-label="فترات سريعة">
    @foreach ($ptPeriods as $ptKey => $ptLabel)
        <button type="button" class="ag-tab period-btn {{ $ptCurrent === (string) $ptKey ? 'is-active' : '' }}" data-period="{{ $ptKey }}">{{ $ptLabel }}</button>
    @endforeach
</div>
