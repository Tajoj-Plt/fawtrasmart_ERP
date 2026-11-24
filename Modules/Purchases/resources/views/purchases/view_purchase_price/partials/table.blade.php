{{--
    جدول «عروض أسعار الموردين» — بكلاسات `sl-*` (تصميم فواتير المبيعات)،
    بنفس بنية `ordersPurchase/partials/table.blade.php` حرفياً.

    `id="reportTable"` مقصود: طباعة/تصدير الشاشة (`report-tools.blade.php`)
    يقرآن هذا المعرّف تحديداً.
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
                <th>{{ __('purchase_cycle.fields.code') }}</th>
                <th>{{ __('purchase_cycle.fields.supplier') }}</th>
                <th>{{ __('purchase_cycle.fields.status') }}</th>
                <th class="text-center" data-num>{{ __('purchase_cycle.fields.items_count') }}</th>
                <th class="text-center" data-num>{{ __('purchase_cycle.fields.grand_total') }}</th>
                <th class="text-center">{{ __('purchase_cycle.fields.datetime') }}</th>
                <th>{{ __('branches.node_filter.column') }}</th>
                <th class="text-center" style="width: 70px;">{{ __('purchase_cycle.actions.options') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchaseQuotation as $quotation)
                <tr data-order-id="{{ $quotation->id }}">
                    <td class="text-center">
                        <input type="checkbox" class="po-check order-checkbox" name="ids[]"
                            value="{{ $quotation->id }}" id="order-{{ $quotation->id }}"
                            aria-label="{{ $quotation->code }}">
                    </td>

                    <td>
                        <div class="sl-media">
                            <div class="sl-media__body">
                                {{-- الرقمُ رابطٌ إلى الاستعراض — نفسُ وجهة أيقونة
                                     العين، فلا حاجة إلى فتح القائمة لرؤية المستند --}}
                                <a href="{{ route('ViewPurchasePrice.show', $quotation->id) }}" class="sl-media__title">
                                    {{ $quotation->code }}
                                </a>
                                <span class="sl-media__sub">
                                    {{ $quotation->creator->name ?? __('purchase_cycle.empty.unknown') }}
                                </span>
                            </div>
                        </div>
                    </td>

                    <td>
                        {{ $quotation->supplier->name ?? __('purchase_cycle.empty.unknown') }}
                        @if ($quotation->valid_days)
                            <br><small class="text-muted">
                                {{ __('purchase_cycle.fields.valid_days') }}:
                                {{ LanguageSettings::formatNumber($quotation->valid_days, 0) }}
                            </small>
                        @endif
                    </td>

                    <td>
                        @include('purchases::partials.status', ['status' => $quotation->status])
                    </td>

                    <td class="text-center" data-num>
                        {{ LanguageSettings::formatNumber($quotation->items_count ?? 0, 0) }}
                    </td>

                    <td class="text-center" data-num>
                        {{ CurrencySettings::format($quotation->grand_total ?? 0) }}
                    </td>

                    <td class="text-center" data-num>
                        <div>{{ LanguageSettings::formatDate($quotation->date) ?: '—' }}</div>
                        <small class="text-muted">
                            {{ $quotation->created_at?->timezone(LanguageSettings::timezone())?->format(LanguageSettings::timeFormat()) }}
                        </small>
                    </td>

                    {{-- «تابع لـ» — عقدةُ المستند بمسارها من الجذر --}}
                    <td>@include('partials.branch-node-path', ['branchId' => $quotation->branch_id])</td>

                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                id="dropdownMenuButton{{ $quotation->id }}" data-bs-toggle="dropdown"
                                aria-expanded="false" aria-label="{{ __('purchase_cycle.actions.options') }}">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end"
                                aria-labelledby="dropdownMenuButton{{ $quotation->id }}">
                                <a class="dropdown-item" href="{{ route('ViewPurchasePrice.show', $quotation->id) }}">
                                    <i class="fas fa-eye me-2 text-primary"></i>{{ __('purchase_cycle.actions.show') }}
                                </a>
                                @permission('purchases.price_quotations.edit')
                                    @if ($quotation->status === 'Under Review')
                                        <a class="dropdown-item" href="{{ route('ViewPurchasePrice.edit', $quotation->id) }}">
                                            <i class="fas fa-edit me-2 text-success"></i>{{ __('purchase_cycle.actions.edit') }}
                                        </a>
                                    @endif
                                @endpermission
                                @permission('purchases.price_quotations.duplicate')
                                    <a class="dropdown-item" href="{{ route('ViewPurchasePrice.duplicate', $quotation->id) }}">
                                        <i class="fas fa-copy me-2 text-secondary"></i>{{ __('purchase_cycle.actions.duplicate') }}
                                    </a>
                                @endpermission
                                @permission('purchases.price_quotations.export')
                                    <a class="dropdown-item"
                                        href="{{ route('ViewPurchasePrice.print', [$quotation->id, 'default']) }}"
                                        target="_blank" rel="noopener">
                                        <i class="fas fa-print me-2 text-info"></i>{{ __('purchase_cycle.actions.print_preview') }}
                                    </a>
                                @endpermission
                                @permission('purchases.price_quotations.delete')
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger po-delete-one" href="#"
                                        data-action="{{ route('ViewPurchasePrice.destroy', $quotation->id) }}"
                                        data-code="{{ $quotation->code }}">
                                        <i class="fas fa-trash me-2"></i>{{ __('purchase_cycle.actions.delete') }}
                                    </a>
                                @endpermission
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        <div class="sl-empty">
                            <span class="sl-empty__icon"><i class="fas fa-tags"></i></span>
                            <p class="sl-empty__title">
                                {{ request()->hasAny(['code', 'status', 'supplier_id']) ? __('purchase_cycle.empty.filtered') : __('purchase_cycle.empty.title') }}
                            </p>
                            @permission('purchases.price_quotations.create')
                                <a href="{{ route('ViewPurchasePrice.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>{{ __('purchase_cycle.actions.create') }}
                                </a>
                            @endpermission
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
