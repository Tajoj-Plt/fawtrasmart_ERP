{{--
    ترقيمُ صفحات جدول التقرير في المتصفّح — يوضع تحت `.rpt-scroll` داخل بطاقة الجدول.

    يُخفي الصفوفَ بالصنف `ag-hidden` لا بخاصية العرض: أداةُ التصدير
    (`stkReportTools`) تتخطّى الصفَّ المخفيَّ بالخاصية وحدَها، فيبقى
    التصديرُ والطباعةُ للجدول كلِّه لا للصفحة المعروضة.

    **أسماءُ المعاملات مسبوقةٌ بـ`pager`** عمداً: الـinclude يرث متغيّراتِ
    الشاشة، وكان المعاملُ `$rows` يلتقط `$rows` الشاشةِ (مجموعةَ الفواتير)
    فتُطبع ١٧٢٤ فاتورةً JSON داخل سمةٍ (٢٫٤ ميغابايت) ويتعطّل الترقيم.

    @param string      $pagerId       بادئةُ المعرّفات (الافتراض `ag`) — جدولان في شاشةٍ = بادئتان
    @param string      $pagerRows     محدِّدُ الصفوف المرقَّمة (الافتراض `#reportTableBody tr[data-row]`)
    @param string      $pagerUnit     اسمُ الوحدة في «عرض 1 – 25 من أصل N …» (فاتورة، عميل، دفعة…)
    @param string      $pagerStorage  مفتاحُ تذكّر حجم الصفحة في المتصفّح
    @param string|null $pagerScroll   عنصرٌ يُمرَّر إليه إن خرج أعلاه عن الشاشة عند تغيير الصفحة

    صفٌّ له صفوفُ تفاصيل تحته يحمل `data-row-key="…"`، وصفوفُ تفاصيله
    `data-parent-row="…"` (بلا `data-row`) — فتُخفى وتظهر معه ولا تُعدّ.

    شاشةٌ تبني صفوفَها بجافاسكربت بعد التحميل تنادي `agRefreshPager('#agPagerBar')`.
--}}
@php
    $pgId = is_string($pagerId ?? null) ? $pagerId : 'ag';
    $pgRows = is_string($pagerRows ?? null) ? $pagerRows : '#reportTableBody tr[data-row]';
    $pgUnit = is_string($pagerUnit ?? null) ? $pagerUnit : 'صف';
    $pgStorage = is_string($pagerStorage ?? null) ? $pagerStorage : 'agReportPageSize';
    $pgScroll = is_string($pagerScroll ?? null) ? $pagerScroll : '';
@endphp

<div class="ag-pager no-print" id="{{ $pgId }}PagerBar" hidden
     data-rows="{{ $pgRows }}"
     data-unit="{{ $pgUnit }}"
     data-storage="{{ $pgStorage }}"
     data-scroll="{{ $pgScroll }}">
    <span class="ag-pager__info"></span>
    {{-- اختيارُ عدد الصفوف فوق الجدول: partials.report-page-size بالبادئة نفسِها --}}
    <nav class="ag-pages" aria-label="صفحات الجدول"></nav>
</div>

