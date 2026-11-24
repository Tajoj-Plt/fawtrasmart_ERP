{{--
    إدارة الحجوزات — على قياس «إدارة الفواتير» في المبيعات بالضبط
    (`sales::invoices.index`): ترويسةٌ فيها الإجراءات، ثمّ الإرشاد، ثمّ
    الإحصاءات في صفٍّ واحد، ثمّ بطاقة فلترة ببحثٍ أساسي و«بحث متقدّم»
    مطويّ، ثمّ بطاقة النتائج. الأصنافُ `rsv-*` نظيرةُ `sl-*` حرفاً بحرف.

    (كانت ثماني بطاقاتٍ في صفّين — اثنتان منها لمالٍ لم يعد في الحجز —
    وكلُّ الفلاتر مكشوفةً دفعةً واحدة بحقولٍ مصغّرة.)
--}}

@extends('client::reservations.master')

@section('title', __('reservations.menu.reservations'))

@section('content')
    @php
        $advancedKeys = ['employee_id', 'product_id', 'date_from', 'date_to', 'sort', 'tree_node'];
        $advancedOpen = collect($advancedKeys)->contains(fn ($key) => filled(request($key)) && ! ($key === 'sort' && request($key) === 'date_desc'));
        $hasFilters = request()->hasAny(array_merge(['q', 'client_id', 'status'], $advancedKeys));
        $selectedStatus = collect((array) request('status', []))->first();
    @endphp

    <div class="content-body">

        @section('page-actions')
            @if (hasPermission('reservations.create'))
                <a href="{{ route('Reservations.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('reservations.menu.create') }}
                </a>
            @endif
            @if (hasPermission('reservations.calendar'))
                <a href="{{ route('Reservations.calendar') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-calendar"></i> {{ __('reservations.menu.calendar') }}
                </a>
            @endif
            @if (hasPermission('reservation_reports.export'))
                <a href="{{ route('Reservations.export', request()->query()) }}" class="btn btn-outline-secondary">
                    <i class="feather icon-download"></i> {{ __('reservations.actions.export_csv') }}
                </a>
            @endif
        @endsection

        @include('client::reservations.components.page-head', [
            'title' => __('reservations.menu.reservations'),
            'icon' => 'bookmark',
            'sub' => __('reservations.hints.index_sub'),
            'crumbs' => [__('reservations.menu.home') => route('dashboard'), __('reservations.menu.reservations') => null],
        ])

        @include('client::reservations.partials.alerts')
        @include('client::reservations.partials.table-menu')

        @include('client::reservations.components.guide', [
            'key' => 'reservations.index',
            'open' => false,
            'title' => __('reservations.guide.index_title'),
            'lead' => __('reservations.hints.index_sub'),
            'blocks' => [__('reservations.guide.index_title') => __('reservations.guide.index_points')],
        ])

        {{-- ══════════ الإحصاءات — بالفلاتر نفسِها التي تحكم الجدول ══════════ --}}
        <div class="rsv-stats">
            @foreach ([
                ['total', 'list', '', $summary['total']],
                ['today', 'sun', '--info', $summary['today']],
                ['upcoming', 'clock', '--warning', $summary['upcoming']],
                ['completed', 'check-square', '--success', $summary['completed']],
            ] as [$cardKey, $cardIcon, $cardTone, $cardValue])
                <div class="rsv-stat">
                    <span class="rsv-stat__icon {{ $cardTone ? 'rsv-stat__icon' . $cardTone : '' }}"><i class="feather icon-{{ $cardIcon }}"></i></span>
                    <div class="rsv-stat__body">
                        <p class="rsv-stat__value">{{ number_format($cardValue) }}</p>
                        <p class="rsv-stat__label">{{ __('reservations.cards.' . $cardKey) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ══════════ البحث والتصفية ══════════
             GET لا POST: تبقى النتيجة في الرابط ويصلح حفظُها ومشاركتُها --}}
        <div class="card rsv-filters rsv-no-print">
            <div class="card-body">
                <div class="rsv-filters__head">
                    <h6 class="rsv-filters__title">
                        <i class="feather icon-filter"></i> {{ __('reservations.ui.filters') }}
                    </h6>

                    <div class="d-flex align-items-center" style="gap:.5rem">
                        @if ($hasFilters)
                            <span class="rsv-count">{{ __('reservations.ui.filters_active') }}</span>
                        @endif

                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
                                data-bs-toggle="collapse" data-target="#rsvAdvancedSearch"
                                data-bs-target="#rsvAdvancedSearch" aria-expanded="{{ $advancedOpen ? 'true' : 'false' }}">
                            <i class="feather icon-sliders"></i> {{ __('reservations.ui.advanced_search') }}
                        </button>
                    </div>
                </div>

                <form method="GET" action="{{ route('Reservations.index') }}" id="rsvSearchForm">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="rsv-q">{{ __('reservations.filters.quick_search') }}</label>
                                <input type="search" id="rsv-q" name="q" value="{{ request('q') }}" class="form-control"
                                       placeholder="{{ __('reservations.filters.quick_search_hint') }}">
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="rsv-client-filter">{{ __('reservations.columns.client') }}</label>
                                <select id="rsv-client-filter" name="client_id" class="form-control rsv-client-select">
                                    <option value="">{{ __('reservations.ui.all') }}</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>{{ $client->display_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4 col-sm-6">
                            <div class="form-group">
                                <label for="rsv-status">{{ __('reservations.columns.status') }}</label>
                                <select id="rsv-status" name="status[]" class="form-control">
                                    <option value="">{{ __('reservations.ui.all') }}</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="collapse {{ $advancedOpen ? 'show' : '' }}" id="rsvAdvancedSearch">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="rsv-employee-filter">{{ __('reservations.columns.employee') }}</label>
                                    <select id="rsv-employee-filter" name="employee_id" class="form-control">
                                        <option value="">{{ __('reservations.ui.all') }}</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="rsv-service-filter">{{ __('reservations.columns.service') }}</label>
                                    <select id="rsv-service-filter" name="product_id" class="form-control">
                                        <option value="">{{ __('reservations.ui.all') }}</option>
                                        @foreach ($services as $service)
                                            <option value="{{ $service->id }}" @selected(request('product_id') == $service->id)>{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="rsv-sort">{{ __('reservations.filters.sort') }}</label>
                                    <select id="rsv-sort" name="sort" class="form-control">
                                        @foreach ($sortOptions as $sortValue => $sortLabel)
                                            <option value="{{ $sortValue }}" @selected(request('sort', 'date_desc') === $sortValue)>{{ $sortLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label>{{ __('reservations.columns.date') }}</label>
                                    <div class="input-group">
                                        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"
                                               aria-label="{{ __('reservations.attributes.date_from') }}">
                                        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"
                                               aria-label="{{ __('reservations.attributes.date_to') }}">
                                    </div>
                                </div>
                            </div>

                            {{-- «تابع لـ» — الشجرةُ نفسُها التي في التقارير --}}
                            <div class="col-12">
                                <div class="row">
                                    @include('reports.partials.affiliation-tree', ['treeNodes' => $treeNodes ?? []])
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rsv-filters__foot">
                        <a href="{{ route('Reservations.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-rotate-ccw"></i> {{ __('reservations.actions.reset') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-search"></i> {{ __('reservations.actions.search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ══════════ النتائج ══════════ --}}
        <div class="card rsv-table-card">
            <div class="rsv-table-head">
                <h6 class="rsv-table-head__title">
                    <i class="feather icon-list"></i> {{ __('reservations.menu.reservations') }}
                    <span class="rsv-count">{{ number_format($reservations->total()) }}</span>
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table rsv-table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('reservations.columns.code') }}</th>
                            <th>{{ __('reservations.columns.client') }}</th>
                            <th>{{ __('reservations.columns.service') }}</th>
                            <th>{{ __('reservations.columns.employee') }}</th>
                            <th>{{ __('reservations.columns.date') }}</th>
                            <th>{{ __('reservations.columns.status') }}</th>
                            <th class="text-end">{{ __('reservations.columns.total') }}</th>
                            <th class="rsv-no-print">{{ __('reservations.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reservations as $reservation)
                            <tr>
                                <td>
                                    <a href="{{ route('Reservations.show', $reservation->id) }}" class="fw-bold">{{ $reservation->code }}</a>
                                    {{-- من الفاتورة القائمة لا من `invoice_id` وحده: فاتورةٌ حُذفت لا تُبقي الحجزَ «مفوتَراً» --}}
                                    @if ($reservation->invoice)
                                        <span class="d-block mt-1">
                                            @if (Route::has('invoices.show'))
                                                <a href="{{ route('invoices.show', $reservation->invoice->id) }}" class="rsv-badge text-white" style="background: #27ae60">
                                                    <i class="feather icon-file-text"></i> {{ __('reservations.links.invoiced_badge') }} {{ $reservation->invoice->code }}
                                                </a>
                                            @else
                                                <span class="rsv-badge" style="background: #27ae60">{{ __('reservations.links.invoiced_badge') }} {{ $reservation->invoice->code }}</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    {{ $reservation->client_name }}
                                    @if ($reservation->client?->mobile || $reservation->client?->phone)
                                        <span class="rsv-muted d-block" dir="ltr">{{ $reservation->client->mobile ?: $reservation->client->phone }}</span>
                                    @endif
                                </td>
                                <td>{{ $reservation->service_name ?: '—' }}</td>
                                <td>{{ $reservation->employee?->full_name ?? '—' }}</td>
                                <td>
                                    {{ $reservation->reservation_date?->format('Y-m-d') }}
                                    <span class="rsv-muted d-block" dir="ltr">
                                        {{ substr((string) $reservation->start_time, 0, 5) }} – {{ substr((string) $reservation->end_time, 0, 5) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="rsv-badge" style="background: {{ $reservation->status->color() }}">{{ $reservation->status->label() }}</span>
                                </td>
                                <td class="text-end rsv-money">{{ number_format((float) $reservation->total, 2) }}</td>
                                {{-- قائمةُ إجراءات الصفّ — زرُّ «⋮» وقائمته، كفواتير المبيعات حرفاً.
                                     `data-bs-toggle` وحده: الوسمان معاً يجعلان بوتستراب ٤ و٥ يعالجان
                                     النقرة نفسها فلا تُفتح. و`partials.table-menu` يُخرجها من قصّ الجدول. --}}
                                <td class="text-center rsv-no-print">
                                    @php
                                        $rowFinal = $reservation->status->isFinal();
                                        // القاعدةُ في النموذج — هي نفسُها التي تحرس `toInvoice` وشاشةَ الفاتورة
                                        $rowInvoiced = $reservation->isInvoiced();
                                        $rowInvoiceable = hasPermission('reservations.create_invoice') && $reservation->canBeInvoiced();
                                    @endphp
                                    <div class="dropdown">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                aria-label="{{ __('reservations.columns.actions') }} {{ $reservation->code }}">
                                            <i class="feather icon-more-vertical"></i>
                                        </button>

                                        <div class="dropdown-menu dropdown-menu-end">
                                            @if (hasPermission('reservations.show'))
                                                <a class="dropdown-item" href="{{ route('Reservations.show', $reservation->id) }}">
                                                    <i class="feather icon-eye"></i> {{ __('reservations.actions.view') }}
                                                </a>
                                            @endif
                                            @if (hasPermission('reservations.edit') && ! $rowFinal && ! $rowInvoiced)
                                                <a class="dropdown-item" href="{{ route('Reservations.edit', $reservation->id) }}">
                                                    <i class="feather icon-edit-2"></i> {{ __('reservations.actions.edit') }}
                                                </a>
                                            @endif
                                            @if (hasPermission('reservations.show'))
                                                <a class="dropdown-item" href="{{ route('Reservations.print', $reservation->id) }}" target="_blank">
                                                    <i class="feather icon-printer"></i> {{ __('reservations.actions.print') }}
                                                </a>
                                            @endif

                                            @if ($rowInvoiceable || $rowInvoiced)
                                                <div class="dropdown-divider"></div>
                                                @if ($rowInvoiced && Route::has('invoices.show'))
                                                    <a class="dropdown-item" href="{{ route('invoices.show', $reservation->invoice->id) }}">
                                                        <i class="feather icon-file-text"></i> {{ __('reservations.ui.invoice') }}
                                                    </a>
                                                @elseif ($rowInvoiceable)
                                                    <a class="dropdown-item" href="{{ route('Reservations.toInvoice', $reservation->id) }}">
                                                        <i class="feather icon-file-plus"></i> {{ __('reservations.actions.to_invoice') }}
                                                    </a>
                                                @endif
                                            @endif

                                            @php
                                                $rowPhone = preg_replace('/\D+/', '', (string) ($reservation->client?->mobile ?: $reservation->client?->phone));
                                            @endphp
                                            @if ($rowPhone)
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" target="_blank" rel="noopener"
                                                   href="https://wa.me/{{ ltrim($rowPhone, '0') }}?text={{ rawurlencode(__('reservations.send.message', [
                                                       'client' => $reservation->client_name,
                                                       'code' => $reservation->code,
                                                       'service' => $reservation->service_name ?: '—',
                                                       'date' => $reservation->reservation_date?->format('Y-m-d'),
                                                       'time' => substr((string) $reservation->start_time, 0, 5),
                                                       'employee' => $reservation->employee?->full_name ?: '—',
                                                   ])) }}">
                                                    <i class="feather icon-message-circle"></i> {{ __('reservations.actions.send_whatsapp') }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="rsv-empty">
                                        <div class="rsv-empty__icon"><i class="feather icon-calendar"></i></div>
                                        <p class="rsv-empty__title">{{ __('reservations.ui.no_results') }}</p>
                                        <p class="rsv-empty__hint">{{ __('reservations.hints.no_results') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($reservations->hasPages())
                <div class="card-body rsv-no-print">{{ $reservations->links() }}</div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        /*
            select2 على منتقي العميل وحدَه — لا `$('select').select2()` عامّاً:
            المسحُ العامّ يلتقط الحاويةَ التي أنشأها select2 نفسُه فيهيّئها
            مرّةً ثانية. وjQuery محمَّلٌ في القالب الأمّ — لا يُعاد تحميله.
        */
        $(function () {
            if ($.fn.select2) {
                $('.rsv-client-select').select2({
                    dir: @json($languageDirection ?? 'rtl'),
                    width: '100%',
                    placeholder: @json(__('reservations.filters.client_search')),
                    allowClear: true,
                    ajax: {
                        url: @json(route('Reservations.clients')),
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term }),
                        processResults: data => ({ results: data.results }),
                    },
                    minimumInputLength: 0,
                });
            }
        });
    </script>
@endsection
