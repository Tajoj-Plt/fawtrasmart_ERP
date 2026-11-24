{{--
    زرّا «تصدير» و«طباعة» في رأس جدول التقرير — يقرآن `?export=rows`
    (كلّ الصفوف لا الصفحة الظاهرة)،
    وقبلهما عددُ الصفوف في الصفحة (`rpt-page-size`). التصديرُ لمن يملك `hr_reports.export`.
--}}
<div class="ag-tools no-print">
    @include('hr::reports.partials.rpt-page-size')
    @if ($canExport ?? false)
        <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
    @endif
    <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
</div>