@once
<script>
(function () {
    function pageList(total, current) {
        var wanted = [1, total, current - 1, current, current + 1]
            .filter(function (p) { return p >= 1 && p <= total; })
            .filter(function (p, i, all) { return all.indexOf(p) === i; })
            .sort(function (a, b) { return a - b; });
        var out = [];
        wanted.forEach(function (p, i) {
            if (i > 0 && p - wanted[i - 1] > 1) out.push(null);
            out.push(p);
        });
        return out;
    }

    function setup(bar) {
        if (bar.__agPager) return bar.__agPager;

        var pager = bar.querySelector('.ag-pages');
        var info = bar.querySelector('.ag-pager__info');
        // اختيارُ الحجم في رأس الجدول (partials.report-page-size) — مربوطٌ بالبادئة
        var sizeSelect = document.getElementById(bar.id.replace(/PagerBar$/, 'PageSize'));
        var storage = bar.getAttribute('data-storage');
        var state = { rows: [], size: 25, page: 1 };

        try {
            var saved = localStorage.getItem(storage);
            if (saved !== null && ['0', '25', '50', '100'].indexOf(saved) !== -1) state.size = parseInt(saved, 10);
        } catch (e) {}
        if (sizeSelect) sizeSelect.value = String(state.size);

        function button(html, target, disabled, active, label) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ag-page' + (active ? ' is-active' : '');
            b.innerHTML = html;
            b.disabled = !!disabled;
            b.dataset.page = String(target);
            if (label) b.setAttribute('aria-label', label);
            if (active) b.setAttribute('aria-current', 'page');
            pager.appendChild(b);
        }

        function render() {
            var rows = state.rows;
            var size = state.size;
            var total = size ? Math.max(1, Math.ceil(rows.length / size)) : 1;
            state.page = Math.min(Math.max(1, state.page), total);
            var from = size ? (state.page - 1) * size : 0;
            var to = size ? Math.min(rows.length, from + size) : rows.length;

            rows.forEach(function (tr, i) {
                var hide = i < from || i >= to;
                tr.classList.toggle('ag-hidden', hide);
                var key = tr.getAttribute('data-row-key');
                if (key) {
                    document.querySelectorAll('tr[data-parent-row="' + key + '"]').forEach(function (child) {
                        child.classList.toggle('ag-hidden', hide);
                    });
                }
            });

            bar.hidden = rows.length === 0;
            info.textContent = 'عرض ' + (rows.length ? from + 1 : 0) + ' – ' + to + ' من أصل ' + rows.length + ' ' + bar.getAttribute('data-unit');

            pager.innerHTML = '';
            if (total <= 1) return;

            button('<i class="fas fa-chevron-right"></i>', state.page - 1, state.page === 1, false, 'الصفحة السابقة');
            pageList(total, state.page).forEach(function (p) {
                if (p === null) {
                    var gap = document.createElement('span');
                    gap.className = 'ag-page ag-page--gap';
                    gap.textContent = '…';
                    pager.appendChild(gap);
                } else {
                    button(String(p), p, false, p === state.page);
                }
            });
            button('<i class="fas fa-chevron-left"></i>', state.page + 1, state.page === total, false, 'الصفحة التالية');
        }

        pager.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-page]');
            if (!b || b.disabled) return;
            state.page = parseInt(b.dataset.page, 10);
            render();
            var target = bar.getAttribute('data-scroll') ? document.querySelector(bar.getAttribute('data-scroll')) : null;
            if (target && target.getBoundingClientRect().top < 0) target.scrollIntoView({ block: 'start' });
        });

        if (sizeSelect) {
            sizeSelect.addEventListener('change', function () {
                state.size = parseInt(this.value, 10) || 0;
                state.page = 1;
                try { localStorage.setItem(storage, String(state.size)); } catch (e) {}
                render();
            });
        }

        bar.__agPager = {
            refresh: function () {
                state.rows = Array.prototype.slice.call(document.querySelectorAll(bar.getAttribute('data-rows')));
                state.page = 1;
                render();
            }
        };

        return bar.__agPager;
    }

    /** يعيد قراءةَ الصفوف ويعرض الصفحةَ الأولى — لشاشةٍ تبني جدولها بجافاسكربت */
    window.agRefreshPager = function (target) {
        var bar = typeof target === 'string' ? document.querySelector(target) : target;
        if (bar) setup(bar).refresh();
    };

    window.agWarn = window.agWarn || function (text) {
        if (typeof Swal !== 'undefined') { Swal.fire({ icon: 'warning', title: 'تنبيه', text: text }); }
        else { alert(text); }
    };

    function initAll() {
        document.querySelectorAll('.ag-pager[data-rows]').forEach(function (bar) {
            try { setup(bar).refresh(); } catch (e) { console.error('agPager', e); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
</script>
@endonce
