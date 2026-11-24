{{--
    جدول الفواتير الدورية — يُستبدل وحده عند البحث والتنقّل بـAjax.

    نظير `sales::invoices.partials.table`.

    ما أُصلح:
      • *العملة*: كانت تُقرأ من `account_setting` — إعداد حسابٍ شخصيّ لكل
        مستخدم — ثمّ يُستبدل بالرمز صورةُ ريالٍ ثابتة مهما كانت العملة.
      • *نوع التكرار سلسلةُ `elseif`* تغطّي ثلاثة أنواع من خمسة، فالربع
        السنوي والسنوي يظهران فارغَين. صار من ملف اللغة.
      • *بلا رقم مستند*: الجدول يعرض اسم الاشتراك ولا يعرض رقم الفاتورة
        الدورية إطلاقاً، ولا حالتَها، ولا كم فاتورةً صدرت منها.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $periodicInvoices
--}}

@php
    $repeatTypes = (array) __('sales_periodic.repeat_types');
@endphp

@if ($periodicInvoices->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">
        @include('sales::components.per-page', ['onchange' => 'loadData(1)'])
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr>
                    <th style="width:18%">{{ __('sales_periodic.filters.number') }}</th>
                    <th style="width:22%">{{ __('sales_periodic.table.subscription') }}</th>
                    <th>{{ __('branches.node_filter.column') }}</th>
                    <th style="width:16%">{{ __('sales_periodic.table.first_date') }}</th>
                    <th style="width:14%">{{ __('sales_periodic.table.repeat') }}</th>
                    <th style="width:14%">{{ __('sales_invoice.table.total') }}</th>
                    <th class="text-center" style="width:10%">{{ __('sales_periodic.filters.state') }}</th>
                    <th class="text-center" style="width:6%">{{ __('sales_invoice.table.actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($periodicInvoices as $preInvoice)
                    @php
                        $clientName = $preInvoice->client
                            ? ($preInvoice->client->trade_name ?: trim($preInvoice->client->first_name.' '.$preInvoice->client->last_name))
                            : __('sales_invoice.table.unknown_client');

                        $repeatLabel = $repeatTypes[$preInvoice->repeat_type] ?? __('sales_invoice.status.unknown');
                        $isActive = (bool) $preInvoice->is_active;
                    @endphp

                    <tr>
                        <td>
                            <div class="sl-media">
                                <span class="sl-media__thumb text-primary"><i class="feather icon-repeat"></i></span>
                                <div class="sl-media__body">
                                    <a href="{{ route('periodic_invoices.show', $preInvoice->id) }}" class="sl-media__title">
                                        {{ $preInvoice->invoice_number }}
                                    </a>
                                    <span class="sl-media__sub sl-num">#{{ $preInvoice->id }}</span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="sl-media__title d-block">{{ $preInvoice->details_subscription ?: '—' }}</span>
                            <span class="sl-media__sub">{{ $clientName }}</span>
                        </td>

                        {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                        <td>@include('partials.branch-node-path', ['branchId' => $preInvoice->branch_id])</td>

                        <td>
                            <span class="sl-num d-block">
                                {{ \App\Support\LanguageSettings::formatDate($preInvoice->first_invoice_date) }}
                            </span>
                            <span class="sl-media__sub">
                                {{ __('sales_periodic.table.issued_of', [
                                    'done' => $preInvoice->instances_count ?? 0,
                                    'total' => $preInvoice->repeat_count ?: '∞',
                                ]) }}
                            </span>
                        </td>

                        <td>
                            <span class="badge badge-light-primary">{{ $repeatLabel }}</span>
                            @if (($preInvoice->repeat_interval ?? 1) > 1)
                                <span class="sl-media__sub d-block sl-num">× {{ $preInvoice->repeat_interval }}</span>
                            @endif
                        </td>

                        <td>
                            <span class="sl-num">
                                {{ \App\Support\CurrencySettings::format($preInvoice->grand_total) }}
                            </span>
                            @if (($preInvoice->total_tax ?? 0) > 0)
                                <span class="sl-media__sub d-block">
                                    {{ __('sales_invoice.tax') }}:
                                    {{ \App\Support\CurrencySettings::format($preInvoice->total_tax) }}
                                </span>
                            @endif
                        </td>

                        <td class="text-center">
                            <span class="badge badge-light-{{ $isActive ? 'success' : 'secondary' }}">
                                {{ $isActive ? __('sales_periodic.state.active') : __('sales_periodic.state.paused') }}
                            </span>

                            @if ($preInvoice->auto_generate)
                                <span class="sl-media__sub d-block">
                                    <i class="feather icon-zap"></i> {{ __('sales_periodic.form.auto_generate') }}
                                </span>
                            @endif
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="{{ __('sales_invoice.table.actions') }} {{ $preInvoice->invoice_number }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('periodic_invoices.show', $preInvoice->id) }}">
                                        <i class="feather icon-eye"></i> {{ __('sales_invoice.actions.show') }}
                                    </a>

                                    @permission('sales.periodic_invoices.edit')
                                        <a class="dropdown-item" href="{{ route('periodic_invoices.edit', $preInvoice->id) }}">
                                            <i class="feather icon-edit-2"></i> {{ __('sales_invoice.actions.edit') }}
                                        </a>
                                    @endpermission

                                    @permission('sales.periodic_invoices.delete')
                                        <div class="dropdown-divider"></div>

                                        <a class="dropdown-item text-danger periodic-delete" href="#"
                                           data-id="{{ $preInvoice->id }}" data-number="{{ $preInvoice->invoice_number }}">
                                            <i class="feather icon-trash-2"></i> {{ __('sales_invoice.actions.delete') }}
                                        </a>
                                    @endpermission
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('sales::periodic_invoices.partials.pagination', ['periodicInvoices' => $periodicInvoices])
@else
    @include('sales::components.empty-state', [
        'icon' => 'repeat',
        'title' => __('sales_periodic.empty.title'),
        'hint' => __('sales_periodic.empty.hint'),
    ])
@endif
