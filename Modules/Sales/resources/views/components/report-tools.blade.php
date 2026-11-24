{{--
    أدوات التصدير والطباعة المشتركة لشاشات المبيعات.

    نظير `stock::components.report-tools` بالبادئة `sl`. نسخة لا مشاركة:
    قالبٌ في المبيعات يشير إلى `stock::` ينكسر بمجرّد إطفاء وحدة المخزون
    في `modules_statuses.json`.

    تُحمّل المكتبتين المساعدتين المعتمدتين في تقارير الحسابات
    (`excel-export-helper.js` و`print-table.js`) وتضيف فوقهما غلافاً
    يقرأ جدول الصفحة كما هو معروض — فلا تحتاج كل شاشة أن تُعيد كتابة
    تجميع الصفوف والإجماليات، ولا يُصدَّر إلا ما يراه المستخدم فعلاً بعد
    الفلترة.

    الاستعمال في الشاشة:

        @include('sales::components.report-tools')

        <script>
            $(function () {
                slReportTools({
                    table: '#reportTable',
                    fileName: 'اسم_الملف',
                    title: 'عنوان التقرير',
                    subTitle: 'سطر تحت العنوان',
                    info: { 'العميل': 'الكل' },
                    cards: [{ title: 'عدد الفواتير', value: '12' }],
                    numericColumns: [3, 4, 5],   // أعمدة تُصدَّر أرقاماً
                    exportButton: '#exportExcel',
                    printButton: '#printReport',
                });
            });
        </script>
--}}

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>

<script>
(function () {
    /** ترويسة الجدول: يُؤخذ آخر صفٍّ في thead لأن الترويسات المركّبة تُقسَّم صفّين */
    function readHeaders(table, override) {
        if (override && override.length) return override;

        const headRows = table.querySelectorAll('thead tr');
        if (! headRows.length) return [];

        const cells = headRows[headRows.length - 1].querySelectorAll('th');

        return Array.from(cells).map(cell => cell.textContent.trim());
    }

    /** الصفوف المعروضة الآن — تتخطّى صفوف «لا نتائج» والصفوف المخفيّة */
    function readRows(table, headerCount, numericColumns) {
        const rows = [];

        table.querySelectorAll('tbody tr').forEach(function (tr) {
            if (tr.offsetParent === null && tr.style.display === 'none') return;

            const cells = tr.querySelectorAll('td');
            if (cells.length < headerCount) return;

            const values = Array.from(cells).slice(0, headerCount).map(function (cell, index) {
                const text = cell.textContent.replace(/\s+/g, ' ').trim();

                if (numericColumns.indexOf(index) === -1) return text;

                const number = parseFloat(text.replace(/[^\d.\-]/g, ''));
                return isNaN(number) ? text : number;
            });

            rows.push({
                values: values,
                alignments: values.map((value, index) =>
                    numericColumns.indexOf(index) === -1 && index === 0 ? 'right' : 'center'),
            });
        });

        return rows;
    }

    /**
     * يربط زرّ تصدير وزرّ طباعة بجدول الصفحة.
     */
    window.slReportTools = function (options) {
        /* الجدول يُقرأ **عند كل تصدير** لا مرّةً عند التحميل: شاشاتُ المبيعات
           تستبدل جدولها بـAjax مع كل بحث، فالعنصرُ المحفوظ يبقى الجدولَ
           القديم المفصول — فيُصدَّر ما عُرض أوّلَ مرّة لا ما يراه المستخدم.
           وشاشةٌ فُتحت بلا نتائج لم يكن فيها جدولٌ فلا يُربط زرٌّ أبداً. */
        let table = document.querySelector(options.table);
        let headers = table ? readHeaders(table, options.headers) : [];

        const numericColumns = options.numericColumns || [];
        const stamp = () => new Date().toISOString().slice(0, 10);

        const info = Object.assign({ 'تاريخ التقرير': stamp() }, options.info || {});

        function refresh() {
            table = document.querySelector(options.table);
            headers = table ? readHeaders(table, options.headers) : [];

            return table;
        }

        function collect() {
            return refresh() ? readRows(table, headers.length, numericColumns) : [];
        }

        if (options.exportButton) {
            document.addEventListener('click', function (event) {
                if (! event.target.closest(options.exportButton)) return;

                event.preventDefault();

                const rows = collect();

                if (! rows.length) {
                    if (typeof showMessage === 'function') showMessage('لا صفوف معروضة للتصدير', 'warning');
                    return;
                }

                exportToExcelProfessional({
                    fileName: options.fileName || 'تقرير',
                    mainTitle: options.title || 'تقرير',
                    subTitle: options.subTitle || '',
                    reportInfo: info,
                    statisticsCards: (options.cards || []).map(card => Object.assign(
                        { color: 'primary', borderColor: 'primary', unit: '' }, card
                    )),
                    tableTitle: options.tableTitle || options.title || 'التفاصيل',
                    tableHeaders: headers,
                    columnWidths: options.columnWidths || headers.map(() => 16),
                    tableData: rows,
                    summaryRows: options.summaryRows || [],
                    buttonSelector: options.exportButton,
                    successMessage: 'تم تصدير التقرير بنجاح',
                    errorMessage: 'تعذّر تصدير التقرير',
                });
            });
        }

        if (options.printButton) {
            document.addEventListener('click', function (event) {
                if (! event.target.closest(options.printButton)) return;

                event.preventDefault();

                if (! refresh()) return;

                printTable({
                    tableSelector: options.table + ' tbody tr',
                    tfootSelector: table.querySelector('tfoot') ? options.table + ' tfoot' : null,
                    title: options.title || 'تقرير',
                    infoCards: (options.cards || []).map(card => ({
                        label: card.title,
                        value: card.value + (card.unit || ''),
                    })),
                    filterInfo: info,
                    tableHeaders: headers,
                    columnWidths: options.printWidths || [],
                    descriptionColumn: options.descriptionColumn ?? 0,
                    windowWidth: 1400,
                    windowHeight: 900,
                });
            });
        }
    };
})();
</script>
