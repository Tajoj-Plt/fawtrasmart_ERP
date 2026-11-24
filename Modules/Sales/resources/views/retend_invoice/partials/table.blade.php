{{--
    جدول الفواتير المرتجعة — يُستبدل وحده عند البحث والتنقّل بـAjax.

    نظير `sales::invoices.partials.table`.

    ما أُصلح:
      • *العملة*: كانت تُقرأ من `account_setting` — إعداد حسابٍ شخصيّ لكل
        مستخدم — ثمّ يُستبدل بالرمز صورةُ ريالٍ ثابتة مهما كانت العملة.
      • *التاريخ*: بصيغة اللغة النشطة لا بصيغة مكتوبة.
      • *الفاتورة الأصلية*: `reference_number` يحمل معرّف الفاتورة التي
        صدر عنها المرتجع، وكان يُعرَض رقماً خاماً بلا رابط.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $return
--}}

@php
    // الفواتير الأصلية دفعةً واحدة: كان كل صفٍّ يسألها وحده
    $sourceIds = collect($return->items())->pluck('reference_number')->filter()->unique()->all();
    $sources = $sourceIds
        ? \App\Models\Invoice::whereIn('id', $sourceIds)->pluck('code', 'id')
        : collect();
@endphp

@if ($return->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">
        @include('sales::components.per-page', ['onchange' => 'loadData(1)'])
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr>
                    <th style="width:16%">{{ __('sales_return.filters.number') }}</th>
                    <th style="width:22%">{{ __('sales_invoice.table.client') }}</th>
                    <th>{{ __('branches.node_filter.column') }}</th>
                    <th style="width:16%">{{ __('sales_return.filters.source_invoice') }}</th>
                    <th style="width:20%">{{ __('sales_invoice.table.date') }}</th>
                    <th style="width:14%">{{ __('sales_invoice.table.total') }}</th>
                    <th class="text-center" style="width:6%">{{ __('sales_invoice.table.actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($return as $item)
                    @php
                        $clientName = $item->client
                            ? ($item->client->trade_name ?: trim($item->client->first_name.' '.$item->client->last_name))
                            : __('sales_invoice.table.unknown_client');

                        $sourceCode = $sources[$item->reference_number] ?? null;
                    @endphp

                    <tr>
                        <td>
                            <div class="sl-media">
                                <span class="sl-media__thumb text-danger"><i class="feather icon-corner-up-left"></i></span>
                                <div class="sl-media__body">
                                    <a href="{{ route('ReturnIInvoices.show', $item->id) }}" class="sl-media__title">
                                        {{ $item->code }}
                                    </a>
                                    <span class="sl-media__sub sl-num">#{{ $item->id }}</span>
                                </div>
                            </div>
                        </td>

                        <td><span class="sl-media__sub">{{ $clientName }}</span></td>

                        {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                        <td>@include('partials.branch-node-path', ['branchId' => $item->branch_id])</td>

                        <td>
                            @if ($sourceCode)
                                @permission('sales.invoices.view')
                                    <a href="{{ route('invoices.show', $item->reference_number) }}" class="sl-code">
                                        {{ $sourceCode }}
                                    </a>
                                @else
                                    <span class="sl-code">{{ $sourceCode }}</span>
                                @endpermission
                            @else
                                <span class="sl-media__sub">—</span>
                            @endif
                        </td>

                        <td>
                            <span class="sl-num d-block">
                                {{ \App\Support\LanguageSettings::formatDate($item->invoice_date ?: $item->created_at) }}
                            </span>
                            <span class="sl-media__sub">
                                <i class="feather icon-user"></i>
                                {{ __('sales_invoice.table.added_by', ['name' => $item->createdByUser->name ?? '—']) }}
                            </span>
                            @if ($item->employee)
                                <span class="sl-media__sub d-block">
                                    {{ __('sales_invoice.table.sales_person', ['name' => $item->employee->first_name ?? '—']) }}
                                </span>
                            @endif
                        </td>

                        <td>
                            <span class="sl-num text-danger">
                                {{ \App\Support\CurrencySettings::format($item->grand_total ?? $item->total) }}
                            </span>
                            @if (($item->tax_total ?? 0) > 0)
                                <span class="sl-media__sub d-block">
                                    {{ __('sales_invoice.tax') }}:
                                    {{ \App\Support\CurrencySettings::format($item->tax_total) }}
                                </span>
                            @endif
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        {{-- `data-bs-toggle` وحده: الصفحة تحمّل بوتستراب ٥
                                             (سطر ٦١ في `master`) وبوتستراب ٤ داخل
                                             `vendors.min.js` (سطر ٤٠١)، والوسمان معاً
                                             يجعلان الاثنين يعالجان النقرة نفسها. --}}
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="{{ __('sales_invoice.table.actions') }} {{ $item->code }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('ReturnIInvoices.show', $item->id) }}">
                                        <i class="feather icon-eye"></i> {{ __('sales_invoice.actions.show') }}
                                    </a>

                                    {{-- المطبوعات و«إرسال إلى العميل» لا مكان لهما هنا:
                                         كلاهما في شاشة العرض. --}}
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('sales::retend_invoice.partials.pagination', ['return' => $return])
@else
    @include('sales::components.empty-state', [
        'icon' => 'corner-up-left',
        'title' => __('sales_return.empty.title'),
        'hint' => __('sales_return.empty.hint'),
    ])
@endif
