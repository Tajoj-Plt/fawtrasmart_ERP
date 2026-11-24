{{--
    تقريرُ المحافظ — المحتوى المشترك بين «محافظ العملاء» و«محافظ الموردين».

    الشاشتان لا تختلفان إلّا في الطرف: اسمُه، وحقولُ فلترته، ورابطُ ملفّه.
    والأرقامُ كلُّها من `App\Services\Wallets\WalletReportService::screen()`.

    @param string $wrParty          client | supplier
    @param string $wrNoun           «العميل» | «المورد»
    @param string $wrNounPlural     «العملاء» | «الموردين»
    @param string $wrTitle          عنوان الشاشة
    @param string $wrAction         رابطُ النموذج
    @param string $wrFields         اسمُ ملفّ حقول الطرف في شبكة الفلترة
    @param string $wrCrumbLabel     أوّلُ المسار
    @param string $wrCrumbUrl       رابطُه
    + ما يعيده `screen()`، و`fromDate`/`toDate`/`periodContext`/`treeNodes`.
--}}
@php
    $wrIsBalances = $walletView === 'balances';
    $wrPeriodLabel = 'من '.$fromDate->format('d/m/Y').' إلى '.$toDate->format('d/m/Y');
    $wrCurrency = \App\Support\CurrencySettings::name();
    $wrSigns = \App\Services\Wallets\WalletReportService::TYPE_SIGNS;
    $wrShowOther = collect($rows)->contains(fn ($r) => abs($r['other']) >= 0.005);
    $wrTypeColors = ['deposit' => '#10b981', 'refund' => '#06b6d4', 'withdraw' => '#f97316', 'invoice_paid' => '#8b5cf6'];
    $wrTypeIcons = ['deposit' => 'arrow-down', 'refund' => 'undo', 'withdraw' => 'arrow-up', 'invoice_paid' => 'file-invoice'];
    $money = fn ($v) => number_format((float) $v, 2);
@endphp

