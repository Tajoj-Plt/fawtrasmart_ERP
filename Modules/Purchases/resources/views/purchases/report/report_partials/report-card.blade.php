{{--
    بلاطة تقرير واحدة — نسخة عن نظيرتها في وحدة المبيعات
    (`Modules/Sales/resources/views/sals_report/report_partials/report-card.blade.php`)
    بنفس بادئة `sl-*` المستنسخة هنا في `partials/sales-theme.blade.php`، بدل
    البطاقات الخمس المتماثلة (التقرير، ثم اليوم والأسبوع والشهر والسنة) التي
    كانت هذه الشاشة تكتبها يدوياً لكل تقرير.

    الفترة تبقى صفَّ روابطَ صغيرة أسفل البلاطة (`sl-tile__kids`) بدل أن
    تضيع مع تبديل التصميم.

    البلاطة `div` لا `a`: هي رابطٌ (العنوان) تحمل روابطَ أخرى (الفترات)،
    ورابطٌ داخل رابط ترميزٌ غير صالح.

    @param string      $title
    @param string      $hint
    @param string      $icon      اسم أيقونة FontAwesome بدون البادئة
    @param string      $route     اسم المسار
    @param array|null  $params    وسائط ثابتة تُضاف لكل روابط البطاقة
    @param bool|null   $periods   هل تُعرض روابط الفترة؟ (افتراضاً لا)
--}}
@php
    $cardParams = $params ?? [];
    $cardShowPeriods = $periods ?? false;
    $cardPeriods = [
        '1' => ['label' => 'اليوم', 'icon' => 'calendar-day'],
        '7' => ['label' => 'الأسبوع', 'icon' => 'calendar-week'],
        '30' => ['label' => 'الشهر', 'icon' => 'calendar-alt'],
        '365' => ['label' => 'السنة', 'icon' => 'calendar-check'],
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
