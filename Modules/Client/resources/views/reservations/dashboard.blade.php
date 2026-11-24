@extends('client::reservations.master')

@section('title', __('reservations.menu.dashboard'))

@section('content')
    @php
        $money = fn ($value) => number_format((float) $value, 2);

        /*
            كلُّ بطاقةٍ رابطٌ إلى القائمة بالفلاتر نفسها: «لديك 18 حجزاً
            اليوم» تُضغط فتظهر الثمانيةَ عشرَ. والفلاترُ المختارةُ هنا (الجهة
            والموظّف والخدمة) تنتقل معها فلا يفترق الرقمُ عمّا تحته.
        */
        $carry = request()->only(['tree_node', 'exact_node', 'employee_id', 'product_id']);
        $range = ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()];
        $today = now()->toDateString();
        $list = fn (array $query) => hasPermission('reservations.view')
            ? route('Reservations.index', array_merge($carry, $query))
            : null;
        $report = fn (string $key) => hasPermission('reservation_reports.view')
            ? route('Reservations.reports.show', array_merge([$key], $carry, $range))
            : null;

        $cards = [
            ['today', 'sun', '', number_format($cards['today']), $list(['date_from' => $today, 'date_to' => $today, 'sort' => 'date_asc'])],
            ['upcoming', 'clock', '--info', number_format($cards['upcoming']), $list(['date_from' => $today, 'status' => ['draft', 'pending', 'confirmed'], 'sort' => 'date_asc'])],
            ['pending', 'alert-circle', '--warning', number_format($cards['pending']), $list($range + ['status' => ['draft', 'pending']])],
            ['confirmed', 'check-circle', '--info', number_format($cards['confirmed']), $list($range + ['status' => ['confirmed']])],
            ['in_service', 'play-circle', '', number_format($cards['in_service']), $list($range + ['status' => ['checked_in', 'in_progress']])],
            ['completed', 'check-square', '--success', number_format($cards['completed']), $list($range + ['status' => ['completed']])],
            ['cancelled', 'x-circle', '--danger', number_format($cards['cancelled']), $list($range + ['status' => ['cancelled', 'rejected']])],
            ['no_show', 'user-x', '--warning', number_format($cards['no_show']), $list($range + ['status' => ['no_show']])],
            ['value', 'layers', '', $money($cards['value']), $list($range)],
            ['revenue', 'trending-up', '--success', $money($cards['revenue']), $report('revenue')],
        ];
    @endphp

    @section('page-actions')
        @if (hasPermission('reservations.calendar'))
            <a href="{{ route('Reservations.calendar') }}" class="btn btn-outline-secondary">
                <i class="feather icon-calendar"></i> {{ __('reservations.menu.calendar') }}
            </a>
        @endif
        @if (hasPermission('reservations.create'))
            <a href="{{ route('Reservations.create') }}" class="btn btn-primary">
                <i class="feather icon-plus"></i> {{ __('reservations.menu.create') }}
            </a>
        @endif
    @endsection

    @include('client::reservations.components.page-head', [
        'title' => __('reservations.menu.dashboard'),
        'icon' => 'activity',
        'sub' => __('reservations.hints.dashboard_sub', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]),
        'crumbs' => [
            __('reservations.menu.home') => route('dashboard'),
            __('reservations.menu.reservations') => route('Reservations.index'),
            __('reservations.menu.dashboard') => null,
        ],
    ])

    @include('client::reservations.partials.alerts')
    @include('client::reservations.partials.readiness-banner')

    {{-- الفلترة على قياس شاشات النظام: رأسٌ بعنوان، وحقولٌ بحجمها العادي، والإجراءان في الذيل --}}
    <div class="card rsv-filters rsv-no-print">
        <div class="card-body">
            <div class="rsv-filters__head">
                <h6 class="rsv-filters__title"><i class="feather icon-filter"></i> {{ __('reservations.ui.filters') }}</h6>
            </div>

            <form method="GET" action="{{ route('Reservations.dashboard') }}">
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>{{ __('reservations.columns.date') }}</label>
                            <div class="input-group">
                                <input type="date" name="date_from" value="{{ $from->format('Y-m-d') }}" class="form-control"
                                       aria-label="{{ __('reservations.attributes.date_from') }}">
                                <input type="date" name="date_to" value="{{ $to->format('Y-m-d') }}" class="form-control"
                                       aria-label="{{ __('reservations.attributes.date_to') }}">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="rsv-dash-employee">{{ __('reservations.columns.employee') }}</label>
                            <select id="rsv-dash-employee" name="employee_id" class="form-control">
                                <option value="">{{ __('reservations.ui.all') }}</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label for="rsv-dash-service">{{ __('reservations.columns.service') }}</label>
                            <select id="rsv-dash-service" name="product_id" class="form-control">
                                <option value="">{{ __('reservations.ui.all') }}</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" @selected(request('product_id') == $service->id)>{{ $service->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="row">
                            @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                        </div>
                    </div>
                </div>

                <div class="rsv-filters__foot">
                    <a href="{{ route('Reservations.dashboard') }}" class="btn btn-outline-secondary">
                        <i class="feather icon-rotate-ccw"></i> {{ __('reservations.actions.reset') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-search"></i> {{ __('reservations.actions.apply') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="rsv-stats">
        @foreach ($cards as [$key, $icon, $tone, $value, $href])
            @php $tag = $href ? 'a' : 'div'; @endphp
            <{{ $tag }} class="rsv-stat" @if ($href) href="{{ $href }}" style="text-decoration:none;color:inherit" @endif>
                <div class="rsv-stat__icon {{ $tone ? 'rsv-stat__icon' . $tone : '' }}"><i class="feather icon-{{ $icon }}"></i></div>
                <div class="rsv-stat__body">
                    <p class="rsv-stat__value">{{ $value }}</p>
                    <p class="rsv-stat__label">{{ __('reservations.cards.' . $key) }}</p>
                    @if (in_array($key, ['today', 'upcoming'], true))
                        <span class="rsv-hint">{{ __('reservations.hints.card_not_ranged') }}</span>
                    @elseif ($key === 'revenue')
                        <span class="rsv-hint">{{ __('reservations.hints.card_revenue') }}</span>
                    @endif
                </div>
            </{{ $tag }}>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- ═════════ مواعيد اليوم ═════════ --}}
        <div class="col-lg-8">
            <div class="rsv-card">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-sun"></i> {{ __('reservations.ui.today_list') }}</h6>
                    <span class="rsv-muted">{{ now()->format('Y-m-d') }}</span>
                </div>
                <div class="rsv-scroll">
                    <table class="table rsv-table">
                        <thead>
                            <tr>
                                <th>{{ __('reservations.columns.from') }}</th>
                                <th>{{ __('reservations.columns.code') }}</th>
                                <th>{{ __('reservations.columns.client') }}</th>
                                <th>{{ __('reservations.columns.service') }}</th>
                                <th>{{ __('reservations.columns.employee') }}</th>
                                <th>{{ __('reservations.columns.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($todayList as $r)
                                <tr>
                                    <td class="fw-bold">{{ substr((string) $r->start_time, 0, 5) }}</td>
                                    <td><a href="{{ route('Reservations.show', $r->id) }}">{{ $r->code }}</a></td>
                                    <td>
                                        {{ $r->client_name }}
                                        @if ($r->client?->phone || $r->client?->mobile)
                                            <span class="rsv-muted d-block" dir="ltr">{{ $r->client->phone ?: $r->client->mobile }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $r->service_name }}</td>
                                    <td>{{ $r->employee?->full_name ?? '—' }}</td>
                                    <td><span class="rsv-badge" style="background: {{ $r->status->color() }}">{{ $r->status->label() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="rsv-empty">
                                    <div class="rsv-empty__icon"><i class="feather icon-coffee"></i></div>
                                    <p class="rsv-empty__title">{{ __('reservations.ui.no_today') }}</p>
                                </div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ═════════ توزيع الحالات ═════════ --}}
        <div class="col-lg-4">
            <div class="rsv-card">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-pie-chart"></i> {{ __('reservations.ui.by_status') }}</h6>
                </div>
                <div class="rsv-card__body">
                    @php $max = max(1, collect($byStatus)->max('count')); @endphp
                    @forelse (collect($byStatus)->where('count', '>', 0) as $row)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between" style="font-size:.82rem">
                                <span>{{ $row['status']->label() }}</span>
                                <strong>{{ number_format($row['count']) }}</strong>
                            </div>
                            {{-- شريطٌ بسيط: لا مكتبة رسم لأجل عشرة أرقام --}}
                            <div style="height:6px;border-radius:4px;background:var(--rsv-line, #eef0f6);overflow:hidden">
                                <div style="height:100%;width:{{ round(($row['count'] / $max) * 100) }}%;background:{{ $row['status']->color() }}"></div>
                            </div>
                        </div>
                    @empty
                        <p class="rsv-muted text-center mb-0">{{ __('reservations.ui.no_data') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ═════════ القادمة ═════════ --}}
        <div class="col-lg-6">
            <div class="rsv-card">
                <div class="rsv-card__head">
                    <h6 class="rsv-card__title"><i class="feather icon-clock"></i> {{ __('reservations.ui.upcoming_list') }}</h6>
                </div>
                <div class="rsv-scroll">
                    <table class="table rsv-table">
                        <thead><tr>
                            <th>{{ __('reservations.columns.date') }}</th><th>{{ __('reservations.columns.from') }}</th>
                            <th>{{ __('reservations.columns.client') }}</th><th>{{ __('reservations.columns.service') }}</th>
                        </tr></thead>
                        <tbody>
                            @forelse ($upcomingList as $r)
                                <tr>
                                    <td>{{ $r->reservation_date?->format('Y-m-d') }}</td>
                                    <td class="rsv-muted">{{ substr((string) $r->start_time, 0, 5) }}</td>
                                    <td><a href="{{ route('Reservations.show', $r->id) }}">{{ $r->client_name }}</a></td>
                                    <td>{{ $r->service_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="rsv-muted text-center py-3">{{ __('reservations.ui.no_upcoming') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ═════════ حسب الجهة التابعة — كلُّ جهةٍ بنوعها ═════════ --}}
        <div class="col-lg-6">
            <div class="rsv-card">
                <div class="rsv-card__head"><h6 class="rsv-card__title"><i class="feather icon-git-branch"></i> {{ __('reservations.ui.by_branch') }}</h6></div>
                <div class="rsv-scroll">
                    <table class="table rsv-table">
                        {{-- لا عمودَ «مدفوع»: لا مالَ في الحجز، والتحصيلُ على فاتورة المبيعات --}}
                        <thead><tr>
                            <th>{{ __('reservations.columns.branch') }}</th><th class="text-end">{{ __('reservations.columns.count') }}</th>
                            <th class="text-end">{{ __('reservations.columns.total') }}</th>
                        </tr></thead>
                        <tbody>
                            @forelse ($byBranch as $row)
                                <tr>
                                    <td>
                                        {{ $row->branch?->name ?? '#' . $row->branch_id }}
                                        @if ($row->branch)
                                            <span class="rsv-muted">— {{ $row->branch->displayTypeLabel() }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold">{{ number_format($row->c) }}</td>
                                    <td class="text-end rsv-money">{{ $money($row->t) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="rsv-muted text-center py-3">{{ __('reservations.ui.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ═════════ الخدمات والموظفون ═════════ --}}
        <div class="col-lg-6">
            <div class="rsv-card">
                <div class="rsv-card__head"><h6 class="rsv-card__title"><i class="feather icon-briefcase"></i> {{ __('reservations.ui.by_service') }}</h6></div>
                <div class="rsv-scroll">
                    <table class="table rsv-table">
                        <thead><tr><th>{{ __('reservations.columns.service') }}</th><th class="text-end">{{ __('reservations.columns.count') }}</th><th class="text-end">{{ __('reservations.columns.total') }}</th></tr></thead>
                        <tbody>
                            @forelse ($topServices as $row)
                                <tr><td>{{ $row->product?->name ?? '—' }}</td><td class="text-end fw-bold">{{ $row->c }}</td><td class="text-end rsv-money">{{ $money($row->t) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="rsv-muted text-center py-3">{{ __('reservations.ui.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="rsv-card">
                <div class="rsv-card__head"><h6 class="rsv-card__title"><i class="feather icon-users"></i> {{ __('reservations.ui.by_employee') }}</h6></div>
                <div class="rsv-scroll">
                    <table class="table rsv-table">
                        <thead><tr><th>{{ __('reservations.columns.employee') }}</th><th class="text-end">{{ __('reservations.columns.count') }}</th><th class="text-end">{{ __('reservations.columns.total') }}</th></tr></thead>
                        <tbody>
                            @forelse ($topEmployees as $row)
                                <tr><td>{{ $row->employee?->full_name ?? '—' }}</td><td class="text-end fw-bold">{{ $row->c }}</td><td class="text-end rsv-money">{{ $money($row->t) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="rsv-muted text-center py-3">{{ __('reservations.ui.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
