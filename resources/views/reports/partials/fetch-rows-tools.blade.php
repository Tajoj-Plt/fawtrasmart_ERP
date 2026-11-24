{{--
    تصديرٌ وطباعةٌ لكلّ صفوف التقرير — لا صفحةِ الجدول المعروضة.

    عند الضغط يُطلب الرابطُ الحاليّ نفسُه بـ`export=rows` (بالفلاتر نفسها)،
    والمتحكّمُ يُعيد `{rows: [[…], …]}` بترتيب `$frHeaders`. الصفوفُ تُحفظ بعد
    أوّل طلب، فالطباعةُ بعد التصدير لا تطلب ثانيةً.

    @param array<int, string> $frHeaders  عناوينُ الأعمدة
    @param string             $frTitle    عنوانُ الملف والورقة
    @param string             $frInfo     سطرُ «الفترة» أو ما يصف النطاق (اختياري)
    @param string             $frExport   محدِّدُ زرّ التصدير (الافتراض `#exportExcel`)
    @param string             $frPrint    محدِّدُ زرّ الطباعة (الافتراض `#printBtn`)
--}}
@php
    $frExport = $frExport ?? '#exportExcel';
    $frPrint = $frPrint ?? '#printBtn';
@endphp
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<table class="d-none" id="frPrintTable"><tbody></tbody></table>
<script>
    (function () {
        const FR = {
            headers: @json(array_values($frHeaders)),
            title: @json($frTitle),
            info: @json(array_filter(['النطاق' => $frInfo ?? null, 'تاريخ التقرير' => now()->format('Y-m-d')])),
        };
        let cache = null;

        function load(button) {
            if (cache) { return Promise.resolve(cache); }
            const url = new URL(window.location.href);
            url.searchParams.set('export', 'rows');
            url.searchParams.delete('page');
            const $btn = $(button);
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> جارٍ التجهيز…');
            return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
                .then(function (data) { cache = data.rows || []; return cache; })
                .finally(function () { $btn.prop('disabled', false).html(html); });
        }

        $(document).on('click', @json($frExport), function (e) {
            e.preventDefault();
            load(this).then(function (rows) {
                if (!rows.length) { alert('لا صفوف للتصدير'); return; }
                exportToExcelProfessional({
                    fileName: FR.title.replace(/\s+/g, '_'),
                    mainTitle: FR.title,
                    subTitle: '',
                    reportInfo: FR.info,
                    statisticsCards: [],
                    tableTitle: FR.title,
                    tableHeaders: FR.headers,
                    tableData: rows.map(function (r) { return { values: r }; }),
                    summaryRows: [],
                    buttonSelector: @json($frExport),
                    successMessage: 'تم تصدير التقرير بنجاح',
                    errorMessage: 'تعذّر تصدير التقرير'
                });
            }).catch(function () { alert('تعذّر تجهيز صفوف التصدير'); });
        });

        $(document).on('click', @json($frPrint), function (e) {
            e.preventDefault();
            load(this).then(function (rows) {
                const tbody = document.querySelector('#frPrintTable tbody');
                const frag = document.createDocumentFragment();
                rows.forEach(function (r) {
                    const tr = document.createElement('tr');
                    r.forEach(function (v) { const td = document.createElement('td'); td.textContent = v === null ? '' : v; tr.appendChild(td); });
                    frag.appendChild(tr);
                });
                tbody.replaceChildren(frag);
                printTable({
                    tableSelector: '#frPrintTable tbody tr',
                    title: FR.title,
                    infoCards: [],
                    filterInfo: FR.info,
                    tableHeaders: FR.headers,
                    columnWidths: [],
                    descriptionColumn: 1,
                    windowWidth: 1400,
                    windowHeight: 900
                });
            }).catch(function () { alert('تعذّر تجهيز صفوف الطباعة'); });
        });
    })();
</script>
