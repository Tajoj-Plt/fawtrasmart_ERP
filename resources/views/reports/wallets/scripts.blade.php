{{--
    سكربتات تقرير المحافظ — المشتركة بين العملاء والموردين (انظر `reports.wallets.content`).
    التصديرُ والطباعةُ من البيانات نفسها التي رسمت الجدول، لا من خلاياه.
--}}
@php
    $wsSigns = \App\Services\Wallets\WalletReportService::TYPE_SIGNS;
    $wsIsBalances = $walletView === 'balances';
    $wsShowOther = collect($rows)->contains(fn ($r) => abs($r['other']) >= 0.005);
    $wsTypeHeads = collect($typeLabels)->map(fn ($label, $key) => ($wsSigns[$key] > 0 ? '+ ' : '− ').$label)->values()->all();

    if ($wsIsBalances) {
        $wsHeaders = array_merge(['الكود', $wrNoun, 'تابع لـ', 'الحالة', 'رصيد أول المدى'], $wsTypeHeads,
            $wsShowOther ? ['± أخرى'] : [], ['رصيد آخر المدى', 'الحركات', 'آخر حركة']);
        $wsRows = array_map(fn ($r) => array_merge(
            [$r['code'] ?: '—', $r['name'], $r['affiliation'], $r['status'], $r['opening']],
            array_map(fn ($key) => $r[$key], array_keys($typeLabels)),
            $wsShowOther ? [$r['other']] : [],
            [$r['closing'], $r['count'], $r['last_movement'] ?? '—']
        ), $rows);
        $wsSummary = array_merge(['', '', '', 'الإجمالي', $totals['opening']],
            array_map(fn ($key) => $totals[$key], array_keys($typeLabels)),
            $wsShowOther ? [$totals['other']] : [], [$totals['closing'], '', '']);
    } else {
        $wsHeaders = ['التاريخ', $wrNoun, 'نوع الحركة', 'المبلغ', 'رصيد المحفظة بعدها', 'الفاتورة', 'الخزينة / البنك', 'المرجع / ملاحظة', 'بواسطة'];
        $wsRows = $exportMovements->map(fn ($m) => [
            substr((string) $m->day, 0, 10),
            $parties[$m->party_id]['name'] ?? ('#'.$m->party_id),
            $typeLabels[$m->type] ?? $m->type,
            ($wsSigns[$m->type] ?? 1) * (float) $m->amount,
            (float) $m->balance_after,
            $m->invoice_code ?? '—',
            $m->treasury_name ?? '—',
            trim(($m->reference ? $m->reference.' — ' : '').($m->note ?? ''), ' —') ?: '—',
            $m->user_name ?? '—',
        ])->all();
        $wsSummary = null;
    }

    $wsCards = collect($typeLabels)->map(fn ($label, $key) => [
        'title' => ($wsSigns[$key] > 0 ? '+ ' : '− ').$label.' ('.($typeTotals[$key]['count'] ?? 0).')',
        'value' => (float) ($typeTotals[$key]['total'] ?? 0),
    ])->values()->all();
    if ($wsIsBalances) {
        array_unshift($wsCards, ['title' => 'رصيد أول المدى', 'value' => (float) $totals['opening']]);
        $wsCards[] = ['title' => 'رصيد آخر المدى ('.$totals['wallets'].' محفظة)', 'value' => (float) $totals['closing']];
    }
@endphp

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="{{ asset('js/print-table.js') }}"></script>
<script src="{{ asset('js/excel-export-helper.js') }}"></script>

