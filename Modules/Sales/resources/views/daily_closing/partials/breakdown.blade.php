{{--
    تفاصيل يومِ موظفٍ واحد — سطراً سطراً، بأقسامه الستّة.

    قالبٌ واحد تشترك فيه المطبوعاتُ الثلاث: تفاصيل إقفالٍ محفوظ، وتقريرُ
    فترة، وتقريرُ يومٍ لكلّ الموظفين. كانت كلٌّ منها تكتب الجداول الستّة
    بيدها، فاختلفت أعمدةُ المطبوع الواحد باختلاف الزرّ الذي فُتح منه.

    المبالغ من `App\Support\CurrencySettings`، والنصوص من
    `sales_daily_closing`، والألوان متغيّراتُ `--app-*` من «المظهر».

    @param array                     $data          مخرج `DailyClosingLedger::breakdown()`
    @param string                    $currency      رمز العملة
    @param \Illuminate\Support\Collection|null $stockDetails  جردُ إقفالٍ محفوظ (المُبلَّغ مقابل النظام)
--}}
@php
    use App\Support\CurrencySettings;

    $stockDetails = $stockDetails ?? null;

    /** الأقسام الأربعة ذاتُ الشكل الواحد: مرجع، عميل، بيان، مبلغ */
    $movementSections = [
        [
            'key' => 'salesEntries',
            'title' => __('sales_daily_closing.sections.sales'),
            'icon' => 'shopping-cart',
            'total' => $data['totalSales'],
            'tone' => 'rpt-pos',
        ],
        [
            'key' => 'cashSalesEntries',
            'title' => __('sales_daily_closing.sections.cash_sales'),
            'icon' => 'dollar-sign',
            'total' => $data['totalCashSales'],
            'tone' => 'rpt-infoc',
        ],
        [
            'key' => 'receiptEntries',
            'title' => __('sales_daily_closing.sections.receipts'),
            'icon' => 'file-text',
            'total' => $data['totalReceipts'],
            'tone' => 'rpt-infoc',
        ],
        [
            'key' => 'returnEntries',
            'title' => __('sales_daily_closing.sections.returns'),
            'icon' => 'corner-up-left',
            'total' => $data['totalReturns'],
            'tone' => 'rpt-warnc',
        ],
    ];

    // المرتجع النقدي المصروف يظهر قسماً حين وُجد وحده — لا كتلةً فارغةً في كلّ مطبوع
    if (! empty($data['cashRefundEntries'])) {
        $movementSections[] = [
            'key' => 'cashRefundEntries',
            'title' => __('sales_daily_closing.sections.cash_refunds'),
            'icon' => 'log-out',
            'total' => $data['totalCashRefunds'] ?? 0,
            'tone' => 'rpt-warnc',
        ];
    }
@endphp

