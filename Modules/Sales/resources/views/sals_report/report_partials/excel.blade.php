{{--
    تصدير Excel لتقارير المبيعات — عبر `public/js/excel-export-helper.js`.

    البيانات تُبنى في الخادم من صفوف التقرير نفسها، فما يُصدَّر هو ما يُرى
    على الشاشة حرفياً — لا قراءةً من الـ DOM تسقط منها الأعمدة أو تلتقط
    صفوف العناوين والمجاميع.

    بيانات المنشأة والعملة تُقرأ من شاشات الإعدادات كما في المطبوع.

    @param string      $fileName    اسم الملف بلا امتداد
    @param string      $mainTitle   عنوان التقرير
    @param string|null $subTitle
    @param array       $headers     رؤوس الأعمدة
    @param array       $rows        صفوف: [['values' => [...], 'cellStyles' => [...]], …]
    @param array       $info        معلومات الترويسة: ['الفترة' => '…']
    @param array       $cards       بطاقات إحصائية
    @param array       $summaryRows صفوف الإجمالي
    @param array       $widths      عرض الأعمدة
--}}
@php
    use App\Support\BusinessInfo;
    use App\Support\CurrencySettings;

    $excel = [
        'fileName' => $fileName,
        'mainTitle' => $mainTitle,
        'subTitle' => $subTitle ?? BusinessInfo::name(),
        'tableTitle' => $tableTitle ?? $mainTitle,
        'tableHeaders' => $headers,
        'tableData' => $rows,
        'columnWidths' => $widths ?? [],
        'statisticsCards' => $cards ?? [],
        'summaryRows' => $summaryRows ?? [],
        'creator' => BusinessInfo::name(),
        'buttonSelector' => '#exportExcel',
        'successMessage' => 'تم تصدير التقرير بنجاح',
        'errorMessage' => 'تعذّر تصدير التقرير',
        'reportInfo' => array_filter(array_merge([
            'المنشأة' => BusinessInfo::name(),
            'العملة' => CurrencySettings::name().' ('.CurrencySettings::symbol().')',
        ], $info ?? []), fn ($v) => filled($v)),
        'footerText' => 'تم إنشاء هذا التقرير آلياً من '.BusinessInfo::name()
            .' بتاريخ '.now()->format('Y-m-d H:i')
            .' — بواسطة '.(auth()->user()->name ?? '—'),
    ];
@endphp

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
    $(function () {
        var rptExcelConfig = @json($excel, JSON_UNESCAPED_UNICODE);

        $('#exportExcel').on('click', function () {
            if (typeof exportToExcelProfessional !== 'function') {
                console.error('excel-export-helper.js is not loaded');
                return;
            }

            if (!rptExcelConfig.tableData || rptExcelConfig.tableData.length === 0) {
                if (typeof toastr !== 'undefined') {
                    toastr.warning('لا توجد بيانات للتصدير');
                } else {
                    alert('لا توجد بيانات للتصدير');
                }
                return;
            }

            exportToExcelProfessional(rptExcelConfig);
        });
    });
</script>
