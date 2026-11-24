{{--
    سكربتاتُ تقرير الموارد البشرية: التصدير والطباعة لكل الصفوف (`?export=rows`)
    وتهيئةُ select2 لحقول الفلتر.

    @param string $frTitle, $frInfo
    @param array  $frHeaders
--}}
@include('reports.partials.fetch-rows-tools', [
    'frTitle' => $frTitle,
    'frInfo' => $frInfo ?? '',
    'frHeaders' => $frHeaders,
])
<script>
    // أزرارُ الفترات السريعة: تملأ التاريخين وتُفرغ منتقيَي السنة والفترة ثم تعرض التقرير
    function hrFmtDate(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    $(document).on('click', '.period-btn', function () {
        var period = String($(this).data('period'));
        $('#periodInput').val(period);
        $('.period-btn').removeClass('is-active');
        $(this).addClass('is-active');
        if (period === 'custom') { $('#from_date').trigger('focus'); return; }
        var today = new Date(), from = new Date();
        from.setDate(today.getDate() - (period === '1' ? 0 : parseInt(period, 10)));
        $('#from_date').val(hrFmtDate(from));
        $('#to_date').val(hrFmtDate(today));
        $('#fiscal_year_id, #accounting_period_id').val('').trigger('change.select2');
        $('#filterForm').trigger('submit');
    });
    $(document).on('change', '#from_date, #to_date', function () {
        $('#periodInput').val('custom');
        $('.period-btn').removeClass('is-active').filter('[data-period="custom"]').addClass('is-active');
    });
    // عددُ الصفوف في الصفحة
    $(document).on('change', '#hrPerPage', function () {
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', this.value);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    });

    $(function () {
        if ($.fn.select2) {
            $('.rpt-select2').each(function () { if ($(this).data('select2')) { $(this).select2('destroy'); } });
            $('.rpt-select2').select2({ dir: 'rtl', allowClear: true, width: '100%', placeholder: 'اختر...' });
        }
    });
</script>
