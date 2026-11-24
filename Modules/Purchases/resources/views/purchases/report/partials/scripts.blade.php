{{--
    سكربت تقارير المشتريات المشترك — select2 والطباعة والرسوم.

    كانت كل شاشة تكرّر هذه الأسطر بنسختها، وتُلوّن رسومها بأرقام مكتوبة
    (`#7367f0`) لا تتبع «مظهر النظام». هنا مصدرٌ واحد يقرأ اللون من
    متغيّرات المظهر، ويُنسّق الأرقام بكسور العملة المضبوطة.

    @param array|null $trend  {labels, purchases, payments} — يرسم مخطط الاتجاه
    @param array|null $bars   [{name, value}] — يرسم مخطط الأعلى
    @param string|null $barsLabel  اسم سلسلة مخطط الأعلى
--}}
@php
    use App\Support\CurrencySettings;
@endphp

<script>
    $(function () {
        if ($.fn.select2) { $('.rpt-select2').select2({ dir: 'rtl', width: '100%' }); }

        /*
         * الطباعة تطبع الصفحة نفسها بقواعد `@media print`، لا نافذةً جديدة
         * يُكتب فيها HTML بـ JavaScript. النوافذ القديمة كانت تفقد ترويسة
         * المنشأة والشعار ورمز العملة، وتُعيد بناء الجدول يدوياً فتسقط منه
         * الأعمدة كلّما أُضيف عمود إلى الشاشة.
         */
        $('#rptPrintAction, #printBtn').on('click', function () { window.print(); });

        // ألوان الرسوم من مظهر النظام، فيتبع الرسمُ اللونَ الأساسي المضبوط
        var css = getComputedStyle(document.documentElement);
        var brand = (css.getPropertyValue('--app-primary') || '#7367f0').trim();
        var ok = (css.getPropertyValue('--app-success') || '#28c76f').trim();

        var decimals = {{ CurrencySettings::decimals() }};
        var fmt = function (v) {
            return Number(v).toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        };

        var trend = @json($trend ?? null);
        var bars = @json($bars ?? null);

        if (window.ApexCharts && trend && trend.labels && trend.labels.length
            && document.querySelector('#rptTrendChart')) {
            new ApexCharts(document.querySelector('#rptTrendChart'), {
                chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [
                    { name: 'المشتريات', data: trend.purchases },
                    { name: 'المدفوعات', data: trend.payments }
                ],
                xaxis: { categories: trend.labels },
                colors: [brand, ok],
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
                legend: { position: 'top', horizontalAlign: 'right' },
                tooltip: { y: { formatter: fmt } }
            }).render();
        }

        if (window.ApexCharts && bars && bars.length && document.querySelector('#rptTopChart')) {
            new ApexCharts(document.querySelector('#rptTopChart'), {
                chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{ name: @json($barsLabel ?? 'القيمة'), data: bars.map(function (b) { return b.value; }) }],
                xaxis: { categories: bars.map(function (b) { return b.name; }) },
                plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
                colors: [brand],
                dataLabels: { enabled: false },
                tooltip: { y: { formatter: fmt } }
            }).render();
        }
    });
</script>