<script>
    (function () {
        const WR = {
            title: @json($wrTitle.($wsIsBalances ? ' — الأرصدة' : ' — الحركات')),
            period: @json('من '.$fromDate->format('Y-m-d').' إلى '.$toDate->format('Y-m-d')),
            headers: @json($wsHeaders),
            rows: @json($wsRows),
            summary: @json($wsSummary),
            cards: @json($wsCards),
            // أعمدةُ المبالغ — تُنسَّق بخانتين في التصدير
            moneyFrom: {{ $wsIsBalances ? 4 : 3 }},
            moneyTo: {{ $wsIsBalances ? 4 + count($typeLabels) + ($wsShowOther ? 1 : 0) + 1 : 4 }},
            isBalances: {{ $wsIsBalances ? 'true' : 'false' }},
            truncated: {{ ! $wsIsBalances && $movements->total() > $exportLimit ? 'true' : 'false' }},
            exportLimit: {{ $exportLimit }},
        };

        function money(n) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatDateForInput(date) {
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }

        function cell(value, index) {
            return (index >= WR.moneyFrom && index <= WR.moneyTo && typeof value === 'number') ? money(value) : value;
        }

        function changePeriod(period) {
            period = String(period);
            $('#periodInput').val(period);
            $('.period-btn').removeClass('is-active');
            $('.period-btn[data-period="' + period + '"]').addClass('is-active');
            if (period === 'custom') { return; }

            const today = new Date();
            const from = new Date();
            from.setDate(today.getDate() - (period === '1' ? 0 : parseInt(period, 10) || 30));
            $('#from_date').val(formatDateForInput(from));
            $('#to_date').val(formatDateForInput(today));
            // المدى السريع يُفرغ السنة والفترة: المتحكّم يقدّمهما على التاريخين
            $('#fiscal_year_id, #accounting_period_id').val('');
            if ($.fn.select2) { $('#fiscal_year_id, #accounting_period_id').trigger('change.select2'); }
            $('#filterForm').submit();
        }

        function exportReport() {
            if (WR.truncated) {
                alert('يُصدَّر أوّل ' + WR.exportLimit + ' حركة فقط — ضيّق المدى أو الفلاتر لتصدير الباقي.');
            }
            exportToExcelProfessional({
                fileName: WR.title.replace(/\s+/g, '_'),
                mainTitle: WR.title,
                subTitle: @json($wrNounPlural),
                reportInfo: { 'الفترة': WR.period, 'تاريخ التصدير': new Date().toLocaleDateString('ar-SA') },
                statisticsCards: WR.cards.map(function (c) { return { title: c.title, value: money(c.value), unit: '', borderColor: 'primary' }; }),
                tableTitle: WR.title,
                tableHeaders: WR.headers,
                tableData: WR.rows.map(function (r) { return { values: r.map(cell) }; }),
                summaryRows: WR.summary ? [{
                    values: WR.summary.map(cell),
                    background: 'warningLight', color: 'warning',
                    cellStyles: WR.summary.map(function () { return { bold: true }; })
                }] : [],
                buttonSelector: '#exportExcel',
                successMessage: 'تم تصدير التقرير بنجاح!',
                errorMessage: 'حدث خطأ أثناء تصدير التقرير'
            });
        }

        function printReport() {
            printTable({
                tableSelector: '#reportTable tbody tr',
                tfootSelector: WR.isBalances ? '#reportTable tfoot' : undefined,
                title: WR.title,
                infoCards: WR.cards.map(function (c) { return { label: c.title, value: money(c.value) }; }),
                filterInfo: { 'الفترة': WR.period },
                tableHeaders: ['#'].concat(WR.isBalances ? WR.headers : WR.headers),
                // عمودُ رابط المحفظة في الأرصدة لا يُطبع
                skipColumns: WR.isBalances ? [WR.headers.length + 1] : [],
                descriptionColumn: 2,
                windowWidth: 1200,
                windowHeight: 800
            });
        }

        $(function () {
            if ($.fn.select2) {
                $('.rpt-select2').each(function () {
                    if ($(this).data('select2')) { $(this).select2('destroy'); }
                });
                $('.rpt-select2').select2({
                    dir: 'rtl',
                    allowClear: true,
                    width: '100%',
                    language: { noResults: function () { return 'لا توجد نتائج'; } }
                });
            }

            $('.period-btn').on('click', function () { changePeriod($(this).data('period')); });
            $('#exportExcel').on('click', exportReport);
            $('#printBtn').on('click', printReport);
        });
    })();
</script>
