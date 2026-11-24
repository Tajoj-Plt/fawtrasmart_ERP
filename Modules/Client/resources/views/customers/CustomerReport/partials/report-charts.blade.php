{{--
    أدواتُ الرسوم المشتركة لتقارير العملاء — تُستدعى في قسم scripts بعد Chart.js.

      agChartTheme()            ألوانُ «مظهر النظام» وخطُّه لقراءتها في الرسم (يتبع الوضعَ الداكن)
      agValueLabels(values)     إضافةٌ ترسم القيمةَ فوق كلّ عمود
      agPalette                 ألوانُ الشرائح/الفئات بالترتيب (من الأحسن إلى الأسوأ)
      agWhole(n) / agMoney(n)   تنسيقُ الأرقام: صحيحٌ بفواصل / بخانتين
--}}
@once
<script>
(function () {
    window.agPalette = ['#3b82f6', '#10b981', '#eab308', '#f97316', '#ef4444', '#8b5cf6', '#06b6d4', '#9f1239', '#64748b', '#84cc16'];

    window.agChartTheme = function () {
        var root = document.querySelector('.rpt') || document.body;
        var css = getComputedStyle(root);
        var theme = {
            ink: css.getPropertyValue('--rpt-ink').trim() || '#2f3349',
            muted: css.getPropertyValue('--rpt-muted').trim() || '#6e6b7b',
            line: css.getPropertyValue('--rpt-line').trim() || '#e8e6f0',
            surface: css.getPropertyValue('--rpt-surface').trim() || '#fff',
            brand: css.getPropertyValue('--rpt-brand').trim() || '#7367f0',
            font: css.fontFamily
        };

        if (typeof Chart !== 'undefined') {
            Chart.defaults.font.family = theme.font;
            Chart.defaults.color = theme.muted;
        }

        return theme;
    };

    var whole = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });
    window.agWhole = function (n) { return whole.format(parseFloat(n || 0)); };
    window.agMoney = function (n) {
        return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    window.agValueLabels = function (values, format) {
        var theme = window.agChartTheme();
        var fmt = format || window.agWhole;

        return {
            id: 'agValueLabels',
            afterDatasetsDraw: function (chart) {
                var ctx = chart.ctx;
                ctx.save();
                ctx.fillStyle = theme.ink;
                ctx.font = '700 11px ' + theme.font;
                ctx.textAlign = 'center';
                chart.getDatasetMeta(0).data.forEach(function (bar, i) {
                    if (values[i] > 0) ctx.fillText(fmt(values[i]), bar.x, bar.y - 6);
                });
                ctx.restore();
            }
        };
    };
})();
</script>
@endonce
