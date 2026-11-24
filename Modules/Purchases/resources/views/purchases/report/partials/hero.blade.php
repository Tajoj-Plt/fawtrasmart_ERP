{{--
    ترويسة شاشة التقرير — العنوان ووصفه وشارتا الفترة والعملة.

    كانت كل شاشة تكتب هذه الكتلة بنفسها، فاختلفت المسافات والشارات بينها.
    مصدرٌ واحد الآن، والعملة تُقرأ من شاشة «العملات» لا مكتوبةً في القالب.

    @param string $icon      صنف أيقونة Font Awesome بلا البادئة
    @param string $title     عنوان التقرير
    @param string $subtitle  سطر يشرح ما تعنيه أرقامه
    @param mixed  $period    ReportPeriod — تُحذف الشارة إن لم تُمرَّر
    @param string|null $extra  شارة إضافية اختيارية
--}}
@php
    use App\Support\CurrencySettings;
@endphp

<div class="rpt-hero no-print">
    <div>
        <h1><i class="fa fa-{{ $icon }}"></i> {{ $title }}</h1>
        <p>{{ $subtitle }}</p>
    </div>
    <div class="rpt-hero__meta">
        @isset($period)
            <span class="rpt-chip"><i class="fa fa-calendar"></i> {{ $period->labelWithDates() }}</span>
        @endisset
        @if (! empty($extra))
            <span class="rpt-chip"><i class="fa fa-info-circle"></i> {{ $extra }}</span>
        @endif
        <span class="rpt-chip"><i class="fa fa-coins"></i> {{ CurrencySettings::name() }}</span>
    </div>
</div>
