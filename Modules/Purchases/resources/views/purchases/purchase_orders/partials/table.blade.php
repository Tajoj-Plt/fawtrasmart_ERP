{{--
    جدول «أوامر الشراء» — أصناف `sl-*` (تصميم فواتير المبيعات)، بلا خانات
    تحديد ولا حذف جماعي لعدم وجود مسار bulkDelete لهذا القسم.

    التواريخ من إعدادات اللغة والمبالغ من العملة الافتراضية بكسورها ورمزها.
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;
@endphp

<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th class="text-center" style="width: 60px;">#</th>
                <th>{{ __('purchase_cycle.fields.code') }}</th>
                <th>{{ __('purchase_cycle.fields.supplier') }}</th>
                <th>{{ __('branches.node_filter.column') }}</th>
                <th>{{ __('purchase_cycle.fields.status') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.items_count') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.grand_total') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.datetime') }}</th>
                <th class="text-center" style="width: 80px;">{{ __('purchase_cycle.actions.options') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchaseOrdersRequests as $order)
                <tr class="order-row" data-order-id="{{ $order->id }}">
                    <td class="text-center">{{ $order->id }}</td>

                    <td>
                        {{-- الرقمُ رابطٌ إلى الاستعراض — نفسُ وجهة أيقونة العين --}}
                        <a href="{{ route('PurchaseOrders.show', $order->id) }}">
                            <strong>{{ $order->code }}</strong>
                        </a>
                        <br><small class="text-muted">
                            {{ $order->creator->name ?? __('purchase_cycle.empty.unknown') }}
                        </small>
                    </td>

                    <td>{{ $order->supplier->name ?? __('purchase_cycle.empty.unknown') }}</td>

                    {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                    <td>@include('partials.branch-node-path', ['branchId' => $order->branch_id])</td>

                    <td>
                        @include('purchases::partials.status', ['status' => $order->status])
                    </td>

                    <td class="text-center num">
                        {{ LanguageSettings::formatNumber($order->items_count ?? 0, 0) }}
                    </td>

                    <td class="text-center num">
                        {{ CurrencySettings::format($order->grand_total ?? 0) }}
                    </td>

                    <td class="text-center num">
                        <div>{{ LanguageSettings::formatDate($order->invoice_date ?? $order->created_at) ?: '—' }}</div>
                        <small class="text-muted">
                            @if ($order->due_date)
                                {{ __('purchase_cycle.fields.due_date') }}:
                                {{ LanguageSettings::formatDate($order->due_date) }}
                            @endif
                        </small>
                    </td>

                    <td class="text-center">
                        <div class="btn-group">
                            <div class="dropdown">
                                <button class="btn bg-gradient-info fa fa-ellipsis-v mr-1 mb-1 btn-sm" type="button"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    aria-label="{{ __('purchase_cycle.actions.options') }}"></button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('PurchaseOrders.show', $order->id) }}">
                                        <i class="fas fa-eye me-2 text-primary"></i>{{ __('purchase_cycle.actions.show') }}
                                    </a>
                                    @if ($order->status === 'Under Review')
                                        <a class="dropdown-item" href="{{ route('PurchaseOrders.edit', $order->id) }}">
                                            <i class="fas fa-edit me-2 text-success"></i>{{ __('purchase_cycle.actions.edit') }}
                                        </a>
                                    @endif
                                    <a class="dropdown-item"
                                        href="{{ route('PurchaseOrders.print', [$order->id, 'default']) }}"
                                        target="_blank" rel="noopener">
                                        <i class="fas fa-print me-2 text-info"></i>{{ __('purchase_cycle.actions.print_preview') }}
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger po-delete-one" href="#"
                                        data-action="{{ route('PurchaseOrders.destroy', $order->id) }}"
                                        data-code="{{ $order->code }}">
                                        <i class="fas fa-trash me-2"></i>{{ __('purchase_cycle.actions.delete') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <i class="fas fa-clipboard-list" style="font-size: 48px; color: #ccc;"></i>
                        <p class="mt-2 text-muted">
                            {{ request()->hasAny(['number_invoice', 'status', 'employee_search']) ? __('purchase_cycle.empty.filtered') : __('purchase_cycle.empty.title') }}
                        </p>
                        <a href="{{ route('PurchaseOrders.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-1"></i>{{ __('purchase_cycle.actions.create') }}
                        </a>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
