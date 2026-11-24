@extends('purchases::master')

@section('title')
    {{ __('purchase_cycle.documents.purchase_quotation') }} — {{ $purchaseQuotation->code }}
@stop

@section('css')
    {{-- ورقة الخطّ الزمني التي تُصمِّم .timeline و.timeline-item --}}
    <link rel="stylesheet" href="{{ asset('assets/css/logs.css') }}">
    @include('purchases::partials.style')
    @include('components.hint-tips')
@endsection

@section('content')
    @php
        use App\Support\CurrencySettings;
        use App\Support\LanguageSettings;

        $isReview = $purchaseQuotation->status === 'Under Review';
        $isApproved = $purchaseQuotation->status === 'approval';
        $isRejected = $purchaseQuotation->status === 'disagree';

        $items = $purchaseQuotation->items;

        // الإجماليات محفوظة في الجدول نفسه، فتُقرأ منه ولا يُعاد حسابها
        // حتى لا يختلف المعروض عمّا اعتُمد
        $totals = [
            'subtotal' => (float) ($purchaseQuotation->subtotal ?? 0),
            // `discount_amount` عمود لا يكتب إليه الكنترولر شيئاً قط (يبقى
            // 0.00 دوماً)، فلا يصلح مصدراً أول — `??` لا يتجاوز 0.00 لأنه
            // ليس null، فكان الخصم المعروض هنا يظهر صفراً دائماً رغم أن
            // total_discount المحسوب والمخزَّن فعلياً صحيح
            'discount' => (float) ($purchaseQuotation->total_discount ?? 0),
            'tax' => (float) ($purchaseQuotation->total_tax ?? 0),
            'grand_total' => (float) ($purchaseQuotation->grand_total ?? 0),
        ];

        $validUntil = ($purchaseQuotation->date && $purchaseQuotation->valid_days)
            ? \Illuminate\Support\Carbon::parse($purchaseQuotation->date)->addDays((int) $purchaseQuotation->valid_days)
            : null;
    @endphp

    <div class="po-page">
        @include('purchases::partials.head', [
            'title' => __('purchase_cycle.documents.purchase_quotation') . ' — ' . $purchaseQuotation->code,
            'crumbs' => [
                ['label' => __('purchase_cycle.sections.purchase_quotation'), 'url' => route('ViewPurchasePrice.index')],
                ['label' => $purchaseQuotation->code],
            ],
            'action' => view('purchases::partials.status', ['status' => $purchaseQuotation->status])->render(),
        ])

        @include('layouts.alerts.error')
        @include('layouts.alerts.success')

        <div class="po-card">
            <div class="po-card__body">
                <div class="po-actions-grid">
                    @permission('purchases.price_quotations.edit')
                        @if ($isReview)
                            <a href="{{ route('ViewPurchasePrice.edit', $purchaseQuotation->id) }}" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i><span>{{ __('purchase_cycle.actions.edit') }}</span>
                            </a>
                        @endif
                    @endpermission

                    @permission('purchases.price_quotations.duplicate')
                        <a href="{{ route('ViewPurchasePrice.duplicate', $purchaseQuotation->id) }}"
                            class="btn btn-outline-secondary">
                            <i class="fas fa-copy"></i><span>{{ __('purchase_cycle.actions.duplicate') }}</span>
                        </a>
                    @endpermission

                    @permission('purchases.price_quotations.export')
                        @include('purchases::partials.print-menu', [
                            'templates' => $documentTemplates ?? collect(),
                            'default' => $defaultTemplate ?? null,
                            'prefersPdf' => $prefersPdf ?? false,
                            'printRoute' => 'ViewPurchasePrice.print',
                            'pdfRoute' => 'ViewPurchasePrice.pdf',
                            'documentId' => $purchaseQuotation->id,
                        ])
                    @endpermission

                    @permission('purchases.price_quotations.approve_reject')
                        @if ($isReview)
                            <button type="button" class="btn btn-outline-success" id="approveBtn">
                                <i class="fas fa-check"></i><span>{{ __('purchase_orders.actions.approve') }}</span>
                            </button>
                            <button type="button" class="btn btn-outline-danger" id="rejectBtn">
                                <i class="fas fa-times"></i><span>{{ __('purchase_orders.actions.reject') }}</span>
                            </button>
                        @elseif ($isRejected)
                            <button type="button" class="btn btn-outline-secondary" id="undoRejectionBtn">
                                <i class="fas fa-undo-alt"></i><span>{{ __('purchase_orders.actions.undo_rejection') }}</span>
                            </button>
                        @endif
                    @endpermission

                    @permission('purchases.price_quotations.convert')
                        @if ($isApproved && ! $purchaseQuotation->converted_to_po)
                            <button type="button" class="btn btn-outline-primary" id="convertBtn">
                                <i class="fas fa-exchange-alt"></i><span>{{ __('purchase_cycle.actions.convert_to_order') }}</span>
                            </button>
                        @endif
                    @endpermission

                    @permission('purchases.price_quotations.delete')
                        <button type="button" class="btn btn-outline-danger" id="deleteBtn">
                            <i class="fas fa-trash-alt"></i><span>{{ __('purchase_cycle.actions.delete') }}</span>
                        </button>
                    @endpermission
                </div>
            </div>
        </div>

        <div class="po-card">
            <ul class="nav po-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#details" role="tab">
                        <i class="fas fa-info-circle me-1"></i>{{ __('purchase_orders.tabs.details') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#items" role="tab">
                        <i class="fas fa-boxes me-1"></i>{{ __('purchase_orders.tabs.items') }}
                        <span class="po-status po-status--quotation ms-1">
                            {{ LanguageSettings::formatNumber($items->count(), 0) }}
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#activity" role="tab">
                        <i class="fas fa-history me-1"></i>{{ __('purchase_orders.tabs.activity') }}
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane active" id="details" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.details', [
                            'details' => [
                                ['icon' => 'fa-hashtag', 'label' => __('purchase_cycle.fields.code'), 'value' => $purchaseQuotation->code],
                                ['icon' => 'fa-truck', 'label' => __('purchase_cycle.fields.supplier'), 'value' => $purchaseQuotation->supplier->name ?? null],
                                ['icon' => 'fa-calendar', 'label' => __('purchase_cycle.fields.date'), 'value' => LanguageSettings::formatDate($purchaseQuotation->date)],
                                ['icon' => 'fa-calendar-alt', 'label' => __('purchase_cycle.fields.due_date'), 'value' => LanguageSettings::formatDate($validUntil)],
                                ['icon' => 'fa-user', 'label' => __('purchase_cycle.fields.creator'), 'value' => $purchaseQuotation->creator->name ?? null],
                                ['icon' => 'fa-map-marker-alt', 'label' => __('purchase_cycle.fields.branch'), 'value' => \App\Models\Branch::pathLabelFor($purchaseQuotation->branch_id)],
                                ['icon' => 'fa-box', 'label' => __('purchase_cycle.fields.items_count'), 'value' => LanguageSettings::formatNumber($items->count(), 0)],
                                ['icon' => 'fa-coins', 'label' => __('purchase_cycle.fields.currency'), 'value' => CurrencySettings::name().' ('.CurrencySettings::symbol().')'],
                                ['icon' => 'fa-clock', 'label' => __('purchase_orders.fields.updated_at'), 'value' => LanguageSettings::formatDate($purchaseQuotation->updated_at, true)],
                            ],
                        ])

                        <hr>

                        <div class="po-detail__label">
                            <i class="fas fa-sticky-note"></i>{{ __('purchase_cycle.fields.notes') }}
                        </div>
                        <div class="po-detail__value">
                            {{ $purchaseQuotation->notes ?: __('purchase_orders.empty.no_notes') }}
                        </div>
                    </div>
                </div>

                <div class="tab-pane" id="items" role="tabpanel">
                    @include('purchases::partials.items-table', ['items' => $items])
                    @if ($items->isNotEmpty())
                        @include('purchases::partials.totals', ['totals' => $totals])
                    @endif
                </div>

                <div class="tab-pane" id="activity" role="tabpanel">
                    <div class="po-card__body">
                        @include('purchases::partials.activity', ['logs' => $logs])
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="approveForm" action="{{ route('ViewPurchasePrice.approve', $purchaseQuotation->id) }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="note">
    </form>
    <form id="rejectForm" action="{{ route('ViewPurchasePrice.reject', $purchaseQuotation->id) }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="note">
    </form>
    <form id="undoRejectionForm" action="{{ route('ViewPurchasePrice.undoRejection', $purchaseQuotation->id) }}" method="POST" class="d-none">
        @csrf
    </form>
    <form id="convertForm" action="{{ route('ViewPurchasePrice.convertToPurchaseOrder', $purchaseQuotation->id) }}" method="POST" class="d-none">
        @csrf
    </form>
    <form id="deleteForm" action="{{ route('ViewPurchasePrice.destroy', $purchaseQuotation->id) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endsection

