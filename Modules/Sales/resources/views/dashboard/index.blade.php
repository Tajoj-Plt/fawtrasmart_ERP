{{--
    لوحة مؤشّرات المبيعات — بنمط تصميم لوحة العملاء
    (`Modules/Client/resources/views/dashboard/client/index.blade.php`):
    شريطُ فلترةٍ واحد، صفّا مؤشّرات، بطاقاتٌ برأسٍ فيه أيقونةٌ وزرُّ
    «من أين تأتي هذه البيانات؟»، وكلُّ لونٍ من متغيّرات `--app-*`.

    كل رقمٍ هنا يأتي جاهزاً من `SalesDashboardMetrics` — والشاشة لا تحسب
    شيئاً. ونظيرتُها لوحة المشتريات بطاقةً ببطاقة.
--}}
@extends('sales::master')

@section('title', __('sales_dashboard.title'))

{{-- سكربتُ الصفحة الرئيسية التجريبي يبحث عن عناصر ليست هنا --}}
@section('disable-dashboard-ecommerce', true)

@php
    /*
     * لا رقم يُنسَّق هنا يدوياً ولا لونَ مكتوب:
     *   المبلغ  ← CurrencySettings (الرمز والمنازل والفواصل من الإعدادات)
     *   التاريخ ← LanguageSettings::dateFormat()
     *   اللون   ← متغيّرات --app-* التي يبثّها ThemeSettings
     *   النصّ   ← __() فيتبع اللغة المفعَّلة
     */
    $money = fn ($value) => \App\Support\CurrencySettings::number($value);
    $currency = \App\Support\CurrencySettings::symbol();
    $dateFormat = \App\Support\LanguageSettings::dateFormat() ?: 'Y-m-d';
    $showDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->translatedFormat($dateFormat) : '—';

    $deltaBadge = function (?array $delta) {
        if (! $delta || $delta['percent'] === null) {
            return ['class' => 'sd-delta-flat', 'icon' => 'minus', 'text' => __('sales_dashboard.kpi.no_comparison')];
        }

        return [
            'class' => 'sd-delta-'.$delta['direction'],
            'icon' => $delta['direction'] === 'up' ? 'trending-up' : ($delta['direction'] === 'down' ? 'trending-down' : 'minus'),
            'text' => ($delta['percent'] > 0 ? '+' : '').number_format($delta['percent'], 1).'%',
        ];
    };

    $kindColors = [
        'sale' => 'var(--sd-primary)',
        'return' => 'var(--sd-danger)',
        'credit_note' => 'var(--sd-info)',
        'collection' => 'var(--sd-success)',
        'cancellation' => 'var(--sd-warning)',
        'cancelled_collection' => 'var(--sd-muted)',
        'refund' => 'var(--sd-danger)',
        'adjustment' => 'var(--sd-muted)',
    ];

    $periodTabs = $periodTabs + ['custom' => __('sales_dashboard.toolbar.custom')];
    $isCustom = $period->key === 'custom';
    $infoLabel = __('sales_dashboard.info.toggle');

    $kpiRow1 = [
        [
            'label' => __('sales_dashboard.kpi.sales'),
            'value' => $money($totals['sales']),
            'unit' => $currency,
            'icon' => 'trending-up',
            'segment' => 'docs.sale',
            'color' => 'var(--sd-primary)',
            'foot' => __('sales_dashboard.kpi.invoices_count', ['count' => $totals['sales_count']]),
            'delta' => $deltaBadge($deltas['sales'] ?? null),
        ],
        [
            'label' => __('sales_dashboard.kpi.net_sales'),
            'value' => $money($totals['net_sales']),
            'unit' => $currency,
            'icon' => 'check-circle',
            'segment' => 'clients.net',
            'color' => 'var(--sd-success)',
            'foot' => __('sales_dashboard.kpi.after_returns'),
            'delta' => $deltaBadge($deltas['net_sales'] ?? null),
        ],
        [
            'label' => __('sales_dashboard.kpi.average_invoice'),
            'value' => $money($totals['average_invoice']),
            'unit' => $currency,
            'icon' => 'bar-chart-2',
            'segment' => 'docs.sale',
            'color' => 'var(--sd-primary)',
            'foot' => __('sales_dashboard.kpi2.average_foot'),
            'delta' => $deltaBadge($deltas['sales_count'] ?? null),
        ],
        [
            // المرتجعُ والإشعارُ الدائن رقمٌ واحد — والحاشيةُ تفصلهما
            'label' => __('sales_dashboard.kpi.returns'),
            'value' => $money($totals['returns_total']),
            'unit' => $currency,
            'icon' => 'corner-up-left',
            'segment' => 'docs.returns',
            'color' => 'var(--sd-danger)',
            'foot' => __('sales_dashboard.kpi.returns_count', [
                'returns' => $totals['returns_count'],
                'returns_amount' => $money($totals['returns']),
                'notes' => $totals['credit_notes_count'],
                'notes_amount' => $money($totals['credit_notes']),
                'rate' => number_format($totals['return_rate'], 2),
            ]),
            'delta' => $deltaBadge($deltas['returns_total'] ?? null),
        ],
        [
            'label' => __('sales_dashboard.kpi.collections'),
            'value' => $money($totals['collections']),
            'unit' => $currency,
            'icon' => 'credit-card',
            'segment' => 'docs.collection',
            'color' => 'var(--sd-info)',
            'foot' => __('sales_dashboard.kpi.collections_count', [
                'count' => $totals['collections_count'],
                'rate' => number_format($totals['collection_rate'], 1),
            ]),
            // السنداتُ الملغاة خارج المحصَّل، والمردودُ نقداً للعميل مطروحٌ منه — كلٌّ سطرٌ يفتح قائمته
            'extras' => array_filter([
                $totals['cancelled_receipts_count'] > 0 ? [
                    'segment' => 'docs.cancellation',
                    'color' => 'var(--sd-warning)',
                    'text' => __('sales_dashboard.kpi.cancelled', [
                        'count' => $totals['cancelled_receipts_count'],
                        'amount' => $money($totals['cancelled_receipts']),
                    ]),
                ] : null,
                $totals['refunds_count'] > 0 ? [
                    'segment' => 'docs.refund',
                    'color' => 'var(--sd-danger)',
                    'text' => __('sales_dashboard.kpi.refunded', [
                        'count' => $totals['refunds_count'],
                        'amount' => $money($totals['refunds']),
                    ]),
                ] : null,
            ]),
            'delta' => $deltaBadge($deltas['collections'] ?? null),
        ],
        [
            'label' => __('sales_dashboard.kpi.receivable'),
            'value' => $money($receivables['receivable']),
            'unit' => $currency,
            'icon' => 'alert-circle',
            'segment' => 'clients.debtors',
            'color' => 'var(--sd-warning)',
            'foot' => __('sales_dashboard.kpi2.receivable_foot', ['date' => $showDate($period->to)]),
            'delta' => null,
        ],
    ];

    $kpiRow2 = [
        [
            'label' => __('sales_dashboard.kpi2.invoices'),
            'value' => number_format($totals['sales_count']),
            'unit' => null,
            'icon' => 'file-text',
            'segment' => 'docs.sale',
            'color' => 'var(--sd-primary)',
            'foot' => __('sales_dashboard.kpi2.invoices_foot'),
        ],
        [
            'label' => __('sales_dashboard.kpi2.return_rate'),
            'value' => number_format($totals['return_rate'], 2).'%',
            'unit' => null,
            'icon' => 'rotate-ccw',
            'segment' => 'docs.returns',
            'color' => 'var(--sd-danger)',
            'foot' => __('sales_dashboard.kpi2.return_rate_foot'),
        ],
        [
            'label' => __('sales_dashboard.kpi2.collection_rate'),
            'value' => number_format($totals['collection_rate'], 1).'%',
            'unit' => null,
            'icon' => 'percent',
            'segment' => 'clients.net',
            'color' => 'var(--sd-info)',
            // فوق ١٠٠٪ ليس خطأً: المحصَّلُ يشمل سدادَ ذمم من فترات سابقة، والمقامُ مبيعاتُ الفترة وحدها
            'foot' => $totals['collection_rate'] > 100
                ? __('sales_dashboard.kpi2.collection_rate_over', [
                    'extra' => $money($totals['collections'] - $totals['net_sales']),
                    'currency' => $currency,
                ])
                : __('sales_dashboard.kpi2.collection_rate_foot'),
        ],
        [
            'label' => __('sales_dashboard.kpi2.debtors'),
            'value' => number_format($receivables['clients']),
            'unit' => null,
            'icon' => 'users',
            'segment' => 'clients.debtors',
            'color' => 'var(--sd-warning)',
            'foot' => __('sales_dashboard.kpi2.debtors_foot'),
        ],
        [
            'label' => __('sales_dashboard.kpi2.credit'),
            'value' => $money($receivables['credit']),
            'unit' => $currency,
            'icon' => 'arrow-up-right',
            'segment' => 'clients.credit',
            'color' => 'var(--sd-success)',
            'foot' => __('sales_dashboard.kpi2.credit_foot', ['count' => number_format($receivables['credit_clients'] ?? 0)]),
        ],
    ];
