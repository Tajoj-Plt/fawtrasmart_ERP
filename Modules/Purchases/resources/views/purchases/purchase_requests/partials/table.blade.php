{{--
    جدول عرض طلبات الشراء — أُعيد بناؤه بكلاسات `sl-*` (تصميم فواتير
    المبيعات) بدل `po-list-table`، مع إبقاء المنطق كما هو: Ajax عبر
    `purchase-cycle-list.js`، تحديد جماعي، حذف مفرد وجماعي.

    `id="reportTable"` مقصود: طباعة/تصدير الشاشة (`report-tools.blade.php`)
    يقرآن هذا المعرّف تحديداً.

    التواريخ والمبالغ تمرّ بإعدادات اللغة والعملة كما في بقية القسم.
--}}
@php
    use App\Support\CurrencySettings;
    use App\Support\LanguageSettings;
@endphp

<div class="table-responsive">
    <table class="table table-hover mb-0" id="reportTable">
        <thead>
            <tr>
                <th class="text-center" style="width: 40px;">
                    <input type="checkbox" id="select-all" class="po-check"
                        aria-label="{{ __('purchase_orders.bulk.select_all') }}">
                </th>
                <th>{{ __('purchase_orders.fields.code') }}</th>
                <th>{{ __('purchase_orders.fields.status') }}</th>
                <th>{{ __('purchase_orders.fields.title') }}</th>
                <th class="text-center" data-num>{{ __('purchase_orders.totals.grand_total') }}</th>
                <th class="text-center">{{ __('purchase_orders.activity.datetime') }}</th>
                <th>{{ __('branches.node_filter.column') }}</th>
                <th class="text-center" style="width: 70px;">{{ __('purchase_orders.actions.options') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchaseOrders as $order)
                <tr data-order-id="{{ $order->id }}">
                    <td class="text-center">
                        <input type="checkbox" class="po-check order-checkbox" name="ids[]"
                            value="{{ $order->id }}" id="order-{{ $order->id }}"
                            aria-label="{{ $order->code }}">
                    </td>

                    <td>
                        <div class="sl-media">
                            <div class="sl-media__body">
                                {{-- الرقمُ رابطٌ إلى الاستعراض — نفسُ وجهة أيقونة
                                     العين، فلا حاجة إلى فتح القائمة لرؤية المستند --}}
                                <a href="{{ route('PurchaseRequests.show', $order->id) }}" class="sl-media__title">
                                    {{ $order->code }}
                                </a>
                                <span class="sl-media__sub">
                                    {{ $order->creator->name ?? __('purchase_orders.empty.unknown') }}
                                </span>
                            </div>
                        </div>
                    </td>

                    <td>
                        @include('purchases::purchases.purchase_requests.partials.status', ['status' => $order->status])
                    </td>

                    <td>
                        {{ \Illuminate\Support\Str::limit(strip_tags($order->title), 60) }}
                        <br><small class="text-muted">
                            {{ __('purchase_orders.fields.items_count') }}:
                            {{ LanguageSettings::formatNumber($order->items_count ?? 0, 0) }}
                        </small>
                    </td>

                    <td class="text-center" data-num>
                        {{ CurrencySettings::format($order->items_sum_total ?? 0) }}
                    </td>

                    <td class="text-center" data-num>
                        <div>{{ LanguageSettings::formatDate($order->order_date) ?: '—' }}</div>
                        <small class="text-muted">
                            @if ($order->due_date)
                                {{ __('purchase_orders.fields.due_date') }}:
                                {{ LanguageSettings::formatDate($order->due_date) }}
                            @else
                                {{ $order->created_at?->timezone(LanguageSettings::timezone())?->format(LanguageSettings::timeFormat()) }}
                            @endif
                        </small>
                    </td>

                    {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                    <td>@include('partials.branch-node-path', ['branchId' => $order->branch_id])</td>

                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                id="dropdownMenuButton{{ $order->id }}" data-bs-toggle="dropdown"
                                aria-expanded="false" aria-label="{{ __('purchase_orders.actions.options') }}">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end"
                                aria-labelledby="dropdownMenuButton{{ $order->id }}">
                                <a class="dropdown-item" href="{{ route('PurchaseRequests.show', $order->id) }}">
                                    <i class="fas fa-eye me-2 text-primary"></i>{{ __('purchase_orders.actions.show') }}
                                </a>
                                @permission('purchases.orders.edit')
                                    @if ($order->status === 'Under Review')
                                        <a class="dropdown-item" href="{{ route('PurchaseRequests.edit', $order->id) }}">
                                            <i class="fas fa-edit me-2 text-success"></i>{{ __('purchase_orders.actions.edit') }}
                                        </a>
                                    @endif
                                @endpermission
                                @permission('purchases.orders.export')
                                    <a class="dropdown-item"
                                        href="{{ route('PurchaseRequests.print', [$order->id, 'default']) }}"
                                        target="_blank" rel="noopener">
                                        <i class="fas fa-print me-2 text-info"></i>{{ __('purchase_orders.actions.print_preview') }}
                                    </a>
                                @endpermission
                                @permission('purchases.orders.delete')
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger po-delete-one" href="#"
                                        data-action="{{ route('PurchaseRequests.destroy', $order->id) }}"
                                        data-code="{{ $order->code }}">
                                        <i class="fas fa-trash me-2"></i>{{ __('purchase_orders.actions.delete') }}
                                    </a>
                                @endpermission
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="sl-empty">
                            <span class="sl-empty__icon"><i class="fas fa-file-invoice"></i></span>
                            <p class="sl-empty__title">
                                {{ request()->hasAny(['code', 'status', 'title', 'employee_id']) ? __('purchase_orders.empty.filtered') : __('purchase_orders.empty.title') }}
                            </p>
                            @permission('purchases.orders.create')
                                <a href="{{ route('PurchaseRequests.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>{{ __('purchase_orders.actions.create') }}
                                </a>
                            @endpermission
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
