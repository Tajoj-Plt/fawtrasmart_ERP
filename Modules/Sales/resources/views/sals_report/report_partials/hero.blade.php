{{--
    ترويسة شاشة التقرير — العنوان ووصفه وشارتا الفترة والعملة.

    @param string      $icon         صنف أيقونة Font Awesome بلا البادئة
    @param string      $title        عنوان التقرير
    @param string      $subtitle     سطر يشرح ما تعنيه أرقامه
    @param string|null $periodLabel  وصف الفترة بتاريخيها
    @param string|null $extra        شارة إضافية اختيارية
    @param string|null $guideKey     مفتاح تذكّر لوحة الإرشاد لهذه الشاشة

    ولوحةُ الإرشاد هنا لا في كلّ شاشةٍ على حدة: خمسُ شاشاتٍ تشترك في هذه
    الترويسة، وما يُقال عن قراءة تقارير المبيعات واحدٌ فيها كلِّها — فكتابتُه
    خمس مرّات يعني خمسَ صياغاتٍ تتباعد مع أوّل تصحيح.

    و`guideKey` يبقى مختلفاً لكلّ شاشة: من طوى الشرح في تقريرٍ أتقنه قد
    يظلّ يحتاجه في غيره.
--}}
@php
    use App\Support\CurrencySettings;
@endphp

{{-- الأنماط من أجل `.sl-guide` وحدها؛ شاشاتُ التقارير على ورقة `rpt-*`
     الخاصّة بها ولا تتقاطع معها في محدِّد --}}
@include('sales::partials.settings-theme')
@include('components.hint-tips')

<div class="no-print">
    @include('sales::components.guide', [
        'key' => $guideKey ?? 'sales-reports.screen',
        'title' => __('sales_reports.guide.hub.title'),
        'lead' => __('sales_reports.guide.hub.lead'),
        'open' => false,
        'blocks' => [
            [
                __('sales_reports.guide.hub.a'),
                __('sales_reports.guide.hub.b'),
                __('sales_reports.guide.hub.c'),
                __('sales_reports.guide.hub.d'),
            ],
        ],
    ])
</div>

<div class="rpt-hero no-print">
    <div>
        <h1><i class="fa fa-{{ $icon }}"></i> {{ $title }}</h1>
        <p>{{ $subtitle }}</p>
    </div>
    <div class="rpt-hero__meta">
        @if (! empty($periodLabel))
            <span class="rpt-chip"><i class="fa fa-calendar"></i> {{ $periodLabel }}</span>
        @endif
        @if (! empty($extra))
            <span class="rpt-chip"><i class="fa fa-info-circle"></i> {{ $extra }}</span>
        @endif
        <span class="rpt-chip"><i class="fa fa-coins"></i> {{ CurrencySettings::name() }}</span>
    </div>
</div>