@endphp

@push('styles')
    @include('sales::dashboard.partials.styles')
@endpush

@section('content')
    <div class="content-body">
        <section id="sales-dashboard">

            {{-- ══════════ إرشادُ اللوحة ══════════ مطويّةٌ افتراضياً: من يفتح
                 لوحةَ المؤشّرات جاء لأرقامها. تُفتح بنقرة وتتذكّر ذلك بعدها. --}}
            @include('sales::partials.settings-theme')
            @include('components.hint-tips')

            {{-- ==================== ابدأ من هنا: دليل قسم المبيعات ==================== --}}
            @include('sales::dashboard.partials.start-guide')

            @include('sales::components.guide', [
                'key' => 'sales-dashboard.index',
                'title' => __('sales_dashboard.guide.title'),
                'lead' => __('sales_dashboard.guide.lead'),
                'open' => false,
                'blocks' => [
                    __('sales_dashboard.guide.source.title') => [
                        __('sales_dashboard.guide.source.a'),
                        __('sales_dashboard.guide.source.b'),
                    ],
                    __('sales_dashboard.guide.period.title') => [
                        __('sales_dashboard.guide.period.a'),
                        __('sales_dashboard.guide.period.b'),
                        __('sales_dashboard.guide.period.c'),
                    ],
                    __('sales_dashboard.guide.branch.title') => [
                        __('sales_dashboard.guide.branch.a'),
                        __('sales_dashboard.guide.branch.b'),
                    ],
                    __('sales_dashboard.guide.settings.title') => [
                        __('sales_dashboard.guide.settings.a'),
                        __('sales_dashboard.guide.settings.b'),
                        __('sales_dashboard.guide.settings.c'),
                    ],
                ],
            ])

            @include('sales::dashboard.partials.toolbar')

            @if (session('error'))
                <div class="sd-notice danger">{{ session('error') }}</div>
            @endif
            @if (session('success'))
                <div class="sd-notice success">{{ session('success') }}</div>
            @endif

            @if ($periodNotice)
                <div class="sd-notice {{ $periodNotice['level'] }}">{{ $periodNotice['message'] }}</div>
            @endif

            {{-- ==================== المؤشرات: صفّان من 6 ==================== --}}
            @foreach ([$kpiRow1, $kpiRow2] as $kpiRow)
                <div class="row">
                    @foreach ($kpiRow as $kpi)
                        <div class="{{ count($kpiRow) >= 6 ? 'col-xl-2' : 'col-xl' }} col-lg-4 col-md-6 col-12 mb-1">
                            <div class="sd-kpi" @if (! empty($kpi['segment'])) data-segment="{{ $kpi['segment'] }}" role="button" tabindex="0" title="{{ __('sales_dashboard.drill.open') }}" @endif>
                                <div class="sd-kpi-top">
                                    <div class="sd-kpi-icon" style="background: color-mix(in srgb, {{ $kpi['color'] }} 14%, transparent);">
                                        <i class="feather icon-{{ $kpi['icon'] }}" style="color: {{ $kpi['color'] }};"></i>
                                    </div>
                                    @if (! empty($kpi['delta']))
                                        <span class="sd-delta {{ $kpi['delta']['class'] }}">
                                            <i class="feather icon-{{ $kpi['delta']['icon'] }}"></i>
                                            {{ $kpi['delta']['text'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="sd-kpi-label">{{ $kpi['label'] }} @if (! empty($kpi['segment']))<i class="feather icon-list sd-kpi-open"></i>@endif</p>
                                <p class="sd-kpi-value">{{ $kpi['value'] }} @if ($kpi['unit'])<small>{{ $kpi['unit'] }}</small>@endif</p>
                                <div class="sd-kpi-foot">{{ $kpi['foot'] }}</div>
                                @foreach ($kpi['extras'] ?? [] as $extra)
                                    <div class="sd-kpi-foot sd-kpi-extra" data-segment="{{ $extra['segment'] }}" role="button" tabindex="0" style="color: {{ $extra['color'] }};">{{ $extra['text'] }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            {{-- ==================== الاتجاه / حالة السداد ==================== --}}
            <div class="row">
                <div class="col-lg-8 col-12">
                    <div class="sd-card">
                        <div class="sd-card-head">
                            <div>
                                <h4><i class="feather icon-trending-up"></i> {{ __('sales_dashboard.cards.trend') }}</h4>
                                <small>{{ __('sales_dashboard.cards.trend_hint', ['period' => $period->label]) }}</small>
                            </div>
                            <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="sd-card-body">
                            <div class="sd-info-panel" hidden>{{ __('sales_dashboard.info.trend') }}</div>
                            @if (count($trend['labels'] ?? []) > 0)
                                <div id="sdTrendChart" style="min-height: 330px;"></div>
                            @else
                                <div class="sd-empty"><i class="feather icon-bar-chart-2"></i> {{ __('sales_dashboard.empty.no_movement') }}</div>
                            @endif
                        </div>
                        <div class="sd-recon">
                            <div data-segment="docs.sale" role="button" tabindex="0">{{ __('sales_dashboard.recon.sales') }} <b>{{ $money($totals['sales']) }}</b></div>
                            <div data-segment="docs.return" role="button" tabindex="0">{{ __('sales_dashboard.recon.less_returns') }} <b>{{ $money($totals['returns']) }}</b></div>
                            <div data-segment="docs.credit_note" role="button" tabindex="0">{{ __('sales_dashboard.recon.less_credit_notes') }} <b>{{ $money($totals['credit_notes']) }}</b></div>
                            <div data-segment="clients.net" role="button" tabindex="0">{{ __('sales_dashboard.recon.equals_net') }} <b>{{ $money($totals['net_sales']) }}</b></div>
                            <div data-segment="docs.collection" role="button" tabindex="0">{{ __('sales_dashboard.recon.less_collections') }} <b>{{ $money($totals['collections']) }}</b></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="sd-card">
                        <div class="sd-card-head">
                            <div>
                                <h4><i class="feather icon-pie-chart"></i> {{ __('sales_dashboard.cards.payment_status') }}</h4>
                                <small>{{ __('sales_dashboard.cards.payment_status_hint') }}</small>
                            </div>
                            <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="sd-card-body">
                            <div class="sd-info-panel" hidden>{{ __('sales_dashboard.info.payment_status') }}</div>
                            @if (collect($paymentStatusMix)->sum('documents') > 0)
                                <div id="sdPaymentStatusChart"></div>
                                <table class="sd-table mt-1">
                                    <tbody>
                                        @foreach ($paymentStatusMix as $row)
                                            <tr @if ($row['documents'] > 0) data-segment="status.{{ $row['key'] }}" role="button" tabindex="0" @endif>
                                                <td>{{ $row['label'] }}</td>
                                                <td class="sd-num">{{ $row['documents'] }}</td>
                                                <td class="sd-num">{{ $money($row['amount']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="sd-empty"><i class="feather icon-file-text"></i> {{ __('sales_dashboard.empty.no_invoices') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== العملاء ==================== --}}
            <div class="row">
                <div class="col-lg-7 col-12">
                    <div class="sd-card">
                        <div class="sd-card-head">
                            <div>
                                <h4><i class="feather icon-users"></i> {{ __('sales_dashboard.cards.top_clients') }}</h4>
                                <small>{{ __('sales_dashboard.cards.top_clients_hint') }}</small>
                            </div>
                            <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="sd-card-body">
                            <div class="sd-info-panel" hidden>{{ __('sales_dashboard.info.top_clients') }}</div>
                            @if ($topClients->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="sd-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('sales_dashboard.table.client') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.invoices') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.sales') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.returns') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.net') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.balance') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $maxNet = max($topClients->max('net_sales'), 0.01);
                                            @endphp
                                            @foreach ($topClients as $client)
                                                <tr data-segment="client.{{ $client['client_id'] }}" role="button" tabindex="0">
                                                    <td>
                                                        <div>{{ $client['name'] }}</div>
                                                        <div class="sd-bar"><span style="width: {{ max(2, ($client['net_sales'] / $maxNet) * 100) }}%; background: var(--sd-primary);"></span></div>
                                                    </td>
                                                    <td class="sd-num">{{ $client['invoices'] }}</td>
                                                    <td class="sd-num">{{ $money($client['sales']) }}</td>
                                                    <td class="sd-num" style="color: var(--sd-danger);">{{ $money($client['returns_total']) }}</td>
                                                    <td class="sd-num font-weight-bold">{{ $money($client['net_sales']) }}</td>
                                                    <td class="sd-num" style="color: {{ $client['balance'] > 0 ? 'var(--sd-warning)' : 'var(--sd-success)' }};">{{ $money($client['balance']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sd-empty"><i class="feather icon-users"></i> {{ __('sales_dashboard.empty.no_clients') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 col-12">
                    <div class="sd-card">
                        <div class="sd-card-head">
                            <div>
                                <h4><i class="feather icon-alert-circle"></i> {{ __('sales_dashboard.cards.top_debtors') }}</h4>
                                <small>{{ __('sales_dashboard.cards.top_debtors_hint', ['date' => $showDate($period->to)]) }}</small>
                            </div>
                            <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="sd-card-body">
                            <div class="sd-info-panel" hidden>{{ __('sales_dashboard.info.top_debtors') }}</div>
                            @if ($topDebtors->isNotEmpty())
                                <div id="sdDebtorsChart" style="min-height: 300px;"></div>
                            @else
                                <div class="sd-empty"><i class="feather icon-check-circle"></i> {{ __('sales_dashboard.empty.no_debt') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== الشجرة: الآباء والأبناء ==================== --}}
            @if ($branchTree->isNotEmpty())
                <div class="row">
                    <div class="col-12">
                        <div class="sd-card">
                            <div class="sd-card-head">
                                <div>
                                    <h4><i class="feather icon-git-branch"></i> {{ __('sales_dashboard.tree.title') }}
                                        @if ($selectedBranchId && $branchLineage->isNotEmpty())
                                            <span class="sd-tree-root">— {{ $branchLineage->last()->name }}</span>
                                        @endif
                                    </h4>
                                    <small>{{ __('sales_dashboard.tree.hint') }}</small>
                                </div>
                                <div class="sd-tree-actions">
                                    <button type="button" class="sd-tree-all" data-sd-tree-all="1">{{ __('sales_dashboard.tree.expand_all') }}</button>
                                    <button type="button" class="sd-tree-all" data-sd-tree-all="0">{{ __('sales_dashboard.tree.collapse_all') }}</button>
                                    <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                                </div>
                            </div>
                            <div class="sd-card-body">
                                <div class="sd-info-panel" hidden>{{ __('sales_dashboard.tree.info') }}</div>
                                <div class="row">
                                    <div class="col-xl-{{ $branchBreakdown->isNotEmpty() ? '7' : '12' }} col-12">
                                        <div class="table-responsive">
                                            <table class="sd-table sd-tree" id="sdTree">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('sales_dashboard.table.branch') }}</th>
                                                        <th class="sd-num">{{ __('sales_dashboard.table.invoices') }}</th>
                                                        <th class="sd-num">{{ __('sales_dashboard.table.sales') }}</th>
                                                        <th class="sd-num">{{ __('sales_dashboard.table.returns') }}</th>
                                                        <th class="sd-num">{{ __('sales_dashboard.table.net') }}</th>
                                                        <th class="sd-num">{{ __('sales_dashboard.table.collections') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php
                                                        $maxTreeNet = max($branchTree->where('depth', 0)->max('net_sales'), 0.01);
                                                    @endphp
                                                    @foreach ($branchTree as $row)
                                                        {{-- الشجرةُ مطويّةٌ كاملةً عند فتح الصفحة — الجذورُ وحدَها ظاهرة،
                                                             وما دونها يبقى مخفياً حتى يفتحه المستخدم بنفسه --}}
                                                        <tr data-tree-id="{{ $row['branch_id'] ?? 'none' }}" data-tree-parent="{{ $row['parent_id'] ?? '' }}"
                                                            class="sd-tree-d{{ min($row['depth'], 3) }}" @if ($row['depth'] > 0) hidden @endif>
                                                            <td style="padding-inline-start: calc(.55rem + {{ $row['depth'] }} * 1.25rem);">
                                                                <div class="sd-tree-name">
                                                                    @if ($row['has_children'])
                                                                        <button type="button" class="sd-tree-caret" data-sd-tree-toggle aria-expanded="false"><i class="feather icon-chevron-left"></i></button>
                                                                    @else
                                                                        <span class="sd-tree-leaf"></span>
                                                                    @endif
                                                                    @if ($row['branch_id'])
                                                                        <a class="sd-drill" href="{{ request()->fullUrlWithQuery(['branch_id' => $row['branch_id']]) }}">{{ $row['name'] }}</a>
                                                                    @else
                                                                        <span>{{ $row['name'] }}</span>
                                                                    @endif
                                                                    @if ($row['type'])
                                                                        <span class="sd-node-type">{{ $row['type'] }}</span>
                                                                    @endif
                                                                </div>
                                                                @if ($row['has_children'] && abs($row['own_net']) > 0.009)
                                                                    <small class="sd-tree-own" data-segment="clients.net" data-segment-params="{{ json_encode(['node' => $row['branch_id'], 'node_scope' => 'direct']) }}" role="button" tabindex="0">{{ __('sales_dashboard.tree.direct', ['amount' => $money($row['own_net'])]) }}</small>
                                                                @endif
                                                                @if ($row['depth'] === 0)
                                                                    <div class="sd-bar"><span style="width: {{ max(2, (max($row['net_sales'], 0) / $maxTreeNet) * 100) }}%; background: var(--sd-primary);"></span></div>
                                                                @endif
                                                            </td>
                                                            {{-- الرقمُ شاملُ ما تحت العقدة، فقائمتُه كذلك --}}
                                                            @php
                                                                $nodeParams = json_encode(['node' => $row['branch_id'] ?? 'none', 'node_scope' => 'tree']);
                                                            @endphp
                                                            <td class="sd-num" data-segment="docs.sale" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0">{{ $row['invoices'] }}</td>
                                                            <td class="sd-num" data-segment="docs.sale" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0">{{ $money($row['sales']) }}</td>
                                                            <td class="sd-num" data-segment="docs.returns" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0" style="color: var(--sd-danger);">{{ $money($row['returns_total']) }}</td>
                                                            <td class="sd-num font-weight-bold" data-segment="clients.net" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0">{{ $money($row['net_sales']) }}</td>
                                                            <td class="sd-num" data-segment="docs.collection" data-segment-params="{{ $nodeParams }}" role="button" tabindex="0" style="color: var(--sd-success);">{{ $money($row['collections']) }}</td>
                                                        </tr>
                                    @endforeach
                                                </tbody>
                                                @php
                                                    /* مجموعُ الجذور وحدَها — كلُّ جذرٍ شاملٌ نسلَه أصلاً، فجمعُ الصفوف
                                                       كلِّها يُضاعف الفروعَ الفرعية. وهذا مجموعُ الشجرة المعروضة، لا
                                                       مجموعِ المنشأة إن كانت الشاشةُ مقتصرةً على فرعٍ بعينه. */
                                                    $treeRoots = $branchTree->where('depth', 0);
                                                    $treeTotals = [
                                                        'invoices' => $treeRoots->sum('invoices'),
                                                        'sales' => $treeRoots->sum('sales'),
                                                        'returns_total' => $treeRoots->sum('returns_total'),
                                                        'net_sales' => $treeRoots->sum('net_sales'),
                                                        'collections' => $treeRoots->sum('collections'),
                                                    ];
                                                @endphp
                                                <tfoot>
                                                    <tr>
                                                        <td>{{ __('sales_dashboard.reps.total_row') }}</td>
                                                        <td class="sd-num">{{ number_format($treeTotals['invoices']) }}</td>
                                                        <td class="sd-num">{{ $money($treeTotals['sales']) }}</td>
                                                        <td class="sd-num" style="color: var(--sd-danger);">{{ $money($treeTotals['returns_total']) }}</td>
                                                        <td class="sd-num font-weight-bold">{{ $money($treeTotals['net_sales']) }}</td>
                                                        <td class="sd-num" style="color: var(--sd-success);">{{ $money($treeTotals['collections']) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                    @if ($branchBreakdown->isNotEmpty())
                                        <div class="col-xl-5 col-12">
                                            <h6 class="sd-tree-chart-title">{{ __('sales_dashboard.tree.chart') }}</h6>
                                            <div id="sdBranchChart" style="min-height: 320px;"></div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- بطاقةُ «المندوبون» حُذفت من لوحة المبيعات بطلب المستخدم — جدولها
                 كثيف الأعمدة (١١ عموداً، وأهدافٌ وزياراتٌ متعدّدةُ الأسطر لكلّ
                 مندوب) ولم يعد مطلوباً هنا. القالبُ `partials.rep-table` وشاشةُ
                 «أداء المندوبين» المستقلّة (تستعمله أيضاً) بقيا كما هما. --}}

            {{-- ==================== الأصناف / آخر الحركات ==================== --}}
            <div class="row">
                <div class="col-lg-5 col-12">
                    <div class="sd-card">
                        <div class="sd-card-head">
                            <div>
                                <h4><i class="feather icon-box"></i> {{ __('sales_dashboard.cards.products') }}</h4>
                                <small>{{ __('sales_dashboard.cards.products_hint') }}</small>
                            </div>
                            <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="sd-card-body">
                            <div class="sd-info-panel" hidden>{{ __('sales_dashboard.info.products') }}</div>
                            @if ($topProducts->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="sd-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('sales_dashboard.table.product') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.quantity') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.sales') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.returns') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.net') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($topProducts as $product)
                                                <tr @if ($product['product_id']) data-segment="product.{{ $product['product_id'] }}" role="button" tabindex="0" @endif>
                                                    @php
                                                        $qty = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
                                                    @endphp
                                                    <td>
                                                        {{ $product['name'] }}
                                                        <small class="sd-rep-sub">{{ __('sales_dashboard.product.avg', ['price' => $money($product['avg_price'])]) }}</small>
                                                    </td>
                                                    <td class="sd-num">
                                                        @if ($product['has_multiple_units'] ?? false)
                                                            {{-- صنفٌ بيع بأكثر من وحدة (كرتون وحبّة مثلاً) — سطرٌ لكلّ
                                                                 وحدة بدل رقمٍ واحد مجموعٍ بلا معنى بينها --}}
                                                            @foreach ($product['units'] as $u)
                                                                <div>
                                                                    {{ $qty($u['net_quantity']) }} {{ $u['unit'] }}
                                                                    @if ($u['returned_quantity'] > 0)
                                                                        <small class="sd-rep-sub">{{ __('sales_dashboard.product.quantities', ['sold' => $qty($u['quantity']), 'returned' => $qty($u['returned_quantity'])]) }}</small>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        @else
                                                            {{ $qty($product['net_quantity']) }}
                                                            @if (($product['units'][0]['unit'] ?? '—') !== '—')
                                                                {{ $product['units'][0]['unit'] }}
                                                            @endif
                                                            @if ($product['returned_quantity'] > 0)
                                                                <small class="sd-rep-sub">{{ __('sales_dashboard.product.quantities', ['sold' => $qty($product['quantity']), 'returned' => $qty($product['returned_quantity'])]) }}</small>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="sd-num">{{ $money($product['amount']) }}</td>
                                                    <td class="sd-num" style="color: var(--sd-danger);">{{ $money($product['returned_amount']) }}</td>
                                                    <td class="sd-num font-weight-bold">{{ $money($product['net_amount']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sd-empty"><i class="feather icon-box"></i> {{ __('sales_dashboard.empty.no_products') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 col-12">
                    <div class="sd-card">
                        <div class="sd-card-head">
                            <div>
                                <h4><i class="feather icon-activity"></i> {{ __('sales_dashboard.cards.recent') }}</h4>
                                <small>{{ __('sales_dashboard.cards.recent_hint') }}</small>
                            </div>
                            <button type="button" class="sd-info-toggle" data-sd-info-toggle title="{{ $infoLabel }}" aria-label="{{ $infoLabel }}"><i class="feather icon-info"></i></button>
                        </div>
                        <div class="sd-card-body">
                            <div class="sd-info-panel" hidden>{{ __('sales_dashboard.info.recent') }}</div>
                            @if ($recentMovements->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="sd-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('sales_dashboard.table.document') }}</th>
                                                <th>{{ __('sales_dashboard.table.type') }}</th>
                                                <th>{{ __('sales_dashboard.table.client') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.date') }}</th>
                                                <th class="sd-num">{{ __('sales_dashboard.table.amount') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($recentMovements as $movement)
                                                @php
                                                    $tone = $kindColors[$movement['kind']] ?? 'var(--sd-muted)';
                                                @endphp
                                                <tr>
                                                    <td class="font-weight-bold">
                                                        @if ($movement['url'])
                                                            <a class="sd-drill" href="{{ $movement['url'] }}" target="_blank" rel="noopener">{{ $movement['code'] }}</a>
                                                        @else
                                                            {{ $movement['code'] }}
                                                        @endif
                                                    </td>
                                                    <td><span class="sd-badge" style="background: color-mix(in srgb, {{ $tone }} 14%, transparent); color: {{ $tone }};">{{ $movement['kind_label'] }}</span></td>
                                                    <td>{{ $movement['client_name'] }}</td>
                                                    <td class="sd-num">{{ $showDate($movement['date']) }}</td>
                                                    <td class="sd-num">{{ $money($movement['amount']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="sd-empty"><i class="feather icon-inbox"></i> {{ __('sales_dashboard.empty.no_movement') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @include('sales::dashboard.partials.drawer')

        </section>
    </div>
@endsection

@push('scripts')
    @include('sales::dashboard.partials.script')
    <script>
        (function () {
            'use strict';
            var openSegment = window.salesDashboard.openSegment;

            if (typeof ApexCharts === 'undefined') { return; }

            /*
             * --sd-primary معرَّفٌ بـ var(--app-primary, …)، وقراءتُه نصّاً تعيد
             * السلسلةَ كما كُتبت لا لوناً فيهجر ApexCharts ألوانَ الثيم. فيُسنَد
             * المتغيّر إلى خاصيّة لونٍ حقيقية على عنصر مسبار ويُقرأ rgb صريحاً.
             */
            var root = document.getElementById('sales-dashboard');
            var probe = document.createElement('span');
            probe.style.display = 'none';
            root.appendChild(probe);
            var SENTINEL = 'rgb(1, 2, 3)';
            var color = function (name, fallback) {
                probe.style.color = SENTINEL;
                probe.style.color = 'var(' + name + ')';
                var resolved = getComputedStyle(probe).color;

                return (!resolved || resolved === SENTINEL) ? fallback : resolved;
            };

            var palette = {
                primary: color('--sd-primary', '#7367f0'),
                success: color('--sd-success', '#28c76f'),
                danger: color('--sd-danger', '#ea5455'),
                warning: color('--sd-warning', '#ff9f43'),
                info: color('--sd-info', '#00cfe8'),
                muted: color('--sd-muted', '#7b8194'),
                border: color('--sd-border', '#e6e9f0'),
                font: getComputedStyle(root).fontFamily || 'Cairo, sans-serif'
            };

            var decimals = @json(\App\Support\CurrencySettings::decimals());
            var symbol = @json(\App\Support\CurrencySettings::symbol());
            var isRtl = document.documentElement.getAttribute('dir') === 'rtl';
            var labels = @json(__('sales_dashboard.kpi'));
            var tableLabels = @json(__('sales_dashboard.table'));

            var money = function (value) {
                return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            };

            var base = {
                chart: { fontFamily: palette.font, toolbar: { show: false } },
                dataLabels: { enabled: false },
                grid: { borderColor: palette.border, strokeDashArray: 4 },
                tooltip: { y: { formatter: function (v) { return money(v) + ' ' + symbol; } } },
                legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', labels: { colors: palette.muted } }
            };

            /*
             * الرسمُ بعد تحميل الخطّ، وإعادتُه إن تأخّر الخطّ عنه.
             *
             * ApexCharts يقيس عرضَ أسماء المحور مرّةً واحدة لحظةَ الرسم. فإن
             * رُسم قبل أن يصل خطّ Cairo قاس الأسماء بالخطّ البديل الأضيق، ثم
             * يصل Cairo فتعرض الأسماء وتنزلق فوق الأعمدة وتُقصّ بـ«…» — وهو
             * ما ظهر في «أعلى العملاء مديونية».
             */
            var charts = [];
            var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
            var render = function (selector, options) {
                var el = document.querySelector(selector);
                if (!el) { return; }
                fontsReady.then(function () {
                    var chart = new ApexCharts(el, options);
                    chart.render();
                    charts.push(chart);
                });
            };

            if (document.fonts && document.fonts.addEventListener) {
                document.fonts.addEventListener('loadingdone', function () {
                    charts.forEach(function (chart) { chart.updateOptions({}, true, false); });
                });
            }

            /* عنوان التلميح من الفئات صراحةً — تركُه لاستنتاج المكتبة يطبع
               «undefined» متى تعذّر ربط النقطة بفئتها */
            var titledTooltip = function (names) {
                return {
                    y: base.tooltip.y,
                    x: { formatter: function (value, opts) { var i = opts && opts.dataPointIndex; return (i != null && names[i]) ? names[i] : value; } }
                };
            };

            /* تسميات المحور تُبنى لكل رسم على حدة: المكتبة تعدّل ما يُمرَّر إليها،
               وإخفاءُ المتزاحم مُبطَل لأن الأسماء العربية الطويلة تُسقَط جميعاً */
            var categoryLabels = function () {
                return {
                    show: true, trim: true, hideOverlappingLabels: false, rotate: -30, rotateAlways: false, maxHeight: 70,
                    style: { colors: palette.muted, fontSize: '11px', fontFamily: palette.font }
                };
            };

            /* أرقام المحور مختصرة (50K، 1.2M) — بالمنازل العشرية كانت تُقصّ إلى
               «50,000…» تحت الأعمدة. والتلميح يبقى بالمبلغ كاملاً من `base.tooltip`. */
            var compact = (window.Intl && Intl.NumberFormat)
                ? new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 })
                : null;
            var moneyAxis = function () {
                return { labels: { style: { colors: palette.muted, fontSize: '11px' }, formatter: function (v) { return compact ? compact.format(Number(v || 0)) : money(v); } } };
            };

            /* اتجاه المبيعات والتحصيل */
            var trend = @json($trend);
            if (trend.labels && trend.labels.length) {
                render('#sdTrendChart', Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, {
                        type: 'area', height: 330,
                        // نقرُ يومٍ أو شهرٍ يفتح حركته — التلميحُ مشترك فلا سلسلةَ بعينها
                        events: {
                            click: function (event, ctx, config) {
                                var i = config ? config.dataPointIndex : -1;
                                if (i === undefined || i === null || i < 0 || !trend.buckets || !trend.buckets[i]) { return; }
                                var kind = config.seriesIndex === 0 ? 'sale' : (config.seriesIndex === 1 ? 'collection' : 'movement');
                                openSegment('trend.' + trend.buckets[i] + '.' + kind);
                            }
                        }
                    }),
                    series: [
                        { name: labels.sales, data: trend.sales },
                        { name: labels.collections, data: trend.collections }
                    ],
                    colors: [palette.primary, palette.info],
                    stroke: { curve: 'smooth', width: 3 },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .05, stops: [0, 90, 100] } },
                    xaxis: { categories: trend.labels, labels: categoryLabels() },
                    yaxis: Object.assign({ opposite: isRtl }, moneyAxis())
                }));
            }

            /* حالة السداد */
            var paymentStatus = @json($paymentStatusMix);
            if (paymentStatus.some(function (r) { return r.documents > 0; })) {
                render('#sdPaymentStatusChart', Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, {
                        type: 'donut', height: 250,
                        events: {
                            dataPointSelection: function (event, ctx, config) {
                                var row = paymentStatus[config.dataPointIndex];
                                if (row && row.documents > 0) { openSegment('status.' + row.key); }
                            }
                        }
                    }),
                    states: { active: { filter: { type: 'none' } } },
                    series: paymentStatus.map(function (r) { return r.amount; }),
                    labels: paymentStatus.map(function (r) { return r.label; }),
                    colors: [palette.success, palette.warning, palette.danger],
                    legend: { position: 'bottom', fontSize: '12px', labels: { colors: palette.muted } },
                    plotOptions: { pie: { donut: { labels: { show: true, total: {
                        show: true, label: tableLabels.total, fontSize: '13px', color: palette.muted,
                        formatter: function (w) { return money(w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0)); }
                    } } } } }
                }));
            }

            /* أعلى العملاء مديونية */
            var debtors = @json($topDebtors);
            var debtorNames = debtors.map(function (r) { return r.name; });
            if (debtors.length) {
                render('#sdDebtorsChart', Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, {
                        type: 'bar', height: 300,
                        events: {
                            dataPointSelection: function (event, ctx, config) {
                                var row = debtors[config.dataPointIndex];
                                if (row) { openSegment('client.' + row.client_id); }
                            }
                        }
                    }),
                    states: { active: { filter: { type: 'none' } } },
                    series: [{ name: tableLabels.balance, data: debtors.map(function (r) { return r.balance; }) }],
                    colors: [palette.warning],
                    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '62%' } },
                    xaxis: { categories: debtorNames, labels: moneyAxis().labels },
                    yaxis: { opposite: isRtl, labels: { maxWidth: 240, style: { colors: palette.muted, fontSize: '11px', fontFamily: palette.font } } },
                    legend: { show: false },
                    tooltip: titledTooltip(debtorNames)
                }));
            }

            /* مقارنة ما يتبع */
            var branches = @json($branchBreakdown);
            var branchNames = branches.map(function (r) { return r.name; });
            if (branches.length > 0) {
                render('#sdBranchChart', Object.assign({}, base, {
                    chart: Object.assign({}, base.chart, {
                        type: 'bar', height: Math.max(260, branches.length * 56 + 70),
                        events: {
                            dataPointSelection: function (event, ctx, config) {
                                var row = branches[config.dataPointIndex];
                                if (!row) { return; }
                                openSegment(config.seriesIndex === 1 ? 'docs.collection' : 'clients.net', null, row.branch_id === null
                                    ? { node: 'none', node_scope: 'tree' }
                                    : { node: row.branch_id, node_scope: row.is_self ? 'direct' : 'tree' });
                            }
                        }
                    }),
                    states: { active: { filter: { type: 'none' } } },
                    series: [
                        { name: tableLabels.net, data: branches.map(function (r) { return r.net_sales; }) },
                        { name: tableLabels.collections, data: branches.map(function (r) { return r.collections; }) }
                    ],
                    colors: [palette.primary, palette.info],
                    /* أفقيٌّ لا عمودي: الأسماء العربية الطويلة تُقرأ كاملةً على محور
                       الأسماء، وكانت تحت الأعمدة تُقصّ إلى «…منفـ» وتتزاحم، والمحورُ
                       المالي في الجهة المقابلة يُرسم فوق الأعمدة نفسها */
                    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
                    xaxis: { categories: branchNames, labels: moneyAxis().labels },
                    yaxis: { opposite: isRtl, labels: { maxWidth: 260, style: { colors: palette.muted, fontSize: '12px', fontFamily: palette.font } } },
                    grid: { borderColor: palette.border, strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
                    tooltip: titledTooltip(branchNames)
                }));
            }

            var resizeTimer;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function () { charts.forEach(function (chart) { chart.render(); }); }, 250);
            });
        })();
    </script>
@endpush
