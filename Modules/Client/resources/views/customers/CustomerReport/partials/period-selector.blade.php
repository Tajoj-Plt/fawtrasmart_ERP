{{--
    مُنتقيا السنة المالية والفترة المحاسبية في تقارير العملاء.

    **لا يُنشئان فلترَ تاريخٍ ثالثاً.** التقاريرُ هنا تملك حقلَي «من/إلى»
    أصلاً، وإضافةُ مدىً ثالثٍ يتنازع معهما تجعل الشاشةَ تقول شيئاً ويقرأ
    الاستعلامُ غيرَه. فهذان يملآن الحقلين القائمَين: تختار «فبراير ٢٠٢٦»
    فيُكتب فيهما أوّلُ الشهر وآخرُه، وتختار «٢٠٢٥» فيُكتب أوّلُ السنة
    وآخرُها — ويبقى للمستخدم أن يعدّلهما يدوياً إن أراد مدىً لا يوافق
    فترةً ولا سنة.

    ══════════ لماذا أُضيفت السنةُ منتقىً مستقلّاً ══════════

    `reportPeriodContext()` كانت تقرأ `fiscal_year_id` منذ البداية، ولا
    شاشةَ ترسله: كان موضعُ السنة في القائمة خياراً معطَّلاً (`——`) لا
    يُختار. فمن أراد تقريرَ سنةٍ كاملةٍ لزمه أن يكتب حدَّيها بيده، أو
    يختار شهراً منها ويظنّ أنّه اختار السنة.

    والأولوية: فترةٌ مختارةٌ تغلب السنةَ، والسنةُ تغلب حقلَي التاريخ —
    وهي أولويةُ `reportPeriodContext()` نفسِها لا اجتهادٌ في الشاشة.

    والمقفلُ يُعرض ويُختار: الإقفال يمنع الكتابة لا القراءة، وتقريرُ سنةٍ
    أُقفلت هو أوّلُ ما يُطلب بعد إقفالها.

    @param array  $periodContext  خرجُ RespectsAccountingPeriods::reportPeriodContext()
    @param string $startId        معرّف حقل «من تاريخ» في الشاشة
    @param string $endId          معرّف حقل «إلى تاريخ» في الشاشة
--}}

@php
    $ctx = $periodContext ?? [];
    $pkStart = $startId ?? 'from_date';
    $pkEnd = $endId ?? 'to_date';
    $pkYears = $ctx['fiscal_years'] ?? collect();
    $pkPeriods = $ctx['periods'] ?? collect();
    $pkSelectedPeriod = (string) request('accounting_period_id', '');
    $pkSelectedYear = (string) request('fiscal_year_id', '');

    $pkStatusText = [
        'open' => 'مفتوحة',
        'closed' => 'مغلقة',
        'locked' => 'مقفلة نهائياً',
    ];

    /* عرضُ العمود والعناوين اختياريان: شاشةٌ تصفُّ حقولَها أربعةً في السطر
       تمرّرهما فتستوي حقولُها مع بقيّة الشبكة، وشاشةٌ لها تخطيطُها القديم
       تتركهما كما كانا. */
    $pkCols = $cols ?? 'col-lg-2 col-md-6 col-6';
    $pkYearTitle = $yearTitle ?? null;
    $pkPeriodTitle = $periodTitle ?? null;
@endphp

<div class="{{ $pkCols }} rpt-field">
    @if ($pkYearTitle)
        <label for="fiscal_year_id">{{ $pkYearTitle }}</label>
    @endif
    <select name="fiscal_year_id" id="fiscal_year_id" class="form-control rpt-select2 rpt-period-picker"
            data-start="{{ $pkStart }}" data-end="{{ $pkEnd }}" data-placeholder="كل السنوات المالية">
        <option value="">كل السنوات المالية</option>
        @foreach ($pkYears as $year)
            <option value="{{ $year->id }}"
                data-from="{{ \Illuminate\Support\Carbon::parse($year->start_date)->format('Y-m-d') }}"
                data-to="{{ \Illuminate\Support\Carbon::parse($year->end_date)->format('Y-m-d') }}"
                {{ $pkSelectedYear === (string) $year->id ? 'selected' : '' }}>
                {{ $year->name }}@if ($year->status !== 'open') — {{ $pkStatusText[$year->status] ?? $year->status }}@endif
            </option>
        @endforeach
    </select>
</div>

<div class="{{ $pkCols }} rpt-field">
    @if ($pkPeriodTitle)
        <label for="accounting_period_id">{{ $pkPeriodTitle }}</label>
    @endif
    <select name="accounting_period_id" id="accounting_period_id" class="form-control rpt-select2 rpt-period-picker"
            data-start="{{ $pkStart }}" data-end="{{ $pkEnd }}" data-placeholder="كل الفترات المحاسبية">
        <option value="">كل الفترات المحاسبية</option>

        @foreach ($pkYears as $year)
            <optgroup label="{{ $year->name }}{{ $year->status === 'closed' ? ' (سنة مقفلة)' : '' }}">
                @foreach ($pkPeriods->where('fiscal_year_id', $year->id) as $period)
                    <option value="{{ $period->id }}"
                        data-from="{{ \Illuminate\Support\Carbon::parse($period->start_date)->format('Y-m-d') }}"
                        data-to="{{ \Illuminate\Support\Carbon::parse($period->end_date)->format('Y-m-d') }}"
                        {{ $pkSelectedPeriod === (string) $period->id ? 'selected' : '' }}>
                        {{ $period->name }}
                        @if ($period->status !== 'open')
                            — {{ $pkStatusText[$period->status] ?? $period->status }}
                        @endif
                    </option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
</div>

<script>
(function () {
    var pickers = document.querySelectorAll('.rpt-period-picker');
    if (!pickers.length) return;

    function applyRange(sel) {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.getAttribute('data-from')) return;

        var from = document.getElementById(sel.dataset.start);
        var to = document.getElementById(sel.dataset.end);

        if (from) { from.value = opt.getAttribute('data-from'); from.dispatchEvent(new Event('change', {bubbles: true})); }
        if (to) { to.value = opt.getAttribute('data-to'); to.dispatchEvent(new Event('change', {bubbles: true})); }
    }

    pickers.forEach(function (sel) {
        if (sel.dataset.bound) return;
        sel.dataset.bound = '1';

        sel.addEventListener('change', function () {
            applyRange(sel);

            /* اختيارُ سنةٍ يُفرغ الفترة، واختيارُ فترةٍ يُفرغ السنة: هما
               وجهان لسؤالٍ واحد، ولو بقيا معاً لغلبت الفترةُ في المتحكّم
               بينما تُظهر الشاشةُ السنةَ مختارةً — فتقول شيئاً ويُقرأ غيرُه. */
            var other = sel.id === 'fiscal_year_id'
                ? document.getElementById('accounting_period_id')
                : document.getElementById('fiscal_year_id');

            if (other && other.value) {
                other.value = '';
                if (window.jQuery && window.jQuery(other).data('select2')) {
                    window.jQuery(other).trigger('change.select2');
                }
            }
        });

        if (window.jQuery) { window.jQuery(sel).on('select2:select', function () { applyRange(sel); }); }
    });
})();
</script>
