{{--
    تصدير Excel لتقارير المشتريات — عبر `public/js/excel-export-helper.js`.

    **لماذا جزئية واحدة؟** كل شاشة كانت إمّا بلا تصدير أصلاً، أو برابط CSV
    خام لا عنوان فيه ولا إحصاءات ولا تنسيق. والمساعد يبني ملفاً احترافياً
    (ترويسة وبطاقات إحصائية وجدولاً ملوّناً وتذييلاً) لكنه يحتاج إعداداً
    طويلاً يتكرّر في كل صفحة. هنا يُكتب مرّة، وتمرّر كل شاشة أعمدتها
    وصفوفها فحسب.

    البيانات تُبنى في الخادم من صفوف التقرير نفسها، فما يُصدَّر هو ما يُرى
    على الشاشة حرفياً — لا استعلامٌ ثانٍ قد يعطي رقماً مختلفاً.

    وبيانات المنشأة والعملة تُقرأ من شاشات الإعدادات كما في المطبوع، فيخرج
    الملف باسم المنشأة وعملتها لا بقيم مكتوبة في القالب.

    @param string $fileName    اسم الملف بلا امتداد
    @param string $mainTitle   عنوان التقرير
    @param string|null $subTitle
    @param array  $headers     رؤوس الأعمدة
    @param array  $rows        صفوف: [['values' => [...], 'cellStyles' => [...]], …]
    @param array  $info        معلومات الترويسة: ['الفترة' => '…']
    @param array  $cards       بطاقات إحصائية: [['title','value','valueColor','backgroundColor','titleColor','borderColor']]
    @param array  $summaryRows صفوف الإجمالي
    @param array  $widths      عرض الأعمدة
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
        'buttonSelector' => '#rptExcelAction',
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

{{--
    المكتبتان من CDN كما في تقارير وحدة الحسابات — ولا نسخة منهما في
    `public/js`. كانت شاشة أعمار الديون تطلبهما محلياً
    (`asset('js/exceljs.min.js')`) وهما غير موجودَين، فزرّ Excel فيها لم
    يكن يفعل شيئاً سوى خطأٍ في الطرفية.
--}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>
<script>
    $(function () {
        // الإعداد كاملاً من الخادم — فلا يُعاد بناء الجدول في المتصفّح
        var rptExcelConfig = @json($excel, JSON_UNESCAPED_UNICODE);

        $('#rptExcelAction').on('click', function () {
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
