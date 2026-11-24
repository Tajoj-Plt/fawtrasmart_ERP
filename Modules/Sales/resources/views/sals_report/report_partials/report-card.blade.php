{{--
    بلاطة تقرير واحدة — على قياس بلاطات لوحة إعدادات المبيعات
    (`sales::sitting.index`، البادئة `sl-`) وبلاطات تقارير المخزون
    (`stock::report.index`، البادئة `stk-`)، بدل تصميم `checks-report-card`
    الخاص الذي كانت هذه الشاشة وحدها تحمله.

    الفترة تبقى صفَّ روابطَ صغيرة أسفل البلاطة (`sl-tile__kids`) بدل أن
    تضيع مع تبديل التصميم — نفس الفكرة التي كانت في `rpt-card-periods`
    سابقاً، بترميز البلاطات الموحَّد فقط.

    البلاطة `div` لا `a`: هي رابطٌ (العنوان) تحمل روابطَ أخرى (الفترات)،
    ورابطٌ داخل رابط ترميزٌ غير صالح — نفس حلّ `sales::sitting.index`.

    @param string      $title
    @param string      $hint
    @param string      $icon      اسم أيقونة FontAwesome بدون البادئة
    @param string      $route     اسم المسار
    @param array|null  $params    وسائط ثابتة تُضاف لكل روابط البطاقة
    @param bool|null   $periods   هل تُعرض روابط الفترة؟ (افتراضاً نعم)
--}}
@php
    $cardParams = $params ?? [];
    $cardShowPeriods = $periods ?? true;
    $cardPeriods = [
        '1' => ['label' => __('sales_reports.periods.today'), 'icon' => 'calendar-day'],
        '7' => ['label' => __('sales_reports.periods.this_week'), 'icon' => 'calendar-week'],
        '30' => ['label' => __('sales_reports.periods.this_month'), 'icon' => 'calendar-alt'],
        '365' => ['label' => __('sales_reports.periods.this_year'), 'icon' => 'calendar-check'],
    ];
@endphp

<div class="sl-tile">
    <span class="sl-tile__icon" aria-hidden="true">
        <i class="fas fa-{{ $icon }}"></i>
    </span>

    <div class="sl-tile__body">
        <p class="sl-tile__title">
            <a href="{{ route($route, $cardParams) }}" style="color:inherit;text-decoration:none">
                {{ $title }}
            </a>
        </p>

        <p class="sl-tile__hint">{{ $hint }}</p>

        @if ($cardShowPeriods)
            <div class="sl-tile__kids">
                @foreach ($cardPeriods as $key => $p)
                    <a class="sl-tile__kid" href="{{ route($route, $cardParams + ['period' => $key]) }}">
                        <i class="fas fa-{{ $p['icon'] }}"></i> {{ $p['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