{{-- ══════════ الملخّص ══════════ --}}
<div class="rpt-mini">
    <div>
        <small>{{ __('sales_daily_closing.fields.sales') }}</small>
        <strong class="rpt-pos">{{ CurrencySettings::number($data['totalSales']) }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.returns') }}</small>
        <strong class="rpt-warnc">{{ CurrencySettings::number($data['totalReturns']) }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.net_sales') }}</small>
        <strong>{{ CurrencySettings::number($data['netSales']) }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.cash_sales') }}</small>
        <strong class="rpt-infoc">{{ CurrencySettings::number($data['totalCashSales']) }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.receipts') }}</small>
        <strong class="rpt-infoc">{{ CurrencySettings::number($data['totalReceipts']) }}</strong>
    </div>
    {{-- المرتجع النقدي المصروف — سطرُ مالٍ يُخفى عند الصفر، ومطروحٌ من إجمالي التحصيل --}}
    @if (($data['totalCashRefunds'] ?? 0) > 0)
        <div>
            <small>{{ __('sales_daily_closing.fields.cash_refunds') }}</small>
            <strong class="rpt-warnc">{{ CurrencySettings::number($data['totalCashRefunds']) }}</strong>
        </div>
    @endif
    <div>
        <small>{{ __('sales_daily_closing.fields.collections') }}</small>
        <strong class="rpt-infoc">{{ CurrencySettings::number($data['totalCollections']) }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.expenses') }}</small>
        <strong class="rpt-neg">{{ CurrencySettings::number($data['totalExpensesDetail']) }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.stock') }}</small>
        <strong>{{ number_format($data['systemStock'], 0) }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.visits_completed') }}</small>
        <strong class="rpt-pos">{{ $data['visits']['completed_count'] ?? 0 }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.visits_missed') }}</small>
        <strong class="rpt-neg">{{ $data['visits']['missed_count'] ?? 0 }}</strong>
    </div>
    {{-- نتيجةُ المكتملة: فاتورةٌ أو تحصيل ⇐ ناجحة، وإلّا سلبية — ومجموعُ الوقت عند العملاء --}}
    <div>
        <small>{{ __('sales_daily_closing.fields.visits_success') }}</small>
        <strong class="rpt-pos">{{ $data['visits']['success_count'] ?? 0 }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.visits_negative') }}</small>
        <strong class="rpt-neg">{{ $data['visits']['negative_count'] ?? 0 }}</strong>
    </div>
    <div>
        <small>{{ __('sales_daily_closing.fields.visits_total_duration') }}</small>
        <strong>{{ $data['visits']['total_duration'] ?? '—' }}</strong>
    </div>
    {{-- ما قضاه في الطريق بين عميلٍ وعميل — يقابل «الوقت عند العملاء» --}}
    <div>
        <small>{{ __('sales_daily_closing.fields.visits_total_travel') }}</small>
        <strong>{{ $data['visits']['total_travel'] ?? '—' }}</strong>
    </div>
    @if (($data['visits']['custody_total'] ?? null) !== null)
        <div>
            <small>{{ __('sales_daily_closing.fields.custody_total') }}</small>
            <strong>{{ $data['visits']['custody_total'] }}</strong>
        </div>
    @endif
</div>

{{-- ══════════ حركات البيع والتحصيل والمرتجع ══════════ --}}
@foreach ($movementSections as $section)
    @php $entries = $data[$section['key']] ?? []; @endphp

    <div class="rpt-block">
        <header>
            <h5><i class="feather icon-{{ $section['icon'] }}"></i> {{ $section['title'] }}</h5>
            <span class="rpt-pill rpt-pill-secondary">
                {{ count($entries) }} · <span class="rpt-num {{ $section['tone'] }}">{{ CurrencySettings::number($section['total']) }} {{ $currency }}</span>
            </span>
        </header>

        @if (empty($entries))
            <div class="rpt-empty"><small>{{ __('sales_daily_closing.messages.no_entries') }}</small></div>
        @else
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th style="width: 5%">#</th>
                            <th>{{ __('sales_daily_closing.fields.reference') }}</th>
                            <th>{{ __('sales_daily_closing.fields.client') }}</th>
                            <th>{{ __('sales_daily_closing.fields.description') }}</th>
                            <th style="width: 16%">{{ __('sales_daily_closing.fields.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $index => $entry)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="rpt-num">
                                    {{ $entry['reference'] ?? '—' }}
                                    @if (! empty($entry['has_return']))
                                        <span class="rpt-pill rpt-pill-warning">{{ __('sales_daily_closing.status.has_return') }}</span>
                                    @endif
                                </td>
                                <td class="rpt-name">{{ $entry['client'] ?? '—' }}</td>
                                <td class="rpt-name">{{ $entry['description'] ?? '—' }}</td>
                                <td class="rpt-num {{ $section['tone'] }}">{{ CurrencySettings::number($entry['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4">{{ __('sales_daily_closing.fields.total') }}</td>
                            <td class="rpt-num {{ $section['tone'] }}">{{ CurrencySettings::number($section['total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
@endforeach

{{-- ══════════ المصروفات ══════════ --}}
<div class="rpt-block">
    <header>
        <h5><i class="feather icon-credit-card"></i> {{ __('sales_daily_closing.sections.expenses') }}</h5>
        <span class="rpt-pill rpt-pill-secondary">
            {{ count($data['expenseEntries']) }} · <span class="rpt-num rpt-neg">{{ CurrencySettings::number($data['totalExpensesDetail']) }} {{ $currency }}</span>
        </span>
    </header>

    @if (empty($data['expenseEntries']))
        <div class="rpt-empty"><small>{{ __('sales_daily_closing.messages.no_entries') }}</small></div>
    @else
        <div class="rpt-scroll">
            <table class="rpt-table">
                <thead>
                    <tr>
                        <th style="width: 5%">#</th>
                        <th>{{ __('sales_daily_closing.fields.reference') }}</th>
                        <th>{{ __('sales_daily_closing.fields.account') }}</th>
                        <th>{{ __('sales_daily_closing.fields.description') }}</th>
                        <th style="width: 16%">{{ __('sales_daily_closing.fields.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['expenseEntries'] as $index => $entry)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="rpt-num">{{ $entry['reference'] ?? '—' }}</td>
                            <td class="rpt-name">{{ $entry['account_name'] ?? '—' }}</td>
                            <td class="rpt-name">{{ $entry['description'] ?? '—' }}</td>
                            <td class="rpt-num rpt-neg">{{ CurrencySettings::number($entry['amount'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">{{ __('sales_daily_closing.fields.total') }}</td>
                        <td class="rpt-num rpt-neg">{{ CurrencySettings::number($data['totalExpensesDetail']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>

{{-- ══════════ دوام اليوم: مقابل الزيارات ══════════ --}}
@include('sales::daily_closing.partials.work-block', ['work' => $data['work'] ?? null])

{{-- ══════════ زيارات اليوم: المكتملة والفائتة ══════════ --}}
@php
    $visitSections = [
        ['key' => 'completed', 'title' => __('sales_daily_closing.sections.visits_completed'), 'icon' => 'check-circle', 'tone' => 'rpt-pos'],
        ['key' => 'missed', 'title' => __('sales_daily_closing.sections.visits_missed'), 'icon' => 'x-circle', 'tone' => 'rpt-neg'],
    ];
    $visits = $data['visits'] ?? ['completed' => [], 'missed' => []];
@endphp

@foreach ($visitSections as $section)
    @php $visitRows = $visits[$section['key']] ?? []; @endphp

    <div class="rpt-block">
        <header>
            <h5><i class="feather icon-{{ $section['icon'] }}"></i> {{ $section['title'] }}</h5>
            <span class="rpt-pill rpt-pill-secondary"><span class="rpt-num {{ $section['tone'] }}">{{ count($visitRows) }}</span></span>
        </header>

        @if (empty($visitRows))
            <div class="rpt-empty"><small>{{ __('sales_daily_closing.messages.no_visits') }}</small></div>
        @else
            <div class="rpt-scroll">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th style="width: 5%">#</th>
                            <th>{{ __('sales_daily_closing.fields.client') }}</th>
                            <th style="width: 12%">{{ __('sales_daily_closing.fields.client_code') }}</th>
                            @if ($section['key'] === 'completed')
                                {{-- التنقّل قبل الوصول: هو ما سبق هذه الزيارة زمناً —
                                     من مغادرة العميل الذي فوقه إلى الوصول عند هذا --}}
                                <th style="width: 12%">{{ __('sales_daily_closing.fields.travel_time') }}</th>
                                <th style="width: 9%">{{ __('sales_daily_closing.fields.arrived_at') }}</th>
                                <th style="width: 9%">{{ __('sales_daily_closing.fields.departed_at') }}</th>
                                <th style="width: 11%">{{ __('sales_daily_closing.fields.visit_duration') }}</th>
                                <th style="width: 8%" title="{{ __('sales_daily_closing.fields.custody_hint') }}">{{ __('sales_daily_closing.fields.custody') }}</th>
                                <th style="width: 11%">{{ __('sales_daily_closing.fields.visit_outcome') }}</th>
                            @else
                                {{-- لم يُزَر اليوم، فعهدتُه آخرُ ما سُجّل له — بتاريخه --}}
                                <th style="width: 12%">{{ __('sales_daily_closing.fields.last_custody') }}</th>
                                <th style="width: 14%">{{ __('sales_daily_closing.fields.last_custody_date') }}</th>
                                <th>{{ __('sales_daily_closing.fields.notes') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visitRows as $index => $visit)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="rpt-name">
                                    {{ $visit['client'] }}
                                    @if (! empty($visit['unplanned']))
                                        <span class="rpt-pill rpt-pill-secondary">{{ __('sales_daily_closing.fields.unplanned_visit') }}</span>
                                    @endif
                                </td>
                                <td class="rpt-num">{{ $visit['code'] ?? '—' }}</td>
                                @if ($section['key'] === 'completed')
                                    <td class="rpt-num">{{ $visit['travel'] ?? '—' }}</td>
                                    <td class="rpt-num">{{ $visit['start_time'] ?? '—' }}</td>
                                    <td class="rpt-num">{{ $visit['time'] ?? '—' }}</td>
                                    <td class="rpt-num">{{ $visit['duration'] ?? '—' }}</td>
                                    <td class="rpt-num">{{ $visit['custody'] ?? '—' }}</td>
                                    <td>
                                        <span class="rpt-pill {{ ($visit['outcome'] ?? '') === 'success' ? 'rpt-pill-success' : 'rpt-pill-danger' }}">
                                            {{ $visit['outcome_label'] ?? '—' }}
                                        </span>
                                    </td>
                                @else
                                    <td class="rpt-num">{{ $visit['custody'] ?? '—' }}</td>
                                    <td class="rpt-num">{{ $visit['custody_date'] ?? '—' }}</td>
                                    <td class="rpt-name">{{ $visit['note'] ?? '—' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($section['key'] === 'missed' && ($visits['missed_custody_total'] ?? null) !== null)
                        <tfoot>
                            <tr>
                                <td colspan="3">{{ __('sales_daily_closing.fields.total') }}</td>
                                <td class="rpt-num">{{ $visits['missed_custody_total'] }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                    @if ($section['key'] === 'completed' && (! empty($visits['total_duration']) || ! empty($visits['total_travel']) || ($visits['custody_total'] ?? null) !== null))
                        <tfoot>
                            <tr>
                                <td colspan="3">{{ __('sales_daily_closing.fields.total') }}</td>
                                <td class="rpt-num">{{ $visits['total_travel'] ?? '—' }}</td>
                                <td colspan="2"></td>
                                <td class="rpt-num">{{ $visits['total_duration'] ?? '—' }}</td>
                                <td class="rpt-num">{{ $visits['custody_total'] ?? '—' }}</td>
                                <td class="rpt-num">
                                    <span class="rpt-pos">{{ $visits['success_count'] ?? 0 }}</span> / <span class="rpt-neg">{{ $visits['negative_count'] ?? 0 }}</span>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </div>
@endforeach

{{-- ══════════ جرد المخزون ══════════ --}}
@php
    // إقفالٌ محفوظ يعرض المُبلَّغ مقابل النظام؛ وتقريرُ يومٍ حيّ يعرض كمية النظام وحدها
    $stockRows = $stockDetails && $stockDetails->isNotEmpty() ? $stockDetails : collect($data['stockProducts']);
    $showComparison = $stockDetails && $stockDetails->isNotEmpty();
@endphp

<div class="rpt-block">
    <header>
        <h5><i class="feather icon-package"></i> {{ __('sales_daily_closing.sections.stock') }}</h5>
        <span class="rpt-pill rpt-pill-secondary">{{ $stockRows->count() }}</span>
    </header>

    @if ($stockRows->isEmpty())
        <div class="rpt-empty"><small>{{ __('sales_daily_closing.messages.no_stock') }}</small></div>
    @else
        <div class="rpt-scroll">
            <table class="rpt-table">
                <thead>
                    <tr>
                        <th style="width: 5%">#</th>
                        <th>{{ __('sales_daily_closing.fields.product') }}</th>
                        @if ($showComparison)
                            <th style="width: 16%">{{ __('sales_daily_closing.fields.reported_value') }}</th>
                            <th style="width: 16%">{{ __('sales_daily_closing.fields.system_value') }}</th>
                            <th style="width: 12%">{{ __('sales_daily_closing.fields.difference') }}</th>
                        @else
                            <th style="width: 20%">{{ __('sales_daily_closing.fields.quantity') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stockRows as $index => $row)
                        @php
                            $isModel = is_object($row);
                            $name = $isModel ? $row->product_name : $row['product_name'];
                            $systemQty = (float) ($isModel ? $row->system_quantity : $row['system_quantity']);
                            $reportedQty = $isModel ? (float) $row->reported_quantity : $systemQty;
                            $diff = $reportedQty - $systemQty;
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="rpt-name">{{ $name }}</td>
                            @if ($showComparison)
                                <td class="rpt-num">{{ number_format($reportedQty, 2) }}</td>
                                <td class="rpt-num">{{ number_format($systemQty, 2) }}</td>
                                <td class="rpt-num {{ abs($diff) < 0.01 ? 'rpt-zero' : 'rpt-neg' }}">
                                    {{ abs($diff) < 0.01 ? '—' : number_format($diff, 2) }}
                                </td>
                            @else
                                <td class="rpt-num">{{ number_format($systemQty, 2) }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">{{ __('sales_daily_closing.fields.total') }}</td>
                        @if ($showComparison)
                            <td class="rpt-num">{{ number_format($stockRows->sum('reported_quantity'), 2) }}</td>
                            <td class="rpt-num">{{ number_format($stockRows->sum('system_quantity'), 2) }}</td>
                            <td></td>
                        @else
                            <td class="rpt-num">{{ number_format($data['systemStock'], 2) }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
