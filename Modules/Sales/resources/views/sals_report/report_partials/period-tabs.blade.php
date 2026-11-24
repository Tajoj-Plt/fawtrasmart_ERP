{{--
    أزرار الفترة.

    صارت روابطَ لا أزراراً تحسب التاريخ في المتصفّح: الرابط يحمل `period`
    وحده ويُسقط `date_from`/`date_to`، فيحسب الخادمُ المدى بنفس الدالة التي
    يحسب بها حين تأتي الفترة من صفحة الفهرس — فلا تختلف فترةٌ عن فترة بين
    الصفحتين، ولا يتغيّر ما اخترته عند الدخول.

    كان الحساب في JavaScript فيرسل الحقلين مع الطلب، والخادم يقدّم الحقلين
    على `period`؛ فإذا اختلّ حسابُ المتصفّح (فرقُ ساعةٍ أو منطقةٍ زمنية) بقي
    الزرّ مضيئاً على فترةٍ والبياناتُ لفترةٍ أخرى.

    «مخصص» وحدها تعتمد على حقلَي التاريخ، وأيّ تعديلٍ يدويٍّ عليهما ينقل
    الفترة إليها تلقائياً (في `report_partials.scripts`) فلا يبقى زرٌّ مضيءٌ
    يكذب على مدىً غيرِه.

    @param string|null $default  الفترة المعتمدة حين لا يرسل الطلب أي فترة (افتراضاً 30)
--}}
@php
    $rptDefaultPeriod = $default ?? '30';
    $rptCurrentPeriod = request('period') ?: $rptDefaultPeriod;

    $rptPeriods = [
        '1' => ['label' => __('sales_reports.periods.day'), 'icon' => 'calendar-day'],
        '7' => ['label' => __('sales_reports.periods.week'), 'icon' => 'calendar-week'],
        '30' => ['label' => __('sales_reports.periods.month'), 'icon' => 'calendar-alt'],
        '90' => ['label' => __('sales_reports.periods.three_months'), 'icon' => 'calendar'],
        '180' => ['label' => __('sales_reports.periods.six_months'), 'icon' => 'calendar'],
        '365' => ['label' => __('sales_reports.periods.year'), 'icon' => 'calendar-check'],
    ];

    // الرابط يحتفظ بكل فلترٍ آخر (الفرع، الموظف، التجميع، نوع العرض…)
    // ويُسقط التاريخين فقط، فتنتقل الفترةُ وحدها دون أن تُفقَد بقيةُ الشاشة.
    $rptCarry = collect(request()->query())
        ->except(['period', 'date_from', 'date_to', 'page'])
        ->all();
@endphp

{{-- بمظهر تقارير العملاء (`ag-tabs`)؛ الصنفُ `period-btn` والسماتُ باقيةٌ لسكربت التقارير --}}
<div class="ag-tabs" role="group" aria-label="فترات سريعة">
    @foreach ($rptPeriods as $key => $tab)
        <a href="{{ request()->url() }}?{{ http_build_query($rptCarry + ['period' => $key]) }}"
           data-period-value="{{ $key }}"
           class="ag-tab period-btn {{ (string) $rptCurrentPeriod === (string) $key ? 'is-active' : '' }}">{{ $tab['label'] }}</a>
    @endforeach

    <button type="button"
            class="ag-tab period-btn period-btn-custom {{ (string) $rptCurrentPeriod === 'custom' ? 'is-active' : '' }}"
            data-period="custom">{{ __('sales_reports.periods.custom') }}</button>
</div>

<input type="hidden" name="period" id="periodInput" value="{{ $rptCurrentPeriod }}">