<div class="rpt">
    @include('client::customers.CustomerReport.partials.report-head', [
        'headIcon' => 'wallet',
        'headTitle' => $wrTitle,
        'headSubtitle' => $wrIsBalances
            ? 'رصيدُ كلِّ محفظةٍ أوّلَ المدى وآخرَه، وما دخلها وخرج منها بينهما'
            : 'كلُّ حركةٍ على المحافظ في المدى — بنوعها وفاتورتها وخزينتها ومُدخِلها',
        'headPeriod' => $wrPeriodLabel,
        'headCrumbLabel' => $wrCrumbLabel,
        'headCrumbUrl' => $wrCrumbUrl,
        'headGuideKey' => 'wallet-reports.'.$wrParty,
        'headGuide' => [
            'title' => 'كيف يُقرأ هذا التقرير؟',
            'lead' => 'المحفظة مالٌ لـ'.$wrNoun.' محفوظٌ لدينا'.($wrParty === 'supplier' ? ' (دفعةٌ مقدَّمة له)' : '').' — حسابٌ مستقلٌّ عن رصيد ذمّته.',
            'blocks' => [[
                'رصيد آخر المدى = رصيد أوّل المدى + الإيداعات + الردّ − السحوبات − سداد الفواتير + أخرى.',
                '«أخرى» ما قُيِّد على المحفظة بلا حركةٍ من شاشتها — كرصيدٍ افتتاحيٍّ أُدخل في المدى. لا يظهر العمود إلّا حين يوجد.',
                'الرصيد من القيود المحاسبية حتى تاريخ القطع، فيطابق كشفَ الحساب لا الرصيدَ الحاليَّ وحده.',
            ]],
            'facts' => [],
        ],
    ])

    @include('layouts.alerts.success')
    @include('layouts.alerts.error')

    @include('client::customers.CustomerReport.partials.period-banner', ['periodContext' => $periodContext ?? []])

    {{-- ===================== الفلترة ===================== --}}
    <div class="rpt-card rpt-filters no-print">
        <div class="rpt-card__body">
            <form method="GET" action="{{ $wrAction }}" id="filterForm">
                <input type="hidden" name="period" id="periodInput" value="{{ request('period') }}">
                <input type="hidden" name="view" value="{{ $walletView }}">

                @include('client::customers.CustomerReport.partials.report-period-tabs', [
                    'tabCurrent' => (string) request('period', ''),
                ])

                <div class="ag-filter-grid">
                    <div class="rpt-field">
                        <label for="from_date">الفترة التاريخية</label>
                        <div class="ag-range">
                            <input type="date" name="from_date" id="from_date" class="form-control" aria-label="من تاريخ" value="{{ $fromDate->format('Y-m-d') }}">
                            <span class="ag-range__sep">إلى</span>
                            <input type="date" name="to_date" id="to_date" class="form-control" aria-label="إلى تاريخ" value="{{ $toDate->format('Y-m-d') }}">
                        </div>
                    </div>

                    @include('client::customers.CustomerReport.partials.period-selector', [
                        'periodContext' => $periodContext ?? [],
                        'startId' => 'from_date',
                        'endId' => 'to_date',
                        'cols' => '',
                        'yearTitle' => 'السنة المالية',
                        'periodTitle' => 'الفترة المحاسبية',
                    ])

                    {{-- حقولُ الطرف — عميلٌ أو مورد --}}
                    @include($wrFields)

                    @if ($wrIsBalances)
                        <div class="rpt-field">
                            <label for="balance">رصيد المحفظة</label>
                            <select name="balance" id="balance" class="form-control rpt-select2" data-placeholder="كل المحافظ">
                                <option value="">كل المحافظ</option>
                                <option value="nonzero" @selected(request('balance') === 'nonzero')>فيها رصيد آخر المدى</option>
                                <option value="zero" @selected(request('balance') === 'zero')>فارغة آخر المدى</option>
                            </select>
                        </div>
                    @else
                        <div class="rpt-field">
                            <label for="type">نوع الحركة</label>
                            <select name="type" id="type" class="form-control rpt-select2" data-placeholder="كل الأنواع">
                                <option value="">كل الأنواع</option>
                                @foreach ($typeLabels as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}" @selected(($walletFilters['type'] ?? null) === $typeKey)>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rpt-field">
                            <label for="treasury">الخزينة / البنك</label>
                            <select name="treasury" id="treasury" class="form-control rpt-select2" data-placeholder="كل الخزائن">
                                <option value="">كل الخزائن</option>
                                @foreach ($treasuries as $treasuryId => $treasuryName)
                                    <option value="{{ $treasuryId }}" @selected((int) ($walletFilters['treasury'] ?? 0) === (int) $treasuryId)>{{ $treasuryName }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- نوعُ العرض زرّا إرسال — والفلاترُ المشتركة تبقى --}}
                    <div class="rpt-field">
                        <label>نوع العرض</label>
                        <div class="btn-group d-flex" role="group">
                            <button type="submit" name="view" value="balances" class="btn {{ $wrIsBalances ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-wallet"></i> الأرصدة</button>
                            <button type="submit" name="view" value="movements" class="btn {{ ! $wrIsBalances ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-exchange-alt"></i> الحركات</button>
                        </div>
                    </div>

                    {{-- «تابع لـ» شجرةٌ تتفرّع حقولُها، فتأخذ سطراً كاملاً --}}
                    <div class="ag-span-all">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? [], 'currentNode' => $selectedNodeId ?? null])
                        </div>
                    </div>
                </div>

                <div class="ag-filter-foot">
                    @if ($wrIsBalances)
                        <div class="form-check">
                            <input type="checkbox" name="show_zero" class="form-check-input" id="show_zero" value="1" @checked(request()->boolean('show_zero'))>
                            <label class="form-check-label" for="show_zero">إظهار المحافظ بلا رصيدٍ ولا حركة</label>
                        </div>
                    @endif
                    <div class="ag-quick__actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> عرض التقرير</button>
                        <a href="{{ $wrAction }}{{ $wrIsBalances ? '' : '?view=movements' }}" class="btn ag-btn-soft"><i class="fas fa-redo"></i> إعادة تعيين</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================== المؤشّرات ===================== --}}
    <div class="ag-kpis">
        @if ($wrIsBalances)
            <div class="ag-kpi" style="--c:#3b82f6">
                <div>
                    <div class="ag-kpi__label">المحافظ</div>
                    <div class="ag-kpi__value"><bdi>{{ number_format($totals['wallets']) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ number_format($totals['holding']) }} فيها رصيد آخر المدى</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-users"></i></span>
            </div>
            <div class="ag-kpi" style="--c:#64748b">
                <div>
                    <div class="ag-kpi__label">رصيد أول المدى</div>
                    <div class="ag-kpi__value"><bdi>{{ $money($totals['opening']) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ $wrCurrency }}</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-flag"></i></span>
            </div>
        @endif
        @foreach ($typeLabels as $typeKey => $typeLabel)
            <div class="ag-kpi" style="--c:{{ $wrTypeColors[$typeKey] }}">
                <div>
                    <div class="ag-kpi__label">{{ $wrSigns[$typeKey] > 0 ? '+' : '−' }} {{ $typeLabel }}</div>
                    <div class="ag-kpi__value"><bdi>{{ $money($typeTotals[$typeKey]['total'] ?? 0) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ $wrCurrency }} · {{ number_format($typeTotals[$typeKey]['count'] ?? 0) }} حركة</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-{{ $wrTypeIcons[$typeKey] }}"></i></span>
            </div>
        @endforeach
        @if ($wrIsBalances)
            <div class="ag-kpi" style="--c:#f59e0b">
                <div>
                    <div class="ag-kpi__label">رصيد آخر المدى</div>
                    <div class="ag-kpi__value"><bdi>{{ $money($totals['closing']) }}</bdi></div>
                    <div class="ag-kpi__sub">{{ $wrCurrency }} · مالُ {{ $wrNounPlural }} لدينا</div>
                </div>
                <span class="ag-kpi__icon"><i class="fas fa-wallet"></i></span>
            </div>
        @endif
    </div>

    {{-- ===================== الجدول ===================== --}}
    <div class="rpt-card" id="reportCard">
        <div class="rpt-card__head ag-table-head">
            <h4>
                <i class="fas fa-table"></i> {{ $wrIsBalances ? 'أرصدة المحافظ' : 'سجل حركات المحافظ' }}
                <span class="rpt-pill rpt-pill-secondary">
                    {{ $wrIsBalances ? number_format(count($rows)).' محفظة' : number_format($movements->total()).' حركة' }}
                </span>
            </h4>
            <div class="ag-tools no-print">
                <button type="button" class="btn btn-sm ag-btn-soft" id="exportExcel"><i class="fas fa-file-excel"></i> تصدير</button>
                <button type="button" class="btn btn-sm ag-btn-soft" id="printBtn"><i class="fas fa-print"></i> طباعة</button>
            </div>
        </div>

        <div class="rpt-scroll table-responsive">
            @if ($wrIsBalances)
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الكود</th>
                            <th>{{ $wrNoun }}</th>
                            <th>تابع لـ</th>
                            <th>الحالة</th>
                            <th>رصيد أول المدى</th>
                            @foreach ($typeLabels as $typeKey => $typeLabel)
                                <th>{{ $wrSigns[$typeKey] > 0 ? '+' : '−' }} {{ $typeLabel }}</th>
                            @endforeach
                            @if ($wrShowOther)
                                <th title="قيودٌ على المحفظة بلا حركةٍ من شاشتها — رصيدٌ افتتاحيٌّ في المدى مثلاً">± أخرى</th>
                            @endif
                            <th>رصيد آخر المدى</th>
                            <th>الحركات</th>
                            <th>آخر حركة</th>
                            <th class="ag-extra no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $row['code'] ?: '—' }}</td>
                                <td class="rpt-name"><a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['name'] }}</a></td>
                                <td>
                                    <span class="rpt-pill rpt-pill-secondary">{{ $row['affiliation'] }}</span>
                                    @if (! empty($row['affiliation_type']))
                                        <div class="text-muted" style="font-size:11px">{{ $row['affiliation_type'] }}</div>
                                    @endif
                                </td>
                                <td><span class="rpt-pill" style="background:{{ $row['status_color'] }}; color:#fff">{{ $row['status'] }}</span></td>
                                <td class="rpt-num">{{ $money($row['opening']) }}</td>
                                @foreach ($typeLabels as $typeKey => $typeLabel)
                                    <td class="rpt-num {{ $row[$typeKey] > 0 ? ($wrSigns[$typeKey] > 0 ? 'rpt-pos' : 'rpt-neg') : 'text-muted' }}">{{ $money($row[$typeKey]) }}</td>
                                @endforeach
                                @if ($wrShowOther)
                                    <td class="rpt-num">{{ $money($row['other']) }}</td>
                                @endif
                                <td class="rpt-num"><strong>{{ $money($row['closing']) }}</strong></td>
                                <td class="rpt-num">{{ number_format($row['count']) }}</td>
                                <td>{{ $row['last_movement'] ?? '—' }}</td>
                                <td class="ag-extra no-print">
                                    @if ($row['wallet_url'])
                                        <a href="{{ $row['wallet_url'] }}" class="btn btn-sm ag-btn-soft" target="_blank" rel="noopener" title="كشف المحفظة"><i class="fas fa-wallet"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 14 + ($wrShowOther ? 1 : 0) }}" class="rpt-empty"><i class="fas fa-inbox"></i> لا محافظ مطابقة — غيّر المدى أو الفلاتر، أو أظهر المحافظ بلا رصيدٍ ولا حركة</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($rows) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="5">الإجمالي</td>
                                <td class="rpt-num">{{ $money($totals['opening']) }}</td>
                                @foreach ($typeLabels as $typeKey => $typeLabel)
                                    <td class="rpt-num">{{ $money($totals[$typeKey]) }}</td>
                                @endforeach
                                @if ($wrShowOther)
                                    <td class="rpt-num">{{ $money($totals['other']) }}</td>
                                @endif
                                <td class="rpt-num"><strong>{{ $money($totals['closing']) }}</strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @else
                <table class="rpt-table ag-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>{{ $wrNoun }}</th>
                            <th>نوع الحركة</th>
                            <th>المبلغ</th>
                            <th>رصيد المحفظة بعدها</th>
                            <th>الفاتورة</th>
                            <th>الخزينة / البنك</th>
                            <th>المرجع / ملاحظة</th>
                            <th>بواسطة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            @php
                                $party = $parties[$movement->party_id] ?? null;
                                $sign = $wrSigns[$movement->type] ?? 1;
                            @endphp
                            <tr>
                                <td>{{ $movements->firstItem() + $loop->index }}</td>
                                <td>{{ substr((string) $movement->day, 0, 10) }}</td>
                                <td class="rpt-name">
                                    @if ($party)
                                        <a href="{{ $party['url'] }}" target="_blank" rel="noopener">{{ $party['name'] }}</a>
                                    @else
                                        #{{ $movement->party_id }}
                                    @endif
                                </td>
                                <td><span class="rpt-pill" style="background:{{ $wrTypeColors[$movement->type] ?? '#64748b' }}; color:#fff">{{ $typeLabels[$movement->type] ?? $movement->type }}</span></td>
                                <td class="rpt-num {{ $sign > 0 ? 'rpt-pos' : 'rpt-neg' }}"><bdi>{{ $sign > 0 ? '+' : '−' }}{{ $money($movement->amount) }}</bdi></td>
                                <td class="rpt-num">{{ $money($movement->balance_after) }}</td>
                                <td>{{ $movement->invoice_code ?? '—' }}</td>
                                <td>{{ $movement->treasury_name ?? '—' }}</td>
                                <td style="max-width:260px; white-space:normal">{{ trim(($movement->reference ? $movement->reference.' — ' : '').($movement->note ?? ''), ' —') ?: '—' }}</td>
                                <td>{{ $movement->user_name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="rpt-empty"><i class="fas fa-inbox"></i> لا حركات على المحافظ في هذا المدى</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @if (! $wrIsBalances && $movements->hasPages())
            <div class="rpt-card__body no-print d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted">عرض {{ $movements->firstItem() }} إلى {{ $movements->lastItem() }} من {{ $movements->total() }} حركة</small>
                {{ $movements->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    @include('sales::sals_report.report_partials.print-footer')
</div>