@section('scripts')
    @php
        $poDialogs = [
            'approveTitle' => __('purchase_orders.confirm.approve_title'),
            'approveText' => __('purchase_orders.confirm.approve_text'),
            'approveNote' => __('purchase_orders.fields.approval_note'),
            'rejectTitle' => __('purchase_orders.confirm.reject_title'),
            'rejectText' => __('purchase_orders.confirm.reject_text'),
            'undoRejectionTitle' => __('purchase_orders.actions.undo_rejection'),
            'undoRejectionText' => __('purchase_orders.confirm.undo_rejection_text'),
            'convertTitle' => __('purchase_cycle.actions.convert_to_order'),
            'convertText' => __('purchase_cycle.confirm.convert_to_order_text'),
            'deleteTitle' => __('purchase_orders.confirm.delete_title'),
            'deleteText' => __('purchase_orders.confirm.delete_text', ['code' => $purchaseQuotation->code]),
            'yes' => __('purchase_orders.confirm.yes'),
            'yesDelete' => __('purchase_orders.confirm.yes_delete'),
            'no' => __('purchase_orders.confirm.no'),
        ];
    @endphp

    <script>
        window.purchaseShowConfig = @json($poDialogs);
    </script>
    <script src="{{ asset('assets/js/purchase-cycle-show.js') }}"></script>
@endsection
